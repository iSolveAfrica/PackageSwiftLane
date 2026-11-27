<?php
/**
 * Developer console, package "infra": super-admin infrastructure overrides, effective server
 * figures, storage breakdown, infrastructure events, the alert-metric catalogue and e-mail
 * notification of new alerts to the developers.
 *
 * Overrides (one store, table cdb_dev_overrides, created on first use)
 *   Figures a shared host cannot report, or reports wrongly (the whole physical disk instead of
 *   the plan quota, no /proc/meminfo, ...). Each row: k, v, note, set_by, set_by_name, set_at.
 *   Read with cdp_devOverride($key) (value or null) and cdp_devOverrideMeta($key)
 *   (['v','by','at','note'] or null). Written only through ajax/sl/dev_infra_ajax.php
 *   (POST + CSRF + cdp_devCanUse + section "infrastructure"; every change in cdb_activity_log).
 *   Shown on Infrastructure (dev_infrastructure.php), Operating costs (cost_settings.php,
 *   "Infrastructure overrides") and, through cdp_devOverride, the observe pages.
 *
 * Nothing here runs a shell command; folder sizes come from a capped PHP directory walk.
 */
if (!defined('CDP_ROOT')) {
    http_response_code(403);
    exit;
}
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/devops.php';

/* ================================================================ access */

if (!function_exists('sl_dinfAllowed')) {
    /** Assigned super admin (cdp_devCanUse) and, when the access package is installed, the section switch. */
    function sl_dinfAllowed($user, string $section): bool
    {
        if (!is_object($user) || !function_exists('cdp_devCanUse') || !cdp_devCanUse($user)) {
            return false;
        }
        if (function_exists('cdp_devSectionAllowed') && !cdp_devSectionAllowed($user, $section)) {
            return false;
        }
        return true;
    }
}

/* ============================================================= overrides */

/**
 * Every figure the super admin may override.
 * type: num | text | date | enum. unit shown after numbers. group: Capacity | Plan | Cost.
 */
function sl_dinfCatalog(): array
{
    return [
        'disk_total_gb' => ['label' => 'Total disk space', 'unit' => 'GB', 'type' => 'num', 'group' => 'Capacity', 'icon' => 'i-folder',
            'hint' => 'Your plan quota. Shared hosts often report the whole physical disk instead.'],
        'disk_used_gb' => ['label' => 'Disk space used', 'unit' => 'GB', 'type' => 'num', 'group' => 'Capacity', 'icon' => 'i-folder',
            'hint' => 'From the hosting control panel when PHP sees a different figure.'],
        'ram_total_gb' => ['label' => 'Total RAM', 'unit' => 'GB', 'type' => 'num', 'group' => 'Capacity', 'icon' => 'i-cpu',
            'hint' => 'The memory your plan gives you. Containers and shared hosts often show the host machine.'],
        'ram_used_gb' => ['label' => 'RAM in use', 'unit' => 'GB', 'type' => 'num', 'group' => 'Capacity', 'icon' => 'i-cpu',
            'hint' => 'Only if the control panel shows a figure PHP cannot read.'],
        'cpu_cores' => ['label' => 'CPU cores', 'unit' => 'cores', 'type' => 'num', 'group' => 'Capacity', 'icon' => 'i-cpu',
            'hint' => 'vCPUs of the plan (load and CPU % are judged against this number).'],
        'bandwidth_limit_gb' => ['label' => 'Monthly bandwidth allowance', 'unit' => 'GB', 'type' => 'num', 'group' => 'Capacity', 'icon' => 'i-activity',
            'hint' => 'Transfer included in the plan each month.'],
        'bandwidth_used_gb' => ['label' => 'Bandwidth used this month', 'unit' => 'GB', 'type' => 'num', 'group' => 'Capacity', 'icon' => 'i-activity',
            'hint' => 'Copy it from the control panel; PHP cannot see the host\'s traffic meter.'],
        'db_limit_mb' => ['label' => 'Database size limit', 'unit' => 'MB', 'type' => 'num', 'group' => 'Capacity', 'icon' => 'i-database',
            'hint' => 'Many shared plans cap each MySQL database.'],
        'inode_limit' => ['label' => 'File count limit (inodes)', 'unit' => 'files', 'type' => 'num', 'group' => 'Capacity', 'icon' => 'i-file',
            'hint' => 'Shared hosts limit the number of files; uploads count towards it.'],
        'plan_name' => ['label' => 'Hosting plan', 'unit' => '', 'type' => 'text', 'group' => 'Plan', 'icon' => 'i-server', 'hint' => 'e.g. "Business Hosting 2"'],
        'provider' => ['label' => 'Provider', 'unit' => '', 'type' => 'text', 'group' => 'Plan', 'icon' => 'i-cloud', 'hint' => 'Company that runs the server.'],
        'region' => ['label' => 'Region / data centre', 'unit' => '', 'type' => 'text', 'group' => 'Plan', 'icon' => 'i-pin', 'hint' => 'e.g. "Frankfurt (eu-central)"'],
        'renewal_date' => ['label' => 'Plan renews on', 'unit' => '', 'type' => 'date', 'group' => 'Plan', 'icon' => 'i-calendar', 'hint' => 'Next invoice date of the hosting plan.'],
        'server_cost_month' => ['label' => 'Monthly server cost', 'unit' => '', 'type' => 'num', 'group' => 'Cost', 'icon' => 'i-dollar',
            'hint' => 'Replaces the sum of the "Servers" lines of Operating costs on the developer pages.'],
        'server_cost_currency' => ['label' => 'Server cost currency', 'unit' => '', 'type' => 'enum', 'opts' => ['USD', 'GHS', 'EUR', 'GBP'], 'group' => 'Cost', 'icon' => 'i-dollar',
            'hint' => 'Currency of the monthly server cost.'],
    ];
}

