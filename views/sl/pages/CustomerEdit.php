<?php
/**
 * CustomerEdit - staff edit a client account (customers_edit.php?user=). Design-system layout after AddCustomer.
 * Writes (unchanged legacy endpoints and field names):
 *   ajax/customers/customers_edit_ajax.php      details, password, ID, status flags, addresses
 *   ajax/customers/customers_avatar_edit_ajax.php / customers_document_edit_ajax.php   photo and ID image
 *   ajax/customers/customers_status_ajax.php    approve / activate / deactivate (approve_client)
 *   ajax/customers/customers_delete_address_ajax.php   delete a saved address
 */
$r = $d['row'];
$id = (int) $r->id;
$slA = ['rows' => $d['addresses'], 'countries' => $d['countries'], 'delete' => 'ajax/customers/customers_delete_address_ajax.php'];
?>
<div class="row g8 mut sm"><a href="<?= sl_route('Customers') ?>">Customers</a><i class="ic ic-sm i-right" aria-hidden="true"></i><a href="<?= h(sl_route('CustomerProfile', ['id' => $id])) ?>"><?= h($d['name']) ?></a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)">Edit</span></div>
<div class="sl-head">
  <div><h1 class="sl-title">Edit <?= h($d['name']) ?></h1><p class="sl-lede">Locker <span class="mono"><?= h($r->locker) ?></span> · username <?= h($r->username) ?> · a blank field keeps what is stored</p></div>
  <div class="row g8 wrap">
    <?php if ($d['canApprove']): ?>
      <?php if (!(int) $r->approve): ?><button class="sl-btn ghost" type="button" data-sl-action="ajax/customers/customers_status_ajax.php" data-p-id="<?= $id ?>" data-p-approve="1" data-sl-confirm="Approve and activate this customer? They get the activation e-mail." data-sl-reload><i class="ic ic-sm i-check" aria-hidden="true"></i>Approve</button>
      <?php elseif ((int) $r->active): ?><button class="sl-btn ghost" type="button" data-sl-action="ajax/customers/customers_status_ajax.php" data-p-id="<?= $id ?>" data-p-stat="0" data-sl-confirm="Deactivate this account? The customer can no longer sign in." data-sl-reload style="color:var(--red-ink)"><i class="ic ic-sm i-ban" aria-hidden="true"></i>Deactivate</button>
      <?php else: ?><button class="sl-btn ghost" type="button" data-sl-action="ajax/customers/customers_status_ajax.php" data-p-id="<?= $id ?>" data-p-stat="1" data-sl-reload><i class="ic ic-sm i-check" aria-hidden="true"></i>Activate</button><?php endif; ?>
    <?php endif; ?>
    <a class="sl-btn ghost" href="<?= h(sl_route('CustomerProfile', ['id' => $id])) ?>">Cancel</a>
    <button class="sl-btn" type="submit" form="ce-form"><i class="ic ic-sm i-check" aria-hidden="true"></i>Save changes</button>
  </div>
