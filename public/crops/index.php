<?php
declare(strict_types=1);

/**
 * VIEW — Crops list.
 * Reads come from the Crop model (src/Models/Crop.php).
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
Auth::requireLogin();

$cropModel = new Crop(new Database());

$q           = get('q');
$category_id = int_get('category_id', 0);
$season_id   = int_get('season_id', 0);
$sort        = current_sort('name', Crop::SORTABLE);
$dir         = current_dir();
$page        = max(1, int_get('page', 1));

$result = $cropModel->listing($q, $category_id, $season_id, $sort, $dir, $page);
$crops  = $result['rows'];
$p      = $result['pager'];

$categories = $cropModel->categories();
$seasons    = $cropModel->seasons();

$pageTitle    = 'Crops';
$active       = 'crops';
$pageSubtitle = 'Crop master data, categories and growing periods';
$pageActions  = '<a class="btn btn-success" href="' . e(url('crops/form.php')) . '">'
    . '<i class="bi bi-plus-lg me-1"></i> New crop</a>';

require __DIR__ . '/../partials/header.php';
?>

<form class="filter-bar mb-3" method="get" data-auto-submit>
    <div class="row g-2 align-items-end">
        <div class="col-12 col-lg-4">
            <label class="form-label" for="q">Search</label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Crop name or variety">
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <label class="form-label" for="category_id">Category</label>
            <select class="form-select" id="category_id" name="category_id">
                <?= options($categories, 'category_id', ['category_name'], $category_id ?: '', 'All categories') ?>
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label class="form-label" for="season_id">Season</label>
            <select class="form-select" id="season_id" name="season_id">
                <?= options($seasons, 'season_id', ['season_name'], $season_id ?: '', 'All seasons') ?>
            </select>
        </div>
        <div class="col-12 col-lg-3 d-flex gap-2">
            <button class="btn btn-success" type="submit"><i class="bi bi-funnel me-1"></i> Filter</button>
            <a class="btn btn-outline-secondary" href="<?= e(url('crops/index.php')) ?>">Reset</a>
        </div>
    </div>
</form>

<div class="card table-card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th><?= sort_link('Crop', 'name', $sort, $dir) ?></th>
                        <th><?= sort_link('Category', 'category', $sort, $dir) ?></th>
                        <th><?= sort_link('Season', 'season', $sort, $dir) ?></th>
                        <th class="text-end"><?= sort_link('Growing period', 'period', $sort, $dir) ?></th>
                        <th class="text-end"><?= sort_link('Plantings', 'plantings', $sort, $dir) ?></th>
                        <th class="text-end"><?= sort_link('Harvested', 'harvested', $sort, $dir) ?></th>
                        <th class="text-end no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($crops === []): ?>
                    <tr><td colspan="7">
                        <div class="empty-state">
                            <i class="bi bi-flower1"></i>
                            No crops match your filters.
                            <div class="mt-2"><a href="<?= e(url('crops/form.php')) ?>">Add the first crop</a></div>
                        </div>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($crops as $row): ?>
                    <tr>
                        <td>
                            <a class="fw-semibold text-dark" href="<?= e(url('crops/view.php?id=' . (int) $row['crop_id'])) ?>"><?= e($row['crop_name']) ?></a>
                            <?php if ($row['variety']): ?><div class="small text-muted"><?= e($row['variety']) ?></div><?php endif; ?>
                        </td>
                        <td><span class="badge text-bg-light border"><?= e($row['category_name']) ?></span></td>
                        <td><?= e($row['season_name'] ?? '—') ?></td>
                        <td class="text-end"><?= number_format((int) $row['growing_period_days']) ?> days</td>
                        <td class="text-end"><?= number_format((int) $row['planting_count']) ?></td>
                        <td class="text-end"><?= e(fmt_kg($row['harvested'])) ?></td>
                        <td class="text-end no-print">
                            <div class="row-actions d-inline-flex gap-1">
                                <a class="btn btn-outline-secondary" title="View"
                                   href="<?= e(url('crops/view.php?id=' . (int) $row['crop_id'])) ?>"><i class="bi bi-eye"></i></a>
                                <a class="btn btn-outline-success" title="Edit"
                                   href="<?= e(url('crops/form.php?id=' . (int) $row['crop_id'])) ?>"><i class="bi bi-pencil"></i></a>
                                <?php if (Auth::isAdmin()): ?>
                                    <button type="button" class="btn btn-outline-danger" title="Delete"
                                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                                            data-delete-url="<?= e(url('crops/delete.php?id=' . (int) $row['crop_id'])) ?>"
                                            data-delete-name="<?= e($row['crop_name'] . ($row['variety'] ? ' (' . $row['variety'] . ')' : '')) ?>">
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
            <?= pagination_links($p, 'crops/index.php') ?>
        </div>
    <?php endif; ?>
</div>

<?php
require __DIR__ . '/../partials/delete-modal.php';
require __DIR__ . '/../partials/footer.php';









