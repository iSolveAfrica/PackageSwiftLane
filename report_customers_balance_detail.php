<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************
// Customer balance drill-down (Swift Lane design: views/sl/pages/BalanceDetail.php).
// ?customer=<id>&fecha_inicio=Y-m-d&fecha_fin=Y-m-d, reached from the customer balance report.
require_once 'loader.php';
require_once 'helpers/finance_switch.php';
cdp_financeGuardPage(); // financial module switched off: back to the dashboard
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_client_balance'); // the permission of the customer balance report this page drills into
require_once 'views/sl/data/account_edit.php';
$d = sl_balanceDetailData();
sl_render('BalanceDetail', ['id' => 'reports', 'title' => 'Customer balance · ' . $d['customer'], 'board' => 'ReportView'], $d);
