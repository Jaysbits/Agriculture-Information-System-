<?php
declare(strict_types=1);

/**
 * =====================================================================
 * MODEL — Planting  (table: planting)
 * =====================================================================
 * Database logic for planting records: filtered/sorted/paginated
 * listing (via the v_planting_detail view), lookup by id, dropdown
 * option lists, the harvest reads used by the detail page, validation
 * and create / update / delete. HTML lives in public/plantings/*.php.
 *
 * Generic all() / find() / create() / update() / delete() come from Model.
 */
final class Planting
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function all(array $filters = [], array $options = []): array
    {
        return $this->db->select('planting', '*', $filters, $options);
    }

    public function find(int $id): ?array
    {
        return $this->db->find('planting', $id, 'planting_id');
    }

    public function create(array $data): array
    {
        return $this->db->insert('planting', $data);
    }

    public function update(int $id, array $data): array
    {
        return $this->db->update('planting', $data, ['planting_id=eq.' . $id]);
    }

    public function delete(int $id): array
    {
        return $this->db->delete('planting', ['planting_id=eq.' . $id]);
    }

    public function count(array $filters = []): int
    {
        return count($this->db->select('planting', 'planting_id', $filters));
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

    public const SORTABLE = ['date', 'farm', 'crop', 'status', 'area', 'expected', 'harvested'];

    /** Planting detail row from the reporting view. */
    public function detail(int $id): ?array
    {
        return Database::one('SELECT * FROM v_planting_detail WHERE planting_id = :id', [':id' => $id]);
    }

    /** Planting option list for the harvest form. */
    public function options(): array
    {
        return Database::all(
            'SELECT planting_id, farm_name, farmer_name, crop_name, variety, planting_date, status_name
               FROM v_planting_detail
              ORDER BY planting_date DESC, planting_id DESC'
        );
    }

    public function statuses(): array
    {
        return $this->db->select('planting_status', '*', [], ['order' => 'status_id']);
    }

    /** Compact farm list for filters. */
    public function farmFilterOptions(): array
    {
        return Database::all(
            'SELECT fm.farm_id, fm.farm_name, fa.last_name FROM farm fm
               JOIN farmer fa ON fa.farmer_id = fm.farmer_id
              ORDER BY fa.last_name, fm.farm_name'
        );
    }

    /** Compact crop list for filters. */
    public function cropFilterOptions(): array
    {
        return $this->db->select('crop', 'crop_id, crop_name, variety', [], ['order' => 'crop_name, variety']);
    }

    /**
     * Filtered, sorted, paginated list for the plantings index view.
     *
     * @return array{rows: array<int,array<string,mixed>>, pager: array<string,mixed>}
     */
    public function listing(
        string $q,
        int $statusId,
        int $farmId,
        int $cropId,
        string $dateFrom,
        string $dateTo,
        string $sort,
        string $dir,
        int $page
    ): array {
        $where  = [];
        $params = [];

        if ($q !== '') {
            $where[] = '(v.farm_name ILIKE :q OR v.farmer_name ILIKE :q OR v.crop_name ILIKE :q OR COALESCE(v.variety, \'\') ILIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($statusId > 0) {
            $where[] = 'v.status_id = :status_id';
            $params[':status_id'] = $statusId;
        }
        if ($farmId > 0) {
            $where[] = 'v.farm_id = :farm_id';
            $params[':farm_id'] = $farmId;
        }
        if ($cropId > 0) {
            $where[] = 'v.crop_id = :crop_id';
            $params[':crop_id'] = $cropId;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $where[] = 'v.planting_date >= :date_from';
            $params[':date_from'] = $dateFrom;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $where[] = 'v.planting_date <= :date_to';
            $params[':date_to'] = $dateTo;
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $orderMap = [
            'date'     => 'v.planting_date',
            'farm'     => 'v.farm_name',
            'crop'     => 'v.crop_name',
            'status'   => 'v.status_name',
            'area'     => 'v.area_planted',
            'expected'   => 'v.expected_harvest_date',
            'harvested'  => 'harvested',
        ];
        $orderCols = implode(', ', array_map(static fn ($c) => trim($c) . ' ' . strtoupper($dir), explode(',', $orderMap[$sort] ?? $orderMap['date'])));
        $orderSql  = $orderCols . ', v.planting_id DESC';

        return $this->paginateQuery(
            'SELECT COUNT(*) FROM v_planting_detail v' . $whereSql,
            "SELECT v.*,
                    (SELECT COUNT(*) FROM harvest h WHERE h.planting_id = v.planting_id) AS harvest_count,
                    (SELECT COALESCE(SUM(h.quantity_kg), 0) FROM harvest h WHERE h.planting_id = v.planting_id) AS harvested
               FROM v_planting_detail v
               {$whereSql}
              ORDER BY {$orderSql}
              LIMIT :limit OFFSET :offset",
            $params,
            $page
        );
    }

    /** Harvest records for one planting (detail page table). */
    public function harvests(int $plantingId): array
    {
        return Database::all(
            'SELECT * FROM v_harvest_detail WHERE planting_id = :id ORDER BY harvest_date DESC, harvest_id DESC',
            [':id' => $plantingId]
        );
    }

    /** Harvest totals for one planting (detail page). */
    public function harvestTotals(int $plantingId): array
    {
        return Database::one(
            'SELECT COUNT(*) AS records, COALESCE(SUM(quantity_kg), 0) AS qty,
                    COALESCE(SUM(quantity_kg * selling_price_per_kg), 0) AS value
               FROM harvest WHERE planting_id = :id',
            [':id' => $plantingId]
        ) ?? [];
    }

    /**
     * Validate and persist a planting record.
     *
     * @return array{ok:bool, id:?int, errors: array<string,string>, dbError:?string}
     */
    public function save(array $input, int $id = 0): array
    {
        $v = new Validator($input);
        $v->exists('farm_id', 'farm', 'farm_id', 'Farm')
          ->exists('crop_id', 'crop', 'crop_id', 'Crop')
          ->exists('status_id', 'planting_status', 'status_id', 'Status')
          ->required('planting_date', 'Planting date')
          ->date('planting_date', 'Planting date')
          ->numeric('area_planted', 'Area planted', 0.01, 10000)
          ->numeric('seed_quantity', 'Seed quantity', 0, 1000000, true);

        if ($v->value('expected_harvest_date') !== '') {
            $v->date('expected_harvest_date', 'Expected harvest date', false)
              ->dateNotAfter('expected_harvest_date', 'planting_date', 'Expected harvest date', 'the planting date');
        }

        if ($v->value('planting_date') !== '' && strtotime($v->value('planting_date')) !== false) {
            $limit = date('Y-m-d', strtotime('+2 years'));
            if ($v->value('planting_date') > $limit) {
                $v->addError('planting_date', 'Planting date cannot be more than two years in the future.');
            }
            $expected = $v->value('expected_harvest_date');
            if ($expected !== '' && strtotime($expected) !== false
                && strtotime($expected) > strtotime($v->value('planting_date') . ' +5 years')) {
                $v->addError('expected_harvest_date', 'Expected harvest date is not realistic for this crop.');
            }
        }

        if ($v->passes() && $v->value('farm_id') !== '') {
            $farm = Database::one(
                'SELECT farm_name, area_hectares FROM farm WHERE farm_id = :id',
                [':id' => (int) $v->value('farm_id')]
            );
            if ($farm && (float) $v->value('area_planted') > (float) $farm['area_hectares']) {
                $v->addError('area_planted', sprintf(
                    'The planted area cannot be larger than the farm size (%s ha).',
                    fmt_num($farm['area_hectares'])
                ));
            }
        }

        if (!$v->passes()) {
            return ['ok' => false, 'id' => null, 'errors' => $v->errors(), 'dbError' => null];
        }

        $clean = [
            'farm_id'               => (int) $v->value('farm_id'),
            'crop_id'               => (int) $v->value('crop_id'),
            'status_id'             => (int) $v->value('status_id'),
            'planting_date'         => $v->value('planting_date'),
            'area_planted'          => $v->value('area_planted'),
            'seed_quantity'         => $v->value('seed_quantity') !== '' ? $v->value('seed_quantity') : 0,
            'expected_harvest_date' => $v->value('expected_harvest_date') !== '' ? $v->value('expected_harvest_date') : null,
        ];

        try {
            if ($id > 0) {
                $this->update($id, $clean);
                return ['ok' => true, 'id' => $id, 'errors' => [], 'dbError' => null];
            }

            $row = $this->create($clean);
            return ['ok' => true, 'id' => (int) ($row['planting_id'] ?? 0), 'errors' => [], 'dbError' => null];
        } catch (PDOException $e) {
            error_log('[planting save] ' . $e->getMessage());
            return ['ok' => false, 'id' => null, 'errors' => [], 'dbError' => friendly_db_error($e)];
        }
    }
}

