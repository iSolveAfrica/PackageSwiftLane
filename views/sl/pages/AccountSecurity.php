<?php
/**
 * AccountSecurity - "My account security" (account_security.php), every signed-in role.
 * Writes: ajax/sl/account_security_ajax.php (forget one / all remembered devices),
 *         ajax/sl/passkey_ajax.php through dataJs/sl/passkey.js + dataJs/sl/account_security_mx.js.
 * Password changes happen on the existing password page (forgot-reset.php).
 */
$dev = $d['dev']; $pk = $d['pk']; $devs = $d['devices']; $place = $d['place'];
$bio = (string) $dev['biometric'];
$pkTitle = ($bio === 'Passkey' ? 'Passkey' : $bio) . ' on this ' . $place;
$current = null; $others = [];
foreach ($devs as $dv) { if ($dv['current']) $current = $dv; else $others[] = $dv; }
$pill = fn(string $t, string $c) => '<span class="sl-pill ' . $c . '">' . h($t) . '</span>';
?>
<div class="mxp wide">
<?= sl_mxHead('My account security', 'Sign-in, devices and password', sl_route($d['isClient'] ? 'UserHome' : 'Workspace')) ?>
<div class="mxp-grid">
<div class="mxp-col">
  <section class="mx-card mx-tgs" aria-label="Sign-in protection">
<?php if ($d['otp'] !== null): ?>
    <div class="mx-tg"><span class="mx-rm"><b>2-step sign-in</b><small><?= $d['otp'] ? 'A one-time code is asked at sign-in. Set for everyone by the administrator.' : 'Off for this system. Only an administrator can turn it on.' ?></small></span><?= $d['otp'] ? $pill('On', 'delivered') : $pill('Off', 'plain') ?></div>
<?php endif; ?>
<?php if ($pk !== null): ?>
    <div class="mx-tg"><span class="mx-rm"><b><?= h($pkTitle) ?></b><small data-as-pk-count data-pk-needs hidden><?= $pk['count'] ? 'Set up on ' . (int) $pk['count'] . ($pk['count'] === 1 ? ' device' : ' devices') : 'Sign in without typing your password' ?></small><small data-pk-unsupported hidden>This browser cannot use it here. Open the site in Chrome or Safari on your phone.</small></span>
      <button type="button" class="mx-sw<?= $pk['here'] ? ' on' : '' ?>" role="switch" aria-checked="<?= $pk['here'] ? 'true' : 'false' ?>" aria-label="<?= h($pkTitle) ?>" data-as-pk data-label="<?= h($pk['label']) ?>" data-id="<?= $pk['here'] ?: '' ?>" data-pk-needs hidden></button>
      <button type="button" data-pk-add data-as-pk-add hidden tabindex="-1" aria-hidden="true"></button>
    </div>
<?php if ($pk['count'] > ($pk['here'] ? 1 : 0)): ?>
    <div class="mx-tg"><span class="mx-rm"><b>Other devices with fingerprint or face sign-in</b><small><?= (int) $pk['count'] - ($pk['here'] ? 1 : 0) ?> more on your account</small></span><button type="button" class="mx-lnk as-lnk" data-sl-open="pk-manage">Manage</button></div>
<?php endif; ?>
<?php endif; ?>
<?php if ($d['otp'] === null && $pk === null): ?>
    <?= sl_mxEmpty('shield', 'No extra sign-in protection on this system', 'Your password is the only way in. Keep it long and private.') ?>
<?php endif; ?>
  </section>
  <div class="mx-btns">
    <a class="mx-btn" href="<?= h(sl_route('PasswordReset')) ?>"><i class="ic ic-sm i-key" aria-hidden="true"></i>Change password</a>
<?php if ($devs): ?>
    <button type="button" class="mx-btn sec" data-sl-action="ajax/sl/account_security_ajax.php" data-p-op="revoke_all_devices" data-sl-confirm="Forget all <?= count($devs) ?> remembered <?= count($devs) === 1 ? 'device' : 'devices' ?>? Each one, this one included, is asked for a sign-in code next time." data-sl-reload>Forget all remembered devices</button>
<?php endif; ?>
  </div>
  <p class="mx-note">Sign-in alerts and ending a session on another device are not available yet. To lock someone out, change your password and forget their device here.</p>
</div>
<div class="mxp-col">
  <?= sl_mxSec('Where you’re signed in') ?>
  <section class="mx-card mx-list">
    <div class="mx-row"><span class="mx-li"><i class="ic i-<?= $dev['form'] === 'desktop' ? 'monitor' : 'phone' ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($dev['label']) ?></b><small>This device<?= $current ? ' · remembered until ' . h($current['expires']) : '' ?></small></span><span class="mx-rr"><?php if ($current): ?><button type="button" class="as-out" data-sl-action="ajax/sl/account_security_ajax.php" data-p-op="revoke_device" data-p-id="<?= (int) $current['id'] ?>" data-sl-confirm="Forget this device? It is asked for a sign-in code next time." data-sl-reload>Forget</button><?php else: ?><?= $pill('Now', 'delivered') ?><?php endif; ?></span></div>
<?php foreach ($others as $dv): ?>
    <div class="mx-row"><span class="mx-li"><i class="ic i-<?= $dv['mobile'] ? 'phone' : 'monitor' ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($dv['label']) ?></b><small><?= h(ucfirst($dv['used'])) ?><?= $dv['ip'] !== '' ? ' · IP ' . h($dv['ip']) : '' ?></small></span><span class="mx-rr"><button type="button" class="as-out" data-sl-action="ajax/sl/account_security_ajax.php" data-p-op="revoke_device" data-p-id="<?= (int) $dv['id'] ?>" data-sl-confirm="Forget <?= h($dv['label']) ?>? It is asked for a sign-in code next time." data-sl-reload aria-label="Forget <?= h($dv['label']) ?>">Forget</button></span></div>
<?php endforeach; ?>
  </section>
<?php if (!$others): ?>
  <p class="mx-note">No other remembered devices. A device shows here after you choose “Remember this device” at sign-in.</p>
<?php endif; ?>
<?php if ($d['signins']): ?>
  <?= sl_mxSec('Recent sign-ins') ?>
  <section class="mx-card mx-list">
<?php foreach ($d['signins'] as $s): ?>
    <div class="mx-row"><span class="mx-li<?= $s['ok'] ? '' : ' as-bad' ?>"><i class="ic i-<?= $s['ok'] ? 'check' : 'alert' ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= $s['ok'] ? 'Signed in' : 'Failed sign-in' ?></b><small><?= h($s['device']) ?><?= $s['ip'] !== '' ? ' · IP ' . h($s['ip']) : '' ?></small></span><span class="mx-rr"><small title="<?= h($s['at']) ?>"><?= h($s['ago']) ?></small></span></div>
<?php endforeach; ?>
  </section>
<?php if ($d['failed']): ?><p class="mx-note">Not you? Change your password and forget the devices you do not know.</p><?php endif; ?>
<?php endif; ?>
</div>
</div>
</div>
