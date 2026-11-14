<?php
declare(strict_types=1);

/**
 * VIEW — Create / edit planting form.
 * Validation and persistence are handled by the Planting model.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
Auth::requireLogin();

$id       = form_id();
$existing = $id > 0 ? Planting::find($id) : null;

if ($id > 0 && !$existing) {
    flash('danger', 'That planting record no longer exists.');
    redirect('plantings/index.php');
}

$farms    = Farm::options();
$crops    = Crop::options();
$statuses = Planting::statuses();

$data = $existing ?? [
    'farm_id'               => int_get('farm_id', 0) ?: '',
    'crop_id'               => int_get('crop_id', 0) ?: '',
    'status_id'             => '',
    'planting_date'         => date('Y-m-d'),
    'area_planted'          => '',
    'seed_quantity'         => '0',
    'expected_harvest_date' => '',
];
$errors = [];

if (is_post()) {
    verify_csrf();

    $result = Planting::save($_POST, $id);
    if ($result['ok']) {
        flash('success', $existing ? 'Planting record updated successfully.' : 'Planting record added successfully.');
        redirect('plantings/view.php?id=' . $result['id']);
    }

    $errors = $result['errors'];
    if ($result['dbError'] !== null) {
        $errors['_form'] = $result['dbError'];
    }
    $data = array_merge($data, $_POST);
}

$pageTitle    = $existing ? 'Edit planting record' : 'New planting record';
$active       = 'plantings';
$pageSubtitle = $existing ? 'Update planting #' . $id : 'Record a crop planting activity';
$pageActions  = '<a class="btn btn-outline-secondary" href="' . e(url($existing ? 'plantings/view.php?id=' . $id : 'plantings/index.php')) . '">'
    . '<i class="bi bi-arrow-left me-1"></i> Back</a>';

require __DIR__ . '/../partials/header.php';
?>

<div class="card form-card">
    <div class="card-body p-4">
        <?php if (isset($errors['_form'])): ?>
            <div class="alert alert-danger d-flex gap-2">
                <i class="bi bi-exclamation-octagon-fill"></i>
                <div><?= e($errors['_form']) ?></div>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= csrf_field() ?>
            <?php if ($id > 0): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

            <div class="form-section-title">Where and what</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="farm_id">Farm <span class="required-mark">*</span></label>
                    <select class="form-select<?= invalid_class($errors, 'farm_id') ?>" id="farm_id" name="farm_id" required>
                        <?= options($farms, 'farm_id', ['farm_name', 'last_name'], $data['farm_id'], 'Select a farm') ?>
                    </select>
                    <?= error_text($errors, 'farm_id') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="crop_id">Crop <span class="required-mark">*</span></label>
                    <select class="form-select<?= invalid_class($errors, 'crop_id') ?>" id="crop_id" name="crop_id" required>
                        <?= options($crops, 'crop_id', ['crop_name', 'variety'], $data['crop_id'], 'Select a crop') ?>
                    </select>
                    <?= error_text($errors, 'crop_id') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="status_id">Planting status <span class="required-mark">*</span></label>
                    <select class="form-select<?= invalid_class($errors, 'status_id') ?>" id="status_id" name="status_id" required>
                        <?= options($statuses, 'status_id', ['status_name'], $data['status_id'], 'Select status') ?>
                    </select>
                    <?= error_text($errors, 'status_id') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="planting_date">Planting date <span class="required-mark">*</span></label>
                    <input type="date" class="form-control<?= invalid_class($errors, 'planting_date') ?>"
                           id="planting_date" name="planting_date" value="<?= e($data['planting_date']) ?>" required>
                    <?= error_text($errors, 'planting_date') ?>
                </div>
            </div>

            <div class="form-section-title mt-4">Area and expected harvest</div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="area_planted">Area planted (hectares) <span class="required-mark">*</span></label>
                    <input type="number" step="0.01" min="0.01" max="10000"
                           class="form-control<?= invalid_class($errors, 'area_planted') ?>" id="area_planted"
                           name="area_planted" value="<?= e($data['area_planted']) ?>" required>
                    <?= error_text($errors, 'area_planted') ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="seed_quantity">Seed quantity (kg)</label>
                    <input type="number" step="0.01" min="0" max="1000000"
                           class="form-control<?= invalid_class($errors, 'seed_quantity') ?>" id="seed_quantity"
                           name="seed_quantity" value="<?= e($data['seed_quantity']) ?>">
                    <?= error_text($errors, 'seed_quantity') ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="expected_harvest_date">Expected harvest date</label>
                    <input type="date" class="form-control<?= invalid_class($errors, 'expected_harvest_date') ?>"
                           id="expected_harvest_date" name="expected_harvest_date"
                           value="<?= e($data['expected_harvest_date']) ?>">
                    <?= error_text($errors, 'expected_harvest_date') ?>
                    <div class="form-text">Optional. Must be on or after the planting date.</div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check2 me-1"></i> <?= $existing ? 'Save changes' : 'Save planting record' ?>
                </button>
                <a class="btn btn-light" href="<?= e(url($existing ? 'plantings/view.php?id=' . $id : 'plantings/index.php')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
