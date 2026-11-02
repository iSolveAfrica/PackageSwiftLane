<?php
// *************************************************************************
// * Customer Service — the staff desk (inbox, ticket view, copilot).      *
// *                                                                       *
// * GET  action=inbox    filters…, pg            list + view counts        *
// * GET  action=ticket   id                      everything on one ticket  *
// * GET  action=poll     id after                new messages, viewers     *
// * GET  action=customers q                      customer search           *
// * GET  action=records  customer_id             a customer's records      *
// * GET  action=kb       q                       articles (all audiences)  *
// * GET  action=tools                            canned, rules, AI modes   *
// * POST action=update   id version fields…      ticket fields             *
// * POST action=send     id body mode …          reply / internal note     *
// * POST action=accept | release | requeue  id   chat ownership            *
// * POST action=bulk     ids[] changes            per-item results         *
// * POST action=create   …                       new ticket (staff)        *
// * POST action=tag | follow | link | unlink | task | task_done | merge    *
// * POST action=view_save | view_delete | typing | proposal(_create)     *
// * POST action=ai_draft | ai_summary | ai_refine | ai_classify | ai_kb    *
// * POST action=run_rule id rule_id                                        *
// * POST action=upload   file                                             *
// *                                                                       *
// * Staff, administrators and super admins only (rank 2+).                *
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
    $reply(['ok' => false, 'error' => 'Customer Service is for staff accounts.'], 403);
}
if (!cdp_csEnsureTables()) {
    $reply(['ok' => false, 'error' => 'The helpdesk tables could not be created. The database user needs the CREATE privilege once.']);
}

$uid    = cdp_csUid();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = (string) ($_REQUEST['action'] ?? 'inbox');
$isAdmin = cdp_csIsAdmin($user);

$needTicket = function ($id) use ($reply) {
    $t = cdp_csTicket((int) $id);
    if (!$t) {
        $reply(['ok' => false, 'error' => 'Ticket not found.'], 404);
    }
    return $t;
};

$ticketPayload = function ($t) use ($uid, $user, $isAdmin) {
    $teams = [];
    foreach (cdp_csTeams() as $tm) {
        $teams[$tm['id']] = $tm['name'];
    }
    $conv = $t->conversation_id ? cdp_csConv($t->conversation_id) : null;
    $row = cdp_csTicketRow((object) ((array) $t + ['owner_state' => $conv ? $conv->owner_state : '', 'locker' => '', 'preview' => '', 'last_author' => '', 'tags' => implode(',', cdp_csTags($t->id)), 'link_ref' => '']), $teams);
    $row += [
        'description' => (string) $t->description, 'summary' => (string) $t->summary, 'pending_reason' => (string) $t->pending_reason,
        'resolution_code' => (string) $t->resolution_code, 'resolution_summary' => (string) $t->resolution_summary,
        'language_source' => (string) $t->language_source, 'reopen_count' => (int) $t->reopen_count, 'merged_into' => (int) $t->merged_into,
        'merged_into_ref' => $t->merged_into ? (string) (cdp_csTicket($t->merged_into)->public_ref ?? '') : '',
        'csat' => $t->csat_score === null ? null : (int) $t->csat_score, 'csat_comment' => (string) $t->csat_comment,
        'conversation' => $conv ? ['id' => (int) $conv->id, 'owner' => (string) $conv->owner_state, 'owner_user' => cdp_csUserName($conv->owner_user_id),
            'owner_user_id' => (int) $conv->owner_user_id, 'handover_reason' => (string) $conv->handover_reason, 'ai_turns' => (int) $conv->ai_turns] : null,
        'resolved_at' => (string) $t->resolved_at, 'first_response_at' => (string) $t->first_response_at,
    ];
    return [
        'ticket' => $row,
        'messages' => cdp_csMessages($t->conversation_id, $t->id, 'staff'),
        'customer' => $t->customer_id ? cdp_csCustomer360($t->customer_id, $user, $t->id) : null,
        'links' => cdp_csLinks($t->id), 'tasks' => cdp_csTasks($t->id), 'followers' => cdp_csFollowers($t->id),
        'following' => (bool) cdp_csOne("SELECT 1 AS x FROM cdb_cs_followers WHERE ticket_id = :t AND user_id = :u", [':t' => (int) $t->id, ':u' => $uid]),
        'sla' => cdp_csSlaClock($t), 'proposals' => cdp_csProposals($t->id), 'similar' => cdp_csSimilarOpen($t->customer_id, $t->id),
        'history' => array_map(function ($e) {
            return ['type' => (string) $e->event_type, 'actor' => $e->actor_type === 'staff' ? cdp_csUserName($e->actor_id) : ($e->actor_type === 'king' ? cdp_csSetting('king_name', 'King') : ucfirst($e->actor_type)),
                'data' => cdp_csDecode($e->data), 'at' => (string) $e->created_at];
        }, cdp_csQ("SELECT * FROM cdb_cs_events WHERE ticket_id = :t ORDER BY id DESC LIMIT 80", [':t' => (int) $t->id])),
        'viewers' => cdp_csPresence($t->id, $uid),
        'can_decide' => $isAdmin,
    ];
};

