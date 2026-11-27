<?php
/**
 * DevServices - monitoring table of every component of this installation (dark DevOps theme).
 * Data: views/sl/data/dev_observe.php sl_dvxServicesData / sl_dvxComponents (live checks).
 * Search and status filter work in the browser (dataJs/sl/dev_observe.js) and without JavaScript (GET).
 * Each row opens a detail drawer; ?svc=key opens it server-side.
 */
if (!isset($d)) { $d = []; }
$c = $d['counts'];
$dvxActive = 'services';
include CDP_ROOT . '/views/sl/pages/partials/dev_subnav.php';
?>
<div class="dvx-page" data-dvx-page="services" data-dvx-ajax="<?= h($d['ajax']) ?>">
<header class="dvx-head">
  <div class="col g4"><h1 class="dvx-title">Services</h1><p class="dvx-lede"><?= (int) $c['all'] ?> components · <span class="dvx-ok"><?= (int) $c['ok'] ?> healthy</span> · <span class="dvx-warn"><?= (int) $c['degraded'] ?> degraded</span> · <span class="dvx-bad"><?= (int) $c['down'] ?> down</span> · <?= (int) $c['other'] ?> not set up or idle · checked <?= h(date('H:i:s')) ?></p></div>
  <div class="row g8 wrap"><a class="dvx-btn" href="<?= h(sl_url(['svc' => null])) ?>"><i class="ic ic-sm i-refresh" aria-hidden="true"></i>Check again</a></div>
</header>
<?php if (!empty($d['blocked'])): ?><div class="dvx-note" role="note"><i class="ic i-info" aria-hidden="true"></i><span>This copy runs on the single-threaded PHP built-in server, so the web and API checks use the recorded requests of the last 15 minutes instead of an HTTP request to itself.</span></div><?php endif; ?>

<section class="dvx-card dvx-flush">
  <form class="dvx-toolbar" method="get" data-dvx-filter>
    <div class="dvx-tabs" role="group" aria-label="Status"><?php foreach ($d['tabs'] as $t): ?><a class="<?= h($t['cls']) ?>" href="<?= h($t['href']) ?>" data-dvx-tab="<?= h($t['k']) ?>"><?= h($t['label']) ?> <span class="mono"><?= (int) $t['n'] ?></span></a><?php endforeach; ?></div>
    <span class="grow"></span>
    <?php if ($d['filter'] !== 'all'): ?><input type="hidden" name="filter" value="<?= h($d['filter']) ?>"><?php endif; ?>
    <label class="dvx-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q']) ?>" placeholder="Search services" aria-label="Search services" data-dvx-q></label>
  </form>
  <div class="dvx-tw"><table class="dvx-table" data-dvx-rows>
    <thead><tr><th>Service</th><th>Status</th><th class="num">Check</th><th class="num" title="Success share over 24 h from the component ledger">Uptime</th><th>Throughput</th><th>Last error</th><th class="dvx-c-at" title="When the check ran, or for ledger-based parts the last activity it saw">Checked / activity</th></tr></thead>
    <tbody>
    <?php foreach ($d['rows'] as $r): ?>
      <tr id="svc-<?= h($r['key']) ?>" class="<?= $d['sel'] === $r['key'] ? 'on' : '' ?>" data-dvx-row="<?= h($r['key']) ?>" data-grp="<?= h($r['grp']) ?>" data-text="<?= h(strtolower($r['name'] . ' ' . $r['tech'] . ' ' . $r['detail'])) ?>"<?= $r['hidden'] ? ' hidden' : '' ?>>
        <td><a class="dvx-svc" href="<?= h(sl_url(['svc' => $r['key']])) ?>#svc-<?= h($r['key']) ?>" data-dvx-open="<?= h($r['key']) ?>"><span class="dvx-node-i"><i class="ic ic-sm <?= h($r['icon']) ?>" aria-hidden="true"></i></span><span class="col dvx-min"><b><?= h($r['name']) ?></b><small class="mono dvx-ell"><?= h($r['tech']) ?></small></span></a></td>
        <?php $atTxt = $r['at_label'] === 'checked' ? 'checked ' . $r['at_ago'] : ($r['checked_at'] ? 'activity ' . $r['at_ago'] : 'no activity'); ?>
        <td><span class="dvx-pill <?= h($r['cls']) ?>"><i class="dvx-dot <?= h($r['cls']) ?>" aria-hidden="true"></i><?= h($r['st']) ?></span><small class="dvx-mut dvx-at-in"><?= h($atTxt) ?></small></td>
        <td class="num mono"><?= h(sl_dvxMs($r['check_ms'])) ?></td>
        <td class="num mono" title="<?= h($r['uptime_label']) ?>"><?= $r['uptime'] !== null ? h(sl_dvxPct($r['uptime'], 2)) : '<span class="dvx-mut">—</span>' ?></td>
        <td class="sm"><?= h($r['thr']) ?></td>
        <td class="sm dvx-err<?= $r['last_error'] ? '' : ' dvx-noerr' ?>"><?php if ($r['last_error']): ?><span class="dvx-ell" title="<?= h($r['last_error']) ?>"><?= h($r['last_error']) ?></span><?php if ($r['last_error_at']): ?><small class="dvx-mut"><?= h(sl_dvxAgo($r['last_error_at'])) ?></small><?php endif; ?><?php else: ?><span class="dvx-mut">None</span><?php endif; ?></td>
        <td class="sm dvx-mut dvx-c-at"><?= h($atTxt) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="dvx-empty" data-dvx-none<?= count(array_filter($d['rows'], fn($r) => !$r['hidden'])) ? ' hidden' : '' ?>>No service matches this filter.</div></div>
