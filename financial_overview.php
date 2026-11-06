<?php
/**
 * Financial Overview. Users without view_money_finance / view_monetary_values
 * (helpers/rbac.php cdp_canViewMoney) get the FinancialOverviewRestricted board:
 * the same page with every amount hidden server-side.
 */
require_once 'loader.php';
require_once 'helpers/finance_switch.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_financial_overview');
cdp_financeGuardPage();
require_once 'helpers/rbac.php';
require_once 'views/sl/data/finance.php';
$money = cdp_canViewMoney($user, 'finance') && empty($_GET['as_restricted']);
$d = sl_financialOverviewData($user, $core, $money);
sl_render($money ? 'FinancialOverview' : 'FinancialOverviewRestricted', ['id' => 'fin', 'title' => 'Financial Overview', 'board' => $money ? 'FinancialOverview' : 'FinancialOverviewRestricted'], $d);
