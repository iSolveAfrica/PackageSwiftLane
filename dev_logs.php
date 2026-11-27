<?php
/** Developer console: Logs (Swift Lane design: DevLogs, dark DevOps theme). Data: views/sl/data/dev_observe.php. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
require_once 'views/sl/data/dev_observe.php';
sl_dvxGate($user, 'logs');
sl_render('DevLogs', ['id' => 'devlogs', 'title' => 'Logs', 'board' => 'DevLogs', 'theme' => 'dk dvx', 'styles' => ['assets/swiftlane/dev_theme.css'], 'scripts' => ['dataJs/sl/dev_observe.js']], sl_dvxLogsData($user, $core));
