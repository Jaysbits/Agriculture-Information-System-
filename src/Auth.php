<?php
declare(strict_types=1);

/**
 * Session authentication and role-based access control.
 * Passwords are verified against bcrypt hashes stored in app_user.
 */
final class Auth
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_SECONDS = 60;

    public static function attempt(string $username, string $password): bool
    {
        $lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);
        if ($lockedUntil > time()) {
            return false;
        }

        $user = Database::one(
            'SELECT user_id, username, full_name, password_hash, role, is_active
               FROM app_user WHERE lower(username) = lower(:u)',
            [':u' => $username]
        );

        if (!$user || !$user['is_active'] || !password_verify($password, $user['password_hash'])) {
            $_SESSION['login_attempts'] = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
            if ($_SESSION['login_attempts'] >= self::MAX_ATTEMPTS) {
                $_SESSION['login_locked_until'] = time() + self::LOCK_SECONDS;
                $_SESSION['login_attempts']    = 0;
            }
            return false;
        }

        unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'user_id'   => (int) $user['user_id'],
            'username'  => $user['username'],
            'full_name' => $user['full_name'],
            'role'      => $user['role'],
        ];

        Database::run('UPDATE app_user SET last_login_at = now() WHERE user_id = :id', [':id' => $user['user_id']]);

        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    /** Fresh user row; deactivated accounts are signed out automatically. */
    public static function user(): ?array
    {
        static $cached = null;
        static $loaded = false;

        if ($loaded) {
            return $cached;
        }
        $loaded = true;

        $id = $_SESSION['user']['user_id'] ?? null;
        if (!$id) {
            return null;
        }

        $user = Database::one(
            'SELECT user_id, username, full_name, role, is_active
               FROM app_user WHERE user_id = :id',
            [':id' => $id]
        );

        if (!$user || !$user['is_active']) {
            self::logout();
            return null;
        }

        $cached = $user;
        return $cached;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['user_id'] : null;
    }

    public static function isAdmin(): bool
    {
        $user = self::user();
        return $user !== null && $user['role'] === 'admin';
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            flash('warning', 'Please sign in to continue.');
            redirect('login.php');
        }
        Database::setAuditUser(self::id());
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            http_response_code(403);
            flash('danger', 'You do not have permission to open that page. Administrator access is required.');
            redirect('index.php');
        }
    }
}
