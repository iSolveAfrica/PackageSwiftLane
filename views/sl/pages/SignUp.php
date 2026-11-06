<?php
/**
 * SignUp - from docs/handover/artifact-source/SignUp.dc.html (scripts/sl_convert.py), bound to sign-up.php.
 * Posts to the legacy ajax/sign_up_ajax.php (same field names, CSRF, uploads); the email code
 * is verified on auth-otp.php?flow=signup, then an admin approves the account.
 * Shell page: no (plain page)
 * Data contract ($d): countries[], locker, prefix, docs[[code,label]], otp, site, heading, button, city, country
 */
if (!isset($d)) { $d = []; }
$countries = (array) sl_get($d, 'countries', []);
$docs = (array) sl_get($d, 'docs', []);
$rules = [['len', '8+ characters'], ['num', 'A number'], ['sym', 'A symbol'], ['case', 'Upper and lower case']];
?>
<div class="su2">
  <header class="su2-top"><a class="sl-brand" href="<?= sl_route('Login') ?>" style="color:inherit"><span class="sl-logo"><i></i></span><?= sl_v($d, 'site') ?></a><span class="grow"></span><span class="mut sm su2-hide">Already have an account?</span><a class="sl-btn ghost sm" href="<?= sl_route('Login') ?>">Log in</a></header>
  <main class="su2-main">
    <div class="su2-head"><span class="su2-badge"><i class="ic ic-sm i-pin" aria-hidden="true"></i>Free shipping address · suite <?= sl_v($d, 'prefix') ?> <?= sl_v($d, 'locker') ?> reserved for you</span><h1><?= sl_v($d, 'heading') ?></h1><p>Three short steps. Fields with * are required.</p></div>
    <nav class="su2-steps" aria-label="Sign-up steps"><a href="#su-step1" class="on"><b>1</b><span>Your details</span></a><i></i><a href="#su-step2"><b>2</b><span>Phone</span></a><i></i><a href="#su-step3"><b>3</b><span>Address and ID</span></a></nav>
    <form id="su-form" data-sl-legacy-form action="ajax/sign_up_ajax.php" method="post" enctype="multipart/form-data" class="col g16">
      <input type="hidden" name="<?= h(cdp_csrf_param()) ?>" value="<?= h(cdp_csrf_token()) ?>">
      <input type="hidden" name="locker" value="<?= sl_v($d, 'locker') ?>">
      <input type="hidden" name="order_no_main" value="<?= sl_v($d, 'locker') ?>">
      <input type="hidden" name="phone" value="">
      <input type="hidden" name="document_type" value="<?= h($docs[0][0] ?? 'DNI') ?>">
      <input type="hidden" name="terms" value="">
<section class="su-card" id="su-step1"><div class="su-ch"><b class="su-n on">1</b><div class="grow"><h2>Your details</h2><p>Your name, sign-in and a strong password</p></div></div><div class="su-bd">
        <div class="grid cols2" style="gap:12px"><label class="sl-field"><span class="sl-lab">First name *</span><input class="sl-in" name="fname" autocomplete="given-name" required></label><label class="sl-field"><span class="sl-lab">Last name *</span><input class="sl-in" name="lname" autocomplete="family-name" required></label></div>
        <div class="grid cols2 su-g"><label class="sl-field"><span class="sl-lab">Username *</span><div class="sl-inwrap"><i class="ic ic-sm i-user" aria-hidden="true"></i><input class="sl-in" name="username" autocomplete="username" autocapitalize="none" minlength="4" pattern="[A-Za-z0-9]{4,}" required></div><span class="su-st mut" aria-live="polite">Letters and numbers only, at least 4</span></label>
        <label class="sl-field"><span class="sl-lab">Company <span class="mut">(optional)</span></span><input class="sl-in" name="company" autocomplete="organization" placeholder="Company name"></label></div>
        <label class="sl-field"><span class="sl-lab">Email *</span><div class="sl-inwrap"><i class="ic ic-sm i-mail" aria-hidden="true"></i><input class="sl-in" name="email" type="email" autocomplete="email" required></div><span class="su-st mut" aria-live="polite"><?= sl_get($d, 'otp') ? 'We send a 6-digit code here to confirm it' : 'Your account updates go here' ?></span></label>
        <div class="grid cols2 su-g"><label class="sl-field"><span class="sl-lab">Password *</span><div class="sl-inwrap"><i class="ic ic-sm i-lock" aria-hidden="true"></i><input class="sl-in" id="pass" name="pass" type="password" autocomplete="new-password" minlength="8" required data-sl-pw="#su-meter" data-sl-pw-msg="#su-pmsg" data-sl-pw-confirm="#pass2" data-sl-pw-match="#su-match"><span role="button" tabindex="0" class="sl-ib" style="width:30px;height:30px;cursor:pointer" aria-label="Show password" data-sl-peek="#pass"><i class="ic ic-sm i-eye" aria-hidden="true"></i></span></div>
          <div class="su-meter" id="su-meter" aria-hidden="true"><i></i><i></i><i></i><i></i></div><span class="su-st mut" id="su-pmsg"></span>
          <div class="su-rules"><?php foreach ($rules as $r): ?><span data-sl-rule="<?= h($r[0]) ?>"><i class="ic ic-sm i-x" aria-hidden="true"></i><?= h($r[1]) ?></span><?php endforeach; ?></div></label>
        <label class="sl-field"><span class="sl-lab">Confirm password *</span><div class="sl-inwrap"><i class="ic ic-sm i-lock" aria-hidden="true"></i><input class="sl-in" id="pass2" name="pass2" type="password" autocomplete="new-password" minlength="8" required></div><span class="su-st" id="su-match"></span></label></div>
      </div></section>
