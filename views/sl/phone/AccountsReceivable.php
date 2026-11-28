<?php
/**
 * M-AR: accounts receivable on a phone (accounts_receivable.php, finance on). Same $d as the web page
 * (views/sl/data/finance.php sl_arData): aging buckets, filter, q, page. Reminders: ajax/sl/ar_remind_ajax.php.
 */
require_once CDP_ROOT . '/views/sl/data/phone_people.php';
$slPhone['nav'] = 'staff';
$bar = ['' => 'ok', 'y' => 'warn', 'o' => 'warn', 'r' => 'bad'];
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-people.css')) ?>">
<?= sl_phoneTop('Accounts receivable', '', ['back' => sl_route('BillingHub')], sl_phoneIcon('download', 'Export receivables (CSV)', sl_url(['export' => 'csv']))) ?>
<main class="mx-body">
<section class="mx-k c2">
  <div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-receipt" aria-hidden="true"></i></span><small>Owed</small></div><b><?= h($d['total']) ?></b><em><?= sl_num($d['n']) ?> open invoice<?= $d['n'] === 1 ? '' : 's' ?></em></div>
  <div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-clock" aria-hidden="true"></i></span><small>Over 60 days</small></div><b><?= h($d['buckets'][3]['v']) ?></b><em class="<?= $d['overdueCustomers'] ? 'down' : '' ?>"><?= sl_num($d['overdueCustomers']) ?> customer<?= $d['overdueCustomers'] === 1 ? '' : 's' ?> overdue</em></div>
</section>
<div class="mx-sec"><h2>Age</h2><?php if ($d['filter'] !== 'all'): ?><a href="<?= h(sl_url(['filter' => null, 'page' => null])) ?>">Show all</a><?php endif; ?></div>
<section class="mx-card mx-mts">
<?php foreach ($d['buckets'] as $b): ?>
  <a class="mx-mt" href="<?= h(sl_url(['filter' => $b['f'], 'page' => null])) ?>" style="color:inherit"<?= $d['filter'] === $b['f'] ? ' aria-current="true"' : '' ?>><div><span><?= h($b['label']) ?><?= $d['filter'] === $b['f'] ? ' · shown' : '' ?></span><b><?= h($b['v']) ?></b></div><span class="mx-trk"><i class="<?= $bar[$b['cls']] ?? '' ?>" style="width:<?= max(1, (int) $b['pct']) ?>%"></i></span></a>
<?php endforeach; ?>
</section>
<form class="mx-srch" method="get" role="search">
  <?php if ($d['filter'] !== 'all'): ?><input type="hidden" name="filter" value="<?= h($d['filter']) ?>"><?php endif; ?>
  <label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q']) ?>" aria-label="Invoice, customer or locker" placeholder="Invoice, customer or locker" autocomplete="off" enterkeyhint="search"></label>
</form>
<nav class="mx-seg" aria-label="Status">
<?php foreach ($d['filters'] as $x): ?>
  <a class="<?= $x['f'] === $d['filter'] ? 'on' : '' ?>" href="<?= h(sl_url(['filter' => $x['f'] === 'all' ? null : $x['f'], 'page' => null])) ?>"><?= h($x['label']) ?></a>
<?php endforeach; ?>
</nav>
<?php if ($d['rows']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['rows'] as $r): ?>
  <a class="mx-row" href="<?= h($r['uid'] ? sl_route('CustomerProfile', ['id' => $r['uid']]) : $r['href']) ?>"><span class="mx-ava"><?= h($r['ini']) ?></span><span class="mx-rm"><b><?= h($r['name']) ?></b><small><?= h($r['inv']) ?> · <?= h($r['st']) ?></small></span><span class="mx-rr"><b><?= h($r['open'] ? $r['bal'] : $r['amt']) ?></b><small><?= h($r['kind']) ?></small></span></a>
<?php endforeach; ?>
</section>
<?= sl_ppPager((int) $d['page'], (int) $d['per'], (int) $d['count']) ?>
<?php else: ?>
<?= sl_mxEmpty('check', 'Nothing owed here', 'No invoice matches this view.') ?>
<?php endif; ?>
<?php if ($d['canRemind'] && $d['overdueCustomers']): ?>
<div class="mx-btns"><button type="button" class="mx-btn" data-sl-action="ajax/sl/ar_remind_ajax.php" data-p-op="overdue" data-sl-confirm="Send a payment reminder to the <?= (int) $d['overdueCustomers'] ?> customers with overdue invoices?">Remind all overdue (<?= (int) $d['overdueCustomers'] ?>)</button></div>
<?php endif; ?>
</main>
