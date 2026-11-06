<?php
/** Developer console: Deployments (Swift Lane design: DevDeployments, dark). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_developer');
require_once 'views/sl/data/dev.php';
sl_render('DevDeployments', ['id' => 'devdep', 'title' => 'Deployments', 'board' => 'DevDeployments', 'theme' => 'dk'], sl_devDeploymentsData($user, $core));
