<?php
declare(strict_types=1);

/**
 * VIEW — User accounts list (admin only).
 * Reads come from the AppUser model (src/Models/AppUser.php).
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
Auth::requireAdmin();

$q    = get('q');
$role = get('role');
$sort = current_sort('username', AppUser::SORTABLE);
$dir  = current_dir();
$page = max(1, int_get('page', 1));

$result = AppUser::listing($q, $role, $sort, $dir, $page);
$users  = $result['rows'];
$p      = $result['pager'];

$pageTitle    = 'User accounts';
$active       = 'users';
$pageSubtitle = 'System accounts, roles and access status';
$pageActions  = '<a class="btn btn-success" href="' . e(url('users/form.php')) . '">'
    . '<i class="bi bi-person-plus me-1"></i> New account</a>';

require __DIR__ . '/../partials/header.php';
?>

<form class="filter-bar mb-3" method="get" data-auto-submit>
    <div class="row g-2 align-items-end">
        <div class="col-12 col-lg-5">
            <label class="form-label" for="q">Search</label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Username or full name">
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <label class="form-label" for="role">Role</label>
            <select class="form-select" id="role" name="role">
                <option value="">All roles</option>
                <option value="admin"<?= selected('admin', $role) ?>>Administrator</option>
                <option value="staff"<?= selected('staff', $role) ?>>Staff</option>
            </select>
        </div>
        <div class="col-6 col-lg-4 d-flex gap-2">
            <button class="btn btn-success" type="submit"><i class="bi bi-funnel me-1"></i> Filter</button>
            <a class="btn btn-outline-secondary" href="<?= e(url('users/index.php')) ?>">Reset</a>
        </div>
    </div>
</form>

<div class="card table-card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th><?= sort_link('Username', 'username', $sort, $dir) ?></th>
                        <th><?= sort_link('Full name', 'name', $sort, $dir) ?></th>
                        <th><?= sort_link('Role', 'role', $sort, $dir) ?></th>
                        <th><?= sort_link('Status', 'active', $sort, $dir) ?></th>
                        <th><?= sort_link('Last sign-in', 'last_login', $sort, $dir) ?></th>
                        <th class="text-end no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($users === []): ?>
                    <tr><td colspan="6"><div class="empty-state"><i class="bi bi-person-badge"></i>No accounts match your filters.</div></td></tr>
                <?php endif; ?>
                <?php foreach ($users as $row): ?>
                    <tr>
                        <td>
                            <span class="fw-semibold"><?= e($row['username']) ?></span>
                            <?php if ((int) $row['user_id'] === Auth::id()): ?>
                                <span class="badge text-bg-light border ms-1">you</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($row['full_name']) ?></td>
                        <td>
                            <span class="badge <?= $row['role'] === 'admin' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                <?= e(ucfirst($row['role'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($row['is_active']): ?>
                                <span class="badge text-bg-success-subtle text-success-emphasis border border-success-subtle">Active</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap"><?= e(fmt_datetime($row['last_login_at'])) ?></td>
                        <td class="text-end no-print">
                            <div class="row-actions d-inline-flex gap-1">
                                <a class="btn btn-outline-success" title="Edit"
                                   href="<?= e(url('users/form.php?id=' . (int) $row['user_id'])) ?>"><i class="bi bi-pencil"></i></a>
                                <?php if ((int) $row['user_id'] !== Auth::id()): ?>
                                    <button type="button" class="btn btn-outline-danger" title="Delete"
                                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                                            data-delete-url="<?= e(url('users/delete.php?id=' . (int) $row['user_id'])) ?>"
                                            data-delete-name="<?= e($row['full_name'] . ' (@' . $row['username'] . ')') ?>">
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
            <?= pagination_links($p, 'users/index.php') ?>
        </div>
    <?php endif; ?>
</div>

<?php
require __DIR__ . '/../partials/delete-modal.php';
require __DIR__ . '/../partials/footer.php';
