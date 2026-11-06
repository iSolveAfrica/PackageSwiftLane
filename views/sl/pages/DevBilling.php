<?php
/**
 * DevBilling - generated from docs/handover/artifact-source/DevBilling.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  periods                      as $p
 *   loop  chans                        as $c
 *   loop  c.bars                       as $b
 *   loop  feed                         as $e
 *   loop  months                       as $m
 *   loop  invF                         as $f
 *   loop  invs                         as $i
 *   loop  pays                         as $p
 *   value b, chans, dueAmt, dueNote, feed, flash, invF, invs, liveCls, liveLabel, months, msgs, paidAmt, paidN, payCount, pays, per, periods, tot
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="sl-head"><div><h1 class="sl-title">Usage &amp; Billing</h1><p class="sl-lede">SMS, e-mail and WhatsApp sent by Swift Lane, what each costs, and what has been paid</p><span class="live <?= sl_v($d, 'liveCls') ?>" style="margin-top:6px"><span class="ld"></span><?= sl_v($d, 'liveLabel') ?></span></div><div class="row g8 wrap"><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'periods', []) as $__i => $p): ?><button class="<?= sl_v($p, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($p, 'label') ?></button><?php endforeach; ?></div><a class="sl-btn ghost" href="<?= sl_route('Documents') ?>"><i class="ic ic-sm i-download" aria-hidden="true"></i>Export</a></div></div>
<div class="grid cols4">
<div class="ik"><div class="t">Total for <?= sl_v($d, 'per') ?></div><div class="v <?= sl_v($d, 'flash') ?>"><?= sl_v($d, 'tot') ?></div><div class="mut sm"><?= sl_v($d, 'msgs') ?> messages</div></div>
<div class="ik"><div class="t">Paid</div><div class="v" style="color:var(--green-ink)"><?= sl_v($d, 'paidAmt') ?></div><div class="mut sm"><?= sl_v($d, 'paidN') ?> invoices</div></div>
<div class="ik"><div class="t">Not paid</div><div class="v <?= sl_v($d, 'flash') ?>" style="color:var(--red-ink)"><?= sl_v($d, 'dueAmt') ?></div><div class="mut sm"><?= sl_v($d, 'dueNote') ?></div></div>
<div class="ik"><div class="t">Rates</div><div class="v" style="font-size:16px;line-height:1.6">SMS ₵0.030 · E-mail ₵0.002 · WhatsApp ₵0.120</div><a class="sm" href="<?= sl_route('CostSettings') ?>" style="color:var(--sky-ink)">Set in Settings › Operating costs</a></div>
</div>
<div class="grid cols3"><?php foreach ((array) sl_get($d, 'chans', []) as $__i => $c): ?><section class="chc"><div class="row between"><span class="row g8"><span class="sl-tile"><i class="ic <?= sl_v($c, 'icon') ?>" aria-hidden="true"></i></span><b class="fw5"><?= sl_v($c, 'name') ?></b></span><span class="paid <?= sl_v($c, 'pc') ?>"><i class="ic ic-sm <?= sl_v($c, 'pi') ?>" aria-hidden="true"></i><?= sl_v($c, 'status') ?></span></div>
<div class="amt <?= sl_v($c, 'flash') ?>"><?= sl_v($c, 'amt') ?></div><div class="row between sm"><span class="mut"><?= sl_v($c, 'n') ?> sent × <?= sl_v($c, 'rate') ?></span><span class="mut"><?= sl_v($c, 'prov') ?></span></div>
<div class="minib" aria-hidden="true"><?php foreach ((array) sl_get($c, 'bars', []) as $__i => $b): ?><i style="<?= sl_v($b, '') ?>"></i><?php endforeach; ?></div><div class="row between mut sm"><span><?= sl_v($c, 'from') ?></span><span><?= sl_v($c, 'to') ?></span></div>
<div class="kvr"><span class="mut">Delivered</span><span><?= sl_v($c, 'dl') ?></span></div><div class="kvr"><span class="mut">Failed (not charged)</span><span><?= sl_v($c, 'fail') ?></span></div><div class="kvr"><span class="mut">Invoice</span><span class="mono"><?= sl_v($c, 'inv') ?></span></div></section><?php endforeach; ?></div>
<section class="sl-card col g4"><div class="sl-ch" style="margin:0"><h2 class="sl-ct">Messages going out now</h2><span class="live <?= sl_v($d, 'liveCls') ?>"><span class="ld"></span>Live</span></div><div class="feed"><?php foreach ((array) sl_get($d, 'feed', []) as $__i => $e): ?><div class="fe <?= sl_v($e, 'anim') ?>"><span class="ico <?= sl_v($e, 'c') ?>"><i class="ic ic-sm <?= sl_v($e, 'icon') ?>" aria-hidden="true"></i></span><span class="col"><span><?= sl_v($e, 't') ?></span><span class="mut sm"><?= sl_v($e, 'sub') ?></span></span><span class="mono sm"><?= sl_v($e, 'cost') ?></span></div><?php endforeach; ?></div></section>
<section class="sl-card flush"><div class="row between" style="padding:14px 16px"><div><h2 class="sl-ct">Usage by month</h2><div class="mut sm">Amount per channel, and whether that month is paid</div></div></div><div class="sl-tw"><table class="sl-table"><thead><tr><th>Month</th><th class="num">SMS</th><th class="num">E-mail</th><th class="num">WhatsApp</th><th class="num">Total</th><th>Status</th></tr></thead><tbody>
<?php foreach ((array) sl_get($d, 'months', []) as $__i => $m): ?><tr><td class="fw5"><?= sl_v($m, 'm') ?></td><td class="num"><span class="col"><span><?= sl_v($m, 'sms') ?></span><span class="mut sm"><?= sl_v($m, 'smsN') ?></span></span></td><td class="num"><span class="col"><span><?= sl_v($m, 'mail') ?></span><span class="mut sm"><?= sl_v($m, 'mailN') ?></span></span></td><td class="num"><span class="col"><span><?= sl_v($m, 'wa') ?></span><span class="mut sm"><?= sl_v($m, 'waN') ?></span></span></td><td class="num fw5"><?= sl_v($m, 'tot') ?></td><td><span class="paid <?= sl_v($m, 'pc') ?>"><?= sl_v($m, 'status') ?></span></td></tr><?php endforeach; ?></tbody></table></div></section>
<div class="split"><section class="a sl-card flush"><div class="row between wrap g8" style="padding:14px 16px"><h2 class="sl-ct">Invoices</h2><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'invF', []) as $__i => $f): ?><button class="<?= sl_v($f, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($f, 'label') ?></button><?php endforeach; ?></div></div><div class="sl-tw"><table class="sl-table"><thead><tr><th>Invoice</th><th>Period</th><th>Channels</th><th class="num">Amount</th><th>Due</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ((array) sl_get($d, 'invs', []) as $__i => $i): ?><tr><td class="mono sm fw5"><?= sl_v($i, 'no') ?></td><td><?= sl_v($i, 'per') ?></td><td class="mut sm"><?= sl_v($i, 'ch') ?></td><td class="num"><?= sl_v($i, 'amt') ?></td><td class="mut"><?= sl_v($i, 'due') ?></td><td><span class="paid <?= sl_v($i, 'pc') ?>"><?= sl_v($i, 'status') ?></span></td><td><a class="sl-ib" aria-label="Download <?= sl_v($i, 'no') ?>" href="<?= sl_route('Documents') ?>"><i class="ic ic-sm i-download" aria-hidden="true"></i></a></td></tr><?php endforeach; ?></tbody></table></div></section>
<aside class="b sl-card col"><div class="sl-ch"><h2 class="sl-ct">Payment history</h2><span class="mut sm"><?= sl_v($d, 'payCount') ?> payments</span></div>
<?php foreach ((array) sl_get($d, 'pays', []) as $__i => $p): ?><div class="kvr" style="min-height:56px"><span class="col"><span class="fw5"><?= sl_v($p, 'amt') ?></span><span class="mut sm"><?= sl_v($p, 'inv') ?> · <?= sl_v($p, 'method') ?></span></span><span class="col" style="align-items:flex-end"><span class="paid <?= sl_v($p, 'pc') ?>"><?= sl_v($p, 'status') ?></span><span class="mut sm"><?= sl_v($p, 'date') ?></span></span></div><?php endforeach; ?></aside></div>
<section class="sl-card flush"><div class="row between wrap g8" style="padding:16px 18px"><div><h2 class="sl-ct">Other costs</h2><span class="mut sm">Rent, staff, fuel and more, added in Settings › Operating Costs</span></div><a class="sl-btn ghost sm" href="<?= sl_route('CostSettings') ?>"><i class="ic ic-sm i-edit" aria-hidden="true"></i>Manage costs</a></div>
<div class="sl-tw"><table class="sl-table"><thead><tr><th>Cost</th><th>Category</th><th>How often</th><th class="num">This month</th><th>Status</th></tr></thead><tbody>
<tr><td class="fw5">Hub rent, Spintex Rd</td><td>Rent</td><td>Monthly</td><td class="num mono">₵9,500.00</td><td><span class="sl-pill paid">Paid</span></td></tr>
<tr><td class="fw5">Staff salaries</td><td>Staff</td><td>Monthly</td><td class="num mono">₵64,200.00</td><td><span class="sl-pill pending">Due Nov 28</span></td></tr>
<tr><td class="fw5">Van fuel</td><td>Fuel</td><td>Monthly</td><td class="num mono">₵7,800.00</td><td><span class="sl-pill paid">Paid</span></td></tr>
<tr><td class="fw5">Customs broker fees</td><td>Customs</td><td>Monthly</td><td class="num mono">₵5,400.00</td><td><span class="sl-pill pending">Not paid</span></td></tr>
<tr><td class="fw5">Accounting software</td><td>Software</td><td>Yearly · ₵3,600</td><td class="num mono">₵300.00</td><td><span class="sl-pill paid">Paid</span></td></tr>
<tr><td class="fw5">Total other costs</td><td></td><td></td><td class="num mono fw5">₵87,200.00</td><td></td></tr></tbody></table></div></section>
