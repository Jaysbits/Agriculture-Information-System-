<?php
declare(strict_types=1);

/**
 * VIEW — Planting detail page.
 * Reads come from the Planting model (src/Models/Planting.php).
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
Auth::requireLogin();

$plantingModel = new Planting();

$id       = int_get('id', 0);
$planting = $id > 0 ? $plantingModel->detail($id) : null;

if (!$planting) {
    flash('danger', 'That planting record could not be found.');
    redirect('plantings/index.php');
}

$harvests = $plantingModel->harvests($id);
$totals   = $plantingModel->harvestTotals($id);

$daysSince  = (int) floor((time() - strtotime((string) $planting['planting_date'])) / 86400);
$daysToGo   = $planting['expected_harvest_date']
    ? (int) floor((strtotime((string) $planting['expected_harvest_date']) - time()) / 86400)
    : null;
$isOpen     = !in_array($planting['status_name'], ['Harvested', 'Failed'], true);
$isOverdue  = $isOpen && $daysToGo !== null && $daysToGo < 0;

$pageTitle    = 'Planting details';
$active       = 'plantings';
$pageSubtitle = $planting['crop_name'] . ' on ' . $planting['farm_name'];
$pageActions  = '<a class="btn btn-outline-secondary" href="' . e(url('plantings/index.php')) . '"><i class="bi bi-arrow-left me-1"></i> Plantings</a>'
    . '<a class="btn btn-success" href="' . e(url('harvests/form.php?planting_id=' . $id)) . '"><i class="bi bi-plus-lg me-1"></i> Add harvest</a>'
    . '<a class="btn btn-outline-success" href="' . e(url('plantings/form.php?id=' . $id)) . '"><i class="bi bi-pencil me-1"></i> Edit</a>';

require __DIR__ . '/../partials/header.php';
?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="detail-hero d-flex flex-wrap align-items-center gap-3">
            <span class="avatar"><i class="bi bi-calendar-check"></i></span>
            <div class="flex-grow-1 min-w-0">
                <h2 class="h4 mb-1"><?= e($planting['crop_name']) ?><?= $planting['variety'] ? ' — ' . e($planting['variety']) : '' ?></h2>
                <div class="small text-muted">
                    <a class="link-light" href="<?= e(url('farms/view.php?id=' . (int) $planting['farm_id'])) ?>"><?= e($planting['farm_name']) ?></a>
                    <span class="mx-2">|</span>
                    <a class="link-light" href="<?= e(url('farmers/view.php?id=' . (int) $planting['farmer_id'])) ?>"><?= e($planting['farmer_name']) ?></a>
                </div>
            </div>
            <div class="text-md-end small text-muted">
                <div>Status</div>
                <span class="badge <?= e(status_badge_class((string) $planting['status_name'])) ?>"><?= e($planting['status_name']) ?></span>
            </div>
        </div>

        <?php if ($isOverdue): ?>
            <div class="alert alert-warning d-flex gap-2 mt-3">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>The expected harvest date has passed <?= abs($daysToGo ?? 0) ?> day(s) ago and this planting is still marked
                    <strong><?= e($planting['status_name']) ?></strong>.
                    <a href="<?= e(url('plantings/form.php?id=' . $id)) ?>" class="alert-link">Update the record</a>.</div>
            </div>
        <?php endif; ?>

        <div class="row g-3 mt-1">
            <?php
            $tiles = [
                ['label' => 'Area planted',  'value' => fmt_ha($planting['area_planted']), 'icon' => 'rulers', 'tone' => ''],
                ['label' => 'Seed quantity', 'value' => fmt_num($planting['seed_quantity']) . ' kg', 'icon' => 'bag', 'tone' => 'blue'],
                ['label' => 'Total harvested', 'value' => fmt_kg($totals['qty'] ?? 0), 'icon' => 'basket3', 'tone' => 'gold'],
                ['label' => 'Harvest value', 'value' => fmt_money($totals['value'] ?? 0), 'icon' => 'cash-coin', 'tone' => 'rose'],
            ];
            foreach ($tiles as $tile): ?>
                <div class="col-6 col-xl-3">
                    <div class="card stat-card"><div class="stat-body py-3">
                        <div class="min-w-0">
                            <div class="stat-label"><?= e($tile['label']) ?></div>
                            <div class="fw-semibold text-truncate" style="font-size:1.05rem"><?= e($tile['value']) ?></div>
                        </div>
                        <span class="stat-icon <?= e($tile['tone']) ?>"><i class="bi bi-<?= e($tile['icon']) ?>"></i></span>
                    </div></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-basket3 me-1 text-success"></i> Harvest records</span>
                <a class="small text-success" href="<?= e(url('harvests/form.php?planting_id=' . $id)) ?>"><i class="bi bi-plus-lg"></i> Add harvest</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>Date</th><th>Grade</th><th>Remark</th><th class="text-end">Quantity</th><th class="text-end">Price / kg</th><th class="text-end">Value</th></tr>
                        </thead>
                        <tbody>
                        <?php if ($harvests === []): ?>
                            <tr><td colspan="6"><div class="empty-state"><i class="bi bi-basket"></i>No harvest records for this planting yet.</div></td></tr>
                        <?php endif; ?>
                        <?php foreach ($harvests as $row): ?>
                            <tr>
                                <td class="text-nowrap">
                                    <a class="text-dark" href="<?= e(url('harvests/view.php?id=' . (int) $row['harvest_id'])) ?>"><?= e(fmt_date($row['harvest_date'])) ?></a>
                                </td>
                                <td><?= e($row['grade_name']) ?></td>
                                <td><?= e($row['remark_description']) ?></td>
                                <td class="text-end"><?= e(fmt_kg($row['quantity_kg'])) ?></td>
                                <td class="text-end"><?= e(fmt_money($row['selling_price_per_kg'])) ?></td>
                                <td class="text-end fw-semibold"><?= e(fmt_money($row['estimated_value'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <?php if ($harvests !== []): ?>
                            <tfoot>
                                <tr class="fw-semibold">
                                    <td colspan="3" class="text-end">Total</td>
                                    <td class="text-end"><?= e(fmt_kg($totals['qty'] ?? 0)) ?></td>
                                    <td></td>
                                    <td class="text-end text-success"><?= e(fmt_money($totals['value'] ?? 0)) ?></td>
                                </tr>
                            </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Planting information</div>
            <div class="card-body">
                <dl class="detail-grid mb-0">
                    <dt>Farm</dt>
                    <dd><a href="<?= e(url('farms/view.php?id=' . (int) $planting['farm_id'])) ?>"><?= e($planting['farm_name']) ?></a></dd>
                    <dt>Farmer</dt>
                    <dd><a href="<?= e(url('farmers/view.php?id=' . (int) $planting['farmer_id'])) ?>"><?= e($planting['farmer_name']) ?></a></dd>
                    <dt>Crop</dt>
                    <dd><a href="<?= e(url('crops/view.php?id=' . (int) $planting['crop_id'])) ?>"><?= e($planting['crop_name']) ?></a>
                        <?php if ($planting['variety']): ?><span class="text-muted">(<?= e($planting['variety']) ?>)</span><?php endif; ?></dd>
                    <dt>Status</dt>
                    <dd><span class="badge <?= e(status_badge_class((string) $planting['status_name'])) ?>"><?= e($planting['status_name']) ?></span></dd>
                    <dt>Planting date</dt>
                    <dd><?= e(fmt_date($planting['planting_date'])) ?>
                        <span class="text-muted small">(<?= $daysSince >= 0 ? $daysSince . ' days ago' : 'in ' . abs($daysSince) . ' days' ?>)</span></dd>
                    <dt>Expected harvest</dt>
                    <dd><?= e(fmt_date($planting['expected_harvest_date'])) ?>
                        <?php if ($daysToGo !== null): ?>
                            <span class="text-muted small">(<?= $daysToGo >= 0 ? 'in ' . $daysToGo . ' days' : abs($daysToGo) . ' days overdue' ?>)</span>
                        <?php endif; ?></dd>
                    <dt>Harvest records</dt>
                    <dd><?= number_format((int) ($totals['records'] ?? 0)) ?></dd>
                    <dt>Record created</dt>
                    <dd class="mb-0"><?= e(fmt_datetime($planting['created_at'])) ?></dd>
                    <dt>Last updated</dt>
                    <dd class="mb-0"><?= e(fmt_datetime($planting['updated_at'])) ?></dd>
                </dl>
            </div>
            <?php if (Auth::isAdmin()): ?>
                <div class="card-footer bg-white">
                    <button type="button" class="btn btn-outline-danger btn-sm w-100"
                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                            data-delete-url="<?= e(url('plantings/delete.php?id=' . $id)) ?>"
                            data-delete-name="<?= e($planting['crop_name'] . ' on ' . $planting['farm_name'] . ' (' . fmt_date($planting['planting_date']) . ')') ?>">
                        <i class="bi bi-trash3 me-1"></i> Delete planting record
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require __DIR__ . '/../partials/delete-modal.php';
require __DIR__ . '/../partials/footer.php';
