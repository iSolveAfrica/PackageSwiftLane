<?php
/**
 * M-CLoyalty: points, tier, referral code. Data: sl_caRewardsData(). Redeeming is done by staff
 * (ajax/sl/loyalty_ajax.php needs manage_loyalty), so rewards show what they cost and have no "Use" button.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$kinds = ['earn' => ['plus', 'Points earned'], 'redeem' => ['tag', 'Points used'], 'referral' => ['users', 'Referral reward'], 'adjust' => ['edit', 'Adjustment']];
$rate = fn(float $r) => rtrim(rtrim(number_format($r, 2), '0'), '.');
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pages/CustomerApp.css')) ?>">
<?= sl_phoneTop('Rewards', '', ['back' => sl_route('MyAccount')]) ?>
<main class="mx-body ca">
<?php if (empty($d['on'])): ?>
<section class="b3-em"><span class="b3-oi"><i class="ic i-star" aria-hidden="true"></i></span><b>Rewards are not switched on</b><small>There is no points programme on your account at the moment.</small><a class="b3-gh" href="<?= h(sl_route('Main')) ?>">Back to home</a></section>
</main>
<?php return; endif; ?>
<section class="mx-hero"><div class="mx-hk"><?= h($d['tier']) ?> member<span class="mx-tag"><?= h($d['tier']) ?></span></div><b class="mx-hv"><?= sl_num($d['balance']) ?> points</b><small><?php if ($d['next_tier']): ?><?= sl_num($d['toNext']) ?> points to <?= h($d['next_tier'][0]) ?> · <?php endif; ?>worth <?= h($d['worth']) ?> off your shipping</small><?php if ($d['next_tier']): ?><div class="mx-prog" role="progressbar" aria-valuemin="0" aria-valuemax="<?= (int) $d['next_tier'][1] ?>" aria-valuenow="<?= (int) min($d['lifetime'], $d['next_tier'][1]) ?>" aria-label="Progress to <?= h($d['next_tier'][0]) ?>"><i style="width:<?= (int) $d['pct'] ?>%"></i></div><?php endif; ?></section>
<?php if ($d['referralOn']): $shareText = 'Ship with ' . $d['site'] . '. Mention my referral code ' . $d['code'] . ' when you register.'; ?>
<section class="b2-hd"><b>Invite a friend, earn <?= sl_num($d['referrerPoints']) ?> points</b>
<div class="mx-in"><span class="mono"><?= h($d['code']) ?></span><button type="button" class="ca-copy" data-sl-copy="<?= h($d['code']) ?>" aria-label="Copy referral code"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></div>
<a class="b2-cta" href="https://wa.me/?text=<?= h(rawurlencode($shareText)) ?>" target="_blank" rel="noopener"><i class="ic ic-sm i-message" aria-hidden="true"></i>Share on WhatsApp</a>
<small class="mx-note"><?= sl_num($d['referrals']) ?> friend<?= $d['referrals'] === 1 ? '' : 's' ?> joined<?= $d['referralPoints'] > 0 ? ' · ' . sl_num($d['referralPoints']) . ' points earned' : '' ?>. Your friend mentions the code to our team when they register; you get the points when their first shipment is delivered.<?= $d['friendReward'] !== '' ? ' They get: ' . h($d['friendReward']) . '.' : '' ?></small></section>
<?php endif; ?>
<div class="mx-sec"><h2>Use points</h2></div>
<section class="mx-card mx-list">
<?= sl_mxRow('tag', sl_num($d['step']) . ' points = ' . $d['stepValue'] . ' off', 'Ask us to use your points when you pay a bill') ?>
<?php foreach ($d['rewards'] as $rw): $enough = $d['balance'] >= (int) $rw->points_cost; ?>
<div class="mx-row"><span class="mx-li"><i class="ic i-<?= $rw->kind === 'voucher' ? 'star' : 'tag' ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($rw->title) ?></b><small><?= sl_num($rw->points_cost) ?> points</small></span><span class="mx-rr"><span class="sl-pill <?= $enough ? 'delivered' : 'sorting' ?>"><?= $enough ? 'Enough points' : sl_num((int) $rw->points_cost - $d['balance']) . ' to go' ?></span></span></div>
<?php endforeach; ?>
</section>
<p class="mx-note">Points are applied by our team, not in the app yet. <a class="mx-lnk" href="support.php">Message support</a> to use them.</p>
<div class="mx-sec"><h2>How you earn</h2></div>
<section class="mx-card mx-list">
<?php if ($d['rateAir'] > 0): ?><?= sl_mxRow('plane', 'Air shipments', $rate($d['rateAir']) . ' point' . ($d['rateAir'] == 1 ? '' : 's') . ' for every ' . $d['unit'] . ' on a delivered shipment') ?><?php endif; ?>
<?php if ($d['rateSea'] > 0): ?><?= sl_mxRow('ship', 'Sea packages', $rate($d['rateSea']) . ' point' . ($d['rateSea'] == 1 ? '' : 's') . ' for every ' . $d['unit'] . ' on a delivered package') ?><?php endif; ?>
</section>
<div class="mx-sec"><h2>Points history</h2></div>
<?php if (!$d['events']): ?>
<section class="b3-em"><span class="b3-oi"><i class="ic i-clock" aria-hidden="true"></i></span><b>No points yet</b><small>You earn points when a shipment is delivered.</small></section>
<?php else: ?>
<section class="mx-card mx-list">
<?php foreach ($d['events'] as $e): $k = $kinds[$e->kind] ?? ['star', ucfirst((string) $e->kind)]; ?>
<div class="mx-row"><span class="mx-li"><i class="ic i-<?= h($k[0]) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($e->note ?: $k[1]) ?></b><small><?= h(sl_date($e->created_at)) ?></small></span><span class="mx-rr"><b class="<?= $e->points < 0 ? 'ca-neg' : 'ca-pos' ?>"><?= $e->points > 0 ? '+' : '−' ?><?= sl_num(abs((int) $e->points)) ?></b></span></div>
<?php endforeach; ?>
</section>
<?php endif; ?>
</main>
