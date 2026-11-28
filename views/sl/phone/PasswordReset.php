<?php
/**
 * M-Reset (forgot-reset.php, view PasswordReset): choose a new password after the code (mode forgot) or when
 * the password is due (mode rotate, signed in). Posts password / password_confirm / sign_out_others to
 * forgot-reset.php like the web page; strength meter and rules from dataJs/sl/auth.js.
 */
$slPhone['nav'] = 'none';
$slPhone['cls'] = 'mx-bare';
$slPhone['scripts'][] = 'dataJs/sl/auth.js';
$age = $d['age'] ?? null;
$done = !empty($d['done']);
$rotate = ($d['mode'] ?? '') === 'rotate';
$rules = [['len', '8+ characters'], ['num', 'A number'], ['sym', 'A symbol'], ['case', 'Upper and lower case']];
$steps = $rotate ? [1 => '1 Signed in', 2 => '2 New password', 3 => '3 Done'] : [1 => '1 Email', 2 => '2 Verify code', 3 => '3 New password'];
$cur = $rotate ? ($done ? 3 : 2) : 3;
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-auth.css')) ?>">
<main class="mx-body">
<div class="mx-auth-hero"><div style="display:flex;align-items:center;justify-content:space-between"><a class="mx-ib" href="<?= h($d['back'] ?? sl_route('Login')) ?>" aria-label="Back"><i class="ic i-left" aria-hidden="true"></i></a><?= sl_brandLogo('white', 40) ?></div><small><?= $rotate ? 'Time for a new password' : 'Reset your password' ?></small></div>
<ol class="b4-steps" aria-label="Steps"><?php foreach ($steps as $n => $label): ?><li class="<?= $n < $cur || ($done && $n === $cur) ? 'done' : ($n === $cur ? 'cur' : '') ?>"<?= $n === $cur ? ' aria-current="step"' : '' ?>><i></i><span><?= h($label) ?></span></li><?php endforeach; ?></ol>
<?php if (!$done): ?>
<h1 class="mx-big">Choose a new password</h1>
<p class="mx-lede"><?php if ($age): ?>Your current password is <?= (int) $age['days'] ?> days old. Passwords expire every <?= (int) $age['period'] ?> days<?= !empty($age['expired']) ? ', so change it before you continue.' : '.' ?><?php else: ?>Use a password you do not use anywhere else.<?php endif; ?></p>
<?php if (($d['error'] ?? '') !== ''): ?><div class="mx-ban bad" role="alert"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><?= h($d['error']) ?></span></div><?php endif; ?>
<form method="post" action="forgot-reset.php" id="reset-form" autocomplete="off" style="display:contents">
<input type="hidden" name="<?= h(cdp_csrf_param()) ?>" value="<?= h(cdp_csrf_token()) ?>">
<section class="mx-card mx-form">
<div class="mx-f"><small><label for="password">New password</label></small><div class="pa-pw"><input class="mx-in mx-inp" id="password" name="password" type="password" autocomplete="new-password" minlength="8" required placeholder="At least 8 characters" data-sl-pw="#pw-meter" data-sl-pw-msg="#pw-msg" data-sl-pw-confirm="#password_confirm" data-sl-pw-match="#pw-match" data-sl-pw-submit="#pw-submit"><button type="button" class="mx-ib" data-sl-peek="#password" aria-label="Show password" aria-pressed="false"><i class="ic ic-sm i-eye" aria-hidden="true"></i></button></div></div>
<label class="mx-f"><small>Confirm password</small><input class="mx-in mx-inp" id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" minlength="8" required placeholder="Repeat the password"><small class="mx-vh" id="pw-match" aria-live="polite"></small></label>
</section>
<section class="mx-card mx-mts"><div class="mx-mt"><div><span>Strength</span><b id="pw-msg" aria-live="polite"></b></div><span class="mx-trk pa-meter" id="pw-meter" aria-hidden="true"><i></i></span></div></section>
<div class="mx-chips pa-rules"><?php foreach ($rules as $r): ?><span data-sl-rule="<?= h($r[0]) ?>"><i class="ic ic-sm i-x" aria-hidden="true"></i> <?= h($r[1]) ?></span><?php endforeach; ?></div>
<?php if ($rotate): ?>
<input type="hidden" name="sign_out_others" id="sign_out_others" value="1">
<section class="mx-card mx-tgs"><div class="mx-tg"><span class="mx-rm"><b>Sign out other devices</b><small>Trusted devices and app sign-ins end</small></span><button type="button" class="mx-sw on" role="switch" aria-checked="true" aria-label="Sign out other devices" data-input="#sign_out_others"></button></div></section>
<?php else: ?>
<input type="hidden" name="sign_out_others" value="1">
<div class="mx-ban info"><i class="ic ic-sm i-shield" aria-hidden="true"></i><span>All devices that were trusted for this account will be signed out.</span></div>
<?php endif; ?>
<div class="mx-btns"><button class="mx-btn b4-b" type="submit" id="pw-submit">Save new password</button></div>
</form>
<?php if ((int) ($d['period'] ?? 0) > 0): ?><p class="mx-note">You’re reminded 7 days before and on the day it expires. Expired passwords must be changed before you can use <?= h($d['site'] ?? 'Swift Lane') ?>.</p><?php endif; ?>
<?php else: ?>
<section class="b4-end ok" role="status"><span class="b4-endi"><i class="ic i-check" aria-hidden="true"></i></span><b>Password updated</b><p>Use it next time you sign in.<?= !empty($d['signedOut']) ? ' Other devices were signed out.' : '' ?><?= ($d['nextDue'] ?? '') !== '' ? ' Next change due ' . h($d['nextDue']) . '.' : '' ?></p></section>
<div class="mx-btns"><a class="mx-btn" href="<?= h($d['continue'] ?? sl_route('Login')) ?>">Continue</a></div>
<?php endif; ?>
<div class="b4-row2"><a class="mx-lnk" href="<?= h($d['help'] ?? 'support.php') ?>">Need help?</a><a class="mx-lnk" href="<?= h(sl_route('PublicTracking')) ?>">Track a package</a></div>
</main>
