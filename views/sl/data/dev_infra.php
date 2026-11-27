<?php
/**
 * Data for the "infra" boards of the developer console: DevInfrastructure (dev_infrastructure.php)
 * and DevAlerts (dev_alerts.php), plus the "Infrastructure overrides" panel of Operating costs.
 *
 * Sources (all measured on this installation; a super-admin override is always labelled
 * "set by <name> on <date>"):
 *   server      /proc (collector samples cdb_dev_metrics via helpers/devops.php), disk_*_space,
 *               sys_getloadavg, PHP, MySQL (helpers/dev_console.php)
 *   storage     capped directory walk + information_schema (helpers/dev_infra.php sl_dinfStorage)
 *   components  views/sl/data/dev_observe.php sl_dvxComponents (same health checks as Overview)
 *   overrides   cdb_dev_overrides (helpers/dev_infra.php)
 *   alerts      cdb_dev_alerts + cdb_dev_alert_rules, notification state in cdb_dev_state
 * Writes: ajax/sl/dev_infra_ajax.php.
 */
require_once CDP_ROOT . '/views/sl/data/dev_observe.php';
require_once CDP_ROOT . '/helpers/dev_infra.php';

/** A GET parameter as a string ('' when missing or not a string, e.g. q[]=x). */
function sl_dinfGet(string $k): string
{
    return is_string($_GET[$k] ?? null) ? (string) $_GET[$k] : '';
}

/** "... · last 2026-11-26 12:26:36" in a stored detail -> "... · last Nov 26, 12:26". */
function sl_dinfNiceDetail(string $t): string
{
    return (string) preg_replace_callback('/\b(\d{4}-\d{2}-\d{2} \d{2}:\d{2})(:\d{2})?\b/', fn($m) => sl_date($m[1] . ($m[2] ?? ''), 'M j, H:i'), $t);
}

/** Page gate: login + view_developer + assigned super admin + section switch. */
function sl_dinfGate($user, string $section): void
{
    sl_gate('view_developer');
    if (!sl_dinfAllowed($user, $section)) {
        header('Location: error403.php');
        exit;
    }
}

function sl_dinfFmt($v, int $dec = 2): string
{
    if ($v === null) {
        return '—';
    }
    $s = number_format((float) $v, $dec, '.', '');
    // trim trailing zeros of the decimals only: 30 stays "30", 2.50 becomes "2.5"
    return $dec > 0 && strpos($s, '.') !== false ? rtrim(rtrim($s, '0'), '.') : $s;
}

function sl_dinfTone(?float $pct, float $warn = 80, float $bad = 90): string
{
    return $pct === null ? 'off' : ($pct >= $bad ? 'bad' : ($pct >= $warn ? 'warn' : 'ok'));
}

/* ====================================================== Infrastructure */

