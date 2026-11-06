<?php
/** Notification Center: switch an event's channel on/off, save a template, send a test. */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../helpers/querys.php';
require_once __DIR__ . '/../../views/sl/data/msg_common.php';
require_login();
require_permission(['manage_notification_rules', 'view_tools']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
if (CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
$op = (string) ($_POST['op'] ?? '');
$event = (string) ($_POST['event'] ?? '');
$channel = (string) ($_POST['channel'] ?? '');
$events = sl_msgEvents();
if (!isset($events[$event]) || !in_array($channel, ['email', 'sms', 'whatsapp'], true)) {
    sl_json(['success' => false, 'message' => 'Unknown event or channel'], 422);
}
$db = new Conexion();
$actor = (int) $_SESSION['userid'];
$name = $events[$event]['name'];
$chLabel = ['email' => 'E-mail', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'][$channel];
switch ($op) {
    case 'toggle':
        if (!sl_tableExists('cdb_notification_rules')) {
            sl_json(['success' => false, 'message' => 'Run the database upgrade (2026_11_06_messaging_settings) first'], 409);
        }
        $on = (int) !empty($_POST['value']);
        $db->cdp_query('INSERT INTO cdb_notification_rules (event_key, channel, enabled, updated_by, updated_at) VALUES (:e, :c, :on, :u, NOW())
            ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)');
        $db->bind(':e', $event); $db->bind(':c', $channel); $db->bind(':on', $on); $db->bind(':u', $actor);
        $ok = $db->cdp_execute();
        if ($ok) {
            cdp_activityLog(['module' => 'notifications', 'action' => 'rule', 'verb' => 'update', 'outcome' => 'ok', 'summary' => "$chLabel for \"$name\" switched " . ($on ? 'on' : 'off'), 'entity_type' => 'notification_rule', 'entity_id' => $event, 'entity_label' => $name]);
        }
        sl_json(['success' => (bool) $ok, 'message' => $ok ? "$chLabel " . ($on ? 'on' : 'off') . " for $name" : 'Could not save']);
        break;
    case 'save':
        $tplId = (int) ($_POST['template_id'] ?? 0);
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $body = (string) ($_POST['body'] ?? '');
        if ($tplId <= 0) {
            sl_json(['success' => false, 'message' => 'Pick the template this channel uses', 'errors' => ['template_id' => 'Required']], 422);
        }
        if (trim(strip_tags($body)) === '') {
            sl_json(['success' => false, 'message' => 'The message cannot be empty', 'errors' => ['body' => 'Required']], 422);
        }
        $templates = sl_msgTemplates($channel);
        if (!isset($templates[$tplId])) {
            sl_json(['success' => false, 'message' => 'Template not found'], 404);
        }
        $old = $templates[$tplId];
        if ($channel === 'email') {
            $ok = cdp_updateTemplatesEmail(['id' => $tplId, 'name' => $old['name'], 'subject' => $subject !== '' ? $subject : $old['subject'], 'body' => $body, 'help' => $old['help']]);
        } elseif ($channel === 'sms') {
            $ok = cdp_updateTemplatesSMSc2rbi(['id' => $tplId, 'name' => $old['name'], 'subject' => $subject !== '' ? $subject : $old['subject'], 'body' => $body, 'help' => $old['help']]);
        } else {
            $ok = updateTemplatesWhatsaApp(['id' => $tplId, 'title' => $old['name'], 'description' => $subject !== '' ? $subject : $old['subject'], 'body' => $body]);
        }
        if ($ok && sl_tableExists('cdb_notification_rules')) {
            $db->cdp_query('INSERT INTO cdb_notification_rules (event_key, channel, enabled, template_id, updated_by, updated_at) VALUES (:e, :c, 1, :t, :u, NOW())
                ON DUPLICATE KEY UPDATE template_id = VALUES(template_id), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)');
            $db->bind(':e', $event); $db->bind(':c', $channel); $db->bind(':t', $tplId); $db->bind(':u', $actor);
            $db->cdp_execute();
        }
        if ($ok) {
            cdp_activityLog(['module' => 'notifications', 'action' => 'template', 'verb' => 'update', 'outcome' => 'ok', 'summary' => "edited $chLabel template #$tplId for \"$name\"", 'entity_type' => 'template', 'entity_id' => $tplId, 'entity_label' => $old['name']]);
        }
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Template saved' : 'Could not save']);
        break;
    case 'test':
        require_once __DIR__ . '/../../helpers/push_notify.php';
        $me = sl_row('SELECT * FROM cdb_users WHERE id = :id', [':id' => $actor]);
        $body = (string) ($_POST['body'] ?? '');
        $subject = trim((string) ($_POST['subject'] ?? '')) ?: $name;
        $sample = sl_msgSample();
        $text = sl_msgPreviewFill($channel === 'email' ? sl_msgPlain($body) : $body, $sample);
        if (trim($text) === '') {
            sl_json(['success' => false, 'message' => 'Nothing to send'], 422);
        }
        $r = cdp_pushNotifyUser($me, '[TEST] ' . $subject, $text, cdp_getSettingsCourier(), ['source' => 'sms_test', 'source_label' => 'Notification Center test', 'entity_type' => 'notification_rule', 'entity_id' => $event, 'entity_label' => $name], $channel);
        $st = $r[$channel]['status'];
        sl_json(['success' => $st === 'sent', 'message' => $st === 'sent' ? "Test $chLabel sent to you" : "$chLabel $st" . ($r[$channel]['detail'] ? ': ' . $r[$channel]['detail'] : '')]);
        break;
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
