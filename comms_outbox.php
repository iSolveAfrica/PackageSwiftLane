<?php
/**
 * Outbox (Communications): every message that went out, is waiting, was
 * blocked or skipped, or came in from a customer - status triggers, old-screen
 * sends, campaigns and broadcasts, staff messages, inbox replies, King, the
 * Support desk and delivery-request notices - with filters, a detail drawer
 * (full message, provider response, attempts, cost, opens and clicks, links),
 * retry / cancel / send again, King's "Explain this failure" and CSV export.
 * Replaces the Communication Center Outbox tab and Message Logs (message_logs.php redirects here).
 * View views/sl/pages/CommsOutbox.php, script dataJs/sl/comms_outbox.js, endpoint ajax/comms/outbox_ajax.php.
 */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
require_once 'views/sl/data/comms_outbox.php';
if (!sl_commsOutboxAllowed($user)) {
    header('Location: error403.php');
    exit;
}
sl_render('CommsOutbox', [
    'id' => 'commshub',
    'title' => 'Outbox',
    'board' => 'CommsOutbox',
    'wide' => true,
    'scripts' => ['dataJs/sl/comms_outbox.js'],
], sl_commsOutboxData($user, $core));
