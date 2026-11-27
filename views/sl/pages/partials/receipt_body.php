<?php
/**
 * Receipt body shared by ReceiptPaid and ReceiptNotPaid. Sections print in the order set in
 * Receipt Studio (receipt_studio.php, cdb_sl_settings receipt_options); hidden ones are skipped.
 * Expects $r (sl_receiptData) and $canPay; $d['ro'] may carry unsaved options for the studio preview.
 */
$ro = $d['ro'] ?? sl_receiptOptions();
$f = $ro['fields'];
$paid = !empty($r['paidState']);
$embed = !empty($_GET['embed']);
$mode = $r['mode'] === 'SEA' ? 'Sea freight' : 'Air freight';
?>
<?php if (!$embed): ?><div class="sl-noprint" style="display:flex;gap:8px;justify-content:center;padding:12px"><button type="button" class="sl-btn" data-sl-print>Print receipt</button><?php if (!$paid && !empty($canPay)): ?><a class="sl-btn ghost" href="<?= h($r['payUrl']) ?>">Pay now</a><?php endif; ?><?php if (!empty($d['canStudio'])): ?><a class="sl-btn ghost" href="<?= h(sl_route('ReceiptStudio', ['id' => $r['id'], 'type' => $r['type']])) ?>">Receipt layout</a><?php endif; ?></div><?php endif; ?>
<div class="rx">
<?php foreach ($ro['order'] as $blk):
    if (!empty($ro['hide'][$blk])) continue;
    switch ($blk):
    case 'logo': ?>
