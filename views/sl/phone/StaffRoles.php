<?php
/**
 * M-StaffRoles: staff accounts and roles on a phone (users_list.php). Same $d as the web page
 * (views/sl/data/staff.php sl_staffRolesData): search q, role filter, page; links and rights as on the web page.
 */
require_once CDP_ROOT . '/views/sl/data/phone_people.php';
$slPhone['nav'] = 'staff';
$personHref = function (array $r) use ($d): string {
    if ($r['driver'] && $d['canEdit'] && ($r['managed'] || $r['self'])) return 'drivers_edit.php?user=' . (int) $r['id'];
    if ($d['canPerms'] && $r['managed']) return sl_route('StaffPermissions', ['user' => $r['id']]);
    return sl_route('StaffActivity', ['id' => $r['id']]);
};
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-people.css')) ?>">
<?= sl_phoneTop('Staff & roles', sl_num($d['online']) . ' online now', ['back' => 'menu.php'], $d['hasLog'] ? sl_phoneIcon('clock', 'Staff activity', sl_route('StaffActivity')) : '') ?>
<main class="mx-body">
<nav class="mx-seg" aria-label="Staff and roles">
  <a class="on" aria-current="true" href="<?= h(sl_url(['role' => null, 'page' => null, 'q' => null, 'branch' => null])) ?>">Staff <?= sl_num($d['total']) ?></a>
  <?php if ($d['canPerms']): ?><a href="<?= h(sl_route('StaffPermissions')) ?>">Roles <?= count($d['byRole']) ?></a><?php endif; ?>
  <?php if ($d['canAdd']): ?><a href="<?= h(sl_route('InviteStaff')) ?>">Invites</a><?php endif; ?>
</nav>
<form class="mx-srch" method="get" role="search">
  <?php if ($d['role']): ?><input type="hidden" name="role" value="<?= (int) $d['role'] ?>"><?php endif; ?>
  <label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q']) ?>" aria-label="Search staff" placeholder="Name, e-mail or username" autocomplete="off" enterkeyhint="search"></label>
</form>
<?php if ($d['role'] || $d['branch'] !== ''): ?><nav class="mx-seg" aria-label="Filter"><a class="on" href="<?= h(sl_url(['role' => null, 'branch' => null, 'page' => null])) ?>">Clear filter ×</a></nav><?php endif; ?>
<?php if ($d['rows']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['rows'] as $r): ?>
  <a class="mx-row" href="<?= h($personHref($r)) ?>"><span class="mx-ava"><?= h($r['ini']) ?></span><span class="mx-rm"><b><?= h($r['name']) ?></b><small><?= h($r['role']) ?><?= $r['branch'] !== '—' ? ' · ' . h($r['branch']) : '' ?> · <?= h($r['seen']) ?></small></span><span class="mx-rr"><?php if ($r['st'] !== 'Active'): ?><span class="sl-pill <?= h($r['cls']) ?>"><?= h($r['st']) ?></span><?php elseif ($r['driver']): ?><span class="sl-pill plain">Rider</span><?php elseif ($r['twostep']): ?><span class="sl-pill delivered">2-step</span><?php else: ?><span class="sl-pill overdue">No 2-step</span><?php endif; ?></span></a>
<?php endforeach; ?>
</section>
<?= sl_ppPager((int) $d['page'], (int) $d['per'], (int) $d['count']) ?>
<?php else: ?>
<?= sl_mxEmpty('users', 'No staff match this search') ?>
<?php endif; ?>
<?php if ($d['byRole']): ?>
<div class="mx-sec"><h2>Roles</h2><?php if ($d['canPerms']): ?><a href="<?= h(sl_route('StaffPermissions')) ?>">Edit roles</a><?php endif; ?></div>
<section class="mx-card mx-list">
<?php foreach ($d['byRole'] as $b): ?>
  <a class="mx-row" href="<?= h($d['canPerms'] ? sl_route('StaffPermissions', ['role' => $b['id']]) : sl_url(['role' => $b['id'], 'page' => null])) ?>"><span class="mx-li"><i class="ic i-shield" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($b['label']) ?></b><small><?= (int) $b['n'] ?> <?= $b['n'] === 1 ? 'person' : 'people' ?></small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<?php endforeach; ?>
</section>
<?php endif; ?>
</main>
<?php if ($d['canAdd']): ?><a class="mx-fab" href="<?= h(sl_route('InviteStaff')) ?>" aria-label="Invite staff"><i class="ic i-plus" aria-hidden="true"></i></a><?php endif; ?>