function sl_dinfInfraData($user, $core): array
{
    // Editing overrides is its own section ("Infrastructure overrides"); viewing them comes with Infrastructure.
    $d = ['host' => (string) gethostname(), 'now' => date('Y-m-d H:i:s'), 'ajax' => 'ajax/sl/dev_infra_ajax.php', 'canEdit' => sl_dinfAllowed($user, 'overrides'),
        'canScan' => sl_dinfAllowed($user, 'infrastructure')];
    $snap = sl_dvxSnap();
    sl_dinfRepairRuleAlerts();
    $srv = $snap['server'] ?? [];
    $eff = sl_dinfEffective();
    $my = sl_devMysql();
    $series = cdp_devSeries('24h');
    $labels = array_map(fn($p) => date('H:i', strtotime($p['t'])), $series);
    $sp = fn(string $k, string $name, string $unit) => sl_dvxSpark(array_map(fn($p) => $p[$k], $series), $labels, $name, $unit);
    $d['samples'] = count($series);
    $cores = $eff['cores'];
    $load = $srv['load'] ?? null;
    $cpu = $srv['cpu_pct'] ?? null;
    $ovr = $eff['ovr'];
    $dbBytes = (float) ($snap['db']['size_bytes'] ?? sl_devDbSize()['bytes']);
    $dbLimit = cdp_devOverrideMeta('db_limit_mb');
    $dbPct = $dbLimit && (float) $dbLimit['v'] > 0 ? round($dbBytes / ((float) $dbLimit['v'] * 1048576) * 100, 1) : null;

    $d['kpis'] = [
        ['k' => 'cpu', 't' => 'CPU usage', 'icon' => 'i-cpu', 'c' => '#60A5FA', 'v' => $cpu !== null ? sl_dinfFmt($cpu, 1) : '—', 'u' => $cpu !== null ? '%' : '', 'tone' => sl_dinfTone($cpu),
            'sub' => ($cores ? $cores . ' core' . ($cores === 1 ? '' : 's') : 'cores not measured') . ' · load ' . ($load ? implode(' / ', $load) : 'not measured'),
            'ovr' => isset($ovr['cores']) ? 'Cores ' . sl_dinfOvrLabel($ovr['cores']) : '', 'pct' => $cpu, 'sp' => $sp('cpu', 'CPU', '%'), 'href' => sl_route('DevMetrics') . '#cpu',
            'how' => $cpu === null ? 'Not measured: /proc/stat and sys_getloadavg() are not available on this host.' : ''],
        ['k' => 'mem', 't' => 'Memory', 'icon' => 'i-server', 'c' => '#A78BFA', 'v' => $eff['mem_pct'] !== null ? sl_dinfFmt($eff['mem_pct'], 1) : '—', 'u' => $eff['mem_pct'] !== null ? '%' : '', 'tone' => sl_dinfTone($eff['mem_pct']),
            'sub' => $eff['mem_total'] ? ($eff['mem_used'] !== null ? sl_devBytes($eff['mem_used']) . ' of ' : '') . sl_devBytes($eff['mem_total']) : 'PHP peak this request ' . sl_devBytes(memory_get_peak_usage(true)),
            'ovr' => isset($ovr['mem_total']) || isset($ovr['mem_used']) ? 'RAM ' . sl_dinfOvrLabel($ovr['mem_total'] ?? $ovr['mem_used']) : '', 'pct' => $eff['mem_pct'], 'sp' => $sp('mem', 'Memory', '%'), 'href' => sl_route('DevMetrics') . '#memory',
            'how' => $eff['mem_total'] ? '' : 'Not measured: /proc/meminfo is not readable here. Set "Total RAM" below.'],
        ['k' => 'disk', 't' => 'Disk', 'icon' => 'i-folder', 'c' => '#FBBF24', 'v' => $eff['disk_pct'] !== null ? sl_dinfFmt($eff['disk_pct'], 1) : '—', 'u' => $eff['disk_pct'] !== null ? '%' : '', 'tone' => sl_dinfTone($eff['disk_pct'], 80, 90),
            'sub' => $eff['disk_total'] ? sl_devBytes($eff['disk_used']) . (isset($ovr['disk_used']) ? ' used' : ' used or reserved') . ' of ' . sl_devBytes($eff['disk_total']) . ' · ' . sl_devBytes($eff['disk_free']) . ' free' : 'disk_total_space() unavailable',
            'ovr' => isset($ovr['disk_total']) || isset($ovr['disk_used']) ? 'Disk ' . sl_dinfOvrLabel($ovr['disk_total'] ?? $ovr['disk_used']) : '', 'pct' => $eff['disk_pct'], 'sp' => $sp('disk', 'Disk', '%'), 'href' => '#storage',
            'how' => $eff['disk_total'] ? (isset($ovr['disk_used']) ? '' : 'Measured as total minus the space PHP may still write, so blocks reserved for root count as used (df\'s "Used" can be lower).') : 'Not measured: set "Total disk space" below.'],
        ['k' => 'db', 't' => 'Database', 'icon' => 'i-database', 'c' => '#34D399', 'v' => $my['ok'] ? (($snap['db']['ping_ms'] ?? $my['ms']) < 0.1 ? '<0.1' : sl_dinfFmt($snap['db']['ping_ms'] ?? $my['ms'], 2)) : 'down', 'u' => $my['ok'] ? 'ms' : '', 'tone' => $my['ok'] ? ($dbPct !== null ? sl_dinfTone($dbPct) : 'ok') : 'bad',
            'sub' => sl_devBytes($dbBytes) . ($dbLimit ? ' of ' . sl_dinfFmt($dbLimit['v'], 0) . ' MB limit' : '') . ' · ' . $my['threads'] . ' / ' . $my['max_conn'] . ' connections',
            'ovr' => $dbLimit ? 'Limit ' . sl_dinfOvrLabel($dbLimit) : '', 'pct' => $dbPct, 'sp' => $sp('db', 'DB ping', 'ms'), 'href' => sl_route('DevServices', ['svc' => 'db']) . '#svc-db', 'how' => ''],
    ];

    // storage + resource distribution (disk)
    // cached scan only: a fresh walk is the POST + CSRF + audited "rescan" action
    $st = sl_dinfStorage(false);
    $d['storage'] = $st;
    $appBytes = array_sum(array_map(fn($g) => $g['bytes'], $st['groups']));
    $maxG = max(1, max(array_map(fn($g) => $g['bytes'], $st['groups'])));
    $d['storeRows'] = array_map(fn($g) => $g + ['pct' => round($g['bytes'] / $maxG * 100, 1), 'size' => sl_devBytes($g['bytes'])], $st['groups']);
    $segs = [];
    foreach ($st['groups'] as $g) {
        $segs[] = ['label' => $g['label'], 'bytes' => $g['bytes'], 'c' => $g['c'], 'href' => '#store-' . $g['key']];
    }
    if ($eff['disk_total']) {
        $other = max(0, (float) $eff['disk_used'] - $appBytes);
        $segs[] = ['label' => isset($ovr['disk_used']) ? 'Other data on this disk' : 'Other data or reserved space', 'bytes' => $other, 'c' => '#334155', 'href' => '#storage'];
        $segs[] = ['label' => 'Free', 'bytes' => max(0, (float) $eff['disk_free']), 'c' => '#1E2B40', 'href' => '#storage'];
        $total = (float) $eff['disk_total'];
    } else {
        $total = max(1, $appBytes);
    }
    $acc = 0;
    $circ = 2 * M_PI * 52;
    foreach ($segs as &$s) {
        $frac = $total > 0 ? min(1, $s['bytes'] / $total) : 0;
        $frac = min($frac, max(0, 1 - $acc)); // never past a full ring
        $s['pct'] = round($frac * 100, 1);
        $s['dash'] = round($frac * $circ, 2) . ' ' . round($circ, 2);
        $s['off'] = round(-$acc * $circ, 2);
        $s['size'] = sl_devBytes($s['bytes']);
        $acc += $frac;
    }
    unset($s);
    // inner ring: the app's own data only (folders + database), so small parts stay visible
    $inner = [];
    $acc = 0;
    $ci = 2 * M_PI * 36;
    foreach ($st['groups'] as $g) {
        $frac = $appBytes > 0 ? $g['bytes'] / $appBytes : 0;
        $inner[] = ['label' => $g['label'], 'c' => $g['c'], 'pct' => round($frac * 100, 1), 'size' => sl_devBytes($g['bytes']), 'dash' => round($frac * $ci, 2) . ' ' . round($ci, 2), 'off' => round(-$acc * $ci, 2)];
        $acc += $frac;
    }
    $d['donut'] = ['segs' => $segs, 'inner' => $inner, 'total' => $eff['disk_total'] ? sl_devBytes($eff['disk_total']) : sl_devBytes($appBytes), 'app' => sl_devBytes($appBytes),
        'center' => $eff['disk_pct'] !== null ? sl_dinfFmt($eff['disk_pct'], 1) . '%' : sl_devBytes($appBytes), 'centerSub' => $eff['disk_pct'] !== null ? 'disk used' : 'app data',
        'ovr' => isset($ovr['disk_total']) || isset($ovr['disk_used']) ? sl_dinfOvrLabel($ovr['disk_total'] ?? $ovr['disk_used']) : ''];
    $inodes = cdp_devOverrideMeta('inode_limit');
    $d['inodes'] = $inodes ? ['files' => $st['files'], 'limit' => (float) $inodes['v'], 'pct' => (float) $inodes['v'] > 0 ? round($st['files'] / (float) $inodes['v'] * 100, 1) : null, 'by' => sl_dinfOvrLabel($inodes)] : null;

    // server facts
    $up = $srv['uptime_s'] ?? null;
    $fmtUp = fn($s) => $s ? floor($s / 86400) . ' d ' . floor($s % 86400 / 3600) . ' h ' . floor($s % 3600 / 60) . ' min' : null;
    $plan = fn($k) => cdp_devOverrideMeta($k);
    $d['facts'] = [
        ['k' => 'Hostname', 'v' => (string) gethostname()],
        ['k' => 'Operating system', 'v' => trim(php_uname('s') . ' ' . php_uname('r') . ' ' . php_uname('m'))],
        ['k' => 'Web server', 'v' => (string) ($_SERVER['SERVER_SOFTWARE'] ?? PHP_SAPI)],
        ['k' => 'PHP', 'v' => PHP_VERSION . ' · ' . PHP_SAPI . ' · memory_limit ' . ini_get('memory_limit') . ' · OPcache ' . (function_exists('opcache_get_status') && @opcache_get_status(false) ? 'on' : 'off')],
        ['k' => 'Database', 'v' => $my['version'] . ' · up ' . ($fmtUp((int) $my['uptime']) ?: '—')],
        ['k' => 'Time zone', 'v' => date_default_timezone_get() . ' (UTC' . date('P') . ') · database UTC' . sl_dinfDbOffset()],
        ['k' => 'Server uptime', 'v' => $up ? $fmtUp($up) : 'not measured (/proc/uptime not readable)'],
        ['k' => 'CPU cores', 'v' => $cores ? (string) $cores : 'not measured', 'ovr' => isset($ovr['cores']) ? sl_dinfOvrLabel($ovr['cores']) : ''],
        ['k' => 'Uploads allowed', 'v' => ini_get('upload_max_filesize') . ' per file · post ' . ini_get('post_max_size')],
        ['k' => 'App root', 'v' => CDP_ROOT],
        ['k' => 'App version', 'v' => sl_devVersion() ?: '—'],
    ];
    foreach (['plan_name' => 'Hosting plan', 'provider' => 'Provider', 'region' => 'Region'] as $k => $l) {
        $m = $plan($k);
        $d['facts'][] = ['k' => $l, 'v' => $m ? $m['v'] : 'not set', 'ovr' => $m ? sl_dinfOvrLabel($m) : '', 'unset' => !$m];
    }

    // plan & capacity
    $cost = cdp_devOverrideMeta('server_cost_month');
    $cur = cdp_devOverrideMeta('server_cost_currency');
    $lines = sl_dinfServerCostLines();
    $bwL = cdp_devOverrideMeta('bandwidth_limit_gb');
    $bwU = cdp_devOverrideMeta('bandwidth_used_gb');
    $ren = cdp_devOverrideMeta('renewal_date');
    // currency of the override: its own override, else the one currency of the Servers lines, else said plainly
    $costCur = $cur ? $cur['v'] : ($lines && count($lines['by']) === 1 ? (string) array_key_first($lines['by']) : '');
    $d['plan'] = [
        'cost' => $cost ? ($costCur !== '' ? $costCur . ' ' : '') . number_format((float) $cost['v'], 2) . ($costCur === '' ? ' (currency not set)' : '') : ($lines ? $lines['text'] : null),
        'costSrc' => $cost ? 'Override ' . sl_dinfOvrLabel($cost) : ($lines ? 'Operating costs › Servers' : 'Not set: add a "Servers" line in Operating costs or set it below'),
        'costOvr' => (bool) $cost,
        'bw' => $bwL || $bwU ? ['used' => $bwU ? (float) $bwU['v'] : null, 'limit' => $bwL ? (float) $bwL['v'] : null, 'pct' => $bwL && $bwU && (float) $bwL['v'] > 0 ? round((float) $bwU['v'] / (float) $bwL['v'] * 100, 1) : null,
            'by' => sl_dinfOvrLabel($bwU ?: $bwL)] : null,
        'renew' => $ren ? ['date' => sl_date($ren['v']), 'days' => (int) floor((strtotime($ren['v']) - strtotime(date('Y-m-d'))) / 86400), 'by' => sl_dinfOvrLabel($ren)] : null,
        'db' => ['size' => sl_devBytes($dbBytes), 'limit' => $dbLimit ? sl_dinfFmt($dbLimit['v'], 0) . ' MB' : null, 'pct' => $dbPct, 'by' => $dbLimit ? sl_dinfOvrLabel($dbLimit) : ''],
    ];

    // nodes / zones: same components and health checks as Overview
    $comps = function_exists('sl_dvxComponents') ? sl_dvxComponents() : [];
    $zoneDef = [
        'edge' => ['Edge', 'How requests reach the server', ['entry']],
        'host' => ['This server · ' . (string) gethostname(), 'PHP application, scheduled jobs and queues on one host', ['app', 'jobs']],
        'data' => ['Data', 'Database, files and backups', ['data']],
        'ext' => ['External services', 'Providers reached over the internet', ['external']],
    ];
    $d['zones'] = [];
    foreach ($zoneDef as $zk => [$zl, $zs, $layers]) {
        $nodes = array_values(array_filter($comps, fn($c) => in_array($c['layer'], $layers, true)));
        if (!$nodes) {
            continue;
        }
        $bad = count(array_filter($nodes, fn($n) => in_array($n['cls'], ['bad'], true)));
        $warn = count(array_filter($nodes, fn($n) => $n['cls'] === 'warn'));
        $d['zones'][] = ['k' => $zk, 'label' => $zl, 'sub' => $zs, 'tone' => $bad ? 'bad' : ($warn ? 'warn' : 'ok'), 'n' => count($nodes),
            'nodes' => array_map(fn($n) => ['name' => $n['name'], 'icon' => $n['icon'], 'cls' => $n['cls'], 'st' => $n['st'], 'sub' => $n['sub'], 'detail' => $n['detail'], 'href' => $n['href']], $nodes)];
    }

    // jobs, queue, backups
    $d['crons'] = array_map(function ($j) {
        $l = $j['last'];
        $state = !$l ? 'never recorded' : ($l->status === 'failed' ? 'failed' : ($l->status === 'running' ? 'running' : 'ok'));
        return ['name' => basename((string) $j['file']), 'what' => $j['what'] ?: '', 'state' => $state, 'lastAt' => $l ? sl_date($l->started_at, 'M j, H:i') : '', 'tone' => !$l ? 'warn' : ($l->status === 'failed' ? 'bad' : 'ok'),
            'last' => $l ? sl_ago($l->started_at) : '', 'sched' => (string) ($j['schedule'] ?? ''), 'runs' => (int) ($j['runs24'] ?? 0), 'fails' => (int) ($j['fails24'] ?? 0)];
    }, sl_devCronJobs());
    $d['queue'] = sl_devQueue();
    $d['backup'] = sl_devBackup();

    $d['events'] = array_map(fn($e) => $e + ['ago' => sl_ago($e['ts']), 'when' => sl_date($e['ts'], 'M j H:i')], sl_dinfEvents(14));
    $d['overrides'] = sl_dinfOverrideRows();
    $d['ovrSet'] = count(array_filter($d['overrides'], fn($r) => $r['set']));
    $d['config'] = sl_devConfig();
    $paths = [];
    foreach (['assets/uploads', 'order_files', 'backups', 'support_files', 'driver_files', 'dev_reports', 'cron', 'logs'] as $p) {
        $full = CDP_ROOT . '/' . $p;
        $paths[] = ['p' => $p . '/', 'exists' => is_dir($full), 'w' => is_dir($full) && is_writable($full)];
    }
    $d['paths'] = $paths;
    $d['ext'] = array_map(fn($e) => ['n' => $e, 'ok' => extension_loaded($e)], ['curl', 'pdo_mysql', 'mbstring', 'gd', 'openssl', 'intl', 'zip', 'fileinfo', 'json', 'xml', 'sodium', 'redis']);
    return $d;
}

