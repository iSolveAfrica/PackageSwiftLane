<?php
/**
 * Rider app · Earnings (design: M-REarn). Data: sl_riderEarningsData() in views/sl/data/rider_money.php.
 * Shown: the rider's stored or rule-based share per finished trip. Tips, boosts and online hours are not
 * recorded by the system, so the design's tiles for them are replaced by numbers that are.
 */
if (!isset($d)) { $d = []; }
$bell = sl_mxIcon('bell', 'Notifications', sl_route('Notifications'), (int) ($d['unread'] ?? 0));
echo sl_rmOpen('Earnings', $d['sub'] ?? 'Rider app', $d['isRider'] ? ['avatar' => $d['ini'], 'href' => sl_route('RiderAccount')] : ['back' => sl_route('Main')], $d['isRider'] ? $bell : '', true);
if (!$d['isRider']) { echo sl_rmNotRider(), sl_rmClose(); return; }
?>
<div class="mxp-grid">
<div class="mxp-col">
<section class="mx-card rc-tot" aria-label="Week total">
  <div class="rc-wk"><a class="mx-ib" href="<?= h($d['prevHref']) ?>" aria-label="Previous week"><i class="ic i-left" aria-hidden="true"></i></a><span><b><?= h($d['span']) ?></b><small><?= h($d['spanSub']) ?></small></span><?php if ($d['isThisWeek']): ?><span class="mx-ib rc-off" aria-hidden="true"><i class="ic i-right" aria-hidden="true"></i></span><?php else: ?><a class="mx-ib" href="<?= h($d['nextHref']) ?>" aria-label="Next week"><i class="ic i-right" aria-hidden="true"></i></a><?php endif; ?></div>
  <b class="rc-big"><?= h($d['total']) ?></b>
  <div class="rc-sub"><?php if ($d['hasPrev']): ?><span class="<?= $d['delta'] >= 0 ? 'rc-up' : 'rm-down' ?>"><i class="ic ic-sm i-<?= $d['delta'] >= 0 ? 'trendup' : 'trend' ?>" aria-hidden="true"></i> <?= abs((int) $d['delta']) ?>% <?= $d['delta'] >= 0 ? 'up' : 'down' ?></span><span>on the week before · <?= h($d['prevTotal']) ?></span><?php else: ?><span>Your share of <?= h($d['gross']) ?> in fees</span><?php endif; ?></div>
  <?php if ($d['isThisWeek']): ?><div class="rc-live"><i aria-hidden="true"></i>Adds up after every finished trip</div><?php endif; ?>
</section>
<section class="mx-k c2">
  <?= sl_mxKpi('box', 'Trips', (string) $d['n'], ($d['nDelta'] >= 0 ? '+' : '−') . abs($d['nDelta']) . ' vs week before', $d['nDelta'] > 0 ? 'up' : '') ?>
  <?= sl_mxKpi('dollar', 'Average per trip', $d['avg'], $d['activeDays'] . ($d['activeDays'] === 1 ? ' day' : ' days') . ' with trips') ?>
  <?= sl_mxKpi('route', 'Still to do', (string) $d['open'], 'Assigned and open now') ?>
  <?= sl_mxKpi('alert', 'Failed attempts', (string) $d['failed'], 'Not paid', $d['failed'] ? 'warn' : '') ?>
</section>
<div class="mx-btns"><a class="mx-btn rs-amb" href="<?= h(sl_route('RiderWallet')) ?>"><i class="ic ic-sm i-card" aria-hidden="true"></i>Wallet · <?= h($d['owed']) ?> owed to you</a></div>
<section class="mx-card rc-chart">
  <div class="rc-chh"><b>Earnings by day</b><small><?= $d['day'] !== '' ? h($d['dayLabel']) : 'Tap a day to see its trips' ?></small></div>
  <div class="rc-cols"><?php foreach ($d['cols'] as $c): ?><a class="rc-col <?= h($c['cls']) ?>" href="<?= h($c['href']) ?>" aria-label="<?= h($c['aria']) ?>"><small><?= h($c['v']) ?></small><span class="rc-bar"><i style="<?= h($c['h']) ?>"></i></span><b><?= h($c['l']) ?></b></a><?php endforeach; ?></div>
  <div class="rc-chf"><?php if ($d['isThisWeek']): ?><span><i class="rc-sw now"></i>Today</span><?php endif; ?><span><i class="rc-sw"></i>Other days</span></div>
