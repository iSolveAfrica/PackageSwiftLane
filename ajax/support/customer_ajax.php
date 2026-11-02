<?php
// *************************************************************************
// * Customer Service — the customer side (Support Hub and chat widget).   *
// *                                                                       *
// * GET  action=bootstrap                 settings, chats, tickets, records *
// * GET  action=poll conversation_id after  new messages since a message  *
// * GET  action=ticket id                  one of my tickets               *
// * GET  action=help q                     help articles                   *
// * POST action=start   [entity_type entity_id subject]                    *
// * POST action=send    conversation_id message client_msg_id files[]      *
// * POST action=human   conversation_id                                    *
// * POST action=solved  conversation_id    "King solved it"                *
// * POST action=ticket_create subject category description entity          *
// * POST action=ticket_reply  id message client_msg_id files[]            *
// * POST action=rate    id score comment                                   *
// * POST action=upload  file                                               *
// * POST action=kb_vote id helpful                                         *
// *                                                                       *
// * Only signed-in customers. Every id is checked against the session's    *
// * own account; internal notes never leave the server through here.      *
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

if (!cdp_csIsCustomer($user)) {
    $reply(['ok' => false, 'error' => 'Help & Support is for customer accounts. Staff use Customer Service.'], 403);
}
if (!cdp_csEnsureTables()) {
    $reply(['ok' => false, 'error' => 'Support is not available right now (the database user needs the CREATE privilege once).']);
}

$uid    = cdp_csUid();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = (string) ($_REQUEST['action'] ?? 'bootstrap');

