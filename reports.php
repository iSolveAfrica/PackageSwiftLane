<?php
/** Reports hub (Reports.dc.html). Every legacy report list/excel/print page keeps working and is linked from here. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_general_reports');
require_once 'views/sl/data/reports.php';
sl_render('Reports', ['id' => 'reports', 'title' => 'Reports', 'board' => 'Reports'], sl_reportsData($user, $core));
