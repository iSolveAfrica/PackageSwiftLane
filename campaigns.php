<?php
// Campaigns: monitor every e-mail, SMS and WhatsApp campaign (Swift Lane UI: views/sl/pages/Campaigns.php).
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['view_message_logs', 'manage_newsletter', 'push_notifications']);
require_once 'views/sl/data/campaigns.php';
sl_render('Campaigns', ['id' => 'campaigns', 'title' => 'Campaigns', 'board' => 'Campaigns', 'wide' => true], sl_campaignsData($user));
