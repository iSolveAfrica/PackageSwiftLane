<?php
/**
 * M-CPreAlert: a customer pre-alerts a parcel (prealert_add.php phone branch). Posts the web pop-up's fields
 * (views/sl/pages/PreAlerts.php #pa-new) to the same endpoint, ajax/sl/prealert_ajax.php op=create, via data-sl-form.
 */
$slPhone['nav'] = 'none';
$a = ((array) ($d['addresses'] ?? []))[0] ?? null;
$openN = (int) ($d['openN'] ?? 0);
$overN = (int) ($d['overdueN'] ?? 0);
$copy = $a ? trim(implode("\n", array_filter([$a['recipient'] ?? '', $a['address1'] ?? '', $a['address2'] ?? '', trim(($a['city'] ?? '') . ' ' . ($a['zip'] ?? '')), $a['note'] ?? '']))) : '';
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-cust.css')) ?>">
<?= sl_phoneTop('New pre-alert', '', ['back' => sl_route('PreAlerts')]) ?>
<form id="pa-form" data-sl-form action="ajax/sl/prealert_ajax.php" method="post" enctype="multipart/form-data">
<input type="hidden" name="op" value="create">
<main class="mx-body">
<h2 class="b2-hl">Tell us what<span>is coming.</span></h2>
<div class="mx-ban info"><i class="ic ic-sm i-info" aria-hidden="true"></i><span>Pre-alert a parcel and we match it the moment the carrier delivers it, so it ships faster and avoids customs holds.</span></div>
<?php if ($a): ?>
<button type="button" class="mx-card mx-row pc-btnrow" data-sl-copy="<?= h($copy) ?>" aria-label="Copy the warehouse address"><span class="mx-li"><i class="ic i-pin" aria-hidden="true"></i></span><span class="mx-rm"><b>Ship it to <?= h(trim(($a['city'] ?? '') ?: ($a['label'] ?? 'our warehouse'))) ?></b><small><?= h(trim(($a['address1'] ?? '') . ', ' . ($a['city'] ?? '') . ' ' . ($a['zip'] ?? ''), ', ')) ?> · <?= h($a['address2'] ?? '') ?></small></span><i class="ic ic-sm mx-ch i-copy" aria-hidden="true"></i></button>
<?php endif; ?>
<div class="mx-sec"><h2>Parcel</h2></div>
<section class="mx-card mx-form">
<label class="mx-f"><small>Carrier tracking number</small><input class="mx-in mx-inp mono" name="tracking" required autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="1Z999AA1…" data-pa-trk><small class="mx-fh">From the shop’s shipping e-mail</small></label>
<label class="mx-f"><small>Carrier</small><select class="mx-in mx-inp" name="courier" required data-pa-car><?php foreach ((array) ($d['couriers'] ?? []) as $c): ?><option value="<?= (int) $c->id ?>"><?= h($c->name_com) ?></option><?php endforeach; ?></select></label>
<label class="mx-f"><small>What is in it?</small><input class="mx-in mx-inp" name="description" required autocomplete="off" placeholder="e.g. 2 pairs of shoes"></label>
<div class="mx-two"><label class="mx-f"><small>Purchase price</small><input class="mx-in mx-inp" name="price" type="number" step="0.01" min="0.01" inputmode="decimal" required placeholder="0.00"></label><label class="mx-f"><small>Expected at warehouse</small><input class="mx-in mx-inp" name="date" type="date" required value="<?= h($d['eta'] ?? '') ?>" data-pa-eta><small class="mx-fh">Today + 5 days</small></label></div>
</section>
<div class="mx-sec"><h2>Invoice</h2></div>
<label class="b2-up pc-btnrow"><span class="mx-li"><i class="ic i-upload" aria-hidden="true"></i></span><span class="mx-rm"><b data-pa-file>Attach invoice</b><small>PDF or photo · needed for customs</small></span><span class="b2-st am">Required</span><input type="file" name="file_invoice" accept=".pdf,image/*" class="pc-vh" data-pa-fileinput></label>
<div class="mx-ban info"><i class="ic ic-sm i-info" aria-hidden="true"></i><span>When a parcel is registered with the same carrier tracking number, this pre-alert is linked automatically.</span></div>
<section class="mx-card mx-list"><a class="mx-row" href="<?= h(sl_route('PreAlerts')) ?>"><span class="mx-li"><i class="ic i-inbox" aria-hidden="true"></i></span><span class="mx-rm"><b>See all pre-alerts</b><small><?= $openN ?> open<?= $overN ? ' · ' . $overN . ' overdue' : '' ?></small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a></section>
</main>
<div class="mx-foot"><div class="b2-ft"><span><small data-pa-sum>Expected <?= h(sl_date($d['eta'] ?? date('Y-m-d'))) ?></small><b>1 parcel</b></span><button type="submit" class="b2-cta"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send pre-alert</button></div></div>
</form>
<script>
(function () {
  var f = document.getElementById('pa-form'); if (!f) return;
  var fi = f.querySelector('[data-pa-fileinput]'), fl = f.querySelector('[data-pa-file]'), car = f.querySelector('[data-pa-car]'), eta = f.querySelector('[data-pa-eta]'), sum = f.querySelector('[data-pa-sum]');
  fi.addEventListener('change', function () { fl.textContent = fi.files.length ? fi.files[0].name : 'Attach invoice'; });
  function paint() { var c = car.selectedOptions[0] ? car.selectedOptions[0].textContent : ''; var d = eta.value ? new Date(eta.value + 'T12:00:00') : null; sum.textContent = (c ? c + ' · ' : '') + (d && !isNaN(d) ? 'expected ' + d.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' }) : ''); }
  car.addEventListener('change', paint); eta.addEventListener('change', paint); paint();
  f.addEventListener('sl:done', function (e) { if (e.detail && (e.detail.success === true || e.detail.status === 'success')) setTimeout(function () { location.href = <?= json_encode(sl_route('PreAlerts')) ?>; }, 400); });
})();
</script>
