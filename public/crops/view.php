<?php
declare(strict_types=1);

/**
 * VIEW — Crop detail page.
 * Reads come from the Crop model (src/Models/Crop.php).
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
Auth::requireLogin();

$cropModel = new Crop();

$id   = int_get('id', 0);
$crop = $id > 0 ? $cropModel->detail($id) : null;

if (!$crop) {
    flash('danger', 'That crop record could not be found.');
    redirect('crops/index.php');
}

$plantings = $cropModel->plantings($id);
$stats     = $cropModel->stats($id);

$pageTitle    = 'Crop details';
$active       = 'crops';
$pageSubtitle = $crop['crop_name'] . ($crop['variety'] ? ' (' . $crop['variety'] . ')' : '');
$pageActions  = '<a class="btn btn-outline-secondary" href="' . e(url('crops/index.php')) . '"><i class="bi bi-arrow-left me-1"></i> Crops</a>'
    . '<a class="btn btn-success" href="' . e(url('plantings/form.php?crop_id=' . $id)) . '"><i class="bi bi-plus-lg me-1"></i> New planting</a>'
    . '<a class="btn btn-outline-success" href="' . e(url('crops/form.php?id=' . $id)) . '"><i class="bi bi-pencil me-1"></i> Edit</a>';

require __DIR__ . '/../partials/header.php';
?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="detail-hero d-flex flex-wrap align-items-center gap-3">
            <span class="avatar"><i class="bi bi-flower1"></i></span>
            <div class="flex-grow-1 min-w-0">
                <h2 class="h4 mb-1"><?= e($crop['crop_name']) ?></h2>
                <div class="small text-muted">
                    <i class="bi bi-tag me-1"></i><?= e($crop['category_name']) ?>
                    <span class="mx-2">|</span>
                    <i class="bi bi-cloud-sun me-1"></i><?= e($crop['season_name'] ?? 'Season not specified') ?>
                </div>
            </div>
            <div class="text-md-end small text-muted">
                <div>Growing period</div>
                <div class="text-white fw-semibold"><?= number_format((int) $crop['growing_period_days']) ?> days</div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <?php
            $tiles = [
                ['label' => 'Farms planting', 'value' => number_format((int) ($stats['farms'] ?? 0)), 'icon' => 'geo-alt', 'tone' => ''],
                ['label' => 'Plantings',       'value' => number_format((int) ($stats['plantings'] ?? 0)), 'icon' => 'calendar-check', 'tone' => 'blue'],
                ['label' => 'Total harvested', 'value' => fmt_kg($stats['qty'] ?? 0), 'icon' => 'basket3', 'tone' => 'gold'],
                ['label' => 'Harvest value',   'value' => fmt_money($stats['value'] ?? 0), 'icon' => 'cash-coin', 'tone' => 'rose'],
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
            <div class="card-header"><i class="bi bi-calendar-check me-1 text-success"></i> Planting records for this crop</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Farm</th><th>Farmer</th><th>Planted</th><th>Status</th><th class="text-end">Area</th></tr></thead>
                        <tbody>
                        <?php if ($plantings === []): ?>
                            <tr><td colspan="5"><div class="empty-state"><i class="bi bi-calendar-plus"></i>This crop has not been planted yet.</div></td></tr>
                        <?php endif; ?>
                        <?php foreach ($plantings as $row): ?>
                            <tr>
                                <td><a class="fw-semibold text-dark" href="<?= e(url('farms/view.php?id=' . (int) $row['farm_id'])) ?>"><?= e($row['farm_name']) ?></a></td>
                                <td><?= e($row['farmer_name']) ?></td>
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

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Crop information</div>
            <div class="card-body">
                <dl class="detail-grid mb-0">
                    <dt>Crop name</dt>
                    <dd><?= e($crop['crop_name']) ?></dd>
                    <dt>Variety</dt>
                    <dd><?= e($crop['variety'] ?? '—') ?></dd>
                    <dt>Category</dt>
                    <dd><?= e($crop['category_name']) ?></dd>
                    <dt>Season</dt>
                    <dd><?= e($crop['season_name'] ?? '—') ?></dd>
                    <dt>Growing period</dt>
                    <dd><?= number_format((int) $crop['growing_period_days']) ?> days</dd>
                    <dt>Harvest records</dt>
                    <dd><?= number_format((int) ($stats['harvests'] ?? 0)) ?></dd>
                    <dt>Record created</dt>
                    <dd class="mb-0"><?= e(fmt_datetime($crop['created_at'])) ?></dd>
                    <dt>Last updated</dt>
                    <dd class="mb-0"><?= e(fmt_datetime($crop['updated_at'])) ?></dd>
                </dl>
            </div>
            <?php if (Auth::isAdmin()): ?>
                <div class="card-footer bg-white">
                    <button type="button" class="btn btn-outline-danger btn-sm w-100"
                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                            data-delete-url="<?= e(url('crops/delete.php?id=' . $id)) ?>"
                            data-delete-name="<?= e($crop['crop_name']) ?>">
                        <i class="bi bi-trash3 me-1"></i> Delete crop
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require __DIR__ . '/../partials/delete-modal.php';
require __DIR__ . '/../partials/footer.php';
