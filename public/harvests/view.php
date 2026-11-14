<?php
declare(strict_types=1);

/**
 * VIEW — Harvest detail page.
 * Reads come from the Harvest model (src/Models/Harvest.php).
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
Auth::requireLogin();

$harvestModel = new Harvest(new Database());

$id      = int_get('id', 0);
$harvest = $id > 0 ? $harvestModel->detail($id) : null;

if (!$harvest) {
    flash('danger', 'That harvest record could not be found.');
    redirect('harvests/index.php');
}

$siblings = $harvestModel->siblings((int) $harvest['planting_id'], $id);
$plantingTotals = $harvestModel->plantingTotals((int) $harvest['planting_id']);

$pageTitle    = 'Harvest details';
$active       = 'harvests';
$pageSubtitle = $harvest['crop_name'] . ' — ' . fmt_date($harvest['harvest_date']);
$pageActions  = '<a class="btn btn-outline-secondary" href="' . e(url('harvests/index.php')) . '"><i class="bi bi-arrow-left me-1"></i> Harvests</a>'
    . '<a class="btn btn-outline-success" href="' . e(url('plantings/view.php?id=' . (int) $harvest['planting_id'])) . '"><i class="bi bi-calendar-check me-1"></i> Planting</a>'
    . '<a class="btn btn-success" href="' . e(url('harvests/form.php?id=' . $id)) . '"><i class="bi bi-pencil me-1"></i> Edit</a>';

require __DIR__ . '/../partials/header.php';
?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="detail-hero d-flex flex-wrap align-items-center gap-3">
            <span class="avatar"><i class="bi bi-basket3"></i></span>
            <div class="flex-grow-1 min-w-0">
                <h2 class="h4 mb-1"><?= e(fmt_kg($harvest['quantity_kg'])) ?> of <?= e($harvest['crop_name']) ?></h2>
                <div class="small text-muted">
                    <a class="link-light" href="<?= e(url('farms/view.php?id=' . (int) $harvest['farm_id'])) ?>"><?= e($harvest['farm_name']) ?></a>
                    <span class="mx-2">|</span>
                    <a class="link-light" href="<?= e(url('farmers/view.php?id=' . (int) $harvest['farmer_id'])) ?>"><?= e($harvest['farmer_name']) ?></a>
                    <span class="mx-2">|</span>
                    <?= e(fmt_date($harvest['harvest_date'])) ?>
                </div>
            </div>
            <div class="text-md-end small text-muted">
                <div>Estimated value</div>
                <div class="text-white fw-semibold"><?= e(fmt_money($harvest['estimated_value'])) ?></div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <?php
            $tiles = [
                ['label' => 'Quantity',      'value' => fmt_kg($harvest['quantity_kg']), 'icon' => 'box-seam', 'tone' => ''],
                ['label' => 'Price per kg',  'value' => fmt_money($harvest['selling_price_per_kg']), 'icon' => 'tag', 'tone' => 'blue'],
                ['label' => 'Quality grade', 'value' => $harvest['grade_name'], 'icon' => 'patch-check', 'tone' => 'gold'],
                ['label' => 'Remark',        'value' => $harvest['remark_description'], 'icon' => 'chat-left-text', 'tone' => 'rose'],
            ];
            foreach ($tiles as $tile): ?>
                <div class="col-6 col-xl-3">
                    <div class="card stat-card"><div class="stat-body py-3">
                        <div class="min-w-0">
                            <div class="stat-label"><?= e($tile['label']) ?></div>
                            <div class="fw-semibold text-truncate" style="font-size:1rem"><?= e($tile['value']) ?></div>
                        </div>
                        <span class="stat-icon <?= e($tile['tone']) ?>"><i class="bi bi-<?= e($tile['icon']) ?>"></i></span>
                    </div></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-link-45deg me-1 text-success"></i> Other harvests from the same planting</span>
                <span class="small text-muted"><?= number_format((int) ($plantingTotals['records'] ?? 0)) ?> total record(s)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Date</th><th>Grade</th><th class="text-end">Quantity</th><th></th></tr></thead>
                        <tbody>
                        <?php if ($siblings === []): ?>
                            <tr><td colspan="4"><div class="empty-state"><i class="bi bi-basket"></i>No other harvest records for this planting.</div></td></tr>
                        <?php endif; ?>
                        <?php foreach ($siblings as $row): ?>
                            <tr>
                                <td class="text-nowrap"><a class="text-dark" href="<?= e(url('harvests/view.php?id=' . (int) $row['harvest_id'])) ?>"><?= e(fmt_date($row['harvest_date'])) ?></a></td>
                                <td><?= e($row['grade_name']) ?></td>
                                <td class="text-end"><?= e(fmt_kg($row['quantity_kg'])) ?></td>
                                <td class="text-end no-print"><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('harvests/view.php?id=' . (int) $row['harvest_id'])) ?>"><i class="bi bi-eye"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="fw-semibold">
                                <td colspan="2" class="text-end">Planting total</td>
                                <td class="text-end"><?= e(fmt_kg($plantingTotals['qty'] ?? 0)) ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Harvest information</div>
            <div class="card-body">
                <dl class="detail-grid mb-0">
                    <dt>Crop</dt>
                    <dd><a href="<?= e(url('crops/view.php?id=' . (int) $harvest['crop_id'])) ?>"><?= e($harvest['crop_name']) ?></a>
                        <?php if ($harvest['variety']): ?><span class="text-muted">(<?= e($harvest['variety']) ?>)</span><?php endif; ?></dd>
                    <dt>Farm</dt>
                    <dd><a href="<?= e(url('farms/view.php?id=' . (int) $harvest['farm_id'])) ?>"><?= e($harvest['farm_name']) ?></a></dd>
                    <dt>Farmer</dt>
                    <dd><a href="<?= e(url('farmers/view.php?id=' . (int) $harvest['farmer_id'])) ?>"><?= e($harvest['farmer_name']) ?></a></dd>
                    <dt>Planting</dt>
                    <dd><a href="<?= e(url('plantings/view.php?id=' . (int) $harvest['planting_id'])) ?>">Planted <?= e(fmt_date($harvest['planting_date'])) ?></a></dd>
                    <dt>Harvest date</dt>
                    <dd><?= e(fmt_date($harvest['harvest_date'])) ?></dd>
                    <dt>Quantity × price</dt>
                    <dd><?= e(fmt_num($harvest['quantity_kg'])) ?> kg × <?= e(fmt_money($harvest['selling_price_per_kg'])) ?></dd>
                    <dt>Estimated value</dt>
                    <dd class="text-success fw-semibold"><?= e(fmt_money($harvest['estimated_value'])) ?></dd>
                    <dt>Recorded</dt>
                    <dd class="mb-0"><?= e(fmt_datetime($harvest['created_at'])) ?></dd>
                    <dt>Last updated</dt>
                    <dd class="mb-0"><?= e(fmt_datetime($harvest['updated_at'])) ?></dd>
                </dl>
            </div>
            <?php if (Auth::isAdmin()): ?>
                <div class="card-footer bg-white">
                    <button type="button" class="btn btn-outline-danger btn-sm w-100"
                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                            data-delete-url="<?= e(url('harvests/delete.php?id=' . $id)) ?>"
                            data-delete-name="<?= e('Harvest of ' . $harvest['crop_name'] . ' on ' . fmt_date($harvest['harvest_date'])) ?>">
                        <i class="bi bi-trash3 me-1"></i> Delete harvest record
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require __DIR__ . '/../partials/delete-modal.php';
require __DIR__ . '/../partials/footer.php';









