<?php
/**
 * M-Loyalty: loyalty, referrals and coupons. Same $d as the web page (views/sl/data/loyalty.php sl_loyaltyData).
 * Switches post to the existing ajax/sl/loyalty_ajax.php exactly as the web switches
 * (op toggle + key loyalty_enabled | referral_enabled; op reward_toggle + id), with value 1/0.
 * The award, coupon and rule forms stay on the website.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
$EP = 'ajax/sl/loyalty_ajax.php';
$sp = function (array $bars): string {
    if (!$bars) return '';
    $out = '<div class="mx-sp" aria-hidden="true">';
    foreach ($bars as $i => $b) $out .= '<i class="' . ($i === count($bars) - 1 ? 'now' : '') . '" style="' . h($b['s']) . '"></i>';
    return $out . '</div>';
};
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-centres.css')) ?>">
<?= sl_phoneTop('Loyalty & referrals', '', ['back' => 'menu.php'], sl_phoneIcon('send', 'Send a campaign', sl_route('Broadcast'))) ?>
<main class="mx-body">
<?php if (!$d['ready']): ?>
<?= sl_mxEmpty('star', 'The loyalty tables are missing', 'Run migrations/2026_11_06_support_ai_loyalty.sql.') ?>
<?php else: $s = $d['settings']; $tMax = max(1, ...array_map(fn($x) => (int) $x['n'], $d['tiers'])); ?>
<section class="mx-k c2">
<?php foreach (array_slice($d['kpis'], 0, 4) as $k): ?>
  <div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm <?= h($k['icon']) ?>" aria-hidden="true"></i></span><small><?= h($k['l']) ?></small></div><b><?= h($k['v']) ?></b><em><?= h($k['s']) ?></em><?= $sp($k['sp']) ?></div>
<?php endforeach; ?>
</section>
<?= sl_mxSec('Tiers') ?>
<section class="mx-card mx-mts">
<?php foreach ($d['tiers'] as $i => $x): ?>
  <div class="mx-mt"><div><span><?= h($x['t']) ?></span><b><?= h(sl_num($x['n'])) ?></b></div><span class="mx-trk"><i class="<?= $i === 2 ? 'or' : '' ?>" style="width:<?= (int) round($x['n'] / $tMax * 100) ?>%"></i></span></div>
<?php endforeach; ?>
</section>
<?= sl_mxSec('Rules', 'Edit', 'loyalty_rewards.php?view=web') ?>
<section class="mx-card mx-list pc-wrap">
  <div class="mx-row"><span class="mx-li"><i class="ic i-star" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($s['loyalty_points_air']) ?> point per <?= h(sl_money(1)) ?> shipped by air</b><small><?= h($s['loyalty_points_sea']) ?> by sea · <?= h(sl_num($s['loyalty_redeem_points'])) ?> points = <?= h(sl_money($s['loyalty_redeem_value'])) ?> off</small></span></div>
  <div class="mx-row"><span class="mx-li"><i class="ic i-users" aria-hidden="true"></i></span><span class="mx-rm"><b>Refer a friend</b><small>Friend gets <?= h($s['referral_friend_reward']) ?> · referrer gets <?= h(sl_num($s['referral_referrer_points'])) ?> points after the first delivered shipment</small></span></div>
</section>
<section class="mx-card mx-tgs">
  <?= sl_mxToggle('Points and tiers', 'Award points on delivered shipments', $s['loyalty_enabled'] === '1', $EP, 'loyalty_enabled', ['op' => 'toggle']) ?>
  <?= sl_mxToggle('Referral programme', 'Reward customers who bring a friend', $s['referral_enabled'] === '1', $EP, 'referral_enabled', ['op' => 'toggle']) ?>
</section>
<?php if ($d['rewards']): ?>
<?= sl_mxSec('Coupons and vouchers') ?>
<section class="mx-card mx-tgs pc-wrap">
<?php foreach ($d['rewards'] as $c): ?>
  <?= sl_mxToggle($c->code, $c->title . ' · ' . ($c->value_type === 'percent' ? (float) $c->value_amount . '%' : sl_money($c->value_amount)) . ' · used ' . (int) $c->used_count . ($c->max_uses ? ' / ' . (int) $c->max_uses : '') . ($c->ends_at ? ' · until ' . sl_date($c->ends_at) : ''), (bool) $c->active, $EP, 'active', ['op' => 'reward_toggle', 'id' => (int) $c->id]) ?>
<?php endforeach; ?>
</section>
<?php endif; ?>
<?php if ($d['refs']): ?>
<?= sl_mxSec('Top referrers') ?>
<section class="mx-card mx-list">
<?php foreach (array_slice($d['refs'], 0, 5) as $r): ?>
<a class="mx-row" href="<?= h(sl_route('CsCustomer', ['id' => $r['id']])) ?>"><span class="mx-ava"><?= h($r['ini']) ?></span><span class="mx-rm"><b><?= h($r['n']) ?></b><small><?= h($r['s']) ?></small></span><span class="mx-rr"><b><?= h($r['v']) ?></b></span></a>
<?php endforeach; ?>
</section>
<?php endif; ?>
<?php if ($d['events']): ?>
<?= sl_mxSec('Latest points activity') ?>
<section class="mx-card mx-list">
<?php foreach (array_slice($d['events'], 0, 6) as $e): ?>
<div class="mx-row"><span class="mx-li"><i class="ic i-<?= $e->points > 0 ? 'plus' : 'receipt' ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h(trim($e->fname . ' ' . $e->lname)) ?></b><small><?= h(($e->note ?: $e->kind) . ' · ' . sl_ago($e->created_at)) ?></small></span><span class="mx-rr"><span class="sl-pill <?= $e->points > 0 ? 'delivered' : 'pending' ?>"><?= $e->points > 0 ? '+' : '' ?><?= h(sl_num($e->points)) ?></span></span></div>
<?php endforeach; ?>
</section>
<?php endif; ?>
<?php endif; ?>
</main>