function sl_dinfEnsure(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    if (cdp_devTableExists('cdb_dev_overrides')) {
        return $ok = true;
    }
    cdp_devX("CREATE TABLE IF NOT EXISTS cdb_dev_overrides (
        k VARCHAR(40) NOT NULL, v VARCHAR(190) NOT NULL, note VARCHAR(255) NULL,
        set_by INT NULL, set_by_name VARCHAR(120) NULL, set_at DATETIME NOT NULL,
        PRIMARY KEY (k)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // cdp_devTableExists() caches its first (false) answer for the request: ask information_schema directly
    return $ok = (bool) cdp_devOne("SELECT 1 AS x FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_dev_overrides'");
}

/** All overrides, keyed. Cached per request (reset with $reload after a write). */
function sl_dinfOverrides(bool $reload = false): array
{
    static $all = null;
    if ($all !== null && !$reload) {
        return $all;
    }
    $all = [];
    try {
        if (sl_dinfEnsure()) {
            foreach (cdp_devQ('SELECT k, v, note, set_by, set_by_name, set_at FROM cdb_dev_overrides') as $r) {
                $all[(string) $r->k] = ['v' => (string) $r->v, 'note' => (string) ($r->note ?? ''), 'by_id' => (int) $r->set_by, 'by' => (string) ($r->set_by_name ?: 'a super admin'), 'at' => (string) $r->set_at];
            }
        }
    } catch (Throwable $e) {
        $all = [];
    }
    return $all;
}

if (!function_exists('cdp_devOverride')) {
    /** Super-admin override of an infrastructure figure, or null when none is set. */
    function cdp_devOverride($key)
    {
        $o = sl_dinfOverrides()[(string) $key] ?? null;
        return $o === null || $o['v'] === '' ? null : $o['v'];
    }
}

if (!function_exists('cdp_devOverrideMeta')) {
    /** ['v','by','at','note'] of an override, or null. */
    function cdp_devOverrideMeta($key)
    {
        return sl_dinfOverrides()[(string) $key] ?? null;
    }
}

/** "set by Test Admin on Nov 26, 2026" */
function sl_dinfOvrLabel(?array $m): string
{
    if (!$m) {
        return '';
    }
    return 'set by ' . $m['by'] . ' on ' . sl_date($m['at'], 'M j, Y');
}

/** Validate and normalise a submitted value. Returns [value, error]. */
function sl_dinfValidate(string $key, $raw): array
{
    $cat = sl_dinfCatalog()[$key] ?? null;
    if (!$cat) {
        return [null, 'Unknown figure'];
    }
    $raw = trim((string) $raw);
    if ($raw === '') {
        return [null, 'Enter a value, or use Clear to remove the override'];
    }
    switch ($cat['type']) {
        case 'num':
            $raw = str_replace([',', ' '], '', $raw);
            if (!is_numeric($raw) || (float) $raw < 0 || (float) $raw > 1000000000) {
                return [null, 'A number from 0 upwards'];
            }
            if ($key === 'cpu_cores' && ((float) $raw < 1 || (float) $raw > 1024 || floor((float) $raw) != (float) $raw)) {
                return [null, 'A whole number of cores (1-1024)'];
            }
            return [rtrim(rtrim(number_format((float) $raw, 3, '.', ''), '0'), '.'), null];
        case 'date':
            $d = DateTime::createFromFormat('Y-m-d', $raw);
            if (!$d || $d->format('Y-m-d') !== $raw) {
                return [null, 'A date (YYYY-MM-DD)'];
            }
            return [$raw, null];
        case 'enum':
            return in_array($raw, $cat['opts'], true) ? [$raw, null] : [null, 'Pick one of the listed values'];
        default:
            if (strpbrk($raw, '<>') !== false) {
                return [null, 'Leave out < and > (plain text only)'];
            }
            $v = trim(preg_replace('/\s+/u', ' ', $raw));
            if (mb_strlen($v) > 120) {
                return [null, 'At most 120 characters'];
            }
            return [$v, null];
    }
}

/** Save (insert or replace) an override. */
function sl_dinfOverrideSet(string $key, string $value, string $note, int $uid, string $uname): bool
{
    if (!sl_dinfEnsure()) {
        return false;
    }
    $ok = cdp_devX('INSERT INTO cdb_dev_overrides (k, v, note, set_by, set_by_name, set_at) VALUES (:k, :v, :n, :u, :un, :t)
        ON DUPLICATE KEY UPDATE v = VALUES(v), note = VALUES(note), set_by = VALUES(set_by), set_by_name = VALUES(set_by_name), set_at = VALUES(set_at)',
        [':k' => $key, ':v' => $value, ':n' => mb_substr($note, 0, 255) ?: null, ':u' => $uid, ':un' => mb_substr($uname, 0, 120), ':t' => cdp_devNow()]);
    sl_dinfOverrides(true);
    return (bool) $ok;
}

function sl_dinfOverrideClear(string $key): bool
{
    if (!sl_dinfEnsure()) {
        return false;
    }
    $ok = cdp_devX('DELETE FROM cdb_dev_overrides WHERE k = :k', [':k' => $key]);
    sl_dinfOverrides(true);
    return (bool) $ok;
}

/* ====================================================== measured figures */

/** /proc/meminfo in bytes, or [] when not readable (shared hosting). */
function sl_dinfMeminfo(): array
{
    static $m = null;
    if ($m !== null) {
        return $m;
    }
    $m = [];
    $raw = @is_readable('/proc/meminfo') ? (string) @file_get_contents('/proc/meminfo') : '';
    foreach (explode("\n", $raw) as $l) {
        if (preg_match('/^(\w+):\s+(\d+)/', $l, $x)) {
            $m[$x[1]] = (float) $x[2] * 1024;
        }
    }
    return $m;
}

function sl_dinfCores(): ?int
{
    $raw = @is_readable('/proc/cpuinfo') ? (string) @file_get_contents('/proc/cpuinfo') : '';
    $n = preg_match_all('/^processor\s*:/m', $raw);
    return $n ? (int) $n : null;
}

/** Monthly equivalent of the "Servers" lines of Operating costs, with currency; null when none. */
function sl_dinfServerCostLines(): ?array
{
    if (!sl_tableExists('cdb_operating_costs')) {
        return null;
    }
    if (!function_exists('sl_costMonthly')) {
        require_once CDP_ROOT . '/views/sl/data/costs.php';
    }
    $by = [];
    $n = 0;
    foreach (sl_rows("SELECT amount, currency, frequency, applies_from FROM cdb_operating_costs WHERE is_active = 1 AND category = 'infra'") as $r) {
        $c = strtoupper((string) $r->currency) ?: 'USD';
        $by[$c] = ($by[$c] ?? 0) + sl_costMonthly($r->amount, (string) $r->frequency, $r->applies_from);
        $n++;
    }
    if (!$n) {
        return null;
    }
    return ['by' => $by, 'n' => $n, 'text' => implode(' + ', array_map(fn($c, $v) => $c . ' ' . number_format($v, 2), array_keys($by), $by))];
}

/**
 * What this host itself reports for each overridable figure.
 * Each: v (number|string|null), text, src (where it comes from), how (how to get it when not measured).
 */
function sl_dinfMeasured(): array
{
    static $out = null;
    if ($out !== null) {
        return $out;
    }
    $gb = 1073741824;
    $tot = @disk_total_space(CDP_ROOT) ?: null;
    $free = @disk_free_space(CDP_ROOT);
    $mem = sl_dinfMeminfo();
    $memT = $mem['MemTotal'] ?? null;
    $memA = $mem['MemAvailable'] ?? (isset($mem['MemFree']) ? $mem['MemFree'] + ($mem['Cached'] ?? 0) : null);
    $cores = sl_dinfCores();
    $nm = fn(string $how) => ['v' => null, 'text' => 'not measured', 'src' => '', 'how' => $how];
    $num = fn($v, string $unit, string $src, int $dec = 1) => ['v' => $v, 'text' => number_format((float) $v, $dec) . ($unit ? ' ' . $unit : ''), 'src' => $src, 'how' => ''];
    $dbBytes = 0;
    try {
        $dbBytes = (float) sl_scalar('SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = DATABASE()', [], 0);
    } catch (Throwable $e) {
        $dbBytes = 0;
    }
    $cost = sl_dinfServerCostLines();
    $out = [
        'disk_total_gb' => $tot ? $num($tot / $gb, 'GB', 'disk_total_space() of the app folder') : $nm('disk_total_space() is disabled on this host: enter the plan quota.'),
        'disk_used_gb' => $tot && $free !== false ? $num(($tot - $free) / $gb, 'GB', 'disk_total_space() - disk_free_space(): used or reserved. Blocks reserved for root count as used here, so this can be higher than df\'s "Used"; set this figure from the control panel if it differs') : $nm('disk_free_space() is disabled on this host: copy the figure from the control panel.'),
        'ram_total_gb' => $memT ? $num($memT / $gb, 'GB', '/proc/meminfo MemTotal') : $nm('/proc/meminfo is not readable on this host (normal on shared hosting): enter the plan RAM.'),
        'ram_used_gb' => $memT && $memA !== null ? $num(($memT - $memA) / $gb, 'GB', '/proc/meminfo MemTotal - MemAvailable', 2) : $nm('/proc/meminfo is not readable on this host: copy the figure from the control panel.'),
        'cpu_cores' => $cores ? ['v' => $cores, 'text' => $cores . ' cores', 'src' => '/proc/cpuinfo', 'how' => ''] : $nm('/proc/cpuinfo is not readable: enter the vCPUs of the plan.'),
        'bandwidth_limit_gb' => $nm('A plan allowance: only the hosting provider knows it.'),
        'bandwidth_used_gb' => $nm('PHP cannot read the host\'s traffic meter. Copy it from the control panel each month.'),
        'db_limit_mb' => $nm('A plan limit. Current database size: ' . sl_devBytesSafe($dbBytes) . ' (information_schema).'),
        'inode_limit' => $nm('A plan limit. The storage scan below counts the app\'s own files.'),
        'plan_name' => $nm('Not visible to PHP.'),
        'provider' => $nm('Not visible to PHP. Host name: ' . (string) gethostname() . '.'),
        'region' => $nm('Not visible to PHP. Time zone: ' . date_default_timezone_get() . '.'),
        'renewal_date' => $nm('From the provider\'s invoices.'),
        'server_cost_month' => $cost ? ['v' => array_sum($cost['by']), 'text' => $cost['text'], 'src' => 'Operating costs › Servers (' . $cost['n'] . ' line' . ($cost['n'] === 1 ? '' : 's') . ', monthly equivalent)', 'how' => '']
            : $nm('No "Servers" line in Operating costs yet: add one there or set the figure here.'),
        'server_cost_currency' => $cost ? ['v' => implode('/', array_keys($cost['by'])), 'text' => implode(' / ', array_keys($cost['by'])), 'src' => 'Operating costs', 'how' => ''] : $nm('Follows the cost lines.'),
    ];
    return $out;
}

/**
 * Seconds the database clock (SELECT NOW()) runs ahead of the application clock (date() after Core
 * applied the configured time zone), rounded to 15 minutes. helpers/cron_run.php stamps cdb_cron_runs
 * with the database's NOW(), while the console's own tables use cdp_devNow(): convert cron times
 * with sl_dinfDbToApp() before showing them or comparing them with PHP's time().
 */
function sl_dinfDbSkew(): int
{
    static $s = null;
    if ($s !== null) {
        return $s;
    }
    sl_dinfAppTz();
    try {
        $db = (string) sl_scalar('SELECT NOW()', [], '');
    } catch (Throwable $e) {
        $db = '';
    }
    $t = $db !== '' ? strtotime($db) : false;
    return $s = $t ? (int) (round(($t - time()) / 900) * 900) : 0;
}

/** The database session's offset from UTC, e.g. "+00:00" (TIMEDIFF(NOW(), UTC_TIMESTAMP())). */
function sl_dinfDbOffset(): string
{
    try {
        $t = (string) sl_scalar('SELECT TIMEDIFF(NOW(), UTC_TIMESTAMP())', [], '');
    } catch (Throwable $e) {
        $t = '';
    }
    if (!preg_match('/^(-)?(\d{1,2}):(\d{2})/', $t, $m)) {
        return ' unknown';
    }
    return ($m[1] ? '-' : '+') . str_pad($m[2], 2, '0', STR_PAD_LEFT) . ':' . $m[3];
}

/** A DATETIME written with the database's NOW(), on the application clock. */
function sl_dinfDbToApp($ts): string
{
    if (!$ts) {
        return '';
    }
    $t = strtotime((string) $ts);
    return $t ? date('Y-m-d H:i:s', $t - sl_dinfDbSkew()) : (string) $ts;
}

/** "1 file" / "3 files" */
function sl_dinfPlural(int $n, string $one, ?string $many = null): string
{
    return sl_num($n) . ' ' . ($n === 1 ? $one : ($many ?? $one . 's'));
}

/**
 * Database backups the console can see, newest first: dumps in backups/ (.sql, .sql.gz, .zip, as
 * written and accepted by backup.php), console backups in the private folder (helpers/dev_github.php)
 * and finished backup jobs in cdb_dev_db_jobs. Each: name, at (unix), bytes, src.
 */
function sl_dinfBackups(): array
{
    static $out = null;
    if ($out !== null) {
        return $out;
    }
    $out = [];
    $seen = [];
    $dirs = [CDP_ROOT . '/backups'];
    if (function_exists('sl_dbBackupDir')) {
        try {
            $pd = (string) sl_dbBackupDir();
            if ($pd !== '' && realpath($pd) !== realpath(CDP_ROOT . '/backups')) {
                $dirs[] = $pd;
            }
        } catch (Throwable $e) {
        }
    }
    foreach ($dirs as $i => $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        foreach (array_merge(glob($dir . '/*.sql') ?: [], glob($dir . '/*.sql.gz') ?: [], glob($dir . '/*.zip') ?: []) as $f) {
            $n = basename($f);
            if (isset($seen[$n]) || !is_file($f)) {
                continue;
            }
            $seen[$n] = true;
            $out[] = ['name' => $n, 'at' => (int) @filemtime($f), 'bytes' => (int) @filesize($f), 'src' => $i === 0 ? 'backups/' : 'private backups folder'];
        }
    }
    if (sl_tableExists('cdb_dev_db_jobs')) {
        try {
            foreach (sl_rows("SELECT backup_file, file_name, file_bytes, COALESCE(finished_at, started_at) AS t FROM cdb_dev_db_jobs WHERE kind = 'backup' AND status = 'done' ORDER BY id DESC LIMIT 50") as $j) {
                $n = (string) ($j->backup_file ?: $j->file_name ?: '');
                if ($n !== '' && isset($seen[$n])) {
                    continue;
                }
                $seen[$n ?: uniqid('job', true)] = true;
                $out[] = ['name' => $n ?: 'console backup', 'at' => (int) strtotime((string) $j->t), 'bytes' => (int) $j->file_bytes, 'src' => 'cdb_dev_db_jobs'];
            }
        } catch (Throwable $e) {
        }
    }
    usort($out, fn($a, $b) => $b['at'] <=> $a['at']);
    return $out;
}

function sl_devBytesSafe($b): string
{
    return function_exists('sl_devBytes') ? sl_devBytes($b) : number_format((float) $b / 1048576, 1) . ' MB';
}

/**
 * Effective server figures: measured, replaced by an override where one is set.
 * Keys: disk_total, disk_used, disk_free, disk_pct, mem_total, mem_used, mem_pct, cores; ovr[field] = meta.
 */
function sl_dinfEffective(): array
{
    static $e = null;
    if ($e !== null) {
        return $e;
    }
    $gb = 1073741824;
    $tot = @disk_total_space(CDP_ROOT) ?: null;
    $free = @disk_free_space(CDP_ROOT);
    $mem = sl_dinfMeminfo();
    $memT = $mem['MemTotal'] ?? null;
    $memA = $mem['MemAvailable'] ?? null;
    $e = ['disk_total' => $tot, 'disk_used' => $tot && $free !== false ? $tot - $free : null, 'mem_total' => $memT, 'mem_used' => $memT && $memA !== null ? $memT - $memA : null,
        'cores' => sl_dinfCores(), 'ovr' => []];
    foreach (['disk_total_gb' => 'disk_total', 'disk_used_gb' => 'disk_used', 'ram_total_gb' => 'mem_total', 'ram_used_gb' => 'mem_used'] as $k => $f) {
        $m = cdp_devOverrideMeta($k);
        if ($m && is_numeric($m['v'])) {
            $e[$f] = (float) $m['v'] * $gb;
            $e['ovr'][$f] = $m;
        }
    }
    $m = cdp_devOverrideMeta('cpu_cores');
    if ($m && is_numeric($m['v'])) {
        $e['cores'] = (int) $m['v'];
        $e['ovr']['cores'] = $m;
    }
    $e['disk_free'] = $e['disk_total'] !== null && $e['disk_used'] !== null ? max(0, $e['disk_total'] - $e['disk_used']) : null;
    $e['disk_pct'] = $e['disk_total'] ? round(min(100, $e['disk_used'] / $e['disk_total'] * 100), 1) : null;
    $e['mem_pct'] = $e['mem_total'] && $e['mem_used'] !== null ? round(min(100, $e['mem_used'] / $e['mem_total'] * 100), 1) : null;
    return $e;
}

/* ============================================================== storage */

/**
 * Size of the app's storage areas, from a capped directory walk (cached 6 h in cdb_dev_state
 * 'infra_storage'; $fresh re-scans). Each group: key, label, bytes, files, paths, partial, colour.
 */
function sl_dinfStorage(bool $fresh = false): array
{
    $cached = cdp_devState('infra_storage', null);
    if (!$fresh && is_array($cached) && ($cached['at'] ?? 0) > time() - 21600) {
        return $cached;
    }
    $deadline = microtime(true) + 6.0;
    $walk = function (string $rel) use ($deadline): array {
        $full = CDP_ROOT . '/' . $rel;
        $b = 0;
        $n = 0;
        $partial = false;
        if (is_file($full)) {
            return [(float) @filesize($full), 1, false, true];
        }
        if (!is_dir($full)) {
            return [0, 0, false, false];
        }
        try {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);
            foreach ($it as $f) {
                if ($f->isFile() && !$f->isLink()) {
                    $b += $f->getSize();
                    $n++;
                }
                if (($n & 255) === 0 && microtime(true) > $deadline) {
                    $partial = true;
                    break;
                }
            }
        } catch (Throwable $e) {
            $partial = true;
        }
        return [(float) $b, $n, $partial, true];
    };
    $groups = [
        ['uploads', 'Uploads', ['assets/uploads', 'uploads'], '#60A5FA', 'Logos, photos, documents and tmp uploads'],
        ['orders', 'Order files', ['order_files'], '#A78BFA', 'Shipment videos and attachments (cdb_order_files)'],
        ['support', 'Support & driver files', ['support_files', 'driver_files', 'files'], '#22D3EE', 'Ticket attachments, proof of delivery'],
        ['backups', 'Backups', ['backups'], '#FBBF24', 'Database dumps written by backup.php'],
        ['logs', 'Logs & reports', ['logs', 'dev_reports'], '#F87171', 'Report files; the PHP error log is added below'],
        ['code', 'Code & libraries', ['vendor', '.git', 'docs', 'assets', 'helpers', 'views', 'lib', 'ajax', 'dataJs', 'pdf', 'apps', 'api'], '#64748B', 'Application pages in the root, vendor/, .git/ and docs/'],
    ];
    $out = ['at' => time(), 'groups' => [], 'partial' => false, 'files' => 0];
    $seenUploads = 0;
    foreach ($groups as [$key, $label, $paths, $c, $what]) {
        $b = 0;
        $n = 0;
        $partial = false;
        $found = [];
        foreach ($paths as $p) {
            [$pb, $pn, $pp, $ex] = $walk($p);
            if ($key === 'code' && $p === 'assets') {
                // assets/uploads is counted under Uploads
                [$ub, $un] = $walk('assets/uploads');
                $pb -= $ub;
                $pn -= $un;
            }
            if ($ex) {
                $found[] = $p . '/';
            }
            $b += $pb;
            $n += $pn;
            $partial = $partial || $pp;
        }
        if ($key === 'code') {
            // the root's own files (*.php pages, loader.php, composer.json, ...), not its folders
            $rn = 0;
            foreach (glob(CDP_ROOT . '/*') ?: [] as $f) {
                if (is_file($f) && !is_link($f)) {
                    $b += (float) @filesize($f);
                    $n++;
                    $rn++;
                }
            }
            if ($rn) {
                $found[] = '*.php (root)';
            }
        }
        if ($key === 'logs') {
            $log = function_exists('sl_devErrorLogPath') ? sl_devErrorLogPath() : '';
            if ($log !== '' && @is_file($log)) {
                $b += (float) @filesize($log);
                $n++;
                $found[] = basename($log);
            }
        }
        $out['groups'][] = ['key' => $key, 'label' => $label, 'bytes' => max(0, $b), 'files' => max(0, $n), 'paths' => $found, 'partial' => $partial, 'c' => $c, 'what' => $what];
        $out['partial'] = $out['partial'] || $partial;
        $out['files'] += max(0, $n);
    }
    try {
        $db = (float) sl_scalar('SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = DATABASE()', [], 0);
    } catch (Throwable $e) {
        $db = 0;
    }
    $out['groups'][] = ['key' => 'db', 'label' => 'Database', 'bytes' => $db, 'files' => 0, 'paths' => ['MySQL ' . (string) sl_scalar('SELECT DATABASE()', [], '')], 'partial' => false, 'c' => '#34D399', 'what' => 'Tables and indexes (information_schema); stored by MySQL, possibly on another disk'];
    cdp_devStateSet('infra_storage', $out);
    return $out;
}

