<?php
/**
 * Label1x2: the small bag and shelf label. Tracking number first, then the customer, locker as a detail.
 * Data: views/sl/data/labels.php (sl_labelPrintData / sl_labelTestData). Printer settings: sl_printerOptions().
 */
if (!isset($d)) { $d = []; }
$s = $d['s'];
$p = $d['p'];
$page = sl_printerPage($p, 'small');
$portrait = $p['small'] === '12';
$pp = $p; $ppTeam = $user->cdp_hasPermission('view_tools'); $ppScope = 'me';
?>
<?php if (empty($_GET['embed'])): ?><div class="sl-noprint lb-bar">
  <button type="button" class="sl-btn" data-sl-print><i class="ic ic-sm i-print" aria-hidden="true"></i><?= $d['test'] ? 'Print test label' : 'Print ' . count($d['labels']) . ' small label' . (count($d['labels']) === 1 ? '' : 's') ?></button>
  <?php if (!$d['test']): ?><a class="sl-btn ghost" href="<?= h(sl_url(['size' => '46'])) ?>">4 × 6 label</a><?php endif; ?>
  <details class="lb-pp"><summary class="sl-btn ghost"><i class="ic ic-sm i-sliders" aria-hidden="true"></i>Printer: <?= h($p['name']) ?> · <?= h($page['label']) ?></summary><div class="lb-pp-in"><?php include __DIR__ . '/partials/printer_panel.php'; ?></div></details>
</div><?php endif; ?>
<?php foreach ($d['labels'] as $l): $code = $s['use'] === 'carrier' && $l['carrierNo'] !== '' ? $l['carrierNo'] : $l['track']; ?>
<div class="sl-page"><div class="lb ls<?= $portrait ? ' pt' : '' ?><?= $p['dark'] ? ' dark' : '' ?><?= $p['border'] ? '' : ' nob' ?>" style="width:<?= $page['w'] ?>mm;height:<?= $page['h'] ?>mm;<?= h(sl_printerStyle($p)) ?>">
  <div class="ls-top"><b class="ls-no"><?= h($l['track']) ?></b><?php if (!empty($s['tag'])): ?><span class="ls-tag"><?= h($l['mode'] . ($l['bay'] !== '' ? ' · B' . $l['bay'] : '')) ?></span><?php endif; ?></div>
  <div class="ls-name"><?= h($l['name']) ?></div>
  <div class="ls-meta"><span>Locker <b><?= h($l['locker'] ?: '—') ?></b></span><span><?= h($l['weight']) ?> · <?= h($l['piece']) ?></span></div>
  <?php if (!empty($s['barcode'])): ?><div class="ls-bc"><?= $l['bcSmall'] ?></div><?php endif; ?>
  <?php if ($d['test']): ?><div class="ls-test">TEST · <?= h($page['label']) ?> · <?= h($p['dx']) ?>/<?= h($p['dy']) ?> mm</div><?php endif; ?>
</div></div>
<?php endforeach; ?>
<?php if (!$d['labels']): ?><div class="sl-empty" style="padding:40px">No shipment to print.</div><?php endif; ?>
<style>
<?= $page['css'] ?>
<?php if (!empty($_GET['embed'])): ?>.sl-page{padding:0!important}body{background:#fff;overflow:hidden}<?php endif; ?>
@media print{.sl-noprint{display:none!important}.sl-page{page-break-after:always;break-after:page;padding:0!important}body{background:#fff}}
.sl-page{display:flex;justify-content:center;padding:12px 0}
.lb-bar{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;padding:12px}
.lb-pp{position:relative}.lb-pp summary{list-style:none;cursor:pointer}.lb-pp summary::-webkit-details-marker{display:none}
.lb-pp-in{position:absolute;z-index:5;top:46px;left:50%;transform:translateX(-50%);width:min(760px,94vw);padding:16px;border-radius:16px;background:#fff;border:1px solid #ddd;box-shadow:0 20px 50px -20px rgba(0,0,0,.3)}
.ls{position:relative;box-sizing:border-box;padding:1.6mm 2.2mm;display:flex;flex-direction:column;gap:.6mm;border:1px solid #000;overflow:hidden;transform:translate(var(--lb-dx,0),var(--lb-dy,0)) scale(var(--lb-scale,1));transform-origin:top left}
.ls.nob{border-color:transparent}
.ls-top{display:flex;justify-content:space-between;align-items:center;gap:4px}
.ls-no{font-family:"JetBrains Mono",ui-monospace,monospace;font-size:<?= max(14, (int) $s['size']) ?>px;font-weight:800;letter-spacing:-.02em;line-height:1}
.ls-tag{font-size:7.5px;font-weight:900;border:1.5px solid #000;border-radius:3px;padding:0 3px}
.ls-name{font-size:10px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.15}
.ls-meta{display:flex;justify-content:space-between;gap:4px;font-size:7px;font-weight:600}
.ls-bc{margin-top:auto}.ls-bc svg{width:100%;height:auto;display:block;shape-rendering:crispEdges}
.ls.dark{font-weight:700}.ls.dark .ls-name,.ls.dark .ls-no{font-weight:900}
.ls.pt{padding:2mm 1.6mm}.ls.pt .ls-no{font-size:12px;word-break:break-all}.ls.pt .ls-meta{flex-direction:column}
.ls-test{position:absolute;bottom:0;left:0;right:0;text-align:center;font-size:6px;background:#fff}
</style>
<?php if ($p['auto'] && $d['labels'] && empty($_GET['embed'])): ?><script>addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script><?php endif; ?>
