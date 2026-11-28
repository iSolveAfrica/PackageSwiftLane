<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************

/**
 * AI writing for every Communications page, through the AI assistant
 * (King): one entry point, cdp_commAiDraft(), and the same validation the
 * Notification Studio uses (helpers/notify_studio.php cdp_notifyAi*):
 * only the placeholders that trigger really fills survive, an SMS is
 * measured with real values (one retry when it is longer than 160
 * characters), WhatsApp keeps *bold* and loses markdown, an e-mail comes
 * back as checked builder blocks (plus the drag-and-drop canvas design),
 * button links can only be the tracking / payment / survey / preference
 * links.
 *
 * Nothing here saves or sends: callers show the result as a proposal the
 * person accepts, edits, retries or discards.
 *
 * The model is called with cdp_aiComplete() (helpers/ai_assistant_llm.php).
 * Not set up: ['ok' => false, 'code' => 'not_configured', 'error' => ..., 'setup_url' => ...].
 * The signed-in user's role may not use King: code 'not_allowed'.
 */

require_once __DIR__ . '/ops_db.php';
require_once __DIR__ . '/comms_triggers.php';

if (!function_exists('cdp_commAiDraft')) {

    /** Tasks cdp_commAiDraft() understands. */
    function cdp_commAiTasks()
    {
        return [
            'trigger_message' => 'Write the message an automation sends (status or event), for one channel.',
            'status_message' => 'Write the message a status sends, for one channel (context status_id or key).',
            'campaign' => 'Write a marketing campaign message for one channel.',
            'subject_lines' => 'Suggest e-mail subject lines.',
            'survey_questions' => 'Suggest the questions of a satisfaction survey.',
            'whatsapp_reply' => 'Suggest a reply in a WhatsApp conversation.',
            'explain_failure' => 'Explain why a message was not delivered and what to do.',
        ];
    }

    /** The assistant's name (King by default). */
    function cdp_commAiName()
    {
        return function_exists('cdp_kingName') ? cdp_kingName() : 'King';
    }

    /**
     * Can AI writing be offered to $user now? For the pages: show the AI
     * buttons, or a clean "not set up" note with the link to the AI centre.
     *
     * @return array{ok:bool,code:string,message:string,setup_url:string,assistant:string}
     */
    function cdp_commAiAvailable($user = null)
    {
        $king = cdp_commAiName();
        $setup = function_exists('sl_route') ? sl_route('AiCenter') : 'ai_center.php';
        $out = ['ok' => false, 'code' => 'not_configured', 'message' => '', 'setup_url' => $setup, 'assistant' => $king];
        if (PHP_SAPI === 'cli' && isset($GLOBALS['cdp_commAiTestComplete']) && is_callable($GLOBALS['cdp_commAiTestComplete'])) {
            return ['ok' => true, 'code' => '', 'message' => ''] + $out;
        }
        try {
            require_once __DIR__ . '/ai_assistant.php';
        } catch (Throwable $e) {
            $out['message'] = $king . ' is not available on this installation.';
            return $out;
        }
        if (!function_exists('cdp_aiConfigured') || !cdp_aiConfigured()) {
            $out['message'] = $king . ' is not set up yet. A super admin switches it on and adds an AI provider key in the AI centre.';
            return $out;
        }
        $user = $user ?? ($GLOBALS['user'] ?? null);
        if (is_object($user) && !empty($user->logged_in) && function_exists('cdp_aiCanUse') && !cdp_aiCanUse($user)) {
            return ['ok' => false, 'code' => 'not_allowed', 'message' => 'Your role cannot use ' . $king . '. The AI centre sets who may use it.'] + $out;
        }
        return ['ok' => true, 'code' => '', 'message' => ''] + $out;
    }

    /**
     * One model call. In a CLI test run $GLOBALS['cdp_commAiTestComplete']
     * (callable($system, $prompt, $maxTokens): ['ok','text','error']) stands in
     * for the provider; it is ignored outside the command line.
     *
     * @return array{ok:bool,text?:string,error?:string,model?:string}
     */
    function cdp_commAiCall($system, $prompt, $maxTokens = 3000)
    {
        if (PHP_SAPI === 'cli' && isset($GLOBALS['cdp_commAiTestComplete']) && is_callable($GLOBALS['cdp_commAiTestComplete'])) {
            return (array) call_user_func($GLOBALS['cdp_commAiTestComplete'], $system, $prompt, $maxTokens);
        }
        require_once __DIR__ . '/ai_assistant.php';
        require_once __DIR__ . '/ai_assistant_llm.php';
        try {
            return (array) cdp_aiComplete($system, $prompt, null, null, null, (int) $maxTokens);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Company name for prompts. */
    function cdp_commAiCompany()
    {
        $core = class_exists('Core') ? new Core : null;
        return trim((string) ($core->site_name ?? '')) ?: 'Swift Lane';
    }

    /** The empty result shape. */
    function cdp_commAiResult(array $over = [])
    {
        return array_merge(['ok' => false, 'subject' => '', 'body' => '', 'blocks' => null, 'warnings' => [], 'error' => ''], $over);
    }

    /**
     * Write with AI. Never saves, never sends.
     *
     * @param string $task    one of cdp_commAiTasks()
     * @param array  $context per task:
     *   trigger_message / status_message: key (status:<id> | claim_reminder |
     *       payment_reminder | driver_arrival | survey) or status_id;
     *       current ['subject' => , 'body' => , 'blocks' => studio blocks]; tone
     *   campaign: name, goal, audience (description), current, tone
     *   subject_lines: key or name/goal, body (the message, plain text), count (1-8)
     *   survey_questions: name, purpose, count (1-8)
     *   whatsapp_reply: thread_id, or facts (string) + conversation [['dir' => in|out, 'body' => ...]]
     *   explain_failure: channel, error, provider, status, to, detail
     * @param string $channel email | sms | whatsapp ('' for survey_questions / explain_failure)
     * @param string $instruction the person's own instruction (optional, short)
     *
     * @return array{ok:bool,subject:string,body:string,blocks:?array,warnings:string[],error:string,
     *               code?:string,design?:array,preview?:array,subjects?:string[],questions?:array,
     *               intro?:string,thanks?:string,chars?:int,parts?:int,assistant?:string}
     */
    function cdp_commAiDraft(string $task, array $context, string $channel, string $instruction = ''): array
    {
        $tasks = cdp_commAiTasks();
        if (!isset($tasks[$task])) {
            return cdp_commAiResult(['error' => 'Unknown writing task.', 'code' => 'invalid']);
        }
        $avail = cdp_commAiAvailable();
        if (!$avail['ok']) {
            return cdp_commAiResult(['error' => $avail['message'], 'code' => $avail['code'], 'setup_url' => $avail['setup_url'], 'assistant' => $avail['assistant']]);
        }
        $instruction = mb_substr(trim(preg_replace('/\s+/', ' ', $instruction)), 0, 400);
        require_once __DIR__ . '/notify_studio.php';
        try {
            switch ($task) {
                case 'trigger_message':
                case 'status_message':
                    $r = cdp_commAiTriggerMessage($context, $channel, $instruction, $task === 'status_message');
                    break;
                case 'campaign':
                    $r = cdp_commAiCampaign($context, $channel, $instruction);
                    break;
                case 'subject_lines':
                    $r = cdp_commAiSubjects($context, $instruction);
                    break;
                case 'survey_questions':
                    $r = cdp_commAiSurvey($context, $instruction);
                    break;
                case 'whatsapp_reply':
                    $r = cdp_commAiReply($context, $instruction);
                    break;
                default:
                    $r = cdp_commAiExplain($context, $instruction);
            }
        } catch (Throwable $e) {
            error_log('comms ai ' . $task . ': ' . $e->getMessage());
            $r = cdp_commAiResult(['error' => 'Something went wrong while writing. Please try again.', 'code' => 'error']);
        }
        $r['assistant'] = $avail['assistant'];
        return $r;
    }

    /** Model reply -> error result when the call failed. */
    function cdp_commAiFailed(array $res)
    {
        return cdp_commAiResult(['code' => 'provider', 'error' => cdp_commAiName() . ' could not answer: ' . trim((string) ($res['error'] ?? 'unknown error'))]);
    }

    /**
     * The AI context of a trigger, for cdp_notifyAiPrompt / cdp_notifyAiParse:
     * a status is the studio's own status context (its meaning, pickup code,
     * payment); an event lists exactly the placeholders it fills.
     *
     * @return array|null
     */
    function cdp_commAiTriggerContext($key)
    {
        $key = (string) $key;
        $sid = cdp_commTriggerStatusId($key);
        if ($sid > 0) {
            return cdp_notifyAiContext('status:' . $sid);
        }
        require_once __DIR__ . '/comms.php';
        $events = cdp_commEvents();
        if (!isset($events[$key])) {
            return null;
        }
        $def = cdp_commDefaultTemplates()[$key] ?? [];
        return cdp_notifyAiContext('comms:' . $key, ['label' => $events[$key]['label'], 'help' => $events[$key]['help'],
            'vars' => array_keys(cdp_commTriggerPlaceholders($key)), 'example' => (string) ($def['text'] ?? '')]);
    }

    /** Sample values a trigger's preview is filled with. */
    function cdp_commAiSample(array $ctx)
    {
        if ($ctx['type'] === 'status') {
            return cdp_notifySampleVars((string) ($ctx['statusName'] ?? ''));
        }
        $event = strpos((string) $ctx['key'], 'comms:') === 0 ? substr((string) $ctx['key'], 6) : (string) $ctx['key'];
        return cdp_notifyCommsSampleVars($event, cdp_notifySampleVars());
    }

    /**
     * Run a studio-style prompt for one channel and check the reply (with one
     * SMS-only retry when the text message is too long).
     */
    function cdp_commAiRunChannel(array $ctx, $channel, array $current, $tone, $instruction, $systemEdit = null)
    {
        $king = cdp_commAiName();
        $tones = cdp_notifyAiTones();
        $tone = isset($tones[$tone]) ? $tone : 'keep';
        [$system, $prompt] = cdp_notifyAiPrompt($ctx, [$channel], [$channel => $current], $tone, $instruction, $king, cdp_commAiCompany());
        if (is_callable($systemEdit)) {
            $system = $systemEdit($system);
        }
        $res = cdp_commAiCall($system, $prompt, 3000);
        if (empty($res['ok'])) {
            return cdp_commAiFailed($res);
        }
        $sample = cdp_commAiSample($ctx);
        $parsed = cdp_notifyAiParse((string) ($res['text'] ?? ''), $ctx, [$channel], $sample);
        if (empty($parsed['ok'])) {
            return cdp_commAiResult(['code' => 'invalid', 'error' => $king . ': ' . $parsed['error']]);
        }
        $p = $parsed['proposals'][$channel];
        if ($channel === 'sms' && ($p['chars'] ?? 0) > 160) {
            $retry = cdp_commAiCall($system, "This SMS is " . $p['chars'] . " characters once the placeholders are filled; the limit is 160.\n"
                . "Rewrite it to at most 150 characters with values filled, keeping what matters for \"" . $ctx['label'] . "\". Keep the same placeholder rules.\n"
                . "SMS: " . json_encode($p['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\nReply with exactly: {\"text\": \"...\"}", 1000);
            if (!empty($retry['ok'])) {
                $again = cdp_notifyAiParse((string) ($retry['text'] ?? ''), $ctx, ['sms'], $sample);
                if (!empty($again['ok']) && $again['proposals']['sms']['chars'] < $p['chars']) {
                    $p = $again['proposals']['sms'];
                }
            }
        }
        $out = cdp_commAiResult(['ok' => true, 'subject' => (string) $p['subject'], 'body' => (string) $p['body'], 'warnings' => array_values($p['warnings']),
            'preview' => $p['preview'] ?? [], 'model' => (string) ($res['model'] ?? '')]);
        if ($channel === 'email') {
            $out['blocks'] = $p['blocks'];
            $out['design'] = cdp_commBlocksToCanvas($p['blocks']);
            $out['body'] = CDP_NOTIFY_HTML_MARK . cdp_notifyCompileBlocks($p['blocks']);
        } else {
            $out['chars'] = (int) ($p['chars'] ?? 0);
            $out['parts'] = (int) ($p['parts'] ?? 0);
        }
        return $out;
    }

    /** Current text for the prompt: what the editor holds, else what is sent today. */
    function cdp_commAiCurrent(array $context, $key, $channel)
    {
        $c = is_array($context['current'] ?? null) ? $context['current'] : [];
        if ($c && (trim((string) ($c['body'] ?? '')) !== '' || !empty($c['blocks']))) {
            return ['subject' => (string) ($c['subject'] ?? ''), 'body' => (string) ($c['body'] ?? ''), 'blocks' => is_array($c['blocks'] ?? null) ? $c['blocks'] : []];
        }
        $t = $key !== '' ? cdp_commTriggerTemplate($key, $channel) : null;
        if (!$t) {
            return ['subject' => '', 'body' => '', 'blocks' => []];
        }
        $isHtml = strpos($t['body'], CDP_NOTIFY_HTML_MARK) === 0;
        return ['subject' => $t['subject'], 'body' => $isHtml ? '' : $t['body'],
            'blocks' => $t['blocks'] ?: ($channel === 'email' ? [['type' => 'text', 'text' => $isHtml ? trim(strip_tags($t['body'])) : $t['body']]] : [])];
    }

    function cdp_commAiTriggerMessage(array $context, $channel, $instruction, $statusOnly)
    {
        $key = isset($context['status_id']) ? 'status:' . (int) $context['status_id'] : (string) ($context['key'] ?? '');
        if ($statusOnly && cdp_commTriggerStatusId($key) <= 0) {
            return cdp_commAiResult(['code' => 'invalid', 'error' => 'Choose a status.']);
        }
        if (!in_array($channel, ['email', 'sms', 'whatsapp'], true)) {
            return cdp_commAiResult(['code' => 'invalid', 'error' => 'Choose e-mail, SMS or WhatsApp.']);
        }
        if (strpos($key, 'legacy:') === 0 || $key === 'status:*') {
            return cdp_commAiResult(['code' => 'invalid', 'error' => 'This message is written on the screen that sends it.']);
        }
        $ctx = cdp_commAiTriggerContext($key);
        if (!$ctx) {
            return cdp_commAiResult(['code' => 'invalid', 'error' => 'Unknown message.']);
        }
        return cdp_commAiRunChannel($ctx, $channel, cdp_commAiCurrent($context, $key, $channel), (string) ($context['tone'] ?? 'keep'), $instruction);
    }

    function cdp_commAiCampaign(array $context, $channel, $instruction)
    {
        if (!in_array($channel, ['email', 'sms', 'whatsapp'], true)) {
            return cdp_commAiResult(['code' => 'invalid', 'error' => 'Choose e-mail, SMS or WhatsApp.']);
        }
        $name = mb_substr(trim((string) ($context['name'] ?? '')), 0, 120);
        $goal = mb_substr(trim(preg_replace('/\s+/', ' ', (string) ($context['goal'] ?? ''))), 0, 600);
        $aud = mb_substr(trim(preg_replace('/\s+/', ' ', (string) ($context['audience'] ?? ''))), 0, 300);
        if ($goal === '' && $instruction === '' && empty($context['current']['body']) && empty($context['current']['blocks'])) {
            return cdp_commAiResult(['code' => 'invalid', 'error' => 'Say what the campaign is about first.']);
        }
        $vars = array_keys(cdp_commBasePlaceholders());
        $ctx = cdp_notifyAiContext('comms:campaign', ['label' => 'Campaign' . ($name !== '' ? ' "' . $name . '"' : ''),
            'help' => 'A marketing message to customers who agreed to receive offers. ' . ($goal !== '' ? 'What it is about: ' . $goal . '. ' : '')
                . ($aud !== '' ? 'Who receives it: ' . $aud . '. ' : '')
                . 'It is not about one shipment: never mention a tracking number. The unsubscribe / STOP line is added automatically: do not write one.',
            'vars' => array_values(array_diff($vars, ['prefs_url'])), 'example' => '']);
        $ctx['meaning'] = str_replace(' Sent automatically by the Communication Center.', '', $ctx['meaning']);
        $edit = function ($system) {
            return str_replace([
                'You write the automatic message a customer receives for one event. Everything you write must be about exactly that event and nothing else.',
                'include {track_url}, ',
                'Always use {company} for the company name and {tracking} for the tracking number.',
            ], [
                'You write one marketing campaign message to many customers. Stay on its topic; be clear about the offer and what to do next.',
                '',
                'Always use {company} for the company name.',
            ], $system);
        };
        $current = is_array($context['current'] ?? null) ? $context['current'] + ['subject' => '', 'body' => '', 'blocks' => []] : ['subject' => '', 'body' => '', 'blocks' => []];
        $r = cdp_commAiRunChannel($ctx, $channel, $current, (string) ($context['tone'] ?? 'keep'), $instruction, $edit);
        if ($r['ok']) {
            $r['warnings'] = array_values(array_filter($r['warnings'], function ($w) { return strpos($w, 'tracking') === false; }));
            if (preg_match('/\b(reply\s+stop|unsubscribe)\b/i', $r['body'] . json_encode($r['blocks']))) {
                $r['warnings'][] = 'The opt-out line is added automatically when it is sent; you can remove the one in the text.';
            }
        }
        return $r;
    }

    function cdp_commAiSubjects(array $context, $instruction)
    {
        $key = (string) ($context['key'] ?? '');
        $count = max(1, min(8, (int) ($context['count'] ?? 5)));
        $ctx = $key !== '' ? cdp_commAiTriggerContext($key) : null;
        $allowed = $ctx ? array_intersect_key(cdp_notifyAiAllowed($ctx, 'email'), ['tracking' => 1, 'company' => 1, 'first_name' => 1, 'status' => 1])
            : ['company' => 'Company name', 'first_name' => 'Customer first name'];
        $topic = $ctx ? $ctx['label'] . ': ' . $ctx['meaning'] : trim((string) ($context['name'] ?? '') . '. ' . (string) ($context['goal'] ?? ''));
        $body = mb_substr(trim(strip_tags((string) ($context['body'] ?? ''))), 0, 1500);
        $king = cdp_commAiName();
        $system = "You are {$king}, the writing assistant of " . cdp_commAiCompany() . ", a freight forwarder shipping parcels from the United States to Ghana. "
            . "You write e-mail subject lines: plain text, at most 70 characters, no emojis, no ALL CAPS, no exclamation marks in a row, never misleading. "
            . "Placeholders in single braces are filled when sent; use only these: " . implode(', ', array_map(function ($k) { return '{' . $k . '}'; }, array_keys($allowed))) . ". Reply with JSON only.";
        $prompt = "WHAT THE E-MAIL IS ABOUT: " . $topic . "\n" . ($body !== '' ? "THE E-MAIL SAYS: " . $body . "\n" : '')
            . ($instruction !== '' ? 'INSTRUCTION: ' . $instruction . "\n" : '')
            . "Write {$count} different subject lines. Reply with exactly: {\"subjects\": [\"...\"]}";
        $res = cdp_commAiCall($system, $prompt, 800);
        if (empty($res['ok'])) {
            return cdp_commAiFailed($res);
        }
        $j = cdp_notifyAiJson((string) ($res['text'] ?? ''));
        $warn = [];
        $out = [];
        foreach (is_array($j['subjects'] ?? null) ? $j['subjects'] : [] as $s) {
            $s = cdp_notifyAiCut(cdp_notifyAiCleanText((string) $s, $allowed, 'subject', $warn), 90);
            if ($s !== '' && !in_array($s, $out, true)) {
                $out[] = $s;
            }
        }
        if (!$out) {
            return cdp_commAiResult(['code' => 'invalid', 'error' => $king . ': no subject line came back. Please try again.']);
        }
        $out = array_slice($out, 0, $count);
        return cdp_commAiResult(['ok' => true, 'subject' => $out[0], 'subjects' => $out, 'warnings' => array_values($warn)]);
    }

    /** Question types a survey form accepts. */
    function cdp_commAiSurveyTypes()
    {
        return ['rating' => 'Rating 1 to 5', 'nps' => 'Recommend 0 to 10', 'choice' => 'One choice from a list', 'text' => 'Free text'];
    }

    function cdp_commAiSurvey(array $context, $instruction)
    {
        $count = max(1, min(8, (int) ($context['count'] ?? 3)));
        $king = cdp_commAiName();
        $system = "You are {$king}, the writing assistant of " . cdp_commAiCompany() . ", a freight forwarder shipping parcels from the United States to Ghana "
            . "(customers shop in the US, we ship by air or sea, they pick up in Accra or a rider delivers). "
            . "You write short customer satisfaction surveys sent right after a delivery or pickup. Questions are short, neutral, one idea each, never leading. "
            . "Question types: rating (1-5 stars), nps (0-10 how likely to recommend), choice (one answer from 2 to 6 short options), text (optional comment). "
            . "Start with the most important rating question; at most one text question, last. Reply with JSON only.";
        $prompt = 'SURVEY: ' . mb_substr(trim((string) ($context['name'] ?? 'How did we do?')), 0, 120) . "\n"
            . (trim((string) ($context['purpose'] ?? '')) !== '' ? 'WHAT WE WANT TO LEARN: ' . mb_substr(trim((string) $context['purpose']), 0, 400) . "\n" : '')
            . ($instruction !== '' ? 'INSTRUCTION: ' . $instruction . "\n" : '')
            . "Write {$count} questions, a one-sentence intro and a one-sentence thank-you. Reply with exactly: "
            . '{"intro": "...", "questions": [{"type": "rating|nps|choice|text", "label": "...", "options": ["..."]}], "thanks": "..."}';
        $res = cdp_commAiCall($system, $prompt, 1500);
        if (empty($res['ok'])) {
            return cdp_commAiFailed($res);
        }
        $j = cdp_notifyAiJson((string) ($res['text'] ?? ''));
        $types = cdp_commAiSurveyTypes();
        $warn = [];
        $qs = [];
        $hasText = false;
        foreach (is_array($j['questions'] ?? null) ? $j['questions'] : [] as $q) {
            if (!is_array($q)) {
                continue;
            }
            $type = (string) ($q['type'] ?? '');
            $label = cdp_notifyAiCut(cdp_notifyAiCleanText((string) ($q['label'] ?? ''), ['company' => 1], 'subject', $warn), 200);
            if (!isset($types[$type]) || $label === '') {
                continue;
            }
            if ($type === 'text') {
                if ($hasText) {
                    continue;
                }
                $hasText = true;
            }
            $item = ['key' => 'q' . (count($qs) + 1), 'type' => $type, 'label' => $label];
            if ($type === 'choice') {
                $opts = [];
                foreach (is_array($q['options'] ?? null) ? $q['options'] : [] as $o) {
                    $o = cdp_notifyAiCut(trim(strip_tags((string) $o)), 60);
                    if ($o !== '' && !in_array($o, $opts, true)) {
                        $opts[] = $o;
                    }
                }
                if (count($opts) < 2) {
                    $warn['choice'] = 'Left out a choice question without at least two options.';
                    continue;
                }
                $item['options'] = array_slice($opts, 0, 6);
            }
            $qs[] = $item;
            if (count($qs) >= $count) {
                break;
            }
        }
        if (!$qs) {
            return cdp_commAiResult(['code' => 'invalid', 'error' => $king . ': no usable question came back. Please try again.']);
        }
        $clean = function ($t) use (&$warn) { return cdp_notifyAiCut(cdp_notifyAiCleanText((string) $t, ['company' => 1, 'first_name' => 1], 'subject', $warn), 240); };
        return cdp_commAiResult(['ok' => true, 'questions' => $qs, 'intro' => $clean($j['intro'] ?? ''), 'thanks' => $clean($j['thanks'] ?? ''), 'warnings' => array_values($warn)]);
    }

    function cdp_commAiReply(array $context, $instruction)
    {
        $king = cdp_commAiName();
        $facts = '';
        $conv = '';
        $tid = (int) ($context['thread_id'] ?? 0);
        if ($tid > 0) {
            require_once __DIR__ . '/comms.php';
            $t = cdp_opsOne("SELECT * FROM cdb_comm_threads WHERE id = :id", [':id' => $tid]);
            if (!$t) {
                return cdp_commAiResult(['code' => 'invalid', 'error' => 'Conversation not found.']);
            }
            $facts = cdp_commBotContext($t);
            foreach (array_reverse(cdp_opsQ("SELECT direction, body FROM cdb_comm_messages WHERE thread_id = :t ORDER BY id DESC LIMIT 12", [':t' => $tid])) as $h) {
                $conv .= ($h->direction === 'in' ? 'Customer: ' : 'Us: ') . cdp_opsClip((string) $h->body, 500) . "\n";
            }
        } else {
            $facts = mb_substr(trim((string) ($context['facts'] ?? '')), 0, 4000);
            foreach (array_slice(is_array($context['conversation'] ?? null) ? $context['conversation'] : [], -12) as $h) {
                $conv .= (($h['dir'] ?? 'in') === 'in' ? 'Customer: ' : 'Us: ') . cdp_opsClip((string) ($h['body'] ?? ''), 500) . "\n";
            }
        }
        if (trim($conv) === '') {
            return cdp_commAiResult(['code' => 'invalid', 'error' => 'There is no message to answer yet.']);
        }
        $system = "You are {$king}, helping a member of staff of " . cdp_commAiCompany() . " (a freight forwarder shipping parcels from the US to Ghana) answer a customer on WhatsApp. "
            . "Write the next reply from Us: warm, brief (at most 4 short sentences), in the customer's language, *bold* with single asterisks only for a tracking number or a key fact, no markdown headings, no emojis. "
            . "Use ONLY the facts given; never guess dates, prices, amounts or statuses. When the facts do not answer the question, say a colleague will check and come back. "
            . "Do not use placeholders in braces. Reply with JSON only: {\"text\": \"...\"}";
        $prompt = "FACTS:\n" . ($facts !== '' ? $facts : '(none)') . "\n\nCONVERSATION:\n" . $conv . ($instruction !== '' ? "\nINSTRUCTION FROM STAFF: " . $instruction : '');
        $res = cdp_commAiCall($system, $prompt, 800);
        if (empty($res['ok'])) {
            return cdp_commAiFailed($res);
        }
        $j = cdp_notifyAiJson((string) ($res['text'] ?? ''));
        $text = is_array($j) ? (string) ($j['text'] ?? '') : trim((string) ($res['text'] ?? ''));
        $warn = [];
        $text = cdp_notifyAiCleanText($text, [], 'whatsapp', $warn);
        if ($text === '') {
            return cdp_commAiResult(['code' => 'invalid', 'error' => $king . ': the reply came back empty. Please try again.']);
        }
        if (mb_strlen($text) > 1500) {
            $text = cdp_notifyAiCut($text, 1500);
            $warn['cut'] = 'Shortened to 1,500 characters.';
        }
        return cdp_commAiResult(['ok' => true, 'body' => $text, 'warnings' => array_values($warn), 'chars' => mb_strlen($text)]);
    }

    function cdp_commAiExplain(array $context, $instruction)
    {
        $king = cdp_commAiName();
        $f = [];
        foreach (['channel' => 'Channel', 'provider' => 'Provider', 'status' => 'Status', 'error' => 'Error', 'detail' => 'Detail', 'to' => 'Recipient', 'attempts' => 'Attempts'] as $k => $label) {
            $v = trim((string) ($context[$k] ?? ''));
            if ($v !== '') {
                // Never send a full address or number to the model.
                if ($k === 'to') {
                    $v = preg_replace('/(?<=.{3}).(?=.{2})/u', '•', $v);
                }
                $f[] = $label . ': ' . mb_substr($v, 0, 500);
            }
        }
        if (!$f) {
            return cdp_commAiResult(['code' => 'invalid', 'error' => 'Nothing to explain.']);
        }
        $system = "You are {$king}, helping a non-technical administrator of " . cdp_commAiCompany() . " understand why a WhatsApp, SMS or e-mail message was not delivered. "
            . "WhatsApp goes through UltraMsg or the Meta Cloud API, SMS through Hubtel or mNotify, e-mail through SMTP or PHP mail(). "
            . "In at most 4 short plain sentences: what most likely happened, then what to do (for example check the number, top up credit, reconnect WhatsApp by scanning the QR code, fix the SMTP password in Communications > Settings, or ask the customer for a new address). "
            . "Say when you are not sure. No markdown, no placeholders. Reply with JSON only: {\"text\": \"...\"}";
        $res = cdp_commAiCall($system, implode("\n", $f) . ($instruction !== '' ? "\nQUESTION: " . $instruction : ''), 600);
        if (empty($res['ok'])) {
            return cdp_commAiFailed($res);
        }
        $j = cdp_notifyAiJson((string) ($res['text'] ?? ''));
        $warn = [];
        $text = cdp_notifyAiCleanText(is_array($j) ? (string) ($j['text'] ?? '') : (string) ($res['text'] ?? ''), [], 'sms', $warn);
        if ($text === '') {
            return cdp_commAiResult(['code' => 'invalid', 'error' => $king . ': no explanation came back. Please try again.']);
        }
        return cdp_commAiResult(['ok' => true, 'body' => cdp_notifyAiCut($text, 700), 'warnings' => []]);
    }
}
