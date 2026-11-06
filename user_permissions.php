<?php
// *************************************************************************
// * PackageSwiftLane — per-user permission overrides (root controller)   *
// *************************************************************************
// Swift Lane design: views/sl/pages/UserPermissions.php. ?user=<id>
// Managing another user's permissions is an admin-grade action (edit_user) and
// only for accounts of lower rank than the viewer (cdp_canManageUser).
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('edit_user');
require_once 'views/sl/data/account_edit.php';
$d = sl_userPermsData($user, (int) ($_GET['user'] ?? 0));
if (!$d) {
    header('Location: users_list.php');
    exit;
}
sl_render('UserPermissions', ['id' => 'staff', 'title' => 'Permissions · ' . $d['name'], 'board' => 'StaffPermissions'], $d);
