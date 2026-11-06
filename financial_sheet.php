<?php
/**
 * Billing and finance hub (BillingHub.dc.html). The hub stays reachable while
 * finance is OFF so an admin can switch it back on. The legacy Financial Sheet
 * (consolidation pricing/billing/payment) is financial_sheet_consolidation.php;
 * ?legacy=1 redirects there.
 */
require_once 'loader.php';
require_once 'helpers/finance_switch.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
if (!empty($_GET['legacy'])) {
    // The old Financial Sheet screen is retired: its workspace is financial_sheet_consolidation.php.
    $q = $_GET;
    unset($q['legacy']);
    header('Location: financial_sheet_consolidation.php' . ($q ? '?' . http_build_query($q) : ''));
    exit;
}
sl_gate();
if (!$user->cdp_hasPermission('financial_sheet') && !sl_userIs('superadmin') && !$user->cdp_hasPermission('manage_finance_switch')) {
    header('Location: error403.php');
    exit;
}
require_once 'views/sl/data/finance.php';
sl_render('BillingHub', ['id' => 'billhub', 'title' => 'Billing and finance', 'board' => 'BillingHub', 'wide' => true], sl_billingHubData($user, $core));
