<?php
// System Logs (Swift Lane UI): PHP error log tail, audit failures, cron and security. Read only.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_system_logs');
require_once 'views/sl/data/system_logs.php';
sl_render('SystemLogs', ['id' => 'security', 'title' => 'System Logs', 'board' => 'SystemLogs'], sl_systemLogsData($user, $core));
