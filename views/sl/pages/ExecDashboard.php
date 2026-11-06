<?php
/**
 * ExecDashboard - generated from docs/handover/artifact-source/ExecDashboard.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  kpis                         as $k
 *   loop  k.sp                         as $h
 *   loop  modes                        as $m
 *   loop  pers                         as $p
 *   loop  tileOpts                     as $o
 *   loop  bars                         as $b
 *   loop  br                           as $b
 *   loop  watch                        as $w
 *   loop  ins                          as $i
 *   loop  reps                         as $r
 *   loop  cf                           as $f
 *   loop  cov                          as $c
 *   if    flash
 *   if    tilesOpen
 *   value addRep, bars, br, cf, chartT, cov, false, flash, h, i, ins, kpis, modeL, modes, peak, pers, reps, tileOpts, tilesOpen, toggleTiles, watch
 * sl-wrap extras in the design: style="max-width:1440px"
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:22px">Management dashboard</h1><p class="sl-lede">The whole business at a glance, air freight first, sea as an option. Pick your tiles, schedule reports, act on the watch list.</p></div><div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('Reports') ?>">All reports</a><a class="sl-btn" href="<?= sl_route('Documents') ?>">Export</a></div></div>
<?php if (sl_get($d, 'flash')): ?><div class="fr-ok" role="status"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= sl_v($d, 'flash') ?></div><?php endif; ?>

<div class="hb-ks"><?php foreach ((array) sl_get($d, 'kpis', []) as $__i => $k): ?><div class="hb-k"><div class="hb-kh"><span class="hb-ki"><i class="ic ic-sm <?= sl_v($k, 'icon') ?>" aria-hidden="true"></i></span><span class="hb-kl"><?= sl_v($k, 'l') ?></span></div><b class="hb-kv <?= sl_v($k, 'anim') ?>"><?= sl_v($k, 'v') ?></b><div class="hb-kf"><span class="hb-ks2"><?= sl_v($k, 's') ?></span><span class="hb-sp" aria-hidden="true"><?php foreach ((array) sl_get($k, 'sp', []) as $__i => $h): ?><i style="<?= sl_v($h, '') ?>"></i><?php endforeach; ?></span></div></div><?php endforeach; ?></div>
<div class="row between wrap g8"><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'modes', []) as $__i => $m): ?><button class="<?= sl_v($m, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($m, 'label') ?></button><?php endforeach; ?></div><div class="row g8 wrap"><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'pers', []) as $__i => $p): ?><button class="<?= sl_v($p, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($p, 'label') ?></button><?php endforeach; ?></div><button class="sl-btn ghost sm" data-sl-on="click:toggleTiles"><i class="ic ic-sm i-sliders" aria-hidden="true"></i>Choose tiles</button></div></div>
<?php if (sl_get($d, 'tilesOpen')): ?><div class="row wrap g6"><?php foreach ((array) sl_get($d, 'tileOpts', []) as $__i => $o): ?><button class="chipbtn <?= sl_v($o, 'cls') ?>" data-sl-on="click:flip"><?= sl_v($o, 'label') ?></button><?php endforeach; ?></div><?php endif; ?>
<div class="hb-g"><section class="hb-p" style="grid-column:1/-1"><div class="sl-ch" style="margin:0"><div><h2 class="sl-ct"><?= sl_v($d, 'chartT') ?></h2><span class="mut sm">Shipments per week · <?= sl_v($d, 'modeL') ?></span></div><span class="mut sm">Peak <?= sl_v($d, 'peak') ?></span></div><div class="hb-mini" style="height:120px"><?php foreach ((array) sl_get($d, 'bars', []) as $__i => $b): ?><i class="<?= sl_v($b, 'c') ?>" style="<?= sl_v($b, 'h') ?>" title="<?= sl_v($b, 't') ?>"></i><?php endforeach; ?></div><div class="row between mut" style="font-size:11.5px"><span>12 weeks ago</span><span>This week</span></div></section>
<section class="hb-p"><h2 class="sl-ct">By destination and branch</h2><div class="sl-tw"><table class="sl-table"><thead><tr><th>Branch</th><th class="num">Shipments</th><th class="num">Revenue</th><th class="num">Cost</th><th class="num">Profit / shipment</th></tr></thead><tbody><?php foreach ((array) sl_get($d, 'br', []) as $__i => $b): ?><tr><td class="fw5"><?= sl_v($b, 'n') ?></td><td class="num mono"><?= sl_v($b, 's') ?></td><td class="num mono"><?= sl_v($b, 'r') ?></td><td class="num mono mut"><?= sl_v($b, 'c') ?></td><td class="num mono"><?= sl_v($b, 'p') ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="hb-p"><h2 class="sl-ct">Watch list</h2><?php foreach ((array) sl_get($d, 'watch', []) as $__i => $w): ?><a class="hb-l" href="<?= sl_v($w, 'href') ?>" style="color:inherit"><span class="hb-dot <?= sl_v($w, 'd') ?>"></span><span class="col grow"><b class="fw5"><?= sl_v($w, 't') ?></b><span class="mut" style="font-size:12px"><?= sl_v($w, 's') ?></span></span><i class="ic ic-sm i-right mut" aria-hidden="true"></i></a><?php endforeach; ?></section>
<section class="hb-p"><h2 class="sl-ct">AI insights · <?= sl_v($d, 'modeL') ?></h2><?php foreach ((array) sl_get($d, 'ins', []) as $__i => $i): ?><div class="hb-l"><i class="ic ic-sm i-spark" aria-hidden="true"></i><span><?= sl_v($i, '') ?></span></div><?php endforeach; ?></section>
<section class="hb-p"><div class="sl-ch" style="margin:0"><h2 class="sl-ct">Scheduled reports</h2><button class="sl-btn ghost sm" data-sl-on="click:addRep">Add</button></div><?php foreach ((array) sl_get($d, 'reps', []) as $__i => $r): ?><div class="hb-l"><i class="ic ic-sm i-file" aria-hidden="true"></i><span class="col grow"><b class="fw5"><?= sl_v($r, 't') ?></b><span class="mut" style="font-size:12px"><?= sl_v($r, 's') ?></span></span><span class="sl-badge"><?= sl_v($r, 'f') ?></span></div><?php endforeach; ?><div class="row g6"><a class="sl-btn ghost sm" href="<?= sl_route('Documents') ?>">PDF</a><a class="sl-btn ghost sm" href="<?= sl_route('Documents') ?>">CSV</a><a class="sl-btn ghost sm" href="<?= sl_route('Documents') ?>">Excel</a></div></section></div>
<section class="col g10"><div class="row between wrap g8"><h2 class="sl-ct">All 25 features and where they live</h2><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'cf', []) as $__i => $f): ?><button class="<?= sl_v($f, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($f, 'label') ?></button><?php endforeach; ?></div></div><div class="hb-cov"><?php foreach ((array) sl_get($d, 'cov', []) as $__i => $c): ?><a class="hb-i <?= sl_v($c, 'off') ?>" href="<?= sl_v($c, 'href') ?>"><span class="n"><?= sl_v($c, 'n') ?></span><span class="col grow"><b class="fw5 sm"><?= sl_v($c, 't') ?></b><span class="mut" style="font-size:11.5px"><?= sl_v($c, 'where') ?></span></span><span class="sl-pill <?= sl_v($c, 'pc') ?>"><?= sl_v($c, 'st') ?></span></a><?php endforeach; ?></div></section>
