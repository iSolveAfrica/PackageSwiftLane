<?php
// *************************************************************************
// * WhatsApp screen (whatsapp_web.php) - staff endpoint.                  *
// *                                                                       *
// * One dispatcher: ?action=... GET reads, POST writes (CSRF checked by   *
// * require_login). Staff (rank 2+) read chats and reply; administrators  *
// * (rank 3+) link or unlink the phone (QR), restart the instance, set up *
// * live updates, export contacts and group members and save them as     *
// * contacts campaigns can reach. Every provider call goes through        *
// * helpers/ultramsg.php, which never throws: a missing feature comes     *
// * back with a code and the page hides it with a note.                   *
// *************************************************************************

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/querys.php');
require_login();
require_once(__DIR__ . '/../../helpers/ultramsg.php');

$action = (string) ($_REQUEST['action'] ?? '');
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$P = function ($k, $d = '') { return isset($_POST[$k]) && is_scalar($_POST[$k]) ? (string) $_POST[$k] : $d; };
$G = function ($k, $d = '') { return isset($_GET[$k]) && is_scalar($_GET[$k]) ? (string) $_GET[$k] : $d; };

$rank = cdp_opsRank();
if ($rank < 2) {
    cdp_opsOut(['ok' => false, 'error' => 'Staff only.'], 403);
}
$isAdmin = $rank >= 3;
$adminOnly = ['qr', 'instance', 'webhook_setup', 'export', 'save_contacts', 'quick_replies_save', 'sync_contacts'];
if (in_array($action, $adminOnly, true) && !$isAdmin) {
    cdp_opsOut(['ok' => false, 'error' => 'Administrators only.'], 403);
}
$writes = ['instance', 'webhook_setup', 'send', 'chat_update', 'tags', 'ticket', 'suggest', 'save_contacts', 'quick_replies_save', 'sync_contacts', 'read'];
if (in_array($action, $writes, true) && !$isPost) {
    cdp_opsOut(['ok' => false, 'error' => 'Use POST.'], 405);
}
if (!cdp_umEnsureTables()) {
    cdp_opsOut(['ok' => false, 'error' => 'The WhatsApp tables could not be created. The database user needs the CREATE privilege once.'], 500);
}
$me = cdp_opsUid();

/** The chat a request is about (validated id and its row). */
$chatOf = function ($id) {
    $id = trim((string) $id);
    if (!cdp_umValidChatId($id)) {
        cdp_opsOut(['ok' => false, 'error' => 'Unknown chat.'], 404);
    }
    $c = cdp_opsOne("SELECT * FROM cdb_wa_chats WHERE chat_id = :c", [':c' => $id]);
    if (!$c) {
        cdp_opsOut(['ok' => false, 'error' => 'Chat not found. Refresh the list.'], 404);
    }
    return $c;
};

/** The WhatsApp Inbox conversation of a one-to-one chat (created when $create). */
$threadOf = function ($c, $create = false) {
    if ((int) $c->is_group === 1 || (string) $c->phone === '') {
        return null;
    }
    require_once __DIR__ . '/../../helpers/comms.php';
    if (!cdp_commEnsureTables()) {
        return null;
    }
    $t = cdp_opsOne("SELECT * FROM cdb_comm_threads WHERE phone = :p", [':p' => (string) $c->phone]);
    if (!$t && $create) {
        $t = cdp_commThread((string) $c->phone, (string) $c->name);
    }
    return $t;
};

