<?php
/**
 * M-PayGw: payments by gateway, what reached the bank and the latest payments. Same $d as the web page
 * (views/sl/data/finance.php sl_paymentsData; GET range, q, filter, page). The finance switch and permissions
 * are checked by the controller before this renders. Gateway on/off settings stay on payment_mode_list.php.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
$k = array_column($d['kpi'], null, 'label');
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-centres.css')) ?>">
<?= sl_phoneTop('Payment gateways', $d['rangeLabel'], ['back' => 'menu.php'], $d['canRecord'] ? sl_phoneIcon('plus', 'Record payment', sl_route('RecordPayment')) : '') ?>
<main class="mx-body">
<div class="mx-ban info"><i class="ic ic-sm i-dollar" aria-hidden="true"></i><span>Part of Finance. Hidden everywhere when Finance is switched off.</span></div>
<div class="mx-seg" role="tablist" aria-label="Period">
<?php foreach ($d['ranges'] as $r): ?>  <a role="tab" aria-selected="<?= $r['cls'] === 'on' ? 'true' : 'false' ?>" class="<?= h($r['cls']) ?>" href="<?= h(sl_url(['range' => $r['f'], 'page' => null])) ?>"><?= h($r['label']) ?></a>
<?php endforeach; ?>
</div>
<?= sl_mxSec('By gateway', 'Gateways', 'payment_mode_list.php') ?>
<?php if ($d['gateways']): ?>
<section class="mx-card mx-list pc-wrap">
<?php foreach ($d['gateways'] as $g): ?>
<div class="mx-row"><span class="mx-li"><i class="ic i-card" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($g['name']) ?></b><small><?= h($g['sub']) ?> · fees <?= h($g['fees']) ?></small></span><span class="mx-rr"><b><?= h($g['amt']) ?></b></span></div>
<?php endforeach; ?>
</section>
<?php else: ?>
<?= sl_mxEmpty('card', 'No payments in this period') ?>
<?php endif; ?>
<section class="mx-card mx-kv">
<?php foreach (['Collected', 'Gateway fees', 'Net to bank', 'Failed'] as $l): if (!isset($k[$l])) continue; ?>
  <div><span><?= h($l) ?></span><b><?= h($k[$l]['value']) ?></b></div>
<?php endforeach; ?>
<?php if ($d['settlements']): $s = $d['settlements'][0]; ?>
  <div><span>Last payout</span><b><?= h($s['date']) ?> · <?= h($s['net']) ?></b></div>
<?php endif; ?>
</section>
<?= sl_mxSec('Latest payments') ?>
<form method="get"><input type="hidden" name="range" value="<?= h($_GET['range'] ?? '30d') ?>"><input type="hidden" name="filter" value="<?= h($d['filter']) ?>"><div class="mx-srch"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q']) ?>" aria-label="Search payments" placeholder="Reference, customer or package" autocomplete="off"></label></div></form>
<?php if ($d['rows']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['rows'] as $r): ?>
<a class="mx-row" href="<?= h($r['sender'] ? sl_route('CustomerProfile', ['id' => $r['sender']]) : $r['pkgHref']) ?>"><span class="mx-ava"><?= h($r['ini']) ?></span><span class="mx-rm"><b><?= h($r['name']) ?></b><small><?= h(implode(' · ', array_filter([$r['pkg'], $r['method'], $r['at']]))) ?></small></span><span class="mx-rr"><b><?= h($r['amt']) ?></b><span class="sl-pill <?= h($r['cls']) ?>"><?= h($r['st']) ?></span></span></a>
<?php endforeach; ?>
</section>
<?php if ($d['count'] > $d['page'] * $d['per']): ?><div class="pc-more"><a href="<?= h(sl_url(['page' => $d['page'] + 1])) ?>">Show more payments</a></div><?php endif; ?>
<?php else: ?>
<?= sl_mxEmpty('receipt', 'No payments found', $d['q'] !== '' ? 'Nothing matches “' . $d['q'] . '”.' : 'Payments received in this period appear here.') ?>
<?php endif; ?>
<?php if ($d['canRecord']): ?><div class="mx-btns"><a class="mx-btn" href="<?= h(sl_route('RecordPayment')) ?>">Record a payment</a></div><?php endif; ?>
</main>
