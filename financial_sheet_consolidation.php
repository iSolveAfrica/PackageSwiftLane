<?php
/**
 * Financial Sheet workspace (Swift Lane design): consolidation picker -> customer
 * billing panel -> item pricing -> payments, discounts and clearance.
 * Template: views/sl/pages/FinancialSheet.php, script: dataJs/sl/financial_sheet.js.
 * Reads and writes go through ajax/reports/financial_sheet_ajax.php (same actions
 * and payloads as before; per-action permissions are enforced there).
 * ?id=N opens a consolidation, &sid=N a customer in it.
 */
require_once 'loader.php';
require_once 'helpers/finance_switch.php';
cdp_financeGuardPage(); // financial module switched off: back to the dashboard
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('financial_sheet');
$rate = (float) ($core->exchange_rate ?: 1);
sl_render('FinancialSheet', [
    'id'      => 'billhub',
    'title'   => 'Financial sheet',
    'board'   => 'BillingHub',
    'styles'  => ['assets/swiftlane/pages/FinancialSheet.css'],
    'scripts' => ['dataJs/sl/financial_sheet.js'],
    'wide'    => true,
], [
    'cid'        => (int) ($_GET['id'] ?? 0),
    'sid'        => (int) ($_GET['sid'] ?? 0),
    'rate'       => $rate > 0 ? $rate : 1,
    'weightRate' => (string) ($core->value_weight ?? ''),
    'unit'       => (string) ($core->weight_p ?? 'lb'),
    'symbol'     => (string) ($core->for_symbol ?: '$'),
    'canRate'    => $user->cdp_hasPermission('fs_price_items'),
]);