<section class="su-card" id="su-step2"><div class="su-ch"><b class="su-n">2</b><div class="grow"><h2>Your phone</h2><p>Parcel updates come here on WhatsApp and SMS.</p></div></div><div class="su-bd">
        <div class="sl-field"><span class="sl-lab">Phone (WhatsApp) *</span><div class="row g8"><select class="sl-in" name="phone_cc" style="width:136px;flex:none" aria-label="Country code"><?php foreach ($countries as $c): if (!$c->phone_code) continue; ?><option value="+<?= h($c->phone_code) ?>" <?= strcasecmp((string) $c->name, (string) sl_get($d, 'country', '')) === 0 ? 'selected' : '' ?>><?= h($c->name) ?> +<?= h($c->phone_code) ?></option><?php endforeach; ?></select><input class="sl-in grow" name="phone_number" type="tel" autocomplete="tel-national" inputmode="numeric" required placeholder="24 412 5678"></div><span class="sl-hint">After approval we confirm this number with a one-time code the first time you log in. Delivery updates come here too.</span></div>
      </div></section>
<section class="su-card" id="su-step3"><div class="su-ch"><b class="su-n">3</b><div class="grow"><h2>Address and ID</h2><p>Where we deliver, and the ID we check at pickup</p></div></div><div class="su-bd">
        <div class="grid cols3" style="gap:12px"><label class="sl-field"><span class="sl-lab">Country *</span><select class="sl-in" id="su-country" name="country" required><option value="">Country</option><?php foreach ($countries as $c): ?><option value="<?= (int) $c->id ?>"><?= h($c->name) ?></option><?php endforeach; ?></select></label><label class="sl-field"><span class="sl-lab">Region / state *</span><select class="sl-in" id="su-state" name="state" required disabled><option value="">Region / state</option></select></label><label class="sl-field"><span class="sl-lab">City *</span><select class="sl-in" id="su-city" name="city" required disabled><option value="">City</option></select></label></div>
        <div class="grid cols2" style="gap:12px;grid-template-columns:2fr 1fr"><label class="sl-field"><span class="sl-lab">Address *</span><input class="sl-in" name="address" autocomplete="street-address" required placeholder="Street, house number, area"></label><label class="sl-field"><span class="sl-lab">Postal / GPS code *</span><input class="sl-in" name="postal" autocomplete="postal-code" required placeholder="GA-492-1234"></label></div>
        <div class="grid cols2" style="gap:12px"><div class="sl-field"><span class="sl-lab">ID type</span><div class="sl-seg boxed"><?php foreach ($docs as $i => $dt): ?><button type="button" class="<?= $i === 0 ? 'on' : '' ?>" data-sl-doc="<?= h($dt[0]) ?>"><?= h($dt[1]) ?></button><?php endforeach; ?></div></div><label class="sl-field"><span class="sl-lab" id="su-doclabel"><?= h($docs[0][1] ?? 'ID') ?> number *</span><input class="sl-in mono" name="document_number" required></label></div>
        <div class="grid cols2" style="gap:12px"><label class="su-up"><i class="ic ic-sm i-user" aria-hidden="true"></i><span class="grow">Profile photo <span class="mut">(optional)</span></span><i class="ic ic-sm i-upload" aria-hidden="true"></i><input type="file" name="avatar" accept="image/*" aria-label="Upload profile photo"></label><label class="su-up"><i class="ic ic-sm i-file" aria-hidden="true"></i><span class="grow">Photo of your ID <span class="mut">(optional)</span></span><i class="ic ic-sm i-upload" aria-hidden="true"></i><input type="file" name="document_photo" accept="image/*" aria-label="Upload ID photo"></label></div>
        <label class="row g10 top" style="cursor:pointer"><span class="sl-chk" style="margin-top:2px" data-sl-terms role="checkbox" aria-checked="false" tabindex="0"></span><span>I agree to the <a href="terms.php" target="_blank" rel="noopener" style="text-decoration:underline">Terms and Conditions</a></span></label>
      </div></section>

    <div class="su2-foot"><div class="sl-banner" data-sl-msg style="display:none"></div><button class="sl-btn block" type="submit" style="height:52px;font-size:15.5px;border-radius:14px"><?= sl_v($d, 'button') ?></button>
      <p class="mut sm" style="text-align:center;margin:0">Already have an account? <a href="<?= sl_route('Login') ?>" class="fw5" style="color:var(--ink);text-decoration:underline">Log in</a> · <a href="<?= sl_route('PublicTracking') ?>" style="text-decoration:underline">Track a package</a></p></div>
    </form>
  </main>
</div>
