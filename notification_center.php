<?php
/** Notification Center: which system event sends which channel, and the template each uses. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['manage_notification_rules', 'view_tools']);
require_once 'views/sl/data/notification_center.php';
sl_render('NotificationCenter', ['id' => 'notifc', 'title' => 'Notification Center', 'board' => 'NotificationCenter'], sl_notificationCenterData($user, $core));
