<?php
declare(strict_types=1);

/**
 * =====================================================================
 * MODEL — AppUser  (table: app_user)
 * =====================================================================
 * Database logic for system accounts: filtered/sorted/paginated
 * listing, lookup by id, validation (including password policy and the
 * "last active administrator" guard) and create / update / delete.
 * HTML lives in public/users/*.php.
 */
final class AppUser extends Model
{
    protected static string $table      = 'app_user';
    protected static string $primaryKey = 'user_id';

    public const SORTABLE = ['username', 'name', 'role', 'active', 'last_login'];

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM app_user WHERE user_id = :id', [':id' => $id]);
    }

    /** Number of enabled administrator accounts. */
    public static function activeAdminCount(): int
    {
        return Database::count("SELECT COUNT(*) FROM app_user WHERE role = 'admin' AND is_active = TRUE");
    }

    /**
     * Filtered, sorted, paginated list for the users index view.
     *
     * @return array{rows: array<int,array<string,mixed>>, pager: array<string,mixed>}
     */
    public static function listing(string $q, string $role, string $sort, string $dir, int $page): array
    {
        $where  = [];
        $params = [];

        if ($q !== '') {
            $where[] = '(username ILIKE :q OR full_name ILIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if (in_array($role, ['admin', 'staff'], true)) {
            $where[] = 'role = :role';
            $params[':role'] = $role;
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $orderMap = [
            'username'   => 'username',
            'name'       => 'full_name',
            'role'       => 'role',
            'active'     => 'is_active',
            'last_login' => 'last_login_at',
        ];
        $orderSql = $orderMap[$sort] . ' ' . strtoupper($dir) . ', user_id DESC';

        return self::paginateQuery(
            'SELECT COUNT(*) FROM app_user' . $whereSql,
            "SELECT * FROM app_user {$whereSql} ORDER BY {$orderSql} LIMIT :limit OFFSET :offset",
            $params,
            $page
        );
    }

    /**
     * Validate and persist a user account.
     *
     * @param int $id           0 = create, >0 = update.
     * @param int $currentUserId Signed-in user (guards self-deactivation).
     * @return array{ok:bool, id:?int, errors: array<string,string>, dbError:?string}
     */
    public static function save(array $input, int $id = 0, int $currentUserId = 0): array
    {
        $existing = $id > 0 ? self::find($id) : null;

        $password = (string) ($input['password'] ?? '');
        $confirm  = (string) ($input['password_confirm'] ?? '');
        $role     = isset($input['role']) && is_string($input['role']) ? trim($input['role']) : 'staff';
        $isActive = isset($input['is_active']) ? 1 : 0;

        $v = new Validator($input);
        $v->required('username', 'Username')
          ->min('username', 3, 'Username')
          ->max('username', 50, 'Username')
          ->regex('username', '/^[A-Za-z0-9._-]+$/', 'Username', 'may only contain letters, numbers, dots, underscores and hyphens.')
          ->unique('username', 'app_user', 'username', 'Username', $id > 0 ? $id : null, 'user_id')
          ->required('full_name', 'Full name')
          ->min('full_name', 3, 'Full name')
          ->max('full_name', 120, 'Full name')
          ->inList('role', ['admin', 'staff'], 'Role');

        if (!$existing && $password === '') {
            $v->addError('password', 'Password is required for a new account.');
        }
        if ($password !== '') {
            if (strlen($password) < 8) {
                $v->addError('password', 'Password must be at least 8 characters long.');
            } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
                $v->addError('password', 'Password must contain at least one letter and one number.');
            }
            if ($password !== $confirm) {
                $v->addError('password_confirm', 'The password confirmation does not match.');
            }
        }

        if ($existing && $existing['role'] === 'admin' && ($role !== 'admin' || !$isActive) && self::activeAdminCount() <= 1) {
            $v->addError('role', 'The last active administrator cannot be demoted or deactivated.');
        }
        if ($existing && $id === $currentUserId && !$isActive) {
            $v->addError('is_active', 'You cannot deactivate your own account.');
        }

        if (!$v->passes()) {
            return ['ok' => false, 'id' => null, 'errors' => $v->errors(), 'dbError' => null];
        }

        try {
            if ($existing) {
                $sql = 'UPDATE app_user SET username = :username, full_name = :full_name,
                               role = :role, is_active = :is_active';
                $params = [
                    ':username'  => $v->value('username'),
                    ':full_name' => $v->value('full_name'),
                    ':role'      => $role,
                    ':is_active' => $isActive ? 'true' : 'false',
                    ':id'        => $id,
                ];
                if ($password !== '') {
                    $sql .= ', password_hash = :password_hash';
                    $params[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                }
                $sql .= ' WHERE user_id = :id';
                Database::run($sql, $params);

                return ['ok' => true, 'id' => $id, 'errors' => [], 'dbError' => null];
            }

            $newId = (int) Database::run(
                'INSERT INTO app_user (username, full_name, password_hash, role, is_active)
                 VALUES (:username, :full_name, :password_hash, :role, :is_active)
                 RETURNING user_id',
                [
                    ':username'      => $v->value('username'),
                    ':full_name'     => $v->value('full_name'),
                    ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    ':role'          => $role,
                    ':is_active'     => $isActive ? 'true' : 'false',
                ]
            )->fetchColumn();

            return ['ok' => true, 'id' => $newId, 'errors' => [], 'dbError' => null];
        } catch (PDOException $e) {
            error_log('[user save] ' . $e->getMessage());
            return ['ok' => false, 'id' => null, 'errors' => [], 'dbError' => friendly_db_error($e)];
        }
    }

    /**
     * Delete an account, enforcing the self-delete and last-admin rules.
     *
     * @return array{ok:bool, deleted:bool, error:?string}
     */
    public static function delete(int $id, int $currentUserId = 0): array
    {
        if ($id === $currentUserId) {
            return ['ok' => false, 'deleted' => false, 'error' => 'You cannot delete your own account while signed in.'];
        }

        $target = self::find($id);
        if (!$target) {
            return ['ok' => true, 'deleted' => false, 'error' => null];
        }

        if ($target['role'] === 'admin' && $target['is_active'] && self::activeAdminCount() <= 1) {
            return ['ok' => false, 'deleted' => false, 'error' => 'The last active administrator account cannot be deleted.'];
        }

        return parent::delete($id);
    }
}
