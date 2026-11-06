<?php
/**
 * SignUp - generated from docs/handover/artifact-source/SignUp.dc.html by scripts/sl_convert.py.
 * Shell page: no (plain page)
 * Data contract ($d):
 *   loop  rules                        as $r
 *   loop  boxes                        as $b
 *   loop  docs                         as $d
 *   if    uWait
 *   if    eWait
 *   if    otpOpen
 *   if    verified
 *   if    whyNot
 *   value boxes, create, createLabel, createOp, docLabel, docNo, docs, eMsg, eSt, eWait, email, false, fname, lname, mMsg, mSt, meter, oMsg, oSt, otp, otpOpen, pMsg, pSt, phone, phoneMask, pw, pw2, pwType, resend, rules, s1, s2, s3, sendCls, sendLabel, sendOtp, setDoc, setE, setF, setL, setOtp, setP, setP2, setPh, setU, termsAria, termsCls, togglePw, toggleTerms, true, uMsg, uSt, uWait, user, verified, whyNot
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="su2">
  <header class="su2-top"><a class="sl-brand" href="<?= sl_route('Login') ?>" style="color:inherit"><span class="sl-logo"><i></i></span>Swift Lane</a><span class="grow"></span><span class="mut sm su2-hide">Already have an account?</span><a class="sl-btn ghost sm" href="<?= sl_route('Login') ?>">Log in</a></header>
  <main class="su2-main">
    <div class="su2-head"><span class="su2-badge"><i class="ic ic-sm i-pin" aria-hidden="true"></i>Free US address · ready after sign-up</span><h1>Create your account</h1><p>Three short steps. Fields with * are required.</p></div>
    <nav class="su2-steps" aria-label="Sign-up steps"><a href="#su-step1" class="<?= sl_v($d, 's1') ?>"><b>1</b><span>Your details</span></a><i></i><a href="#su-step2" class="<?= sl_v($d, 's2') ?>"><b>2</b><span>WhatsApp</span></a><i></i><a href="#su-step3" class="<?= sl_v($d, 's3') ?>"><b>3</b><span>Address and ID</span></a></nav>
<section class="su-card" id="su-step1"><div class="su-ch"><b class="su-n <?= sl_v($d, 's1') ?>">1</b><div class="grow"><h2>Your details</h2><p>Your name, sign-in and a strong password</p></div></div><div class="su-bd">
        <div class="grid cols2" style="gap:12px"><label class="sl-field"><span class="sl-lab">First name *</span><input class="sl-in" autocomplete="given-name" value="<?= sl_v($d, 'fname') ?>" data-sl-on="change:setF"></label><label class="sl-field"><span class="sl-lab">Last name *</span><input class="sl-in" autocomplete="family-name" value="<?= sl_v($d, 'lname') ?>" data-sl-on="change:setL"></label></div>
        <div class="grid cols2 su-g"><label class="sl-field"><span class="sl-lab">Username *</span><div class="sl-inwrap"><i class="ic ic-sm i-user" aria-hidden="true"></i><input class="sl-in" autocomplete="username" autocapitalize="none" value="<?= sl_v($d, 'user') ?>" data-sl-on="change:setU"></div><span class="su-st <?= sl_v($d, 'uSt') ?>" aria-live="polite"><?php if (sl_get($d, 'uWait')): ?><span class="su-spin"></span><?php endif; ?><?= sl_v($d, 'uMsg') ?></span></label>
        <label class="sl-field"><span class="sl-lab">Company <span class="mut">(optional)</span></span><input class="sl-in" autocomplete="organization" placeholder="Company name"></label></div>
        <label class="sl-field"><span class="sl-lab">Email *</span><div class="sl-inwrap"><i class="ic ic-sm i-mail" aria-hidden="true"></i><input class="sl-in" type="email" autocomplete="email" value="<?= sl_v($d, 'email') ?>" data-sl-on="change:setE"></div><span class="su-st <?= sl_v($d, 'eSt') ?>" aria-live="polite"><?php if (sl_get($d, 'eWait')): ?><span class="su-spin"></span><?php endif; ?><?= sl_v($d, 'eMsg') ?></span></label>
        <div class="grid cols2 su-g"><label class="sl-field"><span class="sl-lab">Password *</span><div class="sl-inwrap"><i class="ic ic-sm i-lock" aria-hidden="true"></i><input class="sl-in" type="<?= sl_v($d, 'pwType') ?>" autocomplete="new-password" value="<?= sl_v($d, 'pw') ?>" data-sl-on="input:setP"><button class="sl-ib" style="width:30px;height:30px" aria-label="Show password" data-sl-on="click:togglePw"><i class="ic ic-sm i-eye" aria-hidden="true"></i></button></div>
          <div class="su-meter <?= sl_v($d, 'meter') ?>" aria-hidden="true"><i></i><i></i><i></i><i></i></div><span class="su-st <?= sl_v($d, 'pSt') ?>"><?= sl_v($d, 'pMsg') ?></span>
          <div class="su-rules"><?php foreach ((array) sl_get($d, 'rules', []) as $__i => $r): ?><span class="<?= sl_v($r, 'cls') ?>"><i class="ic ic-sm <?= sl_v($r, 'icon') ?>" aria-hidden="true"></i><?= sl_v($r, 't') ?></span><?php endforeach; ?></div></label>
        <label class="sl-field"><span class="sl-lab">Confirm password *</span><div class="sl-inwrap"><i class="ic ic-sm i-lock" aria-hidden="true"></i><input class="sl-in" type="<?= sl_v($d, 'pwType') ?>" autocomplete="new-password" value="<?= sl_v($d, 'pw2') ?>" data-sl-on="input:setP2"></div><span class="su-st <?= sl_v($d, 'mSt') ?>"><?= sl_v($d, 'mMsg') ?></span></label></div>
      </div></section>
<section class="su-card" id="su-step2"><div class="su-ch"><b class="su-n <?= sl_v($d, 's2') ?>">2</b><div class="grow"><h2>Verify WhatsApp</h2><p>We send a 6-digit code. Parcel updates come here too.</p></div></div><div class="su-bd">
        <div class="sl-field"><span class="sl-lab">Phone (WhatsApp) *</span><div class="row g8"><div class="sl-sel" style="width:116px;flex:none"><span>🇬🇭 +233</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div><input class="sl-in grow" type="tel" autocomplete="tel" value="<?= sl_v($d, 'phone') ?>" data-sl-on="change:setPh"><button class="sl-btn <?= sl_v($d, 'sendCls') ?>" style="flex:none" data-sl-on="click:sendOtp"><?= sl_v($d, 'sendLabel') ?></button></div><span class="sl-hint">We send a 6-digit code to this number on WhatsApp. Delivery updates come here too.</span></div>
        <?php if (sl_get($d, 'otpOpen')): ?><div class="col g10"><div class="row between sm"><span>Code sent to <b class="fw5 mono">+233 <?= sl_v($d, 'phoneMask') ?></b> on WhatsApp</span><span class="mut"><?= sl_v($d, 'resend') ?></span></div>
          <div class="su-otp"><div class="bx" aria-hidden="true"><?php foreach ((array) sl_get($d, 'boxes', []) as $__i => $b): ?><span class="<?= sl_v($b, 'cls') ?>"><?= sl_v($b, 'd') ?></span><?php endforeach; ?></div><input inputmode="numeric" maxlength="6" aria-label="6-digit code" value="<?= sl_v($d, 'otp') ?>" data-sl-on="input:setOtp"></div>
          <span class="su-st <?= sl_v($d, 'oSt') ?>" aria-live="polite"><?= sl_v($d, 'oMsg') ?></span></div><?php endif; ?>
        <?php if (sl_get($d, 'verified')): ?><div class="su-wa"><i class="ic ic-sm i-check" aria-hidden="true"></i>WhatsApp number verified</div><?php endif; ?>
      </div></section>
<section class="su-card" id="su-step3"><div class="su-ch"><b class="su-n <?= sl_v($d, 's3') ?>">3</b><div class="grow"><h2>Address and ID</h2><p>Where we deliver in Ghana, and the ID we check at pickup</p></div></div><div class="su-bd">
        <div class="grid cols3" style="gap:12px"><div class="sl-field"><span class="sl-lab">Country *</span><div class="sl-sel"><span>Ghana</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div><div class="sl-field"><span class="sl-lab">Region / state *</span><div class="sl-sel"><span>Greater Accra</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div><div class="sl-field"><span class="sl-lab">City *</span><div class="sl-sel"><span>Accra</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div></div>
        <div class="grid cols2" style="gap:12px;grid-template-columns:2fr 1fr"><label class="sl-field"><span class="sl-lab">Address *</span><input class="sl-in" autocomplete="street-address" value="14 Palm Close, East Legon"></label><label class="sl-field"><span class="sl-lab">Postal / GPS code *</span><input class="sl-in" autocomplete="postal-code" value="GA-492-1234"></label></div>
        <div class="grid cols2" style="gap:12px"><div class="sl-field"><span class="sl-lab">ID type</span><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'docs', []) as $__i => $it_d): ?><button class="<?= sl_v($it_d, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($it_d, 'label') ?></button><?php endforeach; ?></div></div><label class="sl-field"><span class="sl-lab"><?= sl_v($d, 'docLabel') ?> number *</span><input class="sl-in mono" value="<?= sl_v($d, 'docNo') ?>" data-sl-on="change:setDoc"></label></div>
        <div class="grid cols2" style="gap:12px"><label class="su-up"><i class="ic ic-sm i-user" aria-hidden="true"></i><span class="grow">Profile photo <span class="mut">(optional)</span></span><i class="ic ic-sm i-upload" aria-hidden="true"></i><input type="file" accept="image/*" aria-label="Upload profile photo"></label><label class="su-up"><i class="ic ic-sm i-file" aria-hidden="true"></i><span class="grow">Photo of your ID</span><i class="ic ic-sm i-upload" aria-hidden="true"></i><input type="file" accept="image/*" aria-label="Upload ID photo"></label></div>
        <label class="row g10 top" style="cursor:pointer"><span class="sl-chk <?= sl_v($d, 'termsCls') ?>" style="margin-top:2px" data-sl-on="click:toggleTerms" role="checkbox" aria-checked="<?= sl_v($d, 'termsAria') ?>"></span><span>I agree to the <a href="<?= sl_route('Main') ?>" style="text-decoration:underline">Terms and Conditions</a></span></label>
      </div></section>

    <div class="su2-foot"><button class="sl-btn block" style="height:52px;font-size:15.5px;border-radius:14px;opacity:<?= sl_v($d, 'createOp') ?>" data-sl-on="click:create"><?= sl_v($d, 'createLabel') ?></button>
      <?php if (sl_get($d, 'whyNot')): ?><span class="su-st bad" style="justify-content:center"><?= sl_v($d, 'whyNot') ?></span><?php endif; ?>
      <p class="mut sm" style="text-align:center;margin:0">Already have an account? <a href="<?= sl_route('Login') ?>" class="fw5" style="color:var(--ink);text-decoration:underline">Log in</a> · <a href="<?= sl_route('PublicTracking') ?>" style="text-decoration:underline">Track a package</a></p></div>
  </main>
</div>
