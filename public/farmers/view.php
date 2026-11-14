<?php
declare(strict_types=1);

/**
 * VIEW — Farmer detail page.
 * Reads come from the Farmer model (src/Models/Farmer.php).
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
Auth::requireLogin();

$farmerModel = new Farmer();

$id = int_get('id', 0);
$farmer = $id > 0 ? $farmerModel->find($id) : null;

if (!$farmer) {
    flash('danger', 'That farmer record could not be found.');
    redirect('farmers/index.php');
}

$farms = $farmerModel->farms($id);
$stats = $farmerModel->stats($id);

$pageTitle    = 'Farmer details';
$active       = 'farmers';
$pageSubtitle = $farmer['first_name'] . ' ' . $farmer['last_name'];
$pageActions  = '<a class="btn btn-outline-secondary" href="' . e(url('farmers/index.php')) . '"><i class="bi bi-arrow-left me-1"></i> Farmers</a>'
    . '<a class="btn btn-success" href="' . e(url('farmers/form.php?id=' . $id)) . '"><i class="bi bi-pencil me-1"></i> Edit</a>';

require __DIR__ . '/../partials/header.php';
?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="detail-hero d-flex flex-wrap align-items-center gap-3">
            <span class="avatar"><?= e(initials($farmer['first_name'] . ' ' . $farmer['last_name'])) ?></span>
            <div class="flex-grow-1 min-w-0">
                <h2 class="h4 mb-1"><?= e($farmer['first_name'] . ' ' . $farmer['last_name']) ?></h2>
                <div class="small text-muted">
                    <i class="bi bi-telephone me-1"></i><?= e($farmer['contact_number']) ?>
                    <span class="mx-2">|</span>
                    <i class="bi bi-geo-alt me-1"></i><?= e($farmer['address']) ?>
                </div>
            </div>
            <div class="text-md-end small text-muted">
                <div>Registered</div>
                <div class="text-white fw-semibold"><?= e(fmt_date($farmer['date_registered'])) ?></div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-6 col-xl-3">
                <div class="card stat-card"><div class="stat-body py-3">
                    <div><div class="stat-label">Farms</div><div class="stat-value"><?= number_format((int) ($stats['farms'] ?? 0)) ?></div></div>
                    <span class="stat-icon"><i class="bi bi-geo-alt"></i></span>
                </div></div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card stat-card"><div class="stat-body py-3">
                    <div><div class="stat-label">Total area</div><div class="stat-value" style="font-size:1.25rem"><?= e(fmt_ha($stats['area'] ?? 0)) ?></div></div>
                    <span class="stat-icon blue"><i class="bi bi-rulers"></i></span>
                </div></div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card stat-card"><div class="stat-body py-3">
                    <div><div class="stat-label">Plantings</div><div class="stat-value"><?= number_format((int) ($stats['plantings'] ?? 0)) ?></div></div>
                    <span class="stat-icon gold"><i class="bi bi-calendar-check"></i></span>
                </div></div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card stat-card"><div class="stat-body py-3">
                    <div><div class="stat-label">Harvested</div><div class="stat-value" style="font-size:1.25rem"><?= e(fmt_kg($stats['harvested'] ?? 0)) ?></div></div>
                    <span class="stat-icon rose"><i class="bi bi-basket3"></i></span>
                </div></div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-geo-alt me-1 text-success"></i> Farms of this farmer</span>
                <a class="small text-success" href="<?= e(url('farms/form.php?farmer_id=' . $id)) ?>">
                    <i class="bi bi-plus-lg"></i> Add farm
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>Farm</th><th>Soil</th><th>Irrigation</th><th class="text-end">Area</th><th class="text-end">Plantings</th><th class="text-end">Harvested</th></tr>
                        </thead>
                        <tbody>
                        <?php if ($farms === []): ?>
                            <tr><td colspan="6"><div class="empty-state"><i class="bi bi-geo-alt"></i>No farms registered for this farmer yet.</div></td></tr>
                        <?php endif; ?>
                        <?php foreach ($farms as $farm): ?>
                            <tr>
                                <td>
                                    <a class="fw-semibold text-dark" href="<?= e(url('farms/view.php?id=' . (int) $farm['farm_id'])) ?>"><?= e($farm['farm_name']) ?></a>
                                    <div class="small text-muted"><?= e($farm['location']) ?></div>
                                </td>
                                <td><?= e($farm['soil_type_name']) ?></td>
                                <td><?= e($farm['irrigation_name']) ?></td>
                                <td class="text-end"><?= e(fmt_ha($farm['area_hectares'])) ?></td>
                                <td class="text-end"><?= number_format((int) $farm['plantings']) ?></td>
                                <td class="text-end"><?= e(fmt_kg($farm['harvested'])) ?></td>
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
            <div class="card-header">Farmer information</div>
            <div class="card-body">
                <dl class="detail-grid mb-0">
                    <dt>Full name</dt>
                    <dd><?= e($farmer['first_name'] . ' ' . $farmer['last_name']) ?></dd>
                    <dt>Contact number</dt>
                    <dd><?= e($farmer['contact_number']) ?></dd>
                    <dt>Address</dt>
                    <dd><?= e($farmer['address']) ?></dd>
                    <dt>Date registered</dt>
                    <dd><?= e(fmt_date($farmer['date_registered'])) ?></dd>
                    <dt>Estimated harvest value</dt>
                    <dd class="text-success"><?= e(fmt_money($stats['value'] ?? 0)) ?></dd>
                    <dt>Record created</dt>
                    <dd class="mb-0"><?= e(fmt_datetime($farmer['created_at'])) ?></dd>
                    <dt>Last updated</dt>
                    <dd class="mb-0"><?= e(fmt_datetime($farmer['updated_at'])) ?></dd>
                </dl>
            </div>
            <?php if (Auth::isAdmin()): ?>
                <div class="card-footer bg-white">
                    <button type="button" class="btn btn-outline-danger btn-sm w-100"
                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                            data-delete-url="<?= e(url('farmers/delete.php?id=' . $id)) ?>"
                            data-delete-name="<?= e($farmer['first_name'] . ' ' . $farmer['last_name']) ?>">
                        <i class="bi bi-trash3 me-1"></i> Delete farmer
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require __DIR__ . '/../partials/delete-modal.php';
require __DIR__ . '/../partials/footer.php';
