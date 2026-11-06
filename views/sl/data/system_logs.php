<?php
/**
 * Data for System Logs (SystemLogs.dc.html): PHP error log tail, activity-log
 * failures and sign-ins, cron scripts. Read only.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/sl_charts.php';
require_once CDP_ROOT . '/helpers/activity_log.php';

/** The PHP error log this installation writes to, if readable. */
function sl_sysLogFile(): string
{
    $cands = [];
    if (defined('CDP_ERROR_LOG') && CDP_ERROR_LOG) $cands[] = CDP_ERROR_LOG;
    $ini = (string) ini_get('error_log');
    if ($ini !== '' && $ini !== 'syslog') $cands[] = $ini;
    $cands[] = CDP_ROOT . '/logs/php-error.log';
    foreach ($cands as $c) {
        if (is_file($c) && is_readable($c)) return $c;
    }
    return '';
}

/** Last $max lines of a file without reading it whole. */
function sl_sysTail(string $file, int $max = 400): array
{
    $size = filesize($file);
    if (!$size) return [];
    $fh = fopen($file, 'rb');
    $chunk = min($size, 256 * 1024);
    fseek($fh, -$chunk, SEEK_END);
    $buf = fread($fh, $chunk);
    fclose($fh);
    $lines = preg_split('/\r?\n/', trim($buf));
    return array_slice($lines, -$max);
}

/** Parse one PHP error-log (or php -S) line into an event or null. */
function sl_sysParse(string $line): ?array
{
    if (!preg_match('/^\[(\d{2}-\w{3}-\d{4} \d{2}:\d{2}:\d{2})[^\]]*\]\s*(.*)$/', $line, $m) && !preg_match('/^\[(\w{3} \w{3}\s+\d+ \d{2}:\d{2}:\d{2} \d{4})\]\s*(.*)$/', $line, $m)) {
        return null;
    }
    $ts = strtotime($m[1]);
    $msg = $m[2];
    if (!$ts) return null;
    $lv = 'info';
    $src = 'php';
    if (preg_match('/^PHP (Fatal error|Parse error|Error|Warning|Notice|Deprecated)[^:]*:\s*(.*?)(?: in (\S+) on line (\d+))?$/', $msg, $e)) {
        $lv = in_array($e[1], ['Fatal error', 'Parse error', 'Error'], true) ? 'err' : 'warn';
        $msg = $e[2];
        if (!empty($e[3])) $src = str_replace(CDP_ROOT . '/', '', $e[3]) . ':' . $e[4];
    } elseif (preg_match('/^\S+ \[(\d{3})\]: (\w+) (\S+)/', $msg, $w)) {
        $code = (int) $w[1];
        $lv = $code >= 500 ? 'err' : ($code >= 400 ? 'warn' : 'info');
        $src = 'web server';
        $msg = $w[2] . ' ' . $w[3] . ' → ' . $code;
    } elseif (preg_match('/^RBAC_DENY/', $msg)) {
        $lv = 'warn'; $src = 'rbac';
    } elseif (stripos($msg, 'Accepted') !== false || stripos($msg, 'Closing') !== false) {
        return null;
    }
    return ['ts' => $ts, 'lv' => $lv, 'src' => $src, 'msg' => $msg];
}

