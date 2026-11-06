<?php
/** Management dashboard (ExecDashboard.dc.html): figures computed from the database, air first. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['main_dashboard_index', 'view_exec_dashboard']);
require_once 'views/sl/data/exec.php';
sl_render('ExecDashboard', ['id' => 'execdash', 'title' => 'Management dashboard', 'board' => 'ExecDashboard', 'wide' => true], sl_execData($user, $core));
