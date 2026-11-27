<?php
/**
 * DevOverview - DevOps overview of this installation (dark DevOps theme, assets/swiftlane/dev_theme.css).
 * Data: views/sl/data/dev_observe.php sl_dvxOverviewData (request recorder helpers/dev_metrics.php,
 * live snapshot helpers/devops.php, alerts, deployments, merged logs). Live parts: dataJs/sl/dev_observe.js.
 */
if (!isset($d)) { $d = []; }
$c = $d['counts'];
$cov = $d['cov'];
$dvxActive = 'overview';
include CDP_ROOT . '/views/sl/pages/partials/dev_subnav.php';
?>
<div class="dvx-page" data-dvx-page="overview" data-dvx-ajax="<?= h($d['ajax']) ?>">
<header class="dvx-head">
  <div class="col g4"><h1 class="dvx-title">DevOps overview</h1><p class="dvx-lede"><span class="mono"><?= h($d['host']) ?></span> · PHP <?= h(PHP_VERSION) ?> · <?= h((string) ($d['dbv']['version'] ?? 'database down')) ?> · <span class="dvx-ok"><?= (int) $c['operational'] ?> healthy</span> · <span class="dvx-warn"><?= (int) $c['degraded'] ?> degraded</span> · <span class="dvx-bad"><?= (int) $c['down'] ?> down</span></p></div>
  <div class="row g8 wrap"><span class="dvx-live" data-dvx-live><i class="dvx-pulse" aria-hidden="true"></i><span data-dvx-live-t>Live</span></span><?php if ($d['ok']['services']): ?><a class="dvx-btn" href="<?= h(sl_route('DevServices')) ?>"><i class="ic ic-sm i-server" aria-hidden="true"></i>Services</a><?php endif; ?><?php if ($d['ok']['logs']): ?><a class="dvx-btn" href="<?= h(sl_route('DevLogs')) ?>"><i class="ic ic-sm i-terminal" aria-hidden="true"></i>Logs</a><?php endif; ?><a class="dvx-btn" href="<?= h(sl_self()) ?>"><i class="ic ic-sm i-refresh" aria-hidden="true"></i>Refresh</a></div>
</header>
<?php if (!$cov['rows']): ?><div class="dvx-note" role="note"><i class="ic i-info" aria-hidden="true"></i><span>Request metrics are starting: the recorder in <span class="mono">helpers/dev_metrics.php</span> keeps a sample of every request from now on, so uptime, response time, error rate and throughput fill in as the site is used.</span></div><?php endif; ?>

<section class="dvx-kpis" aria-label="Key figures">
<?php foreach ($d['kpis'] as $k): ?>
  <a class="dvx-kpi" href="<?= h($k['href']) ?>" data-dvx-kpi="<?= h($k['k']) ?>" style="--k:<?= h($k['c']) ?>">
    <span class="dvx-kpi-t"><i class="ic ic-sm <?= h($k['icon']) ?>" aria-hidden="true"></i><?= h($k['t']) ?></span>
    <span class="dvx-kpi-v"><b data-dvx-v><?= h($k['v']) ?></b><small><?= h($k['u']) ?></small></span>
    <span class="dvx-delta <?= h($k['delta']['c']) ?>"><?= h($k['delta']['t']) ?></span>
    <?php if ($k['sp']['line']): ?><svg <?= $k['sp']['attrs'] ?> class="dvx-spark" viewBox="0 0 240 44" preserveAspectRatio="none" aria-hidden="true"><?php if ($k['sp']['area']): ?><path d="<?= h($k['sp']['area']) ?>" fill="var(--k)" opacity=".14"></path><?php endif; ?><path d="<?= h($k['sp']['line']) ?>" fill="none" stroke="var(--k)" stroke-width="1.8" vector-effect="non-scaling-stroke"></path></svg><?php else: ?><span class="dvx-spark-empty">30-day trend appears after two days of data</span><?php endif; ?>
    <span class="dvx-kpi-s"><?= h($k['sub']) ?></span>
  </a>
