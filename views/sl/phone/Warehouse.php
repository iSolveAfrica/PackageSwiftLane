<?php
/**
 * M-Warehouse: shelves, what is waiting and the four counter actions. Same $d as the web page
 * (views/sl/data/warehouse.php sl_warehouseData). Receive posts to ajax/sl/warehouse_ajax.php (op=receive, tracking, bay),
 * like the web scan box. The design's per-office tabs are left out: bays are not recorded per office.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'staff';
$old = count(array_filter($d['rows'], fn($r) => $r['dn'] >= 7));
$toSort = count(array_filter($d['rows'], fn($r) => $r['bay'] === '—'));
// bays grouped in rows of ten, as on the web bay map
$groups = [];
foreach (array_chunk($d['bays'], 10) as $chunk) {
    $used = count(array_filter($chunk, fn($b) => $b['c'] !== 'h0'));
    $groups[] = ['l' => $chunk[0]['n'] . ' – ' . end($chunk)['n'], 'p' => sl_pct($used, count($chunk)), 'u' => $used, 'n' => count($chunk)];
}
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-wh.css')) ?>">
<?= sl_phoneTop('Warehouse', $d['lede'], ['back' => 'menu.php'], sl_phoneIcon('search', 'Locker search', sl_route('LockerSearch'))) ?>
<main class="mx-body">
<section class="mx-k c2">
<?= sl_mxKpi('warehouse', 'Ready', sl_num($d['count'])) ?>
<?= sl_mxKpi('clock', 'Over 7 days', sl_num($old), $old ? 'storage fee' : '', $old ? 'warn' : '') ?>
<?= sl_mxKpi('box', 'No bay yet', sl_num($toSort), $d['count'] > count($d['rows']) ? 'on this page' : '') ?>
<?= sl_mxKpi('layers', 'Space used', $d['hasBay'] ? (int) $d['fullPct'] . '%' : '—', $d['hasBay'] ? sl_num($d['free']) . ' bays free' : '') ?>
</section>
<?php if ($d['canReceive']): ?>
<form class="mx-card mx-form" data-sl-form action="ajax/sl/warehouse_ajax.php" method="post" id="wh-scan"><input type="hidden" name="op" value="receive">
<label class="mx-f"><small>Receive a parcel</small><input class="mx-in mono" name="tracking" placeholder="Scan or type a tracking number" autocomplete="off" autocapitalize="characters" required></label>
<label class="mx-f"><small>Bay</small><span class="wh-selw"><select class="mx-in" name="bay"><option value="">No bay</option><?php foreach ($d['bayList'] as $b): ?><option><?= h($b) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
<button class="mx-btn" type="submit">Receive parcel</button>
<div class="mx-ban ok" id="wh-res" role="status" hidden><i class="ic ic-sm i-check" aria-hidden="true"></i><span></span></div>
</form>
<?php endif; ?>
<?= sl_mxSec('Space by shelf') ?>
<?php if ($d['hasBay']): ?>
<section class="mx-card mx-mts">
<?php foreach ($groups as $g): ?><div class="mx-mt"><div><span>Bays <?= h($g['l']) ?></span><b><?= (int) $g['p'] ?>%</b></div><span class="mx-trk" role="img" aria-label="<?= (int) $g['u'] ?> of <?= (int) $g['n'] ?> bays used"><i class="<?= $g['p'] >= 90 ? 'warn' : ($g['p'] <= 40 ? 'ok' : '') ?>" style="width:<?= (int) $g['p'] ?>%"></i></span></div><?php endforeach; ?>
</section>
<?php else: ?>
<?= sl_mxEmpty('layers', 'Bays are not recorded yet', 'Shelf space shows here once parcels are put in bays.') ?>
<?php endif; ?>
<section class="mx-act c4">
<?php if ($d['canReceive']): ?><a href="<?= h(sl_route('PackageIntake')) ?>"><span><i class="ic i-scan" aria-hidden="true"></i></span><small>Receive</small></a><?php endif; ?>
<?php if ($d['canHand']): ?><a href="<?= h(sl_route('WarehouseDelivery')) ?>"><span><i class="ic i-key" aria-hidden="true"></i></span><small>Hand out</small></a><?php endif; ?>
<a href="<?= h(sl_route('WarehousePickups')) ?>"><span><i class="ic i-truck" aria-hidden="true"></i></span><small>Pickups</small></a>
<a href="<?= h(sl_route('LabelStudio')) ?>"><span><i class="ic i-print" aria-hidden="true"></i></span><small>Labels</small></a>
</section>
<?= sl_mxSec('Ready for hand over', $d['count'] > 0 && $d['canHand'] ? 'Hand out' : '', sl_route('WarehouseDelivery')) ?>
<form method="get" class="mx-srch" role="search"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q']) ?>" aria-label="Find a parcel" placeholder="Name, locker, phone or tracking" enterkeyhint="search"></label></form>
<?php if ($d['rows']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['rows'] as $r): ?>
<a class="mx-row" href="<?= h($d['canHand'] && $r['cleared'] ? sl_route('WarehouseDelivery', ['customer' => $r['sender']]) : $r['href']) ?>"><span class="mx-ava"><?= h($r['ini']) ?></span><span class="mx-rm"><b><?= h($r['cust']) ?></b><small><?= h($r['id']) ?> · bay <?= h($r['bay']) ?> · <?= h($r['days']) ?></small></span><span class="mx-rr"><span class="sl-pill <?= !$r['cleared'] ? 'pending' : ($r['verified'] ? 'delivered' : ($r['dn'] >= 7 ? 'overdue' : 'sorting')) ?>"><?= h(!$r['cleared'] ? 'Awaiting clearance' : ($r['verified'] ? 'Verified' : $r['code'])) ?></span></span></a>
<?php endforeach; ?>
</section>
<?php if ($d['count'] > $d['per']): $pages = (int) ceil($d['count'] / $d['per']); ?><nav class="wh-pg" aria-label="Pages"><?php if ($d['page'] > 1): ?><a href="<?= h(sl_url(['page' => $d['page'] - 1])) ?>">Previous</a><?php else: ?><span></span><?php endif; ?><span>Page <?= (int) $d['page'] ?> of <?= $pages ?></span><?php if ($d['page'] < $pages): ?><a href="<?= h(sl_url(['page' => $d['page'] + 1])) ?>">Next</a><?php else: ?><span></span><?php endif; ?></nav><?php endif; ?>
<?php else: ?>
<?= sl_mxEmpty('box', $d['q'] !== '' ? 'No parcel matches' : 'Nothing is waiting', $d['q'] !== '' ? '' : 'Parcels ready for pickup show here.') ?>
<?php endif; ?>
</main>
<?php if ($d['canReceive']): ?>
<script>
(function () {
  var f = document.getElementById('wh-scan'), res = document.getElementById('wh-res'); if (!f) return;
  f.addEventListener('sl:done', function (e) { var j = e.detail || {}; res.hidden = false; res.className = 'mx-ban ' + (j.success ? 'ok' : 'bad'); res.querySelector('span').textContent = j.message || ''; if (j.success) { f.tracking.value = ''; f.tracking.focus(); } if (!j.success && j.redirect) setTimeout(function () { location.href = j.redirect; }, 1200); }, true);
})();
</script>
<?php endif; ?>
