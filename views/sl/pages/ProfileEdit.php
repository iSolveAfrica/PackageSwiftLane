<?php
/**
 * ProfileEdit - "My account" (customers_profile_edit.php?edit=1, nav "My Account Security" = #account-security).
 * Tab "Security and ID" (#account-security, the default): one checklist of password, WhatsApp number, ID type and
 * number, ID card front and back, fingerprint sign-in; the ID card with both sides; and, on your own account,
 * remembered devices and recent sign-ins. Tab "Details and addresses" (#details, customer accounts): the editor.
 * Writes (legacy endpoints and field names unchanged):
 *   ajax/customers/customers_profile_edit_ajax.php   name, e-mail, gender, password, notes, addresses (partial saves)
 *   ajax/customers/customers_avatar_edit_ajax.php    profile photo
 *   ajax/save_profile_document_ajax.php              ID type and number; photos document_photo (front) / document_photo_back
 *   ajax/send_profile_phone_otp_ajax.php / verify_profile_phone_otp_ajax.php   WhatsApp number with a one-time code
 *   ajax/sl/account_security_ajax.php                forget one remembered device / all of them (own account)
 *   ajax/customers/customers_delete_address_ajax.php delete a saved address
 */
$r = $d['row'];
$id = (int) $r->id;
$doc = $d['doc'];
$ps = $d['phoneStatus'];
$phone = trim((string) $r->phone);
$own = $d['isOwn'];
$tabs = $d['isClient'];
$todo = count($d['todo']);
$slA = ['rows' => $d['addresses'], 'countries' => $d['countries'], 'delete' => 'ajax/customers/customers_delete_address_ajax.php'];
$fp = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="M12 11v4.5a6.5 6.5 0 0 1-1.6 4.3"/><path d="M8.5 11.5a3.5 3.5 0 0 1 7 0V14a10 10 0 0 1-.8 4"/><path d="M5.5 15.5c.3-1.2.5-2.5.5-4a6 6 0 0 1 9.8-4.6"/><path d="M18 9.5c.3.6.5 1.3.5 2v2.5a13 13 0 0 1-.6 4"/><path d="M3.7 11a8.5 8.5 0 0 1 2-5.2M8 3.9A8.5 8.5 0 0 1 20.2 8"/></svg>';
// One checklist row: icon (or a thumbnail), title + pill, detail line, actions, optional extra block.
$item = function (string $key, string $icon, string $title, string $pill, string $detail, string $actions, string $extra = ''): string {
    return '<div class="sec-it" id="sec-' . h($key) . '">' . $icon
        . '<div class="sec-tx"><div class="row g8 wrap"><b class="fw5">' . h($title) . '</b>' . $pill . '</div><span class="mut sm">' . $detail . '</span></div>'
        . ($actions !== '' ? '<div class="sec-ac">' . $actions . '</div>' : '') . $extra . '</div>';
};
$tile = fn(string $ic) => '<span class="sl-tile"><i class="ic ' . $ic . '" aria-hidden="true"></i></span>';
// Tab URLs keep the page path: inside the installed app a <base> element would resolve a bare "#hash" to the app root.
$here = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '#') ?: 'customers_profile_edit.php?edit=1';
?>
<div class="row g8 sm mut wrap"><?php if ($own): ?><i class="ic ic-sm i-user" aria-hidden="true"></i><a href="<?= h(sl_route('UserHome')) ?>">My account</a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)">Security and details</span><?php else: ?><a href="<?= h(sl_route('Customers')) ?>">Customers</a><i class="ic ic-sm i-right" aria-hidden="true"></i><a href="<?= h(sl_route('CustomerProfile', ['id' => $id])) ?>"><?= h($d['name']) ?></a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)">Account</span><?php endif; ?></div>
<div class="sl-head">
  <div><h1 class="sl-title"><?= $own ? 'My account' : 'Account of ' . h($d['name']) ?></h1><p class="sl-lede"><?= $own ? 'Your sign-in, your ID card and your details in one place' : 'Sign-in, ID card and details of this customer' ?></p></div>
  <div class="row g8 wrap">
    <a class="sl-btn ghost" href="<?= h($own ? sl_route('UserHome') : sl_route('CustomerProfile', ['id' => $id])) ?>">Back</a>
    <?php if ($d['staffEdit']): ?><a class="sl-btn ghost" href="<?= h($d['staffEdit']) ?>"><i class="ic ic-sm i-edit" aria-hidden="true"></i>Edit my staff profile</a><?php endif; ?>
  </div>
