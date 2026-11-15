<?php
declare(strict_types=1);

/**
 * VIEW — Farms list.
 * Reads come from the Farm model (src/Models/Farm.php).
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
Auth::requireLogin();

$farmModel = new Farm(new Database());

$q             = get('q');
$irrigation_id = int_get('irrigation_id', 0);
$soil_type_id  = int_get('soil_type_id', 0);
$sort          = current_sort('name', Farm::SORTABLE);
$dir           = current_dir();
$page          = max(1, int_get('page', 1));

$result = $farmModel->listing($q, $irrigation_id, $soil_type_id, $sort, $dir, $page);
$farms  = $result['rows'];
$p      = $result['pager'];

$soilTypes       = $farmModel->soilTypes();
$irrigationTypes = $farmModel->irrigationTypes();

$pageTitle    = 'Farms';
$active       = 'farms';
$pageSubtitle = 'Farm holdings, soil types and irrigation systems';
$pageActions  = '<a class="btn btn-success" href="' . e(url('farms/form.php')) . '">'
    . '<i class="bi bi-plus-lg me-1"></i> New farm</a>';

require __DIR__ . '/../partials/header.php';
?>

<form class="filter-bar mb-3" method="get" data-auto-submit>
    <div class="row g-2 align-items-end">
        <div class="col-12 col-lg-4">
            <label class="form-label" for="q">Search</label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input class="form-control" id="q" name="q" value="<?= e($q) ?>"
                       placeholder="Farm, location or farmer name">
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <label class="form-label" for="irrigation_id">Irrigation type</label>
            <select class="form-select" id="irrigation_id" name="irrigation_id">
                <?= options($irrigationTypes, 'irrigation_id', ['irrigation_name'], $irrigation_id ?: '', 'All types') ?>
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label class="form-label" for="soil_type_id">Soil type</label>
            <select class="form-select" id="soil_type_id" name="soil_type_id">
                <?= options($soilTypes, 'soil_type_id', ['soil_type_name'], $soil_type_id ?: '', 'All soils') ?>
            </select>
        </div>
        <div class="col-12 col-lg-3 d-flex gap-2">
            <button class="btn btn-success" type="submit"><i class="bi bi-funnel me-1"></i> Filter</button>
            <a class="btn btn-outline-secondary" href="<?= e(url('farms/index.php')) ?>">Reset</a>
        </div>
    </div>
</form>

<div class="card table-card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th><?= sort_link('Farm', 'name', $sort, $dir) ?></th>
                        <th><?= sort_link('Farmer', 'farmer', $sort, $dir) ?></th>
                        <th><?= sort_link('Soil', 'soil', $sort, $dir) ?></th>
                        <th><?= sort_link('Irrigation', 'irrigation', $sort, $dir) ?></th>
                        <th class="text-end"><?= sort_link('Area', 'area', $sort, $dir) ?></th>
                        <th class="text-end"><?= sort_link('Plantings', 'plantings', $sort, $dir) ?></th>
                        <th class="text-end no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($farms === []): ?>
                    <tr><td colspan="7">
                        <div class="empty-state">
                            <i class="bi bi-geo-alt"></i>
                            No farms match your filters.
                            <div class="mt-2"><a href="<?= e(url('farms/form.php')) ?>">Register the first farm</a></div>
                        </div>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($farms as $row): ?>
                    <tr>
                        <td>
                            <a class="fw-semibold text-dark" href="<?= e(url('farms/view.php?id=' . (int) $row['farm_id'])) ?>"><?= e($row['farm_name']) ?></a>
                            <div class="small text-muted"><?= e($row['location']) ?></div>
                        </td>
                        <td>
                            <a class="text-dark" href="<?= e(url('farmers/view.php?id=' . (int) $row['farmer_id'])) ?>">
                                <?= e($row['last_name'] . ', ' . $row['first_name']) ?>
                            </a>
                        </td>
                        <td class="text-nowrap"><?= e($row['soil_type_name']) ?></td>
                        <td class="text-nowrap"><?= e($row['irrigation_name']) ?></td>
                        <td class="text-end">
                            <?= e(fmt_ha($row['area_hectares'])) ?>
                            <?php if ((float) $row['planted_area'] > 0): ?>
                                <div class="small text-muted"><?= e(fmt_num($row['planted_area'])) ?> ha planted</div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><?= number_format((int) $row['planting_count']) ?></td>
                        <td class="text-end no-print">
                            <div class="row-actions d-inline-flex gap-1">
                                <a class="btn btn-outline-secondary" title="View"
                                   href="<?= e(url('farms/view.php?id=' . (int) $row['farm_id'])) ?>"><i class="bi bi-eye"></i></a>
                                <a class="btn btn-outline-success" title="Edit"
                                   href="<?= e(url('farms/form.php?id=' . (int) $row['farm_id'])) ?>"><i class="bi bi-pencil"></i></a>
                                <?php if (Auth::isAdmin()): ?>
                                    <button type="button" class="btn btn-outline-danger" title="Delete"
                                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                                            data-delete-url="<?= e(url('farms/delete.php?id=' . (int) $row['farm_id'])) ?>"
                                            data-delete-name="<?= e($row['farm_name']) ?>">
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
            <?= pagination_links($p, 'farms/index.php') ?>
        </div>
    <?php endif; ?>
</div>

<?php
require __DIR__ . '/../partials/delete-modal.php';
require __DIR__ . '/../partials/footer.php';









