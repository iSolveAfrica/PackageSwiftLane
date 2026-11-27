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
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-ops.css')) ?>">
<header class="mx-top"><a class="mx-av" href="<?= h(sl_route('Workspace')) ?>" aria-label="My account"><?= h($me['ini']) ?><i aria-hidden="true"></i></a><div class="mx-ttl"><h1><span data-sl-greet><?= h((int) date('G') < 12 ? 'Good morning' : ((int) date('G') < 17 ? 'Good afternoon' : 'Good evening')) ?></span>, <?= h($me['first']) ?></h1></div><?= sl_phoneIcon('search', 'Search', sl_route('LockerSearch')) . sl_phoneIcon('bell', 'Notifications', sl_route('Notifications'), $ph['unread']) ?></header>
<main class="mx-body">
<?php if ($ph['fx'] !== ''): ?>
<div class="mx-fx"><i class="ic ic-sm i-globe" aria-hidden="true"></i><span>USD <b><?= h($ph['fx']) ?></b></span><small>GH₵ · system rate</small></div>
<?php endif; ?>
<section class="mx-hero"><div class="mx-hk">Your desk<span class="mx-tag"><?= h($d['shift']['label'] ?? 'Today') ?></span></div><b class="mx-hv"><?= $nNeeds ? h($nNeeds . ' thing' . ($nNeeds === 1 ? '' : 's') . ' need' . ($nNeeds === 1 ? 's' : '') . ' you') : 'Nothing needs you' ?></b><small><?= h($deskSub ? implode(' · ', $deskSub) : 'Queues are empty and no exceptions are open.') ?><?= $done ? h(' · ' . $done . ' done today') : '' ?></small><div class="mx-prog" role="img" aria-label="<?= h($deskPct . '% of today’s work done') ?>"><i style="width:<?= (int) $deskPct ?>%"></i></div><a class="mx-hb" href="<?= h(sl_route('Workspace')) ?>">Open my desk<i class="ic ic-sm i-right" aria-hidden="true"></i></a></section>
<?php if ($brief): ?>
<div class="mx-pc">
<?php foreach (array_slice($brief, 0, 3) as $b): $pl = $pill[$b['lvl']] ?? $pill['in']; ?>
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
<section class="mx-card mx-bars"><div class="mx-bh"><b>Parcels received this week</b><small><?= h($ph['week']['split']) ?></small></div><div class="mx-bc" role="img" aria-label="<?= h('Parcels received per day, last 7 days: ' . implode(', ', array_column($ph['week']['bars'], 't'))) ?>">
<?php foreach ($ph['week']['bars'] as $b): ?><span class="<?= h($b['c']) ?>" title="<?= h($b['t']) ?>"><i style="<?= h($b['h']) ?>"></i><small><?= h($b['l']) ?></small></span><?php endforeach; ?>
</div></section>
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
