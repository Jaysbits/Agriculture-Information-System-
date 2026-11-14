<?php
declare(strict_types=1);

/**
 * CONTROLLER — Delete a farmer (admin only, POST + CSRF).
 * The delete itself is performed by the Farmer model.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
Auth::requireAdmin();

if (!is_post()) {
    redirect('farmers/index.php');
}
verify_csrf();

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);

if ($id <= 0) {
    flash('danger', 'Invalid farmer record.');
    redirect('farmers/index.php');
}

$result = (new Farmer())->delete($id);
if (!$result['ok']) {
    flash('danger', $result['error']);
} elseif (!$result['deleted']) {
    flash('warning', 'That farmer record was not found.');
} else {
    flash('success', 'Farmer deleted successfully.');
}

redirect('farmers/index.php');
