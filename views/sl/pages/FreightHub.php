<?php
/**
 * FreightHub - generated from docs/handover/artifact-source/FreightHub.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  kpis                         as $k
 *   loop  deps                         as $d
 *   loop  acts                         as $a
 *   loop  fl                           as $f
 *   loop  tools                        as $t
 *   if    flash
 *   value acts, deps, false, fl, flash, kpis, tools
 * sl-wrap extras in the design: style="max-width:1440px"
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="row g8 mut sm"><a href="<?= sl_route('FreightHub') ?>">Freight</a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)">Freight hub</span></div>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:22px">Freight hub</h1><p class="sl-lede">Everything for moving cargo, from booking a flight to handing over the parcel, in one place.</p></div><div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('FreightSchedule') ?>"><i class="ic ic-sm i-calendar" aria-hidden="true"></i>Schedule</a><a class="sl-btn" href="<?= sl_route('NewConsolidation') ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New consolidation</a></div></div>
<?php if (sl_get($d, 'flash')): ?><div class="fr-ok" role="status"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= sl_v($d, 'flash') ?></div><?php endif; ?><div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(min(180px,100%),1fr));gap:12px"><?php foreach ((array) sl_get($d, 'kpis', []) as $__i => $k): ?><div class="fr-k"><small><?= sl_v($k, 'l') ?></small><b><?= sl_v($k, 'v') ?></b><small><?= sl_v($k, 's') ?></small></div><?php endforeach; ?></div>
<div class="grid cols2" style="gap:16px;align-items:start"><section class="sl-card col g10"><div class="sl-ch" style="margin:0"><h2 class="sl-ct">Next departures</h2><a class="sm fw5" href="<?= sl_route('FreightSchedule') ?>">All →</a></div><?php foreach ((array) sl_get($d, 'deps', []) as $__i => $it_d): ?><a class="fr-row" href="<?= sl_route('FreightSchedule') ?>"><span class="sl-tile"><i class="ic <?= sl_v($it_d, 'icon') ?>" aria-hidden="true"></i></span><span class="col grow"><b class="fw5 sm"><?= sl_v($it_d, 't') ?></b><span class="mut" style="font-size:12px"><?= sl_v($it_d, 's') ?></span></span><span class="col" style="align-items:flex-end;gap:4px;width:120px"><span class="mono sm"><?= sl_v($it_d, 'fill') ?></span><span class="fr-bar" style="width:100%"><i class="<?= sl_v($it_d, 'hi') ?>" style="<?= sl_v($it_d, 'w') ?>"></i></span></span></a><?php endforeach; ?></section>
<section class="sl-card col g10"><div class="sl-ch" style="margin:0"><h2 class="sl-ct">Needs action</h2><a class="sm fw5" href="<?= sl_route('CargoExceptions') ?>">Exceptions →</a></div><?php foreach ((array) sl_get($d, 'acts', []) as $__i => $a): ?><a class="fr-row" href="<?= sl_v($a, 'href') ?>"><span class="pr-dot" style="width:9px;height:9px;border-radius:50%;background:<?= sl_v($a, 'c') ?>;display:inline-block;flex:none"></span><span class="col grow"><b class="fw5 sm"><?= sl_v($a, 't') ?></b><span class="mut" style="font-size:12px"><?= sl_v($a, 's') ?></span></span><i class="ic ic-sm i-right mut" aria-hidden="true"></i></a><?php endforeach; ?></section></div>
<section class="col g10"><div class="row between"><h2 class="sl-ct">All freight tools</h2><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'fl', []) as $__i => $f): ?><button class="<?= sl_v($f, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($f, 'label') ?></button><?php endforeach; ?></div></div><div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(min(260px,100%),1fr));gap:10px"><?php foreach ((array) sl_get($d, 'tools', []) as $__i => $t): ?><a class="fr-row" href="<?= sl_v($t, 'href') ?>" style="align-items:flex-start"><span class="sl-tile"><i class="ic <?= sl_v($t, 'icon') ?>" aria-hidden="true"></i></span><span class="col grow"><b class="fw5 sm"><?= sl_v($t, 't') ?></b><span class="mut" style="font-size:12px"><?= sl_v($t, 'd') ?></span></span><span class="sl-pill <?= sl_v($t, 'pc') ?>"><?= sl_v($t, 'st') ?></span></a><?php endforeach; ?></div></section>
