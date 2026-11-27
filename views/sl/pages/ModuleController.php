<?php
/**
 * ModuleController - Modules page of the developer console (dark DevOps theme) and, with
 * $d['moduleOff'], the friendly "This module is switched off" answer (helpers/sl_modules.php sl_moduleOffPage).
 * Data: module_controller.php + helpers/sl_modules.php. Writes: ajax/sl/modules_ajax.php.
 */
if (!isset($d)) { $d = []; }
$ajax = 'ajax/sl/modules_ajax.php';
if (!empty($d['moduleOff'])): ?>
<div class="mc-off" role="main">
  <div class="mc-off-card">
    <span class="mc-off-ic"><i class="ic i-sliders" aria-hidden="true"></i></span>
    <h1>This module is switched off</h1>
    <p><b><?= h($d['label']) ?></b> is not available on this system right now. Nothing has been deleted; an administrator can switch it back on.</p>
    <div class="mc-off-a"><a class="sl-btn" href="index.php">Back to the dashboard</a><?php if (!empty($d['canSwitch'])): ?><a class="sl-btn ghost" href="<?= h(sl_route('ModuleController', ['q' => $d['label']])) ?>">Open Modules</a><?php endif; ?></div>
  </div>
</div>
<?php return; endif;
$dvxActive = 'modules';
include CDP_ROOT . '/views/sl/pages/partials/dev_subnav.php';
?>
<div class="dvx-page mc">
<header class="dvx-head">
  <div class="col g4"><h1 class="dvx-title">Modules</h1><p class="dvx-lede">Switch a business module off and it is gone for everyone: its menus, its pages<?= $d['central'] ? ', its endpoints, its API resources and its scheduled jobs' : '' ?>. Nothing is deleted.</p></div>
  <div class="row g8 wrap"><span class="dvx-pill <?= $d['offCount'] ? 'warn' : 'ok' ?>"><i class="dvx-dot <?= $d['offCount'] ? 'warn' : 'ok' ?>"></i><?= (int) $d['onCount'] ?> of <?= (int) $d['switchable'] ?> on</span><?php if ($d['offCount']): ?><button type="button" class="dvx-btn" data-sl-action="<?= $ajax ?>" data-p-op="all_on" data-sl-reload data-sl-confirm="Switch every module back on? Their menus, pages, endpoints and jobs return for everyone with permission.">Turn everything on</button><?php endif; ?></div>
</header>

<section class="mc-kpis" aria-label="Summary">
  <a class="mc-kpi" href="<?= h(sl_url(['filter' => 'on'])) ?>"><span class="t"><i class="ic ic-sm i-grid" aria-hidden="true"></i>Modules on</span><b><?= (int) $d['onCount'] ?> <small>/ <?= (int) $d['switchable'] ?></small></b><span class="dvx-meter"><i style="width:<?= (int) $d['onPct'] ?>%"></i></span><small><?= (int) $d['offCount'] ?> switched off</small></a>
  <a class="mc-kpi" href="<?= h(sl_url(['filter' => 'off'])) ?>"><span class="t"><i class="ic ic-sm i-clock" aria-hidden="true"></i>Jobs skipped</span><b><?= count($d['skipJobs']) ?> <small>scheduled</small></b><span class="dvx-meter warn"><i style="width:<?= $d['skipJobs'] ? 100 : 0 ?>%"></i></span><small class="dvx-ell"><?= !$d['central'] ? 'Not enforced yet: jobs run' : ($d['skipJobs'] ? h(implode(', ', $d['skipJobs'])) : 'Every cron job runs') ?></small></a>
  <a class="mc-kpi" href="<?= h(sl_url(['q' => '', 'filter' => 'all'])) ?>#portal"><span class="t"><i class="ic ic-sm i-globe" aria-hidden="true"></i>Customer portal</span><b><?= (int) $d['custOn'] ?> <small>/ <?= (int) $d['custTotal'] ?> on</small></b><span class="dvx-meter"><i style="width:<?= (int) $d['custPct'] ?>%"></i></span><small><?= sl_num($d['custN']) ?> active customers see the change</small></a>
  <a class="mc-kpi" href="<?= h(sl_route('ActivityLogs')) ?>"><span class="t"><i class="ic ic-sm i-users" aria-hidden="true"></i>Staff affected</span><b><?= sl_num($d['staffN']) ?> <small>accounts</small></b><span class="dvx-meter"><i style="width:100%"></i></span><small>Menus change on their next page load</small></a>
