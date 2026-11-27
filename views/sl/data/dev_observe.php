<?php
/**
 * Data for the "observe" boards of the developer console: DevOverview, DevServices, DevLogs,
 * DevMetrics (pages dev_overview.php, dev_services.php, dev_logs.php, dev_metrics.php) and the
 * live endpoint ajax/sl/dev_observe_ajax.php.
 *
 * Every figure is measured on this installation:
 *   requests   helpers/dev_metrics.php (sampled request recorder armed by loader.php)
 *   server     /proc (CPU, memory, network, disk I/O), sys_getloadavg, disk_free_space
 *   database   timed ping, SHOW GLOBAL STATUS, information_schema sizes (helpers/devops.php)
 *   jobs       cdb_cron_runs, support-worker and collector heartbeats, cdb_cs_outbox
 *   channels   cdb_message_log (e-mail, SMS, WhatsApp), cdb_cs_ai_runs (AI), payment gateway config
 *   logs       PHP error log (CDP_ERROR_LOG), cdb_activity_log, cdb_message_log, cdb_cron_runs,
 *              cdb_cs_ai_runs, cdb_dev_alerts, cdb_dev_requests
 * When something cannot be measured on this host the page says so and how to enable it. A value
 * set by a super admin (infrastructure overrides, package "infra") is shown labelled "override".
 */
require_once CDP_ROOT . '/views/sl/data/dev.php';
require_once CDP_ROOT . '/helpers/dev_metrics.php';

/* ================================================================ access */

/**
 * Page gate: login + view_developer (sl_gate, which also shows the lock screen of a locked console),
 * then the access package's one-line gate cdp_devRequireSection() (assigned super admin, unlocked
 * console, section grant; 401 / 403 / 423 handling) when it is installed, else the same test here.
 */
function sl_dvxGate($user, string $section): void
{
    sl_gate('view_developer');
    if (function_exists('cdp_devRequireSection')) {
        cdp_devRequireSection($section, false);
    }
    if (!sl_dvxAllowed($user, $section)) {
        header('Location: error403.php');
        exit;
    }
}

/** Same test without redirecting (used by the AJAX endpoint and the sub-navigation). */
function sl_dvxAllowed($user, string $section): bool
{
    if (!function_exists('cdp_devCanUse') || !cdp_devCanUse($user)) {
        return false;
    }
    if (function_exists('cdp_devSectionAllowed') && !cdp_devSectionAllowed($user, $section)) {
        return false;
    }
    return true;
}

/** Super-admin override for an infrastructure figure (package "infra"); null when none is set. */
function sl_dvxOverride(string $key)
{
    if (function_exists('cdp_devOverride')) {
        try {
            $v = cdp_devOverride($key);
            return ($v === '' || $v === false) ? null : $v;
        } catch (Throwable $e) {
            return null;
        }
    }
    return null;
}

/* ============================================================ time zones */

/**
 * Seconds to add to a time stamped by MySQL's NOW() (cron ledger) to get the application's wall
 * clock, in which the activity, message and request rows are stamped. 0 when both agree.
 * Rounded to 15 minutes so query latency does not leak in.
 */
function sl_dvxDbSkew(): int
{
    static $skew = null;
    if ($skew !== null) {
        return $skew;
    }
    $skew = 0;
    try {
        $db = (string) sl_scalar('SELECT NOW()', [], '');
        if ($db !== '') {
            $app = (new DateTime('now', cdp_reqmTz()))->format('Y-m-d H:i:s');
            $skew = (int) (round((strtotime($app) - strtotime($db)) / 900) * 900);
        }
    } catch (Throwable $e) {
        $skew = 0;
    }
    return $skew;
}

/** A MySQL NOW() stamp as application wall-clock time. */
function sl_dvxFromDb(?string $ts): ?string
{
    if ($ts === null || $ts === '' || !sl_dvxDbSkew()) {
        return $ts;
    }
    $t = strtotime($ts);
    return $t ? date('Y-m-d H:i:s', $t + sl_dvxDbSkew()) : $ts;
}

/** An application wall-clock time as a MySQL NOW() stamp (for WHERE bounds on the cron ledger). */
function sl_dvxToDb(string $ts): string
{
    $t = strtotime($ts);
    return $t && sl_dvxDbSkew() ? date('Y-m-d H:i:s', $t - sl_dvxDbSkew()) : $ts;
}

/**
 * Stamps written by PHP's date() before the application zone was applied (CLI cron, endpoints that
 * never build Core) can sit hours in the future. Bring such a stamp back by the zone offset when that
 * makes it plausible; anything still in the future is clamped to now and flagged.
 * Returns [ts, future(bool)].
 */
function sl_dvxFixTs(?string $ts): array
{
    if ($ts === null || $ts === '') {
        return [$ts, false];
    }
    $t = strtotime($ts);
    $now = time();
    if (!$t || $t <= $now + 60) {
        return [$ts, false];
    }
    $offs = array_unique(array_filter([sl_dvxDbSkew(), (new DateTime('now', cdp_reqmTz()))->getOffset()]));
    foreach ($offs as $o) {
        if ($t + $o <= $now + 60 && $t + $o > $now - 7 * 86400) {
            return [date('Y-m-d H:i:s', $t + $o), false];
        }
    }
    return [date('Y-m-d H:i:s', $now), true];
}

/** sl_ago() that never says "in the future": future stamps are corrected or clamped first. */
function sl_dvxAgo(?string $ts): string
{
    if ($ts === null || $ts === '') {
        return '';
    }
    return sl_ago((string) sl_dvxFixTs($ts)[0]);
}

/* ============================================================ formatting */

function sl_dvxNum($v, int $dec = 0): string
{
    return $v === null ? '—' : number_format((float) $v, $dec);
}

function sl_dvxMs($v): string
{
    if ($v === null) {
        return '—';
    }
    if ((float) $v < 10 && (float) $v != floor((float) $v)) {
        // sub-millisecond pings: one decimal (never "0 ms")
        return ((float) $v < 0.1 ? '<0.1' : number_format((float) $v, 1)) . ' ms';
    }
    return sl_devMs($v);
}

function sl_dvxPct($v, int $dec = 2): string
{
    return $v === null ? '—' : rtrim(rtrim(number_format((float) $v, $dec, '.', ''), '0'), '.') . '%';
}

/** Status key -> [label, css class, colour]. */
function sl_dvxSt(string $st): array
{
    return [
        'operational' => ['Healthy', 'ok', '#34D399'], 'degraded' => ['Degraded', 'warn', '#FBBF24'], 'down' => ['Down', 'bad', '#F87171'],
        'not_configured' => ['Not set up', 'off', '#64748B'], 'idle' => ['Idle', 'idle', '#60A5FA'], 'unknown' => ['Not measured', 'off', '#64748B'],
    ][$st] ?? [ucfirst($st), 'off', '#64748B'];
}

/** "▲ 12%" style change between two numbers; $goodDown = a fall is good (latency, errors). */
function sl_dvxDelta($now, $prev, bool $goodDown, string $unit = '%'): array
{
    if ($now === null || $prev === null) {
        return ['t' => 'no earlier data', 'c' => 'flat'];
    }
    if ($unit === 'pt') {
        $d = (float) $now - (float) $prev;
        if (abs($d) < 0.005) {
            return ['t' => '0 pt vs previous 24 h', 'c' => 'flat'];
        }
        $good = $goodDown ? $d < 0 : $d > 0;
        return ['t' => ($d > 0 ? '▲ ' : '▼ ') . rtrim(rtrim(number_format(abs($d), 2, '.', ''), '0'), '.') . ' pt vs previous 24 h', 'c' => $good ? 'up' : 'down'];
    }
    if ((float) $prev == 0.0) {
        return ['t' => (float) $now == 0.0 ? 'unchanged vs previous 24 h' : 'new vs previous 24 h', 'c' => 'flat'];
    }
    $d = ((float) $now - (float) $prev) / abs((float) $prev) * 100;
    if (abs($d) < 0.5) {
        return ['t' => 'unchanged vs previous 24 h', 'c' => 'flat'];
    }
    $good = $goodDown ? $d < 0 : $d > 0;
    return ['t' => ($d > 0 ? '▲ ' : '▼ ') . number_format(abs($d), abs($d) < 10 ? 1 : 0) . '% vs previous 24 h', 'c' => $good ? 'up' : 'down'];
}

/** Path through the non-null values (gaps stay gaps). */
function sl_dvxPath(array $vals, float $w, float $h, float $top, float $max, float $x0 = 0): string
{
    $n = count($vals);
    if ($n < 2 || $max <= 0) {
        return '';
    }
    $d = '';
    $pen = false;
    $i = 0;
    foreach (array_values($vals) as $i => $v) {
        if ($v === null) {
            $pen = false;
            continue;
        }
        $x = $x0 + ($w - $x0) * $i / ($n - 1);
        $y = $h - ((float) $v / $max) * ($h - $top);
        $d .= ($pen ? ' L' : ' M') . round($x, 1) . ',' . round($y, 1);
        $pen = true;
    }
    return trim($d);
}

/** Sparkline (viewBox 240x44) of a value series; null values are drawn as gaps. */
function sl_dvxSpark(array $vals, array $labels, string $name, string $unit = ''): array
{
    $nums = array_values(array_filter($vals, fn($v) => $v !== null));
    if (count($nums) < 2) {
        return ['line' => '', 'area' => '', 'attrs' => ''];
    }
    $max = max($nums) ?: 1;
    $max *= 1.15;
    $line = sl_dvxPath($vals, 240, 42, 4, $max);
    $area = '';
    $filled = array_map(fn($v) => $v === null ? 0 : $v, $vals);
    if (count(array_filter($vals, fn($v) => $v === null)) === 0) {
        $area = sl_svgAreaPath($filled, 240, 42, 4, $max);
    }
    return ['line' => $line, 'area' => $area, 'attrs' => sl_chartAttrs([['name' => $name, 'values' => array_values($vals), 'unit' => $unit]], $labels, $name)];
}

/**
 * Line chart (viewBox 640 x 200, plot from x=46 to 632, y=12 to 172).
 * $lines: [[key, colour, label], ...] over $points (each a keyed array).
 */
function sl_dvxChart(string $title, string $sub, array $points, array $lines, string $unit, string $id = ''): array
{
    $all = [];
    foreach ($lines as $l) {
        foreach ($points as $p) {
            if (($p[$l[0]] ?? null) !== null) {
                $all[] = (float) $p[$l[0]];
            }
        }
    }
    $peak = $all ? max($all) : 0;
    $max = $peak > 0 ? $peak * 1.15 : ($unit === '%' ? 1 : 10);
    $paths = [];
    foreach ($lines as $l) {
        $vals = array_map(fn($p) => isset($p[$l[0]]) && $p[$l[0]] !== null ? (float) $p[$l[0]] : null, $points);
        $last = null;
        $lastIdx = null;
        for ($i = count($vals) - 1; $i >= 0; $i--) {
            if ($vals[$i] !== null) {
                $last = $vals[$i];
                $lastIdx = $i;
                break;
            }
        }
        $paths[] = ['c' => $l[1], 'label' => $l[2], 'values' => $vals, 'd' => sl_dvxPath($vals, 632, 172, 12, $max, 46), 'last' => $last,
            'lastAt' => $lastIdx !== null ? (string) ($points[$lastIdx]['label'] ?? '') : '', 'stale' => $lastIdx !== null && $lastIdx < count($vals) - 2];
    }
    $fmt = function ($v) use ($unit, $max) {
        if ($unit === 'ms') {
            return $v >= 1000 ? round($v / 1000, 1) . 's' : round($v) . 'ms';
        }
        if ($unit === '%') {
            return ($max < 3 ? round($v, 2) : round($v)) . '%';
        }
        if ($unit === 'B/s') {
            return sl_devBytes($v) . '/s';
        }
        return $max < 10 ? (string) round($v, 1) : sl_num($v);
    };
    $ticks = [];
    foreach ([0, 1, 2, 3, 4] as $i) {
        $ticks[] = ['y' => 12 + $i * 40, 'l' => $fmt($max * (4 - $i) / 4)];
    }
    $xl = [];
    $n = count($points);
    if ($n > 1) {
        foreach ([0, 0.25, 0.5, 0.75, 1] as $f) {
            $i = (int) round(($n - 1) * $f);
            $xl[] = ['x' => round(46 + 586 * $f), 'l' => $points[$i]['label'] ?? '', 'a' => $f === 0 ? 'start' : ($f === 1 ? 'end' : 'middle')];
        }
    }
    $has = (bool) $all;
    return ['id' => $id, 't' => $title, 'sub' => $sub, 'unit' => $unit, 'paths' => $paths, 'ticks' => $ticks, 'xl' => $xl, 'has' => $has, 'max' => $max,
        'attrs' => sl_chartAttrs(array_map(fn($p) => ['name' => $p['label'], 'values' => $p['values'], 'unit' => $unit], $paths), array_map(fn($p) => (string) ($p['label'] ?? ''), $points), $title, 46, 632)];
}

