<?php
/**
 * FinancialOverviewRestricted - generated from docs/handover/artifact-source/FinancialOverviewRestricted.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  months                       as $m
 *   loop  gw                           as $g
 *   loop  svc                          as $s
 *   loop  counts                       as $k
 *   loop  invF                         as $x
 *   loop  invs                         as $r
 *   loop  perm                         as $p
 *   loop  durs                         as $d
 *   loop  feed                         as $f
 *   loop  methods                      as $m
 *   loop  svcN                         as $m
 *   value ask, askLabel, askNote, counts, durs, feed, gw, invF, invs, methods, months, perm, svc, svcN
 * sl-wrap extras in the design: fx
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="fx-bn" role="note"><i class="ic i-lock" aria-hidden="true"></i><span class="grow"><b class="fw5">Viewing as Support agent.</b> This role cannot see financial figures, so amounts are blurred. Counts, names and charts stay readable.</span><a class="sl-btn sm ghost" href="<?= sl_route('StaffPermissions') ?>">Request access</a></div>
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
      <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(min(170px,100%),1fr));gap:12px"><?php foreach ((array) sl_get($d, 'counts', []) as $__i => $k): ?><div class="fr-k"><small><?= sl_v($k, 'l') ?></small><b class="<?= sl_v($k, 'anim') ?>"><?= sl_v($k, 'v') ?></b><small><?= sl_v($k, 's') ?></small></div><?php endforeach; ?></div>
<div class="split"><section class="a sl-card flush"><div class="row between wrap g8" style="padding:16px 18px"><div><h2 class="sl-ct">Invoices to follow up</h2><span class="mut sm">You can see who owes and how long, and send reminders. Amounts stay hidden.</span></div><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'invF', []) as $__i => $x): ?><button class="<?= sl_v($x, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($x, 'label') ?></button><?php endforeach; ?></div></div>
<div class="sl-tw"><table class="sl-table"><thead><tr><th>Customer</th><th>Invoice</th><th>Parcels</th><th>Status</th><th>Days open</th><th>Amount</th><th></th></tr></thead><tbody><?php foreach ((array) sl_get($d, 'invs', []) as $__i => $r): ?><tr><td><div class="sl-cell"><span class="sl-av sm"><?= sl_v($r, 'ini') ?></span><span class="col"><span class="fw5"><?= sl_v($r, 'name') ?></span><span class="mut" style="font-size:12px"><?= sl_v($r, 'lock') ?></span></span></div></td><td class="mono sm"><?= sl_v($r, 'inv') ?></td><td class="sm"><?= sl_v($r, 'p') ?></td><td><span class="sl-pill <?= sl_v($r, 'pc') ?>"><?= sl_v($r, 'st') ?></span></td><td class="mono sm"><?= sl_v($r, 'days') ?></td><td><span class="fr-hid" title="Hidden for your role">₵ ••••</span></td><td><button class="sl-btn ghost sm" data-sl-on="click:remind"><?= sl_v($r, 'rl') ?></button></td></tr><?php endforeach; ?></tbody></table></div></section>
<aside class="b col g16"><section class="sl-card col g10"><h2 class="sl-ct">What this role can see</h2><?php foreach ((array) sl_get($d, 'perm', []) as $__i => $p): ?><div class="row g10 sm"><span class="fr-ok <?= sl_v($p, 'c') ?>"><i class="ic ic-sm <?= sl_v($p, 'i') ?>" aria-hidden="true"></i></span><span class="grow"><?= sl_v($p, 't') ?></span></div><?php endforeach; ?>
<div class="col g8" style="padding-top:10px;border-top:1px solid var(--line)"><b class="fw5 sm">Request access</b><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'durs', []) as $__i => $it_d): ?><button class="<?= sl_v($it_d, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($it_d, 'label') ?></button><?php endforeach; ?></div><input class="sl-in" value="Need totals to answer billing tickets" aria-label="Reason"><button class="sl-btn" data-sl-on="click:ask"><?= sl_v($d, 'askLabel') ?></button><span class="mut sm"><?= sl_v($d, 'askNote') ?></span></div></section>
<section class="sl-card col"><h2 class="sl-ct row g8" style="margin-bottom:4px"><span class="fr-dot"></span>Payments coming in</h2><?php foreach ((array) sl_get($d, 'feed', []) as $__i => $f): ?><div class="fr-fi <?= sl_v($f, 'cls') ?>"><span class="sl-av sm"><?= sl_v($f, 'ini') ?></span><span class="col grow"><span class="sm"><b class="fw5"><?= sl_v($f, 'name') ?></b> paid <?= sl_v($f, 'inv') ?></span><span class="mut" style="font-size:12px"><?= sl_v($f, 'how') ?> · amount hidden</span></span><span class="mono mut" style="font-size:12px"><?= sl_v($f, 'at') ?></span></div><?php endforeach; ?></section></aside></div>
<div class="grid cols3" style="gap:16px;align-items:start"><section class="sl-card col g10"><h2 class="sl-ct">How customers pay</h2><span class="mut sm">Share of payments by count, this month</span><?php foreach ((array) sl_get($d, 'methods', []) as $__i => $m): ?><div class="fr-bar"><span><?= sl_v($m, 'n') ?></span><i style="<?= sl_v($m, 'w') ?>"></i><b class="mono fw5"><?= sl_v($m, 'v') ?></b></div><?php endforeach; ?></section>
<section class="sl-card col g10"><h2 class="sl-ct">Parcels billed by service</h2><span class="mut sm">Count of parcels, this month</span><?php foreach ((array) sl_get($d, 'svcN', []) as $__i => $m): ?><div class="fr-bar"><span><?= sl_v($m, 'n') ?></span><i style="<?= sl_v($m, 'w') ?>"></i><b class="mono fw5"><?= sl_v($m, 'v') ?></b></div><?php endforeach; ?></section>
<section class="sl-card col g10"><h2 class="sl-ct">Billing tickets</h2><span class="mut sm">Questions about bills in the support inbox</span><div class="sl-kv"><div><span>Open</span><span class="mono">18</span></div><div><span>Waiting on finance</span><span class="mono">5</span></div><div><span>Refund requests</span><span class="mono">4</span></div><div><span>Solved this week</span><span class="mono">63</span></div><div><span>Avg time to solve</span><span class="mono">3h 10m</span></div></div><a class="sm fw5" href="<?= sl_route('CsInbox') ?>">Open billing tickets →</a></section></div>
