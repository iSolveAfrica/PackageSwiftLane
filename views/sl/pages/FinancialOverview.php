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
        <div class="sl-data-grid">
          <?php foreach ([
              'revenue' => ['Revenue', 'mint'],
              'cost' => ['Carrier and delivery costs', 'amber'],
              'margin' => ['Gross margin', 'violet'],
          ] as $metric => [$label, $tone]): $figure = $k[$metric]; ?>
            <section class="sl-data-card sl-data-card--<?= h($tone) ?>">
              <header><h2 class="sl-data-card__title"><?= h($label) ?></h2><span class="sl-data-card__period"><?= h($d['rangeLabel']) ?></span></header>
              <p class="sl-data-card__value"><?= h($figure['v']) ?></p>
              <p class="sl-data-card__detail"><?= h($figure['sub']) ?></p>
              <div class="sl-data-meter" aria-hidden="true"><i style="width:<?= max(0, min(100, (int) $figure['pct'])) ?>%"></i></div>
              <p class="sl-data-card__detail"><?= h($figure['foot']) ?></p>
            </section>
          <?php endforeach; ?>
        </div>
        <div class="split">
          <section class="a sl-data-card sl-data-card--mint"><header><h2 class="sl-data-card__title">Revenue by month</h2><span class="sl-data-card__period">Last 12 months</span></header>
            <div class="sl-bars" style="height: 200px"><?php foreach ($d['months'] as $m): ?><div class="sl-bar <?= h($m['hi']) ?>" title="<?= h($m['tip']) ?>"><span style="<?= h($m['sty']) ?>"></span></div><?php endforeach; ?></div>
            <div class="row between mut sm" style="margin-top: 8px"><?php foreach ($d['monthLabels'] as $l): ?><span><?= h($l) ?></span><?php endforeach; ?></div>
          </section>
          <aside class="b sl-data-card sl-data-card--violet"><header><h2 class="sl-data-card__title">Revenue by gateway</h2><span class="sl-data-card__period"><?= h($d['rangeLabel']) ?></span></header>
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