/** SVG markup of a chart built by sl_dvxChart (gridlines, y ticks, x labels, lines, hover data). */
function sl_dvxChartSvg(array $c): string
{
    if (!$c['has']) {
        return '<div class="dvx-chart-empty" role="note"><i class="ic i-info" aria-hidden="true"></i><span>No data in this range yet.</span></div>';
    }
    $o = '<svg ' . $c['attrs'] . ' class="dvx-chart" viewBox="0 0 640 200" preserveAspectRatio="none" role="img" aria-label="' . h($c['t']) . ' chart">';
    foreach ($c['ticks'] as $t) {
        $o .= '<line class="g" x1="46" y1="' . (int) $t['y'] . '" x2="632" y2="' . (int) $t['y'] . '"></line>';
    }
    foreach ($c['paths'] as $i => $p) {
        if ($p['d'] !== '') {
            $o .= '<path d="' . h($p['d']) . '" fill="none" stroke="' . h($p['c']) . '" stroke-width="2" stroke-linejoin="round" vector-effect="non-scaling-stroke"' . ($i ? ' stroke-dasharray="5 4"' : '') . '></path>';
        }
        // isolated points (a value with no neighbour) cannot form a line: mark them
        $v = $p['values'];
        $n = count($v);
        for ($k = 0; $k < $n; $k++) {
            if ($v[$k] !== null && ($k === 0 || $v[$k - 1] === null) && ($k === $n - 1 || $v[$k + 1] === null) && $n > 1 && !empty($c['max'])) {
                $o .= '<ellipse cx="' . round(46 + 586 * $k / ($n - 1), 1) . '" cy="' . round(172 - ((float) $v[$k] / $c['max']) * 160, 1) . '" rx="3" ry="3" fill="' . h($p['c']) . '" vector-effect="non-scaling-stroke"></ellipse>';
            }
        }
    }
    $o .= '</svg><div class="dvx-axis-y" aria-hidden="true">';
    foreach ($c['ticks'] as $t) {
        $o .= '<span style="top:' . round($t['y'] / 200 * 100, 2) . '%">' . h($t['l']) . '</span>';
    }
    $o .= '</div><div class="dvx-axis-x" aria-hidden="true">';
    foreach ($c['xl'] as $x) {
        $o .= '<span style="left:' . round($x['x'] / 640 * 100, 2) . '%" class="' . h($x['a']) . '">' . h($x['l']) . '</span>';
    }
    return '<div class="dvx-chart-w">' . $o . '</div></div>';
}

/* ============================================================ snapshot */

/** True on the single-threaded PHP built-in server, where a request to ourselves would block. */
function sl_dvxSelfProbeBlocked(): bool
{
    return PHP_SAPI === 'cli-server' && (int) getenv('PHP_CLI_SERVER_WORKERS') < 2;
}

/**
 * One live snapshot per request (helpers/devops.php cdp_devCollect). A page view stores a collector
 * sample (at most once a minute) and evaluates the built-in alerts, like cron/devops_collector.php;
 * the AJAX endpoint (SL_DVX_AJAX) only reads (cdp_devCollect(false)), so polling writes nothing.
 * On the single-threaded PHP built-in server an HTTP self-probe would deadlock, so the probe cache
 * (cdp_devState probe_web / probe_api, which cdp_devProbe reads first) is filled from the recorded
 * page / API requests of the last 15 minutes instead; this happens only on that development server.
 */
function sl_dvxSnap(): array
{
    static $s = null;
    if ($s !== null) {
        return $s;
    }
    $GLOBALS['sl_dvxProbeSource'] = 'probe';
    try {
        if (sl_dvxSelfProbeBlocked() && function_exists('cdp_devStateSet')) {
            $GLOBALS['sl_dvxProbeSource'] = 'requests';
            foreach (['web' => 'page', 'api' => 'api'] as $key => $kind) {
                $w = cdp_reqmWindow(time() - 900, time() + 1, null, $kind);
                cdp_devStateSet('probe_' . $key, ['url' => cdp_devBaseUrl() . ($key === 'web' ? 'login.php' : 'api/v1/'), 'ok' => $w['n'] ? ($w['err_pct'] ?? 0) < 50 : null,
                    'code' => null, 'ms' => $w['p50'], 'at' => time(), 'error' => $w['n'] ? null : 'No ' . ($kind === 'page' ? 'page' : 'API') . ' requests recorded in the last 15 minutes', 'source' => 'requests']);
            }
        }
        $s = cdp_devCollect(!defined('SL_DVX_AJAX'));
    } catch (Throwable $e) {
        $s = ['server' => [], 'db' => ['up' => false], 'web' => ['ok' => null], 'api' => ['ok' => null], 'activity' => [], 'channels' => [], 'services' => [], 'error_rate' => 0];
    }
    return $s;
}

/**
 * Server figures with super-admin overrides applied (each flagged).
 * A total / used pair is only overridden as a pair that makes sense: when one side is overridden
 * and the measured other side does not fit (used > total), the incomplete override is ignored and a
 * note says so. Free space, percentages and the storage status are recomputed from what is shown.
 * cpu_cores is honoured for the load capacity.
 */
function sl_dvxServer(array $snap): array
{
    $srv = $snap['server'] ?? [];
    $disk = sl_devDisk();
    $out = [
        'cores' => (int) ($srv['cores'] ?? 1), 'load' => $srv['load'] ?? null, 'cpu_pct' => $srv['cpu_pct'] ?? null,
        'mem_total' => $srv['mem_total'] ?? null, 'mem_used' => $srv['mem_used'] ?? null, 'mem_pct' => $srv['mem_pct'] ?? null, 'mem_src' => isset($srv['mem_total']) && $srv['mem_total'] ? '/proc/meminfo' : null,
        'disk_total' => $disk['total'] ?: null, 'disk_used' => $disk['total'] ? $disk['used'] : null, 'disk_free' => $disk['total'] ? $disk['free'] : null, 'disk_pct' => $disk['total'] ? $disk['pct'] : null,
        'disk_src' => $disk['total'] ? 'total − free (includes reserved blocks, so higher than df)' : null,
        'uptime_s' => $srv['uptime_s'] ?? null, 'os' => $srv['os'] ?? php_uname('s'), 'host' => $srv['host'] ?? gethostname(), 'php' => $srv['php'] ?? [],
        'net_rx' => $srv['net_rx_bps'] ?? null, 'net_tx' => $srv['net_tx_bps'] ?? null, 'disk_r' => $srv['disk_r_bps'] ?? null, 'disk_w' => $srv['disk_w_bps'] ?? null,
        'ovr' => [], 'notes' => [],
    ];
    $gb = 1073741824;
    $num = function (string $k) {
        $v = sl_dvxOverride($k);
        return $v !== null && is_numeric($v) && (float) $v > 0 ? (float) $v : null;
    };
    foreach ([['disk', 'disk_total_gb', 'disk_used_gb', 'Disk'], ['mem', 'ram_total_gb', 'ram_used_gb', 'Memory']] as [$p, $kt, $ku, $label]) {
        $ot = $num($kt);
        $ou = $num($ku);
        if ($ot === null && $ou === null) {
            continue;
        }
        $tot = $ot !== null ? $ot * $gb : $out[$p . '_total'];
        $used = $ou !== null ? $ou * $gb : $out[$p . '_used'];
        if (!$tot || $used === null || $used > $tot) {
            $out['notes'][] = $label . ' override ignored: ' . ($ot !== null ? 'the total (' . sl_devBytes($ot * $gb) . ')' : 'the used figure (' . sl_devBytes($ou * $gb) . ')')
                . ' does not fit the measured ' . ($ot !== null ? 'usage' : 'total') . '. Set both total and used in Infrastructure › Overrides.';
            continue;
        }
        $out[$p . '_total'] = $tot;
        $out[$p . '_used'] = $used;
        if ($ot !== null) {
            $out['ovr'][$p . '_total'] = true;
        }
        if ($ou !== null) {
            $out['ovr'][$p . '_used'] = true;
        }
        $pct = round($used / $tot * 100, 1);
        $src = $ot !== null && $ou !== null ? 'super-admin override' : 'super-admin override (' . ($ot !== null ? 'total' : 'used') . ') with the measured ' . ($ot !== null ? 'usage' : 'total');
        if ($p === 'disk') {
            $out['disk_pct'] = (int) round($pct);
            $out['disk_free'] = $tot - $used;
            $out['disk_src'] = $src;
        } else {
            $out['mem_pct'] = $pct;
            $out['mem_src'] = $src;
        }
    }
    $cores = $num('cpu_cores');
    if ($cores !== null) {
        $out['cores'] = (int) $cores;
        $out['ovr']['cores'] = true;
    }
    if ($out['mem_total'] === null) {
        // No /proc on this host (shared hosting): PHP's own usage is all we can measure.
        $out['php_mem'] = memory_get_peak_usage(true);
    }
    return $out;
}

/* ========================================================== components */

/**
 * GitHub connection (package "deploy", helpers/dev_github.php): read from its stored state, so no
 * network call is made while a page renders. ['configured','ok','ms','detail','repo','error','href'];
 * null when the deploy package is not installed.
 */
function sl_dvxGithub(): ?array
{
    if (!function_exists('sl_ghConfig') && is_file(CDP_ROOT . '/helpers/dev_github.php')) {
        require_once CDP_ROOT . '/helpers/dev_github.php';
    }
    if (function_exists('sl_ghConfig')) {
        try {
            $c = sl_ghConfig();
        } catch (Throwable $e) {
            return ['configured' => true, 'ok' => false, 'detail' => 'GitHub settings could not be read', 'error' => 'Settings unreadable'];
        }
        $repo = $c['configured'] ? $c['owner'] . '/' . $c['repo'] : '';
        if (!$c['ready']) {
            return ['configured' => false, 'ok' => null, 'repo' => $repo ?: 'REST API v3',
                'detail' => $c['configured'] ? 'Repository ' . $repo . ' is set but no read-only token is stored yet' : 'Not connected: add the repository and a read-only fine-grained token'];
        }
        $st = (string) $c['last_status'];
        $when = $c['last_sync'] ?: $c['tested_at'];
        $counts = array_filter((array) $c['last_counts']);
        return ['configured' => true, 'ok' => $st === '' ? null : in_array($st, ['ok', 'partial'], true), 'repo' => $repo, 'ms' => null,
            'detail' => ($when ? 'Last sync ' . sl_dvxAgo((string) $when) . ' (' . ($st ?: 'not synced') . ')' : 'Not synced yet') . ($counts ? ' · ' . implode(', ', array_map(fn($k, $v) => $v . ' ' . $k, array_keys($counts), $counts)) : '')
                . ($c['rate_remaining'] !== null ? ' · API calls left ' . (int) $c['rate_remaining'] : '') . ' · token ' . ($c['token_mask'] ?: 'stored'),
            'error' => $c['last_error'] ?: null, 'checked_at' => $when ? (string) sl_dvxFixTs((string) $when)[0] : null];
    }
    return null;
}

/**
 * Every component of this installation with a real health check.
 * Each: key, name, icon, layer, tech, status, check_ms, check, uptime, uptime_label, thr, last_error, last_error_at, checked_at, detail, sub, logs.
 */