/* =============================================================== events */

/**
 * Recent infrastructure events from real ledgers: alerts on server components, failed cron runs,
 * backups written, database imports, deployments and override changes. Newest first.
 * Each: ts, icon, tone (ok|warn|bad|info), t, s, href.
 */
function sl_dinfEvents(int $limit = 14): array
{
    $ev = [];
    $since = date('Y-m-d H:i:s', time() - 14 * 86400);
    try {
        foreach (cdp_devQ("SELECT id, severity, service, title, status, first_seen, resolved_at FROM cdb_dev_alerts WHERE first_seen >= :s ORDER BY id DESC LIMIT 40", [':s' => $since]) as $a) {
            $ev[] = ['ts' => (string) $a->first_seen, 'icon' => 'i-alert', 'tone' => $a->severity === 'critical' ? 'bad' : 'warn', 't' => 'Alert: ' . $a->title,
                's' => $a->service . ' · ' . $a->status . ($a->resolved_at ? ' · resolved ' . sl_ago($a->resolved_at) : ''), 'href' => sl_route('DevAlerts', ['tab' => 'all', 'alert' => (int) $a->id])];
        }
    } catch (Throwable $e) {
    }
    if (sl_tableExists('cdb_cron_runs')) {
        // cdb_cron_runs is stamped with the database clock (helpers/cron_run.php)
        foreach (sl_rows("SELECT job, started_at, message, duration_ms FROM cdb_cron_runs WHERE status = 'failed' AND started_at >= NOW() - INTERVAL 14 DAY ORDER BY id DESC LIMIT 15") as $c) {
            $ev[] = ['ts' => sl_dinfDbToApp($c->started_at), 'icon' => 'i-clock', 'tone' => 'bad', 't' => 'Cron job failed: ' . $c->job, 's' => mb_substr((string) $c->message, 0, 140) ?: 'no message', 'href' => sl_route('DevLogs', ['source' => 'cron'])];
        }
    }
    foreach (sl_dinfBackups() as $f) {
        // backups made by the console are already listed from cdb_dev_db_jobs below
        if ($f['src'] !== 'cdb_dev_db_jobs' && $f['at'] >= time() - 14 * 86400) {
            $ev[] = ['ts' => date('Y-m-d H:i:s', $f['at']), 'icon' => 'i-archive', 'tone' => 'ok', 't' => 'Database backup written', 's' => $f['name'] . ' · ' . sl_devBytesSafe($f['bytes']) . ' · ' . $f['src'], 'href' => 'backup.php'];
        }
    }
    if (sl_tableExists('cdb_dev_db_jobs')) {
        foreach (sl_rows("SELECT kind, status, file_name, started_at FROM cdb_dev_db_jobs WHERE started_at >= :s ORDER BY id DESC LIMIT 10", [':s' => $since]) as $j) {
            $ev[] = ['ts' => (string) $j->started_at, 'icon' => 'i-database', 'tone' => $j->status === 'failed' ? 'bad' : 'info', 't' => 'Database ' . $j->kind . ': ' . $j->status, 's' => (string) ($j->file_name ?: ''), 'href' => is_file(CDP_ROOT . '/dev_database.php') ? 'dev_database.php' : 'backup.php'];
        }
    }
    try {
        foreach (cdp_devQ("SELECT commit_hash, branch, message, detected_at FROM cdb_dev_deploys WHERE detected_at >= :s ORDER BY id DESC LIMIT 8", [':s' => $since]) as $d) {
            $ev[] = ['ts' => (string) $d->detected_at, 'icon' => 'i-rocket', 'tone' => 'info', 't' => 'Code deployed: ' . mb_substr((string) $d->message, 0, 90), 's' => substr((string) $d->commit_hash, 0, 7) . ($d->branch ? ' · ' . $d->branch : ''), 'href' => sl_route('DevDeployments')];
        }
    } catch (Throwable $e) {
    }
    if (sl_tableExists('cdb_activity_log')) {
        foreach (sl_rows("SELECT created_at, actor_name, summary FROM cdb_activity_log WHERE module = 'developer' AND entity_type = 'dev_override' AND created_at >= :s ORDER BY id DESC LIMIT 10", [':s' => $since]) as $a) {
            $ev[] = ['ts' => (string) $a->created_at, 'icon' => 'i-sliders', 'tone' => 'info', 't' => 'Override: ' . $a->summary, 's' => 'by ' . $a->actor_name, 'href' => sl_route('DevInfrastructure') . '#overrides'];
        }
    }
    usort($ev, fn($a, $b) => strcmp($b['ts'], $a['ts']));
    return array_slice($ev, 0, $limit);
}

