<?php
/**
 * DevInfrastructure - what runs this installation (dark DevOps theme, assets/swiftlane/dev_theme.css).
 * Data: views/sl/data/dev_infra.php sl_dinfInfraData (measured: /proc samples, disk_*_space, MySQL,
 * directory walk; super-admin overrides from cdb_dev_overrides, always labelled "set by ... on ...").
 * Writes: ajax/sl/dev_infra_ajax.php (ovr_save, ovr_clear, rescan). Behaviour: dataJs/sl/dev_infra.js.
 */
if (!isset($d)) { $d = []; }
$dvxActive = 'infrastructure';
include CDP_ROOT . '/views/sl/pages/partials/dev_subnav.php';
$q = $d['queue'];
$b = $d['backup'];
$pl = $d['plan'];
$tone = ['ok' => 'ok', 'warn' => 'warn', 'bad' => 'bad', 'off' => 'off', 'info' => 'idle'];
?>
<div class="dvx-page dinf" data-dinf-page="infra">
<header class="dvx-head">
  <div class="col g4"><h1 class="dvx-title">Infrastructure</h1><p class="dvx-lede"><span class="mono"><?= h($d['host']) ?></span> · PHP <?= h(PHP_VERSION) ?> · <?= (int) $d['samples'] ?> server samples in 24 h<?php if ($d['ovrSet']): ?> · <span class="dinf-ovr-t"><?= (int) $d['ovrSet'] ?> figure<?= $d['ovrSet'] === 1 ? '' : 's' ?> overridden by a super admin</span><?php endif; ?></p></div>
  <div class="row g8 wrap"><span class="dvx-live"><i class="dvx-pulse" aria-hidden="true"></i>Measured <?= h(date('H:i:s')) ?></span><a class="dvx-btn" href="#overrides"><i class="ic ic-sm i-sliders" aria-hidden="true"></i>Overrides</a><a class="dvx-btn" href="<?= h(sl_self()) ?>"><i class="ic ic-sm i-refresh" aria-hidden="true"></i>Refresh</a></div>
</header>
<?php if ($d['samples'] < 2): ?><div class="dvx-note" role="note"><i class="ic i-info" aria-hidden="true"></i><span>Usage trends come from the server samples the console stores at most once a minute while a developer page is open. Schedule <span class="mono">cron/devops_collector.php</span> every minute for a continuous history.</span></div><?php endif; ?>

<section class="dinf-kpis" aria-label="Resource usage">
<?php foreach ($d['kpis'] as $k): ?>
  <a class="dinf-kpi <?= h($k['tone']) ?>" href="<?= h($k['href']) ?>" style="--k:<?= h($k['c']) ?>">
    <span class="dinf-kpi-t"><i class="ic ic-sm <?= h($k['icon']) ?>" aria-hidden="true"></i><?= h($k['t']) ?><?php if ($k['ovr']): ?><span class="dvx-ovr" title="<?= h($k['ovr']) ?>">override</span><?php endif; ?></span>
    <span class="dinf-kpi-v"><b><?= h($k['v']) ?></b><small><?= h($k['u']) ?></small></span>
    <?php if ($k['pct'] !== null): ?><span class="dvx-meter <?= $k['tone'] === 'bad' ? 'bad' : ($k['tone'] === 'warn' ? 'warn' : '') ?>"><i style="width:<?= min(100, max(1, (float) $k['pct'])) ?>%;background:<?= $k['tone'] === 'ok' ? h($k['c']) : '' ?>"></i></span><?php endif; ?>
    <span class="dinf-kpi-s"><?= h($k['sub']) ?></span>
    <?php if ($k['sp']['line']): ?><svg <?= $k['sp']['attrs'] ?> class="dvx-spark" viewBox="0 0 240 44" preserveAspectRatio="none" aria-hidden="true"><?php if ($k['sp']['area']): ?><path d="<?= h($k['sp']['area']) ?>" fill="var(--k)" opacity=".14"></path><?php endif; ?><path d="<?= h($k['sp']['line']) ?>" fill="none" stroke="var(--k)" stroke-width="1.8" vector-effect="non-scaling-stroke"></path></svg><?php else: ?><span class="dvx-spark-empty dinf-spark-empty">24 h trend: needs two samples</span><?php endif; ?>
    <?php if ($k['how']): ?><span class="dinf-how"><?= h($k['how']) ?></span><?php endif; ?>
    <?php if ($k['ovr']): ?><span class="dinf-by"><?= h($k['ovr']) ?></span><?php endif; ?>
  </a>
