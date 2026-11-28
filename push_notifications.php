<?php
/** Push notifications (Broadcast): one message to many customers by WhatsApp, SMS and e-mail, with previews; ?type=invoice is the invoice push. */
require_once 'loader.php';
// Broadcast pushes moved to the Send page (Communications); the invoice push (?type=invoice, ?legacy)
// stays here, and so does the phone app's M-Push screen (views/sl/phone/Broadcast.php): the Send page
// is a desktop page.
require_once 'helpers/sl_device.php';
$pushPhone = function_exists('sl_isPhone') && sl_isPhone() && (is_file(__DIR__ . '/views/sl/phone/Broadcast.php') || is_file(__DIR__ . '/views/sl/phone/Broadcast.staff.php'));
if (($_GET['type'] ?? '') !== 'invoice' && !isset($_GET['legacy']) && !$pushPhone) {
    $q = ['mode' => 'campaign'];
    if (($_GET['type'] ?? '') === 'single_user' || (($_GET['type'] ?? '') === '' && (int) ($_GET['user_id'] ?? 0) > 0)) {
        $q = ['mode' => 'one'] + (isset($_GET['user_id']) ? ['ids' => (int) $_GET['user_id']] : []);
    } elseif (($_GET['type'] ?? '') === 'consolidation' && (int) ($_GET['consolidation_id'] ?? $_GET['id'] ?? 0) > 0) {
        // Air consolidations (cdb_consolidate) unless the link says sea (cdb_consolidate_packages).
        $sea = in_array(strtolower((string) ($_GET['shipping'] ?? $_GET['module'] ?? '')), ['sea', 'consolidate_packages', 'packages'], true);
        $q['consolidation'] = ($sea ? 'sea:' : 'air:') . (int) ($_GET['consolidation_id'] ?? $_GET['id']);
    }
    header('Location: comms_send.php?' . http_build_query($q), true, 302);
    exit;
}
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('push_notifications');
if (isset($_GET['legacy']) && !isset($_GET['type']) && !$pushPhone) {
    $_GET['type'] = 'invoice'; // old invoice-push links: the Invoice push mode of this page
}
require_once 'views/sl/data/broadcast.php';
sl_render('Broadcast', ['id' => 'bcast', 'title' => 'Push notifications', 'board' => 'Broadcast'], sl_broadcastData($user, $core));
