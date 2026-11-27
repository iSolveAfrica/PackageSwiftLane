<?php
/**
 * M-CProfile (edit): the customer's own profile, ID and security (customers_profile_edit.php?edit=1, sl_profileEditData()).
 * Writes go to the same legacy endpoints and field names as views/sl/pages/ProfileEdit.php:
 *   ajax/customers/customers_avatar_edit_ajax.php   photo (id, avatar)
 *   ajax/customers/customers_profile_edit_ajax.php  fname, lname, email, gender, notes, addresses (address_rows.js), password
 *   ajax/save_profile_document_ajax.php             document_type, document_number; ID photos through sl_idDialogsHtml()
 *   ajax/send_profile_phone_otp_ajax.php / verify_profile_phone_otp_ajax.php   WhatsApp number with a code
 */
$slPhone['nav'] = 'none';
$slPhone['scripts'][] = 'dataJs/sl/address_rows.js';
$r = $d['row'];
$id = (int) $r->id;
$doc = (array) ($d['doc'] ?? []);
$ps = (string) ($d['phoneStatus'] ?? 'unknown');
$phone = trim((string) $r->phone);
$own = !empty($d['isOwn']);
$name = (string) ($d['name'] ?? '');
$ini = function_exists('sl_initials') ? sl_initials($name ?: 'U') : '';
$countries = (array) ($d['countries'] ?? []);
$addrDelete = 'ajax/customers/customers_delete_address_ajax.php';
$sel = function (string $nm, array $opts, int $cur, string $ph, string $kind): string {
    $o = '<option value="">' . h($ph) . '</option>';
    foreach ($opts as $x) $o .= '<option value="' . (int) $x->id . '"' . ((int) $x->id === $cur ? ' selected' : '') . '>' . h($x->name) . '</option>';
    return '<select class="mx-in mx-inp" name="' . h($nm) . '[]" data-addr-' . $kind . '>' . $o . '</select>';
};
$rowHtml = function (array $a) use ($sel, $countries, $addrDelete): string {
    ob_start(); ?>
<div class="pc-adr" data-addr-row><input type="hidden" name="address_id[]" value="<?= (int) $a['id'] ?>">
<div class="b3-ch"><span class="mx-rm"><b><?= $a['id'] ? 'Saved address' : 'New address' ?></b></span><button type="button" class="b3-x bad" data-addr-remove<?= $a['id'] ? ' data-addr-delete="' . h($addrDelete) . '" data-id="' . (int) $a['id'] . '"' : '' ?>><i class="ic ic-sm i-x" aria-hidden="true"></i>Remove</button></div>
<label class="mx-f"><small>Country *</small><?= $sel('country', $countries, (int) $a['country'], 'Choose a country', 'country') ?></label>
<div class="mx-two"><label class="mx-f"><small>Region / state *</small><?= $sel('state', (array) $a['states'], (int) $a['state'], 'Choose a region', 'state') ?></label><label class="mx-f"><small>City *</small><?= $sel('city', (array) $a['cities'], (int) $a['city'], 'Choose a city', 'city') ?></label></div>
<label class="mx-f"><small>Address *</small><input class="mx-in mx-inp" name="address[]" value="<?= h($a['address']) ?>" placeholder="Street, house number, landmark" autocomplete="street-address"></label>
<label class="mx-f"><small>Postal / GPS code *</small><input class="mx-in mx-inp mono" name="postal[]" value="<?= h($a['postal']) ?>"></label>
</div>
<?php return ob_get_clean();
};
$blank = ['id' => 0, 'country' => 0, 'state' => 0, 'city' => 0, 'postal' => '', 'address' => '', 'states' => [], 'cities' => []];
$addrs = (array) ($d['addresses'] ?? []);
$pw = (array) ($d['pass'] ?? []);
$types = (array) ($d['docTypes'] ?? []);
if (($doc['type'] ?? '') !== '' && !isset($types[$doc['type']])) $types[$doc['type']] = $doc['type'];
$todo = count((array) ($d['todo'] ?? []));
$pct = (int) ($d['items'] ?? 0) > 0 ? (int) round((int) $d['done'] / (int) $d['items'] * 100) : 0;
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-cust.css')) ?>">
<?= sl_phoneTop('My profile', '', ['back' => sl_route('UserHome')]) ?>
<main class="mx-body">
<section class="b3-stu"><form data-sl-form data-sl-reload action="ajax/customers/customers_avatar_edit_ajax.php" method="post" enctype="multipart/form-data" id="pe-photo">
<input type="hidden" name="id" value="<?= $id ?>">
<span class="b3-av xxl pc-av"<?= !empty($d['avatar']) && strpos((string) $d['avatar'], 'blank') === false ? ' style="background-image:url(\'' . h($d['avatar']) . '\')"' : '' ?> id="pe-avatar-prev"><?= empty($d['avatar']) || strpos((string) $d['avatar'], 'blank') !== false ? h($ini) : '' ?></span>
<div class="b3-row2"><label class="b3-gh pc-btnrow" style="justify-content:center"><i class="ic ic-sm i-upload" aria-hidden="true"></i>Change photo<input type="file" name="avatar" accept="image/*" class="pc-vh" data-pe-avatar></label><button type="submit" class="b3-gh" id="pe-photo-save" disabled><i class="ic ic-sm i-check" aria-hidden="true"></i>Save photo</button></div>
<small>JPEG, PNG or GIF · a square photo works best</small>
</form></section>

