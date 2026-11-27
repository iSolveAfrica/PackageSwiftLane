<?php
/** Developer console: Integrations and GitHub (Swift Lane design: DevIntegrations, dark). GitHub is read only. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_developer');
require_once 'helpers/dev_github.php';
sl_ghPageGate($user, 'integrations');
require_once 'views/sl/data/dev_deploy.php';
sl_render('DevIntegrations', ['id' => 'devint', 'title' => 'Integrations and GitHub', 'board' => 'DevIntegrations', 'theme' => 'dk dvx', 'styles' => sl_dplStyles(), 'scripts' => ['dataJs/sl/dev_deploy.js']], sl_dplIntegrationsData($user, $core));