</div>
<div class="col g16" data-sl-tabs>
<?php if ($tabs): ?>
  <div class="sl-seg boxed pe-tabs" role="tablist" aria-label="Account sections">
    <button type="button" role="tab" class="on" id="pe-tab-sec" data-sl-tab="account-security" data-sl-tab-url="<?= h($here) ?>#account-security" aria-selected="true" aria-controls="account-security"><i class="ic ic-sm i-shield" aria-hidden="true"></i>Security and ID<?php if ($todo): ?><span class="sl-badge warn"><?= $todo ?> to do</span><?php endif; ?></button>
    <button type="button" role="tab" id="pe-tab-det" data-sl-tab="details" data-sl-tab-url="<?= h($here) ?>#details" aria-selected="false" aria-controls="pe-details"><i class="ic ic-sm i-user" aria-hidden="true"></i>Details<span class="pe-tl-long">and addresses</span></button>
  </div>
<?php endif; ?>
  <div id="account-security" class="col g16" data-sl-tabpanel="account-security" role="tabpanel"<?= $tabs ? ' aria-labelledby="pe-tab-sec"' : ' aria-label="Account security"' ?> style="scroll-margin-top:24px">
    <section class="sl-card col g16">
      <div class="sl-ch" style="margin:0">
        <div><h2 class="sl-ct">Account security</h2><span class="mut sm"><?= $todo ? h($todo . ($todo === 1 ? ' thing needs' : ' things need') . ' attention') : 'Everything is in order' ?></span></div>
        <div class="sec-score" aria-label="<?= (int) $d['done'] ?> of <?= (int) $d['items'] ?> done"><b><?= (int) $d['done'] ?></b><span class="mut">of <?= (int) $d['items'] ?> done</span><span class="sl-steps" aria-hidden="true"><?php for ($i = 0; $i < $d['items']; $i++): ?><i class="<?= $i < $d['done'] ? 'on' : '' ?>"></i><?php endfor; ?></span></div>
      </div>
      <div class="sec-list">
<?php
// Password
$pw = $d['pass'];
$pwPill = $pw['expired'] ? sl_pill('Action needed', 'onway') : ($pw['changed'] !== '' ? sl_pill('Done', 'delivered') : sl_pill('Not recorded', 'plain'));
$pwDetail = $pw['changed'] !== '' ? 'Last changed ' . h($pw['changed']) . ($pw['next'] !== '' ? ' · ' . ($pw['expired'] ? 'a new one was due ' : 'next change due ') . h($pw['next']) : '') : 'When it was last changed is not recorded';
if ($pw['inline']) {
    $pwAct = '<button type="button" class="sl-btn ghost sm" data-pe-reveal="pe-pass" aria-expanded="false" aria-controls="pe-pass"><i class="ic ic-sm i-key" aria-hidden="true"></i>Change password</button>';
    $pwMore = '<form id="pe-pass" class="sec-more col g10" hidden data-sl-form data-sl-reload action="ajax/customers/customers_profile_edit_ajax.php" method="post" autocomplete="off">'
        . '<input type="hidden" name="id" value="' . $id . '">'
        . sl_fInput('New password', 'password', '', 'password', 'autocomplete="new-password" minlength="6" required', $own ? 'At least 6 characters. You stay signed in on this device.' : 'At least 6 characters. Tell the customer their new password.')
        . '<div class="row g8 wrap"><button type="submit" class="sl-btn sm"><i class="ic ic-sm i-check" aria-hidden="true"></i>Save password</button><button type="button" class="sl-btn ghost sm" data-pe-reveal="pe-pass">Cancel</button></div>'
        . ($own ? '<span class="mut sm">To also sign out every other device, use the <a href="' . h(sl_route('PasswordReset')) . '">password page</a>.</span>' : '')
        . '</form>';
} else {
    $pwAct = '<a class="sl-btn ghost sm" href="' . h(sl_route('PasswordReset')) . '"><i class="ic ic-sm i-key" aria-hidden="true"></i>Change password</a>';
    $pwMore = '';
}
echo $item('password', $tile('i-lock'), 'Password', $pwPill, $pwDetail, $pwAct, $pwMore);

