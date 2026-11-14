<?php
declare(strict_types=1);

/**
 * VIEW — Create / edit user account form (admin only).
 * Validation and persistence are handled by the AppUser model, which
 * also enforces the password policy and the last-admin guard.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
Auth::requireAdmin();

$userModel = new AppUser(new Database());

$id       = form_id();
$existing = $id > 0 ? $userModel->find($id) : null;

if ($id > 0 && !$existing) {
    flash('danger', 'That user account no longer exists.');
    redirect('users/index.php');
}

$data = $existing ?? [
    'username'  => '',
    'full_name' => '',
    'role'      => 'staff',
    'is_active' => 1,
];
$errors = [];

if (is_post()) {
    verify_csrf();

    $result = $userModel->save($_POST, $id, Auth::id());
    if ($result['ok']) {
        flash('success', $existing ? 'User account updated successfully.' : 'User account created successfully.');
        redirect('users/index.php');
    }

    $errors = $result['errors'];
    if ($result['dbError'] !== null) {
        $errors['_form'] = $result['dbError'];
    }

    $data = [
        'username'  => post('username'),
        'full_name' => post('full_name'),
        'role'      => post('role', 'staff'),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];
}

$pageTitle    = $existing ? 'Edit user account' : 'New user account';
$active       = 'users';
$pageSubtitle = $existing ? 'Update @' . $existing['username'] : 'Create an account for a staff member';
$pageActions  = '<a class="btn btn-outline-secondary" href="' . e(url('users/index.php')) . '"><i class="bi bi-arrow-left me-1"></i> Back</a>';

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

        <form method="post" novalidate autocomplete="off">
            <?= csrf_field() ?>
            <?php if ($id > 0): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

            <div class="form-section-title">Account details</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="username">Username <span class="required-mark">*</span></label>
                    <input class="form-control<?= invalid_class($errors, 'username') ?>" id="username" name="username"
                           value="<?= e($data['username']) ?>" maxlength="50" required>
                    <?= error_text($errors, 'username') ?>
                    <div class="form-text">3–50 characters. Letters, numbers, dots, underscores and hyphens.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="full_name">Full name <span class="required-mark">*</span></label>
                    <input class="form-control<?= invalid_class($errors, 'full_name') ?>" id="full_name" name="full_name"
                           value="<?= e($data['full_name']) ?>" maxlength="120" required>
                    <?= error_text($errors, 'full_name') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="role">Role <span class="required-mark">*</span></label>
                    <select class="form-select<?= invalid_class($errors, 'role') ?>" id="role" name="role" required>
                        <option value="staff"<?= selected('staff', $data['role']) ?>>Staff — manage records, no deletions</option>
                        <option value="admin"<?= selected('admin', $data['role']) ?>>Administrator — full access</option>
                    </select>
                    <?= error_text($errors, 'role') ?>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active"
                               value="1"<?= !empty($data['is_active']) ? ' checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Account is active</label>
                    </div>
                </div>
            </div>

            <div class="form-section-title mt-4">
                <?= $existing ? 'Reset password (leave blank to keep the current one)' : 'Password' ?>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="password">
                        Password <?php if (!$existing): ?><span class="required-mark">*</span><?php endif; ?>
                    </label>
                    <input type="password" class="form-control<?= invalid_class($errors, 'password') ?>" id="password"
                           name="password" autocomplete="new-password"<?= $existing ? '' : ' required' ?>>
                    <?= error_text($errors, 'password') ?>
                    <div class="form-text">At least 8 characters with a letter and a number. Stored as a bcrypt hash.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password_confirm">Confirm password</label>
                    <input type="password" class="form-control<?= invalid_class($errors, 'password_confirm') ?>"
                           id="password_confirm" name="password_confirm" autocomplete="new-password">
                    <?= error_text($errors, 'password_confirm') ?>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check2 me-1"></i> <?= $existing ? 'Save changes' : 'Create account' ?>
                </button>
                <a class="btn btn-light" href="<?= e(url('users/index.php')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>









