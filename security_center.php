<?php
/** Security and reliability (Swift Lane design: SecurityCenter). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_system_logs');
require_once 'views/sl/data/security.php';
sl_render('SecurityCenter', ['id' => 'security', 'title' => 'Security and reliability', 'board' => 'SecurityCenter'], sl_securityData($user, $core));
