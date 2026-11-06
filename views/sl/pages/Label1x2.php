<?php
/**
 * Label1x2 - from docs/handover/artifact-source/Label1x2.dc.html (plain print page).
 * Data: views/sl/data/labels.php (sl_labelPrintData). Bag and shelf label.
 */
if (!isset($d)) { $d = []; }
$s = $d['s'];
?>
<div class="sl-noprint row g8" style="padding:12px;justify-content:center"><button type="button" class="sl-btn" data-sl-print><i class="ic ic-sm i-print" aria-hidden="true"></i>Print <?= count($d['labels']) ?> small label<?= count($d['labels']) === 1 ? '' : 's' ?></button><a class="sl-btn ghost" href="<?= h(sl_url(['size' => '46'])) ?>">4 × 6 label</a></div>
<?php foreach ($d['labels'] as $l): $big = $s['use'] === 'track' ? $l['track'] : ($l['locker'] ?: $l['track']); ?>
<div class="sl-page"><div class="lb lb12"><div class="row between" style="align-items:baseline"><b style="font-size:<?= (int) $s['size'] ?>px;letter-spacing:-.01em;line-height:1"><?= h($big) ?></b><?php if (!empty($s['tag'])): ?><span style="font-size:8px;font-weight:800;border:1.5px solid #000;border-radius:3px;padding:0 3px"><?= h($l['mode'] . ($l['bay'] !== '' ? ' · B' . $l['bay'] : '')) ?></span><?php endif; ?></div><div style="font-size:8.5px;font-weight:600;white-space:nowrap;overflow:hidden"><?= h($l['name'] . ' · ' . $l['weight'] . ' · ' . $l['piece']) ?></div><?php if (!empty($s['barcode'])): ?><?= $l['bcSmall'] ?><?php endif; ?><?php if (!empty($s['code'])): ?><div style="font-size:9px;font-weight:800;letter-spacing:.2em"><?= h($l['track']) ?></div><?php endif; ?></div></div>
<?php endforeach; ?>
<?php if (!$d['labels']): ?><div class="sl-empty" style="padding:40px">No shipment to print.</div><?php endif; ?>
<style>@media print{.sl-noprint{display:none!important}.sl-page{page-break-after:always;break-after:page}body{background:#fff}}.sl-page{display:flex;justify-content:center;padding:12px 0}@page{size:2in 1in;margin:0}</style>
