<?php
// *************************************************************************
// * Customer Service — settings.                                          *
// *                                                                       *
// * GET  action=load                 everything the settings page shows    *
// * GET  action=rule_runs rule_id    a rule's recent runs                  *
// * POST action=settings             King, hours, notifications, SLA       *
// * POST action=team_save | team_delete | member_save | member_remove    *
// * POST action=rule_save | rule_delete | rule_status | rule_simulate     *
// * POST action=rule_template key    add a shipped template as a draft     *
// * POST action=canned_save | canned_delete                              *
// * POST action=kb_save | kb_delete                                      *
// * POST action=outbox_retry id                                          *
// *                                                                       *
// * Administrators (rank 3+) change settings. Staff may keep their own     *
// * saved replies and draft help articles; publishing is for administrators.*
// *************************************************************************

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/querys.php');
require_once(__DIR__ . '/../../helpers/support_king.php');
require_login();

header('Content-Type: application/json; charset=utf-8');
$reply = function (array $body, $code = 200) {
    if ($code !== 200) {
        http_response_code($code);
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

if (!cdp_csIsAgent($user)) {
    $reply(['ok' => false, 'error' => 'Customer Service settings are for staff accounts.'], 403);
}
if (!cdp_csEnsureTables()) {
    $reply(['ok' => false, 'error' => 'The helpdesk tables could not be created.']);
}
$uid = cdp_csUid();
$isAdmin = cdp_csIsAdmin($user);
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = (string) ($_REQUEST['action'] ?? 'load');
$adminOnly = function () use ($isAdmin, $reply) {
    if (!$isAdmin) {
        $reply(['ok' => false, 'error' => 'Only administrators can change this.'], 403);
    }
};

$load = function () use ($uid, $isAdmin) {
    $s = cdp_csSettings(true);
    $teams = cdp_csTeams();
    foreach ($teams as &$t) {
        $t['member_list'] = cdp_csTeamMembers($t['id']);
    }
    unset($t);
    $channels = [];
    $settings = function_exists('cdp_getSettingsCourier') ? cdp_getSettingsCourier() : null;
    $channels[] = ['key' => 'web', 'label' => 'Web chat (Support Hub and chat button)', 'state' => 'live', 'detail' => 'Signed-in customers. Built in.'];
    $channels[] = ['key' => 'portal', 'label' => 'Support request form', 'state' => 'live', 'detail' => 'Help & Support > New request. Built in.'];
    $emailOk = $settings && (($settings->mailer ?? '') === 'PHP' || trim((string) ($settings->smtp_host ?? '')) !== '');
    $channels[] = ['key' => 'email', 'label' => 'E-mail (outgoing updates)', 'state' => $emailOk ? 'live' : 'setup', 'detail' => $emailOk ? 'Uses the app\'s mail settings (Tools > Email).' : 'Set up the mail server on Tools > Email.'];
    $channels[] = ['key' => 'whatsapp', 'label' => 'WhatsApp (outgoing updates)', 'state' => 'configured_elsewhere', 'detail' => 'Uses the WhatsApp provider from Tools > WhatsApp; template 12 carries the message.'];
    $channels[] = ['key' => 'sms', 'label' => 'SMS (outgoing updates)', 'state' => 'configured_elsewhere', 'detail' => 'Uses the SMS provider from Tools > SMS.'];
    $channels[] = ['key' => 'inbound', 'label' => 'Incoming e-mail / WhatsApp / SMS into tickets', 'state' => 'not_connected', 'detail' => 'Not connected. Needs a provider webhook with a signing secret; customers reach support through the web chat and request form meanwhile.'];
    $outbox = cdp_csQ("SELECT status, COUNT(*) AS n FROM cdb_cs_outbox WHERE created_at >= NOW() - INTERVAL 7 DAY GROUP BY status");
    $dead = cdp_csQ("SELECT id, ticket_id, channel, kind, last_error, attempts, created_at FROM cdb_cs_outbox WHERE status IN ('dead','retry') ORDER BY id DESC LIMIT 20");
    $lastWorker = cdp_csSetting('worker_last_run', '');
    return [
        'ok' => true, 'is_admin' => $isAdmin, 'settings' => $s, 'ai_ready' => cdp_csAiReady(),
        'ai_provider' => cdp_aiProviderLabel(cdp_aiSetting('provider')), 'ai_model' => (string) cdp_aiSetting('model'),
        'teams' => $teams, 'agents' => cdp_csAgents(), 'rules' => cdp_csRules(), 'templates' => cdp_csRuleTemplates(),
        'triggers' => cdp_csRuleTriggers(), 'fields' => cdp_csRuleFields(), 'ops' => cdp_csRuleOps(), 'actions' => cdp_csRuleActions(),
        'canned' => cdp_csCanned($uid), 'kb' => cdp_csKbList(true), 'languages' => cdp_csLanguages(), 'priorities' => cdp_csPriorities(),
        'channels' => $channels, 'outbox' => array_map(function ($r) { return ['status' => $r->status, 'n' => (int) $r->n]; }, $outbox),
        'outbox_problems' => array_map(function ($r) { return (array) $r; }, $dead), 'worker_last_run' => $lastWorker,
        'king_runs' => array_map(function ($r) { return (array) $r; }, cdp_csQ("SELECT outcome, COUNT(*) AS n, AVG(latency_ms) AS ms FROM cdb_cs_ai_runs WHERE created_at >= NOW() - INTERVAL 7 DAY GROUP BY outcome")),
    ];
};

if ($method !== 'POST') {
    if ($action === 'rule_runs') {
        $reply(['ok' => true, 'runs' => array_map(function ($r) {
            return ['id' => (int) $r->id, 'ticket_id' => (int) $r->ticket_id, 'ref' => (string) $r->public_ref, 'event' => (string) $r->event, 'outcome' => (string) $r->outcome,
                'version' => (int) $r->rule_version, 'steps' => cdp_csDecode($r->steps), 'at' => (string) $r->created_at];
        }, cdp_csQ("SELECT r.*, t.public_ref FROM cdb_cs_rule_runs r LEFT JOIN cdb_cs_tickets t ON t.id = r.ticket_id WHERE r.rule_id = :r ORDER BY r.id DESC LIMIT 50", [':r' => (int) ($_GET['rule_id'] ?? 0)])),
            'versions' => array_map(function ($v) { return ['version' => (int) $v->version, 'by' => cdp_csUserName($v->saved_by), 'at' => (string) $v->saved_at]; },
                cdp_csQ("SELECT version, saved_by, saved_at FROM cdb_cs_rule_versions WHERE rule_id = :r ORDER BY version DESC LIMIT 20", [':r' => (int) ($_GET['rule_id'] ?? 0)]))]);
    }
    $reply($load());
}

switch ($action) {
    case 'settings':
        $adminOnly();
        $keys = ['king_mode', 'king_name', 'king_greeting', 'king_instructions', 'king_max_turns', 'king_max_rounds', 'king_daily_budget', 'copilot_enabled', 'languages',
            'business_hours', 'offline_note', 'ack_enabled', 'notify_channels', 'csat_enabled', 'csat_delay_min', 'sla_pause_pending', 'sla_approved', 'sla_warn_pct',
            'auto_close_days', 'ref_prefix'];
        foreach (['urgent', 'high', 'normal', 'low'] as $p) {
            $keys[] = 'sla_first_' . $p;
            $keys[] = 'sla_resolve_' . $p;
        }
        $changed = [];
        foreach ($keys as $k) {
            if (!array_key_exists($k, $_POST)) {
                continue;
            }
            $v = is_array($_POST[$k]) ? implode(',', array_map('strval', $_POST[$k])) : trim((string) $_POST[$k]);
            if ($k === 'king_mode' && !in_array($v, ['off', 'copilot', 'customer'], true)) {
                continue;
            }
            if ($k === 'king_mode' && $v === 'customer' && !cdp_csAiReady()) {
                $reply(['ok' => false, 'error' => 'Set the AI provider, key and model on Tools > AI Assistant first, then switch King on for customers.']);
            }
            if ($k === 'king_name') {
                $v = cdp_csClip($v, 40) ?: 'King';
            }
            if ($k === 'notify_channels') {
                $v = implode(',', array_intersect(array_map('trim', explode(',', $v)), ['email', 'whatsapp', 'sms']));
            }
            if (preg_match('/^(sla_|king_max|king_daily|csat_delay|auto_close)/', $k) && !in_array($k, ['sla_pause_pending', 'sla_approved'], true)) {
                $v = (string) max(0, (int) $v);
            }
            if ($k === 'ref_prefix') {
                $v = substr(preg_replace('/[^A-Z]/', '', strtoupper($v)), 0, 4) ?: 'CS';
            }
            if ((string) cdp_csSetting($k) !== $v) {
                $changed[$k] = in_array($k, ['king_instructions', 'king_greeting'], true) ? '(text changed)' : $v;
                cdp_csSettingSet($k, $v, $uid);
            }
        }
        if ($changed) {
            cdp_csEvent('settings.changed', null, null, 'staff', $uid, $changed);
        }
        $reply($load());

    case 'team_save':
        $adminOnly();
        $name = cdp_csClip($_POST['name'] ?? '', 120);
        if ($name === '') {
            $reply(['ok' => false, 'error' => 'Name the team.']);
        }
        $mode = in_array($_POST['assign_mode'] ?? '', ['manual', 'round_robin', 'least_loaded'], true) ? $_POST['assign_mode'] : 'manual';
        $tz = in_array($_POST['timezone'] ?? '', timezone_identifiers_list(), true) ? $_POST['timezone'] : null;
        $b = [':n' => $name, ':d' => cdp_csClip($_POST['description'] ?? '', 255), ':m' => $mode, ':tz' => $tz, ':a' => !empty($_POST['active']) ? 1 : 0];
        $tid = (int) ($_POST['id'] ?? 0);
        if ($tid) {
            cdp_csX("UPDATE cdb_cs_teams SET name = :n, description = :d, assign_mode = :m, timezone = :tz, active = :a WHERE id = :i", $b + [':i' => $tid]);
        } else {
            $tid = cdp_csX("INSERT INTO cdb_cs_teams (name, description, assign_mode, timezone, active, created_at) VALUES (:n, :d, :m, :tz, :a, :c)", $b + [':c' => cdp_csNow()])['id'];
        }
        if (!empty($_POST['is_default'])) {
            cdp_csX("UPDATE cdb_cs_teams SET is_default = (id = :i)", [':i' => $tid]);
        }
        cdp_csEvent('settings.team_saved', null, null, 'staff', $uid, ['team' => $tid, 'name' => $name]);
        $reply($load());

    case 'team_delete':
        $adminOnly();
        $tid = (int) ($_POST['id'] ?? 0);
        $open = cdp_csOne("SELECT COUNT(*) AS n FROM cdb_cs_tickets WHERE team_id = :t AND status NOT IN ('resolved','closed')", [':t' => $tid]);
        if ($open && (int) $open->n > 0) {
            $reply(['ok' => false, 'error' => 'This team still has ' . (int) $open->n . ' open tickets. Move them first, or switch the team off instead.']);
        }
        cdp_csX("DELETE FROM cdb_cs_team_members WHERE team_id = :t", [':t' => $tid]);
        cdp_csX("DELETE FROM cdb_cs_teams WHERE id = :t AND is_default = 0", [':t' => $tid]);
        cdp_csEvent('settings.team_deleted', null, null, 'staff', $uid, ['team' => $tid]);
        $reply($load());

    case 'member_save':
        $adminOnly();
        $tid = (int) ($_POST['team_id'] ?? 0);
        $mid = (int) ($_POST['user_id'] ?? 0);
        $u = cdp_csOne("SELECT userlevel FROM cdb_users WHERE id = :i", [':i' => $mid]);
        if (!$u || cdp_roleRankById((int) $u->userlevel) < 2) {
            $reply(['ok' => false, 'error' => 'Only staff accounts can join a team.']);
        }
        cdp_csX("INSERT INTO cdb_cs_team_members (team_id, user_id, is_lead, capacity, available) VALUES (:t, :u, :l, :c, :a)
            ON DUPLICATE KEY UPDATE is_lead = VALUES(is_lead), capacity = VALUES(capacity), available = VALUES(available)",
            [':t' => $tid, ':u' => $mid, ':l' => !empty($_POST['is_lead']) ? 1 : 0, ':c' => max(1, min(500, (int) ($_POST['capacity'] ?? 20))), ':a' => isset($_POST['available']) ? (int) !empty($_POST['available']) : 1]);
        cdp_csEvent('settings.member_saved', null, null, 'staff', $uid, ['team' => $tid, 'user' => $mid]);
        $reply($load());

    case 'member_remove':
        $adminOnly();
        cdp_csX("DELETE FROM cdb_cs_team_members WHERE team_id = :t AND user_id = :u", [':t' => (int) ($_POST['team_id'] ?? 0), ':u' => (int) ($_POST['user_id'] ?? 0)]);
        $reply($load());

    case 'availability': // anyone on a team can set themselves away / back
        cdp_csX("UPDATE cdb_cs_team_members SET available = :a WHERE user_id = :u", [':a' => !empty($_POST['available']) ? 1 : 0, ':u' => $uid]);
        $reply(['ok' => true]);

    case 'rule_save':
        $adminOnly();
        $d = json_decode((string) ($_POST['rule'] ?? '{}'), true);
        if (!is_array($d)) {
            $reply(['ok' => false, 'error' => 'The rule could not be read.']);
        }
        $r = cdp_csRuleSave($d, $uid);
        if (empty($r['ok'])) {
            $reply($r);
        }
        $reply($load() + ['saved_id' => $r['id']]);

    case 'rule_template':
        $adminOnly();
        $tpl = cdp_csRuleTemplates()[$_POST['key'] ?? ''] ?? null;
        if (!$tpl) {
            $reply(['ok' => false, 'error' => 'Unknown template.']);
        }
        $r = cdp_csRuleSave($tpl + ['status' => 'draft', 'template_key' => $_POST['key']], $uid);
        $reply($load() + ['saved_id' => $r['id'] ?? 0]);

    case 'rule_status':
        $adminOnly();
        $st = in_array($_POST['status'] ?? '', ['draft', 'published', 'paused'], true) ? $_POST['status'] : 'paused';
        cdp_csX("UPDATE cdb_cs_rules SET status = :s, updated_by = :u, updated_at = :n WHERE id = :i", [':s' => $st, ':u' => $uid, ':n' => cdp_csNow(), ':i' => (int) ($_POST['id'] ?? 0)]);
        cdp_csEvent('settings.rule_status', null, null, 'staff', $uid, ['rule' => (int) ($_POST['id'] ?? 0), 'status' => $st]);
        $reply($load());

    case 'rule_delete':
        $adminOnly();
        cdp_csX("DELETE FROM cdb_cs_rules WHERE id = :i", [':i' => (int) ($_POST['id'] ?? 0)]);
        cdp_csEvent('settings.rule_deleted', null, null, 'staff', $uid, ['rule' => (int) ($_POST['id'] ?? 0)]);
        $reply($load());

    case 'rule_simulate':
        // Dry run of the rule as it is in the editor, against a real ticket. Nothing changes.
        $d = json_decode((string) ($_POST['rule'] ?? '{}'), true);
        $t = cdp_csTicketByRef((string) ($_POST['ref'] ?? ''));
        if (!$t) {
            $reply(['ok' => false, 'error' => 'Type the reference of a ticket to test against (e.g. CS-7KQ4M2).']);
        }
        $draft = ['id' => 0, 'name' => (string) ($d['name'] ?? 'Draft'), 'match_mode' => ($d['match_mode'] ?? '') === 'any' ? 'any' : 'all',
            'conditions' => (array) ($d['conditions'] ?? []), 'actions' => (array) ($d['actions'] ?? []), 'stop_processing' => 0, 'version' => 0];
        $res = cdp_csRulesRun((string) ($d['trigger_event'] ?? 'manual'), $t->id, [], true, null, $draft);
        $reply(['ok' => true, 'result' => $res[0] ?? null, 'facts' => cdp_csRuleFacts($t), 'ticket' => ['ref' => $t->public_ref, 'subject' => $t->subject]]);

    case 'canned_save':
        $title = cdp_csClip($_POST['title'] ?? '', 160);
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($title === '' || $body === '') {
            $reply(['ok' => false, 'error' => 'A saved reply needs a title and text.']);
        }
        $shared = !empty($_POST['shared']) && $isAdmin ? 1 : 0;
        $cid = (int) ($_POST['id'] ?? 0);
        if ($cid) {
            cdp_csX("UPDATE cdb_cs_canned SET title = :t, body = :b, shortcut = :s, shared = :sh, updated_at = :n WHERE id = :i AND (created_by = :u" . ($isAdmin ? ' OR 1 = 1' : '') . ")",
                [':t' => $title, ':b' => $body, ':s' => cdp_csClip($_POST['shortcut'] ?? '', 40), ':sh' => $shared, ':n' => cdp_csNow(), ':i' => $cid, ':u' => $uid]);
        } else {
            cdp_csX("INSERT INTO cdb_cs_canned (title, body, shortcut, shared, created_by, created_at, updated_at) VALUES (:t, :b, :s, :sh, :u, :n, :n2)",
                [':t' => $title, ':b' => $body, ':s' => cdp_csClip($_POST['shortcut'] ?? '', 40), ':sh' => $shared, ':u' => $uid, ':n' => cdp_csNow(), ':n2' => cdp_csNow()]);
        }
        $reply($load());

    case 'canned_delete':
        cdp_csX("DELETE FROM cdb_cs_canned WHERE id = :i AND (created_by = :u" . ($isAdmin ? ' OR 1 = 1' : '') . ")", [':i' => (int) ($_POST['id'] ?? 0), ':u' => $uid]);
        $reply($load());

    case 'kb_save':
        $r = cdp_csKbSave($_POST, $user);
        if (empty($r['ok'])) {
            $reply($r);
        }
        $reply($load() + ['note' => ($r['status'] === 'draft' && ($_POST['status'] ?? '') === 'published') ? 'Saved as a draft: an administrator publishes it.' : '']);

    case 'kb_delete':
        $adminOnly();
        // Retire rather than delete, so answers that cited it stay traceable.
        cdp_csX("UPDATE cdb_cs_kb SET status = 'retired', updated_at = :n WHERE id = :i", [':n' => cdp_csNow(), ':i' => (int) ($_POST['id'] ?? 0)]);
        $reply($load());

    case 'outbox_retry':
        $adminOnly();
        cdp_csX("UPDATE cdb_cs_outbox SET status = 'retry', attempts = 0, send_after = :n WHERE id = :i AND status IN ('dead','retry')", [':n' => cdp_csNow(), ':i' => (int) ($_POST['id'] ?? 0)]);
        $reply($load());
}
$reply(['ok' => false, 'error' => 'Unknown action.'], 400);
