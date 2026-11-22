<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************
// Notification Studio: what every status and action sends by e-mail, SMS,
// WhatsApp and in-app, editable in one place (views/sl/pages/NotifyStudio.php).
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['manage_notification_rules', 'view_tools']);
require_once 'views/sl/data/notify_studio.php';
sl_render('NotifyStudio', ['id' => 'notifystudio', 'title' => 'Notification Studio', 'board' => 'NotifyStudio', 'wide' => true,
    'scripts' => ['dataJs/sl/notify_studio.js']], sl_notifyStudioData($user, $core));
