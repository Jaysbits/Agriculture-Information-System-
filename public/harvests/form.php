<?php
declare(strict_types=1);

/**
 * VIEW — Create / edit harvest form.
 * Validation and persistence are handled by the Harvest model.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
Auth::requireLogin();

$harvestModel  = new Harvest();
$plantingModel = new Planting();

$id       = form_id();
$existing = $id > 0 ? $harvestModel->find($id) : null;

if ($id > 0 && !$existing) {
    flash('danger', 'That harvest record no longer exists.');
    redirect('harvests/index.php');
}

$plantings = $plantingModel->options();
$grades    = $harvestModel->grades();
$remarks   = $harvestModel->remarks();

$data = $existing ?? [
    'planting_id'          => int_get('planting_id', 0) ?: '',
    'grade_id'             => '',
    'remark_id'            => '1',
    'harvest_date'         => date('Y-m-d'),
    'quantity_kg'          => '',
    'selling_price_per_kg' => '',
];
$errors = [];

if (is_post()) {
    verify_csrf();

    $result = $harvestModel->save($_POST, $id);
    if ($result['ok']) {
        flash('success', $existing ? 'Harvest record updated successfully.' : 'Harvest record added successfully.');
        redirect('harvests/view.php?id=' . $result['id']);
    }

    $errors = $result['errors'];
    if ($result['dbError'] !== null) {
        $errors['_form'] = $result['dbError'];
    }
    $data = array_merge($data, $_POST);
}

$pageTitle    = $existing ? 'Edit harvest record' : 'New harvest record';
$active       = 'harvests';
$pageSubtitle = $existing ? 'Update harvest #' . $id : 'Record a harvest transaction';
$pageActions  = '<a class="btn btn-outline-secondary" href="' . e(url($existing ? 'harvests/view.php?id=' . $id : 'harvests/index.php')) . '">'
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

            <div class="form-section-title">Planting record</div>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="planting_id">Which planting does this harvest belong to? <span class="required-mark">*</span></label>
                    <select class="form-select<?= invalid_class($errors, 'planting_id') ?>" id="planting_id" name="planting_id" required>
                        <option value="">Select a planting record</option>
                        <?php foreach ($plantings as $row):
                            $label = $row['farm_name'] . ' — ' . $row['crop_name']
                                . ($row['variety'] ? ' (' . $row['variety'] . ')' : '')
                                . ' — planted ' . fmt_date($row['planting_date']);
                            ?>
                            <option value="<?= (int) $row['planting_id'] ?>"<?= selected($row['planting_id'], $data['planting_id']) ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= error_text($errors, 'planting_id') ?>
                    <div class="form-text">The harvest date must be on or after the selected planting date.</div>
                </div>
            </div>

            <div class="form-section-title mt-4">Harvest details</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="harvest_date">Harvest date <span class="required-mark">*</span></label>
                    <input type="date" class="form-control<?= invalid_class($errors, 'harvest_date') ?>"
                           id="harvest_date" name="harvest_date" value="<?= e($data['harvest_date']) ?>"
                           max="<?= date('Y-m-d') ?>" required>
                    <?= error_text($errors, 'harvest_date') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="grade_id">Quality grade <span class="required-mark">*</span></label>
                    <select class="form-select<?= invalid_class($errors, 'grade_id') ?>" id="grade_id" name="grade_id" required>
                        <?= options($grades, 'grade_id', ['grade_name'], $data['grade_id'], 'Select quality grade') ?>
                    </select>
                    <?= error_text($errors, 'grade_id') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="quantity_kg">Quantity harvested (kg) <span class="required-mark">*</span></label>
                    <input type="number" step="0.01" min="0.01" max="1000000"
                           class="form-control<?= invalid_class($errors, 'quantity_kg') ?>" id="quantity_kg"
                           name="quantity_kg" value="<?= e($data['quantity_kg']) ?>" required>
                    <?= error_text($errors, 'quantity_kg') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="selling_price_per_kg">Selling price per kg (₱) <span class="required-mark">*</span></label>
                    <input type="number" step="0.01" min="0" max="100000"
                           class="form-control<?= invalid_class($errors, 'selling_price_per_kg') ?>" id="selling_price_per_kg"
                           name="selling_price_per_kg" value="<?= e($data['selling_price_per_kg']) ?>" required>
                    <?= error_text($errors, 'selling_price_per_kg') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="remark_id">Harvest remark <span class="required-mark">*</span></label>
                    <select class="form-select<?= invalid_class($errors, 'remark_id') ?>" id="remark_id" name="remark_id" required>
                        <?= options($remarks, 'remark_id', ['remark_description'], $data['remark_id'], 'Select a remark') ?>
                    </select>
                    <?= error_text($errors, 'remark_id') ?>
                    <div class="form-text">Stored as a numeric code that points to the remark description.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Estimated value</label>
                    <div class="form-control bg-light d-flex align-items-center justify-content-between" aria-live="polite">
                        <span class="text-muted small">Quantity × price</span>
                        <strong id="value-preview" class="text-success">₱0.00</strong>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check2 me-1"></i> <?= $existing ? 'Save changes' : 'Save harvest record' ?>
                </button>
                <a class="btn btn-light" href="<?= e(url($existing ? 'harvests/view.php?id=' . $id : 'harvests/index.php')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
