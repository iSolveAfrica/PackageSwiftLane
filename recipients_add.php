<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************
// Add a recipient (Swift Lane design: views/sl/pages/RecipientForm.php).
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
require_once 'helpers/sl_form.php';
sl_gate('recipients_add');
require_once 'views/sl/data/account_edit.php';
$d = sl_recipientFormData($user, 0);
if (!$d) {
    header('Location: recipients_list.php');
    exit;
}
sl_render('RecipientForm', ['id' => 'recip', 'title' => 'Add recipient', 'board' => 'AddCustomer'], $d);
