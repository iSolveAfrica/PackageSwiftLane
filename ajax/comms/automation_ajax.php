<?php
/**
 * Communications > Automations and Status messages endpoint
 * (comms_automations.php, comms_statuses.php; dataJs/sl/comms_automations.js).
 *
 * POST only (CSRF checked by require_login). action =
 *   template_get    key, language                  the editor's values for a status or event key
 *   template_save   key, channel, language, enabled (1/0, optional), subject, body,
 *                   e-mail: html + design_json (canvas) or blocks (studio JSON from King),
 *                   wa_template_name / _lang / _params (WhatsApp Cloud), via_ai
 *   template_reset  key, channel, language         back to the predefined text
 *   save_rule       key, channel, value            one trigger channel on/off (sl-toggle data-sl-save)
 *   master          value                          automations master switch (administrators)
 *   drafts          value                          predefined status messages switch (administrators)
 *   inapp_save      key (status:N), body, enabled  the in-app bell line of a status
 *   inapp_reset     key
 *   test_send       key, channel, subject, body, html   a test to yourself (e-mail, or your own phone)
 *   ai              key, channel, tone, instruction, subject, body, blocks
 *                   Write with King: answers {success, proposals: {<channel>: {...}}, model, assistant}
 *                   (the shape dataJs/sl/comms_editor.js expects); never saves
 *   legacy_get      key (legacy:<source>)         the legacy template rows per channel
 *   legacy_save     key, channel, table, id, subject, body | html + design_json
 *
 * Reading needs staff (rank 2+); changing needs an administrator or the
 * manage_notification_rules permission (sl_caCanEdit). Master and predefined
 * switches need an administrator.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../helpers/querys.php';
require_once __DIR__ . '/../../helpers/message_log.php';
require_once __DIR__ . '/../../views/sl/data/comms_automations.php';
require_login();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
if (cdp_opsRank() < 2) {
    sl_json(['success' => false, 'message' => 'Staff only.'], 403);
}
if (!cdp_commEnsureTables()) {
    sl_json(['success' => false, 'message' => 'The message tables could not be created. The database user needs the CREATE privilege once.'], 500);
}

$action = (string) ($_POST['action'] ?? '');
$uid = (int) ($_SESSION['userid'] ?? 0);
$demo = defined('CDP_APP_MODE_DEMO') && CDP_APP_MODE_DEMO === true;
$P = function ($k, $d = '') { return isset($_POST[$k]) && !is_array($_POST[$k]) ? (string) $_POST[$k] : $d; };
$key = $P('key');
$channel = $P('channel');
$lang = preg_match('/^[a-z]{2}$/', $P('language', 'en')) && isset(SL_CA_LANGS[$P('language', 'en')]) ? $P('language', 'en') : 'en';
$chLabels = SL_CA_CHANNELS + ['inapp' => 'In-app'];
$reads = ['template_get', 'legacy_get', 'ai', 'test_send'];
if (!in_array($action, $reads, true)) {
    if ($demo) {
        sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
    }
    if (!sl_caCanEdit()) {
        sl_json(['success' => false, 'message' => 'Only administrators (or staff with the notification rules permission) change automations.'], 403);
    }
}
$log = function (string $summary, string $verb = 'update') use ($action, $key) {
    cdp_activityLog(['module' => 'notifications', 'verb' => $verb, 'action' => 'automations.' . $action, 'outcome' => 'ok',
        'entity_type' => 'notification', 'entity_label' => $key, 'summary' => $summary]);
};
$keyOk = function (bool $allowLegacy = false) use ($key) {
    if (!cdp_commTriggerKeyValid($key) || (!$allowLegacy && strpos($key, 'legacy:') === 0)) {
        sl_json(['success' => false, 'message' => 'Unknown message.'], 422);
    }
    if (cdp_commTriggerStatusId($key) > 0 && cdp_commStatusName(cdp_commTriggerStatusId($key)) === '') {
        sl_json(['success' => false, 'message' => 'Unknown status.'], 422);
    }
};
$chOk = function (array $allowed = ['whatsapp', 'sms', 'email']) use ($channel) {
    if (!in_array($channel, $allowed, true)) {
        sl_json(['success' => false, 'message' => 'Unknown channel.'], 422);
    }
};

switch ($action) {

    // ---------------------------------------------------------------- reads
    case 'template_get':
        $keyOk();
        if ($key === 'status:*') {
            sl_json(['success' => false, 'message' => 'Each status sends its own message: open it on Status messages.'], 422);
        }
        sl_json(['success' => true] + sl_caTemplateValues($key, $lang));

    case 'legacy_get':
        $keyOk(true);
        if (strpos($key, 'legacy:') !== 0) {
            sl_json(['success' => false, 'message' => 'Not an old-screen message.'], 422);
        }
        $src = substr($key, 7);
        $t = cdp_commLegacyTriggers()[$src] ?? null;
        if (!$t) {
            sl_json(['success' => false, 'message' => 'Unknown message.'], 422);
        }
        $rules = cdp_commRules();
        $enabled = [];
        foreach ($t['channels'] as $c) {
            $enabled[$c] = $t['locked'] ? true : !(isset($rules[$key][$c]) && (int) $rules[$key][$c] === 0);
        }
        sl_json(['success' => true, 'key' => $key, 'label' => $t['label'], 'desc' => $t['desc'], 'locked' => (bool) $t['locked'], 'templates' => sl_caLegacyTemplates($src),
            'enabled' => $enabled, 'tags' => sl_caTags($key), 'sample' => sl_caSample($key)]);

    // ---------------------------------------------------------------- switches
    case 'save_rule':
        $keyOk(true);
        $channel = $channel !== '' ? $channel : $P('ch');
        $on = in_array($P('value'), ['1', 'true', 'on'], true);
        if ($channel === 'inapp') {
            if (cdp_commTriggerStatusId($key) <= 0) {
                sl_json(['success' => false, 'message' => 'Only status messages have an in-app line.'], 422);
            }
            $in = sl_caNotifyRows()[$key]['inapp'] ?? null;
            if ($on) {
                // A line switched off with its own text comes back with it; otherwise the predefined line.
                $r = $in && trim((string) $in->body) !== '' && (int) $in->enabled === 0
                    ? cdp_notifySave($key, 'inapp', ['enabled' => 1, 'body' => (string) $in->body], $uid) : cdp_notifyReset($key, 'inapp');
            } else {
                $body = $in ? (string) $in->body : '';
                $r = cdp_notifySave($key, 'inapp', ['enabled' => 0, 'body' => $body], $uid);
            }
            if (empty($r['ok'])) {
                sl_json(['success' => false, 'message' => $r['error'] ?? 'Not saved.'], 500);
            }
            $log('In-app for ' . cdp_commTriggerLabel($key) . ' switched ' . ($on ? 'on' : 'off'));
            sl_json(['success' => true, 'message' => 'In-app ' . ($on ? 'on' : 'off') . ' for ' . cdp_commTriggerLabel($key)]);
        }
        $chOk();
        if (strpos($key, 'legacy:') === 0) {
            $t = cdp_commLegacyTriggers()[substr($key, 7)] ?? null;
            if (!$t || !in_array($channel, $t['channels'], true)) {
                sl_json(['success' => false, 'message' => 'This message is not sent on ' . $chLabels[$channel] . '.'], 422);
            }
            if ($t['locked']) {
                sl_json(['success' => false, 'message' => 'Security messages are always sent.'], 422);
            }
        }
        sl_caSeedStatusRule($key);
        if (!cdp_commSaveRule($key, $channel, $on)) {
            sl_json(['success' => false, 'message' => 'Not saved.'], 500);
        }
        if (cdp_commTriggerStatusId($key) > 0) {
            sl_caMirrorLegacy($key, $channel, $on, $uid);
        }
        $log($chLabels[$channel] . ' for ' . cdp_commTriggerLabel($key) . ' switched ' . ($on ? 'on' : 'off'));
        $note = '';
        if (!$on && cdp_commTriggerStatusId($key) > 0) {
            $note = ' It stays off even when "Any other status" is on.';
        }
        sl_json(['success' => true, 'message' => $chLabels[$channel] . ' ' . ($on ? 'on' : 'off') . ': ' . cdp_commTriggerLabel($key) . '.' . $note]);

    case 'master':
    case 'drafts':
        if (!sl_caIsAdmin()) {
            sl_json(['success' => false, 'message' => 'Only administrators change this switch.'], 403);
        }
        $on = in_array($P('value'), ['1', 'true', 'on'], true);
        if ($action === 'master') {
            cdp_commSaveSettings(['enabled' => $on ? '1' : '0']);
            $log('Automations master switch ' . ($on ? 'on' : 'off'));
            sl_json(['success' => true, 'message' => $on ? 'Automations are on: switched-on triggers send.' : 'Automations are off: the Automation Center sends nothing (old screens still send their own messages).']);
        }
        if (!cdp_notifyDraftsSet($on, $uid)) {
            sl_json(['success' => false, 'message' => 'Not saved.'], 500);
        }
        $log($on ? 'Predefined status messages on' : 'Predefined status messages off (old shared message)');
        sl_json(['success' => true, 'message' => $on ? 'Every status sends the message written for it.' : 'Statuses without a saved message send the old shared message.']);

    // ---------------------------------------------------------------- templates
    case 'template_save':
        $keyOk();
        $chOk();
        if ($key === 'status:*') {
            sl_json(['success' => false, 'message' => 'Each status sends its own message.'], 422);
        }
        $d = ['event_key' => $key, 'channel' => $channel, 'language' => $lang, 'subject' => $P('subject'), 'active' => 1,
            'wa_template_name' => $P('wa_template_name'), 'wa_template_lang' => $P('wa_template_lang'), 'wa_template_params' => $P('wa_template_params')];
        if ($channel === 'email') {
            $blocks = json_decode($P('blocks'), true);
            if ($P('html') !== '') {
                $d['html'] = $P('html');
                $d['design_json'] = $P('design_json');
            } elseif (is_array($blocks) && $blocks) {
                $blocks = cdp_notifyCleanBlocks($blocks);
                if (!$blocks) {
                    sl_json(['success' => false, 'message' => 'The e-mail is empty.'], 422);
                }
                $d['html'] = cdp_notifyCompileBlocks($blocks);
                $d['design_json'] = json_encode(cdp_commBlocksToCanvas($blocks), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                sl_json(['success' => false, 'message' => 'The e-mail is empty.'], 422);
            }
        } else {
            $d['body'] = $P('body');
        }
        $r = cdp_commSaveTemplate($d);
        if (empty($r['ok'])) {
            sl_json(['success' => false, 'message' => $r['error'] ?: 'Not saved.'], 422);
        }
        $isStatus = cdp_commTriggerStatusId($key) > 0;
        $en = $P('enabled', '');
        $on = null;
        if ($en !== '' && $en !== 'null') {
            $on = in_array($en, ['1', 'true'], true);
            sl_caSeedStatusRule($key);
            cdp_commSaveRule($key, $channel, $on);
        }
        if ($isStatus && $lang === 'en') {
            sl_caMirrorLegacy($key, $channel, $on ?? sl_caLegacyOn($key, $channel), $uid);
        }
        $log($chLabels[$channel] . ' text of ' . cdp_commTriggerLabel($key) . ($lang !== 'en' ? ' (' . SL_CA_LANGS[$lang] . ')' : '') . ' saved'
            . ($P('via_ai') === '1' || $P('via_ai') === 'true' ? ' (written with ' . cdp_kingName() . ')' : '') . ($on === null ? '' : '; switched ' . ($on ? 'on' : 'off')));
        $state = sl_caTemplateValues($key, $lang);
        sl_json(['success' => true, 'message' => 'Saved. ' . $chLabels[$channel] . ($on === false ? ' is off for this message.' : ' sends this text from now on.'),
            'enabled' => $state['enabled'], 'sources' => $state['sources'], 'cells' => $isStatus ? sl_caCells($key) : null]);

    case 'template_reset':
        $keyOk();
        $chOk();
        $r = cdp_commResetTemplate($key, $channel, $lang);
        if (empty($r['ok'])) {
            sl_json(['success' => false, 'message' => $r['error'] ?: 'Not reset.'], 500);
        }
        $isStatus = cdp_commTriggerStatusId($key) > 0;
        if ($isStatus && $lang === 'en') {
            sl_caMirrorLegacy($key, $channel, sl_caLegacyOn($key, $channel), $uid);
        }
        $log($chLabels[$channel] . ' text of ' . cdp_commTriggerLabel($key) . ' set back to the predefined message');
        $state = sl_caTemplateValues($key, $lang);
        sl_json(['success' => true, 'message' => $isStatus ? 'Back to the message written for this status.' : 'Back to the built-in message.',
            'value' => $state['values'][$channel], 'sources' => $state['sources'], 'cells' => $isStatus ? sl_caCells($key) : null]);

    case 'inapp_save':
    case 'inapp_reset':
        $keyOk();
        if (cdp_commTriggerStatusId($key) <= 0) {
            sl_json(['success' => false, 'message' => 'Only status messages have an in-app line.'], 422);
        }
        if ($action === 'inapp_reset') {
            cdp_notifyReset($key, 'inapp');
            $log('In-app line of ' . cdp_commTriggerLabel($key) . ' set back to the predefined line');
            $def = cdp_notifyDraft(cdp_commStatusName(cdp_commTriggerStatusId($key)), 'inapp');
            sl_json(['success' => true, 'message' => 'Back to the predefined line.', 'body' => (string) ($def['body'] ?? ''), 'cells' => sl_caCells($key)]);
        }
        $body = trim(preg_replace('/\s+/', ' ', $P('body')));
        $on = $P('enabled', '1') !== '0';
        if ($on && $body === '') {
            sl_json(['success' => false, 'message' => 'Write the line first.'], 422);
        }
        $r = cdp_notifySave($key, 'inapp', ['enabled' => $on ? 1 : 0, 'body' => $body], $uid);
        if (empty($r['ok'])) {
            sl_json(['success' => false, 'message' => $r['error'] ?? 'Not saved.'], 422);
        }
        $log('In-app line of ' . cdp_commTriggerLabel($key) . ' saved' . ($on ? '' : ' and switched off'));
        sl_json(['success' => true, 'message' => $on ? 'Saved. The bell shows this line from now on.' : 'In-app is off for this status.', 'cells' => sl_caCells($key)]);

    case 'test_send':
        $keyOk(true);
        $chOk();
        if (!sl_caCanEdit()) {
            sl_json(['success' => false, 'message' => 'Only people who can edit messages send tests.'], 403);
        }
        $me = sl_row('SELECT * FROM cdb_users WHERE id = :id', [':id' => $uid]);
        if (!$me) {
            sl_json(['success' => false, 'message' => 'Your account was not found.'], 422);
        }
        $legacy = strpos($key, 'legacy:') === 0;
        $sample = sl_caSample($key);
        $fill = function (string $s, bool $html) use ($legacy, $sample) {
            if ($legacy) {
                return preg_replace_callback('/\[([A-Z_]{2,40})\]/', function ($m) use ($sample, $html) {
                    $v = (string) ($sample[$m[1]] ?? $m[0]);
                    return $html ? htmlspecialchars($v, ENT_QUOTES, 'UTF-8') : $v;
                }, $s);
            }
            return $html ? cdp_notifyRender($s, $sample, true) : trim(cdp_notifyRender(cdp_notifyPruneLines($s, $sample), $sample));
        };
        cdp_msgSetContext(['source' => 'comms_test', 'source_label' => 'Test from Automations', 'entity_type' => 'notification', 'entity_label' => $key]);
        if ($channel === 'email') {
            if (!filter_var((string) $me->email, FILTER_VALIDATE_EMAIL)) {
                sl_json(['success' => false, 'message' => 'Your account has no valid e-mail address.'], 422);
            }
            $html = $P('html');
            if ($html === '') {
                sl_json(['success' => false, 'message' => 'The e-mail is empty.'], 422);
            }
            $html = sl_caCleanHtml($html, $legacy);
            $subject = $fill($P('subject') !== '' ? $P('subject') : 'Notification', false);
            $res = cdp_commSendEmail($me, '[Test] ' . $subject, '<!--sl-html-->' . $fill($html, true));
            sl_json(['success' => !empty($res['success']), 'message' => !empty($res['success']) ? 'Test sent to ' . $me->email : 'Not sent: ' . ($res['message'] ?? 'unknown error')]);
        }
        $text = $fill($P('body'), false);
        if (trim($text) === '') {
            sl_json(['success' => false, 'message' => 'Write the message first.'], 422);
        }
        if (trim((string) ($me->phone ?? '')) === '') {
            sl_json(['success' => false, 'message' => 'Your account has no phone number. Add one in My Account, or send the test by e-mail.'], 422);
        }
        $res = $channel === 'sms' ? cdp_commSendSms($me, '[Test] ' . $text) : cdp_commSendWhatsApp($me, "[Test]\n" . $text);
        sl_json(['success' => !empty($res['success']), 'message' => !empty($res['success']) ? 'Test sent to your phone ' . $me->phone : 'Not sent: ' . ($res['message'] ?: 'unknown error')]);

    // ---------------------------------------------------------------- King
    case 'ai':
        $keyOk(true);
        $chOk(['whatsapp', 'sms', 'email', 'inapp']);
        if (!sl_caCanEdit()) {
            sl_json(['success' => false, 'message' => 'Only people who can edit messages write them with ' . cdp_kingName() . '.'], 403);
        }
        require_once __DIR__ . '/../../helpers/comms_ai.php';
        $tone = $P('tone', 'keep');
        $instruction = mb_substr(trim($P('instruction')), 0, 400);
        if (strpos($key, 'legacy:') === 0) {
            sl_json(sl_caLegacyAi($key, $channel, $tone, $instruction, $P('subject'), $channel === 'email' ? $P('text') : $P('body')));
        }
        $blocks = json_decode($P('blocks'), true);
        $current = ['subject' => $P('subject'), 'body' => $channel === 'email' ? '' : $P('body'), 'blocks' => is_array($blocks) ? cdp_notifyCleanBlocks($blocks) : []];
        if ($channel === 'inapp') {
            if (cdp_commTriggerStatusId($key) <= 0) {
                sl_json(['success' => false, 'message' => 'Only status messages have an in-app line.'], 422);
            }
            $avail = cdp_commAiAvailable();
            if (!$avail['ok']) {
                sl_json(['success' => false, 'code' => $avail['code'], 'message' => $avail['message'], 'setup_url' => $avail['setup_url'], 'assistant' => $avail['assistant']]);
            }
            $res = cdp_commAiRunChannel(cdp_commAiTriggerContext($key), 'inapp', $current, $tone, $instruction);
            $res['assistant'] = $avail['assistant'];
        } else {
            $res = cdp_commAiDraft(cdp_commTriggerStatusId($key) > 0 ? 'status_message' : 'trigger_message', ['key' => $key, 'current' => $current, 'tone' => $tone], $channel, $instruction);
        }
        sl_json(sl_caAiAnswer($res, $channel, $key));

    // ---------------------------------------------------------------- old screens (legacy template tables)
    case 'legacy_save':
        $keyOk(true);
        $chOk();
        $src = substr($key, 7);
        $t = strpos($key, 'legacy:') === 0 ? (cdp_commLegacyTriggers()[$src] ?? null) : null;
        $loc = $t['templates'][$channel] ?? null;
        $id = (int) $P('id');
        if (!$loc || !in_array($id, array_map('intval', (array) $loc['ids']), true)) {
            sl_json(['success' => false, 'message' => 'This message has no such template.'], 422);
        }
        $table = (string) $loc['table'];
        $old = sl_caLegacyRow($table, $id);
        $subject = mb_substr(trim(preg_replace('/\s+/', ' ', $P('subject'))), 0, 250);
        if ($channel === 'email') {
            $body = sl_caCleanHtml($P('html'), true);
            if (trim(strip_tags($body, '<img>')) === '') {
                sl_json(['success' => false, 'message' => 'The e-mail is empty.'], 422);
            }
            if ($subject === '') {
                sl_json(['success' => false, 'message' => 'The e-mail needs a subject.'], 422);
            }
            if (strlen($body) > 65000) {
                sl_json(['success' => false, 'message' => 'This e-mail is too large for the old template table (64 KB). Remove an image or a block.'], 422);
            }
        } else {
            $body = trim(str_replace("\r\n", "\n", $P('body')));
            if ($body === '') {
                sl_json(['success' => false, 'message' => 'The message cannot be empty.'], 422);
            }
            if (mb_strlen($body) > ($channel === 'sms' ? 640 : 4096)) {
                sl_json(['success' => false, 'message' => $channel === 'sms' ? 'SMS texts are limited to 640 characters (4 parts).' : 'WhatsApp messages are limited to 4,096 characters.'], 422);
            }
        }
        $name = $old['name'] ?? ($t['label'] . ($channel === 'email' ? '' : ' (' . $chLabels[$channel] . ')'));
        if ($table === 'whatsapp') {
            $sql = $old ? 'UPDATE whatsapp_templates SET body = :b, description = :s WHERE id = :id'
                : 'INSERT INTO whatsapp_templates (id, title, description, body) VALUES (:id, :n, :s, :b)';
            $bind = [':b' => $body, ':s' => $subject !== '' ? $subject : ($old['subject'] ?? $t['label']), ':id' => $id] + ($old ? [] : [':n' => $name]);
        } else {
            $tbl = $table === 'email' ? 'cdb_email_templates' : 'cdb_sms_templates';
            $sql = $old ? "UPDATE $tbl SET body = :b, subject = :s WHERE id = :id"
                : "INSERT INTO $tbl (id, name, subject, help, body) VALUES (:id, :n, :s, :h, :b)";
            $bind = [':b' => $body, ':s' => $subject !== '' ? $subject : ($old['subject'] ?? $t['label']), ':id' => $id]
                + ($old ? [] : [':n' => $name, ':h' => 'Created from Communications > Automations']);
        }
        $r = cdp_opsX($sql, $bind);
        if (!$r['ok']) {
            sl_json(['success' => false, 'message' => 'Not saved.'], 500);
        }
        if ($table === 'email' && $P('design_json') !== '') {
            $dj = json_decode($P('design_json'), true);
            if (is_array($dj) && isset($dj['blocks'])) {
                cdp_opsSettingSet('comms_legacy', 'design_email_' . $id, json_encode(['hash' => md5($body), 'design' => $dj], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $uid);
            }
        }
        if ($src === 'shipment_registered' && $channel === 'whatsapp') {
            cdp_notifyReset('action:shipment.registered', 'whatsapp');   // the edited template is what is sent
        }
        $log(($old ? 'Edited' : 'Created') . ' ' . $chLabels[$channel] . ' template #' . $id . ' (' . $t['label'] . ')', $old ? 'update' : 'create');
        sl_json(['success' => true, 'message' => ($old ? 'Saved' : 'Created') . '. The old screen sends this text from now on.', 'templates' => sl_caLegacyTemplates($src)]);

    default:
        sl_json(['success' => false, 'message' => 'Unknown action.'], 422);
}

/** One status row's cells (after a change), for the grid. */
function sl_caCells(string $key): ?array
{
    foreach (sl_caStatusRows() as $r) {
        if ($r['key'] === $key) {
            return $r['cells'];
        }
    }
    return null;
}

