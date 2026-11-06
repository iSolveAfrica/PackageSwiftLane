<?php
// ============================================================================
// Pay bills (PayCheckout board). Customer only. Online payment reuses the
// My Bills flow: ajax/customer/my_bills_ajax.php (action=bill / checkout) ->
// helpers/fs_payments.php cdp_fsCreateIntent() -> Paystack hosted checkout ->
// payment_return.php / ajax/gateway/paystack_webhook.php settle it. This page
// never marks anything as paid by itself.
// ============================================================================
require_once("loader.php");
require_once("helpers/finance_switch.php");
cdp_financeGuardPage();

$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('pay_my_bills');
require_once 'helpers/sl_auth.php';
sl_passwordRotationGuard();
require_once 'views/sl/data/portal.php';
$_GET['filter'] = 'owing';
$d = sl_billsData($user, $core, $userData);
$d['cid'] = (int) ($_GET['cid'] ?? 0);
sl_render('PayCheckout', ['id' => 'mybills', 'title' => 'Pay your bills', 'board' => 'PayCheckout', 'scripts' => ['dataJs/sl/pay_checkout.js']], $d);
