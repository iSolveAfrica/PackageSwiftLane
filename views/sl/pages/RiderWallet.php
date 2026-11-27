<?php
/**
 * Rider app · Wallet (design: M-RWallet). Data: sl_riderWalletData() in views/sl/data/rider_money.php.
 * The design's instant cash-out to mobile money does not exist: the rider sends a payout request that staff
 * approve and mark paid (ajax/sl/rider_app_ajax.php). Cash on delivery amounts are not stored, so the
 * "cash on hand" block is left out.
 */
if (!isset($d)) { $d = []; }
$ajax = 'ajax/sl/rider_app_ajax.php';
echo sl_rmOpen('Wallet', 'Paid by the office', ['back' => sl_route('RiderEarnings')], $d['isRider'] ? sl_mxIcon('message', 'Help and contact', sl_route('RiderChat')) : '', true);
if (!$d['isRider']) { echo sl_rmNotRider(), sl_rmClose(); return; }
$a = $d['account'];
$o = $d['open'];
?>
<div class="mxp-grid">
<div class="mxp-col">
<section class="mx-card rc-bal" aria-label="Balance">
  <div><small>Owed to you</small><b class="rc-big"><?= h($d['owed']) ?></b><span class="rc-bs"><span><?= h($d['available']) ?> you can ask for</span><?php if ($o): ?><span><?= h($o['amount']) ?> requested</span><?php endif; ?><?php if ($d['pendingRaw'] > 0): ?><span><?= h($d['pending']) ?> settled, not paid yet</span><?php endif; ?></span></div>
  <span class="rc-bi"><i class="ic i-card" aria-hidden="true"></i></span>
</section>
<section class="mx-k c2">
  <?= sl_mxKpi('trend', 'Earned in total', $d['earned'], 'Your share of finished trips') ?>
  <?= sl_mxKpi('check', 'Paid in total', $d['paid'], 'Settlements and payouts') ?>
  <?= sl_mxKpi('calendar', 'Paid this month', $d['paidMonth'], $d['paidMonthN'] . ($d['paidMonthN'] === 1 ? ' payment' : ' payments'), $d['paidMonthN'] ? 'up' : '') ?>
  <?= sl_mxKpi('clock', 'Payout request', $o ? $o['amount'] : 'None', $o ? $o['label'] : 'Nothing waiting', $o ? 'warn' : '') ?>
</section>
<?php if (!$d['ready']): ?>
<div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>Payout requests are not set up on this system yet. Ask the office to run the database upgrade.</span></div>
<?php elseif ($o): ?>
<section class="mx-card rc-co" aria-label="Your payout request">
  <div class="rc-coh"><b>Your payout request</b><span class="sl-pill <?= h($o['cls']) ?>"><?= h($o['label']) ?></span></div>
  <div class="rc-coa"><small>Amount</small><b><?= h($o['amount']) ?></b></div>
  <div class="rc-ml"><div><span>Asked</span><b><?= h($o['at']) ?></b></div><?php if ($o['note'] !== ''): ?><div><span>Your note</span><b class="rm-wrap"><?= h($o['note']) ?></b></div><?php endif; ?><?php if ($o['staffNote'] !== ''): ?><div><span>Office note</span><b class="rm-wrap"><?= h($o['staffNote']) ?></b></div><?php endif; ?></div>
  <?php if ($o['status'] === 'requested'): ?><button type="button" class="mx-btn sec" data-sl-action="<?= h($ajax) ?>" data-p-op="payout_cancel" data-p-id="<?= (int) $o['id'] ?>" data-sl-confirm="Cancel this payout request?" data-sl-reload>Cancel request</button><?php endif; ?>
  <p class="mx-note">The office pays you <?= $a['set'] ? 'to your payout account' : 'the way you agreed' ?> and marks the request paid here. You can send a new request after this one is answered.</p>
