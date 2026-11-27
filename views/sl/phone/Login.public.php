<?php
/**
 * M-Splash + M-Login (login.php, view Login), signed out.
 * Splash: the first screen of the app, until the person taps "Sign in" (login.php?form=1) or the page has
 * something to say (error, notice, a typed username). M-Login: the real sign-in form of the web page
 * (same action, fields, CSRF, passkey button). There is no phone-number sign-in on the server, so the
 * design's Phone / Username tabs are left out; "use fingerprint next time" is offered after sign-in.
 */
$slPhone['nav'] = 'none';
$slPhone['cls'] = 'mx-bare';
$slPhone['scripts'][] = 'dataJs/sl/auth.js';
$slPhone['scripts'][] = 'dataJs/sl/passkey.js';
$errors = (array) ($d['errors'] ?? []);
$next = (string) ($d['next'] ?? '');
$formUrl = sl_route('Login') . '?form=1' . ($next !== '' ? '&next=' . rawurlencode($next) : '');
$showForm = isset($_GET['form']) || $_SERVER['REQUEST_METHOD'] === 'POST' || $errors || ($d['notice'] ?? '') !== '' || ($d['username'] ?? '') !== '';
$rider = defined('SL_PWA_ROLE') && SL_PWA_ROLE === 'rider';
$remember = !empty($d['remember']);
$fp = '<svg class="ic ic-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true" style="background:none;width:18px;height:18px"><path d="M12 11v4.5a6.5 6.5 0 0 1-1.6 4.3"/><path d="M8.5 11.5a3.5 3.5 0 0 1 7 0V14a10 10 0 0 1-.8 4"/><path d="M5.5 15.5c.3-1.2.5-2.5.5-4a6 6 0 0 1 9.8-4.6"/><path d="M18 9.5c.3.6.5 1.3.5 2v2.5a13 13 0 0 1-.6 4"/><path d="M3.7 11a8.5 8.5 0 0 1 2-5.2M8 3.9A8.5 8.5 0 0 1 20.2 8"/></svg>';
?>
<?php if (!$showForm): ?>
<main class="mx-body">
<div class="mx-auth-hero tall"><div style="display:flex;align-items:center;justify-content:space-between"><?= sl_brandLogo('white', 40) ?></div><div><small><?= $rider ? 'Swift Lane rider' : 'USA · UK · China → Ghana' ?></small><h1 class="mx-hl"><?= $rider ? 'Your delivery run, in your pocket.' : 'Ship by air in 5 to 7 days. Sea when you can wait.' ?></h1></div></div>
<p class="mx-lede"><?= $rider ? 'See your jobs, scan parcels, collect payments and track your earnings.' : 'Get a US, UK and China address, pre-alert what you buy, track it live and pick it up with a 6-digit code.' ?></p>
<div class="mx-btns"><a class="mx-btn" href="<?= h($formUrl) ?>">Sign in</a><?php if (!$rider): ?><a class="mx-btn sec" href="<?= h(sl_route('SignUp')) ?>">Create a free account</a><?php endif; ?></div>
<?php if (!$rider): ?><a class="mx-btn sec" href="<?= h(sl_route('PublicTracking')) ?>"><i class="ic ic-sm i-search" aria-hidden="true"></i>Track a parcel without signing in</a><?php endif; ?>
<p class="mx-note">Install it as an app on your phone: tracking and pickup codes are one tap away.</p>
</main>
<?php else: ?>
<main class="mx-body">
<div class="mx-auth-hero"><div style="display:flex;align-items:center;justify-content:space-between"><?= sl_brandLogo('white', 40) ?></div><small><?= $rider ? 'Sign in to your delivery run' : 'Ship by air in 5 to 7 days' ?></small></div>
<h1 class="mx-big"><?= h($d['heading'] ?? 'Welcome back') ?></h1>
<p class="mx-lede">Sign in with your username or email.</p>
<?php if (($d['notice'] ?? '') !== ''): ?><div class="mx-ban info" role="status"><i class="ic ic-sm i-info" aria-hidden="true"></i><span><?= h($d['notice']) ?></span></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="mx-ban bad" role="alert"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><?= h($err) ?></span></div><?php endforeach; ?>
<form method="post" action="login.php" id="login-form" autocomplete="on" style="display:contents">
<input type="hidden" name="<?= h(cdp_csrf_param()) ?>" value="<?= h(cdp_csrf_token()) ?>">
<input type="hidden" name="login" value="1">
<?php if ($next !== ''): ?><input type="hidden" name="next" value="<?= h($next) ?>"><?php endif; ?>
<input type="hidden" name="remember_me" id="remember_me" value="<?= $remember ? '1' : '0' ?>">
<section class="mx-card mx-form">
<label class="mx-f"><small>Username or email</small><input class="mx-in mx-inp" id="username" name="username" type="text" inputmode="email" autocomplete="username" autocapitalize="none" spellcheck="false" required placeholder="Username or you@email.com" value="<?= h($d['username'] ?? '') ?>"></label>
<div class="mx-f"><small><label for="password">Password</label></small><div style="position:relative"><input class="mx-in mx-inp" id="password" name="password" type="password" autocomplete="current-password" required placeholder="Enter your password" style="padding-right:52px"><button type="button" class="mx-ib" data-sl-peek="#password" aria-label="Show password" aria-pressed="false" style="position:absolute;right:2px;top:2px;border:0;background:transparent"><i class="ic ic-sm i-eye" aria-hidden="true"></i></button></div></div>
</section>
<section class="mx-card mx-tgs">
<div class="mx-tg"><span class="mx-rm"><b><?= !empty($d['otp']) ? 'Trust this device for 30 days' : 'Keep me signed in' ?></b><small><?= !empty($d['otp']) ? 'Skip the 6-digit code on this phone' : 'Stay signed in on this phone' ?></small></span><button type="button" class="mx-sw<?= $remember ? ' on' : '' ?>" role="switch" aria-checked="<?= $remember ? 'true' : 'false' ?>" aria-label="<?= !empty($d['otp']) ? 'Trust this device for 30 days' : 'Keep me signed in' ?>" data-input="#remember_me"></button></div>
</section>
<div class="mx-btns">
<button class="mx-btn b4-b" type="submit"><?= h($d['button'] ?? 'Sign in') ?></button>
<?php if (!empty($d['passkeys'])): ?><button type="button" class="mx-btn sec b4-b" data-pk-login hidden aria-label="Sign in with fingerprint or face"><?= $fp ?>Sign in with fingerprint or face</button><?php endif; ?>
</div>
</form>
<div class="b4-auth-links">
<a class="mx-lnk" href="<?= h(sl_route('ForgotPassword')) ?>"><i class="ic ic-sm i-key" aria-hidden="true"></i>Reset my password with a one-time code</a>
<?php if (!$rider): ?><a class="mx-lnk" href="<?= h(sl_route('PublicTracking')) ?>"><i class="ic ic-sm i-search" aria-hidden="true"></i>Track a package</a>
<a class="mx-lnk" href="<?= h(sl_route('SignUp')) ?>"><i class="ic ic-sm i-user" aria-hidden="true"></i>Create an account</a><?php endif; ?>
</div>
<p class="mx-note"><?= $rider ? 'Need rider access? Ask your dispatch team for an account.' : 'After signing in, customers land on their dashboard; staff accounts open the workspace.' ?></p>
</main>
<?php endif; ?>
