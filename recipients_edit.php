<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************
// Edit a recipient (?recipient=<id>) (Swift Lane design: views/sl/pages/RecipientForm.php).
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
require_once 'helpers/sl_form.php';
sl_gate('recipients_edit');
require_once 'views/sl/data/account_edit.php';
$d = sl_recipientFormData($user, (int) ($_GET['recipient'] ?? 0));
if (!$d) {
    header('Location: recipients_list.php');
    exit;
}
sl_render('RecipientForm', ['id' => 'recip', 'title' => 'Edit recipient', 'board' => 'AddCustomer'], $d);
