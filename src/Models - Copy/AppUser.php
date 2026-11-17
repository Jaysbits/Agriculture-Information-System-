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
 *
 * Generic all() / find() / create() / update() / delete() / count()
 * come from the shared Model base class.
 */
final class AppUser
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function all(array $filters = [], array $options = []): array
    {
        return $this->db->select('app_user', '*', $filters, $options);
    }

    public function find(int $id): ?array
    {
        return $this->db->find('app_user', $id, 'user_id');
    }

    public function create(array $data): array
    {
        return $this->db->insert('app_user', $data);
    }

    public function update(int $id, array $data): array
    {
        return $this->db->update('app_user', $data, ['user_id=eq.' . $id]);
    }

    public function count(array $filters = []): int
    {
        return count($this->db->select('app_user', 'user_id', $filters));
    }

    protected function paginateQuery(string $countSql, string $rowsSql, array $params, int $page): array
    {
        $total = Database::count($countSql, $params);
        $pager = paginate($total, $page);
        $rows = Database::all($rowsSql, array_merge($params, [
            ':limit'  => $pager['limit'],
            ':offset' => $pager['offset'],
        ]));
        return ['rows' => $rows, 'pager' => $pager];
    }

    public const SORTABLE = ['username', 'name', 'role', 'active', 'last_login'];

    /** Number of enabled administrator accounts. */
    public function activeAdminCount(): int
    {
        return $this->count(['role=eq.admin', 'is_active=is.true']);
    }

    /**
     * Filtered, sorted, paginated list for the users index view.
     *
     * @return array{rows: array<int,array<string,mixed>>, pager: array<string,mixed>}
     */
    public function listing(string $q, string $role, string $sort, string $dir, int $page): array
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
        $orderSql = ($orderMap[$sort] ?? $orderMap['username']) . ' ' . strtoupper($dir) . ', user_id DESC';

        return $this->paginateQuery(
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
    public function save(array $input, int $id = 0, int $currentUserId = 0): array
    {
        $existing = $id > 0 ? $this->find($id) : null;

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

        if ($existing && $existing['role'] === 'admin' && ($role !== 'admin' || !$isActive) && $this->activeAdminCount() <= 1) {
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
                $clean = [
                    'username'  => $v->value('username'),
                    'full_name' => $v->value('full_name'),
                    'role'      => $role,
                    // A PHP false binds as '' which is not a valid boolean;
                    // Postgres accepts the strings 'true' / 'false'.
                    'is_active' => $isActive ? 'true' : 'false',
                ];
                if ($password !== '') {
                    $clean['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                }
                $this->update($id, $clean);

                return ['ok' => true, 'id' => $id, 'errors' => [], 'dbError' => null];
            }

            $row = $this->create([
                'username'      => $v->value('username'),
                'full_name'     => $v->value('full_name'),
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role'          => $role,
                'is_active'     => $isActive ? 'true' : 'false',
            ]);

            return ['ok' => true, 'id' => (int) ($row['user_id'] ?? 0), 'errors' => [], 'dbError' => null];
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
    public function delete(int $id, int $currentUserId = 0): array
    {
        if ($id === $currentUserId) {
            return ['ok' => false, 'deleted' => false, 'error' => 'You cannot delete your own account while signed in.'];
        }

        $target = $this->find($id);
        if (!$target) {
            return ['ok' => true, 'deleted' => false, 'error' => null];
        }

        if ($target['role'] === 'admin' && $target['is_active'] && $this->activeAdminCount() <= 1) {
            return ['ok' => false, 'deleted' => false, 'error' => 'The last active administrator account cannot be deleted.'];
        }

        return $this->db->delete('app_user', ['user_id=eq.' . $id]);
    }
}

