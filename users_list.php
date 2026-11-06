<?php
// Staff & Roles (Swift Lane UI): staff list, roles, access changes.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['view_user_list', 'view_role_assignment']);
require_once 'views/sl/data/staff.php';
if (($_GET['export'] ?? '') === 'csv') {
    $d = sl_staffRolesData($user, $core);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="staff-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name', 'Email', 'Role', 'Branch', 'Last active', 'Status']);
    foreach ($d['rows'] as $r) fputcsv($out, [$r['name'], $r['mail'], $r['role'], $r['branch'], $r['seen'], $r['st']]);
    fclose($out);
    exit;
}
sl_render('StaffRoles', ['id' => 'staff', 'title' => 'Staff & Roles', 'board' => 'StaffRoles', 'primary' => ($user->cdp_hasPermission('add_user') ? ['href' => sl_route('InviteStaff'), 'label' => 'Invite staff'] : null)], sl_staffRolesData($user, $core));
