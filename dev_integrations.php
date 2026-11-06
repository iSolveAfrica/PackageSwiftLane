<?php
/** Developer console: Integrations (Swift Lane design: DevIntegrations, dark). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_developer');
require_once 'views/sl/data/dev.php';
sl_render('DevIntegrations', ['id' => 'devint', 'title' => 'Integrations', 'board' => 'DevIntegrations', 'theme' => 'dk'], sl_devIntegrationsData($user, $core));
