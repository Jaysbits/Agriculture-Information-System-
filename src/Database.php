<?php
declare(strict_types=1);

/**
 * =====================================================================
 * Database — connection + data access
 * =====================================================================
 *
 * Two ways to use it:
 *
 *   1. Generic, table-oriented API (the simple style used by new models).
 *      Create one instance and call the CRUD methods with filter strings:
 *
 *        $db = new Database();
 *        $db->select('crop', '*', ['category_id=eq.2'], ['order' => 'crop_name']);
 *        $db->find('crop', 5, 'crop_id');
 *        $db->insert('crop', $data);            // returns the created row
 *        $db->update('crop', $data, ['crop_id=eq.5']);
 *        $db->delete('crop', ['crop_id=eq.5']); // ['ok'=>..,'deleted'=>..,'error'=>..]
 *
 *      Filter strings follow the PostgREST style used by Supabase:
 *        'col=eq.value'   'col=neq.value'  'col=gt.value'   'col=gte.value'
 *        'col=lt.value'   'col=lte.value'  'col=like.%x%'   'col=ilike.%x%'
 *        'col=in.(1,2,3)' 'col=is.null'    'or=(a.ilike.%x%,b.ilike.%x%)'
 *
 *   2. Raw SQL helpers (used by the remaining models and reports):
 *        Database::one('SELECT ...', [':id' => $id]);
 *        Database::all(...); Database::run(...); Database::value(...);
 *
 * Credentials come from environment variables (.env) only.
 */
final class Database
{
    private static ?PDO $pdo = null;

    /* =================================================================
     * Connection
     * ================================================================= */

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

    /* =================================================================
     * Raw SQL helpers
     * ================================================================= */

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

    /* =================================================================
     * Generic table API (instance methods)
     * ================================================================= */

    /**
     * SELECT rows from a table.
     *
     * @param array<int,string> $filters  e.g. ['crop_id=eq.5', 'or=(a.ilike.%x%,b.ilike.%x%)']
     * @param array{order?:string, limit?:int, offset?:int} $options
     * @return array<int,array<string,mixed>>
     */
    public function select(string $table, string $columns = '*', array $filters = [], array $options = []): array
    {
        $table = self::identifier($table);
        [$where, $params] = self::buildWhere($filters);

        $sql = 'SELECT ' . $columns . ' FROM ' . $table . $where;

        if (!empty($options['order'])) {
            $sql .= ' ORDER BY ' . $options['order'];
        }
        if (isset($options['limit'])) {
            $sql .= ' LIMIT ' . (int) $options['limit'];
            if (isset($options['offset'])) {
                $sql .= ' OFFSET ' . (int) $options['offset'];
            }
        }

        return self::all($sql, $params);
    }

    /** One row by primary key, or null. */
    public function find(string $table, int $id, string $primaryKey = 'id'): ?array
    {
        $table      = self::identifier($table);
        $primaryKey = self::identifier($primaryKey);

        return self::one("SELECT * FROM {$table} WHERE {$primaryKey} = :id", [':id' => $id]);
    }

    /**
     * INSERT a row and return it.
     *
     * @param array<string,mixed> $data column => value
     * @return array<string,mixed> the created row (empty on failure)
     */
    public function insert(string $table, array $data): array
    {
        $table = self::identifier($table);

        $columns = [];
        $params  = [];
        foreach ($data as $column => $value) {
            $column = self::identifier($column);
            $columns[] = $column;
            $params[':' . $column] = $value;
        }

        $placeholders = array_map(static fn (string $c): string => ':' . $c, $columns);
        $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ')'
             . ' VALUES (' . implode(', ', $placeholders) . ') RETURNING *';

        return self::one($sql, $params) ?? [];
    }

    /**
     * UPDATE matching rows and return them.
     *
     * @param array<string,mixed> $data    column => value
     * @param array<int,string>   $filters
     * @return array<int,array<string,mixed>>
     */
    public function update(string $table, array $data, array $filters = []): array
    {
        $table = self::identifier($table);
        [$where, $params] = self::buildWhere($filters);

        $sets = [];
        foreach ($data as $column => $value) {
            $column = self::identifier($column);
            $sets[] = $column . ' = :set_' . $column;
            $params[':set_' . $column] = $value;
        }

        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . $where . ' RETURNING *';

        return self::all($sql, $params);
    }

