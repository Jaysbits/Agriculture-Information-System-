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
 *
 * Generic all() / find() / create() / update() / delete() come from Model.
 */
final class Farm
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function all(array $filters = [], array $options = []): array
    {
        return $this->db->select('farm', '*', $filters, $options);
    }

    public function find(int $id): ?array
    {
        return $this->db->find('farm', $id, 'farm_id');
    }

    public function create(array $data): array
    {
        return $this->db->insert('farm', $data);
    }

    public function update(int $id, array $data): array
    {
        return $this->db->update('farm', $data, ['farm_id=eq.' . $id]);
    }

    public function delete(int $id): array
    {
        return $this->db->delete('farm', ['farm_id=eq.' . $id]);
    }

    public function count(array $filters = []): int
    {
        return count($this->db->select('farm', 'farm_id', $filters));
    }

    protected function paginateQuery(string $countSql, string $rowsSql, array $params, int $page): array
    {
        $total = Database::count($countSql, $params);
        $pager = paginate($total, $page);
        $rows = Database::all($rowsSql, array_merge($params, [
            ':limit'  => $pager['limit'],
            ':offset' => $pager['offset'],
        ]));
        return ['rows' => $rows, 'pager' => $pager];
    }

    public const SORTABLE = ['name', 'farmer', 'area', 'irrigation', 'plantings', 'soil'];

    /** Farm detail row with related descriptions (detail page hero). */
    public function detail(int $id): ?array
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
    public function options(): array
    {
        return Database::all(
            'SELECT fm.farm_id, fm.farm_name, fm.area_hectares, fa.last_name, fa.first_name
               FROM farm fm JOIN farmer fa ON fa.farmer_id = fm.farmer_id
              ORDER BY fa.last_name, fm.farm_name'
        );
    }

    public function soilTypes(): array
    {
        return $this->db->select('soil_type', '*', [], ['order' => 'soil_type_name']);
    }

    public function irrigationTypes(): array
    {
        return $this->db->select('irrigation_type', '*', [], ['order' => 'irrigation_name']);
    }

    /** Total planted area on a farm (used by the edit form warning). */
    public function plantedArea(int $farmId): float
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
    public function listing(string $q, int $irrigationId, int $soilTypeId, string $sort, string $dir, int $page): array
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
            'soil'       => 'st.soil_type_name',
        ];
        $orderCols = implode(', ', array_map(static fn ($c) => trim($c) . ' ' . strtoupper($dir), explode(',', $orderMap[$sort] ?? $orderMap['name'])));
$orderSql  = $orderCols . ', fm.farm_id DESC';

        return $this->paginateQuery(
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
    public function plantings(int $farmId): array
    {
        return Database::all(
            'SELECT * FROM v_planting_detail WHERE farm_id = :id ORDER BY planting_date DESC, planting_id DESC',
            [':id' => $farmId]
        );
    }

    /** Aggregated statistics for one farm (detail page). */
    public function stats(int $farmId): array
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
    public function save(array $input, int $id = 0): array
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
                $this->update($id, $clean);
                return ['ok' => true, 'id' => $id, 'errors' => [], 'dbError' => null];
            }

            $row = $this->create($clean);
            return ['ok' => true, 'id' => (int) ($row['farm_id'] ?? 0), 'errors' => [], 'dbError' => null];
        } catch (PDOException $e) {
            error_log('[farm save] ' . $e->getMessage());
            return ['ok' => false, 'id' => null, 'errors' => [], 'dbError' => friendly_db_error($e)];
        }
    }
}