<?php endforeach; ?>
</section>

<div class="dvx-grid dinf-g-store" id="storage">
  <section class="dvx-card" aria-labelledby="dinf-dist">
    <div class="dvx-ch"><div><h2 class="dvx-ct" id="dinf-dist">Resource distribution</h2><p class="dvx-cs">Disk space by what uses it · <?= h($d['donut']['total']) ?> <?= $d['donut']['ovr'] ? 'total' : 'disk' ?></p></div><?php if ($d['donut']['ovr']): ?><span class="dvx-ovr" title="<?= h($d['donut']['ovr']) ?>">override</span><?php endif; ?></div>
    <div class="dinf-donut-w">
      <svg class="dinf-donut" viewBox="0 0 132 132" role="img" aria-label="Disk space distribution">
        <circle cx="66" cy="66" r="52" fill="none" stroke="var(--active)" stroke-width="16"></circle>
        <?php foreach ($d['donut']['segs'] as $s): if ($s['pct'] <= 0) continue; ?><circle class="dinf-seg" cx="66" cy="66" r="52" fill="none" stroke="<?= h($s['c']) ?>" stroke-width="16" stroke-dasharray="<?= h($s['dash']) ?>" stroke-dashoffset="<?= h((string) $s['off']) ?>" transform="rotate(-90 66 66)"><title><?= h($s['label'] . ': ' . $s['size'] . ' (' . $s['pct'] . '%)') ?></title></circle><?php endforeach; ?>
        <circle cx="66" cy="66" r="36" fill="none" stroke="var(--active)" stroke-width="9"></circle>
        <?php foreach ($d['donut']['inner'] as $s): if ($s['pct'] <= 0) continue; ?><circle class="dinf-seg in" cx="66" cy="66" r="36" fill="none" stroke="<?= h($s['c']) ?>" stroke-width="9" stroke-dasharray="<?= h($s['dash']) ?>" stroke-dashoffset="<?= h((string) $s['off']) ?>" transform="rotate(-90 66 66)"><title><?= h($s['label'] . ': ' . $s['size'] . ' (' . $s['pct'] . '% of app data)') ?></title></circle><?php endforeach; ?>
        <text x="66" y="65" text-anchor="middle" class="dinf-donut-v"><?= h($d['donut']['center']) ?></text><text x="66" y="78" text-anchor="middle" class="dinf-donut-s"><?= h($d['donut']['centerSub']) ?></text>
      </svg>
      <ul class="dinf-legend">
        <?php foreach ($d['donut']['segs'] as $s): ?><li><a href="<?= h($s['href']) ?>"><i style="background:<?= h($s['c']) ?>"></i><span class="dvx-ell"><?= h($s['label']) ?></span><b class="mono"><?= h($s['size']) ?></b><small class="mono"><?= h(rtrim(rtrim(number_format($s['pct'], 1), '0'), '.')) ?>%</small></a></li><?php endforeach; ?>
      </ul>
    </div>
    <div class="dinf-rings"><span><i aria-hidden="true"></i>Outer ring: whole disk</span><span><i class="in" aria-hidden="true"></i>Inner ring: the app's own data (<?= h($d['donut']['app']) ?>)</span></div>
    <?php if ($d['donut']['ovr']): ?><p class="dinf-by">Disk figures <?= h($d['donut']['ovr']) ?>.</p><?php endif; ?>
  </section>
  <section class="dvx-card dvx-flush" aria-labelledby="dinf-store">
    <div class="dvx-ch pad"><div><h2 class="dvx-ct" id="dinf-store">Storage breakdown</h2><p class="dvx-cs">App folders (scanned <?= h(sl_ago($d['storage']['at'])) ?><?= $d['storage']['partial'] ? ', partial: very many files' : '' ?>) and the database · <?= h(sl_dinfPlural((int) $d['storage']['files'], 'file')) ?></p></div>
      <?php if ($d['canScan']): ?><button type="button" class="dvx-btn sm" data-sl-action="<?= h($d['ajax']) ?>" data-p-op="rescan"><i class="ic ic-sm i-refresh" aria-hidden="true"></i>Re-scan</button><?php endif; ?></div>
    <div class="dinf-store">
      <?php foreach ($d['storeRows'] as $g): ?>
      <div class="dinf-srow" id="store-<?= h($g['key']) ?>">
        <span class="dinf-sw" style="background:<?= h($g['c']) ?>"></span>
        <span class="col dvx-min"><b><?= h($g['label']) ?></b><small class="dvx-ell" title="<?= h(implode(', ', $g['paths'])) ?>"><?= h($g['what']) ?> · <span class="mono"><?= h(implode(' ', $g['paths']) ?: 'no folder yet') ?></span></small></span>
        <span class="dvx-meter dinf-sbar"><i style="width:<?= max(1, (float) $g['pct']) ?>%;background:<?= h($g['c']) ?>"></i></span>
        <span class="col dinf-snum"><b class="mono"><?= h($g['size']) ?></b><small><?= $g['files'] ? h(sl_dinfPlural((int) $g['files'], 'file')) : '&nbsp;' ?></small></span>
      </div>
      <?php endforeach; ?>
      <?php if ($d['inodes']): ?><div class="dinf-srow dinf-lim"><span class="dinf-sw" style="background:var(--violet)"></span><span class="col dvx-min"><b>File count vs plan limit</b><small><?= sl_num($d['inodes']['files']) ?> app files of <?= sl_num($d['inodes']['limit']) ?> · limit <?= h($d['inodes']['by']) ?></small></span><span class="dvx-meter dinf-sbar <?= ($d['inodes']['pct'] ?? 0) >= 90 ? 'bad' : (($d['inodes']['pct'] ?? 0) >= 80 ? 'warn' : '') ?>"><i style="width:<?= min(100, max(1, (float) $d['inodes']['pct'])) ?>%"></i></span><span class="col dinf-snum"><b class="mono"><?= h((string) $d['inodes']['pct']) ?>%</b><small>&nbsp;</small></span></div><?php endif; ?>
    </div>
  </section>