// WhatsApp number
$waPill = $phone === '' ? sl_pill('Missing', 'pending') : ($ps === 'verified' ? sl_pill('Confirmed', 'delivered') : sl_pill('Not confirmed', 'onway'));
$waDetail = $phone === '' ? 'No number on file' : '<span class="mono">' . h($phone) . '</span>';
$waAct = $own ? '<button type="button" class="sl-btn ' . ($ps === 'verified' ? 'ghost ' : '') . 'sm" data-sl-open="pe-phone"><i class="ic ic-sm i-message" aria-hidden="true"></i>' . ($phone === '' ? 'Add number' : ($ps === 'verified' ? 'Change' : 'Confirm number')) . '</button>' : '';
echo $item('whatsapp', $tile('i-phone'), 'WhatsApp number', $waPill, $waDetail, $waAct);

// ID type and number
echo $item('idno', $tile('i-hash'), 'ID type and number', $doc['detailsSet'] ? sl_pill('Set', 'delivered') : sl_pill('Missing', 'pending'),
    $doc['detailsSet'] ? h($doc['typeLabel']) . ' · <span class="mono">' . h($doc['number']) . '</span>' : 'Add the type and the number printed on the card',
    '<button type="button" class="sl-btn ghost sm" data-pe-focus="pe-doc-type"><i class="ic ic-sm i-edit" aria-hidden="true"></i>' . ($doc['detailsSet'] ? 'Edit' : 'Add') . '</button>');

// ID card front and back
foreach (['front' => 'Front', 'back' => 'Back'] as $side => $label) {
    if ($side === 'back' && !$doc['hasBackCol']) continue;
    $url = $doc[$side];
    $icon = $url !== '' ? '<button type="button" class="sec-thumb" data-idv-open="' . $side . '" aria-label="View the ' . $side . ' of the ID card"><img src="' . h($url) . '" alt="" loading="lazy"></button>' : $tile('i-image');
    $acts = ($url !== '' ? '<button type="button" class="sl-btn ghost sm" data-idv-open="' . $side . '"><i class="ic ic-sm i-eye" aria-hidden="true"></i>View</button>' : '')
        . '<button type="button" class="sl-btn ' . ($url !== '' ? 'ghost ' : '') . 'sm" data-idu-open="' . $side . '"><i class="ic ic-sm i-upload" aria-hidden="true"></i>' . ($url !== '' ? 'Replace' : 'Upload') . '</button>';
    echo $item('id' . $side, $icon, 'ID card ' . strtolower($label), $url !== '' ? sl_pill('Uploaded', 'delivered') : sl_pill('Not uploaded', 'pending'),
        $url !== '' ? 'Photo of the ' . $side . ' on file' : $label . ' of ID not uploaded', $acts);
}

// Fingerprint / face sign-in (own account, when the feature is on)
if ($d['passkeys'] !== null) {
    $n = (int) $d['passkeys'];
    echo $item('passkeys', '<span class="sl-tile">' . $fp . '</span>', 'Fingerprint or face sign-in', $n ? sl_pill('On', 'delivered') : sl_pill('Off', 'plain'),
        $n ? 'Set up on ' . $n . ($n === 1 ? ' device' : ' devices') : 'Optional: sign in with your phone instead of typing your password',
        '<button type="button" class="sl-btn ghost sm" data-sl-open="pk-manage"><i class="ic ic-sm i-sliders" aria-hidden="true"></i>' . ($n ? 'Manage' : 'Set up') . '</button>');
}
?>
      </div>
    </section>

    <section class="sl-card col g16" id="pe-iddoc" style="scroll-margin-top:24px">
      <div class="sl-ch" style="margin:0">
        <div><h2 class="sl-ct">ID card</h2><span class="mut sm">A clear photo of each side of <?= $own ? 'your' : 'the' ?> ID, with its type and number</span></div>
        <?= $doc['complete'] ? sl_pill('Complete', 'delivered') : sl_pill(count($doc['missing']) . ' missing', count($doc['missing']) > 1 ? 'pending' : 'onway') ?>
      </div>
      <div class="idk-wrap">
        <?= sl_idSlotsHtml($doc, ['canUpload' => true]) ?>
        <form class="idk-form col g12" data-sl-form data-sl-reload action="ajax/save_profile_document_ajax.php" method="post">
          <input type="hidden" name="id" value="<?= $id ?>">
          <label class="sl-field"><span class="sl-lab">ID type</span><span class="sl-sel native"><select name="document_type" id="pe-doc-type"><option value="">Choose</option><?php foreach ($d['docTypes'] + ($doc['type'] !== '' && !isset($d['docTypes'][$doc['type']]) ? [$doc['type'] => $doc['type']] : []) as $k => $l): ?><option value="<?= h($k) ?>"<?= (string) $k === $doc['type'] ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
          <?= sl_fInput('ID number', 'document_number', $doc['number'], 'text', 'autocomplete="off" placeholder="As printed on the card"') ?>
          <div><button type="submit" class="sl-btn sm"><i class="ic ic-sm i-check" aria-hidden="true"></i>Save type and number</button></div>
        </form>
      </div>
    </section>

