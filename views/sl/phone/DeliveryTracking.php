<?php
/**
 * M-DelivTrack: one parcel on its way to the door, then the rest of the list. Same $d as the web page
 * (views/sl/data/shipments.php sl_deliveryTrackingData; GET filter, q, sel). The map is the same schematic as
 * the web page (there are no live GPS points); directions open the device's maps app for the address.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
require_once CDP_ROOT . '/helpers/sl_phone_centres.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
$s = $d['sel'] ?? null;
$card = null;
foreach ($d['cards'] as $c) if (!empty($c['open'])) { $card = $c; break; }
$pos = ['route' => [180, 75], 'ready' => [70, 75], 'unassigned' => [70, 75], 'today' => [290, 40], 'failed' => [180, 40]];
[$px, $py] = $pos[$d['filter']] ?? [180, 75];
$tl = ['now' => 'cur', 'done' => 'done', 'todo' => 'todo'];
$rtel = $s ? slpc_tel($s['phone']) : '';
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-centres.css')) ?>">
<?= sl_phoneTop('Delivery tracking', (int) $d['outN'] . ' out for delivery now', ['back' => 'menu.php'], sl_phoneIcon('refresh', 'Refresh', sl_url([]))) ?>
<main class="mx-body">
<?php if ($s && $card): ?>
<section class="mx-map" style="--mh:200px" role="img" aria-label="Schematic of the delivery route">
  <span class="mx-ml"><?= h($s['id']) ?><?= $s['rid'] ? ' · ' . h(strtok($s['rider'], ' ')) : '' ?></span>
  <svg viewBox="0 0 330 160" preserveAspectRatio="xMidYMid slice" aria-hidden="true"><rect x="-200" y="-300" width="760" height="760" fill="#E9E6E0"/><rect x="200" y="-110" width="80" height="50" rx="8" fill="#DCE7D6"/><rect x="20" y="180" width="90" height="60" rx="8" fill="#DCE7D6"/><rect x="230" y="190" width="60" height="40" rx="8" fill="#DCE7D6"/><path d="M-200 110 C 80 85, 140 130, 220 105 S 320 75, 560 95 L 560 125 C 300 115, 250 140, 200 133 S 70 115, -200 140Z" fill="#D9DEE0"/><g stroke="#F7F5F1" stroke-width="7"><path d="M-200 40H560M-200 75H560M70 -300V460M180 -300V460M290 -300V460M-200 -40H560M-200 170H560M-200 260H560"/></g><g stroke="#F7F5F1" stroke-width="4"><path d="M-200 -90H560M120 -300V40M240 75V460M20 260L120 170"/></g><polyline points="40,128 70,75 180,75 180,40 290,40" fill="none" stroke="#D9792B" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="6 7" class="mx-dash"/><circle cx="70" cy="75" r="8" fill="#161615" stroke="#fff" stroke-width="3"/><path d="M290 22c-6 0-11 5-11 11 0 8 11 18 11 18s11-10 11-18c0-6-5-11-11-11z" fill="#161615"/><circle cx="290" cy="33" r="4" fill="#fff"/><circle cx="<?= (int) $px ?>" cy="<?= (int) $py ?>" r="13" fill="#D9792B" opacity=".18"/><circle cx="<?= (int) $px ?>" cy="<?= (int) $py ?>" r="7" fill="#D9792B" stroke="#fff" stroke-width="3"/></svg>
<?php if ($card['toSub'] !== '—'): ?>
  <div class="mx-mctl"><a class="mx-ib" href="https://www.google.com/maps/dir/?api=1&amp;destination=<?= h(rawurlencode($card['toSub'])) ?>" data-maps-dest="<?= h($card['toSub']) ?>" target="_blank" rel="noopener" aria-label="Directions to <?= h($card['toSub']) ?>"><i class="ic ic-sm i-route" aria-hidden="true"></i></a></div>
<?php endif; ?>
</section>
<section class="mx-hero lt">
  <div class="mx-hk"><?= h(ucfirst(strtolower($s['status']))) ?><span class="mx-tag"><?= h($card['etaLabel']) ?><?= $card['eta'] !== '—' ? ' ' . h($card['eta']) : '' ?></span></div>
  <b class="mx-hv"><?= $card['left'] !== '' ? h($card['left']) : 'No scan yet' ?></b>
  <small><?= h(implode(' · ', array_filter([$card['to'], $card['toSub']], fn($x) => $x !== '—' && $x !== ''))) ?></small>
  <div class="mx-prog"><i style="<?= h($card['w']) ?>"></i></div>
</section>
<section class="mx-card mx-kv">
  <div><span>Rider</span><b><?php if ($s['rid']): ?><a href="<?= h(sl_route('RiderDetail', ['id' => $s['rid']])) ?>"><?= h($s['rider']) ?></a><?php else: ?><?= h($s['rider']) ?><?php endif; ?></b></div>
  <div><span>Customer</span><b><?= h($card['cust']) ?> · <?= h($card['locker']) ?></b></div>
  <div><span>Pieces · weight</span><b><?= (int) $s['pcs'] ?> · <?= h($s['kg']) ?></b></div>
  <div><span>Pickup code</span><b><?= h($s['code']) ?></b></div>
</section>
<?php if (!empty($d['steps'])): ?>
<section class="mx-card"><ol class="mx-tl">
<?php foreach (array_slice($d['steps'], 0, 6) as $t): ?>
  <li class="<?= h($tl[$t['cls']] ?? 'done') ?>"><span class="mx-td"><i class="ic ic-sm <?= h($t['icon']) ?>" aria-hidden="true"></i></span><div><b><?= h(ucfirst(strtolower($t['what']))) ?></b><small><?= h(trim($t['date'] . ' ' . $t['time'])) ?><?= $t['where'] !== '—' ? ' · ' . h($t['where']) : '' ?></small></div></li>
<?php endforeach; ?>
</ol></section>
<?php endif; ?>
<div class="mx-btns">
<?php if ($rtel): ?><a class="mx-btn" href="<?= h($rtel) ?>">Call rider</a><?php endif; ?>
<?php if ($card['custId']): ?><a class="mx-btn sec" href="<?= h(sl_route('ComposeMessage', ['to' => $card['custId']])) ?>">Message customer</a><?php endif; ?>
  <a class="mx-btn sec" href="<?= h(sl_route('ShipmentTracking', ['id' => $s['oid']])) ?>">Full tracking</a>
</div>
<?php endif; ?>
<form method="get"><input type="hidden" name="filter" value="<?= h($d['filter']) ?>"><div class="mx-srch"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q']) ?>" aria-label="Search tracking number, customer or rider" placeholder="Tracking, customer or rider" autocomplete="off"></label></div></form>
<div class="mx-seg" role="tablist" aria-label="Show">
<?php foreach ($d['filters'] as $f): ?>
  <a role="tab" aria-selected="<?= $f['cls'] === 'on' ? 'true' : 'false' ?>" class="<?= h($f['cls']) ?>" href="<?= h(sl_url(['filter' => $f['f'], 'sel' => null])) ?>"><?= h($f['label']) ?></a>
<?php endforeach; ?>
</div>
<?php if ($d['cards']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['cards'] as $c): ?>
<a class="mx-row" href="<?= h(sl_url(['sel' => $c['id']])) ?>"<?= $c['open'] ? ' aria-current="true"' : '' ?>><span class="mx-li"><i class="ic <?= h($c['tkIcon']) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b class="mono"><?= h($c['track']) ?></b><small><?= h($c['cust']) ?> · <?= h($c['toShort']) ?> · rider <?= h($c['driver']) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($c['pc']) ?>"><?= h($c['st']) ?></span><small><?= h($c['eta']) ?></small></span></a>
<?php endforeach; ?>
</section>
<?php else: ?>
<?= sl_mxEmpty('truck', 'Nothing in this list right now', $d['q'] !== '' ? 'Nothing matches “' . $d['q'] . '”.' : '') ?>
<?php endif; ?>
</main>
