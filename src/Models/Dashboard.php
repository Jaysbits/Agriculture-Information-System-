<?php
declare(strict_types=1);

/**
 * =====================================================================
 * MODEL — Dashboard  (read-only analytics)
 * =====================================================================
 * Every query behind the dashboard (public/index.php) lives here:
 * the summary counters, the plantings-by-status breakdown, the monthly
 * harvest series and the "recent / upcoming / top" panels. The view
 * only formats and renders the rows.
 */
final class Dashboard
{
    /** Headline counters used by the summary cards. */
    public static function summary(): array
    {
        return Database::one(
            'SELECT
                (SELECT COUNT(*) FROM farmer)   AS farmers,
                (SELECT COUNT(*) FROM farm)     AS farms,
                (SELECT COUNT(*) FROM crop)     AS crops,
                (SELECT COUNT(*) FROM planting) AS plantings,
                (SELECT COUNT(*) FROM harvest)  AS harvests,
                (SELECT COALESCE(SUM(quantity_kg), 0) FROM harvest) AS total_qty,
                (SELECT COALESCE(SUM(quantity_kg * selling_price_per_kg), 0) FROM harvest) AS total_value,
                (SELECT COALESCE(SUM(area_hectares), 0) FROM farm) AS farm_area,
                (SELECT COUNT(*) FROM planting p JOIN planting_status s ON s.status_id = p.status_id
                  WHERE s.status_name IN (\'Planted\', \'Growing\', \'Ready for Harvest\')) AS active_plantings'
        ) ?? [];
    }

    /** Planting counts and area grouped by status. */
    public static function statusBreakdown(): array
    {
        return Database::all(
            'SELECT s.status_id, s.status_name, COUNT(p.planting_id) AS total, COALESCE(SUM(p.area_planted), 0) AS area
               FROM planting_status s
               LEFT JOIN planting p ON p.status_id = s.status_id
              GROUP BY s.status_id, s.status_name
              ORDER BY s.status_id'
        );
    }

    /** Harvest totals per month for the last six months (raw rows). */
    public static function monthlyHarvest(): array
    {
        return Database::all(
            "SELECT to_char(harvest_date, 'YYYY-MM') AS ym,
                    SUM(quantity_kg) AS qty,
                    SUM(quantity_kg * selling_price_per_kg) AS value
               FROM harvest
              WHERE harvest_date >= date_trunc('month', CURRENT_DATE) - INTERVAL '5 months'
              GROUP BY 1 ORDER BY 1"
        );
    }

    public static function recentPlantings(int $limit = 5): array
    {
        return Database::all(
            "SELECT * FROM v_planting_detail ORDER BY planting_date DESC, planting_id DESC LIMIT {$limit}"
        );
    }

    public static function recentHarvests(int $limit = 5): array
    {
        return Database::all(
            "SELECT * FROM v_harvest_detail ORDER BY harvest_date DESC, harvest_id DESC LIMIT {$limit}"
        );
    }

    /** Plantings expected to be harvested within the next 30 days. */
    public static function upcomingHarvests(int $limit = 5): array
    {
        return Database::all(
            "SELECT * FROM v_planting_detail
              WHERE expected_harvest_date IS NOT NULL
                AND expected_harvest_date BETWEEN CURRENT_DATE AND CURRENT_DATE + 30
                AND status_name NOT IN ('Harvested', 'Failed')
              ORDER BY expected_harvest_date LIMIT {$limit}"
        );
    }

    /** Top crops by harvested quantity. */
    public static function topCrops(int $limit = 5): array
    {
        return Database::all(
            "SELECT c.crop_name, c.variety, COUNT(h.harvest_id) AS harvests,
                    SUM(h.quantity_kg) AS qty,
                    SUM(h.quantity_kg * h.selling_price_per_kg) AS value
               FROM harvest h
               JOIN planting p ON p.planting_id = h.planting_id
               JOIN crop c ON c.crop_id = p.crop_id
              GROUP BY c.crop_id, c.crop_name, c.variety
              ORDER BY qty DESC LIMIT {$limit}"
        );
    }
}
