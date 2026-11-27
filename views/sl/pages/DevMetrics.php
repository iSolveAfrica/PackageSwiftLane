<?php
/**
 * DevMetrics - metrics and traces (dark DevOps theme). Request series from helpers/dev_metrics.php,
 * host series from the collector (cdb_dev_metrics via cdp_devSeries), MySQL from SHOW GLOBAL STATUS
 * and information_schema. Data: views/sl/data/dev_observe.php sl_dvxMetricsData.
 * Anything this host cannot measure is listed under "Not measured on this host".
 */
if (!isset($d)) { $d = []; }
$my = $d['mysql'];
$dvxActive = 'metrics';
include CDP_ROOT . '/views/sl/pages/partials/dev_subnav.php';
$routeTable = function (array $rows, string $empty) {
    if (!$rows) {
        return '<div class="dvx-empty">' . h($empty) . '</div>';
    }
    $o = '<div class="dvx-tw"><table class="dvx-table dvx-rt"><thead><tr><th>Route</th><th class="num">Requests</th><th class="num">p50</th><th class="num">p95</th><th class="num">Max</th><th class="num">5xx</th><th class="num">4xx</th><th class="num">Peak memory</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $o .= '<tr><td><a class="dvx-route" href="' . h(sl_route('DevLogs', ['source' => 'requests', 'q' => $r['route'], 'window' => 1440])) . '" title="/' . h($r['route']) . '"><span class="dvx-kind ' . h($r['kind']) . '">' . h(strtoupper($r['kind'])) . '</span><span class="mono dvx-ell">/' . h($r['route']) . '</span></a></td>'
            . '<td class="num mono">' . h(sl_num($r['n'])) . '</td><td class="num mono">' . h(sl_dvxMs($r['p50'])) . '</td><td class="num mono' . ($r['p95'] >= 1000 ? ' dvx-warn' : '') . '">' . h(sl_dvxMs($r['p95'])) . '</td><td class="num mono">' . h(sl_dvxMs($r['max'])) . '</td>'
            . '<td class="num mono' . ($r['err5'] ? ' dvx-bad' : ' dvx-mut') . '">' . (int) $r['err5'] . '</td><td class="num mono' . ($r['err4'] ? ' dvx-warn' : ' dvx-mut') . '">' . (int) $r['err4'] . '</td><td class="num mono">' . h(sl_devBytes($r['mem_kb'] * 1024)) . '</td></tr>';
    }
    return $o . '</tbody></table></div>';
};
// Errors by route: only the columns that matter, so the counts stay visible in a half-width card
$errTable = function (array $rows, string $empty) {
    if (!$rows) {
        return '<div class="dvx-empty">' . h($empty) . '</div>';
    }
    $o = '<div class="dvx-tw"><table class="dvx-table dvx-rt"><thead><tr><th>Route</th><th class="num">5xx</th><th class="num">4xx</th><th class="num">Requests</th><th class="num">Error %</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $o .= '<tr><td><a class="dvx-route" href="' . h(sl_route('DevLogs', ['source' => 'requests', 'q' => $r['route'], 'window' => 1440, 'level' => $r['err5'] ? 'ERROR' : 'WARN'])) . '" title="/' . h($r['route']) . '"><span class="dvx-kind ' . h($r['kind']) . '">' . h(strtoupper($r['kind'])) . '</span><span class="mono dvx-ell">/' . h($r['route']) . '</span></a></td>'
            . '<td class="num mono' . ($r['err5'] ? ' dvx-bad' : ' dvx-mut') . '">' . (int) $r['err5'] . '</td><td class="num mono' . ($r['err4'] ? ' dvx-warn' : ' dvx-mut') . '">' . (int) $r['err4'] . '</td>'
            . '<td class="num mono">' . h(sl_num($r['n'])) . '</td><td class="num mono">' . h(sl_dvxPct(($r['err5'] + $r['err4']) / max(1, $r['n']) * 100, 1)) . '</td></tr>';
    }
    return $o . '</tbody></table></div>';
};
?>
<div class="dvx-page" data-dvx-page="metrics" data-dvx-ajax="<?= h($d['ajax']) ?>">
<header class="dvx-head">
  <div class="col g4"><h1 class="dvx-title">Metrics &amp; traces</h1><p class="dvx-lede">Measured on <span class="mono"><?= h($d['host']) ?></span> · <?= h(sl_num($d['reqSamples'])) ?> request samples and <?= (int) $d['colSamples'] ?> collector samples in range<?= $d['cov']['first'] ? ' · requests recorded since ' . h(sl_date($d['cov']['first'], 'M j, H:i')) : '' ?></p></div>
  <div class="dvx-seg boxed" role="group" aria-label="Time range"><?php foreach ($d['ranges'] as $r): ?><a class="<?= h($r['cls']) ?>" href="<?= h($r['href']) ?>"<?= $r['cls'] ? ' aria-current="true"' : '' ?>><?= h($r['label']) ?></a><?php endforeach; ?></div>
