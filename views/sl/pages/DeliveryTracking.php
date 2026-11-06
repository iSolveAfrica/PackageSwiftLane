<?php
/**
 * DeliveryTracking - generated from docs/handover/artifact-source/DeliveryTracking.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  filters                      as $f
 *   loop  cards                        as $c
 *   loop  steps                        as $t
 *   if    c.open
 *   if    c.closed
 *   value ago, cards, ex, ey, false, filters, km, kmh, onQ, outN, q, routeAll, routeDone, rx, ry, steps, stops, sx, sy, true
 * sl-wrap extras in the design: style="max-width:1440px"
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:20px">Delivery tracking</h1><p class="sl-lede">Every parcel on its way to a customer's door, live. <?= sl_v($d, 'outN') ?> out for delivery now.</p></div><div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('ShipmentTracking') ?>"><i class="ic ic-sm i-route" aria-hidden="true"></i>Full journey</a><a class="sl-btn ghost" href="<?= sl_route('RiderDispatch') ?>"><i class="ic ic-sm i-truck" aria-hidden="true"></i>Dispatch board</a><a class="sl-btn" href="<?= sl_route('RiderDispatch') ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Assign delivery</a></div></div>
<div class="dt">
<aside class="col g12"><div class="sl-search" style="max-width:none;min-width:0"><i class="ic ic-sm i-search" aria-hidden="true"></i><input aria-label="Search tracking number" placeholder="Search tracking number, customer or rider" value="<?= sl_v($d, 'q') ?>" data-sl-on="input:onQ"></div>
<div class="dt-tabs"><?php foreach ((array) sl_get($d, 'filters', []) as $__i => $f): ?><button class="chipbtn <?= sl_v($f, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($f, 'label') ?></button><?php endforeach; ?></div>
<div class="dt-list"><?php foreach ((array) sl_get($d, 'cards', []) as $__i => $c): ?><div class="dt-c <?= sl_v($c, 'cls') ?>" role="button" tabindex="0" data-sl-on="click:pick">
<div class="dt-id"><span class="bx"><i class="ic i-box" aria-hidden="true"></i></span><b class="grow"><?= sl_v($c, 'id') ?></b><span class="sl-pill <?= sl_v($c, 'pc') ?>"><?= sl_v($c, 'st') ?></span></div>
<?php if (sl_get($c, 'open')): ?><div class="col g14" style="gap:14px"><div class="dt-eta"><div class="col"><span class="mut sm">Estimated arrival</span><big><?= sl_v($c, 'eta') ?><small><?= sl_v($c, 'ampm') ?></small></big></div><span class="fw5"><?= sl_v($c, 'left') ?></span></div>
<div class="dt-prog <?= sl_v($c, 'pcls') ?>" role="img" aria-label="<?= sl_v($c, 'pAria') ?>"><span class="n"></span><span class="ln"><i style="<?= sl_v($c, 'w') ?>"></i><span class="tk" style="<?= sl_v($c, 'l') ?>"><i class="ic <?= sl_v($c, 'tkIcon') ?>" aria-hidden="true"></i></span></span><span class="n end"></span></div>
<div class="dt-ends"><span><?= sl_v($c, 'from') ?><small><?= sl_v($c, 'fromSub') ?></small></span><span style="text-align:right"><?= sl_v($c, 'to') ?><small><?= sl_v($c, 'toSub') ?></small></span></div>
<div class="dt-who"><span class="me-pic <?= sl_v($c, 'pic') ?>" style="width:44px;height:44px"></span><span class="col grow"><b class="fw5"><?= sl_v($c, 'cust') ?></b><span class="mut sm">Recipient · <?= sl_v($c, 'locker') ?></span></span><a class="dt-act" href="<?= sl_route('ComposeMessage') ?>" aria-label="Call recipient"><i class="ic ic-sm i-phone" aria-hidden="true"></i></a><a class="dt-act pri" href="<?= sl_route('ComposeMessage') ?>" aria-label="Message recipient"><i class="ic ic-sm i-message" aria-hidden="true"></i></a></div></div><?php endif; ?>
<?php if (sl_get($c, 'closed')): ?><div class="row between sm"><span class="mut"><?= sl_v($c, 'cust') ?> · <?= sl_v($c, 'toShort') ?></span><span class="mono"><?= sl_v($c, 'eta') ?> <?= sl_v($c, 'ampm') ?></span></div><?php endif; ?>
</div><?php endforeach; ?></div></aside>
<section class="col g16">
<div class="dt-map" role="img" aria-label="Map of the delivery route">
<svg viewBox="0 0 900 420" preserveAspectRatio="xMidYMid slice"><rect width="900" height="420" fill="#EAE6DF"/>
<path d="M-20 300 C 120 260, 200 330, 330 300 S 520 220, 640 260 S 820 330, 940 290 L 940 340 C 820 380, 700 310, 640 312 S 470 280, 340 350 S 120 320, -20 360 Z" fill="#D5DEE3"/>
<g fill="#D9DFCF"><rect x="60" y="40" width="90" height="70" rx="6"/><rect x="420" y="70" width="70" height="90" rx="6"/><rect x="700" y="60" width="120" height="60" rx="6"/><rect x="150" y="360" width="110" height="50" rx="6"/><rect x="560" y="350" width="80" height="60" rx="6"/></g>
<g stroke="#F7F5F1" stroke-width="10" fill="none"><path d="M0 130H900"/><path d="M0 220H900"/><path d="M180 0V420"/><path d="M380 0V420"/><path d="M600 0V420"/><path d="M780 0V420"/></g>
<g stroke="#F7F5F1" stroke-width="4" fill="none" opacity=".9"><path d="M0 60H900M0 180H900M0 260H900M0 390H900M90 0V420M270 0V420M480 0V420M690 0V420M850 0V420"/></g>
<g stroke="#FFFFFF" stroke-width="14" fill="none" opacity=".9"><path d="M-10 410 L 300 160 L 520 120 L 910 30"/></g>
<polyline points="<?= sl_v($d, 'routeAll') ?>" fill="none" stroke="#1E1D1A" stroke-width="3" stroke-dasharray="7 7" stroke-linecap="round" opacity=".55"/>
<polyline points="<?= sl_v($d, 'routeDone') ?>" fill="none" stroke="#E2632A" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
<circle cx="<?= sl_v($d, 'sx') ?>" cy="<?= sl_v($d, 'sy') ?>" r="9" fill="#fff" stroke="#1E1D1A" stroke-width="3"/>
<g transform="translate(<?= sl_v($d, 'ex') ?> <?= sl_v($d, 'ey') ?>)"><path d="M0 -26c-9 0-16 7-16 16 0 12 16 26 16 26s16-14 16-26c0-9-7-16-16-16z" fill="#1E1D1A"/><circle cy="-10" r="6" fill="#fff"/></g>
<g transform="translate(<?= sl_v($d, 'rx') ?> <?= sl_v($d, 'ry') ?>)"><circle r="26" fill="#E2632A" opacity=".18"><animate attributeName="r" values="18;30;18" dur="2s" repeatCount="indefinite"/></circle><circle r="18" fill="#E2632A" stroke="#fff" stroke-width="3"/><path d="M-8 -3h9v8h-9zM1 0h4l3 3v2h-7z" fill="#fff"/><circle cx="-5" cy="6" r="2" fill="#fff"/><circle cx="5" cy="6" r="2" fill="#fff"/></g>
</svg>
<span class="dt-live"><i aria-hidden="true"></i>Live · updated <?= sl_v($d, 'ago') ?></span>
<div class="dt-ctl" style="top:14px"><a href="<?= sl_route('DeliveryTracking') ?>" aria-label="Full screen"><i class="ic ic-sm i-expand" aria-hidden="true"></i></a></div>
<div class="dt-ctl" style="bottom:14px"><button aria-label="Zoom in"><i class="ic ic-sm i-plus" aria-hidden="true"></i></button><button aria-label="Zoom out"><i class="ic ic-sm i-minus" aria-hidden="true"></i></button><button aria-label="Centre on rider"><i class="ic ic-sm i-pin" aria-hidden="true"></i></button></div>
<div class="dt-chip"><span><small class="mut">Distance left</small><b><?= sl_v($d, 'km') ?> km</b></span><span><small class="mut">Speed</small><b><?= sl_v($d, 'kmh') ?> km/h</b></span><span><small class="mut">Stops before</small><b><?= sl_v($d, 'stops') ?></b></span></div>
</div>
<div class="dt-g">
<div class="col g16">
<section class="sl-card col g12"><h2 class="sl-ct">Shipping info</h2><div class="dt-kv"><div><small>Tracking number</small><b class="mono"><?= sl_v($d, 'sel.id') ?></b></div><div><small>Courier</small><b><?= sl_v($d, 'sel.courier') ?></b></div><div><small>Type</small><b><?= sl_v($d, 'sel.type') ?></b></div><div><small>Pieces</small><b><?= sl_v($d, 'sel.pcs') ?></b></div><div><small>Weight</small><b><?= sl_v($d, 'sel.kg') ?></b></div><div><small>Delivery fee</small><b><?= sl_v($d, 'sel.fee') ?></b></div></div><div class="row between sm" style="padding-top:10px;border-top:1px solid var(--line)"><span class="mut">Pickup code to collect at the door</span><b class="mono" style="letter-spacing:.18em"><?= sl_v($d, 'sel.code') ?></b></div></section>
<section class="sl-card col g12"><h2 class="sl-ct">Rider</h2><div class="row g12"><span class="me-pic <?= sl_v($d, 'sel.rpic') ?>" style="width:52px;height:52px"><i class="me-on" aria-hidden="true"></i></span><span class="col grow"><b class="fw5"><?= sl_v($d, 'sel.rider') ?></b><span class="sm" style="color:var(--green-ink)">● Online · <?= sl_v($d, 'sel.rstat') ?></span></span><a class="dt-act" href="<?= sl_route('RiderDetail') ?>" aria-label="Call rider"><i class="ic ic-sm i-phone" aria-hidden="true"></i></a><a class="dt-act" href="<?= sl_route('RiderDetail') ?>" aria-label="Message rider"><i class="ic ic-sm i-message" aria-hidden="true"></i></a></div><div class="dt-kv"><div><small>Vehicle</small><b><?= sl_v($d, 'sel.veh') ?></b></div><div><small>Plate</small><b class="mono"><?= sl_v($d, 'sel.plate') ?></b></div><div><small>Today</small><b><?= sl_v($d, 'sel.today') ?></b></div></div><a class="sm fw5" href="<?= sl_route('RiderDetail') ?>">Open rider page →</a></section>
</div>
<section class="sl-card col g16"><div class="sl-ch" style="margin:0"><h2 class="sl-ct">Route details</h2><span class="mut sm"><?= sl_v($d, 'sel.mode') ?></span></div><div class="dt-tl"><?php foreach ((array) sl_get($d, 'steps', []) as $__i => $t): ?><div class="dt-st <?= sl_v($t, 'cls') ?>"><span class="d" title="<?= sl_v($t, 'what') ?>"><i class="ic ic-sm <?= sl_v($t, 'icon') ?>" aria-hidden="true"></i></span><span class="col"><b class="fw5 sm"><?= sl_v($t, 'date') ?></b><small><?= sl_v($t, 'time') ?></small></span><span class="col"><b class="fw5 sm"><?= sl_v($t, 'what') ?></b><small><?= sl_v($t, 'where') ?></small></span></div><?php endforeach; ?></div></section>
</div></section></div>