function sl_dvxComponents(): array
{
    static $memo = null;
    if ($memo !== null) {
        return $memo;
    }
    $snap = sl_dvxSnap();
    $svc = [];
    foreach ($snap['services'] ?? [] as $s) {
        $svc[$s['key']] = $s;
    }
    $now = time();
    $nowS = date('Y-m-d H:i:s');
    $probeSrc = $GLOBALS['sl_dvxProbeSource'] ?? 'probe';
    $c = [];
    $add = function (array $x) use (&$c, $nowS) {
        $c[$x['key']] = $x + ['icon' => 'i-server', 'layer' => 'app', 'tech' => '', 'status' => 'unknown', 'check_ms' => null, 'check' => '', 'uptime' => null, 'uptime_label' => '',
            'thr' => '—', 'last_error' => null, 'last_error_at' => null, 'checked_at' => $nowS, 'at_kind' => 'checked', 'detail' => '', 'sub' => '', 'logs' => sl_route('DevLogs'), 'env' => []];
    };

    // Web application: recorded requests (and the HTTP probe where the server allows one).
    $w15 = cdp_reqmWindow($now - 900, $now + 1, null, null);
    $w24 = cdp_reqmWindow($now - 86400, $now + 1);
    $lastErr = cdp_reqmRecent(['since' => date('Y-m-d H:i:s', $now - 86400), 'errors_only' => true], 1)[0] ?? null;
    $web = $snap['web'] ?? [];
    $webSt = $svc['web']['status'] ?? 'unknown';
    if ($probeSrc === 'requests' || $webSt === 'unknown') {
        $webSt = !$w15['n'] ? ($w24['n'] ? 'idle' : 'unknown') : (($w15['err_pct'] ?? 0) >= 50 ? 'down' : ((($w15['err_pct'] ?? 0) >= 5 || ($w15['p95'] ?? 0) > 3000) ? 'degraded' : 'operational'));
    }
    $add(['key' => 'web', 'name' => 'Web application', 'icon' => 'i-terminal', 'layer' => 'app', 'tech' => 'PHP ' . PHP_VERSION . ' · ' . PHP_SAPI, 'status' => $webSt,
        'check_ms' => $probeSrc === 'probe' && isset($web['ms']) ? $web['ms'] : $w15['p50'],
        'check' => $probeSrc === 'probe' ? 'HTTP GET ' . ($web['url'] ?? 'login.php') . ' every 15 s' . (isset($web['code']) && $web['code'] ? ' → HTTP ' . $web['code'] : '') . ($web['error'] ?? '' ? ' · ' . $web['error'] : '')
            : 'Median of ' . sl_num($w15['n']) . ' recorded requests in 15 min (HTTP self-probe skipped: the PHP built-in server is single-threaded)',
        'uptime' => $w24['n'] ? round(100 - (float) $w24['err_pct'], 3) : null, 'uptime_label' => 'requests without a 5xx, 24 h',
        'thr' => $w24['n'] ? sl_num($w24['rpm'], 2) . ' req/min' : '—', 'last_error' => $lastErr ? 'HTTP ' . $lastErr['status'] . ' ' . $lastErr['method'] . ' ' . $lastErr['route'] . ($lastErr['fatal'] ? ' (PHP fatal)' : '') : null,
        'last_error_at' => $lastErr['ts'] ?? null, 'checked_at' => $probeSrc === 'probe' && !empty($web['at']) ? date('Y-m-d H:i:s', (int) $web['at']) : $nowS,
        'detail' => $w24['n'] ? sl_num($w24['n']) . ' requests in 24 h · p95 ' . sl_dvxMs($w24['p95']) . ' · ' . sl_num($w24['err5']) . ' server errors' : 'No request recorded yet',
        'sub' => 'PHP ' . PHP_VERSION . ($w15['p95'] !== null ? ' · p95 ' . sl_dvxMs($w15['p95']) : ''), 'logs' => sl_route('DevLogs', ['source' => 'requests'])]);

    // REST API
    $api = $snap['api'] ?? [];
    $a24 = cdp_reqmWindow($now - 86400, $now + 1, null, 'api');
    $apiSt = $svc['api']['status'] ?? 'unknown';
    if ($probeSrc === 'requests') {
        $apiSt = $a24['n'] ? (($a24['err_pct'] ?? 0) >= 50 ? 'down' : (($a24['err_pct'] ?? 0) >= 5 ? 'degraded' : 'operational')) : 'idle';
    }
    $add(['key' => 'api', 'name' => 'REST API v1', 'icon' => 'i-link', 'layer' => 'entry', 'tech' => 'api/v1 · token auth', 'status' => $apiSt,
        'check_ms' => $probeSrc === 'probe' ? ($api['ms'] ?? null) : $a24['p50'],
        'check' => $probeSrc === 'probe' ? 'HTTP GET ' . ($api['url'] ?? 'api/v1/') . (isset($api['code']) && $api['code'] ? ' → HTTP ' . $api['code'] : '') . (($api['error'] ?? '') ? ' · ' . $api['error'] : '') : 'Recorded api/ requests (self-probe skipped on the built-in server)',
        'uptime' => $a24['n'] ? round(100 - (float) $a24['err_pct'], 3) : null, 'uptime_label' => 'API requests without a 5xx, 24 h',
        'thr' => $a24['n'] ? sl_num($a24['rpm'], 2) . ' req/min' : '0 req/min', 'detail' => $a24['n'] ? sl_num($a24['n']) . ' API requests in 24 h' : 'No API request recorded in 24 h',
        'sub' => $a24['n'] ? sl_num($a24['n']) . ' calls 24 h' : 'token auth', 'logs' => sl_route('DevLogs', ['source' => 'requests', 'q' => 'api/'])]);

    // Browser / PWA clients (entry): real page traffic split.
    $pages = cdp_reqmWindow($now - 86400, $now + 1, null, 'page');
    $ajax = cdp_reqmWindow($now - 86400, $now + 1, null, 'ajax');
    $add(['key' => 'clients', 'name' => 'Browsers & apps', 'icon' => 'i-globe', 'layer' => 'entry', 'tech' => 'Staff, customer and rider apps (PWA)',
        'status' => $pages['n'] + $ajax['n'] ? 'operational' : 'idle', 'check' => 'Page and AJAX requests recorded in 24 h',
        'uptime' => ($pages['n'] + $ajax['n']) ? round(100 - ($pages['err5'] + $ajax['err5']) / ($pages['n'] + $ajax['n']) * 100, 3) : null, 'uptime_label' => 'page + AJAX requests without a 5xx, 24 h',
        'thr' => sl_num($pages['n']) . ' pages · ' . sl_num($ajax['n']) . ' AJAX (24 h)', 'detail' => 'Requests reaching PHP from browsers and the installed apps', 'sub' => sl_num($pages['n'] + $ajax['n']) . ' req 24 h',
        'check_ms' => $pages['p50'], 'logs' => sl_route('DevLogs', ['source' => 'requests'])]);

    // Database
    $db = $snap['db'] ?? ['up' => false];
    $dbUp = null;
    if (function_exists('cdp_devQ') && cdp_devTableExists('cdb_dev_metrics')) {
        $r = cdp_devOne("SELECT COUNT(*) AS n, SUM(db_up) AS u FROM cdb_dev_metrics WHERE ts >= :t", [':t' => date('Y-m-d H:i:s', $now - 86400)]);
        $dbUp = $r && (int) $r->n ? round((int) $r->u / (int) $r->n * 100, 3) : null;
    }
    $add(['key' => 'db', 'name' => 'Database', 'icon' => 'i-database', 'layer' => 'data', 'tech' => 'MySQL ' . ($db['version'] ?? ''), 'status' => $svc['db']['status'] ?? (!empty($db['up']) ? 'operational' : 'down'),
        'check_ms' => $db['ping_ms'] ?? null, 'check' => 'SELECT 1, median of 3 pings', 'uptime' => $dbUp, 'uptime_label' => $dbUp !== null ? 'collector samples with the database up, 24 h' : 'needs collector samples',
        'thr' => isset($db['qps']) && $db['qps'] !== null ? sl_num($db['qps'], 1) . ' queries/s' : '—',
        'detail' => !empty($db['up']) ? sl_devBytes($db['size_bytes'] ?? 0) . ' in ' . (int) ($db['tables'] ?? 0) . ' tables · ' . (int) ($db['connections'] ?? 0) . ' / ' . (int) ($db['max_connections'] ?? 0) . ' connections · up ' . cdp_devDur($db['uptime_s'] ?? 0) : ($db['error'] ?? 'No answer'),
        'last_error' => !empty($db['up']) ? ((int) ($db['slow_queries'] ?? 0) ? sl_num($db['slow_queries']) . ' slow queries since start' : null) : 'Database unreachable',
        'sub' => (($db['version'] ?? '') ? preg_replace('/-.*$/', '', (string) $db['version']) . ' · ' : '') . sl_devBytes($db['size_bytes'] ?? 0) . ' · ' . (int) ($db['connections'] ?? 0) . ' conn',
        'logs' => sl_route('DevMetrics') . '#db']);

    // Storage (status from the figures shown, overrides included)
    $srv = sl_dvxServer($snap);
    $st = $svc['storage'] ?? null;
    $dpct = $srv['disk_pct'];
    $diskOvr = !empty($srv['ovr']['disk_total']) || !empty($srv['ovr']['disk_used']);
    $stStatus = $dpct === null ? ($st['status'] ?? 'unknown') : ($dpct > 95 ? 'down' : ($dpct > 85 ? 'degraded' : 'operational'));
    if ($st && in_array($st['status'] ?? '', ['down', 'degraded'], true) && $stStatus === 'operational' && !$diskOvr) {
        $stStatus = $st['status']; // e.g. an upload folder is not writable
    }
    $add(['key' => 'storage', 'name' => 'File storage', 'icon' => 'i-archive', 'layer' => 'data', 'tech' => 'Local disk' . ($diskOvr ? ' · override' : ''),
        'status' => $stStatus,
        'check' => 'disk_free_space() of the app volume, upload folders writable' . ($diskOvr ? ' (figures from a super-admin override)' : ''),
        'uptime' => null, 'uptime_label' => 'not applicable', 'thr' => $dpct !== null ? $dpct . '% used' : '—',
        'detail' => $srv['disk_total'] ? sl_devBytes($srv['disk_used']) . ' of ' . sl_devBytes($srv['disk_total']) . ' used · ' . sl_devBytes($srv['disk_free']) . ' free · ' . $srv['disk_src'] . ($st['detail'] ?? '' ? ' · ' . $st['detail'] : '') . ($srv['notes'] ? ' · ' . implode(' ', $srv['notes']) : '') : 'disk_total_space() is not available on this host',
        'sub' => $srv['disk_total'] ? sl_devBytes(max(0, $srv['disk_free'])) . ' free' : 'not measured', 'logs' => sl_route('DevMetrics') . '#disk']);

    // Cron worker: run ledger + heartbeats. cdb_cron_runs is stamped with MySQL's NOW(), which may run in
    // another zone than the application: bounds and times are converted (sl_dvxToDb / sl_dvxFromDb).
    $jobs = sl_tableExists('cdb_cron_runs') ? sl_row("SELECT COUNT(*) AS n, SUM(status = 'failed') AS f, SUM(status = 'ok') AS ok, MAX(started_at) AS last, AVG(duration_ms) AS ms FROM cdb_cron_runs WHERE started_at >= NOW() - INTERVAL 1 DAY") : null;
    $lastRun = sl_tableExists('cdb_cron_runs') ? (string) sl_dvxFromDb((string) sl_scalar('SELECT MAX(started_at) FROM cdb_cron_runs', [], '')) : '';
    $lastFail = sl_tableExists('cdb_cron_runs') ? sl_row("SELECT job, message, started_at FROM cdb_cron_runs WHERE status = 'failed' ORDER BY id DESC") : null;
    if ($lastFail) {
        $lastFail->started_at = sl_dvxFromDb($lastFail->started_at);
    }
    $hb = [$svc['worker'] ?? null, $svc['collector'] ?? null];
    $ran = (int) ($jobs->n ?? 0);
    $failed = (int) ($jobs->f ?? 0);
    $cronSt = $ran ? ($failed ? ($failed >= $ran ? 'down' : 'degraded') : 'operational') : ($lastRun ? 'degraded' : 'not_configured');
    $add(['key' => 'cron', 'name' => 'Cron worker', 'icon' => 'i-clock', 'layer' => 'jobs', 'tech' => count(glob(CDP_ROOT . '/cron/*.php') ?: []) . ' scripts in cron/', 'status' => $cronSt,
        'check_ms' => $jobs && $jobs->ms !== null ? round((float) $jobs->ms) : null, 'check' => 'Run ledger cdb_cron_runs (helpers/cron_run.php) and worker heartbeats',
        'uptime' => $ran ? round(($ran - $failed) / $ran * 100, 2) : null, 'uptime_label' => 'successful runs, 24 h', 'thr' => $ran . ' runs in 24 h',
        'last_error' => $lastFail ? $lastFail->job . ': ' . ($lastFail->message ?: 'failed') : null, 'last_error_at' => $lastFail->started_at ?? null, 'checked_at' => $lastRun ?: null, 'at_kind' => 'activity',
        'detail' => ($lastRun ? 'Last run ' . sl_dvxAgo($lastRun) : 'No run recorded: schedule the cron scripts (see cron/README or docs)') . ' · ' . implode(' · ', array_filter(array_map(fn($h) => $h ? $h['name'] . ': ' . $h['detail'] : null, $hb))),
        'sub' => $lastRun ? 'last run ' . sl_dvxAgo($lastRun) : 'no runs', 'logs' => sl_route('DevLogs', ['source' => 'cron'])]);

    // Notification outbox
    $q = sl_devQueue();
    $tot = $q['sent24'] + $q['dead'];
    $add(['key' => 'outbox', 'name' => 'Notification outbox', 'icon' => 'i-send', 'layer' => 'jobs', 'tech' => 'cdb_cs_outbox', 'status' => !$q['exists'] ? 'not_configured' : ($q['dead'] > 0 || $q['oldest_min'] > 30 || $q['queued'] > 200 ? 'degraded' : 'operational'),
        'check' => 'Queue depth, dead letters and age of the oldest waiting item', 'uptime' => $tot ? round($q['sent24'] / $tot * 100, 2) : null, 'uptime_label' => 'delivered vs dead, 24 h',
        'thr' => $q['sent24'] . ' sent in 24 h', 'last_error' => $q['dead'] ? $q['dead'] . ' dead items' : null,
        'detail' => $q['exists'] ? $q['queued'] . ' waiting · ' . $q['retry'] . ' retrying · ' . $q['dead'] . ' dead · oldest ' . $q['oldest_min'] . ' min' : 'Created when Customer Service is first used',
        'sub' => $q['exists'] ? $q['queued'] . ' queued · ' . $q['dead'] . ' dead' : 'not created', 'logs' => sl_route('DevLogs', ['source' => 'messages'])]);

    // Messaging channels (message log, 24 h). Status from the log itself: no traffic is idle, failures
    // without any success are degraded (down only when recent sends keep failing), skipped-only is idle.
    $ch = $snap['channels'] ?? [];
    foreach (['email' => ['E-mail', 'i-mail', 'ch_email'], 'sms' => ['SMS', 'i-phone', 'ch_sms'], 'whatsapp' => ['WhatsApp', 'i-message', 'ch_whatsapp']] as $k => [$label, $icon, $sk]) {
        $x = $ch[$k] ?? null;
        $sent = (int) ($x['sent'] ?? 0);
        $fail = (int) ($x['failed'] ?? 0);
        $n = $sent + $fail;
        $lf = sl_tableExists('cdb_message_log') ? sl_row("SELECT status_detail, created_at FROM cdb_message_log WHERE channel = :c AND status = 'failed' ORDER BY id DESC", [':c' => $k]) : null;
        $recentFail = sl_tableExists('cdb_message_log') ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_message_log WHERE channel = :c AND status = 'failed' AND created_at >= :t", [':c' => $k, ':t' => date('Y-m-d H:i:s', $now - 3600)]) : 0;
        $base = $svc[$sk]['status'] ?? 'idle';
        if ($base === 'not_configured' && !$n) {
            $chSt = 'not_configured';
        } elseif (!$n) {
            $chSt = 'idle';
        } elseif (!$sent) {
            $chSt = $recentFail >= 3 ? 'down' : 'degraded';
        } else {
            $chSt = $fail / $n >= 0.5 ? 'degraded' : ($fail ? 'degraded' : 'operational');
            if ($fail && $fail / $n < 0.05) {
                $chSt = 'operational';
            }
        }
        $add(['key' => $k, 'name' => $label, 'icon' => $icon, 'layer' => 'external', 'tech' => $svc[$sk]['tech'] ?? '', 'status' => $chSt,
            'check' => 'Outcomes in the message log (cdb_message_log), 24 h', 'uptime' => $n ? round($sent / $n * 100, 2) : null, 'uptime_label' => $n ? 'sent vs failed, 24 h' : 'no send in 24 h',
            'thr' => $sent . ' sent in 24 h', 'last_error' => $lf ? mb_substr((string) ($lf->status_detail ?: 'Send failed'), 0, 160) : null, 'last_error_at' => $lf->created_at ?? null,
            'checked_at' => $x['last_at'] ?? null, 'at_kind' => 'activity', 'detail' => ($svc[$sk]['detail'] ?? '') . (!$n ? ' · no message sent or failed in 24 h' : ''),
            'sub' => $n ? $sent . ' sent · ' . $fail . ' failed' : 'idle 24 h',
            'logs' => sl_route('DevLogs', ['source' => 'messages', 'q' => $k === 'email' ? '' : $k])]);
    }

    // Payments
    $pay = $svc['payments'] ?? null;
    $p7 = sl_tableExists('cdb_payments_gateway') ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_payments_gateway WHERE date_payment >= :t', [':t' => date('Y-m-d H:i:s', $now - 7 * 86400)]) : null;
    $add(['key' => 'payments', 'name' => 'Payment gateways', 'icon' => 'i-card', 'layer' => 'external', 'tech' => $pay['tech'] ?? 'Not set up', 'status' => $pay['status'] ?? 'not_configured',
        'check' => 'Gateway configuration (their APIs are only called during a payment)', 'uptime' => null, 'uptime_label' => 'not measured: no health endpoint is called',
        'thr' => $p7 !== null ? $p7 . ' payments in 7 d' : '—', 'detail' => $pay['detail'] ?? 'Configure in Settings › Payment gateways', 'sub' => $pay['instances'] ?? 'none active',
        'logs' => sl_route('DevLogs', ['source' => 'activity', 'view' => 'payments'])]);

    // AI provider: configuration from the snapshot, health from the runs ledger of the last 24 h
    $ai = $svc['ai'] ?? null;
    $aiRuns = sl_tableExists('cdb_cs_ai_runs') ? sl_row("SELECT COUNT(*) AS n, SUM(outcome LIKE 'error%') AS e, AVG(latency_ms) AS l, MAX(created_at) AS last FROM cdb_cs_ai_runs WHERE created_at >= :t", [':t' => date('Y-m-d H:i:s', $now - 86400)]) : null;
    $aiLast = sl_tableExists('cdb_cs_ai_runs') ? sl_row("SELECT detail, created_at FROM cdb_cs_ai_runs WHERE outcome LIKE 'error%' ORDER BY id DESC") : null;
    $aiN = (int) ($aiRuns->n ?? 0);
    $aiE = (int) ($aiRuns->e ?? 0);
    $aiSt = $ai['status'] ?? 'not_configured';
    $aiNote = '';
    if ($aiN) {
        $runSt = $aiE >= $aiN ? 'down' : ($aiE / $aiN >= 0.1 ? 'degraded' : 'operational');
        if ($aiSt === 'not_configured' || $aiSt === 'unknown' || $aiSt === 'idle') {
            $aiSt = $runSt;
            $aiNote = $aiSt === 'operational' ? '' : ' · key not set now, recent runs failing';
        } elseif ($runSt !== 'operational' && $aiSt === 'operational') {
            $aiSt = $runSt;
        }
    }
    $add(['key' => 'ai', 'name' => 'AI provider', 'icon' => 'i-ai', 'layer' => 'external', 'tech' => $ai['tech'] ?? 'Not set up', 'status' => $aiSt,
        'check_ms' => $ai['latency_ms'] ?? ($aiRuns && $aiRuns->l !== null ? round((float) $aiRuns->l) : null), 'check' => 'AI runs ledger (cdb_cs_ai_runs): latency and errors, 24 h',
        'uptime' => $aiN ? round(($aiN - $aiE) / $aiN * 100, 2) : null, 'uptime_label' => 'calls without an error, 24 h',
        'thr' => $aiN ? $aiN . ' calls in 24 h' : ($ai['throughput'] ?? '—'), 'last_error' => $aiLast ? mb_substr((string) ($aiLast->detail ?: 'error'), 0, 160) : null, 'last_error_at' => $aiLast->created_at ?? null,
        'checked_at' => $aiRuns->last ?? null, 'at_kind' => 'activity',
        'detail' => ($ai['detail'] ?? '') . $aiNote . ($aiN ? ' · ' . $aiN . ' calls, ' . $aiE . ' errors in 24 h' : ''), 'sub' => $aiN ? $aiN . ' calls · ' . $aiE . ' errors' : ($ai['throughput'] ?? 'not set up'), 'logs' => sl_route('DevLogs', ['source' => 'ai'])]);

    // GitHub (package "deploy")
    $gh = sl_dvxGithub();
    $ghHref = is_file(CDP_ROOT . '/dev_github.php') ? 'dev_github.php' : sl_route('DevIntegrations');
    $add(['key' => 'github', 'name' => 'GitHub', 'icon' => 'i-git', 'layer' => 'external', 'tech' => $gh['repo'] ?? 'REST API v3',
        'status' => $gh === null || empty($gh['configured']) ? 'not_configured' : (($gh['ok'] ?? null) === null ? 'unknown' : ($gh['ok'] ? 'operational' : 'down')),
        'check_ms' => $gh['ms'] ?? null,
        'uptime_label' => 'not measured', 'detail' => $gh['detail'] ?? 'Not connected: add a read-only fine-grained token to read commits and Actions runs',
        'last_error' => !empty($gh['error']) ? (string) $gh['error'] : null, 'checked_at' => $gh['checked_at'] ?? null, 'at_kind' => 'activity',
        'check' => $gh === null ? 'GitHub connection is set up on the GitHub page (package deploy)' : 'Outcome of the last read-only sync with the stored token (php helpers/dev_github.php sync)',
        'sub' => $gh === null || empty($gh['configured']) ? 'not connected' : ($gh['repo'] ?? 'connected'), 'logs' => $ghHref]);

    // Backups
    $b = sl_devBackup();
    $age = $b['latest_at'] ? ($now - $b['latest_at']) / 86400 : null;
    $add(['key' => 'backups', 'name' => 'Database backups', 'icon' => 'i-archive', 'layer' => 'data', 'tech' => $b['count'] . ' files in backups/', 'status' => $age === null ? 'not_configured' : ($age > 7 ? 'degraded' : 'operational'),
        'check' => 'Newest .sql file in backups/', 'uptime_label' => 'not applicable', 'thr' => $b['count'] . ' files', 'checked_at' => $b['latest_at'] ? date('Y-m-d H:i:s', $b['latest_at']) : null, 'at_kind' => 'activity',
        'detail' => $b['latest'] ? 'Last ' . sl_date($b['latest_at'], 'M j, Y H:i') . ' · ' . sl_devBytes($b['latest_size']) : 'No backup file yet', 'sub' => $b['latest'] ? 'last ' . sl_ago(date('Y-m-d H:i:s', $b['latest_at'])) : 'none',
        'logs' => 'backup.php']);

    foreach ($c as $k => &$x) {
        $st = sl_dvxSt($x['status']);
        $x['st'] = $st[0];
        $x['cls'] = $st[1];
        $x['dot'] = $st[2];
        $x['href'] = sl_route('DevServices', ['svc' => $k]) . '#svc-' . $k;
        $x['at_label'] = $x['at_kind'] === 'checked' ? 'checked' : 'last activity';
        $x['at_ago'] = $x['checked_at'] ? sl_dvxAgo((string) $x['checked_at']) : 'none yet';
    }
    unset($x);
    return $memo = $c;
}

