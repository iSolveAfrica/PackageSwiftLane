<?php
/**
 * Notification Studio endpoint (views/sl/pages/NotifyStudio.php).
 *
 * POST op = save | reset | preview | test
 *   key      status:<id> | action:<name> | comms:<event>
 *   channel  email | sms | whatsapp | inapp
 *   enabled  1/0, subject, body (text channels), blocks (JSON, e-mail)
 *
 * Status and action messages are stored by helpers/notify_studio.php.
 * Communication Center automations keep their texts in cdb_comm_templates and
 * their on/off switches in the comms rules (helpers/comms.php); the studio only
 * adds the e-mail blocks so a built e-mail can be edited again.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../helpers/notify_studio.php';
require_once __DIR__ . '/../../helpers/comms.php';
require_login();
require_permission(['manage_notification_rules', 'view_tools']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}

$op = (string) ($_POST['op'] ?? '');
$key = (string) ($_POST['key'] ?? '');
$channel = (string) ($_POST['channel'] ?? '');
$uid = (int) ($_SESSION['userid'] ?? 0);
if (!cdp_notifyKeyValid($key) || !isset(cdp_notifyChannels()[$channel])) {
    sl_json(['success' => false, 'message' => 'Unknown notification or channel'], 422);
}
$isComms = strpos($key, 'comms:') === 0;
$event = $isComms ? substr($key, 6) : '';
if ($isComms) {
    if (!cdp_commEnsureTables() || !isset(cdp_commEvents()[$event])) {
        sl_json(['success' => false, 'message' => 'Unknown automation'], 422);
    }
    if ($channel === 'inapp') {
        sl_json(['success' => false, 'message' => 'Automations do not send in-app notifications'], 422);
    }
}
$blocks = cdp_notifyCleanBlocks(json_decode((string) ($_POST['blocks'] ?? '[]'), true));
$subject = trim((string) ($_POST['subject'] ?? ''));
$body = trim(str_replace("\r\n", "\n", (string) ($_POST['body'] ?? '')));
$chLabel = cdp_notifyChannels()[$channel];

// Sample values for previews and tests: the studio's own, plus the comms base
// values (prefs_url, branch...) for automations.
$sample = cdp_notifySampleVars();
if ($isComms) {
    $sample += ['branch' => 'Accra office', 'days' => '5', 'balance' => '42.00', 'currency' => 'USD', 'schedule_url' => $sample['track_url'],
        'survey_url' => $sample['track_url'], 'prefs_url' => $sample['track_url'], 'driver_name' => 'Kwame', 'driver_phone' => '+233 24 000 0000'];
}

switch ($op) {
    case 'preview':
        if ($channel === 'email') {
            sl_json(['success' => true, 'subject' => cdp_notifyRender($subject, $sample),
                'html' => cdp_notifyRender(cdp_notifyCompileBlocks($blocks), $sample, true)]);
        }
        $text = cdp_notifyRender($body, $sample);
        sl_json(['success' => true, 'text' => $text, 'chars' => mb_strlen($text)]);

    case 'test':
        if ($channel !== 'email') {
            sl_json(['success' => false, 'message' => 'Tests are sent by e-mail only, so they cost nothing.'], 422);
        }
        if (!$blocks) {
            sl_json(['success' => false, 'message' => 'Add at least one block first.'], 422);
        }
        $me = sl_row('SELECT id, fname, lname, email FROM cdb_users WHERE id = :id', [':id' => $uid]);
        if (!$me || !filter_var((string) $me->email, FILTER_VALIDATE_EMAIL)) {
            sl_json(['success' => false, 'message' => 'Your account has no valid e-mail address.'], 422);
        }
        $html = cdp_notifyRender(cdp_notifyCompileBlocks($blocks), $sample, true);
        $res = cdp_commSendEmail($me, '[Test] ' . cdp_notifyRender($subject !== '' ? $subject : 'Notification', $sample), CDP_NOTIFY_HTML_MARK . $html);
        sl_json(['success' => !empty($res['success']), 'message' => !empty($res['success']) ? 'Test sent to ' . $me->email : 'Not sent: ' . ($res['message'] ?? 'unknown error')]);

    case 'save':
    case 'reset':
        if (defined('CDP_APP_MODE_DEMO') && CDP_APP_MODE_DEMO === true) {
            sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
        }
        break;

    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}

$label = $key;
if ($isComms) {
    $label = cdp_commEvents()[$event]['label'];
} elseif (strpos($key, 'action:') === 0) {
    $label = cdp_notifyActions()[$key]['label'] ?? $key;
} else {
    $st = sl_row('SELECT mod_style FROM cdb_styles WHERE id = :id', [':id' => (int) substr($key, 7)]);
    if (!$st) {
        sl_json(['success' => false, 'message' => 'Unknown status'], 422);
    }
    $label = 'status "' . str_replace('_', ' ', (string) $st->mod_style) . '"';
}
if (strpos($key, 'action:') === 0 && !in_array($channel, cdp_notifyActions()[$key]['channels'] ?? [], true)) {
    sl_json(['success' => false, 'message' => 'This action does not send ' . $chLabel . ' messages.'], 422);
}

$log = function (string $summary) use ($key) {
    cdp_activityLog(['module' => 'notifications', 'verb' => 'update', 'action' => 'notify_studio.' . ($_POST['op'] ?? 'save'), 'outcome' => 'ok',
        'entity_type' => 'notification', 'entity_label' => $key, 'summary' => $summary]);
};

// ---- Communication Center automations ----------------------------------------
if ($isComms) {
    if (!function_exists('cdp_opsRank') || cdp_opsRank() < 3) {
        sl_json(['success' => false, 'message' => 'Only administrators change automation messages.'], 403);
    }
    $enabled = !empty($_POST['enabled']);
    if ($op === 'reset') {
        $def = cdp_commDefaultTemplates()[$event] ?? null;
        if (!$def) {
            sl_json(['success' => false, 'message' => 'This automation has no default text.'], 422);
        }
        $text = $channel === 'sms' ? $def['sms'] : ($channel === 'email' ? str_replace('*', '', $def['text']) : $def['text']);
        $r = cdp_commSaveTemplate(['event_key' => $event, 'channel' => $channel, 'subject' => $def['subject'], 'body' => $text, 'active' => 1]);
        cdp_notifyReset($key, $channel);
        if (empty($r['ok'])) {
            sl_json(['success' => false, 'message' => $r['error'] ?: 'Not saved'], 422);
        }
        $log($chLabel . ' for automation "' . $label . '" set back to the default text');
        sl_json(['success' => true, 'message' => 'Default text restored']);
    }
    if ($channel === 'email') {
        if (!$blocks || $subject === '') {
            sl_json(['success' => false, 'message' => 'The e-mail needs a subject and at least one block.'], 422);
        }
        $r = cdp_commSaveTemplate(['event_key' => $event, 'channel' => 'email', 'subject' => $subject,
            'body' => CDP_NOTIFY_HTML_MARK . cdp_notifyCompileBlocks($blocks), 'active' => 1]);
        if (!empty($r['ok'])) {
            // Keep the blocks so the e-mail opens in the builder next time.
            cdp_notifySave($key, 'email', ['enabled' => 1, 'subject' => $subject, 'blocks' => $blocks], $uid);
        }
    } else {
        $r = cdp_commSaveTemplate(['event_key' => $event, 'channel' => $channel, 'subject' => $subject, 'body' => $body, 'active' => 1]);
    }
    if (empty($r['ok'])) {
        sl_json(['success' => false, 'message' => $r['error'] ?: 'Not saved'], 422);
    }
    $rules = cdp_commRules();
    $rules[$event][$channel] = $enabled;
    cdp_commSaveRules($rules);
    $log($chLabel . ' for automation "' . $label . '" saved and switched ' . ($enabled ? 'on' : 'off'));
    sl_json(['success' => true, 'message' => 'Saved. ' . $chLabel . ' is ' . ($enabled ? 'on' : 'off') . ' for this automation.']);
}

// ---- Statuses and actions --------------------------------------------------------
if ($op === 'reset') {
    $r = cdp_notifyReset($key, $channel);
    if (empty($r['ok'])) {
        sl_json(['success' => false, 'message' => 'Not reset'], 500);
    }
    $log($chLabel . ' for ' . $label . ' set back to the built-in message');
    sl_json(['success' => true, 'message' => 'Back to the built-in message']);
}
$enabled = !empty($_POST['enabled']);
$r = cdp_notifySave($key, $channel, ['enabled' => $enabled ? 1 : 0, 'subject' => $subject, 'body' => $body, 'blocks' => $blocks], $uid);
if (empty($r['ok'])) {
    sl_json(['success' => false, 'message' => $r['error'] ?? 'Not saved'], 422);
}
$log($enabled ? $chLabel . ' for ' . $label . ' saved' : $chLabel . ' switched off for ' . $label);
sl_json(['success' => true, 'message' => $enabled ? 'Saved. This message is sent from now on.' : $chLabel . ' is switched off for this one.']);