/**
 * Sanitise browser HTML. [TAGS] of the old templates are parked first so the
 * HTML serialiser cannot URL-escape them inside href/src.
 */
function sl_caCleanHtml(string $html, bool $legacy): string
{
    $park = [];
    if ($legacy) {
        $html = preg_replace_callback('/\[([A-Z_]{2,40})\]/', function ($m) use (&$park) {
            $tok = 'SLTAG' . count($park) . 'X' . $m[1] . 'X';
            $park[$tok] = $m[0];
            return $tok;
        }, $html);
    }
    $out = cdp_commSanitizeEmailHtml($html);
    return $park ? strtr($out, $park) : $out;
}

/** cdp_commAiDraft result -> the editor kit's King answer. */
function sl_caAiAnswer(array $res, string $channel, string $key): array
{
    if (empty($res['ok'])) {
        return ['success' => false, 'code' => (string) ($res['code'] ?? 'error'), 'message' => (string) ($res['error'] ?: 'Nothing was written.'),
            'setup_url' => (string) ($res['setup_url'] ?? ''), 'assistant' => (string) ($res['assistant'] ?? '')];
    }
    $p = ['subject' => (string) $res['subject'], 'body' => (string) $res['body'], 'warnings' => array_values((array) ($res['warnings'] ?? []))];
    if ($channel === 'email') {
        $p['blocks'] = $res['blocks'] ?? [];
        $p['design'] = $res['design'] ?? null;
        unset($p['body']);
    } else {
        $p['chars'] = (int) ($res['chars'] ?? 0);
        $p['parts'] = (int) ($res['parts'] ?? 0);
    }
    if (!empty($res['preview'])) {
        $p['preview'] = $res['preview'];
    }
    cdp_activityLog(['module' => 'notifications', 'verb' => 'other', 'action' => 'automations.ai', 'outcome' => 'ok', 'entity_type' => 'notification',
        'entity_label' => $key, 'summary' => ($res['assistant'] ?? 'King') . ' proposed ' . (SL_CA_CHANNELS + ['inapp' => 'In-app'])[$channel] . ' text for ' . cdp_commTriggerLabel($key) . ' (not saved)']);
    return ['success' => true, 'proposals' => [$channel => $p], 'model' => (string) ($res['model'] ?? ''), 'assistant' => (string) ($res['assistant'] ?? '')];
}

