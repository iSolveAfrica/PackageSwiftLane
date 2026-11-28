<?php
/**
 * Data for Communications > Surveys (comms_surveys.php, views/sl/pages/CommsSurveys.php).
 *
 * Results (KPIs, trend, per question, by rider and branch, comments and low
 * scores, every answer) for a period and a form; the survey forms with what
 * they sent; the statuses a form can be sent after; the pop-up settings; the
 * invitation message switches (trigger "survey" on Automations); King.
 * Forms and settings are saved by ajax/comms/surveys_ajax.php; the invitation
 * text by ajax/comms/automation_ajax.php (key "survey", the shared editor).
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/comms_surveys.php';

/** Administrators change surveys; staff read them. */
function sl_csvCanEdit($u = null): bool
{
    return cdp_opsRank($u) >= 3;
}

/** King for the builder and the invitation editor. */
function sl_csvAi(): array
{
    require_once CDP_ROOT . '/helpers/comms_ai.php';
    require_once CDP_ROOT . '/helpers/notify_studio.php';
    $a = cdp_commAiAvailable();
    $tones = [];
    if (function_exists('cdp_notifyAiTones')) {
        foreach (cdp_notifyAiTones() as $k => $t) {
            $tones[] = [$k, $t[0]];
        }
    }
    return ['ok' => (bool) $a['ok'], 'code' => (string) $a['code'], 'message' => (string) $a['message'], 'setup_url' => (string) $a['setup_url'],
        'name' => (string) $a['assistant'], 'tones' => $tones];
}

/**
 * The statuses a form can be sent after (only those where the customer has
 * the package: delivered, picked up, arrived), with the delivered / picked-up
 * ones marked as the default. A status a form already lists that may not ask
 * ("No Picked up", "Cancelled", ...) is kept, marked blocked, so the builder
 * can say why it has to go.
 */
function sl_csvStatuses(array $forms = []): array
{
    $def = cdp_surveyDefaultStatuses();
    $used = [];
    foreach ($forms as $f) {
        foreach ($f['statuses'] as $sid) {
            $used[(int) $sid] = 1;
        }
    }
    $out = [];
    foreach (cdp_commStatusNames() as $id => $name) {
        $ok = cdp_surveyStatusAllowed((int) $id, $name);
        if (!$ok && !isset($used[(int) $id])) {
            continue;
        }
        $kind = cdp_commStatusKind($id, $name);
        $out[] = ['id' => (int) $id, 'label' => cdp_commStatusLabel($name), 'kind' => $kind, 'default' => in_array((int) $id, $def, true), 'blocked' => !$ok];
    }
    return $out;
}

