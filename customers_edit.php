<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************
// Edit a client account (Swift Lane design: views/sl/pages/CustomerEdit.php). ?user=<id>
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
require_once 'helpers/sl_form.php';
sl_gate('edit_client');
require_once 'views/sl/data/account_edit.php';
$d = sl_customerEditData($user, (int) ($_GET['user'] ?? 0));
if (!$d) {
    header('Location: users_list.php');
    exit;
}
if (!empty($d['notClient'])) {
    header('Location: ' . sl_route('Customers'));
    exit;
}
sl_render('CustomerEdit', ['id' => 'cust', 'title' => 'Edit ' . $d['name'], 'board' => 'AddCustomer'], $d);
