<?php
/**
 * M-CPay: pay bills (pay_checkout.php, sl_billsData() owing bills + cid). Online payment is the same flow as the web page:
 * ajax/customer/my_bills_ajax.php action=bill (package checklist) and action=checkout (server-computed amount,
 * Paystack hosted page), driven by dataJs/sl/phone/pay_checkout.js. This page never marks anything as paid.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$me = sl_phoneMe();
$slPhone['nav'] = 'none';
$bills = (array) ($d['bills'] ?? []);
$consol = array_values(array_filter($bills, fn($b) => ($b['kind'] ?? '') === 'consol'));
$orders = array_values(array_filter($bills, fn($b) => ($b['kind'] ?? '') === 'order'));
$cid = (int) ($d['cid'] ?? 0);
if (!$cid && $consol) { $cid = (int) $consol[0]['cid']; }
$online = !empty($d['gateway']) && !empty($d['consolidated']) && $consol;
if ($online) { $slPhone['scripts'][] = 'dataJs/sl/phone/pay_checkout.js'; }
?>
<?= sl_phoneTop('Pay', '', ['back' => sl_route('MyBills')], sl_phoneIcon('help', 'Get help', 'support.php')) ?>
<main class="mx-body">
<div class="b2-lock"><i class="ic ic-sm i-user" aria-hidden="true"></i>Locker <b><?= h($d['locker'] ?? '') ?></b> · <?= h($d['name'] ?? $me['name']) ?><a href="<?= h(sl_route('UserHome')) ?>"><i class="ic ic-sm i-pin" aria-hidden="true"></i>Addresses</a></div>
<?php if ($online): ?>
<div class="mx-sec"><h2>Choose what to pay</h2></div>
<?php if (count($consol) > 1): ?>
<label class="mx-f"><small>Bill</small><select class="mx-in mx-inp" id="pc-bill"><?php foreach ($consol as $b): ?><option value="<?= (int) $b['cid'] ?>"<?= (int) $b['cid'] === $cid ? ' selected' : '' ?>><?= h($b['no']) ?> · owing <?= h($b['balMoney']) ?></option><?php endforeach; ?></select></label>
<?php else: ?>
<input type="hidden" id="pc-bill" value="<?= $cid ?>">
<?php endif; ?>
<section class="mx-card b2-ck" id="pc-packages" aria-live="polite"><label class="dis"><span class="mx-rm"><b>Loading the packages on this bill…</b></span></label></section>
<?php endif; ?>
<div class="mx-sec"><h2>Pay with</h2></div>
<section class="b2-rc" role="radiogroup" aria-label="Pay with">
<button type="button" class="<?= $online ? 'on' : '' ?>" role="radio" aria-checked="<?= $online ? 'true' : 'false' ?>"<?= $online ? '' : ' disabled' ?>><span class="mx-li"><i class="ic i-phone" aria-hidden="true"></i></span><span class="mx-rm"><b>Mobile money or card</b><small><?= $online ? 'MoMo or Visa/Mastercard · via Paystack' : 'Not available right now' ?></small></span><span class="b2-rad<?= $online ? ' on' : '' ?>" aria-hidden="true"></span></button>
<button type="button" class="<?= $online ? '' : 'on' ?>" role="radio" aria-checked="<?= $online ? 'false' : 'true' ?>" disabled><span class="mx-li"><i class="ic i-warehouse" aria-hidden="true"></i></span><span class="mx-rm"><b>At the office</b><small>Cash, MoMo or card at the counter. Bring the tracking number.</small></span><span class="b2-rad<?= $online ? '' : ' on' ?>" aria-hidden="true"></span></button>
<button type="button" role="radio" aria-checked="false" disabled><span class="mx-li"><i class="ic i-dollar" aria-hidden="true"></i></span><span class="mx-rm"><b>Bank transfer</b><small>Ask your account manager for the details</small></span><span class="b2-rad" aria-hidden="true"></span></button>
</section>
<?php if ($online): ?>
<div class="mx-sec"><h2>Summary</h2></div>
<section class="mx-card b2-ml"><div id="pc-summary"></div><div class="tot"><span>Total</span><em id="pc-total">—</em></div></section>
<div class="b2-rt"><i class="ic ic-sm i-lock" aria-hidden="true"></i> Secured by Paystack · we never see your card or PIN. The amount is worked out by us from the bill.</div>
<?php endif; ?>
<?php if ($orders): ?>
<div class="mx-sec"><h2>Pay at the office</h2></div>
<section class="mx-card mx-list">
<?php foreach ($orders as $b): ?>
<div class="mx-row"><span class="mx-li"><i class="ic i-warehouse" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($b['no']) ?></b><small><?= !empty($b['due']) ? 'Due ' . h($b['due']) . ' · ' : '' ?>pay at the counter</small></span><span class="mx-rr"><b><?= h($b['balMoney']) ?></b></span></div>
<?php endforeach; ?>
</section>
<?php endif; ?>
<?php if (!$online): ?>
<?php if (!$orders): ?><section class="mx-card"><?= sl_mxEmpty('check', 'Nothing to pay', 'You have no open bills. Thank you!') ?></section><?php endif; ?>
<a class="b2-gh" href="<?= h(sl_route('MyBills')) ?>"><i class="ic ic-sm i-left" aria-hidden="true"></i>Back to my bills</a>
<div class="mx-ban info"><i class="ic ic-sm i-info" aria-hidden="true"></i><span><?= empty($d['consolidated']) || !$consol ? 'Online payment covers consolidated bills. Your open bills are paid at the office with your tracking number, or by bank transfer through your account manager.' : 'Nothing can be paid online right now. Pay at the office (cash, MoMo or card) with your tracking number, or by bank transfer through your account manager.' ?></span></div>
<?php endif; ?>
</main>
<?php if ($online): ?>
<div class="mx-foot"><div class="b2-ft"><span><small id="pc-count">Secured by Paystack</small><b id="pc-foot-total">—</b></span><button class="b2-cta" type="button" id="pc-pay" disabled><i class="ic ic-sm i-lock" aria-hidden="true"></i><span>Pay</span></button></div></div>
<div id="pc-config" data-cid="<?= $cid ?>" hidden></div>
<?php endif; ?>
