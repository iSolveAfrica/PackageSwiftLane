<?php
/**
 * Label4x6 - from docs/handover/artifact-source/Label4x6.dc.html (plain print page).
 * Data: views/sl/data/labels.php (sl_labelPrintData). One label per piece × copies.
 */
if (!isset($d)) { $d = []; }
$o = $d['o'];
?>
<div class="sl-noprint row g8" style="padding:12px;justify-content:center"><button type="button" class="sl-btn" data-sl-print><i class="ic ic-sm i-print" aria-hidden="true"></i>Print <?= count($d['labels']) ?> label<?= count($d['labels']) === 1 ? '' : 's' ?></button><a class="sl-btn ghost" href="<?= h(sl_url(['size' => '12'])) ?>">Small 1 × 2 label</a><a class="sl-btn ghost" href="<?= sl_route('LabelStudio', ['id' => $d['labels'][0]['id'] ?? null]) ?>">Label options</a></div>
<?php foreach ($d['labels'] as $l): ?>
<div class="sl-page">
<div class="lb lb46">
<div class="row between"><div class="row g8"><span style="width:30px;height:30px;border-radius:7px;background:#000;color:#fff;display:grid;place-items:center;font-weight:800;font-size:12px"><?= h($l['ini']) ?></span><b style="font-size:16px"><?= h($l['site']) ?></b></div><div style="text-align:right"><div style="font-size:22px;font-weight:800;line-height:1"><?= h($l['mode']) ?></div><div class="k"><?= h(trim($l['pack'] . ' · ' . $l['svc'], ' ·')) ?></div></div></div>
<div class="bx" style="display:flex;justify-content:space-between;align-items:center"><div><div class="k">Locker</div><div style="font-size:40px;font-weight:800;letter-spacing:-.02em;line-height:1"><?= h($l['locker'] ?: '—') ?></div></div><div style="text-align:right"><?php if ($l['bay'] !== ''): ?><div class="k">Bay</div><div style="font-size:28px;font-weight:800;line-height:1"><?= h($l['bay']) ?></div><?php elseif (!empty($o['qr'])): ?><?= $l['qr'] ?><?php endif; ?></div></div>
<div class="bx"><div class="k">Ship to</div><div style="font-size:18px;font-weight:700"><?= h($l['name']) ?></div><div style="font-size:12px"><?= h(trim($l['addr'] . ', ' . $l['to'], ', ')) ?><?php if (!empty($o['phone']) && $l['phone'] !== ''): ?> · <?= h($l['phone']) ?><?php endif; ?></div></div>
<div class="row g8" style="font-size:12px;font-weight:700"><span class="bx" style="flex:1;text-align:center;padding:6px"><?= h($l['from'] ?: '—') ?></span><span style="font-size:18px">→</span><span class="bx" style="flex:1;text-align:center;padding:6px;background:#000;color:#fff"><?= h($l['to']) ?></span></div>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;font-size:11px"><div class="bx" style="padding:5px 7px"><div class="k">Weight</div><b style="font-size:15px"><?= h($l['weight']) ?></b></div><div class="bx" style="padding:5px 7px"><div class="k">Pieces</div><b style="font-size:15px"><?= h($l['piece']) ?></b></div><div class="bx" style="padding:5px 7px"><div class="k">Received</div><b style="font-size:15px"><?= h($l['received']) ?></b></div></div>
<?php if (!empty($o['contents'])): ?><div style="font-size:10.5px;line-height:1.35"><b>Contents:</b> <?= h($l['contents']) ?><?php if (!empty($o['value'])): ?> · declared <?= h($l['declared']) ?><?php endif; ?><?php if (!empty($o['fragile'])): ?> · <b>FRAGILE</b><?php endif; ?><?php if (!empty($o['care'])): ?> · ⬆ this side up<?php endif; ?></div><?php endif; ?>
<div style="margin-top:auto;display:flex;flex-direction:column;align-items:center;gap:2px"><?= $l['bc'] ?><?php if ($l['carrierNo'] !== ''): ?><div style="font-size:9.5px" class="m"><?= h(trim($l['carrier'] . ' ' . $l['carrierNo'])) ?></div><?php endif; ?></div>
</div>
</div>
<?php endforeach; ?>
<?php if (!$d['labels']): ?><div class="sl-empty" style="padding:40px">No shipment to print. It may not exist or be outside your scope.</div><?php endif; ?>
<style>@media print{.sl-noprint{display:none!important}.sl-page{page-break-after:always;break-after:page}body{background:#fff}}.sl-page{display:flex;justify-content:center;padding:12px 0}@page{size:4in 6in;margin:0}</style>
