<?php
/** Notifications page: mark one / all read, save delivery preferences (own user only). */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/querys.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
$uid = (int) $_SESSION['userid'];
$op = (string) ($_POST['op'] ?? '');
switch ($op) {
    case 'read':
        $ok = cdp_updateNotificationRead($uid, (int) ($_POST['id'] ?? 0));
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Marked as read' : 'Could not update']);
        break;
    case 'read_all':
        $ok = cdp_updateNotificatonsRea($uid, (int) ($_SESSION['userlevel'] ?? 0) === 9);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'All notifications marked as read' : 'Could not update']);
        break;
    case 'pref':
        if (!sl_tableExists('cdb_user_notification_prefs')) {
            sl_json(['success' => false, 'message' => 'Preferences need the database upgrade'], 409);
        }
        $channel = preg_replace('/[^a-z]/', '', (string) ($_POST['channel'] ?? 'inapp'));
        $key = preg_replace('/[^a-z_]/', '', (string) ($_POST['key'] ?? ''));
        if ($key === '' || $channel === '') {
            sl_json(['success' => false, 'message' => 'Unknown preference'], 422);
        }
        $db = new Conexion();
        $db->cdp_query('INSERT INTO cdb_user_notification_prefs (user_id, channel, event_key, enabled, updated_at) VALUES (:u, :c, :k, :e, NOW()) ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), updated_at = VALUES(updated_at)');
        $db->bind(':u', $uid); $db->bind(':c', $channel); $db->bind(':k', $key); $db->bind(':e', (int) !empty($_POST['value']));
        $ok = $db->cdp_execute();
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Preference saved' : 'Could not save']);
        break;
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