</section>

<?php if (!$d['central']): ?>
<div class="dvx-note mc-gap" role="note"><i class="ic i-alert" aria-hidden="true"></i><span><b>Partly enforced.</b> Switching a module off hides its menu items and closes its signed-in pages. Its AJAX endpoints, public pages (sign-up, tracking), scheduled jobs and API resources keep answering until <span class="mono">loader.php</span> runs the central module guard (<span class="mono">sl_moduleGuardRequest('any')</span>, see <span class="mono">helpers/sl_modules.php</span>). Dashboards may still show its panels.</span></div>
<?php endif; ?>
<div class="mc-cols">
<div class="mc-main" data-sl-scope>
  <form method="get" class="mc-tools"><input type="hidden" name="filter" value="<?= h($d['filter']) ?>"><label class="dvx-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" aria-label="Search modules" placeholder="Search modules" value="<?= h($d['q']) ?>"></label><nav class="dvx-seg boxed" aria-label="Filter"><?php foreach ($d['filters'] as $f): ?><a class="<?= $f['f'] === $d['filter'] ? 'on' : '' ?>" href="<?= h(sl_url(['filter' => $f['f']])) ?>"<?= $f['f'] === $d['filter'] ? ' aria-current="true"' : '' ?>><?= h($f['label']) ?></a><?php endforeach; ?></nav></form>
  <?php foreach ($d['groups'] as $g): ?>
  <section class="dvx-card dvx-flush mc-grp" id="<?= h($g['key']) ?>">
    <div class="dvx-ch pad"><div class="row g12 dvx-min"><span class="dvx-node-i"><i class="ic ic-sm <?= h($g['icon']) ?>" aria-hidden="true"></i></span><div class="dvx-min"><h2 class="dvx-ct"><?= h($g['label']) ?></h2><p class="dvx-cs"><?= h($g['desc']) ?></p></div></div><span class="dvx-badge"><?= h($g['count']) ?></span></div>
    <?php foreach ($g['items'] as $m): ?>
    <div class="mc-row<?= $m['on'] ? '' : ' off' ?>">
      <span class="dvx-dot <?= $m['on'] ? 'ok' : 'off' ?>" aria-hidden="true"></span>
      <div class="mc-nm">
        <b><?= h($m['name']) ?></b>
        <small><?= h($m['desc']) ?></small>
        <span class="mc-tags"><?php foreach ($m['tags'] as $t): ?><span><?= h($t) ?></span><?php endforeach; ?></span>
        <?php if ($m['imp']['nav'] || $m['imp']['pages'] || $m['imp']['jobs']): ?>
        <details class="mc-imp"><summary>What switching off removes</summary>
          <dl class="dvx-dl">
            <?php if ($m['imp']['nav']): ?><dt>Menu</dt><dd><?= h(implode(', ', $m['imp']['nav'])) ?></dd><?php endif; ?>
            <?php if ($m['imp']['pages']): ?><dt>Pages</dt><dd class="mono"><?= h(implode(', ', array_slice($m['imp']['pages'], 0, 14))) ?><?= count($m['imp']['pages']) > 14 ? ' and ' . (count($m['imp']['pages']) - 14) . ' more' : '' ?></dd><?php endif; ?>
            <?php if ($m['imp']['ajax']): ?><dt>Endpoints</dt><dd class="mono"><?= h(implode(', ', array_slice($m['imp']['ajax'], 0, 8))) ?><?= count($m['imp']['ajax']) > 8 ? ' and ' . (count($m['imp']['ajax']) - 8) . ' more' : '' ?></dd><?php endif; ?>
            <?php if ($m['imp']['jobs']): ?><dt>Jobs</dt><dd class="mono"><?= h(implode(', ', $m['imp']['jobs'])) ?></dd><?php endif; ?>
          </dl>
        </details>
        <?php endif; ?>
      </div>
      <span class="mc-st"><span class="dvx-pill <?= $m['on'] ? 'ok' : '' ?>"><?= $m['on'] ? 'On' : 'Off' ?></span><small><?= h($m['changed']) ?></small></span>
      <?php if ($m['core']): ?>
      <span class="mc-sw on lock" role="img" aria-label="<?= h($m['name']) ?>: always on" title="Always on"><i></i></span>
      <?php elseif ($m['pinned']): ?>
      <span class="mc-sw <?= $m['on'] ? 'on' : '' ?> lock" role="img" aria-label="<?= h($m['name']) ?>: set in config/config.php" title="Pinned by CDP_FINANCE_ENABLED in config/config.php"><i></i></span>
      <?php else: ?>
      <button type="button" class="mc-sw <?= $m['on'] ? 'on' : '' ?>" role="switch" aria-checked="<?= $m['on'] ? 'true' : 'false' ?>" aria-label="<?= h($m['name']) ?>" data-sl-action="<?= $ajax ?>" data-p-op="toggle" data-p-key="<?= h($m['id']) ?>" data-p-value="<?= $m['on'] ? '0' : '1' ?>" data-sl-reload data-sl-confirm="<?= h($m['confirm']) ?>"><i></i></button>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </section>
  <?php endforeach; ?>
  <?php if (!$d['groups']): ?><div class="dvx-card"><div class="dvx-empty"><i class="ic i-search" aria-hidden="true"></i>No module matches.</div></div><?php endif; ?>
