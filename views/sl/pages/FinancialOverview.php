<?php
/**
 * FinancialOverview - generated from docs/handover/artifact-source/FinancialOverview.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  months                       as $m
 *   loop  gw                           as $g
 *   loop  svc                          as $s
 *   value gw, months, svc
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
        <div class="sl-head">
          <div><h1 class="sl-title">Financial Overview</h1><p class="sl-lede">Revenue, costs and margin for Accra Hub</p></div>
          <div class="row g8 wrap"><div class="sl-seg boxed"><button>7d</button><button class="on">30d</button><button>90d</button><button>1y</button></div><a class="sl-btn ghost" href="<?= sl_route('Documents') ?>"><i class="ic ic-sm i-download" aria-hidden="true"></i>Export</a></div>
        </div>
        <div class="grid cols3">
          <div class="sl-card sl-metric"><div class="sl-ml"><span><i class="ic ic-sm i-dollar" aria-hidden="true"></i>Revenue</span></div><div class="sl-mv">$48,200 <i>/ $60,000 target</i></div><div class="sl-ticks"><i style="width: 80%"></i></div><div class="sl-mf">+12.4% vs last 30 days</div></div>
          <div class="sl-card sl-metric"><div class="sl-ml"><span><i class="ic ic-sm i-truck" aria-hidden="true"></i>Carrier and delivery costs</span></div><div class="sl-mv">$29,640 <i>(61% of revenue)</i></div><div class="sl-ticks y"><i style="width: 61%"></i></div><div class="sl-mf">Air $19,210 · Sea $10,430</div></div>
          <div class="sl-card sl-metric"><div class="sl-ml"><span><i class="ic ic-sm i-chart" aria-hidden="true"></i>Gross margin</span></div><div class="sl-mv">$18,560 <i>(39%)</i></div><div class="sl-ticks v"><i style="width: 39%"></i></div><div class="sl-mf">Target 42%</div></div>
        </div>
        <div class="split">
          <section class="a sl-card"><div class="sl-ch"><h2 class="sl-ct">Revenue by month</h2><span class="mut sm">Last 12 months</span></div>
            <div class="sl-bars" style="height: 200px"><?php foreach ((array) sl_get($d, 'months', []) as $__i => $m): ?><div class="sl-bar <?= sl_v($m, 'hi') ?>"><span style="<?= sl_v($m, 'sty') ?>"></span></div><?php endforeach; ?></div>
            <div class="row between mut sm" style="margin-top: 8px"><span>Dec</span><span>Feb</span><span>Apr</span><span>Jun</span><span>Aug</span><span>Oct</span></div>
          </section>
          <aside class="b sl-card col g12"><h2 class="sl-ct">Revenue by gateway</h2>
            <?php foreach ((array) sl_get($d, 'gw', []) as $__i => $g): ?><div class="col g6"><div class="row between sm"><span class="fw5"><?= sl_v($g, 'n') ?></span><span class="mut"><?= sl_v($g, 'v') ?></span></div><div class="sl-ticks <?= sl_v($g, 'c') ?>"><i style="<?= sl_v($g, 'sty') ?>"></i></div></div><?php endforeach; ?>
          </aside>
        </div>
        <section class="sl-card flush">
          <div class="row between" style="padding: 14px 16px"><h2 class="sl-ct">Profit by service</h2><a href="<?= sl_route('Reports') ?>" class="sm fw5 row g6">Full report <i class="ic ic-sm i-right" aria-hidden="true"></i></a></div>
          <div class="sl-tw"><table class="sl-table"><thead><tr><th>Service</th><th class="num">Parcels</th><th class="num">Revenue</th><th class="num">Cost</th><th class="num">Margin</th></tr></thead><tbody>
            <?php foreach ((array) sl_get($d, 'svc', []) as $__i => $s): ?><tr><td class="fw5"><?= sl_v($s, 'n') ?></td><td class="num"><?= sl_v($s, 'p') ?></td><td class="num"><?= sl_v($s, 'r') ?></td><td class="num"><?= sl_v($s, 'c') ?></td><td class="num fw5"><?= sl_v($s, 'm') ?></td></tr><?php endforeach; ?>
          </tbody></table></div>
        </section>
      
