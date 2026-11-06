<?php
/**
 * BalanceDetail - one customer's bills in the customer balance report (report_customers_balance_detail.php),
 * replacing the old Bootstrap view. Same query: cdp_fsBillingSummary(customer_id, range) from helpers/fs_reports.php.
 */
$range = $d['from'] !== '' && $d['to'] !== '' ? $d['from'] . ' – ' . $d['to'] : 'All dates';
?>
<div class="row g8 mut sm"><a href="<?= h(sl_route('Reports')) ?>">Reports</a><i class="ic ic-sm i-right" aria-hidden="true"></i><a href="report_view.php?r=report_customers_balance_list">Customer balances</a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)"><?= h($d['customer']) ?></span></div>
<div class="sl-head">
  <div><h1 class="sl-title">Customer balance · <?= h($d['customer']) ?></h1><p class="sl-lede"><?= $d['locker'] ? 'Locker <span class="mono">' . h($d['locker']) . '</span> · ' : '' ?><?= h($range) ?></p></div>
  <div class="row g8 wrap"><a class="sl-btn ghost" href="report_view.php?r=report_customers_balance_list"><i class="ic ic-sm i-left" aria-hidden="true"></i>Back to the report</a><?php if ($d['customerId']): ?><a class="sl-btn ghost" href="<?= h(sl_route('CustomerProfile', ['id' => $d['customerId']])) ?>"><i class="ic ic-sm i-user" aria-hidden="true"></i>Customer</a><?php endif; ?><button class="sl-btn ghost" type="button" data-sl-print><i class="ic ic-sm i-print" aria-hidden="true"></i>Print</button></div>
</div>
<div class="grid cols3">
  <div class="sl-card sl-metric"><div class="sl-ml"><span>Billed</span></div><div class="big"><?= h(sl_money($d['sum']['total'])) ?></div><div class="sl-mf"><?= count($d['rows']) ?> bill<?= count($d['rows']) === 1 ? '' : 's' ?></div></div>
  <div class="sl-card sl-metric"><div class="sl-ml"><span>Paid</span></div><div class="big"><?= h(sl_money($d['sum']['paid'])) ?></div><div class="sl-ticks g"><i style="width:<?= sl_pct($d['sum']['paid'], $d['sum']['total']) ?>%"></i></div></div>
  <div class="sl-card sl-metric"><div class="sl-ml"><span>Balance due</span></div><div class="big"><?= h(sl_money($d['sum']['balance'])) ?></div><div class="sl-ticks r"><i style="width:<?= sl_pct($d['sum']['balance'], $d['sum']['total']) ?>%"></i></div></div>
</div>
<section class="sl-card flush">
  <div class="sl-tw"><table class="sl-table">
    <thead><tr><th>Consolidation</th><th>Date</th><th class="num">Discount</th><th>Status</th><th class="num">Amount</th><th class="num">Paid</th><th class="num">Balance</th></tr></thead>
    <tbody>
    <?php foreach ($d['rows'] as $r): ?>
      <tr><td class="fw5 mono"><a href="financial_sheet_consolidation.php?id=<?= (int) $r['id'] ?>"><?= h($r['ref']) ?></a></td><td class="mono sm"><?= h($r['date']) ?></td><td class="num mono"><?= $r['discount'] > 0 ? h(sl_money($r['discount'])) : '—' ?></td><td><?= sl_pill($r['status'], $r['cls']) ?></td><td class="num mono"><?= h(sl_money($r['amount'])) ?></td><td class="num mono"><?= h(sl_money($r['paid'])) ?></td><td class="num mono fw5"><?= h(sl_money($r['balance'])) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$d['rows']): ?><tr><td colspan="7"><div class="sl-empty"><span class="sl-tile"><i class="ic i-receipt" aria-hidden="true"></i></span>No bills for this customer in this period.</div></td></tr><?php else: ?>
      <tr><td class="fw5">Total</td><td colspan="3"></td><td class="num mono fw5"><?= h(sl_money($d['sum']['total'])) ?></td><td class="num mono fw5"><?= h(sl_money($d['sum']['paid'])) ?></td><td class="num mono fw5"><?= h(sl_money($d['sum']['balance'])) ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table></div>
</section>
