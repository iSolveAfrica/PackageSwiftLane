<?php
/**
 * LoginOtp - from docs/handover/artifact-source/LoginOtp.dc.html (scripts/sl_convert.py), bound to auth-otp.php (flow=login|signup).
 * Shell page: no (plain page)
 * Data contract ($d): flow, message, error, email, phone, hasPhone, countdownEnd, canResend, remember, age{left,period,expired,next}|null, pics, site, back, backLabel
 */
if (!isset($d)) { $d = []; }
$pics = sl_get($d, 'pics', []);
$flow = sl_get($d, 'flow', 'login');
$age = sl_get($d, 'age');
$signup = $flow === 'signup';
?>
<div class="sl-auth fp-full">
  <div class="form">
    <div>
      <div class="sl-brand"><span class="sl-logo"><i></i></span><?= sl_v($d, 'site') ?></div>
      <div class="col g8" style="margin-top: 12px">
        <div class="sl-steps" aria-label="Step 2 of 3"><i class="on"></i><i class="on"></i><i></i></div>
        <div class="row between mut sm"><span><?= $signup ? 'Your details' : 'Log in' ?></span><span class="fw5" style="color: var(--ink)">Verify it’s you</span><span><?= $signup ? 'Approval' : ($age ? 'Password check' : 'Done') ?></span></div>
      </div>
      <div class="col g6"><h1 style="font-size: 28px; font-weight: 500; letter-spacing: -.015em"><?= $signup ? 'Confirm your email' : 'Enter your login code' ?></h1><p class="mut"><?= $signup ? 'We sent a one-time code to the email you registered. It expires in 5 minutes.' : 'For your security we send a one-time code every time you log in from a new device. It expires in 5 minutes.' ?></p></div>
      <?php if (sl_get($d, 'message')): ?><div class="sl-banner ok" role="status"><i class="ic ic-sm i-check" aria-hidden="true"></i><span><?= sl_v($d, 'message') ?></span></div><?php endif; ?>
      <?php if (sl_get($d, 'error')): ?><div class="sl-banner bad" role="alert"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><?= sl_v($d, 'error') ?></span></div><?php endif; ?>
      <div class="col g8">
        <div class="row g12 sl-card" style="padding: 12px 14px"><span class="sl-tile"><i class="ic i-mail" aria-hidden="true"></i></span><div class="grow"><div class="up">Code sent to</div><div class="mono fw5"><?= sl_v($d, 'email') ?></div></div><span class="sl-badge ok">Sent</span></div>
        <?php if (sl_get($d, 'hasPhone') && !$signup): ?><div class="row g12 sl-card" style="padding: 12px 14px"><span class="sl-tile"><i class="ic i-message" aria-hidden="true"></i></span><div class="grow"><div class="up">Also on WhatsApp</div><div class="mono fw5"><?= sl_v($d, 'phone') ?></div></div><span class="sl-badge ok">Sent</span></div><?php endif; ?>
      </div>
      <form method="post" action="auth-otp.php?flow=<?= h($flow) ?>" class="col g16" id="otp-form">
        <input type="hidden" name="<?= h(cdp_csrf_param()) ?>" value="<?= h(cdp_csrf_token()) ?>">
        <div class="sl-otp" role="group" aria-label="Login code" data-sl-otp="#otp_code"><span></span><span></span><span></span><span></span><span></span><span></span></div>
        <input id="otp_code" name="otp_code" class="sl-in mono" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required aria-label="6-digit code" style="position:absolute;opacity:0;height:1px;width:1px;overflow:hidden">
        <?php if (!$signup): ?><div class="row g10"><span class="sl-toggle <?= sl_get($d, 'remember') ? 'on' : '' ?>" role="switch" aria-checked="<?= sl_get($d, 'remember') ? 'true' : 'false' ?>" data-static aria-label="Trust this device"></span><span><?= sl_get($d, 'remember') ? 'This device will be trusted for 30 days' : 'This device is not remembered (tick it on the login page)' ?></span></div><?php endif; ?>
        <button class="sl-btn block" type="submit" style="height: 46px"><?= $signup ? 'Verify and finish' : 'Verify and log in' ?></button>
      </form>
      <form method="post" action="auth-otp.php?flow=<?= h($flow) ?>" class="row between sm">
        <input type="hidden" name="<?= h(cdp_csrf_param()) ?>" value="<?= h(cdp_csrf_token()) ?>">
        <span class="mut">Didn’t get it? <button type="submit" name="resend" value="1" id="resendBtn" class="fw5" style="background:none;border:0;padding:0;color:var(--ink);text-decoration:underline;cursor:pointer;font:inherit" <?= sl_get($d, 'canResend') ? '' : 'disabled' ?>>Resend code</button> <span data-sl-countdown="<?= (int) sl_get($d, 'countdownEnd', 0) ?>" data-sl-countdown-btn="#resendBtn" hidden></span></span>
        <a href="<?= sl_v($d, 'back') ?>" class="row g6 fw5"><i class="ic ic-sm i-left" aria-hidden="true"></i><?= sl_v($d, 'backLabel') ?></a>
      </form>
      <div class="sl-banner info"><i class="ic i-info" aria-hidden="true"></i><span>After 5 wrong codes the code is locked and you must request a new one. At most 5 codes every 30 minutes.</span></div>
      <?php if ($age): ?>
      <a class="row g10 sl-card" href="<?= sl_route('PasswordReset') ?>" style="padding:12px 14px;color:inherit"><span class="sl-tile"><i class="ic i-lock" aria-hidden="true"></i></span><span class="col grow"><b class="fw5 sm"><?= $age['expired'] ? 'Your password has expired' : 'Your password expires in ' . max(0, (int) $age['left']) . ' days' ?></b><span class="mut" style="font-size:14px">Passwords reset every <?= (int) $age['period'] ?> days · change it after you log in</span></span><i class="ic ic-sm i-right" aria-hidden="true"></i></a>
      <?php endif; ?>
    </div>
  </div>
  <div class="art fs" aria-label="Pictures and tips" style="<?= h($pics['style'] ?? '') ?>" data-sl-slides="<?= (int) ($pics['n'] ?? 0) ?>" data-sl-every="<?= (int) ($pics['every'] ?? 6) ?>"><div aria-hidden="true" style="position:absolute;inset:0"><?= $pics['bgs'] ?? '' ?></div><div class="fs-dim" aria-hidden="true"></div>
