<?php
/**
 * The signed-in customer's own loyalty balance (for UserHome / ClientDashboard).
 * Include from a customer page:  include CDP_ROOT . '/views/sl/pages/_loyalty_card.php';
 * Only ever reads $_SESSION['userid'].
 */
require_once CDP_ROOT . '/helpers/loyalty.php';
$slLoy = sl_userIs('client') && sl_loyaltyEnabled() ? sl_loyaltyForCustomer((int) ($_SESSION['userid'] ?? 0)) : ['enabled' => false];
if (empty($slLoy['enabled'])) {
    return;
}
?>
<section class="sl-card col g10" id="rewards"><div class="sl-ch" style="margin:0"><h2 class="sl-ct row g8"><i class="ic ic-sm i-star" aria-hidden="true"></i>My rewards</h2><span class="sl-badge"><?= h($slLoy['tier']) ?></span></div>
<div class="row g16 wrap"><div class="col"><b style="font:600 28px var(--font)"><?= sl_num($slLoy['balance']) ?></b><span class="mut sm">points · worth <?= h($slLoy['worth']) ?></span></div><?php if ($slLoy['next_tier']): ?><div class="col grow" style="min-width:180px"><span class="sm"><?= sl_num(max(0, $slLoy['next_tier'][1] - $slLoy['lifetime'])) ?> points to <?= h($slLoy['next_tier'][0]) ?></span><div class="sl-ticks"><i style="width:<?= sl_pct($slLoy['lifetime'], $slLoy['next_tier'][1]) ?>%"></i></div></div><?php endif; ?></div>
<div class="hb-out" style="padding:10px 12px;border-radius:12px;background:var(--field);border:1px solid var(--line)">Your referral code: <b class="mono"><?= h($slLoy['code']) ?></b> · <?= (int) $slLoy['referrals'] ?> friend<?= $slLoy['referrals'] === 1 ? '' : 's' ?> joined <button type="button" class="sl-btn ghost sm" data-sl-copy="<?= h($slLoy['code']) ?>">Copy</button></div>
<?php foreach (array_slice($slLoy['events'], 0, 5) as $e): ?><div class="row between sm" style="border-top:1px solid var(--line);padding-top:6px"><span><?= h($e->note ?: $e->kind) ?> <span class="mut">· <?= h(sl_ago($e->created_at)) ?></span></span><b class="mono"><?= $e->points > 0 ? '+' : '' ?><?= sl_num($e->points) ?></b></div><?php endforeach; ?></section>
