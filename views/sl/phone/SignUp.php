<?php
/**
 * M-SignUp (sign-up.php, view SignUp): the web sign-up form in the app's three steps. Posts to
 * ajax/sign_up_ajax.php with the same fields, CSRF and uploads (data-sl-legacy-form + dataJs/sl/auth.js,
 * which also fills region / city, joins the phone number, sets the ID type and the terms value).
 */
$slPhone['nav'] = 'none';
$slPhone['cls'] = 'mx-bare';
$slPhone['scripts'][] = 'dataJs/sl/auth.js';
$slPhone['scripts'][] = 'dataJs/sl/phone/auth.js';
$countries = (array) ($d['countries'] ?? []);
$docs = (array) ($d['docs'] ?? []);
$rules = [['len', '8+ characters'], ['num', 'A number'], ['sym', 'A symbol'], ['case', 'Upper and lower case']];
$locker = trim((string) ($d['prefix'] ?? '') . (string) ($d['locker'] ?? ''));
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-auth.css')) ?>">
<main class="mx-body">
<div class="mx-auth-hero"><div style="display:flex;align-items:center;justify-content:space-between"><?= sl_brandLogo('white', 40) ?><a class="mx-lnk" href="<?= h(sl_route('Login') . '?form=1') ?>">Log in</a></div><small>Your free US, UK and China address</small></div>
<h1 class="mx-big"><?= h($d['heading'] ?? 'Create your account') ?></h1>
<p class="mx-lede">Three short steps. Fields with * are required.</p>
<nav class="mx-seg" aria-label="Sign-up steps" data-pa-steps><button type="button" class="on" data-pa-goto="#su-step1">1 Your details</button><button type="button" data-pa-goto="#su-step2">2 Your phone</button><button type="button" data-pa-goto="#su-step3">3 Address and ID</button></nav>
<form id="su-form" data-sl-legacy-form action="ajax/sign_up_ajax.php" method="post" enctype="multipart/form-data" style="display:contents">
<input type="hidden" name="<?= h(cdp_csrf_param()) ?>" value="<?= h(cdp_csrf_token()) ?>">
<input type="hidden" name="locker" value="<?= h($d['locker'] ?? '') ?>">
<input type="hidden" name="order_no_main" value="<?= h($d['locker'] ?? '') ?>">
<input type="hidden" name="phone" value="">
<input type="hidden" name="document_type" value="<?= h($docs[0][0] ?? 'DNI') ?>">
<input type="hidden" name="terms" value="">
<div class="mx-sec" id="su-step1"><h2>1 · Your details</h2></div>
<section class="mx-card mx-form">
<div class="mx-two"><label class="mx-f"><small>First name *</small><input class="mx-in mx-inp" name="fname" autocomplete="given-name" required></label><label class="mx-f"><small>Last name *</small><input class="mx-in mx-inp" name="lname" autocomplete="family-name" required></label></div>
<label class="mx-f"><small>Username *</small><input class="mx-in mx-inp" name="username" autocomplete="username" autocapitalize="none" spellcheck="false" minlength="4" pattern="[A-Za-z0-9]{4,}" required><small class="mx-fh">Letters and numbers only, at least 4</small></label>
<label class="mx-f"><small>Company (optional)</small><input class="mx-in mx-inp" name="company" autocomplete="organization" placeholder="Company name"></label>
<label class="mx-f"><small>Referral code (optional)</small><input class="mx-in mx-inp" name="referral_code" autocapitalize="characters" autocomplete="off" maxlength="32" placeholder="From a friend who ships with us" value="<?= h(preg_replace('/[^A-Z0-9-]/', '', strtoupper((string) ($_GET['ref'] ?? '')))) ?>"></label>
<label class="mx-f"><small>Email *</small><input class="mx-in mx-inp" name="email" type="email" inputmode="email" autocomplete="email" autocapitalize="none" required><small class="mx-fh"><?= !empty($d['otp']) ? 'We send a 6-digit code here to confirm it' : 'Your account updates go here' ?></small></label>
<div class="mx-f"><small><label for="pass">Password *</label></small><div class="pa-pw"><input class="mx-in mx-inp" id="pass" name="pass" type="password" autocomplete="new-password" minlength="8" required data-sl-pw="#su-meter" data-sl-pw-msg="#su-pmsg" data-sl-pw-confirm="#pass2" data-sl-pw-match="#su-match"><button type="button" class="mx-ib" data-sl-peek="#pass" aria-label="Show password" aria-pressed="false"><i class="ic ic-sm i-eye" aria-hidden="true"></i></button></div><span class="mx-trk pa-meter" id="su-meter" aria-hidden="true" style="height:6px;margin-top:4px"><i></i></span><small id="su-pmsg" aria-live="polite"></small></div>
<label class="mx-f"><small>Confirm password *</small><input class="mx-in mx-inp" id="pass2" name="pass2" type="password" autocomplete="new-password" minlength="8" required><small id="su-match" aria-live="polite"></small></label>
</section>
<div class="mx-chips pa-rules"><?php foreach ($rules as $r): ?><span data-sl-rule="<?= h($r[0]) ?>"><i class="ic ic-sm i-x" aria-hidden="true"></i> <?= h($r[1]) ?></span><?php endforeach; ?></div>
<div class="mx-sec" id="su-step2"><h2>2 · Your phone</h2></div>
<section class="mx-card mx-form">
<div class="mx-ph2"><label class="mx-f"><small>Code</small><select class="mx-in mx-inp" name="phone_cc" aria-label="Country code"><?php foreach ($countries as $c): if (empty($c->phone_code)) continue; ?><option value="+<?= h($c->phone_code) ?>"<?= strcasecmp((string) $c->name, (string) ($d['country'] ?? '')) === 0 ? ' selected' : '' ?>>+<?= h($c->phone_code) ?> <?= h($c->name) ?></option><?php endforeach; ?></select></label><label class="mx-f"><small>Phone (WhatsApp) *</small><input class="mx-in mx-inp" name="phone_number" type="tel" inputmode="numeric" autocomplete="tel-national" required placeholder="24 412 5678"></label></div>
</section>
<p class="mx-note">Parcel updates come here on WhatsApp and SMS. After approval we confirm this number with a one-time code the first time you log in.</p>
<div class="mx-sec" id="su-step3"><h2>3 · Address and ID</h2></div>
<section class="mx-card mx-form">
<label class="mx-f"><small>Country *</small><select class="mx-in mx-inp" id="su-country" name="country" required><option value="">Country</option><?php foreach ($countries as $c): ?><option value="<?= (int) $c->id ?>"><?= h($c->name) ?></option><?php endforeach; ?></select></label>
<div class="mx-two"><label class="mx-f"><small>Region / state *</small><select class="mx-in mx-inp" id="su-state" name="state" required disabled><option value="">Region / state</option></select></label><label class="mx-f"><small>City *</small><select class="mx-in mx-inp" id="su-city" name="city" required disabled><option value="">City</option></select></label></div>
<label class="mx-f"><small>Address *</small><input class="mx-in mx-inp" name="address" autocomplete="street-address" required placeholder="Street, house number, area"></label>
<label class="mx-f"><small>Postal / GPS code *</small><input class="mx-in mx-inp mono" name="postal" autocomplete="postal-code" autocapitalize="characters" required placeholder="GA-492-1234"></label>
<div class="mx-f"><small>ID type</small><div class="mx-fseg" role="group" aria-label="ID type"><?php foreach ($docs as $i => $dt): ?><button type="button" class="<?= $i === 0 ? 'on' : '' ?>" data-sl-doc="<?= h($dt[0]) ?>"><?= h($dt[1]) ?></button><?php endforeach; ?></div></div>
<label class="mx-f"><small id="su-doclabel"><?= h($docs[0][1] ?? 'ID') ?> number *</small><input class="mx-in mx-inp mono" name="document_number" required></label>
</section>
<section class="mx-ph">
<label class="su-up pa-up"><span><i class="ic i-image" aria-hidden="true"></i></span><small class="grow">Profile photo (optional)</small><input type="file" name="avatar" accept="image/*" aria-label="Upload profile photo"></label>
<label class="su-up pa-up"><span><i class="ic i-image" aria-hidden="true"></i></span><small class="grow">Photo of your ID (optional)</small><input type="file" name="document_photo" accept="image/*" aria-label="Upload ID photo"></label>
</section>
<?php if ($locker !== ''): ?><section class="mx-card mx-kv"><div><span>Your locker ID</span><b class="mono"><?= h($locker) ?></b></div></section><?php endif; ?>
<section class="mx-card mx-tgs"><div class="mx-tg"><span class="mx-rm"><b id="su-terms-l">I agree to the Terms and Conditions</b><small>Banned items and storage fees</small></span><button type="button" class="mx-sw" role="checkbox" aria-checked="false" aria-labelledby="su-terms-l" data-sl-terms></button></div><a class="mx-lnk" href="terms.php" target="_blank" rel="noopener">Read the Terms and Conditions</a></section>
<div class="mx-ban" data-sl-msg style="display:none" role="status"></div>
<div class="mx-btns"><button class="mx-btn b4-b" type="submit"><?= h($d['button'] ?? 'Create my account') ?></button></div>
</form>
<p class="mx-note"><?= !empty($d['otp']) ? 'Next: confirm the 6-digit code we send to your email, then an admin approves the account.' : 'Next: an admin approves the account and we email you.' ?> <a class="mx-lnk" style="min-height:0" href="<?= h(sl_route('PublicTracking')) ?>">Track a package</a></p>
</main>