</header>

<section class="dvx-tiles" aria-label="Current figures">
<?php foreach ($d['tiles'] as $t): ?>
  <a class="dvx-tile" href="<?= h($t['href']) ?>" data-dvx-tile="<?= h($t['k']) ?>"><span class="dvx-kpi-t"><?= h($t['t']) ?><?php if (!empty($t['ovr'])): ?><span class="dvx-ovr" title="Set by a super admin, not measured">override</span><?php endif; ?></span><span class="dvx-kpi-v"><b><?= h($t['v']) ?></b><small><?= h($t['u']) ?></small></span><?php if ($t['w']): ?><span class="dvx-meter <?= $t['w'] >= 90 ? 'bad' : ($t['w'] >= 75 ? 'warn' : '') ?>" role="img" aria-label="<?= (int) $t['w'] ?>%"><i style="width:<?= (int) $t['w'] ?>%"></i></span><?php endif; ?><span class="dvx-kpi-s"><?= h($t['sub']) ?></span></a>
<?php endforeach; ?>
</section>

<div class="dvx-grid dvx-g2">
<?php foreach ($d['charts'] as $ch): ?>
<section class="dvx-card" id="<?= h($ch['id']) ?>">
  <div class="dvx-ch"><div><h2 class="dvx-ct"><?= h($ch['t']) ?></h2><p class="dvx-cs"><?= h($ch['sub']) ?></p></div></div>
  <div class="dvx-chart-body" data-dvx-chart="<?= h($ch['id']) ?>"><?= sl_dvxChartBody($ch) ?></div>
  <?php if (!$ch['has'] && in_array($ch['id'], ['net', 'diskio', 'qps', 'cpu', 'mem'], true) && !$d['colSamples']): ?><p class="dvx-cs">Not measured in this range: these come from the collector. Schedule <span class="mono">* * * * * php cron/devops_collector.php</span>.</p><?php endif; ?>
</section>
<?php endforeach; ?>
</div>

<section class="dvx-card dvx-flush" aria-labelledby="dvx-slow-h">
  <div class="dvx-ch pad"><div><h2 class="dvx-ct" id="dvx-slow-h">Slowest routes</h2><p class="dvx-cs">By 95th percentile · <?= h($d['routeWindow']) ?> · click a route for its requests</p></div></div>
  <?= $routeTable($d['slow'], 'No request recorded in this range.') ?>
</section>
<section class="dvx-card dvx-flush" aria-labelledby="dvx-er-h">
  <div class="dvx-ch pad"><div><h2 class="dvx-ct" id="dvx-er-h">Errors by route</h2><p class="dvx-cs">Server (5xx) and client (4xx) errors · <?= h($d['routeWindow']) ?></p></div></div>
  <?= $errTable($d['errRoutes'], 'No error response recorded in this range.') ?>
