<?php
/**
 * Rewards (design: M-CLoyalty). Data: sl_caRewardsData(). Points are redeemed by staff on an invoice
 * (ajax/sl/loyalty_ajax.php needs manage_loyalty), so this screen explains how points are used and has no "Use" button.
 */
if (!isset($d)) { $d = []; }
$kinds = ['earn' => ['plus', 'Points earned'], 'redeem' => ['tag', 'Points used'], 'referral' => ['users', 'Referral reward'], 'adjust' => ['edit', 'Adjustment']];
$rate = fn(float $r) => rtrim(rtrim(number_format($r, 2), '0'), '.');
?>
<div class="mxp wide ca">
  <?= sl_mxHead('Rewards', '', sl_route('MyAccount')) ?>
  <?php if (empty($d['on'])): ?>
    <?= sl_mxEmpty('star', 'Rewards are not switched on', 'There is no points programme on your account at the moment. Nothing is lost: if it opens, your delivered parcels start earning from then.', 'Back to home', sl_route('Main')) ?>
  </div>
  <?php return; endif; ?>
  <div class="mxp-grid">
    <div class="mxp-col">
      <section class="mx-hero">
        <div class="mx-hk"><?= h($d['tier']) ?> member<span class="mx-tag"><?= h($d['tier']) ?></span></div>
        <b class="mx-hv"><?= sl_num($d['balance']) ?> points</b>
        <small>Worth <?= h($d['worth']) ?> off your shipping<?php if ($d['next_tier']): ?> · <?= sl_num($d['toNext']) ?> points to <?= h($d['next_tier'][0]) ?><?php endif; ?></small>
        <?php if ($d['next_tier']): ?><div class="mx-prog" role="progressbar" aria-valuemin="0" aria-valuemax="<?= (int) $d['next_tier'][1] ?>" aria-valuenow="<?= (int) min($d['lifetime'], $d['next_tier'][1]) ?>" aria-label="Progress to <?= h($d['next_tier'][0]) ?>"><i style="width:<?= (int) $d['pct'] ?>%"></i></div><?php endif; ?>
      </section>
      <div class="mx-k c2">
        <?= sl_mxKpi('star', 'Earned in total', sl_num($d['lifetime']), 'points since you joined') ?>
        <?= sl_mxKpi('users', 'Friends joined', sl_num($d['referrals']), $d['referralOn'] ? sl_num($d['rewarded']) . ' shipped' : '') ?>
      </div>
      <?php if ($d['referralOn']): ?>
      <section class="mx-card ca-ref">
        <b>Invite a friend, earn <?= sl_num($d['referrerPoints']) ?> points</b>
        <small class="mx-note">Your friend enters this code when they register, or opens your link, which fills it in. You get the points when their first shipment is delivered.<?= $d['friendReward'] !== '' ? ' Your friend gets: ' . h($d['friendReward']) . '.' : '' ?></small>
        <div class="mx-in"><span class="mono"><?= h($d['code']) ?></span><button type="button" class="ca-copy" data-sl-copy="<?= h($d['code']) ?>" aria-label="Copy referral code"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></div>
        <button type="button" class="mx-btn" data-mx-share="<?= h($d['signupLink']) ?>" data-title="<?= h($d['site']) ?>" data-text="Ship with <?= h($d['site']) ?>. Register with my code <?= h($d['code']) ?>:"><i class="ic ic-sm i-send" aria-hidden="true"></i>Share my code</button>
        <small class="mx-note"><?= sl_num($d['referrals']) ?> friend<?= $d['referrals'] === 1 ? '' : 's' ?> joined<?= $d['referralPoints'] > 0 ? ' · ' . sl_num($d['referralPoints']) . ' points earned' : '' ?>..</small>
      </section>
      <?php endif; ?>
    </div>
    <div class="mxp-col">
      <?= sl_mxSec('How points work') ?>
      <section class="mx-card mx-list">
        <?php if ($d['rateAir'] > 0): ?><?= sl_mxRow('plane', 'Air shipments', $rate($d['rateAir']) . ' point' . ($d['rateAir'] == 1 ? '' : 's') . ' for every ' . $d['unit'] . ' on a delivered shipment') ?><?php endif; ?>
        <?php if ($d['rateSea'] > 0): ?><?= sl_mxRow('ship', 'Sea packages', $rate($d['rateSea']) . ' point' . ($d['rateSea'] == 1 ? '' : 's') . ' for every ' . $d['unit'] . ' on a delivered package') ?><?php endif; ?>
        <?= sl_mxRow('tag', sl_num($d['step']) . ' points = ' . $d['stepValue'] . ' off', 'Ask us to use your points when you pay a bill') ?>
      </section>
      <?php if ($d['rewards']): ?>
      <?= sl_mxSec('Rewards you can ask for') ?>
      <section class="mx-card mx-list">
        <?php foreach ($d['rewards'] as $rw): $enough = $d['balance'] >= (int) $rw->points_cost; ?>
        <div class="mx-row"><span class="mx-li"><i class="ic i-<?= $rw->kind === 'voucher' ? 'star' : 'tag' ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($rw->title) ?></b><small><?= sl_num($rw->points_cost) ?> points</small></span><span class="mx-rr"><span class="sl-pill <?= $enough ? 'delivered' : 'sorting' ?>"><?= $enough ? 'Enough points' : sl_num((int) $rw->points_cost - $d['balance']) . ' to go' ?></span></span></div>
        <?php endforeach; ?>
      </section>
      <?php endif; ?>
      <p class="mx-note">Points are applied by our team, not in the app yet. <a class="mx-lnk" href="support.php">Message support</a> to use them.</p>
      <?= sl_mxSec('Points history') ?>
      <?php if (!$d['events']): ?>
        <?= sl_mxEmpty('clock', 'No points yet', 'You earn points when a shipment is delivered.') ?>
      <?php else: ?>
      <section class="mx-card mx-list">
        <?php foreach ($d['events'] as $e): $k = $kinds[$e->kind] ?? ['star', ucfirst((string) $e->kind)]; ?>
        <div class="mx-row"><span class="mx-li"><i class="ic i-<?= h($k[0]) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($e->note ?: $k[1]) ?></b><small><?= h(sl_date($e->created_at)) ?></small></span><span class="mx-rr"><b class="<?= $e->points < 0 ? 'ca-neg' : 'ca-pos' ?>"><?= $e->points > 0 ? '+' : '−' ?><?= sl_num(abs((int) $e->points)) ?></b></span></div>
        <?php endforeach; ?>
      </section>
      <p class="mx-note">Showing your last <?= count($d['events']) ?> entries.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
