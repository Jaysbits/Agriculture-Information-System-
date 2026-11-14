<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';

if (Auth::check()) {
    redirect('index.php');
}

$username = '';
$error    = '';
$locked   = (int) ($_SESSION['login_locked_until'] ?? 0) > time();

if (is_post()) {
    verify_csrf();
    $username = post('username');
    $password = (string) ($_POST['password'] ?? '');

    if ($locked) {
        $wait  = (int) ($_SESSION['login_locked_until'] ?? 0) - time();
        $error = 'Too many failed attempts. Please wait ' . max(1, $wait) . ' second(s) and try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Please enter both your username and password.';
    } elseif (Auth::attempt($username, $password)) {
        flash('success', 'Welcome back, ' . (Auth::user()['full_name'] ?? $username) . '.');
        redirect('index.php');
    } else {
        $error = 'Invalid username or password. Please try again.';
        if ((int) ($_SESSION['login_locked_until'] ?? 0) > time()) {
            $error = 'Too many failed attempts. Your sign-in has been paused for 60 seconds.';
        }
    }
}

$flashes = take_flashes();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in &middot; <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="login-page">
    <div class="login-card row g-0">
        <div class="col-md-6 login-visual">
            <div>
                <div class="d-flex align-items-center gap-2 mb-4">
                    <span class="brand-mark"><i class="bi bi-flower2"></i></span>
                    <div>
                        <div class="fw-bold"><?= e(APP_NAME) ?></div>
                        <div class="small text-white-50"><?= e(APP_TAGLINE) ?></div>
                    </div>
                </div>
                <h2 class="h4 fw-bold mb-2">Farm records, harvests and reports in one place.</h2>
                <p class="small text-white-50 mb-0">
                    Track farmers, farms, crops, planting activities and harvest results for the
                    Cebu agriculture community.
                </p>
                <ul>
                    <li><i class="bi bi-check2-circle"></i> Central farmer and farm registry</li>
                    <li><i class="bi bi-check2-circle"></i> Planting and harvest monitoring</li>
                    <li><i class="bi bi-check2-circle"></i> Yield, income and status reports</li>
                </ul>
            </div>
            <div class="small text-white-50">Cebu Technological University &middot; AP3 Midterm Project</div>
        </div>

        <div class="col-md-6 login-form">
            <h1 class="h4 fw-bold mb-1">Sign in</h1>
            <p class="text-muted small mb-4">Use your assigned account to access the system.</p>

            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> py-2 small"><?= e($flash['message']) ?></div>
            <?php endforeach; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger py-2 small d-flex gap-2">
                    <i class="bi bi-exclamation-octagon-fill"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="username">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="username" name="username"
                               value="<?= e($username) ?>" autocomplete="username" autofocus required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password"
                               autocomplete="current-password" required>
                        <button class="btn btn-outline-secondary" type="button" id="toggle-password"
                                aria-label="Show password"><i class="bi bi-eye"></i></button>
                    </div>
                </div>
                <button type="submit" class="btn btn-success w-100 py-2">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Sign in
                </button>
            </form>

            <div class="border rounded-3 bg-light p-3 mt-4 small">
                <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-1"></i> Demo accounts</div>
                <div class="text-muted">Administrator: <code>admin</code> / <code>admin123</code></div>
                <div class="text-muted">Staff: <code>staff</code> / <code>staff123</code></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('toggle-password').addEventListener('click', function () {
    var input = document.getElementById('password');
    var icon = this.querySelector('i');
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
});
</script>
</body>
</html>









