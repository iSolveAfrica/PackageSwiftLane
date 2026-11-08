<?php
/**
 * ProfileEdit - "My account" edit page (customers_profile_edit.php?edit=1), replacing the old Bootstrap form.
 * Writes (unchanged legacy endpoints and field names):
 *   ajax/customers/customers_profile_edit_ajax.php   name, e-mail, gender, password, notes, addresses (partial saves)
 *   ajax/customers/customers_avatar_edit_ajax.php    profile photo
 *   ajax/save_profile_document_ajax.php              ID type, number and photo (optional)
 *   ajax/send_profile_phone_otp_ajax.php / verify_profile_phone_otp_ajax.php   WhatsApp number with a one-time code
 *   ajax/customers/customers_delete_address_ajax.php delete a saved address
 */
$r = $d['row'];
$id = (int) $r->id;
$slA = ['rows' => $d['addresses'], 'countries' => $d['countries'], 'delete' => 'ajax/customers/customers_delete_address_ajax.php'];
$ps = $d['phoneStatus'];
?>
<div class="row g8 sm mut"><i class="ic ic-sm i-user" aria-hidden="true"></i><a href="<?= h(sl_route('UserHome')) ?>">My account</a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)">Edit details</span></div>
<div class="sl-head">
  <div><h1 class="sl-title"><?= $d['isOwn'] ? 'My details' : 'Edit ' . h($d['name']) ?></h1><p class="sl-lede">Change only what you need: a blank field keeps what is stored</p></div>
  <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= h(sl_route('UserHome')) ?>">Back</a><button class="sl-btn" type="submit" form="pe-form"><i class="ic ic-sm i-check" aria-hidden="true"></i>Save details</button></div>
</div>
<div class="split">
  <div class="a col g16">
    <form id="pe-form" class="col g16" data-sl-form data-sl-reload action="ajax/customers/customers_profile_edit_ajax.php" method="post" autocomplete="off">
      <input type="hidden" name="id" value="<?= $id ?>">
      <section class="sl-card col g16">
        <div class="sl-ch" style="margin:0"><h2 class="sl-ct">Personal details</h2></div>
        <div class="grid cols2" style="gap:12px">
          <?= sl_fInput('First name', 'fname', $r->fname) ?>
          <?= sl_fInput('Last name', 'lname', $r->lname) ?>
          <?= sl_fInput('E-mail', 'email', $r->email, 'email') ?>
          <?= sl_fSelect('Gender', 'gender', ['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'], $r->gender, '—') ?>
        </div>
      </section>
      <section class="sl-card col g12" id="account-security" style="scroll-margin-top:24px">
        <div class="sl-ch" style="margin:0"><div><h2 class="sl-ct">Account security</h2><span class="mut sm">Choose a new password when you need to update your sign-in.</span></div><i class="ic i-lock" aria-hidden="true"></i></div>
        <?= sl_fInput('New password', 'password', '', 'password', 'autocomplete="new-password"', 'Leave blank to keep your password') ?>
      </section>
      <section class="sl-card col g16">
        <div class="sl-ch" style="margin:0"><div><h2 class="sl-ct">Addresses <span class="mut sm">(optional)</span></h2><div class="mut sm">A new address needs all of its fields; leave a new block empty to skip it</div></div></div>
        <?php include __DIR__ . '/partials/address_rows.php'; ?>
      </section>
      <section class="sl-card col g12">
        <div class="sl-ch" style="margin:0"><h2 class="sl-ct">Notes</h2></div>
        <textarea class="sl-in" name="notes" rows="3" placeholder="Delivery instructions or anything we should know"><?= h(trim((string) $r->notes)) ?></textarea>
      </section>
      <div class="row g8"><button class="sl-btn" type="submit"><i class="ic ic-sm i-check" aria-hidden="true"></i>Save details</button></div>
    </form>
  </div>
  <aside class="b col g16">
    <section class="sl-card col g12">
      <div class="sl-ch" style="margin:0"><h2 class="sl-ct">WhatsApp number</h2><?= $ps === 'verified' ? sl_pill('Confirmed', 'delivered') : ($ps === 'pending' ? sl_pill('Not confirmed', 'pending') : '') ?></div>
      <div class="row g8"><i class="ic ic-sm i-phone" aria-hidden="true"></i><span class="mono"><?= $r->phone !== '' ? h($r->phone) : '<span class="mut">Not set</span>' ?></span></div>
      <?php if ($d['isOwn']): ?>
      <span class="mut sm">We send a code on WhatsApp to confirm the number you use.</span>
      <div><button class="sl-btn ghost sm" type="button" data-sl-open="pe-phone"><i class="ic ic-sm i-message" aria-hidden="true"></i><?= $r->phone !== '' ? 'Confirm or change number' : 'Add WhatsApp number' ?></button></div>
      <?php endif; ?>
    </section>
    <?= sl_fPhotoForm('Profile photo', 'ajax/customers/customers_avatar_edit_ajax.php', 'avatar', $d['avatar'], ['id' => $id]) ?>
    <form class="sl-card col g12" data-sl-form data-sl-reload action="ajax/save_profile_document_ajax.php" method="post" enctype="multipart/form-data">
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="sl-ch" style="margin:0"><h2 class="sl-ct">ID document <span class="mut sm">(optional)</span></h2></div>
      <div class="row g12"><img id="pe-doc" src="<?= h($d['document']) ?>" alt="ID document" style="width:96px;height:64px;object-fit:cover;border-radius:8px;border:1px solid var(--line);background:var(--field)"><span class="mut sm grow">Add a type and number, a photo, or both.</span></div>
      <?= sl_fSelect('ID type', 'document_type', $d['docTypes'], $r->document_type, 'Choose') ?>
      <?= sl_fInput('ID number', 'document_number', $r->document_number) ?>
      <label class="sl-field"><span class="sl-lab">Photo of the ID</span><input class="sl-in" type="file" name="document_photo" accept="image/*" onchange="var f=this.files[0];if(f)document.getElementById('pe-doc').src=URL.createObjectURL(f)"></label>
      <div class="row g8"><button type="submit" class="sl-btn sm"><i class="ic ic-sm i-check" aria-hidden="true"></i>Save document</button><?php if ($d['hasDoc']): ?><a class="sl-btn ghost sm" href="<?= h($d['document']) ?>" target="_blank" rel="noopener"><i class="ic ic-sm i-eye" aria-hidden="true"></i>View</a><?php endif; ?></div>
    </form>
  </aside>
