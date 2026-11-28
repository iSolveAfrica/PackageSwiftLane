<?php
/**
 * Rider app · Help and contact (design: M-RChat). Data: sl_riderChatData() in views/sl/data/rider_money.php.
 * The system has no rider chat (the support desk is for customers), so this screen gives the real ways to
 * reach dispatch and the customers of the rider's open jobs: phone, WhatsApp and SMS on the rider's own phone.
 */
if (!isset($d)) { $d = []; }
echo sl_rmOpen('Help and contact', 'Dispatch and your customers', ['back' => sl_route('RiderAccount')], $d['isRider'] ? sl_mxIcon('shield', 'Safety', sl_route('RiderSafety')) : '', true);
if (!$d['isRider']) { echo sl_rmNotRider(), sl_rmClose(); return; }
$msg = fn(string $text, string $wa, string $sms) => $wa !== '' ? $wa . '?text=' . rawurlencode($text) : ($sms !== '' ? $sms . '?&body=' . rawurlencode($text) : '');
?>
<div class="mxp-grid">
<div class="mxp-col">
<?= sl_mxSec('Dispatch') ?>
<?php if ($d['dispatch'] || $d['email'] !== ''): ?>
<?php foreach ($d['dispatch'] as $c): ?>
<section class="rd-cj" aria-label="<?= h($c['label']) ?>">
  <div class="rd-cjh"><span class="mx-li"><i class="ic i-truck" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($c['label']) ?></b><small><?= h($c['phone']) ?></small></span><a class="rd-call" href="<?= h($c['tel']) ?>" aria-label="Call <?= h($c['label']) ?>"><i class="ic i-phone" aria-hidden="true"></i></a></div>
  <div class="mx-two"><a class="mx-btn sec" href="<?= h($c['tel']) ?>"><i class="ic ic-sm i-phone" aria-hidden="true"></i>Call</a><?php if ($c['wa'] !== ''): ?><a class="mx-btn sec" href="<?= h($c['wa']) ?>" target="_blank" rel="noopener"><i class="ic ic-sm i-message" aria-hidden="true"></i>WhatsApp</a><?php else: ?><a class="mx-btn sec" href="sms:<?= h(preg_replace('/[^0-9+]/', '', $c['phone'])) ?>"><i class="ic ic-sm i-message" aria-hidden="true"></i>Text</a><?php endif; ?></div>
</section>
<?php endforeach; ?>
<?php if ($d['email'] !== ''): ?><section class="mx-card mx-list"><?= sl_mxRow('mail', 'Email the office', $d['email'], '<i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i>', 'mailto:' . $d['email']) ?></section><?php endif; ?>
<?php else: ?>
<?= sl_mxEmpty('phone', 'No dispatch number is set', 'The office has not added a phone number in the company settings yet. Ask your supervisor for the number to call.') ?>
<?php endif; ?>
<p class="mx-note">There is no in-app chat yet. Calls and messages use your own phone and number.</p>
<?= sl_mxSec('More help') ?>
<section class="mx-card mx-list">
  <?= sl_mxRow('shield', 'Safety', 'Emergency numbers and your emergency contact', '<i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i>', sl_route('RiderSafety')) ?>
  <?= sl_mxRow('card', 'Wallet', 'Balance, payout requests and history', '<i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i>', sl_route('RiderWallet')) ?>
  <?= sl_mxRow('bell', 'Notifications', 'Messages the office sent you', '<i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i>', sl_route('Notifications')) ?>
</section>
</div>
<div class="mxp-col">
<?= sl_mxSec('Customers on your open jobs', $d['stops'] ? 'All jobs' : '', sl_route('RiderQueue')) ?>
<?php if (!$d['stops']): ?>
<?= sl_mxEmpty('user', 'No open jobs', 'When a job is assigned to you, its customer appears here so you can call or message them.') ?>
<?php endif; ?>
<?php foreach ($d['stops'] as $i => $s): $sms = $s['tel'] !== '' ? 'sms:' . substr($s['tel'], 4) : ''; ?>
<section class="rd-cj">
  <div class="rd-cjh"><span class="mx-ava"><?= h($s['ini']) ?></span><span class="mx-rm"><b><?= h($s['name']) ?></b><small><?= h($s['addr'] !== '' ? $s['addr'] : 'No address on the job') ?></small></span><?php if ($s['tel'] !== ''): ?><a class="rd-call" href="<?= h($s['tel']) ?>" aria-label="Call <?= h($s['name']) ?>"><i class="ic i-phone" aria-hidden="true"></i></a><?php endif; ?></div>
  <div class="rd-cjm"><span><i class="ic ic-sm i-box" aria-hidden="true"></i><a href="<?= h($s['href']) ?>"><b><?= h($s['ref']) ?></b></a></span><span class="sl-pill <?= h($s['cls']) ?>"><?= h($s['state']) ?></span><?php if ($s['tel'] === ''): ?><span><i class="ic ic-sm i-alert" aria-hidden="true"></i> No phone number on this job</span><?php endif; ?></div>
  <?php if ($s['wa'] !== '' || $sms !== ''): ?>
  <div class="rd-qrs rm-qrs" role="group" aria-label="Quick messages to <?= h($s['name']) ?>"><?php foreach ($d['quick'] as $q): ?><a class="rd-qr" href="<?= h($msg($q, $s['wa'], $sms)) ?>"<?= $s['wa'] !== '' ? ' target="_blank" rel="noopener"' : '' ?>><?= h($q) ?></a><?php endforeach; ?></div>
  <small class="mx-note"><?= $s['wa'] !== '' ? 'Opens WhatsApp with the message ready to send.' : 'Opens your text messages with the message ready to send.' ?></small>
  <?php endif; ?>
</section>
<?php endforeach; ?>
</div>
</div>
<?= sl_rmClose() ?>
