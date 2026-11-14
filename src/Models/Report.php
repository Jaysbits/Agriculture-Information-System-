<?php
declare(strict_types=1);

/**
 * =====================================================================
 * MODEL — Report  (read-only reporting queries)
 * =====================================================================
 * All SQL behind public/reports.php: the six reports, each with an
 * optional date range and filters. The page decides how to present the
 * rows (summary cards, tables, CSV, print); this class only fetches
 * them.
 *
 * Used as an object:
 *
 *     $report = new Report();
 *     $report->farmers($from, $to);
 */
final class Report
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? new Database();
    }

    public function farmers(string $from, string $to): array
    {
        return Database::all(
            "SELECT fa.farmer_id,
                    fa.last_name || ', ' || fa.first_name AS farmer,
                    fa.contact_number, fa.address, fa.date_registered,
                    (SELECT COUNT(*) FROM farm fm WHERE fm.farmer_id = fa.farmer_id) AS farms,
                    (SELECT COALESCE(SUM(fm.area_hectares), 0) FROM farm fm WHERE fm.farmer_id = fa.farmer_id) AS area,
                    (SELECT COUNT(*) FROM planting p JOIN farm fm ON fm.farm_id = p.farm_id
                      WHERE fm.farmer_id = fa.farmer_id) AS plantings,
                    (SELECT COALESCE(SUM(h.quantity_kg), 0)
                       FROM harvest h JOIN planting p ON p.planting_id = h.planting_id
                       JOIN farm fm ON fm.farm_id = p.farm_id
                      WHERE fm.farmer_id = fa.farmer_id) AS harvested
               FROM farmer fa
              WHERE fa.date_registered BETWEEN :from AND :to
              ORDER BY fa.last_name, fa.first_name",
            [':from' => $from, ':to' => $to]
        );
    }

    public function farmCrops(string $from, string $to, int $farmId = 0): array
    {
        $where  = ['p.planting_date BETWEEN :from AND :to'];
        $params = [':from' => $from, ':to' => $to];
        if ($farmId > 0) {
            $where[] = 'p.farm_id = :farm_id';
            $params[':farm_id'] = $farmId;
        }

        return Database::all(
            'SELECT fm.farm_name, fa.last_name || \', \' || fa.first_name AS farmer,
                    c.crop_name, c.variety,
                    COUNT(p.planting_id) AS plantings,
                    SUM(p.area_planted) AS area,
                    COALESCE(SUM(h.qty), 0) AS harvested
               FROM planting p
               JOIN farm fm ON fm.farm_id = p.farm_id
               JOIN farmer fa ON fa.farmer_id = fm.farmer_id
               JOIN crop c ON c.crop_id = p.crop_id
               LEFT JOIN (SELECT planting_id, SUM(quantity_kg) AS qty FROM harvest GROUP BY planting_id) h
                      ON h.planting_id = p.planting_id
              WHERE ' . implode(' AND ', $where) . '
              GROUP BY fm.farm_id, fm.farm_name, fa.farmer_id, c.crop_id, c.crop_name, c.variety
              ORDER BY fm.farm_name, c.crop_name',
            $params
        );
    }

    /** Summary rows: plantings grouped by status. */
    public function plantingStatusGrouped(string $from, string $to, int $statusId = 0): array
    {
        [$whereSql, $params] = $this->plantingStatusFilter($from, $to, $statusId);

        return Database::all(
            "SELECT v.status_name, COUNT(*) AS plantings, SUM(v.area_planted) AS area,
                    COUNT(DISTINCT v.farm_id) AS farms
               FROM v_planting_detail v
              WHERE {$whereSql}
              GROUP BY v.status_id, v.status_name
              ORDER BY v.status_id",
            $params
        );
    }

    /** Detail rows: individual planting records. */
    public function plantingStatusRows(string $from, string $to, int $statusId = 0): array
    {
        [$whereSql, $params] = $this->plantingStatusFilter($from, $to, $statusId);

        return Database::all(
            "SELECT v.planting_id, v.planting_date, v.farm_name, v.farmer_name, v.crop_name,
                    v.status_name, v.area_planted, v.expected_harvest_date
               FROM v_planting_detail v
              WHERE {$whereSql}
              ORDER BY v.planting_date DESC, v.planting_id DESC",
            $params
        );
    }

    public function harvests(string $from, string $to, int $gradeId = 0, int $farmId = 0): array
    {
        $where  = ['v.harvest_date BETWEEN :from AND :to'];
        $params = [':from' => $from, ':to' => $to];
        if ($gradeId > 0) {
            $where[] = 'v.grade_id = :grade_id';
            $params[':grade_id'] = $gradeId;
        }
        if ($farmId > 0) {
            $where[] = 'v.farm_id = :farm_id';
            $params[':farm_id'] = $farmId;
        }

        return Database::all(
            'SELECT v.harvest_id, v.harvest_date, v.farm_name, v.farmer_name, v.crop_name,
                    v.grade_name, v.remark_description, v.quantity_kg, v.selling_price_per_kg,
                    v.quantity_kg * v.selling_price_per_kg AS estimated_value
               FROM v_harvest_detail v
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY v.harvest_date DESC, v.harvest_id DESC',
            $params
        );
    }

    public function harvestTotalsByCrop(string $from, string $to, int $farmId = 0): array
    {
        [$whereSql, $params] = $this->harvestFilter($from, $to, $farmId);

        return Database::all(
            "SELECT v.crop_name, COUNT(*) AS records, SUM(v.quantity_kg) AS qty
               FROM v_harvest_detail v
              WHERE {$whereSql}
              GROUP BY v.crop_id, v.crop_name
              ORDER BY qty DESC",
            $params
        );
    }

    public function harvestTotalsByFarm(string $from, string $to, int $farmId = 0): array
    {
        [$whereSql, $params] = $this->harvestFilter($from, $to, $farmId);

        return Database::all(
            "SELECT v.farm_name, v.farmer_name, COUNT(*) AS records, SUM(v.quantity_kg) AS qty
               FROM v_harvest_detail v
              WHERE {$whereSql}
              GROUP BY v.farm_id, v.farm_name, v.farmer_name
              ORDER BY qty DESC",
            $params
        );
    }

    public function harvestValueByCrop(string $from, string $to, int $farmId = 0): array
    {
        [$whereSql, $params] = $this->harvestFilter($from, $to, $farmId);

        return Database::all(
            "SELECT v.crop_name, SUM(v.quantity_kg) AS qty,
                    CASE WHEN SUM(v.quantity_kg) > 0
                         THEN SUM(v.quantity_kg * v.selling_price_per_kg) / SUM(v.quantity_kg)
                         ELSE 0 END AS avg_price,
                    SUM(v.quantity_kg * v.selling_price_per_kg) AS value
               FROM v_harvest_detail v
              WHERE {$whereSql}
              GROUP BY v.crop_id, v.crop_name
              ORDER BY value DESC",
            $params
        );
    }

    public function harvestValueByFarm(string $from, string $to, int $farmId = 0): array
    {
        [$whereSql, $params] = $this->harvestFilter($from, $to, $farmId);

        return Database::all(
            "SELECT v.farm_name, v.farmer_name, SUM(v.quantity_kg) AS qty,
                    SUM(v.quantity_kg * v.selling_price_per_kg) AS value
               FROM v_harvest_detail v
              WHERE {$whereSql}
              GROUP BY v.farm_id, v.farm_name, v.farmer_name
              ORDER BY value DESC",
            $params
        );
    }

    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    private function plantingStatusFilter(string $from, string $to, int $statusId): array
    {
        $where  = ['v.planting_date BETWEEN :from AND :to'];
        $params = [':from' => $from, ':to' => $to];
        if ($statusId > 0) {
            $where[] = 'v.status_id = :status_id';
            $params[':status_id'] = $statusId;
        }
        return [implode(' AND ', $where), $params];
    }

    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    private function harvestFilter(string $from, string $to, int $farmId): array
    {
        $where  = ['v.harvest_date BETWEEN :from AND :to'];
        $params = [':from' => $from, ':to' => $to];
        if ($farmId > 0) {
            $where[] = 'v.farm_id = :farm_id';
            $params[':farm_id'] = $farmId;
        }
        return [implode(' AND ', $where), $params];
    }
}
