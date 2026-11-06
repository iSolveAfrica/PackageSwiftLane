<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************
// Edit a driver (Swift Lane design: views/sl/pages/StaffEdit.php). ?user=<id>
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
require_once 'helpers/sl_form.php';
sl_gate('drivers_edit');
$targetId = (int) ($_GET['user'] ?? 0);
if ($targetId !== (int) $user->uid && !$user->cdp_is_Admin()) {
    header('Location: login.php');
    exit;
}
require_once 'views/sl/data/account_edit.php';
$d = sl_staffEditData($user, $core, $targetId, true);
if (!$d) {
    header('Location: login.php');
    exit;
}
sl_render('StaffEdit', ['id' => 'drivers', 'title' => 'Edit ' . $d['name'], 'board' => 'InviteStaff'], $d);
