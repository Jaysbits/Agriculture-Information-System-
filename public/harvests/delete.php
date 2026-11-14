<?php
declare(strict_types=1);

/**
 * CONTROLLER — Delete a harvest record (admin only, POST + CSRF).
 * The delete itself is performed by the Harvest model.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
Auth::requireAdmin();

if (!is_post()) {
    redirect('harvests/index.php');
}
verify_csrf();

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);

if ($id <= 0) {
    flash('danger', 'Invalid harvest record.');
    redirect('harvests/index.php');
}

$result = Harvest::delete($id);
if (!$result['ok']) {
    flash('danger', $result['error']);
} elseif (!$result['deleted']) {
    flash('warning', 'That harvest record was not found.');
} else {
    flash('success', 'Harvest record deleted successfully.');
}

redirect('harvests/index.php');
