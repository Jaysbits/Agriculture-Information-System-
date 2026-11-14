<?php
declare(strict_types=1);

/**
 * VIEW — Reports.
 * All report SQL lives in the Report model (src/Models/Report.php);
 * this page handles the date range / filters and renders the tables,
 * summary cards, CSV export and print layout.
 */

require_once dirname(__DIR__) . '/config/config.php';
Auth::requireLogin();

$reports = [
    'farmers'         => ['label' => 'Farmers and their farms', 'icon' => 'people'],
    'farm_crops'      => ['label' => 'Crops planted per farm', 'icon' => 'diagram-3'],
    'planting_status' => ['label' => 'Planting records by status', 'icon' => 'kanban'],
    'harvests'        => ['label' => 'Harvest records', 'icon' => 'basket3'],
    'harvest_totals'  => ['label' => 'Total harvested quantity', 'icon' => 'boxes'],
    'harvest_value'   => ['label' => 'Estimated harvest value', 'icon' => 'cash-coin'],
];

$report = get('report', 'farmers');
if (!isset($reports[$report])) {
    $report = 'farmers';
}

$validDate = static fn (string $value): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
$from = get('from', date('Y-01-01'));
$to   = get('to', date('Y-m-d'));
if (!$validDate($from)) {
    $from = date('Y-01-01');
}
if (!$validDate($to)) {
    $to = date('Y-m-d');
}
if ($from > $to) {
    [$from, $to] = [$to, $from];
}

$farm_id   = int_get('farm_id', 0);
$status_id = int_get('status_id', 0);
$grade_id  = int_get('grade_id', 0);

$plantingModel = new Planting(new Database());
$harvestModel  = new Harvest(new Database());
$reportModel   = new Report(new Database());

$farms    = $plantingModel->farmFilterOptions();
$statuses = $plantingModel->statuses();
$grades   = $harvestModel->grades();

/* --------------------------------------------------------------------
 * Build the report: summary cards + one or more tables.
 * ------------------------------------------------------------------ */
$summary = [];
$tables  = [];

