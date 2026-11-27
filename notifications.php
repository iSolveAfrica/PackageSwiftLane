<?php
/** Notifications (M-CNotifs): the customer's own feed. Staff and riders use the full notifications page. */
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
if (!sl_userIs('client')) { header('Location: ' . sl_route('Notifications')); exit; }
header('Cache-Control: private, no-store');
require_once 'helpers/sl_mx.php';
require_once 'views/sl/data/customer_app.php';
sl_render('MyNotifications', sl_mxPage(['id' => 'notif', 'title' => 'Notifications', 'board' => 'CustomerApp', 'scripts' => ['dataJs/sl/customer_app.js']]), sl_caNotificationsData((int) $_SESSION['userid']));
