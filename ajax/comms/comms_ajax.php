<?php
// *************************************************************************
// * Communication Center — staff endpoint (helpers/comms.php).            *
// *                                                                       *
// * One dispatcher: ?action=... GET reads, POST writes (CSRF checked by   *
// * require_login). Staff (rank 2+) work messages, threads and history;   *
// * administrators (rank 3+) change settings, automations, templates and  *
// * approve campaigns. Drivers may only call driver_arriving.             *
// *************************************************************************

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/querys.php');
require_once(__DIR__ . '/../../helpers/comms.php');
require_login();

$action = (string) ($_REQUEST['action'] ?? '');
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$P = function ($k, $d = '') { return $_POST[$k] ?? $d; };
$G = function ($k, $d = '') { return $_GET[$k] ?? $d; };

if (!cdp_commEnsureTables()) {
    cdp_opsOut(['ok' => false, 'error' => 'The communication tables could not be created. The database user needs the CREATE privilege once.'], 500);
}

$rank = cdp_opsRank();
$driverActions = ['driver_arriving'];
if ($rank < 2 && !(in_array($action, $driverActions, true) && cdp_roleHasFlag((int) $user->userlevel, 'is_driver'))) {
    cdp_opsOut(['ok' => false, 'error' => 'Staff only.'], 403);
}
$adminOnly = ['save_settings', 'save_rules', 'save_status_map', 'save_template', 'approve_campaign', 'run_worker', 'delete_segment'];
if (in_array($action, $adminOnly, true) && $rank < 3) {
    cdp_opsOut(['ok' => false, 'error' => 'Administrators only.'], 403);
}
$writes = ['save_settings', 'save_rules', 'save_status_map', 'save_template', 'preview_template', 'manual_send', 'save_segment', 'preview_segment',
    'delete_segment', 'save_campaign', 'approve_campaign', 'cancel_campaign', 'retry', 'cancel', 'save_prefs', 'reply', 'thread_update',
    'slot_update', 'driver_arriving', 'run_worker'];
if (in_array($action, $writes, true) && !$isPost) {
    cdp_opsOut(['ok' => false, 'error' => 'Use POST.'], 405);
}

$sample = ['name' => 'Ama Mensah', 'first_name' => 'Ama', 'locker' => 'SW1024', 'tracking' => 'AWB10001', 'status' => 'In Transit', 'company' => '',
    'track_url' => cdp_commTrackUrl('AWB10001'), 'branch' => 'Accra Office', 'days' => 5, 'balance' => '120.00', 'currency' => cdp_commSetting('currency'),
    'schedule_url' => cdp_opsSiteUrl() . '/delivery_schedule.php?t=…', 'survey_url' => cdp_opsSiteUrl() . '/survey.php?t=…',
    'prefs_url' => cdp_opsSiteUrl() . '/comms_preferences.php?t=…', 'driver_name' => 'Kofi', 'driver_phone' => '+233 20 000 0000', 'eta' => 'Expected in about 20 minutes. ', 'date' => date('Y-m-d')];