/* ======================================================== alert metrics */

/**
 * Metrics an alert rule can watch (sl_devMetric in helpers/dev_console.php evaluates them).
 * key => [label, unit, windowed (uses window_min), description].
 */
function sl_dinfMetrics(): array
{
    return [
        'disk_pct' => ['Disk used', '%', false, 'Share of the disk in use (super-admin override of total/used applies).'],
        'mem_pct' => ['Memory used', '%', false, 'From the latest collector sample (/proc/meminfo) or the RAM override.'],
        'cpu_pct' => ['CPU busy', '%', false, 'From the latest collector sample (/proc/stat or load average).'],
        'error_rate' => ['Server error rate', '%', true, 'Share of recorded requests answered with a 5xx or PHP fatal, over the window.'],
        'resp_p95' => ['Response time p95', 'ms', true, '95th percentile of recorded request durations over the window.'],
        'resp_avg' => ['Average response time', 'ms', true, 'Average of recorded request durations over the window.'],
        'db_ms' => ['Database ping', 'ms', false, 'Latest collector sample of a SELECT 1 round trip.'],
        'failed_cron' => ['Failed cron runs', 'runs', true, 'Rows with status failed in cdb_cron_runs over the window.'],
        'cron_stale_min' => ['Minutes since last cron run', 'min', false, 'Time since any scheduled job was recorded in cdb_cron_runs (the manual "Evaluate rules now" button does not count).'],
        'queue_backlog' => ['Outbox waiting', 'messages', false, 'Queued or retrying rows in cdb_cs_outbox.'],
        'dead_letters' => ['Outbox dead letters', 'messages', false, 'Messages in cdb_cs_outbox that gave up (status dead).'],
        'backup_age_h' => ['Hours since last backup', 'h', false, 'Age of the newest backup (.sql, .sql.gz or .zip in backups/, console backups); not measured while there is none.'],
        'failed_logins' => ['Failed sign-ins', 'attempts', true, 'Failed or denied logins in cdb_activity_log over the window.'],
        'otp_failures' => ['Failed one-time codes', 'codes', true, 'Failed, expired or locked OTP challenges over the window.'],
        'php_errors' => ['PHP errors in log', 'lines', true, 'Lines in the PHP error log (CDP_ERROR_LOG) over the window.'],
    ];
}

