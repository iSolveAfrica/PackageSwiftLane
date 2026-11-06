<?php
/**
 * RecipientForm - add (recipients_add.php) or edit (recipients_edit.php?recipient=) a recipient in the
 * address book. Same two steps as before: the e-mail is checked first
 * (ajax/recipients/recipients_check_email_ajax.php / recipients_check_email_exist_ajax.php), then the form
 * posts the legacy fields to ajax/recipients/recipients_add_ajax.php or recipients_edit_ajax.php.
 */
$r = $d['row'];
$edit = $r !== null;
$slA = ['rows' => $d['addresses'], 'countries' => $d['countries'], 'delete' => 'ajax/recipients/recipients_delete_address_ajax.php', 'min' => 1];
$list = sl_route('RecipientsMine');
?>
<div class="row g8 mut sm"><a href="<?= h($list) ?>">Recipients</a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)"><?= $edit ? h(trim($r->fname . ' ' . $r->lname)) : 'Add recipient' ?></span></div>
<div class="sl-head">
  <div><h1 class="sl-title"><?= $edit ? 'Edit recipient' : 'Add recipient' ?></h1><p class="sl-lede">A person or business you send parcels to. They appear when you create a shipment.</p></div>
  <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= h($list) ?>">Cancel</a><button class="sl-btn" type="submit" form="rf-form"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= $edit ? 'Save changes' : 'Add recipient' ?></button></div>
</div>
<form id="rf-form" autocomplete="off" data-edit="<?= $edit ? 1 : 0 ?>" data-original="<?= $edit ? h($r->email) : '' ?>" data-list="<?= h($list) ?>">
  <?php if ($edit): ?><input type="hidden" name="recipient_id" value="<?= (int) $r->id ?>"><input type="hidden" name="original_email" value="<?= h($r->email) ?>"><?php endif; ?>
  <input type="hidden" name="phone" value="<?= $edit ? h($r->phone) : '' ?>">
  <div class="split">
    <div class="a col g16">
      <section class="sl-card col g16">
        <div class="sl-ch" style="margin:0"><h2 class="sl-ct">Recipient</h2></div>
        <div class="grid cols2" style="gap:12px">
          <?= sl_fInput('First name', 'fname', $edit ? $r->fname : '', 'text', 'required') ?>
          <?= sl_fInput('Last name', 'lname', $edit ? $r->lname : '', 'text', 'required') ?>
          <?= sl_fInput('E-mail', 'email', $edit ? $r->email : '', 'email', 'required') ?>
          <?= sl_fInput('Phone', 'phone_custom', $edit ? $r->phone : '', 'tel', 'required placeholder="+233 20 000 0000"') ?>
        </div>
      </section>
      <section class="sl-card col g16">
        <div class="sl-ch" style="margin:0"><h2 class="sl-ct">Addresses</h2></div>
        <?php include __DIR__ . '/partials/address_rows.php'; ?>
      </section>
    </div>
    <aside class="b col g16"><section class="sl-card col g8"><h2 class="sl-ct">Where it shows</h2><span class="mut sm">The recipient appears in your address book and when a shipment is created for you. The e-mail must not belong to another recipient.</span></section></aside>
  </div>
</form>
<script>
(function () {
  var f = document.getElementById('rf-form'), edit = f.getAttribute('data-edit') === '1';
  f.addEventListener('submit', function (e) {
    e.preventDefault();
    f.querySelector('[name="phone"]').value = f.querySelector('[name="phone_custom"]').value.trim();
    var email = f.querySelector('[name="email"]').value.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { SL.toast('Enter a valid e-mail address', true); return; }
    var btns = document.querySelectorAll('[form="rf-form"]'); btns.forEach(function (b) { b.disabled = true; });
    var check = (edit && email === f.getAttribute('data-original')) ? Promise.resolve({ status: 'success' })
      : SL.post(edit ? 'ajax/recipients/recipients_check_email_exist_ajax.php' : 'ajax/recipients/recipients_check_email_ajax.php', { email: email });
    check.then(function (j) {
      if (j.status === 'error') { SL.toast(j.message || 'That e-mail is already used', true); f.querySelector('[name="email"]').classList.add('is-invalid'); return; }
      return SL.post(edit ? 'ajax/recipients/recipients_edit_ajax.php' : 'ajax/recipients/recipients_add_ajax.php', new FormData(f)).then(function (r) {
        if (r.status === 'success' || r.success === true) { SL.toast(r.message || 'Saved'); setTimeout(function () { location.href = f.getAttribute('data-list'); }, 500); }
        else SL.toast(r.message || 'Please check the form', true);
      });
    }).catch(function () { SL.toast('Network error', true); }).finally(function () { btns.forEach(function (b) { b.disabled = false; }); });
  });
})();
</script>
