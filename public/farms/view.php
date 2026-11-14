<?php
declare(strict_types=1);

/**
 * VIEW — Farm detail page.
 * Reads come from the Farm model (src/Models/Farm.php).
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
Auth::requireLogin();

$id = int_get('id', 0);

$farm = $id > 0 ? Farm::detail($id) : null;

if (!$farm) {
    flash('danger', 'That farm record could not be found.');
    redirect('farms/index.php');
}

$plantings = Farm::plantings($id);
$stats     = Farm::stats($id);

$pageTitle    = 'Farm details';
$active       = 'farms';
$pageSubtitle = $farm['farm_name'];
$pageActions  = '<a class="btn btn-outline-secondary" href="' . e(url('farms/index.php')) . '"><i class="bi bi-arrow-left me-1"></i> Farms</a>'
    . '<a class="btn btn-success" href="' . e(url('plantings/form.php?farm_id=' . $id)) . '"><i class="bi bi-plus-lg me-1"></i> New planting</a>'
    . '<a class="btn btn-outline-success" href="' . e(url('farms/form.php?id=' . $id)) . '"><i class="bi bi-pencil me-1"></i> Edit</a>';

require __DIR__ . '/../partials/header.php';
?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="detail-hero d-flex flex-wrap align-items-center gap-3">
            <span class="avatar"><i class="bi bi-geo-alt"></i></span>
            <div class="flex-grow-1 min-w-0">
                <h2 class="h4 mb-1"><?= e($farm['farm_name']) ?></h2>
                <div class="small text-muted">
                    <i class="bi bi-person me-1"></i>
                    <a class="link-light" href="<?= e(url('farmers/view.php?id=' . (int) $farm['farmer_id'])) ?>">
                        <?= e($farm['first_name'] . ' ' . $farm['last_name']) ?>
                    </a>
                    <span class="mx-2">|</span>
                    <i class="bi bi-pin-map me-1"></i><?= e($farm['location']) ?>
                </div>
            </div>
            <div class="text-md-end small text-muted">
                <div>Farm size</div>
                <div class="text-white fw-semibold"><?= e(fmt_ha($farm['area_hectares'])) ?></div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <?php
            $tiles = [
                ['label' => 'Soil type',     'value' => $farm['soil_type_name'],  'icon' => 'layers',       'tone' => ''],
                ['label' => 'Irrigation',    'value' => $farm['irrigation_name'], 'icon' => 'droplet',      'tone' => 'blue'],
                ['label' => 'Planted area',  'value' => fmt_ha($stats['planted_area'] ?? 0), 'icon' => 'rulers', 'tone' => 'gold'],
                ['label' => 'Harvest value', 'value' => fmt_money($stats['value'] ?? 0), 'icon' => 'cash-coin', 'tone' => 'rose'],
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
                <span><i class="bi bi-calendar-check me-1 text-success"></i> Planting records on this farm</span>
                <span class="small text-muted"><?= number_format((int) ($stats['plantings'] ?? 0)) ?> total</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>Crop</th><th>Planted</th><th>Expected harvest</th><th>Status</th><th class="text-end">Area</th></tr>
                        </thead>
                        <tbody>
                        <?php if ($plantings === []): ?>
                            <tr><td colspan="5"><div class="empty-state"><i class="bi bi-calendar-plus"></i>No planting records for this farm yet.<div class="mt-2"><a href="<?= e(url('plantings/form.php?farm_id=' . $id)) ?>">Add a planting record</a></div></div></td></tr>
                        <?php endif; ?>
                        <?php foreach ($plantings as $row): ?>
                            <tr>
                                <td>
                                    <a class="fw-semibold text-dark" href="<?= e(url('plantings/view.php?id=' . (int) $row['planting_id'])) ?>"><?= e($row['crop_name']) ?></a>
                                    <?php if ($row['variety']): ?><div class="small text-muted"><?= e($row['variety']) ?></div><?php endif; ?>
                                </td>
                                <td class="text-nowrap"><?= e(fmt_date($row['planting_date'])) ?></td>
                                <td class="text-nowrap"><?= e(fmt_date($row['expected_harvest_date'])) ?></td>
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
            <div class="card-header">Farm details</div>
            <div class="card-body">
                <dl class="detail-grid mb-0">
                    <dt>Farmer</dt>
                    <dd><a href="<?= e(url('farmers/view.php?id=' . (int) $farm['farmer_id'])) ?>"><?= e($farm['first_name'] . ' ' . $farm['last_name']) ?></a></dd>
                    <dt>Contact</dt>
                    <dd><?= e($farm['contact_number']) ?></dd>
                    <dt>Location</dt>
                    <dd><?= e($farm['location']) ?></dd>
                    <dt>Farm size</dt>
                    <dd><?= e(fmt_ha($farm['area_hectares'])) ?></dd>
                    <dt>Soil / irrigation</dt>
                    <dd><?= e($farm['soil_type_name']) ?> &middot; <?= e($farm['irrigation_name']) ?></dd>
                    <dt>Total harvested</dt>
                    <dd class="text-success"><?= e(fmt_kg($stats['harvested'] ?? 0)) ?></dd>
                    <dt>Record created</dt>
                    <dd class="mb-0"><?= e(fmt_datetime($farm['created_at'])) ?></dd>
                    <dt>Last updated</dt>
                    <dd class="mb-0"><?= e(fmt_datetime($farm['updated_at'])) ?></dd>
                </dl>
            </div>
            <?php if (Auth::isAdmin()): ?>
                <div class="card-footer bg-white">
                    <button type="button" class="btn btn-outline-danger btn-sm w-100"
                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                            data-delete-url="<?= e(url('farms/delete.php?id=' . $id)) ?>"
                            data-delete-name="<?= e($farm['farm_name']) ?>">
                        <i class="bi bi-trash3 me-1"></i> Delete farm
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require __DIR__ . '/../partials/delete-modal.php';
require __DIR__ . '/../partials/footer.php';