/** Latest collector sample (cdb_dev_metrics) when it is at most 20 minutes old. */
function sl_dinfLatestSample(): ?object
{
    static $s = false;
    if ($s !== false) {
        return $s;
    }
    try {
        // samples are stamped with PHP's (application) clock, so compare with it, not the database's NOW()
        $s = cdp_devOne('SELECT * FROM cdb_dev_metrics WHERE ts >= :s ORDER BY id DESC LIMIT 1', [':s' => date('Y-m-d H:i:s', time() - 1200)]) ?: null;
    } catch (Throwable $e) {
        $s = null;
    }
    return $s;
}

/** Suffixes written to cdb_dev_alerts.detail when a rule alert is closed on purpose. */
function sl_dinfClosedMarks(): array
{
    return [' · cleared', ' · rule deleted', ' · resolved by hand'];
}

/**
 * Rule alerts (alert_key rule:*) belong to the rule evaluator (sl_devEvaluateAlerts). The built-in
 * checks in helpers/devops.php cdp_devEvaluate, run with every stored server sample, resolve every open
 * alert whose key they did not raise themselves, rule:* keys included, so a firing rule alert was closed
 * by the next developer page load. Until that query leaves rule:% keys alone, this puts such alerts back:
 * the newest row of its key, resolved without one of the marks above and without a hand resolve in the
 * activity log, whose rule still exists, is on and still fires, is reopened (acknowledged again if it was);
 * one whose rule no longer fires is marked cleared so it is not looked at again. Returns reopened count.
 */
