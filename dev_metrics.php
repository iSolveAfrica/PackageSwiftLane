<?php
/** Developer console: Metrics (Swift Lane design: DevMetrics, dark). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_developer');
require_once 'views/sl/data/dev.php';
sl_render('DevMetrics', ['id' => 'devmet', 'title' => 'Metrics', 'board' => 'DevMetrics', 'theme' => 'dk'], sl_devMetricsData($user, $core));