</div>
<?php if ($d['isOwn']): ?>
<div class="sl-dialog" id="pe-phone" hidden><div class="sl-modal col g12" role="dialog" aria-label="WhatsApp number">
  <div class="row g10"><span class="sl-tile"><i class="ic i-message" aria-hidden="true"></i></span><div class="col grow"><b class="fw5">Your WhatsApp number</b><span class="mut sm">Is this the number you use on WhatsApp? Change it if not, then we send a code.</span></div><button type="button" class="sl-ib" data-sl-close aria-label="Close"><i class="ic i-x" aria-hidden="true"></i></button></div>
  <div class="col g12" id="pe-step1">
    <label class="sl-field"><span class="sl-lab">Phone with country code</span><input class="sl-in mono" id="pe-phone-in" type="tel" value="<?= h($r->phone) ?>" placeholder="+233 20 000 0000"></label>
    <div class="row g8"><button class="sl-btn" type="button" id="pe-send"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send code</button></div>
  </div>
  <div class="col g12" id="pe-step2" hidden>
    <span class="sm">Enter the 6-digit code sent to <b id="pe-masked"></b>.</span>
    <input class="sl-in mono" id="pe-code" inputmode="numeric" maxlength="6" placeholder="6 digits" style="font-size:22px;letter-spacing:.3em;text-align:center">
    <div class="row g8 wrap"><button class="sl-btn" type="button" id="pe-verify"><i class="ic ic-sm i-check" aria-hidden="true"></i>Confirm</button><button class="sl-btn ghost" type="button" id="pe-resend">Resend code</button><button class="sl-btn ghost" type="button" id="pe-back">Use another number</button></div>
  </div>
  <div class="sl-banner" id="pe-msg" hidden></div>
</div></div>
<script>
(function () {
  var msg = document.getElementById('pe-msg'), phone = document.getElementById('pe-phone-in');
  function note(t, bad) { msg.hidden = !t; msg.textContent = t || ''; msg.className = 'sl-banner ' + (bad ? 'bad' : 'info'); }
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
    SL.post('ajax/verify_profile_phone_otp_ajax.php', { otp_code: c }).then(function (j) {
      if (j.status === 'success') done(); else note(j.message || 'Verification failed.', true);
    }).catch(function () { note('Network error. Please try again.', true); });
  });
  document.getElementById('pe-code').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); document.getElementById('pe-verify').click(); } });
})();
</script>
<?php endif; ?>
