<?php
/** Developer console: Alerts (dark DevOps theme). Data: views/sl/data/dev_infra.php. Writes: ajax/sl/dev_infra_ajax.php. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
require_once 'views/sl/data/dev_infra.php';
sl_dinfGate($user, 'alerts');
sl_render('DevAlerts', ['id' => 'devalert', 'title' => 'Alerts', 'board' => 'DevAlerts', 'theme' => 'dk dvx', 'styles' => ['assets/swiftlane/dev_theme.css'], 'scripts' => ['dataJs/sl/dev_infra.js']], sl_dinfAlertsData($user, $core));
