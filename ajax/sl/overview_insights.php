<?php
/** Read-only refresh for the staff overview charts. */
require_once __DIR__ . '/../../loader.php';
require_once CDP_ROOT . '/helpers/ajax_guard.php';
require_once CDP_ROOT . '/views/sl/data/overview.php';

$user = new User();
require_login();
$dashboard = cdp_dashboardType($_SESSION['userlevel'] ?? 0);
if ($dashboard !== 'admin' && ($dashboard !== 'roles' || !$user->cdp_hasPermission('view_dashboard'))) {
    http_response_code(403);
    exit;
}

header('Cache-Control: private, no-store');
header('Content-Type: text/html; charset=UTF-8');
$d = ['insights' => sl_overviewChartsData($user)];
require CDP_ROOT . '/views/sl/pages/partials/overview_insights.php';
