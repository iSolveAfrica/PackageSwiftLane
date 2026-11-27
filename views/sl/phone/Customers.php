<?php
/**
 * M-Customers: the customer list on a phone. Same $d as the web page (views/sl/data/customers.php
 * sl_customersData): tiles, status counts, search (q), filter, page. Rows open the customer profile.
 */
require_once CDP_ROOT . '/views/sl/data/phone_people.php';
$slPhone['nav'] = 'staff';
$st = $d['status'] ?? ['active' => 0, 'quiet' => 0, 'new' => 0, 'blocked' => 0];
$spark = function (array $vals): string {
    $vals = array_slice(array_values($vals), -12);
    $mx = max(1, max($vals ?: [0]));
    $out = '';
    foreach ($vals as $i => $v) $out .= '<i class="' . ($i === count($vals) - 1 ? 'now' : '') . '" style="height:' . max(8, (int) round($v / $mx * 100)) . '%"></i>';
    return $out;
};
$newKpi = null;
foreach ($d['kpi'] ?? [] as $k) if ($k['key'] === 'new') $newKpi = $k;
$segs = [['all', 'All', $d['total'] ?? 0], ['active', 'Active', $st['active']], ['quiet', 'Quiet', $st['quiet']], ['new', 'New', $st['new']], ['blocked', 'Blocked', $st['blocked']]];
$canScan = $user->cdp_hasPermission(['package_intake', 'add_package']);
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-people.css')) ?>">
<?= sl_phoneTop('Customers', sl_num($d['total'] ?? 0) . ' accounts', ['back' => 'menu.php'], sl_phoneIcon('download', 'Export customers (CSV)', sl_url(['export' => 'csv']))) ?>
<main class="mx-body">
<section class="mx-k c2">
  <div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-users" aria-hidden="true"></i></span><small>New this month</small></div><b><?= sl_num($d['newM'] ?? 0) ?></b><?php if ($newKpi): ?><em class="<?= strpos($newKpi['sub'], '▲') === 0 ? 'up' : (strpos($newKpi['sub'], '▼') === 0 ? 'down' : '') ?>"><?= h(trim(str_replace(['▲', '▼'], '', $newKpi['sub']))) ?></em><?php endif; ?><div class="mx-sp" aria-hidden="true"><?= $spark(array_column($d['months'] ?? [], 'v')) ?></div></div>
  <div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-clock" aria-hidden="true"></i></span><small>Ships every</small></div><b><?= h($d['shipEvery'] ?? '—') ?></b><em>active per month</em><div class="mx-sp" aria-hidden="true"><?= $spark($d['activeSeries'] ?? []) ?></div></div>
</section>
<form class="mx-srch" method="get" action="customers_list.php" role="search">
  <?php if (($d['filter'] ?? 'all') !== 'all'): ?><input type="hidden" name="filter" value="<?= h($d['filter']) ?>"><?php endif; ?>
  <label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q'] ?? '') ?>" aria-label="Name, phone, locker ID" placeholder="Name, phone, locker ID" autocomplete="off" enterkeyhint="search"></label>
  <?php if ($canScan): ?><a class="mx-ib" href="<?= h(sl_route('PackageIntake')) ?>" aria-label="Scan"><i class="ic i-scan" aria-hidden="true"></i></a><?php endif; ?>
</form>
<nav class="mx-seg" aria-label="Filter customers">
<?php foreach ($segs as [$f, $label, $n]): ?>
  <a class="<?= $f === $d['filter'] ? 'on' : '' ?>" href="<?= h(sl_url(['filter' => $f === 'all' ? null : $f, 'page' => null, 'ym' => null, 'city' => null])) ?>"<?= $f === $d['filter'] ? ' aria-current="true"' : '' ?>><?= h($label) ?> <?= sl_num($n) ?></a>
<?php endforeach; ?>
<?php if (!in_array($d['filter'], array_column($segs, 0), true)): ?><a class="on" aria-current="true" href="<?= h(sl_url(['filter' => null, 'page' => null, 'ym' => null])) ?>"><?= h($d['filterLabel']) ?> <?= sl_num($d['count']) ?> ×</a><?php endif; ?>
</nav>
<?php if ($d['rows']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['rows'] as $r): $flag = in_array($r['cls'], ['overdue', 'onway', 'pending'], true); ?>
  <a class="mx-row" href="<?= h(sl_route('CustomerProfile', ['id' => $r['id']])) ?>"><span class="mx-ava"><?= h($r['ini']) ?></span><span class="mx-rm"><b><?= h($r['name']) ?></b><small><?= h($r['locker']) ?> · <?= (int) $r['n'] ?> parcel<?= $r['n'] === 1 ? '' : 's' ?><?= $r['city'] !== '—' ? ' · ' . h($r['city']) : '' ?></small></span><span class="mx-rr"><?php if ($flag): ?><span class="sl-pill <?= h($r['cls']) ?>"><?= h($r['cls'] === 'onway' ? 'Owes ' . $r['bal'] : $r['st']) ?></span><?php else: ?><small><?= h($r['st']) ?></small><?php endif; ?></span></a>
<?php endforeach; ?>
</section>
<?= sl_ppPager((int) $d['page'], (int) $d['per'], (int) $d['count']) ?>
<?php else: ?>
<?= sl_mxEmpty('users', 'No customers match', ($d['q'] ?? '') !== '' ? 'Nothing found for “' . $d['q'] . '”.' : 'No customer is in this group.', 'Show everyone', 'customers_list.php') ?>
<?php endif; ?>
</main>
<?php if ($user->cdp_hasPermission('add_client')): ?><a class="mx-fab" href="<?= h(sl_route('AddCustomer')) ?>" aria-label="Add customer"><i class="ic i-plus" aria-hidden="true"></i></a><?php endif; ?>
