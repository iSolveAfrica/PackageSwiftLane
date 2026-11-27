<?php
/**
 * Label4x6: the shipping label. Tracking number leads, then who it is for; locker is a detail.
 * Data: views/sl/data/labels.php (sl_labelPrintData / sl_labelTestData). One label per piece × copies.
 * Printer settings (paper, offsets, scale, darkness, logo, auto print) come from sl_printerOptions().
 */
if (!isset($d)) { $d = []; }
$o = $d['o'];
$p = $d['p'];
$page = sl_printerPage($p, 'big');
$lay = $d['lay'] ?? sl_labelLayout();
// The barcode sits at the bottom of the label (above the footer) only while it is placed last;
// moved higher, it stays in line with the other blocks.
$visible = array_values(array_filter($lay['order'], fn($k) => empty($lay['hide'][$k])));
$bcAt = array_search('bc', $visible, true);
$bcBottom = $bcAt !== false && !array_diff(array_slice($visible, $bcAt + 1), ['foot']);
$pp = $p; $ppTeam = $user->cdp_hasPermission('view_tools'); $ppScope = 'me';
?>
<?php if (empty($_GET['embed'])): ?><div class="sl-noprint lb-bar">
  <button type="button" class="sl-btn" data-sl-print><i class="ic ic-sm i-print" aria-hidden="true"></i><?= $d['test'] ? 'Print test label' : 'Print ' . count($d['labels']) . ' label' . (count($d['labels']) === 1 ? '' : 's') ?></button>
  <?php if (!$d['test']): ?><a class="sl-btn ghost" href="<?= h(sl_url(['size' => '12'])) ?>">Small label</a><a class="sl-btn ghost" href="<?= sl_route('LabelStudio', ['id' => $d['labels'][0]['id'] ?? null]) ?>">Label options</a><?php endif; ?>
  <details class="lb-pp"><summary class="sl-btn ghost"><i class="ic ic-sm i-sliders" aria-hidden="true"></i>Printer: <?= h($p['name']) ?> · <?= h($page['label']) ?></summary><div class="lb-pp-in"><?php include __DIR__ . '/partials/printer_panel.php'; ?></div></details>