</div>

<div class="dvx-grid dinf-g-top">
  <section class="dvx-card" aria-labelledby="dinf-topo">
    <div class="dvx-ch"><div><h2 class="dvx-ct" id="dinf-topo">Nodes and zones</h2><p class="dvx-cs">The real parts of this installation, grouped by where they run · same health checks as Overview · click a part for its detail</p></div><a class="dvx-link" href="<?= h(sl_route('DevServices')) ?>">All services</a></div>
    <div class="dinf-zones">
      <?php foreach ($d['zones'] as $z): ?>
      <div class="dinf-zone <?= h($z['tone']) ?> <?= $z['k'] === 'host' ? 'host' : '' ?>">
        <div class="dinf-zone-h"><span class="dvx-dot <?= h($z['tone']) ?>" aria-hidden="true"></span><b><?= h($z['label']) ?></b><small><?= h($z['sub']) ?></small></div>
        <div class="dinf-nodes">
          <?php foreach ($z['nodes'] as $n): ?><a class="dvx-node <?= h($n['cls']) ?>" href="<?= h($n['href']) ?>" title="<?= h($n['st'] . ': ' . $n['detail']) ?>"><span class="dvx-node-i"><i class="ic ic-sm <?= h($n['icon']) ?>" aria-hidden="true"></i></span><span class="col dvx-node-b"><b><?= h($n['name']) ?></b><small><?= h($n['sub']) ?></small></span><span class="dvx-dot <?= h($n['cls']) ?>" role="img" aria-label="<?= h($n['st']) ?>"></span></a><?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (!$d['zones']): ?><div class="dvx-empty"><i class="ic i-info" aria-hidden="true"></i>Component checks are not available.</div><?php endif; ?>
    </div>
  </section>
  <section class="dvx-card" aria-labelledby="dinf-facts">
    <div class="dvx-ch"><div><h2 class="dvx-ct" id="dinf-facts">Server facts</h2><p class="dvx-cs">From PHP, the operating system and MySQL</p></div></div>
    <dl class="dvx-dl dinf-facts">
      <?php foreach ($d['facts'] as $f): ?><dt><?= h($f['k']) ?></dt><dd class="<?= !empty($f['unset']) ? 'dvx-mut' : 'mono' ?>"><?= h($f['v']) ?><?php if (!empty($f['ovr'])): ?><span class="dvx-ovr" title="<?= h($f['ovr']) ?>">override</span><small class="dinf-by"><?= h($f['ovr']) ?></small><?php endif; ?><?php if (!empty($f['unset']) && $d['canEdit']): ?> · <a class="dvx-link" href="#overrides">set</a><?php endif; ?></dd><?php endforeach; ?>
    </dl>
  </section>
