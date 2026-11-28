<?php
/** M-Home: the staff home screen on a phone. $d = views/sl/data/overview.php, plus views/sl/data/phone_home.php. */
require_once CDP_ROOT . '/helpers/sl_mx.php';
require_once CDP_ROOT . '/views/sl/data/phone_home.php';
$me = sl_phoneMe();
$ph = sl_phoneHomeData($user);
$slPhone['tab'] = 'Home';
$can = fn($p) => $user->cdp_hasPermission($p);

// Your desk: the briefing cards that need someone (high or medium), plus the work queues.
$brief = array_values(array_filter($d['brief'] ?? [], fn($b) => ($b['tag'] ?? '') !== 'All clear'));
$needs = array_values(array_filter($brief, fn($b) => in_array($b['lvl'], ['hi', 'md'], true)));
$nNeeds = count($needs) + count(array_filter($d['queues'] ?? [], fn($q) => (int) $q['n'] > 0));
$deskSub = array_map(fn($b) => $b['big'] . ' ' . $b['bigS'], array_slice($needs, 0, 3));
if (!$deskSub) {
    foreach (array_slice(array_filter($d['queues'] ?? [], fn($q) => (int) $q['n'] > 0), 0, 3) as $q) $deskSub[] = sl_num($q['n']) . ' ' . strtolower($q['label']);
}
$done = (int) ($d['doneToday'] ?? 0);
$deskPct = $done + $nNeeds > 0 ? sl_pct($done, $done + $nNeeds) : 100;
$pill = ['hi' => ['overdue', 'Needs you'], 'md' => ['pending', 'Watch'], 'ok' => ['delivered', 'Going well'], 'in' => ['delivered', 'Going well']];

$pulse = [];
foreach ($d['pulse'] ?? [] as $x) $pulse[$x['l']] = $x;
$cards = [];
foreach ($d['cards'] ?? [] as $c) $cards[$c['key']] = $c;
$transit = (int) str_replace(',', '', (string) ($pulse['In transit']['v'] ?? 0));
$inBay = (int) ($d['warehouse']['used'] ?? 0);
$out = $cards['delivery']['sub'][0]['n'] ?? ($pulse['Out for delivery']['v'] ?? '0');
$riders = (int) ($d['pickups']['drivers'] ?? 0);
$regToday = 0;
foreach ($d['deskStats'] ?? [] as $s) if ($s['label'] === 'Registered today') $regToday = (int) $s['n'];
if (isset($pulse['Registered today'])) $regToday = (int) str_replace(',', '', (string) $pulse['Registered today']['v']);

$spark = function (array $bars): string {
    $h = '<div class="mx-sp" aria-hidden="true">';
    foreach ($bars as $b) $h .= '<i class="' . h($b['c']) . '" style="' . h($b['h']) . '"></i>';
    return $h . '</div>';
};
$actions = [];
if ($can('add_shipment')) $actions[] = ['plus', 'New shipment', sl_route('NewShipment')];
if ($can(['package_intake', 'add_package'])) $actions[] = ['scan', 'Intake scan', sl_route('PackageIntake')];
if ($can(['view_warehouse_delivery', 'warehouse_deliver_package'])) $actions[] = ['key', 'Hand out', sl_route('HandOut')];
if ($can('dispatch_riders')) $actions[] = ['route', 'Dispatch', sl_route('RiderDispatch')];
if ($can('add_consolidate_shipment')) $actions[] = ['layers', 'Consolidate', sl_route('NewConsolidation')];
if ($can('prealert_list')) $actions[] = ['inbox', 'Pre-alerts', sl_route('PreAlerts')];
if (sl_navAllowed('ops:staff', [])) $actions[] = ['message', 'WhatsApp', sl_route('CommsInbox')];
if (sl_navAllowed('ai:use', [])) $actions[] = ['ai', 'Ask ' . (function_exists('cdp_kingName') ? cdp_kingName() : 'King'), sl_route('StaffAI')];

