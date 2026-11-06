<?php
/** Record a payment (design RecordPayment.dc.html) against a consolidation_packages. Write: ajax/sl/record_payment_ajax.php. */
require_once 'loader.php';
require_once 'helpers/finance_switch.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['fs_record_payment', 'manage_account_payments', 'verify_payments', 'verify_consolidate_payment', 'verify_consolidate_package_payment']);
cdp_financeGuardPage();
require_once 'views/sl/data/finance.php';
$type = (string) ($_GET['type'] ?? 'consolidation_packages');
sl_render('RecordPayment', ['id' => 'pay', 'title' => 'Record a payment', 'board' => 'RecordPayment'], sl_recordPaymentData($user, $core, $type));
