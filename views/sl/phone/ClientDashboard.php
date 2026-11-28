<?php
/**
 * M-CHome (index.php, customer, view ClientDashboard): greeting, parcel counts, ready-for-pickup card, overview,
 * the last 7 days of registered parcels, quick links, active parcels and the shipping address.
 * Data: sl_clientDashboardData(); plus the unread count and the first parcel ready for pickup (own rows only).
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
global $core;
$slPhone['nav'] = 'customer';
$slPhone['tab'] = 'Home';
$uid = (int) ($d['uid'] ?? 0);
$unread = (int) sl_scalar('SELECT COUNT(*) FROM cdb_notifications_users WHERE user_id = :u AND notification_read = 0', [':u' => $uid]);
$hour = (int) date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$readyRow = (int) ($d['ready'] ?? 0) > 0 ? sl_row("SELECT * FROM (
    SELECT 'air' AS src, order_id AS id, CONCAT(order_prefix,order_no) AS trk, order_date, provider_purchase FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND status_courier IN (6,32)
    UNION ALL
    SELECT 'pkg', order_id, CONCAT(order_prefix,order_no), order_date, provider_purchase FROM cdb_customers_packages WHERE sender_id = :u2 AND status_courier IN (6,32)
) x ORDER BY order_date DESC", [':u' => $uid, ':u2' => $uid]) : null;
$st = function (array $r): string {
    if (stripos($r['st'], 'ready') !== false) return 'ok';
    if (in_array($r['st'], ['Delivered', 'Picked up'], true)) return 'ink';
    if (in_array($r['cls'], ['overdue'], true) || stripos($r['st'], 'payment') !== false) return 'bad';
    return $r['mode'] === 'Air' ? 'air' : 'am';
};
$act = (array) ($d['parcelActivity'] ?? []);
$week = array_slice($act, -7, 7, true);
$weekTop = max(5, ...array_values($week ?: [0]));
$weekSum = array_sum($week);
$prevSum = array_sum(array_slice($act, 0, 7));
$rate = (float) ($core->exchange_rate ?? 0);
$addr = $d['addresses'][0] ?? null;
// The greeting uses one word: the first part of the first name, in normal case (HENRY NTIAKO AYI -> Henry).
$cuteFirst = (string) (preg_split('/\s+/u', trim((string) ($d['first'] ?? '')))[0] ?? '');
$cuteFirst = $cuteFirst !== '' ? mb_convert_case(mb_strtolower($cuteFirst), MB_CASE_TITLE, 'UTF-8') : 'there';
$slPhone['scripts'][] = 'dataJs/sl/phone/help_prompt.js';
$rows = (array) ($d['mobileRows'] ?? []);
if (!$rows) $rows = array_slice((array) ($d['rows'] ?? []), 0, 3);
?>
<header class="mx-top"><a class="mx-av" href="<?= h(sl_route('MyAccount')) ?>" aria-label="My account"><?= h($d['ini'] ?? '?') ?><i aria-hidden="true"></i></a><div class="mx-ttl"><h1>Home</h1></div><?= sl_phoneIcon('search', 'Track a parcel', sl_route('LiveTracking')) . sl_phoneIcon('bell', 'Notifications', sl_route('MyNotifications'), $unread) ?></header>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-customer-home.css')) ?>">
<main class="mx-body">
<header class="b3-hh"><a class="b3-av" href="<?= h(sl_route('MyAccount')) ?>" aria-label="My account"><?= h($d['ini'] ?? '?') ?></a><span class="b3-hg"><small><i class="ic ic-sm i-sun" aria-hidden="true"></i><?= h($greet) ?></small><b><?= h($cuteFirst) ?> <span class="b3-wave" aria-hidden="true">👋</span></b></span><a class="b3-chat ch-ic" href="support.php" aria-label="Chat with us" title="Chat with us"><i class="ic i-message" aria-hidden="true"></i><i class="ch-dot" aria-hidden="true"></i></a><a class="b3-bell" href="<?= h(sl_route('MyNotifications')) ?>" aria-label="Notifications<?= $unread ? ', ' . $unread . ' unread' : '' ?>"><i class="ic i-bell" aria-hidden="true"></i><?php if ($unread): ?><i><?= $unread > 99 ? '99+' : $unread ?></i><?php endif; ?></a></header>
<h2 class="b3-hl">Your parcels<span><?= (int) ($d['active'] ?? 0) > 0 ? (int) $d['active'] . ' on the move.' : 'Nothing on the move.' ?></span></h2>
<div class="b3-sr"><a class="b3-sp" href="<?= h(sl_route('PublicTracking')) ?>"><i class="ic i-search" aria-hidden="true"></i><span>Where’s your parcel?</span></a><a class="b3-scan" href="<?= h(sl_route('LiveTracking')) ?>" aria-label="Track my parcels"><i class="ic i-route" aria-hidden="true"></i></a></div>
<section class="b3-kp">
<a class="kp-way" href="<?= h(sl_route('LiveTracking')) ?>"><span class="kp-ic"><i class="ic ic-sm i-plane" aria-hidden="true"></i></span><b><?= (int) ($d['onWay'] ?? 0) ?></b><small>On the way</small><em><?= (int) ($d['active'] ?? 0) ?> active</em></a>
<a class="kp-wh" href="<?= h(sl_route('MyParcels')) ?>"><span class="kp-ic"><i class="ic ic-sm i-warehouse" aria-hidden="true"></i></span><b><?= (int) ($d['atWh'] ?? 0) ?></b><small>At warehouse</small><em>Being processed</em></a>
<a class="kp-rd <?= (int) ($d['ready'] ?? 0) > 0 ? 'ok' : '' ?>" href="<?= h(sl_route('PickupCodes')) ?>"><span class="kp-ic"><i class="ic ic-sm i-key" aria-hidden="true"></i></span><b><?= (int) ($d['ready'] ?? 0) ?></b><small>Ready</small><em><?= (int) ($d['ready'] ?? 0) > 0 ? 'Bring ID' : 'Nothing yet' ?></em></a>
<?php if (!empty($d['canBills'])): ?><a class="kp-bl <?= (int) $d['dueN'] > 0 ? 'warn' : '' ?>" href="<?= h(sl_route('MyBills')) ?>"><span class="kp-ic"><i class="ic ic-sm i-receipt" aria-hidden="true"></i></span><b><?= h($d['dueMoney'] ?? '') ?></b><small>Balance due</small><em><?= (int) $d['dueN'] ?> bill<?= (int) $d['dueN'] === 1 ? '' : 's' ?><?= ($d['dueDate'] ?? '') !== '' ? ' · ' . h($d['dueDate']) : '' ?></em></a><?php else: ?><a class="kp-yr" href="<?= h(sl_route('MyParcels')) ?>"><span class="kp-ic"><i class="ic ic-sm i-box" aria-hidden="true"></i></span><b><?= (int) ($d['year'] ?? 0) ?></b><small>This year</small><em>Parcels</em></a><?php endif; ?>
</section>
<?php if ($readyRow): ?>
<section class="b3-cx"><div class="b3-cxh"><span class="b3-oi dk"><i class="ic i-key" aria-hidden="true"></i></span><span class="mx-rm"><b><?= (int) $d['ready'] ?> parcel<?= (int) $d['ready'] === 1 ? '' : 's' ?> ready for pickup</b><small><?= h($readyRow->trk) ?><?= ($readyRow->provider_purchase ?? '') !== '' ? ' · ' . h($readyRow->provider_purchase) : '' ?> · bring your ID and pickup code</small></span><span class="b3-st ok">Ready</span></div><div class="b3-row2"><a class="b3-cta" href="<?= h(sl_route('PickupCodes')) ?>"><i class="ic ic-sm i-key" aria-hidden="true"></i>Pickup codes</a><a class="b3-gh" href="delivery_request.php"><i class="ic ic-sm i-truck" aria-hidden="true"></i>Deliver to me</a></div></section>
<?php endif; ?>
<?= sl_mxSec('Shipment overview', 'See all', sl_route('MyParcels')) ?>
<section class="b3-ov">
<div class="b3-ot"><div class="b3-oh"><span class="b3-oi"><i class="ic i-box" aria-hidden="true"></i></span></div><b><?= (int) ($d['active'] ?? 0) ?></b><small>Parcels moving</small><div class="b3-of"><em><?= (int) ($d['atWh'] ?? 0) ?> at the warehouse</em><a href="<?= h(sl_route('LiveTracking')) ?>" aria-label="Open parcels moving"><i class="ic ic-sm i-arrow" aria-hidden="true"></i></a></div></div>
<div class="b3-ot"><div class="b3-oh"><span class="b3-oi"><i class="ic i-check" aria-hidden="true"></i></span></div><b><?= (int) ($d['deliveredYear'] ?? 0) ?></b><small>Delivered this year</small><div class="b3-of"><em><?= ($d['since'] ?? '') !== '' ? 'With us since ' . h($d['since']) : (int) ($d['year'] ?? 0) . ' this year' ?></em><a href="<?= h(sl_route('MyParcels') . '?filter=delivered') ?>" aria-label="Open delivered parcels"><i class="ic ic-sm i-arrow" aria-hidden="true"></i></a></div></div>
</section>
<?php if ($week): ?>
<section class="b3-wk" aria-label="Parcels registered in the last 7 days"><div class="b3-wkh"><b>Weekly overview</b><span class="b3-dd">Last 7 days</span></div><div class="b3-wkb"><div class="b3-ya" aria-hidden="true"><?php for ($k = 5; $k >= 0; $k--): ?><span><?= h((string) round($weekTop * $k / 5)) ?></span><?php endfor; ?></div><div class="b3-bs"><?php $last = array_key_last($week); foreach ($week as $day => $n): ?><span class="<?= $day === $last ? 'cur' : '' ?>" title="<?= h(sl_date($day, 'D j M')) ?>: <?= (int) $n ?>"><i style="height:<?= max(3, round($n / $weekTop * 100)) ?>%"></i><small><?= h(date('D', strtotime($day))) ?></small></span><?php endforeach; ?><div class="b3-tip" style="right:0"><span><?= h(sl_date($last, 'D j M Y')) ?></span><div><small>Registered</small><b><?= (int) $week[$last] ?></b></div><div><small>This week</small><em><?= $weekSum ?><?= $prevSum ? ' vs ' . $prevSum : '' ?></em></div></div></div></div></section>
<p class="mx-note">Parcels registered for you each day over the last 7 days.</p>
<?php endif; ?>
<?= sl_mxSec('Quick links') ?>
<section class="b3-qa">
<?php if (!empty($d['canShip'])): ?><a href="courier_add_client.php"><span><i class="ic i-plus" aria-hidden="true"></i></span><small>Ship a parcel</small></a><?php endif; ?>
<?php if (!empty($d['canPrealert'])): ?><a href="prealert_add.php"><span><i class="ic i-inbox" aria-hidden="true"></i></span><small>Pre-alert</small></a><?php endif; ?>
<a href="delivery_request.php"><span><i class="ic i-truck" aria-hidden="true"></i></span><small>Request delivery</small></a>
<a href="<?= h(sl_route('PickupCodes')) ?>"><span><i class="ic i-key" aria-hidden="true"></i></span><small>Pickup codes</small></a>
<?php if (!empty($d['canBills'])): ?><a href="<?= h(sl_route('MyBills')) ?>"><span><i class="ic i-receipt" aria-hidden="true"></i><?php if ((int) $d['dueN'] > 0): ?><em><?= (int) $d['dueN'] ?> due</em><?php endif; ?></span><small>Pay bills</small></a><?php endif; ?>
<a href="support.php"><span><i class="ic i-help" aria-hidden="true"></i></span><small>Get help</small></a>
</section>
<?php if ($rate > 0): ?><a class="b3-fx" href="<?= h(sl_route('QuoteCalculator')) ?>"><i class="ic ic-sm i-globe" aria-hidden="true"></i><span>USD 1 = <b>GH₵<?= h(number_format($rate, 2)) ?></b></span><small>rate used for prices</small></a><?php endif; ?>
<?= sl_mxSec('Your parcels', 'View all', sl_route('MyParcels')) ?>
<?php if ($rows): ?>
<section class="b3-cd b3-pl2"><?php foreach ($rows as $r): ?><a class="b3-pr" href="<?= h($r['href']) ?>"><span class="b3-oi"><i class="ic <?= $r['mode'] === 'Air' ? 'i-plane' : 'i-ship' ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($r['id']) ?><?= $r['from'] !== '—' ? ' · ' . h($r['from']) : '' ?></b><small><?= h($r['st']) ?> · <?= h($r['date']) ?><?= $r['c'] !== '—' ? ' · ' . h($r['c']) : '' ?></small><span class="b3-pb"><i style="<?= h($r['pg']) ?>"></i></span></span><span class="b3-st <?= $st($r) ?>"><?= h($r['st']) ?></span></a><?php endforeach; ?></section>
<?php else: ?>
<?= sl_mxEmpty('box', 'No parcels yet', 'Shop with your shipping address below and your parcels show up here.', !empty($d['canPrealert']) ? 'Pre-alert a parcel' : '', 'prealert_add.php') ?>
<?php endif; ?>
<?php if (!empty($d['addresses'])): $addrs = (array) $d['addresses']; ?>
<?= sl_mxSec(count($addrs) > 1 ? 'Your ' . count($addrs) . ' shipping addresses' : 'Your shipping address', 'How it works', sl_route('MyAccount')) ?>
<section class="b3-wl" aria-label="Shipping addresses">
<div class="b3-wl-lk"><span><small>Locker ID</small><b class="mono"><?= h(($d['locker'] ?? '') !== '' ? $d['locker'] : '—') ?></b></span><?php if (($d['locker'] ?? '') !== ''): ?><button type="button" class="b3-cp" data-sl-copy="<?= h($d['locker']) ?>" aria-label="Copy locker ID"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button><?php endif; ?><small class="b3-wl-hint"><?= count($addrs) > 1 ? 'Swipe the cards or tap a flag' : 'Use it at checkout' ?></small></div>
<?php $locker = (string) ($d['locker'] ?? ''); $awName = (string) ($d['name'] ?? ''); $awAssets = true; include CDP_ROOT . '/views/sl/pages/partials/address_wallet.php'; ?>
</section>
<?php endif; ?>
<?= sl_mxSec('Need a hand?', 'Help centre', 'support.php') ?>
<div class="hh-grid">
<a class="hh t-trk" href="support.php"><span class="hh-ic"><i class="ic i-route" aria-hidden="true"></i></span><b>Where is my parcel?</b><small>Ask us, we check it for you</small></a>
<a class="hh t-key" href="<?= h(sl_route('PickupCodes')) ?>"><span class="hh-ic"><i class="ic i-key" aria-hidden="true"></i></span><b>Pickup codes</b><small>Your codes for collection</small></a>
<a class="hh t-ban" href="terms.php"><span class="hh-ic"><i class="ic i-ban" aria-hidden="true"></i></span><b>What can I ship?</b><small>Items we can and cannot carry</small></a>
<a class="hh t-quo" href="<?= h(sl_route('QuoteCalculator')) ?>"><span class="hh-ic"><i class="ic i-dollar" aria-hidden="true"></i></span><b>Get a quote</b><small>Price by weight, air or sea</small></a>
</div>
</main>
