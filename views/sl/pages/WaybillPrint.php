<?php
/** Print view for waybill_print.php: the manifest text, or one house bill per page. No artboard; uses the design tokens only. */
if (!isset($d)) { $d = []; }
$w = $d['w'];
?>
<style>.wp{max-width:900px;margin:24px auto;padding:0 16px;display:flex;flex-direction:column;gap:16px}.wp-h{page-break-after:always;border:1px solid var(--line);border-radius:12px;padding:18px}@media print{.wp-tools{display:none}.wp{margin:0;max-width:none}}</style>
<div class="wp">
<div class="row between wp-tools"><a class="sl-btn ghost sm" href="<?= h(sl_route('Waybills', ['id' => (int) $w->id, 'tab' => 'man'])) ?>">← Back to waybill</a><button class="sl-btn sm" data-sl-print><i class="ic ic-sm i-print" aria-hidden="true"></i>Print</button></div>
<?php if ($d['fmt'] !== 'hawb'): ?>
<pre class="fr-pre" style="font:12px/1.55 var(--mono);white-space:pre;overflow:auto;border:1px solid var(--line);border-radius:12px;padding:16px;background:#fff;color:#111"><?= h($d['text']) ?></pre>
<?php else: foreach ($d['items'] as $i): ?>
<section class="wp-h col g8"><div class="row between"><b class="fw5" style="font-size:18px">HOUSE AIR WAYBILL · H-<?= h(str_pad((string) $i->id, 5, '0', STR_PAD_LEFT)) ?></b><span class="mono">MAWB <?= h($w->waybill_no) ?></span></div>
<div class="sl-kv"><div><span>Shipper</span><span><?= h($d['site']) ?><?= $d['address'] ? ' · ' . h($d['address']) : '' ?></span></div><div><span>Consignee</span><span><?= h($i->customer_name ?: '—') ?></span></div><div><span>Tracking</span><span class="mono"><?= h($i->order_track) ?></span></div><div><span>Route</span><span><?= h(($w->origin ?: $w->s_origin) . ' → ' . ($w->destination ?: $w->s_dest)) ?><?= $w->voyage_no ? ' · ' . h($w->voyage_no) : '' ?></span></div><div><span>Pieces / weight</span><span class="mono"><?= (int) $i->pieces ?> pcs · <?= h(number_format((float) $i->weight_kg, 1)) ?> kg</span></div><div><span>Description</span><span><?= h($i->description ?: '—') ?></span></div><div><span>Charges</span><span class="mono"><?= h(sl_money($i->charge)) ?></span></div></div></section>
<?php endforeach; if (!$d['items']): ?><div class="sl-empty">This waybill has no house bills.</div><?php endif; endif; ?>
</div>
