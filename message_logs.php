<?php
/** Message Logs: every e-mail, SMS and WhatsApp the system sent (cdb_message_log). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_message_logs');
require_once 'views/sl/data/message_logs.php';
sl_render('MessageLogs', ['id' => 'messages', 'title' => 'Message Logs', 'board' => 'MessageLogs'], sl_messageLogsData($user, $core));
