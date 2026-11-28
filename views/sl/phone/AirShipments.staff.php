<?php
/** M-Air: Air shipments on a staff phone. $d = views/sl/data/shipments.php sl_airShipmentsData() (GET q, filter, status, page). */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$me = sl_phoneMe();
$slPhone['tab'] = 'Shipments';
$unread = (int) sl_scalar('SELECT COUNT(*) FROM cdb_notifications_users WHERE user_id = :u AND notification_read = 0', [':u' => (int) $_SESSION['userid']]);
$m = array_column($d['metrics'], 'v', 'label');
$cnt = ['all' => $d['all'], 'open' => $m['Open / in progress'] ?? null, 'delivered' => $m['Delivered'] ?? null, 'unpaid' => $d['unpaid'], 'consol' => $m['In consolidations'] ?? null, 'pickup' => null];
$pages = max(1, (int) ceil($d['count'] / max(1, $d['per'])));
$keep = array_filter(['q' => $d['q'], 'status' => $d['status'] ?: null, 'period' => $d['period'] !== 'all' ? $d['period'] : null, 'driver' => $d['driver'] ?: null]);
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-ops.css')) ?>">
<?= sl_phoneTop('Air shipments', sl_num($d['count']) . ' shown · ' . sl_num($d['month']) . ' this month', ['avatar' => $me['ini'], 'href' => sl_route('Workspace'), 'online' => true], sl_phoneIcon('search', 'Search', sl_route('LockerSearch')) . sl_phoneIcon('bell', 'Notifications', sl_route('Notifications'), $unread)) ?>
<main class="mx-body">
<form method="get" action="<?= h(sl_route('AirShipments')) ?>" role="search" class="mx-formcontents">
<?php if ($d['filter'] !== 'all'): ?><input type="hidden" name="filter" value="<?= h($d['filter']) ?>"><?php endif; ?>
<div class="mx-srch"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q']) ?>" aria-label="Tracking, carrier number, name or locker" placeholder="Tracking, carrier number, name or locker" autocomplete="off" enterkeyhint="search"></label><?php if ($user->cdp_hasPermission(['package_intake', 'add_package'])): ?><a class="mx-ib" href="<?= h(sl_route('PackageIntake')) ?>" aria-label="Scan"><i class="ic i-scan" aria-hidden="true"></i></a><?php endif; ?></div>
</form>
<nav class="mx-seg" aria-label="Show">
<?php foreach ($d['filters'] as $f): $on = $d['filter'] === $f['f']; ?><a class="mx-segl<?= $on ? ' on' : '' ?>" href="<?= h(sl_route('AirShipments', $keep + ($f['f'] === 'all' ? [] : ['filter' => $f['f']]))) ?>"<?= $on ? ' aria-current="page"' : '' ?>><?= h($f['label']) ?><?= isset($cnt[$f['f']]) && $cnt[$f['f']] !== null ? ' ' . sl_num($cnt[$f['f']]) : '' ?></a><?php endforeach; ?>
</nav>
<section class="mx-k c2">
<a class="mx-kt" href="<?= h(sl_route('AirShipments', ['filter' => 'customs'])) ?>"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-shield" aria-hidden="true"></i></span><small>Held / customs</small></div><b><?= h(sl_num($d['customs'])) ?></b><em class="<?= $d['noCarrier'] ? 'warn' : '' ?>"><?= h(sl_num($d['noCarrier'])) ?> without carrier no.</em></a>
<a class="mx-kt" href="<?= h(sl_route('AirShipments', ['filter' => 'unpaid'])) ?>"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-receipt" aria-hidden="true"></i></span><small>Unpaid</small></div><b><?= h(sl_num($d['unpaid'])) ?></b><em class="<?= $d['unpaid'] ? 'warn' : '' ?>">awaiting payment</em></a>
</section>
<?php if ($d['rows']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['rows'] as $r): ?>
<a class="mx-row" href="<?= h(sl_route('ShipmentDetail', ['id' => $r['id']])) ?>"><span class="mx-rm"><b><?= h($r['track'] . ' · ' . $r['name']) ?></b><small><?= h(implode(' · ', array_filter([$r['dest'] !== '—' ? $r['dest'] : '', $r['kg'] !== '—' ? $r['kg'] : '', $r['date']]))) ?></small><small class="mono"><?= h($r['carrier'] !== '' ? $r['carrier'] : ($r['from'] !== '' ? 'Locker ' . $r['from'] : ($r['pay'] . ' · ' . $r['total']))) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($r['stCls']) ?>"><?= h(ucfirst(strtolower($r['st']))) ?></span></span></a>
<?php endforeach; ?>
</section>
<?php if ($pages > 1): ?>
<div class="mx-btns" style="flex-direction:row">
<?php if ($d['page'] > 1): ?><a class="mx-btn sec" style="flex:1" href="<?= h(sl_url(['page' => $d['page'] - 1])) ?>">Previous</a><?php endif; ?>
<?php if ($d['page'] < $pages): ?><a class="mx-btn sec" style="flex:1" href="<?= h(sl_url(['page' => $d['page'] + 1])) ?>">Next</a><?php endif; ?>
</div>
<p class="mx-note">Page <?= (int) $d['page'] ?> of <?= (int) $pages ?></p>
<?php endif; ?>
<?php else: ?>
<?= sl_mxEmpty('plane', 'No shipments here', $d['q'] !== '' ? 'Nothing matches “' . $d['q'] . '”.' : 'Try another filter.', 'Show all', sl_route('AirShipments')) ?>
<?php endif; ?>
</main>
<?php if ($d['canAdd']): ?><a class="mx-fab" href="<?= h(sl_route('NewShipment')) ?>" aria-label="New shipment"><i class="ic i-plus" aria-hidden="true"></i></a><?php endif; ?>
