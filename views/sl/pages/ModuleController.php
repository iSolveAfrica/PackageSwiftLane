<?php
/**
 * ModuleController - generated from docs/handover/artifact-source/ModuleController.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  filters                      as $f
 *   loop  groups                       as $g
 *   loop  g.items                      as $m
 *   loop  m.tags                       as $t
 *   loop  preview                      as $g
 *   loop  g.items                      as $m
 *   loop  log                          as $l
 *   if    warn
 *   value allOn, apiOn, apiW, apply, applyLabel, custOn, custTotal, custW, false, filters, groups, log, offCount, onCount, onQ, onW, preview, q, staffHit, staffW, total, warn
 * sl-wrap extras in the design: style="max-width:1180px"
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:20px">Module Controller</h1><p class="sl-lede">Switch a module off and it disappears everywhere: menus, dashboards, search, API, customer portal and mobile app</p></div><div class="row g8 wrap"><span class="ov-pill"><?= sl_v($d, 'onCount') ?> of <?= sl_v($d, 'total') ?> modules on</span><button class="sl-btn ghost" data-sl-on="click:allOn">Turn everything on</button><button class="sl-btn" data-sl-on="click:apply"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= sl_v($d, 'applyLabel') ?></button></div></div>
<div class="grid cols4">
<div class="ov-card"><div class="hd"><span><i class="ic ic-sm i-grid" aria-hidden="true"></i>Modules on</span></div><div class="fig"><?= sl_v($d, 'onCount') ?> <i>/ <?= sl_v($d, 'total') ?></i></div><div class="sl-ticks" role="img" aria-label="Modules on"><i style="<?= sl_v($d, 'onW') ?>"></i></div><div class="ft"><?= sl_v($d, 'offCount') ?> switched off</div></div>
<div class="ov-card"><div class="hd"><span><i class="ic ic-sm i-users" aria-hidden="true"></i>Staff affected</span></div><div class="fig"><?= sl_v($d, 'staffHit') ?> <i>/ 46 staff</i></div><div class="sl-ticks y" role="img" aria-label="Staff affected"><i style="<?= sl_v($d, 'staffW') ?>"></i></div><div class="ft">Lose a menu item on next page load</div></div>
<div class="ov-card"><div class="hd"><span><i class="ic ic-sm i-user" aria-hidden="true"></i>Customer portal</span></div><div class="fig"><?= sl_v($d, 'custOn') ?> <i>/ <?= sl_v($d, 'custTotal') ?> features on</i></div><div class="sl-ticks" role="img" aria-label="Customer features"><i style="<?= sl_v($d, 'custW') ?>"></i></div><div class="ft">Hidden features show no trace</div></div>
<div class="ov-card"><div class="hd"><span><i class="ic ic-sm i-link" aria-hidden="true"></i>API endpoints</span></div><div class="fig"><?= sl_v($d, 'apiOn') ?> <i>/ 64 active</i></div><div class="sl-ticks" role="img" aria-label="API endpoints"><i style="<?= sl_v($d, 'apiW') ?>"></i></div><div class="ft">Off modules return 404 module_disabled</div></div>
</div>
<div class="mc">
<div class="mc-main">
<div class="row between wrap g12"><div class="sl-search" style="max-width:340px"><i class="ic ic-sm i-search" aria-hidden="true"></i><input aria-label="Search modules" placeholder="Search modules" value="<?= sl_v($d, 'q') ?>" data-sl-on="change:onQ"></div><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'filters', []) as $__i => $f): ?><button class="<?= sl_v($f, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($f, 'label') ?></button><?php endforeach; ?></div></div>
<?php if (sl_get($d, 'warn')): ?><div class="mc-toast" role="status"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><?= sl_v($d, 'warn') ?></span></div><?php endif; ?>
<?php foreach ((array) sl_get($d, 'groups', []) as $__i => $g): ?>
<section class="mc-grp"><div class="mc-gh"><span class="tl"><i class="ic <?= sl_v($g, 'icon') ?>" aria-hidden="true"></i></span><div class="grow"><b><?= sl_v($g, 'label') ?></b><small><?= sl_v($g, 'desc') ?></small></div><span class="ov-pill"><?= sl_v($g, 'count') ?></span><button class="sl-btn sm ghost" data-sl-on="click:toggleAll"><?= sl_v($g, 'allLabel') ?></button></div>
<?php foreach ((array) sl_get($g, 'items', []) as $__i => $m): ?><div class="mc-row <?= sl_v($m, 'cls') ?>"><i class="ic ic-sm <?= sl_v($m, 'icon') ?>" aria-hidden="true"></i><div class="nm"><b><?= sl_v($m, 'name') ?></b><small><?= sl_v($m, 'desc') ?></small><div class="mc-tags"><?php foreach ((array) sl_get($m, 'tags', []) as $__i => $t): ?><span class="<?= sl_v($t, 'cls') ?>"><?= sl_v($t, 'label') ?></span><?php endforeach; ?></div></div><span class="mut sm"><?= sl_v($m, 'state') ?></span><button class="mc-sw <?= sl_v($m, 'sw') ?>" data-sl-on="click:toggle" role="switch" aria-checked="<?= sl_v($m, 'checked') ?>" aria-label="<?= sl_v($m, 'name') ?>"></button></div><?php endforeach; ?>
</section>
<?php endforeach; ?>
</div>
<aside class="mc-side">
<section class="sl-card col g8"><h2 class="sl-ct">What staff will see</h2><p class="mut sm">The sidebar after you apply, for an Ops manager</p><div class="mc-prev"><?php foreach ((array) sl_get($d, 'preview', []) as $__i => $g): ?><div class="gl"><?= sl_v($g, 'label') ?></div><?php foreach ((array) sl_get($g, 'items', []) as $__i => $m): ?><a href="<?= sl_route('ModuleController') ?>"><i class="ic ic-sm <?= sl_v($m, 'icon') ?>" aria-hidden="true"></i><?= sl_v($m, 'name') ?></a><?php endforeach; ?><?php endforeach; ?></div></section>
<section class="sl-card col g8"><h2 class="sl-ct">Recent switches</h2><?php foreach ((array) sl_get($d, 'log', []) as $__i => $l): ?><div class="ov-row sm"><span class="sl-av sm"><?= sl_v($l, 'who') ?></span><span class="grow"><?= sl_v($l, 'what') ?></span><span class="mut"><?= sl_v($l, 'when') ?></span></div><?php endforeach; ?></section>
</aside>
</div>
