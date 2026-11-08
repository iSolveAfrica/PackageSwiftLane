<?php
/**
 * Data for the developer console boards (DevOverview, DevServices, DevLogs, DevMetrics,
 * DevInfrastructure, DevDeployments, DevAlerts, DevIssues, DevIntegrations).
 *
 * One console: the live samples, services, alerts, logs and deployments come from the
 * DevOps dashboard engine in helpers/devops.php (tables cdb_dev_metrics, cdb_dev_alerts,
 * cdb_dev_deploys, cdb_dev_state, filled by cron/devops_collector.php). This file adds
 * what that engine does not have: cron run ledger (cdb_cron_runs), alert rules
 * (cdb_dev_alert_rules), the issue tracker (cdb_dev_issues), the change log
 * (cdb_dev_changelog), table sizes and the integrations list (helpers/dev_console.php).
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/sl_charts.php';
require_once CDP_ROOT . '/helpers/rbac.php';
require_once CDP_ROOT . '/helpers/devops.php';
require_once CDP_ROOT . '/helpers/dev_console.php';

/** One live snapshot per request (stores a sample at most once a minute, like developer.php). */
function sl_devSnap(): array
{
    static $s = null;
    if ($s === null) {
        try {
            $s = cdp_devCollect(true);
        } catch (Throwable $e) {
            $s = ['server' => [], 'db' => ['up' => false], 'web' => [], 'api' => [], 'activity' => [], 'channels' => [], 'services' => [], 'error_rate' => 0];
        }
    }
    return $s;
}

function sl_devStatusCls(string $st): array
{
    return ['operational' => ['Healthy', '#3FD08F', ''], 'degraded' => ['Degraded', '#EBB43C', 'dv-warn'], 'down' => ['Down', '#E5545A', 'dv-bad'], 'not_configured' => ['Not set up', '#8E8A82', ''], 'idle' => ['Idle', '#5B9BF0', ''], 'unknown' => ['Unknown', '#8E8A82', '']][$st] ?? [ucfirst($st), '#8E8A82', ''];
}

/** Sparkline paths (viewBox 240x46 / 200x40) from a series. */
function sl_devSpark(array $vals, float $w = 240, float $h = 46): array
{
    $vals = array_values(array_map(fn($v) => (float) ($v ?? 0), $vals));
    if (count($vals) < 2) {
        return ['line' => '', 'area' => ''];
    }
    $max = max($vals) ?: 1;
    return ['values' => $vals, 'line' => sl_svgLinePath($vals, $w, $h, 4, $max * 1.1), 'area' => sl_svgAreaPath($vals, $w, $h, 4, $max * 1.1)];
}

function sl_devCommon($user): array
{
    $snap = sl_devSnap();
    $svc = $snap['services'];
    $c = ['operational' => 0, 'degraded' => 0, 'down' => 0, 'other' => 0];
    foreach ($svc as $s) {
        $c[isset($c[$s['status']]) ? $s['status'] : 'other']++;
    }
    return ['snap' => $snap, 'counts' => $c, 'host' => (string) ($snap['server']['host'] ?? gethostname()), 'canManage' => $user->cdp_hasPermission('view_developer'), 'liveUrl' => 'developer.php', 'flash' => (string) ($_GET['flash'] ?? '')];
}

/* ------------------------------------------------------------- overview */

function sl_devOverviewData($user, $core): array
{
    $d = sl_devCommon($user);
    $snap = $d['snap'];
    $range = in_array($_GET['range'] ?? '24h', ['1h', '24h', '7d'], true) ? (string) ($_GET['range'] ?? '24h') : '24h';
    $d['range'] = $range;
    $d['ranges'] = array_map(fn($r) => ['label' => $r, 'cls' => $r === $range ? 'on' : '', 'href' => sl_url(['range' => $r])], ['1h', '24h', '7d']);
    $series = cdp_devSeries($range);
    $d['samples'] = count($series);
    $up = cdp_devAvailability(30);
    $act = $snap['activity'];
    $d['kpis'] = [
        ['t' => 'Actions / min', 'v' => sl_num($act['actions_1m'] ?? 0), 'u' => '', 'd' => sl_num($act['actions_24h'] ?? 0) . ' in 24 h', 'dc' => '', 'sp' => sl_devSpark(array_column($series, 'apm')), 'c' => '#5B9BF0'],
        ['t' => 'Web response', 'v' => isset($snap['web']['ms']) && $snap['web']['ok'] ? sl_num($snap['web']['ms']) : '—', 'u' => isset($snap['web']['ms']) && $snap['web']['ok'] ? 'ms' : '', 'd' => $snap['web']['error'] ?? ('HTTP ' . ($snap['web']['code'] ?? '')), 'dc' => !empty($snap['web']['ok']) ? 'dv-ok' : 'dv-warn', 'sp' => sl_devSpark(array_column($series, 'web')), 'c' => '#3FD08F'],
        ['t' => 'Error rate (5 min)', 'v' => number_format((float) $snap['error_rate'], 2), 'u' => '%', 'd' => (int) ($act['failed_24h'] ?? 0) . ' failed actions in 24 h', 'dc' => $snap['error_rate'] > 5 ? 'dv-bad' : 'dv-ok', 'sp' => sl_devSpark(array_column($series, 'err')), 'c' => '#E5545A'],
        ['t' => 'Uptime (30 d)', 'v' => $up !== null ? number_format($up, 2) : '—', 'u' => $up !== null ? '%' : '', 'd' => $up !== null ? 'From collector samples' : 'No samples yet: schedule cron/devops_collector.php', 'dc' => '', 'sp' => sl_devSpark(array_column($series, 'db')), 'c' => '#EBB43C'],
    ];
    $srv = $snap['server'];
    $disk = sl_devDisk();
    $d['gauges'] = [
        ['icon' => 'i-folder', 't' => 'Disk', 'v' => $disk['pct'] . '%', 'sub' => sl_devBytes($disk['used']) . ' / ' . sl_devBytes($disk['total']), 'w' => $disk['pct'], 'cls' => $disk['pct'] >= 90 ? 'r' : ($disk['pct'] >= 80 ? 'y' : ''), 'href' => sl_route('DevInfrastructure')],
        ['icon' => 'i-cpu', 't' => 'Memory', 'v' => isset($srv['mem_pct']) && $srv['mem_pct'] !== null ? $srv['mem_pct'] . '%' : '—', 'sub' => isset($srv['mem_total']) && $srv['mem_total'] ? sl_devBytes($srv['mem_used']) . ' / ' . sl_devBytes($srv['mem_total']) : '/proc not readable', 'w' => (int) ($srv['mem_pct'] ?? 0), 'cls' => ($srv['mem_pct'] ?? 0) >= 90 ? 'r' : (($srv['mem_pct'] ?? 0) >= 80 ? 'y' : ''), 'href' => sl_route('DevMetrics')],
        ['icon' => 'i-database', 't' => 'Database', 'v' => !empty($snap['db']['up']) ? ($snap['db']['ping_ms'] . ' ms') : 'down', 'sub' => (int) ($snap['db']['connections'] ?? 0) . ' / ' . (int) ($snap['db']['max_connections'] ?? 0) . ' connections · ' . sl_devBytes($snap['db']['size_bytes'] ?? 0), 'w' => sl_pct($snap['db']['connections'] ?? 0, $snap['db']['max_connections'] ?? 0), 'cls' => empty($snap['db']['up']) ? 'r' : '', 'href' => sl_route('DevMetrics')],
    ];
    $q = sl_devQueue();
    $d['quick'] = ['queue' => $q, 'cron' => sl_devCronJobs()];
    // status donut from activity outcomes (24 h)
    $o = sl_tableExists('cdb_activity_log') ? sl_row("SELECT SUM(outcome = 'success' OR outcome = 'ok') AS ok, SUM(outcome = 'failure') AS f, SUM(outcome = 'denied') AS dn, COUNT(*) AS n FROM cdb_activity_log WHERE created_at >= NOW() - INTERVAL 1 DAY") : null;
    $n = (int) ($o->n ?? 0);
    $other = $n - (int) $o->ok - (int) $o->f - (int) $o->dn;
    $d['outcomes'] = ['n' => $n, 'conic' => $n ? sl_conic([['#3FD08F', (int) $o->ok], ['#5B9BF0', max(0, $other)], ['#EBB43C', (int) $o->dn], ['#E5545A', (int) $o->f]]) : 'conic-gradient(var(--line) 0 100%)', 'rows' => [['#3FD08F', 'Succeeded', (int) ($o->ok ?? 0)], ['#5B9BF0', 'Other', max(0, $other)], ['#EBB43C', 'Denied', (int) ($o->dn ?? 0)], ['#E5545A', 'Failed', (int) ($o->f ?? 0)]]];
    $dep = cdp_devDeployments($snap['services']);
    $d['deploys'] = array_map(fn($h) => ['t' => $h['message'], 's' => $h['author'] . ' · ' . $h['hash'] . ' · ' . $h['kind'], 'at' => sl_ago($h['ts']), 'w' => $h['kind'] === 'reset' ? 'w' : ''], array_slice($dep['history'], 0, 4));
    $d['logs'] = array_map(fn($l) => ['ts' => date('H:i:s', strtotime($l['ts'])), 'sv' => $l['service'], 'lv' => $l['level'], 'lc' => $l['level'] === 'ERROR' ? 'e' : ($l['level'] === 'WARN' ? 'w' : 'i'), 'm' => $l['message']], cdp_devLogs(['window' => 1440, 'limit' => 8]));
    $d['openAlerts'] = count(cdp_devAlerts('open', 200));
    $d['openIssues'] = sl_tableExists('cdb_dev_issues') ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_dev_issues WHERE status IN ('open','in_progress','review')") : 0;
    return $d;
}