</div>

<div class="dvx-grid dinf-g-plan">
  <section class="dvx-card" aria-labelledby="dinf-plan">
    <div class="dvx-ch"><div><h2 class="dvx-ct" id="dinf-plan">Plan and capacity</h2><p class="dvx-cs">Limits only the provider knows, entered by a super admin, against what this server measures</p></div></div>
    <div class="dinf-cap">
      <a class="dinf-cap-i" href="<?= h(sl_route('CostSettings')) ?>"><span class="dinf-cap-t"><i class="ic ic-sm i-dollar" aria-hidden="true"></i>Monthly server cost<?php if ($pl['costOvr']): ?><span class="dvx-ovr">override</span><?php endif; ?></span><b class="mono"><?= h($pl['cost'] ?? '—') ?></b><small><?= h($pl['costSrc']) ?></small></a>
      <a class="dinf-cap-i" href="#ovr-bandwidth_limit_gb"><span class="dinf-cap-t"><i class="ic ic-sm i-activity" aria-hidden="true"></i>Bandwidth this month<?php if ($pl['bw']): ?><span class="dvx-ovr">override</span><?php endif; ?></span>
        <?php if ($pl['bw']): ?><b class="mono"><?= $pl['bw']['used'] !== null ? h(rtrim(rtrim(number_format($pl['bw']['used'], 2), '0'), '.')) . ' GB' : '—' ?><?= $pl['bw']['limit'] !== null ? ' / ' . h(rtrim(rtrim(number_format($pl['bw']['limit'], 2), '0'), '.')) . ' GB' : '' ?></b><?php if ($pl['bw']['pct'] !== null): ?><span class="dvx-meter <?= $pl['bw']['pct'] >= 90 ? 'bad' : ($pl['bw']['pct'] >= 80 ? 'warn' : '') ?>"><i style="width:<?= min(100, max(1, $pl['bw']['pct'])) ?>%"></i></span><?php endif; ?><small><?= h($pl['bw']['by']) ?></small>
        <?php else: ?><b class="mono dvx-mut">not measured</b><small>PHP cannot read the host's traffic meter: copy it from the control panel.</small><?php endif; ?></a>
      <a class="dinf-cap-i" href="#ovr-db_limit_mb"><span class="dinf-cap-t"><i class="ic ic-sm i-database" aria-hidden="true"></i>Database size<?php if ($pl['db']['limit']): ?><span class="dvx-ovr">override</span><?php endif; ?></span><b class="mono"><?= h($pl['db']['size']) ?><?= $pl['db']['limit'] ? ' / ' . h($pl['db']['limit']) : '' ?></b><?php if ($pl['db']['pct'] !== null): ?><span class="dvx-meter <?= $pl['db']['pct'] >= 90 ? 'bad' : ($pl['db']['pct'] >= 80 ? 'warn' : '') ?>"><i style="width:<?= min(100, max(1, $pl['db']['pct'])) ?>%"></i></span><?php endif; ?><small><?= $pl['db']['limit'] ? 'Limit ' . h($pl['db']['by']) : 'Measured (information_schema) · no plan limit set' ?></small></a>
      <a class="dinf-cap-i" href="#ovr-renewal_date"><span class="dinf-cap-t"><i class="ic ic-sm i-calendar" aria-hidden="true"></i>Plan renewal<?php if ($pl['renew']): ?><span class="dvx-ovr">override</span><?php endif; ?></span><?php if ($pl['renew']): ?><b class="mono <?= $pl['renew']['days'] < 0 ? 'dvx-bad' : ($pl['renew']['days'] <= 14 ? 'dvx-warn' : '') ?>"><?= h($pl['renew']['date']) ?></b><small><?= $pl['renew']['days'] >= 0 ? 'in ' . (int) $pl['renew']['days'] . ' days' : (int) abs($pl['renew']['days']) . ' days ago' ?> · <?= h($pl['renew']['by']) ?></small><?php else: ?><b class="mono dvx-mut">not set</b><small>From the provider's invoices.</small><?php endif; ?></a>
    </div>
  </section>
  <section class="dvx-card" aria-labelledby="dinf-ev">
    <div class="dvx-ch"><div><h2 class="dvx-ct" id="dinf-ev">Recent infrastructure events</h2><p class="dvx-cs">Alerts, failed jobs, backups, database jobs, deployments and override changes · 14 days</p></div><a class="dvx-link" href="<?= h(sl_route('DevLogs')) ?>">Logs</a></div>
    <div class="dinf-feed">
      <?php foreach ($d['events'] as $e): ?><a class="dvx-row" href="<?= h($e['href']) ?>"><span class="dinf-ev-i <?= h($e['tone']) ?>"><i class="ic ic-sm <?= h($e['icon']) ?>" aria-hidden="true"></i></span><span class="col dvx-min grow"><b class="dvx-ell"><?= h($e['t']) ?></b><small class="dvx-ell"><?= h($e['s']) ?></small></span><small class="mono" title="<?= h($e['when']) ?>"><?= h($e['ago']) ?></small></a><?php endforeach; ?>
      <?php if (!$d['events']): ?><div class="dvx-empty"><i class="ic i-check" aria-hidden="true"></i>Nothing happened to the infrastructure in 14 days.</div><?php endif; ?>
    </div>
  </section>
