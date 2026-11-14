<?php
declare(strict_types=1);

/**
 * VIEW — Harvest records list.
 * Reads come from the Harvest model (src/Models/Harvest.php); the
 * aggregate totals come back with the listing.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
Auth::requireLogin();

$q         = get('q');
$grade_id  = int_get('grade_id', 0);
$remark_id = int_get('remark_id', 0);
$farm_id   = int_get('farm_id', 0);
$dateFrom  = get('date_from');
$dateTo    = get('date_to');
$sort      = current_sort('date', Harvest::SORTABLE);
$dir       = current_dir();
$page      = max(1, int_get('page', 1));

$result   = Harvest::listing($q, $grade_id, $remark_id, $farm_id, $dateFrom, $dateTo, $sort, $dir, $page);
$harvests = $result['rows'];
$p        = $result['pager'];
$aggregate = $result['aggregate'];
$total    = (int) $p['total'];

$grades  = Harvest::grades();
$remarks = Harvest::remarks();
$farms   = Harvest::farmFilterOptions();

$pageTitle    = 'Harvest records';
$active       = 'harvests';
$pageSubtitle = 'Harvest transactions, quality grades and estimated value';
$pageActions  = '<a class="btn btn-success" href="' . e(url('harvests/form.php')) . '">'
    . '<i class="bi bi-plus-lg me-1"></i> New harvest</a>';

require __DIR__ . '/../partials/header.php';
?>

<form class="filter-bar mb-3" method="get" data-auto-submit>
    <div class="row g-2 align-items-end">
        <div class="col-12 col-lg-3">
            <label class="form-label" for="q">Search</label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Farm, farmer, crop or grade">
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="grade_id">Quality grade</label>
            <select class="form-select" id="grade_id" name="grade_id">
                <?= options($grades, 'grade_id', ['grade_name'], $grade_id ?: '', 'All grades') ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="remark_id">Remarks</label>
            <select class="form-select" id="remark_id" name="remark_id">
                <?= options($remarks, 'remark_id', ['remark_description'], $remark_id ?: '', 'All remarks') ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="farm_id">Farm</label>
            <select class="form-select" id="farm_id" name="farm_id">
                <?= options($farms, 'farm_id', ['farm_name'], $farm_id ?: '', 'All farms') ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-1">
            <label class="form-label" for="date_from">From</label>
            <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
        </div>
        <div class="col-6 col-md-4 col-lg-1">
            <label class="form-label" for="date_to">To</label>
            <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
        </div>
        <div class="col-12 col-lg-1 d-flex gap-2">
            <button class="btn btn-success w-100" type="submit"><i class="bi bi-funnel"></i></button>
            <a class="btn btn-outline-secondary" href="<?= e(url('harvests/index.php')) ?>" title="Reset"><i class="bi bi-x-lg"></i></a>
        </div>
    </div>
</form>

<div class="row g-2 mb-3">
    <div class="col-sm-4">
        <div class="card stat-card"><div class="stat-body py-2 px-3">
            <div><div class="stat-label">Matching records</div><div class="fw-bold"><?= number_format($total) ?></div></div>
            <span class="stat-icon"><i class="bi bi-basket3"></i></span>
        </div></div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card"><div class="stat-body py-2 px-3">
            <div><div class="stat-label">Total quantity</div><div class="fw-bold"><?= e(fmt_kg($aggregate['qty'] ?? 0)) ?></div></div>
            <span class="stat-icon gold"><i class="bi bi-box-seam"></i></span>
        </div></div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card"><div class="stat-body py-2 px-3">
            <div><div class="stat-label">Estimated value</div><div class="fw-bold text-success"><?= e(fmt_money($aggregate['value'] ?? 0)) ?></div></div>
            <span class="stat-icon blue"><i class="bi bi-cash-coin"></i></span>
        </div></div>
    </div>
</div>

<div class="card table-card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th><?= sort_link('Date', 'date', $sort, $dir) ?></th>
                        <th><?= sort_link('Farm', 'farm', $sort, $dir) ?></th>
                        <th><?= sort_link('Crop', 'crop', $sort, $dir) ?></th>
                        <th><?= sort_link('Grade', 'grade', $sort, $dir) ?></th>
                        <th>Remark</th>
                        <th class="text-end"><?= sort_link('Quantity', 'qty', $sort, $dir) ?></th>
                        <th class="text-end">Price / kg</th>
                        <th class="text-end"><?= sort_link('Value', 'value', $sort, $dir) ?></th>
                        <th class="text-end no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($harvests === []): ?>
                    <tr><td colspan="9">
                        <div class="empty-state">
                            <i class="bi bi-basket3"></i>
                            No harvest records match your filters.
                            <div class="mt-2"><a href="<?= e(url('harvests/form.php')) ?>">Record the first harvest</a></div>
                        </div>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($harvests as $row): ?>
                    <tr>
                        <td class="text-nowrap">
                            <a class="fw-semibold text-dark" href="<?= e(url('harvests/view.php?id=' . (int) $row['harvest_id'])) ?>"><?= e(fmt_date($row['harvest_date'])) ?></a>
                        </td>
                        <td>
                            <?= e($row['farm_name']) ?>
                            <div class="small text-muted"><?= e($row['farmer_name']) ?></div>
                        </td>
                        <td><?= e($row['crop_name']) ?><?php if ($row['variety']): ?><div class="small text-muted"><?= e($row['variety']) ?></div><?php endif; ?></td>
                        <td><?= e($row['grade_name']) ?></td>
                        <td><span class="badge text-bg-light border"><?= e($row['remark_description']) ?></span></td>
                        <td class="text-end fw-semibold"><?= e(fmt_kg($row['quantity_kg'])) ?></td>
                        <td class="text-end"><?= e(fmt_money($row['selling_price_per_kg'])) ?></td>
                        <td class="text-end fw-semibold text-success"><?= e(fmt_money($row['estimated_value'])) ?></td>
                        <td class="text-end no-print">
                            <div class="row-actions d-inline-flex gap-1">
                                <a class="btn btn-outline-secondary" title="View"
                                   href="<?= e(url('harvests/view.php?id=' . (int) $row['harvest_id'])) ?>"><i class="bi bi-eye"></i></a>
                                <a class="btn btn-outline-success" title="Edit"
                                   href="<?= e(url('harvests/form.php?id=' . (int) $row['harvest_id'])) ?>"><i class="bi bi-pencil"></i></a>
                                <?php if (Auth::isAdmin()): ?>
                                    <button type="button" class="btn btn-outline-danger" title="Delete"
                                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                                            data-delete-url="<?= e(url('harvests/delete.php?id=' . (int) $row['harvest_id'])) ?>"
                                            data-delete-name="<?= e('Harvest of ' . $row['crop_name'] . ' on ' . fmt_date($row['harvest_date'])) ?>">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($p['pages'] > 1): ?>
        <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="small text-muted">Showing <?= $p['from'] ?>–<?= $p['to'] ?> of <?= number_format($p['total']) ?></span>
            <?= pagination_links($p, 'harvests/index.php') ?>
        </div>
    <?php endif; ?>
</div>

<?php
require __DIR__ . '/../partials/delete-modal.php';
require __DIR__ . '/../partials/footer.php';
