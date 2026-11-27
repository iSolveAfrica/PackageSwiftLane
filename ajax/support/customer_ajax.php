<?php
// *************************************************************************
// * Customer Service — the customer side (Support Hub and chat widget).   *
// *                                                                       *
// * GET  action=bootstrap                 settings, chats, tickets, records *
// * GET  action=brief                     King's personal briefing (chat)  *
// * GET  action=poll conversation_id after [peek]  new messages (peek: do  *
// *                                        not mark them read)            *
// * GET  action=ticket id                  one of my tickets               *
// * GET  action=help q                     help articles                   *
// * POST action=start   [entity_type entity_id subject via=king]           *
// * POST action=send    conversation_id message client_msg_id files[]      *
// * POST action=quick   conversation_id intent client_msg_id (quick reply) *
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
require_once(__DIR__ . '/../../helpers/sl_auth.php'); // money, dates and routes for King's briefing cards
require_login();
if (!isset($core) || !is_object($core)) {
    $core = new Core();
}
// One fixed clock for every support row, whatever ran first (the phone app's router creates Core, which switches
// PHP to the site's timezone, before this file runs): the support clock (cdp_csClockTz, php.ini's date.timezone),
// as the desk stamps them. King's account answers and briefing switch to the site's timezone themselves
// (cdp_csKingInSite), because the shipping records they read are written in it.
$csTz = cdp_csClockTz()->getName();
date_default_timezone_set($csTz);
$csClock = function () { return cdp_csNow(); };

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
    if (in_array($conv->owner_state, ['human_active', 'resolved'], true) && $conv->owner_user_id) {
        $owner = explode(' ', cdp_csUserName($conv->owner_user_id))[0];
    }
    $tk = $conv->ticket_id ? cdp_csTicket($conv->ticket_id) : null;
    $seen = $tk ? cdp_csOne("SELECT MAX(seen_at) AS s FROM cdb_cs_presence WHERE ticket_id = :t", [':t' => (int) $tk->id]) : null;
    return ['owner' => (string) $conv->owner_state, 'owner_name' => $owner, 'ticket_ref' => $tk ? (string) $tk->public_ref : '',
        // King chat extras: the ticket to rate once a person closed the chat, the queue while waiting, when the team last looked.
        'ticket_id' => $tk ? (int) $tk->id : 0, 'rated' => $tk && $tk->csat_score !== null ? (int) $tk->csat_score : null,
        'can_rate' => $tk && in_array($tk->status, ['resolved', 'closed'], true) && $tk->csat_score === null && cdp_csSetting('csat_enabled', '1') === '1',
        'queue' => $conv->owner_state === 'handoff_queued' ? cdp_csQueueInfo($conv) : null, 'staff_seen' => (string) ($seen->s ?? ''), 'now' => cdp_csNow()];
};
// What King may say about paying: My bills when the customer may see it, and whether online payment is on for them
// (as the My bills page decides it: permission, a configured gateway, consolidated billing). Otherwise bills are paid at the office.
$payOpts = function () use ($user) {
    $bills = function_exists('cdp_financeEnabled') && cdp_financeEnabled() && $user->cdp_hasPermission('view_my_bills') ? sl_route('MyBills') : '';
    $online = false;
    if ($bills !== '' && $user->cdp_hasPermission('pay_my_bills') && function_exists('sl_tableExists') && sl_tableExists('cdb_consolidate_customer_billing')
        && is_file(__DIR__ . '/../../helpers/fs_payments.php')) {
        require_once __DIR__ . '/../../helpers/fs_payments.php';
        $online = function_exists('cdp_fsGatewayConfigured') && cdp_fsGatewayConfigured('paystack');
    }
    return ['bills_url' => $bills, 'pay_online' => $online];
};
$markRead = function ($convId, array $msgs) {
    if ($msgs) {
        cdp_csX("UPDATE cdb_cs_conversations SET customer_read_id = GREATEST(customer_read_id, :m) WHERE id = :i", [':m' => (int) end($msgs)['id'], ':i' => (int) $convId]);
    }
};
// The newest message the customer can see in a conversation (the chat starts counting unread after it).
$lastId = function ($convId) {
    $r = cdp_csOne("SELECT MAX(id) AS m FROM cdb_cs_messages WHERE conversation_id = :c AND visibility = 'public'", [':c' => (int) $convId]);
    return (int) ($r->m ?? 0);
};
// The site's hour, for "Good morning": the same clock as the dashboard's own greeting.
$siteHour = function () {
    return (int) cdp_csKingInSite(function () { return date('G'); });
};
// A customer may not flood the chat (or the team's alerts): 20 messages a minute, 10 new chats an hour.
$tooFast = function ($newChat = false) use ($uid) {
    $n = cdp_csOne("SELECT COUNT(*) AS n FROM cdb_cs_messages WHERE author_type = 'customer' AND author_id = :u AND created_at >= :s", [':u' => $uid, ':s' => cdp_csNow(-60)]);
    if ((int) ($n->n ?? 0) >= 20) {
        return 'You are sending messages very quickly. Please wait a moment and try again.';
    }
    if ($newChat) {
        $c = cdp_csOne("SELECT COUNT(*) AS n FROM cdb_cs_conversations WHERE customer_id = :u AND created_at >= :s", [':u' => $uid, ':s' => cdp_csNow(-3600)]);
        if ((int) ($c->n ?? 0) >= 10) {
            return 'You have started a lot of chats in the last hour. Please continue in your current chat.';
        }
    }
    return '';
};