/* ================================================================= logs */

/** Log sources: key => label. */
function sl_dvxSources(): array
{
    return ['php' => 'PHP error log', 'activity' => 'Activity log', 'messages' => 'Message log', 'cron' => 'Cron runs', 'requests' => 'Request metrics', 'ai' => 'AI runs', 'alerts' => 'Alerts'];
}

/** Saved filters a link can name with ?view= (each limited to one source). */
function sl_dvxViews(): array
{
    return ['slow' => ['requests', 'Slow requests only'], 'signin' => ['activity', 'Failed sign-ins only'], 'payments' => ['activity', 'Payment events only']];
}

/** Valid UTF-8 for any string that leaves the server (a bad byte would make json_encode return false). */
function sl_dvxClean($v)
{
    if (is_string($v)) {
        return function_exists('mb_scrub') ? mb_scrub($v, 'UTF-8') : (string) iconv('UTF-8', 'UTF-8//IGNORE', $v);
    }
    if (is_array($v)) {
        return array_map('sl_dvxClean', $v);
    }
    return $v;
}

/** Level of an activity row from its outcome: ok / success INFO, denied WARN, fail / failure / error ERROR. */
function sl_dvxActLevelSql(): string
{
    return "(CASE WHEN outcome IN ('fail','failure','error') THEN 'ERROR' WHEN outcome = 'denied' THEN 'WARN' ELSE 'INFO' END)";
}

/**
 * SQL filter of one database-backed log source: [table, time column, where, binds, level expression].
 * The same filter feeds the event list and the counters, so a count always matches what its link opens.
 * $since is application wall-clock time; the cron ledger is converted to MySQL's clock.
 */
function sl_dvxSrcSql(string $src, string $since, string $q, string $lv, string $view): ?array
{
    $like = $q !== '' ? '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q) . '%' : null;
    switch ($src) {
        case 'activity':
            if (!sl_tableExists('cdb_activity_log')) return null;
            $w = 'created_at > :s';
            $b = [':s' => $since];
            if ($like) {
                $w .= ' AND (summary LIKE :q OR action_label LIKE :q2 OR actor_name LIKE :q3 OR entity_label LIKE :q4 OR module LIKE :q5)';
                $b += [':q' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like, ':q5' => $like];
            }
            if ($view === 'signin') $w .= " AND verb = 'login' AND outcome IN ('fail','failure','denied')";
            if ($view === 'payments') $w .= " AND (module IN ('finance','billing','payments') OR action LIKE '%payment%')";
            $l = sl_dvxActLevelSql();
            return ['cdb_activity_log', 'created_at', $w . ($lv ? " AND $l = '$lv'" : ''), $b, $l];
        case 'messages':
            if (!sl_tableExists('cdb_message_log')) return null;
            $w = 'created_at > :s';
            $b = [':s' => $since];
            if ($like) {
                $w .= ' AND (subject LIKE :q OR recipient_to LIKE :q2 OR status_detail LIKE :q3 OR channel LIKE :q4)';
                $b += [':q' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like];
            }
            $l = "(CASE WHEN status = 'sent' THEN 'INFO' WHEN status = 'skipped' THEN 'WARN' ELSE 'ERROR' END)";
            return ['cdb_message_log', 'created_at', $w . ($lv ? " AND $l = '$lv'" : ''), $b, $l];
        case 'ai':
            if (!sl_tableExists('cdb_cs_ai_runs')) return null;
            $w = 'created_at > :s';
            $b = [':s' => $since];
            if ($like) {
                $w .= ' AND (detail LIKE :q OR kind LIKE :q2 OR model LIKE :q3)';
                $b += [':q' => $like, ':q2' => $like, ':q3' => $like];
            }
            $l = "(CASE WHEN outcome LIKE 'error%' THEN 'ERROR' WHEN outcome = 'discarded' THEN 'WARN' ELSE 'INFO' END)";
            return ['cdb_cs_ai_runs', 'created_at', $w . ($lv ? " AND $l = '$lv'" : ''), $b, $l];
        case 'cron':
            if (!sl_tableExists('cdb_cron_runs')) return null;
            $w = 'started_at > :s';
            $b = [':s' => sl_dvxToDb($since)];
            if ($like) {
                $w .= ' AND (job LIKE :q OR message LIKE :q2)';
                $b += [':q' => $like, ':q2' => $like];
            }
            $l = "(CASE WHEN status = 'failed' THEN 'ERROR' WHEN status = 'running' THEN 'WARN' ELSE 'INFO' END)";
            return ['cdb_cron_runs', 'started_at', $w . ($lv ? " AND $l = '$lv'" : ''), $b, $l];
    }
    return null;
}

/** Sources a filter reads (a saved view pins its own source). */
function sl_dvxLogSources(string $src, string $view): array
{
    if ($view !== '' && isset(sl_dvxViews()[$view])) {
        return [sl_dvxViews()[$view][0]];
    }
    return $src !== '' ? [$src] : array_keys(sl_dvxSources());
}

