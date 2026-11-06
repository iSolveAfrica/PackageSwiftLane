<?php
/**
 * Login - from docs/handover/artifact-source/Login.dc.html (scripts/sl_convert.py), bound to login.php.
 * Shell page: no (plain page)
 * Data contract ($d): errors[], notice, username, remember, otp, pics{bgs,caps,dots,n,every,style,captions}, heading, button, site, next
 */
if (!isset($d)) { $d = []; }
$pics = sl_get($d, 'pics', []);
?>
<div class="lg" style="<?= h($pics['style'] ?? '') ?>" data-sl-slides="<?= (int) ($pics['n'] ?? 0) ?>" data-sl-every="<?= (int) ($pics['every'] ?? 6) ?>">
  <div aria-hidden="true" style="position:absolute;inset:0"><?= $pics['bgs'] ?? '' ?></div>
  <div class="lg-dim" aria-hidden="true" style="opacity:calc(var(--sl-dim,.3) + .5)"></div>
  <div class="lg-top"><div class="sl-brand" style="color:#fff"><span class="sl-logo"><i></i></span><?= sl_v($d, 'site') ?></div><a class="sm fw5" href="<?= sl_route('PublicTracking') ?>">Track a package →</a></div>
  <main class="lg-card">
    <div class="col g6"><h1 style="font-size:28px;font-weight:500;letter-spacing:-.015em;margin:0"><?= sl_v($d, 'heading') ?></h1><p class="mut" style="margin:0">Log in to manage shipments, packages and pickups.</p></div>
    <?php if (sl_get($d, 'notice')): ?><div class="sl-banner info" role="status"><i class="ic ic-sm i-info" aria-hidden="true"></i><span><?= sl_v($d, 'notice') ?></span></div><?php endif; ?>
    <?php foreach ((array) sl_get($d, 'errors', []) as $err): ?><div class="sl-banner bad" role="alert"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><?= h($err) ?></span></div><?php endforeach; ?>
    <form class="col g16" method="post" action="login.php" id="login-form" autocomplete="on">
      <input type="hidden" name="<?= h(cdp_csrf_param()) ?>" value="<?= h(cdp_csrf_token()) ?>">
      <input type="hidden" name="login" value="1">
      <?php if (sl_get($d, 'next')): ?><input type="hidden" name="next" value="<?= sl_v($d, 'next') ?>"><?php endif; ?>
      <div class="sl-field"><label class="sl-lab" for="username">Username or email</label><div class="sl-inwrap"><i class="ic ic-sm i-mail" aria-hidden="true"></i><input id="username" name="username" class="sl-in" type="text" autocomplete="username" autocapitalize="none" spellcheck="false" required placeholder="you@company.com" value="<?= sl_v($d, 'username') ?>"></div></div>
      <div class="sl-field"><div class="row between"><label class="sl-lab" for="password">Password</label><a href="<?= sl_route('ForgotPassword') ?>" class="sm fw5" style="color: var(--violet-ink)">Forgot password?</a></div><div class="sl-inwrap"><i class="ic ic-sm i-lock" aria-hidden="true"></i><input id="password" name="password" class="sl-in" type="password" autocomplete="current-password" required placeholder="Enter your password"><span role="button" tabindex="0" class="sl-ib" style="width:30px;height:30px;margin-left:auto;cursor:pointer" aria-label="Show password" data-sl-peek="#password"><i class="ic ic-sm i-eye" aria-hidden="true"></i></span></div></div>
      <label class="row g10" style="cursor:pointer"><span class="sl-toggle <?= sl_get($d, 'remember') ? 'on' : '' ?>" role="switch" aria-checked="<?= sl_get($d, 'remember') ? 'true' : 'false' ?>"><input type="hidden" name="remember_me" value="<?= sl_get($d, 'remember') ? '1' : '0' ?>"></span><span><?= sl_get($d, 'otp') ? 'Trust this device for 30 days (no code next time)' : 'Keep me signed in on this device' ?></span></label>
      <button class="sl-btn block" type="submit" style="height:48px;font-size:15px"><?= sl_v($d, 'button') ?></button>
    </form>
    <div class="sl-sep" style="margin:0">or</div>
    <a class="sl-btn ghost block" href="<?= sl_route('ForgotPassword') ?>" style="height:44px" title="We send a one-time code to your email or WhatsApp"><i class="ic ic-sm i-phone" aria-hidden="true"></i>Reset my password with a one-time code</a>
    <p class="mut sm" style="text-align:center;margin:0">New to <?= sl_v($d, 'site') ?>? <a href="<?= sl_route('SignUp') ?>" class="fw5" style="color: var(--ink); text-decoration: underline">Create an account</a></p>
  </main>
  <?php if (!empty($pics['captions'])): ?>
  <div class="lg-cap" aria-hidden="true" style="width:420px;max-width:calc(100vw - 80px)"><div class="lg-caps"><?= $pics['caps'] ?></div><div class="lg-dots"><?= $pics['dots'] ?></div></div>
  <?php endif; ?>
</div>