<div class="mx-sec"><h2>Name and phone</h2></div>
<section class="mx-card mx-form">
<label class="mx-f"><small>Full name</small><div class="mx-in b3-rof"><span><?= h($name) ?></span><i class="ic ic-sm i-lock" aria-hidden="true"></i></div><small class="mx-fh">Must match your ID for customs · change it below</small></label>
<div class="mx-f"><small>Phone (WhatsApp)</small><div class="mx-in b3-rof"><span class="mono"><?= $phone !== '' ? h($phone) : 'No number on file' ?></span><span class="b3-st <?= $ps === 'verified' ? 'ok' : 'am' ?>"><?= $phone === '' ? 'Missing' : ($ps === 'verified' ? 'Confirmed' : 'Not confirmed') ?></span></div><?php if ($own): ?><button type="button" class="b3-lk pc-linkbtn" data-sl-open="pe-phone"><?= $phone === '' ? 'Add number' : ($ps === 'verified' ? 'Change number' : 'Confirm number') ?><i class="ic ic-sm i-right" aria-hidden="true"></i></button><?php endif; ?></div>
</section>

<div class="mx-sec"><h2>Your details</h2></div>
<form id="pe-form" data-sl-form data-sl-reload action="ajax/customers/customers_profile_edit_ajax.php" method="post" autocomplete="off">
<input type="hidden" name="id" value="<?= $id ?>">
<section class="mx-card mx-form">
<div class="mx-two"><label class="mx-f"><small>First name *</small><input class="mx-in mx-inp" name="fname" value="<?= h($r->fname) ?>" autocomplete="given-name"></label><label class="mx-f"><small>Last name *</small><input class="mx-in mx-inp" name="lname" value="<?= h($r->lname) ?>" autocomplete="family-name"></label></div>
<label class="mx-f"><small>Username</small><div class="mx-in b3-rof"><span><?= h($r->username ?? '') ?></span><i class="ic ic-sm i-lock" aria-hidden="true"></i></div></label>
<label class="mx-f"><small>Email *</small><input class="mx-in mx-inp" name="email" type="email" inputmode="email" autocapitalize="none" spellcheck="false" autocomplete="email" value="<?= h($r->email) ?>"></label>
<label class="mx-f"><small>Gender</small><select class="mx-in mx-inp" name="gender"><option value="">—</option><?php foreach (['Male', 'Female', 'Other'] as $g): ?><option value="<?= h($g) ?>"<?= (string) ($r->gender ?? '') === $g ? ' selected' : '' ?>><?= h($g) ?></option><?php endforeach; ?></select></label>
<label class="mx-f"><small>Notes</small><textarea class="mx-in mx-inp pc-ta" name="notes" rows="2" placeholder="Delivery instructions or anything we should know"><?= h(trim((string) ($r->notes ?? ''))) ?></textarea><small class="mx-fh">A blank field keeps what is stored</small></label>
</section>
<div class="mx-sec"><h2>Address</h2></div>
<section class="mx-card mx-form" data-addr-list>
<input type="hidden" name="total_address" value="<?= count($addrs) ?>">
<?php foreach ($addrs as $a) echo $rowHtml($a); ?>
<p class="b3-cap" data-addr-empty<?= $addrs ? ' hidden' : '' ?>>No address saved yet.</p>
<template data-addr-tpl><?= $rowHtml($blank) ?></template>
<button type="button" class="b2-add" data-addr-add><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add address</button>
<label class="mx-f"><small>Locker ID</small><div class="mx-in ph"><span><?= h(($r->locker ?? '') !== '' ? $r->locker . ' · cannot be changed' : '—') ?></span></div></label>
</section>
</form>