<?php endforeach; ?>
</section>

<div class="dvx-grid dvx-g-arch">
<section class="dvx-card" aria-labelledby="dvx-arch-h">
  <div class="dvx-ch"><div><h2 class="dvx-ct" id="dvx-arch-h">System architecture</h2><p class="dvx-cs">The parts of this installation and how they connect · health from live checks · click a part for its detail</p></div><span class="dvx-legend"><span><i style="background:#34D399"></i>Healthy</span><span><i style="background:#FBBF24"></i>Degraded</span><span><i style="background:#F87171"></i>Down</span><span><i style="background:#64748B"></i>Not set up</span></span></div>
  <div class="dvx-arch">
  <?php foreach ($d['arch'] as $i => $lane): ?>
    <div class="dvx-lane"><span class="dvx-lane-t"><?= h($lane['t']) ?></span><div class="dvx-lane-n">
    <?php foreach ($lane['nodes'] as $n): ?>
      <a class="dvx-node <?= h($n['cls']) ?>" href="<?= h($n['href']) ?>" title="<?= h($n['st'] . ': ' . $n['detail']) ?>"><span class="dvx-node-i"><i class="ic ic-sm <?= h($n['icon']) ?>" aria-hidden="true"></i></span><span class="col dvx-node-b"><b><?= h($n['name']) ?></b><small><?= h($n['sub']) ?></small></span><span class="dvx-dot <?= h($n['cls']) ?>" role="img" aria-label="<?= h($n['st']) ?>"></span></a>
    <?php endforeach; ?>
    </div></div>
    <?php if ($i < count($d['arch']) - 1): ?><span class="dvx-flow" aria-hidden="true"></span><?php endif; ?>
  <?php endforeach; ?>
  </div>
</section>
<section class="dvx-card" aria-labelledby="dvx-st-h">
  <div class="dvx-ch"><h2 class="dvx-ct" id="dvx-st-h">System status</h2><a class="dvx-link" href="<?= h(sl_route('DevServices')) ?>">All services</a></div>
  <div class="dvx-status">
  <?php foreach ($d['status'] as $s): ?>
    <a class="dvx-st-row" href="<?= h($s['href']) ?>"><span class="dvx-dot <?= h($s['cls']) ?>" aria-hidden="true"></span><span class="grow dvx-ell"><?= h($s['name']) ?></span><span class="dvx-st-u mono" title="<?= h($s['uptime_label']) ?>"><?= $s['uptime'] !== null ? h(sl_dvxPct($s['uptime'], 2)) : '<span class="dvx-mut">' . h($s['st']) . '</span>' ?></span></a>
  <?php endforeach; ?>
  </div>
  <p class="dvx-cs">Percentages are success shares over 24 h from each part's own ledger (requests, cron runs, message log, AI runs); hover for the source.</p>
</section>
</div>

<div class="dvx-grid dvx-g2" id="charts">
<?php foreach ($d['charts'] as $ch): ?>
<section class="dvx-card" id="<?= h($ch['id']) ?>">
  <div class="dvx-ch"><div><h2 class="dvx-ct"><?= h($ch['t']) ?></h2><p class="dvx-cs"><?= h($ch['sub']) ?></p></div><div class="dvx-seg" role="group" aria-label="Range"><?php foreach ($d['ranges'] as $r): ?><a class="<?= h($r['cls']) ?>" href="<?= h($r['href']) ?>" data-dvx-range="<?= h($r['label']) ?>"<?= $r['cls'] ? ' aria-current="true"' : '' ?>><?= h($r['label']) ?></a><?php endforeach; ?></div></div>
  <div class="dvx-chart-body" data-dvx-chart="<?= h($ch['id']) ?>"><?= sl_dvxChartBody($ch) ?></div>
</section>
<?php endforeach; ?>
</div>