</div>

<section class="dvx-card dvx-flush dinf-ovr" id="overrides" aria-labelledby="dinf-ovr-h">
  <div class="dvx-ch pad"><div><h2 class="dvx-ct" id="dinf-ovr-h"><i class="ic ic-sm i-lock" aria-hidden="true"></i>Super-admin overrides</h2><p class="dvx-cs">For figures this host cannot report, or reports wrongly. An override replaces the measured value on every developer page and is labelled with who set it and when. Clear it to go back to the measurement. Every change is written to the activity log.<?php if (!$d['canEdit']): ?> <b>View only:</b> changing these needs the “Infrastructure overrides” section.<?php endif; ?> Also shown on <a class="dvx-link" href="<?= h(sl_route('CostSettings')) ?>#infra-overrides">Operating costs</a>.</p></div><span class="dvx-badge"><?= (int) $d['ovrSet'] ?> set</span></div>
  <div class="dvx-tw"><table class="dvx-table dinf-ovr-t">
    <thead><tr><th scope="col">Figure</th><th scope="col">Measured on this server</th><th scope="col">Override</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    <?php $grp = ''; foreach ($d['overrides'] as $o): ?>
      <?php if ($o['group'] !== $grp): $grp = $o['group']; ?><tr class="dinf-grp"><th colspan="4" scope="colgroup"><?= h($grp) ?></th></tr><?php endif; ?>
      <tr id="ovr-<?= h($o['k']) ?>" class="<?= $o['set'] ? 'is-set' : '' ?>">
        <td><span class="row g8"><i class="ic ic-sm <?= h($o['icon']) ?> dvx-mut" aria-hidden="true"></i><span class="col"><b class="fw5"><?= h($o['label']) ?></b><small class="dvx-mut"><?= h($o['hint']) ?></small></span></span></td>
        <td><?php if ($o['measured_v'] !== null): ?><span class="mono"><?= h($o['measured']) ?></span><small class="dvx-mut dinf-src"><?= h($o['src']) ?></small><?php else: ?><span class="dvx-pill">not measured</span><small class="dvx-mut dinf-src"><?= h($o['how']) ?></small><?php endif; ?></td>
        <td><?php if ($o['set']): ?><span class="mono dinf-ovr-v"><?= h($o['shown']) ?></span><span class="dvx-ovr">override</span><small class="dinf-by"><?= h($o['by']) ?><?= $o['note'] !== '' ? ' · “' . h($o['note']) . '”' : '' ?></small><?php else: ?><span class="dvx-mut">—</span><?php endif; ?></td>
        <td class="num"><?php if ($d['canEdit']): ?><span class="row g6 dinf-acts"><button type="button" class="dvx-btn sm" data-dinf-ovr="<?= h(json_encode(['key' => $o['k'], 'label' => $o['label'], 'unit' => $o['unit'], 'type' => $o['type'], 'opts' => $o['opts'], 'value' => $o['value'], 'note' => $o['note'], 'measured' => $o['measured'], 'hint' => $o['hint']])) ?>"><i class="ic ic-sm i-edit" aria-hidden="true"></i><?= $o['set'] ? 'Edit' : 'Override' ?></button><?php if ($o['set']): ?><button type="button" class="dvx-btn sm ghost" data-sl-action="<?= h($d['ajax']) ?>" data-p-op="ovr_clear" data-p-key="<?= h($o['k']) ?>" data-sl-confirm="Clear the override of <?= h($o['label']) ?> and go back to the measured value?">Clear</button><?php endif; ?></span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>

