<?php
// Staff & Branches: branches and countries, staff profiles and branch access,
// role access, shifts, tasks, transfers, approvals, announcements, agent
// commissions, performance and activity. Administrators see everything;
// branch managers their own branches (helpers/staffops.php, docs/STAFF_BRANCHES.md).
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
require_once 'helpers/querys.php';
require_once 'helpers/staffops.php';
if (cdp_opsRank($user) < 2) {
    header('Location: error403.php');
    exit;
}
cdp_opsRender('StaffHub', 'staffhub', 'Staff & Branches', ['dataJs/staff_hub.js'], ['ready' => cdp_staffEnsureTables(), 'isAdmin' => cdp_opsRank($user) >= 3]);
