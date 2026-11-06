<?php
/** Message Costs: what every SMS, WhatsApp and e-mail costs (helpers/message_cost.php). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_message_logs');
require_once 'views/sl/data/message_costs.php';
sl_render('MessageCosts', ['id' => 'messages', 'title' => 'Message Costs', 'board' => 'MessageCosts'], sl_messageCostsData($user, $core));
