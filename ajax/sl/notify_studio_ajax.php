<?php
/**
 * Notification Studio endpoint (views/sl/pages/NotifyStudio.php).
 *
 * POST op = save | reset | preview | test | ai | drafts
 *   key          status:<id> | action:<name> | comms:<event>
 *   channel      email | sms | whatsapp | inapp   (op ai also takes "all")
 *   enabled      1/0, subject, body (text channels), blocks (JSON, e-mail)
 *   op ai        tone (cdp_notifyAiTones), instruction (optional, short): asks the
 *                AI assistant (King) to rewrite the message and returns a
 *                PROPOSAL per channel. Nothing is saved; the studio shows it
 *                and the administrator accepts, retries or discards it.
 *   op drafts    on = 1/0: the studio switch "Status-specific messages". It
 *                changes what every status sends to every customer, so it
 *                needs an administrator (cdp_opsRank() >= 3), like automations.
 *
 * Status and action messages are stored by helpers/notify_studio.php; the
 * drafted message of every status comes from helpers/notify_drafts.php.
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
$uid = (int) ($_SESSION['userid'] ?? 0);
$demo = defined('CDP_APP_MODE_DEMO') && CDP_APP_MODE_DEMO === true;

// ---- the global switch: drafted status messages on / old shared messages -------
if ($op === 'drafts') {
    if ($demo) {
        sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
    }
    if (!function_exists('cdp_opsRank') || cdp_opsRank() < 3) {
        sl_json(['success' => false, 'message' => 'Only administrators switch the status messages for every customer.'], 403);
    }
    $on = !empty($_POST['on']);
    if (!cdp_notifyDraftsSet($on, $uid)) {
        sl_json(['success' => false, 'message' => 'Not saved. Please try again.'], 500);
    }
    cdp_activityLog(['module' => 'notifications', 'verb' => 'update', 'action' => 'notify_studio.drafts', 'outcome' => 'ok',
        'entity_type' => 'notification', 'entity_label' => 'status messages',
        'summary' => $on ? 'Status-specific messages switched on' : 'Status messages set back to the old shared messages']);
    sl_json(['success' => true, 'on' => $on, 'message' => $on
        ? 'Status-specific messages are on: every status sends its own drafted message unless you saved your own.'
        : 'Old shared messages are back: statuses without a saved message send the shared built-in message again.']);
}

$key = (string) ($_POST['key'] ?? '');
$channel = (string) ($_POST['channel'] ?? '');
$channelOk = isset(cdp_notifyChannels()[$channel]) || ($op === 'ai' && $channel === 'all');
if (!cdp_notifyKeyValid($key) || !$channelOk) {
    sl_json(['success' => false, 'message' => 'Unknown notification or channel'], 422);
}
$isComms = strpos($key, 'comms:') === 0;
$isStatus = strpos($key, 'status:') === 0;
$event = $isComms ? substr($key, 6) : '';
if ($isComms) {
    if (!cdp_commEnsureTables() || !isset(cdp_commEvents()[$event])) {
        sl_json(['success' => false, 'message' => 'Unknown automation'], 422);
    }
    if ($channel === 'inapp') {
        sl_json(['success' => false, 'message' => 'Automations do not send in-app notifications'], 422);
    }
}
$statusName = '';
if ($isStatus) {
    $statusName = cdp_notifyStatusName((int) substr($key, 7));
    if ($statusName === '') {
        sl_json(['success' => false, 'message' => 'Unknown status'], 422);
    }
}
$blocks = cdp_notifyCleanBlocks(json_decode((string) ($_POST['blocks'] ?? '[]'), true));
$subject = trim((string) ($_POST['subject'] ?? ''));
$body = trim(str_replace("\r\n", "\n", (string) ($_POST['body'] ?? '')));
$chLabel = $channel === 'all' ? 'All channels' : cdp_notifyChannels()[$channel];

// Sample values for previews and tests: fitted to the status, plus the comms base
// values (prefs_url, branch...) for automations.
$sample = cdp_notifySampleVars($isStatus ? $statusName : null);
if ($isComms) {
    $sample = cdp_notifyCommsSampleVars($event, $sample);
}

switch ($op) {
    case 'preview':
        if ($channel === 'email') {
            sl_json(['success' => true, 'subject' => cdp_notifyRender(cdp_notifyPruneLines($subject, $sample), $sample),
                'html' => cdp_notifyRender(cdp_notifyCompileBlocks($blocks, $sample), $sample, true)]);
        }
        $text = trim(cdp_notifyRender(cdp_notifyPruneLines($body, $sample), $sample));
        sl_json(['success' => true, 'text' => $text, 'chars' => mb_strlen($text), 'parts' => $channel === 'sms' ? cdp_notifySmsParts($text) : 0]);

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
        $html = cdp_notifyRender(cdp_notifyCompileBlocks($blocks, $sample), $sample, true);
        $res = cdp_commSendEmail($me, '[Test] ' . cdp_notifyRender($subject !== '' ? $subject : 'Notification', $sample), CDP_NOTIFY_HTML_MARK . $html);
        sl_json(['success' => !empty($res['success']), 'message' => !empty($res['success']) ? 'Test sent to ' . $me->email : 'Not sent: ' . ($res['message'] ?? 'unknown error')]);

    case 'ai':
        notify_studio_ai($key, $channel, $isComms, $event, $subject, $body, $blocks, $sample, $uid);
        // notify_studio_ai always answers

    case 'save':
    case 'reset':
        if ($demo) {
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
    $label = 'status "' . str_replace('_', ' ', $statusName) . '"';
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
    $drafted = $isStatus && cdp_notifyDraftsOn();
    $log($chLabel . ' for ' . $label . ' set back to the ' . ($drafted ? 'drafted' : 'built-in') . ' message');
    sl_json(['success' => true, 'message' => $drafted ? 'Back to the message drafted for this status' : 'Back to the built-in message']);
}
$enabled = !empty($_POST['enabled']);
$r = cdp_notifySave($key, $channel, ['enabled' => $enabled ? 1 : 0, 'subject' => $subject, 'body' => $body, 'blocks' => $blocks], $uid);
if (empty($r['ok'])) {
    sl_json(['success' => false, 'message' => $r['error'] ?? 'Not saved'], 422);
}
$log($enabled ? $chLabel . ' for ' . $label . ' saved' . (!empty($_POST['via_ai']) ? ' (written with ' . cdp_kingName() . ')' : '') : $chLabel . ' switched off for ' . $label);
sl_json(['success' => true, 'message' => $enabled ? 'Saved. This message is sent from now on.' : $chLabel . ' is switched off for this one.']);

/**
 * op ai: ask the AI assistant for a rewrite of one channel (or all) and answer
 * with checked proposals. Same permission as saving; automations need an
 * administrator, like saving them. Never saves.
 */