// More of the desktop dashboard on the phone: the "today" cards, work queues, the 14-day forecast,
// the US packages pipeline and live shipments (all already in $d, scoped to this user's permissions).
$ovBars = function (array $bars): string {
    if (!$bars) return '';
    $o = '<div class="ph-sb" aria-hidden="true">';
    $n = count($bars);
    foreach ($bars as $i => $b) $o .= '<i class="' . ($i === $n - 1 ? 'now' : '') . '" style="' . h($b['s'] ?? '') . '"></i>';
    return $o . '</div>';
};
$todayCards = array_values(array_filter($d['cards'] ?? [], fn($c) => ($c['big'] ?? '') !== ''));
$queues = array_values($d['queues'] ?? []);
usort($queues, fn($a, $b) => ((int) $b['n'] > 0) <=> ((int) $a['n'] > 0));
$fc = $d['fc'] ?? [];
$fcSum = 0;
foreach ($fc as $f) if (preg_match('/: (\d+) expected/', (string) $f['tip'], $m)) $fcSum += (int) $m[1];
$pipe = $can('view_package_list') ? ($d['pipe'] ?? []) : [];
$tracks = array_slice($d['tracking']['items'] ?? [], 0, 5);
$wh = $d['warehouse'] ?? [];
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-ops.css')) ?>">
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-home.css')) ?>">
<header class="mx-top"><a class="mx-av" href="<?= h(sl_route('Workspace')) ?>" aria-label="My account"><?= h($me['ini']) ?><i aria-hidden="true"></i></a><div class="mx-ttl"><h1><span data-sl-greet><?= h((int) date('G') < 12 ? 'Good morning' : ((int) date('G') < 17 ? 'Good afternoon' : 'Good evening')) ?></span>, <?= h($me['first']) ?></h1></div><?= sl_phoneIcon('search', 'Search', sl_route('LockerSearch')) . sl_phoneIcon('bell', 'Notifications', sl_route('Notifications'), $ph['unread']) ?></header>
<main class="mx-body">
<?php if ($ph['fx'] !== ''): ?>
<div class="mx-fx"><i class="ic ic-sm i-globe" aria-hidden="true"></i><span>USD <b><?= h($ph['fx']) ?></b></span><small>GH₵ · system rate</small></div>
<?php endif; ?>
<section class="mx-hero"><div class="mx-hk">Your desk<span class="mx-tag"><?= h($d['shift']['label'] ?? 'Today') ?></span></div><b class="mx-hv"><?= $nNeeds ? h($nNeeds . ' thing' . ($nNeeds === 1 ? '' : 's') . ' need' . ($nNeeds === 1 ? 's' : '') . ' you') : 'Nothing needs you' ?></b><small><?= h($deskSub ? implode(' · ', $deskSub) : 'Queues are empty and no exceptions are open.') ?><?= $done ? h(' · ' . $done . ' done today') : '' ?></small><div class="mx-prog" role="img" aria-label="<?= h($deskPct . '% of today’s work done') ?>"><i style="width:<?= (int) $deskPct ?>%"></i></div><a class="mx-hb" href="<?= h(sl_route('Workspace')) ?>">Open my desk<i class="ic ic-sm i-right" aria-hidden="true"></i></a></section>
<?php if ($brief): ?>
<div class="mx-pc">
<?php foreach (array_slice($brief, 0, 6) as $b): $pl = $pill[$b['lvl']] ?? $pill['in']; ?>
<div><span class="sl-pill <?= h($pl[0]) ?>"><?= h($pl[1]) ?></span><b><?= h($b['big'] . ' ' . $b['bigS']) ?></b><small><?= h($b['d']) ?></small><a class="mx-lnk" href="<?= h($b['href']) ?>"><?= h($b['cta']) ?> →</a></div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<section class="mx-k c2">
<a class="mx-kt" href="<?= h(sl_route('AirShipments', ['status' => 3])) ?>"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-plane" aria-hidden="true"></i></span><small>In transit</small></div><b><?= h(sl_num($transit)) ?></b><em class="<?= $regToday ? 'up' : '' ?>">+<?= h(sl_num($regToday)) ?> today</em><?= $spark($ph['sparkAir']) ?></a>
<a class="mx-kt" href="<?= h(sl_route('Warehouse')) ?>"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-warehouse" aria-hidden="true"></i></span><small>In warehouse</small></div><b><?= h(sl_num($inBay)) ?></b><em class=""><?= h(sl_num($d['warehouse']['awaiting'] ?? 0)) ?> ready</em><?= $spark($ph['sparkSea']) ?></a>
<a class="mx-kt" href="<?= h(sl_route('DeliveryTracking')) ?>"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-truck" aria-hidden="true"></i></span><small>Out for delivery</small></div><b><?= h($out) ?></b><em class=""><?= h($riders . ' rider' . ($riders === 1 ? '' : 's')) ?></em><?= $spark($ph['sparkOut']) ?></a>
<?php if ($ph['paid'] !== null): ?>
<a class="mx-kt" href="<?= h(sl_route('FinancialOverview')) ?>"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-dollar" aria-hidden="true"></i></span><small>Paid today</small></div><b><?= h(sl_money($ph['paid']['today'])) ?></b><em class="<?= $ph['paid']['up'] ? 'up' : '' ?>"><?= h($ph['paid']['note']) ?></em><?= $spark($ph['paid']['spark']) ?></a>
<?php else: ?>
<a class="mx-kt" href="<?= h(sl_route('AirShipments', ['status' => 8])) ?>"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-check" aria-hidden="true"></i></span><small>Delivered today</small></div><b><?= h($pulse['Delivered today']['v'] ?? '0') ?></b><em class="">since midnight</em><?= $spark($ph['sparkOut']) ?></a>
<?php endif; ?>
</section>
<?php if ($actions): ?>
<div class="mx-sec"><h2>Quick actions</h2></div>
<section class="mx-act c4">
<?php foreach ($actions as $a): ?><a href="<?= h($a[2]) ?>"><span><i class="ic i-<?= h($a[0]) ?>" aria-hidden="true"></i></span><small><?= h($a[1]) ?></small></a><?php endforeach; ?>
</section>
<?php endif; ?>
<?php if ($todayCards): ?>
<div class="mx-sec"><h2>Today</h2><small class="ph-clock" data-sl-clock><?= h($d['clock'] ?? date('H:i')) ?></small></div>
<?php foreach ($todayCards as $c): ?>
<a class="mx-card ph-tc t-<?= h($c['tone'] ?? '') ?>" href="<?= h($c['href']) ?>">
<div class="ph-tc-h"><span class="ph-tc-ic"><i class="ic ic-sm <?= h($c['icon']) ?>" aria-hidden="true"></i></span><small><?= h($c['eyebrow']) ?></small><?php if (!empty($c['alert'])): ?><span class="sl-pill overdue"><?= h($c['alert']) ?></span><?php else: ?><i class="ic ic-sm i-right ph-go" aria-hidden="true"></i><?php endif; ?></div>
<b class="ph-tc-big"><?= h($c['big']) ?></b>
<?php if (($c['lede'] ?? '') !== ''): ?><small class="ph-tc-lede"><?= h($c['lede']) ?></small><?php endif; ?>
<?php if (!empty($c['sub'])): ?><div class="ph-tc-sub" style="--n:<?= min(4, count($c['sub'])) ?>"><?php foreach (array_slice($c['sub'], 0, 4) as $x): ?><span><b><?= h($x['n']) ?></b><small><?= h($x['l']) ?></small></span><?php endforeach; ?></div><?php endif; ?>
<?= $ovBars($c['bars'] ?? []) ?>
<?php if (($c['barsL'] ?? '') !== ''): ?><div class="ph-tc-f"><span><?= h($c['barsL']) ?></span><span><?= h($c['barsR'] ?? '') ?></span></div><?php endif; ?>
</a>
<?php endforeach; ?>
<?php endif; ?>

