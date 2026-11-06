<?php
// Staff Permissions (Swift Lane UI): roles × module actions, departments, per-user overrides.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_role_assignment');
require_once 'views/sl/data/staff_permissions.php';
sl_render('StaffPermissions', ['id' => 'perm', 'title' => 'Staff Permissions', 'board' => 'StaffPermissions', 'wide' => true], sl_staffPermissionsData($user, $core));
