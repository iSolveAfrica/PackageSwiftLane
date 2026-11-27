<?php
// Campaigns: monitor every e-mail, SMS and WhatsApp campaign (Swift Lane UI: views/sl/pages/Campaigns.php).
require_once 'loader.php';
// The campaign list lives on the Send page; a report (?batch=) and the bulk-send overview (?all=1) stay here.
if (!isset($_GET['batch']) && !isset($_GET['all']) && !isset($_GET['days']) && !isset($_GET['ch']) && !isset($_GET['q'])) {
    header('Location: comms_send.php#campaigns', true, 302);
    exit;
}
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['view_message_logs', 'manage_newsletter', 'push_notifications']);
require_once 'views/sl/data/campaigns.php';
sl_render('Campaigns', ['id' => 'campaigns', 'title' => 'Campaigns', 'board' => 'Campaigns', 'wide' => true], sl_campaignsData($user));
