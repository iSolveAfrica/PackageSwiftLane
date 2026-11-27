<?php
/**
 * Customer satisfaction surveys (Communications > Surveys, comms_surveys.php).
 *
 * A survey FORM (cdb_comm_survey_forms) holds the questions (rating 1-5,
 * recommend 0-10 (NPS), one choice, several choices, free text; each with a
 * required flag, in order), the intro and thank-you lines, and when it is sent:
 * the statuses that trigger it (default: every status whose name means
 * delivered or picked up; "No Picked up" never), a delay in hours, the
 * channels (WhatsApp / SMS / E-mail invitations and the in-app pop-up) and
 * "once per customer per N days".
 *
 * A SURVEY (cdb_comm_surveys, one per customer and shipment) is created by
 * cdp_commSurveyOnStatus() only when at least one channel was queued (an
 * invitation in the outbox, or the in-app pop-up). Its sent_at is the moment
 * the invitation goes out (now + delay). The answers are rows in
 * cdb_comm_survey_answers; the first rating answer is also kept in
 * cdb_comm_surveys.score (and the first text answer in .comment) so every
 * older reader (Customer history, the Overview KPI, management reports, the
 * WhatsApp "reply with a number" path) keeps working.
 *
 * The in-app pop-up (views/sl/inc/survey_prompt.php) asks a customer with an
 * unanswered survey younger than the window when they come back to the
 * portal: "Ask me later" waits the gap, the pop-up is shown at most N times.
 *
 * Settings: cdb_ops_settings ns=comms key survey_settings (JSON).
 * Schema: migrations/2026_11_27_comms_surveys.sql, also applied on first use
 * by cdp_surveyEnsure().
 */
require_once __DIR__ . '/comms.php';

