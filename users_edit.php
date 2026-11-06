<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************
// Edit a staff account (Swift Lane design: views/sl/pages/StaffEdit.php). ?user=<id>
// Everyone may open their OWN account ("My profile"); other accounts need edit_user
// and a higher rank than the target (cdp_canManageUser).
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
require_once 'helpers/sl_form.php';
sl_gate();
$targetId = (int) ($_GET['user'] ?? 0);
$isOwnProfile = $targetId === (int) $user->uid;
if (!$isOwnProfile && !$user->cdp_hasPermission('edit_user')) {
    header('Location: error403.php');
    exit;
}
if (!$isOwnProfile && !$user->cdp_is_Admin()) {
    header('Location: login.php');
    exit;
}
require_once 'views/sl/data/account_edit.php';
$d = sl_staffEditData($user, $core, $targetId, false);
if (!$d) {
    header('Location: login.php');
    exit;
}
if (!empty($d['denied'])) {
    header('Location: users_list.php');
    exit;
}
sl_render('StaffEdit', ['id' => 'staff', 'title' => $isOwnProfile ? 'My profile' : 'Edit ' . $d['name'], 'board' => 'InviteStaff'], $d);