</section>
<?php elseif ($d['availableRaw'] >= 0.01): ?>
<section class="mx-card rc-co" aria-label="Request a payout"><form data-sl-form data-sl-reload action="<?= h($ajax) ?>" method="post">
  <input type="hidden" name="op" value="payout_request">
  <div class="rc-coh"><b>Request a payout</b><span class="sl-pill plain">Up to <?= h($d['available']) ?></span></div>
  <label class="mx-f"><small>Amount<?= $d['symbol'] !== '' ? ' (' . h($d['symbol']) . ')' : '' ?></small><input class="mx-in rm-amount" id="rm-amount" name="amount" inputmode="decimal" autocomplete="off" required value="<?= h($d['availableInput']) ?>" aria-describedby="rm-amount-help"></label>
  <?php if (count($d['chips']) > 1): ?><div class="rc-chips" data-mx-pick="#rm-amount" role="group" aria-label="Quick amounts"><?php foreach ($d['chips'] as $i => $c): ?><button type="button" class="rc-chip<?= $i === 0 ? ' on' : '' ?>" data-value="<?= h($c['v']) ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>"><?= h($c['l']) ?></button><?php endforeach; ?></div><?php endif; ?>
  <label class="mx-f"><small>Note to the office (optional)</small><input class="mx-in" name="note" maxlength="255" placeholder="For example: please pay before Friday"></label>
  <div class="rc-to"><span class="rc-momo"><i class="ic i-<?= $a['method'] === 'bank' ? 'card' : ($a['method'] === 'cash' ? 'dollar' : 'phone') ?>" aria-hidden="true"></i></span><span class="mx-rm"><?php if ($a['set']): ?><b><?= h($a['label'] . ($a['provider'] !== '' ? ' · ' . $a['provider'] : '') . ($a['masked'] !== '' ? ' · ' . $a['masked'] : '')) ?></b><small><?= h($a['name'] !== '' ? $a['name'] : 'Collect from the office') ?></small><?php else: ?><b>No payout account saved</b><small><a class="mx-lnk" href="<?= h(sl_route('RiderAccount')) ?>#payout">Add one in Account</a> so the office knows where to pay</small><?php endif; ?></span></div>
  <button type="submit" class="mx-btn rs-amb"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send payout request</button>
  <p class="mx-note" id="rm-amount-help">This sends a request to the office. No money moves automatically: staff pay you and mark it paid.</p>
</form></section>
<?php else: ?>
<?= sl_mxEmpty('card', 'Nothing to pay out yet', 'When you finish trips, your share is added here and you can ask the office to pay it.', 'Open my jobs', sl_route('RiderQueue')) ?>
<?php endif; ?>
</div>
<div class="mxp-col">
<div class="mx-sec"><h2>Payout account</h2><a href="<?= h(sl_route('RiderAccount')) ?>#payout"><?= $a['set'] ? 'Change' : 'Add' ?></a></div>
<section class="mx-card mx-list">
<?php if ($a['set']): ?>
  <?= sl_mxRow($a['method'] === 'bank' ? 'card' : ($a['method'] === 'cash' ? 'dollar' : 'phone'), $a['label'] . ($a['provider'] !== '' ? ' · ' . $a['provider'] : '') . ($a['masked'] !== '' ? ' · ' . $a['masked'] : ''), $a['name'] !== '' ? $a['name'] . ' · as you entered it' : 'You collect your pay at the office', '<i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i>', sl_route('RiderAccount') . '#payout') ?>
<?php else: ?>
  <?= sl_mxRow('plus', 'Add a payout account', 'Mobile money, bank or cash at the office', '<i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i>', sl_route('RiderAccount') . '#payout') ?>
<?php endif; ?>
</section>
<?= sl_mxSec('Payout history') ?>
<?php if ($d['history']): ?>
<section class="mx-card mx-list rm-hist"><?php foreach ($d['history'] as $x): ?><div class="mx-row"><span class="mx-li"><i class="ic i-<?= h($x['icon']) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($x['title']) ?></b><small><?= h($x['sub']) ?></small><?php if ($x['note'] !== ''): ?><small><?= h($x['note']) ?></small><?php endif; ?><small class="mono"><?= h($x['ref']) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($x['cls']) ?>"><?= h($x['pill']) ?></span><b><?= h($x['amount']) ?></b></span></div><?php endforeach; ?></section>
<?php else: ?>
<?= sl_mxEmpty('download', 'No payouts yet', 'Settlements the office records and your payout requests are listed here.') ?>
<?php endif; ?>
<div class="mx-ban info"><i class="ic ic-sm i-shield" aria-hidden="true"></i><span>Money question? <a class="mx-lnk" href="<?= h(sl_route('RiderChat')) ?>">Contact the office</a>. Nobody from the office will ever ask for your mobile money PIN.</span></div>
</div>
</div>
<?= sl_rmClose() ?>
