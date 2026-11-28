<?php
/**
 * Rider app · Performance (design: M-RPerf). Data: sl_riderRatingsData() in views/sl/data/rider_money.php.
 * Customers cannot rate riders today, so there are no stars, compliments or comments: the screen shows the
 * numbers the system really has (finished, failed, handover time on door deliveries).
 */
if (!isset($d)) { $d = []; }
echo sl_rmOpen('Performance', 'Last 90 days', ['back' => sl_route('RiderAccount')], $d['isRider'] ? sl_mxIcon('help', 'Help and contact', sl_route('RiderChat')) : '', true);
if (!$d['isRider']) { echo sl_rmNotRider(), sl_rmClose(); return; }
$p = $d['p'];
$attempts = $p['done'] + $p['failed'];
?>
<div class="mxp-grid">
<div class="mxp-col">
<section class="mx-k c2">
  <?= sl_mxKpi('check', 'Finished', (string) $p['done'], 'Deliveries and pickups', $p['done'] ? 'up' : '') ?>
  <?= sl_mxKpi('ban', 'Failed attempts', (string) $p['failed'], $attempts ? 'of ' . $attempts . ' attempts' : 'None recorded', $p['failed'] ? 'warn' : '') ?>
  <?= sl_mxKpi('thumb', 'Completion', $p['finish'] !== null ? $p['finish'] . '%' : '—', 'Finished out of attempted') ?>
  <?= sl_mxKpi('clock', 'Handover time', $d['handover'], $p['handoverN'] ? 'Average of ' . $p['handoverN'] . ' door deliveries' : 'No door deliveries yet') ?>
</section>
<div class="mx-ban info"><i class="ic ic-sm i-star" aria-hidden="true"></i><span>Customer star ratings and comments are not collected yet. When they are, they will show here.</span></div>
<?= sl_mxSec('The numbers') ?>
<section class="mx-card rd-tms">
  <div class="rd-tm"><div><span>Completion</span><b><?= $p['finish'] !== null ? (int) $p['finish'] . '%' : '—' ?></b></div><span class="mx-trk rd-trk"><i class="<?= $p['finish'] !== null && $p['finish'] >= 90 ? 'ok' : 'warn' ?>" style="width:<?= (int) ($p['finish'] ?? 0) ?>%"></i></span><small><?= $attempts ? (int) $p['done'] . ' finished and ' . (int) $p['failed'] . ' failed in ' . (int) $p['days'] . ' days' : 'No finished or failed trips in the last ' . (int) $p['days'] . ' days' ?></small></div>
  <div class="rd-tm"><div><span>Customer door deliveries</span><b><?= (int) $p['door'] ?></b></div><span class="mx-trk rd-trk"><i style="width:<?= sl_pct($p['door'], max(1, $p['done'])) ?>%"></i></span><small><?= (int) $p['door'] ?> of <?= (int) $p['done'] ?> finished trips</small></div>
  <div class="rd-tm"><div><span>Pickups</span><b><?= (int) $p['pickups'] ?></b></div><span class="mx-trk rd-trk"><i style="width:<?= sl_pct($p['pickups'], max(1, $p['done'])) ?>%"></i></span><small><?= (int) $p['pickups'] ?> of <?= (int) $p['done'] ?> finished trips</small></div>
</section>
<p class="mx-note">On-time delivery is not measured: jobs have no promised time. Handover time runs from “on the way” to the finished delivery and exists for customer door deliveries only.</p>
</div>
<div class="mxp-col">
<section class="mx-card mx-bars">
  <div class="mx-bh"><b>Finished trips per week</b><small>12 weeks</small></div>
  <div class="mx-bc" role="img" aria-label="Finished trips per week for the last 12 weeks"><?php foreach ($d['bars'] as $b): ?><span class="<?= $b['cls'] === 'on' ? 'now' : '' ?>" title="<?= h($b['t']) ?>"><i style="<?= h($b['h']) ?>"></i></span><?php endforeach; ?></div>
</section>
<section class="mx-card mx-kv">
  <div><span>Finished since you joined</span><b><?= sl_num($d['lifetime']) ?></b></div>
  <div><span>Rider since</span><b><?= h($d['since']) ?></b></div>
  <div><span>Open jobs now</span><b><?= (int) $p['open'] ?></b></div>
</section>
<?= sl_mxSec('Latest finished trips', $d['recent'] ? 'Earnings' : '', sl_route('RiderEarnings')) ?>
<?php if ($d['recent']): ?>
<section class="mx-card mx-list"><?php foreach ($d['recent'] as $t): ?><a class="mx-row" href="<?= h($t['href']) ?>"><span class="mx-ava"><?= h($t['ini']) ?></span><span class="mx-rm"><b><?= h($t['name']) ?></b><small><?= h($t['line']) ?></small><small class="mono"><?= h($t['ref']) ?></small></span><span class="mx-rr"><b><?= h($t['share']) ?></b></span></a><?php endforeach; ?></section>
<?php else: ?>
<?= sl_mxEmpty('box', 'No finished trips yet', 'Your finished deliveries and pickups build these numbers.', 'Open my jobs', sl_route('RiderQueue')) ?>
<?php endif; ?>
<?= sl_mxSec('What helps') ?>
<section class="mx-card mx-list">
  <?= sl_mxRow('image', 'Take a clear handover photo', 'Fewer disputes about who received the parcel') ?>
  <?= sl_mxRow('message', 'Message before you arrive', 'Fewer failed attempts', '<i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i>', sl_route('RiderChat')) ?>
</section>
</div>
</div>
<?= sl_rmClose() ?>
