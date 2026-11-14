<?php
declare(strict_types=1);

/**
 * =====================================================================
 * MODEL — Farmer  (table: farmer)
 * =====================================================================
 * All database logic for the farmer entity lives here: listing with
 * search / sort / pagination, single-record lookup, the read queries
 * used by the farmer detail page, validation and create / update /
 * delete. The matching HTML is in public/farmers/*.php.
 */
final class Farmer extends Model
{
    protected static string $table      = 'farmer';
    protected static string $primaryKey = 'farmer_id';

    /** Sort keys accepted by the index page. */
    public const SORTABLE = ['name', 'address', 'registered', 'farms'];

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM farmer WHERE farmer_id = :id', [':id' => $id]);
    }

    /** Farmer list for dropdowns. */
    public static function options(): array
    {
        return Database::all(
            'SELECT farmer_id, first_name, last_name, address FROM farmer ORDER BY last_name, first_name'
        );
    }

    /**
     * Filtered, sorted, paginated list for the farmers index view.
     *
     * @return array{rows: array<int,array<string,mixed>>, pager: array<string,mixed>}
     */
    public static function listing(string $q, string $address, string $sort, string $dir, int $page): array
    {
        $where  = [];
        $params = [];

        if ($q !== '') {
            $where[] = '(f.first_name ILIKE :q OR f.last_name ILIKE :q OR (f.first_name || \' \' || f.last_name) ILIKE :q
                         OR f.contact_number ILIKE :q OR f.address ILIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($address !== '') {
            $where[] = 'f.address ILIKE :address';
            $params[':address'] = '%' . $address . '%';
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $orderMap = [
            'name'       => 'f.last_name, f.first_name',
            'address'    => 'f.address',
            'registered' => 'f.date_registered',
            'farms'      => 'farm_count',
        ];
        $orderSql = $orderMap[$sort] . ' ' . strtoupper($dir) . ', f.farmer_id DESC';

        return self::paginateQuery(
            'SELECT COUNT(*) FROM farmer f' . $whereSql,
            "SELECT f.farmer_id, f.first_name, f.last_name, f.contact_number, f.address,
                    f.date_registered, f.created_at,
                    COUNT(fm.farm_id) AS farm_count,
                    COALESCE(SUM(fm.area_hectares), 0) AS total_area
               FROM farmer f
               LEFT JOIN farm fm ON fm.farmer_id = f.farmer_id
               {$whereSql}
              GROUP BY f.farmer_id
              ORDER BY {$orderSql}
              LIMIT :limit OFFSET :offset",
            $params,
            $page
        );
    }

    /** Farms belonging to one farmer (used by the detail page). */
    public static function farms(int $farmerId): array
    {
        return Database::all(
            'SELECT fm.farm_id, fm.farm_name, fm.location, fm.area_hectares,
                    st.soil_type_name, it.irrigation_name,
                    (SELECT COUNT(*) FROM planting p WHERE p.farm_id = fm.farm_id) AS plantings,
                    (SELECT COALESCE(SUM(h.quantity_kg), 0)
                       FROM harvest h JOIN planting p ON p.planting_id = h.planting_id
                      WHERE p.farm_id = fm.farm_id) AS harvested
               FROM farm fm
               JOIN soil_type st ON st.soil_type_id = fm.soil_type_id
               JOIN irrigation_type it ON it.irrigation_id = fm.irrigation_id
              WHERE fm.farmer_id = :id
              ORDER BY fm.farm_name',
            [':id' => $farmerId]
        );
    }

    /** Aggregated statistics for one farmer (used by the detail page). */
    public static function stats(int $farmerId): array
    {
        return Database::one(
            'SELECT COUNT(DISTINCT fm.farm_id) AS farms,
                    COALESCE(SUM(fm.area_hectares), 0) AS area,
                    (SELECT COUNT(*) FROM planting p JOIN farm f2 ON f2.farm_id = p.farm_id WHERE f2.farmer_id = :id) AS plantings,
                    (SELECT COALESCE(SUM(h.quantity_kg), 0)
                       FROM harvest h
                       JOIN planting p ON p.planting_id = h.planting_id
                       JOIN farm f2 ON f2.farm_id = p.farm_id
                      WHERE f2.farmer_id = :id) AS harvested,
                    (SELECT COALESCE(SUM(h.quantity_kg * h.selling_price_per_kg), 0)
                       FROM harvest h
                       JOIN planting p ON p.planting_id = h.planting_id
                       JOIN farm f2 ON f2.farm_id = p.farm_id
                      WHERE f2.farmer_id = :id) AS value
               FROM farm fm WHERE fm.farmer_id = :id',
            [':id' => $farmerId]
        ) ?? [];
    }

    /**
     * Validate and persist a farmer record.
     *
     * @param array<string,mixed> $input  Usually $_POST.
     * @param int                 $id     0 = create, >0 = update.
     * @return array{ok:bool, id:?int, errors: array<string,string>, dbError:?string}
     */
    public static function save(array $input, int $id = 0): array
    {
        $v = new Validator($input);
        $v->required('first_name', 'First name')
          ->max('first_name', 100, 'First name')
          ->regex('first_name', "/^[\p{L} .'-]+$/u", 'First name', 'may only contain letters, spaces, apostrophes and hyphens.')
          ->required('last_name', 'Last name')
          ->max('last_name', 100, 'Last name')
          ->regex('last_name', "/^[\p{L} .'-]+$/u", 'Last name', 'may only contain letters, spaces, apostrophes and hyphens.')
          ->required('contact_number', 'Contact number')
          ->max('contact_number', 30, 'Contact number')
          ->regex('contact_number', '/^[0-9+\-\s()]{7,30}$/', 'Contact number', 'must contain 7–30 digits and may include +, -, spaces or parentheses.')
          ->unique('contact_number', 'farmer', 'contact_number', 'Contact number', $id > 0 ? $id : null, 'farmer_id')
          ->required('address', 'Address')
          ->max('address', 255, 'Address')
          ->required('date_registered', 'Date registered')
          ->date('date_registered', 'Date registered')
          ->notFuture('date_registered', 'Date registered');

        if (!$v->passes()) {
            return ['ok' => false, 'id' => null, 'errors' => $v->errors(), 'dbError' => null];
        }

        $clean = $v->validated(['first_name', 'last_name', 'contact_number', 'address', 'date_registered']);

        try {
            if ($id > 0) {
                Database::run(
                    'UPDATE farmer
                        SET first_name = :first_name, last_name = :last_name,
                            contact_number = :contact_number, address = :address,
                            date_registered = :date_registered
                      WHERE farmer_id = :id',
                    $clean + [':id' => $id]
                );
                return ['ok' => true, 'id' => $id, 'errors' => [], 'dbError' => null];
            }

            $newId = (int) Database::run(
                'INSERT INTO farmer (first_name, last_name, contact_number, address, date_registered)
                 VALUES (:first_name, :last_name, :contact_number, :address, :date_registered)
                 RETURNING farmer_id',
                $clean
            )->fetchColumn();

            return ['ok' => true, 'id' => $newId, 'errors' => [], 'dbError' => null];
        } catch (PDOException $e) {
            error_log('[farmer save] ' . $e->getMessage());
            return ['ok' => false, 'id' => null, 'errors' => [], 'dbError' => friendly_db_error($e)];
        }
    }
}
