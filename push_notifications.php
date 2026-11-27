<?php
/** Push notifications (Broadcast): one message to many customers by WhatsApp, SMS and e-mail, with previews; ?type=invoice is the invoice push. */
require_once 'loader.php';
// Broadcast pushes moved to the Send page (Communications); the invoice push (?type=invoice, ?legacy) stays here.
if (($_GET['type'] ?? '') !== 'invoice' && !isset($_GET['legacy'])) {
    $q = ['mode' => 'campaign'];
    if (($_GET['type'] ?? '') === 'single_user') {
        $q = ['mode' => 'one'] + (isset($_GET['user_id']) ? ['ids' => (int) $_GET['user_id']] : []);
    } elseif (($_GET['type'] ?? '') === 'consolidation' && (int) ($_GET['consolidation_id'] ?? $_GET['id'] ?? 0) > 0) {
        $q['consolidation'] = 'air:' . (int) ($_GET['consolidation_id'] ?? $_GET['id']);
    }
    header('Location: comms_send.php?' . http_build_query($q), true, 302);
    exit;
}
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('push_notifications');
if (isset($_GET['legacy']) && !isset($_GET['type'])) {
    $_GET['type'] = 'invoice'; // old invoice-push links: the Invoice push mode of this page
}
require_once 'views/sl/data/broadcast.php';
sl_render('Broadcast', ['id' => 'bcast', 'title' => 'Push notifications', 'board' => 'Broadcast'], sl_broadcastData($user, $core));