switch ($report) {
    case 'farmers':
        $rows = $reportModel->farmers($from, $to);

        $summary = [
            ['label' => 'Farmers in range', 'value' => number_format(count($rows))],
            ['label' => 'Total farms', 'value' => number_format(array_sum(array_map(static fn ($r) => (int) $r['farms'], $rows)))],
            ['label' => 'Total farm area', 'value' => fmt_ha(array_sum(array_map(static fn ($r) => (float) $r['area'], $rows)))],
            ['label' => 'Total harvested', 'value' => fmt_kg(array_sum(array_map(static fn ($r) => (float) $r['harvested'], $rows)))],
        ];
        $tables[] = [
            'title'   => 'Farmers registered between ' . fmt_date($from) . ' and ' . fmt_date($to),
            'columns' => ['Farmer' => 'farmer', 'Contact' => 'contact_number', 'Address' => 'address',
                          'Registered' => 'date_registered', 'Farms' => 'farms', 'Farm area (ha)' => 'area',
                          'Plantings' => 'plantings', 'Harvested (kg)' => 'harvested'],
            'rows'    => $rows,
            'format'  => ['date_registered' => 'date', 'area' => 'number', 'harvested' => 'number'],
        ];
        break;

    case 'farm_crops':
        $rows = $reportModel->farmCrops($from, $to, $farm_id);

        $summary = [
            ['label' => 'Farm / crop combinations', 'value' => number_format(count($rows))],
            ['label' => 'Plantings', 'value' => number_format(array_sum(array_map(static fn ($r) => (int) $r['plantings'], $rows)))],
            ['label' => 'Planted area', 'value' => fmt_ha(array_sum(array_map(static fn ($r) => (float) $r['area'], $rows)))],
            ['label' => 'Harvested', 'value' => fmt_kg(array_sum(array_map(static fn ($r) => (float) $r['harvested'], $rows)))],
        ];
        $tables[] = [
            'title'   => 'Crops planted per farm (' . fmt_date($from) . ' – ' . fmt_date($to) . ')',
            'columns' => ['Farm' => 'farm_name', 'Farmer' => 'farmer', 'Crop' => 'crop_name', 'Variety' => 'variety',
                          'Plantings' => 'plantings', 'Area planted (ha)' => 'area', 'Harvested (kg)' => 'harvested'],
            'rows'    => $rows,
            'format'  => ['area' => 'number', 'harvested' => 'number'],
        ];
        break;

    case 'planting_status':
        $grouped = $reportModel->plantingStatusGrouped($from, $to, $status_id);
        $rows    = $reportModel->plantingStatusRows($from, $to, $status_id);

        $summary = [
            ['label' => 'Planting records', 'value' => number_format(count($rows))],
            ['label' => 'Status groups', 'value' => number_format(count($grouped))],
            ['label' => 'Planted area', 'value' => fmt_ha(array_sum(array_map(static fn ($r) => (float) $r['area'], $grouped)))],
            ['label' => 'Farms involved', 'value' => number_format(array_sum(array_map(static fn ($r) => (int) $r['farms'], $grouped)))],
        ];
        $tables[] = [
            'title'   => 'Summary by status',
            'columns' => ['Status' => 'status_name', 'Plantings' => 'plantings', 'Farms' => 'farms', 'Area planted (ha)' => 'area'],
            'rows'    => $grouped,
            'format'  => ['area' => 'number'],
            'badge'   => 'status_name',
        ];
        $tables[] = [
            'title'   => 'Planting records (' . fmt_date($from) . ' – ' . fmt_date($to) . ')',
            'columns' => ['Planted' => 'planting_date', 'Farm' => 'farm_name', 'Farmer' => 'farmer_name',
                          'Crop' => 'crop_name', 'Status' => 'status_name', 'Area (ha)' => 'area_planted',
                          'Expected harvest' => 'expected_harvest_date'],
            'rows'    => $rows,
            'format'  => ['planting_date' => 'date', 'expected_harvest_date' => 'date', 'area_planted' => 'number'],
            'badge'   => 'status_name',
            'link'    => ['planting_id', 'plantings/view.php?id='],
        ];
        break;

    case 'harvests':
        $rows = $reportModel->harvests($from, $to, $grade_id, $farm_id);

        $summary = [
            ['label' => 'Harvest records', 'value' => number_format(count($rows))],
            ['label' => 'Total quantity', 'value' => fmt_kg(array_sum(array_map(static fn ($r) => (float) $r['quantity_kg'], $rows)))],
            ['label' => 'Average price', 'value' => fmt_money(
                array_sum(array_map(static fn ($r) => (float) $r['quantity_kg'], $rows)) > 0
                    ? array_sum(array_map(static fn ($r) => (float) $r['estimated_value'], $rows))
                      / array_sum(array_map(static fn ($r) => (float) $r['quantity_kg'], $rows))
                    : 0
            )],
            ['label' => 'Estimated value', 'value' => fmt_money(array_sum(array_map(static fn ($r) => (float) $r['estimated_value'], $rows)))],
        ];
        $tables[] = [
            'title'   => 'Harvest records (' . fmt_date($from) . ' – ' . fmt_date($to) . ')',
            'columns' => ['Date' => 'harvest_date', 'Farm' => 'farm_name', 'Farmer' => 'farmer_name',
                          'Crop' => 'crop_name', 'Grade' => 'grade_name', 'Remark' => 'remark_description',
                          'Quantity (kg)' => 'quantity_kg', 'Price / kg' => 'selling_price_per_kg',
                          'Value' => 'estimated_value'],
            'rows'    => $rows,
            'format'  => ['harvest_date' => 'date', 'quantity_kg' => 'number', 'selling_price_per_kg' => 'money', 'estimated_value' => 'money'],
            'link'    => ['harvest_id', 'harvests/view.php?id='],
        ];
        break;

    case 'harvest_totals':
        $byCrop = $reportModel->harvestTotalsByCrop($from, $to, $farm_id);
        $byFarm = $reportModel->harvestTotalsByFarm($from, $to, $farm_id);
        $grand  = array_sum(array_map(static fn ($r) => (float) $r['qty'], $byCrop));

        $summary = [
            ['label' => 'Total harvested', 'value' => fmt_kg($grand)],
            ['label' => 'Harvest records', 'value' => number_format(array_sum(array_map(static fn ($r) => (int) $r['records'], $byCrop)))],
            ['label' => 'Crops harvested', 'value' => number_format(count($byCrop))],
            ['label' => 'Farms harvesting', 'value' => number_format(count($byFarm))],
        ];
        $tables[] = [
            'title'   => 'Total harvested quantity by crop',
            'columns' => ['Crop' => 'crop_name', 'Harvest records' => 'records', 'Quantity (kg)' => 'qty'],
            'rows'    => $byCrop,
            'format'  => ['qty' => 'number'],
            'footer'  => ['crop_name' => 'Total', 'records' => array_sum(array_map(static fn ($r) => (int) $r['records'], $byCrop)), 'qty' => $grand],
        ];
        $tables[] = [
            'title'   => 'Total harvested quantity by farm',
            'columns' => ['Farm' => 'farm_name', 'Farmer' => 'farmer_name', 'Harvest records' => 'records', 'Quantity (kg)' => 'qty'],
            'rows'    => $byFarm,
            'format'  => ['qty' => 'number'],
        ];
        break;

    case 'harvest_value':
        $byCrop = $reportModel->harvestValueByCrop($from, $to, $farm_id);
        $byFarm = $reportModel->harvestValueByFarm($from, $to, $farm_id);
        $grand  = array_sum(array_map(static fn ($r) => (float) $r['value'], $byCrop));

        $summary = [
            ['label' => 'Estimated value', 'value' => fmt_money($grand)],
            ['label' => 'Total quantity', 'value' => fmt_kg(array_sum(array_map(static fn ($r) => (float) $r['qty'], $byCrop)))],
            ['label' => 'Crops included', 'value' => number_format(count($byCrop))],
            ['label' => 'Farms included', 'value' => number_format(count($byFarm))],
        ];
        $tables[] = [
            'title'   => 'Estimated harvest value by crop',
            'columns' => ['Crop' => 'crop_name', 'Quantity (kg)' => 'qty', 'Average price / kg' => 'avg_price', 'Estimated value' => 'value'],
            'rows'    => $byCrop,
            'format'  => ['qty' => 'number', 'avg_price' => 'money', 'value' => 'money'],
            'footer'  => ['crop_name' => 'Total', 'qty' => array_sum(array_map(static fn ($r) => (float) $r['qty'], $byCrop)),
                          'avg_price' => '', 'value' => $grand],
        ];
        $tables[] = [
            'title'   => 'Estimated harvest value by farm',
            'columns' => ['Farm' => 'farm_name', 'Farmer' => 'farmer_name', 'Quantity (kg)' => 'qty', 'Estimated value' => 'value'],
            'rows'    => $byFarm,
            'format'  => ['qty' => 'number', 'value' => 'money'],
        ];
        break;
}