if ($method !== 'POST') {
    switch ($action) {
        case 'ticket':
            $t = $needTicket($_GET['id'] ?? 0);
            cdp_csPresencePing($t->id, $uid);
            $reply(['ok' => true] + $ticketPayload($t));

        case 'poll':
            $t = $needTicket($_GET['id'] ?? 0);
            cdp_csPresencePing($t->id, $uid);
            $conv = $t->conversation_id ? cdp_csConv($t->conversation_id) : null;
            $reply(['ok' => true, 'messages' => cdp_csMessages($t->conversation_id, $t->id, 'staff', (int) ($_GET['after'] ?? 0)),
                'viewers' => cdp_csPresence($t->id, $uid), 'version' => (int) $t->version, 'status' => (string) $t->status,
                'owner' => $conv ? (string) $conv->owner_state : '', 'owner_user' => $conv ? cdp_csUserName($conv->owner_user_id) : '']);

        case 'customers':
            $q = trim((string) ($_GET['q'] ?? ''));
            if (mb_strlen($q) < 2) {
                $reply(['ok' => true, 'customers' => []]);
            }
            $like = '%' . $q . '%';
            $rows = cdp_csQ("SELECT id, fname, lname, email, phone, locker, userlevel FROM cdb_users WHERE (CONCAT(fname,' ',lname) LIKE :a OR email LIKE :b OR phone LIKE :c OR locker LIKE :d) ORDER BY fname LIMIT 30",
                [':a' => $like, ':b' => $like, ':c' => $like, ':d' => $like]);
            $out = [];
            foreach ($rows as $r) {
                if (cdp_roleRankById((int) $r->userlevel) === 0) {
                    $out[] = ['id' => (int) $r->id, 'name' => trim($r->fname . ' ' . $r->lname), 'email' => (string) $r->email, 'locker' => (string) $r->locker];
                }
            }
            $reply(['ok' => true, 'customers' => array_slice($out, 0, 12)]);

        case 'records':
            $reply(['ok' => true, 'records' => cdp_csCustomerEntities((int) ($_GET['customer_id'] ?? 0), 30)]);

        case 'tools': // what the ticket view's composer and side panel offer
            $reply(['ok' => true, 'canned' => cdp_csCanned($uid), 'refine' => cdp_csRefineModes(), 'proposal_kinds' => cdp_csProposalKinds(),
                'manual_rules' => array_values(array_map(function ($r) { return ['id' => $r['id'], 'name' => $r['name']]; },
                    array_filter(cdp_csRules(), function ($r) { return $r['trigger_event'] === 'manual' && $r['status'] === 'published'; }))),
                'resolution_codes' => cdp_csResolutionCodes()]);

        case 'kb':
            $reply(['ok' => true, 'articles' => cdp_csKbSearch((string) ($_GET['q'] ?? ''), ['public', 'customer', 'staff'], 8)]);

        default: // inbox
            if (mt_rand(1, 10) === 1) {
                cdp_csSlaSweep(50); // keeps SLA flags fresh even before the worker is scheduled
            }
            $f = [];
            foreach (['view', 'status', 'priority', 'category', 'channel', 'team_id', 'assignee_id', 'tag', 'language', 'from', 'to', 'q', 'sort'] as $k) {
                if (isset($_GET[$k])) {
                    $f[$k] = (string) $_GET[$k];
                }
            }
            $list = cdp_csInboxList($f, $uid, (int) ($_GET['pg'] ?? 1), (int) ($_GET['per'] ?? 25));
            $views = [];
            foreach (cdp_csInboxViews($uid) as $k => $v) {
                $views[$k] = $v[0];
            }
            $reply(['ok' => true, 'list' => $list, 'counts' => cdp_csInboxCounts($uid), 'views' => $views, 'saved_views' => cdp_csSavedViews($uid)]);
    }
}