/** Request-metrics filter of a log filter. In the merged view only the interesting requests (WARN / ERROR). */
function sl_dvxReqFilter(string $src, string $since, string $q, string $lv, string $view): array
{
    $rf = ['since' => $since, 'q' => $q, 'level' => $lv];
    if ($view === 'slow') {
        $rf = ['since' => $since, 'q' => $q, 'slow_only' => true];
    } elseif ($src === '' && $lv === '') {
        $rf['level'] = 'NOTINFO';
    }
    return $rf;
}

/**
 * PHP error-log lines newer than $since (newest first, at most the last 3000 lines / 1 MB of the file),
 * matching q and level. Each: [ts(int|null), line(valid UTF-8, capped at 8 KB), parsed].
 */
function sl_dvxPhpLines(string $since, string $q, string $lv): array
{
    static $memo = [];
    $key = $since . '|' . $q . '|' . $lv;
    if (isset($memo[$key])) {
        return $memo[$key];
    }
    $out = [];
    $sinceT = strtotime($since);
    foreach (sl_devTail(sl_devErrorLogPath(), 3000) as $line) {
        $line = sl_dvxClean((string) $line);
        if (strlen($line) > 8192) {
            $line = mb_strcut($line, 0, 8192, 'UTF-8') . ' …[truncated]';
        }
        $p = sl_devParseLogLine($line);
        if ($p['ts'] !== null && $p['ts'] <= $sinceT) {
            break;
        }
        if ($lv && $p['level'] !== $lv) {
            continue;
        }
        if ($q !== '' && stripos($line, $q) === false) {
            continue;
        }
        $out[] = [$p['ts'], $line, $p];
    }
    return $memo[$key] = $out;
}

/** Alert rows (helpers/devops.php) with their times corrected to the application clock, newer than $since. */
function sl_dvxAlertRows(string $since, string $q, string $lv): array
{
    $rows = [];
    foreach (cdp_devLogs(['since' => date('Y-m-d H:i:s', strtotime($since) - 86400), 'limit' => 200, 'source' => 'alerts', 'q' => $q]) as $l) {
        [$ts, $future] = sl_dvxFixTs((string) $l['ts']);
        if ((string) $ts <= $since || ($lv && $l['level'] !== $lv)) {
            continue;
        }
        $l['ts'] = $ts;
        if ($future) {
            $l['detail']['clock_note'] = 'stamped in the future; shown at the current time';
        }
        $rows[] = $l;
    }
    return $rows;
}

/**
 * One merged stream, newest first. $f: source (key or ''), level (INFO|WARN|ERROR|''), q, window (minutes),
 * since (Y-m-d H:i:s, exclusive, application time), limit, view (sl_dvxViews key).
 * Row: id, ts, source, service, level, message, detail (array), raw (string).
 * Every source is in application wall-clock time (the cron ledger is converted from MySQL's clock).
 */
function sl_dvxLogs(array $f): array
{
    $win = max(1, min(10080, (int) ($f['window'] ?? 60)));
    $since = !empty($f['since']) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $f['since']) ? (string) $f['since'] : date('Y-m-d H:i:s', time() - $win * 60);
    $limit = max(10, min(500, (int) ($f['limit'] ?? 200)));
    $src = array_key_exists((string) ($f['source'] ?? ''), sl_dvxSources()) ? (string) $f['source'] : '';
    $lv = in_array($f['level'] ?? '', ['INFO', 'WARN', 'ERROR'], true) ? (string) $f['level'] : '';
    $q = trim((string) ($f['q'] ?? ''));
    $view = isset(sl_dvxViews()[(string) ($f['view'] ?? '')]) ? (string) $f['view'] : '';
    $srcs = sl_dvxLogSources($src, $view);
    $rows = [];
    foreach ($srcs as $s) {
        if ($s === 'php' || $s === 'requests' || $s === 'alerts') {
            continue;
        }
        $x = sl_dvxSrcSql($s, $since, $q, $lv, $view);
        if (!$x) {
            continue;
        }
        [$t, $col, $w, $b, $l] = $x;
        foreach (sl_rows("SELECT *, $l AS lvl FROM $t WHERE $w ORDER BY id DESC LIMIT $limit", $b) as $r) {
            if ($s === 'activity') {
                $rows[] = ['id' => 'a' . $r->id, 'ts' => $r->created_at, 'source' => 'activity', 'service' => ucfirst((string) $r->module), 'level' => $r->lvl,
                    'message' => trim(($r->summary ?: $r->action_label) . ($r->actor_name ? ' · ' . $r->actor_name : '')),
                    'detail' => ['action' => $r->action, 'outcome' => $r->outcome, 'actor' => $r->actor_name, 'role' => $r->role_name,
                        'entity' => trim($r->entity_type . ' ' . $r->entity_id . ' ' . $r->entity_label), 'request' => trim($r->method . ' ' . $r->endpoint), 'ip' => $r->ip]];
            } elseif ($s === 'messages') {
                $rows[] = ['id' => 'm' . $r->id, 'ts' => $r->created_at, 'source' => 'messages', 'service' => strtoupper((string) $r->channel) === 'EMAIL' ? 'E-mail' : ucfirst((string) $r->channel), 'level' => $r->lvl,
                    'message' => ucfirst((string) $r->status) . ': ' . ($r->subject ?: $r->source_label) . ' → ' . ($r->recipient_name ?: $r->recipient_to) . ($r->status === 'failed' && $r->status_detail ? ' · ' . mb_substr((string) $r->status_detail, 0, 160) : ''),
                    'detail' => ['channel' => $r->channel, 'to' => $r->recipient_to, 'status' => $r->status, 'status_detail' => $r->status_detail, 'source' => $r->source_label, 'sent_by' => $r->sent_by_name]];
            } elseif ($s === 'ai') {
                $rows[] = ['id' => 'k' . $r->id, 'ts' => $r->created_at, 'source' => 'ai', 'service' => 'King AI', 'level' => $r->lvl,
                    'message' => ucfirst((string) $r->kind) . ' ' . $r->outcome . ($r->latency_ms ? ' in ' . $r->latency_ms . ' ms' : '') . ($r->lvl !== 'INFO' && $r->detail ? ' · ' . mb_substr((string) $r->detail, 0, 200) : ''),
                    'detail' => ['provider' => $r->provider, 'model' => $r->model, 'outcome' => $r->outcome, 'detail' => $r->detail, 'latency_ms' => $r->latency_ms,
                        'tokens' => (int) $r->tokens_in + (int) $r->tokens_out, 'ticket_id' => $r->ticket_id, 'conversation_id' => $r->conversation_id]];
            } elseif ($s === 'cron') {
                $rows[] = ['id' => 'c' . $r->id, 'ts' => sl_dvxFromDb($r->started_at), 'source' => 'cron', 'service' => (string) $r->job, 'level' => $r->lvl,
                    'message' => strtoupper((string) $r->status) . ($r->duration_ms !== null ? ' in ' . sl_devMs($r->duration_ms) : '') . ($r->message ? ' · ' . $r->message : ''),
                    'detail' => ['job' => $r->job, 'status' => $r->status, 'started_at' => sl_dvxFromDb($r->started_at), 'finished_at' => sl_dvxFromDb($r->finished_at), 'duration_ms' => $r->duration_ms !== null ? (int) $r->duration_ms : null, 'host' => $r->host, 'message' => $r->message]
                        + (sl_dvxDbSkew() ? ['clock' => 'stamped by MySQL NOW() ' . $r->started_at . ', shown in the application time zone'] : [])];
            }
        }
    }
    if (in_array('alerts', $srcs, true)) {
        foreach (sl_dvxAlertRows($since, $q, $lv) as $l) {
            $rows[] = ['id' => $l['id'], 'ts' => $l['ts'], 'source' => 'alerts', 'service' => (string) $l['service'], 'level' => $l['level'], 'message' => (string) $l['message'], 'detail' => $l['detail'] ?? []];
        }
    }
    if (in_array('php', $srcs, true)) {
        $path = sl_devErrorLogPath();
        $n = 0;
        foreach (sl_dvxPhpLines($since, $q, $lv) as [$ts, $line, $p]) {
            $file = $lineNo = null;
            if (preg_match('/ in (\/\S+?)(?: on line |:)(\d+)/', $p['text'], $m)) {
                $file = str_replace(CDP_ROOT . '/', '', $m[1]);
                $lineNo = (int) $m[2];
            }
            $type = preg_match('/PHP ([A-Za-z ]+?):/', $p['text'], $m2) ? $m2[1] : 'log';
            // content-based id: stable while new lines are appended (identical lines collapse, fine for a tail)
            $rows[] = ['id' => 'p' . ($ts ?: 0) . '_' . substr(sha1($line), 0, 12), 'ts' => $ts ? date('Y-m-d H:i:s', $ts) : '', 'source' => 'php', 'service' => 'PHP', 'level' => $p['level'],
                'message' => mb_substr(preg_replace('/^PHP\s+/', '', $p['text']), 0, 400), 'detail' => array_filter(['type' => $type, 'file' => $file, 'line' => $lineNo, 'log' => basename($path)], fn($v) => $v !== null), 'raw' => $line];
            if (++$n >= $limit) {
                break;
            }
        }
    }
    if (in_array('requests', $srcs, true)) {
        $rf = sl_dvxReqFilter($src, $since, $q, $lv, $view);
        if (($rf['level'] ?? '') === 'NOTINFO') {
            $req = array_merge(cdp_reqmRecent(['level' => 'WARN'] + $rf, $limit), cdp_reqmRecent(['level' => 'ERROR'] + $rf, $limit));
        } else {
            $req = cdp_reqmRecent($rf, $limit);
        }
        foreach ($req as $r) {
            $st = (int) $r['status'];
            $lvl = $st >= 500 ? 'ERROR' : (($st >= 400 || (int) $r['ms'] >= cdp_reqmSlowMs()) ? 'WARN' : 'INFO');
            $rows[] = ['id' => 'r' . $r['id'], 'ts' => $r['ts'], 'source' => 'requests', 'service' => strtoupper((string) $r['kind']), 'level' => $lvl,
                'message' => $r['method'] . ' /' . $r['route'] . ' → ' . $st . ' in ' . sl_devMs($r['ms']) . ($r['fatal'] ? ' · PHP fatal error' : '') . ((int) $r['ms'] >= cdp_reqmSlowMs() ? ' · slow' : ''),
                'detail' => ['route' => $r['route'], 'method' => $r['method'], 'status' => $st, 'duration_ms' => (int) $r['ms'], 'peak_memory' => sl_devBytes((int) $r['mem_kb'] * 1024), 'kind' => $r['kind'],
                    'sample_weight' => (int) $r['w'], 'fatal' => (bool) $r['fatal'], 'host_load_1m' => $r['load1'] !== null ? (float) $r['load1'] : null, 'host_memory_pct' => $r['mem_pct'] !== null ? (float) $r['mem_pct'] : null]];
        }
    }
    // a writer that stamped before the application zone was applied (CLI, endpoints without Core)
    // leaves rows hours in the future: bring them back to the application clock, then re-apply the window
    foreach ($rows as $i => &$r) {
        [$fixed, $future] = sl_dvxFixTs((string) $r['ts']);
        if ($fixed !== $r['ts']) {
            $r['detail'] = (array) $r['detail'] + ['clock_note' => $future ? 'time stamp is ahead of the server clock; shown at the current time' : 'stamped ' . $r['ts'] . ' in another time zone; shown in the application time zone'];
            $r['ts'] = $fixed;
            if ($fixed <= $since) {
                unset($rows[$i]);
            }
        }
    }
    unset($r);
    usort($rows, fn($a, $b) => strcmp((string) $b['ts'], (string) $a['ts']));
    $rows = array_slice($rows, 0, $limit);
    $labels = sl_dvxSources();
    foreach ($rows as &$r) {
        $r['message'] = sl_dvxClean((string) $r['message']);
        $r['service'] = sl_dvxClean((string) $r['service']);
        $r['detail'] = sl_dvxClean($r['detail']);
        $r['srcLabel'] = $labels[$r['source']] ?? $r['source'];
        if (!isset($r['raw'])) {
            $r['raw'] = '[' . $r['ts'] . '] ' . $r['level'] . ' ' . $r['source'] . '/' . $r['service'] . ': ' . $r['message'];
        }
        $r['raw'] = sl_dvxClean((string) $r['raw']);
        $r['json'] = json_encode(['id' => $r['id'], 'timestamp' => $r['ts'], 'source' => $r['source'], 'service' => $r['service'], 'level' => $r['level'], 'message' => $r['message'], 'detail' => $r['detail']],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $r['time'] = $r['ts'] ? date('M j H:i:s', strtotime($r['ts'])) : '';
        $r['lc'] = $r['level'] === 'ERROR' ? 'e' : ($r['level'] === 'WARN' ? 'w' : 'i');
    }
    unset($r);
    return $rows;
}

/**
 * Counts over the whole window (not just the listed rows), by source and level, plus a histogram of
 * $nb buckets from $since to now. Uses the same filters as sl_dvxLogs, with COUNT / GROUP BY queries.
 * PHP log lines are counted from the last 3000 lines of the file (noted on the page).
 */