/* --------------------------------------------------------------------
 * CSV export (same data, no layout)
 * ------------------------------------------------------------------ */
if (get('export') === 'csv') {
    $filename = 'agrisys-' . $report . '-' . $from . '-to-' . $to . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['AgriSys — ' . $reports[$report]['label']]);
    fputcsv($out, ['Period', fmt_date($from) . ' to ' . fmt_date($to)]);
    fputcsv($out, []);
    foreach ($tables as $table) {
        fputcsv($out, [$table['title']]);
        fputcsv($out, array_keys($table['columns']));
        foreach ($table['rows'] as $row) {
            $line = [];
            foreach (array_values($table['columns']) as $key) {
                $line[] = $row[$key] ?? '';
            }
            fputcsv($out, $line);
        }
        if (!empty($table['footer'])) {
            $line = [];
            foreach (array_values($table['columns']) as $key) {
                $line[] = $table['footer'][$key] ?? '';
            }
            fputcsv($out, $line);
        }
        fputcsv($out, []);
    }
    fclose($out);
    exit;
}

$cell = static function (array $table, array $row, string $key, mixed $value): string {
    if ($value === null || $value === '') {
        return '';
    }
    $format = $table['format'][$key] ?? null;
    return match ($format) {
        'date'   => e(fmt_date((string) $value)),
        'number' => e(fmt_num($value)),
        'money'  => e(fmt_money($value)),
        default  => e((string) $value),
    };
};

$pageTitle    = 'Reports';
$active       = 'reports';
$pageSubtitle = $reports[$report]['label'] . ' — ' . fmt_date($from) . ' to ' . fmt_date($to);
$pageActions  = '<button type="button" class="btn btn-outline-secondary" data-print><i class="bi bi-printer me-1"></i> Print</button>'
    . '<a class="btn btn-success" href="' . e(query_url(['export' => 'csv'])) . '"><i class="bi bi-filetype-csv me-1"></i> Export CSV</a>';

require __DIR__ . '/partials/header.php';
?>

