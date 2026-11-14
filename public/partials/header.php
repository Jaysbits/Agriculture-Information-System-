<?php
declare(strict_types=1);

/**
 * Shared layout — top half.
 * Pages set $pageTitle (and optionally $active, $pageSubtitle, $pageActions)
 * before including this file.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';

$pageTitle    = $pageTitle    ?? 'Dashboard';
$active       = $active       ?? '';
$pageSubtitle = $pageSubtitle ?? '';
$pageActions  = $pageActions  ?? '';
$user         = Auth::user();
$flashes      = take_flashes();

$navGroups = [
    'Operations' => [
        ['key' => 'dashboard', 'label' => 'Dashboard',  'icon' => 'speedometer2',      'href' => 'index.php'],
        ['key' => 'farmers',   'label' => 'Farmers',    'icon' => 'people',            'href' => 'farmers/index.php'],
        ['key' => 'farms',     'label' => 'Farms',      'icon' => 'geo-alt',           'href' => 'farms/index.php'],
        ['key' => 'crops',     'label' => 'Crops',      'icon' => 'flower1',           'href' => 'crops/index.php'],
        ['key' => 'plantings', 'label' => 'Plantings',  'icon' => 'calendar-check',    'href' => 'plantings/index.php'],
        ['key' => 'harvests',  'label' => 'Harvests',   'icon' => 'basket3',           'href' => 'harvests/index.php'],
        ['key' => 'reports',   'label' => 'Reports',    'icon' => 'file-earmark-bar-graph', 'href' => 'reports.php'],
    ],
];

if (Auth::isAdmin()) {
    $navGroups['Administration'] = [
        ['key' => 'users',   'label' => 'User Accounts', 'icon' => 'person-badge',  'href' => 'users/index.php'],
        ['key' => 'lookups', 'label' => 'Lookup Tables', 'icon' => 'sliders',       'href' => 'lookups.php'],
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1f5134">
    <title><?= e($pageTitle) ?> &middot; <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <span class="brand-mark"><i class="bi bi-flower2"></i></span>
        <span>
            <span class="brand-name"><?= e(APP_NAME) ?></span>
            <span class="brand-sub">Agriculture Info System</span>
        </span>
        <button type="button" class="btn btn-sm btn-close-sidebar d-lg-none" data-sidebar-close aria-label="Close menu">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($navGroups as $group => $items): ?>
            <div class="nav-group-label"><?= e($group) ?></div>
            <?php foreach ($items as $item): ?>
                <a class="nav-link<?= $active === $item['key'] ? ' active' : '' ?>" href="<?= e(url($item['href'])) ?>">
                    <i class="bi bi-<?= e($item['icon']) ?>"></i>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="d-flex align-items-center gap-2">
            <span class="avatar"><?= e(initials((string) ($user['full_name'] ?? 'U'))) ?></span>
            <div class="small lh-sm">
                <div class="fw-semibold text-truncate" style="max-width:130px"><?= e($user['full_name'] ?? '') ?></div>
                <div class="text-white-50 text-uppercase" style="font-size:.68rem;letter-spacing:.06em"><?= e($user['role'] ?? '') ?></div>
            </div>
        </div>
    </div>
</aside>
<div class="sidebar-backdrop" data-sidebar-close hidden></div>

<div class="app-main">
    <header class="topbar">
        <button class="btn btn-icon d-lg-none" type="button" data-sidebar-toggle aria-label="Open menu">
            <i class="bi bi-list"></i>
        </button>
        <div class="flex-grow-1 min-w-0">
            <h1 class="page-title text-truncate"><?= e($pageTitle) ?></h1>
            <?php if ($pageSubtitle !== ''): ?>
                <div class="page-subtitle text-truncate"><?= e($pageSubtitle) ?></div>
            <?php endif; ?>
        </div>
        <?php if ($pageActions !== ''): ?>
            <div class="d-none d-md-flex gap-2"><?= $pageActions ?></div>
        <?php endif; ?>
        <div class="dropdown">
            <button class="btn btn-user dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="avatar avatar-sm"><?= e(initials((string) ($user['full_name'] ?? 'U'))) ?></span>
                <span class="d-none d-sm-inline"><?= e($user['full_name'] ?? '') ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li class="px-3 py-2">
                    <div class="fw-semibold"><?= e($user['full_name'] ?? '') ?></div>
                    <div class="small text-muted">@<?= e($user['username'] ?? '') ?> &middot; <?= e(ucfirst($user['role'] ?? '')) ?></div>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="<?= e(url('logout.php')) ?>" class="m-0">
                        <?= csrf_field() ?>
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="bi bi-box-arrow-right me-1"></i> Sign out
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </header>

    <main class="app-content">
        <?php if ($pageActions !== ''): ?>
            <div class="d-flex d-md-none gap-2 mb-3 flex-wrap"><?= $pageActions ?></div>
        <?php endif; ?>









