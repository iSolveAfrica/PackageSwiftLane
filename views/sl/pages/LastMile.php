<?php
/**
 * LastMile - generated from docs/handover/artifact-source/LastMile.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  kpis                         as $k
 *   loop  k.sp                         as $h
 *   loop  dtypes                       as $d
 *   loop  slots                        as $t
 *   loop  stops                        as $x
 *   loop  cod                          as $c
 *   loop  trans                        as $x
 *   loop  cf                           as $f
 *   loop  cov                          as $c
 *   if    flash
 *   value book, cf, cod, cov, dtypes, false, fee, flash, gps, gpsOut, h, kpis, look, opt, optL, routeSum, setGps, slots, stops, trans
 * sl-wrap extras in the design: style="max-width:1440px"
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:22px">Ghana last-mile delivery</h1><p class="sl-lede">Delivery requests with GhanaPost GPS, routes, cash on delivery, branch transfers and returns. Live tracking and the rider app are linked below.</p></div><div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('DeliveryTracking') ?>">Live tracking</a><a class="sl-btn" href="<?= sl_route('RiderDispatch') ?>">Dispatch board</a></div></div>
<?php if (sl_get($d, 'flash')): ?><div class="fr-ok" role="status"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= sl_v($d, 'flash') ?></div><?php endif; ?>

<div class="hb-ks"><?php foreach ((array) sl_get($d, 'kpis', []) as $__i => $k): ?><div class="hb-k"><div class="hb-kh"><span class="hb-ki"><i class="ic ic-sm <?= sl_v($k, 'icon') ?>" aria-hidden="true"></i></span><span class="hb-kl"><?= sl_v($k, 'l') ?></span></div><b class="hb-kv <?= sl_v($k, 'anim') ?>"><?= sl_v($k, 'v') ?></b><div class="hb-kf"><span class="hb-ks2"><?= sl_v($k, 's') ?></span><span class="hb-sp" aria-hidden="true"><?php foreach ((array) sl_get($k, 'sp', []) as $__i => $h): ?><i style="<?= sl_v($h, '') ?>"></i><?php endforeach; ?></span></div></div><?php endforeach; ?></div>
<div class="hb-g">
<section class="hb-p"><h2 class="sl-ct">Delivery request</h2><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'dtypes', []) as $__i => $it_d): ?><button class="<?= sl_v($it_d, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($it_d, 'label') ?></button><?php endforeach; ?></div><label class="sl-field"><span class="sl-lab">GhanaPost GPS address</span><div class="row g6"><input class="sl-in mono grow" value="<?= sl_v($d, 'gps') ?>" data-sl-on="input:setGps"><button class="sl-btn ghost" data-sl-on="click:look">Find</button></div></label><div class="hb-out"><?= sl_v($d, 'gpsOut') ?></div><div class="sl-field"><span class="sl-lab">Time slot</span><div class="row wrap g6"><?php foreach ((array) sl_get($d, 'slots', []) as $__i => $t): ?><button class="chipbtn <?= sl_v($t, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($t, 'label') ?></button><?php endforeach; ?></div></div><button class="sl-btn" data-sl-on="click:book">Book delivery · <?= sl_v($d, 'fee') ?></button></section>
<section class="hb-p"><div class="sl-ch" style="margin:0"><div><h2 class="sl-ct">Route planner · Kwesi · today</h2><span class="mut sm"><?= sl_v($d, 'routeSum') ?></span></div><button class="sl-btn ghost sm" data-sl-on="click:opt"><?= sl_v($d, 'optL') ?></button></div><?php foreach ((array) sl_get($d, 'stops', []) as $__i => $x): ?><div class="hb-l"><span class="mono" style="width:22px"><?= sl_v($x, 'n') ?></span><span class="col grow"><b class="fw5"><?= sl_v($x, 'who') ?></b><span class="mut" style="font-size:12px"><?= sl_v($x, 'gps') ?> · <?= sl_v($x, 'slot') ?></span></span><span class="sl-pill <?= sl_v($x, 'pc') ?>"><?= sl_v($x, 'st') ?></span></div><?php endforeach; ?><span class="mut" style="font-size:12px">Failed reasons used: not home · wrong address · refused · no cash · unsafe area</span></section>
<section class="hb-p"><h2 class="sl-ct">Cash on delivery</h2><?php foreach ((array) sl_get($d, 'cod', []) as $__i => $c): ?><div class="hb-l"><span class="hb-dot <?= sl_v($c, 'd') ?>"></span><span class="col grow"><b class="fw5"><?= sl_v($c, 'r') ?></b><span class="mut" style="font-size:12px"><?= sl_v($c, 's') ?></span></span><span class="mono"><?= sl_v($c, 'v') ?></span><button class="sl-btn ghost sm" data-sl-on="click:go"><?= sl_v($c, 'l') ?></button></div><?php endforeach; ?></section>
<section class="hb-p"><h2 class="sl-ct">Branch transfers and returns</h2><?php foreach ((array) sl_get($d, 'trans', []) as $__i => $x): ?><div class="hb-l"><i class="ic ic-sm <?= sl_v($x, 'icon') ?>" aria-hidden="true"></i><span class="col grow"><b class="fw5"><?= sl_v($x, 't') ?></b><span class="mut" style="font-size:12px"><?= sl_v($x, 's') ?></span></span><span class="sl-pill <?= sl_v($x, 'pc') ?>"><?= sl_v($x, 'st') ?></span></div><?php endforeach; ?></section>
</div>
<section class="col g10"><div class="row between wrap g8"><h2 class="sl-ct">All 25 features and where they live</h2><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'cf', []) as $__i => $f): ?><button class="<?= sl_v($f, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($f, 'label') ?></button><?php endforeach; ?></div></div><div class="hb-cov"><?php foreach ((array) sl_get($d, 'cov', []) as $__i => $c): ?><a class="hb-i <?= sl_v($c, 'off') ?>" href="<?= sl_v($c, 'href') ?>"><span class="n"><?= sl_v($c, 'n') ?></span><span class="col grow"><b class="fw5 sm"><?= sl_v($c, 't') ?></b><span class="mut" style="font-size:11.5px"><?= sl_v($c, 'where') ?></span></span><span class="sl-pill <?= sl_v($c, 'pc') ?>"><?= sl_v($c, 'st') ?></span></a><?php endforeach; ?></div></section>
