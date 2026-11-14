<?php
declare(strict_types=1);

/**
 * =====================================================================
 * MODEL — Harvest  (table: harvest)
 * =====================================================================
 * Database logic for harvest transactions: filtered/sorted/paginated
 * listing (via v_harvest_detail) plus the aggregate totals shown above
 * the table, lookup by id, dropdown option lists, the reads used by
 * the detail page, validation and create / update / delete.
 * HTML lives in public/harvests/*.php.
 */
final class Harvest extends Model
{
    protected static string $table      = 'harvest';
    protected static string $primaryKey = 'harvest_id';

    public const SORTABLE = ['date', 'farm', 'crop', 'grade', 'qty', 'value'];

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM harvest WHERE harvest_id = :id', [':id' => $id]);
    }

    /** Harvest detail row from the reporting view. */
    public static function detail(int $id): ?array
    {
        return Database::one('SELECT * FROM v_harvest_detail WHERE harvest_id = :id', [':id' => $id]);
    }

    public static function grades(): array
    {
        return Database::all('SELECT * FROM quality_grade ORDER BY grade_id');
    }

    public static function remarks(): array
    {
        return Database::all('SELECT * FROM harvest_remark ORDER BY remark_id');
    }

    /** Compact farm list for filters. */
    public static function farmFilterOptions(): array
    {
        return Database::all(
            'SELECT fm.farm_id, fm.farm_name, fa.last_name FROM farm fm
               JOIN farmer fa ON fa.farmer_id = fm.farmer_id
              ORDER BY fa.last_name, fm.farm_name'
        );
    }

    /**
     * Filtered, sorted, paginated list for the harvests index view.
     * Also returns the aggregate totals for the matching rows.
     *
     * @return array{rows: array<int,array<string,mixed>>, pager: array<string,mixed>, aggregate: array<string,mixed>}
     */
    public static function listing(
        string $q,
        int $gradeId,
        int $remarkId,
        int $farmId,
        string $dateFrom,
        string $dateTo,
        string $sort,
        string $dir,
        int $page
    ): array {
        $where  = [];
        $params = [];

        if ($q !== '') {
            $where[] = '(v.farm_name ILIKE :q OR v.farmer_name ILIKE :q OR v.crop_name ILIKE :q OR v.grade_name ILIKE :q OR v.remark_description ILIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($gradeId > 0) {
            $where[] = 'v.grade_id = :grade_id';
            $params[':grade_id'] = $gradeId;
        }
        if ($remarkId > 0) {
            $where[] = 'v.remark_id = :remark_id';
            $params[':remark_id'] = $remarkId;
        }
        if ($farmId > 0) {
            $where[] = 'v.farm_id = :farm_id';
            $params[':farm_id'] = $farmId;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $where[] = 'v.harvest_date >= :date_from';
            $params[':date_from'] = $dateFrom;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $where[] = 'v.harvest_date <= :date_to';
            $params[':date_to'] = $dateTo;
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $aggregate = Database::one(
            "SELECT COUNT(*) AS records, COALESCE(SUM(v.quantity_kg), 0) AS qty,
                    COALESCE(SUM(v.quantity_kg * v.selling_price_per_kg), 0) AS value
               FROM v_harvest_detail v {$whereSql}",
            $params
        ) ?? [];

        $orderMap = [
            'date'  => 'v.harvest_date',
            'farm'  => 'v.farm_name',
            'crop'  => 'v.crop_name',
            'grade' => 'v.grade_name',
            'qty'   => 'v.quantity_kg',
            'value' => 'estimated_value',
        ];
        $orderSql = $orderMap[$sort] . ' ' . strtoupper($dir) . ', v.harvest_id DESC';

        $result = self::paginateQuery(
            "SELECT COUNT(*) FROM v_harvest_detail v {$whereSql}",
            "SELECT v.* FROM v_harvest_detail v
               {$whereSql}
              ORDER BY {$orderSql}
              LIMIT :limit OFFSET :offset",
            $params,
            $page
        );

        // The pager must reflect the filtered count from the aggregate query.
        $result['pager']     = paginate((int) ($aggregate['records'] ?? 0), $page);
        $result['aggregate'] = $aggregate;

        return $result;
    }

    /** Other harvest records from the same planting (detail page table). */
    public static function siblings(int $plantingId, int $excludeId): array
    {
        return Database::all(
            'SELECT harvest_id, harvest_date, quantity_kg, grade_name
               FROM v_harvest_detail
              WHERE planting_id = :pid AND harvest_id <> :id
              ORDER BY harvest_date DESC',
            [':pid' => $plantingId, ':id' => $excludeId]
        );
    }

    /** Harvest totals for a planting (detail page). */
    public static function plantingTotals(int $plantingId): array
    {
        return Database::one(
            'SELECT COUNT(*) AS records, COALESCE(SUM(quantity_kg), 0) AS qty,
                    COALESCE(SUM(quantity_kg * selling_price_per_kg), 0) AS value
               FROM harvest WHERE planting_id = :pid',
            [':pid' => $plantingId]
        ) ?? [];
    }

    /**
     * Validate and persist a harvest record.
     *
     * @return array{ok:bool, id:?int, errors: array<string,string>, dbError:?string}
     */
    public static function save(array $input, int $id = 0): array
    {
        $v = new Validator($input);
        $v->exists('planting_id', 'planting', 'planting_id', 'Planting record')
          ->exists('grade_id', 'quality_grade', 'grade_id', 'Quality grade')
          ->exists('remark_id', 'harvest_remark', 'remark_id', 'Harvest remark')
          ->required('harvest_date', 'Harvest date')
          ->date('harvest_date', 'Harvest date')
          ->notFuture('harvest_date', 'Harvest date')
          ->numeric('quantity_kg', 'Quantity', 0.01, 1000000)
          ->numeric('selling_price_per_kg', 'Selling price per kg', 0, 100000);

        if ($v->passes()) {
            $planting = Database::one(
                'SELECT p.planting_date, p.status_id, s.status_name, c.crop_name
                   FROM planting p
                   JOIN planting_status s ON s.status_id = p.status_id
                   JOIN crop c ON c.crop_id = p.crop_id
                  WHERE p.planting_id = :id',
                [':id' => (int) $v->value('planting_id')]
            );

            if ($planting && $v->value('harvest_date') < $planting['planting_date']) {
                $v->addError('harvest_date', 'Harvest date cannot be earlier than the planting date ('
                    . fmt_date($planting['planting_date']) . ').');
            }
            if ($planting && $planting['status_name'] === 'Failed') {
                $v->addError('planting_id', 'A harvest cannot be recorded for a planting that failed.');
            }
        }

        if (!$v->passes()) {
            return ['ok' => false, 'id' => null, 'errors' => $v->errors(), 'dbError' => null];
        }

        $clean = [
            'planting_id'          => (int) $v->value('planting_id'),
            'grade_id'             => (int) $v->value('grade_id'),
            'remark_id'            => (int) $v->value('remark_id'),
            'harvest_date'         => $v->value('harvest_date'),
            'quantity_kg'          => $v->value('quantity_kg'),
            'selling_price_per_kg' => $v->value('selling_price_per_kg'),
        ];

        try {
            if ($id > 0) {
                Database::run(
                    'UPDATE harvest
                        SET planting_id = :planting_id, grade_id = :grade_id, remark_id = :remark_id,
                            harvest_date = :harvest_date, quantity_kg = :quantity_kg,
                            selling_price_per_kg = :selling_price_per_kg
                      WHERE harvest_id = :id',
                    $clean + [':id' => $id]
                );
                return ['ok' => true, 'id' => $id, 'errors' => [], 'dbError' => null];
            }

            $newId = (int) Database::run(
                'INSERT INTO harvest (planting_id, grade_id, remark_id, harvest_date, quantity_kg, selling_price_per_kg)
                 VALUES (:planting_id, :grade_id, :remark_id, :harvest_date, :quantity_kg, :selling_price_per_kg)
                 RETURNING harvest_id',
                $clean
            )->fetchColumn();

            return ['ok' => true, 'id' => $newId, 'errors' => [], 'dbError' => null];
        } catch (PDOException $e) {
            error_log('[harvest save] ' . $e->getMessage());
            return ['ok' => false, 'id' => null, 'errors' => [], 'dbError' => friendly_db_error($e)];
        }
    }
}
