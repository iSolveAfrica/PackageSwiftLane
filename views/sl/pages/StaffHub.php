<?php
/** StaffHub - Staff & Branches (staff_hub.php). Panes filled by dataJs/staff_hub.js from ajax/staffops/staffops_ajax.php. */
if (!isset($d)) { $d = []; }
$tabs = ['branches' => 'Branches', 'staff' => 'Staff', 'access' => 'Role access', 'shifts' => 'Shifts', 'tasks' => 'Tasks', 'transfers' => 'Transfers',
    'approvals' => 'Approvals', 'announcements' => 'Announcements', 'commissions' => 'Agent commissions', 'performance' => 'Performance', 'activity' => 'Activity log'];
?>
<div class="ops">
<div class="sl-head"><div><h1 class="sl-title" style="font-size:20px">Staff &amp; Branches</h1><p class="sl-lede">Branches in every country, staff and their roles, shifts, tasks, transfers, approvals and agents</p></div>
<div class="row g8 wrap"><a class="sl-btn ghost" href="<?= h(sl_route('Workspace')) ?>">My workspace</a><a class="sl-btn ghost" href="<?= h(sl_route('StaffRoles')) ?>">User accounts &amp; permissions</a></div></div>
<?php if (empty($d['ready'])): ?><div class="swl-ops-banner is-danger">The staff tables could not be created. The database user needs the CREATE privilege once.</div><?php endif; ?>
<div class="swl-ops-tabs" role="tablist" aria-label="Staff and branches sections">
<?php $first = true; foreach ($tabs as $k => $label): ?><button type="button" role="tab" id="sh_tab_<?= h($k) ?>" aria-controls="sh_pane_<?= h($k) ?>" aria-selected="<?= $first ? 'true' : 'false' ?>" data-tab="<?= h($k) ?>"><?= h($label) ?></button><?php $first = false; endforeach; ?>
</div>
<?php $first = true; foreach ($tabs as $k => $label): ?>
<section class="swl-ops-pane sl-card" role="tabpanel" id="sh_pane_<?= h($k) ?>" aria-labelledby="sh_tab_<?= h($k) ?>"<?= $first ? '' : ' hidden' ?>><div data-pane-body="<?= h($k) ?>"><p class="swl-ops-muted">Loading…</p></div></section>
<?php $first = false; endforeach; ?>
</div>
