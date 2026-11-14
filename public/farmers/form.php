<?php
declare(strict_types=1);

/**
 * VIEW — Create / edit farmer form.
 * Validation and persistence are handled by the Farmer model.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
Auth::requireLogin();

$farmerModel = new Farmer();

$id       = form_id();
$existing = $id > 0 ? $farmerModel->find($id) : null;

if ($id > 0 && !$existing) {
    flash('danger', 'That farmer record no longer exists.');
    redirect('farmers/index.php');
}

$data = $existing ?? [
    'first_name'      => '',
    'last_name'       => '',
    'contact_number'  => '',
    'address'         => '',
    'date_registered' => date('Y-m-d'),
];
$errors = [];

if (is_post()) {
    verify_csrf();

    $result = $farmerModel->save($_POST, $id);
    if ($result['ok']) {
        flash('success', $existing ? 'Farmer updated successfully.' : 'Farmer registered successfully.');
        redirect('farmers/view.php?id=' . $result['id']);
    }

    $errors = $result['errors'];
    if ($result['dbError'] !== null) {
        $errors['_form'] = $result['dbError'];
    }
    $data = array_merge($data, $_POST);
}

$pageTitle    = $existing ? 'Edit farmer' : 'New farmer';
$active       = 'farmers';
$pageSubtitle = $existing
    ? 'Update the details of ' . $existing['first_name'] . ' ' . $existing['last_name']
    : 'Register a farmer in the system';
$pageActions  = '<a class="btn btn-outline-secondary" href="' . e(url($existing ? 'farmers/view.php?id=' . $id : 'farmers/index.php')) . '">'
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

            <div class="form-section-title">Personal information</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="first_name">First name <span class="required-mark">*</span></label>
                    <input class="form-control<?= invalid_class($errors, 'first_name') ?>" id="first_name" name="first_name"
                           value="<?= e($data['first_name']) ?>" maxlength="100" required>
                    <?= error_text($errors, 'first_name') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="last_name">Last name <span class="required-mark">*</span></label>
                    <input class="form-control<?= invalid_class($errors, 'last_name') ?>" id="last_name" name="last_name"
                           value="<?= e($data['last_name']) ?>" maxlength="100" required>
                    <?= error_text($errors, 'last_name') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="contact_number">Contact number <span class="required-mark">*</span></label>
                    <input class="form-control<?= invalid_class($errors, 'contact_number') ?>" id="contact_number" name="contact_number"
                           value="<?= e($data['contact_number']) ?>" maxlength="30" placeholder="e.g. 09171234567" required>
                    <?= error_text($errors, 'contact_number') ?>
                    <div class="form-text">Mobile or landline number, 7–30 characters.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="date_registered">Date registered <span class="required-mark">*</span></label>
                    <input type="date" class="form-control<?= invalid_class($errors, 'date_registered') ?>" id="date_registered"
                           name="date_registered" value="<?= e($data['date_registered']) ?>" max="<?= date('Y-m-d') ?>" required>
                    <?= error_text($errors, 'date_registered') ?>
                </div>
                <div class="col-12">
                    <label class="form-label" for="address">Address <span class="required-mark">*</span></label>
                    <input class="form-control<?= invalid_class($errors, 'address') ?>" id="address" name="address"
                           value="<?= e($data['address']) ?>" maxlength="255"
                           placeholder="Barangay, City/Municipality, Province" required>
                    <?= error_text($errors, 'address') ?>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check2 me-1"></i> <?= $existing ? 'Save changes' : 'Register farmer' ?>
                </button>
                <a class="btn btn-light" href="<?= e(url($existing ? 'farmers/view.php?id=' . $id : 'farmers/index.php')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