<?php if ($own): $devs = $d['devices']; $more = array_slice($devs, 5); ?>
    <div class="sec-pair">
      <section class="sl-card col g12" id="sec-devices">
        <div class="sl-ch" style="margin:0">
          <div><h2 class="sl-ct">Remembered devices</h2><span class="mut sm">Browsers that skip the sign-in code for 60 days</span></div>
          <div class="row g8"><?= $devs ? sl_pill(count($devs) . ' remembered', 'plain') : sl_pill('None', 'plain') ?><?php if ($devs): ?><button type="button" class="sl-btn ghost sm" data-sl-action="ajax/sl/account_security_ajax.php" data-p-op="revoke_all_devices" data-sl-confirm="Forget all <?= count($devs) ?> remembered devices? Each one, this browser included, asks for a sign-in code next time." data-sl-reload><i class="ic ic-sm i-logout" aria-hidden="true"></i>Forget all</button><?php endif; ?></div>
        </div>
        <?php if (!$devs): ?><div class="sec-empty"><i class="ic i-monitor" aria-hidden="true"></i><span>No remembered devices. When you choose “Remember this device” at sign-in, it shows here.</span></div><?php endif; ?>
        <div class="sec-rows">
        <?php foreach ($devs as $i => $dv): if ($i === 5): ?></div><div class="sec-rows" id="sec-dev-more" hidden><?php endif; ?>
          <div class="sec-row">
            <span class="sl-tile"><i class="ic <?= $dv['mobile'] ? 'i-phone' : 'i-monitor' ?>" aria-hidden="true"></i></span>
            <div class="col grow" style="min-width:0"><div class="row g8 wrap"><b class="fw5"><?= h($dv['label']) ?></b><?php if ($dv['current']): ?><span class="sl-badge ok">This browser</span><?php endif; ?></div><span class="mut sm"><?= $dv['ip'] !== '' ? 'IP <span class="mono">' . h($dv['ip']) . '</span> · ' : '' ?><span title="<?= h($dv['usedAt']) ?>"><?= h($dv['used']) ?></span> · added <?= h($dv['added']) ?></span></div>
            <button type="button" class="sl-btn ghost sm" data-sl-action="ajax/sl/account_security_ajax.php" data-p-op="revoke_device" data-p-id="<?= (int) $dv['id'] ?>" data-sl-confirm="Forget <?= h($dv['label']) ?>? It asks for a sign-in code next time." data-sl-reload aria-label="Forget <?= h($dv['label']) ?>">Forget</button>
          </div>
        <?php endforeach; ?>
        </div>
        <?php if ($more): ?><div><button type="button" class="sl-btn ghost sm" data-pe-reveal="sec-dev-more" aria-expanded="false" aria-controls="sec-dev-more" data-alt="Show fewer">Show all <?= count($devs) ?> devices</button></div><?php endif; ?>
      </section>
      <section class="sl-card col g12" id="sec-signins">
        <div class="sl-ch" style="margin:0">
          <div><h2 class="sl-ct">Recent sign-ins</h2><span class="mut sm">The last five attempts on this account</span></div>
          <?= !$d['signins'] ? sl_pill('None yet', 'plain') : ($d['failed'] ? sl_pill($d['failed'] . ' failed', 'onway') : sl_pill('All successful', 'delivered')) ?>
        </div>
        <?php if (!$d['signins']): ?><div class="sec-empty"><i class="ic i-clock" aria-hidden="true"></i><span>No sign-ins recorded yet.</span></div><?php endif; ?>
        <div class="sec-rows">
        <?php foreach ($d['signins'] as $s): ?>
          <div class="sec-row">
            <span class="sl-tile <?= $s['ok'] ? 'ok' : 'bad' ?>"><i class="ic <?= $s['ok'] ? 'i-check' : 'i-alert' ?>" aria-hidden="true"></i></span>
            <div class="col grow" style="min-width:0"><b class="fw5"><?= $s['ok'] ? 'Signed in' : 'Failed sign-in' ?></b><span class="mut sm"><?= h($s['device']) ?><?= $s['ip'] !== '' ? ' · IP <span class="mono">' . h($s['ip']) . '</span>' : '' ?></span></div>
            <span class="mut sm sec-when" title="<?= h($s['at']) ?>"><?= h($s['ago']) ?></span>
          </div>
        <?php endforeach; ?>
        </div>
        <?php if ($d['failed']): ?><span class="mut sm">Not you? <a href="<?= h(sl_route('PasswordReset')) ?>">Change your password</a> and forget the devices you do not know.</span><?php endif; ?>
      </section>
    </div>
