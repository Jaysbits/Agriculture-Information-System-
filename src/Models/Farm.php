<?php
declare(strict_types=1);

/**
 * =====================================================================
 * MODEL — Farm  (table: farm)
 * =====================================================================
 * Database logic for farm holdings: filtered/sorted/paginated listing,
 * lookup by id (with farmer, soil and irrigation joined), dropdown
 * option lists, the reads used by the detail page, validation and
 * create / update / delete. HTML lives in public/farms/*.php.
 */
final class Farm extends Model
{
    protected static string $table      = 'farm';
    protected static string $primaryKey = 'farm_id';

    public const SORTABLE = ['name', 'farmer', 'area', 'irrigation', 'plantings'];

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM farm WHERE farm_id = :id', [':id' => $id]);
    }

    /** Farm detail row with related descriptions (detail page hero). */
    public static function detail(int $id): ?array
    {
        return Database::one(
            'SELECT fm.*, fa.first_name, fa.last_name, fa.contact_number, fa.farmer_id,
                    st.soil_type_name, it.irrigation_name
               FROM farm fm
               JOIN farmer fa ON fa.farmer_id = fm.farmer_id
               JOIN soil_type st ON st.soil_type_id = fm.soil_type_id
               JOIN irrigation_type it ON it.irrigation_id = fm.irrigation_id
              WHERE fm.farm_id = :id',
            [':id' => $id]
        );
    }

    /** Farm option list (with owning farmer) for dropdowns. */
    public static function options(): array
    {
        return Database::all(
            'SELECT fm.farm_id, fm.farm_name, fm.area_hectares, fa.last_name, fa.first_name
               FROM farm fm JOIN farmer fa ON fa.farmer_id = fm.farmer_id
              ORDER BY fa.last_name, fm.farm_name'
        );
    }

    public static function soilTypes(): array
    {
        return Database::all('SELECT * FROM soil_type ORDER BY soil_type_name');
    }

    public static function irrigationTypes(): array
    {
        return Database::all('SELECT * FROM irrigation_type ORDER BY irrigation_name');
    }

    /** Total planted area on a farm (used by the edit form warning). */
    public static function plantedArea(int $farmId): float
    {
        return (float) Database::value(
            'SELECT COALESCE(SUM(area_planted), 0) FROM planting WHERE farm_id = :id',
            [':id' => $farmId],
            0
        );
    }

    /**
     * Filtered, sorted, paginated list for the farms index view.
     *
     * @return array{rows: array<int,array<string,mixed>>, pager: array<string,mixed>}
     */
    public static function listing(string $q, int $irrigationId, int $soilTypeId, string $sort, string $dir, int $page): array
    {
        $where  = [];
        $params = [];

        if ($q !== '') {
            $where[] = '(fm.farm_name ILIKE :q OR fm.location ILIKE :q
                         OR fa.first_name ILIKE :q OR fa.last_name ILIKE :q
                         OR (fa.first_name || \' \' || fa.last_name) ILIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($irrigationId > 0) {
            $where[] = 'fm.irrigation_id = :irrigation_id';
            $params[':irrigation_id'] = $irrigationId;
        }
        if ($soilTypeId > 0) {
            $where[] = 'fm.soil_type_id = :soil_type_id';
            $params[':soil_type_id'] = $soilTypeId;
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $orderMap = [
            'name'       => 'fm.farm_name',
            'farmer'     => 'fa.last_name, fa.first_name',
            'area'       => 'fm.area_hectares',
            'irrigation' => 'it.irrigation_name',
            'plantings'  => 'planting_count',
        ];
        $orderSql = $orderMap[$sort] . ' ' . strtoupper($dir) . ', fm.farm_id DESC';

        return self::paginateQuery(
            "SELECT COUNT(*) FROM farm fm JOIN farmer fa ON fa.farmer_id = fm.farmer_id {$whereSql}",
            "SELECT fm.farm_id, fm.farm_name, fm.location, fm.area_hectares,
                    fa.farmer_id, fa.first_name, fa.last_name,
                    st.soil_type_name, it.irrigation_name,
                    COUNT(p.planting_id) AS planting_count,
                    COALESCE(SUM(p.area_planted), 0) AS planted_area
               FROM farm fm
               JOIN farmer fa ON fa.farmer_id = fm.farmer_id
               JOIN soil_type st ON st.soil_type_id = fm.soil_type_id
               JOIN irrigation_type it ON it.irrigation_id = fm.irrigation_id
               LEFT JOIN planting p ON p.farm_id = fm.farm_id
               {$whereSql}
              GROUP BY fm.farm_id, fa.farmer_id, st.soil_type_name, it.irrigation_name
              ORDER BY {$orderSql}
              LIMIT :limit OFFSET :offset",
            $params,
            $page
        );
    }

    /** Planting records on a farm (detail page table). */
    public static function plantings(int $farmId): array
    {
        return Database::all(
            'SELECT * FROM v_planting_detail WHERE farm_id = :id ORDER BY planting_date DESC, planting_id DESC',
            [':id' => $farmId]
        );
    }

    /** Aggregated statistics for one farm (detail page). */
    public static function stats(int $farmId): array
    {
        return Database::one(
            'SELECT COUNT(*) AS plantings,
                    COALESCE(SUM(area_planted), 0) AS planted_area,
                    (SELECT COALESCE(SUM(h.quantity_kg), 0)
                       FROM harvest h JOIN planting p ON p.planting_id = h.planting_id
                      WHERE p.farm_id = :id) AS harvested,
                    (SELECT COALESCE(SUM(h.quantity_kg * h.selling_price_per_kg), 0)
                       FROM harvest h JOIN planting p ON p.planting_id = h.planting_id
                      WHERE p.farm_id = :id) AS value
               FROM planting WHERE farm_id = :id',
            [':id' => $farmId]
        ) ?? [];
    }

    /**
     * Validate and persist a farm record.
     *
     * @return array{ok:bool, id:?int, errors: array<string,string>, dbError:?string}
     */
    public static function save(array $input, int $id = 0): array
    {
        $v = new Validator($input);
        $v->exists('farmer_id', 'farmer', 'farmer_id', 'Farmer')
          ->exists('soil_type_id', 'soil_type', 'soil_type_id', 'Soil type')
          ->exists('irrigation_id', 'irrigation_type', 'irrigation_id', 'Irrigation type')
          ->required('farm_name', 'Farm name')
          ->max('farm_name', 150, 'Farm name')
          ->required('location', 'Location')
          ->max('location', 255, 'Location')
          ->numeric('area_hectares', 'Farm size', 0.01, 10000);

        if ($v->passes()) {
            $duplicate = Database::count(
                'SELECT COUNT(*) FROM farm
                  WHERE farmer_id = :farmer AND lower(farm_name) = lower(:name) AND farm_id <> :id',
                [':farmer' => (int) $v->value('farmer_id'), ':name' => $v->value('farm_name'), ':id' => $id]
            );
            if ($duplicate > 0) {
                $v->addError('farm_name', 'This farmer already has a farm with that name.');
            }
        }

        if (!$v->passes()) {
            return ['ok' => false, 'id' => null, 'errors' => $v->errors(), 'dbError' => null];
        }

        $clean = [
            'farmer_id'     => (int) $v->value('farmer_id'),
            'soil_type_id'  => (int) $v->value('soil_type_id'),
            'irrigation_id' => (int) $v->value('irrigation_id'),
            'farm_name'     => $v->value('farm_name'),
            'location'      => $v->value('location'),
            'area_hectares' => $v->value('area_hectares'),
        ];

        try {
            if ($id > 0) {
                Database::run(
                    'UPDATE farm
                        SET farmer_id = :farmer_id, soil_type_id = :soil_type_id, irrigation_id = :irrigation_id,
                            farm_name = :farm_name, location = :location, area_hectares = :area_hectares
                      WHERE farm_id = :id',
                    $clean + [':id' => $id]
                );
                return ['ok' => true, 'id' => $id, 'errors' => [], 'dbError' => null];
            }

            $newId = (int) Database::run(
                'INSERT INTO farm (farmer_id, soil_type_id, irrigation_id, farm_name, location, area_hectares)
                 VALUES (:farmer_id, :soil_type_id, :irrigation_id, :farm_name, :location, :area_hectares)
                 RETURNING farm_id',
                $clean
            )->fetchColumn();

            return ['ok' => true, 'id' => $newId, 'errors' => [], 'dbError' => null];
        } catch (PDOException $e) {
            error_log('[farm save] ' . $e->getMessage());
            return ['ok' => false, 'id' => null, 'errors' => [], 'dbError' => friendly_db_error($e)];
        }
    }
}
