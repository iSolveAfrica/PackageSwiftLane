<?php
/**
 * View helpers for the Swift Lane design system pages (views/sl/*).
 */
require_once __DIR__ . '/sl_routes.php';
require_once __DIR__ . '/sl_chart_interactions.php';
require_once __DIR__ . '/asset.php';
require_once __DIR__ . '/csrf.php';

if (!function_exists('h')) {
    function h($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

/** Escaped value lookup with a dotted path into an array/object tree. */
function sl_v($data, string $path, $default = '')
{
    $cur = $data;
    foreach (explode('.', $path) as $seg) {
        if (is_array($cur) && array_key_exists($seg, $cur)) {
            $cur = $cur[$seg];
        } elseif (is_object($cur) && isset($cur->$seg)) {
            $cur = $cur->$seg;
        } else {
            return h($default);
        }
    }
    if (is_bool($cur)) {
        return $cur ? '1' : '';
    }
    if (is_array($cur) || is_object($cur)) {
        return '';
    }
    return h($cur);
}

/** Raw (unescaped) lookup, for pre-built HTML or style strings produced by trusted PHP code. */
function sl_raw($data, string $path, $default = '')
{
    $cur = $data;
    foreach (explode('.', $path) as $seg) {
        if (is_array($cur) && array_key_exists($seg, $cur)) {
            $cur = $cur[$seg];
        } elseif (is_object($cur) && isset($cur->$seg)) {
            $cur = $cur->$seg;
        } else {
            return $default;
        }
    }
    return is_scalar($cur) ? (string) $cur : $default;
}

function sl_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $ini = '';
    foreach ($parts as $p) {
        if ($p !== '') {
            $ini .= mb_strtoupper(mb_substr($p, 0, 1));
        }
        if (mb_strlen($ini) >= 2) {
            break;
        }
    }
    return $ini !== '' ? $ini : '?';
}

/** Money in the installation currency (cdb_settings), e.g. $1,234.50. */
function sl_money($amount, ?string $currency = null): string
{
    global $core;
    $symbol = $currency ?? ($core->for_symbol ?? ($core->currency ?? '$'));
    $dec = isset($core->for_decimal) ? (int) $core->for_decimal : 2;
    $formatted = number_format((float) $amount, $dec, $core->dec_point ?? '.', $core->thousands_sep ?? ',');
    return ($core->cformat ?? '1') == '2' ? $formatted . ' ' . $symbol : $symbol . $formatted;
}

function sl_num($n, int $dec = 0): string
{
    return number_format((float) $n, $dec);
}

function sl_date($value, string $format = 'M j, Y'): string
{
    if (!$value || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '';
    }
    $ts = is_numeric($value) ? (int) $value : strtotime((string) $value);
    return $ts ? date($format, $ts) : '';
}

function sl_ago($value): string
{
    $ts = is_numeric($value) ? (int) $value : strtotime((string) $value);
    if (!$ts) {
        return '';
    }
    $d = time() - $ts;
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d / 60) . ' min ago';
    if ($d < 86400) return floor($d / 3600) . ' h ago';
    if ($d < 86400 * 14) return floor($d / 86400) . ' d ago';
    return date('M j', $ts);
}

/** Status pill class for a cdb_styles status id. */
function sl_statusClass($statusId): string
{
    $map = [
        1 => 'pending', 2 => 'sorting', 3 => 'transit', 4 => 'sorting', 5 => 'onway', 6 => 'delivered', 7 => 'onway',
        8 => 'delivered', 10 => 'delivered', 11 => 'pending', 12 => 'overdue', 13 => 'sorting', 14 => 'onway',
        15 => 'delivered', 16 => 'overdue', 17 => 'plain', 18 => 'pending', 19 => 'paid', 21 => 'overdue',
        23 => 'pending', 25 => 'overdue', 28 => 'overdue', 29 => 'overdue', 32 => 'delivered',
    ];
    return $map[(int) $statusId] ?? 'plain';
}

function sl_pill($label, $cls = 'plain', $style = ''): string
{
    $st = $style ? ' style="' . h($style) . '"' : '';
    return '<span class="sl-pill ' . h($cls) . '"' . $st . '>' . h($label) . '</span>';
}

/** Percentage (0-100) safe for width styles. */
function sl_pct($part, $whole): int
{
    $whole = (float) $whole;
    if ($whole <= 0) {
        return 0;
    }
    return (int) max(0, min(100, round((float) $part / $whole * 100)));
}

/** Current page URL without query, used for pagination links. */
function sl_self(): string
{
    return basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
}

/** Build a URL with merged query parameters. */
function sl_url(array $params = [], ?string $base = null): string
{
    $base = $base ?? sl_self();
    $q = array_merge($_GET, $params);
    foreach ($q as $k => $v) {
        if ($v === null || $v === '') {
            unset($q[$k]);
        }
    }
    return $base . ($q ? '?' . http_build_query($q) : '');
}