</section>

<?php foreach ($d['rows'] as $r): $open = $d['sel'] === $r['key']; ?>
<aside class="dvx-drawer" id="dvx-d-<?= h($r['key']) ?>" data-dvx-drawer="<?= h($r['key']) ?>" role="dialog" aria-modal="false" aria-labelledby="dvx-dh-<?= h($r['key']) ?>"<?= $open ? '' : ' hidden' ?>>
  <div class="dvx-drawer-h"><span class="dvx-node-i lg"><i class="ic <?= h($r['icon']) ?>" aria-hidden="true"></i></span><div class="col grow dvx-min"><h2 class="dvx-ct" id="dvx-dh-<?= h($r['key']) ?>"><?= h($r['name']) ?></h2><small class="mono dvx-mut dvx-ell"><?= h($r['tech']) ?></small></div><a class="dvx-ib" href="<?= h(sl_url(['svc' => null])) ?>" data-dvx-close aria-label="Close details"><i class="ic ic-sm i-x" aria-hidden="true"></i></a></div>
  <div class="dvx-drawer-b">
    <span class="dvx-pill <?= h($r['cls']) ?>"><i class="dvx-dot <?= h($r['cls']) ?>" aria-hidden="true"></i><?= h($r['st']) ?></span>
    <p class="dvx-p"><?= h($r['detail']) ?></p>
    <dl class="dvx-dl">
      <dt>Health check</dt><dd><?= h($r['check']) ?></dd>
      <dt>Check time</dt><dd class="mono"><?= h(sl_dvxMs($r['check_ms'])) ?></dd>
      <dt>Success / uptime</dt><dd><span class="mono"><?= $r['uptime'] !== null ? h(sl_dvxPct($r['uptime'], 3)) : '—' ?></span> <small class="dvx-mut"><?= h($r['uptime_label']) ?></small></dd>
      <dt>Throughput</dt><dd><?= h($r['thr']) ?></dd>
      <dt>Last error</dt><dd><?= $r['last_error'] ? h($r['last_error']) . ($r['last_error_at'] ? ' <small class="dvx-mut">' . h(sl_date((string) sl_dvxFixTs((string) $r['last_error_at'])[0], 'M j H:i')) . '</small>' : '') : '<span class="dvx-mut">None recorded</span>' ?></dd>
      <dt><?= $r['at_label'] === 'checked' ? 'Last checked' : 'Last activity' ?></dt><dd class="mono"><?= $r['checked_at'] ? h(sl_dvxFixTs((string) $r['checked_at'])[0]) . ' <small class="dvx-mut">' . h($r['at_ago']) . '</small>' : '<span class="dvx-mut">none recorded</span>' ?></dd>
    </dl>
    <?php if ($r['key'] === 'web' && !empty($d['php'])): $p = $d['php']; ?>
    <div class="dvx-code"><span class="c"># PHP runtime</span>