/* ------------------------------------------------------------- services */

function sl_devServicesData($user, $core): array
{
    $d = sl_devCommon($user);
    $f = (string) ($_GET['filter'] ?? 'all');
    $rows = [];
    foreach ($d['snap']['services'] as $s) {
        $st = sl_devStatusCls($s['status']);
        $grp = $s['status'] === 'operational' ? 'ok' : (in_array($s['status'], ['degraded', 'down'], true) ? $s['status'] : 'other');
        if ($f !== 'all' && $f !== $grp) continue;
        $rows[] = ['key' => $s['key'], 'name' => $s['name'], 'tech' => $s['tech'], 'layer' => $s['layer'], 'st' => $st[0], 'dot' => $st[1], 'ec' => $st[2], 'lat' => $s['latency_ms'] !== null ? sl_devMs($s['latency_ms']) : '—', 'err' => $s['error_rate'] !== null ? number_format((float) $s['error_rate'], 2) . '%' : '—', 'thr' => $s['throughput'] ?? '—', 'inst' => $s['instances'], 'detail' => $s['detail'], 'cls' => ($_GET['svc'] ?? '') === $s['key'] ? 'on' : '', 'href' => sl_url(['svc' => $s['key']])];
    }
    // extra local checks (cron ledger, backups, SMS/WhatsApp providers) from dev_console
    $extra = array_filter(sl_devServices(), fn($s) => in_array($s['key'], ['cron', 'backup', 'hubtel_sms', 'mnotify_sms', 'whatsapp'], true));
    $map = ['ok' => 'operational', 'warn' => 'degraded', 'bad' => 'down', 'off' => 'not_configured'];
    foreach ($extra as $s) {
        $stKey = $map[$s['status']] ?? 'unknown';
        $grp = $stKey === 'operational' ? 'ok' : (in_array($stKey, ['degraded', 'down'], true) ? $stKey : 'other');
        $d['counts'][$grp === 'ok' ? 'operational' : ($grp === 'other' ? 'other' : $grp)]++;
        if ($f !== 'all' && $f !== $grp) continue;
        $st = sl_devStatusCls($stKey);
        $rows[] = ['key' => 'x_' . $s['key'], 'name' => $s['name'], 'tech' => $s['version'] ?: '', 'layer' => 'jobs', 'st' => $st[0], 'dot' => $st[1], 'ec' => $st[2], 'lat' => $s['ms'] !== null ? sl_devMs($s['ms']) : '—', 'err' => '—', 'thr' => '—', 'inst' => '1/1', 'detail' => $s['detail'], 'cls' => ($_GET['svc'] ?? '') === 'x_' . $s['key'] ? 'on' : '', 'href' => sl_url(['svc' => 'x_' . $s['key']])];
    }
    $d['rows'] = $rows;
    $c = $d['counts'];
    $total = array_sum($c);
    $d['total'] = $total;
    $d['tabs'] = array_map(fn($t) => ['label' => $t[1], 'cls' => $f === $t[0] ? 'on' : '', 'href' => sl_url(['filter' => $t[0], 'svc' => null])], [['all', 'All ' . $total], ['ok', 'Healthy ' . $c['operational']], ['degraded', 'Degraded ' . $c['degraded']], ['down', 'Down ' . $c['down']], ['other', 'Not set up / idle ' . $c['other']]]);
    $sel = null;
    foreach ($rows as $r) if ($r['cls'] === 'on') $sel = $r;
    if (!$sel) foreach ($rows as $r) if ($r['st'] !== 'Healthy') { $sel = $r; break; }
    $sel = $sel ?: ($rows[0] ?? null);
    $d['sel'] = $sel;
    // environment block for the selected service: masked config lines that belong to it
    $d['env'] = sl_devConfig();
    $d['endpoints'] = sl_tableExists('cdb_activity_log') ? sl_rows("SELECT module, COUNT(*) AS n, SUM(outcome = 'failure') AS f FROM cdb_activity_log WHERE created_at >= NOW() - INTERVAL 1 DAY GROUP BY module ORDER BY n DESC LIMIT 6") : [];
    return $d;
}

/* ----------------------------------------------------------------- logs */

