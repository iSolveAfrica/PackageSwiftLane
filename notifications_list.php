<?php
/** Notifications: the signed-in user's in-app notifications and delivery preferences. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
require_once 'views/sl/data/notifications.php';
sl_render('Notifications', ['id' => 'notif', 'title' => 'Notifications', 'board' => 'Notifications'], sl_notificationsData($user, $core));