function sl_dvxLogCounts(array $f, int $nb = 60): array
{
    $win = max(1, min(10080, (int) ($f['window'] ?? 60)));
    $span = $win * 60;
    $sinceT = time() - $span;
    $since = date('Y-m-d H:i:s', $sinceT);
    $src = array_key_exists((string) ($f['source'] ?? ''), sl_dvxSources()) ? (string) $f['source'] : '';
    $lv = in_array($f['level'] ?? '', ['INFO', 'WARN', 'ERROR'], true) ? (string) $f['level'] : '';
    $q = trim((string) ($f['q'] ?? ''));
    $view = isset(sl_dvxViews()[(string) ($f['view'] ?? '')]) ? (string) $f['view'] : '';
    $bs = max(1, (int) ceil($span / $nb));
    $out = ['bySource' => [], 'lv' => ['i' => 0, 'w' => 0, 'e' => 0], 'total' => 0, 'hist' => array_fill(0, $nb, ['i' => 0, 'w' => 0, 'e' => 0])];
    $addN = function (string $s, string $level, int $k, int $n) use (&$out, $nb) {
        $lc = $level === 'ERROR' ? 'e' : ($level === 'WARN' ? 'w' : 'i');
        $out['bySource'][$s] = ($out['bySource'][$s] ?? 0) + $n;
        $out['lv'][$lc] += $n;
        $out['total'] += $n;
        $k = max(0, min($nb - 1, $k));
        $out['hist'][$k][$lc] += $n;
    };
    foreach (sl_dvxLogSources($src, $view) as $s) {
        if ($s === 'php') {
            foreach (sl_dvxPhpLines($since, $q, $lv) as [$ts, $line, $p]) {
                $addN('php', $p['level'], $ts ? (int) floor(($ts - $sinceT) / $bs) : $nb - 1, 1);
            }
        } elseif ($s === 'alerts') {
            foreach (sl_dvxAlertRows($since, $q, $lv) as $l) {
                $addN('alerts', $l['level'], (int) floor((strtotime($l['ts']) - $sinceT) / $bs), 1);
            }
        } elseif ($s === 'requests') {
            if (!cdp_reqmReady()) {
                continue;
            }
            $rf = sl_dvxReqFilter($src, $since, $q, $lv, $view);
            $only = ($rf['level'] ?? '') === 'NOTINFO';
            if ($only) {
                unset($rf['level']);
            }
            [$w, $a] = cdp_reqmWhere($rf);
            $slow = cdp_reqmSlowMs();
            $l = "(CASE WHEN status >= 500 THEN 'ERROR' WHEN status >= 400 OR ms >= $slow THEN 'WARN' ELSE 'INFO' END)";
            if ($only) {
                $w .= " AND $l <> 'INFO'";
            }
            foreach (cdp_reqmQ("SELECT FLOOR(TIMESTAMPDIFF(SECOND, ?, ts) / $bs) AS k, $l AS lvl, COUNT(*) AS n FROM cdb_dev_requests WHERE $w GROUP BY k, lvl", array_merge([$since], $a)) as $r) {
                $addN('requests', $r['lvl'], (int) $r['k'], (int) $r['n']);
            }
        } else {
            $x = sl_dvxSrcSql($s, $since, $q, $lv, $view);
            if (!$x) {
                continue;
            }
            [$t, $col, $w, $b, $l] = $x;
            $b[':from'] = $s === 'cron' ? sl_dvxToDb($since) : $since;
            foreach (sl_rows("SELECT FLOOR(TIMESTAMPDIFF(SECOND, :from, $col) / $bs) AS k, $l AS lvl, COUNT(*) AS n FROM $t WHERE $w GROUP BY k, lvl", $b) as $r) {
                $addN($s, (string) $r->lvl, (int) $r->k, (int) $r->n);
            }
        }
    }
    return $out;
}

/** Rows as the JSON the live tail sends (escaped by the browser with textContent; JSON pretty-printed there). */
function sl_dvxLogsPublic(array $rows): array
{
    return array_map(fn($r) => ['id' => $r['id'], 'ts' => $r['ts'], 'time' => $r['time'], 'source' => $r['source'], 'srcLabel' => $r['srcLabel'], 'service' => $r['service'], 'level' => $r['level'], 'lc' => $r['lc'],
        'message' => $r['message'], 'detail' => $r['detail'], 'raw' => $r['source'] === 'php' ? $r['raw'] : null], $rows);
}

/* ============================================================ sub-nav */

function sl_dvxCommon($user, string $section): array
{
    $ok = [];
    foreach (['overview', 'services', 'logs', 'metrics', 'alerts', 'deployments', 'issues'] as $k) {
        $ok[$k] = sl_dvxAllowed($user, $k);
    }
    return ['section' => $section, 'host' => (string) gethostname(), 'now' => date('Y-m-d H:i:s'), 'liveUrl' => 'developer.php', 'ajax' => 'ajax/sl/dev_observe_ajax.php',
        'cov' => cdp_reqmCoverage(), 'blockedProbe' => sl_dvxSelfProbeBlocked(), 'ok' => $ok];
}

/* ============================================================ overview */

/** KPI cards of the overview (and the live pulse): last 24 h vs the 24 h before, 30-day daily sparklines. */
function sl_dvxKpis(array $cov): array
{
    $now = time();
    $cur = cdp_reqmWindow($now - 86400, $now + 1);
    $prev = cdp_reqmWindow($now - 2 * 86400, $now - 86400);
    $days = cdp_reqmDaily(30);
    $dl = array_map(fn($x) => sl_date($x['day'], 'M j'), $days);
    $n30 = array_sum(array_column($days, 'n'));
    $e30 = array_sum(array_column($days, 'err5'));
    $since = $cov['first'] ? sl_date($cov['first'], 'M j') : null;
    $how = 'Recorded by helpers/dev_metrics.php from the first request after it was installed';
    return [
        ['k' => 'uptime', 't' => 'Uptime', 'icon' => 'i-activity', 'v' => $n30 ? sl_dvxNum(100 - $e30 / $n30 * 100, 2) : '—', 'u' => $n30 ? '%' : '', 'c' => '#34D399',
            'sub' => $n30 ? 'Requests without a server error, 30 d' . ($since ? ' (since ' . $since . ')' : '') : 'Not measured yet: ' . $how,
            'delta' => sl_dvxDelta($cur['n'] ? 100 - $cur['err_pct'] : null, $prev['n'] ? 100 - $prev['err_pct'] : null, false, 'pt'),
            'sp' => sl_dvxSpark(array_map(fn($x) => $x['has'] ? round(100 - (float) $x['err_pct'], 3) : null, $days), $dl, 'Uptime', '%'), 'href' => sl_route('DevMetrics', ['range' => '30d']) . '#errors'],
        ['k' => 'latency', 't' => 'Avg response time', 'icon' => 'i-clock', 'v' => $cur['avg'] !== null ? sl_num($cur['avg']) : '—', 'u' => $cur['avg'] !== null ? 'ms' : '', 'c' => '#60A5FA',
            'sub' => $cur['avg'] !== null ? 'p50 ' . sl_dvxMs($cur['p50']) . ' · p95 ' . sl_dvxMs($cur['p95']) . ' · 24 h' : 'Not measured yet: ' . $how,
            'delta' => sl_dvxDelta($cur['avg'], $prev['avg'], true), 'sp' => sl_dvxSpark(array_column($days, 'avg'), $dl, 'Avg response', 'ms'), 'href' => sl_route('DevMetrics') . '#latency'],
        ['k' => 'errors', 't' => 'Error rate', 'icon' => 'i-alert', 'v' => $cur['err_pct'] !== null ? sl_dvxNum($cur['err_pct'], 2) : '—', 'u' => $cur['err_pct'] !== null ? '%' : '', 'c' => '#F87171',
            'sub' => $cur['n'] ? sl_num($cur['err5']) . ' server errors (5xx) of ' . sl_num($cur['n']) . ' requests · 24 h' : 'Not measured yet: ' . $how,
            'delta' => sl_dvxDelta($cur['err_pct'], $prev['err_pct'], true, 'pt'), 'sp' => sl_dvxSpark(array_column($days, 'err_pct'), $dl, 'Error rate', '%'), 'href' => sl_route('DevLogs', ['level' => 'ERROR', 'window' => 1440])],
        ['k' => 'throughput', 't' => 'Throughput', 'icon' => 'i-bolt', 'v' => $cur['n'] ? sl_dvxNum($cur['rpm'], $cur['rpm'] < 10 ? 2 : 0) : '—', 'u' => $cur['n'] ? 'req/min' : '', 'c' => '#A78BFA',
            'sub' => $cur['n'] ? sl_num($cur['n']) . ' requests in 24 h (sampled at ' . round(cdp_reqmRate() * 100) . '%, weighted)' : 'Not measured yet: ' . $how,
            'delta' => sl_dvxDelta($cur['rpm'], $prev['n'] ? $prev['rpm'] : null, false), 'sp' => sl_dvxSpark(array_column($days, 'rpm'), $dl, 'Throughput', 'req/min'), 'href' => sl_route('DevMetrics') . '#traffic'],
    ];
}

/** Light live refresh for the overview (ajax op=pulse): KPI values and component health. */
function sl_dvxPulse(): array
{
    $k = array_map(fn($x) => ['k' => $x['k'], 'v' => $x['v'], 'u' => $x['u'], 'delta' => $x['delta'], 'sub' => $x['sub']], sl_dvxKpis(cdp_reqmCoverage()));
    $c = [];
    $cnt = ['operational' => 0, 'degraded' => 0, 'down' => 0, 'other' => 0];
    foreach (sl_dvxComponents() as $key => $x) {
        $c[] = ['key' => $key, 'cls' => $x['cls'], 'st' => $x['st'], 'sub' => $x['sub'], 'uptime' => $x['uptime'] !== null ? sl_dvxPct($x['uptime'], 2) : null];
        $cnt[isset($cnt[$x['status']]) ? $x['status'] : 'other']++;
    }
    return ['kpis' => $k, 'components' => $c, 'counts' => $cnt, 'alertsOpen' => count(cdp_devAlerts('open', 200)), 'at' => date('H:i:s')];
}



function sl_dvxOverviewData($user, $core): array
{
    $d = sl_dvxCommon($user, 'overview');
    $snap = sl_dvxSnap();
    $comp = sl_dvxComponents();
    $now = time();
    $range = in_array($_GET['range'] ?? '24h', ['24h', '7d', '30d'], true) ? (string) ($_GET['range'] ?? '24h') : '24h';
    $d['range'] = $range;
    $d['ranges'] = array_map(fn($r) => ['label' => $r, 'cls' => $r === $range ? 'on' : '', 'href' => sl_url(['range' => $r]) . '#charts'], ['24h', '7d', '30d']);

    $d['kpis'] = sl_dvxKpis($d['cov']);
    // a card whose detail page is not granted to this user links to the charts on this page instead
    foreach ($d['kpis'] as &$k) {
        $target = strpos($k['href'], 'dev_logs.php') === 0 ? 'logs' : (strpos($k['href'], 'dev_metrics.php') === 0 ? 'metrics' : 'overview');
        if (empty($d['ok'][$target])) {
            $k['href'] = '#charts';
        }
    }
    unset($k);

    // Architecture lanes
    $lane = fn(array $keys) => array_values(array_filter(array_map(fn($k) => $comp[$k] ?? null, $keys)));
    $d['arch'] = [
        ['t' => 'Clients', 'nodes' => $lane(['clients', 'api'])],
        ['t' => 'Application', 'nodes' => $lane(['web', 'cron', 'outbox'])],
        ['t' => 'Data', 'nodes' => $lane(['db', 'storage', 'backups'])],
        ['t' => 'External services', 'nodes' => $lane(['email', 'sms', 'whatsapp', 'payments', 'ai', 'github'])],
    ];
    $cnt = ['operational' => 0, 'degraded' => 0, 'down' => 0, 'other' => 0];
    foreach ($comp as $x) {
        $cnt[isset($cnt[$x['status']]) ? $x['status'] : 'other']++;
    }
    $d['counts'] = $cnt;
    $d['status'] = array_values($comp);

    // Charts
    $oc = sl_dvxOverviewCharts($range);
    $d['charts'] = $oc['charts'];
    $d['seriesSamples'] = $oc['samples'];

    // Alerts, deployments, logs
    $d['alerts'] = array_map(fn($a) => ['id' => $a['id'], 'sev' => $a['severity'], 't' => $a['title'], 's' => $a['service'] . ' · ' . $a['detail'], 'at' => sl_dvxAgo($a['first_seen']), 'st' => $a['status']], cdp_devAlerts('open', 6));
    $d['alertsOpen'] = count(cdp_devAlerts('open', 200));
    $d['deploys'] = sl_dvxDeploys(5, $snap);
    $d['logs'] = sl_dvxLogsPublic(sl_dvxLogs(['window' => 60, 'limit' => 14]));
    $d['srv'] = sl_dvxServer($snap);
    $d['dbv'] = $snap['db'] ?? [];
    return $d;
}

