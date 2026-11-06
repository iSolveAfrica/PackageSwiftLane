<?php
// Activity Logs (Swift Lane UI) - who did what, and when.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_activity_logs');
require_once 'views/sl/data/activity_logs.php';
if (($_GET['export'] ?? '') === 'csv') {
    if (!$user->cdp_hasPermission('export_activity_logs')) {
        header('Location: error403.php');
        exit;
    }
    require 'views/sl/export/activity_logs_csv.php';
    exit;
}
sl_render('ActivityLogs', ['id' => 'activity', 'title' => 'Activity Logs', 'board' => 'ActivityLogs'], sl_activityLogsData($user, $core));
