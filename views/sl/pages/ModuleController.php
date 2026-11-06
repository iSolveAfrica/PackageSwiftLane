<?php
/**
 * ModuleController - from docs/handover/artifact-source/ModuleController.dc.html.
 * Data: module_controller.php + helpers/sl_modules.php.
 */
if (!isset($d)) { $d = []; }
$ajax = 'ajax/sl/modules_ajax.php';
?>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:20px">Module Controller</h1><p class="sl-lede">Switch a module off and it disappears everywhere: menus, dashboards, search, API, customer portal and mobile app</p></div><div class="row g8 wrap"><span class="ov-pill"><?= (int) $d['onCount'] ?> of <?= (int) $d['total'] ?> modules on</span><button class="sl-btn ghost" data-sl-action="<?= $ajax ?>" data-p-op="all_on" data-sl-reload data-sl-confirm="Switch every module on?">Turn everything on</button></div></div>
<div class="grid cols4">
<div class="ov-card"><div class="hd"><span><i class="ic ic-sm i-grid" aria-hidden="true"></i>Modules on</span></div><div class="fig"><?= (int) $d['onCount'] ?> <i>/ <?= (int) $d['total'] ?></i></div><div class="sl-ticks" role="img" aria-label="Modules on"><i style="<?= h($d['onW']) ?>"></i></div><div class="ft"><?= (int) $d['offCount'] ?> switched off</div></div>
<div class="ov-card"><div class="hd"><span><i class="ic ic-sm i-users" aria-hidden="true"></i>Active staff</span></div><div class="fig"><?= (int) $d['staffN'] ?> <i>staff accounts</i></div><div class="sl-ticks y" role="img" aria-label="Staff"><i style="width:100%"></i></div><div class="ft">Lose a menu item on next page load</div></div>
<div class="ov-card"><div class="hd"><span><i class="ic ic-sm i-user" aria-hidden="true"></i>Customer portal</span></div><div class="fig"><?= (int) $d['custOn'] ?> <i>/ <?= (int) $d['custTotal'] ?> features on</i></div><div class="sl-ticks" role="img" aria-label="Customer features"><i style="<?= h($d['custW']) ?>"></i></div><div class="ft">Hidden features show no trace</div></div>
<div class="ov-card"><div class="hd"><span><i class="ic ic-sm i-link" aria-hidden="true"></i>API resources</span></div><div class="fig"><?= (int) $d['apiOn'] ?> <i>/ <?= (int) $d['apiAll'] ?> active</i></div><div class="sl-ticks" role="img" aria-label="API resources"><i style="<?= h($d['apiW']) ?>"></i></div><div class="ft">Off modules can return 404 module_disabled</div></div>
</div>
<div class="mc">
<div class="mc-main">
<form method="get" class="row between wrap g12"><input type="hidden" name="filter" value="<?= h($d['filter']) ?>"><div class="sl-search" style="max-width:340px"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" aria-label="Search modules" placeholder="Search modules" value="<?= h($d['q']) ?>"></div><div class="sl-seg boxed"><?php foreach ($d['filters'] as $f): ?><a class="<?= $f['f'] === $d['filter'] ? 'on' : '' ?>" href="<?= h(sl_url(['filter' => $f['f']])) ?>"><?= h($f['label']) ?></a><?php endforeach; ?></div></form>
<?php foreach ($d['groups'] as $g): ?>
<section class="mc-grp"><div class="mc-gh"><span class="tl"><i class="ic <?= h($g['icon']) ?>" aria-hidden="true"></i></span><div class="grow"><b><?= h($g['label']) ?></b><small><?= h($g['desc']) ?></small></div><span class="ov-pill"><?= h($g['count']) ?></span></div>
<?php foreach ($g['items'] as $m): ?><div class="mc-row <?= h($m['cls']) ?>"><i class="ic ic-sm i-sliders" aria-hidden="true"></i><div class="nm"><b><?= h($m['name']) ?></b><small><?= h($m['desc']) ?></small><div class="mc-tags"><?php foreach ($m['tags'] as $t): ?><span class="<?= h($t['cls']) ?>"><?= h($t['label']) ?></span><?php endforeach; ?></div></div><span class="mut sm"><?= h($m['state']) ?></span><span class="sl-toggle <?= $m['on'] ? 'on' : '' ?>" role="switch" aria-checked="<?= $m['on'] ? 'true' : 'false' ?>" aria-label="<?= h($m['name']) ?>" <?= $m['lock'] ? 'data-static' : 'data-sl-save="' . $ajax . '" data-key="' . h($m['id']) . '"' ?>></span></div><?php endforeach; ?>
</section>
<?php endforeach; ?>
<?php if (!$d['groups']): ?><div class="sl-empty">No module matches.</div><?php endif; ?>
</div>
<aside class="mc-side">
<section class="sl-card col g8"><h2 class="sl-ct">How it applies</h2><p class="mut sm">The switch is stored now. Menus hide the module once the navigation reads <span class="mono">sl_moduleEnabled()</span> (helpers/sl_modules.php); pages and the API can check the same function.</p></section>
<section class="sl-card col g8"><h2 class="sl-ct">Recent switches</h2><?php foreach ($d['log'] as $l): ?><div class="ov-row sm"><span class="sl-av sm"><?= h($l['who']) ?></span><span class="grow"><?= h($l['what']) ?></span><span class="mut"><?= h($l['when']) ?></span></div><?php endforeach; ?><?php if (!$d['log']): ?><span class="mut sm">No switches yet.</span><?php endif; ?></section>
</aside>
</div>
