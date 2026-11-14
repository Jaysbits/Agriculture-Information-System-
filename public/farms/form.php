<?php
declare(strict_types=1);

/**
 * VIEW — Create / edit farm form.
 * Validation and persistence are handled by the Farm model.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
Auth::requireLogin();

$farmModel   = new Farm(new Database());
$farmerModel = new Farmer(new Database());

$id  = form_id();
$prefillFarmer = int_get('farmer_id', 0);

$existing = $id > 0 ? $farmModel->find($id) : null;
if ($id > 0 && !$existing) {
    flash('danger', 'That farm record no longer exists.');
    redirect('farms/index.php');
}

$farmers         = $farmerModel->options();
$soilTypes       = $farmModel->soilTypes();
$irrigationTypes = $farmModel->irrigationTypes();

$data = $existing ?? [
    'farmer_id'     => $prefillFarmer ?: '',
    'soil_type_id'  => '',
    'irrigation_id' => '',
    'farm_name'     => '',
    'location'      => '',
    'area_hectares' => '',
];
$errors = [];

$plantedArea = $existing ? $farmModel->plantedArea($id) : 0.0;

if (is_post()) {
    verify_csrf();

    $result = $farmModel->save($_POST, $id);
    if ($result['ok']) {
        flash('success', $existing ? 'Farm updated successfully.' : 'Farm registered successfully.');
        redirect('farms/view.php?id=' . $result['id']);
    }

    $errors = $result['errors'];
    if ($result['dbError'] !== null) {
        $errors['_form'] = $result['dbError'];
    }
    $data = array_merge($data, $_POST);
}

$pageTitle    = $existing ? 'Edit farm' : 'New farm';
$active       = 'farms';
$pageSubtitle = $existing ? 'Update ' . $existing['farm_name'] : 'Register a farm holding for a farmer';
$pageActions  = '<a class="btn btn-outline-secondary" href="' . e(url($existing ? 'farms/view.php?id=' . $id : 'farms/index.php')) . '">'
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

        <?php if ($existing && $plantedArea > 0): ?>
            <div class="alert alert-info py-2 small d-flex gap-2">
                <i class="bi bi-info-circle-fill"></i>
                <div><?= e(fmt_num($plantedArea)) ?> ha of this farm is already planted. The farm size cannot be set below that area.</div>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= csrf_field() ?>
            <?php if ($id > 0): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

            <div class="form-section-title">Ownership &amp; location</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="farmer_id">Farmer <span class="required-mark">*</span></label>
                    <select class="form-select<?= invalid_class($errors, 'farmer_id') ?>" id="farmer_id" name="farmer_id" required>
                        <?= options($farmers, 'farmer_id', ['last_name', 'first_name'], $data['farmer_id'], 'Select a farmer') ?>
                    </select>
                    <?= error_text($errors, 'farmer_id') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="farm_name">Farm name <span class="required-mark">*</span></label>
                    <input class="form-control<?= invalid_class($errors, 'farm_name') ?>" id="farm_name" name="farm_name"
                           value="<?= e($data['farm_name']) ?>" maxlength="150" placeholder="e.g. North Field" required>
                    <?= error_text($errors, 'farm_name') ?>
                </div>
                <div class="col-12">
                    <label class="form-label" for="location">Location <span class="required-mark">*</span></label>
                    <input class="form-control<?= invalid_class($errors, 'location') ?>" id="location" name="location"
                           value="<?= e($data['location']) ?>" maxlength="255"
                           placeholder="Barangay, City/Municipality, Province" required>
                    <?= error_text($errors, 'location') ?>
                </div>
            </div>

            <div class="form-section-title mt-4">Farm characteristics</div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="soil_type_id">Soil type <span class="required-mark">*</span></label>
                    <select class="form-select<?= invalid_class($errors, 'soil_type_id') ?>" id="soil_type_id" name="soil_type_id" required>
                        <?= options($soilTypes, 'soil_type_id', ['soil_type_name'], $data['soil_type_id'], 'Select soil type') ?>
                    </select>
                    <?= error_text($errors, 'soil_type_id') ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="irrigation_id">Irrigation type <span class="required-mark">*</span></label>
                    <select class="form-select<?= invalid_class($errors, 'irrigation_id') ?>" id="irrigation_id" name="irrigation_id" required>
                        <?= options($irrigationTypes, 'irrigation_id', ['irrigation_name'], $data['irrigation_id'], 'Select irrigation type') ?>
                    </select>
                    <?= error_text($errors, 'irrigation_id') ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="area_hectares">Farm size (hectares) <span class="required-mark">*</span></label>
                    <input type="number" step="0.01" min="0.01" max="10000"
                           class="form-control<?= invalid_class($errors, 'area_hectares') ?>" id="area_hectares" name="area_hectares"
                           value="<?= e($data['area_hectares']) ?>" required>
                    <?= error_text($errors, 'area_hectares') ?>
                    <div class="form-text">Between 0.01 and 10,000 hectares.</div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check2 me-1"></i> <?= $existing ? 'Save changes' : 'Register farm' ?>
                </button>
                <a class="btn btn-light" href="<?= e(url($existing ? 'farms/view.php?id=' . $id : 'farms/index.php')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>