</div>
<div class="split">
  <div class="a col g16">
    <form id="ce-form" class="col g16" data-sl-form data-sl-reload action="ajax/customers/customers_edit_ajax.php" method="post" autocomplete="off">
      <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="approve" value="<?= (int) $r->approve ?>"><input type="hidden" name="username" value="<?= h($r->username) ?>">
      <section class="sl-card col g16">
        <div class="sl-ch" style="margin:0"><h2 class="sl-ct">Customer</h2></div>
        <div class="grid cols2" style="gap:12px">
          <?= sl_fInput('First name', 'fname', $r->fname) ?>
          <?= sl_fInput('Last name', 'lname', $r->lname) ?>
          <?= sl_fInput('E-mail', 'email', $r->email, 'email') ?>
          <?= sl_fInput('WhatsApp phone', 'phone', $r->phone, 'tel', 'placeholder="+233 20 000 0000"') ?>
          <?= sl_fInput('Company (optional)', 'company', $r->company ?? '') ?>
          <?= sl_fSelect('Gender', 'gender', ['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'], $r->gender, '—') ?>
          <?= sl_fInput('New password', 'password', '', 'text', 'autocomplete="new-password" minlength="6"', 'Leave blank to keep the current password') ?>
        </div>
        <div class="grid cols2" style="gap:12px">
          <?= sl_fRadios('Account', 'active', ['1' => 'Active', '0' => 'Inactive'], (string) (int) $r->active) ?>
          <?= sl_fRadios('Newsletter', 'newsletter', ['1' => 'Yes', '0' => 'No'], (string) (int) $r->newsletter) ?>
        </div>
      </section>
      <section class="sl-card col g16">
        <div class="sl-ch" style="margin:0"><h2 class="sl-ct">ID document</h2></div>
        <div class="grid cols2" style="gap:12px">
          <?= sl_fSelect('ID type', 'document_type', $d['docTypes'] + (isset($d['docTypes'][$r->document_type]) || !$r->document_type ? [] : [$r->document_type => $r->document_type]), $r->document_type, '—') ?>
          <?= sl_fInput('ID number', 'document_number', $r->document_number) ?>
        </div>
      </section>
      <section class="sl-card col g16">
        <div class="sl-ch" style="margin:0"><div><h2 class="sl-ct">Addresses</h2><div class="mut sm">A new address is saved only when every field is filled in</div></div></div>
        <?php include __DIR__ . '/partials/address_rows.php'; ?>
      </section>
      <section class="sl-card col g12">
        <div class="sl-ch" style="margin:0"><h2 class="sl-ct">Notes</h2><span class="mut sm">Staff only</span></div>
        <textarea class="sl-in" name="notes" rows="4"><?= h(trim((string) $r->notes)) ?></textarea>
      </section>
      <div class="row g8"><button class="sl-btn" type="submit"><i class="ic ic-sm i-check" aria-hidden="true"></i>Save changes</button></div>
    </form>
  </div>
  <aside class="b col g16">
    <section class="sl-card col g12">
      <div class="row g12"><span class="sl-av lg dark" style="width:56px;height:56px;background-image:url(<?= h($d['avatar']) ?>);background-size:cover;color:transparent"><?= h(sl_initials($d['name'])) ?></span><div class="col"><b class="fw5"><?= h($d['name']) ?></b><span class="mut sm"><?= h($r->email) ?></span></div></div>
      <div class="sl-kv">
        <div><span>Status</span><span><?= !(int) $r->approve ? sl_pill('Waiting for approval', 'pending') : ((int) $r->active ? sl_pill('Active', 'delivered') : sl_pill('Inactive', 'plain')) ?></span></div>
        <div><span>Locker</span><span class="mono"><?= h($r->locker) ?></span></div>
        <div><span>Phone</span><span><?= h($r->phone) ?></span></div>
        <div><span>Member since</span><span><?= h(sl_date($r->created)) ?></span></div>
        <div><span>Last sign-in</span><span><?= h(sl_date($r->lastlogin, 'M j, Y H:i')) ?: '—' ?></span></div>
      </div>
    </section>
    <?= sl_fPhotoForm('Profile photo', 'ajax/customers/customers_avatar_edit_ajax.php', 'avatar', $d['avatar'], ['id' => $id, 'approve' => (int) $r->approve, 'current_avatar' => $r->avatar ?: '/uploads/blankID.jpg'], $d['canAvatar']) ?>
    <?= sl_fPhotoForm('ID photo', 'ajax/customers/customers_document_edit_ajax.php', 'document', $d['document'], ['id' => $id, 'current_document' => $r->document_photo ?: '/uploads/blankID.jpg'], $d['canDocument'], 'JPEG, PNG or GIF. The previous image is kept in the history below.', false) ?>
    <section class="sl-card col g12">
      <div class="sl-ch" style="margin:0"><h2 class="sl-ct">ID update history</h2></div>
      <?php if (!$d['history']): ?><div class="sl-empty mut sm">No earlier ID images.</div><?php else: ?>
      <div class="sl-tw"><table class="sl-table"><thead><tr><th>Previous</th><th>Date</th><th>Remarks</th></tr></thead><tbody>
      <?php foreach ($d['history'] as $hst): $img = 'assets/' . ltrim($hst->prev_document ?: 'uploads/blank.png', '/'); ?>
        <tr><td><a href="<?= h($img) ?>" target="_blank" rel="noopener"><img src="<?= h($img) ?>" alt="Previous ID" style="height:40px;border-radius:6px"></a></td><td class="mono sm"><?= h(sl_date($hst->datetime, 'Y-m-d H:i')) ?></td><td class="wrap sm"><?= h(trim($hst->remarks . ' by ' . $hst->update_by_fname)) ?></td></tr>
      <?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
  </aside>
</div>
<?= sl_fRadioScript() ?>