function notify_studio_ai(string $key, string $channel, bool $isComms, string $event, string $subject, string $body, array $blocks, array $sample, int $uid): void
{
    global $user, $core;
    require_once __DIR__ . '/../../helpers/ai_assistant.php';
    $king = function_exists('cdp_kingName') ? cdp_kingName() : 'King';
    $setup = function_exists('sl_route') ? sl_route('AiCenter') : 'ai_center.php';
    if (!cdp_aiConfigured()) {
        sl_json(['success' => false, 'code' => 'not_configured', 'setup_url' => $setup,
            'message' => $king . ' is not set up yet. A super admin switches it on and adds an AI provider key in the AI centre.']);
    }
    if (!cdp_aiCanUse($user)) {
        sl_json(['success' => false, 'code' => 'not_allowed', 'setup_url' => $setup,
            'message' => 'Your role cannot use ' . $king . '. The AI centre sets who may use it.']);
    }
    if ($isComms && (!function_exists('cdp_opsRank') || cdp_opsRank() < 3)) {
        sl_json(['success' => false, 'message' => 'Only administrators change automation messages.'], 403);
    }
    $comms = [];
    if ($isComms) {
        $ev = cdp_commEvents()[$event];
        $def = cdp_commDefaultTemplates()[$event] ?? [];
        $comms = ['label' => $ev['label'], 'help' => $ev['help'], 'vars' => cdp_commPlaceholders(), 'example' => (string) ($def['text'] ?? '')];
    }
    $ctx = cdp_notifyAiContext($key, $comms);
    $channels = $channel === 'all' ? $ctx['channels'] : [$channel];
    if (!$channels || array_diff($channels, $ctx['channels'])) {
        sl_json(['success' => false, 'message' => 'This message is not sent on ' . ($channel === 'all' ? 'any channel' : cdp_notifyChannels()[$channel]) . '.'], 422);
    }

    // What the editor holds now: the posted text for one channel, the stored or drafted text for all.
    $current = [];
    if ($channel !== 'all') {
        $current[$channel] = ['subject' => $subject, 'body' => $body, 'blocks' => $blocks];
    } else {
        $rows = $isComms ? [] : cdp_notifyGet($key);
        foreach ($channels as $ch) {
            if ($isComms) {
                $tpl = cdp_commTemplate($event, $ch);
                $text = (string) ($tpl->body ?? '');
                $saved = $ch === 'email' ? cdp_notifyGet($key)['email'] ?? null : null;
                $bl = $saved && $saved->blocks ? json_decode((string) $saved->blocks, true) : null;
                $current[$ch] = ['subject' => (string) ($tpl->subject ?? ''), 'body' => strpos($text, CDP_NOTIFY_HTML_MARK) === 0 ? '' : $text,
                    'blocks' => is_array($bl) && $bl ? $bl : [['type' => 'text', 'text' => strpos($text, CDP_NOTIFY_HTML_MARK) === 0 ? '' : str_replace('*', '', $text)]]];
                continue;
            }
            $r = $rows[$ch] ?? null;
            $def = cdp_notifyDefault($key, $ch);
            $bl = $r && $r->blocks ? json_decode((string) $r->blocks, true) : null;
            $current[$ch] = [
                'subject' => $r && (int) $r->enabled === 1 && $r->subject !== null ? (string) $r->subject : $def['subject'],
                'body' => $r && (int) $r->enabled === 1 && $ch !== 'email' ? (string) $r->body : $def['body'],
                'blocks' => is_array($bl) && $bl ? $bl : $def['blocks'],
            ];
        }
    }
    $tones = cdp_notifyAiTones();
    $tone = (string) ($_POST['tone'] ?? 'keep');
    $tone = isset($tones[$tone]) ? $tone : 'keep';
    $instruction = mb_substr(trim((string) ($_POST['instruction'] ?? '')), 0, 400);
    $company = trim((string) ($core->site_name ?? '')) ?: 'Swift Lane';
    [$system, $prompt] = cdp_notifyAiPrompt($ctx, $channels, $current, $tone, $instruction, $king, $company);

    require_once __DIR__ . '/../../helpers/ai_assistant_llm.php';
    $res = cdp_aiComplete($system, $prompt, null, null, null, $channel === 'all' ? 6000 : 3000);
    if (empty($res['ok'])) {
        sl_json(['success' => false, 'code' => 'provider', 'setup_url' => $setup,
            'message' => $king . ' could not answer: ' . trim((string) ($res['error'] ?? 'unknown error'))]);
    }
    $parsed = cdp_notifyAiParse((string) $res['text'], $ctx, $channels, $sample);
    if (empty($parsed['ok'])) {
        sl_json(['success' => false, 'code' => 'invalid', 'message' => $king . ': ' . $parsed['error']]);
    }
    $proposals = $parsed['proposals'];

    // An SMS that is still too long gets one more, SMS-only, try. Not when
    // writing every channel (the bulk redraft): one request per status, and
    // the proposal already warns about the length.
    if ($channel === 'sms' && isset($proposals['sms']) && ($proposals['sms']['chars'] ?? 0) > 160) {
        $retry = cdp_aiComplete($system, "This SMS is " . $proposals['sms']['chars'] . " characters once the placeholders are filled; the limit is 160.\n"
            . "Rewrite it to at most 150 characters with values filled, keeping what matters for the event \"" . $ctx['label'] . "\". Keep the same placeholders rules.\n"
            . "SMS: " . json_encode($proposals['sms']['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\nReply with exactly: {\"text\": \"...\"}", null, null, null, 1000);
        if (!empty($retry['ok'])) {
            $again = cdp_notifyAiParse((string) $retry['text'], $ctx, ['sms'], $sample);
            if (!empty($again['ok']) && $again['proposals']['sms']['chars'] < $proposals['sms']['chars']) {
                $proposals['sms'] = $again['proposals']['sms'];
            }
        }
    }
    cdp_activityLog(['module' => 'notifications', 'verb' => 'other', 'action' => 'notify_studio.ai', 'outcome' => 'ok',
        'entity_type' => 'notification', 'entity_label' => $key,
        'summary' => $king . ' proposed new text for ' . ($channel === 'all' ? 'every channel' : cdp_notifyChannels()[$channel]) . ' of ' . $ctx['label'] . ' (not saved)']);
    sl_json(['success' => true, 'proposals' => $proposals, 'model' => (string) ($res['model'] ?? ''), 'assistant' => $king]);
}
