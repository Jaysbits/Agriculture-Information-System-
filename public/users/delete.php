<?php
declare(strict_types=1);

/**
 * CONTROLLER — Delete a user account (admin only, POST + CSRF).
 * The AppUser model enforces the self-delete and last-admin rules.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
Auth::requireAdmin();

if (!is_post()) {
    redirect('users/index.php');
}
verify_csrf();

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);

if ($id <= 0) {
    flash('danger', 'Invalid user account.');
    redirect('users/index.php');
}

$result = (new AppUser())->delete($id, Auth::id());
if (!$result['ok']) {
    flash('danger', $result['error']);
} elseif (!$result['deleted']) {
    flash('warning', 'That user account was not found.');
} else {
    flash('success', 'User account deleted successfully.');
}

redirect('users/index.php');