// Ticket fields a customer may see.
$publicTicket = function ($t) {
    return [
        'id' => (int) $t->id, 'ref' => (string) $t->public_ref, 'subject' => (string) $t->subject,
        'status' => cdp_csPublicStatus($t->status), 'status_key' => (string) $t->status,
        'category' => cdp_csCategories()[$t->category] ?? 'General question',
        'team' => (string) (cdp_csOne("SELECT name FROM cdb_cs_teams WHERE id = :i", [':i' => (int) $t->team_id])->name ?? 'Customer Support'),
        'created_at' => (string) $t->created_at, 'updated_at' => (string) $t->last_activity_at,
        'can_rate' => $t->status === 'resolved' && $t->csat_score === null && cdp_csSetting('csat_enabled', '1') === '1',
        'rating' => $t->csat_score === null ? null : (int) $t->csat_score,
        'waiting_on_you' => $t->status === 'pending_customer', 'conversation_id' => (int) $t->conversation_id,
    ];
};
$myTickets = function () use ($uid, $publicTicket) {
    return array_map($publicTicket, cdp_csQ("SELECT * FROM cdb_cs_tickets WHERE customer_id = :c AND is_internal = 0 AND disposition IN ('none','archived') AND merged_into IS NULL
        ORDER BY (status IN ('resolved','closed')), last_activity_at DESC LIMIT 50", [':c' => $uid]));
};
$convState = function ($conv) {
    $owner = '';
    if ($conv->owner_state === 'human_active' && $conv->owner_user_id) {
        $owner = explode(' ', cdp_csUserName($conv->owner_user_id))[0];
    }
    return ['owner' => (string) $conv->owner_state, 'owner_name' => $owner, 'ticket_ref' => $conv->ticket_id ? (string) (cdp_csTicket($conv->ticket_id)->public_ref ?? '') : ''];
};
$markRead = function ($convId, array $msgs) {
    if ($msgs) {
        cdp_csX("UPDATE cdb_cs_conversations SET customer_read_id = GREATEST(customer_read_id, :m) WHERE id = :i", [':m' => (int) end($msgs)['id'], ':i' => (int) $convId]);
    }
};

if ($method !== 'POST') {
    switch ($action) {
        case 'poll':
            $conv = cdp_csConvForCustomer((int) ($_GET['conversation_id'] ?? 0), $uid);
            if (!$conv) {
                $reply(['ok' => false, 'error' => 'Chat not found.'], 404);
            }
            $msgs = cdp_csMessages($conv->id, null, 'customer', (int) ($_GET['after'] ?? 0));
            $markRead($conv->id, $msgs);
            $typing = false;
            if ($conv->ticket_id) {
                $typing = (bool) array_filter(cdp_csPresence($conv->ticket_id, 0), function ($p) { return $p['typing']; });
            }
            $reply(['ok' => true, 'messages' => $msgs, 'typing' => $typing] + $convState($conv));

        case 'ticket':
            $t = cdp_csTicketForCustomer((int) ($_GET['id'] ?? 0), $uid);
            if (!$t) {
                $reply(['ok' => false, 'error' => 'Ticket not found.'], 404);
            }
            $links = array_map(function ($l) { return ['label' => $l['label'], 'ref' => $l['ref'], 'status' => $l['status']]; }, cdp_csLinks($t->id));
            $reply(['ok' => true, 'ticket' => $publicTicket($t), 'messages' => cdp_csMessages($t->conversation_id, $t->id, 'customer'), 'links' => $links]);

        case 'help':
            $q = trim((string) ($_GET['q'] ?? ''));
            $hits = $q === '' ? array_slice(array_values(array_filter(cdp_csKbList(false), function ($a) { return in_array($a['audience'], ['public', 'customer'], true); })), 0, 12)
                              : cdp_csKbSearch($q, ['public', 'customer'], 10);
            $reply(['ok' => true, 'articles' => array_map(function ($a) {
                return ['id' => $a['id'], 'title' => $a['title'], 'body' => $a['body'], 'category' => $a['category'], 'updated_at' => $a['updated_at']];
            }, $hits)]);

        default: // bootstrap
            $reply([
                'ok' => true,
                'king' => ['on' => cdp_csKingOn(), 'name' => cdp_csSetting('king_name', 'King'), 'greeting' => cdp_csSetting('king_greeting')],
                'hours' => cdp_csSetting('business_hours', ''),
                'conversations' => cdp_csConvListForCustomer($uid),
                'tickets' => $myTickets(),
                'records' => cdp_csCustomerEntities($uid, 30),
                'categories' => array_diff_key(cdp_csCategories(), ['technical' => 1]),
            ]);
    }
}

// ── POST ────────────────────────────────────────────────────────────────────
$userData = $user->cdp_getUserData();

switch ($action) {
    case 'upload':
        if (empty($_FILES['file'])) {
            $reply(['ok' => false, 'error' => 'Choose a file.']);
        }
        $r = cdp_csFileStore($_FILES['file'], $uid);
        $reply($r);

    case 'start':
        $subject = cdp_csClip((string) ($_POST['subject'] ?? ''), 190);
        $entity = null;
        if (!empty($_POST['entity_type']) && !empty($_POST['entity_id'])) {
            $e = cdp_csEntity((string) $_POST['entity_type'], (int) $_POST['entity_id']);
            if ($e && (int) $e['owner'] === $uid) { // ownership from the server, never from the page
                $entity = $e;
                $subject = $subject ?: ('About ' . strtolower($e['label']) . ' ' . $e['ref']);
            }
        }
        $id = cdp_csConvCreate($uid, 'web', $subject ?: null);
        if (!$id) {
            $reply(['ok' => false, 'error' => 'The chat could not be started.']);
        }
        if ($entity) {
            cdp_csX("UPDATE cdb_cs_conversations SET subject = :s WHERE id = :i", [':s' => $subject, ':i' => $id]);
        }
        $conv = cdp_csConv($id);
        $greet = [];
        if ($conv->owner_state === 'ai_active') {
            cdp_csMessageAdd($id, null, 'king', null, cdp_csSetting('king_greeting'), 'public', ['meta' => ['kind' => 'greeting']]);
        } else {
            $hours = trim((string) cdp_csSetting('business_hours', ''));
            cdp_csMessageAdd($id, null, 'system', null, "Tell us what you need and a member of our team will reply here." . ($hours !== '' ? "\n\nOur support hours: $hours" : ''), 'public', ['meta' => ['kind' => 'greeting']]);
        }
        $reply(['ok' => true, 'conversation_id' => $id, 'entity' => $entity ? ['type' => $entity['type'], 'id' => $entity['id'], 'ref' => $entity['ref']] : null,
            'messages' => cdp_csMessages($id, null, 'customer'), 'conversations' => cdp_csConvListForCustomer($uid)] + $convState($conv));

    case 'send':
        $conv = cdp_csConvForCustomer((int) ($_POST['conversation_id'] ?? 0), $uid);
        if (!$conv) {
            $reply(['ok' => false, 'error' => 'Chat not found.'], 404);
        }
        $text = trim((string) ($_POST['message'] ?? ''));
        $files = cdp_csFilesFromTokens($_POST['files'] ?? [], $uid);
        if ($text === '' && !$files) {
            $reply(['ok' => false, 'error' => 'Type a message first.']);
        }
        if (mb_strlen($text) > 4000) {
            $reply(['ok' => false, 'error' => 'That message is too long (4,000 characters at most).']);
        }
        $after = (int) ($_POST['after'] ?? 0);
        $m = cdp_csMessageAdd($conv->id, $conv->ticket_id, 'customer', $uid, $text, 'public', ['client_msg_id' => $_POST['client_msg_id'] ?? null, 'attachments' => $files]);
        if (!$m['id']) {
            $reply(['ok' => false, 'error' => 'Your message could not be saved. Try again.']);
        }
        if (!$m['duplicate']) {
            // A chat that was resolved comes back to life with whoever should own it.
            if ($conv->owner_state === 'resolved') {
                if ($conv->ticket_id) {
                    $tk = cdp_csTicket($conv->ticket_id);
                    if ($tk && in_array($tk->status, ['resolved', 'closed'], true)) {
                        cdp_csTicketUpdate($tk->id, ['status' => 'open'], 'customer', $uid, null, ['reason' => 'Customer wrote again']);
                    }
                    cdp_csOwnerMove($conv->id, $tk && $tk->assignee_id ? 'human_active' : 'handoff_queued', 'customer', $uid, 'Customer wrote again', null, $tk ? $tk->assignee_id : null);
                } else {
                    cdp_csOwnerMove($conv->id, cdp_csKingOn() ? 'ai_active' : 'handoff_queued', 'customer', $uid, 'Customer wrote again');
                }
                $conv = cdp_csConv($conv->id);
            }
            if ($conv->ticket_id) {
                $tk = cdp_csTicket($conv->ticket_id);
                if ($tk && $tk->status === 'pending_customer') {
                    cdp_csTicketUpdate($tk->id, ['status' => 'open'], 'customer', $uid, null, ['reason' => 'Customer replied']);
                }
                cdp_csRulesRun('message.customer', $conv->ticket_id, ['message' => $text, 'message_id' => $m['id']]);
                cdp_csNotifyTeam($conv->ticket_id, 'Customer replied on ' . ($tk->public_ref ?? 'a chat'));
            }
            session_write_close(); // King may take a while; never lock the customer's other tabs
            if ($conv->owner_state === 'ai_active') {
                if (cdp_csWantsHuman($text)) {
                    cdp_csHandover($conv->id, 'Customer asked for a person', 'customer', $uid);
                } else {
                    cdp_csKingRespond($conv->id, $user, $userData);
                }
            } elseif ($conv->owner_state === 'handoff_queued' && !$conv->ticket_id) {
                cdp_csHandover($conv->id, cdp_csKingOn() ? 'Customer asked for a person' : 'Chat with the support team', 'customer', $uid);
            }
        }
        $conv = cdp_csConv($conv->id);
        $msgs = cdp_csMessages($conv->id, null, 'customer', $after);
        $markRead($conv->id, $msgs);
        $reply(['ok' => true, 'message_id' => $m['id'], 'messages' => $msgs] + $convState($conv));

    case 'human':
        $conv = cdp_csConvForCustomer((int) ($_POST['conversation_id'] ?? 0), $uid);
        if (!$conv) {
            $reply(['ok' => false, 'error' => 'Chat not found.'], 404);
        }
        session_write_close();
        cdp_csHandover($conv->id, 'Customer pressed "Talk to a person"', 'customer', $uid);
        $conv = cdp_csConv($conv->id);
        $reply(['ok' => true, 'messages' => cdp_csMessages($conv->id, null, 'customer', (int) ($_POST['after'] ?? 0))] + $convState($conv));

    case 'solved':
        $conv = cdp_csConvForCustomer((int) ($_POST['conversation_id'] ?? 0), $uid);
        if (!$conv) {
            $reply(['ok' => false, 'error' => 'Chat not found.'], 404);
        }
        if ($conv->owner_state === 'ai_active') {
            // The customer's own confirmation: the only thing that counts as a verified King resolution.
            cdp_csX("UPDATE cdb_cs_conversations SET ai_outcome = 'confirmed' WHERE id = :i", [':i' => (int) $conv->id]);
            cdp_csOwnerMove($conv->id, 'resolved', 'customer', $uid, 'Customer confirmed King solved it');
            cdp_csMessageAdd($conv->id, null, 'system', null, 'Glad that helped. You can write here again any time.', 'public', ['meta' => ['kind' => 'closed']]);
        }
        $conv = cdp_csConv($conv->id);
        $reply(['ok' => true, 'messages' => cdp_csMessages($conv->id, null, 'customer', (int) ($_POST['after'] ?? 0))] + $convState($conv));

    case 'ticket_create':
        $subject = cdp_csClip((string) ($_POST['subject'] ?? ''), 190);
        $descr = trim((string) ($_POST['description'] ?? ''));
        if ($subject === '' || $descr === '') {
            $reply(['ok' => false, 'error' => 'Add a subject and tell us what happened.']);
        }
        $cat = (string) ($_POST['category'] ?? 'general');
        if ($cat === 'technical' || !isset(cdp_csCategories()[$cat])) {
            $cat = 'general';
        }
        $links = [];
        if (!empty($_POST['entity'])) {
            list($et, $eid) = array_pad(explode(':', (string) $_POST['entity']), 2, 0);
            $e = cdp_csEntity($et, (int) $eid);
            if ($e && (int) $e['owner'] === $uid) {
                $links[] = [$e['type'], $e['id'], $e['ref']];
            }
        }
        $similar = cdp_csSimilarOpen($uid);
        $id = cdp_csTicketCreate(['customer_id' => $uid, 'subject' => $subject, 'description' => $descr, 'category' => $cat, 'channel' => 'portal', 'links' => $links], 'customer', $uid, ['skip_ack' => true]);
        if (!$id) {
            $reply(['ok' => false, 'error' => 'Your request could not be saved. Try again.']);
        }
        $files = cdp_csFilesFromTokens($_POST['files'] ?? [], $uid);
        cdp_csMessageAdd(null, $id, 'customer', $uid, $descr, 'public', ['channel' => 'portal', 'attachments' => $files]);
        cdp_csAcknowledge($id);
        $t = cdp_csTicket($id);
        $reply(['ok' => true, 'ticket' => $publicTicket($t), 'tickets' => $myTickets(), 'similar_open' => count($similar)]);

    case 'ticket_reply':
        $t = cdp_csTicketForCustomer((int) ($_POST['id'] ?? 0), $uid);
        if (!$t) {
            $reply(['ok' => false, 'error' => 'Ticket not found.'], 404);
        }
        if ($t->status === 'closed') {
            $reply(['ok' => false, 'error' => 'This ticket is closed. Start a new chat or request and mention ' . $t->public_ref . '.']);
        }
        $text = trim((string) ($_POST['message'] ?? ''));
        $files = cdp_csFilesFromTokens($_POST['files'] ?? [], $uid);
        if ($text === '' && !$files) {
            $reply(['ok' => false, 'error' => 'Type a message first.']);
        }
        $m = cdp_csMessageAdd($t->conversation_id, $t->id, 'customer', $uid, mb_substr($text, 0, 4000), 'public', ['client_msg_id' => $_POST['client_msg_id'] ?? null, 'attachments' => $files, 'channel' => 'portal']);
        if (!$m['duplicate'] && $m['id']) {
            if (in_array($t->status, ['resolved', 'pending_customer'], true)) {
                cdp_csTicketUpdate($t->id, ['status' => 'open'], 'customer', $uid, null, ['reason' => 'Customer replied']);
            }
            cdp_csRulesRun('message.customer', $t->id, ['message' => $text, 'message_id' => $m['id']]);
            cdp_csNotifyTeam($t->id, 'Customer replied on ' . $t->public_ref);
        }
        $t = cdp_csTicket($t->id);
        $reply(['ok' => true, 'ticket' => $publicTicket($t), 'messages' => cdp_csMessages($t->conversation_id, $t->id, 'customer')]);

    case 'rate':
        $t = cdp_csTicketForCustomer((int) ($_POST['id'] ?? 0), $uid);
        $score = (int) ($_POST['score'] ?? 0);
        if (!$t || $score < 1 || $score > 5) {
            $reply(['ok' => false, 'error' => 'Choose 1 to 5 stars.']);
        }
        if ($t->csat_score !== null) {
            $reply(['ok' => false, 'error' => 'You already rated this ticket. Thank you!']);
        }
        if (!in_array($t->status, ['resolved', 'closed'], true)) {
            $reply(['ok' => false, 'error' => 'You can rate a ticket once it is resolved.']);
        }
        cdp_csX("UPDATE cdb_cs_tickets SET csat_score = :s, csat_comment = :c, csat_at = :n WHERE id = :i AND csat_score IS NULL",
            [':s' => $score, ':c' => cdp_csClip($_POST['comment'] ?? '', 2000), ':n' => cdp_csNow(), ':i' => (int) $t->id]);
        cdp_csEvent('csat.received', $t->id, $t->conversation_id, 'customer', $uid, ['score' => $score]);
        if ($score <= 2) {
            cdp_csRulesRun('csat.poor', $t->id, []);
        }
        $reply(['ok' => true, 'tickets' => $myTickets()]);

    case 'kb_vote':
        $col = !empty($_POST['helpful']) ? 'helpful' : 'not_helpful';
        cdp_csX("UPDATE cdb_cs_kb SET $col = $col + 1 WHERE id = :i AND status = 'published' AND audience IN ('public','customer')", [':i' => (int) ($_POST['id'] ?? 0)]);
        $reply(['ok' => true]);
}
$reply(['ok' => false, 'error' => 'Unknown action.'], 400);
