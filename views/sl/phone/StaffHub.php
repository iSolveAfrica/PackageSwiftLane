<?php
/**
 * M-StaffHub: Staff & branches on a phone (staff_hub.php, ops rank 2+ as checked by the controller).
 * The web page loads its panes by AJAX; the phone screen reads the same helpers directly
 * (helpers/staffops.php cdp_staffBranches / cdp_staffShifts, scoped to the viewer's branches).
 * Shifts are edited in the full web tool (Shifts tab).
 */
require_once CDP_ROOT . '/views/sl/data/phone_people.php';
$slPhone['nav'] = 'staff';
$hub = !empty($d['ready']) ? sl_ppStaffHub((int) ($_GET['branch'] ?? 0)) : ['branches' => [], 'branch' => 0, 'rows' => [], 'on' => 0, 'late' => 0, 'total' => 0];
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-people.css')) ?>">
<?= sl_phoneTop('Staff & branches', date('l j M'), ['back' => 'menu.php'], sl_phoneIcon('users', 'User accounts and permissions', sl_route('StaffRoles'))) ?>
<main class="mx-body">
<?php if (empty($d['ready'])): ?><div class="mx-ban bad"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>The staff tables could not be created. The database user needs the CREATE privilege once.</span></div><?php endif; ?>
<?php if ($hub['branches']): ?>
<nav class="mx-seg" aria-label="Branch">
  <a class="<?= !$hub['branch'] ? 'on' : '' ?>" href="<?= h(sl_url(['branch' => null])) ?>">All branches</a>
<?php foreach ($hub['branches'] as $b): ?>
  <a class="<?= $b['id'] === $hub['branch'] ? 'on' : '' ?>" href="<?= h(sl_url(['branch' => $b['id']])) ?>"<?= $b['id'] === $hub['branch'] ? ' aria-current="true"' : '' ?>><?= h($b['name']) ?></a>
<?php endforeach; ?>
</nav>
<?php endif; ?>
<section class="mx-k c2">
  <div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-users" aria-hidden="true"></i></span><small>On shift</small></div><b><?= (int) $hub['on'] ?></b><em>of <?= (int) $hub['total'] ?> planned today</em></div>
  <div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-clock" aria-hidden="true"></i></span><small>Checked in late</small></div><b><?= (int) $hub['late'] ?></b><em class="<?= $hub['late'] ? 'warn' : '' ?>"><?= $hub['late'] ? 'after the shift start' : 'everyone on time' ?></em></div>
</section>
<div class="mx-sec"><h2>Today</h2></div>
<?php if ($hub['rows']): ?>
<section class="mx-card mx-list">
<?php foreach ($hub['rows'] as $r): ?>
  <div class="mx-row"><span class="mx-ava"><?= h($r['ini']) ?></span><span class="mx-rm"><b><?= h($r['name']) ?></b><small><?= h($r['sub']) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($r['pill'][1]) ?>"><?= h($r['pill'][0]) ?></span></span></div>
<?php endforeach; ?>
</section>
<?php else: ?>
<?= sl_mxEmpty('clock', 'No shifts planned today', $hub['branches'] ? 'Plan shifts for this branch in the Shifts tab.' : 'Add a branch first, then plan shifts.') ?>
<?php endif; ?>
<div class="mx-btns"><a class="mx-btn" href="staff_hub.php?view=web">Edit shifts</a></div>
<p class="mx-note">Shifts, tasks, transfers and approvals open in the full website on this phone.</p>
</main>
