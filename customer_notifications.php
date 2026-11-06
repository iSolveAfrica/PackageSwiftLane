<?php
// CustomerNotifications board: the signed-in user's own message preferences.
// Writes: ajax/sl/notification_prefs_ajax.php (cdb_user_notification_prefs, cdb_user_preferences).
require_once("loader.php");
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
require_once 'helpers/sl_auth.php';
sl_passwordRotationGuard();
require_once 'views/sl/data/portal.php';
sl_render('CustomerNotifications', ['id' => 'notifset', 'title' => 'Notification settings', 'board' => 'CustomerNotifications', 'scripts' => ['dataJs/sl/notifications.js']], sl_notifPrefsData($user, $core, $userData));
