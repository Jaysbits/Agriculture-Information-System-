<?php
declare(strict_types=1);

/**
 * VIEW — Planting records list.
 * Reads come from the Planting model (src/Models/Planting.php).
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
Auth::requireLogin();

$plantingModel = new Planting();

$q         = get('q');
$status_id = int_get('status_id', 0);
$farm_id   = int_get('farm_id', 0);
$crop_id   = int_get('crop_id', 0);
$dateFrom  = get('date_from');
$dateTo    = get('date_to');
$sort      = current_sort('date', Planting::SORTABLE);
$dir       = current_dir();
$page      = max(1, int_get('page', 1));

$result    = $plantingModel->listing($q, $status_id, $farm_id, $crop_id, $dateFrom, $dateTo, $sort, $dir, $page);
$plantings = $result['rows'];
$p         = $result['pager'];

$statuses = $plantingModel->statuses();
$farms    = $plantingModel->farmFilterOptions();
$crops    = $plantingModel->cropFilterOptions();

$pageTitle    = 'Planting records';
$active       = 'plantings';
$pageSubtitle = 'Crop planting activities across all farms';
$pageActions  = '<a class="btn btn-success" href="' . e(url('plantings/form.php')) . '">'
    . '<i class="bi bi-plus-lg me-1"></i> New planting</a>';

require __DIR__ . '/../partials/header.php';
?>

<form class="filter-bar mb-3" method="get" data-auto-submit>
    <div class="row g-2 align-items-end">
        <div class="col-12 col-lg-3">
            <label class="form-label" for="q">Search</label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Farm, farmer or crop">
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="status_id">Status</label>
            <select class="form-select" id="status_id" name="status_id">
                <?= options($statuses, 'status_id', ['status_name'], $status_id ?: '', 'All statuses') ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="farm_id">Farm</label>
            <select class="form-select" id="farm_id" name="farm_id">
                <?= options($farms, 'farm_id', ['farm_name'], $farm_id ?: '', 'All farms') ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="crop_id">Crop</label>
            <select class="form-select" id="crop_id" name="crop_id">
                <?= options($crops, 'crop_id', ['crop_name', 'variety'], $crop_id ?: '', 'All crops') ?>
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
            <a class="btn btn-outline-secondary" href="<?= e(url('plantings/index.php')) ?>" title="Reset"><i class="bi bi-x-lg"></i></a>
        </div>
    </div>
</form>

<div class="card table-card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th><?= sort_link('Farm', 'farm', $sort, $dir) ?></th>
                        <th><?= sort_link('Crop', 'crop', $sort, $dir) ?></th>
                        <th><?= sort_link('Planted', 'date', $sort, $dir) ?></th>
                        <th><?= sort_link('Expected harvest', 'expected', $sort, $dir) ?></th>
                        <th><?= sort_link('Status', 'status', $sort, $dir) ?></th>
                        <th class="text-end"><?= sort_link('Area', 'area', $sort, $dir) ?></th>
                        <th class="text-end">Harvested</th>
                        <th class="text-end no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($plantings === []): ?>
                    <tr><td colspan="8">
                        <div class="empty-state">
                            <i class="bi bi-calendar-check"></i>
                            No planting records match your filters.
                            <div class="mt-2"><a href="<?= e(url('plantings/form.php')) ?>">Add the first planting record</a></div>
                        </div>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($plantings as $row): ?>
                    <tr>
                        <td>
                            <a class="fw-semibold text-dark" href="<?= e(url('plantings/view.php?id=' . (int) $row['planting_id'])) ?>"><?= e($row['farm_name']) ?></a>
                            <div class="small text-muted"><?= e($row['farmer_name']) ?></div>
                        </td>
                        <td>
                            <?= e($row['crop_name']) ?>
                            <?php if ($row['variety']): ?><div class="small text-muted"><?= e($row['variety']) ?></div><?php endif; ?>
                        </td>
                        <td class="text-nowrap"><?= e(fmt_date($row['planting_date'])) ?></td>
                        <td class="text-nowrap"><?= e(fmt_date($row['expected_harvest_date'])) ?></td>
                        <td><span class="badge <?= e(status_badge_class((string) $row['status_name'])) ?>"><?= e($row['status_name']) ?></span></td>
                        <td class="text-end"><?= e(fmt_ha($row['area_planted'])) ?></td>
                        <td class="text-end">
                            <?php if ((int) $row['harvest_count'] > 0): ?>
                                <?= e(fmt_kg($row['harvested'])) ?>
                                <div class="small text-muted"><?= (int) $row['harvest_count'] ?> record(s)</div>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end no-print">
                            <div class="row-actions d-inline-flex gap-1">
                                <a class="btn btn-outline-secondary" title="View"
                                   href="<?= e(url('plantings/view.php?id=' . (int) $row['planting_id'])) ?>"><i class="bi bi-eye"></i></a>
                                <a class="btn btn-outline-success" title="Edit"
                                   href="<?= e(url('plantings/form.php?id=' . (int) $row['planting_id'])) ?>"><i class="bi bi-pencil"></i></a>
                                <?php if (Auth::isAdmin()): ?>
                                    <button type="button" class="btn btn-outline-danger" title="Delete"
                                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                                            data-delete-url="<?= e(url('plantings/delete.php?id=' . (int) $row['planting_id'])) ?>"
                                            data-delete-name="<?= e($row['crop_name'] . ' planting on ' . $row['farm_name'] . ' (' . fmt_date($row['planting_date']) . ')') ?>">
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
            <?= pagination_links($p, 'plantings/index.php') ?>
        </div>
    <?php endif; ?>
</div>

<?php
require __DIR__ . '/../partials/delete-modal.php';
require __DIR__ . '/../partials/footer.php';
