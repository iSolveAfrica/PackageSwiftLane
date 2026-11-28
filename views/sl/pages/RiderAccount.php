<?php
/**
 * Rider app · Account (design: M-RMe). Data: sl_riderAccountData() in views/sl/data/rider_money.php.
 * Identity and vehicle come from the rider's user record (staff edit them); the payout account, navigation
 * app and offer sounds are saved per rider in cdb_rider_prefs. Tiers, document expiry dates, auto-accept,
 * voice, dark map and delivery-type filters have no backend and are left out.
 */
if (!isset($d)) { $d = []; }
$ajax = 'ajax/sl/rider_app_ajax.php';
$bell = sl_mxIcon('bell', 'Notifications', sl_route('Notifications'), (int) ($d['unread'] ?? 0));
echo sl_rmOpen('Account', '', $d['isRider'] ? ['avatar' => $d['ini'], 'href' => sl_route('RiderAccount')] : ['back' => sl_route('Main')], $d['isRider'] ? $bell : '', true);
if (!$d['isRider']) { echo sl_rmNotRider(), sl_rmClose(); return; }
$p = $d['p'];
$a = $d['account'];
$ch = '<i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i>';
$vehLine = trim(($d['veh'] !== '' ? $d['veh'] : '') . ($d['plate'] !== '' ? ' · ' . $d['plate'] : ''), ' ·');
?>
<div class="mxp-grid">
<div class="mxp-col">
<a class="rd-pf" href="<?= h(sl_route('RiderRatings')) ?>"><span class="rd-ph"><?= h($d['ini']) ?><?php if ($d['active']): ?><i aria-hidden="true"><i class="ic ic-sm i-check" aria-hidden="true"></i></i><?php endif; ?></span><span class="mx-rm"><b><?= h($d['name']) ?></b><small><?= sl_num($p['done']) ?> trips in 90 days · rider since <?= h($d['since']) ?></small><small><?= $vehLine !== '' ? h($vehLine) : 'No vehicle on your record' ?></small></span><?php if ($d['zone'] !== ''): ?><span class="rd-tier rm-zone"><i class="ic ic-sm i-pin" aria-hidden="true"></i> <?= h($d['zone']) ?></span><?php endif; ?></a>
<section class="mx-k c3">
  <?= sl_mxKpi('check', 'Finished', (string) $p['done'], '90 days') ?>
  <?= sl_mxKpi('thumb', 'Completion', $p['finish'] !== null ? $p['finish'] . '%' : '—', 'of attempts') ?>
  <?= sl_mxKpi('clock', 'Handover', $d['handover'], 'door deliveries') ?>
</section>
<?= sl_mxSec('Your details') ?>
<section class="mx-card mx-list">
  <?= sl_mxRow('phone', $d['phone'] !== '' ? $d['phone'] : 'No phone number', 'Phone on your record') ?>
  <?php if ($d['email'] !== ''): ?><?= sl_mxRow('mail', $d['email'], 'Email on your record') ?><?php endif; ?>
  <?php foreach ($d['docs'] as $x): ?><?= sl_mxRow($x['icon'], $x['title'], $x['sub'], $x['pill'] !== '' ? '<span class="sl-pill ' . h($x['cls']) . '">' . h($x['pill']) . '</span>' : '') ?><?php endforeach; ?>
</section>
<p class="mx-note">The office keeps these details. To change your phone, vehicle or documents, <a class="mx-lnk rm-inl" href="<?= h(sl_route('RiderChat')) ?>">contact the office</a>. Licence, insurance and roadworthy documents are not stored here yet.</p>
<div id="payout"></div>
<?= sl_mxSec('Payout') ?>
<section class="mx-card mx-list">
  <?= sl_mxRow($a['method'] === 'bank' ? 'card' : ($a['method'] === 'cash' ? 'dollar' : 'phone'), $a['set'] ? $a['label'] . ($a['provider'] !== '' ? ' · ' . $a['provider'] : '') . ($a['masked'] !== '' ? ' · ' . $a['masked'] : '') : 'No payout account saved', $a['set'] ? ($a['name'] !== '' ? $a['name'] : 'You collect your pay at the office') : 'Add one so the office knows where to pay you') ?>
  <?= sl_mxRow('card', 'Wallet', 'Owed to you now', '<b>' . h($d['owed']) . '</b>', sl_route('RiderWallet')) ?>
