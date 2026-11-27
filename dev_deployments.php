<?php
/** Developer console: Deployments (Swift Lane design: DevDeployments, dark). Pipelines from GitHub Actions, deployments, database deploy tools. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_developer');
require_once 'helpers/dev_github.php';
// the page is "deployments" (also enforced by sl_moduleDevPages); its Database tab and the database
// endpoint need "deployments" AND "database"
sl_ghPageGate($user, 'deployments');
require_once 'views/sl/data/dev_deploy.php';
sl_render('DevDeployments', ['id' => 'devdep', 'title' => 'Deployments', 'board' => 'DevDeployments', 'theme' => 'dk dvx', 'styles' => sl_dplStyles(), 'scripts' => ['dataJs/sl/dev_deploy.js']], sl_dplDeploymentsData($user, $core));
