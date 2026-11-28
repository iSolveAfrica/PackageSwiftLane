<?php
/**
 * M-RecordPay: record a payment taken outside the website. Same $d as the web page
 * (views/sl/data/finance.php sl_recordPaymentData), same endpoint and field names
 * (ajax/sl/record_payment_ajax.php): type, customer_id, orders[], amount, currency, method, reference,
 * paid_at, note, notify, release.
 */
$cur = $d['customer'];
$slPhone['nav'] = 'staff';
$sym = (string) ($core->for_symbol ?? ($core->currency ?? '$'));
$pay = fn(float $n) => $sym . number_format($n, 2);
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-staff.css')) ?>">
<?= sl_phoneTop('Record payment', $cur ? $d['typeLabel'] . ' invoices' : 'Cash, bank or mobile money', ['back' => sl_route('Payments')]) ?>
<?php if (!$cur): ?>
<main class="mx-body">
<form method="get" class="mx-card mx-form">
  <label class="mx-f"><small>Invoice type</small><select class="mx-in" name="type" onchange="this.form.submit()"><?php foreach ($d['types'] as $t): ?><option value="<?= h($t['k']) ?>"<?= $t['k'] === $d['type'] ? ' selected' : '' ?>><?= h($t['label']) ?></option><?php endforeach; ?></select></label>
  <label class="mx-f"><small>Customer</small><select class="mx-in" name="customer" onchange="if(this.value)this.form.submit()"><option value="">Pick a customer with an unpaid invoice</option><?php foreach ($d['customers'] as $c): ?><option value="<?= (int) $c->id ?>"><?= h(trim($c->fname . ' ' . $c->lname) . ($c->locker ? ' · ' . $c->locker : '')) ?></option><?php endforeach; ?></select></label>
</form>
<?php if (!$d['customers']): ?><section class="mx-card"><?= sl_mxEmpty('check', 'No unpaid invoices', 'No customer owes anything for this invoice type.') ?></section><?php endif; ?>
</main>
<?php else: ?>
<form data-sl-form action="ajax/sl/record_payment_ajax.php" method="post" id="rp-form" class="sp-form">
<main class="mx-body">
<input type="hidden" name="type" value="<?= h($d['type']) ?>"><input type="hidden" name="customer_id" value="<?= (int) $cur->id ?>">
<section class="mx-card mx-form">
  <div class="mx-f"><small>Customer</small><a class="mx-in sel" href="<?= h(sl_url(['customer' => null, 'id_order' => null, 'id' => null])) ?>" aria-label="Customer: <?= h($d['customerLabel']) ?>. Change"><span><?= h($d['customerLabel']) ?></span><i class="ic ic-sm i-down" aria-hidden="true"></i></a></div>
  <div class="mx-f"><small>For</small>
<?php foreach ($d['invoices'] as $i): ?>
    <label class="mx-in sp-inv"><input type="checkbox" name="orders[]" value="<?= (int) $i['id'] ?>" data-amt="<?= h($i['amt']) ?>"<?= $i['on'] ? ' checked' : '' ?>><span class="sp-invm"><b class="mono"><?= h($i['track']) ?></b><small><?= h($i['due']) ?></small></span><b><?= h($i['amtF']) ?></b></label>
<?php endforeach; ?>
<?php if (!$d['invoices']): ?>
    <?= sl_mxEmpty('receipt', 'No unpaid ' . strtolower($d['typeLabel']) . ' invoices', 'This customer owes nothing for this invoice type.', 'Pick another customer', sl_url(['customer' => null, 'id_order' => null, 'id' => null])) ?>
<?php endif; ?>
  </div>
  <label class="mx-f"><small>Amount</small><input class="mx-in" name="amount" id="rp-amount" inputmode="decimal" autocomplete="off" value="<?= number_format($d['selectedTotal'], 2, '.', '') ?>" required></label>
<?php if ($d['rate'] > 0 && $d['currency'] !== 'GHS'): ?>
  <label class="mx-f"><small>Currency</small><select class="mx-in" name="currency" id="rp-currency"><option value="<?= h($d['currency']) ?>"><?= h($d['currency']) ?></option><option value="GHS">GHS (÷ <?= number_format($d['rate'], 2) ?>)</option></select></label>
