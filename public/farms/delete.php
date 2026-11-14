<?php
declare(strict_types=1);

/**
 * CONTROLLER — Delete a farm (admin only, POST + CSRF).
 * The delete itself is performed by the Farm model.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
Auth::requireAdmin();

if (!is_post()) {
    redirect('farms/index.php');
}
verify_csrf();

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);

if ($id <= 0) {
    flash('danger', 'Invalid farm record.');
    redirect('farms/index.php');
}

$result = (new Farm(new Database()))->delete($id);
if (!$result['ok']) {
    flash('danger', $result['error']);
} elseif (!$result['deleted']) {
    flash('warning', 'That farm record was not found.');
} else {
    flash('success', 'Farm deleted successfully.');
}

redirect('farms/index.php');









