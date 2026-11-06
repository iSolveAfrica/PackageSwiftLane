<?php
/**
 * LoyaltyRewards - generated from docs/handover/artifact-source/LoyaltyRewards.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  kpis                         as $k
 *   loop  k.sp                         as $h
 *   loop  refs                         as $r
 *   loop  tiers                        as $x
 *   loop  cps                          as $c
 *   loop  camps                        as $c
 *   loop  cf                           as $f
 *   loop  cov                          as $c
 *   if    flash
 *   value addC, addCamp, camps, cf, cov, cps, false, flash, flipRef, h, kpis, rEarned, rFriend, rRef, refAria, refCls, refs, setRF, setRR, tiers
 * sl-wrap extras in the design: style="max-width:1440px"
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:22px">Loyalty, referrals and rewards</h1><p class="sl-lede">Referral codes, points and tiers, coupons and vouchers, and campaigns that bring customers back, air shipments first.</p></div><div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('Customers') ?>">Customers</a><a class="sl-btn" href="<?= sl_route('Broadcast') ?>">Send a campaign</a></div></div>
<?php if (sl_get($d, 'flash')): ?><div class="fr-ok" role="status"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= sl_v($d, 'flash') ?></div><?php endif; ?>

<div class="hb-ks"><?php foreach ((array) sl_get($d, 'kpis', []) as $__i => $k): ?><div class="hb-k"><div class="hb-kh"><span class="hb-ki"><i class="ic ic-sm <?= sl_v($k, 'icon') ?>" aria-hidden="true"></i></span><span class="hb-kl"><?= sl_v($k, 'l') ?></span></div><b class="hb-kv <?= sl_v($k, 'anim') ?>"><?= sl_v($k, 'v') ?></b><div class="hb-kf"><span class="hb-ks2"><?= sl_v($k, 's') ?></span><span class="hb-sp" aria-hidden="true"><?php foreach ((array) sl_get($k, 'sp', []) as $__i => $h): ?><i style="<?= sl_v($h, '') ?>"></i><?php endforeach; ?></span></div></div><?php endforeach; ?></div>
<div class="hb-g">
<section class="hb-p"><div class="sl-ch" style="margin:0"><div><h2 class="sl-ct">Referral programme</h2><span class="mut sm">Every customer gets a code and link. Both sides get a reward when the friend's first air shipment is delivered.</span></div><button class="sl-toggle <?= sl_v($d, 'refCls') ?>" role="switch" aria-checked="<?= sl_v($d, 'refAria') ?>" aria-label="Referral programme" data-sl-on="click:flipRef"></button></div><div class="grid cols2" style="gap:10px"><label class="sl-field"><span class="sl-lab">Friend gets</span><input class="sl-in" value="<?= sl_v($d, 'rFriend') ?>" data-sl-on="input:setRF"></label><label class="sl-field"><span class="sl-lab">Referrer gets</span><input class="sl-in" value="<?= sl_v($d, 'rRef') ?>" data-sl-on="input:setRR"></label></div><div class="hb-out">Kwame's code: <b>KWAME-SL20</b> · swiftlane.com/r/kwame-sl20
Shared on WhatsApp 14 times · 6 friends signed up · 4 shipped · earned <?= sl_v($d, 'rEarned') ?></div>
<?php foreach ((array) sl_get($d, 'refs', []) as $__i => $r): ?><div class="hb-l"><span class="me-pic <?= sl_v($r, 'pic') ?>" style="width:26px;height:26px"></span><span class="col grow"><b class="fw5"><?= sl_v($r, 'n') ?></b><span class="mut" style="font-size:12px"><?= sl_v($r, 's') ?></span></span><b class="mono"><?= sl_v($r, 'v') ?></b></div><?php endforeach; ?></section>
<section class="hb-p"><h2 class="sl-ct">Points and tiers</h2><div class="hb-out">1 point per $1 shipped by air · 0.5 per $1 by sea · 100 points = ₵10 off
Tiers reset every 12 months</div><?php foreach ((array) sl_get($d, 'tiers', []) as $__i => $x): ?><div class="hb-l"><span class="sl-tile" style="width:32px;height:32px"><i class="ic ic-sm i-star" aria-hidden="true"></i></span><span class="col grow"><b class="fw5"><?= sl_v($x, 't') ?></b><span class="mut" style="font-size:12px"><?= sl_v($x, 'd') ?></span></span><span class="col" style="align-items:flex-end"><b class="mono"><?= sl_v($x, 'n') ?></b><span class="mut" style="font-size:11.5px">customers</span></span></div><?php endforeach; ?></section>
<section class="hb-p"><div class="sl-ch" style="margin:0"><h2 class="sl-ct">Coupons and vouchers</h2><button class="sl-btn ghost sm" data-sl-on="click:addC">New coupon</button></div><?php foreach ((array) sl_get($d, 'cps', []) as $__i => $c): ?><div class="hb-l"><span class="col grow"><b class="fw5 mono"><?= sl_v($c, 'code') ?></b><span class="mut" style="font-size:12px"><?= sl_v($c, 'd') ?></span></span><span class="mono sm"><?= sl_v($c, 'used') ?></span><button class="sl-toggle <?= sl_v($c, 'cls') ?>" role="switch" aria-checked="<?= sl_v($c, 'aria') ?>" aria-label="<?= sl_v($c, 'code') ?>" data-sl-on="click:flip"></button></div><?php endforeach; ?></section>
<section class="hb-p"><div class="sl-ch" style="margin:0"><h2 class="sl-ct">Campaigns</h2><button class="sl-btn ghost sm" data-sl-on="click:addCamp">Schedule campaign</button></div><?php foreach ((array) sl_get($d, 'camps', []) as $__i => $c): ?><div class="hb-l"><i class="ic ic-sm <?= sl_v($c, 'icon') ?>" aria-hidden="true"></i><span class="col grow"><b class="fw5"><?= sl_v($c, 't') ?></b><span class="mut" style="font-size:12px"><?= sl_v($c, 's') ?></span></span><span class="sl-pill <?= sl_v($c, 'pc') ?>"><?= sl_v($c, 'st') ?></span></div><?php endforeach; ?></section></div>
<section class="col g10"><div class="row between wrap g8"><h2 class="sl-ct">All 15 features and where they live</h2><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'cf', []) as $__i => $f): ?><button class="<?= sl_v($f, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($f, 'label') ?></button><?php endforeach; ?></div></div><div class="hb-cov"><?php foreach ((array) sl_get($d, 'cov', []) as $__i => $c): ?><a class="hb-i <?= sl_v($c, 'off') ?>" href="<?= sl_v($c, 'href') ?>"><span class="n"><?= sl_v($c, 'n') ?></span><span class="col grow"><b class="fw5 sm"><?= sl_v($c, 't') ?></b><span class="mut" style="font-size:11.5px"><?= sl_v($c, 'where') ?></span></span><span class="sl-pill <?= sl_v($c, 'pc') ?>"><?= sl_v($c, 'st') ?></span></a><?php endforeach; ?></div></section>
