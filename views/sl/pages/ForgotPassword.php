<?php
/**
 * ForgotPassword - from docs/handover/artifact-source/ForgotPassword.dc.html (scripts/sl_convert.py).
 * Step 1 (forgot-password.php): ask for the account email. Step 2 (auth-otp.php?flow=forgot): verify the code.
 * Shell page: no (plain page)
 * Data contract ($d): step (1|2), message, error, email, phone, hasPhone, countdownEnd, canResend, pics, site, notice
 */
if (!isset($d)) { $d = []; }
$pics = sl_get($d, 'pics', []);
$step = (int) sl_get($d, 'step', 1);
?>
<div class="sl-auth fp-full">
  <div class="form">
    <div>
      <div class="sl-brand"><span class="sl-logo"><i></i></span><?= sl_v($d, 'site') ?></div>
      <div class="col g8" style="margin-top: 12px">
        <div class="sl-steps" aria-label="Step <?= $step ?> of 3"><i class="on"></i><i class="<?= $step >= 2 ? 'on' : '' ?>"></i><i></i></div>
        <div class="row between mut sm"><span class="<?= $step === 1 ? 'fw5' : '' ?>" <?= $step === 1 ? 'style="color: var(--ink)"' : '' ?>>Email</span><span class="<?= $step === 2 ? 'fw5' : '' ?>" <?= $step === 2 ? 'style="color: var(--ink)"' : '' ?>>Verify code</span><span>New password</span></div>
      </div>
      <?php if ($step === 1): ?>
      <div class="col g6"><h1 style="font-size: 28px; font-weight: 500; letter-spacing: -.015em"><?= sl_v($d, 'heading', 'Forgot your password?') ?></h1><p class="mut">Enter the email of your account. We send a 6-digit code to it (and to your WhatsApp when it is on file). It expires in 5 minutes.</p></div>
      <?php if (sl_get($d, 'notice')): ?><div class="sl-banner info" role="status"><i class="ic ic-sm i-info" aria-hidden="true"></i><span><?= sl_v($d, 'notice') ?></span></div><?php endif; ?>
      <form class="col g16" data-sl-legacy-form action="ajax/forgot-password-ajax.php" method="post" id="forgotPassword">
        <div class="sl-banner" data-sl-msg style="display:none"></div>
        <div class="sl-field"><label class="sl-lab" for="email">Email address</label><div class="sl-inwrap"><i class="ic ic-sm i-mail" aria-hidden="true"></i><input id="email" name="email" class="sl-in" type="email" autocomplete="email" autocapitalize="none" spellcheck="false" required placeholder="you@company.com"></div></div>
        <button class="sl-btn block" type="submit" style="height: 46px"><?= sl_v($d, 'button', 'Send me a code') ?></button>
      </form>
      <div class="row between sm"><a href="<?= sl_route('SignUp') ?>" class="mut">No account yet? Sign up</a><a href="<?= sl_route('Login') ?>" class="row g6 fw5"><i class="ic ic-sm i-left" aria-hidden="true"></i>Back to log in</a></div>
      <div class="sl-banner info"><i class="ic i-info" aria-hidden="true"></i><span>At most 5 codes every 30 minutes per account. Codes are single use.</span></div>
      <?php else: ?>
      <div class="col g6"><h1 style="font-size: 28px; font-weight: 500; letter-spacing: -.015em">Check your email<?= sl_get($d, 'hasPhone') ? ' or WhatsApp' : '' ?></h1><p class="mut">We sent a 6-digit code to your account contact. It expires in 5 minutes.</p></div>
      <?php if (sl_get($d, 'message')): ?><div class="sl-banner ok" role="status"><i class="ic ic-sm i-check" aria-hidden="true"></i><span><?= sl_v($d, 'message') ?></span></div><?php endif; ?>
      <?php if (sl_get($d, 'error')): ?><div class="sl-banner bad" role="alert"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><?= sl_v($d, 'error') ?></span></div><?php endif; ?>
      <div class="col g8">
        <div class="row g12 sl-card" style="padding: 12px 14px"><span class="sl-tile"><i class="ic i-mail" aria-hidden="true"></i></span><div class="grow"><div class="up">Email</div><div class="mono fw5"><?= sl_v($d, 'email') ?></div></div><span class="sl-badge ok">Sent</span></div>
        <?php if (sl_get($d, 'hasPhone')): ?><div class="row g12 sl-card" style="padding: 12px 14px"><span class="sl-tile"><i class="ic i-phone" aria-hidden="true"></i></span><div class="grow"><div class="up">WhatsApp</div><div class="mono fw5"><?= sl_v($d, 'phone') ?></div></div><span class="sl-badge ok">Sent</span></div><?php endif; ?>
      </div>
      <form method="post" action="auth-otp.php?flow=forgot" class="col g16" id="otp-form">
        <input type="hidden" name="<?= h(cdp_csrf_param()) ?>" value="<?= h(cdp_csrf_token()) ?>">
        <div class="sl-otp" role="group" aria-label="Verification code" data-sl-otp="#otp_code"><span></span><span></span><span></span><span></span><span></span><span></span></div>
        <input id="otp_code" name="otp_code" class="sl-in mono" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required aria-label="6-digit code" style="position:absolute;opacity:0;height:1px;width:1px;overflow:hidden">
        <button class="sl-btn block" type="submit" style="height: 46px">Verify and continue</button>
      </form>
      <form method="post" action="auth-otp.php?flow=forgot" class="row between sm">
        <input type="hidden" name="<?= h(cdp_csrf_param()) ?>" value="<?= h(cdp_csrf_token()) ?>">
        <span class="mut">Didn’t get it? <button type="submit" name="resend" value="1" id="resendBtn" class="fw5" style="background:none;border:0;padding:0;color:var(--ink);text-decoration:underline;cursor:pointer;font:inherit" <?= sl_get($d, 'canResend') ? '' : 'disabled' ?>>Resend code</button> <span data-sl-countdown="<?= (int) sl_get($d, 'countdownEnd', 0) ?>" data-sl-countdown-btn="#resendBtn" hidden></span></span>
        <a href="<?= sl_route('Login') ?>" class="row g6 fw5"><i class="ic ic-sm i-left" aria-hidden="true"></i>Back to log in</a>
      </form>
      <div class="sl-banner info"><i class="ic i-info" aria-hidden="true"></i><span>Your email and phone number are partly hidden to keep your account private.</span></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="art fs" aria-label="Pictures and tips" style="<?= h($pics['style'] ?? '') ?>" data-sl-slides="<?= (int) ($pics['n'] ?? 0) ?>" data-sl-every="<?= (int) ($pics['every'] ?? 6) ?>"><div aria-hidden="true" style="position:absolute;inset:0"><?= $pics['bgs'] ?? '' ?></div><div class="fs-dim" aria-hidden="true"></div>
<div class="fs-top"><span class="up" style="color:rgba(255,255,255,.75)">Account security</span><a href="<?= sl_route('PublicTracking') ?>" style="color:#fff">Track a package →</a></div>
<div class="fs-cap"><div class="fs-caps"><div><span class="fs-ic"><i class="ic i-mail" aria-hidden="true"></i></span><span class="up" style="color:rgba(255,255,255,.7)">Step 1</span><h2>Enter the code we sent you</h2><span style="opacity:.8;max-width:460px">One-time codes are single use and expire in 5 minutes.</span></div><div><span class="fs-ic"><i class="ic i-key" aria-hidden="true"></i></span><span class="up" style="color:rgba(255,255,255,.7)">Step 2</span><h2>Choose a new password</h2><span style="opacity:.8;max-width:460px">At least 8 characters with a number and a symbol.</span></div><div><span class="fs-ic"><i class="ic i-shield" aria-hidden="true"></i></span><span class="up" style="color:rgba(255,255,255,.7)">Stay safe</span><h2><?= sl_v($d, 'site') ?> never asks for your code</h2><span style="opacity:.8;max-width:460px">Do not share it with anyone, even our staff.</span></div></div><div class="fs-dots"><i></i><i></i><i></i></div></div></div>
</div>