</div>
<aside class="mc-side">
  <section class="dvx-card"><h2 class="dvx-ct"><i class="ic ic-sm i-shield" aria-hidden="true"></i>How "off" is enforced</h2>
    <ul class="mc-how">
      <li><b>Menus</b><span>hidden for every role, staff and customers</span></li>
<?php if ($d['central']): ?>
      <li><b>Pages</b><span>answer "This module is switched off" (403), public pages included</span></li>
      <li><b>Endpoints</b><span>refuse with <span class="mono">module_disabled</span> (403)</span></li>
      <li><b>API</b><span>its <span class="mono">/api/v1</span> resources answer <span class="mono">module_disabled</span></span></li>
      <li><b>Scheduled jobs</b><span>print "skipped" and exit</span></li>
<?php else: ?>
      <li><b>Pages</b><span>signed-in pages that call <span class="mono">sl_gate</span> answer "This module is switched off" (403)</span></li>
      <li class="mc-no"><b>Not yet</b><span>AJAX endpoints, public pages, scheduled jobs and the API: they need the central guard in <span class="mono">loader.php</span></span></li>
<?php endif; ?>
      <li class="mc-no"><b>Dashboards</b><span>panels are not hidden by the switch yet</span></li>
      <li><b>Financial</b><span>is the same switch as the Billing hub</span></li>
    </ul>
    <p class="dvx-cs">One map decides which page, endpoint and job belongs to which module: <span class="mono">helpers/sl_modules.php</span>. The developer console itself is switched off from <a class="dvx-link" href="dev_access.php#shutdown">Developer access</a>, with an unlock code.</p>
  </section>
  <section class="dvx-card"><div class="dvx-ch"><h2 class="dvx-ct">Recent switches</h2><a class="dvx-link" href="<?= h(sl_route('ActivityLogs')) ?>">Audit log</a></div>
    <div class="col">
    <?php foreach ($d['log'] as $l): ?><div class="dvx-row"><span class="sl-av sm" aria-hidden="true"><?= h($l['ini']) ?></span><span class="dvx-min grow"><b class="dvx-ell"><?= h($l['who']) ?></b><small><?= h($l['what']) ?></small></span><small title="<?= h($l['at']) ?>"><?= h($l['when']) ?></small></div><?php endforeach; ?>
    <?php if (!$d['log']): ?><div class="dvx-empty">No switches yet.</div><?php endif; ?>
    </div>
  </section>
</aside>
</div>
</div>
<?= sl_dvxSubnavEnd() ?>
<script>
(function () {
  // Phone: the developer sub-navigation is a horizontal strip; bring "Modules" into view.
  var act = document.querySelector('.dvx-sub [aria-current="page"]');
  for (var c = act && act.parentElement; c && c !== document.body; c = c.parentElement) {
    if (c.scrollWidth > c.clientWidth + 4 && getComputedStyle(c).overflowX !== 'visible') {
      var r = act.getBoundingClientRect(), cr = c.getBoundingClientRect();
      c.scrollLeft += (r.left - cr.left) - (cr.width - r.width) / 2;
      break;
    }
  }
})();
</script>
