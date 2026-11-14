<?php
declare(strict_types=1);

/**
 * Thin PDO wrapper around the Supabase PostgreSQL database.
 * Credentials come from environment variables (.env) only.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host    = (string) env('DB_HOST', '');
        $port    = (string) env('DB_PORT', '5432');
        $name    = (string) env('DB_NAME', 'postgres');
        $user    = (string) env('DB_USER', '');
        $pass    = (string) env('DB_PASSWORD', '');
        $sslMode = (string) env('DB_SSLMODE', 'require');

        if ($host === '' || $user === '') {
            throw new RuntimeException(
                'Database is not configured yet. Copy .env.example to .env and enter your Supabase connection details.'
            );
        }

        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=%s', $host, $port, $name, $sslMode);

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
                PDO::ATTR_TIMEOUT            => 10,
            ]);
            self::$pdo->exec("SET client_encoding TO 'UTF8'");
            // Tell the audit triggers which user is saving the record.
            self::$pdo->exec("SET application_name TO 'agrisys'");
        } catch (PDOException $e) {
            error_log('[db] connection failed: ' . $e->getMessage());
            throw new RuntimeException(
                'Unable to connect to the database. Check the server settings and try again later.'
            );
        }

        return self::$pdo;
    }

    /** Run a prepared statement and return it. */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** First row or null. */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** All rows. */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** Single scalar value. */
    public static function value(string $sql, array $params = [], mixed $default = null): mixed
    {
        $value = self::run($sql, $params)->fetchColumn();
        return $value === false ? $default : $value;
    }

    public static function count(string $sql, array $params = []): int
    {
        return (int) self::value($sql, $params, 0);
    }

    /** Stamp the acting user on the connection so audit triggers can use it. */
    public static function setAuditUser(?int $userId): void
    {
        $stmt = self::pdo()->prepare("SELECT set_config('agrisys.user_id', :id, false)");
        $stmt->execute([':id' => (string) ($userId ?? '')]);
    }

    /** Column names of a table (used to whitelist sort keys). */
    public static function columns(string $table): array
    {
        static $cache = [];
        if (!isset($cache[$table])) {
            $rows = self::all(
                'SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = :t',
                [':t' => $table]
            );
            $cache[$table] = array_column($rows, 'column_name');
        }
        return $cache[$table];
    }
}