</div><?php endif; ?>
<?php foreach ($d['labels'] as $l): ?>
<div class="sl-page<?= $p['big'] === 'a4' ? ' a4' : '' ?>">
<div class="lb lb46<?= $p['big'] === '44' ? ' sq' : '' ?><?= $p['dark'] ? ' dark' : '' ?><?= $p['border'] ? '' : ' nob' ?><?= $d['test'] ? ' test' : '' ?>" style="<?= h(sl_printerStyle($p)) ?>">
  <?php foreach ($lay['order'] as $blk): if (!empty($lay['hide'][$blk])) continue; switch ($blk): case 'top': ?>
  <div class="l-top">
    <?php if ($p['logo']): ?><?= sl_brandLogo('black', 40, 'l-logo') ?><?php else: ?><b class="l-site"><?= h($l['site']) ?></b><?php endif; ?>
    <div class="l-mode"><b><?= h($l['mode']) ?></b><span><?= h(trim($l['svc'] ?: $l['pack'])) ?></span></div>
  </div>
  <?php break; case 'trk': ?>
  <div class="l-trk">
    <div class="l-tid"><span class="k">Tracking number</span><?= $l['bcTrack'] ?><b class="l-no"><?= h($l['track']) ?></b><span class="l-sub"><?= h($l['pack']) ?> · piece <?= h($l['piece']) ?></span></div>
    <?php if (!empty($o['qr'])): ?><div class="l-qr"><?= $l['qr'] ?><span><?= ($o['qrData'] ?? 'details') === 'details' ? 'Scan: package details' : 'Scan to track' ?></span></div><?php endif; ?>
  </div>
  <?php break; case 'to': ?>
  <div class="l-to">
    <div class="row between" style="align-items:flex-start;gap:8px"><span class="k">Deliver to</span><span class="l-lock">Locker <b><?= h($l['locker'] ?: '—') ?></b><?= $l['bay'] !== '' ? ' · Bay <b>' . h($l['bay']) . '</b>' : '' ?></span></div>
    <b class="l-name"><?= h($l['name']) ?></b>
    <span class="l-addr"><?= h(trim($l['addr'] . ', ' . $l['to'], ', ')) ?><?php if (!empty($o['phone']) && $l['phone'] !== ''): ?> · <?= h($l['phone']) ?><?php endif; ?></span>
  </div>
  <?php break; case 'route': ?>
  <div class="l-route"><span><?= h($l['from'] ?: '—') ?></span><i>→</i><span class="dst"><?= h($l['to']) ?></span></div>
  <?php break; case 'facts': ?>
  <div class="l-facts">
    <div><span class="k">Weight</span><b><?= h($l['weight']) ?></b></div>
    <div><span class="k">Pieces</span><b><?= h($l['piece']) ?></b></div>
    <div><span class="k">Received</span><b><?= h($l['received']) ?></b></div>
    <div class="<?= $l['paid'] ? '' : 'inv' ?>"><span class="k">Payment</span><b><?= $l['paid'] ? 'PAID' : 'COLLECT' ?></b></div>
  </div>
  <?php break; case 'from': ?>
  <?php if (!empty($o['sender']) && $l['sender'] !== ''): ?><div class="l-from"><span class="k">From</span> <?= h($l['sender']) ?><?= $l['senderCity'] !== '' ? ' · ' . h($l['senderCity']) : '' ?></div><?php endif; ?>
  <?php break; case 'cont': ?>
  <?php if (!empty($o['contents'])): ?><div class="l-cont"><b>Contents:</b> <?= h($l['contents']) ?><?php if (!empty($o['value'])): ?> · declared <?= h($l['declared']) ?><?php endif; ?></div><?php endif; ?>
  <?php break; case 'flags': ?>
  <?php if (!empty($o['fragile']) || !empty($l['dg']) || !empty($o['care'])): ?><div class="l-flags"><?php if (!empty($o['fragile'])): ?><span>FRAGILE</span><?php endif; ?><?php if (!empty($l['dg'])): ?><span>DANGEROUS GOODS</span><?php endif; ?><?php if (!empty($o['care'])): ?><span>⬆ THIS SIDE UP</span><?php endif; ?></div><?php endif; ?>
  <?php break; case 'bc': ?>
  <div class="l-bc<?= $bcBottom ? ' end' : '' ?>"><?php if ($l['bc'] !== ''): ?><span class="l-car"><?= h($l['carrier'] !== '' ? $l['carrier'] : 'Courier') ?> · original tracking number</span><?= $l['bc'] ?><?php else: ?><span class="l-nocar">Courier tracking number not added yet</span><?php endif; ?></div>
  <?php break; case 'foot': ?>
  <div class="l-foot"><span><?= h($l['hub']) ?></span><span><?= h($l['siteUrl'] !== '' ? 'Track: ' . $l['siteUrl'] . '/track.php' : '') ?></span></div>
  <?php break; endswitch; endforeach; ?>
  <?php if ($d['test']): ?><div class="l-test"><b>TEST PRINT</b><span><?= h($p['name']) ?> · <?= h($page['label']) ?> · <?= h($p['dpi']) ?> dpi · move <?= h($p['dx']) ?>/<?= h($p['dy']) ?> mm · <?= (int) $p['scale'] ?>%</span><i class="c tl"></i><i class="c tr"></i><i class="c bl"></i><i class="c br"></i><i class="x"></i></div><?php endif; ?>
