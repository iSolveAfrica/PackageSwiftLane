<?php
/**
 * FinancialSheet - the consolidation Financial Sheet in the Swift Lane design (BillingHub look).
 * Controller: financial_sheet_consolidation.php. Script: dataJs/sl/financial_sheet.js.
 * Reads: ajax/reports/financial_sheet_ajax.php sl_list, list_stats, sl_find, sl_sheet, sl_items, payment_history, payment_events.
 * Writes (unchanged actions and payloads): save_item, save_group, clear_group, bill_preview, bill_customer, payment_form,
 * gateway_init, record_payment, clear_debt, apply_discount, remove_discount, clear_package, set_weight_rate, check_payment,
 * check_intent, lock/unlock. Money rules stay server-side (helpers/autoload_lang.php, helpers/fs_*.php).
 */
if (!isset($d)) { $d = []; }
?>
<div class="row g8 mut sm"><a href="<?= sl_route('BillingHub') ?>">Billing and finance</a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)">Financial sheet</span></div>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:20px">Financial sheet</h1><p class="sl-lede">Price package items, bill each customer, take payments and discounts, and clear packages for delivery.</p></div>
<div class="row g8 wrap"><div class="sl-seg boxed" id="fs-cur" role="group" aria-label="Display currency"><button type="button" data-cur="ghs" class="on">GH₵</button><button type="button" data-cur="usd">$</button></div><?php if ($d['canRate']): ?><button type="button" class="sl-btn ghost" id="fs-rate-btn"><i class="ic ic-sm i-sliders" aria-hidden="true"></i>Weight rate <span class="mono" id="fs-wr"><?= h($d['symbol'] . $d['weightRate']) ?>/<?= h($d['unit']) ?></span></button><?php endif; ?><a class="sl-btn ghost" href="<?= sl_route('FinancialOverview') ?>"><i class="ic ic-sm i-chart" aria-hidden="true"></i>Overview</a></div></div>
<div class="sl-banner info"><i class="ic ic-sm i-info" aria-hidden="true"></i><span>Rate: $1 = ₵<span class="mono" id="fs-rate"><?= h(rtrim(rtrim(number_format((float) $d['rate'], 4, '.', ''), '0'), '.')) ?></span><span id="fs-ratelog"></span>. Prices are stored in USD; bills, payments and discounts in GHS at the rate of the day.</span></div>
<div class="fs-ws">
<aside class="col g12">
<section class="sl-card col g12"><h2 class="sl-ct">Find</h2>
<div class="sl-seg boxed" id="fs-by"><button type="button" class="on" data-by="consol">Consolidation</button><button type="button" data-by="package">Package</button><button type="button" data-by="customer">Customer</button></div>
<div class="sl-search" style="max-width:none"><i class="ic ic-sm i-search" aria-hidden="true"></i><input id="fs-q" aria-label="Search" placeholder="Consolidation #" autocomplete="off"></div>
<div class="fs-list" id="fs-consols"><div class="sl-empty mut sm">Loading…</div></div></section>
<section class="sl-card col g12" id="fs-custs-card" hidden><div class="row between"><h2 class="sl-ct">Customers</h2><span class="mut sm" id="fs-custs-n"></span></div><div class="fs-list" id="fs-custs"></div></section>
</aside>
<main class="col g16" id="fs-main">
<section class="sl-card"><div class="sl-empty mut" style="padding:32px 12px;text-align:center"><i class="ic i-layers" aria-hidden="true"></i><p>Pick a consolidation to start pricing and billing.</p></div></section>
</main>
</div>
<div class="sl-dialog" id="fs-dlg" hidden role="dialog" aria-modal="true" aria-labelledby="fs-dlg-t"><div class="sl-card sl-modal" style="width:min(680px,100%)"><div class="row between"><h2 class="sl-ct" id="fs-dlg-t"></h2><button type="button" class="sl-btn ghost sm" data-sl-close aria-label="Close"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div><div class="col g12" id="fs-dlg-b"></div><div class="fs-err" id="fs-dlg-e" hidden></div><div class="row g8" style="justify-content:flex-end"><button type="button" class="sl-btn ghost" data-sl-close id="fs-dlg-c">Cancel</button><button type="button" class="sl-btn" id="fs-dlg-ok">OK</button></div></div></div>
<script>window.FS = <?= json_encode(['cid' => $d['cid'], 'sid' => $d['sid'], 'rate' => (float) $d['rate'], 'weightRate' => $d['weightRate'], 'unit' => $d['unit'], 'symbol' => $d['symbol']]) ?>;</script>
