<?php
/**
 * StaffEdit - edit a staff account (users_edit.php?user=) or a driver (drivers_edit.php?user=), after InviteStaff.
 * Writes (unchanged legacy endpoints and field names):
 *   ajax/users/users_edit_ajax.php or ajax/drivers/drivers_edit_ajax.php   details, role, office, password, flags
 *   ajax/users/users_avatar_edit_ajax.php                                     profile photo
 * Rank rules stay in the endpoint (cdp_canManageUser / cdp_canAssignRole); the role list only offers assignable roles.
 */
$r = $d['row'];
$id = (int) $r->id;
$drv = $d['driver'];
$back = $drv ? sl_route('Drivers') : sl_route('StaffRoles');
?>
<div class="row g8 mut sm"><a href="<?= h($back) ?>"><?= $drv ? 'Drivers' : 'Staff and roles' ?></a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)"><?= h($d['name']) ?></span></div>
<div class="sl-head">
  <div><h1 class="sl-title"><?= $d['isOwn'] ? 'My profile' : 'Edit ' . h($d['name']) ?></h1><p class="sl-lede"><?= $drv ? 'Driver' : h($d['roleName'] ?: 'Staff') ?> · username <?= h($r->username) ?><?= !$drv && $r->name_off ? ' · ' . h($r->name_off) : '' ?></p></div>
  <div class="row g8 wrap">
    <?php if ($d['canPerms']): ?><a class="sl-btn ghost" href="user_permissions.php?user=<?= $id ?>"><i class="ic ic-sm i-key" aria-hidden="true"></i>Permissions</a><?php endif; ?>
    <a class="sl-btn ghost" href="<?= h($back) ?>">Cancel</a>
    <button class="sl-btn" type="submit" form="se-form"><i class="ic ic-sm i-check" aria-hidden="true"></i>Save changes</button>
  </div>
</div>
<div class="split">
  <div class="a col g16">
    <form id="se-form" class="col g16" data-sl-form data-sl-reload action="<?= $drv ? 'ajax/drivers/drivers_edit_ajax.php' : 'ajax/users/users_edit_ajax.php' ?>" method="post" autocomplete="off">
      <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="username" value="<?= h($r->username) ?>">
      <section class="sl-card col g16">
        <div class="sl-ch" style="margin:0"><h2 class="sl-ct">Account</h2></div>
        <div class="grid cols2" style="gap:12px">
          <?= sl_fInput('First name', 'fname', $r->fname) ?>
          <?= sl_fInput('Last name', 'lname', $r->lname) ?>
          <?= sl_fInput('E-mail', 'email', $r->email, 'email') ?>
          <?= sl_fInput('Phone', 'phone', $r->phone, 'tel', 'placeholder="+233 20 000 0000"') ?>
          <?= sl_fSelect('Gender', 'gender', ['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'], $r->gender, '—') ?>
          <?= sl_fInput('New password', 'password', '', 'text', 'autocomplete="new-password"', 'Leave blank to keep the current password') ?>
          <?php if ($drv): ?>
          <?= sl_fInput('Vehicle registration', 'enrollment', $r->enrollment ?? '') ?>
          <?= sl_fInput('Vehicle code', 'vehiclecode', $r->vehiclecode ?? '') ?>
          <?php else: ?>
          <label class="sl-field"><span class="sl-lab">Role</span><span class="sl-sel native"><select name="userlevel"><?= $d['roleOptions'] /* built by cdp_roleOptionsHtml(), escaped there */ ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span><span class="sl-note">Only roles below your own rank are offered</span></label>
          <?php if ($d['officeMode'] === 'select'): ?>
          <label class="sl-field"><span class="sl-lab">Office</span><span class="sl-sel native"><select name="branch_office"><?php foreach ($d['offices'] as $off): ?><option value="<?= h($off->name_off) ?>" <?= $off->name_off === $r->name_off ? 'selected' : '' ?>><?= h($off->name_off) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
          <?php elseif ($d['officeMode'] === 'readonly'): ?>
          <?= sl_fInput('Office', 'branch_office', $r->name_off, 'text', 'readonly') ?>
          <?php endif; ?>
          <?php endif; ?>
        </div>
        <div class="grid cols2" style="gap:12px">
          <?= sl_fRadios('Status', 'active', ['1' => 'Active', '0' => 'Inactive'], (string) (int) $r->active) ?>
          <?= sl_fRadios('Newsletter', 'newsletter', ['1' => 'Yes', '0' => 'No'], (string) (int) $r->newsletter) ?>
        </div>
      </section>
      <section class="sl-card col g12">
        <div class="sl-ch" style="margin:0"><h2 class="sl-ct">Notes</h2></div>
        <textarea class="sl-in" name="notes" rows="4"><?= h(trim((string) $r->notes)) ?></textarea>
      </section>
      <div class="row g8"><button class="sl-btn" type="submit"><i class="ic ic-sm i-check" aria-hidden="true"></i>Save changes</button></div>
    </form>
  </div>
  <aside class="b col g16">
    <section class="sl-card col g12">
      <div class="row g12"><span class="sl-av lg dark" style="width:56px;height:56px;background-image:url(<?= h($d['avatar']) ?>);background-size:cover;color:transparent"><?= h(sl_initials($d['name'])) ?></span><div class="col"><b class="fw5"><?= h($d['name']) ?></b><span class="mut sm"><?= h($r->email) ?></span></div></div>
      <div class="sl-kv">
        <div><span>Status</span><span><?= (int) $r->active ? sl_pill('Active', 'delivered') : sl_pill('Inactive', 'plain') ?></span></div>
        <div><span>Role</span><span><?= $drv ? 'Driver' : h($d['roleName']) ?></span></div>
        <?php if (!$drv && $r->name_off): ?><div><span>Office</span><span><?= h($r->name_off) ?></span></div><?php endif; ?>
        <div><span>Phone</span><span><?= h($r->phone) ?: '—' ?></span></div>
        <div><span>Created</span><span><?= h(sl_date($r->created)) ?></span></div>
        <div><span>Last sign-in</span><span><?= h(sl_date($r->lastlogin, 'M j, Y H:i')) ?: '—' ?></span></div>
      </div>
    </section>
    <?= sl_fPhotoForm('Profile photo', 'ajax/users/users_avatar_edit_ajax.php', 'avatar', $d['avatar'], ['id' => $id]) ?>
  </aside>
</div>
<?= sl_fRadioScript() ?>
