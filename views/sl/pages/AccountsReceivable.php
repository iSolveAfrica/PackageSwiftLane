<?php
/**
 * AccountsReceivable - generated from docs/handover/artifact-source/AccountsReceivable.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  rows                         as $r
 *   value rows
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
        <div class="sl-head">
          <div><h1 class="sl-title">Accounts Receivable</h1><p class="sl-lede">$6,840 outstanding across 23 invoices</p></div>
          <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('Documents') ?>"><i class="ic ic-sm i-download" aria-hidden="true"></i>Export</a><a class="sl-btn" href="<?= sl_route('ComposeMessage') ?>"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send Reminders</a></div>
        </div>
        <div class="grid cols4">
          <div class="sl-card sl-metric"><div class="sl-ml"><span>Current</span></div><div class="sl-mv">$3,120 <i>(46%)</i></div><div class="sl-ticks"><i style="width: 46%"></i></div></div>
          <div class="sl-card sl-metric"><div class="sl-ml"><span>1–30 days</span></div><div class="sl-mv">$2,010 <i>(29%)</i></div><div class="sl-ticks y"><i style="width: 29%"></i></div></div>
          <div class="sl-card sl-metric"><div class="sl-ml"><span>31–60 days</span></div><div class="sl-mv">$980 <i>(14%)</i></div><div class="sl-ticks o"><i style="width: 14%"></i></div></div>
          <div class="sl-card sl-metric"><div class="sl-ml"><span>60+ days</span></div><div class="sl-mv">$730 <i>(11%)</i></div><div class="sl-ticks r"><i style="width: 11%"></i></div></div>
        </div>
        <section class="sl-card flush">
          <div class="row wrap g12" style="padding: 14px 16px"><div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input aria-label="Search invoices" placeholder="Search invoice or customer"></div><div class="sl-seg boxed"><button class="on">All</button><button>Pending</button><button>Overdue</button><button>Paid</button></div></div>
          <div class="sl-tw"><table class="sl-table"><thead><tr><th>Invoice</th><th>Customer</th><th>Issued</th><th>Due</th><th class="num">Amount</th><th class="num">Balance</th><th>Status</th><th></th></tr></thead><tbody>
            <?php foreach ((array) sl_get($d, 'rows', []) as $__i => $r): ?><tr><td class="mono sm fw5"><?= sl_v($r, 'inv') ?></td><td><div class="sl-cell"><span class="sl-av sm"><?= sl_v($r, 'ini') ?></span><?= sl_v($r, 'name') ?></div></td><td><?= sl_v($r, 'issued') ?></td><td><?= sl_v($r, 'due') ?></td><td class="num"><?= sl_v($r, 'amt') ?></td><td class="num fw5"><?= sl_v($r, 'bal') ?></td><td><span class="sl-pill <?= sl_v($r, 'cls') ?>"><?= sl_v($r, 'st') ?></span></td><td><a class="sl-btn ghost sm" href="<?= sl_route('ComposeMessage') ?>">Remind</a></td></tr><?php endforeach; ?>
          </tbody></table></div>
          <div class="sl-pager"><span>Showing 1–8 of 23 invoices</span><div class="sl-pg"><a class="on" href="<?= sl_route('AccountsReceivable') ?>">1</a><a href="<?= sl_route('AccountsReceivable') ?>">2</a><a href="<?= sl_route('AccountsReceivable') ?>">3</a></div></div>
        </section>
      
