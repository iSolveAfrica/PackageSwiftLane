<?php
/**
 * ReceiptPaid - from docs/handover/artifact-source/ReceiptPaid.dc.html (plain print page).
 * Data: views/sl/data/labels.php (sl_receiptData).
 */
if (!isset($d)) { $d = []; }
$r = $d['r'];
if (!$r) { echo '<div class="sl-empty" style="padding:40px">Receipt not found or outside your scope.</div>'; return; }
$canPay = $d['canPay'] ?? false;
?>
<div class="sl-noprint" style="display:flex;gap:8px;justify-content:center;padding:12px"><button type="button" class="sl-btn" data-sl-print>Print receipt</button><?php if (!$r['paidState'] && !empty($canPay)): ?><a class="sl-btn ghost" href="<?= h($r['payUrl']) ?>">Pay now</a><?php endif; ?></div>
<div class="rx">
<div class="rx-hd"><div class="rx-logo"><span class="rx-mark"><i class="ic i-arrow" aria-hidden="true" style="background:#fff;width:20px;height:20px"></i></span><div style="text-align:left"><div class="rx-name"><?= h(explode(' ', $r['company']['name'])[0]) ?></div><div class="rx-sub"><?= h(trim(substr($r['company']['name'], strlen(explode(' ', $r['company']['name'])[0]))) ?: 'Logistics') ?></div></div></div>
<div class="rx-ct"><?= h(implode(' · ', array_filter([$r['company']['phone'], $r['company']['mail']]))) ?><br><?= h($r['company']['addr']) ?></div></div>
<div style="display:flex;justify-content:space-between;align-items:flex-end;border-top:1.5px solid #111;border-bottom:1.5px solid #111;padding:10px 0"><div><div style="font-size:10px;font-weight:700;letter-spacing:.1em">PACKAGE RECEIPT</div><div class="rx-mono" style="font-size:17px;font-weight:600"><?= h($r['track']) ?></div><div style="color:#555;font-size:10.5px"><?= h($r['date']) ?><?= $r['office'] ? ' · ' . h($r['office']) : '' ?></div></div><?= $r['bcTrack'] ?></div>
<div class="rx-stamp paid"><div><b>PAID</b><small>Thank you · paid in full</small></div><span style="width:40px;height:40px;border-radius:50%;background:#0A7C57;color:#fff;display:grid;place-items:center"><i class="ic i-check" aria-hidden="true" style="background:#fff"></i></span></div>
<div class="rx-box"><div class="rx-bh"><span>Bill to</span><span>Locker <?= h($r['bill']['locker']) ?></span></div><div class="rx-kv"><span>Name</span><b><?= h($r['bill']['name']) ?></b><span>Locker</span><b class="rx-mono"><?= h($r['bill']['locker']) ?></b><span>Location</span><b><?= h($r['bill']['loc']) ?></b><span>Phone</span><b class="rx-mono"><?= h($r['bill']['phone']) ?></b></div></div>
<div class="rx-box"><div class="rx-bh"><span>Shipment</span><span><?= $r['mode'] === 'SEA' ? 'Sea freight' : 'Air freight' ?></span></div><div class="rx-kv"><span>Courier</span><b><?= h($r['carrier'] ?: '—') ?></b><span>Category</span><b><?= h(($r['mode'] === 'SEA' ? 'Sea freight' : 'Air freight') . ($r['svc'] ? ' · ' . $r['svc'] : '')) ?></b><span>Tracking #</span><b class="rx-mono"><?= h($r['carrierNo'] ?: '—') ?></b></div><?php if ($r['bcCarrier']): ?><div style="display:flex;justify-content:center;padding:0 12px 10px"><?= $r['bcCarrier'] ?></div><?php endif; ?></div>
<div class="rx-box"><div class="rx-bh"><span>Items</span><span><?= count($r['items']) ?> item<?= count($r['items']) === 1 ? '' : 's' ?></span></div><table class="rx-tb"><thead><tr><th style="width:44px">Qty</th><th>Description</th></tr></thead><tbody><?php foreach ($r['items'] as $it): ?><tr><td class="n" style="text-align:left"><?= h($it['q']) ?></td><td><?= h($it['d']) ?></td></tr><?php endforeach; ?><?php if (!$r['items']): ?><tr><td colspan="2">No items declared</td></tr><?php endif; ?></tbody></table><div class="rx-tot"><span>Original package weight</span><span class="rx-mono"><?= h($r['weight']) ?></span></div></div>
<div class="rx-box"><div class="rx-bh"><span>Charges</span><span><?= h($core->currency ?? '') ?></span></div><div style="padding:6px 0 8px"><?php foreach ($r['charges'] as $c): ?><div class="rx-r"><span><?= h($c['l']) ?></span><span class="rx-mono"><?= h($c['v']) ?></span></div><?php endforeach; ?><div class="rx-r big"><span>Total paid</span><span class="rx-mono"><?= h($r['total']) ?></span></div><?php if ($r['ghs']): ?><div class="rx-r"><span>In cedis</span><span class="rx-mono"><?= h($r['ghs']) ?></span></div><?php endif; ?></div></div>
<?php if ($r['pay']): ?><div class="rx-box"><div class="rx-bh"><span>Payment</span><span>✓ Settled</span></div><div class="rx-kv"><span>Method</span><b><?= h($r['pay']['method']) ?></b><span>Reference</span><b class="rx-mono"><?= h($r['pay']['ref']) ?></b><span>Paid on</span><b><?= h($r['pay']['on']) ?></b><span>Received by</span><b><?= h($r['pay']['by'] ?: '—') ?></b></div></div><?php endif; ?>
<div class="rx-cut" aria-hidden="true"></div>
<div class="rx-ft">Track at <?= h($r['url']) ?> · Your pickup code comes by WhatsApp when the package is ready.<br>Keep this receipt as proof of payment.</div>
</div>
<style>@media print{.sl-noprint{display:none!important}body{background:#fff}}</style>
