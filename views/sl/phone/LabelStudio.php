<?php
/**
 * M-Labels: print a shipment's label. Same $d as the web page (views/sl/data/labels.php sl_labelStudioData).
 * The preview is the real print page (print_label_ship.php ...&embed=1); Print opens it with &size= and &copies=,
 * which that page already reads. Size tabs use the web page's ?view=46|12. A4 sheets are left out: the print
 * page has no A4 layout.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'staff';
$l = $d['l'];
$o = $d['o'];
$size = $d['view'] === '12' ? '12' : '46';
$copies = max(1, min(3, (int) ($_GET['copies'] ?? $o['copies'] ?? 1)));
$print = $l ? 'print_label_ship.php?id=' . (int) $l['id'] . '&size=' . $size . '&copies=' . $copies : '';
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-wh.css')) ?>">
<?= sl_phoneTop('Print label', $l ? $l['track'] : '', ['back' => 'menu.php']) ?>
<main class="mx-body">
<form method="get" class="mx-srch" role="search"><input type="hidden" name="view" value="<?= h($size) ?>"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q']) ?>" aria-label="Shipment tracking number" placeholder="Tracking number" autocapitalize="characters" enterkeyhint="search"></label><a class="mx-ib" href="<?= h(sl_route('PackageIntake')) ?>" aria-label="Scan"><i class="ic i-scan" aria-hidden="true"></i></a></form>
<?php if ($d['recent']): ?><div class="mx-chips"><?php foreach ($d['recent'] as $r): ?><a href="<?= h(sl_url(['id' => $r['id'], 'q' => null])) ?>" style="padding:6px 11px;border-radius:99px;background:<?= $l && $r['id'] === $l['id'] ? 'var(--ink);color:var(--inverse)' : 'var(--active);color:var(--ink-2)' ?>;font-size:12px;font-weight:500;min-height:32px;display:inline-flex;align-items:center"><?= h($r['track']) ?></a><?php endforeach; ?></div><?php endif; ?>
<nav class="mx-seg" aria-label="Label size"><?php foreach (['46' => '4×6', '12' => '1×2 small'] as $v => $t): ?><a class="<?= $size === $v ? 'on' : '' ?>" href="<?= h(sl_url(['view' => $v])) ?>"<?= $size === $v ? ' aria-current="page"' : '' ?>><?= h($t) ?></a><?php endforeach; ?></nav>
<?php if ($l): ?>
<div class="mx-lbl">
<div style="display:flex;justify-content:space-between"><span class="mono"><?= h(mb_strtoupper($l['site']) . ' · ' . $l['mode']) ?></span><span><?= h($l['weight']) ?></span></div>
<b><?= h($size === '12' && $l['locker'] !== '' ? $l['locker'] : $l['track']) ?></b>
<span><?= h(ucwords(strtolower($l['name']))) ?><?= $l['to'] !== '—' ? ' · ' . h(ucwords(strtolower($l['to']))) : '' ?></span>
<?php if (!empty($d['bc'])): ?><div class="wh-bc" role="img" aria-label="Barcode <?= h($l['track']) ?>"><?= $d['bc'] /* trusted SVG from sl_barcodeSvg() */ ?></div><?php else: ?><div class="bc" aria-hidden="true"></div><?php endif; ?>
<span class="mono"><?= h($l['track']) ?> · <?= h($l['from'] ?: '—') ?>→<?= h($l['to']) ?> · <?= h(mb_strtoupper($l['received'])) ?></span>
</div>
<section class="mx-card mx-form">
<div class="mx-f"><small>Printer</small><div class="mx-in"><span><?= h($o['printer'] ?: 'Browser print') ?></span></div></div>
<div class="mx-f"><small id="lb-cl">Copies</small><nav class="mx-fseg" aria-labelledby="lb-cl"><?php for ($i = 1; $i <= 3; $i++): ?><a class="<?= $copies === $i ? 'on' : '' ?>" href="<?= h(sl_url(['copies' => $i])) ?>"<?= $copies === $i ? ' aria-current="true"' : '' ?>><?= $i ?></a><?php endfor; ?></nav></div>
<p class="mx-note"><?= h($d['summary']) ?></p>
</section>
<div class="mx-btns">
<a class="mx-btn" href="<?= h($print) ?>" target="_blank" rel="noopener"><i class="ic ic-sm i-print" aria-hidden="true"></i>Print</a>
<a class="mx-btn sec" href="<?= h(sl_url(['view' => 'web'])) ?>">Edit layout in Label Studio (full website)</a>
</div>
<?php else: ?>
<?= sl_mxEmpty('tag', 'No shipment loaded', $d['q'] !== '' ? 'No shipment matches "' . $d['q'] . '".' : 'Search a tracking number to print its label.') ?>
<?php endif; ?>
</main>
