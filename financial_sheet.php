<?php
/**
 * Billing and finance hub (BillingHub.dc.html). The hub stays reachable while
 * finance is OFF so an admin can switch it back on. The legacy Financial Sheet
 * (consolidation pricing/billing/payment UI) stays at ?legacy=1.
 */
require_once 'loader.php';
require_once 'helpers/finance_switch.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
if (!empty($_GET['legacy'])) {
    if ($user->cdp_loginCheck() && $user->cdp_hasPermission('financial_sheet')) {
        $permissions = $user->cdp_getUserPermissions();
        include 'views/reports/financial_sheet/financial_sheet.php';
        exit;
    }
    header('Location: ' . ($user->cdp_loginCheck() ? 'error403.php' : 'login.php'));
    exit;
}
sl_gate();
if (!$user->cdp_hasPermission('financial_sheet') && !sl_userIs('superadmin') && !$user->cdp_hasPermission('manage_finance_switch')) {
    header('Location: error403.php');
    exit;
}
require_once 'views/sl/data/finance.php';
sl_render('BillingHub', ['id' => 'billhub', 'title' => 'Billing and finance', 'board' => 'BillingHub', 'wide' => true], sl_billingHubData($user, $core));
