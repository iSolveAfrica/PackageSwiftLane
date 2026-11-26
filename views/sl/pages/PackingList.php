<?php
/**
 * Packing list page (packing_list.php): printable, with a download for Excel.
 * Customers in name order; each customer's packages with their items and weights; dangerous goods apart.
 */
if (!isset($d)) { $d = []; }
$u = $d['unit'];
$w = fn($n) => number_format((float) $n, 2) . ' ' . $u;
$pl = fn(int $n, string $one, string $many) => $n . ' ' . ($n === 1 ? $one : $many);
?>
<div class="pl">
  <div class="pl-bar">
    <a class="sl-btn ghost sm" href="<?= h($d['back']) ?>"><i class="ic ic-sm i-left" aria-hidden="true"></i>Back to <?= h($d['code']) ?></a>
    <span class="grow"></span>
    <a class="sl-btn ghost sm" href="<?= h(sl_url(['format' => 'csv'])) ?>"><i class="ic ic-sm i-download" aria-hidden="true"></i>Download Excel</a>
    <button type="button" class="sl-btn sm" onclick="window.print()"><i class="ic ic-sm i-print" aria-hidden="true"></i>Print or save PDF</button>
  </div>

  <header class="pl-head">
    <div><span class="pl-k"><?= h($d['site']) ?> · packing list</span><h1><?= h($d['code']) ?></h1><span class="pl-sub"><?= $d['sea'] ? 'Sea' : 'Air' ?> consolidation · <?= h($d['status']) ?></span></div>
    <dl class="pl-facts"><?php foreach ($d['facts'] as [$k, $v]): ?><div><dt><?= h($k) ?></dt><dd><?= h($v) ?></dd></div><?php endforeach; ?></dl>
  </header>

  <div class="pl-sum">
    <div><b><?= (int) $d['totals']['all']['cust'] ?></b><span><?= (int) $d['totals']['all']['cust'] === 1 ? 'customer' : 'customers' ?></span></div>
    <div><b><?= (int) $d['totals']['all']['pk'] ?></b><span><?= (int) $d['totals']['all']['pk'] === 1 ? 'package' : 'packages' ?></span></div>
    <div><b><?= (int) $d['totals']['all']['pcs'] ?></b><span><?= (int) $d['totals']['all']['pcs'] === 1 ? 'piece' : 'pieces' ?></span></div>
    <div class="big"><b><?= h($w($d['totals']['all']['w'])) ?></b><span>total weight</span></div>
    <?php if (!empty($d['sections']['dg'])): ?><div class="dg"><b><?= h($w($d['totals']['dg']['w'])) ?></b><span>dangerous goods · <?= h($pl((int) $d['totals']['dg']['pk'], 'package', 'packages')) ?></span></div><?php endif; ?>
  </div>

  <?php foreach (['normal' => 'Normal goods', 'dg' => 'Dangerous goods'] as $sk => $sl): if (empty($d['sections'][$sk])) continue; $t = $d['totals'][$sk]; ?>
  <section class="pl-sec <?= $sk ?>">
    <h2><?= $sk === 'dg' ? '⚠ ' : '' ?><?= h($sl) ?><span><?= h($pl((int) $t['cust'], 'customer', 'customers')) ?> · <?= h($pl((int) $t['pk'], 'package', 'packages')) ?> · <?= h($w($t['w'])) ?></span></h2>
    <table class="pl-t">
      <thead><tr><th style="width:44px">S/N</th><th>Swift tracking</th><th>Postal tracking</th><th class="n" style="width:60px">Qty</th><th>Item</th><th class="n">Item weight</th><th class="n">Package weight</th></tr></thead>
      <?php foreach ($d['sections'][$sk] as $c): ?>
      <tbody class="pl-cust">
        <tr class="pl-ch"><td colspan="7"><b><?= h($c['name']) ?></b><?= $c['locker'] !== '' ? ' <span>· ' . h($c['locker']) . '</span>' : '' ?><?= $c['phone'] !== '' ? ' <span>· ' . h($c['phone']) . '</span>' : '' ?></td></tr>
        <?php foreach ($c['pk'] as $p): $its = $p['items'] ?: [['qty' => '', 'what' => 'No items listed', 'w' => null]]; foreach ($its as $i => $it): ?>
        <tr class="<?= $i ? 'it' : 'pk' ?>">
          <td><?= $i ? '' : (int) $p['sn'] ?></td>
          <td class="mono"><?= $i ? '' : h($p['code']) ?></td>
          <td class="mono"><?= $i ? '' : h($p['post']) ?></td>
          <td class="n"><?= h((string) $it['qty']) ?></td>
          <td><?= h($it['what']) ?></td>
          <td class="n"><?= $it['w'] === null ? '' : h($w($it['w'])) ?></td>
          <td class="n"><?= $i ? '' : '<b>' . h($w($p['w'])) . '</b>' ?></td>
        </tr>
        <?php endforeach; endforeach; ?>
        <tr class="pl-ct"><td colspan="3"><?= h($c['name']) ?> · <?= count($c['pk']) ?> package<?= count($c['pk']) === 1 ? '' : 's' ?></td><td class="n"><?= (int) $c['pcs'] ?></td><td></td><td></td><td class="n"><b><?= h($w($c['w'])) ?></b></td></tr>
      </tbody>
      <?php endforeach; ?>
      <tfoot><tr><td colspan="3"><?= h($sl) ?> total · <?= h($pl((int) $t['pk'], 'package', 'packages')) ?></td><td class="n"><?= (int) $t['pcs'] ?></td><td></td><td></td><td class="n"><?= h($w($t['w'])) ?></td></tr></tfoot>
    </table>
  </section>
  <?php endforeach; ?>
  <?php if (empty($d['sections'])): ?><div class="sl-empty">No packages in this consolidation yet.</div><?php endif; ?>
  <footer class="pl-foot"><span>Grand total: <?= h($pl((int) $d['totals']['all']['pk'], 'package', 'packages')) ?> · <?= h($pl((int) $d['totals']['all']['pcs'], 'piece', 'pieces')) ?> · <b><?= h($w($d['totals']['all']['w'])) ?></b></span><span>Printed <?= h(date('M j, Y H:i')) ?></span></footer>
</div>
