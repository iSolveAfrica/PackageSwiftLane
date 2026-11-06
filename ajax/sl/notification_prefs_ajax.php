<?php
/**
 * Customer notification preferences (CustomerNotifications board).
 * Writes cdb_user_notification_prefs (per channel × event; event '*' = channel master switch),
 * cdb_user_preferences (quiet hours, language, digest, promotions, pause) and cdb_users.newsletter.
 * Only ever changes the signed-in user's own rows.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/sl_auth.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
if (CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
if (!sl_tableExists('cdb_user_notification_prefs')) {
    sl_json(['success' => false, 'message' => 'Notification settings need the database upgrade (migrations/2026_11_06_auth_portal.sql).'], 409);
}
$uid = (int) $_SESSION['userid'];
$op = (string) ($_POST['op'] ?? '');
$channels = ['whatsapp', 'sms', 'email'];
$events = sl_notificationEvents();
$db = new Conexion();

$set = function (string $channel, string $event, int $on) use ($db, $uid) {
    $db->cdp_query('INSERT INTO cdb_user_notification_prefs (user_id, channel, event_key, enabled, updated_at) VALUES (:u, :c, :e, :v, NOW())
        ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), updated_at = NOW()');
    $db->bind(':u', $uid);
    $db->bind(':c', $channel);
    $db->bind(':e', $event);
    $db->bind(':v', $on);
    return (bool) $db->cdp_execute();
};
$log = function (string $summary) use ($uid) {
    cdp_activityLog(['module' => 'notifications', 'action' => 'prefs.update', 'action_label' => 'Notification settings changed', 'verb' => 'update', 'outcome' => 'ok', 'summary' => $summary, 'entity_type' => 'user', 'entity_id' => $uid]);
};
$on = (int) !empty($_POST['value']);

switch ($op) {
    case 'channel':
        $ch = (string) ($_POST['channel'] ?? '');
        if (!in_array($ch, $channels, true)) {
            sl_json(['success' => false, 'message' => 'Unknown channel'], 422);
        }
        $set($ch, '*', $on);
        $log(($on ? 'Turned on ' : 'Turned off ') . $ch);
        sl_json(['success' => true, 'message' => ucfirst($ch === 'sms' ? 'SMS' : $ch) . ($on ? ' turned on' : ' turned off'), 'reload' => true]);
        break;
    case 'event':
        $ch = (string) ($_POST['channel'] ?? '');
        $ev = (string) ($_POST['event'] ?? '');
        if (!in_array($ch, $channels, true) || !isset($events[$ev])) {
            sl_json(['success' => false, 'message' => 'Unknown setting'], 422);
        }
        $set($ch, $ev, $on);
        $log($events[$ev][0] . ' by ' . $ch . ': ' . ($on ? 'on' : 'off'));
        sl_json(['success' => true, 'message' => 'Saved. ' . $events[$ev][0] . ' updated.']);
        break;
    case 'preset':
        $p = (string) ($_POST['preset'] ?? '');
        if (!in_array($p, ['all', 'key'], true)) {
            sl_json(['success' => false, 'message' => 'Unknown preset'], 422);
        }
        $important = ['invoice', 'ready', 'delivery'];
        foreach ($events as $key => $e) {
            foreach (['whatsapp', 'sms', 'email'] as $ch) {
                $v = $p === 'all' ? 1 : ($ch === 'whatsapp' ? 1 : (in_array($key, $important, true) ? 1 : 0));
                $set($ch, $key, $v);
            }
        }
        $log('Preset: ' . $p);
        sl_json(['success' => true, 'message' => 'Saved.', 'reload' => true]);
        break;
    case 'pref':
        $key = (string) ($_POST['key'] ?? '');
        $allowed = [
            'notif_quiet' => fn($v) => $v === '1' || $v === '0',
            'notif_quiet_from' => fn($v) => (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v),
            'notif_quiet_to' => fn($v) => (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v),
            'notif_lang' => fn($v) => in_array($v, ['en', 'tw', 'fr'], true),
            'notif_digest' => fn($v) => in_array($v, ['week', 'month', 'off'], true),
            'notif_promo' => fn($v) => $v === '1' || $v === '0',
            'notif_paused' => fn($v) => $v === '1' || $v === '0',
        ];
        $val = trim((string) ($_POST['value'] ?? ''));
        if (!isset($allowed[$key]) || !$allowed[$key]($val)) {
            sl_json(['success' => false, 'message' => 'That value is not allowed', 'errors' => [$key => 'Invalid value']], 422);
        }
        sl_prefSet($uid, $key, $val);
        $log($key . ' = ' . $val);
        sl_json(['success' => true, 'message' => $key === 'notif_paused' ? ($val === '1' ? 'All messages paused (security codes still arrive)' : 'Messages resumed') : 'Saved.', 'reload' => $key === 'notif_paused']);
        break;
    case 'quiet':
        $from = (string) ($_POST['from'] ?? '');
        $to = (string) ($_POST['to'] ?? '');
        $re = '/^([01]\d|2[0-3]):[0-5]\d$/';
        if (!preg_match($re, $from) || !preg_match($re, $to)) {
            sl_json(['success' => false, 'message' => 'Use the 24-hour format, e.g. 21:00', 'errors' => ['from' => 'HH:MM', 'to' => 'HH:MM']], 422);
        }
        sl_prefSet($uid, 'notif_quiet_from', $from);
        sl_prefSet($uid, 'notif_quiet_to', $to);
        $log("Quiet hours $from-$to");
        sl_json(['success' => true, 'message' => "Quiet hours $from to $to saved."]);
        break;
    case 'newsletter':
        $db->cdp_query('UPDATE cdb_users SET newsletter = :n WHERE id = :u');
        $db->bind(':n', $on);
        $db->bind(':u', $uid);
        $db->cdp_execute();
        $log('Newsletter ' . ($on ? 'on' : 'off'));
        sl_json(['success' => true, 'message' => $on ? 'Subscribed to the newsletter' : 'Unsubscribed from the newsletter']);
        break;
    case 'test':
        // An in-app test message (bell) — no SMS/WhatsApp cost, nothing pretends to be delivered elsewhere.
        $db->cdp_query("INSERT INTO cdb_notifications (user_id, order_id, notification_description, shipping_type, notification_date) VALUES (:u, 0, :t, '0', NOW())");
        $db->bind(':u', $uid);
        $db->bind(':t', 'Test message: your notification settings work. You will get parcel updates on the channels you chose.');
        $ok = $db->cdp_execute();
        $nid = (int) $db->dbh->lastInsertId();
        if ($ok && $nid) {
            $db->cdp_query('INSERT INTO cdb_notifications_users (user_id, notification_id, notification_read, notification_status) VALUES (:u, :n, 0, 1)');
            $db->bind(':u', $uid);
            $db->bind(':n', $nid);
            $db->cdp_execute();
        }
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Test message sent to your bell (top right).' : 'Could not send a test message', 'reload' => (bool) $ok]);
        break;
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