<div class="fs-top"><span class="up" style="color:rgba(255,255,255,.75)">Two-step sign-in</span><a href="<?= sl_route('PublicTracking') ?>" style="color:#fff">Track a package →</a></div>
<div class="fs-cap"><div class="fs-caps"><div><span class="fs-ic"><i class="ic i-mail" aria-hidden="true"></i></span><span class="up" style="color:rgba(255,255,255,.7)">Step 1</span><h2>Enter the code we sent</h2><span style="opacity:.8;max-width:460px">Every log-in from a new device needs a fresh code. It expires in 5 minutes.</span></div><div><span class="fs-ic"><i class="ic i-key" aria-hidden="true"></i></span><span class="up" style="color:rgba(255,255,255,.7)">Step 2</span><h2>Keep your password strong</h2><span style="opacity:.8;max-width:460px">At least 8 characters with a number and a symbol.</span></div><div><span class="fs-ic"><i class="ic i-shield" aria-hidden="true"></i></span><span class="up" style="color:rgba(255,255,255,.7)">Stay safe</span><h2><?= sl_v($d, 'site') ?> never asks for your code</h2><span style="opacity:.8;max-width:460px">Do not share it with anyone, even our staff.</span></div></div><div class="fs-dots"><i></i><i></i><i></i></div></div></div>
</div>
