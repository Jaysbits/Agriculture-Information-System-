<?php
declare(strict_types=1);

/**
 * VIEW — Dashboard.
 * Every query behind this page lives in the Dashboard model
 * (src/Models/Dashboard.php); the view only formats and renders it.
 */

require_once dirname(__DIR__) . '/config/config.php';
Auth::requireLogin();

$dashboard = new Dashboard();

/* ---- summary numbers (all queried live) ---------------------------- */
$summary = $dashboard->summary();

$statusRows = $dashboard->statusBreakdown();

$monthly = $dashboard->monthlyHarvest();

$monthLabels = [];
$monthQty    = [];
$monthValue  = [];
for ($i = 5; $i >= 0; $i--) {
    $key = date('Y-m', strtotime('first day of -' . $i . ' month'));
    $monthLabels[] = date('M Y', strtotime($key . '-01'));
    $match = null;
    foreach ($monthly as $row) {
        if ($row['ym'] === $key) {
            $match = $row;
            break;
        }
    }
    $monthQty[]   = $match ? round((float) $match['qty'], 2) : 0;
    $monthValue[] = $match ? round((float) $match['value'], 2) : 0;
}

$recentPlantings = $dashboard->recentPlantings();
$recentHarvests  = $dashboard->recentHarvests();
$upcoming        = $dashboard->upcomingHarvests();
$topCrops        = $dashboard->topCrops();

$avgPrice = (float) ($summary['total_qty'] ?? 0) > 0
    ? (float) $summary['total_value'] / (float) $summary['total_qty']
    : 0;

$pageTitle    = 'Dashboard';
$active       = 'dashboard';
$pageSubtitle = 'Live overview of farmers, farms, plantings and harvests';
$pageActions  = '<a class="btn btn-success" href="' . e(url('plantings/form.php')) . '">'
    . '<i class="bi bi-plus-lg me-1"></i> New planting</a>'
    . '<a class="btn btn-outline-success" href="' . e(url('reports.php')) . '">'
    . '<i class="bi bi-file-earmark-bar-graph me-1"></i> Reports</a>';

require __DIR__ . '/partials/header.php';
?>