<div class="dvx-grid dinf-g-jobs">
  <section class="dvx-card dvx-flush" aria-labelledby="dinf-jobs">
    <div class="dvx-ch pad"><div><h2 class="dvx-ct" id="dinf-jobs">Scheduled jobs and queues</h2><p class="dvx-cs">Scripts in cron/ and their last recorded run (cdb_cron_runs) · 24 h counts</p></div><a class="dvx-link" href="<?= h(sl_route('DevLogs', ['source' => 'cron'])) ?>">Cron log</a></div>
    <div class="dvx-tw"><table class="dvx-table">
      <thead><tr><th scope="col">Job</th><th scope="col">State</th><th scope="col">Last run</th><th scope="col" class="num">Runs / failed</th></tr></thead>
      <tbody>
      <?php foreach ($d['crons'] as $c): ?><tr><td><span class="col"><b class="mono fw5"><?= h($c['name']) ?></b><small class="dvx-mut dinf-what" title="<?= h($c['what']) ?>"><?= h($c['what']) ?></small></span></td><td><span class="dvx-pill <?= h($c['tone']) ?>"><span class="dvx-dot <?= h($c['tone']) ?>"></span><?= h($c['state']) ?></span></td><td class="mono"<?= $c['lastAt'] ? ' title="' . h($c['lastAt']) . '"' : '' ?>><?= $c['last'] ? h($c['last']) : ($c['sched'] ? '<span class="dvx-mut" title="Schedule written in the script">runs ' . h($c['sched']) . '</span>' : '—') ?></td><td class="num mono"><?= (int) $c['runs'] ?> / <span class="<?= $c['fails'] ? 'dvx-bad' : '' ?>"><?= (int) $c['fails'] ?></span></td></tr><?php endforeach; ?>
      <tr><td><span class="col"><b class="mono fw5">cdb_cs_outbox</b><small class="dvx-mut">Notification queue</small></span></td><td><span class="dvx-pill <?= !$q['exists'] ? '' : ($q['dead'] ? 'warn' : 'ok') ?>"><?= $q['exists'] ? (int) $q['queued'] . ' waiting' : 'not created' ?></span></td><td class="mono"><?= $q['exists'] && $q['oldest_min'] ? 'oldest ' . (int) $q['oldest_min'] . ' min' : '—' ?></td><td class="num mono"><?= $q['exists'] ? (int) $q['sent24'] . ' sent / <span class="' . ($q['dead'] ? 'dvx-bad' : '') . '">' . (int) $q['dead'] . ' dead</span>' : '' ?></td></tr>
      </tbody>
    </table></div>
  </section>
  <section class="dvx-card dinf-bk" aria-labelledby="dinf-bk">
    <div class="dvx-ch"><div><h2 class="dvx-ct" id="dinf-bk">Backups</h2><p class="dvx-cs">Database dumps (.sql, .sql.gz, .zip) in backups/ and the console's private backup folder · not web-accessible</p></div></div>
    <dl class="dvx-dl">
      <dt>Last backup</dt><dd class="mono <?= !$b['latest'] || $b['latest_at'] < time() - 7 * 86400 ? 'dvx-warn' : '' ?>"><?= $b['latest'] ? h(sl_date($b['latest_at'], 'M j, Y H:i') . ' · ' . sl_ago($b['latest_at'])) : 'none yet' ?></dd>
      <dt>Size</dt><dd class="mono"><?= $b['latest'] ? h(sl_devBytes($b['latest_size'])) : '—' ?></dd>
      <dt>Files kept</dt><dd class="mono"><?= (int) $b['count'] ?><?= $b['latest'] && !empty($b['latest_src']) ? ' · newest in ' . h($b['latest_src']) : '' ?></dd>
    </dl>
    <div class="row g8 wrap"><a class="dvx-btn sm" href="backup.php"><i class="ic ic-sm i-archive" aria-hidden="true"></i>Backup tool</a><?php if (is_file(CDP_ROOT . '/dev_database.php')): ?><a class="dvx-btn sm ghost" href="dev_database.php"><i class="ic ic-sm i-database" aria-hidden="true"></i>Database</a><?php endif; ?></div>
  </section>