<div class="dvx-grid dvx-g2">
<section class="dvx-card" aria-labelledby="dvx-al-h">
  <div class="dvx-ch"><h2 class="dvx-ct" id="dvx-al-h">Active alerts <span class="dvx-badge <?= $d['alertsOpen'] ? 'bad' : '' ?>"><?= (int) $d['alertsOpen'] ?></span></h2><a class="dvx-link" href="<?= h(sl_route('DevAlerts')) ?>">Manage alerts</a></div>
  <?php foreach ($d['alerts'] as $a): ?>
    <a class="dvx-row" href="<?= h(sl_route('DevAlerts')) ?>"><span class="dvx-sev <?= $a['sev'] === 'critical' ? 'bad' : 'warn' ?>"><?= h(ucfirst($a['sev'])) ?></span><span class="col grow dvx-min"><b class="dvx-ell"><?= h($a['t']) ?></b><small class="dvx-ell"><?= h($a['s']) ?></small></span><span class="dvx-mut sm"><?= h($a['at']) ?><?= $a['st'] === 'acknowledged' ? ' · ack' : '' ?></span></a>
  <?php endforeach; ?>
  <?php if (!$d['alerts']): ?><div class="dvx-empty"><i class="ic i-check" aria-hidden="true"></i>No open alerts.</div><?php endif; ?>
</section>
<section class="dvx-card" aria-labelledby="dvx-dp-h">
  <div class="dvx-ch"><h2 class="dvx-ct" id="dvx-dp-h">Recent deployments</h2><a class="dvx-link" href="<?= h(sl_route('DevDeployments')) ?>">All deployments</a></div>
  <?php foreach ($d['deploys'] as $dp): ?>
    <a class="dvx-row" href="<?= h($dp['href']) ?>"><span class="dvx-dot <?= $dp['state'] === 'ok' ? 'ok' : ($dp['state'] === 'failed' ? 'bad' : 'warn') ?>" aria-hidden="true"></span><span class="col grow dvx-min"><b class="dvx-ell"><?= h($dp['t']) ?></b><small class="dvx-ell mono"><?= h($dp['s']) ?></small></span><span class="dvx-mut sm"><?= h($dp['at']) ?></span></a>
  <?php endforeach; ?>
  <?php if (!$d['deploys']): ?><div class="dvx-empty">No deployment recorded: this checkout has no git history.</div><?php endif; ?>
</section>
</div>

<section class="dvx-card" aria-labelledby="dvx-lt-h">
  <div class="dvx-ch"><div><h2 class="dvx-ct" id="dvx-lt-h">Live logs</h2><p class="dvx-cs">All sources, newest first · refreshes every 5 s</p></div><div class="row g8"><button class="dvx-btn sm" type="button" data-dvx-pause aria-pressed="false"><i class="ic ic-sm i-clock" aria-hidden="true"></i><span>Pause</span></button><?php if ($d['ok']['logs']): ?><a class="dvx-link" href="<?= h(sl_route('DevLogs')) ?>">Open log explorer</a><?php endif; ?></div></div>
  <div class="dvx-term" data-dvx-tail data-dvx-since="<?= h($d['logs'][0]['ts'] ?? $d['now']) ?>" data-dvx-max="14" role="log" aria-live="off" aria-label="Live log lines">
  <?php foreach ($d['logs'] as $l): ?>
    <a class="dvx-ln" data-dvx-id="<?= h($l['id']) ?>" href="<?= $d['ok']['logs'] ? h(sl_route('DevLogs', ['source' => $l['source'], 'window' => 1440, 'row' => $l['id']])) : '#' ?>"><span class="ts"><?= h($l['time']) ?></span><span class="lv <?= h($l['lc']) ?>"><?= h($l['level']) ?></span><span class="sv"><?= h($l['service']) ?></span><span class="msg"><?= h($l['message']) ?></span></a>
  <?php endforeach; ?>
  <?php if (!$d['logs']): ?><div class="dvx-ln dvx-ln-empty" data-dvx-empty><span class="msg">No log line in the last hour. New lines appear here as they are written.</span></div><?php endif; ?>
  </div>
</section>
</div>
<?= sl_dvxSubnavEnd() ?>
