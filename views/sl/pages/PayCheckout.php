<?php
/**
 * PayCheckout - from docs/handover/artifact-source/PayCheckout.dc.html (scripts/sl_convert.py), bound to pay_checkout.php.
 * Shell page: yes. Payment goes through ajax/customer/my_bills_ajax.php (server computes the amount) and Paystack;
 * dataJs/sl/pay_checkout.js loads the package checklist of the chosen bill and hands off to the hosted checkout.
 * Data contract ($d): bills[] (owing), cid, gateway, consolidated, canPay
 */
if (!isset($d)) { $d = []; }
$consol = array_values(array_filter((array) sl_get($d, 'bills', []), fn($b) => $b['kind'] === 'consol'));
$orders = array_values(array_filter((array) sl_get($d, 'bills', []), fn($b) => $b['kind'] === 'order'));
$cid = (int) sl_get($d, 'cid');
if (!$cid && $consol) { $cid = (int) $consol[0]['cid']; }
$online = sl_get($d, 'gateway') && sl_get($d, 'consolidated') && $consol;
?>
<div class="row g8 mut sm"><a href="<?= sl_route('MyBills') ?>">My bills</a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)">Pay</span></div>
<div class="sl-head"><div><h1 class="sl-title">Pay your bills</h1><p class="sl-lede">Paid parcels are released for pickup once the payment is confirmed</p></div></div>
<div class="split"><div class="a col g16"><section class="sl-card col g16"><div class="sl-ch" style="margin:0"><h2 class="sl-ct">Pay with</h2></div>
<div class="grid cols3" style="gap:12px"><div class="np-opt <?= $online ? 'on' : '' ?>"><span class="sl-radio <?= $online ? 'on' : '' ?>"></span><span class="col"><b class="fw5">Mobile money or card</b><span class="mut sm"><?= $online ? 'Secure Paystack page' : 'Not available right now' ?></span></span></div><div class="np-opt <?= $online ? '' : 'on' ?>"><span class="sl-radio <?= $online ? '' : 'on' ?>"></span><span class="col"><b class="fw5">At the office</b><span class="mut sm">Cash, MoMo or card at the counter</span></span></div><div class="np-opt"><span class="sl-radio"></span><span class="col"><b class="fw5">Bank transfer</b><span class="mut sm">Ask your account manager</span></span></div></div>
<?php if ($online): ?>
<label class="sl-field"><span class="sl-lab">Bill</span><select class="sl-in" id="pc-bill"><?php foreach ($consol as $b): ?><option value="<?= (int) $b['cid'] ?>" <?= (int) $b['cid'] === $cid ? 'selected' : '' ?>><?= h($b['no']) ?> · owing <?= h($b['balMoney']) ?></option><?php endforeach; ?></select></label>
<div class="col g8" id="pc-packages" aria-live="polite"><span class="mut sm">Loading the packages on this bill…</span></div>
<div class="mut sm">You choose which packages to pay for. The amount is worked out by us from the bill, never from this page. On the next page you approve the payment with your PIN or card.</div>
<?php else: ?>
<div class="sl-banner info"><i class="ic ic-sm i-info" aria-hidden="true"></i><span><?= !sl_get($d, 'consolidated') || !$consol ? 'Online payment covers consolidated bills. Your open bills below are paid at the office.' : 'Online payment is switched off right now. Please pay at the office.' ?></span></div>
<?php endif; ?>
</section>
<?php if ($orders): ?><section class="sl-card col g8"><h2 class="sl-ct">Pay at the office</h2><div class="sl-kv"><?php foreach ($orders as $b): ?><div><span><?= h($b['no']) ?><?= !empty($b['due']) ? ' · due ' . h($b['due']) : '' ?></span><span><?= h($b['balMoney']) ?></span></div><?php endforeach; ?></div><span class="mut sm">Bring your tracking number. Staff record the payment and release the parcel.</span></section><?php endif; ?>
</div>
<aside class="b col g16"><section class="sl-card col g16"><div class="sl-ch" style="margin:0"><h2 class="sl-ct">Summary</h2></div>
<?php if ($online): ?>
<div class="sl-kv" id="pc-summary"></div><div class="row between"><b class="fw5">Total</b><b class="mono" style="font-size:22px" id="pc-total">—</b></div>
<div class="sl-mobile-sticky"><button class="sl-btn block" type="button" id="pc-pay" style="height:48px" disabled><i class="ic ic-sm i-lock" aria-hidden="true"></i><span>Pay</span></button><span class="mut sm" style="text-align:center">Secured by Paystack</span></div>
<?php else: ?>
<div class="mut sm">Nothing can be paid online on this page.</div><a class="sl-btn ghost block" href="<?= sl_route('MyBills') ?>">Back to my bills</a>
<?php endif; ?>
</section></aside></div>
<?php if ($online): ?><div id="pc-config" data-cid="<?= $cid ?>" hidden></div><?php endif; ?>
