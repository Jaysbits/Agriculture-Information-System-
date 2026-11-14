<?php
declare(strict_types=1);

/**
 * VIEW / CONTROLLER — Lookup (reference) tables.
 * The registry, usage counts and add / update / delete logic all live in
 * the Lookup model (src/Models/Lookup.php). This page reads the request
 * and renders the tables.
 */

require_once dirname(__DIR__) . '/config/config.php';
Auth::requireAdmin();

$lookup = new Lookup(new Database());

$lookups = $lookup->tables();

$tableKey = get('table', 'soil_type');
if (!isset($lookups[$tableKey])) {
    $tableKey = 'soil_type';
}
$config = $lookup->config($tableKey);
$errors = [];

if (is_post()) {
    verify_csrf();

    $action   = post('action', isset($_GET['delete']) ? 'delete' : '');
    $name     = post('name');
    $recordId = (int) post('record_id', (string) ($_GET['delete'] ?? '0'));

    if ($action === 'delete' && $recordId > 0) {
        $result = $lookup->delete($tableKey, $recordId);
        if (!$result['ok']) {
            flash('danger', $result['error']);
        } else {
            flash('success', 'Lookup value deleted.');
        }
        redirect('lookups.php?table=' . urlencode($tableKey));
    }

    $result = $lookup->save($tableKey, $action, $name, $recordId);
    if ($result['ok']) {
        flash('success', $action === 'update' ? 'Lookup value updated.' : 'Lookup value added.');
        redirect('lookups.php?table=' . urlencode($tableKey));
    }

    $errors = $result['errors'];
    if ($result['dbError'] !== null) {
        $errors['_form'] = $result['dbError'];
    }
}

$rows = $lookup->rows($tableKey);

$pageTitle    = 'Lookup tables';
$active       = 'lookups';
$pageSubtitle = 'Controlled dropdown values stored in separate reference tables';

require __DIR__ . '/partials/header.php';
?>

<div class="row g-3">
    <div class="col-lg-4 col-xl-3">
        <div class="card">
            <div class="card-header">Reference tables</div>
            <div class="list-group list-group-flush">
                <?php foreach ($lookups as $key => $info): ?>
                    <a class="list-group-item list-group-item-action d-flex align-items-center gap-2<?= $key === $tableKey ? ' active' : '' ?>"
                       href="<?= e(url('lookups.php?table=' . $key)) ?>">
                        <i class="bi bi-<?= e($info['icon']) ?>"></i>
                        <span><?= e($info['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="alert alert-info small mt-3">
            <i class="bi bi-info-circle me-1"></i>
            Core tables store the numeric ID of these values. Renaming a description does not break existing records.
            Values that are still used by records cannot be deleted.
        </div>
    </div>

    <div class="col-lg-8 col-xl-9">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-<?= e($config['icon']) ?> me-1 text-success"></i> <?= e($config['label']) ?></span>
                <span class="small text-muted"><?= number_format(count($rows)) ?> value(s)</span>
            </div>
            <div class="card-body">
                <?php if (isset($errors['_form'])): ?>
                    <div class="alert alert-danger d-flex gap-2">
                        <i class="bi bi-exclamation-octagon-fill"></i>
                        <div><?= e($errors['_form']) ?></div>
                    </div>
                <?php endif; ?>

                <form method="post" class="row g-2 align-items-start mb-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <div class="col">
                        <label class="form-label" for="new-name">Add a new value</label>
                        <input class="form-control<?= invalid_class($errors, 'name') ?>" id="new-name" name="name"
                               placeholder="Description shown in the dropdown" required>
                        <?= error_text($errors, 'name') ?>
                    </div>
                    <div class="col-auto pt-4">
                        <button type="submit" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i> Add</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th style="width:80px">ID</th>
                                <th>Description</th>
                                <th class="text-end">Used by</th>
                                <th class="text-end">Updated</th>
                                <th class="text-end no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($rows === []): ?>
                            <tr><td colspan="5"><div class="empty-state"><i class="bi bi-sliders"></i>No values yet. Add the first one above.</div></td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><span class="badge text-bg-light border">#<?= (int) $row['id'] ?></span></td>
                                <td>
                                    <form method="post" class="d-flex gap-2" id="edit-<?= (int) $row['id'] ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="record_id" value="<?= (int) $row['id'] ?>">
                                        <input class="form-control form-control-sm" name="name" value="<?= e($row['name']) ?>" required>
                                    </form>
                                </td>
                                <td class="text-end">
                                    <?php if ((int) $row['used'] > 0): ?>
                                        <span class="badge text-bg-light border"><?= number_format((int) $row['used']) ?> <?= e($config['usage']['label']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">unused</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end small text-muted text-nowrap"><?= e(fmt_date($row['updated_at'])) ?></td>
                                <td class="text-end no-print">
                                    <div class="row-actions d-inline-flex gap-1">
                                        <button type="submit" form="edit-<?= (int) $row['id'] ?>" class="btn btn-outline-success" title="Save">
                                            <i class="bi bi-check2"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" title="Delete"
                                                data-bs-toggle="modal" data-bs-target="#deleteModal"
                                                data-delete-url="<?= e(url('lookups.php?table=' . $tableKey . '&delete=' . (int) $row['id'])) ?>"
                                                data-delete-name="<?= e($row['name']) ?>">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require __DIR__ . '/partials/delete-modal.php';
require __DIR__ . '/partials/footer.php';









