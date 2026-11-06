<?php
/**
 * LoginOtp - generated from docs/handover/artifact-source/LoginOtp.dc.html by scripts/sl_convert.py.
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
        <div class="row between mut sm"><span>Log in</span><span class="fw5" style="color: var(--ink)">Verify it’s you</span><span>Password check</span></div>
      </div>
      <div class="col g6"><h1 style="font-size: 28px; font-weight: 500; letter-spacing: -.015em">Enter your login code</h1><p class="mut">For your security we send a one-time code every time you log in. It expires in 5 minutes.</p></div>
      <div class="sl-seg boxed" role="group" aria-label="Send code to"><button class="on">Email</button><button>Phone (SMS)</button><button>WhatsApp</button></div>
      <div class="row g12 sl-card" style="padding: 12px 14px"><span class="sl-tile"><i class="ic i-mail" aria-hidden="true"></i></span><div class="grow"><div class="up">Code sent to</div><div class="mono fw5">k•••••@example.com</div></div><span class="sl-badge ok">Sent</span></div>
      <div class="sl-otp" role="group" aria-label="Login code"><span>7</span><span>3</span><span>9</span><span style="border-color: var(--ink); box-shadow: 0 0 0 1px var(--ink)"></span><span></span><span></span></div>
      <div class="row g10"><span class="sl-toggle on"></span><span>Trust this device for 30 days</span></div>
      <a class="sl-btn block" href="<?= sl_route('Main') ?>" style="height: 46px">Verify and log in</a>
      <div class="row between sm"><span class="mut">Didn’t get it? <a href="<?= sl_route('LoginOtp') ?>" class="fw5" style="color: var(--ink); text-decoration: underline">Resend code</a> in 0:42</span><a href="<?= sl_route('Login') ?>" class="row g6 fw5"><i class="ic ic-sm i-left" aria-hidden="true"></i>Back to log in</a></div>
      <div class="sl-banner info"><i class="ic i-info" aria-hidden="true"></i><span>After 5 wrong codes this account is locked for 15 minutes.</span></div>
      <a class="row g10 sl-card" href="<?= sl_route('PasswordReset') ?>" style="padding:12px 14px;color:inherit"><span class="sl-tile"><i class="ic i-lock" aria-hidden="true"></i></span><span class="col grow"><b class="fw5 sm">Your password expires in 3 days</b><span class="mut" style="font-size:12px">Passwords reset every 30 days · change it now</span></span><i class="ic ic-sm i-right" aria-hidden="true"></i></a>
    </div>
  </div>
  <div class="art fs" aria-label="Pictures and tips"><div aria-hidden="true" style="position:absolute;inset:0"><div class="fs-bg bg-plane"></div><div class="fs-bg bg-ship"></div><div class="fs-bg bg-wh"></div></div><div class="fs-dim" aria-hidden="true"></div>
<div class="fs-top"><span class="up" style="color:rgba(255,255,255,.75)">Two-step sign-in</span><a href="<?= sl_route('PublicTracking') ?>" style="color:#fff">Track a package →</a></div>
<div class="fs-cap"><div class="fs-caps"><div><span class="fs-ic"><i class="ic i-mail" aria-hidden="true"></i></span><span class="up" style="color:rgba(255,255,255,.7)">Step 1</span><h2>Enter the code we sent</h2><span style="opacity:.8;max-width:460px">Every log-in needs a fresh code. It expires in 5 minutes.</span></div><div><span class="fs-ic"><i class="ic i-key" aria-hidden="true"></i></span><span class="up" style="color:rgba(255,255,255,.7)">Step 2</span><h2>Choose a new password</h2><span style="opacity:.8;max-width:460px">At least 8 characters with a number and a symbol.</span></div><div><span class="fs-ic"><i class="ic i-shield" aria-hidden="true"></i></span><span class="up" style="color:rgba(255,255,255,.7)">Stay safe</span><h2>Swift Lane never asks for your code</h2><span style="opacity:.8;max-width:460px">Do not share it with anyone, even our staff.</span></div></div><div class="fs-dots"><i></i><i></i><i></i></div></div></div>
</div>