</section>
</div>
<div class="mxp-col">
<?= sl_mxSec('Where it came from') ?>
<?php if ($d['lines']): ?>
<section class="mx-card rc-brk"><div class="rc-ml"><?php foreach ($d['lines'] as $l): ?><div><span><?= h($l['l']) ?></span><b><?= h($l['v']) ?></b></div><?php endforeach; ?><div class="rc-ml-t"><span>Week total</span><b><?= h($d['total']) ?></b></div></div></section>
<?php else: ?>
<?= sl_mxEmpty('dollar', 'No earnings this week', $d['isThisWeek'] ? 'Your share appears here as soon as you finish a delivery or pickup.' : 'You finished no trips in this week.', $d['isThisWeek'] ? 'Open my jobs' : '', sl_route('RiderQueue')) ?>
<?php endif; ?>
<?php if ($d['skipped']): ?><p class="mx-note"><?= (int) $d['skipped'] ?> door <?= $d['skipped'] === 1 ? 'delivery was' : 'deliveries were' ?> priced in another currency and <?= $d['skipped'] === 1 ? 'is' : 'are' ?> not in this total. Ask the office about <?= $d['skipped'] === 1 ? 'it' : 'them' ?>.</p><?php endif; ?>
<p class="mx-note">Tips, peak-hour boosts and online hours are not recorded yet, so they are not shown.</p>
<div class="mx-sec"><h2><?= $d['day'] !== '' ? h($d['dayLabel']) : 'Trips this week' ?> · <?= (int) $d['tripCount'] ?></h2><?php if ($d['day'] !== ''): ?><a href="<?= h($d['allHref']) ?>">Whole week</a><?php endif; ?></div>
<?php if ($d['trips']): ?>
<section class="mx-card mx-list"><?php foreach ($d['trips'] as $t): ?><a class="mx-row" href="<?= h($t['href']) ?>"><span class="mx-ava"><?= h($t['ini']) ?></span><span class="mx-rm"><b><?= h($t['name']) ?></b><small><?= h($t['line']) ?></small><small class="mono"><?= h($t['ref']) ?></small></span><span class="mx-rr"><b><?= h($t['share']) ?></b><small><?= h($t['fee']) ?></small></span></a><?php endforeach; ?></section>
<?php if ($d['tripCount'] > count($d['trips'])): ?><p class="mx-note">Showing the latest <?= count($d['trips']) ?>. Tap a day in the chart to see that day’s trips.</p><?php endif; ?>
<?php else: ?>
<?= sl_mxEmpty('box', 'No finished trips', $d['day'] !== '' ? 'Nothing was finished on this day.' : 'Finished deliveries and pickups are listed here with your share.') ?>
<?php endif; ?>
<?= sl_mxSec('More') ?>
<section class="mx-card mx-list">
  <?php $q = $d['quest']; ?>
  <?= sl_mxRow('rocket', $q ? $q['title'] . ' · ' . $q['done'] . ' of ' . $q['target'] . ' trips' : 'Quests', $q ? $q['left'] . ' more to earn ' . $q['reward'] : 'Extra pay the office sets for hitting a trip target', $q ? '<b>' . h($q['pct']) . '%</b>' : '<i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i>', sl_route('RiderQuests')) ?>
  <?= sl_mxRow('star', 'Performance', 'Finished, failed and handover time', '<i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i>', sl_route('RiderRatings')) ?>
  <?= sl_mxRow('calendar', 'Shifts', 'Book the hours you plan to ride', '<i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i>', sl_route('RiderShifts')) ?>
</section>
</div>
</div>
<?= sl_rmClose() ?>