if ($method !== 'POST') {
    switch ($action) {
        case 'poll':
            $conv = cdp_csConvForCustomer((int) ($_GET['conversation_id'] ?? 0), $uid);
            if (!$conv) {
                $reply(['ok' => false, 'error' => 'Chat not found.'], 404);
            }
            $msgs = cdp_csMessages($conv->id, null, 'customer', (int) ($_GET['after'] ?? 0));
            if (empty($_GET['peek'])) { // the closed chat bubble only peeks: what the customer has not seen stays unread
                $markRead($conv->id, $msgs);
                // The chat was opened: everything it already showed (fetched earlier with peek) is read too,
                // up to the id the page has, never beyond what the conversation really holds.
                $to = (int) ($_GET['read_to'] ?? 0);
                if ($to > 0) {
                    cdp_csX("UPDATE cdb_cs_conversations SET customer_read_id = GREATEST(customer_read_id, LEAST(:t, (SELECT COALESCE(MAX(m.id), 0) FROM cdb_cs_messages m WHERE m.conversation_id = :c))) WHERE id = :i",
                        [':t' => $to, ':c' => (int) $conv->id, ':i' => (int) $conv->id]);
                }
            }
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

        case 'brief': // King's greeting: what matters now, from this customer's own records only
            $userData = $user->cdp_getUserData();
            $live = cdp_csKingLiveConv($uid);
            $b = cdp_csKingBriefing($uid, ['since' => (string) ($_SESSION['last'] ?? ''), 'exclude_ticket' => $live ? (int) $live->ticket_id : 0] + $payOpts());
            unset($b['facts']);
            $unread = 0;
            if ($live) {
                $u = cdp_csOne("SELECT COUNT(*) AS n FROM cdb_cs_messages WHERE conversation_id = :c AND visibility = 'public' AND author_type IN ('king','staff','system') AND id > :r",
                    [':c' => (int) $live->id, ':r' => (int) $live->customer_read_id]);
                $unread = (int) ($u->n ?? 0);
            }
            $reply(['ok' => true, 'now' => $csClock(), 'hour' => $siteHour(), 'first' => cdp_csKingFirst($userData), 'king' => ['name' => cdp_kingName(), 'ai' => cdp_csKingOn()],
                'hours' => (string) cdp_csSetting('business_hours', ''), 'brief' => $b,
                'live' => $live ? ['id' => (int) $live->id, 'unread' => $unread, 'last_id' => $lastId($live->id), 'now' => $csClock()] + $convState($live) : null]);

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
$first = cdp_csKingFirst($userData);

$viaKing = ($_POST['via'] ?? '') === 'king'; // the King chat (views/sl/inc/king_chat.php), not the Help page
if ($viaKing) {
    cdp_csKingViaChat(true); // hand-overs from it carry a short note: King and the chat's header already say what happens next
}

// What follows any customer message: a resolved chat comes back to life with whoever should own it,
// a waiting ticket reopens, rules run and the team hears about it (once a minute per chat, not per message).
// Returns the conversation as it is now.
$customerWrote = function ($conv, $text, $messageId) use ($uid) {
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
        cdp_csRulesRun('message.customer', $conv->ticket_id, ['message' => $text, 'message_id' => $messageId]);
        $recent = cdp_csOne("SELECT id FROM cdb_cs_messages WHERE conversation_id = :c AND author_type = 'customer' AND id < :m AND created_at >= :s LIMIT 1",
            [':c' => (int) $conv->id, ':m' => (int) $messageId, ':s' => cdp_csNow(-60)]);
        if (!$recent) {
            cdp_csNotifyTeam($conv->ticket_id, 'Customer replied on ' . ($tk->public_ref ?? 'a chat'));
        }
    }
    return $conv;
};

// The King chat never writes into a chat the team already closed: King owns the customer's next message, in a new
// chat (a second tab or device may still hold the old one). Returns the conversation to write to.
$kingChatConv = function ($conv) use ($uid, $viaKing, $tooFast, $reply) {
    if (!$viaKing || $conv->owner_state !== 'resolved') {
        return $conv;
    }
    if (($e = $tooFast(true)) !== '') {
        $reply(['ok' => false, 'error' => $e], 429);
    }
    $id = cdp_csConvCreate($uid, 'web', 'Chat with ' . cdp_kingName());
    $new = $id ? cdp_csConv($id) : null;
    if (!$new) {
        return $conv;
    }
    if ($new->owner_state === 'handoff_queued' && !$new->ticket_id) {
        cdp_csOwnerMove($id, 'ai_active', 'system', null, 'King chat');
        $new = cdp_csConv($id);
    }
    return $new;
};

// What King's last message offered (e.g. 'human' after "Would you like me to do that now?").
$kingOffer = function ($convId, $beforeId) {
    $m = cdp_csOne("SELECT author_type, meta FROM cdb_cs_messages WHERE conversation_id = :c AND visibility = 'public' AND author_type <> 'customer' AND id < :b ORDER BY id DESC LIMIT 1",
        [':c' => (int) $convId, ':b' => (int) $beforeId]);
    return $m && $m->author_type === 'king' ? (string) (cdp_csDecode($m->meta)['offer'] ?? '') : '';
};

// King answers a typed message while it owns the chat. A request for a person, or the question whether
// King is a person, is answered without the AI (truthfully, always); with no AI provider, short questions
// about the account are answered from the account, a problem only a person can decide (refund, damage,
// payment trouble ...) goes to the team with its topic, and anything else goes to a person in King's own words.
$kingAnswer = function ($conv, $text, $hasFiles, $messageId) use ($user, $userData, $uid, $first, $payOpts, $kingOffer) {
    $asks = cdp_csKingAsksIdentity($text);
    if (cdp_csWantsHuman($text)) {
        cdp_csKingHandover($conv->id, 'Customer asked for a person', cdp_csKingLine($asks ? 'identity_human' : 'wants', $first), 'customer', $uid);
        return;
    }
    if ($asks) {
        $q = cdp_csKingQuick('identity', $uid, $userData);
        cdp_csKingWrite($conv->id, $q['body'], $q['meta'], (int) $conv->owner_seq);
        return;
    }
    $offer = $kingOffer($conv->id, $messageId);
    $intent = $hasFiles ? null : cdp_csKingIntent($text, $uid, $offer);
    if ($intent === 'yes' && $offer === 'human') { // "yes" to "Would you like me to bring in someone?"
        cdp_csKingHandover($conv->id, 'Customer asked for a person', cdp_csKingLine('asked', $first), 'customer', $uid);
        return;
    }
    if (!cdp_csKingOn()) {
        if ($intent && ($q = cdp_csKingQuick($intent, $uid, $userData, $text, $payOpts()))) {
            cdp_csKingWrite($conv->id, $q['body'], $q['meta'], (int) $conv->owner_seq);
            return;
        }
        if (cdp_csKingTopic($text)) {
            cdp_csKingHandover($conv->id, 'Needs a person', cdp_csKingLine('topic', $first), 'customer', $uid, [], (int) $conv->owner_seq);
            return;
        }
    } elseif (in_array($intent, ['hello', 'thanks', 'ack', 'no'], true) && ($q = cdp_csKingQuick($intent, $uid, $userData, $text))) {
        cdp_csKingWrite($conv->id, $q['body'], $q['meta'], (int) $conv->owner_seq); // small talk needs no AI turn
        return;
    }
    cdp_csKingRespond($conv->id, $user, $userData);
};

// While the chat waits for a person, King still answers "am I talking to a bot?" truthfully, and says once that
// the team will see what the customer adds.
$queuedAnswer = function ($conv, $text) use ($uid) {
    if ($conv->owner_state !== 'handoff_queued' || !$conv->ticket_id) {
        return;
    }
    if ($r = cdp_csKingFindRef($text, $uid)) { // a parcel named while waiting is linked to the ticket for the team (only their own)
        cdp_csLinkAdd($conv->ticket_id, $r['type'], $r['id'], $r['ref'], null);
    }
    $king = cdp_kingName();
    $site = cdp_csKingSite();
    if (cdp_csKingAsksIdentity($text)) {
        cdp_csMessageAdd($conv->id, $conv->ticket_id, 'king', null, "I'm $king, $site's automated customer care assistant, not a person. A member of our customer care team will reply right here.", 'public', ['meta' => ['kind' => 'queued_identity']]);
        return;
    }
    $acked = cdp_csOne("SELECT id FROM cdb_cs_messages WHERE conversation_id = :c AND author_type = 'king' AND meta LIKE :k AND created_at >= :h LIMIT 1",
        [':c' => (int) $conv->id, ':k' => '%"queued_ack"%', ':h' => (string) ($conv->handover_at ?: cdp_csNow(-86400))]);
    if (!$acked) {
        cdp_csMessageAdd($conv->id, $conv->ticket_id, 'king', null, "Thanks, I've added that to your chat. Our team will see it when they pick it up.", 'public', ['meta' => ['kind' => 'queued_ack']]);
    }
};

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
        if (($e = $tooFast(true)) !== '') {
            $reply(['ok' => false, 'error' => $e], 429);
        }
        $id = cdp_csConvCreate($uid, 'web', $subject ?: ($viaKing ? 'Chat with ' . cdp_kingName() : null));
        if (!$id) {
            $reply(['ok' => false, 'error' => 'The chat could not be started.']);
        }
        if ($entity) {
            cdp_csX("UPDATE cdb_cs_conversations SET subject = :s WHERE id = :i", [':s' => $subject, ':i' => $id]);
        }
        $conv = cdp_csConv($id);
        if ($viaKing) {
            // The King chat already greeted with the briefing. King owns the chat (account answers, the AI when set up)
            // until the customer asks for a person, even when King does not answer customers on the Help page.
            if ($conv->owner_state === 'handoff_queued' && !$conv->ticket_id) {
                cdp_csOwnerMove($id, 'ai_active', 'system', null, 'King chat');
                $conv = cdp_csConv($id);
            }
        } elseif ($conv->owner_state === 'ai_active') {
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
        if (($e = $tooFast()) !== '') {
            $reply(['ok' => false, 'error' => $e], 429);
        }
        $conv = $kingChatConv($conv);
        $m = cdp_csMessageAdd($conv->id, $conv->ticket_id, 'customer', $uid, $text, 'public', ['client_msg_id' => $_POST['client_msg_id'] ?? null, 'attachments' => $files]);
        if (!$m['id']) {
            $reply(['ok' => false, 'error' => 'Your message could not be saved. Try again.']);
        }
        if (!$m['duplicate']) {
            $wasQueued = $conv->owner_state === 'handoff_queued' && $conv->ticket_id;
            $conv = $customerWrote($conv, $text, $m['id']);
            session_write_close(); // King may take a while; never lock the customer's other tabs
            if ($conv->owner_state === 'ai_active') {
                $kingAnswer($conv, $text, (bool) $files, $m['id']);
            } elseif ($conv->owner_state === 'handoff_queued' && !$conv->ticket_id) {
                cdp_csHandover($conv->id, cdp_csKingOn() ? 'Customer asked for a person' : 'Chat with the support team', 'customer', $uid);
            } elseif ($viaKing && $wasQueued) {
                $queuedAnswer($conv, $text);
            }
        }
        $conv = cdp_csConv($conv->id);
        $msgs = cdp_csMessages($conv->id, null, 'customer', $after);
        $markRead($conv->id, $msgs);
        $reply(['ok' => true, 'message_id' => $m['id'], 'conversation_id' => (int) $conv->id, 'messages' => $msgs] + $convState($conv));

    case 'human':
        $conv = cdp_csConvForCustomer((int) ($_POST['conversation_id'] ?? 0), $uid);
        if (!$conv) {
            $reply(['ok' => false, 'error' => 'Chat not found.'], 404);
        }
        if ($viaKing && $conv->owner_state === 'resolved') {
            $conv = $kingChatConv($conv);
        }
        session_write_close();
        // King says it is bringing someone in (when King has the chat); the packet carries the account snapshot.
        cdp_csKingHandover($conv->id, 'Customer pressed "Talk to a person"', cdp_csKingLine('asked', $first), 'customer', $uid);
        $conv = cdp_csConv($conv->id);
        $reply(['ok' => true, 'conversation_id' => (int) $conv->id, 'messages' => cdp_csMessages($conv->id, null, 'customer', (int) ($_POST['after'] ?? 0))] + $convState($conv));

    case 'quick': // a quick reply: the customer's words come from the server's list, King answers from the account
        $intents = cdp_csKingIntents();
        $intent = (string) ($_POST['intent'] ?? '');
        if (!isset($intents[$intent])) {
            $reply(['ok' => false, 'error' => 'Unknown question.'], 400);
        }
        $conv = cdp_csConvForCustomer((int) ($_POST['conversation_id'] ?? 0), $uid);
        if (!$conv) {
            $reply(['ok' => false, 'error' => 'Chat not found.'], 404);
        }
        $after = (int) ($_POST['after'] ?? 0);
        if (($e = $tooFast()) !== '') {
            $reply(['ok' => false, 'error' => $e], 429);
        }
        $conv = $kingChatConv($conv);
        $m = cdp_csMessageAdd($conv->id, $conv->ticket_id, 'customer', $uid, $intents[$intent], 'public', ['client_msg_id' => $_POST['client_msg_id'] ?? null, 'meta' => ['kind' => 'quick', 'intent' => $intent]]);
        if (!$m['id']) {
            $reply(['ok' => false, 'error' => 'Your message could not be saved. Try again.']);
        }
        if (!$m['duplicate']) {
            $conv = $customerWrote($conv, $intents[$intent], $m['id']);
            session_write_close();
            if ($conv->owner_state === 'ai_active') {
                if ($intent === 'human') {
                    cdp_csKingHandover($conv->id, 'Customer pressed "Talk to a person"', cdp_csKingLine('asked', $first), 'customer', $uid);
                } elseif ($q = cdp_csKingQuick($intent, $uid, $userData, '', $payOpts())) {
                    cdp_csKingWrite($conv->id, $q['body'], $q['meta'], (int) $conv->owner_seq);
                }
            } elseif ($conv->owner_state === 'handoff_queued' && !$conv->ticket_id) {
                cdp_csHandover($conv->id, 'Chat with the support team', 'customer', $uid);
            } // with a person on the chat, the quick reply is simply the customer's message to them
        }
        $conv = cdp_csConv($conv->id);
        $msgs = cdp_csMessages($conv->id, null, 'customer', $after);
        $markRead($conv->id, $msgs);
        $reply(['ok' => true, 'message_id' => $m['id'], 'conversation_id' => (int) $conv->id, 'messages' => $msgs] + $convState($conv));

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
