<?php
/** API Monitoring: /api/v1 request log (cdb_api_request_log) and outbound provider calls. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['view_api_logs', 'manage_api_settings']);
require_once 'views/sl/data/api_admin.php';
sl_render('ApiMonitor', ['id' => 'apitraffic', 'title' => 'API Monitoring', 'board' => 'ApiMonitor'], sl_apiMonitorData($user, $core));
