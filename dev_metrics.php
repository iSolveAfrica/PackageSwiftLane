<?php
/** Developer console: Metrics and traces (Swift Lane design: DevMetrics, dark DevOps theme). Data: views/sl/data/dev_observe.php. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
require_once 'views/sl/data/dev_observe.php';
sl_dvxGate($user, 'metrics');
sl_render('DevMetrics', ['id' => 'devmet', 'title' => 'Metrics and traces', 'board' => 'DevMetrics', 'theme' => 'dk dvx', 'styles' => ['assets/swiftlane/dev_theme.css'], 'scripts' => ['dataJs/sl/dev_observe.js']], sl_dvxMetricsData($user, $core));
