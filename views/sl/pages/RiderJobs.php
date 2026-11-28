<?php
/**
 * The rider's run today (design: M-RQueue). Data: ro_data(): finished today, then started stops and pickups to
 * collect, then what has not started, in the order dispatch assigned them.
 * Left out: batch numbers, minute estimates, route optimisation, scheduled time slots (none are recorded).
 */
if (!isset($d)) { $d = []; }
$ph = !empty($roPhone);
if (empty($d['isRider'])) { ro_notRider($ph, 'Jobs'); return; }
$slPhone['tab'] = 'Jobs';
$next = $d['next'];
$total = count($d['done']) + count($d['open']);
$collected = [];
foreach ($d['done'] as $s) if ($s['collect'] && $s['collected']) $collected[] = [$s['fee'], $s['cur']];
$collectedN = count($collected);
echo ro_open($ph, 'Jobs', $total ? $total . ' stop' . ($total === 1 ? '' : 's') . ' today' : 'Nothing assigned today', ['avatar' => $d['ini'], 'href' => sl_route('RiderAccount'), 'online' => $d['duty']['online']], sl_mxIcon('route', 'Route map', sl_route('RiderMap')) . sl_mxIcon('message', 'Chat', sl_route('RiderChat')));
?>
<section class="mx-k c3"><?= sl_mxKpi('pin', 'Left', (string) count($d['open']), 'of ' . $total . ' stops') ?><?= sl_mxKpi('dollar', 'To earn', $d['openPay'], 'on open stops') ?><?= sl_mxKpi('check', 'Earned', $d['earnedTxt'], (int) $d['trips'] . ' done today', $d['trips'] ? 'up' : '') ?></section>
<a class="mx-card ra-en" href="<?= h(sl_route('RiderTasks')) ?>"><span class="mx-li"><i class="ic i-inbox" aria-hidden="true"></i></span><span class="mx-rm"><b>Task list</b><small><?= count($d['new']) ? count($d['new']) . ' assigned, not started' : 'Nothing waiting to start' ?></small></span><?php if ($d['new']): ?><span class="mx-cnt"><?= count($d['new']) ?></span><?php endif; ?><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<div class="mxp-grid">
<div class="mxp-col">
<?php if (!$total): ?>
<?= sl_mxEmpty('box', 'No stops today', 'Your run appears here when dispatch assigns pickups or deliveries to you.') ?>
<?php else: ?>
<section class="mx-card ra-bt"><div class="ra-bth"><span class="mx-rm"><b>Today’s run</b><small><?= count($d['open']) ?> to go · <?= (int) $d['parcelsOpen'] ?> parcel<?= $d['parcelsOpen'] === 1 ? '' : 's' ?> on open stops</small></span><span class="sl-pill <?= $d['active'] ? 'transit' : ($d['open'] ? 'sorting' : 'delivered') ?>"><?= $d['active'] ? 'On route' : ($d['open'] ? 'Not started' : 'Done') ?></span></div>
<ol class="ra-ns"><?php $n = 0; foreach (array_merge($d['done'], $d['open']) as $s): $n++; $isNext = $next && $s['key'] === $next['key']; $cls = $s['state'] === 'done' ? 'done' : ($isNext ? 'next' : 'later'); ?>
<li class="<?= $cls ?>"><span class="ra-n"><?php if ($s['state'] === 'done'): ?><i class="ic ic-sm i-check" aria-hidden="true"></i><?php else: ?><?= $n ?><?php endif; ?></span><a class="ro-st" href="<?= h($s['href']) ?>"><b><?= h(($s['pickup'] ? 'Pickup · ' : '') . $s['name']) ?></b><small><?= h($s['state'] === 'done' ? ($s['pickup'] ? 'Collected ' : 'Delivered ') . $s['atTxt'] . ($s['receiver'] !== '' ? ' · ' . $s['receiver'] : '') : ($s['addr'] ?: 'Address not supplied') . ' · ' . $s['nTxt'] . ($s['collect'] ? ' · collect ' . $s['feeTxt'] : '')) ?></small></a><em><?= h($s['state'] === 'done' ? 'Done' : ($isNext ? 'Next' : $s['st'])) ?></em></li>
<?php endforeach; ?></ol>
<div class="ra-bta ro-eq"><a class="mx-btn sec" href="<?= h(sl_route('RiderMap')) ?>"><i class="ic ic-sm i-route" aria-hidden="true"></i>Map</a><a class="mx-btn sec" href="<?= h(sl_route('RiderScan')) ?>"><i class="ic ic-sm i-scan" aria-hidden="true"></i>Scan parcels</a></div></section>
<?php endif; ?>
<?php if ($next): ?><a class="ra-swipe" href="<?= h($next['href']) ?>"><span class="ra-knob"><i class="ic i-right" aria-hidden="true"></i></span><span class="ra-swt"><b><?= $next['state'] === 'new' ? 'See the next job and start' : ($next['pickup'] ? 'Go to the next pickup' : 'Go to the next stop') ?></b><small><?= h($next['name'] . ' · ' . ($next['addr'] ?: $next['ref'])) ?></small></span></a><?php endif; ?>
</div>
<div class="mxp-col">
<?php if ($d['failed']): ?>
<div class="mx-sec"><h2>With dispatch</h2></div>
<section class="mx-card mx-list"><?php foreach ($d['failed'] as $s): ?><?= sl_mxRow($s['ini'], $s['name'], 'Failed attempt · ' . $s['ref'], '<span class="sl-pill overdue">Waiting</span>', $s['open'], true) ?><?php endforeach; ?></section>
<p class="mx-note">Dispatch reviews a failed attempt and sends the job out again.</p>
<?php endif; ?>
<?php if ($collectedN): ?>
<section class="mx-card mx-list"><?= sl_mxRow('dollar', 'Delivery fees collected today', $collectedN . ' door deliver' . ($collectedN === 1 ? 'y' : 'ies') . ' · hand in at the office', '<b>' . h(ro_sumTxt($collected)) . '</b>', sl_route('RiderWallet')) ?></section>
<?php endif; ?>
<section class="mx-card mx-list"><?= sl_mxRow('dollar', 'Earnings', (int) $d['trips'] . ' finished today', '<b>' . h($d['earnedTxt']) . '</b>', sl_route('RiderEarnings')) ?><?= sl_mxRow('calendar', 'Shifts', 'Your booked blocks', '<i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i>', sl_route('RiderShifts')) ?></section>
<p class="mx-note">Stops are in the order dispatch assigned them. Time slots and route optimisation are not part of the app yet.</p>
</div>
</div>
<?= ro_close($ph) ?>
