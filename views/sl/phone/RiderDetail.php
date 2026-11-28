<?php
/**
 * M-RiderDetail: one rider today. Same $d as the web page (views/sl/data/riders.php sl_riderDetailData).
 * No writes here; settling stays on the web page (ajax/sl/rider_ajax.php).
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
require_once CDP_ROOT . '/helpers/sl_phone_centres.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
$t = $d['today'];
$m = $d['month'];
$next = null;
foreach ($d['run'] as $k) if ($k['state'] === 'open') { $next = $k; break; }
$tel = slpc_tel($d['phone']);
$wa = slpc_wa($d['phone']);
$week = slpc_riderDaily(7, (int) $d['id']);
$acts = $d['canEdit'] ? sl_phoneIcon('edit', 'Edit rider', 'drivers_edit.php?user=' . (int) $d['id']) : '';
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-centres.css')) ?>">
<?= sl_phoneTop($d['name'], $d['veh'], ['back' => sl_route('Drivers')], $acts) ?>
<main class="mx-body">
<section class="mx-hero">
  <div class="mx-hk">Today<?= $d['zone'] !== '—' ? ' · ' . h($d['zone']) : '' ?><span class="mx-tag"><?= h($d['st'][0]) ?></span></div>
  <b class="mx-hv"><?= (int) $t['done'] ?> of <?= (int) $t['assigned'] ?> delivered</b>
  <small><?= $next ? 'Next: ' . h($next['addr']) : ($t['assigned'] ? 'Nothing left to do today' : 'No packages assigned today') ?></small>
  <div class="mx-prog"><i style="width:<?= (int) sl_pct($t['done'], max(1, $t['assigned'])) ?>%"></i></div>
</section>
<section class="mx-act c4">
  <a href="<?= h($tel ?: '#') ?>"<?= $tel ? '' : ' aria-disabled="true" tabindex="-1"' ?>><span><i class="ic i-phone" aria-hidden="true"></i></span><small>Call</small></a>
  <a href="<?= h($wa ?: sl_route('ComposeMessage', ['to' => $d['id']])) ?>"<?= $wa ? ' target="_blank" rel="noopener"' : '' ?>><span><i class="ic i-message" aria-hidden="true"></i></span><small><?= $wa ? 'WhatsApp' : 'Message' ?></small></a>
  <a href="<?= h(sl_route('DeliveryTracking')) ?>"><span><i class="ic i-route" aria-hidden="true"></i></span><small>See live</small></a>
  <a href="<?= h(sl_route('RiderDispatch')) ?>"<?= $d['canDispatch'] ? '' : ' aria-disabled="true" tabindex="-1"' ?>><span><i class="ic i-box" aria-hidden="true"></i></span><small>Assign</small></a>
</section>
<section class="mx-card mx-kv">
  <div><span>Phone</span><b><?= h($d['phone']) ?></b></div>
  <div><span>Vehicle</span><b><?= h($d['veh']) ?></b></div>
  <div><span>Rider since</span><b><?= h($d['since']) ?></b></div>
  <div><span>Done this month</span><b><?= (int) $m['done'] ?><?= $m['failed'] ? ' · ' . (int) $m['failed'] . ' failed' : '' ?></b></div>
  <div><span>Earned this month</span><b><?= h(sl_money($m['rid'])) ?></b></div>
  <div><span>Not yet settled</span><b><?= h($d['due'] ? $d['due']['txt'] : sl_money(0)) ?></b></div>
</section>
<section class="mx-card mx-bars">
  <div class="mx-bh"><b>Deliveries this week</b><small><?= (int) array_sum(array_column($week, 'n')) ?> in 7 days</small></div>
  <?= slpc_bars(array_column($week, 'n'), array_column($week, 'l')) ?>
</section>
<?php if ($d['run']): ?>
<?= sl_mxSec("Today's run sheet") ?>
<section class="mx-card mx-list">
<?php foreach (array_slice($d['run'], 0, 12) as $k): ?>
<a class="mx-row" href="<?= h(sl_route('ShipmentDetail', ['id' => $k['id']])) ?>"><span class="mx-li"><i class="ic i-<?= $k['pickup'] ? 'download' : 'box' ?>" aria-hidden="true"></i></span><span class="mx-rm"><b class="mono"><?= h($k['trk']) ?></b><small><?= h($k['cust'] . ' · ' . $k['addr']) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($k['cls']) ?>"><?= h($k['st']) ?><?= $k['at'] ? ' ' . h($k['at']) : '' ?></span></span></a>
<?php endforeach; ?>
</section>
<?php endif; ?>
</main>
