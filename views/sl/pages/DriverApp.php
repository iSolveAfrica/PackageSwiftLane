<?php
/**
 * DriverApp - generated from docs/handover/artifact-source/DriverApp.dc.html by scripts/sl_convert.py.
 * Shell page: no (plain page)
 * Data contract ($d):
 *   loop  segs                         as $g
 *   loop  pkgs                         as $p
 *   loop  boxes                        as $b
 *   loop  keys                         as $k
 *   loop  stops                        as $s
 *   loop  pkgsAll                      as $p
 *   loop  week                         as $w
 *   loop  payouts                      as $p
 *   loop  tabs                         as $t
 *   if    toast
 *   if    inDetail
 *   if    notDetail
 *   if    scrHome
 *   if    hasNext
 *   if    scrDetail
 *   if    d.note
 *   if    canDeliver
 *   if    codeBad
 *   if    delivered
 *   if    scrMap
 *   if    scrPay
 *   if    scrMe
 *   value alerts, back, boxes, canDeliver, codeBad, codeCls, confOp, confirm, delivered, dismiss, done, earned, fail, false, feesToday, hasNext, inDetail, keys, left, notDetail, onAria, onCls, onLabel, onSub, openNext, payouts, photoCls, photoLabel, pkgs, pkgsAll, routeInfo, scrDetail, scrHome, scrMap, scrMe, scrPay, segs, stops, tabs, takePhoto, title, toast, toggleOn, total, true, week
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="da">
<?php if (sl_get($d, 'toast')): ?><div class="da-toast" role="status"><i class="ic i-box" aria-hidden="true"></i><span class="grow"><?= sl_v($d, 'toast') ?></span><button class="da-ib" style="width:36px;height:36px;background:transparent;border-color:rgba(255,255,255,.2);color:#fff" aria-label="Dismiss" data-sl-on="click:dismiss"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div><?php endif; ?>
<header class="da-top"><?php if (sl_get($d, 'inDetail')): ?><button class="da-ib" aria-label="Back" data-sl-on="click:back"><i class="ic i-left" aria-hidden="true"></i></button><?php endif; ?><?php if (sl_get($d, 'notDetail')): ?><span class="me-pic p-kb" style="width:44px;height:44px"><i class="me-on" aria-hidden="true"></i></span><?php endif; ?><h1><?= sl_v($d, 'title') ?></h1><a class="da-ib" href="<?= sl_route('Notifications') ?>" aria-label="Alerts"><i class="ic i-bell" aria-hidden="true"></i><b><?= sl_v($d, 'alerts') ?></b></a></header>
<div class="da-body">
<?php if (sl_get($d, 'scrHome')): ?>
<div class="da-on"><span class="t"><b class="fw5"><?= sl_v($d, 'onLabel') ?></b><small><?= sl_v($d, 'onSub') ?></small></span><button class="da-sw <?= sl_v($d, 'onCls') ?>" role="switch" aria-checked="<?= sl_v($d, 'onAria') ?>" aria-label="Online" data-sl-on="click:toggleOn"></button></div>
<div class="da-k"><div><b><?= sl_v($d, 'left') ?></b><small>To deliver</small></div><div><b><?= sl_v($d, 'done') ?></b><small>Delivered</small></div><div><b><?= sl_v($d, 'earned') ?></b><small>Earned today</small></div></div>
<?php if (sl_get($d, 'hasNext')): ?><section class="da-next"><div class="da-mini"><svg viewBox="0 0 360 130" preserveAspectRatio="xMidYMid slice"><rect width="360" height="130" fill="#EAE6DF"/><path d="M0 95 C 80 70, 140 115, 220 90 S 320 60, 360 80 L 360 110 C 300 100, 250 125, 200 118 S 70 100, 0 125Z" fill="#D5DEE3"/><g stroke="#F7F5F1" stroke-width="7"><path d="M0 40H360M0 75H360M70 0V130M180 0V130M280 0V130"/></g><polyline points="40,110 70,75 180,75 180,40 280,40" fill="none" stroke="#E2632A" stroke-width="4" stroke-linecap="round"/><circle cx="70" cy="75" r="9" fill="#E2632A" stroke="#fff" stroke-width="3"/><path d="M280 18c-6 0-11 5-11 11 0 8 11 18 11 18s11-10 11-18c0-6-5-11-11-11z" fill="#1E1D1A"/></svg></div>
<div class="in"><div class="da-row"><span class="up grow">Next stop · <?= sl_v($d, 'next.eta') ?></span><span class="sl-pill onway"><?= sl_v($d, 'next.km') ?> km</span></div><div class="da-row"><span class="col grow"><b class="fw5" style="font-size:17px"><?= sl_v($d, 'next.name') ?></b><span class="mut sm"><?= sl_v($d, 'next.addr') ?></span></span><span class="da-fee"><?= sl_v($d, 'next.fee') ?><small>you get <?= sl_v($d, 'next.cut') ?></small></span></div>
<div class="da-row" style="gap:8px"><a class="da-sec" href="tel:+233245510921"><i class="ic ic-sm i-phone" aria-hidden="true"></i>Call</a><a class="da-sec" href="<?= sl_route('ComposeMessage') ?>"><i class="ic ic-sm i-message" aria-hidden="true"></i>WhatsApp</a><button class="da-sec" data-sl-on="click:openNext"><i class="ic ic-sm i-route" aria-hidden="true"></i>Details</button></div>
<button class="da-pri" data-sl-on="click:openNext"><i class="ic ic-sm i-key" aria-hidden="true"></i>Deliver with pickup code</button></div></section><?php endif; ?>
<div class="da-row"><b class="fw5 grow">My packages</b><span class="mut sm"><?= sl_v($d, 'total') ?> assigned</span></div>
<div class="da-seg"><?php foreach ((array) sl_get($d, 'segs', []) as $__i => $g): ?><button class="<?= sl_v($g, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($g, 'label') ?></button><?php endforeach; ?></div>
<?php foreach ((array) sl_get($d, 'pkgs', []) as $__i => $p): ?><button class="da-pk <?= sl_v($p, 'anim') ?>" data-sl-on="click:open"><span class="da-n <?= sl_v($p, 'nCls') ?>"><?= sl_v($p, 'n') ?></span><span class="m"><b class="fw5"><?= sl_v($p, 'name') ?></b><small><?= sl_v($p, 'addr') ?></small><small class="mono"><?= sl_v($p, 'id') ?> · <?= sl_v($p, 'pcs') ?></small></span><span class="col" style="align-items:flex-end;gap:6px"><span class="sl-pill <?= sl_v($p, 'pc') ?>"><?= sl_v($p, 'st') ?></span><span class="da-fee"><?= sl_v($p, 'fee') ?></span></span></button><?php endforeach; ?>
<?php endif; ?>
<?php if (sl_get($d, 'scrDetail')): ?>
<section class="da-sheet"><div class="da-row"><span class="me-pic <?= sl_v($d, 'd.pic') ?>" style="width:52px;height:52px"></span><span class="col grow"><b class="fw5" style="font-size:17px"><?= sl_v($d, 'd.name') ?></b><span class="mut sm"><?= sl_v($d, 'd.locker') ?> · <?= sl_v($d, 'd.phone') ?></span></span><span class="sl-pill <?= sl_v($d, 'd.pc') ?>"><?= sl_v($d, 'd.st') ?></span></div>
<div class="da-row"><i class="ic i-pin" aria-hidden="true"></i><span class="grow"><?= sl_v($d, 'd.addr') ?></span></div>
<div class="da-row" style="gap:8px"><a class="da-sec" href="tel:+233245510921"><i class="ic ic-sm i-phone" aria-hidden="true"></i>Call</a><a class="da-sec" href="<?= sl_route('ComposeMessage') ?>"><i class="ic ic-sm i-message" aria-hidden="true"></i>WhatsApp</a><a class="da-sec" href="<?= sl_route('DeliveryTracking') ?>"><i class="ic ic-sm i-route" aria-hidden="true"></i>Navigate</a></div>
<div class="da-kv"><div><small>Tracking</small><b class="mono"><?= sl_v($d, 'd.id') ?></b></div><div><small>Pieces</small><b><?= sl_v($d, 'd.pcs') ?></b></div><div><small>Weight</small><b><?= sl_v($d, 'd.kg') ?></b></div><div><small>Collect cash</small><b><?= sl_v($d, 'd.cash') ?></b></div><div><small>Delivery fee</small><b><?= sl_v($d, 'd.fee') ?></b></div><div><small>You get (64%)</small><b><?= sl_v($d, 'd.cut') ?></b></div></div>
<?php if (sl_get($d, 'd.note')): ?><div class="sl-banner info"><i class="ic i-info" aria-hidden="true"></i><span><?= sl_v($d, 'd.note') ?></span></div><?php endif; ?></section>
<?php if (sl_get($d, 'canDeliver')): ?><section class="da-sheet"><b class="fw5">Ask the customer for their 4-digit pickup code</b><div class="da-code <?= sl_v($d, 'codeCls') ?>"><?php foreach ((array) sl_get($d, 'boxes', []) as $__i => $b): ?><span class="<?= sl_v($b, 'cls') ?>"><?= sl_v($b, 'd') ?></span><?php endforeach; ?></div>
<?php if (sl_get($d, 'codeBad')): ?><div class="da-bad"><i class="ic ic-sm i-x" aria-hidden="true"></i>That code doesn't match. Check with the customer.</div><?php endif; ?>
<div class="da-pad"><?php foreach ((array) sl_get($d, 'keys', []) as $__i => $k): ?><button aria-label="<?= sl_v($k, 'l') ?>" data-sl-on="click:press"><?= sl_v($k, 'l') ?></button><?php endforeach; ?></div>
<button class="da-photo <?= sl_v($d, 'photoCls') ?>" data-sl-on="click:takePhoto"><span><i class="ic i-image" aria-hidden="true"></i> <?= sl_v($d, 'photoLabel') ?></span></button>
<button class="da-pri" style="opacity:<?= sl_v($d, 'confOp') ?>" data-sl-on="click:confirm"><i class="ic ic-sm i-check" aria-hidden="true"></i>Confirm delivery</button>
<button class="da-sec" style="flex:none" data-sl-on="click:fail">Customer not home · try later</button></section><?php endif; ?>
<?php if (sl_get($d, 'delivered')): ?><div class="da-ok"><i class="ic i-check" aria-hidden="true"></i><span>Delivered and code matched. <?= sl_v($d, 'd.cut') ?> added to today's earnings.</span></div><button class="da-pri" data-sl-on="click:back">Next package</button><?php endif; ?>
<?php endif; ?>
<?php if (sl_get($d, 'scrMap')): ?>
<section class="da-next"><div class="da-mini" style="height:300px"><svg viewBox="0 0 360 300" preserveAspectRatio="xMidYMid slice"><rect width="360" height="300" fill="#EAE6DF"/><path d="M0 200 C 90 170, 150 230, 230 200 S 330 160, 360 180 L 360 220 C 300 210, 240 250, 200 240 S 60 220, 0 250Z" fill="#D5DEE3"/><g stroke="#F7F5F1" stroke-width="8"><path d="M0 60H360M0 130H360M0 270H360M60 0V300M170 0V300M290 0V300"/></g><polyline points="30,280 60,130 170,130 170,60 290,60 290,130 330,130" fill="none" stroke="#E2632A" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><?php foreach ((array) sl_get($d, 'stops', []) as $__i => $s): ?><g transform="translate(<?= sl_v($s, 'x') ?> <?= sl_v($s, 'y') ?>)"><circle r="13" fill="<?= sl_v($s, 'fill') ?>" stroke="#fff" stroke-width="3"/><text y="4" text-anchor="middle" font-size="11" font-weight="600" fill="#fff" font-family="Figtree"><?= sl_v($s, 'n') ?></text></g><?php endforeach; ?><circle cx="<?= sl_v($d, 'me.x') ?>" cy="<?= sl_v($d, 'me.y') ?>" r="9" fill="#2F6FB5" stroke="#fff" stroke-width="3"/></svg></div>
<div class="in"><div class="da-row"><b class="fw5 grow">Today's route</b><span class="mut sm"><?= sl_v($d, 'routeInfo') ?></span></div><a class="da-pri" href="<?= sl_route('DeliveryTracking') ?>"><i class="ic ic-sm i-route" aria-hidden="true"></i>Start navigation</a></div></section>
<section class="da-sheet"><?php foreach ((array) sl_get($d, 'pkgsAll', []) as $__i => $p): ?><div class="da-li"><span class="da-n <?= sl_v($p, 'nCls') ?>"><?= sl_v($p, 'n') ?></span><span class="col grow" style="min-width:0"><span class="fw5"><?= sl_v($p, 'name') ?></span><span class="mut sm"><?= sl_v($p, 'addr') ?></span></span><span class="sl-pill <?= sl_v($p, 'pc') ?>"><?= sl_v($p, 'st') ?></span></div><?php endforeach; ?></section>
<?php endif; ?>
<?php if (sl_get($d, 'scrPay')): ?>
<section class="da-sheet"><span class="up">Earned today</span><b style="font:600 38px var(--font);letter-spacing:-.02em"><?= sl_v($d, 'earned') ?></b><span class="mut sm"><?= sl_v($d, 'done') ?> deliveries · 64% of <?= sl_v($d, 'feesToday') ?> in delivery fees</span><div class="da-bars"><?php foreach ((array) sl_get($d, 'week', []) as $__i => $w): ?><i class="<?= sl_v($w, 'cls') ?>" style="<?= sl_v($w, 'h') ?>" title="<?= sl_v($w, 't') ?>"></i><?php endforeach; ?></div><div class="row between mut" style="font-size:11.5px"><span>Fri</span><span>Sat</span><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span></div></section>
<div class="da-k"><div><b>₵1,284</b><small>This week</small></div><div><b>₵4,820</b><small>Cash to hand in</small></div><div><b>Fri</b><small>Next payout</small></div></div>
<section class="da-sheet"><b class="fw5">Payouts</b><?php foreach ((array) sl_get($d, 'payouts', []) as $__i => $p): ?><div class="da-li"><i class="ic i-card" aria-hidden="true"></i><span class="col grow"><span class="fw5"><?= sl_v($p, 'a') ?></span><span class="mut sm"><?= sl_v($p, 'd') ?></span></span><span class="sl-pill <?= sl_v($p, 'c') ?>"><?= sl_v($p, 's') ?></span></div><?php endforeach; ?></section>
<?php endif; ?>
<?php if (sl_get($d, 'scrMe')): ?>
<section class="da-sheet" style="align-items:center;text-align:center"><span class="me-pic p-kb" style="width:84px;height:84px"><i class="me-on" aria-hidden="true"></i></span><b style="font-size:19px">Kwesi Bonsu</b><span class="mut sm">Rider · Accra Hub · ★ 4.9 · 1,204 deliveries</span></section>
<section class="da-sheet"><a class="da-li" href="<?= sl_route('RiderDetail') ?>"><i class="ic i-truck" aria-hidden="true"></i><span class="grow">Motorbike · GR 4471-24</span><i class="ic ic-sm i-right mut" aria-hidden="true"></i></a><a class="da-li" href="<?= sl_route('Documents') ?>"><i class="ic i-file" aria-hidden="true"></i><span class="grow">Licence and insurance</span><span class="sl-pill delivered">Valid</span></a><a class="da-li" href="<?= sl_route('CustomerNotifications') ?>"><i class="ic i-bell" aria-hidden="true"></i><span class="grow">Notifications</span><i class="ic ic-sm i-right mut" aria-hidden="true"></i></a><a class="da-li" href="<?= sl_route('RiderSettings') ?>"><i class="ic i-dollar" aria-hidden="true"></i><span class="grow">My fee split · 64%</span><i class="ic ic-sm i-right mut" aria-hidden="true"></i></a><a class="da-li" href="<?= sl_route('CsInbox') ?>"><i class="ic i-help" aria-hidden="true"></i><span class="grow">Help from the hub</span><i class="ic ic-sm i-right mut" aria-hidden="true"></i></a><a class="da-li" href="<?= sl_route('Login') ?>" style="color:var(--red-ink)"><i class="ic i-logout" aria-hidden="true"></i><span class="grow">Sign out</span></a></section>
<a class="da-sec" style="flex:none" href="<?= sl_route('RiderDispatch') ?>"><i class="ic ic-sm i-monitor" aria-hidden="true"></i>Open the desktop view</a>
<?php endif; ?>
</div>
<nav class="da-tab" aria-label="Rider app"><?php foreach ((array) sl_get($d, 'tabs', []) as $__i => $t): ?><button class="<?= sl_v($t, 'cls') ?>" data-sl-on="click:pick" aria-current="<?= sl_v($t, 'cur') ?>"><i class="ic <?= sl_v($t, 'icon') ?>" aria-hidden="true"></i><?= sl_v($t, 'label') ?></button><?php endforeach; ?></nav>
</div>