/** Recent deployments: package "deploy" (sl_ghDeployments: Actions runs, GitHub deployments, manual records, this checkout), else the git reflog. */
function sl_dvxDeploys(int $limit, array $snap): array
{
    if (!function_exists('sl_ghDeployments') && is_file(CDP_ROOT . '/helpers/dev_github.php')) {
        require_once CDP_ROOT . '/helpers/dev_github.php';
    }
    if (function_exists('sl_ghDeployments')) {
        try {
            $r = sl_ghDeployments();
            $rows = $r['all'] ?? [];
            if ($rows) {
                $map = ['success' => 'ok', 'failure' => 'failed', 'cancelled' => 'warn', 'running' => 'warn', 'queued' => 'warn', 'superseded' => 'ok', 'ok' => 'ok', 'failed' => 'failed'];
                return array_map(fn($x) => ['t' => (string) $x['title'], 's' => trim(($x['workflow'] ?? '') . ($x['sha'] ? ' · ' . substr((string) $x['sha'], 0, 7) : '') . ($x['branch'] ? ' · ' . $x['branch'] : '') . ($x['actor'] ? ' · ' . $x['actor'] : ''), ' ·'),
                    'at' => $x['at'] ? sl_dvxAgo((string) $x['at']) : '', 'state' => $map[$x['state']] ?? ($x['state'] === 'ok' ? 'ok' : 'warn'), 'href' => sl_route('DevDeployments')], array_slice($rows, 0, $limit));
            }
        } catch (Throwable $e) {
            // fall back to the local git history below
        }
    }
    $dep = cdp_devDeployments($snap['services'] ?? []);
    return array_map(fn($h) => ['t' => $h['message'], 's' => $h['author'] . ' · ' . $h['hash'] . ' · ' . $h['kind'], 'at' => sl_dvxAgo($h['ts']), 'state' => $h['kind'] === 'reset' ? 'warn' : 'ok', 'href' => sl_route('DevDeployments')],
        array_slice($dep['history'], 0, $limit));
}

/** Overview charts for a range (also served by ajax/sl/dev_observe_ajax.php op=charts). */
function sl_dvxOverviewCharts(string $range): array
{
    $series = cdp_reqmSeries($range);
    $pts = $series['points'];
    return ['samples' => $series['samples'], 'charts' => [
        sl_dvxChart('Response time', 'Average and 95th percentile of recorded requests', $pts, [['avg', '#60A5FA', 'avg'], ['p95', '#A78BFA', 'p95']], 'ms', 'latency'),
        sl_dvxChart('Error rate', 'Share of requests answered with a 5xx or a PHP fatal error', $pts, [['err', '#F87171', '5xx']], '%', 'errors'),
    ]];
}

/**
 * Collector samples (cdp_devSeries) placed on the request series' time grid, so every chart of a range
 * shares the same x axis; samples stamped ahead of the clock (CLI collector writing before the
 * application zone is applied) are brought back first (sl_dvxFixTs).
 */
function sl_dvxRegrid(array $cs, array $pts, int $bucket): array
{
    $keys = ['cpu', 'mem', 'rx', 'tx', 'dr', 'dw', 'qps'];
    $acc = [];
    $t0 = $pts ? (int) $pts[0]['t'] : 0;
    foreach ($cs as $r) {
        $t = strtotime((string) sl_dvxFixTs((string) $r['t'])[0]);
        if (!$t || !$pts) {
            continue;
        }
        $i = (int) floor(($t - $t0) / $bucket);
        if ($i < 0 || $i >= count($pts)) {
            continue;
        }
        foreach ($keys as $k) {
            if (($r[$k] ?? null) !== null) {
                $acc[$i][$k][] = (float) $r[$k];
            }
        }
    }
    $out = [];
    foreach ($pts as $i => $p) {
        $row = ['t' => $p['t'], 'label' => $p['label']];
        foreach ($keys as $k) {
            $row[$k] = !empty($acc[$i][$k]) ? round(array_sum($acc[$i][$k]) / count($acc[$i][$k]), 2) : null;
        }
        $out[] = $row;
    }
    return $out;
}

/** Metrics charts for a range: requests (recorder) and host / database history (collector, cdp_devSeries). */
function sl_dvxMetricsCharts(string $range): array
{
    $rs = cdp_reqmSeries($range);
    $pts = $rs['points'];
    // Collector history (cron/devops_collector.php or page views) for CPU %, network and disk I/O, queries/s
    $cs = cdp_devSeries($range);
    $cpts = sl_dvxRegrid($cs, $pts, (int) $rs['bucket']);
    $hasLoad = count(array_filter(array_column($pts, 'load'), fn($v) => $v !== null)) > 1;
    $hasMem = count(array_filter(array_column($pts, 'mem'), fn($v) => $v !== null)) > 1;
    return ['reqSamples' => $rs['samples'], 'colSamples' => count($cs), 'charts' => [
        sl_dvxChart('Request rate', 'Requests per minute (weighted samples)', $pts, [['rpm', '#A78BFA', 'req/min']], '', 'traffic'),
        sl_dvxChart('Response time', 'Average and p95 of recorded requests', $pts, [['avg', '#60A5FA', 'avg'], ['p95', '#F472B6', 'p95']], 'ms', 'latency'),
        sl_dvxChart('Error rate', 'Requests answered with a 5xx or PHP fatal error', $pts, [['err', '#F87171', '5xx']], '%', 'errors'),
        $range !== '30d' && $hasLoad ? sl_dvxChart('CPU load', '1-minute load average when requests ran (sys_getloadavg)', $pts, [['load', '#FBBF24', 'load']], '', 'cpu')
            : sl_dvxChart('CPU usage', 'Collector samples from /proc/stat', $cpts, [['cpu', '#FBBF24', 'CPU']], '%', 'cpu'),
        $range !== '30d' && $hasMem ? sl_dvxChart('Memory', 'Host memory in use when requests ran (/proc/meminfo)', $pts, [['mem', '#34D399', 'memory']], '%', 'mem')
            : sl_dvxChart('Memory', 'Collector samples from /proc/meminfo', $cpts, [['mem', '#34D399', 'memory']], '%', 'mem'),
        sl_dvxChart('Network I/O', 'Per second, all interfaces but lo (collector)', $cpts, [['rx', '#60A5FA', 'in'], ['tx', '#A78BFA', 'out']], 'B/s', 'net'),
        sl_dvxChart('Disk I/O', 'Per second read and written (collector, /proc/diskstats)', $cpts, [['dr', '#34D399', 'read'], ['dw', '#FBBF24', 'write']], 'B/s', 'diskio'),
        sl_dvxChart('Database queries', 'Queries per second (MySQL Questions counter, collector)', $cpts, [['qps', '#22D3EE', 'queries/s']], '', 'qps'),
    ]];
}

/** Legend values of a chart, formatted by unit. */
function sl_dvxLegendValue(array $ch, $v): string
{
    if ($v === null) {
        return '—';
    }
    if ($ch['unit'] === 'ms') {
        return sl_devMs($v);
    }
    if ($ch['unit'] === '%') {
        return sl_dvxPct($v, 2);
    }
    if ($ch['unit'] === 'B/s') {
        return sl_devBytes($v) . '/s';
    }
    return sl_num($v, $v < 10 ? 2 : 0);
}

/** Legend + chart markup of one chart card body (pages and the AJAX range switch use the same markup). */
function sl_dvxChartBody(array $ch): string
{
    $o = '<div class="dvx-lgd">';
    $at = '';
    $stale = false;
    foreach ($ch['paths'] as $p) {
        $o .= '<span><i style="background:' . h($p['c']) . '"></i>' . h($p['label']) . '<b class="mono">' . h(sl_dvxLegendValue($ch, $p['last'])) . '</b></span>';
        $at = $at ?: $p['lastAt'];
        $stale = $stale || $p['stale'];
    }
    if ($at !== '') {
        // the legend shows the latest bucket with data, not the range average: say which one
        $o .= '<small class="dvx-lgd-at" title="Legend values are the latest bucket with data, not the range average">' . ($stale ? 'last sample ' : 'latest bucket ') . h($at) . ($stale ? ' · no newer sample' : '') . '</small>';
    }
    return $o . '</div>' . sl_dvxChartSvg($ch);
}

/* ============================================================ services */

/** Log filter of each component's "recent events" (drawer, ajax op=svc). */
function sl_dvxSvcLogMap(): array
{
    return ['web' => ['source' => 'requests'], 'api' => ['source' => 'requests', 'q' => 'api/'], 'clients' => ['source' => 'requests'], 'cron' => ['source' => 'cron'],
        'outbox' => ['source' => 'messages'], 'email' => ['source' => 'messages', 'q' => 'email'], 'sms' => ['source' => 'messages', 'q' => 'sms'], 'whatsapp' => ['source' => 'messages', 'q' => 'whatsapp'],
        'ai' => ['source' => 'ai'], 'db' => ['source' => 'php', 'q' => 'SQL'], 'payments' => ['source' => 'activity', 'view' => 'payments']];
}

function sl_dvxServicesData($user, $core): array
{
    $d = sl_dvxCommon($user, 'services');
    $comp = sl_dvxComponents();
    $f = in_array($_GET['filter'] ?? 'all', ['all', 'ok', 'degraded', 'down', 'other'], true) ? (string) ($_GET['filter'] ?? 'all') : 'all';
    $q = trim((string) ($_GET['q'] ?? ''));
    $grp = fn($s) => $s === 'operational' ? 'ok' : (in_array($s, ['degraded', 'down'], true) ? $s : 'other');
    $cnt = ['all' => count($comp), 'ok' => 0, 'degraded' => 0, 'down' => 0, 'other' => 0];
    foreach ($comp as $x) {
        $cnt[$grp($x['status'])]++;
    }
    $d['counts'] = $cnt;
    $d['filter'] = $f;
    $d['q'] = $q;
    $d['tabs'] = array_map(fn($t) => ['k' => $t[0], 'label' => $t[1], 'n' => $cnt[$t[0]], 'cls' => $f === $t[0] ? 'on' : '', 'href' => sl_url(['filter' => $t[0] === 'all' ? null : $t[0], 'svc' => null])],
        [['all', 'All'], ['ok', 'Healthy'], ['degraded', 'Degraded'], ['down', 'Down'], ['other', 'Not set up / idle']]);
    $rows = [];
    foreach ($comp as $k => $x) {
        $x['grp'] = $grp($x['status']);
        $x['hidden'] = ($f !== 'all' && $f !== $x['grp']) || ($q !== '' && stripos($x['name'] . ' ' . $x['tech'] . ' ' . $x['detail'], $q) === false);
        $rows[] = $x;
    }
    $d['rows'] = $rows;
    $sel = (string) ($_GET['svc'] ?? '');
    $d['sel'] = isset($comp[$sel]) ? $sel : '';
    // Configuration lines shown in the drawer of the web / database rows. Credentials never show a
    // single character, whatever their length: only whether they are set.
    $d['env'] = array_map(function ($e) {
        if (!empty($e['secret']) || in_array($e['k'], ['CDP_DB_PASS', 'CDP_DB_USER'], true)) {
            $set = defined($e['k']) && (string) constant($e['k']) !== '';
            $e['v'] = $set ? 'set (hidden)' : 'empty';
        }
        return $e;
    }, sl_devConfig());
    $d['php'] = sl_dvxServer(sl_dvxSnap())['php'];
    // Recent log lines per component for the drawer (24 h, max 5 each)
    $d['recent'] = [];
    $map = sl_dvxSvcLogMap();
    if ($d['sel'] && isset($map[$d['sel']])) {
        $d['recent'][$d['sel']] = sl_dvxLogsPublic(sl_dvxLogs($map[$d['sel']] + ['window' => 1440, 'limit' => 6]));
    }
    $d['blocked'] = sl_dvxSelfProbeBlocked();
    // cross-section buttons only for sections this user may open
    $d['logsOk'] = sl_dvxAllowed($user, 'logs');
    $d['metricsOk'] = sl_dvxAllowed($user, 'metrics');
    $d['issuesOk'] = sl_dvxAllowed($user, 'issues');
    return $d;
}

/* ================================================================ logs */