function sl_dinfRepairRuleAlerts(): int
{
    if (!sl_tableExists('cdb_dev_alerts') || !sl_tableExists('cdb_dev_alert_rules') || !function_exists('sl_devMetric')) {
        return 0;
    }
    $marks = sl_dinfClosedMarks();
    $not = implode(' AND ', array_map(fn($i) => "COALESCE(a.detail,'') NOT LIKE :m$i", array_keys($marks)));
    $bind = [];
    foreach ($marks as $i => $m) {
        $bind[":m$i"] = '%' . $m . '%';
    }
    $n = 0;
    try {
        $cand = cdp_devQ("SELECT a.id, a.alert_key, a.ack_at, a.detail FROM cdb_dev_alerts a
            WHERE a.alert_key LIKE 'rule:%' AND a.status = 'resolved' AND $not
              AND a.id = (SELECT MAX(b.id) FROM cdb_dev_alerts b WHERE b.alert_key = a.alert_key) LIMIT 20", $bind);
        foreach ($cand as $a) {
            $byHand = sl_tableExists('cdb_activity_log') ? sl_row("SELECT action FROM cdb_activity_log WHERE entity_type = 'dev_alert' AND entity_id = :i
                AND action IN ('alert_resolve','developer.alert_resolve','alert_reopen','developer.alert_reopen') ORDER BY id DESC", [':i' => (int) $a->id]) : null;
            if ($byHand && strpos((string) $byHand->action, 'resolve') !== false) {
                continue;
            }
            $r = sl_row('SELECT metric, comparator, threshold, window_min, enabled FROM cdb_dev_alert_rules WHERE rule_key = :k', [':k' => substr((string) $a->alert_key, 5)]);
            $fires = $r && (int) $r->enabled === 1 && sl_devCompare(sl_devMetric((string) $r->metric, (int) $r->window_min), (string) $r->comparator, (float) $r->threshold);
            if ($fires) {
                cdp_devX("UPDATE cdb_dev_alerts SET status = :s, resolved_at = NULL WHERE id = :i AND status = 'resolved'", [':s' => $a->ack_at ? 'acknowledged' : 'active', ':i' => (int) $a->id]);
                $n++;
            } else {
                cdp_devX("UPDATE cdb_dev_alerts SET detail = :d WHERE id = :i", [':d' => mb_substr((string) $a->detail, 0, 480) . ($r ? ' · cleared' : ' · rule deleted'), ':i' => (int) $a->id]);
            }
        }
    } catch (Throwable $e) {
        error_log('dev rule alert repair: ' . $e->getMessage());
    }
    return $n;
}

/**
 * cron/dev_alerts.php runs without Core: apply the application time zone once, so time windows match
 * the times stored by web requests (which run with Core's time zone).
 */
function sl_dinfAppTz(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (empty($GLOBALS['core']) && class_exists('Core')) {
        try {
            $GLOBALS['core'] = new Core();
        } catch (Throwable $e) {
        }
    }
}

/** Value of the metrics added by this package (null = not measured); sl_devMetric delegates here. */
function sl_dinfMetricValue(string $metric, int $windowMin): ?float
{
    sl_dinfAppTz();
    $win = max(1, $windowMin) * 60;
    switch ($metric) {
        case 'disk_pct':
            $e = sl_dinfEffective();
            return $e['disk_pct'];
        case 'mem_pct':
            $e = sl_dinfEffective();
            if (!empty($e['ovr']['mem_total']) || !empty($e['ovr']['mem_used'])) {
                return $e['mem_pct'];
            }
            $s = sl_dinfLatestSample();
            return $s && $s->mem_pct !== null ? (float) $s->mem_pct : $e['mem_pct'];
        case 'cpu_pct':
            $s = sl_dinfLatestSample();
            return $s && $s->cpu_pct !== null ? (float) $s->cpu_pct : null;
        case 'db_ms':
            $s = sl_dinfLatestSample();
            return $s && $s->db_ms !== null ? (float) $s->db_ms : null;
        case 'error_rate':
        case 'resp_p95':
        case 'resp_avg':
            if (!function_exists('cdp_reqmWindow')) {
                return null;
            }
            $w = cdp_reqmWindow(time() - $win, time() + 1);
            if (!$w['n']) {
                return null;
            }
            return $metric === 'error_rate' ? (float) $w['err_pct'] : ($metric === 'resp_p95' ? (float) $w['p95'] : (float) $w['avg']);
        case 'dead_letters':
            return sl_tableExists('cdb_cs_outbox') ? (float) sl_scalar("SELECT COUNT(*) FROM cdb_cs_outbox WHERE status = 'dead'") : null;
        case 'backup_age_h':
            $b = sl_dinfBackups();
            // no backup at all: not measured (an age is not invented); the Backups card says "none yet"
            return $b && $b[0]['at'] ? round(max(0, time() - $b[0]['at']) / 3600, 1) : null;
    }
    return null;
}

/* ======================================================== notifications */

/** Notification settings (cdb_dev_state 'alert_notify'). */
function sl_dinfNotifySettings(): array
{
    $s = cdp_devState('alert_notify', []);
    $s = is_array($s) ? $s : [];
    return ['on' => (int) ($s['on'] ?? 1) === 1, 'min' => ($s['min'] ?? 'warning') === 'critical' ? 'critical' : 'warning', 'builtin' => (int) ($s['builtin'] ?? 1) === 1,
        'extra' => array_values(array_filter(array_map('trim', (array) ($s['extra'] ?? [])), fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL)))];
}

/**
 * Developers with an e-mail address who still pass the console gate (super admin, listed in
 * cdb_dev_access) and are granted the Alerts section, plus the extra addresses. Each: [name, email].
 */
function sl_dinfRecipients(?array $settings = null): array
{
    $settings = $settings ?? sl_dinfNotifySettings();
    $out = [];
    try {
        if (!function_exists('cdp_roleHasFlag')) {
            require_once CDP_ROOT . '/helpers/rbac.php';
        }
        foreach (cdp_devQ("SELECT u.id, u.fname, u.lname, u.email, u.userlevel FROM cdb_dev_access a JOIN cdb_users u ON u.id = a.user_id") as $u) {
            // only developers who may still open the console AND its Alerts section
            // (a row left in cdb_dev_access after the super-admin role was removed gets nothing)
            if (!cdp_roleHasFlag((int) $u->userlevel, 'is_superadmin')) {
                continue;
            }
            if (function_exists('cdp_devSectionAllowed') && !cdp_devSectionAllowed((int) $u->id, 'alerts')) {
                continue;
            }
            if (filter_var((string) $u->email, FILTER_VALIDATE_EMAIL)) {
                $out[strtolower((string) $u->email)] = ['name' => trim($u->fname . ' ' . $u->lname), 'email' => (string) $u->email];
            }
        }
    } catch (Throwable $e) {
    }
    foreach ($settings['extra'] as $e) {
        $out[strtolower($e)] = $out[strtolower($e)] ?? ['name' => $e, 'email' => $e];
    }
    return array_values($out);
}

/** Send one e-mail through the app's mailer (helpers/comms.php). ['success','message']. */
function sl_dinfSendMail(string $to, string $subject, string $text): array
{
    if (!function_exists('cdp_commSendEmail')) {
        try {
            require_once CDP_ROOT . '/helpers/comms.php';
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Mailer unavailable'];
        }
    }
    if (!function_exists('cdp_commSendEmail')) {
        return ['success' => false, 'message' => 'Mailer unavailable'];
    }
    try {
        return cdp_commSendEmail((object) ['email' => $to], $subject, $text);
    } catch (Throwable $e) {
        return ['success' => false, 'message' => mb_substr($e->getMessage(), 0, 200)];
    }
}

/** Record of e-mails sent per alert (cdb_dev_state 'alert_notified'): id => [at, sent, failed, to]. */
function sl_dinfNotified(): array
{
    $n = cdp_devState('alert_notified', []);
    return is_array($n) ? $n : [];
}

/**
 * E-mail the developers about every active alert they have not been told about yet:
 * rule alerts whose rule says notify=email, built-in service alerts when that is switched on,
 * at or above the minimum severity. Run after every evaluation (cron/dev_alerts.php and the
 * "Evaluate now" button). Returns ['alerts' => n, 'sent' => n, 'failed' => n].
 */
function sl_dinfNotifySweep(int $max = 10): array
{
    $res = ['alerts' => 0, 'sent' => 0, 'failed' => 0];
    $st = sl_dinfNotifySettings();
    if (!$st['on']) {
        return $res;
    }
    // One sweep at a time (cron and the button can overlap): the "already e-mailed" record is a
    // read-modify-write of cdb_dev_state. The PDO handle is shared per request (lib/Conexion.php),
    // so the named lock is held by this request until RELEASE_LOCK below.
    $lock = cdp_devOne("SELECT GET_LOCK('swiftlane_dev_alert_notify', 0) AS l");
    if (!$lock || (int) $lock->l !== 1) {
        $res['busy'] = true;
        return $res;
    }
    try {
        return sl_dinfNotifySweepLocked($st, $max, $res);
    } finally {
        cdp_devOne("SELECT RELEASE_LOCK('swiftlane_dev_alert_notify') AS r");
    }
}

function sl_dinfNotifySweepLocked(array $st, int $max, array $res): array
{
    $done = sl_dinfNotified();
    $rules = [];
    if (sl_tableExists('cdb_dev_alert_rules')) {
        foreach (sl_rows('SELECT rule_key, notify FROM cdb_dev_alert_rules') as $r) {
            $rules['rule:' . $r->rule_key] = (string) $r->notify;
        }
    }
    $todo = [];
    foreach (cdp_devQ("SELECT * FROM cdb_dev_alerts WHERE status = 'active' ORDER BY id") as $a) {
        if (isset($done[(string) $a->id])) {
            continue;
        }
        if ($st['min'] === 'critical' && $a->severity !== 'critical') {
            continue;
        }
        $isRule = strpos((string) $a->alert_key, 'rule:') === 0;
        if ($isRule ? (($rules[$a->alert_key] ?? 'email') !== 'email') : !$st['builtin']) {
            continue;
        }
        $todo[] = $a;
    }
    $to = sl_dinfRecipients($st);
    if (!$todo || !$to) {
        return $res;
    }
    $base = function_exists('cdp_devBaseUrl') ? cdp_devBaseUrl() : '';
    foreach (array_slice($todo, 0, $max) as $a) {
        $res['alerts']++;
        $subj = '[' . strtoupper((string) $a->severity) . '] ' . $a->title . ' · ' . (string) gethostname();
        $text = "A developer-console alert opened on " . (string) gethostname() . ".\n\n"
            . 'Alert: ' . $a->title . "\nService: " . $a->service . "\nSeverity: " . $a->severity . "\nSince: " . $a->first_seen . "\n"
            . ($a->detail ? 'Detail: ' . $a->detail . "\n" : '') . "\nOpen the Alerts page to acknowledge or resolve it:\n" . $base . 'dev_alerts.php?alert=' . (int) $a->id
            . "\n\nYou receive this because you are assigned to the developer console.";
        $sent = 0;
        $failed = 0;
        foreach ($to as $r) {
            $x = sl_dinfSendMail($r['email'], $subj, $text);
            !empty($x['success']) ? $sent++ : $failed++;
        }
        $res['sent'] += $sent;
        $res['failed'] += $failed;
        $done[(string) $a->id] = ['at' => date('Y-m-d H:i:s'), 'sent' => $sent, 'failed' => $failed, 'to' => count($to)];
    }
    // keep the record small, but never forget an alert that is still open (it would be e-mailed again)
    if (count($done) > 300) {
        $open = [];
        foreach (cdp_devQ("SELECT id FROM cdb_dev_alerts WHERE status IN ('active','acknowledged')") as $o) {
            $open[(string) $o->id] = true;
        }
        $keep = array_intersect_key($done, $open);
        $rest = array_slice(array_diff_key($done, $open), -max(0, 300 - count($keep)), null, true);
        $done = $keep + $rest;
    }
    cdp_devStateSet('alert_notified', $done);
    return $res;
}

/* ======================================================== panel rows */

/** One override row as shown in the overrides panel (and Operating costs). */
function sl_dinfOverrideRows(): array
{
    $meas = sl_dinfMeasured();
    $rows = [];
    foreach (sl_dinfCatalog() as $k => $c) {
        $m = cdp_devOverrideMeta($k);
        $ms = $meas[$k] ?? ['v' => null, 'text' => 'not measured', 'src' => '', 'how' => ''];
        $val = $m ? $m['v'] : '';
        $shown = $m ? ($c['type'] === 'num' ? number_format((float) $val, floor((float) $val) == (float) $val ? 0 : 2) . ($c['unit'] ? ' ' . $c['unit'] : '') : ($c['type'] === 'date' ? sl_date($val) : $val)) : '';
        $rows[] = ['k' => $k, 'label' => $c['label'], 'unit' => $c['unit'], 'type' => $c['type'], 'opts' => $c['opts'] ?? [], 'group' => $c['group'], 'icon' => $c['icon'], 'hint' => $c['hint'],
            'measured' => $ms['text'], 'measured_v' => $ms['v'], 'src' => $ms['src'], 'how' => $ms['how'], 'set' => (bool) $m, 'value' => $val, 'shown' => $shown,
            'note' => $m['note'] ?? '', 'by' => $m ? sl_dinfOvrLabel($m) : '', 'at' => $m['at'] ?? ''];
    }
    return $rows;
}

/* ================================================= Operating costs panel */

/**
 * The "Infrastructure overrides" block of Operating costs. Shown to developers granted Infrastructure
 * or Infrastructure overrides; editable (Save / Clear) only with the "overrides" section.
 */
function sl_dinfCostPanel($user): ?array
{
    $edit = sl_dinfAllowed($user, 'overrides');
    $view = $edit || sl_dinfAllowed($user, 'infrastructure');
    if (!$view) {
        return null;
    }
    return ['rows' => sl_dinfOverrideRows(), 'canEdit' => $edit, 'canOpen' => sl_dinfAllowed($user, 'infrastructure'),
        'href' => sl_route('DevInfrastructure') . '#overrides', 'ajax' => 'ajax/sl/dev_infra_ajax.php'];
}