// ── POST ────────────────────────────────────────────────────────────────────
$id = (int) ($_POST['id'] ?? 0);

switch ($action) {
    case 'upload':
        if (empty($_FILES['file'])) {
            $reply(['ok' => false, 'error' => 'Choose a file.']);
        }
        $reply(cdp_csFileStore($_FILES['file'], $uid));

    case 'typing':
        cdp_csPresencePing($id, $uid, true);
        $reply(['ok' => true]);

    case 'update':
        $t = $needTicket($id);
        $ch = [];
        foreach (['status', 'priority', 'category', 'team_id', 'assignee_id', 'language', 'subject', 'pending_reason', 'resolution_code', 'resolution_summary', 'disposition', 'summary'] as $k) {
            if (array_key_exists($k, $_POST)) {
                $ch[$k] = in_array($k, ['team_id', 'assignee_id'], true) ? ((int) $_POST[$k] ?: null) : (string) $_POST[$k];
            }
        }
        if (isset($ch['language'])) {
            $ch['language_source'] = 'manual';
        }
        if (in_array($ch['disposition'] ?? '', ['spam', 'trash'], true) && !$isAdmin && (int) $t->assignee_id !== $uid) {
            $reply(['ok' => false, 'error' => 'Only the assignee or an administrator can move a ticket to spam or trash.']);
        }
        if (($ch['status'] ?? '') === 'resolved' && trim((string) ($ch['resolution_summary'] ?? $t->resolution_summary)) === '' && empty($_POST['quick'])) {
            $reply(['ok' => false, 'need_resolution' => true, 'error' => 'Say briefly how it was resolved.']);
        }
        $r = cdp_csTicketUpdate($id, $ch, 'staff', $uid, isset($_POST['version']) ? (int) $_POST['version'] : null);
        if (empty($r['ok'])) {
            $reply($r);
        }
        if (!empty($ch['team_id']) && empty($ch['assignee_id'])) {
            cdp_csAutoAssign($id);
        }
        $reply(['ok' => true] + $ticketPayload(cdp_csTicket($id)));

    case 'send':
        $t = $needTicket($id);
        $mode = ($_POST['mode'] ?? 'reply') === 'note' ? 'note' : 'reply';
        $r = cdp_csStaffSend($id, $uid, (string) ($_POST['body'] ?? ''), $mode, [
            'last_seen_id' => (int) ($_POST['last_seen_id'] ?? 0), 'force' => !empty($_POST['force']), 'client_msg_id' => $_POST['client_msg_id'] ?? null,
            'attachments' => cdp_csFilesFromTokens($_POST['files'] ?? [], $uid), 'set_status' => (string) ($_POST['set_status'] ?? ''),
            'ai_assisted' => !empty($_POST['ai_assisted']),
        ]);
        if (empty($r['ok'])) {
            $reply($r);
        }
        $reply(['ok' => true, 'message_id' => $r['message_id']] + $ticketPayload(cdp_csTicket($id)));

    case 'accept':
        $t = $needTicket($id);
        if (!$t->conversation_id) {
            $reply(['ok' => false, 'error' => 'This ticket has no live chat.']);
        }
        $r = cdp_csAccept($t->conversation_id, $uid, !empty($_POST['takeover']) && $isAdmin);
        if (empty($r['ok'])) {
            $reply($r);
        }
        $reply(['ok' => true] + $ticketPayload(cdp_csTicket($id)));

    case 'requeue': // give the chat back to the team queue (e.g. end of shift)
        $t = $needTicket($id);
        $conv = $t->conversation_id ? cdp_csConv($t->conversation_id) : null;
        if (!$conv || $conv->owner_state !== 'human_active') {
            $reply(['ok' => false, 'error' => 'Only a chat with a person can go back to the queue.']);
        }
        if ((int) $conv->owner_user_id !== $uid && !$isAdmin) {
            $reply(['ok' => false, 'error' => 'Only ' . cdp_csUserName($conv->owner_user_id) . ' or an administrator can do that.']);
        }
        cdp_csOwnerMove($conv->id, 'handoff_queued', 'staff', $uid, 'Returned to the team queue');
        cdp_csTicketUpdate($id, ['assignee_id' => null], 'staff', $uid);
        cdp_csNotifyTeam($id, 'Chat returned to the queue: ' . $t->public_ref);
        $reply(['ok' => true] + $ticketPayload(cdp_csTicket($id)));

    case 'release': // explicit, visible return to King
        $t = $needTicket($id);
        $conv = $t->conversation_id ? cdp_csConv($t->conversation_id) : null;
        if (!$conv || !in_array($conv->owner_state, ['human_active', 'handoff_queued'], true) || !cdp_csKingOn()) {
            $reply(['ok' => false, 'error' => cdp_csKingOn() ? 'This chat cannot go back to King.' : 'King is not switched on.']);
        }
        if (cdp_csOwnerMove($conv->id, 'ai_active', 'staff', $uid, 'Returned to King by ' . cdp_csUserName($uid))) {
            cdp_csMessageAdd($conv->id, $t->id, 'system', null, cdp_csSetting('king_name', 'King') . ' (our AI assistant) is back in this chat. Ask for a person at any time.', 'public', ['meta' => ['kind' => 'king_back']]);
        }
        $reply(['ok' => true] + $ticketPayload(cdp_csTicket($id)));

    case 'bulk':
        $ids = array_slice(array_unique(array_map('intval', (array) ($_POST['ids'] ?? []))), 0, 100);
        $ch = [];
        foreach (['status', 'priority', 'team_id', 'assignee_id', 'disposition', 'category'] as $k) {
            if (isset($_POST[$k]) && $_POST[$k] !== '') {
                $ch[$k] = in_array($k, ['team_id', 'assignee_id'], true) ? ((int) $_POST[$k] ?: null) : (string) $_POST[$k];
            }
        }
        $tag = cdp_csTag($_POST['tag'] ?? '');
        if (in_array($ch['disposition'] ?? '', ['spam', 'trash'], true) && !$isAdmin) {
            $reply(['ok' => false, 'error' => 'Only administrators can move tickets to spam or trash in bulk.']);
        }
        $results = [];
        foreach ($ids as $tid) {
            if (!cdp_csTicket($tid)) {
                $results[] = ['id' => $tid, 'ok' => false, 'error' => 'Not found'];
                continue;
            }
            $r = $ch ? cdp_csTicketUpdate($tid, $ch, 'staff', $uid) : ['ok' => true];
            if ($tag !== '') {
                cdp_csTagAdd($tid, $tag);
            }
            $results[] = ['id' => $tid, 'ok' => !empty($r['ok']), 'error' => $r['error'] ?? ''];
        }
        cdp_csEvent('bulk.update', null, null, 'staff', $uid, ['ids' => $ids, 'changes' => $ch, 'tag' => $tag]);
        $reply(['ok' => true, 'results' => $results]);

    case 'create':
        $internal = !empty($_POST['is_internal']);
        $cust = $internal ? 0 : (int) ($_POST['customer_id'] ?? 0);
        if (!$internal && $cust) {
            $cu = cdp_csOne("SELECT userlevel FROM cdb_users WHERE id = :i", [':i' => $cust]);
            if (!$cu || cdp_roleRankById((int) $cu->userlevel) !== 0) {
                $reply(['ok' => false, 'error' => 'Choose a customer account.']);
            }
        }
        $subject = cdp_csClip($_POST['subject'] ?? '', 190);
        if ($subject === '') {
            $reply(['ok' => false, 'error' => 'Add a subject.']);
        }
        $links = [];
        if (!empty($_POST['entity'])) {
            list($et, $eid) = array_pad(explode(':', (string) $_POST['entity']), 2, 0);
            $links[] = [$et, (int) $eid, ''];
        }
        $newId = cdp_csTicketCreate([
            'customer_id' => $cust ?: null, 'subject' => $subject, 'description' => (string) ($_POST['description'] ?? ''),
            'category' => $internal ? 'technical' : (string) ($_POST['category'] ?? 'general'), 'priority' => (string) ($_POST['priority'] ?? 'normal'),
            'channel' => (string) ($_POST['channel'] ?? 'staff'), 'team_id' => (int) ($_POST['team_id'] ?? 0), 'assignee_id' => (int) ($_POST['assignee_id'] ?? 0),
            'is_internal' => $internal, 'contact_name' => (string) ($_POST['contact_name'] ?? ''), 'contact_email' => (string) ($_POST['contact_email'] ?? ''),
            'links' => $links,
        ], 'staff', $uid, ['skip_ack' => empty($_POST['notify_customer'])]);
        if (!$newId) {
            $reply(['ok' => false, 'error' => 'The ticket could not be created.']);
        }
        if (trim((string) ($_POST['description'] ?? '')) !== '') {
            cdp_csMessageAdd(null, $newId, 'staff', $uid, (string) $_POST['description'], 'internal', ['meta' => ['kind' => 'description']]);
        }
        $reply(['ok' => true, 'id' => $newId]);

    case 'tag':
        $needTicket($id);
        if (!empty($_POST['remove'])) {
            cdp_csTagRemove($id, $_POST['tag'] ?? '');
        } else {
            cdp_csTagAdd($id, $_POST['tag'] ?? '');
        }
        cdp_csEvent('ticket.tags', $id, null, 'staff', $uid, [(!empty($_POST['remove']) ? 'removed' : 'added') => cdp_csTag($_POST['tag'] ?? '')]);
        $reply(['ok' => true, 'tags' => cdp_csTags($id)]);

    case 'follow':
        $needTicket($id);
        cdp_csFollow($id, $uid, !empty($_POST['on']));
        $reply(['ok' => true, 'followers' => cdp_csFollowers($id)]);

    case 'link':
        $needTicket($id);
        $ok = cdp_csLinkAdd($id, (string) ($_POST['type'] ?? ''), (int) ($_POST['entity_id'] ?? 0), '', $uid);
        if (!$ok) {
            $reply(['ok' => false, 'error' => 'That record does not exist or belongs to another customer.']);
        }
        cdp_csEvent('ticket.linked', $id, null, 'staff', $uid, ['type' => $_POST['type'] ?? '', 'entity' => (int) ($_POST['entity_id'] ?? 0)]);
        $reply(['ok' => true, 'links' => cdp_csLinks($id)]);

    case 'link_ref': // link by typing a tracking number
        $t = $needTicket($id);
        $ref = trim((string) ($_POST['ref'] ?? ''));
        $found = null;
        foreach (cdp_csEntityTypes() as $type => $m) {
            $r = cdp_csOne("SELECT a.{$m['id']} AS id FROM {$m['table']} a WHERE {$m['ref']} = :r AND ({$m['where']}) LIMIT 1", [':r' => $ref]);
            if ($r) {
                $found = [$type, (int) $r->id];
                break;
            }
        }
        if (!$found || !cdp_csLinkAdd($id, $found[0], $found[1], '', $uid)) {
            $reply(['ok' => false, 'error' => $found ? 'That record belongs to another customer.' : 'No record with that tracking number.']);
        }
        cdp_csEvent('ticket.linked', $id, null, 'staff', $uid, ['type' => $found[0], 'entity' => $found[1]]);
        $reply(['ok' => true, 'links' => cdp_csLinks($id)]);

    case 'unlink':
        cdp_csX("DELETE FROM cdb_cs_links WHERE id = :l AND ticket_id = :t", [':l' => (int) ($_POST['link_id'] ?? 0), ':t' => $id]);
        cdp_csEvent('ticket.unlinked', $id, null, 'staff', $uid, ['link' => (int) ($_POST['link_id'] ?? 0)]);
        $reply(['ok' => true, 'links' => cdp_csLinks($id)]);

    case 'task':
        $needTicket($id);
        $tid = cdp_csTaskAdd($id, (string) ($_POST['title'] ?? ''), (string) ($_POST['due_at'] ?? ''), (int) ($_POST['assignee_id'] ?? 0) ?: $uid, $uid);
        if (!$tid) {
            $reply(['ok' => false, 'error' => 'Give the task a title.']);
        }
        $reply(['ok' => true, 'tasks' => cdp_csTasks($id)]);

    case 'task_done':
        $task = cdp_csOne("SELECT * FROM cdb_cs_tasks WHERE id = :i", [':i' => (int) ($_POST['task_id'] ?? 0)]);
        if (!$task) {
            $reply(['ok' => false, 'error' => 'Task not found.']);
        }
        $done = !empty($_POST['done']);
        cdp_csX("UPDATE cdb_cs_tasks SET done_at = :d, done_by = :u WHERE id = :i", [':d' => $done ? cdp_csNow() : null, ':u' => $done ? $uid : null, ':i' => (int) $task->id]);
        cdp_csEvent($done ? 'task.done' : 'task.reopened', $task->ticket_id, null, 'staff', $uid, ['task' => (int) $task->id, 'title' => $task->title]);
        $reply(['ok' => true, 'tasks' => cdp_csTasks($task->ticket_id)]);

    case 'merge':
        $target = cdp_csTicketByRef((string) ($_POST['target_ref'] ?? ''));
        if (!$target) {
            $reply(['ok' => false, 'error' => 'No ticket with that reference.']);
        }
        $r = cdp_csMerge($id, $target->id, $uid);
        $reply($r + ['target_id' => (int) $target->id]);

    case 'view_save':
        $name = cdp_csClip($_POST['name'] ?? '', 80);
        if ($name === '') {
            $reply(['ok' => false, 'error' => 'Name the view.']);
        }
        $filters = array_intersect_key((array) ($_POST['filters'] ?? []), array_flip(['view', 'status', 'priority', 'category', 'channel', 'team_id', 'assignee_id', 'tag', 'language', 'q', 'sort']));
        $shared = !empty($_POST['shared']) && $isAdmin ? 1 : 0;
        cdp_csX("INSERT INTO cdb_cs_views (user_id, name, filters, shared, created_at) VALUES (:u, :n, :f, :s, :c)",
            [':u' => $uid, ':n' => $name, ':f' => cdp_csJson($filters), ':s' => $shared, ':c' => cdp_csNow()]);
        $reply(['ok' => true, 'saved_views' => cdp_csSavedViews($uid)]);

    case 'view_delete':
        cdp_csX("DELETE FROM cdb_cs_views WHERE id = :i AND (user_id = :u" . ($isAdmin ? ' OR shared = 1' : '') . ")", [':i' => (int) ($_POST['view_id'] ?? 0), ':u' => $uid]);
        $reply(['ok' => true, 'saved_views' => cdp_csSavedViews($uid)]);

    case 'proposal_create': // an agent asks an administrator to approve something (recorded; done on its own page)
        $t = $needTicket($id);
        $kind = (string) ($_POST['kind'] ?? 'other');
        if (!isset(cdp_csProposalKinds()[$kind])) {
            $reply(['ok' => false, 'error' => 'Choose what needs approval.']);
        }
        $details = ['summary' => cdp_csClip($_POST['summary'] ?? '', 500)];
        if (trim((string) ($_POST['amount'] ?? '')) !== '') {
            $details['amount'] = cdp_csClip($_POST['amount'], 40);
        }
        if ($details['summary'] === '') {
            $reply(['ok' => false, 'error' => 'Say what should be approved and why.']);
        }
        if (!cdp_csProposalCreate($id, $kind, $details, 'staff', $uid)) {
            $reply(['ok' => false, 'error' => 'The request could not be saved.']);
        }
        $reply(['ok' => true] + $ticketPayload(cdp_csTicket($id)));

    case 'proposal':
        $r = cdp_csProposalDecide((int) ($_POST['proposal_id'] ?? 0), (string) ($_POST['decision'] ?? ''), $user, (string) ($_POST['note'] ?? ''), (string) ($_POST['hash'] ?? ''));
        if (empty($r['ok'])) {
            $reply($r);
        }
        $reply(['ok' => true] + $ticketPayload(cdp_csTicket($id)));

    case 'run_rule':
        $needTicket($id);
        $rule = cdp_csOne("SELECT id FROM cdb_cs_rules WHERE id = :r AND trigger_event = 'manual' AND status = 'published'", [':r' => (int) ($_POST['rule_id'] ?? 0)]);
        if (!$rule) {
            $reply(['ok' => false, 'error' => 'Choose a published "run by hand" rule.']);
        }
        $res = cdp_csRulesRun('manual', $id, ['dedupe' => 'manual' . time()], false, (int) $rule->id);
        $reply(['ok' => true, 'result' => $res] + $ticketPayload(cdp_csTicket($id)));

    // ── Copilot ─────────────────────────────────────────────────────────────
    case 'ai_draft':
        $needTicket($id);
        session_write_close();
        $reply(cdp_csCopilotDraft($id, $user, (string) ($_POST['instruction'] ?? '')));

    case 'ai_summary':
        $needTicket($id);
        session_write_close();
        $reply(cdp_csCopilotSummary($id, $user, !empty($_POST['save'])));

    case 'ai_refine':
        session_write_close();
        $reply(cdp_csCopilotRefine((string) ($_POST['text'] ?? ''), (string) ($_POST['mode'] ?? ''), (string) ($_POST['lang'] ?? 'en'), $id ?: null));

    case 'ai_classify':
        $needTicket($id);
        session_write_close();
        $reply(cdp_csCopilotClassify($id, $user, false));

    case 'ai_kb':
        $t = $needTicket($id);
        if (!in_array($t->status, ['resolved', 'closed'], true)) {
            $reply(['ok' => false, 'error' => 'Draft an article from a resolved ticket.']);
        }
        session_write_close();
        $reply(cdp_csCopilotKbDraft($id, $user));
}
$reply(['ok' => false, 'error' => 'Unknown action.'], 400);