/**
 * Write with King for an old-screen template: the text keeps the [TAGS] that
 * screen fills, and nothing else. Unknown tags are removed with a warning.
 */
function sl_caLegacyAi(string $key, string $channel, string $tone, string $instruction, string $subject, string $body): array
{
    $avail = cdp_commAiAvailable();
    if (!$avail['ok']) {
        return ['success' => false, 'code' => $avail['code'], 'message' => $avail['message'], 'setup_url' => $avail['setup_url'], 'assistant' => $avail['assistant']];
    }
    $t = cdp_commLegacyTriggers()[substr($key, 7)] ?? null;
    if (!$t || empty($t['templates'][$channel])) {
        return ['success' => false, 'code' => 'invalid', 'message' => 'This message has no ' . SL_CA_CHANNELS[$channel] . ' text to write.'];
    }
    $tags = (array) $t['tags'];
    $tones = cdp_notifyAiTones();
    $toneText = $tones[$tone][1] ?? $tones['keep'][1];
    $company = cdp_commAiCompany();
    $rules = ['sms' => 'An SMS: plain text, at most 160 characters with the tags filled, no emoji, no formatting.',
        'whatsapp' => 'A WhatsApp message: short paragraphs, *bold* for the key fact, at most 900 characters, at most one emoji.',
        'email' => 'An e-mail: a subject line (at most 70 characters) and a body of 2 to 4 short paragraphs separated by blank lines, plain text (no HTML).'][$channel];
    $system = 'You write customer messages for ' . $company . ', a parcel forwarding company shipping from the United States to Ghana. '
        . 'Write clear, warm, professional English. Never invent facts, prices, dates or links. Reply with JSON only.';
    $prompt = "Message: " . $t['label'] . " (" . $t['desc'] . ")\nChannel: " . SL_CA_CHANNELS[$channel] . "\n" . $rules . "\n"
        . "Tags the system fills (use them exactly as written, brackets included; use no other bracketed tag): " . implode(' ', $tags) . "\n"
        . "Tone: " . $toneText . "\n" . ($instruction !== '' ? "Instruction from the team: " . $instruction . "\n" : '')
        . "Current " . ($channel === 'email' ? "subject: " . json_encode($subject, JSON_UNESCAPED_UNICODE) . "\nCurrent body (text): " : "text: ")
        . json_encode(mb_substr($body, 0, 3000), JSON_UNESCAPED_UNICODE) . "\n"
        . 'Reply with exactly: {"subject": "...", "body": "..."}' . ($channel === 'email' ? '' : ' (subject empty)');
    $res = cdp_commAiCall($system, $prompt, 1500);
    if (empty($res['ok'])) {
        return ['success' => false, 'code' => 'provider', 'message' => $avail['assistant'] . ' could not answer: ' . trim((string) ($res['error'] ?? 'unknown error'))];
    }
    $j = cdp_notifyAiJson((string) ($res['text'] ?? ''));
    if (!is_array($j) || trim((string) ($j['body'] ?? '')) === '') {
        return ['success' => false, 'code' => 'invalid', 'message' => $avail['assistant'] . ' answered in a form that could not be read. Try again.'];
    }
    $warn = [];
    $clean = function ($s) use ($tags, &$warn) {
        return preg_replace_callback('/\[([A-Za-z_ ]{2,40})\]/', function ($m) use ($tags, &$warn) {
            if (in_array($m[0], $tags, true)) {
                return $m[0];
            }
            $warn[] = 'Removed ' . $m[0] . ': this screen does not fill it.';
            return '';
        }, (string) $s);
    };
    $outBody = trim(preg_replace("/ {2,}/", " ", $clean(str_replace("\r\n", "\n", (string) $j["body"]))));
    $outSubj = $channel === 'email' ? trim($clean(mb_substr((string) ($j['subject'] ?? ''), 0, 120))) : '';
    if ($channel === 'sms' && mb_strlen($outBody) > 160) {
        $warn[] = 'The SMS is ' . mb_strlen($outBody) . ' characters before the tags are filled; it may go out in more than one part.';
    }
    $p = ['subject' => $outSubj, 'warnings' => array_values(array_unique($warn))];
    if ($channel === 'email') {
        $blocks = [];
        foreach (preg_split("/\n{2,}/", $outBody) as $para) {
            if (trim($para) !== '') {
                $blocks[] = ['type' => 'text', 'text' => trim($para)];
            }
        }
        $p['blocks'] = $blocks;
    } else {
        $p['body'] = $outBody;
    }
    cdp_activityLog(['module' => 'notifications', 'verb' => 'other', 'action' => 'automations.ai', 'outcome' => 'ok', 'entity_type' => 'notification',
        'entity_label' => $key, 'summary' => $avail['assistant'] . ' proposed ' . SL_CA_CHANNELS[$channel] . ' text for ' . $t['label'] . ' (not saved)']);
    return ['success' => true, 'proposals' => [$channel => $p], 'model' => (string) ($res['model'] ?? ''), 'assistant' => $avail['assistant']];
}
