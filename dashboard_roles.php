<?php
// Generic permission-aware dashboard for staff roles whose dashboard_type is 'roles' (index.php includes
// this file). Renders the design Overview (views/sl/pages/Main.php): sl_overviewData() gates every
// queue and metric by the viewer's own permissions.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_dashboard');
require_once 'views/sl/data/overview.php';
sl_render('Main', ['id' => 'dash', 'title' => 'Overview', 'board' => 'Main'], sl_overviewData($user, $core, $userData));
