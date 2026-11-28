<?php
/**
 * M-CParcels (my_parcels.php, view MyParcels): the customer's parcels with search (q), status tabs (filter)
 * and pages (page), as the web page. Tab counts: sl_portalCount() for the signed-in customer.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
global $user;
$slPhone['nav'] = 'customer';
$slPhone['tab'] = 'Parcels';
$uid = (int) $_SESSION['userid'];
$q = (string) ($d['q'] ?? '');
$filter = (string) ($d['filter'] ?? 'active');
$page = (int) ($d['page'] ?? 1);
$per = max(1, (int) ($d['per'] ?? 12));
$count = (int) ($d['count'] ?? 0);
$tabs = ['active' => 'On the way', 'ready' => 'Ready', 'delivered' => 'Delivered', 'all' => 'All'];
$counts = [];
foreach ($tabs as $k => $l) {
    $counts[$k] = $k === $filter ? $count : sl_portalCount($uid, $q, $k);
}
$unread = (int) sl_scalar('SELECT COUNT(*) FROM cdb_notifications_users WHERE user_id = :u AND notification_read = 0', [':u' => $uid]);
$me = sl_phoneMe();
$canShip = $user->cdp_hasPermission('courier_add_client');
$pages = (int) ceil($count / $per);
$stCls = function (array $r): string {
    if (stripos($r['st'], 'ready') !== false || in_array($r['st'], ['Delivered', 'Picked up'], true)) return 'ok';
    if ($r['cls'] === 'overdue' || stripos($r['st'], 'payment') !== false) return 'bad';
    return in_array($r['cls'], ['sorting', 'pending'], true) ? 'gr' : '';
};
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-auth.css')) ?>">
<?= sl_phoneTop('All my parcels', '', ['avatar' => $me['ini'], 'href' => sl_route('MyAccount'), 'online' => true], sl_phoneIcon('truck', 'Request delivery', 'delivery_request.php') . sl_phoneIcon('bell', 'Notifications', sl_route('MyNotifications'), $unread)) ?>
<main class="mx-body">
<h2 class="b1-hl">Everything you ship,<span><?= (int) $counts['all'] ?> on record.</span></h2>
<div class="mx-srch"><form method="get" action="my_parcels.php" role="search"><input type="hidden" name="filter" value="<?= h($filter) ?>"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($q) ?>" aria-label="Tracking number or store" placeholder="Tracking number or store" enterkeyhint="search"></label></form></div>
<nav class="mx-seg" aria-label="Parcel status"><?php foreach ($tabs as $k => $l): ?><a class="<?= $k === $filter ? 'on' : '' ?>" href="<?= h(sl_url(['filter' => $k, 'page' => null])) ?>"<?= $k === $filter ? ' aria-current="page"' : '' ?>><?= h($l) ?> <?= (int) $counts[$k] ?></a><?php endforeach; ?></nav>
<?php foreach ((array) ($d['rows'] ?? []) as $r): $pct = (int) preg_replace('/\D/', '', (string) $r['pg']); $cls = $stCls($r); $ready = stripos($r['st'], 'ready') !== false; ?>
<div class="b1-pc"><a class="b1-pch" href="<?= h($r['href']) ?>"><span class="b1-pi"><i class="ic <?= $r['mode'] === 'Air' ? 'i-plane' : 'i-ship' ?>" aria-hidden="true"></i></span><div><b><?= h($r['id']) ?><?= $r['from'] !== '—' ? ' · ' . h($r['from']) : '' ?></b><small class="mono"><?= h($r['w']) ?><?= $r['c'] !== '—' ? ' · ' . h($r['c']) : '' ?></small><small>Registered <?= h($r['date']) ?></small></div><span class="b1-st <?= h($cls) ?>"><?= h($r['st']) ?></span></a>
<div class="b1-tags"><span class="b1-tag <?= $r['mode'] === 'Air' ? 'air' : 'sea' ?>"><?= h($r['mode']) ?> freight</span></div>
<div class="b1-pbar" role="img" aria-label="<?= $pct ?>% of the way"><i class="<?= $cls === 'ok' ? 'ok' : ($cls === 'bad' ? 'bad' : '') ?>" style="width:<?= $pct ?>%"></i></div>
<div class="b1-pf"><span><?= $pct >= 100 ? 'Done' : $pct . '% of the way' ?></span><?php if ($ready): ?><a href="<?= h(sl_route('PickupCodes')) ?>">Pickup code<i class="ic ic-sm i-right" aria-hidden="true"></i></a><?php else: ?><a href="<?= h($r['href']) ?>">View journey<i class="ic ic-sm i-right" aria-hidden="true"></i></a><?php endif; ?></div></div>
<?php endforeach; ?>
<?php if ($pages > 1): ?>
<div class="b1-pg"><span>Showing <?= ($page - 1) * $per + 1 ?>–<?= min($count, $page * $per) ?> of <?= $count ?></span><div><?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?><a class="<?= $p === $page ? 'on' : '' ?>" href="<?= h(sl_url(['page' => $p])) ?>" aria-label="Page <?= $p ?>"<?= $p === $page ? ' aria-current="page"' : '' ?>><?= $p ?></a><?php endfor; ?></div></div>
<?php endif; ?>
<?php if (empty($d['rows'])): ?>
<section class="b1-empty"><span><i class="ic i-box" aria-hidden="true"></i></span><b><?= $q !== '' ? 'No matching parcels' : ($filter === 'all' ? 'No parcels yet' : 'Nothing here yet') ?></b><small><?= $q !== '' ? 'Try another tracking number or store name.' : 'Your parcels appear here as they move through our network. Got a tracking number from someone?' ?></small><div class="b1-btns"><a class="b1-lk" href="<?= h(sl_route('PublicTracking')) ?>">Track with a number</a><?php if ($canShip): ?><a class="b1-lk dk" href="courier_add_client.php">Ship a parcel</a><?php endif; ?></div></section>
<?php endif; ?>
</main>
<?php if ($canShip): ?><a class="mx-fab" href="courier_add_client.php" aria-label="New shipment"><i class="ic i-plus" aria-hidden="true"></i></a><?php endif; ?>