/** Chat list with the effective status / assignee (inbox thread for people, the chat row for groups). */
$listChats = function ($filter, $q) use ($me) {
    $hasThreads = cdp_opsTableExists('cdb_comm_threads');
    $th = $hasThreads ? 'LEFT JOIN cdb_comm_threads th ON (c.is_group = 0 AND th.phone = c.phone)' : '';
    $thc = $hasThreads ? 'th.id thread_id, th.status th_status, th.assignee_id th_assignee, th.bot_active' : 'NULL thread_id, NULL th_status, NULL th_assignee, 0 bot_active';
    $status = $hasThreads ? "IF(c.is_group = 1, c.status, COALESCE(th.status, 'open'))" : 'c.status';
    $assignee = $hasThreads ? 'IF(c.is_group = 1, c.assignee_id, th.assignee_id)' : 'c.assignee_id';
    $w = '1=1';
    $b = [];
    $q = trim((string) $q);
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $w .= " AND (c.name LIKE :q1 OR c.last_preview LIKE :q2 OR u.locker LIKE :q3 OR CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')) LIKE :q4 OR c.tags LIKE :q5";
        foreach ([1, 2, 3, 4, 5] as $i) {
            $b[':q' . $i] = $like;
        }
        $digits = preg_replace('/\D+/', '', $q);
        if (strlen($digits) >= 3) {
            $w .= ' OR c.phone LIKE :qd';
            $b[':qd'] = '%' . $digits . '%';
        }
        $w .= ')';
    }
    $from = "FROM cdb_wa_chats c $th LEFT JOIN cdb_users u ON u.id = c.user_id";
    $cnt = cdp_opsOne("SELECT COUNT(*) a, SUM(c.unread > 0) un, SUM(c.is_group = 1) g, SUM(c.user_id IS NOT NULL) cu, SUM($assignee = :me AND $status <> 'closed') mi $from WHERE $w", $b + [':me' => $me]);
    $fw = '';
    if ($filter === 'unread') {
        $fw = ' AND c.unread > 0';
    } elseif ($filter === 'groups') {
        $fw = ' AND c.is_group = 1';
    } elseif ($filter === 'customers') {
        $fw = ' AND c.user_id IS NOT NULL';
    } elseif ($filter === 'mine') {
        $fw = " AND $assignee = :me2 AND $status <> 'closed'";
        $b[':me2'] = $me;
    }
    $rows = cdp_opsQ("SELECT c.id, c.chat_id, c.is_group, c.name, c.phone, c.user_id, c.unread, c.last_ts, c.last_preview, c.last_from_me, c.last_ack, c.pinned, c.muted,
                             c.tags, c.participants, c.updated_at, $thc, $status eff_status, $assignee eff_assignee, u.locker,
                             TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) customer_name
                      $from WHERE $w$fw ORDER BY c.pinned DESC, c.last_ts DESC, c.id DESC LIMIT 400", $b);
    $out = [];
    foreach ($rows as $r) {
        $out[] = ['id' => (string) $r->chat_id, 'group' => (int) $r->is_group, 'name' => (string) $r->name, 'phone' => (string) $r->phone,
            'customer' => (string) $r->customer_name, 'user_id' => (int) $r->user_id, 'locker' => (string) $r->locker, 'unread' => (int) $r->unread,
            'ts' => (int) $r->last_ts, 'preview' => (string) $r->last_preview, 'from_me' => (int) $r->last_from_me, 'ack' => (string) $r->last_ack,
            'pinned' => (int) $r->pinned, 'muted' => (int) $r->muted, 'tags' => array_values(array_filter(array_map('trim', explode(',', (string) $r->tags)))),
            'status' => (string) $r->eff_status, 'assignee' => (int) $r->eff_assignee, 'bot' => (int) $r->bot_active === 1 && $r->th_status === 'bot' ? 1 : 0,
            'participants' => (int) $r->participants];
    }
    $counts = ['all' => (int) ($cnt->a ?? 0), 'unread' => (int) ($cnt->un ?? 0), 'groups' => (int) ($cnt->g ?? 0), 'customers' => (int) ($cnt->cu ?? 0), 'mine' => (int) ($cnt->mi ?? 0)];
    return ['rows' => $out, 'counts' => $counts, 'sig' => md5(cdp_opsJson([$out, $counts]))];
};