/* ============================================================== Alerts */

function sl_dinfAlertsData($user, $core): array
{
    $d = ['ajax' => 'ajax/sl/dev_infra_ajax.php', 'host' => (string) gethostname()];
    // refresh the built-in checks (stores a sample at most once a minute), then put back rule alerts
    // that sample closed by mistake (helpers/dev_infra.php sl_dinfRepairRuleAlerts)
    sl_dvxSnap();
    sl_dinfRepairRuleAlerts();
    $tab = in_array(sl_dinfGet('tab'), ['open', 'resolved', 'all'], true) ? sl_dinfGet('tab') : 'open';
    $sev = in_array(sl_dinfGet('sev'), ['critical', 'warning', 'info'], true) ? sl_dinfGet('sev') : '';
    $q = mb_substr(trim(sl_dinfGet('q')), 0, 80);
    $d['tab'] = $tab;
    $d['sev'] = $sev;
    $d['q'] = $q;
    $all = cdp_devAlerts('all', 500);
    $open = array_values(array_filter($all, fn($a) => in_array($a['status'], ['active', 'acknowledged'], true)));
    $cnt = fn(array $l, string $s) => count(array_filter($l, fn($a) => $a['severity'] === $s));
    $res24 = array_filter($all, fn($a) => $a['status'] === 'resolved' && $a['resolved_at'] && strtotime($a['resolved_at']) >= time() - 86400);
    $res7 = array_filter($all, fn($a) => $a['status'] === 'resolved' && $a['resolved_at'] && strtotime($a['resolved_at']) >= time() - 7 * 86400);
    $mttr = $res7 ? array_sum(array_map(fn($a) => $a['duration_s'], $res7)) / count($res7) : null;
    $d['totals'] = [
        ['k' => 'critical', 't' => 'Critical', 'n' => $cnt($open, 'critical'), 'tone' => 'bad', 'icon' => 'i-alert', 'sub' => 'open now', 'href' => sl_url(['tab' => 'open', 'sev' => 'critical', 'alert' => null])],
        ['k' => 'warning', 't' => 'Warning', 'n' => $cnt($open, 'warning'), 'tone' => 'warn', 'icon' => 'i-bell', 'sub' => 'open now', 'href' => sl_url(['tab' => 'open', 'sev' => 'warning', 'alert' => null])],
        ['k' => 'info', 't' => 'Info', 'n' => $cnt($open, 'info'), 'tone' => 'info', 'icon' => 'i-info', 'sub' => 'open now', 'href' => sl_url(['tab' => 'open', 'sev' => 'info', 'alert' => null])],
        ['k' => 'resolved', 't' => 'Resolved', 'n' => count($res24), 'tone' => 'ok', 'icon' => 'i-check', 'sub' => 'in the last 24 h', 'href' => sl_url(['tab' => 'resolved', 'sev' => null, 'alert' => null])],
        ['k' => 'mttr', 't' => 'Mean time to resolve', 'n' => $mttr !== null ? cdp_devDur((int) $mttr) : '—', 'tone' => 'info', 'icon' => 'i-clock', 'sub' => $res7 ? count($res7) . ' resolved in 7 days' : 'nothing resolved in 7 days', 'href' => sl_url(['tab' => 'resolved', 'sev' => null, 'alert' => null])],
    ];
    $d['openN'] = count($open);
    $d['tabs'] = array_map(fn($t) => ['label' => $t[1], 'n' => $t[2], 'cls' => $tab === $t[0] ? 'on' : '', 'href' => sl_url(['tab' => $t[0], 'alert' => null])],
        [['open', 'Open', count($open)], ['resolved', 'Resolved', count($all) - count($open)], ['all', 'All', count($all)]]);
    $d['sevs'] = array_map(fn($s) => ['label' => $s === '' ? 'All severities' : ucfirst($s), 'cls' => $sev === $s ? 'on' : '', 'href' => sl_url(['sev' => $s ?: null, 'alert' => null])], ['', 'critical', 'warning', 'info']);
    $list = $tab === 'open' ? $open : ($tab === 'resolved' ? array_values(array_filter($all, fn($a) => $a['status'] === 'resolved')) : $all);
    if ($sev !== '') {
        $list = array_values(array_filter($list, fn($a) => $a['severity'] === $sev));
    }
    if ($q !== '') {
        $list = array_values(array_filter($list, fn($a) => stripos($a['title'] . ' ' . $a['service'] . ' ' . $a['detail'], $q) !== false));
    }
    $names = [];
    foreach (sl_rows("SELECT id, CONCAT(fname,' ',lname) AS n FROM cdb_users WHERE id IN (SELECT DISTINCT ack_by FROM cdb_dev_alerts WHERE ack_by IS NOT NULL)") as $u) {
        $names[(int) $u->id] = trim((string) $u->n);
    }
    $notified = sl_dinfNotified();
    $rules = [];
    if (sl_tableExists('cdb_dev_alert_rules')) {
        foreach (sl_rows('SELECT * FROM cdb_dev_alert_rules ORDER BY id') as $r) {
            $rules[(string) $r->rule_key] = $r;
        }
    }
    $metrics = sl_dinfMetrics();
    $selId = is_scalar($_GET['alert'] ?? null) ? (int) $_GET['alert'] : 0;
    $d['alerts'] = [];
    foreach (array_slice($list, 0, 150) as $a) {
        $ctx = $a['context'];
        $isRule = strpos((string) $a['key'], 'rule:') === 0;
        $rule = $isRule ? ($rules[substr((string) $a['key'], 5)] ?? null) : null;
        $unit = $rule ? ($metrics[$rule->metric][1] ?? '') : '';
        $n = $notified[(string) $a['id']] ?? null;
        $st = $a['status'];
        $row = ['id' => $a['id'], 't' => $a['title'], 'svc' => $a['service'], 'detail' => sl_dinfNiceDetail((string) $a['detail']), 'sev' => $a['severity'],
            'sevCls' => $a['severity'] === 'critical' ? 'bad' : ($a['severity'] === 'warning' ? 'warn' : 'info'),
            'st' => $st, 'stLabel' => ['active' => 'Active', 'acknowledged' => 'Acknowledged', 'resolved' => 'Resolved'][$st] ?? ucfirst($st), 'stCls' => $st === 'resolved' ? 'ok' : ($st === 'acknowledged' ? 'idle' : 'bad'),
            'first' => sl_date($a['first_seen'], 'M j, H:i'), 'firstAgo' => sl_ago($a['first_seen']), 'last' => sl_date($a['last_seen'], 'M j, H:i'),
            'resolved' => $a['resolved_at'] ? sl_date($a['resolved_at'], 'M j, H:i') : '', 'dur' => cdp_devDur($a['duration_s']),
            'ack' => $a['ack_at'] ? 'Acknowledged by ' . ($names[(int) $a['ack_by']] ?? 'user #' . (int) $a['ack_by']) . ' · ' . sl_date($a['ack_at'], 'M j, H:i') : '',
            'source' => $isRule ? 'Rule: ' . ($rule ? $rule->label : substr((string) $a['key'], 5)) : 'Built-in service check',
            'value' => isset($ctx['value']) && $ctx['value'] !== null ? sl_dinfFmt($ctx['value']) . ($unit ? ' ' . $unit : '') : '',
            'limit' => isset($ctx['threshold']) ? ($rule ? $rule->comparator . ' ' : '') . sl_dinfFmt($ctx['threshold']) . ($unit ? ' ' . $unit : '') : '',
            'window' => !empty($ctx['window_min']) ? (int) $ctx['window_min'] . ' min' : '',
            'mail' => $n ? 'E-mailed ' . (int) $n['sent'] . ' of ' . (int) $n['to'] . ' developer' . ((int) $n['to'] === 1 ? '' : 's') . ' · ' . sl_date($n['at'], 'M j, H:i') . ((int) $n['failed'] ? ' · ' . (int) $n['failed'] . ' failed' : '') : '',
            'sel' => $selId === $a['id']];
        $d['alerts'][] = $row;
    }
    $d['sel'] = null;
    foreach ($d['alerts'] as $r) {
        if ($r['sel']) {
            $d['sel'] = $r;
        }
    }
    if ($selId && !$d['sel']) {
        // linked alert outside the current filter: load it alone
        foreach ($all as $a) {
            if ($a['id'] === $selId) {
                $d['selOutside'] = sl_url(['tab' => 'all', 'sev' => null, 'q' => null]);
            }
        }
    }

    // alerts opened per day, 14 days, stacked by severity
    $days = [];
    for ($i = 13; $i >= 0; $i--) {
        $days[date('Y-m-d', strtotime("-$i days"))] = ['critical' => 0, 'warning' => 0, 'info' => 0];
    }
    foreach ($all as $a) {
        $k = substr((string) $a['first_seen'], 0, 10);
        if (isset($days[$k])) {
            $days[$k][$a['severity']] = ($days[$k][$a['severity']] ?? 0) + 1;
        }
    }
    $mx = max(1, max(array_map(fn($x) => array_sum($x), $days)));
    $d['perDay'] = array_map(fn($k, $v) => ['l' => date('M j', strtotime($k)), 'short' => date('j', strtotime($k)), 'n' => array_sum($v), 'c' => $v['critical'], 'w' => $v['warning'], 'i' => $v['info'],
        'hc' => round($v['critical'] / $mx * 100, 1), 'hw' => round($v['warning'] / $mx * 100, 1), 'hi' => round($v['info'] / $mx * 100, 1)], array_keys($days), $days);
    $d['perDayMax'] = $mx;
    $src = [];
    foreach ($all as $a) {
        if (strtotime((string) $a['first_seen']) >= time() - 14 * 86400) {
            $k = (string) $a['service'];
            $src[$k] = ($src[$k] ?? 0) + 1;
        }
    }
    arsort($src);
    $smx = $src ? max($src) : 1;
    $d['sources'] = array_map(fn($k, $v) => ['svc' => $k, 'n' => $v, 'pct' => round($v / $smx * 100, 1), 'href' => sl_url(['tab' => 'all', 'q' => $k, 'sev' => null, 'alert' => null])], array_keys(array_slice($src, 0, 5, true)), array_slice($src, 0, 5, true));

    // rules with live values
    $d['metrics'] = $metrics;
    $d['rules'] = [];
    foreach ($rules as $r) {
        $v = sl_devMetric((string) $r->metric, (int) $r->window_min);
        $m = $metrics[$r->metric] ?? [$r->metric, '', true, ''];
        $fires = sl_devCompare($v, (string) $r->comparator, (float) $r->threshold);
        $d['rules'][] = ['id' => (int) $r->id, 'key' => (string) $r->rule_key, 'label' => (string) $r->label, 'metric' => (string) $r->metric, 'mlabel' => $m[0], 'unit' => $m[1], 'windowed' => $m[2],
            'cmp' => (string) $r->comparator, 'th' => sl_dinfFmt($r->threshold), 'win' => (int) $r->window_min, 'sev' => (string) $r->severity, 'notify' => (string) $r->notify, 'on' => (int) $r->enabled === 1,
            'now' => $v === null ? 'not measured' : sl_dinfFmt($v) . ($m[1] ? ' ' . $m[1] : ''), 'fires' => (int) $r->enabled === 1 && $fires, 'measured' => $v !== null,
            'json' => json_encode(['id' => (int) $r->id, 'label' => (string) $r->label, 'metric' => (string) $r->metric, 'comparator' => (string) $r->comparator, 'threshold' => sl_dinfFmt($r->threshold), 'window_min' => (int) $r->window_min, 'severity' => (string) $r->severity, 'notify' => (string) $r->notify])];
    }
    $d['rulesReady'] = sl_tableExists('cdb_dev_alert_rules');
    // last SCHEDULED evaluation (cron/dev_alerts.php; cdb_cron_runs is on the database clock) and, apart, the last manual one
    $d['lastEval'] = sl_tableExists('cdb_cron_runs') ? sl_dinfDbToApp(sl_scalar("SELECT MAX(started_at) FROM cdb_cron_runs WHERE job = 'dev_alerts'", [], '')) : '';
    $d['lastManual'] = sl_tableExists('cdb_activity_log') ? (string) sl_scalar("SELECT MAX(created_at) FROM cdb_activity_log WHERE action = 'developer.alerts_evaluate'", [], '') : '';
    $d['notify'] = sl_dinfNotifySettings();
    $d['recipients'] = sl_dinfRecipients($d['notify']);
    $d['devUsers'] = $d['recipients'];
    $d['mailer'] = (string) ($core->mailer ?? '');
    return $d;
}