</div>

<details class="dvx-card dinf-more">
  <summary><span class="dvx-ct">Configuration, writable paths and PHP extensions</span><span class="dvx-cs">config/config.php · secrets masked</span></summary>
  <div class="dvx-grid dvx-g2">
    <dl class="dvx-dl"><?php foreach ($d['config'] as $c): ?><dt class="mono"><?= h($c['k']) ?></dt><dd class="mono"><?= h($c['v']) ?></dd><?php endforeach; ?></dl>
    <div class="col g12">
      <div class="dinf-paths"><?php foreach ($d['paths'] as $p): ?><span class="row g8 sm"><span class="dvx-dot <?= !$p['exists'] ? 'off' : ($p['w'] ? 'ok' : 'bad') ?>" aria-hidden="true"></span><span class="mono"><?= h($p['p']) ?></span><span class="dvx-mut"><?= !$p['exists'] ? 'not created yet' : ($p['w'] ? 'writable' : 'read-only') ?></span></span><?php endforeach; ?></div>
      <div class="row g6 wrap"><?php foreach ($d['ext'] as $e): ?><span class="dvx-pill <?= $e['ok'] ? 'ok' : '' ?>"><?= h($e['n']) ?><?= $e['ok'] ? '' : ' · missing' ?></span><?php endforeach; ?></div>
    </div>
  </div>
</details>

<?php if ($d['canEdit']): ?>
<div class="sl-dialog" id="dinf-ovr-dlg" hidden><form class="sl-modal dinf-modal" data-sl-form data-sl-reload action="<?= h($d['ajax']) ?>" method="post" aria-label="Override a figure">
  <input type="hidden" name="op" value="ovr_save"><input type="hidden" name="key" value="">
  <h2 class="dvx-ct" data-dinf-t>Override</h2>
  <p class="dvx-cs" data-dinf-hint></p>
  <div class="dinf-meas"><span class="dvx-mut">Measured on this server</span><b class="mono" data-dinf-meas></b></div>
  <label class="sl-field"><span class="sl-lab">Override value <span data-dinf-unit class="dvx-mut"></span></span><span data-dinf-in></span></label>
  <label class="sl-field"><span class="sl-lab">Note (optional, shown with the override)</span><input class="sl-in" name="note" maxlength="255" placeholder="e.g. From the hosting panel, plan upgraded"></label>
  <p class="dvx-cs"><i class="ic ic-sm i-lock" aria-hidden="true"></i> Saved as “set by <?= h(trim(($GLOBALS['userData']->fname ?? '') . ' ' . ($GLOBALS['userData']->lname ?? ''))) ?> on <?= h(date('M j, Y')) ?>” and recorded in the activity log.</p>
  <div class="row g8"><button type="submit" class="dvx-btn primary">Save override</button><button type="button" class="dvx-btn ghost" data-sl-close>Cancel</button></div>
</form></div>
<?php endif; ?>
</div>
<?= sl_dvxSubnavEnd() ?>
