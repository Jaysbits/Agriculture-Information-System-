<?php
declare(strict_types=1);

/**
 * VIEW — Create / edit crop form.
 * Validation and persistence are handled by the Crop model.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
Auth::requireLogin();

$cropModel = new Crop();

$id       = form_id();
$existing = $id > 0 ? $cropModel->find($id) : null;

if ($id > 0 && !$existing) {
    flash('danger', 'That crop record no longer exists.');
    redirect('crops/index.php');
}

$categories = $cropModel->categories();
$seasons    = $cropModel->seasons();

$data = $existing ?? [
    'category_id'         => '',
    'season_id'           => '',
    'crop_name'           => '',
    'variety'             => '',
    'growing_period_days' => '',
];
$errors = [];

if (is_post()) {
    verify_csrf();

    $result = $cropModel->save($_POST, $id);
    if ($result['ok']) {
        flash('success', $existing ? 'Crop updated successfully.' : 'Crop added successfully.');
        redirect('crops/view.php?id=' . $result['id']);
    }

    $errors = $result['errors'];
    if ($result['dbError'] !== null) {
        $errors['_form'] = $result['dbError'];
    }
    $data = array_merge($data, $_POST);
}

$pageTitle    = $existing ? 'Edit crop' : 'New crop';
$active       = 'crops';
$pageSubtitle = $existing ? 'Update ' . $existing['crop_name'] : 'Add a crop to the master list';
$pageActions  = '<a class="btn btn-outline-secondary" href="' . e(url($existing ? 'crops/view.php?id=' . $id : 'crops/index.php')) . '">'
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

            <div class="form-section-title">Crop information</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="crop_name">Crop name <span class="required-mark">*</span></label>
                    <input class="form-control<?= invalid_class($errors, 'crop_name') ?>" id="crop_name" name="crop_name"
                           value="<?= e($data['crop_name']) ?>" maxlength="150" placeholder="e.g. Rice" required>
                    <?= error_text($errors, 'crop_name') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="variety">Variety</label>
                    <input class="form-control<?= invalid_class($errors, 'variety') ?>" id="variety" name="variety"
                           value="<?= e($data['variety']) ?>" maxlength="100" placeholder="e.g. NSIC Rc 222">
                    <?= error_text($errors, 'variety') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="category_id">Category <span class="required-mark">*</span></label>
                    <select class="form-select<?= invalid_class($errors, 'category_id') ?>" id="category_id" name="category_id" required>
                        <?= options($categories, 'category_id', ['category_name'], $data['category_id'], 'Select category') ?>
                    </select>
                    <?= error_text($errors, 'category_id') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="season_id">Season</label>
                    <select class="form-select<?= invalid_class($errors, 'season_id') ?>" id="season_id" name="season_id">
                        <?= options($seasons, 'season_id', ['season_name'], $data['season_id'], 'Not specified') ?>
                    </select>
                    <?= error_text($errors, 'season_id') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="growing_period_days">Growing period (days) <span class="required-mark">*</span></label>
                    <input type="number" min="1" max="3650" step="1"
                           class="form-control<?= invalid_class($errors, 'growing_period_days') ?>"
                           id="growing_period_days" name="growing_period_days"
                           value="<?= e($data['growing_period_days']) ?>" required>
                    <?= error_text($errors, 'growing_period_days') ?>
                    <div class="form-text">Typical days from planting to harvest (1–3650).</div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check2 me-1"></i> <?= $existing ? 'Save changes' : 'Add crop' ?>
                </button>
                <a class="btn btn-light" href="<?= e(url($existing ? 'crops/view.php?id=' . $id : 'crops/index.php')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
