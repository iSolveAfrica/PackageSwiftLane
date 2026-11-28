<?php
// *************************************************************************
// * Send page (comms_send.php): one person, several people or a campaign. *
// *                                                                       *
// * POST action=... (CSRF checked by require_login). Staff (rank 2+) send *
// * direct messages; campaigns need push_notifications or               *
// * manage_newsletter; approving and cancelling a campaign need an       *
// * administrator (rank 3+). Everything is queued in the Communication   *
// * Center outbox (helpers/comms.php, helpers/comms_campaigns.php).      *
// *************************************************************************

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_once(__DIR__ . '/../../helpers/querys.php');
require_once(__DIR__ . '/../../helpers/comms.php');
require_once(__DIR__ . '/../../helpers/comms_campaigns.php');

$action = (string) ($_REQUEST['action'] ?? '');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    cdp_opsOut(['success' => false, 'message' => 'Use POST.'], 405);
}
if (!cdp_commEnsureTables()) {
    cdp_opsOut(['success' => false, 'message' => 'The communication tables could not be created. The database user needs the CREATE privilege once.'], 500);
}
$rank = cdp_opsRank();
if ($rank < 2) {
    cdp_opsOut(['success' => false, 'message' => 'Staff only.'], 403);
}
$user->cdp_getUserPermissions();
$canCampaign = $user->cdp_hasPermission(['push_notifications', 'manage_newsletter']);
$uid = cdp_opsUid();
$P = function ($k, $d = '') { return $_POST[$k] ?? $d; };
$J = function ($k) { $v = $_POST[$k] ?? ''; return is_array($v) ? $v : cdp_opsDecode((string) $v, []); };
$out = function (array $b, $code = 200) { cdp_opsOut($b + ['success' => !empty($b['ok'])], $code); };
$channelsIn = function () use ($P) {
    $c = $P('channels', '');
    $c = is_array($c) ? $c : explode(',', (string) $c);
    return array_values(array_intersect(array_keys(cdp_cmpChannels()), array_map('trim', $c)));
};
$idsIn = function () use ($P) {
    $v = $P('ids', '');
    return array_map('intval', is_array($v) ? $v : explode(',', (string) $v));
};
$log = function ($verb, $summary, $id = 0, $label = '') {
    if (function_exists('cdp_activityLog')) {
        cdp_activityLog(['module' => 'communications', 'verb' => $verb, 'action' => 'comms_send.' . ($_POST['action'] ?? ''), 'outcome' => 'ok',
            'entity_type' => $id ? 'campaign' : 'message', 'entity_id' => $id ?: null, 'entity_label' => $label, 'summary' => mb_substr($summary, 0, 250)]);
    }
};
$campaignActions = ['campaigns', 'campaign_get', 'campaign_save', 'campaign_submit', 'campaign_approve', 'campaign_unschedule', 'campaign_cancel',
    'campaign_duplicate', 'campaign_delete', 'contacts_sources'];
if (in_array($action, $campaignActions, true) && !$canCampaign) {
    $out(['ok' => false, 'message' => 'Campaigns need the Push notifications or Newsletter permission.'], 403);
}
if (in_array($action, ['campaign_approve', 'campaign_cancel'], true) && $rank < 3) {
    $out(['ok' => false, 'message' => 'Only an administrator can do that.'], 403);
}
// Agency-restricted staff only reach their agency's campaigns.
if (in_array($action, ['campaign_get', 'campaign_approve', 'campaign_unschedule', 'campaign_cancel', 'campaign_duplicate', 'campaign_delete'], true)
    || (in_array($action, ['campaign_save', 'campaign_submit'], true) && (int) ($_POST['id'] ?? 0) > 0)) {
    if (!cdp_cmpInScope((int) ($_POST['id'] ?? 0))) {
        $out(['ok' => false, 'message' => 'Campaign not found.'], 404);
    }
}