function sl_dvxLogsData($user, $core): array
{
    $d = sl_dvxCommon($user, 'logs');
    $view = isset(sl_dvxViews()[(string) ($_GET['view'] ?? '')]) ? (string) $_GET['view'] : '';
    $src = array_key_exists((string) ($_GET['source'] ?? ''), sl_dvxSources()) ? (string) $_GET['source'] : '';
    if ($view !== '') {
        $src = sl_dvxViews()[$view][0];
    }
    $lv = in_array($_GET['level'] ?? '', ['INFO', 'WARN', 'ERROR'], true) ? (string) $_GET['level'] : '';
    $win = in_array((int) ($_GET['window'] ?? 60), [15, 60, 360, 1440, 10080], true) ? (int) ($_GET['window'] ?? 60) : 60;
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 120);
    $d += ['source' => $src, 'level' => $lv, 'window' => $win, 'q' => $q, 'view' => $view, 'viewLabel' => $view !== '' ? sl_dvxViews()[$view][1] : ''];
    $d['sources'] = sl_dvxSources();
    $d['windows'] = [15 => 'Last 15 min', 60 => 'Last hour', 360 => 'Last 6 h', 1440 => 'Last 24 h', 10080 => 'Last 7 days'];
    $d['levels'] = array_map(fn($l) => ['v' => $l, 'label' => $l ?: 'All levels', 'cls' => $lv === $l ? 'on' : '', 'href' => sl_url(['level' => $l ?: null, 'row' => null])], ['', 'INFO', 'WARN', 'ERROR']);
    $f = ['source' => $src, 'level' => $lv, 'window' => $win, 'q' => $q, 'view' => $view];
    $rows = sl_dvxLogs($f + ['limit' => 300]);
    $d['rows'] = $rows;
    $d['listCap'] = 300;
    // Counts over the whole window (COUNT / GROUP BY), not over the 300 listed rows
    $nb = 60;
    $cnt = sl_dvxLogCounts($f, $nb);
    $d['total'] = $cnt['total'];
    $d['nErr'] = $cnt['lv']['e'];
    $d['nWarn'] = $cnt['lv']['w'];
    // chips: each source on its own with the other filters (so a chip says what clicking it opens)
    $d['bySource'] = [];
    if ($view === '') {
        foreach (array_keys(sl_dvxSources()) as $k) {
            $d['bySource'][$k] = $src === '' || $src === $k ? (int) ($cnt['bySource'][$k] ?? 0) : null;
        }
    }
    $mx = 1;
    foreach ($cnt['hist'] as $hb) {
        $mx = max($mx, $hb['i'] + $hb['w'] + $hb['e']);
    }
    $now = time();
    $span = $win * 60;
    $bs = (int) ceil($span / $nb);
    $d['hist'] = [];
    foreach ($cnt['hist'] as $i => $hb) {
        $t = $now - $span + $i * $bs;
        $d['hist'][] = ['i' => sl_pct($hb['i'], $mx), 'w' => sl_pct($hb['w'], $mx), 'e' => sl_pct($hb['e'], $mx), 'title' => date($win > 1440 ? 'M j H:i' : 'H:i', (int) $t) . ': ' . $hb['i'] . ' info, ' . $hb['w'] . ' warn, ' . $hb['e'] . ' error'];
    }
    $d['histFrom'] = date($win > 1440 ? 'M j H:i' : 'H:i', $now - $span);
    $d['phpCapped'] = $src === '' || $src === 'php';
    $sel = (string) ($_GET['row'] ?? '');
    $d['sel'] = null;
    foreach ($rows as $r) {
        if ($r['id'] === $sel) {
            $d['sel'] = $r;
        }
    }
    $d['selMissing'] = $sel !== '' && !$d['sel'];
    $d['sel'] = $d['sel'] ?: ($rows[0] ?? null); // newest row
    $d['phpPath'] = sl_devErrorLogPath();
    $d['phpReadable'] = $d['phpPath'] !== '' && is_readable($d['phpPath']);
    $d['cronReady'] = sl_tableExists('cdb_cron_runs');
    $d['newest'] = $rows[0]['ts'] ?? date('Y-m-d H:i:s', $now - 1);
    if ($d['newest'] > date('Y-m-d H:i:s')) {
        $d['newest'] = date('Y-m-d H:i:s');
    }
    // Saved views (24 h): each count comes from the same filter its link opens
    $saved = [
        ['Server errors (5xx)', ['source' => 'requests', 'level' => 'ERROR']],
        ['Slow requests', ['view' => 'slow']],
        ['PHP errors and warnings', ['source' => 'php']],
        ['Failed actions', ['source' => 'activity', 'level' => 'ERROR']],
        ['Failed sign-ins', ['view' => 'signin']],
        ['Message delivery errors', ['source' => 'messages', 'level' => 'ERROR']],
        ['Failed cron runs', ['source' => 'cron', 'level' => 'ERROR']],
    ];
    $d['saved'] = [];
    foreach ($saved as [$t, $sf]) {
        $sf += ['source' => '', 'level' => '', 'view' => '', 'q' => '', 'window' => 1440];
        $c = sl_dvxLogCounts($sf, 1);
        $d['saved'][] = ['t' => $t, 'n' => $c['total'], 'on' => $sf['view'] === $view && $sf['source'] === $src && $sf['level'] === $lv && $q === '' && $win === 1440,
            'href' => sl_url(['source' => $sf['source'] ?: null, 'level' => $sf['level'] ?: null, 'view' => $sf['view'] ?: null, 'window' => 1440, 'q' => null, 'row' => null])];
    }
    $d['issuesOk'] = sl_dvxAllowed($user, 'issues');
    return $d;
}

/** Event as the compact JSON a log line carries (detail panel; pretty-printed in the browser). */
function sl_dvxEvAttr(array $r): string
{
    return (string) json_encode(['id' => $r['id'], 'ts' => $r['ts'], 'level' => $r['level'], 'source' => $r['source'], 'srcLabel' => $r['srcLabel'], 'service' => $r['service'], 'message' => $r['message'],
        'detail' => $r['detail'], 'raw' => $r['source'] === 'php' ? $r['raw'] : null], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}

/** Issue form link for a log event: id, source and the message only (never the raw line in a URL). */
function sl_dvxTicketHref(array $r): string
{
    return sl_route('DevIssues', ['new' => 1, 'title' => mb_substr((string) $r['message'], 0, 120), 'page' => mb_substr((string) $r['service'], 0, 60),
        'log' => 'Log event ' . $r['id'] . ' (' . ($r['srcLabel'] ?? $r['source']) . ', ' . $r['ts'] . '): ' . mb_substr((string) $r['message'], 0, 300) . "\n" . 'Full entry: ' . sl_route('DevLogs', ['source' => $r['source'], 'window' => 10080, 'row' => $r['id']])]);
}

/* ============================================================= metrics */

function sl_dvxMetricsData($user, $core): array
{
    $d = sl_dvxCommon($user, 'metrics');
    $snap = sl_dvxSnap();
    $now = time();
    $range = in_array($_GET['range'] ?? '24h', ['1h', '24h', '7d', '30d'], true) ? (string) ($_GET['range'] ?? '24h') : '24h';
    $span = cdp_reqmRanges()[$range][0];
    $d['range'] = $range;
    $d['ranges'] = array_map(fn($r) => ['label' => $r, 'cls' => $r === $range ? 'on' : '', 'href' => sl_url(['range' => $r])], ['1h', '24h', '7d', '30d']);
    $srv = sl_dvxServer($snap);
    $db = $snap['db'] ?? [];
    $d['srv'] = $srv;
    $d['db'] = $db;
    $win = $range === '30d' ? null : cdp_reqmWindow($now - $span, $now + 1);
    if ($win === null) {
        $days = cdp_reqmDaily(30);
        $n = array_sum(array_column($days, 'n'));
        $p95s = array_filter(array_column($days, 'p95'), fn($v) => $v !== null);
        $win = ['n' => $n, 'err5' => array_sum(array_column($days, 'err5')), 'avg' => $n ? round(array_sum(array_map(fn($x) => ($x['avg'] ?? 0) * $x['n'], $days)) / $n, 1) : null,
            'p50' => null, 'p95' => $p95s ? max($p95s) : null, 'rpm' => round($n / (30 * 1440), 2), 'err_pct' => $n ? round(array_sum(array_column($days, 'err5')) / $n * 100, 3) : null];
    }
    $d['win'] = $win;
    $load = $srv['load'];
    $cores = max(1, (int) $srv['cores']);
    $d['tiles'] = [
        ['k' => 'cpu', 't' => 'CPU load', 'v' => $load ? sl_dvxNum($load[0], 2) : '—', 'u' => $load ? '1-min load' : '', 'sub' => $load ? 'on ' . $cores . ' cores' . (!empty($srv['ovr']['cores']) ? ' (override)' : '') . ' · ' . round(min(999, $load[0] / $cores * 100)) . '% of capacity · 5 / 15 min ' . $load[1] . ' / ' . $load[2] : 'Not measured: sys_getloadavg() is disabled on this host', 'w' => $load ? sl_pct($load[0], $cores) : 0, 'ovr' => !empty($srv['ovr']['cores']), 'href' => '#cpu'],
        ['k' => 'mem', 't' => 'Memory', 'v' => $srv['mem_pct'] !== null ? sl_dvxNum($srv['mem_pct'], 1) : '—', 'u' => $srv['mem_pct'] !== null ? '%' : '', 'sub' => $srv['mem_total'] ? sl_devBytes($srv['mem_used']) . ' of ' . sl_devBytes($srv['mem_total']) . ' · ' . $srv['mem_src'] . ($srv['notes'] ? ' · ' . implode(' ', array_filter($srv['notes'], fn($n) => strpos($n, 'Memory') === 0)) : '') : 'Not measured: /proc/meminfo is not readable (shared hosting). PHP peak ' . sl_devBytes(memory_get_peak_usage(true)) . '. A super admin can set the plan\'s RAM as an override.', 'w' => (int) round((float) ($srv['mem_pct'] ?? 0)), 'ovr' => !empty($srv['ovr']['mem_total']) || !empty($srv['ovr']['mem_used']), 'href' => '#mem'],
        ['k' => 'disk', 't' => 'Disk', 'v' => $srv['disk_pct'] !== null ? (string) $srv['disk_pct'] : '—', 'u' => $srv['disk_pct'] !== null ? '%' : '', 'sub' => $srv['disk_total'] ? sl_devBytes($srv['disk_used']) . ' of ' . sl_devBytes($srv['disk_total']) . ' used · ' . sl_devBytes(max(0, (float) $srv['disk_free'])) . ' free · ' . $srv['disk_src'] . ($srv['notes'] ? ' · ' . implode(' ', array_filter($srv['notes'], fn($n) => strpos($n, 'Disk') === 0)) : '') : 'Not measured', 'w' => (int) ($srv['disk_pct'] ?? 0), 'ovr' => !empty($srv['ovr']['disk_total']) || !empty($srv['ovr']['disk_used']), 'href' => '#disk'],
        ['k' => 'db', 't' => 'Database', 'v' => !empty($db['up']) ? sl_devBytes($db['size_bytes'] ?? 0) : 'down', 'u' => '', 'sub' => !empty($db['up']) ? (int) ($db['connections'] ?? 0) . ' / ' . (int) ($db['max_connections'] ?? 0) . ' connections · ' . (int) ($db['running'] ?? 0) . ' running · ping ' . sl_dvxMs($db['ping_ms'] ?? null) : 'No answer', 'w' => sl_pct($db['connections'] ?? 0, $db['max_connections'] ?? 0), 'href' => '#db'],
        ['k' => 'rpm', 't' => 'Request rate', 'v' => $win['n'] ? sl_dvxNum($win['rpm'], $win['rpm'] < 10 ? 2 : 0) : '—', 'u' => $win['n'] ? 'req/min' : '', 'sub' => sl_num($win['n']) . ' requests in ' . $range, 'w' => 0, 'href' => '#traffic'],
        ['k' => 'lat', 't' => 'Response time', 'v' => $win['p95'] !== null ? sl_num($win['p95']) : '—', 'u' => $win['p95'] !== null ? 'ms p95' : '', 'sub' => 'p50 ' . sl_dvxMs($win['p50']) . ' · avg ' . sl_dvxMs($win['avg']), 'w' => 0, 'href' => '#latency'],
        ['k' => 'err', 't' => 'Error rate', 'v' => $win['err_pct'] !== null ? sl_dvxNum($win['err_pct'], 2) : '—', 'u' => $win['err_pct'] !== null ? '%' : '', 'sub' => sl_num($win['err5']) . ' server errors in ' . $range, 'w' => 0, 'href' => '#errors'],
    ];
    // Charts: request recorder + collector history
    $mc = sl_dvxMetricsCharts($range);
    $d['charts'] = $mc['charts'];
    $d['reqSamples'] = $mc['reqSamples'];
    $d['colSamples'] = $mc['colSamples'];
    $d['collectorScheduled'] = (bool) (function_exists('cdp_devState') && cdp_devState('collector_last'));
    // Route tables (raw rows cover the retention window; 30 d uses the last 7 days of raw rows)
    $from = $range === '30d' ? $now - cdp_reqmRetentionDays() * 86400 : $now - $span;
    $d['routeWindow'] = $range === '30d' ? 'last ' . cdp_reqmRetentionDays() . ' days (raw retention)' : $range;
    $d['slow'] = cdp_reqmRoutes($from, $now + 1, 'slow', 10);
    $d['busy'] = cdp_reqmRoutes($from, $now + 1, 'busy', 10);
    $d['errRoutes'] = array_values(array_filter(cdp_reqmRoutes($from, $now + 1, 'errors', 10), fn($r) => $r['err5'] + $r['err4'] > 0));
    // Database
    $d['mysql'] = sl_devMysql();
    $d['dbSize'] = sl_devDbSize();
    $d['tables'] = array_map(fn($t) => ['t' => $t->t, 'n' => sl_num($t->n), 'size' => sl_devBytes((float) $t->d + (float) $t->i), 'b' => (float) $t->d + (float) $t->i], sl_devTableSizes(10));
    $mx = $d['tables'] ? max(array_column($d['tables'], 'b')) : 1;
    foreach ($d['tables'] as &$t) {
        $t['w'] = sl_pct($t['b'], $mx);
    }
    unset($t);
    // What this host cannot measure
    $nm = [];
    if (!$srv['load']) $nm[] = ['CPU load', 'sys_getloadavg() is disabled', 'Ask the host to allow it, or read CPU in the hosting control panel.'];
    if ($srv['mem_total'] === null) $nm[] = ['Memory', '/proc/meminfo is not readable', 'Set the plan\'s RAM as a super-admin override (Infrastructure › Overrides).'];
    if (!$d['colSamples']) $nm[] = ['CPU %, network and disk I/O, queries/s history', 'No collector samples in this range', 'Schedule * * * * * php ' . CDP_ROOT . '/cron/devops_collector.php'];
    if (!$d['cov']['rows']) $nm[] = ['Requests, latency and errors', 'No request recorded yet', 'The recorder is armed in loader.php; it fills as soon as pages are opened (CDP_REQ_METRICS must not be false).'];
    $nm[] = ['Traces', 'Per-request spans are not collected', 'This console records one row per request (route, status, duration, peak memory); use the slowest routes table to find hot spots.'];
    if (sl_dvxSelfProbeBlocked()) $nm[] = ['HTTP self-probe', 'Skipped on the single-threaded PHP built-in server', 'Run under Apache / PHP-FPM, or set PHP_CLI_SERVER_WORKERS=4.'];
    $d['notMeasured'] = $nm;
    return $d;
}