</section>
<?php if ($d['ready']): ?>
<details class="rm-details"<?= $a['set'] ? '' : ' open' ?>><summary class="mx-btn sec"><i class="ic ic-sm i-<?= $a['set'] ? 'edit' : 'plus' ?>" aria-hidden="true"></i><?= $a['set'] ? 'Change payout account' : 'Add payout account' ?></summary>
<section class="mx-card mx-form"><form data-sl-form data-sl-reload action="<?= h($ajax) ?>" method="post">
  <input type="hidden" name="op" value="payout_account">
  <label class="mx-f"><small>How you want to be paid</small><select class="mx-in" name="method" data-rm-method><?php foreach ($a['labels'] as $k => $l): ?><option value="<?= h($k) ?>"<?= ($a['method'] ?: 'momo') === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></label>
  <div class="rm-fields" data-rm-method-fields<?= $a['method'] === 'cash' ? ' hidden' : '' ?>>
  <label class="mx-f"><small>Network or bank</small><input class="mx-in" name="provider" maxlength="60" value="<?= h($a['provider']) ?>" autocomplete="off"></label>
  <label class="mx-f"><small>Name on the account</small><input class="mx-in" name="name" maxlength="100" value="<?= h($a['name']) ?>" autocomplete="name"></label>
  <label class="mx-f"><small>Number<?= $a['masked'] !== '' ? ' · saved: ' . h($a['masked']) : '' ?></small><input class="mx-in" name="number" inputmode="tel" maxlength="40" value="" autocomplete="off" placeholder="<?= $a['masked'] !== '' ? 'Type the full number again' : '' ?>"></label>
  </div>
  <button type="submit" class="mx-btn">Save payout account</button>
  <p class="mx-note">The office uses this when it pays you. It is not checked with your network or bank, so type it carefully.</p>
</form></section></details>
<?php endif; ?>
</div>
<div class="mxp-col">
<?= sl_mxSec('Preferences') ?>
<?php if ($d['ready']): ?>
<section class="mx-card mx-form">
  <div class="mx-f"><small id="rm-nav-l">Navigation app</small><div class="mx-fseg" role="group" aria-labelledby="rm-nav-l" data-rm-pref="nav_app" data-rm-url="<?= h($ajax) ?>"><?php foreach (['google' => 'Google Maps', 'waze' => 'Waze'] as $k => $l): ?><button type="button" class="<?= $d['nav'] === $k ? 'on' : '' ?>" aria-pressed="<?= $d['nav'] === $k ? 'true' : 'false' ?>" data-value="<?= h($k) ?>"><?= h($l) ?></button><?php endforeach; ?></div></div>
</section>
<section class="mx-card mx-tgs"><?= sl_mxToggle('Offer sounds', 'Chime when a new job is offered to you', $d['sounds'], $ajax, 'offer_sounds') ?></section>
<?php else: ?><div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>Preferences are not set up on this system yet.</span></div><?php endif; ?>
<?= sl_mxSec('More') ?>
<section class="mx-card mx-list">
  <?= sl_mxRow('calendar', 'Shifts', $d['nextShift'] !== '' ? 'Next block ' . $d['nextShift'] : 'No blocks booked', $ch, sl_route('RiderShifts')) ?>
  <?= sl_mxRow('rocket', 'Quests', $d['liveQuests'] ? $d['liveQuests'] . ($d['liveQuests'] === 1 ? ' quest' : ' quests') . ' running' : 'No quests are running', $ch, sl_route('RiderQuests')) ?>
  <?= sl_mxRow('star', 'Performance', $p['finish'] !== null ? $p['finish'] . '% completion · ' . $p['done'] . ' trips' : 'Finished and failed trips', $ch, sl_route('RiderRatings')) ?>
  <?= sl_mxRow('shield', 'Safety', $d['contact']['set'] ? 'Emergency contact: ' . $d['contact']['name'] : 'Add an emergency contact', $ch, sl_route('RiderSafety')) ?>
  <?= sl_mxRow('message', 'Help and contact', 'Call or message dispatch', $ch, sl_route('RiderChat')) ?>
  <?= sl_mxRow('bell', 'Notifications', $d['unread'] ? $d['unread'] . ' unread' : 'Nothing new', $ch, sl_route('Notifications')) ?>
  <?= sl_mxRow('lock', 'Account security', 'Password, sign-in and devices', $ch, sl_route('AccountSecurity')) ?>
</section>
<section class="mx-card mx-list"><a class="mx-row bad" href="logout.php"><span class="mx-li"><i class="ic i-logout" aria-hidden="true"></i></span><span class="mx-rm"><b>Sign out</b></span></a></section>
<p class="mx-note"><?= h(trim($d['site'] . ' rider app' . ($d['version'] !== '' ? ' · version ' . $d['version'] : ''))) ?> · Rider ID <?= h($d['riderRef']) ?></p>
</div>
</div>
<?= sl_rmClose() ?>