/** The message posted by the page: channels plus per-channel texts (and the Cloud template). */
$message = function () use ($J, $P, $channelsIn) {
    $bodies = cdp_cmpBodies($J('bodies'));
    $wa = preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string) $P('wa_template'))));
    if ($wa !== '') {
        $bodies['_wa_template'] = $wa;
        $bodies['_wa_lang'] = preg_replace('/[^A-Za-z_]/', '', (string) $P('wa_lang', 'en')) ?: 'en';
        $bodies['_wa_params'] = mb_substr(preg_replace('/[^a-z0-9_,]/', '', strtolower((string) $P('wa_params'))), 0, 255);
    }
    return [$bodies, $channelsIn()];
};

switch ($action) {

    case 'search':
        $q = trim((string) $P('q'));
        if (mb_strlen($q) < 2) {
            $out(['ok' => true, 'rows' => []]);
        }
        $roles = implode(',', array_map('intval', cdp_commClientRoles()));
        $ag = cdp_cmpAgencyScope();
        // The phone condition only when the query has digits enough to mean a number:
        // '%%' would match every customer with a phone.
        $digits = preg_replace('/\D+/', '', $q);
        if (strlen($digits) >= 4 && $digits[0] === '0') {
            $digits = substr($digits, 1);   // a local number (0541...) is stored with the country code
        }
        $bind = [':q' => '%' . $q . '%', ':q2' => '%' . $q . '%', ':q4' => '%' . $q . '%'];
        $phoneOr = '';
        if (strlen($digits) >= 3) {
            $phoneOr = " OR REPLACE(REPLACE(REPLACE(u.phone,'+',''),' ',''),'-','') LIKE :q3";
            $bind[':q3'] = '%' . $digits . '%';
        }
        $rows = cdp_opsQ("SELECT u.id, u.fname, u.lname, u.locker, u.phone, u.email, u.city FROM cdb_users u
                          WHERE u.userlevel IN ($roles) AND u.active = 1" . ($ag ? ' AND u.agency_id = ' . $ag : '') . "
                          AND (CONCAT(u.fname,' ',u.lname) LIKE :q OR u.locker LIKE :q2 OR u.email LIKE :q4$phoneOr)
                          ORDER BY (CONCAT(u.fname,' ',u.lname) LIKE :q5) DESC, u.fname, u.lname LIMIT 12", $bind + [':q5' => $q . '%']);
        $out(['ok' => true, 'rows' => array_map(function ($r) {
            return ['id' => (int) $r->id, 'name' => trim($r->fname . ' ' . $r->lname) ?: 'Customer', 'locker' => (string) $r->locker, 'phone' => (string) $r->phone,
                'email' => (string) $r->email, 'city' => (string) $r->city];
        }, $rows)]);

    case 'reach':
        $mode = (string) $P('mode', 'one');
        $channels = $channelsIn();
        if ($mode === 'campaign') {
            if (!$canCampaign) {
                $out(['ok' => false, 'message' => 'Campaigns need the Push notifications or Newsletter permission.'], 403);
            }
            $a = cdp_cmpAudience($J('audience'));
            if ($ag = cdp_cmpAgencyScope()) {
                $a['agency_id'] = $ag;   // the server's scope wins over anything posted
            }
            $reach = cdp_cmpReach($a, $channels);
            $out(['ok' => true, 'kind' => 'marketing', 'reach' => $reach, 'sample' => cdp_cmpPeople($a, $channels, 0, 5), 'label' => cdp_cmpAudienceLabel($a)]);
        }
        $r = cdp_cmpDirectRecipients($idsIn(), (string) $P('list'));
        $reach = cdp_cmpReachDirect($r, $channels);
        $sample = $r['ids'] ? cdp_cmpPeopleByIds(array_slice($r['ids'], 0, 5), $channels) : [];
        foreach (array_slice($r['raw'], 0, max(0, 5 - count($sample))) as $x) {
            $sample[] = ['id' => 0, 'kind' => 'address', 'name' => $x['to'], 'locker' => '', 'phone' => $x['type'] === 'phone' ? $x['to'] : '', 'email' => $x['type'] === 'email' ? $x['to'] : '',
                'state' => array_combine($channels, array_map(function ($ch) use ($x) { return $ch !== 'inapp' && (($ch === 'email') === ($x['type'] === 'email')) ? 'ok' : 'no_address'; }, $channels)) ?: []];
        }
        $out(['ok' => true, 'kind' => 'transactional', 'reach' => $reach, 'sample' => $sample, 'too_many' => $reach['total'] > CDP_CMP_DIRECT_MAX]);

    case 'people':
        $channels = $channelsIn();
        $off = max(0, (int) $P('offset', 0));
        $q = mb_substr(trim((string) $P('q')), 0, 60);
        if ((string) $P('mode') === 'campaign') {
            if (!$canCampaign) {
                $out(['ok' => false, 'message' => 'Campaigns need the Push notifications or Newsletter permission.'], 403);
            }
            $a = cdp_cmpAudience($J('audience'));
            if ($ag = cdp_cmpAgencyScope()) {
                $a['agency_id'] = $ag;
            }
            $rows = cdp_cmpPeople($a, $channels, $off, 51, $q);
        } else {
            // Chosen customers, then the typed addresses; "Find someone" filters both.
            $r = cdp_cmpDirectRecipients($idsIn(), (string) $P('list'));
            $all = cdp_cmpPeopleByIds(array_slice($r['ids'], 0, CDP_CMP_DIRECT_MAX + 50), $channels);
            foreach ($r['raw'] as $x) {
                $all[] = ['id' => 0, 'kind' => 'address', 'name' => $x['to'], 'locker' => '', 'phone' => $x['type'] === 'phone' ? $x['to'] : '', 'email' => $x['type'] === 'email' ? $x['to'] : '',
                    'city' => '', 'state' => array_combine($channels, array_map(function ($ch) use ($x) { return $ch !== 'inapp' && (($ch === 'email') === ($x['type'] === 'email')) ? 'ok' : 'no_address'; }, $channels)) ?: []];
            }
            if ($q !== '') {
                $ql = mb_strtolower($q);
                $qd = preg_replace('/\D+/', '', $q);
                $all = array_values(array_filter($all, function ($p) use ($ql, $qd) {
                    foreach (['name', 'locker', 'email'] as $k) {
                        if ($p[$k] !== '' && mb_strpos(mb_strtolower($p[$k]), $ql) !== false) {
                            return true;
                        }
                    }
                    return strlen($qd) >= 3 && strpos(preg_replace('/\D+/', '', $p['phone']), $qd) !== false;
                }));
            }
            $rows = array_slice($all, $off, 51);
        }
        $out(['ok' => true, 'rows' => array_slice($rows, 0, 50), 'more' => count($rows) > 50, 'offset' => $off]);

    case 'send':
        list($bodies, $channels) = $message();
        $err = cdp_cmpValidateMessage($bodies, $channels, false);
        if ($err) {
            $out(['ok' => false, 'message' => implode(' ', $err), 'errors' => $err], 422);
        }
        $at = null;
        if ((string) $P('when') === 'later') {
            $at = strtotime(str_replace('T', ' ', (string) $P('at')));
            if (!$at || $at < time() - 60) {
                $out(['ok' => false, 'message' => 'Choose a date and time in the future.'], 422);
            }
        }
        $r = cdp_cmpDirectRecipients($idsIn(), (string) $P('list'));
        $res = cdp_cmpSendDirect($r, $bodies, $channels, $at);
        if (!$res['ok']) {
            $out(['ok' => false, 'message' => $res['error']], 422);
        }
        if (!$at && $res['ids']) {
            require_once(__DIR__ . '/../../helpers/after_response.php');
            $ids = $res['ids'];
            cdp_afterResponse(function () use ($ids) {
                foreach (cdp_opsQ("SELECT * FROM cdb_comm_outbox WHERE status = 'queued' AND id IN (" . implode(',', array_map('intval', array_slice($ids, 0, 400))) . ")") as $row) {
                    cdp_commDeliver($row);
                }
            });
        }
        $skipped = array_sum($res['skipped']);
        if (!$res['queued'] && !$res['inapp']) {
            $why = [];
            foreach ($res['skipped'] as $k => $n) {
                $why[] = $n . ' · ' . $k;
            }
            $out(['ok' => false, 'message' => 'Nothing was sent: ' . ($why ? implode('; ', $why) : 'nobody could receive it') . '.', 'skipped' => $res['skipped']], 422);
        }
        $log('send', 'Message to ' . $res['recipients'] . ' ' . ($res['recipients'] === 1 ? 'person' : 'people') . ' by ' . implode(', ', $channels) . ': ' . $res['queued'] . ' queued, ' . $skipped . ' skipped');
        $msg = ($at ? 'Scheduled ' : 'Sending ') . ($res['queued'] + $res['inapp']) . ' message' . (($res['queued'] + $res['inapp']) === 1 ? '' : 's') . ($skipped ? ' · ' . $skipped . ' skipped' : '');
        $out(['ok' => true, 'message' => $msg, 'queued' => $res['queued'], 'inapp' => $res['inapp'], 'skipped' => $res['skipped'], 'not_configured' => $res['not_configured'] ?? [],
            'scheduled' => $at ? date('Y-m-d H:i', $at) : '',
            'outbox_ids' => array_slice($res['ids'], 0, 400)]);

    case 'status':
        // Delivery state of the messages just sent (the page polls a few times).
        $ids = array_slice(array_filter(array_map('intval', explode(',', (string) $P('outbox_ids')))), 0, 400);
        if (!$ids) {
            $out(['ok' => true, 'counts' => []]);
        }
        // Only the caller's own messages (administrators: any).
        $own = $rank >= 3 ? '' : ' AND created_by = ' . (int) $uid;
        $c = [];
        foreach (cdp_opsQ("SELECT status, COUNT(*) n FROM cdb_comm_outbox WHERE id IN (" . implode(',', $ids) . ")$own GROUP BY status") as $r) {
            $c[(string) $r->status] = (int) $r->n;
        }
        $errs = [];
        foreach (cdp_opsQ("SELECT channel, MAX(last_error) last_error FROM cdb_comm_outbox WHERE id IN (" . implode(',', $ids) . ")$own AND last_error IS NOT NULL AND last_error <> '' GROUP BY channel LIMIT 3") as $r) {
            $errs[] = (cdp_cmpChannels()[$r->channel] ?? $r->channel) . ': ' . cdp_cmpErrorText($r->last_error);
        }
        $out(['ok' => true, 'counts' => $c, 'errors' => $errs]);

    case 'test':
        list($bodies, $channels) = $message();
        $ch = (string) $P('channel');
        if (!isset(cdp_cmpChannels()[$ch])) {
            $out(['ok' => false, 'message' => 'Choose a channel.'], 422);
        }
        $err = cdp_cmpValidateMessage($bodies, [$ch], false);
        if ($err) {
            $out(['ok' => false, 'message' => implode(' ', $err)], 422);
        }
        $me = cdp_commUser($uid);
        if ($ch === 'inapp') {
            $ok = cdp_cmpInApp($uid, (string) ($bodies['inapp']['body'] ?? ''));
            $out(['ok' => $ok, 'message' => $ok ? 'Test posted to your own notification bell.' : 'Not posted.']);
        }
        if (($why = cdp_cmpChannelProblem($ch)) !== '') {
            $out(['ok' => false, 'message' => 'Test not sent: ' . $why . '.'], 422);
        }
        $to = $ch === 'email' ? trim((string) ($me->email ?? '')) : trim((string) ($me->phone ?? ''));
        if ($to === '') {
            $out(['ok' => false, 'message' => 'Your account has no ' . ($ch === 'email' ? 'e-mail address' : 'phone number') . ' to send a test to.'], 422);
        }
        $o = cdp_cmpQueueOptions($bodies, [$ch]) + ['event' => 'manual', 'kind' => 'transactional', 'entity_type' => 'test', 'send_after' => time(),
            'name' => trim(($me->fname ?? '') . ' ' . ($me->lname ?? ''))];
        unset($o['wa_template']);
        if (isset($o['subjects']['email'])) {
            $o['subjects']['email'] = '[Test] ' . $o['subjects']['email'];
        }
        $q = cdp_commQueueRaw($to, $ch, [], $o);
        if (!$q['ids']) {
            $out(['ok' => false, 'message' => 'Not sent: ' . (reset($q['skipped']) ?: 'unknown reason') . '.']);
        }
        $row = cdp_opsOne("SELECT * FROM cdb_comm_outbox WHERE id = :id", [':id' => (int) $q['ids'][0]]);
        $state = $row ? cdp_commDeliver($row) : 'failed';
        $after = cdp_opsOne("SELECT status, last_error FROM cdb_comm_outbox WHERE id = :id", [':id' => (int) $q['ids'][0]]);
        if ($state === 'sent') {
            $out(['ok' => true, 'message' => 'Test sent to ' . $to . '.']);
        }
        if ($after && $after->status === 'queued') {
            // A test is not retried: it is recorded as failed, so the Outbox shows it.
            cdp_opsX("UPDATE cdb_comm_outbox SET status = 'failed' WHERE id = :id", [':id' => (int) $q['ids'][0]]);
        }
        $out(['ok' => false, 'message' => 'Test not sent to ' . $to . ': ' . rtrim($after && $after->last_error ? cdp_cmpErrorText($after->last_error) : 'the provider did not accept it', '. ') . '.']);

    // ---- campaigns ------------------------------------------------------

    case 'campaigns':
        $out(['ok' => true, 'rows' => cdp_cmpList((string) $P('status'))]);

    case 'contacts_sources':
        $out(['ok' => true, 'ready' => (bool) cdp_cmpContactsInfo(), 'sources' => cdp_cmpContactSources()]);

    case 'campaign_get':
        $c = cdp_cmpGet((int) $P('id'));
        if (!$c) {
            $out(['ok' => false, 'message' => 'Campaign not found.'], 404);
        }
        $b = $c['bodies'];
        $out(['ok' => true, 'campaign' => ['id' => (int) $c['row']->id, 'name' => (string) $c['row']->name, 'status' => (string) $c['row']->status,
            'scheduled_at' => $c['row']->scheduled_at ? substr((string) $c['row']->scheduled_at, 0, 16) : '', 'channels' => $c['channels'], 'audience' => $c['audience'],
            'bodies' => $b, 'goal' => (string) ($b['_meta']['goal'] ?? ''), 'wa_template' => (string) ($b['_wa_template'] ?? ''), 'wa_lang' => (string) ($b['_wa_lang'] ?? 'en'),
            'wa_params' => (string) ($b['_wa_params'] ?? ''), 'editable' => in_array($c['row']->status, ['draft', 'pending', 'scheduled'], true) && (int) $c['row']->launch_cursor === 0]]);

    case 'campaign_save':
    case 'campaign_submit':
        $d = ['id' => (int) $P('id'), 'name' => (string) $P('name'), 'audience' => $J('audience'), 'bodies' => $J('bodies'), 'channels' => $channelsIn(),
            'scheduled_at' => (string) $P('when') === 'later' ? (string) $P('at') : '', 'wa_template' => (string) $P('wa_template'), 'wa_lang' => (string) $P('wa_lang', 'en'),
            'wa_params' => (string) $P('wa_params')];
        if ((string) $P('when') === 'later') {
            $t = strtotime(str_replace('T', ' ', (string) $P('at')));
            if (!$t || $t < time() - 60) {
                $out(['ok' => false, 'message' => 'Choose a date and time in the future.'], 422);
            }
        }
        if ($d['id'] > 0) {
            $cur = cdp_opsOne("SELECT created_by FROM cdb_comm_campaigns WHERE id = :id", [':id' => $d['id']]);
            if ($cur && (int) $cur->created_by !== $uid && $rank < 3) {
                $out(['ok' => false, 'message' => 'Only the person who wrote it, or an administrator, can change this campaign.'], 403);
            }
        }
        if ($action === 'campaign_submit') {
            // The same campaign sent twice by the same person within a few minutes (a double click,
            // a second press after it started) is refused: the dedupe keys are per campaign.
            $na = cdp_cmpAudience($d['audience']);
            unset($na['agency_id']);
            $sig = cdp_opsJson([$na, cdp_cmpBodies($d['bodies']), $d['channels']]);
            foreach (cdp_opsQ("SELECT id, audience, bodies, channels FROM cdb_comm_campaigns WHERE created_by = :u AND id <> :id AND name = :n
                               AND status IN ('pending','scheduled','sending','launching','sent') AND updated_at >= :t", [':u' => $uid, ':id' => $d['id'], ':n' => mb_substr(trim($d['name']), 0, 160), ':t' => cdp_opsNow(-600)]) as $prev) {
                $pa = cdp_opsDecode($prev->audience, []);
                unset($pa['agency_id']);
                $pb = cdp_opsDecode($prev->bodies, []);
                unset($pb['_wa_template'], $pb['_wa_lang'], $pb['_wa_params']);
                $pa = cdp_cmpAudience($pa);
                unset($pa['agency_id']);
                if (cdp_opsJson([$pa, cdp_cmpBodies($pb), array_values(array_filter(explode(',', (string) $prev->channels)))]) === $sig) {
                    $out(['ok' => false, 'message' => 'You already sent this campaign a few minutes ago (#' . (int) $prev->id . '). Duplicate it from the list if you really want to send it again.'], 409);
                }
            }
        }
        $s = cdp_cmpSave($d, $uid);
        if (!$s['ok']) {
            $out(['ok' => false, 'message' => $s['error']], 422);
        }
        if ($action === 'campaign_save') {
            $log('update', 'Campaign draft saved: ' . $d['name'], $s['id'], $d['name']);
            $out(['ok' => true, 'id' => $s['id'], 'message' => 'Draft saved.']);
        }
        $r = cdp_cmpSubmit($s['id'], $uid, $rank);
        if (!$r['ok']) {
            $out(['ok' => false, 'id' => $s['id'], 'message' => $r['error'] . ' The draft is saved.', 'errors' => $r['errors'] ?? []], 422);
        }
        if ($r['status'] === 'scheduled' && $d['scheduled_at'] === '') {
            // Start now: the first batch is queued after this answer, the rest by the worker.
            require_once(__DIR__ . '/../../helpers/after_response.php');
            $cid = $s['id'];
            cdp_afterResponse(function () use ($cid) {
                cdp_cmpRunBatch($cid);
                cdp_commDeliverDue(40);
            });
        }
        $log('send', 'Campaign ' . ($r['status'] === 'pending' ? 'sent for approval' : 'scheduled') . ': ' . $d['name'] . ' (' . $r['reach']['total'] . ' in the audience)', $s['id'], $d['name']);
        $out(['ok' => true, 'id' => $s['id'], 'status' => $r['status'],
            'message' => $r['status'] === 'pending' ? 'Sent for approval. An administrator approves it in the campaign list.'
                : ($d['scheduled_at'] !== '' ? 'Scheduled for ' . str_replace('T', ' ', $d['scheduled_at']) . '.' : 'Sending started. Messages go out in batches; follow it in the campaign list.')]);

    case 'campaign_approve':
        $id = (int) $P('id');
        $r = cdp_cmpApprove($id, $uid, $rank);
        if ($r['ok']) {
            $row = cdp_opsOne("SELECT name, scheduled_at FROM cdb_comm_campaigns WHERE id = :id", [':id' => $id]);
            if ($row && (!$row->scheduled_at || strtotime($row->scheduled_at) <= time())) {
                require_once(__DIR__ . '/../../helpers/after_response.php');
                cdp_afterResponse(function () use ($id) { cdp_cmpRunBatch($id); cdp_commDeliverDue(40); });
            }
            $log('approve', 'Campaign approved: ' . ($row->name ?? '#' . $id), $id, (string) ($row->name ?? ''));
        }
        $out($r + ['message' => $r['ok'] ? 'Approved. It goes out ' . (($row->scheduled_at ?? null) && strtotime($row->scheduled_at) > time() ? 'at ' . substr($row->scheduled_at, 0, 16) : 'now') . '.' : $r['error']]);

    case 'campaign_unschedule':
    case 'campaign_cancel':
    case 'campaign_duplicate':
    case 'campaign_delete':
        $id = (int) $P('id');
        $row = cdp_opsOne("SELECT name, created_by FROM cdb_comm_campaigns WHERE id = :id", [':id' => $id]);
        if (!$row) {
            $out(['ok' => false, 'message' => 'Campaign not found.'], 404);
        }
        if (in_array($action, ['campaign_unschedule', 'campaign_delete'], true) && (int) $row->created_by !== $uid && $rank < 3) {
            $out(['ok' => false, 'message' => 'Only the person who wrote it, or an administrator, can do that.'], 403);
        }
        $fn = ['campaign_unschedule' => 'cdp_cmpUnschedule', 'campaign_cancel' => 'cdp_cmpCancel', 'campaign_delete' => 'cdp_cmpDelete'][$action] ?? null;
        $r = $fn ? $fn($id) : cdp_cmpDuplicate($id, $uid);
        if ($r['ok']) {
            $log($action === 'campaign_delete' ? 'delete' : 'update', ['campaign_unschedule' => 'Campaign back to draft', 'campaign_cancel' => 'Campaign cancelled',
                'campaign_duplicate' => 'Campaign duplicated', 'campaign_delete' => 'Campaign draft deleted'][$action] . ': ' . $row->name, $id, (string) $row->name);
        }
        $msgs = ['campaign_unschedule' => 'Back to draft: edit it and send again.', 'campaign_cancel' => 'Cancelled' . (!empty($r['cancelled_messages']) ? ': ' . $r['cancelled_messages'] . ' waiting messages will not be sent.' : '.'),
            'campaign_duplicate' => 'Copied as a new draft.', 'campaign_delete' => 'Draft deleted.'];
        $out($r + ['message' => $r['ok'] ? $msgs[$action] : $r['error']]);

    // ---- writing with King ------------------------------------------------

    case 'ai':
        require_once(__DIR__ . '/../../helpers/comms_ai.php');
        $ch = (string) $P('channel');
        if (!in_array($ch, ['whatsapp', 'sms', 'email'], true)) {
            $out(['ok' => false, 'message' => 'King writes WhatsApp, SMS and e-mail messages.'], 422);
        }
        $blocks = cdp_opsDecode((string) $P('blocks'), []);
        $ctx = ['name' => (string) $P('name'), 'goal' => (string) $P('goal'), 'tone' => (string) $P('tone', 'keep'),
            'audience' => (string) $P('audience_label') !== '' ? (string) $P('audience_label') : ((string) $P('mode') === 'campaign' ? 'customers who agreed to receive offers' : 'one or a few customers, a personal service message'),
            'current' => ['subject' => (string) $P('subject'), 'body' => $ch === 'email' ? '' : (string) $P('body'), 'blocks' => is_array($blocks) ? $blocks : []]];
        $r = cdp_commAiDraft('campaign', $ctx, $ch, (string) $P('instruction'));
        if (empty($r['ok'])) {
            $out(['ok' => false, 'code' => $r['code'] ?? 'error', 'setup_url' => $r['setup_url'] ?? '', 'assistant' => $r['assistant'] ?? '', 'message' => $r['error'] ?: 'Not written.']);
        }
        $p = ['subject' => $r['subject'], 'body' => $r['body'], 'warnings' => $r['warnings']];
        if ($ch === 'email') {
            if (!empty($r['design'])) {
                $p['design'] = $r['design'];
            }
            if (!empty($r['blocks'])) {
                $p['blocks'] = $r['blocks'];
            }
            if (!empty($r['preview'])) {
                $p['preview'] = $r['preview'];
            }
        }
        $out(['ok' => true, 'proposals' => [$ch => $p], 'assistant' => $r['assistant'] ?? '', 'model' => $r['model'] ?? '']);

    case 'subjects':
        require_once(__DIR__ . '/../../helpers/comms_ai.php');
        $r = cdp_commAiDraft('subject_lines', ['name' => (string) $P('name'), 'goal' => (string) $P('goal'), 'body' => (string) $P('text'), 'count' => 5], '', (string) $P('instruction'));
        if (empty($r['ok'])) {
            $out(['ok' => false, 'code' => $r['code'] ?? 'error', 'setup_url' => $r['setup_url'] ?? '', 'message' => $r['error'] ?: 'No subject lines came back.']);
        }
        $out(['ok' => true, 'subjects' => $r['subjects'] ?? [$r['subject']], 'warnings' => $r['warnings']]);

    default:
        $out(['ok' => false, 'message' => 'Unknown action.'], 400);
}
