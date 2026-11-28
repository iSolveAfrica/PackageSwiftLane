<?php
/**
 * M-CRecipientNew: add (recipients_add.php) or edit (recipients_edit.php?recipient=) a recipient. Same fields and the
 * same two steps as views/sl/pages/RecipientForm.php: e-mail check (recipients_check_email[_exist]_ajax.php), then
 * recipients_add_ajax.php / recipients_edit_ajax.php. Address rows use the hooks of dataJs/sl/address_rows.js
 * (legacy names address[i], country[i], state[i], city[i], postal[i], address_id[i], total_address).
 */
global $user;
$slPhone['nav'] = 'none';
$slPhone['scripts'][] = 'dataJs/sl/address_rows.js';
$r = $d['row'] ?? null;
$edit = $r !== null;
$list = sl_route('RecipientsMine');
$countries = (array) ($d['countries'] ?? []);
$addrDelete = 'ajax/recipients/recipients_delete_address_ajax.php';
$parcels = $edit ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_add_order WHERE receiver_id = :r AND is_deleted = 0', [':r' => (int) $r->id]) : 0;
$canDelete = $edit && isset($user) && $user->cdp_hasPermission(['delete_recipient', 'view_recipients']);
$sel = function (string $name, array $opts, int $cur, string $ph, string $kind): string {
    $o = '<option value="">' . h($ph) . '</option>';
    foreach ($opts as $x) {
        $o .= '<option value="' . (int) $x->id . '"' . ((int) $x->id === $cur ? ' selected' : '') . '>' . h($x->name) . '</option>';
    }
    return '<select class="mx-in mx-inp" name="' . h($name) . '[]" data-addr-' . $kind . '>' . $o . '</select>';
};
$rowHtml = function (array $a, int $i) use ($sel, $countries, $addrDelete): string {
    ob_start(); ?>
<section class="b3-cd b3-adr" data-addr-row><input type="hidden" name="address_id[]" value="<?= (int) $a['id'] ?>">
<div class="b3-ch"><span class="mx-rm"><b><?= $a['id'] ? 'Saved address' : 'New address' ?></b><small><?= $a['id'] ? 'On file' : 'Not saved yet' ?></small></span><button type="button" class="b3-x bad" data-addr-remove<?= $a['id'] ? ' data-addr-delete="' . h($addrDelete) . '" data-id="' . (int) $a['id'] . '"' : '' ?>><i class="ic ic-sm i-x" aria-hidden="true"></i>Remove address</button></div>
<label class="mx-f"><small>Country</small><?= $sel('country', $countries, (int) $a['country'], 'Choose a country', 'country') ?></label>
<div class="mx-two"><label class="mx-f"><small>Region / state</small><?= $sel('state', (array) $a['states'], (int) $a['state'], 'Choose a region', 'state') ?></label><label class="mx-f"><small>City</small><?= $sel('city', (array) $a['cities'], (int) $a['city'], 'Choose a city', 'city') ?></label></div>
<label class="mx-f"><small>Street</small><input class="mx-in mx-inp" name="address[]" value="<?= h($a['address']) ?>" placeholder="Street, house number, landmark" autocomplete="street-address"></label>
<label class="mx-f"><small>Postal / GPS</small><input class="mx-in mx-inp mono" name="postal[]" value="<?= h($a['postal']) ?>" autocomplete="postal-code"></label>
</section>
<?php return ob_get_clean();
};
$blank = ['id' => 0, 'country' => 0, 'state' => 0, 'city' => 0, 'postal' => '', 'address' => '', 'states' => [], 'cities' => []];
$rows = (array) ($d['addresses'] ?? []);
if (!$rows) $rows = [$blank];
$name = $edit ? trim($r->fname . ' ' . $r->lname) : '';
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-cust.css')) ?>">
<?= sl_phoneTop($edit ? 'Edit recipient' : 'New recipient', $edit ? $name : '', ['back' => $list]) ?>
<form id="rf-form" autocomplete="off" data-edit="<?= $edit ? 1 : 0 ?>" data-original="<?= $edit ? h($r->email) : '' ?>" data-list="<?= h($list) ?>">
<?php if ($edit): ?><input type="hidden" name="recipient_id" value="<?= (int) $r->id ?>"><input type="hidden" name="original_email" value="<?= h($r->email) ?>"><?php endif; ?>
<input type="hidden" name="phone" value="<?= $edit ? h($r->phone) : '' ?>">
<main class="mx-body">
<div class="mx-sec"><h2>Contact</h2></div>
<section class="mx-card mx-form">
<div class="mx-two"><label class="mx-f"><small>First name</small><input class="mx-in mx-inp" name="fname" required autocomplete="given-name" value="<?= $edit ? h($r->fname) : '' ?>"></label><label class="mx-f"><small>Last name</small><input class="mx-in mx-inp" name="lname" required autocomplete="family-name" value="<?= $edit ? h($r->lname) : '' ?>"></label></div>
<label class="mx-f"><small>E-mail</small><input class="mx-in mx-inp" name="email" type="email" inputmode="email" autocapitalize="none" spellcheck="false" required autocomplete="email" value="<?= $edit ? h($r->email) : '' ?>"><small class="mx-fh">Must not belong to another recipient</small></label>
<label class="mx-f"><small>Phone</small><input class="mx-in mx-inp mono" name="phone_custom" type="tel" inputmode="tel" required autocomplete="tel" placeholder="+233 20 000 0000" value="<?= $edit ? h($r->phone) : '' ?>"><small class="mx-fh">The rider calls this number</small></label>
</section>
<div class="pc-list" data-addr-list>
<input type="hidden" name="total_address" value="<?= count($rows) ?>">
<?php foreach ($rows as $i => $a) echo $rowHtml($a, $i); ?>
<p class="b3-cap" data-addr-empty hidden>No address saved yet.</p>
<template data-addr-tpl><?= $rowHtml($blank, 0) ?></template>
<button type="button" class="b3-add pc-btnrow" data-addr-add style="justify-content:center;text-align:center;background:transparent"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add address</button>
</div>
<?php if ($canDelete): ?>
<?php if ($parcels === 0): ?><button type="button" class="b3-gh bad" data-sl-action="ajax/sl/recipient_ajax.php" data-p-op="delete" data-p-id="<?= (int) $r->id ?>" data-sl-confirm="Delete <?= h($name) ?>?" data-rf-deleted><i class="ic ic-sm i-trash" aria-hidden="true"></i>Delete recipient</button><?php endif; ?>
<p class="b3-cap">You can delete a recipient only when they have no parcels. <?= h($r->fname) ?> has <?= $parcels ?>.</p>
<?php endif; ?>
</main>
<div class="mx-foot"><div class="b3-ft"><a class="b3-gh" href="<?= h($list) ?>">Cancel</a><button class="mx-btn" type="submit"><?= $edit ? 'Save recipient' : 'Add recipient' ?></button></div></div>
</form>
<script>
(function () {
  var f = document.getElementById('rf-form'), edit = f.getAttribute('data-edit') === '1';
  f.addEventListener('submit', function (e) {
    e.preventDefault();
    f.querySelector('[name="phone"]').value = f.querySelector('[name="phone_custom"]').value.trim();
    var email = f.querySelector('[name="email"]').value.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { SL.toast('Enter a valid e-mail address', true); return; }
    var btns = f.querySelectorAll('[type="submit"]'); btns.forEach(function (b) { b.disabled = true; });
    var check = (edit && email === f.getAttribute('data-original')) ? Promise.resolve({ status: 'success' })
      : SL.post(edit ? 'ajax/recipients/recipients_check_email_exist_ajax.php' : 'ajax/recipients/recipients_check_email_ajax.php', { email: email });
    check.then(function (j) {
      if (j.status === 'error') { SL.toast(j.message || 'That e-mail is already used', true); f.querySelector('[name="email"]').classList.add('bad'); return; }
      return SL.post(edit ? 'ajax/recipients/recipients_edit_ajax.php' : 'ajax/recipients/recipients_add_ajax.php', new FormData(f)).then(function (r) {
        if (r.status === 'success' || r.success === true) { SL.toast(r.message || 'Saved'); setTimeout(function () { location.href = f.getAttribute('data-list'); }, 500); }
        else SL.toast(r.message || 'Please check the form', true);
      });
    }).catch(function () { SL.toast('Network error', true); }).finally(function () { btns.forEach(function (b) { b.disabled = false; }); });
  });
  var del = f.querySelector('[data-rf-deleted]');
  if (del) del.addEventListener('sl:done', function (e) { var j = e.detail || {}; if (j.success === true || j.status === 'success') setTimeout(function () { location.href = f.getAttribute('data-list'); }, 400); });
})();
</script>