<?php else: ?>
  <input type="hidden" name="currency" id="rp-currency" value="<?= h($d['currency']) ?>">
<?php endif; ?>
  <div class="mx-f"><small id="rp-ml">Method</small><div class="mx-fseg" data-mx-pick="#rp-method" role="group" aria-labelledby="rp-ml"><?php foreach ($d['methods'] as $k => $m): ?><button type="button" class="<?= $k === 0 ? 'on' : '' ?>" aria-pressed="<?= $k === 0 ? 'true' : 'false' ?>" data-value="<?= (int) $m->id ?>"><?= h($m->label) ?></button><?php endforeach; ?></div><input type="hidden" name="method" id="rp-method" value="<?= $d['methods'] ? (int) $d['methods'][0]->id : 0 ?>"></div>
  <label class="mx-f"><small>Reference</small><input class="mx-in" name="reference" maxlength="190" placeholder="optional" autocomplete="off"></label>
  <label class="mx-f"><small>Date</small><input class="mx-in" type="date" name="paid_at" value="<?= h($d['today']) ?>" max="<?= h($d['today']) ?>"></label>
  <label class="mx-f"><small>Note</small><input class="mx-in" name="note" maxlength="190" placeholder="optional"></label>
</section>
<section class="mx-card mx-tgs">
  <div class="mx-tg"><span class="mx-rm"><b>Send receipt on WhatsApp / e-mail</b><?php if (!$d['hasWhatsapp']): ?><small>No phone number on file</small><?php endif; ?></span><button type="button" class="mx-sw<?= $d['hasWhatsapp'] ? ' on' : '' ?>" role="switch" aria-checked="<?= $d['hasWhatsapp'] ? 'true' : 'false' ?>" aria-label="Send receipt on WhatsApp or e-mail" data-input="#rp-notify"></button><input type="hidden" name="notify" id="rp-notify" value="<?= $d['hasWhatsapp'] ? '1' : '0' ?>"></div>
<?php if ($d['held']): ?>
  <div class="mx-tg"><span class="mx-rm"><b>Release held parcels</b><small><?= (int) $d['held'] ?> parcel<?= $d['held'] > 1 ? 's' : '' ?> on hold for this customer</small></span><button type="button" class="mx-sw on" role="switch" aria-checked="true" aria-label="Release held parcels" data-input="#rp-release"></button><input type="hidden" name="release" id="rp-release" value="1"></div>
<?php endif; ?>
</section>
<p class="mx-note" id="rp-left" aria-live="polite"></p>
</main>
<div class="mx-foot sp-foot"><div class="mx-btns"><button type="submit" class="mx-btn" id="rp-save">Record <?= h($pay((float) $d['selectedTotal'])) ?></button></div></div>
</form>
<script>
(function(){var f=document.getElementById('rp-form');if(!f)return;var sym=<?= json_encode($sym) ?>,rate=<?= (float) $d['rate'] ?>,amt=document.getElementById('rp-amount'),curEl=document.getElementById('rp-currency'),base=<?= json_encode((string) $d['currency']) ?>;
function fmt(n){return n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');}
function total(){var t=0;f.querySelectorAll('input[name="orders[]"]:checked').forEach(function(c){t+=parseFloat(c.dataset.amt)||0;});return t;}
function show(){var t=total(),paid=parseFloat(amt.value)||0,ghs=curEl.value==='GHS'&&base!=='GHS';document.getElementById('rp-save').textContent='Record '+(ghs?'GHS ':sym)+fmt(paid);
var inBase=ghs&&rate>0?paid/rate:paid,left=Math.max(0,t-inBase);document.getElementById('rp-left').textContent=t>0&&left>0.004?sym+fmt(left)+' of '+sym+fmt(t)+' stays unpaid after this payment.':'';}
f.addEventListener('change',function(e){if(e.target.name==='orders[]')amt.value=total().toFixed(2);show();});f.addEventListener('input',show);show();})();
</script>
<?php endif; ?>
