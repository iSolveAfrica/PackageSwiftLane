<?php
/**
 * Communications > Surveys endpoint (comms_surveys.php; dataJs/sl/comms_surveys.js).
 *
 * POST only (CSRF checked by require_login). action =
 *   form_get       id                          one survey form (builder)
 *   form_save      form (JSON: id, name, active, intro, thanks, questions[], statuses[], delay_hours,
 *                  channels[], frequency_days)
 *   form_toggle    id, value (1/0)             switch a survey on or off (sl-toggle data-sl-save)
 *   form_duplicate id
 *   form_delete    id                          a survey already sent is switched off instead
 *   settings_save  popup, max_prompts, gap_hours, window_days   the in-app pop-up
 *   ai             name, purpose, count, instruction   Write questions with King; never saves
 *   response       id                          every answer of one survey (Results row detail)
 *   invite_email_reset                         the e-mail invitation back to its built-in design
 *
 * Reading needs staff (rank 2+); changing needs an administrator (rank 3+).
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../helpers/comms_surveys.php';
require_once __DIR__ . '/../../helpers/comms_ai.php';
require_login();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
if (cdp_opsRank() < 2) {
    sl_json(['success' => false, 'message' => 'Staff only.'], 403);
}
if (!cdp_surveyEnsure()) {
    sl_json(['success' => false, 'message' => 'The survey tables could not be created. The database user needs the CREATE privilege once.'], 500);
}

$action = (string) ($_POST['action'] ?? '');
$uid = (int) ($_SESSION['userid'] ?? 0);
$P = function ($k, $d = '') { return isset($_POST[$k]) && !is_array($_POST[$k]) ? (string) $_POST[$k] : $d; };
// Reading a form or an answer: staff. Everything else (King writes for the builder): administrators.
if (!in_array($action, ['form_get', 'response'], true)) {
    if (defined('CDP_APP_MODE_DEMO') && CDP_APP_MODE_DEMO === true && $action !== 'ai') {
        sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
    }
    if (cdp_opsRank() < 3) {
        sl_json(['success' => false, 'message' => 'Only administrators change surveys.'], 403);
    }
}
$log = function (string $summary, string $label = '', string $verb = 'update') use ($action) {
    cdp_activityLog(['module' => 'notifications', 'verb' => $verb, 'action' => 'surveys.' . $action, 'outcome' => 'ok',
        'entity_type' => 'survey_form', 'entity_label' => $label, 'summary' => $summary]);
};

switch ($action) {
    case 'form_get':
        $f = cdp_surveyForm((int) $P('id'));
        if (!$f) {
            sl_json(['success' => false, 'message' => 'This survey no longer exists.'], 404);
        }
        sl_json(['success' => true, 'form' => $f]);

    case 'form_save':
        $in = json_decode($P('form'), true);
        if (!is_array($in)) {
            sl_json(['success' => false, 'message' => 'The survey could not be read. Reload the page and try again.'], 422);
        }
        $r = cdp_surveySaveForm($in, $uid);
        if (!$r['ok']) {
            sl_json(['success' => false, 'message' => $r['error']], 422);
        }
        $log(((int) ($in['id'] ?? 0) > 0 ? 'Survey changed: ' : 'Survey created: ') . $r['form']['name'], $r['form']['name'], (int) ($in['id'] ?? 0) > 0 ? 'update' : 'create');
        sl_json(['success' => true, 'message' => 'Survey saved', 'id' => $r['id'], 'form' => $r['form']]);

    case 'form_toggle':
        $id = (int) ($P('id') !== '' ? $P('id') : $P('key'));
        $on = in_array($P('value'), ['1', 'true', 'on'], true);
        $r = cdp_surveySetActive($id, $on, $uid);
        if (!$r['ok']) {
            sl_json(['success' => false, 'message' => $r['error']], 422);
        }
        $f = cdp_surveyForm($id);
        $log('Survey ' . ($on ? 'switched on' : 'switched off') . ': ' . ($f['name'] ?? ''), $f['name'] ?? '');
        sl_json(['success' => true, 'message' => $on ? 'Survey on' : 'Survey off']);

    case 'form_duplicate':
        $f = cdp_surveyForm((int) $P('id'));
        if (!$f) {
            sl_json(['success' => false, 'message' => 'This survey no longer exists.'], 404);
        }
        $f['id'] = 0;
        $f['name'] = mb_substr('Copy of ' . $f['name'], 0, 120);
        $f['active'] = false;
        $r = cdp_surveySaveForm($f, $uid);
        if (!$r['ok']) {
            sl_json(['success' => false, 'message' => $r['error']], 422);
        }
        $log('Survey copied: ' . $f['name'], $f['name'], 'create');
        sl_json(['success' => true, 'message' => 'Copy made (switched off)', 'id' => $r['id'], 'reload' => true]);

    case 'form_delete':
        $f = cdp_surveyForm((int) $P('id'));
        $r = cdp_surveyDeleteForm((int) $P('id'));
        if (!$r['ok']) {
            sl_json(['success' => false, 'message' => $r['error']], 422);
        }
        $log(!empty($r['archived']) ? 'Survey switched off (it has answers): ' . ($f['name'] ?? '') : 'Survey deleted: ' . ($f['name'] ?? ''), $f['name'] ?? '', !empty($r['archived']) ? 'update' : 'delete');
        sl_json(['success' => true, 'reload' => true, 'archived' => !empty($r['archived']),
            'message' => !empty($r['archived']) ? 'This survey was already sent, so it was switched off and its answers kept.' : 'Survey deleted']);

    case 'settings_save':
        $r = cdp_surveySaveSettings(['popup' => $P('popup', '0') === '1' ? 1 : 0, 'max_prompts' => (int) $P('max_prompts'),
            'gap_hours' => (int) $P('gap_hours'), 'window_days' => (int) $P('window_days')], $uid);
        if (!$r['ok']) {
            sl_json(['success' => false, 'message' => $r['error']], 422);
        }
        $log('Survey pop-up settings changed', 'Pop-up');
        sl_json(['success' => true, 'message' => 'Pop-up settings saved', 'settings' => $r['settings']]);

    case 'ai':
        $count = max(1, min(8, (int) $P('count', '3')));
        $res = cdp_commAiDraft('survey_questions', ['name' => mb_substr($P('name', 'How did we do?'), 0, 120), 'purpose' => mb_substr($P('purpose'), 0, 400), 'count' => $count],
            '', mb_substr($P('instruction'), 0, 400));
        if (empty($res['ok'])) {
            sl_json(['success' => false, 'message' => $res['error'] ?: 'Nothing came back. Please try again.', 'code' => $res['code'] ?? 'error',
                'setup_url' => $res['setup_url'] ?? '', 'assistant' => $res['assistant'] ?? cdp_kingName()]);
        }
        $qs = [];
        foreach ((array) ($res['questions'] ?? []) as $i => $q) {
            $qs[] = ['key' => 'q' . ($i + 1), 'type' => (string) $q['type'], 'label' => (string) $q['label'],
                'required' => $q['type'] !== 'text', 'options' => array_values((array) ($q['options'] ?? []))];
        }
        $clean = cdp_surveyCleanQuestions($qs);
        if (!$clean['ok']) {
            sl_json(['success' => false, 'message' => $clean['error'], 'code' => 'invalid']);
        }
        // The pop-up and survey.php fill {company} / {first_name}; anything else King left in braces is dropped.
        $plain = function ($t) {
            $t = preg_replace('/\{(?!company\}|first_name\})[a-z_]*\}/', '', (string) $t);
            return trim(preg_replace('/\s+([,.!?])/u', '$1', preg_replace('/[ \t]{2,}/', ' ', $t)));
        };
        sl_json(['success' => true, 'questions' => $clean['questions'], 'intro' => $plain($res['intro'] ?? ''), 'thanks' => $plain($res['thanks'] ?? ''),
            'warnings' => array_values((array) ($res['warnings'] ?? [])), 'assistant' => $res['assistant'] ?? cdp_kingName()]);

    case 'response':
        $id = (int) $P('id');
        $s = cdp_opsOne("SELECT s.*, TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) customer, f.name form_name
                         FROM cdb_comm_surveys s LEFT JOIN cdb_users u ON u.id = s.user_id LEFT JOIN cdb_comm_survey_forms f ON f.id = s.form_id WHERE s.id = :id", [':id' => $id]);
        if (!$s) {
            sl_json(['success' => false, 'message' => 'Survey not found.'], 404);
        }
        $answers = [];
        foreach (cdp_surveyAnswers($id) as $a) {
            $k = (string) $a->question_key;
            if (!isset($answers[$k])) {
                $answers[$k] = ['label' => (string) $a->question_label, 'type' => (string) $a->question_type, 'values' => []];
            }
            $answers[$k]['values'][] = $a->value_num !== null ? (string) (int) $a->value_num : (string) $a->value_text;
        }
        if (!$answers && $s->score !== null) {
            $answers['score'] = ['label' => 'Rating (reply by message)', 'type' => 'rating', 'values' => [(string) (int) $s->score]];
        }
        sl_json(['success' => true, 'survey' => ['id' => (int) $s->id, 'customer' => trim((string) $s->customer), 'ref' => (string) $s->entity_ref,
            'form' => (string) ($s->form_name ?? ''), 'sent_at' => (string) $s->sent_at, 'responded_at' => (string) $s->responded_at, 'channel' => (string) $s->channel,
            'sent_label' => $s->sent_at ? date('M j, Y H:i', strtotime((string) $s->sent_at)) : '', 'responded_label' => $s->responded_at ? date('M j, Y H:i', strtotime((string) $s->responded_at)) : '',
            'channel_label' => cdp_surveyChannels()[(string) $s->channel] ?? ucfirst((string) $s->channel),
            'ticket_link' => !empty($s->followup_ticket_id) ? sl_route('CsTicket', ['id' => (int) $s->followup_ticket_id]) : '',
            'customer_link' => sl_route('CustomerProfile', ['id' => (int) $s->user_id]), 'record_link' => cdp_surveyRecordLink((string) $s->entity_type, (int) $s->order_id)],
            'answers' => array_values($answers)]);

    case 'invite_email_reset':
        // "Reset to built-in" for the e-mail invitation: the designed e-mail (heading, button), not the WhatsApp text.
        if (!cdp_surveyInviteEmailSeed(true)) {
            sl_json(['success' => false, 'message' => 'The e-mail could not be reset. Please try again.'], 500);
        }
        require_once __DIR__ . '/../../views/sl/data/comms_automations.php';
        $state = function_exists('sl_caTemplateValues') ? sl_caTemplateValues('survey', 'en') : null;
        $log('Survey invitation e-mail set back to the built-in design', 'Invitation');
        sl_json(['success' => true, 'message' => 'Back to the built-in e-mail.', 'value' => $state['values']['email'] ?? null, 'sources' => $state['sources'] ?? null]);

    default:
        sl_json(['success' => false, 'message' => 'Unknown action.'], 400);
}
