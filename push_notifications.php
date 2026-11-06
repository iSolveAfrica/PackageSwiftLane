<?php
/** Push notifications (Broadcast): one message to many customers by WhatsApp, SMS and e-mail, with previews. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('push_notifications');
if (isset($_GET['legacy'])) {
    // The invoice push (per-customer invoice rows) still lives on the previous screen.
    include 'views/tools/push_notifications.php';
    exit;
}
require_once 'views/sl/data/broadcast.php';
sl_render('Broadcast', ['id' => 'bcast', 'title' => 'Push notifications', 'board' => 'Broadcast'], sl_broadcastData($user, $core));