<div class="mx-sec"><h2>Identity check (KYC)</h2></div>
<section class="mx-card pc-pad14"><div class="pc-ck-row"><span class="mx-li"><i class="ic i-shield" aria-hidden="true"></i></span><span class="mx-rm"><b><?= $todo ? h($todo . ($todo === 1 ? ' thing needs' : ' things need') . ' attention') : 'Everything is in order' ?></b><small><?= (int) ($d['done'] ?? 0) ?> of <?= (int) ($d['items'] ?? 0) ?> done · needed for customs</small></span><span class="sl-pill <?= !empty($doc['complete']) ? 'delivered' : 'pending' ?>"><?= !empty($doc['complete']) ? 'ID complete' : count((array) ($doc['missing'] ?? [])) . ' missing' ?></span></div><div class="mx-trk"><i class="<?= $todo ? '' : 'ok' ?>" style="width:<?= $pct ?>%"></i></div><?php if (!empty($doc['missing'])): ?><small class="mx-note">Missing: <?= h(implode(', ', (array) $doc['missing'])) ?>.</small><?php endif; ?></section>

<div class="mx-sec"><h2>ID document</h2></div>
<form data-sl-form data-sl-reload action="ajax/save_profile_document_ajax.php" method="post">
<input type="hidden" name="id" value="<?= $id ?>">
<section class="mx-card mx-form">
<label class="mx-f"><small>ID type</small><select class="mx-in mx-inp" name="document_type" id="pe-doc-type"><option value="">Choose</option><?php foreach ($types as $k => $l): ?><option value="<?= h($k) ?>"<?= (string) $k === (string) ($doc['type'] ?? '') ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></label>
<label class="mx-f"><small>ID number</small><input class="mx-in mx-inp mono" name="document_number" value="<?= h($doc['number'] ?? '') ?>" autocomplete="off" placeholder="As printed on the card"></label>
<button type="submit" class="b3-gh"><i class="ic ic-sm i-check" aria-hidden="true"></i>Save type and number</button>
</section>
</form>
<section class="mx-ph">
<?php foreach (['front' => 'Front of ID', 'back' => 'Back of ID'] as $side => $label): if ($side === 'back' && empty($doc['hasBackCol'])) continue; $url = (string) ($doc[$side] ?? ''); ?>
<button type="button" class="pc-phbtn" data-<?= $url !== '' ? 'idv' : 'idu' ?>-open="<?= $side ?>" aria-label="<?= h(($url !== '' ? 'View ' : 'Upload ') . strtolower($label)) ?>"><span<?= $url !== '' ? ' style="background-image:url(\'' . h($url) . '\');background-size:cover;background-position:center"' : '' ?>><?php if ($url === ''): ?><i class="ic i-image" aria-hidden="true"></i><?php endif; ?></span><small><?= h($label) ?> · <?= $url !== '' ? 'on file' : 'not uploaded' ?></small></button>
<?php endforeach; ?>
</section>
<div class="b3-row2"><button type="button" class="b3-gh" data-idu-open="front"><i class="ic ic-sm i-upload" aria-hidden="true"></i>Upload front</button><?php if (!empty($doc['hasBackCol'])): ?><button type="button" class="b3-gh" data-idu-open="back"><i class="ic ic-sm i-upload" aria-hidden="true"></i>Upload back</button><?php endif; ?></div>

