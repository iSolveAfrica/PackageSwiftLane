<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-auth.css')) ?>">
<?php
/**
 * M-Track (track.php / tracking.php, view PublicTracking): public lookup of one tracking number. Same data as
 * the web page (sl_trackData: recipient masked, no addresses). Search submits GET order_track like the web form.
 */
$slPhone['nav'] = 'none';
$slPhone['cls'] = 'mx-bare';
$found = !empty($d['found']);
$in = !empty($d['loggedIn']);
$steps = (array) ($d['steps'] ?? []);
$idx = (int) ($d['stepIndex'] ?? 0);
$pct = (int) ($d['pct'] ?? 0);
$map = $d['map'] ?? null;
?>
<main class="mx-body">
<div class="b4-tbar"><a class="mx-ib" href="<?= h($in ? sl_route('Main') : sl_route('Login')) ?>" data-mx-back aria-label="Back"><i class="ic i-left" aria-hidden="true"></i></a><a class="b4-qt" href="<?= h(sl_route('QuoteCalculator')) ?>"><i class="ic ic-sm i-dollar" aria-hidden="true"></i>Get a quote</a><?php if ($in): ?><a class="b4-li" href="<?= h(sl_route('Main')) ?>">My account</a><?php else: ?><a class="b4-li" href="<?= h(sl_route('Login') . '?form=1') ?>">Log in</a><?php endif; ?></div>
<h1 class="mx-big">Track a parcel</h1>
<?php if ((float) ($d['rate'] ?? 0) > 0): ?><div class="mx-fx"><i class="ic ic-sm i-globe" aria-hidden="true"></i><span>USD <b><?= h(number_format((float) $d['rate'], 2)) ?></b></span><small>GH₵ · rate used for prices</small></div><?php endif; ?>
<div class="mx-srch"><form method="get" action="<?= h(sl_route('PublicTracking')) ?>" role="search"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="order_track" aria-label="Tracking number" value="<?= h($d['code'] ?? '') ?>" placeholder="e.g. SL-100482" class="mono" required pattern="[A-Za-z0-9-]+" autocapitalize="characters" autocomplete="off" enterkeyhint="search"></label></form></div>
<?php if (!empty($d['throttled'])): ?>
<div class="mx-ban warn" role="alert"><i class="ic ic-sm i-clock" aria-hidden="true"></i><span><b>Too many searches.</b> Please wait a minute and try again<?= $in ? '' : ', or log in to see all your parcels' ?>.</span></div>
<?php elseif (!empty($d['searched']) && !$found): ?>
<div class="mx-ban bad" role="alert"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><b>No parcel found for <?= h($d['code'] ?? '') ?>.</b> Check the number on your receipt. A parcel appears here once our warehouse has registered it.</span></div>
<?php elseif ($found): ?>
<section class="mx-hero" aria-label="Tracking result">
<div class="mx-hk"><span class="mono"><?= h($d['number'] ?? '') ?></span> · <?= !empty($d['air']) ? 'Air' : 'Sea' ?><?php if (($d['pay'] ?? '') !== '' && ($d['pay'] ?? '') !== '—'): ?><span class="mx-tag"><?= h($d['pay']) ?></span><?php endif; ?></div>
<b class="mx-hv"><?= h($d['st'] ?? '') ?></b>
<small><?= h(($d['from'] ?? '—') . ' → ' . ($d['to'] ?? '—')) ?> · ETA <?= h($d['eta'] ?? 'Not set') ?> · <?= $pct ?>% of the way</small>
<div class="b4-5" role="img" aria-label="Step <?= $idx + 1 ?> of <?= count($steps) ?>"><?php foreach ($steps as $i => $s): ?><i class="<?= $i < $idx || $idx === count($steps) - 1 ? 'd' : ($i === $idx ? 'c' : '') ?>"></i><?php endforeach; ?></div>
<div class="b4-5l"><?php foreach ($steps as $s): ?><span><?= h($s['l']) ?></span><?php endforeach; ?></div>
</section>
<?php if ($map): $f = (float) ($map['frac'] ?? 0); $px = 40 + 280 * $f; $py = 120 - 4 * 70 * $f * (1 - $f) - 0; ?>
<section class="mx-map" style="--mh:170px" aria-label="Flight route"><span class="mx-ml"><?= h(($map['from']['label'] ?? '') . ' → ' . ($map['to']['label'] ?? '')) ?></span><svg viewBox="0 0 360 170" preserveAspectRatio="xMidYMid slice" aria-hidden="true"><rect width="360" height="170" fill="#E9E6E0"/><path d="M0 140 C 90 120, 160 150, 240 128 S 320 110, 360 118 L 360 170 L 0 170Z" fill="#D9DEE0"/><path d="M40 120 Q 180 -20 320 120" fill="none" stroke="#D9792B" stroke-width="4" stroke-linecap="round" stroke-dasharray="6 7" class="mx-dash"/><circle cx="40" cy="120" r="8" fill="#161615" stroke="#fff" stroke-width="3"/><path d="M320 102c-6 0-11 5-11 11 0 8 11 18 11 18s11-10 11-18c0-6-5-11-11-11z" fill="#161615"/><circle cx="320" cy="113" r="4" fill="#fff"/><circle cx="<?= round($px, 1) ?>" cy="<?= round($py, 1) ?>" r="13" fill="#D9792B" opacity=".18"/><circle cx="<?= round($px, 1) ?>" cy="<?= round($py, 1) ?>" r="7" fill="#D9792B" stroke="#fff" stroke-width="3"/></svg></section>
<?php endif; ?>
<section class="mx-card"><ol class="mx-tl">
<?php for ($i = count($steps) - 1; $i > $idx; $i--): ?><li class="todo"><span class="mx-td"><i class="ic ic-sm <?= h($steps[$i]['i']) ?>" aria-hidden="true"></i></span><div><b><?= h($steps[$i]['l']) ?></b><small><?= $i === count($steps) - 1 && ($d['eta'] ?? '') !== 'Not set' ? 'Expected ' . h($d['eta'] ?? '') : 'Next' ?></small></div></li><?php endfor; ?>
<?php foreach ((array) ($d['events'] ?? []) as $k => $e): ?><li class="<?= $k === 0 ? 'cur' : 'done' ?>"><span class="mx-td"><i class="ic ic-sm <?= h($e['ic'] ?? 'i-check') ?>" aria-hidden="true"></i></span><div><b><?= h($e['t'] ?? '') ?></b><small><?= h($e['s'] ?? '') ?></small></div></li><?php endforeach; ?>
<?php if (empty($d['events'])): ?><li class="cur"><span class="mx-td"><i class="ic ic-sm i-newdoc" aria-hidden="true"></i></span><div><b>Registered</b><small><?= h($d['registered'] ?? '') ?> · no tracking events yet</small></div></li><?php endif; ?>
</ol></section>
<div class="mx-sec"><h2>Package details</h2></div>
<section class="mx-card mx-kv">
<div><span>Registered</span><b><?= h($d['registered'] ?? '—') ?></b></div>
<div><span>Contents</span><b><?= h($d['contents'] ?? '—') ?></b></div>
<div><span>Store</span><b><?= h($d['store'] ?? '—') ?></b></div>
<div><span>Carrier tracking</span><b><span class="mono"><?= h(($d['carrierNo'] ?? '') !== '' ? $d['carrierNo'] : '—') ?></span></b></div>
<div><span>Weight</span><b><?= h($d['weight'] ?? '—') ?></b></div>
<div><span>Dimensions</span><b><?= h($d['dims'] ?? '—') ?></b></div>
<div><span>Pieces</span><b><?= h($d['pieces'] ?? '—') ?></b></div>
<div><span>Service</span><b><?= h($d['mode'] ?? '—') ?></b></div>
<div><span>Consolidation</span><b class="mono"><?= h($d['consolidation'] ?? '—') ?></b></div>
<div><span>Recipient</span><b><?= h($d['recipient'] ?? '—') ?></b></div>
<div><span>Payment</span><b><span class="sl-pill <?= h($d['payCls'] ?? '') ?>"><?= h($d['pay'] ?? '—') ?></span></b></div>
</section>
<?php if (!empty($d['items'])): ?>
<div class="mx-sec"><h2>Items in the box</h2></div>
<section class="mx-card mx-list"><?php foreach ((array) $d['items'] as $it): ?><div class="mx-row"><span class="mx-li"><i class="ic i-box" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($it['n'] ?? '') ?></b><small><?= (int) ($it['q'] ?? 1) ?> ×<?= ($it['w'] ?? '') !== '' ? ' · ' . h($it['w']) : '' ?></small></span></div><?php endforeach; ?></section>
<?php endif; ?>
<?php endif; ?>
<section class="b4-cta"><span class="mx-li"><i class="ic i-message" aria-hidden="true"></i></span><div><b>Want updates on WhatsApp?</b>Arrival, price and pickup code messages for your parcels.<div class="b4-ca"><?php if ($in): ?><a href="<?= h(sl_route('CustomerNotifications')) ?>">Choose your alerts</a><?php else: ?><a href="<?= h(sl_route('Login') . '?form=1') ?>">Log in</a><?php endif; ?></div></div></section>
<?php if (!$in): ?><a class="mx-lnk" href="<?= h(sl_route('SignUp')) ?>">No account? Create one free</a><?php endif; ?>
</main>