/** Sent / answered / average per form (all time). */
function sl_csvFormCounts(): array
{
    $out = [];
    foreach (cdp_opsQ("SELECT form_id, SUM(sent_at <= :n) sent, SUM(responded_at IS NOT NULL) answered, AVG(score) avg_score, MAX(responded_at) last_answer
                       FROM cdb_comm_surveys WHERE form_id IS NOT NULL GROUP BY form_id", [':n' => cdp_surveyNow()]) as $r) {
        $out[(int) $r->form_id] = ['sent' => (int) $r->sent, 'answered' => (int) $r->answered, 'avg' => $r->avg_score !== null ? round((float) $r->avg_score, 2) : null,
            'last' => (string) ($r->last_answer ?? '')];
    }
    return $out;
}

/** One line saying when a form is sent. */
function sl_csvWhen(array $f, array $statusLabels): string
{
    if ($f['statuses']) {
        $names = array_map(function ($id) use ($statusLabels) { return $statusLabels[$id] ?? ('Status ' . $id); }, $f['statuses']);
        $st = count($names) > 3 ? implode(', ', array_slice($names, 0, 3)) . ' +' . (count($names) - 3) : implode(', ', $names);
    } else {
        $st = 'Delivered or picked up';
    }
    $delay = $f['delay_hours'] <= 0 ? 'right away' : ($f['delay_hours'] % 24 === 0 ? $f['delay_hours'] / 24 . ' day' . ($f['delay_hours'] === 24 ? '' : 's') . ' after' : $f['delay_hours'] . ' hour' . ($f['delay_hours'] === 1 ? '' : 's') . ' after');
    return $st . ' · ' . $delay;
}

/** Page data. */
function sl_commsSurveysData($user): array
{
    $ready = cdp_surveyEnsure();
    $preset = (string) ($_GET['range'] ?? '30');
    $presets = ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days', '365' => 'Last 12 months', 'custom' => 'Custom'];
    if (!isset($presets[$preset])) {
        $preset = '30';
    }
    $badDates = false;
    if ($preset === 'custom') {
        [$from, $to, $badDates] = cdp_surveyRange($_GET['from'] ?? '', $_GET['to'] ?? '', 30);
        if ($badDates) {
            $preset = '30';
        }
    } else {
        [$from, $to] = cdp_surveyRange('', '', (int) $preset);
    }
    $formId = max(0, (int) ($_GET['form'] ?? 0));
    $forms = $ready ? cdp_surveyForms() : [];
    if ($formId && !array_filter($forms, function ($f) use ($formId) { return $f['id'] === $formId; })) {
        $formId = 0;
    }
    $statuses = $ready ? sl_csvStatuses($forms) : [];
    $labels = [];
    foreach ($statuses as $s) {
        $labels[$s['id']] = $s['label'];
    }
    $counts = $ready ? sl_csvFormCounts() : [];
    foreach ($forms as &$f) {
        $f['counts'] = $counts[$f['id']] ?? ['sent' => 0, 'answered' => 0, 'avg' => null, 'last' => ''];
        $f['when'] = sl_csvWhen($f, $labels);
    }
    unset($f);
    $results = $ready ? cdp_surveyResults($from, $to, $formId) : null;
    if ($results) {
        foreach (['comments', 'low'] as $k) {
            foreach ($results[$k] as &$r) {
                $r['record_link'] = cdp_surveyRecordLink($r['entity_type'], $r['order_id']);
                $r['customer_link'] = sl_route('CustomerProfile', ['id' => $r['user_id']]);
                $r['ticket_link'] = $r['ticket_id'] > 0 ? sl_route('CsTicket', ['id' => $r['ticket_id']]) : '';
            }
            unset($r);
        }
    }
    $rules = $ready ? cdp_commRules() : [];
    $tab = (string) ($_GET['tab'] ?? '');
    return [
        'ready' => $ready, 'canEdit' => sl_csvCanEdit($user), 'ai' => sl_csvAi(),
        'tab' => in_array($tab, ['results', 'forms', 'settings'], true) ? $tab : 'results',
        'preset' => $preset, 'presets' => $presets, 'badDates' => $badDates, 'here' => sl_route('CommsSurveys'), 'from' => $from, 'to' => $to, 'formId' => $formId,
        'forms' => $forms, 'statuses' => $statuses, 'types' => cdp_surveyTypes(), 'channels' => cdp_surveyChannels(),
        'settings' => cdp_surveySettings(), 'results' => $results,
        'inviteRules' => ['whatsapp' => !isset($rules['survey']['whatsapp']) || (int) $rules['survey']['whatsapp'] === 1,
            'sms' => !isset($rules['survey']['sms']) || (int) $rules['survey']['sms'] === 1, 'email' => !isset($rules['survey']['email']) || (int) $rules['survey']['email'] === 1],
        'engineOn' => cdp_commSetting('enabled') === '1', 'hookLoaded' => function_exists('cdp_commSurveyOnStatus'),
        'automationsUrl' => sl_route('CommsAutomations'), 'outboxUrl' => sl_route('CommsOutbox'), 'settingsUrl' => sl_route('CommsSettings'),
        'exportBase' => sl_route('CommsSurveys'),
    ];
}

/** CSV / Excel export of every answer in the period (comms_surveys.php?export=csv|xlsx). */
function sl_commsSurveysExport(string $format): void
{
    [$from, $to] = ($_GET['range'] ?? '') === 'custom' ? cdp_surveyRange($_GET['from'] ?? '', $_GET['to'] ?? '', 30)
        : cdp_surveyRange('', '', (int) (in_array($_GET['range'] ?? '30', ['7', '30', '90', '365'], true) ? $_GET['range'] : 30));
    $formId = max(0, (int) ($_GET['form'] ?? 0));
    $rows = cdp_surveyResponses($from, $to, $formId, 'all', 5000);
    $ids = array_column($rows, 'id');
    $answers = [];
    if ($ids) {
        $in = implode(',', array_map('intval', $ids));
        foreach (cdp_opsQ("SELECT survey_id, question_label, question_type, value_num, value_text FROM cdb_comm_survey_answers WHERE survey_id IN ($in) ORDER BY id") as $a) {
            $v = $a->value_num !== null ? (string) (int) $a->value_num : (string) $a->value_text;
            $label = (string) $a->question_label;
            $answers[(int) $a->survey_id][$label] = isset($answers[(int) $a->survey_id][$label]) ? $answers[(int) $a->survey_id][$label] . '; ' . $v : $v;
        }
    }
    $qLabels = [];
    foreach ($answers as $set) {
        foreach (array_keys($set) as $l) {
            $qLabels[$l] = 1;
        }
    }
    $qLabels = array_keys($qLabels);
    $head = array_merge(['Answered', 'Sent', 'Customer', 'Locker', 'Tracking', 'Survey', 'Channel', 'Rating (1-5)', 'Recommend (0-10)', 'Comment', 'Rider', 'Branch'], $qLabels);
    $out = [];
    foreach ($rows as $r) {
        $line = [$r['responded_at'], $r['sent_at'], $r['customer'], $r['locker'], $r['ref'], $r['form'], $r['channel'], $r['score'] ?? '', $r['nps'] ?? '', $r['comment'], $r['rider'], $r['branch']];
        foreach ($qLabels as $l) {
            $line[] = $answers[$r['id']][$l] ?? '';
        }
        $out[] = $line;
    }
    cdp_opsSendExport($format === 'xlsx' ? 'xlsx' : 'csv', 'survey-answers-' . $from . '-to-' . $to, 'Survey answers ' . $from . ' to ' . $to,
        ['Answers' => ['head' => $head, 'rows' => $out]]);
}