<span class="k">version</span>=<span class="s"><?= h($p['version'] ?? PHP_VERSION) ?></span> <span class="k">sapi</span>=<span class="s"><?= h($p['sapi'] ?? PHP_SAPI) ?></span>
<span class="k">memory_limit</span>=<span class="s"><?= h((string) ($p['memory_limit'] ?? '')) ?></span> <span class="k">max_execution_time</span>=<span class="s"><?= h((string) ($p['max_execution_time'] ?? '')) ?></span>
<span class="k">opcache</span>=<span class="s"><?= !empty($p['opcache']) ? 'on' : 'off' ?></span> <span class="k">extensions</span>=<span class="s"><?= h(implode(',', (array) ($p['extensions'] ?? []))) ?></span></div>
    <?php endif; ?>
    <?php if (in_array($r['key'], ['web', 'db'], true)): ?>
    <div class="dvx-code"><span class="c"># config/config.php · secrets masked</span>
<?php foreach ($d['env'] as $e): ?><span class="k"><?= h($e['k']) ?></span>=<span class="s"><?= h($e['v']) ?></span>
<?php endforeach; ?></div>
    <?php endif; ?>
    <?php if (isset($d['recent'][$r['key']])): ?>
    <h3 class="dvx-h3">Recent events · 24 h</h3>
    <div class="dvx-term sm"><?php foreach ($d['recent'][$r['key']] as $l): ?><a class="dvx-ln" href="<?= $d['logsOk'] ? h(sl_route('DevLogs', ['source' => $l['source'], 'window' => 1440, 'row' => $l['id']])) : '#' ?>"><span class="ts"><?= h($l['time']) ?></span><span class="lv <?= h($l['lc']) ?>"><?= h($l['level']) ?></span><span class="msg"><?= h($l['message']) ?></span></a><?php endforeach; ?><?php if (!$d['recent'][$r['key']]): ?><div class="dvx-ln"><span class="msg">Nothing logged in 24 h.</span></div><?php endif; ?></div>
    <?php elseif (!$open): ?>
    <p class="dvx-mut sm" data-dvx-recent="<?= h($r['key']) ?>"></p>
    <?php endif; ?>
    <?php $lk = $r['logs']; $toLogs = strpos($lk, 'dev_logs.php') === 0; $toMetrics = strpos($lk, 'dev_metrics.php') === 0; ?>
    <div class="row g8 wrap"><?php if (($toLogs && $d['logsOk']) || ($toMetrics && $d['metricsOk']) || (!$toLogs && !$toMetrics)): ?><a class="dvx-btn sm" href="<?= h($lk) ?>"><i class="ic ic-sm i-terminal" aria-hidden="true"></i><?= $toMetrics ? 'Details' : ($toLogs ? 'Logs' : 'Open') ?></a><?php endif; ?><?php if ($d['metricsOk'] && !$toMetrics): ?><a class="dvx-btn sm" href="<?= h(sl_route('DevMetrics')) ?>"><i class="ic ic-sm i-activity" aria-hidden="true"></i>Metrics</a><?php endif; ?><?php if ($d['issuesOk']): ?><a class="dvx-btn sm" href="<?= h(sl_route('DevIssues', ['new' => 1, 'title' => $r['name'] . ' ' . strtolower($r['st']), 'log' => mb_substr($r['name'] . ': ' . $r['st'] . ' · ' . $r['detail'], 0, 400), 'page' => $r['name']])) ?>"><i class="ic ic-sm i-bug" aria-hidden="true"></i>Open a ticket</a><?php endif; ?></div>
  </div>
</aside>
<?php endforeach; ?>
<div class="dvx-scrim" data-dvx-scrim<?= $d['sel'] ? '' : ' hidden' ?>></div>
</div>
<?= sl_dvxSubnavEnd() ?>
