<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************
// My account (Swift Lane design).
//   default      UserHome board (views/sl/pages/UserHome.php)
//   ?edit=1      ProfileEdit (views/sl/pages/ProfileEdit.php): details, addresses, ID document,
//                password and WhatsApp number with a one-time code. ?user= lets an admin edit a customer.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('customers_profile_edit');
if (!empty($_GET['edit'])) {
    require_once 'helpers/sl_form.php';
    require_once 'views/sl/data/account_edit.php';
    $targetId = isset($_GET['user']) ? (int) $_GET['user'] : (int) $user->uid;
    if ($targetId !== (int) $user->uid && !$user->cdp_is_Admin()) {
        header('Location: login.php');
        exit;
    }
    $d = sl_profileEditData($user, $targetId);
    if (!$d) {
        header('Location: ' . ($user->cdp_is_Admin() ? 'customers_list.php' : 'index.php'));
        exit;
    }
    sl_render('ProfileEdit', ['id' => $d['isOwn'] && !sl_userIs('client') ? 'accountsec' : 'myaccount', 'title' => $d['isOwn'] ? 'My details' : 'Edit ' . $d['name'], 'board' => 'UserHome'], $d);
    exit;
}
require_once 'views/sl/data/portal.php';
sl_render('UserHome', ['id' => 'myaccount', 'title' => 'My account', 'board' => 'UserHome'], sl_userHomeData($user, $core, $userData));
