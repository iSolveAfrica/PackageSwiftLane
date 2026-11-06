<?php
/**
 * StaffPermissions - generated from docs/handover/artifact-source/StaffPermissions.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  roles                        as $r
 *   loop  tabs                         as $t
 *   loop  presets                      as $p
 *   loop  acts                         as $a
 *   loop  rows                         as $r
 *   loop  r.cells                      as $c
 *   loop  sens                         as $x
 *   loop  members                      as $m
 *   if    tabPerm
 *   if    r.isGroup
 *   if    r.isRow
 *   if    isBlur
 *   if    tabMem
 *   if    tabLog
 *   value a, acts, blur, false, grantW, granted, isBlur, members, moneyLabel, moneyTick, moneyW, onQ, possible, presets, q, roleCount, roles, rows, save, saveLabel, sens, tabLog, tabMem, tabPerm, tabs, true
 * sl-wrap extras in the design: style="max-width:1180px"
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:20px">Staff Permissions</h1><p class="sl-lede">Roles decide what each person can see and do, module by module, down to who sees money</p></div><div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('ModuleController') ?>"><i class="ic ic-sm i-sliders" aria-hidden="true"></i>Module controller</a><button class="sl-btn ghost"><i class="ic ic-sm i-eye" aria-hidden="true"></i>View as <?= sl_v($d, 'role.name') ?></button><button class="sl-btn" data-sl-on="click:save"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= sl_v($d, 'saveLabel') ?></button></div></div>
<div class="grid cols4">
<div class="ov-card"><div class="hd"><span><i class="ic ic-sm i-key" aria-hidden="true"></i>Roles</span></div><div class="fig"><?= sl_v($d, 'roleCount') ?> <i>roles · 46 staff</i></div><div class="sl-ticks n"><i style="width:60%"></i></div><div class="ft">2 custom roles</div></div>
<div class="ov-card"><div class="hd"><span><i class="ic ic-sm i-shield" aria-hidden="true"></i><?= sl_v($d, 'role.name') ?> can do</span></div><div class="fig"><?= sl_v($d, 'granted') ?> <i>/ <?= sl_v($d, 'possible') ?> actions</i></div><div class="sl-ticks" role="img" aria-label="Granted"><i style="<?= sl_v($d, 'grantW') ?>"></i></div><div class="ft"><?= sl_v($d, 'role.members') ?> people have this role</div></div>
<div class="ov-card"><div class="hd"><span><i class="ic ic-sm i-dollar" aria-hidden="true"></i>See money</span></div><div class="fig"><?= sl_v($d, 'moneyLabel') ?></div><div class="sl-ticks <?= sl_v($d, 'moneyTick') ?>"><i style="<?= sl_v($d, 'moneyW') ?>"></i></div><div class="ft">Amounts are blurred without it</div></div>
<div class="ov-card"><div class="hd"><span><i class="ic ic-sm i-user" aria-hidden="true"></i>Access requests</span></div><div class="fig">3 <i>waiting</i></div><div class="sl-ticks r"><i style="width:30%"></i></div><div class="ft">Oldest 2 days</div></div>
</div>
<div class="pm">
<aside class="pm-roles" aria-label="Roles">
<div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input aria-label="Search roles" placeholder="Search roles"></div>
<?php foreach ((array) sl_get($d, 'roles', []) as $__i => $r): ?><button class="pm-role <?= sl_v($r, 'cls') ?>" data-sl-on="click:pick" aria-pressed="<?= sl_v($r, 'sel') ?>"><span class="pm-dot" style="<?= sl_v($r, 'dot') ?>"></span><span class="grow"><b><?= sl_v($r, 'name') ?></b><small><?= sl_v($r, 'members') ?> people · <?= sl_v($r, 'count') ?> actions</small></span><i class="ic ic-sm i-right mut" aria-hidden="true"></i></button><?php endforeach; ?>
<button class="sl-btn ghost" style="margin-top:6px"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New role</button>
</aside>
<div class="pm-main">
<section class="sl-card col g12"><div class="row between wrap g12"><div><div class="up">Role</div><h2 style="font-size:20px;font-weight:600;letter-spacing:-.01em"><?= sl_v($d, 'role.name') ?></h2><p class="mut sm"><?= sl_v($d, 'role.desc') ?></p></div><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'tabs', []) as $__i => $t): ?><button class="<?= sl_v($t, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($t, 'label') ?></button><?php endforeach; ?></div></div>
<div class="row g8 wrap sm"><span class="mut">Start from:</span><?php foreach ((array) sl_get($d, 'presets', []) as $__i => $p): ?><button class="sl-btn sm ghost" data-sl-on="click:apply"><?= sl_v($p, 'label') ?></button><?php endforeach; ?></div></section>

<?php if (sl_get($d, 'tabPerm')): ?>
<section class="sl-card flush"><div class="row between wrap g8" style="padding:14px 16px"><h2 class="sl-ct">Module permissions</h2><div class="sl-search" style="max-width:260px"><i class="ic ic-sm i-search" aria-hidden="true"></i><input aria-label="Search permissions" placeholder="Search modules" value="<?= sl_v($d, 'q') ?>" data-sl-on="change:onQ"></div></div>
<div class="sl-tw"><table class="pm-mx"><thead><tr><th>Module</th><?php foreach ((array) sl_get($d, 'acts', []) as $__i => $a): ?><th><?= sl_v($a, '') ?></th><?php endforeach; ?><th></th></tr></thead><tbody>
<?php foreach ((array) sl_get($d, 'rows', []) as $__i => $r): ?>
<?php if (sl_get($r, 'isGroup')): ?><tr class="gh"><td colspan="8"><?= sl_v($r, 'label') ?></td></tr><?php endif; ?>
<?php if (sl_get($r, 'isRow')): ?><tr><td><span class="row g10"><i class="ic ic-sm <?= sl_v($r, 'icon') ?> mut" aria-hidden="true"></i><?= sl_v($r, 'label') ?></span></td><?php foreach ((array) sl_get($r, 'cells', []) as $__i => $c): ?><td><button class="pm-c <?= sl_v($c, 'cls') ?>" data-sl-on="click:toggle" aria-label="<?= sl_v($c, 'aria') ?>" aria-pressed="<?= sl_v($c, 'p') ?>"><i class="ic ic-sm i-check" aria-hidden="true"></i></button></td><?php endforeach; ?><td><button class="pm-row-all" data-sl-on="click:all"><?= sl_v($r, 'allLabel') ?></button></td></tr><?php endif; ?>
<?php endforeach; ?>
</tbody></table></div></section>
<section class="sl-card col"><div class="sl-ch"><div><h2 class="sl-ct">Sensitive data</h2><div class="mut sm">Fine controls that cut across every module</div></div></div>
<?php foreach ((array) sl_get($d, 'sens', []) as $__i => $x): ?><div class="pm-sens"><i class="ic <?= sl_v($x, 'icon') ?> mut" aria-hidden="true"></i><div class="nm"><b><?= sl_v($x, 'label') ?></b><small><?= sl_v($x, 'desc') ?></small></div><button class="pm-sw <?= sl_v($x, 'cls') ?>" role="switch" aria-checked="<?= sl_v($x, 'p') ?>" aria-label="<?= sl_v($x, 'label') ?>" data-sl-on="click:toggle"></button></div><?php endforeach; ?>
</section>
<section class="sl-card col g12"><div class="row between wrap g8"><div><h2 class="sl-ct">Preview: what <?= sl_v($d, 'role.name') ?> sees on Financial Overview</h2><div class="mut sm">Live as you switch "See financial figures"</div></div><a class="sm fw5" href="<?= sl_route('FinancialOverviewRestricted') ?>">Open full preview</a></div>
<div class="pm-prev"><div><div class="up">Revenue</div><div class="big <?= sl_v($d, 'blur') ?>">$48,200</div></div><div><div class="up">Gross margin</div><div class="big <?= sl_v($d, 'blur') ?>">$18,560</div></div><div><div class="up">Outstanding</div><div class="big <?= sl_v($d, 'blur') ?>">$6,840</div></div></div>
<?php if (sl_get($d, 'isBlur')): ?><div class="pm-lock"><i class="ic ic-sm i-lock" aria-hidden="true"></i>Figures are blurred for this role. Shipments, weights and counts stay visible.</div><?php endif; ?>
</section>
<?php endif; ?>

<?php if (sl_get($d, 'tabMem')): ?>
<section class="sl-card col"><div class="sl-ch"><h2 class="sl-ct">People with this role</h2><a class="sl-btn sm" href="<?= sl_route('InviteStaff') ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add person</a></div>
<?php foreach ((array) sl_get($d, 'members', []) as $__i => $m): ?><div class="pm-mem"><span class="sl-av sm"><?= sl_v($m, 'ini') ?></span><span class="grow"><?= sl_v($m, 'name') ?> <span class="mut">· <?= sl_v($m, 'branch') ?></span></span><span class="mut sm">Last seen <?= sl_v($m, 'seen') ?></span><button class="sl-btn sm ghost">Change role</button></div><?php endforeach; ?></section>
<?php endif; ?>

<?php if (sl_get($d, 'tabLog')): ?>
<section class="sl-card col"><h2 class="sl-ct">Permission changes</h2>
<div class="pm-mem"><span class="sl-av sm">HC</span><span class="grow">Henry removed <b class="fw5">Delete</b> on Payments from Finance</span><span class="mut sm">Nov 3</span></div>
<div class="pm-mem"><span class="sl-av sm">HC</span><span class="grow">Henry turned off <b class="fw5">See financial figures</b> for Support agent</span><span class="mut sm">Oct 30</span></div>
<div class="pm-mem"><span class="sl-av sm">NL</span><span class="grow">Nii created role <b class="fw5">Night shift lead</b></span><span class="mut sm">Oct 21</span></div></section>
<?php endif; ?>
</div>
</div>
