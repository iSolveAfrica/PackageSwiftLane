<?php
/**
 * ForgotPassword - generated from docs/handover/artifact-source/ForgotPassword.dc.html by scripts/sl_convert.py.
 * Shell page: no (plain page)
 * Data contract ($d):
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="sl-auth fp-full">
  <div class="form">
    <div>
      <div class="sl-brand"><span class="sl-logo"><i></i></span>Swift Lane</div>
      <div class="col g8" style="margin-top: 12px">
        <div class="sl-steps" aria-label="Step 2 of 3"><i class="on"></i><i class="on"></i><i></i></div>
        <div class="row between mut sm"><span>Email or phone</span><span class="fw5" style="color: var(--ink)">Verify code</span><span>New password</span></div>
      </div>
      <div class="col g6"><h1 style="font-size: 28px; font-weight: 500; letter-spacing: -.015em">Check your email or phone</h1><p class="mut">We sent a 6-digit code to your account contact. It expires in 10 minutes.</p></div>
      <div class="sl-seg boxed" role="group" aria-label="Send code to"><button class="on">Email</button><button>Phone</button></div>
      <div class="col g8">
        <div class="row g12 sl-card" style="padding: 12px 14px"><span class="sl-tile"><i class="ic i-mail" aria-hidden="true"></i></span><div class="grow"><div class="up">Email</div><div class="mono fw5">k•••••@example.com</div></div><span class="sl-badge ok">Sent</span></div>
        <div class="row g12 sl-card" style="padding: 12px 14px"><span class="sl-tile"><i class="ic i-phone" aria-hidden="true"></i></span><div class="grow"><div class="up">Phone</div><div class="mono fw5">+233 •• ••• •• 82</div></div><a class="sl-btn ghost sm" href="<?= sl_route('LoginOtp') ?>">Send to phone</a></div>
      </div>
      <div class="sl-otp" role="group" aria-label="Verification code"><span>4</span><span>8</span><span>2</span><span>1</span><span style="border-color: var(--ink); box-shadow: 0 0 0 1px var(--ink)"></span><span></span></div>
      <a class="sl-btn block" href="<?= sl_route('Login') ?>" style="height: 46px">Verify and continue</a>
      <div class="row between sm"><span class="mut">Didn’t get it? <a href="<?= sl_route('ForgotPassword') ?>" class="fw5" style="color: var(--ink); text-decoration: underline">Resend code</a> in 0:42</span><a href="<?= sl_route('Login') ?>" class="row g6 fw5"><i class="ic ic-sm i-left" aria-hidden="true"></i>Back to log in</a></div>
      <div class="sl-banner info"><i class="ic i-info" aria-hidden="true"></i><span>Your email and phone number are partly hidden to keep your account private.</span></div>
    </div>
  </div>
  <div class="art fs" aria-label="Pictures and tips"><div aria-hidden="true" style="position:absolute;inset:0"><div class="fs-bg bg-plane"></div><div class="fs-bg bg-ship"></div><div class="fs-bg bg-wh"></div></div><div class="fs-dim" aria-hidden="true"></div>
<div class="fs-top"><span class="up" style="color:rgba(255,255,255,.75)">Account security</span><a href="<?= sl_route('PublicTracking') ?>" style="color:#fff">Track a package →</a></div>
<div class="fs-cap"><div class="fs-caps"><div><span class="fs-ic"><i class="ic i-mail" aria-hidden="true"></i></span><span class="up" style="color:rgba(255,255,255,.7)">Step 1</span><h2>Enter the code we sent you</h2><span style="opacity:.8;max-width:460px">One-time codes are single use and expire in 10 minutes.</span></div><div><span class="fs-ic"><i class="ic i-key" aria-hidden="true"></i></span><span class="up" style="color:rgba(255,255,255,.7)">Step 2</span><h2>Choose a new password</h2><span style="opacity:.8;max-width:460px">At least 8 characters with a number and a symbol.</span></div><div><span class="fs-ic"><i class="ic i-shield" aria-hidden="true"></i></span><span class="up" style="color:rgba(255,255,255,.7)">Stay safe</span><h2>Swift Lane never asks for your code</h2><span style="opacity:.8;max-width:460px">Do not share it with anyone, even our staff.</span></div></div><div class="fs-dots"><i></i><i></i><i></i></div></div></div>
</div>