/** Pager markup in the design's .sl-pager style. */
function sl_pager(int $page, int $perPage, int $total, string $noun = 'rows'): string
{
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    $from = $total ? ($page - 1) * $perPage + 1 : 0;
    $to = min($total, $page * $perPage);
    $out = '<div class="sl-pager"><span>Showing ' . $from . '–' . $to . ' of ' . sl_num($total) . ' ' . h($noun) . '</span><div class="sl-pg">';
    $start = max(1, $page - 2);
    $end = min($pages, $start + 4);
    if ($page > 1) {
        $out .= '<a href="' . h(sl_url(['page' => $page - 1])) . '" aria-label="Previous page">‹</a>';
    }
    for ($i = $start; $i <= $end; $i++) {
        $out .= '<a class="' . ($i === $page ? 'on' : '') . '" href="' . h(sl_url(['page' => $i])) . '">' . $i . '</a>';
    }
    if ($page < $pages) {
        $out .= '<a href="' . h(sl_url(['page' => $page + 1])) . '" aria-label="Next page">›</a>';
    }
    return $out . '</div></div>';
}

/**
 * Render a design page inside the application shell.
 * $page: id (nav key), title, board (artboard name for CSS), actions (html), lede, primary (['href','label']),
 *        theme ('dk' for the dark pages), wide (bool), plain (bool: no shell, e.g. auth pages).
 */
function sl_render(string $view, array $page, array $d = []): void
{
    global $user, $core, $lang, $permissions, $userData;
    $page += ['id' => '', 'title' => $core->site_name ?? 'Swift Lane', 'board' => '', 'actions' => '', 'theme' => '', 'plain' => false, 'scripts' => [], 'styles' => []];
    $slPage = $page;
    $viewFile = CDP_ROOT . '/views/sl/pages/' . $view . '.php';
    if ($page['plain']) {
        include CDP_ROOT . '/views/sl/inc/plain_start.php';
        include $viewFile;
        include CDP_ROOT . '/views/sl/inc/plain_end.php';
        return;
    }
    include CDP_ROOT . '/views/sl/inc/shell_start.php';
    include $viewFile;
    include CDP_ROOT . '/views/sl/inc/shell_end.php';
}

/** One row from a query helper; returns [] on failure. */
function sl_rows(string $sql, array $binds = []): array
{
    $db = new Conexion();
    $db->cdp_query($sql);
    foreach ($binds as $k => $v) {
        $db->bind($k, $v);
    }
    $db->cdp_execute();
    $rows = $db->cdp_registros();
    return is_array($rows) ? $rows : [];
}

function sl_row(string $sql, array $binds = [])
{
    $rows = sl_rows($sql . (stripos($sql, ' limit ') === false ? ' LIMIT 1' : ''), $binds);
    return $rows[0] ?? null;
}

function sl_scalar(string $sql, array $binds = [], $default = 0)
{
    $r = sl_row($sql, $binds);
    if (!$r) {
        return $default;
    }
    $vars = get_object_vars($r);
    return reset($vars);
}

/** True if a table exists (optional modules created by migrations). */
function sl_tableExists(string $table): bool
{
    static $cache = [];
    if (!isset($cache[$table])) {
        $cache[$table] = (int) sl_scalar('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t', [':t' => $table]) > 0;
    }
    return $cache[$table];
}

function sl_columnExists(string $table, string $column): bool
{
    static $cache = [];
    $k = $table . '.' . $column;
    if (!isset($cache[$k])) {
        $cache[$k] = (int) sl_scalar('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c', [':t' => $table, ':c' => $column]) > 0;
    }
    return $cache[$k];
}

/** JSON response for the sl AJAX endpoints. */
function sl_json($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Common page gate for sl controllers: login + permission(s). */
function sl_gate($permission = null): void
{
    global $user, $permissions, $userData;
    if (!$user->cdp_loginCheck()) {
        $next = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: login.php' . ($next ? '?next=' . $next : ''));
        exit;
    }
    $permissions = $user->cdp_getUserPermissions();
    $userData = $user->cdp_getUserData();
    if (is_file(CDP_ROOT . '/helpers/sl_auth.php')) {
        require_once CDP_ROOT . '/helpers/sl_auth.php';
        if (function_exists('sl_passwordRotationGuard')) {
            sl_passwordRotationGuard();
        }
    }
    if ($permission !== null && !$user->cdp_hasPermission($permission)) {
        header('Location: error403.php');
        exit;
    }
}

/** Current user's effective role flags. */
function sl_userIs(string $flag): bool
{
    require_once CDP_ROOT . '/helpers/rbac.php';
    $lvl = (int) ($_SESSION['userlevel'] ?? 0);
    if ($flag === 'client') return cdp_roleHasFlag($lvl, 'is_client') || $lvl === 1;
    if ($flag === 'driver') return cdp_roleHasFlag($lvl, 'is_driver') || $lvl === 3;
    if ($flag === 'superadmin') return cdp_roleHasFlag($lvl, 'is_superadmin') || $lvl === 9;
    if ($flag === 'admin') return cdp_roleHasFlag($lvl, 'is_admin') || in_array($lvl, [2, 9], true);
    if ($flag === 'staff') return !sl_userIs('client') && !sl_userIs('driver');
    return false;
}

/** Raw lookup returning the value itself (arrays allowed) for loops and conditions. */
function sl_get($data, string $path, $default = null)
{
    $cur = $data;
    foreach (explode('.', $path) as $seg) {
        if (is_array($cur) && array_key_exists($seg, $cur)) {
            $cur = $cur[$seg];
        } elseif (is_object($cur) && isset($cur->$seg)) {
            $cur = $cur->$seg;
        } else {
            return $default;
        }
    }
    return $cur;
}
