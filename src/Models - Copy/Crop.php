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
 *
 * The generic all() / find() / create() / update() / delete() / count()
 * methods come from the shared Model base class.
 */
final class Crop
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function all(array $filters = [], array $options = []): array
    {
        return $this->db->select('crop', '*', $filters, $options);
    }

    public function find(int $id): ?array
    {
        return $this->db->find('crop', $id, 'crop_id');
    }

    public function create(array $data): array
    {
        return $this->db->insert('crop', $data);
    }

    public function update(int $id, array $data): array
    {
        return $this->db->update('crop', $data, ['crop_id=eq.' . $id]);
    }

    public function delete(int $id): array
    {
        return $this->db->delete('crop', ['crop_id=eq.' . $id]);
    }

    public function count(array $filters = []): int
    {
        return count($this->db->select('crop', 'crop_id', $filters));
    }

    public const SORTABLE = ['name', 'category', 'season', 'period', 'plantings', 'harvested'];

    /** Free-text search over name/variety, with optional category/season. */
    public function search(string $term, int $categoryId = 0, int $seasonId = 0): array
    {
        $filters = [];
        foreach (preg_split('/\s+/', trim($term)) ?: [] as $word) {
            // Commas and parentheses would break the or=(...) filter syntax.
            $word = trim(str_replace([',', '(', ')'], ' ', $word));
            if ($word === '') {
                continue;
            }
            $filters[] = 'or=(crop_name.ilike.%' . $word . '%,variety.ilike.%' . $word . '%)';
        }
        if ($categoryId > 0) {
            $filters[] = 'category_id=eq.' . $categoryId;
        }
        if ($seasonId > 0) {
            $filters[] = 'season_id=eq.' . $seasonId;
        }

        return $this->all($filters, ['order' => 'crop_name, variety']);
    }

    /* ---------------------------------------------------------------
     * Dropdown option lists
     * ------------------------------------------------------------- */

    public function categories(): array
    {
        return $this->db->select('crop_category', '*', [], ['order' => 'category_name']);
    }

    public function seasons(): array
    {
        return $this->db->select('season', '*', [], ['order' => 'season_name']);
    }

    public function options(): array
    {
        return $this->db->select(
            'crop',
            'crop_id, crop_name, variety, growing_period_days',
            [],
            ['order' => 'crop_name, variety']
        );
    }

    /* ---------------------------------------------------------------
     * Crop-specific reads (need joins / aggregates, so they use SQL)
     * ------------------------------------------------------------- */

    /** Crop detail row with category and season names (detail page). */
    public function detail(int $id): ?array
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

    /**
     * Filtered, sorted, paginated list for the crops index view.
     *
     * @return array{rows: array<int,array<string,mixed>>, pager: array<string,mixed>}
     */
    public function listing(string $q, int $categoryId, int $seasonId, string $sort, string $dir, int $page): array
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
            'harvested' => 'harvested',
        ];
        $direction = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
        $orderCols = implode(', ', array_map(static fn ($c) => trim($c) . ' ' . $direction, explode(',', $orderMap[$sort] ?? $orderMap['name'])));
        $orderSql  = $orderCols . ', c.crop_id DESC';

        $total = Database::count('SELECT COUNT(*) FROM crop c' . $whereSql, $params);
        $pager = paginate($total, $page);

        $rows = Database::all(
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
            $params + [':limit' => $pager['limit'], ':offset' => $pager['offset']]
        );

        return ['rows' => $rows, 'pager' => $pager];
    }

    /** Planting records for one crop (detail page table). */
    public function plantings(int $cropId): array
    {
        return Database::all(
            'SELECT * FROM v_planting_detail WHERE crop_id = :id ORDER BY planting_date DESC, planting_id DESC',
            [':id' => $cropId]
        );
    }

    /** Aggregated statistics for one crop (detail page). */
    public function stats(int $cropId): array
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

    /* ---------------------------------------------------------------
     * Validation + save
     * ------------------------------------------------------------- */

    /**
     * Validate and persist a crop record.
     *
     * @return array{ok:bool, id:?int, errors: array<string,string>, dbError:?string}
     */
    public function save(array $input, int $id = 0): array
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
                $this->update($id, $clean);
                return ['ok' => true, 'id' => $id, 'errors' => [], 'dbError' => null];
            }

            $row = $this->create($clean);
            return ['ok' => true, 'id' => (int) ($row['crop_id'] ?? 0), 'errors' => [], 'dbError' => null];
        } catch (PDOException $e) {
            error_log('[crop save] ' . $e->getMessage());
            return ['ok' => false, 'id' => null, 'errors' => [], 'dbError' => friendly_db_error($e)];
        }
    }
}