<?php if ($queues): ?>
<div class="mx-sec"><h2>Work queues</h2><small class="ph-clock"><?= h(sl_num($d['queueTotal'] ?? 0)) ?> waiting</small></div>
<section class="mx-card mx-list ph-q">
<?php foreach (array_slice($queues, 0, 8) as $q): $qn = (int) $q['n']; ?>
<a class="mx-row<?= $qn ? '' : ' zero' ?>" href="<?= h($q['href']) ?>"><span class="mx-li" style="color:<?= h($q['tone'] ?? 'var(--ink-2)') ?>"><i class="ic ic-sm <?= h($q['icon']) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($q['label']) ?></b><small><?= h($q['sub']) ?></small></span><span class="ph-cnt"><?= h(sl_num($qn)) ?></span></a>
<?php endforeach; ?>
</section>
<?php endif; ?>

<?php if ($fc): ?>
<section class="mx-card mx-bars ph-fc"><div class="mx-bh"><b>Arrivals, next 14 days</b><span class="sl-pill <?= h($d['fcPill'] ?? '') ?>"><?= h($d['fcNote'] ?? '') ?></span></div>
<div class="ph-fc-c" role="img" aria-label="<?= h('Parcels expected at the hub, next 14 days: ' . sl_num($fcSum) . ' in total') ?>">
<?php foreach ($fc as $f): ?><span class="<?= h($f['c']) ?>" title="<?= h($f['tip']) ?>"><i style="<?= h($f['h']) ?>"></i><small><?= h($f['l'] !== '' ? explode(' ', $f['l'])[1] ?? $f['l'] : '') ?></small></span><?php endforeach; ?>
<em class="ph-cap" style="<?= h($d['capY'] ?? '') ?>"></em>
</div>
<div class="ph-fc-f"><span><b><?= h(sl_num($fcSum)) ?></b> expected</span><span><?= h($d['capacityLabel'] ?? '') ?></span></div>
<?php if (!empty($wh)): ?><div class="ph-wh"><div><small>In the bays</small><b><?= h(sl_num($wh['used'] ?? 0)) ?><?= !empty($wh['capacity']) ? '<em>/' . h(sl_num($wh['capacity'])) . '</em>' : '' ?></b></div><div><small>Arriving</small><b><?= h(sl_num($wh['arriving'] ?? 0)) ?></b></div><div><small>Ready to collect</small><b><?= h(sl_num($wh['awaiting'] ?? 0)) ?></b></div></div>
<?php if (!empty($wh['capacity'])): ?><div class="ph-meter" role="img" aria-label="<?= (int) $wh['pct'] ?>% of the bays used"><i style="width:<?= min(100, (int) $wh['pct']) ?>%" class="<?= (int) $wh['pct'] >= 90 ? 'bad' : ((int) $wh['pct'] >= 75 ? 'warn' : '') ?>"></i></div><?php endif; ?><?php endif; ?>
</section>
<?php endif; ?>