function sl_systemLogsData($user, $core): array
{
    $ranges = ['1h' => 3600, '24h' => 86400, '7d' => 7 * 86400, '30d' => 30 * 86400];
    $range = (string) ($_GET['range'] ?? '24h');
    if (!isset($ranges[$range])) $range = '24h';
    $since = time() - $ranges[$range];
    $sinceSql = date('Y-m-d H:i:s', $since);
    $q = trim((string) ($_GET['q'] ?? ''));
    $lvl = (string) ($_GET['lvl'] ?? 'all');
    $srcF = trim((string) ($_GET['src'] ?? ''));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = 50;
    $events = [];
    // 1) PHP error log
    $file = sl_sysLogFile();
    if ($file !== '') {
        foreach (sl_sysTail($file, 2000) as $ln) {
            $e = sl_sysParse($ln);
            if ($e && $e['ts'] >= $since) $events[] = $e;
        }
    }
    // 2) Audit trail: failures, denials, sign-ins, system module
    $hasLog = cdp_activityTableReady();
    if ($hasLog) {
        foreach (sl_rows("SELECT l.created_at, l.module, l.verb, l.outcome, l.summary, l.actor_name, l.endpoint, l.ip FROM cdb_activity_log l WHERE l.created_at >= :s AND (l.outcome IN ('denied','failure') OR l.verb IN ('login','logout','impersonate') OR l.module IN ('auth','system','access')) ORDER BY l.id DESC LIMIT 1500", [':s' => $sinceSql]) as $r) {
            $lv = $r->outcome === 'failure' ? 'err' : ($r->outcome === 'denied' || ($r->verb === 'login' && $r->outcome !== 'ok') ? 'warn' : 'info');
            $events[] = ['ts' => strtotime($r->created_at), 'lv' => $lv, 'src' => $r->endpoint ?: ($r->module ?: 'app'), 'msg' => ($r->actor_name ? $r->actor_name . ' · ' : '') . ($r->summary ?: $r->verb) . ($r->ip ? ' from ' . $r->ip : '')];
        }
    }
    usort($events, fn($a, $b) => $b['ts'] <=> $a['ts']);
    $total = count($events);
    $errs = count(array_filter($events, fn($e) => $e['lv'] === 'err'));
    $warns = count(array_filter($events, fn($e) => $e['lv'] === 'warn'));
    // per hour (last 24 slots of the range, bucketed)
    $slots = 24;
    $step = max(1, (int) ($ranges[$range] / $slots));
    $buckets = array_fill(0, $slots, ['i' => 0, 'w' => 0, 'e' => 0]);
    foreach ($events as $e) {
        $k = min($slots - 1, (int) (($e['ts'] - $since) / $step));
        if ($k < 0) continue;
        $buckets[$k][$e['lv'] === 'err' ? 'e' : ($e['lv'] === 'warn' ? 'w' : 'i')]++;
    }
    $peak = 1; $peakAt = '';
    foreach ($buckets as $k => $b) { $n = $b['i'] + $b['w'] * 3 + $b['e'] * 6; if ($n > $peak) { $peak = $n; $peakAt = date($range === '1h' ? 'H:i' : ($range === '24h' ? 'H:00' : 'M j'), $since + $k * $step); } }
    $bars = [];
    foreach ($buckets as $k => $b) {
        $x = 51.8 + $k * 41.1; $y = 190.0;
        $segs = [];
        foreach ([['i', 1, '#E9E7E1'], ['w', 3, '#F2C400'], ['e', 6, '#E5484D']] as [$key, $mult, $col]) {
            $hgt = $b[$key] * $mult / $peak * 178;
            if ($hgt > 0) { $y -= $hgt; $segs[] = ['x' => round($x, 1), 'y' => round($y, 1), 'h' => round($hgt, 1), 'c' => $col]; }
        }
        $bars[] = ['segs' => $segs, 'label' => $k % 3 === 0 ? date($range === '1h' ? 'H:i' : ($range === '24h' ? 'H' : 'j M'), $since + $k * $step) : '', 'x' => round($x + 12.7, 1)];
    }
    // spark (40 slots)
    $spark = [];
    $ss = max(1, (int) ($ranges[$range] / 40));
    $sc = array_fill(0, 40, ['n' => 0, 'lv' => '']);
    foreach ($events as $e) { $k = max(0, min(39, (int) (($e['ts'] - $since) / $ss))); $sc[$k]['n']++; if ($e['lv'] === 'err') $sc[$k]['lv'] = 'e'; elseif ($e['lv'] === 'warn' && $sc[$k]['lv'] !== 'e') $sc[$k]['lv'] = 'w'; }
    $sm = max(1, max(array_column($sc, 'n')));
    foreach ($sc as $k => $s) $spark[] = ['s' => 'height:' . max(4, sl_pct($s['n'], $sm)) . '%', 'c' => $k === 39 ? 'now' : $s['lv']];
    // by source
    $bySrc = [];
    foreach ($events as $e) {
        $key = preg_replace('/:\d+$/', '', $e['src']);
        $bySrc[$key] = $bySrc[$key] ?? ['n' => 0, 'e' => 0];
        $bySrc[$key]['n']++;
        if ($e['lv'] === 'err') $bySrc[$key]['e']++;
    }
    arsort($bySrc);
    uasort($bySrc, fn($a, $b) => $b['n'] <=> $a['n']);
    $sources = [];
    foreach (array_slice($bySrc, 0, 8, true) as $k => $v) {
        $rate = $v['n'] ? $v['e'] / $v['n'] * 100 : 0;
        $sources[] = ['label' => $k, 'n' => $v['n'], 'rate' => number_format($rate, $rate < 1 ? 2 : 1) . '%', 'health' => 100 - (int) min(100, round($rate)), 'cls' => $rate >= 5 ? 'r' : ($rate >= 1 ? 'y' : ''), 'col' => $rate >= 5 ? 'var(--red-ink)' : ($rate >= 1 ? 'var(--amber-ink)' : 'var(--muted)')];
    }
    // recurring errors
    $groups = [];
    foreach ($events as $e) {
        if ($e['lv'] === 'info') continue;
        $key = $e['src'] . '|' . preg_replace('/\d+/', '#', mb_substr($e['msg'], 0, 80));
        if (!isset($groups[$key])) $groups[$key] = ['msg' => mb_substr($e['msg'], 0, 90), 'src' => $e['src'], 'lv' => $e['lv'], 'n' => 0, 'first' => $e['ts'], 'last' => $e['ts'], 'days' => array_fill(0, 7, 0)];
        $g = &$groups[$key];
        $g['n']++; $g['first'] = min($g['first'], $e['ts']); $g['last'] = max($g['last'], $e['ts']);
        $dIdx = 6 - (int) floor((time() - $e['ts']) / 86400);
        if ($dIdx >= 0 && $dIdx <= 6) $g['days'][$dIdx]++;
        unset($g);
    }
    uasort($groups, fn($a, $b) => $b['last'] <=> $a['last']);
    $recurring = [];
    foreach (array_slice($groups, 0, 8) as $g) {
        $dm = max(1, max($g['days']));
        $recurring[] = ['msg' => $g['msg'], 'src' => $g['src'], 'n' => $g['n'], 'first' => sl_ago($g['first']), 'last' => sl_ago($g['last']), 'status' => $g['first'] >= time() - 86400 ? ['New', 'bad'] : ['Known', $g['lv'] === 'err' ? 'warn' : ''], 'bars' => array_map(fn($v) => max(2, (int) round($v / $dm * 22)), $g['days'])];
    }
    // heatmap: errors/warnings by weekday x hour, last 7 days
    $hm = array_fill(0, 7, array_fill(0, 24, 0));
    $hmPeak = 1;
    $weekEvents = $events;
    if ($ranges[$range] < 7 * 86400 && $file !== '') { // widen for the heat map
        foreach (sl_sysTail($file, 4000) as $ln) { $e = sl_sysParse($ln); if ($e && $e['ts'] >= time() - 7 * 86400 && $e['lv'] !== 'info') $weekEvents[] = $e; }
    }
    foreach ($weekEvents as $e) { if ($e['lv'] === 'info' || $e['ts'] < time() - 7 * 86400) continue; $w = ((int) date('N', $e['ts'])) - 1; $h = (int) date('G', $e['ts']); $hm[$w][$h]++; $hmPeak = max($hmPeak, $hm[$w][$h]); }
    $heat = [];
    foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $i => $dn) {
        $cells = [];
        foreach ($hm[$i] as $h => $n) $cells[] = ['cls' => sl_heatLevel($n, $hmPeak), 'tip' => "$dn $h:00 · $n"];
        $heat[] = ['label' => $dn, 'cells' => $cells];
    }
    // security
    $sec = ['fails' => 0, 'otp' => 0, 'viewas' => 0, 'perm' => 0, 'keys' => 0, 'blocked' => 0];
    if ($hasLog) {
        $sec['fails'] = (int) sl_scalar("SELECT COUNT(*) FROM cdb_activity_log WHERE verb = 'login' AND outcome <> 'ok' AND created_at >= :s", [':s' => $sinceSql]);
        $sec['viewas'] = (int) sl_scalar("SELECT COUNT(*) FROM cdb_activity_log WHERE verb = 'impersonate' AND created_at >= :s", [':s' => $sinceSql]);
        $sec['perm'] = (int) sl_scalar("SELECT COUNT(*) FROM cdb_activity_log WHERE module = 'access' AND verb <> 'view' AND created_at >= :s", [':s' => $sinceSql]);
        $sec['blocked'] = (int) sl_scalar("SELECT COUNT(DISTINCT ip) FROM cdb_activity_log WHERE outcome = 'denied' AND created_at >= :s", [':s' => $sinceSql]);
    }
    if (sl_tableExists('cdb_auth_otp_challenges')) $sec['otp'] = (int) sl_scalar("SELECT COUNT(*) FROM cdb_auth_otp_challenges WHERE created_at >= :s", [':s' => $sinceSql]);
    if (sl_tableExists('cdb_api_tokens')) $sec['keys'] = (int) sl_scalar("SELECT COUNT(*) FROM cdb_api_tokens WHERE revoked_at >= :s", [':s' => $sinceSql]);
    // cron jobs
    $cron = [];
    foreach (glob(CDP_ROOT . '/cron/*.php') ?: [] as $cf) {
        $name = basename($cf);
        $head = (string) file_get_contents($cf, false, null, 0, 2000);
        $sched = preg_match('/every minute|every (\d+ )?min|hourly|every hour|daily/i', $head, $sm2) ? strtolower($sm2[0]) : '—';
        $last = $hasLog ? sl_row("SELECT created_at, outcome FROM cdb_activity_log WHERE endpoint LIKE :e ORDER BY id DESC", [':e' => 'cron/' . $name . '%']) : null;
        $cron[] = ['name' => $name, 'sched' => $sched, 'last' => $last ? sl_ago($last->created_at) : 'no record', 'status' => $last ? ($last->outcome === 'ok' ? ['Ran', 'ok'] : ['Failed', 'bad']) : ['Not recorded', 'warn'], 'next' => $sched === '—' ? '—' : 'per crontab'];
    }
    // backup
    $backup = ['size' => '—', 'when' => 'never', 'pct' => 0];
    foreach (glob(CDP_ROOT . '/backup/*.sql*') ?: [] as $bf) { if (!isset($bt) || filemtime($bf) > $bt) { $bt = filemtime($bf); $backup = ['size' => number_format(filesize($bf) / 1048576, 1) . ' MB', 'when' => sl_ago($bt), 'pct' => 70]; } }
    // table list (filterable, paged)
    $list = array_values(array_filter($events, function ($e) use ($q, $lvl, $srcF) {
        if ($lvl !== 'all' && $e['lv'] !== $lvl) return false;
        if ($srcF !== '' && stripos($e['src'], $srcF) !== 0) return false;
        if ($q !== '' && stripos($e['msg'] . ' ' . $e['src'], $q) === false) return false;
        return true;
    }));
    $fmt = fn($e) => ['t' => date($ranges[$range] > 86400 ? 'M j H:i' : 'H:i:s', $e['ts']), 'lv' => ['info' => 'INFO', 'warn' => 'WARN', 'err' => 'ERROR'][$e['lv']], 'cls' => $e['lv'] === 'info' ? '' : $e['lv'], 'pill' => $e['lv'] === 'err' ? 'bad' : ($e['lv'] === 'warn' ? 'warn' : ''), 'src' => $e['src'], 'msg' => $e['msg']];
    $srcOptions = array_keys($bySrc);
    sort($srcOptions);
    return [
        'range' => $range, 'ranges' => array_keys($ranges), 'file' => $file ? str_replace(CDP_ROOT . '/', '', $file) : '', 'hasLog' => $hasLog,
        'total' => $total, 'errs' => $errs, 'warns' => $warns, 'groups' => count($groups), 'health' => $total ? number_format(100 - $errs / $total * 100, 1) : '100',
        'lines' => array_map($fmt, array_slice($events, 0, 14)), 'rate' => $total ? number_format($total / max(1, $ranges[$range] / 60), 1) : '0', 'spark' => $spark,
        'bars' => $bars, 'peak' => $peak, 'peakAt' => $peakAt, 'sources' => $sources, 'recurring' => $recurring, 'heat' => $heat, 'sec' => $sec, 'cron' => $cron, 'backup' => $backup,
        'q' => $q, 'lvl' => $lvl, 'src' => $srcF, 'srcOptions' => $srcOptions, 'page' => $page, 'per' => $per, 'count' => count($list),
        'rows' => array_map($fmt, array_slice($list, ($page - 1) * $per, $per)),
        'newGroup' => $recurring ? ($recurring[0]['status'][0] === 'New' ? $recurring[0]['msg'] : 'none new') : 'none',
        'cronOk' => count(array_filter($cron, fn($c) => $c['status'][1] === 'ok')), 'cronN' => count($cron),
    ];
}