<div class="row g-3 mb-1">
    <?php
    $cards = [
        ['label' => 'Farmers',  'value' => (int) ($summary['farmers'] ?? 0),  'icon' => 'people',         'tone' => '',       'hint' => 'Registered farmers',      'href' => 'farmers/index.php'],
        ['label' => 'Farms',    'value' => (int) ($summary['farms'] ?? 0),    'icon' => 'geo-alt',        'tone' => 'blue',   'hint' => fmt_ha($summary['farm_area'] ?? 0) . ' total area', 'href' => 'farms/index.php'],
        ['label' => 'Crops',    'value' => (int) ($summary['crops'] ?? 0),    'icon' => 'flower1',        'tone' => 'gold',   'hint' => 'Crop varieties on record', 'href' => 'crops/index.php'],
        ['label' => 'Plantings','value' => (int) ($summary['plantings'] ?? 0),'icon' => 'calendar-check', 'tone' => 'slate',  'hint' => (int) ($summary['active_plantings'] ?? 0) . ' active', 'href' => 'plantings/index.php'],
        ['label' => 'Harvests', 'value' => (int) ($summary['harvests'] ?? 0), 'icon' => 'basket3',        'tone' => 'rose',   'hint' => fmt_kg($summary['total_qty'] ?? 0) . ' harvested', 'href' => 'harvests/index.php'],
    ];
    foreach ($cards as $card): ?>
        <div class="col-6 col-md-4 col-xl">
            <div class="card stat-card">
                <div class="stat-body">
                    <div class="min-w-0">
                        <div class="stat-label"><?= e($card['label']) ?></div>
                        <div class="stat-value"><?= number_format($card['value']) ?></div>
                        <div class="stat-hint text-truncate"><?= e($card['hint']) ?></div>
                    </div>
                    <span class="stat-icon <?= e($card['tone']) ?>"><i class="bi bi-<?= e($card['icon']) ?>"></i></span>
                </div>
                <a class="stat-link" href="<?= e(url($card['href'])) ?>">
                    Open module <i class="bi bi-arrow-right-short"></i>
                </a>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mt-0">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-pie-chart me-1 text-success"></i> Plantings by status</span>
                <a class="small text-success" href="<?= e(url('plantings/index.php')) ?>">View all</a>
            </div>
            <div class="card-body">
                <div class="chart-wrap"><canvas id="statusChart" aria-label="Plantings by status" role="img"></canvas></div>
                <div class="table-responsive mt-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Status</th><th class="text-end">Plantings</th><th class="text-end">Area</th></tr></thead>
                        <tbody>
                        <?php foreach ($statusRows as $row): ?>
                            <tr>
                                <td><span class="badge <?= e(status_badge_class((string) $row['status_name'])) ?>"><?= e($row['status_name']) ?></span></td>
                                <td class="text-end"><?= number_format((int) $row['total']) ?></td>
                                <td class="text-end text-muted"><?= e(fmt_num($row['area'])) ?> ha</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bar-chart me-1 text-success"></i> Harvest volume — last 6 months</span>
                <a class="small text-success" href="<?= e(url('reports.php?report=harvests')) ?>">Harvest report</a>
            </div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-sm-4">
                        <div class="border rounded-3 p-2 px-3 h-100">
                            <div class="stat-label">Total harvested</div>
                            <div class="fw-bold"><?= e(fmt_kg($summary['total_qty'] ?? 0)) ?></div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="border rounded-3 p-2 px-3 h-100">
                            <div class="stat-label">Estimated value</div>
                            <div class="fw-bold text-success"><?= e(fmt_money($summary['total_value'] ?? 0)) ?></div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="border rounded-3 p-2 px-3 h-100">
                            <div class="stat-label">Average price / kg</div>
                            <div class="fw-bold"><?= e(fmt_money($avgPrice)) ?></div>
                        </div>
                    </div>
                </div>
                <div class="chart-wrap" style="height:210px"><canvas id="monthChart" aria-label="Monthly harvest quantity" role="img"></canvas></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-0">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-calendar-check me-1 text-success"></i> Recent planting records</span>
                <a class="small text-success" href="<?= e(url('plantings/index.php')) ?>">View all</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>Farm</th><th>Crop</th><th>Planted</th><th>Status</th><th class="text-end">Area</th></tr>
                        </thead>
                        <tbody>
                        <?php if ($recentPlantings === []): ?>
                            <tr><td colspan="5"><div class="empty-state"><i class="bi bi-inbox"></i>No planting records yet.</div></td></tr>
                        <?php endif; ?>
                        <?php foreach ($recentPlantings as $row): ?>
                            <tr>
                                <td>
                                    <a class="fw-semibold text-dark" href="<?= e(url('plantings/view.php?id=' . (int) $row['planting_id'])) ?>"><?= e($row['farm_name']) ?></a>
                                    <div class="small text-muted"><?= e($row['farmer_name']) ?></div>
                                </td>
                                <td><?= e($row['crop_name']) ?><?php if ($row['variety']): ?><div class="small text-muted"><?= e($row['variety']) ?></div><?php endif; ?></td>
                                <td class="text-nowrap"><?= e(fmt_date($row['planting_date'])) ?></td>
                                <td><span class="badge <?= e(status_badge_class((string) $row['status_name'])) ?>"><?= e($row['status_name']) ?></span></td>
                                <td class="text-end"><?= e(fmt_ha($row['area_planted'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-basket3 me-1 text-success"></i> Recent harvests</span>
                <a class="small text-success" href="<?= e(url('harvests/index.php')) ?>">View all</a>
            </div>
            <div class="card-body">
                <?php if ($recentHarvests === []): ?>
                    <div class="empty-state"><i class="bi bi-inbox"></i>No harvest records yet.</div>
                <?php else: ?>
                    <ul class="list-feed">
                        <?php foreach ($recentHarvests as $row): ?>
                            <li>
                                <span class="feed-icon"><i class="bi bi-basket2"></i></span>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex justify-content-between gap-2">
                                        <a class="fw-semibold text-dark text-truncate" href="<?= e(url('harvests/view.php?id=' . (int) $row['harvest_id'])) ?>"><?= e($row['crop_name']) ?></a>
                                        <span class="fw-semibold text-success text-nowrap"><?= e(fmt_kg($row['quantity_kg'])) ?></span>
                                    </div>
                                    <div class="small text-muted text-truncate">
                                        <?= e($row['farm_name']) ?> &middot; <?= e(fmt_date($row['harvest_date'])) ?>
                                    </div>
                                    <div class="small text-muted"><?= e($row['grade_name']) ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-0">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-alarm me-1 text-success"></i> Expected harvests — next 30 days</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Farm</th><th>Crop</th><th>Target date</th><th class="text-end">Area</th></tr></thead>
                        <tbody>
                        <?php if ($upcoming === []): ?>
                            <tr><td colspan="4"><div class="empty-state"><i class="bi bi-calendar2-week"></i>No harvests expected in the next 30 days.</div></td></tr>
                        <?php endif; ?>
                        <?php foreach ($upcoming as $row): ?>
                            <tr>
                                <td><?= e($row['farm_name']) ?><div class="small text-muted"><?= e($row['farmer_name']) ?></div></td>
                                <td><?= e($row['crop_name']) ?></td>
                                <td class="text-nowrap"><?= e(fmt_date($row['expected_harvest_date'])) ?></td>
                                <td class="text-end"><?= e(fmt_ha($row['area_planted'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-trophy me-1 text-success"></i> Top crops by harvested quantity</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Crop</th><th class="text-end">Harvests</th><th class="text-end">Quantity</th><th class="text-end">Value</th></tr></thead>
                        <tbody>
                        <?php if ($topCrops === []): ?>
                            <tr><td colspan="4"><div class="empty-state"><i class="bi bi-graph-up"></i>No harvest data yet.</div></td></tr>
                        <?php endif; ?>
                        <?php foreach ($topCrops as $row): ?>
                            <tr>
                                <td><?= e($row['crop_name']) ?><?php if ($row['variety']): ?><div class="small text-muted"><?= e($row['variety']) ?></div><?php endif; ?></td>
                                <td class="text-end"><?= number_format((int) $row['harvests']) ?></td>
                                <td class="text-end"><?= e(fmt_kg($row['qty'])) ?></td>
                                <td class="text-end text-success fw-semibold"><?= e(fmt_money($row['value'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$statusLabels = array_map(static fn ($r) => $r['status_name'], $statusRows);
$statusCounts = array_map(static fn ($r) => (int) $r['total'], $statusRows);

$pageScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>'
    . '<script>
(function () {
    var statusCtx = document.getElementById("statusChart");
    if (statusCtx) {
        new Chart(statusCtx, {
            type: "doughnut",
            data: {
                labels: ' . json_encode($statusLabels) . ',
                datasets: [{
                    data: ' . json_encode($statusCounts) . ',
                    backgroundColor: ["#9aa8a0","#5aa9c9","#3f7fc1","#e0a53c","#3f8f5b","#c25a5a"],
                    borderWidth: 2,
                    borderColor: "#fff"
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: "62%",
                plugins: { legend: { position: "bottom", labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, font: { size: 11 } } } }
            }
        });
    }

    var monthCtx = document.getElementById("monthChart");
    if (monthCtx) {
        new Chart(monthCtx, {
            type: "bar",
            data: {
                labels: ' . json_encode($monthLabels) . ',
                datasets: [{
                    label: "Harvested (kg)",
                    data: ' . json_encode($monthQty) . ',
                    backgroundColor: "rgba(63,143,91,.85)",
                    borderRadius: 6,
                    maxBarThickness: 38
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: function (v) { return v.toLocaleString(); } }, grid: { color: "#eef2ef" } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
})();
</script>';

require __DIR__ . '/partials/footer.php';
