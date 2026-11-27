<?php
/**
 * M-AddCust: add a customer (or a recipient with ?type=recipient) on a phone. Same $d and the same endpoints
 * and field names as the web page: customer → ajax/customers/customers_add_ajax.php with the e-mail OTP step
 * (client_otp_send / verify / discard); recipient → ajax/sl/recipient_ajax.php (op=save).
 */
require_once CDP_ROOT . '/views/sl/data/phone_people.php';
$slPhone['nav'] = 'none';
$isR = $d['type'] === 'recipient';
$countries = function () use ($d) { $o = '<option value="">Choose</option>'; foreach ($d['countries'] as $c) $o .= '<option value="' . (int) $c->id . '">' . h($c->name) . '</option>'; return $o; };
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-people.css')) ?>">
<?= sl_phoneTop($isR ? 'Add recipient' : 'Add customer', '', ['back' => sl_route($isR ? 'Recipients' : 'Customers')]) ?>
<?php if (!$isR): ?>
<form id="ac-form" class="pp-form" autocomplete="off" novalidate>
<main class="mx-body">
<input type="hidden" name="total_address" value="1">
<section class="mx-card mx-form">
  <?php if ($d['canRecip']): ?><div class="mx-f"><small id="ac-type">Type</small><div class="mx-fseg" role="group" aria-labelledby="ac-type"><button type="button" class="on" aria-pressed="true">Customer</button><button type="button" aria-pressed="false" onclick="location.href='<?= h(sl_url(['type' => 'recipient'])) ?>'">Recipient</button></div></div><?php endif; ?>
  <div class="mx-two"><label class="mx-f"><small>First name</small><input class="mx-in" name="fname" required autocomplete="given-name"></label><label class="mx-f"><small>Last name</small><input class="mx-in" name="lname" required autocomplete="family-name"></label></div>
  <label class="mx-f"><small>Phone (WhatsApp)</small><input class="mx-in" type="tel" name="phone" required inputmode="tel" autocomplete="tel" placeholder="+233 "></label>
  <label class="mx-f"><small>Email</small><input class="mx-in" type="email" name="email" required inputmode="email" autocomplete="email"></label>
  <div class="mx-two"><label class="mx-f"><small>Username</small><input class="mx-in" name="username" required minlength="4" pattern="[A-Za-z0-9]+" autocapitalize="none" autocomplete="off" title="At least 4 letters or digits"></label><label class="mx-f"><small>Password</small><input class="mx-in" name="password" id="ac-pw" required minlength="8" autocomplete="new-password"></label></div>
  <button type="button" class="mx-btn sec" id="ac-gen">Generate a password</button>
</section>
<section class="mx-card mx-form">
  <label class="mx-f"><small>Country</small><select class="mx-in" name="country[0]" required><?= $countries() ?></select></label>
  <label class="mx-f"><small>Address</small><input class="mx-in" name="address[0]" required autocomplete="street-address"></label>
  <div class="mx-two"><label class="mx-f"><small>Region (id)</small><input class="mx-in" name="state[0]" inputmode="numeric"></label><label class="mx-f"><small>City (id)</small><input class="mx-in" name="city[0]" inputmode="numeric"></label></div>
  <label class="mx-f"><small>Postal / GPS code</small><input class="mx-in" name="postal[0]" autocomplete="postal-code"></label>
  <div class="mx-two"><label class="mx-f"><small>ID type</small><select class="mx-in" name="document_type"><option value="">—</option><option value="PSP">Passport</option><option value="ECW">National ID card</option><option value="DNI">Other ID</option></select></label><label class="mx-f"><small>ID number</small><input class="mx-in" name="document_number" autocomplete="off"></label></div>
  <label class="mx-f"><small>Gender (optional)</small><select class="mx-in" name="gender"><option value="">—</option><option>Male</option><option>Female</option><option>Other</option></select></label>
  <label class="mx-f"><small>Staff note</small><textarea class="mx-in area" name="notes" rows="3" placeholder="Visible to staff only"></textarea></label>
</section>
<section class="mx-card mx-tgs">
  <div class="mx-tg"><span class="mx-rm"><b>Locker ID</b><small><?= h($d['lockerHint']) ?> · <?= h($d['agency']) ?></small></span><span class="sl-pill delivered">Auto</span></div>
  <div class="mx-tg"><span class="mx-rm"><b>E-mail check</b><small><?= $d['otp'] ? 'A code is sent to the customer after saving' : 'Off (Settings › Login)' ?></small></span><span class="sl-pill <?= $d['otp'] ? 'delivered' : 'plain' ?>"><?= $d['otp'] ? 'On' : 'Off' ?></span></div>
  <div class="mx-tg"><span class="mx-rm"><b>Send offers and news by e-mail</b></span><button type="button" class="mx-sw" role="switch" aria-checked="false" aria-label="Send offers and news by e-mail" data-input="#ac-news"></button><input type="hidden" name="newsletter" id="ac-news" value="0"></div>