function sl_devLogsData($user, $core): array
{
    $d = sl_devCommon($user);
    $tab = in_array($_GET['tab'] ?? 'all', ['all', 'php', 'cron', 'api'], true) ? (string) ($_GET['tab'] ?? 'all') : 'all';
    $lv = in_array($_GET['level'] ?? '', ['', 'INFO', 'WARN', 'ERROR'], true) ? (string) ($_GET['level'] ?? '') : '';
    $win = in_array((int) ($_GET['window'] ?? 60), [15, 60, 1440, 10080], true) ? (int) ($_GET['window'] ?? 60) : 60;
    $q = trim((string) ($_GET['q'] ?? ''));
    $src = in_array($_GET['source'] ?? '', ['', 'activity', 'messages', 'ai', 'php', 'alerts'], true) ? (string) ($_GET['source'] ?? '') : '';
    $d += ['tab' => $tab, 'level' => $lv, 'window' => $win, 'q' => $q, 'source' => $src];
    $d['tabs'] = array_map(fn($t) => ['label' => $t[1], 'cls' => $tab === $t[0] ? 'on' : '', 'href' => sl_url(['tab' => $t[0]])], [['all', 'All sources'], ['php', 'PHP error log'], ['cron', 'Cron runs'], ['api', 'API requests']]);
    $d['levels'] = array_map(fn($l) => ['label' => $l ?: 'All', 'cls' => $lv === $l ? 'on' : '', 'href' => sl_url(['level' => $l])], ['', 'INFO', 'WARN', 'ERROR']);
    $d['windows'] = [15 => 'Last 15 min', 60 => 'Last hour', 1440 => 'Last 24 h', 10080 => 'Last 7 days'];
    $d['sources'] = ['' => 'All services', 'activity' => 'Application', 'messages' => 'Messages', 'ai' => 'AI', 'php' => 'PHP errors', 'alerts' => 'Alerts'];
    $rows = [];
    if ($tab === 'all') {
        foreach (cdp_devLogs(['window' => $win, 'limit' => 300, 'source' => $src, 'q' => $q, 'level' => $lv]) as $l) {
            $rows[] = ['ts' => $l['ts'], 'sv' => $l['service'], 'lv' => $l['level'], 'm' => $l['message'], 'detail' => $l['detail'] ?? null];
        }
    } elseif ($tab === 'php') {
        $path = sl_devErrorLogPath();
        $d['phpPath'] = $path;
        $since = time() - $win * 60;
        foreach (sl_devTail($path, 2000) as $line) {
            $p = sl_devParseLogLine($line);
            if ($p['ts'] !== null && $p['ts'] < $since) break;
            if ($lv && $p['level'] !== $lv) continue;
            if ($q !== '' && stripos($p['text'], $q) === false) continue;
            $rows[] = ['ts' => $p['ts'] ? date('Y-m-d H:i:s', $p['ts']) : '', 'sv' => 'php', 'lv' => $p['level'], 'm' => $p['text'], 'detail' => null];
            if (count($rows) >= 300) break;
        }
    } elseif ($tab === 'cron') {
        if (sl_tableExists('cdb_cron_runs')) {
            $w = 'started_at >= NOW() - INTERVAL :m MINUTE';
            $b = [':m' => $win];
            if ($q !== '') { $w .= ' AND (job LIKE :q OR message LIKE :q2)'; $b[':q'] = "%$q%"; $b[':q2'] = "%$q%"; }
            if ($lv === 'ERROR') $w .= " AND status = 'failed'";
            if ($lv === 'INFO') $w .= " AND status = 'ok'";
            if ($lv === 'WARN') $w .= " AND status = 'running'";
            foreach (sl_rows("SELECT * FROM cdb_cron_runs WHERE $w ORDER BY id DESC LIMIT 300", $b) as $r) {
                $rows[] = ['ts' => $r->started_at, 'sv' => $r->job, 'lv' => $r->status === 'failed' ? 'ERROR' : ($r->status === 'running' ? 'WARN' : 'INFO'), 'm' => strtoupper($r->status) . ($r->duration_ms !== null ? ' in ' . sl_devMs($r->duration_ms) : '') . ($r->message ? ' · ' . $r->message : ''), 'detail' => ['host' => $r->host, 'finished_at' => $r->finished_at]];
            }
        }
        $d['cronReady'] = sl_tableExists('cdb_cron_runs');
    } else {
        $t = sl_tableExists('cdb_api_request_log') ? 'cdb_api_request_log' : '';
        $d['apiTable'] = $t;
        if ($t) {
            $cols = array_column(sl_rows("SELECT COLUMN_NAME AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t", [':t' => $t]), 'c');
            $tsCol = in_array('created_at', $cols, true) ? 'created_at' : (in_array('ts', $cols, true) ? 'ts' : '');
            if ($tsCol) {
                foreach (sl_rows("SELECT * FROM $t WHERE $tsCol >= NOW() - INTERVAL :m MINUTE ORDER BY $tsCol DESC LIMIT 300", [':m' => $win]) as $r) {
                    $a = (array) $r;
                    $code = (int) ($a['status_code'] ?? $a['status'] ?? $a['http_status'] ?? 0);
                    $rows[] = ['ts' => $a[$tsCol], 'sv' => 'api', 'lv' => $code >= 500 ? 'ERROR' : ($code >= 400 ? 'WARN' : 'INFO'), 'm' => trim(($a['method'] ?? '') . ' ' . ($a['endpoint'] ?? $a['path'] ?? $a['uri'] ?? '') . ' ' . ($code ?: '') . (isset($a['duration_ms']) ? ' ' . $a['duration_ms'] . 'ms' : (isset($a['latency_ms']) ? ' ' . $a['latency_ms'] . 'ms' : ''))), 'detail' => null];
                }
            }
        }
    }
    $d['rows'] = array_map(function ($r) {
        $r['time'] = $r['ts'] ? date('M j H:i:s', strtotime($r['ts'])) : '';
        $r['lc'] = $r['lv'] === 'ERROR' ? 'e' : ($r['lv'] === 'WARN' ? 'w' : 'i');
        $r['json'] = $r['detail'] ? json_encode($r['detail'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        return $r;
    }, $rows);
    $d['nErr'] = count(array_filter($rows, fn($r) => $r['lv'] === 'ERROR'));
    $d['nWarn'] = count(array_filter($rows, fn($r) => $r['lv'] === 'WARN'));
    // histogram: 48 buckets across the window
    $buckets = array_fill(0, 48, 0);
    $now = time();
    foreach ($rows as $r) {
        if (!$r['ts']) continue;
        $i = 47 - (int) floor(($now - strtotime($r['ts'])) / max(1, $win * 60 / 48));
        if ($i >= 0 && $i < 48) $buckets[$i]++;
    }
    $mx = max(1, max($buckets));
    $d['hist'] = array_map(fn($v) => max(3, sl_pct($v, $mx)), $buckets);
    $sel = (int) ($_GET['row'] ?? -1);
    $d['sel'] = $d['rows'][$sel] ?? (array_values(array_filter($d['rows'], fn($r) => $r['lv'] === 'ERROR'))[0] ?? ($d['rows'][0] ?? null));
    // saved views: real counts in 24 h
    $act = sl_tableExists('cdb_activity_log');
    $d['saved'] = [
        ['t' => 'Failed sign-ins', 'n' => $act ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_activity_log WHERE verb = 'login' AND outcome IN ('failure','denied') AND created_at >= NOW() - INTERVAL 1 DAY") : 0, 'href' => sl_url(['tab' => 'all', 'source' => 'activity', 'q' => 'login', 'window' => 1440])],
        ['t' => 'Failed actions', 'n' => $act ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_activity_log WHERE outcome = 'failure' AND created_at >= NOW() - INTERVAL 1 DAY") : 0, 'href' => sl_url(['tab' => 'all', 'source' => 'activity', 'level' => 'ERROR', 'window' => 1440])],
        ['t' => 'Message delivery errors', 'n' => sl_tableExists('cdb_message_log') ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_message_log WHERE status = 'failed' AND created_at >= NOW() - INTERVAL 1 DAY") : 0, 'href' => sl_url(['tab' => 'all', 'source' => 'messages', 'level' => 'ERROR', 'window' => 1440])],
        ['t' => 'PHP errors (1 h)', 'n' => sl_devErrorLogPath() !== '' ? sl_devPhpErrorCount(60) : 0, 'href' => sl_url(['tab' => 'php', 'window' => 60])],
        ['t' => 'Failed cron runs', 'n' => sl_tableExists('cdb_cron_runs') ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_cron_runs WHERE status = 'failed' AND started_at >= NOW() - INTERVAL 1 DAY") : 0, 'href' => sl_url(['tab' => 'cron', 'level' => 'ERROR', 'window' => 1440])],
    ];
    return $d;
}

/* -------------------------------------------------------------- metrics */

function sl_devMetricsData($user, $core): array
{
    $d = sl_devCommon($user);
    $range = in_array($_GET['range'] ?? '24h', ['1h', '24h', '7d', '30d'], true) ? (string) ($_GET['range'] ?? '24h') : '24h';
    $d['range'] = $range;
    $d['ranges'] = array_map(fn($r) => ['label' => $r, 'cls' => $r === $range ? 'on' : '', 'href' => sl_url(['range' => $r])], ['1h', '24h', '7d', '30d']);
    $s = cdp_devSeries($range);
    $d['samples'] = count($s);
    $chart = function (string $title, string $sub, array $lines, string $unit) use ($s): array {
        $all = [];
        foreach ($lines as $l) foreach (array_column($s, $l[0]) as $v) if ($v !== null) $all[] = (float) $v;
        $max = $all ? max($all) : 0;
        $max = $max > 0 ? $max * 1.15 : 1;
        $paths = [];
        foreach ($lines as $l) {
            $vals = array_map(fn($v) => (float) ($v ?? 0), array_column($s, $l[0]));
            $paths[] = ['c' => $l[1], 'label' => $l[2], 'values' => array_column($s, $l[0]), 'd' => count($vals) > 1 ? sl_svgLinePath($vals, 634, 160, 10, $max, 44) : '', 'last' => $vals ? end($vals) : null];
        }
        $ticks = [];
        foreach ([0, 1, 2, 3] as $i) $ticks[] = ['y' => 10 + $i * 50, 'l' => $unit === '%' ? round($max * (3 - $i) / 3) . '%' : sl_num($max * (3 - $i) / 3, $max < 10 ? 1 : 0)];
        $xl = [];
        if ($s) {
            $n = count($s);
            foreach ([0, 0.25, 0.5, 0.75, 1] as $f) {
                $i = (int) round(($n - 1) * $f);
                $xl[] = ['x' => round(44 + 590 * $f), 'l' => date(count($s) > 0 && strtotime(end($s)['t']) - strtotime($s[0]['t']) > 86400 * 2 ? 'M j' : 'H:i', strtotime($s[$i]['t']))];
            }
        }
        return ['labels' => array_map(fn($row) => sl_date($row['t'], 'M j H:i'), $s), 't' => $title, 'sub' => $sub, 'paths' => $paths, 'ticks' => $ticks, 'xl' => $xl, 'unit' => $unit];
    };
    $d['charts'] = [
        $chart('Throughput', 'Application actions per minute (cdb_activity_log)', [['apm', '#5B9BF0', 'actions/min']], ''),
        $chart('Response time', 'Web probe, API probe and database ping (ms)', [['web', '#3FD08F', 'web'], ['api', '#EBB43C', 'API'], ['db', '#E5545A', 'DB']], 'ms'),
        $chart('CPU and memory', 'Server, from /proc', [['cpu', '#8A74F0', 'CPU'], ['mem', '#5B9BF0', 'memory']], '%'),
        $chart('Database queries', 'Queries per second (MySQL Questions counter)', [['qps', '#3FD08F', 'queries/s']], ''),
        $chart('Error rate', 'Share of actions that did not succeed (%)', [['err', '#E5545A', 'errors']], '%'),
        $chart('Disk', 'Used space on the application volume', [['disk', '#EBB43C', 'disk']], '%'),
    ];
    // request log (if present)
    $d['api'] = null;
    if (sl_tableExists('cdb_api_request_log')) {
        $cols = array_column(sl_rows("SELECT COLUMN_NAME AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_api_request_log'"), 'c');
        $lat = in_array('duration_ms', $cols, true) ? 'duration_ms' : (in_array('latency_ms', $cols, true) ? 'latency_ms' : '');
        $tsCol = in_array('created_at', $cols, true) ? 'created_at' : '';
        if ($tsCol) {
            $r = sl_row("SELECT COUNT(*) AS n" . ($lat ? ", AVG($lat) AS avg, MAX($lat) AS mx" : '') . " FROM cdb_api_request_log WHERE $tsCol >= NOW() - INTERVAL 1 DAY");
            $d['api'] = ['n' => (int) $r->n, 'avg' => $lat ? (float) $r->avg : null, 'max' => $lat ? (float) $r->mx : null];
            if ($lat) {
                $d['api']['slow'] = sl_rows("SELECT " . (in_array('endpoint', $cols, true) ? 'endpoint' : (in_array('path', $cols, true) ? 'path' : "'?'")) . " AS e, COUNT(*) AS n, AVG($lat) AS a, MAX($lat) AS m FROM cdb_api_request_log WHERE $tsCol >= NOW() - INTERVAL 1 DAY GROUP BY e ORDER BY a DESC LIMIT 6");
            }
        }
    }
    $d['tables'] = array_map(fn($t) => ['t' => $t->t, 'n' => sl_num($t->n), 'size' => sl_devBytes((float) $t->d + (float) $t->i), 'data' => sl_devBytes($t->d), 'idx' => sl_devBytes($t->i), 'w' => 0, 'b' => (float) $t->d + (float) $t->i], sl_devTableSizes(12));
    $mx = $d['tables'] ? max(array_column($d['tables'], 'b')) : 1;
    foreach ($d['tables'] as &$t) $t['w'] = sl_pct($t['b'], $mx);
    unset($t);
    $d['slow'] = array_map(fn($r) => ['t' => $r->t, 'n' => sl_num($r->n), 'idx' => (int) $r->idx], sl_devSlowCandidates());
    $d['mysql'] = sl_devMysql();
    $d['dbSize'] = sl_devDbSize();
    return $d;
}

/* ------------------------------------------------------- infrastructure */

function sl_devInfraData($user, $core): array
{
    $d = sl_devCommon($user);
    $srv = $d['snap']['server'];
    $my = sl_devMysql();
    $disk = sl_devDisk();
    $s = cdp_devSeries('24h');
    $sp = fn($k) => sl_devSpark(array_column($s, $k), 200, 40);
    $d['kpis'] = [
        ['icon' => 'i-cpu', 'label' => 'CPU', 'v' => isset($srv['cpu_pct']) ? (string) $srv['cpu_pct'] : '—', 'unit' => isset($srv['cpu_pct']) && $srv['cpu_pct'] !== null ? '%' : '', 'd' => ($srv['cores'] ?? '?') . ' cores · load ' . ($srv['load'] ? implode(' / ', $srv['load']) : '—'), 'dc' => '', 'sp' => $sp('cpu')],
        ['icon' => 'i-server', 'label' => 'Memory', 'v' => isset($srv['mem_pct']) && $srv['mem_pct'] !== null ? (string) $srv['mem_pct'] : '—', 'unit' => isset($srv['mem_pct']) && $srv['mem_pct'] !== null ? '%' : '', 'd' => !empty($srv['mem_total']) ? sl_devBytes($srv['mem_used']) . ' of ' . sl_devBytes($srv['mem_total']) : 'Not readable', 'dc' => '', 'sp' => $sp('mem')],
        ['icon' => 'i-folder', 'label' => 'Disk', 'v' => (string) $disk['pct'], 'unit' => '%', 'd' => sl_devBytes($disk['free']) . ' free', 'dc' => $disk['pct'] >= 85 ? 'dv-warn' : '', 'sp' => $sp('disk')],
        ['icon' => 'i-database', 'label' => 'Database', 'v' => $my['ok'] ? (string) $my['ms'] : '—', 'unit' => $my['ok'] ? 'ms' : '', 'd' => $my['threads'] . ' / ' . $my['max_conn'] . ' connections', 'dc' => $my['ok'] ? '' : 'dv-bad', 'sp' => $sp('db')],
        ['icon' => 'i-clock', 'label' => 'Server uptime', 'v' => isset($srv['uptime_s']) && $srv['uptime_s'] ? (string) floor($srv['uptime_s'] / 86400) : '—', 'unit' => isset($srv['uptime_s']) && $srv['uptime_s'] ? 'days' : '', 'd' => 'MySQL up ' . floor($my['uptime'] / 86400) . ' d', 'dc' => '', 'sp' => ['line' => '', 'area' => '']],
    ];
    $d['facts'] = [
        ['k' => 'Host', 'v' => (string) ($srv['host'] ?? gethostname())],
        ['k' => 'OS', 'v' => trim(($srv['os'] ?? PHP_OS_FAMILY) . ' ' . ($srv['arch'] ?? ''))],
        ['k' => 'Web server', 'v' => (string) ($srv['software'] ?? ($_SERVER['SERVER_SOFTWARE'] ?? PHP_SAPI))],
        ['k' => 'PHP', 'v' => PHP_VERSION . ' · ' . PHP_SAPI],
        ['k' => 'memory_limit', 'v' => (string) ini_get('memory_limit')],
        ['k' => 'max_execution_time', 'v' => ini_get('max_execution_time') . ' s'],
        ['k' => 'upload_max_filesize', 'v' => (string) ini_get('upload_max_filesize') . ' · post_max_size ' . ini_get('post_max_size')],
        ['k' => 'OPcache', 'v' => function_exists('opcache_get_status') && @opcache_get_status(false) ? 'on' : 'off'],
        ['k' => 'Time zone', 'v' => date_default_timezone_get() . ' · DB ' . (string) sl_scalar('SELECT @@session.time_zone', [], '')],
        ['k' => 'MySQL', 'v' => $my['version'] . ' · slow log ' . ($my['slow_log'] ?? '?') . ' · long_query_time ' . ($my['long_query'] ?? '?') . ' s'],
        ['k' => 'App root', 'v' => CDP_ROOT],
        ['k' => 'Error log', 'v' => sl_devErrorLogPath() ?: 'not set'],
        ['k' => 'App version', 'v' => sl_devVersion() ?: '—'],
    ];
    $want = ['curl', 'pdo_mysql', 'mbstring', 'gd', 'openssl', 'intl', 'zip', 'fileinfo', 'json', 'xml', 'sodium', 'redis'];
    $d['ext'] = array_map(fn($e) => ['n' => $e, 'ok' => extension_loaded($e)], $want);
    $d['config'] = sl_devConfig();
    $paths = [];
    foreach (['uploads', 'backups', 'support_files', 'driver_files', 'dev_reports', 'cron', 'logs'] as $p) {
        $full = CDP_ROOT . '/' . $p;
        $paths[] = ['p' => $p . '/', 'exists' => is_dir($full), 'w' => is_dir($full) && is_writable($full)];
    }
    $d['paths'] = $paths;
    $d['crons'] = array_map(function ($j) {
        $l = $j['last'];
        $state = !$l ? 'Never recorded' : ($l->status === 'failed' ? 'Failed' : ($l->status === 'running' ? 'Running' : 'OK'));
        return ['name' => $j['file'], 'what' => $j['what'] ?: '—', 'state' => $state, 'dot' => !$l ? 'y' : ($l->status === 'failed' ? 'r' : ''), 'sc' => !$l ? 'dv-warn' : ($l->status === 'failed' ? 'dv-bad' : 'dv-ok'), 'next' => $l ? sl_ago($l->started_at) : ($j['schedule'] ?: '—')];
    }, sl_devCronJobs());
    $q = sl_devQueue();
    $d['queue'] = $q;
    $d['backup'] = sl_devBackup();
    $d['db'] = sl_devDbSize();
    $d['mysql'] = $my;
    $d['disk'] = $disk;
    $d['https'] = sl_devHttps();
    $d['events'] = sl_tableExists('cdb_dev_changelog') ? array_map(fn($e) => ['t' => $e->title, 'who' => $e->version . ' · ' . ($e->author ?: '') . ' · ' . $e->environment, 'at' => sl_date($e->released_at, 'M j H:i'), 'icon' => 'i-rocket'], sl_rows('SELECT * FROM cdb_dev_changelog ORDER BY released_at DESC LIMIT 6')) : [];
    return $d;
}

/* ---------------------------------------------------------- deployments */

function sl_devDeploymentsData($user, $core): array
{
    $d = sl_devCommon($user);
    $dep = cdp_devDeployments($d['snap']['services']);
    $git = sl_devGit(30);
    $d['dep'] = $dep;
    $d['git'] = $git;
    $d['version'] = sl_devVersion();
    $st = $dep['stats'];
    $d['kpis'] = [
        ['label' => 'Deploys (30 d)', 'v' => (string) $st['total_30d'], 'unit' => '', 'd' => 'From the git reflog', 'dc' => ''],
        ['label' => 'Rollbacks (30 d)', 'v' => (string) $st['rollbacks_30d'], 'unit' => '', 'd' => 'git reset entries', 'dc' => $st['rollbacks_30d'] ? 'dv-warn' : ''],
        ['label' => 'Success rate', 'v' => $st['success_rate'] !== null ? (string) $st['success_rate'] : '—', 'unit' => $st['success_rate'] !== null ? '%' : '', 'd' => 'Deploys not rolled back', 'dc' => ''],
        ['label' => 'Last deploy', 'v' => $st['last_at'] ? sl_ago($st['last_at']) : '—', 'unit' => '', 'd' => $git['branch'] ? $git['branch'] . ' @ ' . $git['head'] : ($git['source'] ?: ''), 'dc' => ''],
    ];
    $icons = ['Code' => 'i-code', 'Tests' => 'i-check', 'Dependencies' => 'i-box', 'Deploy' => 'i-rocket', 'Health check' => 'i-activity'];
    $d['steps'] = array_map(fn($p) => ['label' => $p['name'], 'sub' => $p['detail'], 'icon' => $icons[$p['name']] ?? 'i-check', 'cls' => $p['state'] === 'passed' ? '' : (in_array($p['state'], ['failed', 'warning'], true) ? 'run' : 'todo'), 'state' => $p['state']], $dep['pipeline']);
    $d['deps'] = array_map(fn($h) => ['svc' => 'app', 'ver' => $h['hash'], 'sha' => $h['from'] ?? '', 'msg' => $h['message'], 'dur' => $h['kind'], 'by' => $h['author'], 'ini' => sl_initials((string) $h['author']), 'env' => 'this checkout', 'status' => $h['kind'] === 'reset' ? 'Rollback' : 'Deployed', 'pc' => $h['kind'] === 'reset' ? 'pending' : 'delivered', 'dc' => $h['kind'] === 'reset' ? 'y' : '', 'when' => sl_ago($h['ts'])], array_slice($dep['history'], 0, 25));
    $d['commits'] = $git['commits'];
    // deploy frequency, 14 days
    $days = [];
    for ($i = 13; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i days"))] = 0;
    foreach ($dep['history'] as $h) {
        $k = date('Y-m-d', strtotime($h['ts']));
        if (isset($days[$k])) $days[$k]++;
    }
    $mx = max(1, max($days));
    $d['freq'] = array_map(fn($k, $v) => ['h' => max(4, sl_pct($v, $mx)), 'n' => $v, 'l' => date('M j', strtotime($k))], array_keys($days), $days);
    $d['rollbacks'] = array_values(array_filter($dep['history'], fn($h) => $h['kind'] === 'reset'));
    // change log
    $d['changelogReady'] = sl_tableExists('cdb_dev_changelog');
    $kind = (string) ($_GET['kind'] ?? '');
    $d['kinds'] = array_map(fn($k) => ['label' => $k === '' ? 'All' : ucfirst($k), 'cls' => $kind === $k ? 'on' : '', 'href' => sl_url(['kind' => $k])], ['', 'feature', 'fix', 'change', 'security', 'migration']);
    $d['log'] = [];
    if ($d['changelogReady']) {
        $w = $kind !== '' ? 'WHERE kind = :k' : '';
        $byV = [];
        foreach (sl_rows("SELECT * FROM cdb_dev_changelog $w ORDER BY released_at DESC, id DESC LIMIT 200", $kind !== '' ? [':k' => $kind] : []) as $c) {
            $byV[$c->version][] = $c;
        }
        foreach ($byV as $v => $items) {
            $d['log'][] = ['ver' => $v, 'env' => $items[0]->environment, 'date' => sl_date($items[0]->released_at), 'by' => $items[0]->author ?: '—', 'n' => count($items), 'items' => array_map(fn($c) => ['type' => $c->kind, 'cls' => ['feature' => 'add', 'fix' => 'fix', 'security' => 'sec', 'change' => 'chg', 'migration' => 'chg'][$c->kind] ?? 'chg', 'text' => $c->title, 'ref' => $c->ref ?: ''], $items)];
        }
    }
    $d['changeCount'] = $d['changelogReady'] ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_dev_changelog') : 0;
    $d['migrations'] = sl_tableExists('cdb_schema_migrations') ? sl_rows('SELECT name, applied_at FROM cdb_schema_migrations ORDER BY applied_at DESC LIMIT 10') : [];
    return $d;
}

/* --------------------------------------------------------------- alerts */

function sl_devAlertsData($user, $core): array
{
    $d = sl_devCommon($user);
    $tab = in_array($_GET['tab'] ?? 'open', ['open', 'resolved', 'all'], true) ? (string) ($_GET['tab'] ?? 'open') : 'open';
    $all = cdp_devAlerts('all', 300);
    $open = array_filter($all, fn($a) => in_array($a['status'], ['active', 'acknowledged'], true));
    $crit = count(array_filter($open, fn($a) => $a['severity'] === 'critical'));
    $res24 = count(array_filter($all, fn($a) => $a['status'] === 'resolved' && $a['resolved_at'] && strtotime($a['resolved_at']) >= time() - 86400));
    $d['openN'] = count($open);
    $d['critN'] = $crit;
    $d['warnN'] = count($open) - $crit;
    $d['tabs'] = array_map(fn($t) => ['label' => $t[1], 'cls' => $tab === $t[0] ? 'on' : '', 'href' => sl_url(['tab' => $t[0]])], [['open', 'Open ' . count($open)], ['resolved', 'Resolved'], ['all', 'All']]);
    $list = $tab === 'open' ? $open : ($tab === 'resolved' ? array_filter($all, fn($a) => $a['status'] === 'resolved') : $all);
    $d['alerts'] = [];
    foreach (array_slice(array_values($list), 0, 60) as $a) {
        $isCrit = $a['severity'] === 'critical';
        $ctx = $a['context'];
        $val = isset($ctx['value']) ? $ctx['value'] : null;
        $lim = isset($ctx['threshold']) ? (float) $ctx['threshold'] : null;
        $d['alerts'][] = ['id' => $a['id'], 't' => $a['title'], 'sub' => $a['service'] . ' · ' . ($a['detail'] ?? ''), 'sc' => $isCrit ? 'c' : 'w', 'icon' => $isCrit ? 'i-alert' : 'i-bell', 'val' => $val !== null ? rtrim(rtrim(number_format((float) $val, 2, '.', ''), '0'), '.') : '', 'limit' => $lim !== null ? rtrim(rtrim(number_format($lim, 2, '.', ''), '0'), '.') : '', 'bar' => $val !== null && $lim ? 'width:' . min(100, sl_pct($val, $lim * 1.5)) . '%' : 'width:0', 'lim' => $lim ? 'left:66%' : 'display:none', 'for' => cdp_devDur($a['duration_s']), 'tc' => $isCrit ? 'dv-bad' : 'dv-warn', 'canAck' => $a['status'] === 'active', 'acked' => $a['status'] === 'acknowledged', 'resolved' => $a['status'] === 'resolved', 'by' => $a['status'] === 'acknowledged' ? 'Acknowledged ' . sl_ago($a['ack_at']) : ''];
    }
    $d['none'] = !$d['alerts'];
    $d['kpis'] = [
        ['label' => 'Open', 'v' => (string) count($open), 'unit' => '', 'd' => $crit . ' critical', 'dc' => $crit ? 'dv-bad' : ''],
        ['label' => 'Warnings', 'v' => (string) (count($open) - $crit), 'unit' => '', 'd' => 'Acknowledge to take ownership', 'dc' => ''],
        ['label' => 'Resolved in 24 h', 'v' => (string) $res24, 'unit' => '', 'd' => 'Closed automatically or by hand', 'dc' => 'dv-ok'],
        ['label' => 'Rules', 'v' => sl_tableExists('cdb_dev_alert_rules') ? (string) (int) sl_scalar('SELECT COUNT(*) FROM cdb_dev_alert_rules WHERE enabled = 1') : '0', 'unit' => '', 'd' => 'Plus the built-in service checks', 'dc' => ''],
    ];
    // heat: alerts opened per half hour, 48 cells
    $cells = array_fill(0, 48, '');
    foreach ($all as $a) {
        $i = 47 - (int) floor((time() - strtotime($a['first_seen'])) / 1800);
        if ($i >= 0 && $i < 48) $cells[$i] = $a['severity'] === 'critical' ? 'c' : ($cells[$i] === 'c' ? 'c' : 'w');
    }
    $d['heat'] = $cells;
    // rules with live values
    $d['rules'] = [];
    $d['mons'] = [];
    if (sl_tableExists('cdb_dev_alert_rules')) {
        foreach (sl_rows('SELECT * FROM cdb_dev_alert_rules ORDER BY id') as $r) {
            $v = sl_devMetric($r->metric, (int) $r->window_min);
            $fires = sl_devCompare($v, $r->comparator, (float) $r->threshold);
            $d['rules'][] = ['id' => (int) $r->id, 'label' => $r->label, 'metric' => $r->metric, 'cmp' => $r->comparator, 'th' => rtrim(rtrim(number_format((float) $r->threshold, 2, '.', ''), '0'), '.'), 'win' => (int) $r->window_min, 'sev' => $r->severity, 'notify' => $r->notify, 'on' => (int) $r->enabled === 1];
            $d['mons'][] = ['name' => $r->label, 'v' => $v === null ? 'n/a' : rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.'), 'dot' => !(int) $r->enabled ? 'y' : ($fires ? 'r' : ''), 'rule' => $r->metric . ' ' . $r->comparator . ' ' . rtrim(rtrim(number_format((float) $r->threshold, 2, '.', ''), '0'), '.') . ($r->window_min ? ' over ' . $r->window_min . ' min' : '') . ((int) $r->enabled ? '' : ' · off')];
        }
    }
    $d['metrics'] = ['disk_pct' => 'Disk used %', 'failed_cron' => 'Failed cron runs', 'cron_stale_min' => 'Minutes since last cron run', 'failed_logins' => 'Failed sign-ins', 'queue_backlog' => 'Outbox waiting', 'php_errors' => 'PHP errors in log', 'otp_failures' => 'Failed one-time codes'];
    $d['lastEval'] = sl_tableExists('cdb_cron_runs') ? (string) sl_scalar("SELECT MAX(started_at) FROM cdb_cron_runs WHERE job = 'dev_alerts'", [], '') : '';
    return $d;
}

/* --------------------------------------------------------------- issues */

function sl_devIssuesData($user, $core): array
{
    $d = sl_devCommon($user);
    $d['ready'] = sl_tableExists('cdb_dev_issues');
    $tab = in_array($_GET['tab'] ?? 'issues', ['issues', 'site', 'log'], true) ? (string) ($_GET['tab'] ?? 'issues') : 'issues';
    $d['tIssues'] = $tab === 'issues';
    $d['tSite'] = $tab === 'site';
    $d['tLog'] = $tab === 'log';
    $open = $d['ready'] ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_dev_issues WHERE status <> 'done'") : 0;
    $clN = sl_tableExists('cdb_dev_changelog') ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_dev_changelog') : 0;
    $d['tabs'] = array_map(fn($t) => ['label' => $t[1], 'icon' => $t[2], 'n' => $t[3], 'cls' => $tab === $t[0] ? 'on' : '', 'href' => sl_url(['tab' => $t[0]])], [['issues', 'Tickets', 'i-bug', $open], ['site', 'Pages and services', 'i-activity', count($d['snap']['services'])], ['log', 'Change log', 'i-rocket', $clN]]);
    $d['staff'] = array_map(fn($r) => ['id' => (int) $r->id, 'label' => trim($r->fname . ' ' . $r->lname)], sl_rows('SELECT id, fname, lname FROM cdb_users WHERE userlevel IN (9,2) AND active = 1 ORDER BY fname'));
    $d['kpis'] = [];
    $d['cols'] = [];
    $d['list'] = [];
    $d['d'] = null;
    if ($d['ready']) {
        $k = sl_row("SELECT SUM(status <> 'done') AS o, SUM(status <> 'done' AND priority = 'p1') AS p1, SUM(status = 'in_progress') AS ip, SUM(status = 'done' AND closed_at >= NOW() - INTERVAL 7 DAY) AS d7, AVG(CASE WHEN status = 'done' THEN TIMESTAMPDIFF(HOUR, created_at, closed_at) END) AS fix FROM cdb_dev_issues");
        $d['kpis'] = [['l' => 'Open tickets', 'v' => (int) $k->o, 's' => (int) $k->p1 . ' priority 1'], ['l' => 'In progress', 'v' => (int) $k->ip, 's' => 'Being worked on'], ['l' => 'Closed in 7 days', 'v' => (int) $k->d7, 's' => 'Moved to done'], ['l' => 'Average time to fix', 'v' => $k->fix !== null ? number_format((float) $k->fix / 24, 1) . ' d' : '—', 's' => 'Open to done'], ['l' => 'PHP errors (1 h)', 'v' => sl_devErrorLogPath() !== '' ? sl_devPhpErrorCount(60) : '—', 's' => sl_devErrorLogPath() !== '' ? 'From the error log' : 'CDP_ERROR_LOG not set']];
        $q = trim((string) ($_GET['q'] ?? ''));
        $pri = in_array($_GET['pri'] ?? '', ['', 'p1', 'p2', 'p3'], true) ? (string) ($_GET['pri'] ?? '') : '';
        $view = ($_GET['view'] ?? 'board') === 'list' ? 'list' : 'board';
        $d['q'] = $q;
        $d['vBoard'] = $view === 'board';
        $d['vList'] = $view === 'list';
        $d['views'] = array_map(fn($v) => ['label' => ucfirst($v), 'cls' => $view === $v ? 'on' : '', 'href' => sl_url(['view' => $v])], ['board', 'list']);
        $d['pris'] = array_map(fn($p) => ['label' => $p === '' ? 'Any priority' : strtoupper($p), 'cls' => $pri === $p ? 'on' : '', 'href' => sl_url(['pri' => $p])], ['', 'p1', 'p2', 'p3']);
        $w = '1';
        $b = [];
        if ($q !== '') { $w .= ' AND (i.title LIKE :q OR i.ref LIKE :q2 OR i.area LIKE :q3 OR i.page LIKE :q4)'; $b = [':q' => "%$q%", ':q2' => "%$q%", ':q3' => "%$q%", ':q4' => "%$q%"]; }
        if ($pri !== '') { $w .= ' AND i.priority = :p'; $b[':p'] = $pri; }
        $rows = sl_rows("SELECT i.*, CONCAT(a.fname,' ',a.lname) AS who, CONCAT(r.fname,' ',r.lname) AS reporter FROM cdb_dev_issues i LEFT JOIN cdb_users a ON a.id = i.assignee_id LEFT JOIN cdb_users r ON r.id = i.reporter_id WHERE $w ORDER BY FIELD(i.priority,'p1','p2','p3'), i.created_at DESC LIMIT 200", $b);
        $sel = (int) ($_GET['id'] ?? 0);
        $cur = null;
        $stLabels = ['open' => 'Open', 'in_progress' => 'In progress', 'review' => 'In review', 'done' => 'Done'];
        $cols = [];
        foreach ($stLabels as $sk => $sl) $cols[$sk] = ['label' => $sl, 'n' => 0, 'over' => '', 'items' => []];
        foreach ($rows as $i) {
            $isSel = $sel ? $sel === (int) $i->id : $cur === null;
            if ($isSel) $cur = $i;
            $it = ['id' => $i->ref, 'rid' => (int) $i->id, 'pri' => strtoupper($i->priority), 'pc' => $i->priority, 'type' => ucfirst($i->type), 't' => $i->title, 'area' => $i->area ?: '—', 'age' => sl_ago($i->created_at), 'who' => trim((string) $i->who) ?: 'Unassigned', 'ini' => sl_initials(trim((string) $i->who) ?: '?'), 'st' => $stLabels[$i->status] ?? $i->status, 'cls' => $isSel ? 'on' : '', 'href' => sl_url(['id' => (int) $i->id])];
            $cols[$i->status]['items'][] = $it;
            $cols[$i->status]['n']++;
            $d['list'][] = $it;
        }
        $d['cols'] = array_values($cols);
        if ($cur) {
            $events = sl_rows('SELECT * FROM cdb_dev_issue_events WHERE issue_id = :i ORDER BY id DESC LIMIT 50', [':i' => (int) $cur->id]);
            $order = array_keys($stLabels);
            $idx = array_search($cur->status, $order, true);
            $moves = [];
            foreach ($order as $j => $sk) {
                if ($sk === $cur->status) continue;
                $moves[] = ['label' => $j > $idx ? 'Move to ' . $stLabels[$sk] : 'Back to ' . $stLabels[$sk], 'status' => $sk, 'cls' => $j === $idx + 1 ? '' : 'ghost'];
            }
            $d['moves'] = $moves;
            $d['d'] = ['id' => $cur->ref, 'rid' => (int) $cur->id, 'pri' => strtoupper($cur->priority), 'pc' => $cur->priority, 'type' => ucfirst($cur->type), 'st' => $stLabels[$cur->status] ?? $cur->status, 'status' => $cur->status, 't' => $cur->title, 'area' => trim(($cur->area ?: '—') . ($cur->page ? ' · ' . $cur->page : '')), 'by' => trim((string) $cur->reporter) ?: '—', 'who' => trim((string) $cur->who) ?: 'Unassigned', 'whoId' => (int) $cur->assignee_id, 'ini' => sl_initials(trim((string) $cur->who) ?: '?'), 'age' => sl_date($cur->created_at, 'M j, Y H:i') . ' · ' . sl_ago($cur->created_at), 'rel' => $cur->fix_version ?: '—', 'steps' => (string) $cur->steps, 'log' => (string) $cur->log_excerpt, 'cms' => array_map(fn($e) => ['who' => $e->user_name ?: 'System', 'ini' => sl_initials($e->user_name ?: 'S'), 'at' => sl_ago($e->created_at), 't' => $e->body, 'kind' => $e->kind], $events)];
        }
    }
    $d['newOpen'] = !empty($_GET['new']);
    $d['prefill'] = ['title' => mb_substr((string) ($_GET['title'] ?? ''), 0, 190), 'log' => mb_substr((string) ($_GET['log'] ?? ''), 0, 2000), 'page' => mb_substr((string) ($_GET['page'] ?? ''), 0, 120)];
    // site tab: services and pages health from the live snapshot + error counts per page
    $d['pages'] = [];
    foreach ($d['snap']['services'] as $s) {
        $st = sl_devStatusCls($s['status']);
        $d['pages'][] = ['n' => $s['name'], 'dot' => $s['status'] === 'operational' ? '' : ($s['status'] === 'down' ? 'r' : 'y'), 'ms' => $s['latency_ms'] !== null ? sl_devMs($s['latency_ms']) : '', 'url' => $s['tech'], 'up' => $st[0], 'err' => $s['detail']];
    }
    $d['errPages'] = sl_tableExists('cdb_activity_log') ? sl_rows("SELECT module, COUNT(*) AS n FROM cdb_activity_log WHERE outcome = 'failure' AND created_at >= NOW() - INTERVAL 7 DAY GROUP BY module ORDER BY n DESC LIMIT 8") : [];
    // change log tab
    $d['vers'] = [];
    $d['clReady'] = sl_tableExists('cdb_dev_changelog');
    if ($d['clReady']) {
        $byV = [];
        foreach (sl_rows('SELECT * FROM cdb_dev_changelog ORDER BY released_at DESC, id DESC LIMIT 200') as $c) $byV[$c->version][] = $c;
        foreach ($byV as $v => $items) {
            $d['vers'][] = ['v' => $v, 'd' => sl_date($items[0]->released_at), 'tag' => $items[0]->environment, 'by' => $items[0]->author ?: '—', 'title' => count($items) . ' changes', 'items' => array_map(fn($c) => ['k' => $c->kind, 'kl' => ucfirst($c->kind), 't' => $c->title, 'ref' => $c->ref ?: ''], $items)];
        }
    }
    return $d;
}

/* --------------------------------------------------------- integrations */

function sl_devIntegrationsData($user, $core): array
{
    $d = sl_devCommon($user);
    $list = sl_devIntegrations();
    $grp = (string) ($_GET['group'] ?? 'all');
    $groups = array_values(array_unique(array_column($list, 'group')));
    $d['groups'] = array_merge([['label' => 'All ' . count($list), 'cls' => $grp === 'all' ? 'on' : '', 'href' => sl_url(['group' => ''])]], array_map(fn($g) => ['label' => $g, 'cls' => $grp === $g ? 'on' : '', 'href' => sl_url(['group' => $g])], $groups));
    $d['rows'] = array_values(array_filter($list, fn($i) => $grp === 'all' || $grp === '' || $i['group'] === $grp));
    $d['on'] = count(array_filter($list, fn($i) => $i['configured']));
    $d['total'] = count($list);
    $d['channels'] = $d['snap']['channels'];
    $d['webhooks'] = sl_tableExists('cdb_package_status_webhooks') ? sl_rows('SELECT id, created_at, LEFT(payload, 160) AS p FROM cdb_package_status_webhooks ORDER BY id DESC LIMIT 5') : [];
    $d['tokens'] = sl_tableExists('cdb_api_tokens') ? sl_rows("SELECT t.name, t.created_at, t.last_used_at, t.expires_at, CONCAT(u.fname,' ',u.lname) AS who FROM cdb_api_tokens t LEFT JOIN cdb_users u ON u.id = t.user_id WHERE t.revoked_at IS NULL AND t.expires_at > NOW() ORDER BY t.id DESC LIMIT 6") : [];
    $d['cors'] = sl_tableExists('cdb_cors_origins') ? sl_rows('SELECT origin, active FROM cdb_cors_origins ORDER BY id LIMIT 10') : [];
    return $d;
}
