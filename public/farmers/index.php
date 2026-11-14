<?php
declare(strict_types=1);

/**
 * VIEW — Farmers list.
 * Reads come from the Farmer model (src/Models/Farmer.php); this file
 * only handles request input and renders the table.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
Auth::requireLogin();

$farmerModel = new Farmer();

$q       = get('q');
$address = get('address');
$sort    = current_sort('name', Farmer::SORTABLE);
$dir     = current_dir();
$page    = max(1, int_get('page', 1));

$result  = $farmerModel->listing($q, $address, $sort, $dir, $page);
$farmers = $result['rows'];
$p       = $result['pager'];

$pageTitle    = 'Farmers';
$active       = 'farmers';
$pageSubtitle = 'Farmer registry and their registered farms';
$pageActions  = '<a class="btn btn-success" href="' . e(url('farmers/form.php')) . '">'
    . '<i class="bi bi-person-plus me-1"></i> New farmer</a>';

require __DIR__ . '/../partials/header.php';
?>

<form class="filter-bar mb-3" method="get" data-auto-submit>
    <div class="row g-2 align-items-end">
        <div class="col-12 col-lg-4">
            <label class="form-label" for="q">Search</label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input class="form-control" id="q" name="q" value="<?= e($q) ?>"
                       placeholder="Name, contact number or address">
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label" for="address">Address</label>
            <input class="form-control" id="address" name="address" value="<?= e($address) ?>"
                   placeholder="e.g. Talisay">
        </div>
        <div class="col-12 col-sm-6 col-lg-3 d-flex gap-2">
            <button class="btn btn-success" type="submit"><i class="bi bi-funnel me-1"></i> Filter</button>
            <a class="btn btn-outline-secondary" href="<?= e(url('farmers/index.php')) ?>">Reset</a>
        </div>
        <div class="col-12 col-lg-2 text-lg-end small text-muted">
            <?= number_format($p['total']) ?> farmer<?= $p['total'] === 1 ? '' : 's' ?>
        </div>
    </div>
</form>

<div class="card table-card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th><?= sort_link('Farmer', 'name', $sort, $dir) ?></th>
                        <th><?= sort_link('Address', 'address', $sort, $dir) ?></th>
                        <th><?= sort_link('Registered', 'registered', $sort, $dir) ?></th>
                        <th class="text-end"><?= sort_link('Farms', 'farms', $sort, $dir) ?></th>
                        <th class="text-end">Total area</th>
                        <th class="text-end no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($farmers === []): ?>
                    <tr><td colspan="6">
                        <div class="empty-state">
                            <i class="bi bi-people"></i>
                            No farmers match your search.
                            <div class="mt-2"><a href="<?= e(url('farmers/form.php')) ?>">Register the first farmer</a></div>
                        </div>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($farmers as $row): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar avatar-sm"><?= e(initials($row['first_name'] . ' ' . $row['last_name'])) ?></span>
                                <div class="min-w-0">
                                    <a class="fw-semibold text-dark" href="<?= e(url('farmers/view.php?id=' . (int) $row['farmer_id'])) ?>">
                                        <?= e($row['last_name'] . ', ' . $row['first_name']) ?>
                                    </a>
                                    <div class="small text-muted"><?= e($row['contact_number']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?= e($row['address']) ?></td>
                        <td class="text-nowrap"><?= e(fmt_date($row['date_registered'])) ?></td>
                        <td class="text-end"><?= number_format((int) $row['farm_count']) ?></td>
                        <td class="text-end"><?= e(fmt_ha($row['total_area'])) ?></td>
                        <td class="text-end no-print">
                            <div class="row-actions d-inline-flex gap-1">
                                <a class="btn btn-outline-secondary" title="View"
                                   href="<?= e(url('farmers/view.php?id=' . (int) $row['farmer_id'])) ?>">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a class="btn btn-outline-success" title="Edit"
                                   href="<?= e(url('farmers/form.php?id=' . (int) $row['farmer_id'])) ?>">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if (Auth::isAdmin()): ?>
                                    <button type="button" class="btn btn-outline-danger" title="Delete"
                                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                                            data-delete-url="<?= e(url('farmers/delete.php?id=' . (int) $row['farmer_id'])) ?>"
                                            data-delete-name="<?= e($row['first_name'] . ' ' . $row['last_name']) ?>">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($p['pages'] > 1): ?>
        <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="small text-muted">Showing <?= $p['from'] ?>–<?= $p['to'] ?> of <?= number_format($p['total']) ?></span>
            <?= pagination_links($p, 'farmers/index.php') ?>
        </div>
    <?php endif; ?>
</div>

<?php
require __DIR__ . '/../partials/delete-modal.php';
require __DIR__ . '/../partials/footer.php';