/** Messages of a chat from the local copy (oldest first) and their signature. */
$listMessages = function ($chatId, $limit = 200) {
    $rows = array_reverse(cdp_opsQ("SELECT m.id, m.msg_id, m.from_me, m.author, m.author_name, m.type, m.body, m.media_url, m.thumb, m.filename, m.ack, m.ts, m.sent_by
                                    FROM cdb_wa_messages m WHERE m.chat_id = :c ORDER BY m.ts DESC, m.id DESC LIMIT " . (int) $limit, [':c' => $chatId]));
    $names = [];
    $authors = [];
    foreach ($rows as $r) {
        if ($r->author) {
            $authors[] = cdp_umPhoneOf($r->author);
        }
    }
    $people = $authors ? cdp_umPeople($authors) : [];
    $out = [];
    foreach ($rows as $r) {
        $ph = $r->author ? cdp_umPhoneOf($r->author) : '';
        $an = $ph !== '' ? ($people[$ph]['customer'] ?: ($people[$ph]['name'] ?: (string) $r->author_name)) : '';
        if ($r->sent_by && !isset($names[(int) $r->sent_by])) {
            $names[(int) $r->sent_by] = cdp_opsUserName((int) $r->sent_by);
        }
        $out[] = ['id' => (int) $r->id, 'mid' => (string) $r->msg_id, 'me' => (int) $r->from_me, 'author' => $ph, 'author_name' => $an,
            'type' => (string) $r->type, 'body' => (string) $r->body, 'media' => (string) $r->media_url, 'thumb' => (string) $r->thumb,
            'filename' => (string) $r->filename, 'ack' => (string) $r->ack, 'ts' => (int) $r->ts, 'by' => $r->sent_by ? $names[(int) $r->sent_by] : ''];
    }
    $sig = md5(cdp_opsJson(array_map(function ($m) { return [$m['id'], $m['ack'], $m['media'] !== '']; }, $out)));
    return ['messages' => $out, 'sig' => $sig];
};

/** Status of the connection the page shows (state, phone, what the provider supports). */
$stateOf = function ($fresh = false) use ($isAdmin) {
    $cfg = cdp_umConfig();
    $st = cdp_umStatus($fresh);
    $out = ['ok' => true, 'configured' => $cfg['configured'], 'active' => $cfg['active'], 'instance' => $cfg['instance'], 'state' => $st['state'],
        'raw' => $st['raw'], 'error' => $st['error'] ?? '', 'checked' => (int) ($st['at'] ?? time()), 'me' => null, 'is_admin' => $isAdmin,
        'cached' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_wa_chats"),
        'caps' => ['chats' => cdp_umCap('chats'), 'messages' => cdp_umCap('messages'), 'contacts' => cdp_umCap('contacts'),
            'groups' => cdp_umCap('group') || cdp_umCap('groups'), 'read' => cdp_umCap('read')]];
    if ($st['state'] === 'connected') {
        $out['me'] = cdp_umMe();
    }
    return $out;
};

$webhookUrl = function () {
    require_once __DIR__ . '/../../helpers/comms.php';
    $s = cdp_commSettings();
    return $s['webhook_key'] !== '' ? cdp_commWebhookBase() . '?key=' . $s['webhook_key'] : '';
};

switch ($action) {

    case 'state':
        $st = $stateOf($G('fresh') === '1');
        if ($isAdmin && $st['state'] === 'connected' && $G('webhook') === '1') {
            $st['webhook'] = cdp_umWebhookSettings();
            $st['webhook']['can_setup'] = $webhookUrl() !== '';
        }
        cdp_opsOut($st);

    case 'qr':
        $st = cdp_umStatus(true);
        if ($st['state'] === 'connected') {
            cdp_opsOut(['ok' => true, 'connected' => true, 'state' => 'connected']);
        }
        $qr = cdp_umQr();
        cdp_opsOut(['ok' => $qr['image'] !== '' || $qr['text'] !== '', 'connected' => false, 'state' => $st['state'], 'image' => $qr['image'], 'text' => $qr['text'],
            'error' => $qr['error'], 'next' => 20]);

    case 'instance':
        $op = $P('op');
        $r = cdp_umInstanceAction($op);
        if ($r['ok'] && in_array($op, ['logout'], true)) {
            cdp_umAudit('instance', 'Unlinked the WhatsApp phone', 'logout', 0);
        }
        $labels = ['logout' => 'The phone is unlinked. Scan the new QR code to link a phone again.', 'restart' => 'The instance is restarting. This takes a few seconds.',
            'takeover' => 'This instance is the active WhatsApp Web session again.'];
        cdp_opsOut(['ok' => $r['ok'], 'message' => $r['ok'] ? ($labels[$op] ?? 'Done.') : '', 'error' => $r['ok'] ? '' : ($r['code'] === 'unsupported' ? 'The provider does not offer this action.' : $r['error'])]);

    case 'webhook_setup':
        $url = $webhookUrl();
        $r = cdp_umWebhookSetup($url);
        cdp_opsOut(['ok' => $r['ok'], 'message' => $r['ok'] ? 'Live updates are on: new messages and ticks now arrive as they happen.' : '', 'error' => $r['error']]);

    case 'chats':
        $sync = ['ok' => true, 'code' => 'cached', 'error' => ''];
        if ($G('sync') === '1' && cdp_umStatus()['state'] === 'connected') {
            $sync = cdp_umSyncChats($G('force') === '1');
        }
        $l = $listChats($G('filter'), mb_substr($G('q'), 0, 80));
        cdp_opsOut(['ok' => true, 'sync' => ['ok' => $sync['ok'], 'code' => $sync['code'], 'error' => $sync['error']]] + $l + ['me' => $me]);

    case 'chat':
        $c = $chatOf($G('id'));
        $connected = cdp_umStatus()['state'] === 'connected';
        $sync = ['ok' => true, 'code' => 'offline', 'error' => ''];
        if ($connected) {
            $sync = cdp_umSyncMessages((string) $c->chat_id, 80, $G('force') === '1', 2);
            if ((int) $c->unread > 0) {
                cdp_umMarkRead((string) $c->chat_id);
            }
            if ((int) $c->is_group === 1 && $c->participants === null) {
                cdp_umGroupParticipants((string) $c->chat_id);
            }
            $c = cdp_opsOne("SELECT * FROM cdb_wa_chats WHERE id = :id", [':id' => (int) $c->id]);
        } elseif ((int) $c->unread > 0) {
            cdp_opsX("UPDATE cdb_wa_chats SET unread = 0 WHERE id = :id", [':id' => (int) $c->id]);
        }
        $t = $threadOf($c);
        $customer = (int) $c->user_id ? cdp_umCustomer((int) $c->user_id) : null;
        require_once __DIR__ . '/../../helpers/sl_routes.php';
        if ($customer) {
            $customer['url'] = $user->cdp_hasPermission(['view_client_list', 'edit_client']) ? sl_route('CustomerProfile', ['id' => (int) $customer['id']]) : '';
            foreach ($customer['shipments'] as &$s) {
                $s['url'] = $s['kind'] === 'package' ? 'customer_packages_view.php?id=' . $s['id'] : 'courier_view.php?id=' . $s['id'];
            }
            unset($s);
            $customer['history_url'] = sl_route('CommsCustomers') . '?user=' . (int) $customer['id'];
        }
        require_once __DIR__ . '/../../helpers/comms.php';
        $bot = cdp_commSetting('bot_enabled') === '1';
        $g = (int) $c->is_group === 1 ? cdp_opsOne("SELECT participant_count, source FROM cdb_wa_groups WHERE chat_id = :c", [':c' => (string) $c->chat_id]) : null;
        $m = $listMessages((string) $c->chat_id);
        cdp_opsOut(['ok' => true, 'sync' => ['ok' => $sync['ok'], 'code' => $sync['code'], 'error' => $sync['error']], 'connected' => $connected,
            'chat' => ['id' => (string) $c->chat_id, 'group' => (int) $c->is_group, 'name' => (string) $c->name, 'phone' => (string) $c->phone,
                'tags' => array_values(array_filter(array_map('trim', explode(',', (string) $c->tags)))),
                'status' => (int) $c->is_group === 1 ? (string) $c->status : ($t ? (string) $t->status : 'open'),
                'assignee' => (int) ((int) $c->is_group === 1 ? $c->assignee_id : ($t->assignee_id ?? 0)),
                'assignee_name' => cdp_opsUserName((int) ((int) $c->is_group === 1 ? $c->assignee_id : ($t->assignee_id ?? 0))),
                'bot' => $t && (int) $t->bot_active === 1 && $t->status === 'bot' ? 1 : 0, 'thread_id' => $t ? (int) $t->id : 0,
                'participants' => $g ? (int) $g->participant_count : (int) $c->participants, 'participants_source' => $g ? (string) $g->source : ''],
            'customer' => $customer, 'bot_enabled' => $bot, 'inbox_url' => sl_route('CommsInbox'),
            'me' => $me] + $m);

    case 'poll':
        // Live updates by polling: the list and the open chat, only when they changed.
        $st = cdp_umStatus();
        $out = ['ok' => true, 'state' => $st['state']];
        if ($st['state'] === 'connected') {
            cdp_umSyncChats(false, 15);
        }
        $l = $listChats($G('filter'), mb_substr($G('q'), 0, 80));
        if ($l['sig'] !== $G('lsig')) {
            $out += $l;
        } else {
            $out['sig'] = $l['sig'];
        }
        $cid = $G('chat');
        if ($cid !== '' && cdp_umValidChatId($cid)) {
            if ($st['state'] === 'connected') {
                cdp_umSyncMessages($cid, 40, false, 4);
                if ((int) cdp_opsVal("SELECT unread FROM cdb_wa_chats WHERE chat_id = :c", [':c' => $cid], 0) > 0 && $G('visible') === '1') {
                    cdp_umMarkRead($cid);
                }
            }
            $m = $listMessages($cid);
            $out['msig'] = $m['sig'];
            if ($m['sig'] !== $G('msig')) {
                $out['messages'] = $m['messages'];
            }
        }
        cdp_opsOut($out);

    case 'read':
        $c = $chatOf($P('chat_id'));
        cdp_umMarkRead((string) $c->chat_id);
        cdp_opsOut(['ok' => true]);

    case 'send':
        $c = $chatOf($P('chat_id'));
        $text = trim(str_replace("\r\n", "\n", $P('body')));
        $media = null;
        if (!empty($_FILES['file']) && is_array($_FILES['file']) && (int) ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            require_once __DIR__ . '/../../helpers/push_compose.php';
            $tmp = (string) ($_FILES['file']['tmp_name'] ?? '');
            $stored = cdp_pushMediaStore($_FILES['file'], (string) (cdp_getSettingsCourier()->site_url ?? ''));
            if (empty($stored['success'])) {
                cdp_opsOut(['ok' => false, 'error' => (string) ($stored['message'] ?? 'The file could not be saved.')]);
            }
            if ($stored['kind'] === 'video') {
                cdp_opsOut(['ok' => false, 'error' => 'Send pictures or documents from here. Videos are not supported on this screen yet.']);
            }
            $path = cdp_pushMediaDirFs() . '/' . basename((string) $stored['file']);
            $mime = function_exists('mime_content_type') ? (string) @mime_content_type($path) : '';
            $media = ['kind' => $stored['kind'], 'url' => (string) ($stored['url'] ?? ''), 'path' => $path, 'filename' => (string) ($stored['name'] ?? 'file'), 'mime' => $mime];
            unset($tmp);
        }
        if ($text === '' && !$media) {
            cdp_opsOut(['ok' => false, 'error' => 'Write a message or attach a file first.']);
        }
        if (mb_strlen($text) > 4000) {
            cdp_opsOut(['ok' => false, 'error' => 'WhatsApp messages can be up to 4,000 characters.']);
        }
        $t = $threadOf($c);
        if ($t && $t->status === 'closed') {
            cdp_opsOut(['ok' => false, 'error' => 'This conversation is closed. Reopen it to reply.', 'closed' => true]);
        }
        if ((int) $c->is_group === 1 && $c->status === 'closed') {
            cdp_opsOut(['ok' => false, 'error' => 'This group chat is closed here. Reopen it to reply.', 'closed' => true]);
        }
        $r = cdp_umSend((string) $c->chat_id, $text, $media, $me);
        if ($r['ok'] && $t) {
            // The inbox shows the same conversation: a person who replied takes it, King stops answering.
            require_once __DIR__ . '/../../helpers/comms.php';
            cdp_commThreadLogOutbound((string) $c->phone, (int) $c->user_id, $text !== '' ? $text : '[' . $media['kind'] . ']', 'agent', $me, $r['provider_id'] ?? null);
            cdp_opsX("UPDATE cdb_comm_threads SET status = 'open', bot_active = 0, handoff_reason = NULL, unread = 0, assignee_id = COALESCE(assignee_id, :u) WHERE id = :id",
                [':u' => $me, ':id' => (int) $t->id]);
        }
        $m = $listMessages((string) $c->chat_id);
        cdp_opsOut(['ok' => $r['ok'], 'error' => $r['ok'] ? '' : 'Not sent: ' . $r['error'], 'msig' => $m['sig'], 'messages' => $m['messages']]);

    case 'chat_update':
        $c = $chatOf($P('chat_id'));
        $op = $P('op');
        if (!in_array($op, ['assign_me', 'unassign', 'close', 'reopen', 'bot_on', 'bot_off'], true)) {
            cdp_opsOut(['ok' => false, 'error' => 'Unknown change.']);
        }
        if ((int) $c->is_group === 1) {
            if (in_array($op, ['bot_on', 'bot_off'], true)) {
                cdp_opsOut(['ok' => false, 'error' => 'King answers one-to-one chats only.']);
            }
            if ($c->status === 'closed' && $op === 'assign_me') {
                cdp_opsOut(['ok' => false, 'error' => 'This chat is closed. Reopen it first.']);
            }
            $sql = ['assign_me' => 'assignee_id = :me', 'unassign' => 'assignee_id = NULL', 'close' => "status = 'closed', unread = 0", 'reopen' => "status = 'open'"][$op];
            $b = [':id' => (int) $c->id, ':n' => cdp_opsNow()];
            if ($op === 'assign_me') {
                $b[':me'] = $me;
            }
            $r = cdp_opsX("UPDATE cdb_wa_chats SET $sql, updated_at = :n WHERE id = :id", $b);
            cdp_opsOut(['ok' => $r['ok'], 'error' => $r['ok'] ? '' : 'Not saved.']);
        }
        require_once __DIR__ . '/../../helpers/comms.php';
        if ($op === 'bot_on' && cdp_commSetting('bot_enabled') !== '1') {
            cdp_opsOut(['ok' => false, 'error' => cdp_kingName() . ' does not answer WhatsApp yet: switch it on in Communications > Settings first.']);
        }
        $t = $threadOf($c, true);
        if (!$t) {
            cdp_opsOut(['ok' => false, 'error' => 'The conversation could not be opened in the WhatsApp Inbox.']);
        }
        if ($t->status === 'closed' && in_array($op, ['assign_me', 'bot_on', 'bot_off'], true)) {
            cdp_opsOut(['ok' => false, 'error' => 'This conversation is closed. Reopen it first.']);
        }
        // Same changes as the WhatsApp Inbox (ajax/comms/comms_ajax.php thread_update).
        $sql = [
            'close' => "status = 'closed', unread = 0, handoff_reason = NULL",
            'reopen' => "status = 'open', bot_active = 0, handoff_reason = NULL",
            'assign_me' => 'assignee_id = :me',
            'unassign' => 'assignee_id = NULL',
            'bot_on' => "bot_active = 1, status = 'bot', handoff_reason = NULL",
            'bot_off' => "bot_active = 0, status = IF(status = 'bot', 'open', status)",
        ][$op];
        $b = [':n' => cdp_opsNow(), ':id' => (int) $t->id];
        if ($op === 'assign_me') {
            $b[':me'] = $me;
        }
        $r = cdp_opsX("UPDATE cdb_comm_threads SET $sql, updated_at = :n WHERE id = :id", $b);
        cdp_opsX("UPDATE cdb_wa_chats SET updated_at = :n WHERE id = :id", [':n' => cdp_opsNow(), ':id' => (int) $c->id]);
        cdp_opsOut(['ok' => $r['ok'], 'error' => $r['ok'] ? '' : 'Not saved.']);

    case 'tags':
        $c = $chatOf($P('chat_id'));
        $tags = [];
        foreach (explode(',', $P('tags')) as $tg) {
            $tg = mb_substr(trim(preg_replace('/[^\p{L}\p{N} _\-]/u', '', $tg)), 0, 30);
            if ($tg !== '' && !in_array(mb_strtolower($tg), array_map('mb_strtolower', $tags), true)) {
                $tags[] = $tg;
            }
        }
        $tags = array_slice($tags, 0, 8);
        $r = cdp_opsX("UPDATE cdb_wa_chats SET tags = :t, updated_at = :n WHERE id = :id", [':t' => $tags ? implode(',', $tags) : null, ':n' => cdp_opsNow(), ':id' => (int) $c->id]);
        cdp_opsOut(['ok' => $r['ok'], 'tags' => $tags, 'error' => $r['ok'] ? '' : 'Not saved.']);

    case 'ticket':
        $c = $chatOf($P('chat_id'));
        require_once __DIR__ . '/../../helpers/support.php';
        if (!function_exists('cdp_csTicketCreate') || !cdp_opsTableExists('cdb_cs_tickets')) {
            cdp_opsOut(['ok' => false, 'error' => 'The Support desk is not set up on this installation.']);
        }
        $subject = mb_substr(trim($P('subject')), 0, 190);
        if ($subject === '') {
            cdp_opsOut(['ok' => false, 'error' => 'Give the ticket a subject.', 'errors' => ['subject' => 'Required']]);
        }
        $lines = [];
        foreach (array_reverse(cdp_opsQ("SELECT from_me, author_name, type, body, ts FROM cdb_wa_messages WHERE chat_id = :c ORDER BY ts DESC, id DESC LIMIT 15", [':c' => (string) $c->chat_id])) as $m) {
            $lines[] = '[' . date('Y-m-d H:i', (int) $m->ts) . '] ' . ($m->from_me ? 'Us' : ($m->author_name ?: 'Customer')) . ': ' . ($m->body !== '' && $m->body !== null ? $m->body : '[' . $m->type . ']');
        }
        $note = trim($P('note'));
        $desc = ($note !== '' ? $note . "\n\n" : '') . "From WhatsApp " . ((int) $c->is_group ? 'group ' . $c->name : cdp_umPlusPhone((string) $c->phone)) . ". Last messages:\n" . implode("\n", $lines);
        $pri = in_array($P('priority'), ['low', 'normal', 'high', 'urgent'], true) ? $P('priority') : 'normal';
        $id = cdp_csTicketCreate(['customer_id' => (int) $c->user_id ?: null, 'subject' => $subject, 'description' => mb_substr($desc, 0, 20000), 'channel' => 'whatsapp',
            'priority' => $pri, 'contact_name' => (string) $c->name, 'contact_phone' => (int) $c->is_group ? '' : cdp_umPlusPhone((string) $c->phone), 'assignee_id' => $me,
            'tags' => ['whatsapp']], 'staff', $me);
        if (!$id) {
            cdp_opsOut(['ok' => false, 'error' => 'The ticket could not be created.']);
        }
        require_once __DIR__ . '/../../helpers/sl_routes.php';
        $ref = (string) cdp_opsVal("SELECT public_ref FROM cdb_cs_tickets WHERE id = :id", [':id' => (int) $id], '');
        cdp_opsOut(['ok' => true, 'id' => (int) $id, 'ref' => $ref, 'url' => sl_route('CsTicket', ['id' => (int) $id]), 'message' => 'Ticket ' . $ref . ' opened.']);

    case 'suggest':
        $c = $chatOf($P('chat_id'));
        require_once __DIR__ . '/../../helpers/comms_ai.php';
        $conv = [];
        foreach (array_reverse(cdp_opsQ("SELECT from_me, author_name, type, body FROM cdb_wa_messages WHERE chat_id = :c ORDER BY ts DESC, id DESC LIMIT 12", [':c' => (string) $c->chat_id])) as $m) {
            $body = (string) $m->body !== '' ? (string) $m->body : '[' . $m->type . ']';
            $conv[] = ['dir' => (int) $m->from_me ? 'out' : 'in', 'body' => ((int) $c->is_group && !$m->from_me && $m->author_name ? $m->author_name . ': ' : '') . $body];
        }
        $facts = cdp_umFacts((int) $c->user_id);
        if ((int) $c->is_group) {
            $facts = 'This is the WhatsApp group "' . $c->name . '" (several customers read it): never reveal one customer\'s shipments, balance or details in the group.';
        }
        $tones = ['friendly' => 'Warm and friendly.', 'short' => 'As short as possible: one or two sentences.', 'formal' => 'Polite and formal.'];
        $instruction = trim(($tones[$P('tone')] ?? '') . ' ' . mb_substr($P('instruction'), 0, 300));
        $r = cdp_commAiDraft('whatsapp_reply', ['facts' => $facts, 'conversation' => $conv], 'whatsapp', $instruction);
        cdp_opsOut(['ok' => !empty($r['ok']), 'text' => (string) ($r['body'] ?? ''), 'warnings' => $r['warnings'] ?? [], 'error' => (string) ($r['error'] ?? ''),
            'code' => (string) ($r['code'] ?? ''), 'setup_url' => (string) ($r['setup_url'] ?? ''), 'assistant' => (string) ($r['assistant'] ?? cdp_kingName())]);

    case 'participants':
        $c = $chatOf($G('chat_id'));
        if ((int) $c->is_group !== 1) {
            cdp_opsOut(['ok' => false, 'error' => 'Not a group.']);
        }
        $p = cdp_umGroupParticipants((string) $c->chat_id, $G('fresh') === '1' && cdp_umStatus()['state'] === 'connected');
        if (empty($p['ok'])) {
            $row = cdp_opsOne("SELECT * FROM cdb_wa_groups WHERE chat_id = :c", [':c' => (string) $c->chat_id]);
            $p = ['ok' => (bool) $row, 'source' => $row ? (string) $row->source : '', 'note' => 'WhatsApp is not connected: this is the last saved list.',
                'participants' => $row ? cdp_opsDecode($row->participants, []) : []];
        }
        $people = cdp_umPeople(array_column($p['participants'], 'phone'));
        $saved = [];
        $phones = array_column($p['participants'], 'phone');
        if ($phones) {
            $in = [];
            $b = [];
            foreach (array_values($phones) as $i => $ph) {
                $in[] = ':p' . $i;
                $b[':p' . $i] = $ph;
            }
            foreach (cdp_opsQ("SELECT phone, opted_out FROM cdb_comm_contacts WHERE phone IN (" . implode(',', $in) . ")", $b) as $r) {
                $saved[$r->phone] = (int) $r->opted_out ? 'stop' : 'saved';
            }
        }
        $meP = cdp_umMe()['phone'] ?? '';
        $rows = [];
        foreach ($p['participants'] as $x) {
            $i = $people[$x['phone']] ?? ['name' => '', 'customer' => '', 'locker' => '', 'user_id' => 0];
            $rows[] = ['phone' => $x['phone'], 'admin' => (int) $x['admin'], 'name' => $i['customer'] ?: $i['name'], 'locker' => $i['locker'], 'user_id' => (int) $i['user_id'],
                'saved' => $saved[$x['phone']] ?? '', 'me' => $meP !== '' && $meP === $x['phone'] ? 1 : 0];
        }
        cdp_opsOut(['ok' => true, 'source' => $p['source'] ?? '', 'note' => $p['note'] ?? '', 'rows' => $rows, 'name' => (string) $c->name, 'can_export' => $isAdmin]);

    case 'sync_contacts':
        $r = cdp_umSyncContacts(true);
        cdp_opsOut(['ok' => $r['ok'], 'count' => $r['count'], 'error' => $r['error'], 'code' => $r['code']]);

    case 'contacts_info':
        $n = (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_wa_contacts_cache");
        $last = (string) cdp_opsVal("SELECT MAX(synced_at) FROM cdb_wa_contacts_cache", [], '');
        $saved = (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_comm_contacts");
        $stop = (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_comm_contacts WHERE opted_out = 1");
        $exports = [];
        foreach (cdp_opsQ("SELECT kind, target, format, row_count, saved_count, user_id, created_at FROM cdb_wa_exports ORDER BY id DESC LIMIT 8") as $e) {
            $exports[] = ['kind' => $e->kind, 'target' => (string) $e->target, 'format' => $e->format, 'rows' => (int) $e->row_count, 'saved' => (int) $e->saved_count,
                'by' => cdp_opsUserName((int) $e->user_id), 'at' => (string) $e->created_at];
        }
        require_once __DIR__ . '/../../helpers/sl_routes.php';
        cdp_opsOut(['ok' => true, 'count' => $n, 'synced_at' => $last, 'saved' => $saved, 'stopped' => $stop, 'exports' => $isAdmin ? $exports : [],
            'caps' => ['contacts' => cdp_umCap('contacts')], 'send_url' => sl_route('CommsSend') . '?mode=campaign']);

    case 'export':
        // Contacts of the phone, or the members of one group, as CSV or Excel. Audited.
        $what = $G('what') === 'group' ? 'group' : 'contacts';
        $fmt = $G('format') === 'xlsx' ? 'xlsx' : 'csv';
        $head = ['Name', 'Phone', 'WhatsApp ID', 'Customer', 'Locker', 'Saved in the phone', 'Business', 'Opted out (STOP)'];
        $rows = [];
        $target = 'Phone contacts';
        if ($what === 'group') {
            $c = $chatOf($G('chat_id'));
            if ((int) $c->is_group !== 1) {
                cdp_opsOut(['ok' => false, 'error' => 'Not a group.']);
            }
            $p = cdp_umGroupParticipants((string) $c->chat_id);
            $list = $p['participants'];
            $target = 'Group ' . ($c->name ?: $c->chat_id);
            $head = ['Name', 'Phone', 'WhatsApp ID', 'Group admin', 'Customer', 'Locker', 'Opted out (STOP)'];
        } else {
            if (cdp_umStatus()['state'] === 'connected') {
                cdp_umSyncContacts(false);
            }
            $list = [];
            foreach (cdp_opsQ("SELECT wa_id, phone, name, pushname, is_business, is_my_contact FROM cdb_wa_contacts_cache ORDER BY COALESCE(NULLIF(name,''), pushname, phone) LIMIT 20000") as $r) {
                $list[] = ['id' => $r->wa_id, 'phone' => (string) $r->phone, 'admin' => 0, 'n' => (string) ($r->name ?: $r->pushname), 'b' => (int) $r->is_business, 'm' => (int) $r->is_my_contact];
            }
        }
        $phones = array_column($list, 'phone');
        $people = cdp_umPeople($phones);
        $stopped = array_flip(cdp_umStoppedPhones($phones));
        foreach ($list as $x) {
            $i = $people[$x['phone']] ?? ['name' => '', 'customer' => '', 'locker' => ''];
            $name = (string) ($x['n'] ?? '') !== '' ? $x['n'] : $i['name'];
            if ($what === 'group') {
                $rows[] = [$name, cdp_umPlusPhone($x['phone']), $x['id'], $x['admin'] ? 'Yes' : '', $i['customer'], $i['locker'], isset($stopped[$x['phone']]) ? 'Yes' : ''];
            } else {
                $rows[] = [$name, cdp_umPlusPhone($x['phone']), $x['id'], $i['customer'], $i['locker'], $x['m'] ? 'Yes' : '', $x['b'] ? 'Yes' : '', isset($stopped[$x['phone']]) ? 'Yes' : ''];
            }
        }
        cdp_umAudit($what, $target, $fmt, count($rows));
        $base = 'whatsapp-' . ($what === 'group' ? 'group-' . preg_replace('/[^A-Za-z0-9]+/', '-', strtolower((string) $c->name ?: 'members')) : 'contacts') . '-' . date('Y-m-d');
        // Values go out as text cells; cdp_opsCsvCell keeps a spreadsheet from running one as a formula.
        cdp_opsSendExport($fmt, $base, 'WhatsApp ' . ($what === 'group' ? 'group members' : 'contacts'), [$what === 'group' ? 'Members' : 'Contacts' => ['head' => $head, 'rows' => $rows]], $target);

    case 'save_contacts':
        // Save as contacts campaigns can reach (the owner's choice), tagged with where they came from.
        $what = $P('what');
        $people = [];
        $target = '';
        if ($what === 'group') {
            $c = $chatOf($P('chat_id'));
            if ((int) $c->is_group !== 1) {
                cdp_opsOut(['ok' => false, 'error' => 'Not a group.']);
            }
            $p = cdp_umGroupParticipants((string) $c->chat_id);
            $meP = cdp_umMe()['phone'] ?? '';
            $info = cdp_umPeople(array_column($p['participants'], 'phone'));
            foreach ($p['participants'] as $x) {
                if ($x['phone'] !== $meP) {
                    $people[] = ['phone' => $x['phone'], 'name' => $info[$x['phone']]['name'] ?? ''];
                }
            }
            $gname = trim((string) ($c->name ?: $p['name'])) ?: (string) $c->chat_id;
            $source = 'whatsapp_group:' . mb_substr($gname, 0, 170);
            $target = 'Group ' . $gname;
        } elseif ($what === 'chat') {
            $c = $chatOf($P('chat_id'));
            if ((int) $c->is_group === 1) {
                cdp_opsOut(['ok' => false, 'error' => 'Use "Save members as contacts" for a group.']);
            }
            $people[] = ['phone' => (string) $c->phone, 'name' => (string) $c->name];
            $source = 'whatsapp_chat';
            $target = 'Chat ' . cdp_umPlusPhone((string) $c->phone);
        } else {
            if (cdp_umStatus()['state'] === 'connected') {
                cdp_umSyncContacts(false);
            }
            foreach (cdp_opsQ("SELECT phone, COALESCE(NULLIF(name,''), pushname) n FROM cdb_wa_contacts_cache WHERE phone IS NOT NULL LIMIT 20000") as $r) {
                $people[] = ['phone' => (string) $r->phone, 'name' => (string) $r->n];
            }
            $source = 'whatsapp_contacts';
            $target = 'Phone contacts';
        }
        if (!$people) {
            cdp_opsOut(['ok' => false, 'error' => 'There is nobody to save yet.']);
        }
        $r = cdp_umSaveContacts($people, $source, $me);
        cdp_umAudit($what === 'group' ? 'group' : ($what === 'chat' ? 'chat' : 'contacts'), $target, 'save', count($people), $r['saved'] + $r['updated']);
        $msg = ($r['saved'] ? $r['saved'] . ' saved' : 'No new contacts') . ($r['updated'] ? ', ' . $r['updated'] . ' already saved (updated)' : '')
            . ($r['opted_out'] ? ', ' . $r['opted_out'] . ' opted out (STOP: campaigns skip them)' : '') . ($r['skipped'] ? ', ' . $r['skipped'] . ' skipped (not a valid number)' : '') . '.';
        cdp_opsOut(['ok' => true, 'message' => $msg, 'source' => $source] + $r);

    case 'quick_replies':
        cdp_opsOut(['ok' => true, 'rows' => cdp_umQuickReplies(), 'can_edit' => $isAdmin]);

    case 'quick_replies_save':
        $rows = cdp_opsDecode($P('rows'), []);
        cdp_umSettingSet('quick_replies', array_slice(array_values(array_filter(array_map(function ($r) {
            return is_array($r) ? ['title' => mb_substr(trim((string) ($r['title'] ?? '')), 0, 60), 'body' => mb_substr(trim((string) ($r['body'] ?? '')), 0, 1500)] : null;
        }, $rows), function ($r) { return $r && $r['title'] !== '' && $r['body'] !== ''; })), 0, 40));
        cdp_opsOut(['ok' => true, 'rows' => cdp_umQuickReplies(), 'message' => 'Quick replies saved.']);
}

cdp_opsOut(['ok' => false, 'error' => 'Unknown action.'], 400);