</section>
<section class="mx-card mx-form" id="ac-otp" hidden>
  <div class="mx-ban info"><i class="ic ic-sm i-mail" aria-hidden="true"></i><span>A 6-digit code was sent to <b id="ac-otp-mail"></b>. Ask the customer for it.</span></div>
  <label class="mx-f"><small>Code</small><input class="mx-in pp-otp" id="ac-otp-code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="6 digits"></label>
  <p class="mx-note" id="ac-otp-msg" aria-live="polite"></p>
  <div class="mx-btns"><button class="mx-btn" type="button" id="ac-otp-ok">Verify</button><button class="mx-btn sec" type="button" id="ac-otp-resend">Resend code</button><button class="mx-btn sec" type="button" id="ac-otp-cancel">Discard account</button></div>
</section>
</main>
<div class="mx-foot pp-foot"><div class="mx-btns"><button type="submit" class="mx-btn" id="ac-save">Add customer</button></div></div>
</form>
<script>
(function () {
  var f = document.getElementById('ac-form'), box = document.getElementById('ac-otp'), msg = document.getElementById('ac-otp-msg'), done = <?= json_encode(sl_route('Customers')) ?>;
  function note(t) { msg.textContent = t; }
  document.getElementById('ac-gen').addEventListener('click', function () { var c = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789', s = '', a = new Uint32Array(12); crypto.getRandomValues(a); a.forEach(function (n) { s += c[n % c.length]; }); document.getElementById('ac-pw').value = s; });
  function send() { return SL.post('ajax/customers/client_otp_send_ajax.php', {}).then(function (j) { note(j.ok ? 'Code sent to ' + (j.email || 'the customer') : (j.error || 'Could not send the code')); }); }
  f.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!f.reportValidity()) return;
    var btn = document.getElementById('ac-save'); btn.disabled = true;
    SL.post('ajax/customers/customers_add_ajax.php', new FormData(f)).then(function (j) {
      if (j.status === 'success') { SL.toast(j.message || 'Customer created'); setTimeout(function () { location.href = done; }, 500); return; }
      if (j.status === 'verify') { document.getElementById('ac-otp-mail').textContent = j.email || ''; box.hidden = false; box.scrollIntoView({ behavior: 'smooth' }); send(); return; }
      SL.toast(j.message || 'Please check the form', true);
    }).catch(function () { SL.toast('Network error', true); }).finally(function () { btn.disabled = false; });
  });
  document.getElementById('ac-otp-resend').addEventListener('click', send);
  document.getElementById('ac-otp-ok').addEventListener('click', function () {
    SL.post('ajax/customers/client_otp_verify_ajax.php', { otp_code: document.getElementById('ac-otp-code').value }).then(function (j) {
      if (j.ok) { SL.toast('E-mail verified, customer created'); setTimeout(function () { location.href = done; }, 500); } else note(j.error || 'Wrong code');
    });
  });
  document.getElementById('ac-otp-cancel').addEventListener('click', function () {
    if (!confirm('Discard the new account?')) return;
    SL.post('ajax/customers/client_otp_discard_ajax.php', {}).then(function () { box.hidden = true; SL.toast('Account discarded'); });
  });
})();
</script>
<?php else: ?>
<form id="ac-recip" class="pp-form" data-sl-form action="ajax/sl/recipient_ajax.php" method="post">
<main class="mx-body">
<input type="hidden" name="op" value="save">
<section class="mx-card mx-form">
  <div class="mx-f"><small id="ac-type">Type</small><div class="mx-fseg" role="group" aria-labelledby="ac-type"><button type="button" aria-pressed="false" onclick="location.href='<?= h(sl_url(['type' => null])) ?>'">Customer</button><button type="button" class="on" aria-pressed="true">Recipient</button></div></div>
  <label class="mx-f"><small>Belongs to customer</small><select class="mx-in" name="sender_id"><option value="<?= (int) $_SESSION['userid'] ?>">Me (staff)</option><?php foreach ($d['customers'] as $c): ?><option value="<?= (int) $c->id ?>"<?= (int) $c->id === $d['owner'] ? ' selected' : '' ?>><?= h(trim($c->fname . ' ' . $c->lname) . ($c->locker ? ' · ' . $c->locker : '')) ?></option><?php endforeach; ?></select></label>
  <div class="mx-two"><label class="mx-f"><small>First name</small><input class="mx-in" name="fname" required></label><label class="mx-f"><small>Last name</small><input class="mx-in" name="lname"></label></div>
  <label class="mx-f"><small>Phone</small><input class="mx-in" type="tel" name="phone" required inputmode="tel"></label>
  <label class="mx-f"><small>E-mail (optional)</small><input class="mx-in" type="email" name="email" inputmode="email"></label>
</section>
<section class="mx-card mx-form">
  <label class="mx-f"><small>Country</small><select class="mx-in" name="country" required><?= $countries() ?></select></label>
  <label class="mx-f"><small>Address</small><input class="mx-in" name="address" required></label>
  <div class="mx-two"><label class="mx-f"><small>Region (id)</small><input class="mx-in" name="state" inputmode="numeric"></label><label class="mx-f"><small>City (id)</small><input class="mx-in" name="city" inputmode="numeric"></label></div>
  <label class="mx-f"><small>Postal / GPS code</small><input class="mx-in" name="postal"></label>
</section>
</main>
<div class="mx-foot pp-foot"><div class="mx-btns"><button type="submit" class="mx-btn">Add recipient</button></div></div>
</form>
<?php endif; ?>
