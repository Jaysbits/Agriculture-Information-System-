<?php
declare(strict_types=1);

/**
 * Application bootstrap: environment, error handling, session, helpers.
 * Every page includes this file (through public/partials/header.php).
 */

define('APP_ROOT', dirname(__DIR__));
define('APP_NAME', 'AgriSys');
define('APP_TAGLINE', 'Agriculture Information System');

/** Read KEY=VALUE pairs from a .env file into the process environment. */
function load_env_file(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        if (strlen($value) >= 2
            && ($value[0] === '"' || $value[0] === "'")
            && str_ends_with($value, $value[0])) {
            $value = substr($value, 1, -1);
        }
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }
    }
}

/** Fetch an environment value with a fallback. */
function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

load_env_file(APP_ROOT . '/.env');

date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Manila') ?? 'Asia/Manila');

/*
 * Errors are logged, never printed: pages must not leak connection
 * strings, SQL or stack traces to end users.
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
$logDir = APP_ROOT . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
ini_set('error_log', $logDir . '/app.log');

define('BASE_URL', rtrim((string) env('APP_BASE_URL', ''), '/'));
define('ITEMS_PER_PAGE', max(5, (int) env('ITEMS_PER_PAGE', '10')));

require_once APP_ROOT . '/src/Database.php';
require_once APP_ROOT . '/src/helpers.php';
require_once APP_ROOT . '/src/Auth.php';
require_once APP_ROOT . '/src/Validator.php';

/*
 * Model layer. Each entity's database logic lives in its own file under
 * src/Models/. The public/ pages are the views and only call these.
 */
require_once APP_ROOT . '/src/Models/Model.php';
require_once APP_ROOT . '/src/Models/Farmer.php';
require_once APP_ROOT . '/src/Models/Farm.php';
require_once APP_ROOT . '/src/Models/Crop.php';
require_once APP_ROOT . '/src/Models/Planting.php';
require_once APP_ROOT . '/src/Models/Harvest.php';
require_once APP_ROOT . '/src/Models/AppUser.php';
require_once APP_ROOT . '/src/Models/Lookup.php';
require_once APP_ROOT . '/src/Models/Dashboard.php';
require_once APP_ROOT . '/src/Models/Report.php';

/* Session cookie hardening. */
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('agrisys_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* Last-resort handler for unexpected exceptions. */
set_exception_handler(static function (Throwable $e): void {
    error_log('[unhandled] ' . $e::class . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine());

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    $friendly = ($e instanceof RuntimeException)
        ? $e->getMessage()
        : 'Something went wrong while processing your request. Please try again.';

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Error &middot; ' . APP_NAME . '</title>'
        . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">'
        . '</head><body class="bg-light d-flex align-items-center" style="min-height:100vh">'
        . '<div class="container" style="max-width:560px"><div class="card shadow-sm">'
        . '<div class="card-body p-4"><h1 class="h5 text-danger">Request failed</h1>'
        . '<p class="mb-3">' . htmlspecialchars($friendly, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<a class="btn btn-success" href="' . htmlspecialchars(BASE_URL . '/index.php', ENT_QUOTES, 'UTF-8') . '">Back to dashboard</a>'
        . '</div></div></div></body></html>';
});
