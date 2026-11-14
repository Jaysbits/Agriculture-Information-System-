<?php
declare(strict_types=1);

/**
 * CONTROLLER — Delete a planting record (admin only, POST + CSRF).
 * The delete itself is performed by the Planting model.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
Auth::requireAdmin();

if (!is_post()) {
    redirect('plantings/index.php');
}
verify_csrf();

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);

if ($id <= 0) {
    flash('danger', 'Invalid planting record.');
    redirect('plantings/index.php');
}

$result = Planting::delete($id);
if (!$result['ok']) {
    flash('danger', $result['error']);
} elseif (!$result['deleted']) {
    flash('warning', 'That planting record was not found.');
} else {
    flash('success', 'Planting record deleted successfully.');
}

redirect('plantings/index.php');
