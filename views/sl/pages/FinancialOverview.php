<?php
/**
 * Financial Overview - from docs/handover/artifact-source/FinancialOverview.dc.html.
 * Data: views/sl/data/finance.php (sl_financialOverviewData, money = true).
 */
if (!isset($d)) { $d = []; }
$k = $d['kpi'];
?>
        <div class="sl-head">
          <div><h1 class="sl-title">Financial Overview</h1><p class="sl-lede">Revenue, costs and margin for <?= h($d['hub']) ?> · <?= h($d['rangeLabel']) ?></p></div>
          <div class="row g8 wrap"><div class="sl-seg boxed"><?php foreach ($d['ranges'] as $r): ?><a class="<?= h($r['cls']) ?>" href="<?= h(sl_url(['range' => $r['f']])) ?>"><?= h($r['label']) ?></a><?php endforeach; ?></div><a class="sl-btn ghost" href="<?= sl_route('Transactions', ['export' => 'csv']) ?>"><i class="ic ic-sm i-download" aria-hidden="true"></i>Export</a></div>
        </div>
        <div class="grid cols3">
          <div class="sl-card sl-metric"><div class="sl-ml"><span><i class="ic ic-sm i-dollar" aria-hidden="true"></i>Revenue</span></div><div class="sl-mv"><?= h($k['revenue']['v']) ?> <i><?= h($k['revenue']['sub']) ?></i></div><div class="sl-ticks"><i style="width: <?= (int) $k['revenue']['pct'] ?>%"></i></div><div class="sl-mf"><?= h($k['revenue']['foot']) ?></div></div>
          <div class="sl-card sl-metric"><div class="sl-ml"><span><i class="ic ic-sm i-truck" aria-hidden="true"></i>Carrier and delivery costs</span></div><div class="sl-mv"><?= h($k['cost']['v']) ?> <i><?= h($k['cost']['sub']) ?></i></div><div class="sl-ticks y"><i style="width: <?= (int) $k['cost']['pct'] ?>%"></i></div><div class="sl-mf"><?= h($k['cost']['foot']) ?></div></div>
          <div class="sl-card sl-metric"><div class="sl-ml"><span><i class="ic ic-sm i-chart" aria-hidden="true"></i>Gross margin</span></div><div class="sl-mv"><?= h($k['margin']['v']) ?> <i><?= h($k['margin']['sub']) ?></i></div><div class="sl-ticks v"><i style="width: <?= (int) $k['margin']['pct'] ?>%"></i></div><div class="sl-mf"><?= h($k['margin']['foot']) ?></div></div>
        </div>
        <div class="split">
          <section class="a sl-card"><div class="sl-ch"><h2 class="sl-ct">Revenue by month</h2><span class="mut sm">Last 12 months</span></div>
            <div class="sl-bars" style="height: 200px"><?php foreach ($d['months'] as $m): ?><div class="sl-bar <?= h($m['hi']) ?>" title="<?= h($m['tip']) ?>"><span style="<?= h($m['sty']) ?>"></span></div><?php endforeach; ?></div>
            <div class="row between mut sm" style="margin-top: 8px"><?php foreach ($d['monthLabels'] as $l): ?><span><?= h($l) ?></span><?php endforeach; ?></div>
          </section>
          <aside class="b sl-card col g12"><h2 class="sl-ct">Revenue by gateway</h2>
            <?php foreach ($d['gw'] as $g): ?><div class="col g6"><div class="row between sm"><span class="fw5"><?= h($g['n']) ?></span><span class="mut"><?= h($g['v']) ?></span></div><div class="sl-ticks <?= h($g['c']) ?>"><i style="<?= h($g['sty']) ?>"></i></div></div><?php endforeach; ?>
            <?php if (!$d['gw']): ?><div class="sl-empty">No payments in this period.</div><?php endif; ?>
          </aside>
        </div>
        <section class="sl-card flush">
          <div class="row between" style="padding: 14px 16px"><h2 class="sl-ct">Profit by service</h2><a href="<?= sl_route('Reports') ?>#fin" class="sm fw5 row g6">Full report <i class="ic ic-sm i-right" aria-hidden="true"></i></a></div>
          <div class="sl-tw"><table class="sl-table"><thead><tr><th>Service</th><th class="num">Parcels</th><th class="num">Revenue</th><th class="num">Cost</th><th class="num">Margin</th></tr></thead><tbody>
            <?php foreach ($d['svc'] as $s): ?><tr><td class="fw5"><?= h($s['n']) ?></td><td class="num"><?= h($s['p']) ?></td><td class="num"><?= h($s['r']) ?></td><td class="num"><?= h($s['c']) ?></td><td class="num fw5"><?= h($s['m']) ?></td></tr><?php endforeach; ?>
          </tbody></table></div>
        </section>
        <p class="mut sm">Costs are purchase prices recorded on packages plus the operating costs in <a href="<?= sl_route('CostSettings') ?>">Operating costs</a>, prorated to the period (split 60/40 air/sea).</p>
