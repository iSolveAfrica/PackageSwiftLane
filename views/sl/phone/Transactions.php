<?php
/**
 * M-Trans: transactions on a phone (transactions.php, finance on). Same $d as the web page
 * (views/sl/data/finance.php sl_transactionsData): range, q, page.
 */
require_once CDP_ROOT . '/views/sl/data/phone_people.php';
$slPhone['nav'] = 'staff';
$range = (string) ($_GET['range'] ?? '30d');
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-people.css')) ?>">
<?= sl_phoneTop('Transactions', $d['rangeLabel'] ?? '', ['back' => sl_route('BillingHub')], sl_phoneIcon('download', 'Export transactions (CSV)', sl_url(['export' => 'csv']))) ?>
<main class="mx-body">
<form class="mx-srch" method="get" role="search">
  <input type="hidden" name="range" value="<?= h($range) ?>">
  <label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q']) ?>" aria-label="Receipt, customer or reference" placeholder="Receipt, customer or reference" autocomplete="off" enterkeyhint="search"></label>
</form>
<nav class="mx-seg" aria-label="Period">
<?php foreach ($d['ranges'] as $r): ?>
  <a class="<?= h($r['cls']) ?>" href="<?= h(sl_url(['range' => $r['f'], 'page' => null])) ?>"<?= $r['cls'] === 'on' ? ' aria-current="true"' : '' ?>><?= h($r['label']) ?></a>
<?php endforeach; ?>
</nav>
<?php if ($d['rows']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['rows'] as $r): ?>
  <div class="mx-row"><?php if ($r['neg']): ?><span class="mx-li"><i class="ic i-reply" aria-hidden="true"></i></span><?php else: ?><span class="mx-ava"><?= h(sl_initials($r['name'])) ?></span><?php endif; ?><span class="mx-rm"><b><?= h($r['neg'] ? 'Refund · ' . $r['name'] : $r['name']) ?></b><small><?= h($r['method']) ?><?= $r['inv'] !== '' ? ' · ' . h($r['inv']) : '' ?></small></span><span class="mx-rr"><b><?= h($r['amt']) ?></b><small><?= h($r['at']) ?></small><?php if ($r['st'] !== 'Settled'): ?><span class="sl-pill <?= $r['pill'] === 'bad' ? 'overdue' : 'pending' ?>"><?= h($r['st']) ?></span><?php endif; ?></span></div>
<?php endforeach; ?>
</section>
<?= sl_ppPager((int) $d['page'], (int) $d['per'], (int) $d['count']) ?>
<?php else: ?>
<?= sl_mxEmpty('activity', 'No transactions', $d['q'] !== '' ? 'Nothing found for “' . $d['q'] . '”.' : 'No payment in ' . strtolower($d['rangeLabel']) . '.') ?>
<?php endif; ?>
<section class="mx-card mx-kv">
  <div><span>Money in</span><b><?= h($d['in']['v']) ?></b></div>
  <div><span>Refunds and reversals</span><b><?= h($d['refunds']['v']) ?></b></div>
  <div><span>Unsettled with gateways</span><b><?= h($d['unsettled']['v']) ?></b></div>
  <div><span>Average per day</span><b><?= h($d['avg']) ?></b></div>
</section>
</main>