<?php endif; ?>
  </div>

<?php if ($tabs): ?>
  <div id="pe-details" class="col g16" data-sl-tabpanel="details" role="tabpanel" aria-labelledby="pe-tab-det" hidden>
    <form class="sl-card pe-photo" data-sl-form data-sl-reload action="ajax/customers/customers_avatar_edit_ajax.php" method="post" enctype="multipart/form-data">
      <input type="hidden" name="id" value="<?= $id ?>">
      <img id="pe-avatar" src="<?= h($d['avatar']) ?>" alt="Profile photo">
      <div class="col grow" style="min-width:180px"><h2 class="sl-ct">Profile photo</h2><span class="mut sm">JPEG, PNG or GIF. A square photo works best.</span></div>
      <input class="sl-in pe-file" type="file" name="avatar" accept="image/*" aria-label="Choose a profile photo" onchange="var f=this.files[0];if(f){document.getElementById('pe-avatar').src=URL.createObjectURL(f);}">
      <div class="row g8"><button type="submit" class="sl-btn sm"><i class="ic ic-sm i-upload" aria-hidden="true"></i>Upload</button></div>
    </form>
    <form id="pe-form" class="col g16" data-sl-form data-sl-reload action="ajax/customers/customers_profile_edit_ajax.php" method="post" autocomplete="off">
      <input type="hidden" name="id" value="<?= $id ?>">
      <section class="sl-card col g16">
        <div class="sl-ch" style="margin:0"><h2 class="sl-ct">Personal details</h2><span class="mut sm">A blank field keeps what is stored</span></div>
        <div class="grid cols2" style="gap:12px">
          <?= sl_fInput('First name', 'fname', $r->fname) ?>
          <?= sl_fInput('Last name', 'lname', $r->lname) ?>
          <?= sl_fInput('E-mail', 'email', $r->email, 'email') ?>
          <?= sl_fSelect('Gender', 'gender', ['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'], $r->gender, '—') ?>
        </div>
        <label class="sl-field"><span class="sl-lab">Notes</span><textarea class="sl-in" name="notes" rows="3" placeholder="Delivery instructions or anything we should know"><?= h(trim((string) $r->notes)) ?></textarea></label>
      </section>
      <section class="sl-card col g16">
        <div class="sl-ch" style="margin:0"><div><h2 class="sl-ct">Addresses <span class="mut sm">(optional)</span></h2><div class="mut sm">A new address needs all of its fields; leave a new block empty to skip it</div></div></div>
        <?php include __DIR__ . '/partials/address_rows.php'; ?>
      </section>
      <div class="row g8"><button class="sl-btn" type="submit"><i class="ic ic-sm i-check" aria-hidden="true"></i>Save details</button></div>
    </form>
  </div>
<?php endif; ?>
</div>
<?= sl_idDialogsHtml($doc, ['canUpload' => true, 'endpoint' => 'ajax/save_profile_document_ajax.php', 'hidden' => ['id' => $id], 'fields' => ['front' => 'document_photo', 'back' => 'document_photo_back']]) ?>
<?php if ($own): ?>
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
