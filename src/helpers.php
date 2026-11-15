<?php
declare(strict_types=1);

/* --------------------------------------------------------------------
 * Output / URL helpers
 * ------------------------------------------------------------------ */

/** Escape untrusted output. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Absolute application URL (honours APP_BASE_URL for sub-folder hosting). */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Redirect and stop. */
function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Trimmed string from POST, or a fallback. */
function post(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

/** Trimmed string from GET, or a fallback. */
function get(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function int_get(string $key, int $default = 0): int
{
    return max(0, (int) get($key, (string) $default));
}

/**
 * Record id for the shared create/edit forms.
 *
 * Normally the id arrives as ?id=N. Falling back to a posted hidden field
 * keeps a submitted form editing the same record even when the query string
 * is missing (for example a POST sent straight to form.php).
 */
function form_id(): int
{
    $id = int_get('id', 0);
    if ($id > 0) {
        return $id;
    }
    return max(0, (int) post('id', '0'));
}

/** Build a URL with the current query string plus overrides. */
function query_url(array $overrides = [], ?string $path = null): string
{
    $params = array_merge($_GET, $overrides);
    $params = array_filter($params, static fn ($v) => $v !== '' && $v !== null);
    $path ??= (string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    $query = http_build_query($params);
    return url($path) . ($query !== '' ? '?' . $query : '');
}

/* --------------------------------------------------------------------
 * Flash messages
 * ------------------------------------------------------------------ */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/* --------------------------------------------------------------------
 * CSRF protection
 * ------------------------------------------------------------------ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Validate the token on every POST; abort with a friendly message otherwise. */
function verify_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        flash('danger', 'Your session expired. Please review the form and submit it again.');
        redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
    }
}

/* --------------------------------------------------------------------
 * Formatting
 * ------------------------------------------------------------------ */

function fmt_date(?string $date, string $fallback = '—'): string
{
    if ($date === null || $date === '') {
        return $fallback;
    }
    $ts = strtotime($date);
    return $ts === false ? $fallback : date('M j, Y', $ts);
}

function fmt_datetime(?string $value): string
{
    if (!$value) {
        return '—';
    }
    $ts = strtotime($value);
    return $ts === false ? '—' : date('M j, Y g:i A', $ts);
}

function fmt_num(mixed $value, int $decimals = 2): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    return number_format((float) $value, $decimals);
}

function fmt_money(mixed $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    return '₱' . number_format((float) $value, 2);
}

function fmt_kg(mixed $value): string
{
    return fmt_num($value) . ' kg';
}

function fmt_ha(mixed $value): string
{
    return fmt_num($value) . ' ha';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last  = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
}

/* --------------------------------------------------------------------
 * Form / option helpers
 * ------------------------------------------------------------------ */

function selected(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

/**
 * Render <option> tags.
 * @param array<int,array<string,mixed>> $rows
 */
function options(array $rows, string $valueKey, array $labelKeys, mixed $selectedValue, string $emptyLabel = ''): string
{
    $html = $emptyLabel !== ''
        ? '<option value="">' . e($emptyLabel) . '</option>'
        : '';
    foreach ($rows as $row) {
        $label = implode(' — ', array_map(
            static fn ($k) => (string) ($row[$k] ?? ''),
            $labelKeys
        ));
        $html .= '<option value="' . e($row[$valueKey]) . '"'
            . selected($row[$valueKey], $selectedValue) . '>' . e($label) . '</option>';
    }
    return $html;
}

/** Bootstrap validation feedback block. */
function invalid_class(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' is-invalid' : '';
}

function error_text(array $errors, string $field): string
{
    return isset($errors[$field])
        ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>'
        : '';
}

/* --------------------------------------------------------------------
 * Pagination
 * ------------------------------------------------------------------ */

/**
 * @return array{page:int,pages:int,offset:int,limit:int,total:int,from:int,to:int}
 */
function paginate(int $total, int $page, int $perPage = ITEMS_PER_PAGE): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = min(max(1, $page), $pages);
    $offset = ($page - 1) * $perPage;

    return [
        'page'   => $page,
        'pages'  => $pages,
        'offset' => $offset,
        'limit'  => $perPage,
        'total'  => $total,
        'from'   => $total === 0 ? 0 : $offset + 1,
        'to'     => min($offset + $perPage, $total),
    ];
}

