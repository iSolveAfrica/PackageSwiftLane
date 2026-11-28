<?php
/**
 * M-AcctSec: my account security, every signed-in role. Same $d as the web page
 * (views/sl/data/account_security.php). Writes: ajax/sl/account_security_ajax.php (forget devices),
 * ajax/sl/passkey_ajax.php through dataJs/sl/passkey.js + dataJs/sl/account_security_mx.js.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$dev = $d['dev']; $pk = $d['pk']; $devs = $d['devices'];
$bio = (string) $dev['biometric'];
$pkTitle = $bio . ' on this ' . $d['place'];
$current = null; $others = [];
foreach ($devs as $dv) { if ($dv['current']) $current = $dv; else $others[] = $dv; }
$pill = fn(string $t, string $c) => '<span class="sl-pill ' . $c . '">' . h($t) . '</span>';
$slPhone['tab'] = sl_userIs('client') || sl_userIs('driver') ? '' : 'Menu';
if ($pk !== null) { $slPhone['scripts'][] = 'dataJs/sl/passkey.js'; $slPhone['scripts'][] = 'dataJs/sl/account_security_mx.js'; }
$back = sl_userIs('client') ? sl_route('MyAccount') : (sl_userIs('driver') ? sl_route('RiderAccount') : 'menu.php');
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-staff.css')) ?>">
<?= sl_phoneTop('My account security', '', ['back' => $back]) ?>
<main class="mx-body">
<section class="mx-card mx-tgs sp-wrap">
<?php if ($d['otp'] !== null): ?>
  <div class="mx-tg"><span class="mx-rm"><b>2-step sign-in</b><small><?= $d['otp'] ? 'A one-time code at sign-in. Set for everyone by the administrator.' : 'Off for this system. Only an administrator can turn it on.' ?></small></span><?= $d['otp'] ? $pill('On', 'delivered') : $pill('Off', 'plain') ?></div>
<?php endif; ?>
<?php if ($pk !== null): ?>
  <div class="mx-tg"><span class="mx-rm"><b><?= h($pkTitle) ?></b><small data-as-pk-count data-pk-needs hidden><?= $pk['count'] ? 'Set up on ' . (int) $pk['count'] . ($pk['count'] === 1 ? ' device' : ' devices') : 'Sign in without typing your password' ?></small><small data-pk-unsupported hidden>This browser cannot use it. Open the site in Chrome or Safari.</small></span>
    <button type="button" class="mx-sw<?= $pk['here'] ? ' on' : '' ?>" role="switch" aria-checked="<?= $pk['here'] ? 'true' : 'false' ?>" aria-label="<?= h($pkTitle) ?>" data-as-pk data-label="<?= h($pk['label']) ?>" data-id="<?= $pk['here'] ?: '' ?>" data-pk-needs hidden></button>
    <button type="button" data-pk-add data-as-pk-add hidden tabindex="-1" aria-hidden="true"></button>
  </div>
<?php endif; ?>
<?php if ($d['otp'] === null && $pk === null): ?>
  <?= sl_mxEmpty('shield', 'No extra sign-in protection on this system', 'Your password is the only way in. Keep it long and private.') ?>
<?php endif; ?>
</section>
<div class="mx-sec"><h2>Where you’re signed in</h2></div>
<section class="mx-card mx-list">
  <div class="mx-row"><span class="mx-li"><i class="ic i-<?= $dev['form'] === 'desktop' ? 'monitor' : 'phone' ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($dev['label']) ?></b><small>This device<?= $current ? ' · remembered until ' . h($current['expires']) : '' ?></small></span><span class="mx-rr"><?= $pill('Now', 'delivered') ?></span></div>
<?php foreach ($others as $dv): ?>
  <div class="mx-row"><span class="mx-li"><i class="ic i-<?= $dv['mobile'] ? 'phone' : 'monitor' ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($dv['label']) ?></b><small><?= h(ucfirst($dv['used'])) ?><?= $dv['ip'] !== '' ? ' · IP ' . h($dv['ip']) : '' ?></small></span><span class="mx-rr"><button type="button" class="sp-out" data-sl-action="ajax/sl/account_security_ajax.php" data-p-op="revoke_device" data-p-id="<?= (int) $dv['id'] ?>" data-sl-confirm="Forget <?= h($dv['label']) ?>? It is asked for a sign-in code next time." data-sl-reload aria-label="Forget <?= h($dv['label']) ?>">Forget</button></span></div>
<?php endforeach; ?>
</section>
<?php if (!$others): ?><p class="mx-note">No other remembered devices. A device shows here after you choose “Remember this device” at sign-in.</p><?php endif; ?>
<?php if ($d['failed']): ?><div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><?= (int) $d['failed'] ?> of your last <?= count($d['signins']) ?> sign-in attempts failed. If that was not you, change your password.</span></div><?php endif; ?>
<div class="mx-btns">
  <a class="mx-btn" href="<?= h(sl_route('PasswordReset')) ?>">Change password</a>
<?php if ($devs): ?>
  <button type="button" class="mx-btn sec" data-sl-action="ajax/sl/account_security_ajax.php" data-p-op="revoke_all_devices" data-sl-confirm="Forget all <?= count($devs) ?> remembered <?= count($devs) === 1 ? 'device' : 'devices' ?>? Each one, this one included, is asked for a sign-in code next time." data-sl-reload>Forget all remembered devices</button>
<?php endif; ?>
</div>
<p class="mx-note">Sign-in alerts and ending a session on another device are not available yet. To lock someone out, change your password and forget their device here.</p>
</main>
