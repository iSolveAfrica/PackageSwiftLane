<?php
/**
 * M-WhPickups: customers whose parcels wait at the counter, and today's hand-outs.
 * $d is the web page's (views/sl/data/warehouse.php sl_warehousePickupsData: handed-out history, ?days=&kind=&q=).
 * The waiting queue comes from the same module's sl_whReady() / sl_whRowsOut() (agency scope applied there).
 * ?tab=waiting|today|overdue picks the list; Today is the web page's own history list.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'staff';
$tab = in_array($_GET['tab'] ?? '', ['waiting', 'today', 'overdue'], true) ? (string) $_GET['tab'] : 'waiting';
$ready = sl_whRowsOut(sl_whReady('', [], 500));
$by = [];
foreach ($ready as $r) {
    $k = $r['sender'];
    if (!isset($by[$k])) $by[$k] = ['id' => $k, 'name' => $r['cust'], 'ini' => $r['ini'], 'locker' => $r['locker'], 'n' => 0, 'bays' => [], 'dn' => 0, 'verified' => false, 'code' => false, 'uncleared' => 0];
    $b = &$by[$k];
    $b['n']++;
    if ($r['bay'] !== '—') $b['bays'][$r['bay']] = 1;
    $b['dn'] = max($b['dn'], $r['dn']);
    $b['verified'] = $b['verified'] || $r['verified'];
    $b['code'] = $b['code'] || $r['code'] !== 'No code yet';
    if (!$r['cleared']) $b['uncleared']++;
    unset($b);
}
$cust = array_values($by);
usort($cust, fn($a, $b) => $b['dn'] <=> $a['dn']);
$overdue = array_values(array_filter($cust, fn($c) => $c['dn'] >= 7));
$list = $tab === 'overdue' ? $overdue : $cust;
$state = function (array $c): array {
    if ($c['uncleared'] === $c['n']) return ['Unpaid', 'overdue'];
    if ($c['dn'] >= 7) return ['Overdue', 'overdue'];
    if ($c['verified']) return ['Verified', 'delivered'];
    if ($c['code']) return ['Code sent', 'delivered'];
    return ['Ready', 'pending'];
};
$tabs = [['waiting', 'Waiting ' . count($cust)], ['today', 'Today ' . (int) $d['todayTot']], ['overdue', 'Overdue ' . count($overdue)]];
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-wh.css')) ?>">
<?= sl_phoneTop('Warehouse pickups', '', ['back' => sl_route('Warehouse')], $d['canHand'] ? sl_phoneIcon('key', 'Hand out a parcel', sl_route('WarehouseDelivery')) : '') ?>
<main class="mx-body">
<nav class="mx-seg" aria-label="Show"><?php foreach ($tabs as [$k, $l]): ?><a class="<?= $tab === $k ? 'on' : '' ?>" href="<?= h(sl_url(['tab' => $k === 'waiting' ? null : $k, 'page' => null, 'days' => null, 'kind' => null])) ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>><?= h($l) ?></a><?php endforeach; ?></nav>
<?php if ($tab === 'today'): ?>
<?php if ($d['rows']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['rows'] as $r): ?>
<a class="mx-row" href="<?= h($r['href']) ?>"><span class="mx-ava"><?= h($r['ini']) ?></span><span class="mx-rm"><b><?= h($r['cust']) ?></b><small><?= h($r['id']) ?> · <?= h($r['time']) ?> · <?= h($r['by']) ?></small></span><span class="mx-rr"><span class="sl-pill <?= $r['kc'] === 'ok' ? 'delivered' : 'onway' ?>"><?= h($r['kind']) ?></span><small><?= h($r['code']) ?></small></span></a>
<?php endforeach; ?>
</section>
<?php else: ?><?= sl_mxEmpty('box', 'Nothing handed out today') ?><?php endif; ?>
<?php elseif ($list): ?>
<section class="mx-card mx-list">
<?php foreach (array_slice($list, 0, 60) as $c): [$sl, $sc] = $state($c); $bays = array_keys($c['bays']); ?>
<a class="mx-row" href="<?= h($d['canHand'] ? sl_route('WarehouseDelivery', ['customer' => $c['id']]) : sl_route('Warehouse', ['q' => $c['locker'] ?: $c['name']])) ?>"><span class="mx-ava"><?= h($c['ini']) ?></span><span class="mx-rm"><b><?= h($c['name']) ?></b><small><?= (int) $c['n'] ?> parcel<?= $c['n'] === 1 ? '' : 's' ?> · <?= $c['dn'] >= 7 ? (int) $c['dn'] . ' days' : ($bays ? 'bay ' . h(implode(', ', array_slice($bays, 0, 2))) : h($c['locker'] ?: 'no bay')) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($sc) ?>"><?= h($sl) ?></span></span></a>
<?php endforeach; ?>
</section>
<?php else: ?>
<?= sl_mxEmpty('check', $tab === 'overdue' ? 'Nobody is overdue' : 'Nobody is waiting', 'Customers with parcels ready for pickup show here.') ?>
<?php endif; ?>
<?php if ($d['canAging'] && $overdue): ?><div class="mx-btns"><a class="mx-btn" href="<?= h(sl_route('PickupAging')) ?>">Remind overdue customers</a></div><?php endif; ?>
</main>