</div>
</div>
<?php endforeach; ?>
<?php if (!$d['labels']): ?><div class="sl-empty" style="padding:40px">No shipment to print. It may not exist or be outside your scope.</div><?php endif; ?>
<style>
<?= $page['css'] ?>
<?php if (!empty($_GET['embed'])): ?>.sl-page{padding:0!important}body{background:#fff;overflow:hidden}<?php endif; ?>
@media print{.sl-noprint{display:none!important}.sl-page{page-break-after:always;break-after:page;padding:0!important}body{background:#fff}}
.sl-page{display:flex;justify-content:center;padding:12px 0}
.sl-page.a4{align-items:flex-start;padding-top:20mm}
.lb-bar{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;padding:12px}
.lb-pp{position:relative}.lb-pp summary{list-style:none;cursor:pointer}.lb-pp summary::-webkit-details-marker{display:none}
.lb-pp-in{position:absolute;z-index:5;top:46px;left:50%;transform:translateX(-50%);width:min(760px,94vw);padding:16px;border-radius:16px;background:#fff;border:1px solid #ddd;box-shadow:0 20px 50px -20px rgba(0,0,0,.3)}
.lb46{position:relative;transform:translate(var(--lb-dx,0),var(--lb-dy,0)) scale(var(--lb-scale,1));transform-origin:top left;gap:7px;padding:12px}
.lb46.sq{height:384px}.lb46.sq .l-from,.lb46.sq .l-cont,.lb46.sq .l-foot{display:none}
.lb46.nob{border-color:transparent}
.lb46.dark{-webkit-font-smoothing:none;font-weight:600}.lb46.dark .l-name,.lb46.dark .l-no{font-weight:900}.lb46.dark svg rect,.lb46.dark svg path{shape-rendering:crispEdges}
.lb46 svg{shape-rendering:crispEdges}
.l-top{display:flex;justify-content:space-between;align-items:center;gap:8px}
.l-top .l-logo{height:40px;width:auto}
.l-site{font-size:16px}
.l-mode{display:flex;flex-direction:column;align-items:center;background:#000;color:#fff;border-radius:6px;padding:3px 12px 4px;line-height:1}
.l-mode b{font-size:24px;font-weight:900;letter-spacing:.02em}.l-mode span{font-size:8px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;margin-top:2px}
.l-trk{display:flex;justify-content:space-between;align-items:center;gap:8px;border:2.5px solid #000;border-radius:8px;padding:8px 10px}
.l-no{font-family:"JetBrains Mono",ui-monospace,monospace;font-size:24px;letter-spacing:.06em;font-weight:800;line-height:1.05}
.l-sub{font-size:10px;font-weight:600}
.l-tid{display:flex;flex-direction:column;min-width:0;flex:1}.l-tid svg{width:100%;max-width:230px;height:56px;display:block;margin:2px 0 1px}
.l-qr{display:flex;flex-direction:column;align-items:center;gap:1px;flex:none}.l-qr svg{width:104px;height:104px;display:block}.l-qr span{font-size:7.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
.l-nocar{display:block;width:100%;text-align:center;border:1.5px dashed #000;border-radius:6px;padding:10px;font-size:11px;font-weight:700}
.l-to{display:flex;flex-direction:column;gap:2px;border:2px solid #000;border-radius:8px;padding:7px 10px}
.l-name{font-size:21px;font-weight:800;line-height:1.15}
.l-addr{font-size:11.5px;line-height:1.3}
.l-lock{font-size:10px;border:1.5px solid #000;border-radius:4px;padding:1px 5px;white-space:nowrap}
.l-route{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:6px;font-size:12px;font-weight:800;text-align:center}
.l-route span{border:2px solid #000;border-radius:6px;padding:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.l-route span.dst{background:#000;color:#fff}.l-route i{font-style:normal;font-size:16px}
.l-facts{display:grid;grid-template-columns:repeat(4,1fr);gap:5px}
.l-facts div{border:2px solid #000;border-radius:6px;padding:4px 6px;display:flex;flex-direction:column}
.l-facts b{font-size:14px;font-weight:800}
.l-facts .inv{background:#000;color:#fff}
.l-from,.l-cont{font-size:10px;line-height:1.3}
.l-flags{display:flex;gap:5px;flex-wrap:wrap}.l-flags span{font-size:9.5px;font-weight:900;border:2px solid #000;padding:1px 6px;border-radius:4px}
.l-bc{margin-top:0;display:flex;flex-direction:column;align-items:center;gap:1px}
.l-bc.end{margin-top:auto}
.l-car{font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.06em}
.l-foot{display:flex;justify-content:space-between;gap:8px;font-size:8.5px;font-weight:600;border-top:1.5px solid #000;padding-top:3px}
.l-test{position:absolute;inset:0;pointer-events:none}
.l-test b{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-28deg);font-size:40px;font-weight:900;color:rgba(0,0,0,.12);white-space:nowrap}
.l-test span{position:absolute;bottom:-1px;left:0;right:0;text-align:center;font-size:8px;background:#fff}
.l-test .c{position:absolute;width:12px;height:12px;border:0 solid #000}
.l-test .tl{top:2px;left:2px;border-top-width:2px;border-left-width:2px}.l-test .tr{top:2px;right:2px;border-top-width:2px;border-right-width:2px}
.l-test .bl{bottom:2px;left:2px;border-bottom-width:2px;border-left-width:2px}.l-test .br{bottom:2px;right:2px;border-bottom-width:2px;border-right-width:2px}
.l-test .x{position:absolute;top:50%;left:50%;width:16px;height:16px;transform:translate(-50%,-50%);background:linear-gradient(#000,#000) center/1.5px 100% no-repeat,linear-gradient(#000,#000) center/100% 1.5px no-repeat}
</style>
<?php if ($p['auto'] && $d['labels'] && empty($_GET['embed'])): ?><script>addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script><?php endif; ?>