<div class="row g-3">
    <div class="col-lg-3">
        <div class="card no-print">
            <div class="card-header">Choose a report</div>
            <div class="list-group list-group-flush">
                <?php foreach ($reports as $key => $info): ?>
                    <a class="list-group-item list-group-item-action d-flex align-items-center gap-2<?= $key === $report ? ' active' : '' ?>"
                       href="<?= e(query_url(['report' => $key, 'export' => null, 'page' => null])) ?>">
                        <i class="bi bi-<?= e($info['icon']) ?>"></i>
                        <span><?= e($info['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card mt-3 no-print">
            <div class="card-header">Report filters</div>
            <div class="card-body">
                <form method="get" class="row g-2">
                    <input type="hidden" name="report" value="<?= e($report) ?>">
                    <div class="col-6">
                        <label class="form-label" for="from">From</label>
                        <input type="date" class="form-control" id="from" name="from" value="<?= e($from) ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="to">To</label>
                        <input type="date" class="form-control" id="to" name="to" value="<?= e($to) ?>">
                    </div>

                    <?php if (in_array($report, ['farm_crops', 'harvests', 'harvest_totals', 'harvest_value'], true)): ?>
                        <div class="col-12">
                            <label class="form-label" for="farm_id">Farm</label>
                            <select class="form-select" id="farm_id" name="farm_id">
                                <?= options($farms, 'farm_id', ['farm_name'], $farm_id ?: '', 'All farms') ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($report === 'planting_status'): ?>
                        <div class="col-12">
                            <label class="form-label" for="status_id">Status</label>
                            <select class="form-select" id="status_id" name="status_id">
                                <?= options($statuses, 'status_id', ['status_name'], $status_id ?: '', 'All statuses') ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($report === 'harvests'): ?>
                        <div class="col-12">
                            <label class="form-label" for="grade_id">Quality grade</label>
                            <select class="form-select" id="grade_id" name="grade_id">
                                <?= options($grades, 'grade_id', ['grade_name'], $grade_id ?: '', 'All grades') ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="col-12 d-flex gap-2 mt-2">
                        <button class="btn btn-success flex-grow-1" type="submit"><i class="bi bi-funnel me-1"></i> Apply</button>
                        <a class="btn btn-outline-secondary" href="<?= e(url('reports.php?report=' . $report)) ?>">Reset</a>
                    </div>
                </form>
                <div class="form-text mt-2">Dates filter the report's primary date field (registration, planting or harvest date).</div>
            </div>
        </div>
    </div>

    <div class="col-lg-9">
        <div class="row g-2 mb-3">
            <?php foreach ($summary as $item): ?>
                <div class="col-6 col-xl-3">
                    <div class="card stat-card"><div class="stat-body py-2 px-3">
                        <div class="min-w-0">
                            <div class="stat-label"><?= e($item['label']) ?></div>
                            <div class="fw-bold text-truncate" style="font-size:1.15rem"><?= e($item['value']) ?></div>
                        </div>
                    </div></div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php foreach ($tables as $table): ?>
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span><?= e($table['title']) ?></span>
                    <span class="small text-muted"><?= number_format(count($table['rows'])) ?> row(s)</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead>
                                <tr>
                                    <?php foreach ($table['columns'] as $heading => $key): ?>
                                        <th class="<?= in_array($table['format'][$key] ?? '', ['number', 'money'], true) ? 'text-end' : '' ?>">
                                            <?= e($heading) ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($table['rows'] === []): ?>
                                <tr><td colspan="<?= count($table['columns']) ?>"><div class="empty-state"><i class="bi bi-inbox"></i>No data for the selected period and filters.</div></td></tr>
                            <?php endif; ?>
                            <?php foreach ($table['rows'] as $row): ?>
                                <tr>
                                    <?php foreach ($table['columns'] as $key): ?>
                                        <td class="<?= in_array($table['format'][$key] ?? '', ['number', 'money'], true) ? 'text-end' : '' ?>">
                                            <?php if (($table['badge'] ?? null) === $key): ?>
                                                <span class="badge <?= e(status_badge_class((string) $row[$key])) ?>"><?= e($row[$key]) ?></span>
                                            <?php elseif (isset($table['link']) && $table['link'][0] === $key && !empty($row[$key])): ?>
                                                <a href="<?= e(url($table['link'][1] . (int) $row[$key])) ?>"><?= $cell($table, $row, $key, $row[$key]) ?></a>
                                            <?php else: ?>
                                                <?= $cell($table, $row, $key, $row[$key] ?? null) ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <?php if (!empty($table['footer'])): ?>
                                <tfoot>
                                    <tr class="fw-semibold">
                                        <?php foreach ($table['columns'] as $key): ?>
                                            <td class="<?= in_array($table['format'][$key] ?? '', ['number', 'money'], true) ? 'text-end' : '' ?>">
                                                <?= $cell($table, $table['footer'], $key, $table['footer'][$key] ?? '') ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="text-muted small">
            Generated on <?= e(date('M j, Y g:i A')) ?> by <?= e(Auth::user()['full_name'] ?? '') ?>.
            All figures are calculated live from the Supabase database.
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>









