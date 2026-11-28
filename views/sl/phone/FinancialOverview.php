<?php
/**
 * M-FinOverview: financial overview on a phone (financial_overview.php, finance on). Same $d as the web page
 * (views/sl/data/finance.php sl_financialOverviewData). Viewers without money rights get the restricted
 * board (FinancialOverviewRestricted.php includes this file): every amount is already hidden server-side
 * ($d['money'] false), and the invoices to follow up with reminders are shown instead.
 */
require_once CDP_ROOT . '/views/sl/data/phone_people.php';
$slPhone['nav'] = 'staff';
$money = !empty($d['money']);
$range = (string) ($_GET['range'] ?? '30d');
$segs = [['30d', 'Month'], ['90d', 'Quarter'], ['1y', 'Year'], ['7d', 'Week']];
// Month labels of the 12 bars (the same months as sl_financialOverviewData: the last 12 ending now).
$labels = [];
for ($i = 11; $i >= 0; $i--) $labels[] = date('M', strtotime("first day of -$i months"));
$months = array_slice($d['months'] ?? [], -6, 6, true);
$kpi = $d['kpi'];
$rev = $kpi['revenue']; $mar = $kpi['margin'];
$up = fn(string $s) => strpos($s, '+') === 0 ? 'up' : (strpos($s, '−') === 0 ? 'down' : '');
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-people.css')) ?>">
<?= sl_phoneTop('Financial overview', $d['rangeLabel'] ?? '', ['back' => sl_route('BillingHub')], $money ? sl_phoneIcon('activity', 'Transactions', sl_route('Transactions')) : '') ?>
<main class="mx-body">
<?php if (!$money): ?><div class="mx-ban info"><i class="ic ic-sm i-lock" aria-hidden="true"></i><span>Amounts are hidden for your role (<?= h($d['roleName']) ?>). You can see who owes, for how long, and send reminders.</span></div><?php endif; ?>
<nav class="mx-seg" aria-label="Period">
<?php foreach ($segs as [$f, $l]): ?>
  <a class="<?= $f === $range ? 'on' : '' ?>" href="<?= h(sl_url(['range' => $f])) ?>"<?= $f === $range ? ' aria-current="true"' : '' ?>><?= h($l) ?></a>
<?php endforeach; ?>
</nav>
<section class="mx-k c2">
  <div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-dollar" aria-hidden="true"></i></span><small>Revenue</small></div><b><?= h($rev['v']) ?></b><em class="<?= $up($rev['foot']) ?>"><?= h($rev['foot']) ?></em></div>
  <div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-chart" aria-hidden="true"></i></span><small>Profit</small></div><b><?= h($mar['v']) ?></b><em class="<?= $money && $mar['pct'] > 0 ? 'up' : '' ?>"><?= h($money ? trim($mar['sub'], '()') . ' · ' . $mar['foot'] : $mar['foot']) ?></em></div>
</section>
<section class="mx-card mx-bars" role="img" aria-label="Revenue by month, last 6 months">
  <div class="mx-bh"><b>Revenue by month</b></div>
  <div class="mx-bc"><?php foreach ($months as $i => $m): ?><span class="<?= $m['hi'] ? 'now' : '' ?>"<?= $m['tip'] !== '' ? ' title="' . h($m['tip']) . '"' : '' ?>><i style="<?= h($m['sty']) ?>"></i><small><?= h($labels[$i] ?? '') ?></small></span><?php endforeach; ?></div>
</section>
<div class="mx-sec"><h2>By service</h2></div>
<section class="mx-card mx-mts">
<?php foreach ($d['svc'] as $i => $s): $w = $d['svcN'][$i]['w'] ?? 'width:0%'; ?>
  <div class="mx-mt"><div><span><?= h($s['n']) ?> · <?= h($s['p']) ?> parcels</span><b><?= h($s['r']) ?></b></div><span class="mx-trk"><i class="<?= $i === 0 ? 'or' : '' ?>" style="<?= h($w) ?>"></i></span></div>
<?php endforeach; ?>
</section>
<section class="mx-card mx-kv">
<?php foreach ($d['counts'] as $c): ?>
  <div><span><?= h($c['l']) ?></span><b><?= h($c['v']) ?> <small><?= h($c['s']) ?></small></b></div>
<?php endforeach; ?>
</section>
<?php if (!$money): ?>
<div class="mx-sec"><h2>Invoices to follow up</h2></div>
<nav class="mx-seg" aria-label="Invoices"><?php foreach ($d['invF'] as $x): ?><a class="<?= h($x['cls']) ?>" href="<?= h(sl_url(['inv' => $x['f']])) ?>"><?= h($x['label']) ?></a><?php endforeach; ?></nav>
<?php if ($d['invs']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['invs'] as $r): ?>
  <div class="mx-row"><span class="mx-ava"><?= h($r['ini']) ?></span><span class="mx-rm"><b><?= h($r['name']) ?></b><small><?= h($r['inv']) ?> · <?= h($r['days']) ?> open</small></span><span class="mx-rr"><span class="sl-pill <?= h($r['pc']) ?>"><?= h($r['st']) ?></span><?php if ($user->cdp_hasPermission('view_receivable_accounts')): ?><button type="button" class="sl-pill plain" style="border:0;cursor:pointer;min-height:32px" data-sl-action="ajax/sl/ar_remind_ajax.php" data-p-op="one" data-p-kind="shipment" data-p-id="<?= (int) $r['id'] ?>" aria-label="Remind <?= h($r['name']) ?>">Remind</button><?php endif; ?></span></div>
<?php endforeach; ?>
</section>
<?php else: ?>
<?= sl_mxEmpty('check', 'No invoices to follow up') ?>
<?php endif; ?>
<?php endif; ?>
</main>
