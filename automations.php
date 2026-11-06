<?php
/** Automations: "when <event> then <action>" rules (helpers/automations.php). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('manage_automations');
require_once 'views/sl/data/automations.php';
sl_render('Automations', ['id' => 'auto', 'title' => 'Automations', 'board' => 'Automations'], sl_automationsData($user, $core));