<section class="mx-card mx-bars"><div class="mx-bh"><b>Parcels received this week</b><small><?= h($ph['week']['split']) ?></small></div><div class="mx-bc" role="img" aria-label="<?= h('Parcels received per day, last 7 days: ' . implode(', ', array_column($ph['week']['bars'], 't'))) ?>">
<?php foreach ($ph['week']['bars'] as $b): ?><span class="<?= h($b['c']) ?>" title="<?= h($b['t']) ?>"><i style="<?= h($b['h']) ?>"></i><small><?= h($b['l']) ?></small></span><?php endforeach; ?>
</div></section>
<?php if ($pipe): ?>
<div class="mx-sec"><h2>US packages</h2><a href="<?= h(sl_route('SeaPackages')) ?>">Open</a></div>
<div class="ph-pipe">
<?php foreach ($pipe as $k => $st): ?><div><span class="ph-pipe-ic"><i class="ic ic-sm <?= h($st['icon']) ?>" aria-hidden="true"></i></span><b><?= h(sl_num($st['n'])) ?></b><span><?= h($st['label']) ?></span><small><?= h($st['sub']) ?></small></div><?php endforeach; ?>
</div>
<?php if (!empty($d['departures']) || !empty($d['carriers'])): ?>
<section class="mx-card ph-dep">
<?php foreach ($d['departures'] ?? [] as $dep): ?><div class="ph-dep-r"><span class="mx-li"><i class="ic ic-sm <?= h($dep['icon']) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($dep['title']) ?></b><small><?= h($dep['sub']) ?></small></span><span class="ph-cnt"><?= (int) $dep['n'] ?> pkgs</span></div><?php endforeach; ?>
<?php if (!empty($d['carriers'])): ?><div class="ph-car"><small>Collected by carrier · 30 days</small><?php foreach (array_slice($d['carriers'], 0, 4) as $c): ?><div><span><?= h($c['name']) ?></span><span class="ph-car-b"><i style="width:<?= (int) $c['w'] ?>%"></i></span><b><?= (int) $c['pct'] ?>%</b></div><?php endforeach; ?></div><?php endif; ?>
</section>
<?php endif; ?>
<?php endif; ?>

