<?php
/**
 * Rider app · Quests (design: M-RQuests). Data: sl_riderQuestsData() in views/sl/data/rider_money.php.
 * Quests are rows of cdb_rider_quests set by staff; progress is the rider's finished trips in the quest window.
 * Boost zones, tiers, streak pay and referrals have no backend and are left out.
 */
if (!isset($d)) { $d = []; }
echo sl_rmOpen('Quests', 'Extra pay on top of trips', ['back' => sl_route('RiderEarnings')], '', true);
if (!$d['isRider']) { echo sl_rmNotRider(), sl_rmClose(); return; }
$icons = ['live' => 'flag', 'soon' => 'calendar', 'earned' => 'check', 'missed' => 'clock'];
?>
<div class="mxp-grid">
<div class="mxp-col">
<section class="mx-k c2">
  <?= sl_mxKpi('box', 'Trips this week', (string) $d['weekTrips'], $d['activeDays'] . ' of 7 days with trips', $d['weekTrips'] ? 'up' : '') ?>
  <?= sl_mxKpi('dollar', 'Earned this week', $d['weekEarned'], 'Trip pay only') ?>
  <?= sl_mxKpi('route', 'Open jobs', (string) $d['open'], 'Each one counts when finished') ?>
  <?= sl_mxKpi('alert', 'Failed attempts', (string) $d['failed'], 'Do not count', $d['failed'] ? 'warn' : '') ?>
</section>
<?php if (!$d['quests']): ?>
<?= sl_mxEmpty('rocket', 'No quests are running', $d['ready'] ? 'When the office sets a quest, it appears here with your progress and the reward.' : 'Quests are not set up on this system yet.') ?>
<?php endif; ?>
<?php foreach ($d['quests'] as $q): ?>
<section class="mx-card rc-q" aria-label="<?= h($q['title']) ?>">
  <div class="rc-qh"><span class="mx-li"><i class="ic i-<?= h($icons[$q['state']]) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($q['title']) ?></b><small>Finish <?= (int) $q['target'] ?> trips, get <?= h($q['reward']) ?> on top of trip pay</small></span><span class="sl-pill <?= h($q['pill'][1]) ?>"><?= h($q['pill'][0]) ?></span></div>
  <div class="rc-qp"><b><?= (int) $q['done'] ?><small> / <?= (int) $q['target'] ?> trips</small></b><b class="rc-qr"><?= h($q['reward']) ?></b></div>
  <span class="mx-trk rc-qt" role="img" aria-label="<?= (int) $q['pct'] ?>% done"><i class="<?= $q['state'] === 'earned' ? 'ok' : 'or' ?>" style="width:<?= (int) $q['pct'] ?>%"></i></span>
  <small class="rc-qw"><i class="ic ic-sm i-clock" aria-hidden="true"></i>
  <?php if ($q['state'] === 'live'): ?><?= (int) $q['left'] ?> more · ends in <b data-mx-countdown="<?= h($q['endsIso']) ?>" data-done="a moment"></b>
  <?php elseif ($q['state'] === 'soon'): ?><?= h($q['span']) ?>
  <?php elseif ($q['state'] === 'earned'): ?>Target reached · the office adds <?= h($q['reward']) ?> to your next settlement
  <?php else: ?>Ended <?= h($q['span']) ?> · <?= (int) $q['left'] ?> short<?php endif; ?></small>
</section>
<?php endforeach; ?>
<?php if ($d['quests']): ?><p class="mx-note">Quest rewards are not added to your wallet automatically yet. The office adds a reached reward when it settles your pay.</p><?php endif; ?>
</div>
<div class="mxp-col">
<section class="mx-card rc-days7" aria-label="Days with trips this week">
  <div class="rc-d7h"><b>This week · <?= (int) $d['activeDays'] ?> <?= $d['activeDays'] === 1 ? 'day' : 'days' ?> with trips</b><small><?= (int) $d['weekTrips'] ?> trips</small></div>
  <div class="rc-d7"><?php foreach ($d['days'] as $x): ?><span class="<?= $x['cls'] === 'now' ? 'cur' : ($x['on'] ? 'hit' : '') ?>"><i></i><small><?= h($x['l']) ?><?= $x['n'] ? ' ' . (int) $x['n'] : '' ?></small></span><?php endforeach; ?></div>
</section>
<?php if ($d['extra']): ?>
<?= sl_mxSec('Fee rules the office uses') ?>
<section class="mx-card mx-list"><?php foreach ($d['extra'] as $r): ?><?= sl_mxRow($r['icon'], $r['label'], $r['kind'], '<b>' . h($r['amount']) . '</b>') ?><?php endforeach; ?></section>
<p class="mx-note">These are set by the office and applied when it settles your pay. They are not added to the totals in this app.</p>
<?php endif; ?>
<div class="mx-btns"><a class="mx-btn rs-amb" href="<?= h(sl_route('RiderQueue')) ?>"><i class="ic ic-sm i-box" aria-hidden="true"></i>Open my jobs</a><a class="mx-btn sec" href="<?= h(sl_route('RiderEarnings')) ?>">See this week’s earnings</a></div>
<p class="mx-note">Peak-hour boosts, tiers, streak pay and referral rewards are not available yet.</p>
</div>
</div>
<?= sl_rmClose() ?>
