<?php
// Campaigns: monitor every e-mail, SMS and WhatsApp campaign (Swift Lane UI: views/sl/pages/Campaigns.php).
require_once 'loader.php';
// The campaign list lives on the Send page; a report (?batch=) and the bulk-send overview (?all=1) stay here.
$user = new User();
$core = new Core();
if (!isset($_GET['batch']) && !isset($_GET['all']) && !isset($_GET['days']) && !isset($_GET['ch']) && !isset($_GET['q'])) {
    // Staff who may only read the message logs keep the read-only overview here.
    $user->cdp_getUserPermissions();
    $canCampaign = $user->cdp_hasPermission(['push_notifications', 'manage_newsletter']);
    header('Location: ' . ($canCampaign ? 'comms_send.php#campaigns' : 'campaigns.php?all=1'), true, 302);
    exit;
}
require_once 'helpers/sl_view.php';
sl_gate(['view_message_logs', 'manage_newsletter', 'push_notifications']);
require_once 'views/sl/data/campaigns.php';
sl_render('Campaigns', ['id' => 'campaigns', 'title' => 'Campaigns', 'board' => 'Campaigns', 'wide' => true], sl_campaignsData($user));
