<?php
declare(strict_types=1);

/**
 * CONTROLLER — Delete a crop (admin only, POST + CSRF).
 * The delete itself is performed by the Crop model.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
Auth::requireAdmin();

if (!is_post()) {
    redirect('crops/index.php');
}
verify_csrf();

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);

if ($id <= 0) {
    flash('danger', 'Invalid crop record.');
    redirect('crops/index.php');
}

$result = (new Crop(new Database()))->delete($id);
if (!$result['ok']) {
    flash('danger', $result['error']);
} elseif (!$result['deleted']) {
    flash('warning', 'That crop record was not found.');
} else {
    flash('success', 'Crop deleted successfully.');
}

redirect('crops/index.php');









