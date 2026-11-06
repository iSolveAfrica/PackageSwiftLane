<?php
/**
 * PasswordReset - generated from docs/handover/artifact-source/PasswordReset.dc.html by scripts/sl_convert.py.
 * Shell page: no (plain page)
 * Data contract ($d):
 *   loop  rules                        as $r
 *   if    notDone
 *   if    done
 *   value done, false, mCls, mMsg, matchCls, matchMsg, meter, notDone, op, pw, pw2, pwType, rules, s2, s3, setPw, setPw2, togglePw, true, update
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="sl-auth fp-full">
  <div class="form">
    <div>
      <div class="sl-brand"><span class="sl-logo"><i></i></span>Swift Lane</div>
      <div class="col g8" style="margin-top: 12px">
        <div class="sl-steps" aria-label="Step 2 of 3"><i class="on"></i><i class="<?= sl_v($d, 's2') ?>"></i><i class="<?= sl_v($d, 's3') ?>"></i></div>
        <div class="row between mut sm"><span>Verify it’s you</span><span class="fw5" style="color: var(--ink)">New password</span><span>Done</span></div>
      </div>
      <?php if (sl_get($d, 'notDone')): ?>
      <div class="col g6"><h1 style="font-size: 28px; font-weight: 500; letter-spacing: -.015em;margin:0">Choose a new password</h1><p class="mut" style="margin:0">Passwords reset every 30 days. Yours expires in 3 days.</p></div>
      <div class="sl-card col g8" style="padding:12px 14px"><div class="row between"><span class="fw5 sm"><i class="ic ic-sm i-lock" aria-hidden="true"></i> Password age</span><span class="sl-pill pending">Expires in 3 days</span></div><div class="sl-ticks r"><i style="width: 90%"></i></div><div class="row between mut sm"><span>Last changed Oct 9</span><span>27 of 30 days</span></div></div>
      <label class="sl-field"><span class="sl-lab">New password</span><div class="sl-inwrap"><i class="ic ic-sm i-lock" aria-hidden="true"></i><input class="sl-in" type="<?= sl_v($d, 'pwType') ?>" placeholder="At least 8 characters" value="<?= sl_v($d, 'pw') ?>" data-sl-on="input:setPw"><button class="sl-ib" style="width:30px;height:30px" aria-label="Show password" data-sl-on="click:togglePw"><i class="ic ic-sm i-eye" aria-hidden="true"></i></button></div></label>
      <div class="pr-meter <?= sl_v($d, 'meter') ?>" aria-hidden="true"><i></i><i></i><i></i><i></i></div><span class="sm <?= sl_v($d, 'mCls') ?>"><?= sl_v($d, 'mMsg') ?></span>
      <label class="sl-field"><span class="sl-lab">Confirm password</span><div class="sl-inwrap"><i class="ic ic-sm i-lock" aria-hidden="true"></i><input class="sl-in" type="<?= sl_v($d, 'pwType') ?>" placeholder="Repeat the password" value="<?= sl_v($d, 'pw2') ?>" data-sl-on="input:setPw2"></div><span class="sm <?= sl_v($d, 'matchCls') ?>"><?= sl_v($d, 'matchMsg') ?></span></label>
      <div class="col g6 sm"><?php foreach ((array) sl_get($d, 'rules', []) as $__i => $r): ?><span class="row g8 <?= sl_v($r, 'cls') ?>"><i class="ic ic-sm <?= sl_v($r, 'icon') ?>" aria-hidden="true"></i><?= sl_v($r, 't') ?></span><?php endforeach; ?></div>
      <div class="row g10 sm"><button class="sl-toggle on" role="switch" aria-checked="true" aria-label="Sign out other devices"></button><span>Sign out of all other devices</span></div>
      <button class="sl-btn block" style="height:46px;opacity:<?= sl_v($d, 'op') ?>" data-sl-on="click:update">Update password</button>
      <p class="mut sm" style="margin:0">You’re reminded 7 days before and on the day it expires. Expired passwords must be reset before you can log in.</p>
      <?php endif; ?>
      <?php if (sl_get($d, 'done')): ?><div class="col g12" style="align-items:center;text-align:center;padding:20px 0"><span class="sl-tile" style="width:64px;height:64px;border-radius:20px;background:var(--green-bg);color:var(--green-ink)"><i class="ic i-check" aria-hidden="true"></i></span><h1 style="font-size:26px;font-weight:500;margin:0">Password updated</h1><p class="mut" style="margin:0">Next reset due Dec 5, 2026. Other devices were signed out.</p><a class="sl-btn block" href="<?= sl_route('Main') ?>" style="height:46px">Continue to Swift Lane</a></div><?php endif; ?>
      <div class="row between sm"><a href="<?= sl_route('LoginOtp') ?>" class="row g6 fw5"><i class="ic ic-sm i-left" aria-hidden="true"></i>Back</a><a href="<?= sl_route('CsInbox') ?>" class="mut">Need help?</a></div>
    </div>
  </div>
  <div class="art fs" aria-label="Pictures and tips"><div aria-hidden="true" style="position:absolute;inset:0"><div class="fs-bg bg-plane"></div><div class="fs-bg bg-ship"></div><div class="fs-bg bg-wh"></div></div><div class="fs-dim" aria-hidden="true"></div>
<div class="fs-top"><span class="up" style="color:rgba(255,255,255,.75)">Password reset</span><a href="<?= sl_route('PublicTracking') ?>" style="color:#fff">Track a package →</a></div>
<div class="fs-cap"><div class="fs-caps"><div><span class="fs-ic"><i class="ic i-mail" aria-hidden="true"></i></span><span class="up" style="color:rgba(255,255,255,.7)">Step 1</span><h2>Enter the code we sent you</h2><span style="opacity:.8;max-width:460px">One-time codes are single use and expire in 10 minutes.</span></div><div><span class="fs-ic"><i class="ic i-key" aria-hidden="true"></i></span><span class="up" style="color:rgba(255,255,255,.7)">Step 2</span><h2>Choose a new password</h2><span style="opacity:.8;max-width:460px">At least 8 characters with a number and a symbol.</span></div><div><span class="fs-ic"><i class="ic i-shield" aria-hidden="true"></i></span><span class="up" style="color:rgba(255,255,255,.7)">Stay safe</span><h2>Swift Lane never asks for your code</h2><span style="opacity:.8;max-width:460px">Do not share it with anyone, even our staff.</span></div></div><div class="fs-dots"><i></i><i></i><i></i></div></div></div>
</div>