switch ($action) {

    case 'overview':
        cdp_opsOut(['ok' => true, 'data' => cdp_commOverview(max(1, min(365, (int) $G('days', 30))))]);

    case 'settings':
        $s = cdp_commSettings();
        $public = $s;
        foreach (['wa_cloud_token', 'wa_cloud_app_secret'] as $k) {
            $public[$k] = '';
            $public[$k . '_set'] = $s[$k] !== '';
        }
        $statuses = [];
        $map = cdp_opsDecode($s['status_map'], []);
        foreach (cdp_commStatuses() as $st) {
            $statuses[] = ['id' => (int) $st->id, 'name' => ucwords(str_replace('_', ' ', (string) $st->name)), 'color' => $st->color,
                'auto' => cdp_commClassifyStatus((int) $st->id, (string) $st->name, []), 'override' => $map[(int) $st->id] ?? ''];
        }
        cdp_opsOut(['ok' => true, 'settings' => $rank >= 3 ? $public : ['enabled' => $s['enabled']], 'rules' => cdp_commRules(), 'events' => cdp_commEvents(),
            'channels' => cdp_commChannels(), 'statuses' => $statuses, 'is_admin' => $rank >= 3,
            'webhook_url' => cdp_opsSiteUrl() . '/webhooks/whatsapp.php?key=' . ($rank >= 3 ? $s['webhook_key'] : '…')]);

    case 'save_settings':
        cdp_commSaveSettings($_POST);
        cdp_opsOut(['ok' => true]);

    case 'save_rules':
        cdp_commSaveRules(cdp_opsDecode($P('rules'), []));
        cdp_opsOut(['ok' => true]);

    case 'save_status_map':
        cdp_commSaveStatusMap(cdp_opsDecode($P('map'), []));
        cdp_opsOut(['ok' => true]);

    case 'templates':
        cdp_opsOut(['ok' => true, 'templates' => cdp_commTemplates(), 'events' => cdp_commEvents(), 'placeholders' => cdp_commPlaceholders(), 'can_edit' => $rank >= 3]);

    case 'save_template':
        cdp_opsOut(cdp_commSaveTemplate($_POST));

    case 'preview_template':
        $sample['company'] = cdp_commBaseVars((object) [])['company'];
        cdp_opsOut(['ok' => true, 'subject' => cdp_commRender((string) $P('subject'), $sample), 'body' => cdp_commRender((string) $P('body'), $sample),
            'chars' => mb_strlen(cdp_commRender((string) $P('body'), $sample))]);

    case 'customers':
        $q = trim((string) $G('q'));
        if (mb_strlen($q) < 2) {
            cdp_opsOut(['ok' => true, 'rows' => []]);
        }
        $roles = implode(',', array_map('intval', cdp_commClientRoles()));
        $rows = cdp_opsQ("SELECT id, fname, lname, locker, phone, email FROM cdb_users WHERE userlevel IN ($roles)
                          AND (CONCAT(fname,' ',lname) LIKE :q OR locker LIKE :q OR phone LIKE :q OR email LIKE :q) ORDER BY fname LIMIT 20", [':q' => '%' . $q . '%']);
        cdp_opsOut(['ok' => true, 'rows' => array_map(function ($r) {
            return ['id' => (int) $r->id, 'name' => trim($r->fname . ' ' . $r->lname), 'locker' => $r->locker, 'phone' => $r->phone, 'email' => $r->email];
        }, $rows)]);

    case 'history':
        $uid = (int) $G('user_id');
        $u = cdp_commUser($uid);
        if (!$u) {
            cdp_opsOut(['ok' => false, 'error' => 'Customer not found.'], 404);
        }
        cdp_opsOut(['ok' => true, 'customer' => ['id' => (int) $u->id, 'name' => trim($u->fname . ' ' . $u->lname), 'locker' => $u->locker, 'phone' => $u->phone, 'email' => $u->email],
            'prefs' => cdp_commPrefs($uid), 'items' => cdp_commHistory($uid), 'events' => cdp_commEvents()]);

    case 'save_prefs':
        $uid = (int) $P('user_id');
        if (!cdp_commUser($uid)) {
            cdp_opsOut(['ok' => false, 'error' => 'Customer not found.'], 404);
        }
        // Staff may record consent the customer gave in person or on the phone; the source says so.
        $ok = cdp_commSavePrefs($uid, $_POST, 'staff:' . preg_replace('/[^a-z_]/', '', (string) $P('source', 'phone')));
        cdp_opsOut(['ok' => $ok]);

    case 'manual_send':
        $uid = (int) $P('user_id');
        $channels = array_values(array_intersect((array) ($_POST['channels'] ?? []), array_keys(cdp_commChannels())));
        $event = (string) $P('event', '');
        if (!$channels) {
            cdp_opsOut(['ok' => false, 'error' => 'Choose a channel.']);
        }
        $o = ['channels' => $channels, 'entity_ref' => mb_substr((string) $P('tracking'), 0, 80), 'entity_type' => 'manual', 'send_after' => time()];
        if ($event === '' || $event === 'custom') {
            $body = trim((string) $P('body'));
            if ($body === '') {
                cdp_opsOut(['ok' => false, 'error' => 'Write a message.']);
            }
            $o['body'] = $body;
            $o['subject'] = (string) $P('subject', 'Message from us');
            $event = 'shipment_alert';
        } elseif (!isset(cdp_commEvents()[$event])) {
            cdp_opsOut(['ok' => false, 'error' => 'Unknown message type.']);
        }
        $trk = (string) $P('tracking');
        $vars = ['tracking' => $trk, 'track_url' => $trk !== '' ? cdp_commTrackUrl($trk) : cdp_opsSiteUrl(), 'status' => (string) $P('status')];
        if (in_array($event, ['delivery_schedule', 'failed_delivery'], true)) {
            $vars['schedule_url'] = cdp_commCreateSlotLink($uid, $trk ?: 'manual', $event === 'failed_delivery' ? 'failed' : 'ready');
        }
        if ($event === 'survey') {
            $vars['survey_url'] = cdp_commCreateSurveyLink($uid, $trk, $channels[0]);
        }
        $q = cdp_commQueueEvent($event, $uid, $vars, $o);
        $res = $q['queued'] ? cdp_commDeliverDue(20) : [];
        cdp_opsOut(['ok' => $q['queued'] > 0, 'queued' => $q['queued'], 'skipped' => $q['skipped'], 'delivered' => $res,
            'error' => $q['queued'] ? '' : 'Nothing was sent: ' . implode('; ', array_map(function ($k, $v) { return $k . ' - ' . $v; }, array_keys($q['skipped']), $q['skipped']))]);

    case 'outbox':
        $w = '1=1';
        $b = [];
        foreach (['status', 'channel', 'event_key'] as $k) {
            if ($G($k) !== '') {
                $w .= " AND o.$k = :$k";
                $b[":$k"] = (string) $G($k);
            }
        }
        if ((int) $G('campaign_id') > 0) {
            $w .= ' AND o.campaign_id = :cid';
            $b[':cid'] = (int) $G('campaign_id');
        }
        if ($G('q') !== '') {
            $w .= ' AND (o.recipient_to LIKE :q OR o.entity_ref LIKE :q OR u.locker LIKE :q OR CONCAT(u.fname, \' \', u.lname) LIKE :q)';
            $b[':q'] = '%' . $G('q') . '%';
        }
        $page = max(1, (int) $G('page', 1));
        $total = (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_comm_outbox o LEFT JOIN cdb_users u ON u.id = o.user_id WHERE $w", $b);
        $rows = cdp_opsQ("SELECT o.id, o.user_id, o.recipient_to, o.event_key, o.channel, o.kind, o.subject, o.body, o.entity_ref, o.status, o.attempts, o.send_after,
                                 o.last_error, o.sent_at, o.created_at, o.campaign_id, TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) customer, u.locker
                          FROM cdb_comm_outbox o LEFT JOIN cdb_users u ON u.id = o.user_id WHERE $w ORDER BY o.id DESC LIMIT 50 OFFSET " . (($page - 1) * 50), $b);
        cdp_opsOut(['ok' => true, 'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / 50))]);

    case 'retry':
        $r = cdp_opsX("UPDATE cdb_comm_outbox SET status = 'queued', attempts = 0, send_after = :n, last_error = NULL WHERE id = :id AND status IN ('failed','cancelled')",
            [':n' => cdp_opsNow(), ':id' => (int) $P('id')]);
        cdp_opsOut(['ok' => $r['rows'] === 1]);

    case 'cancel':
        $r = cdp_opsX("UPDATE cdb_comm_outbox SET status = 'cancelled', last_error = 'Cancelled by staff' WHERE id = :id AND status = 'queued'", [':id' => (int) $P('id')]);
        cdp_opsOut(['ok' => $r['rows'] === 1]);

    case 'segments':
        cdp_opsOut(['ok' => true, 'rows' => cdp_opsQ("SELECT * FROM cdb_comm_segments ORDER BY name"), 'fields' => cdp_commSegmentFields()]);

    case 'preview_segment':
        $def = cdp_opsDecode($P('definition'), []);
        cdp_opsOut(['ok' => true, 'count' => cdp_commSegmentCount($def), 'sample' => array_map(function ($r) {
            return ['name' => trim($r->fname . ' ' . $r->lname), 'locker' => $r->locker, 'city' => $r->city, 'country' => $r->country];
        }, cdp_commSegmentSample($def))]);

    case 'save_segment':
        cdp_opsOut(cdp_commSaveSegment(['id' => $P('id'), 'name' => $P('name'), 'description' => $P('description'), 'definition' => cdp_opsDecode($P('definition'), [])]));

    case 'delete_segment':
        if (cdp_opsOne("SELECT id FROM cdb_comm_campaigns WHERE segment_id = :s AND status IN ('draft','scheduled','sending')", [':s' => (int) $P('id')])) {
            cdp_opsOut(['ok' => false, 'error' => 'A campaign still uses this segment.']);
        }
        cdp_opsOut(['ok' => cdp_opsX("DELETE FROM cdb_comm_segments WHERE id = :id", [':id' => (int) $P('id')])['ok']]);

    case 'campaigns':
        cdp_opsOut(['ok' => true, 'rows' => cdp_commCampaigns(), 'segments' => cdp_opsQ("SELECT id, name, last_count FROM cdb_comm_segments ORDER BY name"),
            'needs_approval' => cdp_commSetting('campaign_needs_approval') === '1', 'is_admin' => $rank >= 3, 'me' => cdp_opsUid()]);

    case 'save_campaign':
        cdp_opsOut(cdp_commSaveCampaign(['id' => $P('id'), 'name' => $P('name'), 'segment_id' => $P('segment_id'), 'channels' => (array) ($_POST['channels'] ?? []),
            'subject' => $P('subject'), 'body' => $P('body'), 'scheduled_at' => $P('scheduled_at')]));

    case 'approve_campaign':
        cdp_opsOut(cdp_commApproveCampaign((int) $P('id'), cdp_opsUid()));

    case 'cancel_campaign':
        $id = (int) $P('id');
        $r = cdp_opsX("UPDATE cdb_comm_campaigns SET status = 'cancelled', updated_at = :n WHERE id = :id AND status IN ('draft','scheduled')", [':n' => cdp_opsNow(), ':id' => $id]);
        $q = cdp_opsX("UPDATE cdb_comm_outbox SET status = 'cancelled', last_error = 'Campaign cancelled' WHERE campaign_id = :id AND status = 'queued'", [':id' => $id]);
        cdp_opsOut(['ok' => $r['rows'] === 1 || $q['rows'] > 0, 'unsent_cancelled' => $q['rows']]);

    case 'threads':
        cdp_opsOut(['ok' => true, 'rows' => cdp_commThreads((string) $G('status'), trim((string) $G('q'))),
            'counts' => ['bot' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_comm_threads WHERE status='bot'"), 'open' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_comm_threads WHERE status='open'"),
                'mine' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_comm_threads WHERE status<>'closed' AND assignee_id = :u", [':u' => cdp_opsUid()])],
            'bot_enabled' => cdp_commSetting('bot_enabled') === '1']);

    case 'thread':
        $t = cdp_opsOne("SELECT t.*, u.locker FROM cdb_comm_threads t LEFT JOIN cdb_users u ON u.id = t.user_id WHERE t.id = :id", [':id' => (int) $G('id')]);
        if (!$t) {
            cdp_opsOut(['ok' => false, 'error' => 'Conversation not found.'], 404);
        }
        cdp_opsX("UPDATE cdb_comm_threads SET unread = 0 WHERE id = :id", [':id' => (int) $t->id]);
        $msgs = cdp_opsQ("SELECT m.id, m.direction, m.author_type, m.author_id, m.body, m.status, m.created_at FROM cdb_comm_messages m WHERE m.thread_id = :t ORDER BY m.id DESC LIMIT 200", [':t' => (int) $t->id]);
        foreach ($msgs as $m) {
            $m->author_name = $m->author_type === 'agent' ? cdp_opsUserName($m->author_id) : '';
        }
        cdp_opsOut(['ok' => true, 'thread' => $t, 'messages' => array_reverse($msgs), 'window_open' => cdp_commWaWindowOpen($t->phone),
            'provider' => cdp_commSetting('wa_provider'), 'assignee' => cdp_opsUserName($t->assignee_id)]);

    case 'reply':
        $t = cdp_opsOne("SELECT * FROM cdb_comm_threads WHERE id = :id", [':id' => (int) $P('id')]);
        $text = trim((string) $P('body'));
        if (!$t || $text === '') {
            cdp_opsOut(['ok' => false, 'error' => 'Nothing to send.']);
        }
        if (cdp_commSetting('wa_provider') === 'cloud' && !cdp_commWaWindowOpen($t->phone)) {
            cdp_opsOut(['ok' => false, 'error' => 'The 24-hour WhatsApp window is closed: the customer has not written in the last 24 hours. Send an approved template from the customer history instead.']);
        }
        // A person replying takes the thread: the bot stops answering it.
        cdp_opsX("UPDATE cdb_comm_threads SET status = 'open', bot_active = 0, assignee_id = COALESCE(assignee_id, :u) WHERE id = :id", [':u' => cdp_opsUid(), ':id' => (int) $t->id]);
        $r = cdp_commReply($t, mb_substr($text, 0, 4000), 'agent', cdp_opsUid());
        cdp_opsOut(['ok' => !empty($r['success']), 'error' => !empty($r['success']) ? '' : ('Not sent: ' . ($r['message'] ?? ''))]);

    case 'thread_update':
        $id = (int) $P('id');
        $op = (string) $P('op');
        $sql = [
            'close' => "status = 'closed', unread = 0",
            'reopen' => "status = 'open'",
            'assign_me' => 'assignee_id = ' . cdp_opsUid() . ", status = IF(status = 'closed', 'open', status)",
            'unassign' => 'assignee_id = NULL',
            'bot_on' => "bot_active = 1, status = 'bot'",
            'bot_off' => "bot_active = 0, status = IF(status = 'bot', 'open', status)",
        ];
        if (!isset($sql[$op])) {
            cdp_opsOut(['ok' => false, 'error' => 'Unknown change.']);
        }
        $r = cdp_opsX("UPDATE cdb_comm_threads SET " . $sql[$op] . ", updated_at = :n WHERE id = :id", [':n' => cdp_opsNow(), ':id' => $id]);
        cdp_opsOut(['ok' => $r['ok']]);

    case 'surveys':
        $from = cdp_opsDate($G('from'), date('Y-m-d', strtotime('-29 days')));
        $to = cdp_opsDate($G('to'), date('Y-m-d'));
        $rows = cdp_opsQ("SELECT s.*, TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) customer, u.locker FROM cdb_comm_surveys s
                          LEFT JOIN cdb_users u ON u.id = s.user_id WHERE s.sent_at BETWEEN :f AND :t ORDER BY s.id DESC LIMIT 300", [':f' => $from . ' 00:00:00', ':t' => $to . ' 23:59:59']);
        cdp_opsOut(['ok' => true, 'rows' => $rows, 'stats' => cdp_commSurveyStats($from, $to)]);

    case 'slots':
        $st = (string) $G('status', 'booked');
        $w = in_array($st, ['pending', 'booked', 'done', 'cancelled'], true) ? 's.status = :s' : '1=1';
        $rows = cdp_opsQ("SELECT s.*, TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) customer, u.locker, u.phone customer_phone FROM cdb_comm_delivery_slots s
                          LEFT JOIN cdb_users u ON u.id = s.user_id WHERE $w ORDER BY s.slot_date IS NULL, s.slot_date, s.id DESC LIMIT 300", $w === '1=1' ? [] : [':s' => $st]);
        foreach ($rows as $r) {
            $r->driver_name = cdp_opsUserName($r->driver_id);
        }
        cdp_opsOut(['ok' => true, 'rows' => $rows, 'windows' => cdp_commSlotWindows()]);

    case 'slot_update':
        $id = (int) $P('id');
        $op = (string) $P('op');
        if ($op === 'assign') {
            $r = cdp_opsX("UPDATE cdb_comm_delivery_slots SET driver_id = :d, updated_at = :n WHERE id = :id", [':d' => (int) $P('driver_id') ?: null, ':n' => cdp_opsNow(), ':id' => $id]);
        } elseif (in_array($op, ['done', 'cancelled'], true)) {
            $r = cdp_opsX("UPDATE cdb_comm_delivery_slots SET status = :s, updated_at = :n WHERE id = :id", [':s' => $op, ':n' => cdp_opsNow(), ':id' => $id]);
        } else {
            cdp_opsOut(['ok' => false, 'error' => 'Unknown change.']);
        }
        cdp_opsOut(['ok' => $r['ok']]);

    case 'driver_arriving':
        $trk = trim((string) $P('tracking'));
        $info = cdp_commResolveTrack($trk);
        if (!$info['owners']) {
            cdp_opsOut(['ok' => false, 'error' => 'No customer found for that tracking number.']);
        }
        $channels = array_keys(array_filter(cdp_commRules()['driver_arrival'] ?? [])) ?: ['whatsapp', 'sms'];
        $eta = max(0, min(600, (int) $P('eta')));
        $n = 0;
        foreach ($info['owners'] as $owner) {
            $vars = array_merge(['tracking' => $trk, 'status' => 'On the way', 'track_url' => cdp_commTrackUrl($trk)], cdp_commDriverVars(cdp_opsUid(), $eta ?: null));
            $n += cdp_commQueueEvent('driver_arrival', $owner, $vars, ['channels' => $channels, 'entity_type' => $info['type'], 'entity_ref' => $trk,
                'dedupe' => 'arriving:' . $trk . ':' . $owner . ':' . date('YmdH'), 'send_after' => time()])['queued'];
        }
        $res = $n ? cdp_commDeliverDue(20) : [];
        cdp_opsOut(['ok' => $n > 0, 'queued' => $n, 'delivered' => $res, 'error' => $n ? '' : 'Already sent this hour, or the customer has switched these messages off.']);

    case 'run_worker':
        cdp_opsOut(['ok' => true, 'result' => cdp_commRunWorker()]);

    default:
        cdp_opsOut(['ok' => false, 'error' => 'Unknown action.'], 400);
}