if (!function_exists('cdp_surveyEnsure')) {

    define('CDP_SURVEY_SCHEMA', 1);

    /** Question types: key => [label, short hint]. */
    function cdp_surveyTypes()
    {
        return [
            'rating' => ['Rating 1 to 5', 'Stars from 1 (poor) to 5 (excellent)'],
            'nps' => ['Recommend 0 to 10', 'How likely to recommend us (NPS)'],
            'choice' => ['One choice', 'Pick one answer from a list'],
            'multi' => ['Several choices', 'Tick any answers from a list'],
            'text' => ['Free text', 'A comment in their own words'],
        ];
    }

    /** Survey channels: the three invitation channels plus the in-app pop-up. */
    function cdp_surveyChannels()
    {
        return ['inapp' => 'In-app pop-up', 'whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'E-mail'];
    }

    /** The questions of the default form ("How did we do?"): today's rating and comment. */
    function cdp_surveyDefaultQuestions()
    {
        return [
            ['key' => 'q1', 'type' => 'rating', 'label' => 'How would you rate your experience with this delivery?', 'required' => true, 'options' => []],
            ['key' => 'q2', 'type' => 'text', 'label' => 'Anything you would like to tell us?', 'required' => false, 'options' => []],
        ];
    }

    /** Statuses that send the survey when a form names none: delivered and picked up. */
    function cdp_surveyDefaultStatuses()
    {
        $out = [];
        foreach (cdp_commStatusNames() as $id => $name) {
            if (in_array(cdp_commStatusKind($id, $name), ['delivered', 'picked_up'], true)) {
                $out[] = (int) $id;
            }
        }
        return $out;
    }

    /**
     * Create the tables and columns once (schema version in comms.survey_schema).
     * Seeds the default form with the channels the old "Satisfaction survey"
     * rule had on, plus the in-app pop-up.
     */
    function cdp_surveyEnsure()
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        if (!cdp_commEnsureTables()) {
            return $ok = false;
        }
        if ((int) cdp_opsSetting('comms', 'survey_schema', '0') >= CDP_SURVEY_SCHEMA) {
            return $ok = true;
        }
        $t = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        $ddl = [
            "CREATE TABLE IF NOT EXISTS cdb_comm_survey_forms (
                id INT NOT NULL AUTO_INCREMENT, name VARCHAR(120) NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1,
                questions MEDIUMTEXT NULL, intro VARCHAR(500) NULL, thanks VARCHAR(500) NULL,
                trigger_statuses TEXT NULL, delay_hours INT NOT NULL DEFAULT 2, channels VARCHAR(120) NULL,
                frequency_days INT NOT NULL DEFAULT 7, sort_order INT NOT NULL DEFAULT 0,
                created_by INT NULL, updated_by INT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
                PRIMARY KEY (id), KEY idx_active (active, sort_order))$t",
            "CREATE TABLE IF NOT EXISTS cdb_comm_survey_answers (
                id BIGINT NOT NULL AUTO_INCREMENT, survey_id INT NOT NULL, form_id INT NULL, question_key VARCHAR(20) NOT NULL,
                question_type VARCHAR(10) NOT NULL, question_label VARCHAR(200) NULL, value_num INT NULL, value_text TEXT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id), KEY idx_survey (survey_id), KEY idx_form_q (form_id, question_key))$t",
        ];
        if (!cdp_opsDdl($ddl)) {
            return $ok = false;
        }
        $cols = [
            'form_id' => 'INT NULL', 'status_id' => 'INT NULL', 'track_id' => 'INT NULL', 'entity_type' => 'VARCHAR(20) NULL',
            'order_id' => 'INT NULL', 'rider_id' => 'INT NULL', 'nps' => 'TINYINT NULL',
            'prompted_count' => 'INT NOT NULL DEFAULT 0', 'last_prompted_at' => 'DATETIME NULL', 'dismissed_at' => 'DATETIME NULL',
        ];
        foreach ($cols as $c => $def) {
            if (!cdp_opsColumnExists('cdb_comm_surveys', $c)) {
                cdp_opsX("ALTER TABLE cdb_comm_surveys ADD COLUMN $c $def");
            }
        }
        $keys = ['uq_track' => 'ADD UNIQUE KEY uq_track (track_id, user_id)', 'idx_form' => 'ADD KEY idx_form (form_id, sent_at)'];
        foreach ($keys as $name => $sql) {
            if (!cdp_opsVal("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_surveys' AND INDEX_NAME = :n", [':n' => $name])) {
                cdp_opsX("ALTER TABLE cdb_comm_surveys $sql");
            }
        }
        if (!cdp_opsVal("SELECT COUNT(*) FROM cdb_comm_survey_forms")) {
            $rules = cdp_commRules();
            $chs = ['inapp'];
            foreach (['whatsapp', 'sms', 'email'] as $c) {
                if (!empty($rules['survey'][$c])) {
                    $chs[] = $c;
                }
            }
            cdp_opsX("INSERT INTO cdb_comm_survey_forms (name, active, questions, intro, thanks, trigger_statuses, delay_hours, channels, frequency_days, sort_order, created_by, created_at, updated_at)
                      VALUES (:n, 1, :q, :i, :t, :s, 2, :c, 7, 0, 0, :now, :now)",
                [':n' => 'How did we do?', ':q' => cdp_opsJson(cdp_surveyDefaultQuestions()),
                    ':i' => 'Your package has arrived. Tell us in a few seconds how it went.', ':t' => 'Thank you! Your feedback helps us serve you better.',
                    ':s' => '[]', ':c' => implode(',', $chs), ':now' => cdp_surveyNow()]);
        }
        cdp_opsSettingSet('comms', 'survey_schema', (string) CDP_SURVEY_SCHEMA, 0);
        return $ok = true;
    }

    // ------------------------------------------------------------- settings

    function cdp_surveySettingsDefaults()
    {
        return ['popup' => 1, 'max_prompts' => 3, 'gap_hours' => 24, 'window_days' => 30];
    }

    function cdp_surveySettings()
    {
        $s = cdp_opsDecode(cdp_opsSetting('comms', 'survey_settings', ''), []);
        $d = cdp_surveySettingsDefaults();
        return [
            'popup' => isset($s['popup']) ? ((int) $s['popup'] ? 1 : 0) : $d['popup'],
            'max_prompts' => max(1, min(10, (int) ($s['max_prompts'] ?? $d['max_prompts']))),
            'gap_hours' => max(1, min(720, (int) ($s['gap_hours'] ?? $d['gap_hours']))),
            'window_days' => max(1, min(90, (int) ($s['window_days'] ?? $d['window_days']))),
        ];
    }

    /** @return array{ok:bool,error:string,settings?:array} */
    function cdp_surveySaveSettings(array $in, $userId = null)
    {
        $cur = cdp_surveySettings();
        $new = [
            'popup' => array_key_exists('popup', $in) ? ((int) $in['popup'] ? 1 : 0) : $cur['popup'],
            'max_prompts' => array_key_exists('max_prompts', $in) ? (int) $in['max_prompts'] : $cur['max_prompts'],
            'gap_hours' => array_key_exists('gap_hours', $in) ? (int) $in['gap_hours'] : $cur['gap_hours'],
            'window_days' => array_key_exists('window_days', $in) ? (int) $in['window_days'] : $cur['window_days'],
        ];
        if ($new['max_prompts'] < 1 || $new['max_prompts'] > 10) {
            return ['ok' => false, 'error' => 'Ask between 1 and 10 times.'];
        }
        if ($new['gap_hours'] < 1 || $new['gap_hours'] > 720) {
            return ['ok' => false, 'error' => 'Wait between 1 and 720 hours before asking again.'];
        }
        if ($new['window_days'] < 1 || $new['window_days'] > 90) {
            return ['ok' => false, 'error' => 'Keep a survey open between 1 and 90 days.'];
        }
        cdp_opsSettingSet('comms', 'survey_settings', cdp_opsJson($new), $userId);
        return ['ok' => true, 'error' => '', 'settings' => $new];
    }

    // ---------------------------------------------------------------- forms

    /**
     * Clean a question list from the builder or from King.
     * @return array{ok:bool,error:string,questions:array}
     */
    function cdp_surveyCleanQuestions($list)
    {
        $types = cdp_surveyTypes();
        $out = [];
        $used = [];
        if (!is_array($list)) {
            return ['ok' => false, 'error' => 'The questions could not be read.', 'questions' => []];
        }
        foreach ($list as $i => $q) {
            if (!is_array($q)) {
                continue;
            }
            $type = (string) ($q['type'] ?? '');
            $label = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($q['label'] ?? ''))));
            if (!isset($types[$type])) {
                return ['ok' => false, 'error' => 'Question ' . ($i + 1) . ' has an unknown type.', 'questions' => []];
            }
            if ($label === '') {
                return ['ok' => false, 'error' => 'Question ' . ($i + 1) . ' needs its question text.', 'questions' => []];
            }
            $key = (string) ($q['key'] ?? '');
            if (!preg_match('/^q[0-9]{1,4}$/', $key) || isset($used[$key])) {
                $n = 1;
                while (isset($used['q' . $n])) {
                    $n++;
                }
                $key = 'q' . $n;
            }
            $used[$key] = 1;
            $item = ['key' => $key, 'type' => $type, 'label' => mb_substr($label, 0, 200), 'required' => !empty($q['required']) && $q['required'] !== '0' && $q['required'] !== 'false', 'options' => []];
            if ($type === 'choice' || $type === 'multi') {
                $opts = [];
                foreach (is_array($q['options'] ?? null) ? $q['options'] : [] as $o) {
                    $o = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $o))), 0, 80);
                    if ($o !== '' && !in_array($o, $opts, true)) {
                        $opts[] = $o;
                    }
                }
                if (count($opts) < 2) {
                    return ['ok' => false, 'error' => 'Question ' . ($i + 1) . ' needs at least two answers to choose from.', 'questions' => []];
                }
                $item['options'] = array_slice($opts, 0, 8);
            }
            $out[] = $item;
        }
        if (!$out) {
            return ['ok' => false, 'error' => 'Add at least one question.', 'questions' => []];
        }
        if (count($out) > 12) {
            return ['ok' => false, 'error' => 'Keep a survey to 12 questions or fewer; short surveys get answered.', 'questions' => []];
        }
        return ['ok' => true, 'error' => '', 'questions' => $out];
    }

    /** A form row decoded: questions, statuses and channels as arrays. */
    function cdp_surveyDecodeForm($r)
    {
        if (!$r) {
            return null;
        }
        $qs = cdp_opsDecode($r->questions ?? '', []);
        $clean = cdp_surveyCleanQuestions($qs);
        $st = array_values(array_filter(array_map('intval', cdp_opsDecode($r->trigger_statuses ?? '', [])), function ($x) { return $x > 0; }));
        $chs = array_values(array_intersect(array_keys(cdp_surveyChannels()), array_filter(explode(',', (string) ($r->channels ?? '')))));
        return [
            'id' => (int) $r->id, 'name' => (string) $r->name, 'active' => (int) $r->active === 1,
            'questions' => $clean['ok'] ? $clean['questions'] : cdp_surveyDefaultQuestions(),
            'intro' => (string) ($r->intro ?? ''), 'thanks' => (string) ($r->thanks ?? ''),
            'statuses' => $st, 'statuses_default' => !$st, 'delay_hours' => (int) $r->delay_hours, 'channels' => $chs,
            'frequency_days' => (int) $r->frequency_days, 'sort_order' => (int) ($r->sort_order ?? 0),
            'updated_at' => (string) ($r->updated_at ?? ''),
        ];
    }

    /** @return array[] decoded forms, in order */
    function cdp_surveyForms($activeOnly = false)
    {
        if (!cdp_surveyEnsure()) {
            return [];
        }
        $rows = cdp_opsQ("SELECT * FROM cdb_comm_survey_forms" . ($activeOnly ? " WHERE active = 1" : '') . " ORDER BY sort_order, id");
        return array_map('cdp_surveyDecodeForm', $rows);
    }

    function cdp_surveyForm($id)
    {
        if (!cdp_surveyEnsure() || (int) $id <= 0) {
            return null;
        }
        return cdp_surveyDecodeForm(cdp_opsOne("SELECT * FROM cdb_comm_survey_forms WHERE id = :id", [':id' => (int) $id]));
    }

    /** The form a survey row answers: its own, else the first form, else the built-in questions. */
    function cdp_surveyFormFor($survey)
    {
        $f = !empty($survey->form_id) ? cdp_surveyForm((int) $survey->form_id) : null;
        if (!$f) {
            $all = cdp_surveyForms();
            $f = $all[0] ?? null;
        }
        if (!$f) {
            $f = ['id' => 0, 'name' => 'How did we do?', 'active' => true, 'questions' => cdp_surveyDefaultQuestions(), 'intro' => '', 'thanks' => '',
                'statuses' => [], 'statuses_default' => true, 'delay_hours' => 0, 'channels' => ['inapp'], 'frequency_days' => 7, 'sort_order' => 0, 'updated_at' => ''];
        }
        return $f;
    }

    /**
     * Save a form (new when id is 0). The invitation channels it uses are also
     * switched on for the "survey" automation trigger so the Automations page
     * and this form agree.
     *
     * @return array{ok:bool,error:string,id?:int,form?:array}
     */
    function cdp_surveySaveForm(array $d, $userId = null)
    {
        if (!cdp_surveyEnsure()) {
            return ['ok' => false, 'error' => 'The survey tables could not be created.'];
        }
        $id = (int) ($d['id'] ?? 0);
        if ($id > 0 && !cdp_opsOne("SELECT id FROM cdb_comm_survey_forms WHERE id = :id", [':id' => $id])) {
            return ['ok' => false, 'error' => 'This survey no longer exists.'];
        }
        $name = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($d['name'] ?? '')))), 0, 120);
        if ($name === '') {
            return ['ok' => false, 'error' => 'Give the survey a name.'];
        }
        $qs = $d['questions'] ?? [];
        if (is_string($qs)) {
            $qs = json_decode($qs, true);
        }
        $clean = cdp_surveyCleanQuestions($qs);
        if (!$clean['ok']) {
            return ['ok' => false, 'error' => $clean['error']];
        }
        $statusNames = cdp_commStatusNames();
        $st = [];
        foreach ((array) ($d['statuses'] ?? []) as $s) {
            $s = (int) $s;
            if ($s > 0 && isset($statusNames[$s]) && !in_array($s, $st, true)) {
                $st[] = $s;
            }
        }
        $chs = array_values(array_intersect(array_keys(cdp_surveyChannels()), (array) ($d['channels'] ?? [])));
        $active = !empty($d['active']) && $d['active'] !== '0';
        if ($active && !$chs) {
            return ['ok' => false, 'error' => 'Choose at least one way to ask: the in-app pop-up, WhatsApp, SMS or e-mail.'];
        }
        $delay = (int) ($d['delay_hours'] ?? 0);
        $freq = (int) ($d['frequency_days'] ?? 7);
        if ($delay < 0 || $delay > 336) {
            return ['ok' => false, 'error' => 'The delay is between 0 and 336 hours (14 days).'];
        }
        if ($freq < 0 || $freq > 365) {
            return ['ok' => false, 'error' => 'Ask a customer at most once every 0 to 365 days.'];
        }
        $bind = [':n' => $name, ':a' => $active ? 1 : 0, ':q' => cdp_opsJson($clean['questions']),
            ':i' => mb_substr(trim(strip_tags((string) ($d['intro'] ?? ''))), 0, 500), ':t' => mb_substr(trim(strip_tags((string) ($d['thanks'] ?? ''))), 0, 500),
            ':s' => cdp_opsJson($st), ':d' => $delay, ':c' => implode(',', $chs), ':f' => $freq, ':u' => $userId === null ? cdp_opsUid() : (int) $userId, ':now' => cdp_surveyNow()];
        if ($id > 0) {
            $bind[':id'] = $id;
            $r = cdp_opsX("UPDATE cdb_comm_survey_forms SET name = :n, active = :a, questions = :q, intro = :i, thanks = :t, trigger_statuses = :s, delay_hours = :d,
                           channels = :c, frequency_days = :f, updated_by = :u, updated_at = :now WHERE id = :id", $bind);
        } else {
            $bind[':o'] = (int) cdp_opsVal("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM cdb_comm_survey_forms");
            $r = cdp_opsX("INSERT INTO cdb_comm_survey_forms (name, active, questions, intro, thanks, trigger_statuses, delay_hours, channels, frequency_days, sort_order, created_by, updated_by, created_at, updated_at)
                           VALUES (:n, :a, :q, :i, :t, :s, :d, :c, :f, :o, :u, :u, :now, :now)", $bind);
            $id = (int) ($r['id'] ?? 0);
        }
        if (empty($r['ok']) || $id <= 0) {
            return ['ok' => false, 'error' => 'The survey was not saved. Please try again.'];
        }
        if ($active) {
            $rules = [];
            foreach (array_intersect($chs, ['whatsapp', 'sms', 'email']) as $c) {
                $rules[$c] = 1;
            }
            if ($rules) {
                cdp_commSaveRules(['survey' => $rules]);
            }
        }
        return ['ok' => true, 'error' => '', 'id' => $id, 'form' => cdp_surveyForm($id)];
    }

    /** Switch a form on or off. */
    function cdp_surveySetActive($id, $on, $userId = null)
    {
        $f = cdp_surveyForm($id);
        if (!$f) {
            return ['ok' => false, 'error' => 'This survey no longer exists.'];
        }
        if ($on && !$f['channels']) {
            return ['ok' => false, 'error' => 'Choose how this survey is sent (Edit) before switching it on.'];
        }
        $r = cdp_opsX("UPDATE cdb_comm_survey_forms SET active = :a, updated_by = :u, updated_at = :n WHERE id = :id",
            [':a' => $on ? 1 : 0, ':u' => $userId === null ? cdp_opsUid() : (int) $userId, ':n' => cdp_surveyNow(), ':id' => (int) $id]);
        return ['ok' => (bool) $r['ok'], 'error' => $r['ok'] ? '' : 'Not saved.'];
    }

    /**
     * Delete a form. A form that was already sent keeps its answers: it is
     * switched off instead (its results stay on the Results tab).
     */
    function cdp_surveyDeleteForm($id)
    {
        $f = cdp_surveyForm($id);
        if (!$f) {
            return ['ok' => false, 'error' => 'This survey no longer exists.'];
        }
        if ((int) cdp_opsVal("SELECT COUNT(*) FROM cdb_comm_surveys WHERE form_id = :f", [':f' => (int) $id]) > 0) {
            cdp_opsX("UPDATE cdb_comm_survey_forms SET active = 0, updated_at = :n WHERE id = :id", [':n' => cdp_surveyNow(), ':id' => (int) $id]);
            return ['ok' => true, 'error' => '', 'archived' => true];
        }
        $r = cdp_opsX("DELETE FROM cdb_comm_survey_forms WHERE id = :id", [':id' => (int) $id]);
        return ['ok' => (bool) $r['ok'], 'error' => $r['ok'] ? '' : 'Not deleted.', 'archived' => false];
    }

    /** The form a status sends: the first active form listing it (or, listing none, a delivered / picked-up status). */
    function cdp_surveyFormForStatus($statusId)
    {
        $statusId = (int) $statusId;
        $defaults = null;
        foreach (cdp_surveyForms(true) as $f) {
            if ($f['statuses']) {
                if (in_array($statusId, $f['statuses'], true)) {
                    return $f;
                }
                continue;
            }
            $defaults = $defaults ?? cdp_surveyDefaultStatuses();
            if (in_array($statusId, $defaults, true)) {
                return $f;
            }
        }
        return null;
    }

    // -------------------------------------------------------------- sending

    /**
     * Engine hook (helpers/comms.php cdp_commSurveyHook): after a status
     * message for one owner. Creates the survey only when at least one way
     * of asking was queued; idempotent per tracking row and customer.
     *
     * @param object $trackRow cdb_courier_track row (id, order_track, office_id, mod_style ...)
     * @param string[] $queuedChannels channels the status message itself went out on (unused: a survey has its own channels)
     * @return int number of invitations queued (the in-app pop-up counts as one)
     */
    function cdp_commSurveyOnStatus(int $statusId, int $owner, $trackRow, array $queuedChannels): int
    {
        if ($owner <= 0 || $statusId <= 0 || !cdp_surveyEnsure()) {
            return 0;
        }
        $form = cdp_surveyFormForStatus($statusId);
        if (!$form || !$form['channels']) {
            return 0;
        }
        $trackId = (int) ($trackRow->id ?? 0);
        if ($trackId > 0 && cdp_opsOne("SELECT id FROM cdb_comm_surveys WHERE track_id = :t AND user_id = :u", [':t' => $trackId, ':u' => $owner])) {
            return 0;
        }
        if ($form['frequency_days'] > 0 && cdp_opsOne("SELECT id FROM cdb_comm_surveys WHERE user_id = :u AND sent_at >= :d LIMIT 1",
                [':u' => $owner, ':d' => cdp_surveyNow(-86400 * $form['frequency_days'])])) {
            return 0;
        }
        $ref = (string) ($trackRow->order_track ?? '');
        if ($ref !== '' && cdp_opsOne("SELECT id FROM cdb_comm_surveys WHERE user_id = :u AND entity_ref = :r LIMIT 1", [':u' => $owner, ':r' => mb_substr($ref, 0, 80)])) {
            return 0;   // one survey per shipment, whichever status asked first
        }
        // Invitation channels: the form's, unless the "survey" trigger has that channel switched off on Automations.
        $rules = cdp_commRules();
        $send = [];
        foreach (array_intersect($form['channels'], ['whatsapp', 'sms', 'email']) as $c) {
            if (!isset($rules['survey'][$c]) || (int) $rules['survey'][$c] === 1) {
                $send[] = $c;
            }
        }
        $inapp = in_array('inapp', $form['channels'], true);
        if (!$send && !$inapp) {
            return 0;
        }
        $info = $ref !== '' ? cdp_commResolveTrack($ref) : ['type' => '', 'driver_id' => 0, 'order_id' => 0, 'module' => ''];
        $due = time() + 3600 * max(0, $form['delay_hours']);
        $due = cdp_commQuietUntil($due);
        $ins = cdp_opsX("INSERT INTO cdb_comm_surveys (user_id, entity_ref, channel, sent_at, branch_id, form_id, status_id, track_id, entity_type, order_id, rider_id)
                         VALUES (:u, :r, :c, :s, :b, :f, :st, :t, :et, :o, :rd)",
            [':u' => $owner, ':r' => $ref !== '' ? mb_substr($ref, 0, 80) : null, ':c' => $send[0] ?? 'inapp', ':s' => cdp_surveyClock($due),
                ':b' => (int) ($trackRow->office_id ?? 0) ?: null, ':f' => $form['id'], ':st' => $statusId, ':t' => $trackId ?: null,
                ':et' => (string) ($info['type'] ?? '') ?: null, ':o' => (int) ($info['order_id'] ?? 0) ?: null, ':rd' => (int) ($info['driver_id'] ?? 0) ?: null]);
        $sid = (int) ($ins['id'] ?? 0);
        if ($sid <= 0) {
            return 0;   // a concurrent pass created it (uq_track)
        }
        $queued = 0;
        $done = [];
        if ($send) {
            $vars = ['tracking' => $ref, 'track_url' => cdp_commTrackUrl($ref), 'survey_url' => cdp_surveyLink($sid)];
            $q = cdp_commQueueEvent('survey', $owner, $vars, ['entity_type' => ($info['type'] ?? '') ?: 'shipment', 'entity_ref' => $ref,
                'dedupe' => 'trk' . $trackId . ':' . $owner . ':survey', 'channels' => $send, 'send_after' => $due]);
            $queued = (int) $q['queued'];
            foreach ($send as $c) {
                if (!isset($q['skipped'][$c])) {
                    $done[] = $c;
                }
            }
        }
        if ($queued === 0 && !$inapp) {
            cdp_opsX("DELETE FROM cdb_comm_surveys WHERE id = :id AND responded_at IS NULL", [':id' => $sid]);
            return 0;
        }
        cdp_opsX("UPDATE cdb_comm_surveys SET channel = :c WHERE id = :id", [':c' => $done[0] ?? 'inapp', ':id' => $sid]);
        return $queued + ($inapp ? 1 : 0);
    }

    /**
     * A timestamp as the app's wall clock (cdb_settings.timezone), the clock
     * pages, cdp_surveyNow() in a request and the outbox's send_after use. The
     * worker runs in the server's zone until something loads Core, so a bare
     * date() would put an in-app-only survey hours off.
     */
    function cdp_surveyNow($offsetSeconds = 0)
    {
        return cdp_surveyClock(time() + (int) $offsetSeconds);
    }

    function cdp_surveyClock($ts)
    {
        static $tz = null;
        if ($tz === null) {
            $tz = '';
            try {
                $tz = (string) cdp_opsVal("SELECT timezone FROM cdb_settings LIMIT 1");
            } catch (Throwable $e) {
                $tz = '';
            }
        }
        try {
            return (new DateTime('@' . (int) $ts))->setTimezone(new DateTimeZone($tz !== '' ? $tz : date_default_timezone_get()))->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return date('Y-m-d H:i:s', (int) $ts);
        }
    }

    /**
     * Worker pass (helpers/comms.php cdp_commRunWorker). Invitations wait in
     * the outbox until their send_after, so nothing is sent from here; the pass
     * only reports what is open.
     */
    function cdp_commSurveyTick()
    {
        if (!cdp_surveyEnsure()) {
            return ['open' => 0];
        }
        $s = cdp_surveySettings();
        return ['open' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_comm_surveys WHERE responded_at IS NULL AND sent_at BETWEEN :f AND :n",
            [':f' => cdp_surveyNow(-86400 * $s['window_days']), ':n' => cdp_surveyNow()])];
    }

    /** The public link of a survey (survey.php, signed for the open window). */
    function cdp_surveyLink($surveyId)
    {
        $days = cdp_surveySettings()['window_days'];
        return cdp_opsSiteUrl() . '/survey.php?t=' . rawurlencode(cdp_opsSign('survey', (int) $surveyId, max(1, $days)));
    }

    // ------------------------------------------------------- the pop-up

    /** The survey to ask this customer about now, or null. Never throws. */
    function cdp_surveyPending($userId)
    {
        try {
            if ((int) $userId <= 0 || !cdp_surveyEnsure()) {
                return null;
            }
            $s = cdp_surveySettings();
            if (!$s['popup']) {
                return null;
            }
            // One pop-up per customer per gap, however many surveys are waiting.
            if (cdp_opsOne("SELECT id FROM cdb_comm_surveys WHERE user_id = :u AND last_prompted_at > :g LIMIT 1",
                    [':u' => (int) $userId, ':g' => cdp_surveyNow(-3600 * $s['gap_hours'])])) {
                return null;
            }
            $r = cdp_opsOne("SELECT s.* FROM cdb_comm_surveys s LEFT JOIN cdb_comm_survey_forms f ON f.id = s.form_id
                             WHERE s.user_id = :u AND s.responded_at IS NULL AND s.sent_at <= :n AND s.sent_at >= :w
                               AND s.prompted_count < :m AND (s.last_prompted_at IS NULL OR s.last_prompted_at <= :g)
                               AND (s.form_id IS NULL OR f.id IS NULL OR f.channels LIKE '%inapp%')
                             ORDER BY s.sent_at DESC LIMIT 1",
                [':u' => (int) $userId, ':n' => cdp_surveyNow(), ':w' => cdp_surveyNow(-86400 * $s['window_days']), ':m' => $s['max_prompts'], ':g' => cdp_surveyNow(-3600 * $s['gap_hours'])]);
            return $r ?: null;
        } catch (Throwable $e) {
            error_log('survey pending: ' . $e->getMessage());
            return null;
        }
    }

    /** The pop-up was shown. */
    function cdp_surveyMarkPrompted($surveyId)
    {
        cdp_opsX("UPDATE cdb_comm_surveys SET prompted_count = prompted_count + 1, last_prompted_at = :n WHERE id = :id AND responded_at IS NULL",
            [':n' => cdp_surveyNow(), ':id' => (int) $surveyId]);
    }

    /** "Ask me later" (or, with $never, "Don't ask again"). */
    function cdp_surveyDismiss($surveyId, $never = false)
    {
        $max = cdp_surveySettings()['max_prompts'];
        $r = cdp_opsX("UPDATE cdb_comm_surveys SET dismissed_at = :n, last_prompted_at = :n" . ($never ? ', prompted_count = GREATEST(prompted_count, :m)' : '')
            . " WHERE id = :id AND responded_at IS NULL", $never ? [':n' => cdp_surveyNow(), ':m' => $max, ':id' => (int) $surveyId] : [':n' => cdp_surveyNow(), ':id' => (int) $surveyId]);
        return (bool) $r['ok'];
    }

    // ----------------------------------------------------------- answering

    /**
     * Save the answers of one survey (public link, pop-up). $answers is
     * [question key => value]: a number for rating / nps, an option for
     * choice, a list of options for multi, text for text. Answering again
     * within the window replaces the earlier answers.
     *
     * @return array{ok:bool,error:string,errors:array<string,string>,thanks?:string}
     */
    function cdp_surveySubmit($surveyId, array $answers)
    {
        if (!cdp_surveyEnsure()) {
            return ['ok' => false, 'error' => 'Surveys are not available right now.', 'errors' => []];
        }
        $s = cdp_opsOne("SELECT * FROM cdb_comm_surveys WHERE id = :id", [':id' => (int) $surveyId]);
        if (!$s) {
            return ['ok' => false, 'error' => 'This survey is no longer open.', 'errors' => []];
        }
        $form = cdp_surveyFormFor($s);
        $errors = [];
        $rows = [];
        $score = null;
        $nps = null;
        $comment = null;
        foreach ($form['questions'] as $q) {
            $k = $q['key'];
            $v = $answers[$k] ?? null;
            $empty = $v === null || $v === '' || (is_array($v) && !array_filter($v, 'strlen'));
            if ($empty) {
                if ($q['required']) {
                    $errors[$k] = $q['type'] === 'text' ? 'Please write a short answer.' : 'Please choose an answer.';
                }
                continue;
            }
            switch ($q['type']) {
                case 'rating':
                case 'nps':
                    $n = is_array($v) ? '' : trim((string) $v);
                    $lo = $q['type'] === 'nps' ? 0 : 1;
                    $hi = $q['type'] === 'nps' ? 10 : 5;
                    if (!preg_match('/^\d{1,2}$/', $n) || (int) $n < $lo || (int) $n > $hi) {
                        $errors[$k] = 'Choose a number from ' . $lo . ' to ' . $hi . '.';
                        break;
                    }
                    $rows[] = [$q, (int) $n, null];
                    if ($q['type'] === 'rating' && $score === null) {
                        $score = (int) $n;
                    }
                    if ($q['type'] === 'nps' && $nps === null) {
                        $nps = (int) $n;
                    }
                    break;
                case 'choice':
                    $o = is_array($v) ? '' : (string) $v;
                    if (!in_array($o, $q['options'], true)) {
                        $errors[$k] = 'Choose one of the answers shown.';
                        break;
                    }
                    $rows[] = [$q, null, $o];
                    break;
                case 'multi':
                    $picked = array_values(array_intersect($q['options'], array_map('strval', (array) $v)));
                    if (!$picked) {
                        $errors[$k] = 'Choose from the answers shown.';
                        break;
                    }
                    foreach ($picked as $o) {
                        $rows[] = [$q, null, $o];
                    }
                    break;
                default:
                    $t = mb_substr(trim(is_array($v) ? '' : (string) $v), 0, 2000);
                    if ($t === '') {
                        if ($q['required']) {
                            $errors[$k] = 'Please write a short answer.';
                        }
                        break;
                    }
                    $rows[] = [$q, null, $t];
                    if ($comment === null) {
                        $comment = $t;
                    }
            }
        }
        if ($errors) {
            return ['ok' => false, 'error' => 'Please answer the questions marked.', 'errors' => $errors];
        }
        if (!$rows) {
            return ['ok' => false, 'error' => 'Please answer at least one question.', 'errors' => []];
        }
        cdp_opsX("DELETE FROM cdb_comm_survey_answers WHERE survey_id = :s", [':s' => (int) $s->id]);
        $now = cdp_surveyNow();
        foreach ($rows as [$q, $num, $text]) {
            cdp_opsX("INSERT INTO cdb_comm_survey_answers (survey_id, form_id, question_key, question_type, question_label, value_num, value_text, created_at)
                      VALUES (:s, :f, :k, :t, :l, :n, :v, :c)",
                [':s' => (int) $s->id, ':f' => $form['id'] ?: null, ':k' => $q['key'], ':t' => $q['type'], ':l' => mb_substr($q['label'], 0, 200), ':n' => $num, ':v' => $text, ':c' => $now]);
        }
        cdp_opsX("UPDATE cdb_comm_surveys SET score = :sc, nps = :np, comment = :cm, responded_at = :n, form_id = COALESCE(form_id, :f) WHERE id = :id",
            [':sc' => $score, ':np' => $nps, ':cm' => $comment, ':n' => $now, ':f' => $form['id'] ?: null, ':id' => (int) $s->id]);
        if (($score !== null && $score <= 2) || ($nps !== null && $nps <= 4)) {
            cdp_surveyFlagUnhappy($s, $score, $nps, $comment);
        }
        return ['ok' => true, 'error' => '', 'errors' => [], 'thanks' => $form['thanks'] !== '' ? $form['thanks'] : 'Thank you! Your feedback helps us serve you better.'];
    }

    /** A low score: tell the staff through the activity log (Customer Care picks it up there). */
    function cdp_surveyFlagUnhappy($s, $score, $nps, $comment)
    {
        try {
            if (is_file(__DIR__ . '/activity_log.php')) {
                require_once __DIR__ . '/activity_log.php';
                if (function_exists('cdp_activityLog')) {
                    cdp_activityLog(['module' => 'notifications', 'verb' => 'update', 'action' => 'surveys.low_score', 'outcome' => 'warn',
                        'entity_type' => 'survey', 'entity_id' => (int) $s->id, 'entity_label' => (string) ($s->entity_ref ?? ''),
                        'summary' => 'Low survey score' . ($score !== null ? ' ' . $score . '/5' : '') . ($nps !== null ? ' (recommend ' . $nps . '/10)' : '')
                            . ($comment ? ': ' . mb_substr($comment, 0, 120) : '')]);
                }
            }
        } catch (Throwable $e) {
            error_log('survey low score: ' . $e->getMessage());
        }
    }

    /**
     * The questions as form controls (public page, pop-up, staff preview).
     * Names: a[<key>] (multi: a[<key>][]). Values from $values. Escaped.
     */
    function cdp_surveyQuestionsHtml(array $questions, array $values = [], $idPrefix = 'sq', array $errors = [])
    {
        $e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
        $out = '';
        foreach ($questions as $i => $q) {
            $k = $q['key'];
            $id = $idPrefix . '-' . $k;
            $name = 'a[' . $k . ']';
            $req = !empty($q['required']);
            $v = $values[$k] ?? null;
            $err = $errors[$k] ?? '';
            $legend = '<legend id="' . $e($id) . '-l"><span class="sq-n">' . ($i + 1) . '.</span> ' . $e($q['label']) . ($req ? ' <span class="sq-req" aria-hidden="true">*</span><span class="sq-vh"> (required)</span>' : '') . '</legend>';
            $errHtml = '<p class="sq-err" id="' . $e($id) . '-e" data-sq-err="' . $e($k) . '"' . ($err === '' ? ' hidden' : '') . ' role="alert">' . $e($err) . '</p>';
            $out .= '<fieldset class="sq-q sq-' . $e($q['type']) . '" data-sq="' . $e($k) . '" data-sq-type="' . $e($q['type']) . '"' . ($req ? ' data-sq-req' : '') . ' aria-describedby="' . $e($id) . '-e">' . $legend;
            switch ($q['type']) {
                case 'rating':
                case 'nps':
                    $lo = $q['type'] === 'nps' ? 0 : 1;
                    $hi = $q['type'] === 'nps' ? 10 : 5;
                    $out .= '<div class="sq-scale" role="radiogroup" aria-labelledby="' . $e($id) . '-l">';
                    for ($n = $lo; $n <= $hi; $n++) {
                        $out .= '<label class="sq-opt"><input type="radio" name="' . $e($name) . '" value="' . $n . '"' . ((string) $v === (string) $n ? ' checked' : '') . ($req ? ' required' : '')
                            . '><span' . ($q['type'] === 'rating' ? ' aria-label="' . $n . ' of 5"' : '') . '>' . ($q['type'] === 'rating' ? '<i aria-hidden="true">★</i>' . $n : $n) . '</span></label>';
                    }
                    $out .= '</div><div class="sq-ends" aria-hidden="true"><span>' . ($q['type'] === 'nps' ? 'Not likely' : 'Poor') . '</span><span>' . ($q['type'] === 'nps' ? 'Very likely' : 'Excellent') . '</span></div>';
                    break;
                case 'choice':
                case 'multi':
                    $multi = $q['type'] === 'multi';
                    $vals = array_map('strval', (array) $v);
                    $out .= '<div class="sq-list">';
                    foreach ($q['options'] as $o) {
                        $out .= '<label class="sq-li"><input type="' . ($multi ? 'checkbox' : 'radio') . '" name="' . $e($name . ($multi ? '[]' : '')) . '" value="' . $e($o) . '"'
                            . (in_array($o, $vals, true) ? ' checked' : '') . ($req && !$multi ? ' required' : '') . '><span>' . $e($o) . '</span></label>';
                    }
                    $out .= '</div>' . ($multi ? '<p class="sq-hint">Tick all that apply.</p>' : '');
                    break;
                default:
                    $out .= '<textarea class="sq-text" name="' . $e($name) . '" rows="3" maxlength="2000" aria-labelledby="' . $e($id) . '-l"' . ($req ? ' required' : '') . '>' . $e(is_array($v) ? '' : (string) $v) . '</textarea>';
            }
            $out .= $errHtml . '</fieldset>';
        }
        return $out;
    }

    /** Styles of the question controls (public page and pop-up share them; colours from swiftlane.css tokens). */
    function cdp_surveyQuestionsCss()
    {
        return '.sq-q{border:0;padding:0;margin:0 0 18px;min-width:0}.sq-q legend{font-weight:600;font-size:16px;line-height:1.4;padding:0;margin:0 0 10px;color:var(--ink)}'
            . '.sq-n{color:var(--muted);font-weight:500}.sq-req{color:var(--red-ink)}.sq-vh{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}'
            . '.sq-scale{display:flex;gap:6px;flex-wrap:wrap}.sq-opt{position:relative;margin:0!important;display:inline-flex}.sq-opt input,.sq-li input{position:absolute;opacity:0;width:1px;height:1px}'
            . '.sq-opt span{display:inline-flex;align-items:center;justify-content:center;gap:4px;min-width:44px;height:44px;padding:0 10px;border-radius:var(--r-sm,10px);border:1px solid var(--line-2);background:var(--field,var(--card));color:var(--ink);font:500 15px var(--font);cursor:pointer;transition:background .12s,border-color .12s}'
            . '.sq-opt span i{font-style:normal;color:var(--amber-ink,#b7791f)}.sq-opt input:checked+span{background:var(--ink);border-color:var(--ink);color:var(--inverse)}.sq-opt input:checked+span i{color:inherit}'
            . '.sq-opt input:focus-visible+span,.sq-li input:focus-visible+span{outline:2px solid var(--violet,#6d5bd0);outline-offset:2px}.sq-opt span:hover,.sq-li span:hover{border-color:var(--ink-3,var(--muted))}'
            . '.sq-nps .sq-opt span{min-width:38px;padding:0 6px}.sq-ends{display:flex;justify-content:space-between;color:var(--muted);font-size:13px;margin-top:6px;max-width:560px}'
            . '.sq-list{display:flex;flex-direction:column;gap:6px}.sq-li{position:relative;margin:0!important;display:block;font-weight:400!important}'
            . '.sq-li span{display:flex;align-items:center;gap:10px;min-height:44px;padding:8px 12px;border-radius:var(--r-sm,10px);border:1px solid var(--line-2);background:var(--field,var(--card));color:var(--ink);font:400 15px var(--font);cursor:pointer}'
            . '.sq-li span:before{content:"";flex:none;width:18px;height:18px;border:1.5px solid var(--line-2);border-radius:50%;background:var(--card)}.sq-multi .sq-li span:before{border-radius:5px}'
            . '.sq-li input:checked+span{border-color:var(--ink)}.sq-li input:checked+span:before{background:var(--ink);border-color:var(--ink);box-shadow:inset 0 0 0 3px var(--card)}'
            . '.sq-text{width:100%;box-sizing:border-box;border:1px solid var(--line-2);border-radius:var(--r-sm,10px);background:var(--field,var(--card));padding:10px 12px;font:400 16px var(--font);color:var(--ink);resize:vertical}'
            . '.sq-hint{color:var(--muted);font-size:13px;margin:6px 0 0}.sq-err{color:var(--red-ink);font-size:14px;margin:6px 0 0}';
    }

    // -------------------------------------------------------------- results

    /** Date range filter from the request (defaults: last 30 days). */
    function cdp_surveyRange($from, $to, $days = 30)
    {
        $t = cdp_opsDate($to, date('Y-m-d'));
        $f = cdp_opsDate($from, date('Y-m-d', strtotime($t . ' -' . max(0, (int) $days - 1) . ' days')));
        if ($f > $t) {
            [$f, $t] = [$t, $f];
        }
        return [$f, $t];
    }

    /**
     * The Results tab: KPIs, trend, per-question breakdown, by rider and
     * branch, comments and low scores. $formId 0 = every form.
     */
    function cdp_surveyResults($from, $to, $formId = 0)
    {
        $out = ['kpi' => ['sent' => 0, 'answered' => 0, 'rate' => null, 'avg' => null, 'csat' => null, 'nps' => null, 'nps_n' => 0, 'unhappy' => 0, 'waiting' => 0, 'popup' => 0],
            'trend' => [], 'trend_unit' => 'day', 'questions' => [], 'riders' => [], 'branches' => [], 'comments' => [], 'low' => []];
        if (!cdp_surveyEnsure()) {
            return $out;
        }
        $f = $from . ' 00:00:00';
        $t = $to . ' 23:59:59';
        $now = cdp_surveyNow();
        $fw = $formId > 0 ? ' AND s.form_id = :fm' : '';
        $b = [':f' => $f, ':t' => $t, ':now' => $now] + ($formId > 0 ? [':fm' => (int) $formId] : []);
        $k = cdp_opsOne("SELECT SUM(s.sent_at <= :now) sent, SUM(s.responded_at IS NOT NULL) answered, AVG(s.score) avg_score,
                                SUM(s.score >= 4) happy, COUNT(s.score) scored, SUM(s.score <= 2) unhappy,
                                SUM(s.nps >= 9) prom, SUM(s.nps <= 6) detr, COUNT(s.nps) nps_n,
                                SUM(s.responded_at IS NULL AND s.sent_at <= :now) waiting, SUM(s.prompted_count > 0) popup
                         FROM cdb_comm_surveys s WHERE s.sent_at BETWEEN :f AND :t$fw", $b);
        $sent = (int) ($k->sent ?? 0);
        $ans = (int) ($k->answered ?? 0);
        $scored = (int) ($k->scored ?? 0);
        $npsN = (int) ($k->nps_n ?? 0);
        $out['kpi'] = ['sent' => $sent, 'answered' => $ans, 'rate' => $sent ? round(100 * $ans / $sent, 1) : null,
            'avg' => $k && $k->avg_score !== null ? round((float) $k->avg_score, 2) : null, 'csat' => $scored ? round(100 * (int) $k->happy / $scored, 1) : null,
            'nps' => $npsN ? (int) round(100 * ((int) $k->prom - (int) $k->detr) / $npsN) : null, 'nps_n' => $npsN,
            'unhappy' => (int) ($k->unhappy ?? 0), 'waiting' => (int) ($k->waiting ?? 0), 'popup' => (int) ($k->popup ?? 0)];

        // Trend: by day up to 45 days, else by week.
        $days = (int) round((strtotime($to) - strtotime($from)) / 86400) + 1;
        $weekly = $days > 45;
        $out['trend_unit'] = $weekly ? 'week' : 'day';
        $grp = $weekly ? "DATE_SUB(DATE(s.sent_at), INTERVAL WEEKDAY(s.sent_at) DAY)" : "DATE(s.sent_at)";
        $rows = cdp_opsQ("SELECT $grp d, SUM(s.sent_at <= :now) sent, SUM(s.responded_at IS NOT NULL) answered, AVG(s.score) avg_score
                          FROM cdb_comm_surveys s WHERE s.sent_at BETWEEN :f AND :t$fw GROUP BY d ORDER BY d", $b);
        $by = [];
        foreach ($rows as $r) {
            $by[(string) $r->d] = $r;
        }
        $start = strtotime($from);
        if ($weekly) {
            $start = strtotime('-' . ((int) date('N', $start) - 1) . ' days', $start);
        }
        for ($ts = $start; $ts <= strtotime($to); $ts = strtotime($weekly ? '+7 days' : '+1 day', $ts)) {
            $d = date('Y-m-d', $ts);
            $r = $by[$d] ?? null;
            $out['trend'][] = ['d' => $d, 'label' => $weekly ? 'Week of ' . date('M j', $ts) : date('M j', $ts), 'sent' => (int) ($r->sent ?? 0), 'answered' => (int) ($r->answered ?? 0),
                'avg' => $r && $r->avg_score !== null ? round((float) $r->avg_score, 2) : null];
            if (count($out['trend']) > 120) {
                break;
            }
        }

        // Per question (answers keep the label and type they were asked with).
        $aw = $formId > 0 ? ' AND a.form_id = :fm' : '';
        $ab = [':f' => $f, ':t' => $t] + ($formId > 0 ? [':fm' => (int) $formId] : []);
        $qrows = cdp_opsQ("SELECT a.form_id, a.question_key, a.question_type, MAX(a.question_label) label, a.value_num, a.value_text, COUNT(*) n
                           FROM cdb_comm_survey_answers a JOIN cdb_comm_surveys s ON s.id = a.survey_id
                           WHERE s.responded_at BETWEEN :f AND :t$aw AND a.question_type <> 'text'
                           GROUP BY a.form_id, a.question_key, a.question_type, a.value_num, a.value_text", $ab);
        $forms = [];
        foreach (cdp_surveyForms() as $fm) {
            $forms[$fm['id']] = $fm;
        }
        $qs = [];
        foreach ($qrows as $r) {
            $id = (int) $r->form_id . ':' . $r->question_key;
            if (!isset($qs[$id])) {
                $fm = $forms[(int) $r->form_id] ?? null;
                $order = 99;
                $label = (string) $r->label;
                if ($fm) {
                    foreach ($fm['questions'] as $i => $q) {
                        if ($q['key'] === $r->question_key) {
                            $order = $i;
                            $label = $q['label'];
                        }
                    }
                }
                $qs[$id] = ['form' => $fm ? $fm['name'] : 'Earlier survey', 'form_id' => (int) $r->form_id, 'key' => (string) $r->question_key, 'type' => (string) $r->question_type,
                    'label' => $label, 'order' => $order, 'n' => 0, 'dist' => [], 'avg' => null, 'sum' => 0];
            }
            $val = $r->value_num !== null ? (string) (int) $r->value_num : (string) $r->value_text;
            $qs[$id]['dist'][$val] = ($qs[$id]['dist'][$val] ?? 0) + (int) $r->n;
            $qs[$id]['n'] += (int) $r->n;
            if ($r->value_num !== null) {
                $qs[$id]['sum'] += (int) $r->value_num * (int) $r->n;
            }
        }
        $textN = cdp_opsQ("SELECT a.form_id, a.question_key, MAX(a.question_label) label, COUNT(*) n FROM cdb_comm_survey_answers a JOIN cdb_comm_surveys s ON s.id = a.survey_id
                           WHERE s.responded_at BETWEEN :f AND :t$aw AND a.question_type = 'text' GROUP BY a.form_id, a.question_key", $ab);
        foreach ($textN as $r) {
            $fm = $forms[(int) $r->form_id] ?? null;
            $order = 99;
            foreach ($fm ? $fm['questions'] : [] as $i => $q) {
                if ($q['key'] === $r->question_key) {
                    $order = $i;
                }
            }
            $qs[(int) $r->form_id . ':' . $r->question_key] = ['form' => $fm ? $fm['name'] : 'Earlier survey', 'form_id' => (int) $r->form_id, 'key' => (string) $r->question_key,
                'type' => 'text', 'label' => (string) $r->label, 'order' => $order, 'n' => (int) $r->n, 'dist' => [], 'avg' => null, 'sum' => 0];
        }
        foreach ($qs as &$q) {
            if (in_array($q['type'], ['rating', 'nps'], true) && $q['n']) {
                $q['avg'] = round($q['sum'] / $q['n'], 2);
                $full = [];
                for ($i = $q['type'] === 'nps' ? 0 : 1; $i <= ($q['type'] === 'nps' ? 10 : 5); $i++) {
                    $full[(string) $i] = $q['dist'][(string) $i] ?? 0;
                }
                $q['dist'] = $full;
                if ($q['type'] === 'nps') {
                    $p = 0;
                    $dt = 0;
                    foreach ($full as $v => $c) {
                        if ((int) $v >= 9) {
                            $p += $c;
                        } elseif ((int) $v <= 6) {
                            $dt += $c;
                        }
                    }
                    $q['nps'] = (int) round(100 * ($p - $dt) / $q['n']);
                }
            } elseif (in_array($q['type'], ['choice', 'multi'], true)) {
                $fm = $forms[$q['form_id']] ?? null;
                $full = [];
                foreach ($fm ? $fm['questions'] : [] as $fq) {
                    if ($fq['key'] === $q['key']) {
                        foreach ($fq['options'] as $o) {
                            $full[$o] = 0;
                        }
                    }
                }
                foreach ($q['dist'] as $o => $c) {
                    $full[(string) $o] = $c;
                }
                arsort($full);
                $q['dist'] = $full;
                if ($q['type'] === 'multi') {
                    $q['people'] = (int) cdp_opsVal("SELECT COUNT(DISTINCT a.survey_id) FROM cdb_comm_survey_answers a JOIN cdb_comm_surveys s ON s.id = a.survey_id
                        WHERE s.responded_at BETWEEN :f AND :t AND a.form_id <=> :fi AND a.question_key = :k", [':f' => $f, ':t' => $t, ':fi' => $q['form_id'] ?: null, ':k' => $q['key']]);
                }
            }
            unset($q['sum']);
        }
        unset($q);
        $list = array_values($qs);
        usort($list, function ($a, $b) { return [$a['form_id'], $a['order'], $a['key']] <=> [$b['form_id'], $b['order'], $b['key']]; });
        $out['questions'] = $list;

        // By rider and by branch.
        $out['riders'] = array_map(function ($r) {
            return ['id' => (int) $r->rider_id, 'name' => trim((string) $r->name) ?: 'Rider #' . (int) $r->rider_id, 'sent' => (int) $r->sent, 'answered' => (int) $r->answered,
                'avg' => $r->avg_score !== null ? round((float) $r->avg_score, 2) : null, 'low' => (int) $r->low];
        }, cdp_opsQ("SELECT s.rider_id, TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) name, COUNT(*) sent, SUM(s.responded_at IS NOT NULL) answered, AVG(s.score) avg_score, SUM(s.score <= 2) low
                     FROM cdb_comm_surveys s LEFT JOIN cdb_users u ON u.id = s.rider_id
                     WHERE s.sent_at BETWEEN :f AND :t AND s.sent_at <= :now AND s.rider_id IS NOT NULL$fw GROUP BY s.rider_id, name ORDER BY answered DESC, sent DESC LIMIT 30", $b));
        $out['branches'] = array_map(function ($r) {
            return ['id' => (int) $r->branch_id, 'name' => trim((string) $r->name) ?: ((int) $r->branch_id ? 'Branch #' . (int) $r->branch_id : 'No branch recorded'), 'sent' => (int) $r->sent,
                'answered' => (int) $r->answered, 'avg' => $r->avg_score !== null ? round((float) $r->avg_score, 2) : null, 'low' => (int) $r->low];
        }, cdp_opsQ("SELECT COALESCE(s.branch_id, 0) branch_id, MAX(o.name_off) name, COUNT(*) sent, SUM(s.responded_at IS NOT NULL) answered, AVG(s.score) avg_score, SUM(s.score <= 2) low
                     FROM cdb_comm_surveys s LEFT JOIN cdb_offices o ON o.id = s.branch_id
                     WHERE s.sent_at BETWEEN :f AND :t AND s.sent_at <= :now$fw GROUP BY COALESCE(s.branch_id, 0) ORDER BY answered DESC, sent DESC LIMIT 30", $b));

        // Comments and low scores.
        $out['comments'] = cdp_surveyResponses($from, $to, $formId, 'comments', 40);
        $out['low'] = cdp_surveyResponses($from, $to, $formId, 'low', 40);
        return $out;
    }

    /**
     * Answered surveys for lists and the export.
     * $which: all | comments (with a written answer) | low (rating 1-2 or recommend 0-4)
     */
    function cdp_surveyResponses($from, $to, $formId = 0, $which = 'all', $limit = 500)
    {
        if (!cdp_surveyEnsure()) {
            return [];
        }
        $w = $which === 'comments' ? " AND s.comment IS NOT NULL AND s.comment <> ''" : ($which === 'low' ? ' AND (s.score <= 2 OR s.nps <= 4)' : '');
        $b = [':f' => $from . ' 00:00:00', ':t' => $to . ' 23:59:59'];
        if ($formId > 0) {
            $w .= ' AND s.form_id = :fm';
            $b[':fm'] = (int) $formId;
        }
        $rows = cdp_opsQ("SELECT s.id, s.user_id, s.entity_ref, s.entity_type, s.order_id, s.channel, s.score, s.nps, s.comment, s.sent_at, s.responded_at, s.form_id, s.rider_id, s.branch_id,
                                 TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) customer, u.locker, f.name form_name, o.name_off branch,
                                 TRIM(CONCAT(COALESCE(r.fname,''),' ',COALESCE(r.lname,''))) rider
                          FROM cdb_comm_surveys s LEFT JOIN cdb_users u ON u.id = s.user_id LEFT JOIN cdb_comm_survey_forms f ON f.id = s.form_id
                          LEFT JOIN cdb_offices o ON o.id = s.branch_id LEFT JOIN cdb_users r ON r.id = s.rider_id
                          WHERE s.responded_at BETWEEN :f AND :t$w ORDER BY s.responded_at DESC LIMIT " . max(1, min(5000, (int) $limit)), $b);
        return array_map(function ($r) {
            return ['id' => (int) $r->id, 'user_id' => (int) $r->user_id, 'customer' => trim((string) $r->customer) ?: 'Customer #' . (int) $r->user_id, 'locker' => (string) ($r->locker ?? ''),
                'ref' => (string) ($r->entity_ref ?? ''), 'entity_type' => (string) ($r->entity_type ?? ''), 'order_id' => (int) ($r->order_id ?? 0), 'channel' => (string) ($r->channel ?? ''),
                'score' => $r->score !== null ? (int) $r->score : null, 'nps' => $r->nps !== null ? (int) $r->nps : null, 'comment' => (string) ($r->comment ?? ''),
                'sent_at' => (string) $r->sent_at, 'responded_at' => (string) $r->responded_at, 'form' => (string) ($r->form_name ?? ''), 'rider' => trim((string) $r->rider),
                'branch' => (string) ($r->branch ?? '')];
        }, $rows);
    }

    /** Every answer of one survey (the Results row detail and the export). */
    function cdp_surveyAnswers($surveyId)
    {
        return cdp_opsQ("SELECT question_key, question_type, question_label, value_num, value_text FROM cdb_comm_survey_answers WHERE survey_id = :s ORDER BY id", [':s' => (int) $surveyId]);
    }

    /** Average rating answered in a period (management reports). */
    function cdp_surveyScoreAvg($from, $to)
    {
        if (!cdp_opsTableExists('cdb_comm_surveys')) {
            return ['avg' => null, 'unhappy' => 0, 'answered' => 0];
        }
        $s = cdp_opsOne("SELECT AVG(score) a, SUM(score <= 2) bad, COUNT(score) n FROM cdb_comm_surveys WHERE responded_at BETWEEN :f AND :t", [':f' => $from, ':t' => $to]);
        return ['avg' => $s && $s->a !== null ? round((float) $s->a, 2) : null, 'unhappy' => (int) ($s->bad ?? 0), 'answered' => (int) ($s->n ?? 0)];
    }

    /** Staff link to the record a survey is about. */
    function cdp_surveyRecordLink($type, $orderId)
    {
        if ((int) $orderId <= 0 || !function_exists('sl_route')) {
            return '';
        }
        $boards = ['shipment' => 'ShipmentDetail', 'package' => 'PackageDetail', 'consolidation' => 'ConsolidationDetail'];
        return isset($boards[$type]) ? sl_route($boards[$type], ['id' => (int) $orderId]) : '';
    }
}
