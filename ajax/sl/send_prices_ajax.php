<?php
/**
 * Send the current tariff sheet to chosen customers. WhatsApp and e-mail go
 * through cdp_pushNotifyUser() and SMS through cdp_sendSms(); both write to
 * cdb_message_log (source send_prices). Capped at 50 recipients per request.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
if (CDP_APP_MODE_DEMO === true) sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
require_permission(['send_prices', 'push_notifications']);
require_once __DIR__ . '/../../helpers/push_notify.php';
@include_once __DIR__ . '/../../helpers/hubtel_sms.php';
require_once __DIR__ . '/../../views/sl/data/send_prices.php';
$ids = array_slice(array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['users'] ?? []))))), 0, 50);
$chans = array_values(array_intersect(['whatsapp', 'email', 'sms'], (array) ($_POST['channels'] ?? [])));
$subject = mb_substr(trim((string) ($_POST['subject'] ?? 'Our current shipping prices')), 0, 120);
$tpl = mb_substr(trim((string) ($_POST['message'] ?? '')), 0, 1500);
$errors = [];
if (!$ids) $errors['users[]'] = 'Tick at least one customer';
if (!$chans) $errors['channels[]'] = 'Pick a channel';
if ($tpl === '') $errors['message'] = 'Write the message';
if ($errors) sl_json(['success' => false, 'message' => reset($errors), 'errors' => $errors], 422);
$sheet = sl_tariffSheetText();
$settings = cdp_getSettingsCourier();
$batch = function_exists('cdp_msgNewBatchId') ? cdp_msgNewBatchId('sp') : null;
$ok = 0; $fail = 0;
foreach (sl_rows('SELECT * FROM cdb_users WHERE userlevel = 1 AND active = 1 AND id IN (' . implode(',', $ids) . ')') as $u) {
    $name = trim($u->fname . ' ' . $u->lname);
    $msg = str_replace(['[NAME]', '[PRICES]'], [$name, $sheet], $tpl);
    $sent = false;
    $push = array_values(array_intersect($chans, ['whatsapp', 'email']));
    if ($push) {
        $r = cdp_pushNotifyUser($u, $subject, $msg, $settings, ['source' => 'send_prices', 'source_label' => 'Send prices', 'batch_id' => $batch, 'entity_type' => 'tariff_sheet', 'entity_label' => 'Tariff sheet'], implode(',', $push));
        foreach ($push as $c) if (($r[$c]['status'] ?? '') === 'sent') $sent = true;
    }
    if (in_array('sms', $chans, true) && function_exists('cdp_sendSms') && !empty($u->phone)) {
        if (function_exists('cdp_msgSetContext')) cdp_msgSetContext(['source' => 'send_prices', 'source_label' => 'Send prices', 'batch_id' => $batch, 'recipient_user_id' => (int) $u->id, 'recipient_name' => $name]);
        try { $s = cdp_sendSms($u->phone, $msg, ['user_id' => (int) $u->id, 'name' => $name]); if (!empty($s['success']) || $s === true) $sent = true; } catch (Throwable $e) {}
        if (function_exists('cdp_msgClearContext')) cdp_msgClearContext();
    }
    $sent ? $ok++ : $fail++;
}
cdp_activityLog(['module' => 'messaging', 'action' => 'send_prices', 'action_label' => 'Sent prices', 'verb' => 'other', 'outcome' => $fail && !$ok ? 'fail' : 'ok', 'summary' => 'sent the tariff sheet to ' . ($ok + $fail) . ' customers by ' . implode('+', $chans) . " ($ok delivered)", 'entity_type' => 'tariff_sheet']);
sl_json(['success' => $ok > 0 || $fail === 0, 'message' => "Sent to $ok customer" . ($ok === 1 ? '' : 's') . ($fail ? " · $fail failed (see Message Logs)" : ''), 'reload' => true]);