<?php if (!empty($pw['inline'])): ?>
<div class="mx-sec"><h2>Password</h2></div>
<form data-sl-form data-sl-reload action="ajax/customers/customers_profile_edit_ajax.php" method="post" autocomplete="off">
<input type="hidden" name="id" value="<?= $id ?>">
<section class="mx-card mx-form">
<label class="mx-f"><small>New password</small><input class="mx-in mx-inp" name="password" type="password" autocomplete="new-password" minlength="6" required><small class="mx-fh"><?= ($pw['changed'] ?? '') !== '' ? 'Last changed ' . h($pw['changed']) . ' · ' : '' ?>At least 6 characters. You stay signed in on this device.</small></label>
<button type="submit" class="b3-gh"><i class="ic ic-sm i-key" aria-hidden="true"></i>Save password</button>
</section>
</form>
<?php endif; ?>
<a class="b3-lk" href="<?= h(sl_route('AccountSecurity')) ?>">Devices and sign-ins<i class="ic ic-sm i-right" aria-hidden="true"></i></a>
</main>
<div class="mx-foot"><div class="b3-ft"><button class="mx-btn" type="submit" form="pe-form">Save changes</button></div></div>
<?= function_exists('sl_idDialogsHtml') ? sl_idDialogsHtml((array) $d['doc'], ['canUpload' => true, 'endpoint' => 'ajax/save_profile_document_ajax.php', 'hidden' => ['id' => $id], 'fields' => ['front' => 'document_photo', 'back' => 'document_photo_back']]) : '' ?>
<?php if ($own): ?>
<div class="sl-dialog" id="pe-phone" hidden><div class="sl-modal col g12" role="dialog" aria-label="WhatsApp number">
  <div class="row between"><b class="fw5">Your WhatsApp number</b><button type="button" class="mx-ib" data-sl-close aria-label="Close"><i class="ic i-x" aria-hidden="true"></i></button></div>
  <span class="mx-fh">Is this the number you use on WhatsApp? Change it if not, then we send a code.</span>
  <div class="col g12" id="pe-step1">
    <label class="mx-f"><small>Phone with country code</small><input class="mx-in mx-inp mono" id="pe-phone-in" type="tel" inputmode="tel" autocomplete="tel" value="<?= h($r->phone) ?>" placeholder="+233 20 000 0000"></label>
    <button class="b3-cta" type="button" id="pe-send"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send code</button>
  </div>
  <div class="col g12" id="pe-step2" hidden>
    <span class="mx-fh">Enter the 6-digit code sent to <b id="pe-masked"></b>.</span>
    <input class="mx-in mx-inp mono" id="pe-code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="6 digits" style="letter-spacing:.3em;text-align:center">
    <button class="b3-cta" type="button" id="pe-verify"><i class="ic ic-sm i-check" aria-hidden="true"></i>Confirm</button>
    <div class="b3-row2"><button class="b3-gh" type="button" id="pe-resend">Resend code</button><button class="b3-gh" type="button" id="pe-back">Another number</button></div>
  </div>
  <div class="mx-ban info" id="pe-msg" hidden></div>
</div></div>
<?php endif; ?>
<script>
(function () {
  var av = document.querySelector('[data-pe-avatar]'), prev = document.getElementById('pe-avatar-prev'), save = document.getElementById('pe-photo-save');
  if (av) av.addEventListener('change', function () { var f = av.files[0]; if (!f) return; prev.textContent = ''; prev.style.backgroundImage = 'url(' + URL.createObjectURL(f) + ')'; save.disabled = false; });
  var msg = document.getElementById('pe-msg'), phone = document.getElementById('pe-phone-in');
  if (!phone) return;
  function note(t, bad) { msg.hidden = !t; msg.textContent = t || ''; msg.className = 'mx-ban ' + (bad ? 'bad' : 'info'); }
  function step(n) { document.getElementById('pe-step1').hidden = n !== 1; document.getElementById('pe-step2').hidden = n !== 2; }
  function done() { SL.toast('WhatsApp number saved'); setTimeout(function () { location.reload(); }, 600); }
  function send(resend) {
    var v = phone.value.trim(); if (!v) { note('Enter your WhatsApp number', true); return; }
    var data = { phone: v }; if (resend) data.resend = 1;
    note('');
    SL.post('ajax/send_profile_phone_otp_ajax.php', data).then(function (j) {
      if (j.status !== 'success') { note(j.message || 'That number could not receive a WhatsApp message. Please use another number.', true); step(1); return; }
      if (j.otp_required === false) { done(); return; }
      document.getElementById('pe-masked').textContent = j.masked || v; step(2); note(resend ? 'A new code has been sent.' : '');
      document.getElementById('pe-code').focus();
    }).catch(function () { note('Network error. Please try again.', true); });
  }
  document.getElementById('pe-send').addEventListener('click', function () { send(false); });
  document.getElementById('pe-resend').addEventListener('click', function () { send(true); });
  document.getElementById('pe-back').addEventListener('click', function () { step(1); note(''); phone.focus(); });
  document.getElementById('pe-verify').addEventListener('click', function () {
    var c = document.getElementById('pe-code').value.replace(/\D/g, ''); if (c.length < 4) { note('Enter the code from WhatsApp', true); return; }
    SL.post('ajax/verify_profile_phone_otp_ajax.php', { otp_code: c }).then(function (j) { if (j.status === 'success') done(); else note(j.message || 'Verification failed.', true); }).catch(function () { note('Network error. Please try again.', true); });
  });
})();
</script>
