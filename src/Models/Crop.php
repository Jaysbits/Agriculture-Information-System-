<?php
declare(strict_types=1);

/**
 * =====================================================================
 * MODEL — Crop  (table: crop)
 * =====================================================================
 * Database logic for the crop master list: filtered/sorted/paginated
 * listing, lookup by id (with category and season joined), dropdown
 * option lists, the reads used by the detail page, validation and
 * create / update / delete. HTML lives in public/crops/*.php.
 */
final class Crop extends Model
{
    protected static string $table      = 'crop';
    protected static string $primaryKey = 'crop_id';

    public const SORTABLE = ['name', 'category', 'season', 'period', 'plantings'];

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM crop WHERE crop_id = :id', [':id' => $id]);
    }

    /** Crop detail row with category and season names (detail page). */
    public static function detail(int $id): ?array
    {
        return Database::one(
            'SELECT c.*, cc.category_name, s.season_name
               FROM crop c
               JOIN crop_category cc ON cc.category_id = c.category_id
               LEFT JOIN season s ON s.season_id = c.season_id
              WHERE c.crop_id = :id',
            [':id' => $id]
        );
    }

    /** Crop option list for dropdowns. */
    public static function options(): array
    {
        return Database::all('SELECT crop_id, crop_name, variety, growing_period_days FROM crop ORDER BY crop_name, variety');
    }

    public static function categories(): array
    {
        return Database::all('SELECT * FROM crop_category ORDER BY category_name');
    }

    public static function seasons(): array
    {
        return Database::all('SELECT * FROM season ORDER BY season_name');
    }

    /**
     * Filtered, sorted, paginated list for the crops index view.
     *
     * @return array{rows: array<int,array<string,mixed>>, pager: array<string,mixed>}
     */
    public static function listing(string $q, int $categoryId, int $seasonId, string $sort, string $dir, int $page): array
    {
        $where  = [];
        $params = [];

        if ($q !== '') {
            $where[] = '(c.crop_name ILIKE :q OR COALESCE(c.variety, \'\') ILIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($categoryId > 0) {
            $where[] = 'c.category_id = :category_id';
            $params[':category_id'] = $categoryId;
        }
        if ($seasonId > 0) {
            $where[] = 'c.season_id = :season_id';
            $params[':season_id'] = $seasonId;
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $orderMap = [
            'name'      => 'c.crop_name, c.variety',
            'category'  => 'cc.category_name, c.crop_name',
            'season'    => 's.season_name, c.crop_name',
            'period'    => 'c.growing_period_days',
            'plantings' => 'planting_count',
        ];
        $orderSql = $orderMap[$sort] . ' ' . strtoupper($dir) . ', c.crop_id DESC';

        return self::paginateQuery(
            'SELECT COUNT(*) FROM crop c' . $whereSql,
            "SELECT c.crop_id, c.crop_name, c.variety, c.growing_period_days,
                    cc.category_name, s.season_name,
                    COUNT(DISTINCT p.planting_id) AS planting_count,
                    COALESCE(SUM(h.quantity_kg), 0) AS harvested
               FROM crop c
               JOIN crop_category cc ON cc.category_id = c.category_id
               LEFT JOIN season s ON s.season_id = c.season_id
               LEFT JOIN planting p ON p.crop_id = c.crop_id
               LEFT JOIN harvest h ON h.planting_id = p.planting_id
               {$whereSql}
              GROUP BY c.crop_id, cc.category_name, s.season_name
              ORDER BY {$orderSql}
              LIMIT :limit OFFSET :offset",
            $params,
            $page
        );
    }

    /** Planting records for one crop (detail page table). */
    public static function plantings(int $cropId): array
    {
        return Database::all(
            'SELECT * FROM v_planting_detail WHERE crop_id = :id ORDER BY planting_date DESC, planting_id DESC',
            [':id' => $cropId]
        );
    }

    /** Aggregated statistics for one crop (detail page). */
    public static function stats(int $cropId): array
    {
        return Database::one(
            'SELECT COUNT(DISTINCT p.planting_id) AS plantings,
                    COUNT(DISTINCT p.farm_id) AS farms,
                    (SELECT COUNT(*) FROM harvest h JOIN planting p2 ON p2.planting_id = h.planting_id WHERE p2.crop_id = :id) AS harvests,
                    (SELECT COALESCE(SUM(h.quantity_kg), 0) FROM harvest h JOIN planting p2 ON p2.planting_id = h.planting_id WHERE p2.crop_id = :id) AS qty,
                    (SELECT COALESCE(SUM(h.quantity_kg * h.selling_price_per_kg), 0) FROM harvest h JOIN planting p2 ON p2.planting_id = h.planting_id WHERE p2.crop_id = :id) AS value
               FROM planting p WHERE p.crop_id = :id',
            [':id' => $cropId]
        ) ?? [];
    }

    /**
     * Validate and persist a crop record.
     *
     * @return array{ok:bool, id:?int, errors: array<string,string>, dbError:?string}
     */
    public static function save(array $input, int $id = 0): array
    {
        $v = new Validator($input);
        $v->exists('category_id', 'crop_category', 'category_id', 'Category')
          ->required('crop_name', 'Crop name')
          ->max('crop_name', 150, 'Crop name')
          ->max('variety', 100, 'Variety')
          ->integer('growing_period_days', 'Growing period', 1, 3650);

        if ($v->value('season_id') !== '') {
            $v->exists('season_id', 'season', 'season_id', 'Season');
        }

        if ($v->passes()) {
            $duplicate = Database::count(
                'SELECT COUNT(*) FROM crop
                  WHERE lower(crop_name) = lower(:name)
                    AND lower(COALESCE(variety, \'\')) = lower(:variety)
                    AND crop_id <> :id',
                [':name' => $v->value('crop_name'), ':variety' => $v->value('variety'), ':id' => $id]
            );
            if ($duplicate > 0) {
                $v->addError('crop_name', 'This crop and variety combination already exists.');
            }
        }

        if (!$v->passes()) {
            return ['ok' => false, 'id' => null, 'errors' => $v->errors(), 'dbError' => null];
        }

        $clean = [
            'category_id'         => (int) $v->value('category_id'),
            'season_id'           => $v->value('season_id') !== '' ? (int) $v->value('season_id') : null,
            'crop_name'           => $v->value('crop_name'),
            'variety'             => $v->value('variety') !== '' ? $v->value('variety') : null,
            'growing_period_days' => (int) $v->value('growing_period_days'),
        ];

        try {
            if ($id > 0) {
                Database::run(
                    'UPDATE crop
                        SET category_id = :category_id, season_id = :season_id,
                            crop_name = :crop_name, variety = :variety,
                            growing_period_days = :growing_period_days
                      WHERE crop_id = :id',
                    $clean + [':id' => $id]
                );
                return ['ok' => true, 'id' => $id, 'errors' => [], 'dbError' => null];
            }

            $newId = (int) Database::run(
                'INSERT INTO crop (category_id, season_id, crop_name, variety, growing_period_days)
                 VALUES (:category_id, :season_id, :crop_name, :variety, :growing_period_days)
                 RETURNING crop_id',
                $clean
            )->fetchColumn();

            return ['ok' => true, 'id' => $newId, 'errors' => [], 'dbError' => null];
        } catch (PDOException $e) {
            error_log('[crop save] ' . $e->getMessage());
            return ['ok' => false, 'id' => null, 'errors' => [], 'dbError' => friendly_db_error($e)];
        }
    }
}
