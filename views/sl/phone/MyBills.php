<?php
/** M-CBills: the customer's bills (sl_billsData() in views/sl/data/portal.php), as the design's phone screen. */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$me = sl_phoneMe();
$slPhone['tab'] = 'Bills';
$f = (string) ($d['filter'] ?? 'owing');
$canOnline = !empty($d['canPay']) && !empty($d['gateway']) && !empty($d['consolidated']);
$owingN = (int) ($d['owingN'] ?? 0);
$owingGhs = (float) ($d['owingGhs'] ?? 0);
$bills = (array) ($d['bills'] ?? []);
$bars = (array) ($d['bars'] ?? []);
$num = fn($s) => (float) preg_replace('/[^0-9.]/', '', (string) $s);
$short = function (float $v): string {
    if ($v >= 1000) return rtrim(rtrim(number_format($v / 1000, 1), '0'), '.') . 'k';
    return (string) round($v);
};
$top = $num($d['top'] ?? 0);
$firstOwing = null;
foreach ($bills as $b) { if (empty($b['settled'])) { $firstOwing = $b; break; } }
$cur = $bars ? $bars[count($bars) - 1] : null;
?>
<?= sl_phoneTop('My bills', '', ['avatar' => $me['ini'], 'href' => sl_route('MyAccount'), 'online' => true], sl_phoneIcon('search', 'Search', sl_route('LiveTracking')) . sl_phoneIcon('bell', 'Notifications', sl_route('MyNotifications'))) ?>
<main class="mx-body">
<div class="b2-lock"><i class="ic ic-sm i-user" aria-hidden="true"></i>Locker <b><?= h($d['locker'] ?? '') ?></b> · <?= h($d['name'] ?? $me['name']) ?><a href="<?= h(sl_route('UserHome')) ?>"><i class="ic ic-sm i-pin" aria-hidden="true"></i>Addresses</a></div>
<h2 class="b2-hl">My bills<span><?= $owingN > 0 ? h($owingN . ' to pay.') : 'All paid.' ?></span></h2>
<section class="b2-ov">
<div class="b2-ot"><div class="b2-oh"><span class="b2-oi"><i class="ic i-receipt" aria-hidden="true"></i></span><span class="b2-dd">Now</span></div><b><?= h($d['owing'] ?? '') ?></b><small>Owing now</small><div class="b2-of"><em><?php if ($owingN > 0): ?><i class="dn">↓ <?= $owingN ?> bill<?= $owingN === 1 ? '' : 's' ?></i> <?= !empty($d['dueDate']) ? 'next due ' . h($d['dueDate']) : 'to pay' ?><?php else: ?>Nothing overdue<?php endif; ?></em><a href="<?= h(sl_route('MyBills', ['filter' => 'owing'])) ?>" aria-label="Open Owing now"><i class="ic ic-sm i-right" aria-hidden="true"></i></a></div></div>
<div class="b2-ot"><div class="b2-oh"><span class="b2-oi"><i class="ic i-check" aria-hidden="true"></i></span><span class="b2-dd">This year</span></div><b><?= h($d['paidY'] ?? '') ?></b><small>Paid this year</small><div class="b2-of"><em><i class=""><?= (int) ($d['paidPct'] ?? 0) ?>%</i> of <?= h($d['billedY'] ?? '') ?></em><a href="<?= h(sl_route('MyBills', ['filter' => 'settled'])) ?>" aria-label="Open Paid this year"><i class="ic ic-sm i-right" aria-hidden="true"></i></a></div></div>
</section>
<?php if ($owingN > 0): ?>
<section class="b2-hd"><div class="b2-hdt"><span class="mx-rm"><small><?= $canOnline ? 'Choose packages to pay' : 'Owing now' ?></small><b><?= h($canOnline && $owingGhs > 0 ? 'GHS ' . number_format($owingGhs, 2) : ($d['owing'] ?? '')) ?></b></span><span class="b2-st am">Owing</span></div>
<?php if ($firstOwing): ?><div class="b2-rt"><?= h($firstOwing['no']) ?> is waiting for payment<?= !empty($firstOwing['due']) ? ' · due ' . h($firstOwing['due']) : '' ?>.</div><?php endif; ?>
<?php if ($canOnline && $owingGhs > 0): ?><a class="b2-cta" href="<?= h(sl_route('PayCheckout')) ?>"><i class="ic ic-sm i-card" aria-hidden="true"></i>Pay GHS <?= h(number_format($owingGhs, 2)) ?></a>
<?php else: ?><a class="b2-cta" href="support.php"><i class="ic ic-sm i-help" aria-hidden="true"></i>How to pay</a><?php endif; ?>
</section>
<?php endif; ?>
<?php if ($bars): ?>
<section class="b2-wk"><div class="b2-wkh"><span><b>Last 12 months</b><small>Average month <b><?= h($d['avg'] ?? '') ?></b></small></span></div>
<div class="b2-wkb"><div class="b2-ya"><?php foreach ([1, .75, .5, .25, 0] as $k): ?><span><?= h($short($top * $k)) ?></span><?php endforeach; ?></div>
<div class="b2-bs" role="img" aria-label="Spending per month over the last 12 months"><?php foreach ($bars as $b): $pct = max(0, min(100, round((float) $b['h'] / 146 * 100))); ?><span class="<?= !empty($b['now']) ? 'cur' : '' ?>"><i class="<?= !empty($b['now']) && $owingN > 0 ? 'ow' : '' ?>" style="height:<?= $pct ?>%" title="<?= h($b['l'] . ': ' . $b['v']) ?>"></i><small><?= h(mb_substr((string) $b['l'], 0, 1)) ?></small></span><?php endforeach; ?>
<?php if ($cur): ?><div class="b2-tip" style="right:0;top:0"><span><?= h(date('F Y')) ?></span><b><?= h($cur['v']) ?> billed</b><?php if ($owingN > 0): ?><em class="dn"><?= h(($d['owing'] ?? '') . ' owing') ?></em><?php endif; ?></div><?php endif; ?>
</div></div>
<div class="b2-lg"><span><i class=""></i>Billed</span><span><i class="ow"></i>Owing</span><span><i class="cur"></i>This month</span></div></section>
<?php endif; ?>
<form class="mx-seg" method="get" action="<?= h(sl_route('MyBills')) ?>"><?php foreach (['all' => 'All', 'owing' => 'Owing', 'settled' => 'Settled'] as $k => $l): ?><button type="submit" name="filter" value="<?= h($k) ?>" aria-pressed="<?= $f === $k ? 'true' : 'false' ?>" class="<?= $f === $k ? 'on' : '' ?>"><?= h($l) ?><?= $k === 'owing' && $owingN ? ' ' . $owingN : ($k === 'settled' && (int) ($d['settledN'] ?? 0) ? ' ' . (int) $d['settledN'] : '') ?></button><?php endforeach; ?></form>
<?php if ($bills): ?>
<section class="mx-card mx-list">
<?php foreach ($bills as $b):
    $detail = $b['kind'] === 'order' ? sl_route('ShipmentDetail', ['id' => $b['oid']]) : '';
    $payHref = $b['kind'] === 'consol' && $canOnline ? sl_route('PayCheckout', ['cid' => $b['cid']]) : '';
