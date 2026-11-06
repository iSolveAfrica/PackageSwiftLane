<?php
/**
 * Message templates (SMS, WhatsApp, e-mail, default per event). JSON wrapper over the same
 * helpers/querys.php functions the legacy ajax/tools/templates_* endpoints call
 * (cdp_updateTemplatesSMSc2rbi, cdp_updateTemplatesEmail, updateTemplatesWhatsaApp,
 * addTemplatesWhatsaApp, deleteTemplateWhatsApp, updateDefaultTemplateWhatsApp); those
 * endpoints answer with HTML alerts, which the new pages cannot read.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../helpers/querys.php';
require_once __DIR__ . '/../../views/sl/data/msg_common.php';
require_login();
require_permission(['view_tools', 'list_sms_templates', 'manage_whatsapp_templates']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
if (CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
$op = (string) ($_POST['op'] ?? '');
$kind = (string) ($_POST['kind'] ?? '');
$id = (int) ($_POST['id'] ?? 0);
$name = trim((string) ($_POST['name'] ?? ''));
$subject = trim((string) ($_POST['subject'] ?? ''));
$body = (string) ($_POST['body'] ?? '');
$log = fn($a, $s, $eid, $l) => cdp_activityLog(['module' => 'templates', 'action' => $kind . '_' . $a, 'verb' => $a === 'delete' ? 'delete' : ($a === 'add' ? 'create' : 'update'), 'outcome' => 'ok', 'summary' => $s, 'entity_type' => $kind . '_template', 'entity_id' => $eid, 'entity_label' => $l]);
if ($op === 'defaults') {
    $ids = (array) ($_POST['id'] ?? []);
    $tpls = (array) ($_POST['id_template'] ?? []);
    $acts = (array) ($_POST['active'] ?? []);
    foreach ($ids as $i => $rid) {
        if (!updateDefaultTemplateWhatsApp(['id' => (int) $rid, 'id_template' => (int) ($tpls[$i] ?? 0), 'active' => (int) !empty($acts[$i])])) {
            sl_json(['success' => false, 'message' => 'Could not save row ' . ($i + 1)], 500);
        }
    }
    cdp_activityLog(['module' => 'templates', 'action' => 'defaults', 'verb' => 'update', 'outcome' => 'ok', 'summary' => 'saved default notification templates']);
    sl_json(['success' => true, 'message' => 'Default templates saved']);
}
if (!in_array($kind, ['sms', 'whatsapp', 'email'], true)) {
    sl_json(['success' => false, 'message' => 'Unknown template type'], 422);
}
$list = sl_msgTemplates($kind);
if ($op !== 'add' && !isset($list[$id])) {
    sl_json(['success' => false, 'message' => 'Template not found'], 404);
}
$errors = [];
if (in_array($op, ['save', 'add'], true)) {
    if ($name === '') $errors['name'] = 'Give the template a title';
    if (trim(strip_tags($body)) === '') $errors['body'] = 'The message cannot be empty';
    if ($kind !== 'whatsapp' && $subject === '') $errors['subject'] = 'Subject is required';
    if ($errors) sl_json(['success' => false, 'message' => reset($errors), 'errors' => $errors], 422);
}
switch ($op) {
    case 'save':
        $old = $list[$id];
        if ($kind === 'sms') $ok = cdp_updateTemplatesSMSc2rbi(['id' => $id, 'name' => cdp_sanitize($name), 'subject' => cdp_sanitize($subject), 'body' => $body, 'help' => $old['help']]);
        elseif ($kind === 'email') $ok = cdp_updateTemplatesEmail(['id' => $id, 'name' => cdp_sanitize($name), 'subject' => cdp_sanitize($subject), 'body' => $body, 'help' => $old['help']]);
        else $ok = updateTemplatesWhatsaApp(['id' => $id, 'title' => $name, 'description' => $subject !== '' ? $subject : $old['subject'], 'body' => $body]);
        if ($ok) $log('edit', "edited $kind template #$id", $id, $name);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Template saved' : 'Could not save']);
        break;
    case 'add':
        if ($kind !== 'whatsapp') sl_json(['success' => false, 'message' => 'Only WhatsApp templates can be added; SMS and e-mail templates are fixed system messages'], 422);
        $ok = addTemplatesWhatsaApp(['title' => $name, 'description' => $subject !== '' ? $subject : $name, 'body' => $body]);
        $nid = (int) sl_scalar('SELECT MAX(id) FROM whatsapp_templates');
        if ($ok) $log('add', "added WhatsApp template \"$name\"", $nid, $name);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Template added' : 'Could not add', 'redirect' => 'templates_whatsapp.php?id=' . $nid]);
        break;
    case 'delete':
        if ($kind !== 'whatsapp') sl_json(['success' => false, 'message' => 'System templates cannot be deleted'], 422);
        if ((int) sl_scalar('SELECT COUNT(*) FROM default_notification_templates WHERE id_template = :t', [':t' => $id]) > 0) {
            sl_json(['success' => false, 'message' => 'This template is in use under Default templates; pick another one there first'], 409);
        }
        $ok = deleteTemplateWhatsApp($id);
        if ($ok) $log('delete', "deleted WhatsApp template #$id", $id, $list[$id]['name']);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Template deleted' : 'Could not delete', 'redirect' => 'templates_whatsapp.php']);
        break;
    case 'test':
        require_once __DIR__ . '/../../helpers/push_notify.php';
        $me = sl_row('SELECT * FROM cdb_users WHERE id = :id', [':id' => (int) $_SESSION['userid']]);
        $text = sl_msgPreviewFill($kind === 'email' ? sl_msgPlain($body) : sl_msgPlain($body), sl_msgSample());
        $r = cdp_pushNotifyUser($me, '[TEST] ' . ($subject ?: $name), $text, cdp_getSettingsCourier(), ['source' => 'sms_test', 'source_label' => 'Template test', 'template_id' => $id], $kind);
        $st = $r[$kind]['status'];
        sl_json(['success' => $st === 'sent', 'message' => $st === 'sent' ? 'Test sent to you' : 'Test ' . $st . ($r[$kind]['detail'] ? ': ' . $r[$kind]['detail'] : '')]);
        break;
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
