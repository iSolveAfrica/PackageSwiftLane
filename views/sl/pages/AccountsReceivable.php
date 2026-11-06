<?php
/**
 * Accounts Receivable - from docs/handover/artifact-source/AccountsReceivable.dc.html.
 * Data: views/sl/data/finance.php (sl_arData). Reminder: ajax/sl/ar_remind_ajax.php.
 */
if (!isset($d)) { $d = []; }
?>
        <div class="sl-head">
          <div><h1 class="sl-title">Accounts Receivable</h1><p class="sl-lede"><?= h($d['total']) ?> outstanding across <?= sl_num($d['n']) ?> invoices</p></div>
          <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= h(sl_url(['export' => 'csv'])) ?>"><i class="ic ic-sm i-download" aria-hidden="true"></i>Export</a><?php if ($d['canRemind']): ?><button class="sl-btn" data-sl-action="ajax/sl/ar_remind_ajax.php" data-p-op="overdue" data-sl-confirm="Send a payment reminder to the <?= (int) $d['overdueCustomers'] ?> customers with overdue invoices?"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send Reminders</button><?php endif; ?></div>
        </div>
        <div class="grid cols4">
          <?php foreach ($d['buckets'] as $b): ?><a class="sl-card sl-metric" href="<?= h(sl_url(['filter' => $b['f'], 'page' => null])) ?>" style="color:inherit"><div class="sl-ml"><span><?= h($b['label']) ?></span></div><div class="sl-mv"><?= h($b['v']) ?> <i>(<?= (int) $b['pct'] ?>%)</i></div><div class="sl-ticks <?= h($b['cls']) ?>"><i style="width: <?= (int) $b['pct'] ?>%"></i></div></a><?php endforeach; ?>
        </div>
        <section class="sl-card flush">
          <form method="get" class="row wrap g12" style="padding: 14px 16px"><input type="hidden" name="filter" value="<?= h($d['filter']) ?>"><div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" value="<?= h($d['q']) ?>" aria-label="Search invoices" placeholder="Search invoice or customer"></div><div class="sl-seg boxed"><?php foreach ($d['filters'] as $x): ?><a class="<?= $x['f'] === $d['filter'] ? 'on' : '' ?>" href="<?= h(sl_url(['filter' => $x['f'], 'page' => null])) ?>"><?= h($x['label']) ?></a><?php endforeach; ?></div></form>
          <div class="sl-tw"><table class="sl-table"><thead><tr><th>Invoice</th><th>Customer</th><th>Issued</th><th>Due</th><th class="num">Amount</th><th class="num">Balance</th><th>Status</th><th></th></tr></thead><tbody>
            <?php foreach ($d['rows'] as $r): ?><tr><td class="mono sm fw5"><a href="<?= h($r['href']) ?>"><?= h($r['inv']) ?></a></td><td><div class="sl-cell"><span class="sl-av sm"><?= h($r['ini']) ?></span><?= h($r['name']) ?></div></td><td><?= h($r['issued']) ?></td><td><?= h($r['due']) ?></td><td class="num"><?= h($r['amt']) ?></td><td class="num fw5"><?= h($r['bal']) ?></td><td><span class="sl-pill <?= h($r['cls']) ?>"><?= h($r['st']) ?></span></td><td><?php if ($r['open'] && $d['canRemind']): ?><button class="sl-btn ghost sm" data-sl-action="ajax/sl/ar_remind_ajax.php" data-p-op="one" data-p-kind="<?= h($r['kind']) ?>" data-p-id="<?= (int) $r['id'] ?>">Remind</button><?php endif; ?></td></tr><?php endforeach; ?>
            <?php if (!$d['rows']): ?><tr><td colspan="8"><div class="sl-empty"><span class="sl-tile"><i class="ic i-receipt" aria-hidden="true"></i></span>No invoices match this filter.</div></td></tr><?php endif; ?>
          </tbody></table></div>
          <?= sl_pager($d['page'], $d['per'], $d['count'], 'invoices') ?>
        </section>