?>
<div class="mx-row"><span class="mx-li"><i class="ic i-receipt" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($b['no']) ?></b><small><?= h($b['sub']) ?><?= empty($b['settled']) && !empty($b['due']) ? ' · due ' . h($b['due']) : '' ?></small></span><span class="mx-rr"><b><?= h($b['money']) ?></b><span style="display:flex;gap:6px"><?php if ($detail !== ''): ?><a class="b2-st" href="<?= h($detail) ?>">Details</a><?php endif; ?><?php if (!empty($b['settled'])): ?><span class="b2-st ok">Paid</span><?php elseif ($payHref !== ''): ?><a class="b2-st am" href="<?= h($payHref) ?>">Pay now</a><?php else: ?><span class="b2-st am">Owing</span><?php endif; ?></span></span></div>
<?php endforeach; ?>
</section>
<?php else: ?>
<section class="mx-card"><?= sl_mxEmpty('receipt', $f === 'settled' ? 'No settled bills yet' : ($f === 'owing' ? 'You owe nothing' : 'No bills yet'), $f === 'owing' ? 'Thank you! New bills show up here as soon as a parcel is billed.' : '') ?></section>
<?php endif; ?>
<a class="mx-card mx-row" href="support.php"><span class="mx-li"><i class="ic i-help" aria-hidden="true"></i></span><span class="mx-rm"><b>Question a charge</b><small>Wrong weight or price? Ask us</small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<a class="mx-card mx-row" href="<?= h(sl_route('QuoteCalculator')) ?>"><span class="mx-li"><i class="ic i-tag" aria-hidden="true"></i></span><span class="mx-rm"><b>Check prices before you ship</b><small>Air, sea and express by weight</small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<a class="mx-card mx-row" href="<?= h(sl_route('MyBills', ['filter' => 'settled'])) ?>"><span class="mx-li"><i class="ic i-activity" aria-hidden="true"></i></span><span class="mx-rm"><b>Payment history</b><small>Bills you have settled</small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<div class="mx-ban info"><i class="ic ic-sm i-info" aria-hidden="true"></i><span><?php if ($canOnline): ?>How to pay: online with Mobile Money or card, or at the office with your tracking number. Parcels are released once the bill is paid.<?php elseif (empty($d['consolidated'])): ?>Online payment is available for consolidated bills once billing is set up. Until then, pay at the office or with your account manager.<?php elseif (empty($d['gateway'])): ?>Online payment is switched off right now. Please pay at the office.<?php else: ?>Your account cannot pay online. Ask us to enable it.<?php endif; ?></span></div>
</main>
