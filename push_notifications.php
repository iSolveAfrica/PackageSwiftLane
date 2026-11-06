<?php
/** Push notifications (Broadcast): one message to many customers by WhatsApp, SMS and e-mail, with previews; ?type=invoice is the invoice push. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('push_notifications');
if (isset($_GET['legacy']) && !isset($_GET['type'])) {
    $_GET['type'] = 'invoice'; // old invoice-push links: the Invoice push mode of this page
}
require_once 'views/sl/data/broadcast.php';
sl_render('Broadcast', ['id' => 'bcast', 'title' => 'Push notifications', 'board' => 'Broadcast'], sl_broadcastData($user, $core));