<?php if ($tracks): ?>
<div class="mx-sec"><h2>Shipments on the move</h2><a href="<?= h(sl_route('LiveTracking')) ?>">Track all</a></div>
<section class="mx-card mx-list ph-trk">
<?php foreach ($tracks as $t): ?>
<a class="mx-row" href="<?= h($t['href']) ?>"><span class="mx-li <?= $t['mode'] === 'sea' ? 'sea' : 'air' ?>"><i class="ic ic-sm <?= $t['mode'] === 'sea' ? 'i-ship' : 'i-plane' ?>" aria-hidden="true"></i></span><span class="mx-rm"><b class="mono"><?= h($t['code']) ?></b><small><?= h(trim($t['customer'] . ' · ' . ($t['from']['city'] ?? '') . ' → ' . ($t['to']['city'] ?? ''), ' ·→')) ?></small><span class="ph-trk-p" aria-hidden="true"><i style="width:<?= (int) $t['pct'] ?>%"></i></span></span><span class="ph-trk-r"><span class="sl-pill <?= h($t['cls']) ?>"><?= h($t['st']) ?></span><small class="<?= h($t['estCls']) ?>"><?= h($t['estimate'] === 'On time' ? 'ETA ' . $t['etaShort'] : $t['estimate']) ?></small></span></a>
<?php endforeach; ?>
</section>
<?php endif; ?>

<div class="mx-sec"><h2>Live activity</h2><?php if ($can('view_activity_logs')): ?><a href="<?= h(sl_route('ActivityLogs')) ?>">All</a><?php endif; ?></div>
<?php if ($ph['activity']): ?>
<section class="mx-card mx-list">
<?php foreach ($ph['activity'] as $a): ?>
<div class="mx-row<?= $a['bad'] ? ' bad' : '' ?>"><span class="mx-ava"><?= h($a['ini']) ?></span><span class="mx-rm"><b><?= h($a['what']) ?></b><small><?= h($a['who'] . ($a['sub'] !== '' ? ' · ' . $a['sub'] : '')) ?></small></span><span class="mx-rr"><small><?= h(str_replace(' ago', '', $a['at'])) ?></small></span></div>
<?php endforeach; ?>
</section>
<?php else: ?>
<section class="mx-card"><?= sl_mxEmpty('activity', 'No activity yet today', 'Work done in the system shows up here as it happens.') ?></section>
<?php endif; ?>
</main>
