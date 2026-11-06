<?php
// Pre-alerts (PreAlerts artboard). Legacy view: views/prealert/prealert_list.php.
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('prealert_list');
require_once 'views/sl/data/prealerts.php';
sl_render('PreAlerts', ['id' => 'prealert', 'title' => 'Pre-Alerts', 'board' => 'PreAlerts', 'styles' => ['assets/swiftlane/pages/ops.css']], sl_preAlertsData($user, $core));