</section>
<section class="dvx-card dvx-flush" aria-labelledby="dvx-busy-h">
  <div class="dvx-ch pad"><div><h2 class="dvx-ct" id="dvx-busy-h">Top endpoints</h2><p class="dvx-cs">Most requested · <?= h($d['routeWindow']) ?></p></div></div>
  <?php if ($d['busy']): $mx = max(1, $d['busy'][0]['n']); ?>
  <div class="dvx-bars"><?php foreach ($d['busy'] as $r): ?><a class="dvx-bar" href="<?= h(sl_route('DevLogs', ['source' => 'requests', 'q' => $r['route'], 'window' => 1440])) ?>"><span class="mono dvx-ell">/<?= h($r['route']) ?></span><span class="dvx-bar-t"><i style="width:<?= sl_pct($r['n'], $mx) ?>%"></i></span><b class="mono"><?= h(sl_num($r['n'])) ?></b><small class="dvx-mut mono"><?= h(sl_dvxMs($r['avg'])) ?></small></a><?php endforeach; ?></div>
  <?php else: ?><div class="dvx-empty">No request recorded in this range.</div><?php endif; ?>
</section>
<div class="dvx-grid dvx-g2" id="db">
<section class="dvx-card" aria-labelledby="dvx-my-h">
  <div class="dvx-ch"><h2 class="dvx-ct" id="dvx-my-h">Database</h2><span class="dvx-mut sm mono"><?= h($my['version']) ?></span></div>
  <dl class="dvx-dl wide">
    <dt>Ping</dt><dd class="mono"><?= h(sl_dvxMs($d['db']['ping_ms'] ?? $my['ms'])) ?> <small class="dvx-mut">median of 3, same check as Services</small></dd>
    <dt>Connections</dt><dd class="mono"><?= (int) $my['threads'] ?> / <?= (int) $my['max_conn'] ?></dd>
    <dt>Size</dt><dd class="mono"><?= h(sl_devBytes($d['dbSize']['bytes'])) ?> · <?= (int) $d['dbSize']['tables'] ?> tables · ~<?= h(sl_num($d['dbSize']['rows'])) ?> rows</dd>
    <dt>Queries since start</dt><dd class="mono"><?= h(sl_num($my['questions'])) ?></dd>
    <dt>Slow queries since start</dt><dd class="mono"><?= h(sl_num($my['slow'])) ?></dd>
    <dt>Slow query log</dt><dd class="mono"><?= ($my['slow_log'] ?? '0') == '1' ? 'on' : 'off' ?> · long_query_time <?= h((string) ($my['long_query'] ?? '')) ?> s</dd>
    <dt>Uptime</dt><dd class="mono"><?= h(cdp_devDur((int) $my['uptime'])) ?></dd>
  </dl>
</section>
<section class="dvx-card" aria-labelledby="dvx-tb-h" id="disk">
  <div class="dvx-ch"><h2 class="dvx-ct" id="dvx-tb-h">Largest tables</h2></div>
  <div class="dvx-bars"><?php foreach ($d['tables'] as $t): ?><div class="dvx-bar"><span class="mono dvx-ell"><?= h($t['t']) ?></span><span class="dvx-bar-t"><i style="width:<?= (int) $t['w'] ?>%"></i></span><b class="mono"><?= h($t['size']) ?></b><small class="dvx-mut mono"><?= h($t['n']) ?> rows</small></div><?php endforeach; ?></div>
</section>
</div>

<section class="dvx-card" aria-labelledby="dvx-nm-h">
  <div class="dvx-ch"><h2 class="dvx-ct" id="dvx-nm-h">Not measured on this host</h2></div>
  <div class="dvx-nm"><?php foreach ($d['notMeasured'] as [$what, $why, $how]): ?><div class="dvx-nm-i"><b><?= h($what) ?></b><span class="dvx-mut"><?= h($why) ?></span><span class="sm"><?= h($how) ?></span></div><?php endforeach; ?></div>
  <p class="dvx-cs">Request recorder: sampling <?= round($d['cov']['rate'] * 100) ?>% of normal requests (every error and slow request is kept), raw rows kept <?= (int) $d['cov']['retention'] ?> days, daily rollups 400 days. Settings: <span class="mono">CDP_REQ_METRICS</span>, <span class="mono">CDP_REQ_METRICS_RATE</span>, <span class="mono">CDP_REQ_METRICS_SLOW_MS</span>, <span class="mono">CDP_REQ_METRICS_DAYS</span> in config/config.php.</p>
</section>
</div>
<?= sl_dvxSubnavEnd() ?>
