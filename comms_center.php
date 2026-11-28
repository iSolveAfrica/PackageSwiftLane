<?php
// Old Communication Center address. Its sections became their own pages in
// Notifications & Messages; the part after # (or ?tab=) chooses which one,
// so old links and bookmarks keep working (views/sl/pages/CommsCenter.php
// sends the browser on). See docs/COMMUNICATIONS.md.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
require_once 'helpers/ops_db.php';
// The Overview's gate: each target page then applies its own (e.g. Outbox for view_message_logs).
if (cdp_opsRank($user) < 2 && !$user->cdp_hasPermission(['push_notifications', 'manage_newsletter', 'view_message_logs'])) {
    header('Location: error403.php');
    exit;
}
$map = [
    'overview' => 'CommsHub', 'deliveries' => 'CommsHub',
    'automations' => 'CommsAutomations', 'templates' => 'CommsStatuses',
    'send' => 'CommsSend', 'campaigns' => 'CommsSend', 'segments' => 'CommsSend',
    'outbox' => 'CommsOutbox', 'customers' => 'CommsCustomers', 'surveys' => 'CommsSurveys',
    'settings' => 'CommsSettings', 'inbox' => 'CommsInbox',
];
$targets = [];
foreach ($map as $hash => $board) {
    $targets[$hash] = sl_route($board, $hash === 'campaigns' || $hash === 'segments' ? ['mode' => 'campaign'] : []) . ($hash === 'campaigns' ? '#campaigns' : '');
}
// ?tab=… works without JavaScript.
$tab = (string) ($_GET['tab'] ?? '');
if ($tab !== '' && isset($targets[$tab])) {
    header('Location: ' . $targets[$tab]);
    exit;
}
sl_render('CommsCenter', ['id' => 'commshub', 'title' => 'Notifications & Messages', 'board' => ''], ['targets' => $targets, 'home' => $targets['overview']]);
