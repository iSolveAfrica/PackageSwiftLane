<?php
/**
 * ReceiptPaid - from docs/handover/artifact-source/ReceiptPaid.dc.html (plain print page).
 * Data: views/sl/data/labels.php (sl_receiptData). Layout (section order, what shows) is set in
 * Receipt Studio and rendered by partials/receipt_body.php.
 */
if (!isset($d)) { $d = []; }
$r = $d['r'];
if (!$r) { echo '<div class="sl-empty" style="padding:40px">Receipt not found or outside your scope.</div>'; return; }
$canPay = $d['canPay'] ?? false;
include __DIR__ . '/partials/receipt_body.php';
