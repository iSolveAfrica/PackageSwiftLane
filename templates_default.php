<?php
/** SMS and WhatsApp templates page, default tab (MessageTemplates artboard). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_tools');
require_once 'helpers/push_compose.php';
require_once 'views/sl/data/message_templates.php';
sl_render('MessageTemplates', ['id' => 'msgtpl', 'title' => 'Message templates', 'board' => 'MessageTemplates'], sl_messageTemplatesData($user, $core, 'default'));