/** Render pagination links that keep search / filter / sort parameters. */
function pagination_links(array $p, string $path): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $window = 2;
    $start  = max(1, $p['page'] - $window);
    $end    = min($p['pages'], $p['page'] + $window);

    $html = '<nav aria-label="Pagination"><ul class="pagination pagination-sm mb-0">';
    $html .= '<li class="page-item' . ($p['page'] <= 1 ? ' disabled' : '') . '">'
        . '<a class="page-link" href="' . e(query_url(['page' => $p['page'] - 1], $path)) . '">&laquo;</a></li>';

    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . e(query_url(['page' => 1], $path)) . '">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
    }
    for ($i = $start; $i <= $end; $i++) {
        $html .= '<li class="page-item' . ($i === $p['page'] ? ' active' : '') . '">'
            . '<a class="page-link" href="' . e(query_url(['page' => $i], $path)) . '">' . $i . '</a></li>';
    }
    if ($end < $p['pages']) {
        if ($end < $p['pages'] - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . e(query_url(['page' => $p['pages']], $path)) . '">'
            . $p['pages'] . '</a></li>';
    }

    $html .= '<li class="page-item' . ($p['page'] >= $p['pages'] ? ' disabled' : '') . '">'
        . '<a class="page-link" href="' . e(query_url(['page' => $p['page'] + 1], $path)) . '">&raquo;</a></li>';
    $html .= '</ul></nav>';

    return $html;
}

/* --------------------------------------------------------------------
 * Sorting
 * ------------------------------------------------------------------ */

function current_sort(string $default, array $allowed): string
{
    $sort = get('sort', $default);
    return in_array($sort, $allowed, true) ? $sort : $default;
}

function current_dir(string $default = 'asc'): string
{
    return strtolower(get('dir', $default)) === 'desc' ? 'desc' : 'asc';
}

/** Clickable table header that toggles asc/desc. */
function sort_link(string $label, string $column, string $currentSort, string $currentDir): string
{
    $dir  = ($currentSort === $column && $currentDir === 'asc') ? 'desc' : 'asc';
    $icon = '';
    if ($currentSort === $column) {
        $icon = $currentDir === 'asc'
            ? ' <i class="bi bi-caret-up-fill small"></i>'
            : ' <i class="bi bi-caret-down-fill small"></i>';
    }
    return '<a class="table-sort" href="' . e(query_url(['sort' => $column, 'dir' => $dir, 'page' => 1]))
        . '">' . e($label) . $icon . '</a>';
}

/* --------------------------------------------------------------------
 * Database error translation (never show raw SQL to users)
 * ------------------------------------------------------------------ */

function friendly_db_error(PDOException $e): string
{
    $state = (string) ($e->getCode() ?: '');

    // Trigger RAISE EXCEPTION messages carry a readable sentence.
    if (preg_match('/ERROR:\s+(.+?)(?:\s+CONTEXT:|\s+SQL state:|\R|$)/s', $e->getMessage(), $m)) {
        $message = trim($m[1]);
        if ($state === '23514' && $message !== '') {
            return $message;
        }
    }

    return match ($state) {
        '23001' => 'This record is still used by other records, so it cannot be deleted. Remove the related records first.',
        '23503' => 'This record is still used by other records, so it cannot be deleted. Remove the related records first.',
        '23505' => 'That value is already used by another record. Please enter a different value.',
        '23514' => 'The submitted values break a database rule. Please review the form and try again.',
        '23502' => 'A required field was left empty.',
        '22P02' => 'One of the submitted values has an invalid format.',
        '08006', '08001', '08004' => 'The database is unreachable right now. Please try again in a moment.',
        default => 'The record could not be saved. Please review the form and try again.',
    };
}

/* --------------------------------------------------------------------
 * UI helpers
 * ------------------------------------------------------------------ */

function status_badge_class(string $status): string
{
    return match (mb_strtolower($status)) {
        'planned'          => 'text-bg-secondary',
        'planted'          => 'text-bg-info',
        'growing'          => 'text-bg-primary',
        'ready for harvest'=> 'text-bg-warning',
        'harvested'        => 'text-bg-success',
        'failed'           => 'text-bg-danger',
        default            => 'text-bg-light border',
    };
}