<div class="rx-hd rx-brand"><?= sl_brandLogo(($ro['logo'] ?? 'color') === 'black' ? 'black' : 'color', 58, 'rx-brand-logo') ?></div>
<?php break; case 'contact': ?>
<div class="rx-ct" style="text-align:center"><b style="color:#111"><?= h($r['company']['name']) ?></b><br><?= h(implode(' · ', array_filter([$r['company']['phone'], $r['company']['mail']]))) ?><?php if ($r['company']['addr'] !== ''): ?><br><?= h($r['company']['addr']) ?><?php endif; ?></div>
<?php break; case 'barcode': ?>
<div class="rx-id"><div class="row between" style="align-items:flex-end;gap:8px"><div><div style="font-size:10px;font-weight:700;letter-spacing:.1em"><?= h($ro['title'] !== '' ? mb_strtoupper($ro['title']) : 'PACKAGE RECEIPT') ?></div><div class="rx-mono" style="font-size:17px;font-weight:600"><?= h($r['track']) ?></div></div><div style="color:#555;font-size:10.5px;text-align:right"><?= h($r['date']) ?><?= $r['office'] ? '<br>' . h($r['office']) : '' ?></div></div><div class="rx-bcw"><?= $r['bcTrack'] ?></div></div>
<?php break; case 'stamp': ?>
<?php if ($paid): ?><div class="rx-stamp paid"><div><b>PAID</b><small>Thank you · paid in full</small></div><span style="width:40px;height:40px;border-radius:50%;background:#0A7C57;color:#fff;display:grid;place-items:center"><i class="ic i-check" aria-hidden="true" style="background:#fff"></i></span></div>
<?php else: ?><div class="rx-stamp due"><div><b>NOT PAID</b><small>Pay before pickup<?= $r['due'] ? ' · due ' . h($r['due']) : '' ?></small></div><span style="width:40px;height:40px;border-radius:50%;background:#B3262F;color:#fff;display:grid;place-items:center"><i class="ic i-alert" aria-hidden="true" style="background:#fff"></i></span></div><?php endif; ?>
<?php break; case 'bill': ?>
<div class="rx-box"><div class="rx-bh"><span>Bill to</span><span>Locker <?= h($r['bill']['locker']) ?></span></div><div class="rx-kv"><span>Name</span><b><?= h($r['bill']['name']) ?></b><span>Locker</span><b class="rx-mono"><?= h($r['bill']['locker']) ?></b><?php if ($f['billLoc']): ?><span>Location</span><b><?= h($r['bill']['loc']) ?></b><?php endif; ?><?php if ($f['billPhone']): ?><span>Phone</span><b class="rx-mono"><?= h($r['bill']['phone']) ?></b><?php endif; ?></div></div>
<?php break; case 'shipment': ?>
<div class="rx-box"><div class="rx-bh"><span>Shipment</span><span><?= $mode ?></span></div><div class="rx-kv"><span>Courier</span><b><?= h($r['carrier'] ?: '—') ?></b><span>Category</span><b><?= h($mode . ($r['svc'] ? ' · ' . $r['svc'] : '')) ?></b><?php if ($f['carrierNo']): ?><span>Tracking #</span><b class="rx-mono"><?= h($r['carrierNo'] ?: '—') ?></b><?php endif; ?><?php if (!empty($r['serial'])): ?><span>Consolidation</span><b class="rx-mono"><?= h($r['serial']) ?></b><?php endif; ?><?php if (!empty($r['fsPay'])): ?><span>Paid (Financial Sheet)</span><b><?= h($r['fsPay']['amount'] . ' · ' . $r['fsPay']['mode'] . ($r['fsPay']['ref'] !== '' ? ' · ' . $r['fsPay']['ref'] : '') . ($r['fsPay']['on'] ? ' · ' . $r['fsPay']['on'] : '')) ?></b><?php endif; ?></div><?php if ($f['carrierBc'] && $r['bcCarrier']): ?><div style="display:flex;justify-content:center;padding:0 12px 10px"><?= $r['bcCarrier'] ?></div><?php endif; ?></div>
<?php break; case 'items': ?>
<div class="rx-box"><div class="rx-bh"><span>Items</span><span><?= count($r['items']) ?> item<?= count($r['items']) === 1 ? '' : 's' ?></span></div><table class="rx-tb"><thead><tr><th style="width:44px">Qty</th><th>Description</th></tr></thead><tbody><?php foreach ($r['items'] as $it): ?><tr><td class="n" style="text-align:left"><?= h($it['q']) ?></td><td><?= h($it['d']) ?></td></tr><?php endforeach; ?><?php if (!$r['items']): ?><tr><td colspan="2">No items declared</td></tr><?php endif; ?></tbody></table><?php if ($f['weight']): ?><div class="rx-tot"><span>Original package weight</span><span class="rx-mono"><?= h($r['weight']) ?></span></div><?php endif; ?></div>
<?php break; case 'charges': ?>
<div class="rx-box"><div class="rx-bh"><span>Charges</span><span><?= h($core->currency ?? '') ?></span></div><div style="padding:6px 0 8px"><?php if ($f['breakdown']): foreach ($r['charges'] as $c): ?><div class="rx-r"><span><?= h($c['l']) ?></span><span class="rx-mono"><?= h($c['v']) ?></span></div><?php endforeach; endif; ?><div class="rx-r big"<?= $f['breakdown'] ? '' : ' style="border-top:0;margin-top:0"' ?>><span><?= $paid ? 'Total paid' : 'Total due' ?></span><span class="rx-mono"><?= h($r['total']) ?></span></div><?php if ($f['cedis'] && $r['ghs']): ?><div class="rx-r"><span>In cedis</span><span class="rx-mono"><?= h($r['ghs']) ?></span></div><?php endif; ?></div></div>
<?php break; case 'payment': if (!$paid || !$r['pay']) break; ?>
<div class="rx-box"><div class="rx-bh"><span>Payment</span><span>✓ Settled</span></div><div class="rx-kv"><span>Method</span><b><?= h($r['pay']['method']) ?></b><?php if ($f['payRef']): ?><span>Reference</span><b class="rx-mono"><?= h($r['pay']['ref']) ?></b><?php endif; ?><span>Paid on</span><b><?= h($r['pay']['on']) ?></b><?php if ($f['payBy']): ?><span>Received by</span><b><?= h($r['pay']['by'] ?: '—') ?></b><?php endif; ?></div></div>
<?php break; case 'howtopay': if ($paid) break; ?>
<div class="rx-box" style="border-color:#B3262F"><div class="rx-bh" style="background:#B3262F"><span>How to pay</span><span><?= $r['due'] ? 'Due ' . h($r['due']) : 'Before pickup' ?></span></div><div class="rx-pay"><div style="display:flex;flex-direction:column;gap:4px"><b style="font-size:13px">Pay <?= h($r['total']) ?><?= $r['ghs'] ? ' (' . h($r['ghs']) . ')' : '' ?></b><span>At the counter: cash, card or mobile money</span><span>Online: <b><?= h($r['url']) ?></b></span></div></div></div>
<?php break; case 'qr': ?>
<div class="rx-qr"><?= $r['qr'] ?><div><b>Scan to track</b><span>Point your phone camera here to see where your package is.</span><span class="rx-mono"><?= h($r['track']) ?></span></div></div>
<?php break; case 'footer': ?>
<div class="rx-cut" aria-hidden="true"></div>
<div class="rx-ft"><?php if ($f['trackLink']): ?>Track at <?= h($r['url']) ?> · <?php endif; ?>Your pickup code comes by WhatsApp when the package is ready.<br><?= h($ro['note'] !== '' ? $ro['note'] : ($paid ? 'Keep this receipt as proof of payment.' : 'This package will be released once payment is received.')) ?></div>
<?php break; endswitch; endforeach; ?>
</div>
<style>
@media print{.sl-noprint{display:none!important}body{background:#fff}}
.rx-brand{padding-bottom:2px}.rx-brand .rx-brand-logo{height:58px;width:auto}
.rx-id{border-top:1.5px solid #111;border-bottom:1.5px solid #111;padding:10px 0;display:flex;flex-direction:column;gap:8px}
.rx-bcw{display:flex;flex-direction:column;align-items:center;gap:2px}.rx-bcw svg{max-width:100%;height:auto;display:block}.rx-bcw span{font-size:11px;letter-spacing:.2em}
.rx-qr{display:flex;align-items:center;gap:14px;border:1.5px solid #111;border-radius:10px;padding:10px 12px}
.rx-qr svg{flex:none;width:120px;height:120px}.rx-qr div{display:flex;flex-direction:column;gap:3px}.rx-qr b{font-size:14px}.rx-qr span{color:#555;font-size:11px}
.rx svg{shape-rendering:crispEdges}
<?php if ($embed): ?>body{background:#fff}.rx{margin:0 auto}<?php endif; ?>
</style>