    /**
     * DELETE matching rows.
     *
     * @param array<int,string> $filters
     * @return array{ok:bool, deleted:bool, error:?string}
     */
    public function delete(string $table, array $filters = []): array
    {
        $table = self::identifier($table);
        [$where, $params] = self::buildWhere($filters);

        try {
            $deleted = self::run('DELETE FROM ' . $table . $where, $params)->rowCount();
            return ['ok' => true, 'deleted' => $deleted > 0, 'error' => null];
        } catch (PDOException $e) {
            error_log('[db] delete ' . $table . ': ' . $e->getMessage());
            return ['ok' => false, 'deleted' => false, 'error' => friendly_db_error($e)];
        }
    }

    /* =================================================================
     * Filter-string helpers
     * ================================================================= */

    /** Only plain identifiers may reach the SQL string. */
    private static function identifier(string $name): string
    {
        if (preg_match('/^[a-z_][a-z0-9_]*$/i', $name) !== 1) {
            throw new InvalidArgumentException('Unsafe SQL identifier: ' . $name);
        }
        return $name;
    }

    /**
     * Turn a list of filter strings into a WHERE clause + bound params.
     *
     * @param array<int,string> $filters
     * @return array{0:string, 1:array<string,string>}
     */
    private static function buildWhere(array $filters): array
    {
        if ($filters === []) {
            return ['', []];
        }

        $clauses = [];
        $params  = [];
        $n       = 0;

        foreach ($filters as $filter) {
            if (str_starts_with($filter, 'or=(') && str_ends_with($filter, ')')) {
                $inner = substr($filter, 4, -1);
                $ors   = [];
                foreach (explode(',', $inner) as $condition) {
                    [$sql, $bound] = self::parseCondition($condition, $n);
                    $ors[] = $sql;
                    $params += $bound;
                }
                $clauses[] = '(' . implode(' OR ', $ors) . ')';
                continue;
            }

            [$sql, $bound] = self::parseCondition($filter, $n);
            $clauses[] = $sql;
            $params += $bound;
        }

        return [' WHERE ' . implode(' AND ', $clauses), $params];
    }

    /**
     * Parse one "column=operator.value" condition.
     *
     * @return array{0:string, 1:array<string,string>}
     */
    private static function parseCondition(string $condition, int &$n): array
    {
        // Top-level filters look like "column=op.value"; conditions inside an
        // or=(...) group look like "column.op.value" (no "=").
        $equals = strpos($condition, '=');
        if ($equals !== false) {
            $column = self::identifier(substr($condition, 0, $equals));
            $rest   = substr($condition, $equals + 1);
        } else {
            $dot = strpos($condition, '.');
            if ($dot === false) {
                throw new InvalidArgumentException('Malformed filter: ' . $condition);
            }
            $column = self::identifier(substr($condition, 0, $dot));
            $rest   = substr($condition, $dot + 1);
        }

        $dot = strpos($rest, '.');
        $op  = strtolower($dot === false ? $rest : substr($rest, 0, $dot));
        $val = $dot === false ? '' : substr($rest, $dot + 1);

        if ($op === 'is') {
            return match (strtolower($val)) {
                'null'  => ["{$column} IS NULL", []],
                'true'  => ["{$column} IS TRUE", []],
                'false' => ["{$column} IS FALSE", []],
                default => throw new InvalidArgumentException('Unsupported is filter: ' . $condition),
            };
        }

        if ($op === 'in') {
            $items  = array_values(array_filter(explode(',', trim($val, '()')), static fn ($v) => $v !== ''));
            $holder = [];
            $bound  = [];
            foreach ($items as $item) {
                $key = ':p' . $n++;
                $holder[] = $key;
                $bound[$key] = $item;
            }
            return ["{$column} IN (" . implode(', ', $holder) . ')', $bound];
        }

        $key = ':p' . $n++;
        $sql = match ($op) {
            'eq'    => "{$column} = {$key}",
            'neq'   => "{$column} <> {$key}",
            'gt'    => "{$column} > {$key}",
            'gte'   => "{$column} >= {$key}",
            'lt'    => "{$column} < {$key}",
            'lte'   => "{$column} <= {$key}",
            'like'  => "{$column} LIKE {$key}",
            'ilike' => "{$column} ILIKE {$key}",
            default => throw new InvalidArgumentException('Unsupported filter operator: ' . $op),
        };

        return [$sql, [$key => $val]];
    }
}
