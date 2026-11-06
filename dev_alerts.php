<?php
/** Developer console: Alerts (Swift Lane design: DevAlerts, dark). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_developer');
require_once 'views/sl/data/dev.php';
sl_render('DevAlerts', ['id' => 'devalert', 'title' => 'Alerts', 'board' => 'DevAlerts', 'theme' => 'dk'], sl_devAlertsData($user, $core));
