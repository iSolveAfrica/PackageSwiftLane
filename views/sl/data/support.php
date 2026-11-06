<?php
/**
 * Data for the customer service pages (CsInbox, CsTicket, CsCustomer, CsRules,
 * CsPlaybook, CsReports, CsChannels, CsAI, SupportHub). All business logic stays
 * in helpers/support.php and helpers/support_king.php; this file only shapes the
 * $d arrays the templates render.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/querys.php';
require_once CDP_ROOT . '/helpers/support_king.php';

/** Everything every CS page needs: tables, vocab, the workflow tabs, rights. */
function sl_csBase($user, string $tab): array
{
    $ready = cdp_csEnsureTables();
    $tabs = [
        ['Capture', 'CsInbox', 'inbox'], ['Solve', 'CsTicket', 'ticket'], ['Route', 'CsRules', 'rules'], ['Automate', 'CsPlaybook', 'playbook'],
        ['Measure', 'CsReports', 'reports'], ['AI Assistant', 'CsAI', 'ai'], ['Channels', 'CsChannels', 'channels'],
    ];
    $isAdmin = cdp_csIsAdmin($user);
    $out = [
        'ready' => $ready, 'isAdmin' => $isAdmin, 'uid' => cdp_csUid(), 'tab' => $tab,
        'tabs' => array_map(fn($t) => ['label' => $t[0], 'href' => $t[1] === 'CsTicket' ? sl_route('CsInbox', ['view' => 'mine']) : sl_route($t[1]), 'on' => $t[2] === $tab], array_filter($tabs, fn($t) => $isAdmin || !in_array($t[2], ['rules', 'ai', 'channels', 'reports'], true) || $t[2] === 'reports')),
        'king' => cdp_csSetting('king_name', 'King'), 'kingOn' => cdp_csKingOn(), 'copilot' => cdp_csCopilotOn(), 'aiReady' => cdp_csAiReady(),
        'statuses' => cdp_csStatuses(), 'priorities' => cdp_csPriorities(), 'categories' => cdp_csCategories(), 'channels' => cdp_csChannels(), 'languages' => cdp_csLanguages(),
        'teams' => $ready ? cdp_csTeams() : [], 'agents' => $ready ? cdp_csAgents() : [],
    ];
    return $out;
}

function sl_csStatusPill(string $status): array
{
    $map = ['new' => 'pending', 'open' => 'onway', 'pending_customer' => 'sorting', 'pending_internal' => 'sorting', 'on_hold' => 'plain', 'resolved' => 'delivered', 'closed' => 'plain'];
    return [cdp_csStatuses()[$status] ?? ucfirst($status), $map[$status] ?? 'plain'];
}

function sl_csPrioCls(string $p): string
{
    return ['urgent' => 'u', 'high' => 'h', 'normal' => 'm', 'low' => 'l'][$p] ?? 'm';
}

function sl_csChan(string $c): array
{
    $m = ['web' => ['g', 'message'], 'portal' => ['y', 'globe'], 'staff' => ['n', 'user'], 'email' => ['v', 'at'], 'whatsapp' => ['g', 'message'], 'sms' => ['b', 'phone'], 'phone' => ['b', 'phone']];
    return $m[$c] ?? ['n', 'message'];
}

/** "14 min left" / "overdue 2 h" for an SLA clock entry. */
function sl_csLeft(array $clock): array
{
    $st = $clock['state'] ?? 'ok';
    if ($st === 'met') return ['Met' . ($clock['met_at'] ?? '' ? '' : ''), 'ok'];
    if ($st === 'paused') return ['Paused', ''];
    $due = strtotime((string) ($clock['due'] ?? ''));
    if (!$due) return ['—', ''];
    $diff = $due - time();
    $fmt = function ($s) { $s = abs($s); return $s < 3600 ? max(1, floor($s / 60)) . ' min' : ($s < 86400 ? floor($s / 3600) . ' h ' . floor(($s % 3600) / 60) . ' min' : floor($s / 86400) . ' d'); };
    if ($st === 'breached' || $diff < 0) return ['Overdue ' . $fmt($diff), 'bad'];
    return [$fmt($diff) . ' left', $st === 'warning' || $diff < 3600 ? 'warn' : 'ok'];
}

function sl_csMinutes($m): string
{
    if ($m === null) return '—';
    $m = (int) round((float) $m);
    if ($m < 60) return $m . ' min';
    if ($m < 1440) return floor($m / 60) . 'h ' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT) . 'm';
    return floor($m / 1440) . 'd ' . floor(($m % 1440) / 60) . 'h';
}

// ---------------------------------------------------------------------------
// Inbox
// ---------------------------------------------------------------------------
function sl_csInboxData($user, $core): array
{
    $d = sl_csBase($user, 'inbox');
    $uid = $d['uid'];
    $f = [];
    foreach (['view', 'status', 'priority', 'category', 'channel', 'team_id', 'assignee_id', 'tag', 'language', 'from', 'to', 'q', 'sort'] as $k) {
        if (isset($_GET[$k]) && $_GET[$k] !== '') $f[$k] = (string) $_GET[$k];
    }
    $sortMap = ['newest' => 'created', 'oldest' => 'oldest', 'priority' => 'priority', 'activity' => 'activity', 'sla' => 'sla'];
    $sortKey = $_GET['sort'] ?? 'activity';
    $f['sort'] = $sortMap[$sortKey] ?? 'activity';
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $d['page'] = $page;
    $d['per'] = 25;
    $d['q'] = $f['q'] ?? '';
    $d['sort'] = $sortKey;
    $d['view'] = $f['view'] ?? 'all';
    $d['teamId'] = (int) ($f['team_id'] ?? 0);
    $d['tag'] = $f['tag'] ?? '';
    $d['filters'] = array_intersect_key($f, array_flip(['status', 'priority', 'category', 'channel', 'assignee_id', 'language', 'from', 'to']));
    $d['rows'] = [];
    $d['count'] = 0;
    $d['views'] = [];
    $d['teamsSide'] = [];
    $d['tags'] = [];
    $d['total'] = 0;
    $d['unassigned'] = 0;
    $d['firstReply'] = '—';
    if (!$d['ready']) {
        return $d;
    }
    if (mt_rand(1, 10) === 1) cdp_csSlaSweep(50);
    $list = cdp_csInboxList($f, $uid, $page, $d['per']);
    $counts = cdp_csInboxCounts($uid);
    $icons = ['all' => 'inbox', 'mine' => 'user', 'unassigned' => 'flag', 'queued' => 'hourglass', 'ai' => 'ai', 'new' => 'bolt', 'open' => 'folder', 'pending_customer' => 'hourglass', 'pending_internal' => 'users', 'on_hold' => 'clock', 'sla_risk' => 'alert', 'overdue' => 'alert', 'followed' => 'eye', 'internal' => 'shield', 'resolved' => 'check', 'closed' => 'archive', 'archived' => 'archive', 'spam' => 'ban', 'trash' => 'trash'];
    foreach (cdp_csInboxViews($uid) as $k => $v) {
        if (in_array($k, ['open', 'on_hold', 'pending_internal', 'closed', 'spam', 'trash'], true) && $d['view'] !== $k) continue; // keep the side list short like the design
        $d['views'][] = ['key' => $k, 'label' => $v[0], 'icon' => 'i-' . ($icons[$k] ?? 'inbox'), 'n' => isset($counts[$k]) ? sl_num($counts[$k]) : '', 'on' => $d['view'] === $k, 'href' => sl_url(['view' => $k, 'page' => null, 'team_id' => null])];
    }
    $saved = cdp_csSavedViews($uid);
    $d['savedViews'] = array_map(fn($v) => ['id' => $v['id'], 'name' => $v['name'], 'href' => sl_url($v['filters'] + ['page' => null, 'saved' => $v['id']]), 'on' => (int) ($_GET['saved'] ?? 0) === $v['id'], 'mine' => $v['mine']], $saved);
    foreach (sl_rows("SELECT team_id, COUNT(*) AS n FROM cdb_cs_tickets WHERE disposition = 'none' AND status NOT IN ('resolved','closed') GROUP BY team_id") as $r) $teamCounts[(int) $r->team_id] = (int) $r->n;
    foreach ($d['teams'] as $t) {
        if (!$t['active']) continue;
        $d['teamsSide'][] = ['id' => $t['id'], 'name' => $t['name'], 'n' => sl_num($teamCounts[$t['id']] ?? 0), 'on' => $d['teamId'] === $t['id'], 'href' => sl_url(['team_id' => $t['id'], 'page' => null])];
    }
    foreach (sl_rows("SELECT g.tag, COUNT(*) AS n FROM cdb_cs_ticket_tags g JOIN cdb_cs_tickets t ON t.id = g.ticket_id WHERE t.status NOT IN ('closed') GROUP BY g.tag ORDER BY n DESC LIMIT 8") as $r) {
        $d['tags'][] = ['tag' => $r->tag, 'on' => $d['tag'] === $r->tag, 'href' => sl_url(['tag' => $d['tag'] === $r->tag ? null : $r->tag, 'page' => null])];
    }
    $d['count'] = $list['total'];
    foreach ($list['rows'] as $r) {
        [$stLabel, $stCls] = sl_csStatusPill($r['status']);
        $chan = sl_csChan($r['channel']);
        $sla = $r['sla'];
        $d['rows'][] = [
            'id' => $r['id'], 'ref' => $r['ref'], 'subject' => $r['subject'], 'preview' => $r['preview'], 'awaiting' => $r['awaiting_us'],
            'customer' => $r['customer'], 'ini' => sl_initials($r['customer']), 'customer_id' => $r['customer_id'],
            'chanCls' => $chan[0], 'chanIcon' => 'i-' . $chan[1], 'chanLabel' => cdp_csChannels()[$r['channel']] ?? $r['channel'],
            'prio' => cdp_csPriorities()[$r['priority']] ?? $r['priority'], 'prioCls' => sl_csPrioCls($r['priority']),
            'assignee' => $r['assignee'] ?: 'Unassigned', 'team' => $r['team'], 'status' => $stLabel, 'statusCls' => $stCls,
            'ago' => sl_ago($r['last_activity_at']), 'sla' => sl_csLeft($sla), 'owner' => $r['owner_state'], 'tags' => $r['tags'], 'internal' => $r['is_internal'],
        ];
    }
    $d['total'] = (int) sl_scalar("SELECT COUNT(*) FROM cdb_cs_tickets WHERE disposition = 'none'");
    $d['unassigned'] = (int) ($counts['unassigned'] ?? 0);
    $fr = sl_scalar("SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, human_first_response_at)) FROM cdb_cs_tickets WHERE human_first_response_at IS NOT NULL AND created_at >= NOW() - INTERVAL 30 DAY", [], null);
    $d['firstReply'] = $fr === null ? '—' : sl_csMinutes($fr);
    $d['sorts'] = [['newest', 'Newest'], ['oldest', 'Oldest'], ['priority', 'Priority']];
    $d['canCreate'] = true;
    $d['customerPrefill'] = (int) ($_GET['customer'] ?? 0);
    return $d;
}

// ---------------------------------------------------------------------------
// Ticket
// ---------------------------------------------------------------------------
function sl_csTicketData($user, $core, int $id): array
{
    $d = sl_csBase($user, 'ticket');
    $d['id'] = $id;
    $d['ticket'] = null;
    if (!$d['ready']) return $d;
    $t = cdp_csTicket($id);
    if (!$t) return $d;
    cdp_csPresencePing($id, $d['uid']);
    $conv = $t->conversation_id ? cdp_csConv($t->conversation_id) : null;
    $teams = [];
    foreach ($d['teams'] as $tm) $teams[$tm['id']] = $tm['name'];
    [$stLabel, $stCls] = sl_csStatusPill($t->status);
    $msgs = cdp_csMessages($t->conversation_id, $t->id, 'staff');
    $me = $d['uid'];
    $thread = [];
    $notes = 0;
    $files = 0;
    foreach ($msgs as $m) {
        $when = sl_date($m['created_at'], 'M j · H:i');
        $files += count((array) $m['attachments']);
        if ($m['author_type'] === 'system') {
            $thread[] = ['kind' => 'sys', 'text' => strip_tags(str_replace('**', '', $m['body'])) . ' · ' . $when, 'id' => $m['id']];
            continue;
        }
        $isNote = $m['visibility'] === 'internal';
        if ($isNote) $notes++;
        $cls = $isNote ? 'note' : (in_array($m['author_type'], ['staff', 'king'], true) ? 'me' : '');
        $who = $m['author'] ?: ucfirst($m['author_type']);
        $thread[] = ['kind' => 'msg', 'cls' => $cls, 'note' => $isNote, 'who' => $who, 'when' => $when, 'body' => $m['body'], 'id' => $m['id'],
            'files' => array_map(fn($a) => ['name' => $a['name'] ?? 'file', 'href' => 'ajax/support/file.php?t=' . urlencode($a['token'] ?? '')], (array) $m['attachments'])];
    }
    $lastId = $msgs ? (int) end($msgs)['id'] : 0;
    $customer = $t->customer_id ? cdp_csCustomer360($t->customer_id, $user, $t->id) : null;
    $sla = cdp_csSlaClock($t);
    $first = sl_csLeft($sla['first']);
    if ($sla['first']['state'] === 'met' && $t->human_first_response_at) {
        $first = ['Met · ' . sl_csMinutes((strtotime($t->human_first_response_at) - strtotime($t->created_at)) / 60), 'ok'];
    }
    $resolve = sl_csLeft($sla['resolve']);
    $resolveTotal = max(1, strtotime($t->sla_resolve_due) - strtotime($t->created_at));
    $resolvePct = (int) max(0, min(100, round((time() - strtotime($t->created_at)) / $resolveTotal * 100)));
    $nextDue = $t->status === 'pending_customer' ? ['Waiting on customer', ''] : ($t->last_customer_at && (!$t->human_first_response_at || strtotime($t->last_customer_at) > strtotime($t->last_activity_at) - 1) ? ['Customer is waiting', 'warn'] : ['Nothing pending', 'ok']);
    $events = sl_rows('SELECT * FROM cdb_cs_events WHERE ticket_id = :t ORDER BY id DESC LIMIT 60', [':t' => $id]);
    $history = array_map(fn($e) => ['type' => str_replace(['.', '_'], ' ', $e->event_type), 'actor' => $e->actor_type === 'staff' ? cdp_csUserName($e->actor_id) : ($e->actor_type === 'king' ? $d['king'] : ucfirst($e->actor_type)), 'at' => sl_date($e->created_at, 'M j · H:i'), 'data' => cdp_csDecode($e->data)], $events);
    $viewers = cdp_csPresence($id, $me);
    $links = cdp_csLinks($id);
    $tasks = cdp_csTasks($id);
    $followers = cdp_csFollowers($id);
    $following = false;
    foreach ($followers as $fo) if ((int) ($fo['user_id'] ?? $fo['id'] ?? 0) === $me) $following = true;
    $tags = cdp_csTags($id);
    // Prev / next in the open queue
    $ids = array_map(fn($r) => (int) $r->id, sl_rows("SELECT id FROM cdb_cs_tickets WHERE disposition = 'none' AND status NOT IN ('resolved','closed') ORDER BY last_activity_at DESC LIMIT 200"));
    $pos = array_search($id, $ids, true);
    $d['prev'] = $pos !== false && $pos > 0 ? $ids[$pos - 1] : 0;
    $d['next'] = $pos !== false && $pos < count($ids) - 1 ? $ids[$pos + 1] : 0;
    $d['posLabel'] = $pos !== false ? 'Ticket ' . ($pos + 1) . ' of ' . count($ids) . ' in "All open"' : $stLabel;
    $canned = cdp_csCanned($me);
    $kbHits = cdp_csKbSearch($t->subject, ['public', 'customer', 'staff'], 3);
    $d['ticket'] = [
        'id' => (int) $t->id, 'ref' => $t->public_ref, 'subject' => $t->subject, 'status' => $t->status, 'statusLabel' => $stLabel, 'statusCls' => $stCls,
        'priority' => $t->priority, 'prioLabel' => cdp_csPriorities()[$t->priority] ?? $t->priority, 'prioCls' => sl_csPrioCls($t->priority),
        'category' => $t->category, 'categoryLabel' => cdp_csCategories()[$t->category] ?? $t->category, 'channel' => $t->channel, 'channelLabel' => cdp_csChannels()[$t->channel] ?? $t->channel,
        'language' => $t->language, 'languageLabel' => cdp_csLanguages()[$t->language] ?? strtoupper($t->language), 'languageSource' => $t->language_source,
        'team_id' => (int) $t->team_id, 'team' => $teams[(int) $t->team_id] ?? 'No team', 'assignee_id' => (int) $t->assignee_id, 'assignee' => cdp_csUserName($t->assignee_id) ?: 'Unassigned',
        'from' => $t->contact_name ?: 'No account', 'opened' => sl_date($t->created_at, 'M j, H:i'), 'n' => count($thread), 'notes' => $notes, 'files' => $files,
        'version' => (int) $t->version, 'customer_id' => (int) $t->customer_id, 'internal' => (int) $t->is_internal === 1, 'summary' => (string) $t->summary,
        'due' => sl_date($t->sla_resolve_due, 'M j, H:i'), 'resolved' => in_array($t->status, ['resolved', 'closed'], true), 'disposition' => $t->disposition,
        'csat' => $t->csat_score, 'resolution' => (string) $t->resolution_summary, 'merged' => (int) $t->merged_into,
        'owner' => $conv ? $conv->owner_state : '', 'ownerLabel' => $conv ? (cdp_csOwnerStates()[$conv->owner_state] ?? '') : '', 'ownerUser' => $conv ? (int) $conv->owner_user_id : 0, 'hasChat' => (bool) $conv,
        'activity' => count($events), 'lastId' => $lastId,
    ];
    $d['thread'] = $thread;
    $d['sla'] = ['first' => $first, 'resolve' => $resolve, 'resolvePct' => $resolvePct, 'resolveHours' => round($resolveTotal / 3600), 'next' => $nextDue, 'policy' => $sla['policy']];
    $d['viewers'] = $viewers;
    $d['customer'] = $customer;
    $d['links'] = $links;
    $d['tasks'] = $tasks;
    $d['followers'] = $followers;
    $d['following'] = $following;
    $d['tags'] = $tags;
    $d['history'] = $history;
    $d['similar'] = cdp_csSimilarOpen($t->customer_id, $t->id);
    $d['kb'] = $kbHits;
    $d['canned'] = $canned;
    $d['refine'] = cdp_csRefineModes();
    $d['proposals'] = cdp_csProposals($id);
    $d['proposalKinds'] = cdp_csProposalKinds();
    $d['resolutionCodes'] = cdp_csResolutionCodes();
    $d['manualRules'] = array_values(array_filter(cdp_csRules(), fn($r) => $r['trigger_event'] === 'manual' && $r['status'] === 'published'));
    return $d;
}

// ---------------------------------------------------------------------------
// Customer 360
// ---------------------------------------------------------------------------
function sl_csCustomerData($user, $core, int $customerId): array
{
    $d = sl_csBase($user, 'ticket');
    $d['customer'] = null;
    $c = sl_row('SELECT * FROM cdb_users WHERE id = :i AND userlevel = 1', [':i' => $customerId]);
    if (!$c) return $d;
    $c360 = $d['ready'] ? cdp_csCustomer360($customerId, $user) : null;
    $name = trim($c->fname . ' ' . $c->lname);
    $open = $d['ready'] ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_cs_tickets WHERE customer_id = :c AND status NOT IN ('resolved','closed') AND disposition = 'none'", [':c' => $customerId]) : 0;
    $all = $d['ready'] ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_cs_tickets WHERE customer_id = :c', [':c' => $customerId]) : 0;
    $solved = $d['ready'] ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_cs_tickets WHERE customer_id = :c AND status IN ('resolved','closed')", [':c' => $customerId]) : 0;
    $avgRes = $d['ready'] ? sl_scalar("SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) FROM cdb_cs_tickets WHERE customer_id = :c AND resolved_at IS NOT NULL", [':c' => $customerId], null) : null;
    $ship = sl_row("SELECT COUNT(*) AS n, COALESCE(SUM(CASE WHEN YEAR(order_date) = YEAR(CURDATE()) THEN total_weight ELSE 0 END),0) AS kg, SUM(status_courier IN (28,29)) AS held FROM cdb_add_order WHERE sender_id = :c AND is_deleted = 0", [':c' => $customerId]);
    $yearN = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order WHERE sender_id = :c AND is_deleted = 0 AND YEAR(order_date) = YEAR(CURDATE())", [':c' => $customerId]);
    $due = sl_row("SELECT COALESCE(SUM(CASE WHEN status_invoice = 23 AND status_courier <> 21 THEN total_order ELSE 0 END),0) AS due, COALESCE(SUM(CASE WHEN status_invoice = 19 THEN total_order ELSE 0 END),0) AS paid FROM cdb_add_order WHERE sender_id = :c AND is_deleted = 0", [':c' => $customerId]);
    $money = function_exists('cdp_canViewMoney') ? cdp_canViewMoney($user, 'customers') : false;
    $tickets = $d['ready'] ? sl_rows('SELECT id, public_ref, subject, status, channel, created_at, csat_score FROM cdb_cs_tickets WHERE customer_id = :c ORDER BY id DESC LIMIT 8', [':c' => $customerId]) : [];
    $lastCsat = $d['ready'] ? sl_row('SELECT csat_score, csat_at FROM cdb_cs_tickets WHERE customer_id = :c AND csat_score IS NOT NULL ORDER BY csat_at DESC', [':c' => $customerId]) : null;
    $months = [];
    $map = [];
    if ($d['ready']) {
        foreach (sl_rows("SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS n FROM cdb_cs_tickets WHERE customer_id = :c AND created_at >= DATE_FORMAT(CURDATE() - INTERVAL 11 MONTH, '%Y-%m-01') GROUP BY ym", [':c' => $customerId]) as $r) $map[$r->ym] = (int) $r->n;
    }
    $max = max(1, $map ? max($map) : 1);
    for ($i = 11; $i >= 0; $i--) {
        $ym = date('Y-m', strtotime("first day of -$i months"));
        $months[] = ['l' => date('M', strtotime($ym . '-01')), 'n' => $map[$ym] ?? 0];
    }
    $events = sl_tableExists('cdb_activity_log') ? sl_rows('SELECT action_label, summary, created_at, module FROM cdb_activity_log WHERE entity_type = :t AND entity_id = :i ORDER BY id DESC LIMIT 6', [':t' => 'customer', ':i' => $customerId]) : [];
    $notes = $d['ready'] ? sl_rows("SELECT m.body, m.author_id, m.created_at FROM cdb_cs_messages m JOIN cdb_cs_tickets t ON t.id = m.ticket_id WHERE t.customer_id = :c AND m.visibility = 'internal' AND m.author_type = 'staff' ORDER BY m.id DESC LIMIT 3", [':c' => $customerId]) : [];
    $lastTicket = $tickets[0] ?? null;
    require_once CDP_ROOT . '/helpers/loyalty.php';
    $loy = sl_loyaltyEnabled() ? sl_loyaltyAccount($customerId, false) : null;
    $pref = sl_tableExists('cdb_customer_notification_prefs') ? sl_row('SELECT * FROM cdb_customer_notification_prefs WHERE user_id = :u', [':u' => $customerId]) : null;
    $d['customer'] = [
        'id' => (int) $c->id, 'name' => $name, 'ini' => sl_initials($name), 'since' => sl_date($c->created, 'M Y'), 'locker' => $c->locker ?: '—', 'city' => $c->city ?? '', 'address' => trim(($c->address ?? '') . ' ' . ($c->city ?? '')),
        'email' => $c->email, 'phone' => $c->phone ?: '—', 'verified' => !empty($c->document_photo), 'tier' => $loy ? sl_loyaltyTierLabel($loy->tier) : '', 'points' => $loy ? (int) $loy->points_balance : null,
        'open' => $open, 'all' => $all, 'solved' => $solved, 'avgRes' => sl_csMinutes($avgRes), 'solvedPct' => sl_pct($solved, $all),
        'shipments' => (int) ($ship->n ?? 0), 'held' => (int) ($ship->held ?? 0), 'kg' => number_format((float) ($ship->kg ?? 0), 1), 'yearPct' => sl_pct($yearN, max(1, (int) ($ship->n ?? 0))),
        'csat' => $c360['csat_avg'] ?? null, 'csatN' => $c360['csat_n'] ?? 0, 'lastCsat' => $lastCsat ? $lastCsat->csat_score . ' on ' . sl_date($lastCsat->csat_at) : 'No rating yet',
        'due' => $money ? sl_money($due->due ?? 0) : '—', 'dueRaw' => (float) ($due->due ?? 0), 'paid' => $money ? sl_money($due->paid ?? 0) : '—', 'duePct' => sl_pct($due->due ?? 0, ((float) ($due->due ?? 0)) + ((float) ($due->paid ?? 0))),
        'records' => $c360['records'] ?? [], 'lastTicketId' => $lastTicket ? (int) $lastTicket->id : 0, 'lastTicketRef' => $lastTicket ? $lastTicket->public_ref : '',
        'language' => cdp_csLanguages()[$c->language ?? 'en'] ?? 'English', 'lastLogin' => $c->lastlogin ? sl_ago($c->lastlogin) : 'never',
        'prefs' => ['whatsapp' => $pref ? !empty($pref->whatsapp) : true, 'sms' => $pref ? !empty($pref->sms) : true, 'marketing' => $pref ? !empty($pref->marketing) : false],
    ];
    $d['tickets'] = array_map(function ($t) { [$l, $cls] = sl_csStatusPill($t->status); return ['id' => (int) $t->id, 'ref' => $t->public_ref, 'subject' => $t->subject, 'status' => $l, 'ok' => in_array($t->status, ['resolved', 'closed'], true), 'chan' => cdp_csChannels()[$t->channel] ?? $t->channel, 'when' => sl_ago($t->created_at)]; }, $tickets);
    $d['months'] = $months;
    $d['monthsMax'] = $max;
    $d['activity'] = array_map(fn($e) => ['text' => $e->summary ?: $e->action_label, 'when' => sl_ago($e->created_at), 'icon' => ['customers' => 'user', 'shipments' => 'box', 'payments' => 'card', 'support' => 'ticket'][$e->module] ?? 'clock'], $events);
    $d['notes'] = array_map(fn($n) => ['who' => cdp_csUserName($n->author_id), 'when' => sl_date($n->created_at), 'body' => $n->body], $notes);
    return $d;
}

// ---------------------------------------------------------------------------
// Rules
// ---------------------------------------------------------------------------
function sl_csRulesData($user, $core): array
{
    $d = sl_csBase($user, 'rules');
    $d['rules'] = [];
    $d['templates'] = [];
    if (!$d['ready']) return $d;
    $icons = ['billing_route' => 'card', 'warehouse_missing_ref' => 'warehouse', 'claims_priority' => 'bug', 'handover_route' => 'users', 'classify_new' => 'lang', 'no_reply_reminder' => 'hourglass', 'sla_escalate' => 'alert', 'poor_csat' => 'star'];
    $existing = cdp_csRules();
    $used = array_filter(array_map(fn($r) => $r['template_key'] ?? null, sl_rows('SELECT template_key FROM cdb_cs_rules WHERE template_key IS NOT NULL') ? array_map(fn($r) => (array) $r, sl_rows('SELECT template_key FROM cdb_cs_rules WHERE template_key IS NOT NULL')) : []));
    foreach (cdp_csRuleTemplates() as $k => $t) {
        $d['templates'][] = ['key' => $k, 'name' => $t['name'], 'sub' => cdp_csRuleTriggers()[$t['trigger_event']] ?? '', 'icon' => 'i-' . ($icons[$k] ?? 'sliders'), 'added' => in_array($k, $used, true)];
    }
    $triggers = cdp_csRuleTriggers();
    $fields = cdp_csRuleFields();
    $ops = cdp_csRuleOps();
    $actions = cdp_csRuleActions();
    $teams = [];
    foreach ($d['teams'] as $t) $teams[$t['id']] = $t['name'];
    $agents = [];
    foreach ($d['agents'] as $a) $agents[$a['id']] = $a['name'];
    foreach ($existing as $r) {
        $condText = [];
        foreach ($r['conditions'] as $c) {
            $f = $fields[$c['field']] ?? null;
            $v = $c['value'];
            if ($f && ($f['type'] ?? '') === 'enum') $v = $f['options'][$v] ?? $v;
            if ($f && ($f['type'] ?? '') === 'team') $v = $teams[(int) $v] ?? $v;
            $condText[] = ($f['label'] ?? $c['field']) . ' ' . ($ops[$c['op']] ?? $c['op']) . ($v !== '' ? ' "' . $v . '"' : '');
        }
        $actText = [];
        foreach ($r['actions'] as $a) {
            $m = $actions[$a['type']] ?? null;
            $v = $a['value'];
            if ($m && $m['value'] === 'enum') $v = $m['options'][$v] ?? $v;
            if ($m && $m['value'] === 'team') $v = $teams[(int) $v] ?? $v;
            if ($m && $m['value'] === 'agent') $v = $agents[(int) $v] ?? $v;
            $actText[] = ($m['label'] ?? $a['type']) . ($v !== '' && ($m['value'] ?? '') !== 'none' ? ' → ' . mb_substr($v, 0, 40) : '');
        }
        $d['rules'][] = $r + [
            'triggerLabel' => $triggers[$r['trigger_event']] ?? $r['trigger_event'],
            'sentence' => 'When ' . lcfirst($triggers[$r['trigger_event']] ?? $r['trigger_event']) . ($condText ? ' and ' . implode($r['match_mode'] === 'any' ? ' or ' : ' and ', $condText) : '') . ': ' . implode(', ', $actText) . '.',
            'footer' => $r['status'] === 'paused' ? 'Paused' : ($r['status'] === 'draft' ? 'Draft · not running' : 'Ran ' . sl_num($r['run_count']) . ' time' . ($r['run_count'] === 1 ? '' : 's') . ($r['fail_count'] ? ' · ' . $r['fail_count'] . ' failed' : '')),
            'on' => $r['status'] === 'published',
        ];
    }
    $d['vocab'] = ['triggers' => $triggers, 'fields' => $fields, 'ops' => $ops, 'actions' => $actions, 'teams' => $teams, 'agents' => $agents, 'canned' => array_map(fn($c) => ['id' => $c['id'], 'title' => $c['title']], cdp_csCanned($d['uid']))];
    $d['channelsSide'] = sl_csChannelHealth();
    return $d;
}

/** Channel health rows shared by CsRules and CsChannels. */
function sl_csChannelHealth(): array
{
    $settings = function_exists('cdp_getSettingsCourier') ? cdp_getSettingsCourier() : null;
    $emailOk = $settings && (($settings->mailer ?? '') === 'PHP' || trim((string) ($settings->smtp_host ?? '')) !== '');
    $kingOn = cdp_csKingOn();
    return [
        ['label' => 'Web chat widget', 'icon' => 'globe', 'cls' => 'g', 'state' => 'ON', 'pill' => 'delivered', 'detail' => $kingOn ? cdp_csSetting('king_name', 'King') . ' answers first' : 'A person answers'],
        ['label' => 'Support request form', 'icon' => 'inbox', 'cls' => 'y', 'state' => 'ON', 'pill' => 'delivered', 'detail' => 'Help & Support › New request'],
        ['label' => 'E-mail · outgoing updates', 'icon' => 'at', 'cls' => 'v', 'state' => $emailOk ? 'ON' : 'SETUP', 'pill' => $emailOk ? 'delivered' : 'pending', 'detail' => $emailOk ? 'Uses Settings › Email' : 'Set up the mail server first'],
        ['label' => 'WhatsApp · outgoing updates', 'icon' => 'message', 'cls' => 'g', 'state' => in_array('whatsapp', explode(',', cdp_csSetting('notify_channels', 'email')), true) ? 'ON' : 'OFF', 'pill' => in_array('whatsapp', explode(',', cdp_csSetting('notify_channels', 'email')), true) ? 'delivered' : 'pending', 'detail' => 'Settings › WhatsApp provider'],
        ['label' => 'SMS · outgoing updates', 'icon' => 'phone', 'cls' => 'b', 'state' => in_array('sms', explode(',', cdp_csSetting('notify_channels', 'email')), true) ? 'ON' : 'OFF', 'pill' => in_array('sms', explode(',', cdp_csSetting('notify_channels', 'email')), true) ? 'delivered' : 'pending', 'detail' => 'Settings › SMS provider'],
        ['label' => 'Incoming e-mail / WhatsApp into tickets', 'icon' => 'link', 'cls' => 'n', 'state' => 'SOON', 'pill' => 'onway', 'detail' => 'Needs a provider webhook'],
    ];
}

// ---------------------------------------------------------------------------
// Playbook (saved replies + knowledge base)
// ---------------------------------------------------------------------------
function sl_csPlaybookData($user, $core): array
{
    $d = sl_csBase($user, 'playbook');
    $d['canned'] = $d['ready'] ? cdp_csCanned($d['uid']) : [];
    $d['kb'] = $d['ready'] ? cdp_csKbList(true) : [];
    $d['published'] = count(array_filter($d['kb'], fn($a) => $a['status'] === 'published'));
    $d['kbCategories'] = array_values(array_unique(array_filter(array_map(fn($a) => $a['category'], $d['kb']))));
    $d['edit'] = ['kb' => (int) ($_GET['kb'] ?? 0), 'canned' => (int) ($_GET['canned'] ?? 0)];
    $d['rulesCount'] = $d['ready'] ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_cs_rules WHERE status = 'published'") : 0;
    $d['example'] = null;
    if ($d['ready']) {
        $r = sl_row("SELECT * FROM cdb_cs_rules WHERE status = 'published' ORDER BY run_count DESC");
        if ($r) {
            $d['example'] = ['name' => $r->name, 'trigger' => cdp_csRuleTriggers()[$r->trigger_event] ?? $r->trigger_event, 'conds' => cdp_csDecode($r->conditions), 'acts' => cdp_csDecode($r->actions)];
        }
    }
    $icons = ['account' => 'user', 'warehouse' => 'warehouse', 'shipment' => 'plane', 'billing' => 'card', 'delivery' => 'truck', 'claim' => 'bug', 'complaint' => 'alert', 'business' => 'users', 'technical' => 'cog', 'general' => 'help'];
    $d['kbIcon'] = fn($cat) => 'i-' . ($icons[strtolower((string) $cat)] ?? 'book');
    return $d;
}

// ---------------------------------------------------------------------------
// Reports
// ---------------------------------------------------------------------------
function sl_csReportsData($user, $core): array
{
    $d = sl_csBase($user, 'reports');
    $days = in_array((int) ($_GET['days'] ?? 30), [7, 30, 90], true) ? (int) ($_GET['days'] ?? 30) : 30;
    $d['days'] = $days;
    $d['section'] = (string) ($_GET['section'] ?? 'resolution');
    $d['sections'] = [['resolution', 'Resolution time', 'clock'], ['first', 'First response', 'reply'], ['volume', 'Ticket volume', 'chart'], ['csat', 'Customer satisfaction', 'star'], ['agents', 'Teammate workload', 'users'], ['channels', 'Channel mix', 'message']];
    $d['report'] = null;
    if (!$d['ready']) return $d;
    $to = date('Y-m-d');
    $from = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));
    $pfrom = date('Y-m-d', strtotime("-" . (2 * $days - 1) . " days"));
    $pto = date('Y-m-d', strtotime("-$days days"));
    $r = cdp_csReport($from, $to);
    $p = cdp_csReport($pfrom, $pto);
    $d['report'] = $r;
    $d['prev'] = $p;
    $delta = function ($now, $before, $lowerIsBetter = true, $unit = 'min') {
        if ($now === null || $before === null) return '';
        $diff = $now - $before;
        if (abs($diff) < 0.5) return 'same as last period';
        $arrow = $diff < 0 ? '▼' : '▲';
        $val = $unit === 'min' ? sl_csMinutes(abs($diff)) : number_format(abs($diff), $unit === 'pts' ? 1 : 0) . ($unit === '%' ? ' pts' : '');
        return "$arrow $val vs last period";
    };
    $d['kpi'] = [
        'resolution' => sl_csMinutes($r['resolution']['median']), 'resolutionSub' => $delta($r['resolution']['median'], $p['resolution']['median']),
        'first' => sl_csMinutes($r['first_response']['median']), 'firstSub' => $delta($r['first_response']['median'], $p['first_response']['median']),
        'solved' => sl_num($r['resolved']), 'tickets' => sl_num($r['tickets']), 'solvedPct' => sl_pct($r['resolved'], $r['tickets']),
        'csat' => $r['csat']['responses'] ? number_format(sl_csCsatAvg($from, $to), 1) : '—', 'csatN' => $r['csat']['responses'], 'csatPct' => sl_pct($r['csat']['positive'], $r['csat']['responses']),
    ];
    // Resolution time by weekday (median / mean) from resolved tickets in the period
    $rows = sl_rows("SELECT DAYOFWEEK(created_at) AS dow, TIMESTAMPDIFF(MINUTE, created_at, resolved_at) - sla_paused_minutes AS m FROM cdb_cs_tickets WHERE resolved_at IS NOT NULL AND created_at BETWEEN :f AND :t AND disposition IN ('none','archived')", [':f' => $from . ' 00:00:00', ':t' => $to . ' 23:59:59']);
    $byDow = array_fill(1, 7, []);
    foreach ($rows as $x) $byDow[(int) $x->dow][] = max(0, (int) $x->m);
    $order = [2, 3, 4, 5, 6, 7, 1];
    $labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $bars = [];
    $max = 1;
    foreach ($order as $i => $dow) {
        $s = cdp_csStats($byDow[$dow]);
        $bars[] = ['l' => $labels[$i], 'avg' => $s['mean'] ?? 0, 'med' => $s['median'] ?? 0, 'n' => $s['n']];
        $max = max($max, $s['mean'] ?? 0);
    }
    foreach ($bars as &$b) { $b['avgH'] = sl_pct($b['avg'], $max); $b['medH'] = sl_pct($b['med'], $max); $b['tip'] = $b['l'] . ': ' . $b['n'] . ' resolved · avg ' . sl_csMinutes($b['avg']) . ' · median ' . sl_csMinutes($b['med']); }
    unset($b);
    $d['weekday'] = $bars;
    // Volume by day
    $vol = [];
    $vmax = 1;
    foreach ($r['by_day'] as $day => $n) { $vol[] = ['l' => date('j M', strtotime($day)), 'n' => $n]; $vmax = max($vmax, $n); }
    $d['volume'] = array_map(fn($v) => $v + ['h' => sl_pct($v['n'], $vmax)], $vol);
    // Topic breakdown
    $topics = sl_rows("SELECT category, COUNT(*) AS n, AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, created_at, resolved_at) - sla_paused_minutes END) AS res, AVG(CASE WHEN human_first_response_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, created_at, human_first_response_at) END) AS fr, AVG(csat_score) AS csat FROM cdb_cs_tickets WHERE created_at BETWEEN :f AND :t AND disposition IN ('none','archived') GROUP BY category ORDER BY n DESC", [':f' => $from . ' 00:00:00', ':t' => $to . ' 23:59:59']);
    $d['topics'] = array_map(fn($x) => ['label' => cdp_csCategories()[$x->category] ?? $x->category, 'n' => (int) $x->n, 'res' => sl_csMinutes($x->res), 'fr' => sl_csMinutes($x->fr), 'csat' => $x->csat === null ? '—' : number_format((float) $x->csat, 1)], $topics);
    $d['agents'] = $r['agents'];
    $d['channelsMix'] = $r['by_channel'];
    $d['teamsMix'] = $r['by_team'];
    $d['ai'] = $r['ai'];
    $d['backlog'] = $r['backlog'];
    $d['exportHref'] = 'ajax/support/reports_ajax.php?action=export&from=' . $from . '&to=' . $to;
    $d['from'] = $from;
    $d['to'] = $to;
    return $d;
}

function sl_csCsatAvg(string $from, string $to): float
{
    return (float) sl_scalar('SELECT AVG(csat_score) FROM cdb_cs_tickets WHERE csat_score IS NOT NULL AND created_at BETWEEN :f AND :t', [':f' => $from . ' 00:00:00', ':t' => $to . ' 23:59:59'], 0);
}

// ---------------------------------------------------------------------------
// Channels and teams
// ---------------------------------------------------------------------------
function sl_csChannelsData($user, $core): array
{
    $d = sl_csBase($user, 'channels');
    $d['in'] = [];
    $d['teamsOut'] = [];
    $d['teamCards'] = [];
    $d['monthTotal'] = 0;
    $d['notes'] = [];
    if (!$d['ready']) return $d;
    $since = date('Y-m-01 00:00:00');
    $chan = [];
    foreach (sl_rows('SELECT channel, COUNT(*) AS n FROM cdb_cs_tickets WHERE created_at >= :s GROUP BY channel', [':s' => $since]) as $r) $chan[$r->channel] = (int) $r->n;
    $max = max(1, $chan ? max($chan) : 1);
    foreach (cdp_csChannels() as $k => $label) {
        if (!isset($chan[$k]) && !in_array($k, ['web', 'portal', 'email', 'whatsapp'], true)) continue;
        $c = sl_csChan($k);
        $d['in'][] = ['label' => $label, 'icon' => 'i-' . $c[1], 'n' => sl_num($chan[$k] ?? 0), 'w' => sl_pct($chan[$k] ?? 0, $max)];
    }
    $d['monthTotal'] = array_sum($chan);
    $tc = [];
    foreach (sl_rows('SELECT team_id, COUNT(*) AS n FROM cdb_cs_tickets WHERE created_at >= :s GROUP BY team_id', [':s' => $since]) as $r) $tc[(int) $r->team_id] = (int) $r->n;
    foreach ($d['teams'] as $t) {
        $members = cdp_csTeamMembers($t['id']);
        $d['teamsOut'][] = ['id' => $t['id'], 'name' => $t['name'], 'desc' => $t['description'] ?: ucfirst(str_replace('_', ' ', $t['assign_mode'])) . ' assignment', 'n' => sl_num($tc[$t['id']] ?? 0), 'href' => sl_route('CsInbox', ['team_id' => $t['id']])];
        $d['teamCards'][] = $t + ['members_list' => $members, 'modeLabel' => ['manual' => 'Manual', 'round_robin' => 'Round robin', 'least_loaded' => 'Least loaded'][$t['assign_mode']] ?? $t['assign_mode']];
    }
    $d['notes'] = array_map(fn($m) => ['who' => cdp_csUserName($m->author_id), 'body' => $m->body, 'ref' => $m->public_ref, 'id' => (int) $m->ticket_id], sl_rows("SELECT m.body, m.author_id, t.public_ref, m.ticket_id FROM cdb_cs_messages m JOIN cdb_cs_tickets t ON t.id = m.ticket_id WHERE m.visibility = 'internal' AND m.author_type = 'staff' ORDER BY m.id DESC LIMIT 3"));
    $d['health'] = sl_csChannelHealth();
    $d['settings'] = cdp_csSettings(true);
    $outbox = [];
    foreach (sl_rows("SELECT status, COUNT(*) AS n FROM cdb_cs_outbox WHERE created_at >= NOW() - INTERVAL 7 DAY GROUP BY status") as $r) $outbox[$r->status] = (int) $r->n;
    $d['outbox'] = $outbox;
    $d['outboxProblems'] = sl_rows("SELECT id, ticket_id, channel, kind, last_error, attempts, created_at FROM cdb_cs_outbox WHERE status IN ('dead','retry') ORDER BY id DESC LIMIT 10");
    $d['workerLast'] = cdp_csSetting('worker_last_run', '');
    $d['timezones'] = ['Africa/Accra', 'Africa/Lagos', 'Europe/London', 'America/New_York', 'UTC'];
    return $d;
}

// ---------------------------------------------------------------------------
// AI for customers (King)
// ---------------------------------------------------------------------------
function sl_csAiData($user, $core): array
{
    $d = sl_csBase($user, 'ai');
    $s = $d['ready'] ? cdp_csSettings(true) : cdp_csSettingDefaults();
    $d['settings'] = $s;
    $d['providerLabel'] = cdp_aiProviderLabel(cdp_aiSetting('provider'));
    $d['model'] = (string) cdp_aiSetting('model');
    $d['mode'] = $s['king_mode'];
    $d['runs'] = [];
    $d['runStats'] = [];
    if ($d['ready']) {
        foreach (sl_rows("SELECT outcome, COUNT(*) AS n, AVG(latency_ms) AS ms FROM cdb_cs_ai_runs WHERE created_at >= NOW() - INTERVAL 7 DAY GROUP BY outcome ORDER BY n DESC") as $r) $d['runStats'][] = ['outcome' => $r->outcome, 'n' => (int) $r->n, 'ms' => $r->ms === null ? '—' : round((float) $r->ms) . ' ms'];
        $d['runs'] = array_map(fn($r) => ['kind' => $r->kind, 'outcome' => $r->outcome, 'ref' => $r->public_ref, 'ticket_id' => (int) $r->ticket_id, 'model' => $r->model, 'ms' => $r->latency_ms, 'tokens' => (int) $r->tokens_in + (int) $r->tokens_out, 'detail' => $r->detail, 'at' => sl_ago($r->created_at), 'ok' => in_array($r->outcome, ['answered', 'ok', 'drafted', 'done', 'summary', 'classified'], true) || strpos($r->outcome, 'error') === false && strpos($r->outcome, 'fail') === false && strpos($r->outcome, 'discard') === false],
            sl_rows('SELECT r.*, t.public_ref FROM cdb_cs_ai_runs r LEFT JOIN cdb_cs_tickets t ON t.id = r.ticket_id ORDER BY r.id DESC LIMIT 12'));
        $d['kbPublished'] = (int) sl_scalar("SELECT COUNT(*) FROM cdb_cs_kb WHERE status = 'published'");
        $d['today'] = (int) sl_scalar("SELECT COUNT(*) FROM cdb_cs_ai_runs WHERE kind = 'king' AND DATE(created_at) = CURDATE()");
    }
    return $d;
}

// ---------------------------------------------------------------------------
// Support hub
// ---------------------------------------------------------------------------
function sl_csHubData($user, $core): array
{
    $d = sl_csBase($user, 'hub');
    $d['kpis'] = [];
    $d['sla'] = [];
    $d['esc'] = [];
    $d['cats'] = [];
    $d['kb'] = [];
    $d['dis'] = [];
    if (!$d['ready']) return $d;
    $open = (int) sl_scalar("SELECT COUNT(*) FROM cdb_cs_tickets WHERE disposition = 'none' AND status NOT IN ('resolved','closed')");
    $queued = (int) sl_scalar("SELECT COUNT(*) FROM cdb_cs_conversations WHERE owner_state = 'handoff_queued'");
    $breached = (int) sl_scalar("SELECT COUNT(*) FROM cdb_cs_tickets WHERE disposition = 'none' AND status NOT IN ('resolved','closed') AND (first_breached_at IS NOT NULL OR resolve_breached_at IS NOT NULL OR sla_resolve_due < NOW())");
    $fr = sl_scalar("SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, human_first_response_at)) FROM cdb_cs_tickets WHERE human_first_response_at IS NOT NULL AND created_at >= NOW() - INTERVAL 30 DAY", [], null);
    $csat = sl_row('SELECT AVG(csat_score) AS a, COUNT(csat_score) AS n FROM cdb_cs_tickets WHERE csat_at >= NOW() - INTERVAL 30 DAY');
    $solvedToday = (int) sl_scalar('SELECT COUNT(*) FROM cdb_cs_tickets WHERE DATE(resolved_at) = CURDATE()');
    $spark = function (string $sql, array $b = []) {
        $map = [];
        foreach (sl_rows($sql, $b) as $r) $map[$r->d] = (int) $r->n;
        $out = [];
        $max = max(1, $map ? max($map) : 1);
        for ($i = 11; $i >= 0; $i--) { $day = date('Y-m-d', strtotime("-$i days")); $out[] = 'height:' . max(8, sl_pct($map[$day] ?? 0, $max)) . '%'; }
        return $out;
    };
    $d['kpis'] = [
        ['icon' => 'i-ticket', 'l' => 'Open tickets', 'v' => sl_num($open), 's' => $queued . ' waiting for a person', 'sp' => $spark("SELECT DATE(created_at) AS d, COUNT(*) AS n FROM cdb_cs_tickets WHERE created_at >= CURDATE() - INTERVAL 11 DAY GROUP BY d"), 'href' => sl_route('CsInbox')],
        ['icon' => 'i-clock', 'l' => 'First reply', 'v' => $fr === null ? '—' : sl_csMinutes($fr), 's' => 'median target ' . cdp_csSetting('sla_first_normal', '240') . ' min', 'sp' => $spark("SELECT DATE(created_at) AS d, COUNT(*) AS n FROM cdb_cs_tickets WHERE human_first_response_at IS NOT NULL AND created_at >= CURDATE() - INTERVAL 11 DAY GROUP BY d"), 'href' => sl_route('CsReports')],
        ['icon' => 'i-alert', 'l' => 'SLA breached', 'v' => sl_num($breached), 's' => 'open tickets past a deadline', 'sp' => $spark("SELECT DATE(created_at) AS d, COUNT(*) AS n FROM cdb_cs_tickets WHERE (first_breached_at IS NOT NULL OR resolve_breached_at IS NOT NULL) AND created_at >= CURDATE() - INTERVAL 11 DAY GROUP BY d"), 'href' => sl_route('CsInbox', ['view' => 'overdue'])],
        ['icon' => 'i-star', 'l' => 'CSAT · 30 days', 'v' => $csat && $csat->n ? number_format((float) $csat->a, 1) : '—', 's' => sl_num($csat->n ?? 0) . ' ratings', 'sp' => $spark("SELECT DATE(csat_at) AS d, COUNT(*) AS n FROM cdb_cs_tickets WHERE csat_at >= CURDATE() - INTERVAL 11 DAY GROUP BY d"), 'href' => sl_route('CsReports', ['section' => 'csat'])],
        ['icon' => 'i-check', 'l' => 'Solved today', 'v' => sl_num($solvedToday), 's' => count($d['teams']) . ' team' . (count($d['teams']) === 1 ? '' : 's') . ' · ' . count($d['agents']) . ' agents', 'sp' => $spark("SELECT DATE(resolved_at) AS d, COUNT(*) AS n FROM cdb_cs_tickets WHERE resolved_at >= CURDATE() - INTERVAL 11 DAY GROUP BY d"), 'href' => sl_route('CsInbox', ['view' => 'resolved'])],
    ];
    foreach (sl_rows("SELECT * FROM cdb_cs_tickets WHERE disposition = 'none' AND status NOT IN ('resolved','closed') ORDER BY sla_resolve_due ASC LIMIT 6") as $t) {
        $clock = cdp_csSlaClock($t);
        [$left, $cls] = sl_csLeft($clock['resolve']['state'] === 'met' ? $clock['first'] : $clock['resolve']);
        $d['sla'][] = ['id' => (int) $t->id, 'ref' => $t->public_ref, 't' => $t->subject, 'who' => cdp_csUserName($t->assignee_id) ?: 'Unassigned', 'cat' => cdp_csCategories()[$t->category] ?? $t->category, 'left' => $left, 'd' => $cls === 'bad' ? 'e' : ($cls === 'warn' ? 'w' : ''), 'c' => $cls === 'bad' ? 'var(--red-ink)' : ($cls === 'warn' ? 'var(--amber-ink)' : 'var(--green-ink)')];
    }
    foreach (sl_rows("SELECT id, name, status, trigger_event FROM cdb_cs_rules WHERE trigger_event IN ('sla.warning','sla.breached','csat.poor','schedule.no_reply','handover.requested') ORDER BY sort_order, id LIMIT 6") as $r) {
        $d['esc'][] = ['id' => (int) $r->id, 't' => $r->name, 'on' => $r->status === 'published'];
    }
    $cats = sl_rows("SELECT category, COUNT(*) AS n FROM cdb_cs_tickets WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') GROUP BY category ORDER BY n DESC LIMIT 6");
    $cmax = $cats ? max(1, (int) $cats[0]->n) : 1;
    $d['cats'] = array_map(fn($c) => ['t' => cdp_csCategories()[$c->category] ?? $c->category, 'n' => (int) $c->n, 'w' => 'width:' . sl_pct($c->n, $cmax) . '%'], $cats);
    $d['kb'] = array_map(fn($a) => ['id' => $a['id'], 't' => $a['title'], 'v' => $a['helpful'] + $a['not_helpful']], array_slice(cdp_csKbList(false), 0, 5));
    foreach (sl_rows("SELECT p.*, t.public_ref FROM cdb_cs_proposals p JOIN cdb_cs_tickets t ON t.id = p.ticket_id ORDER BY p.id DESC LIMIT 4") as $p) {
        $det = cdp_csDecode($p->details);
        $d['dis'][] = ['id' => (int) $p->ticket_id, 't' => (cdp_csProposalKinds()[$p->kind] ?? ucfirst($p->kind)) . ' · ' . $p->public_ref, 's' => mb_substr((string) ($det['summary'] ?? ''), 0, 70), 'st' => ucfirst($p->status), 'pc' => $p->status === 'approved' ? 'delivered' : ($p->status === 'rejected' ? 'overdue' : 'pending')];
    }
    $cov = [
        ['Helpdesk', 'Support inbox', 'CsInbox'], ['Ticket management', 'Support inbox', 'CsInbox'], ['Automatic ticket numbers', 'Support ticket', 'CsInbox'], ['Tickets linked to shipments', 'Support ticket', 'CsInbox'],
        ['WhatsApp to ticket', 'Channels and teams', 'CsChannels'], ['E-mail to ticket', 'Channels and teams', 'CsChannels'], ['Live chat', 'Channels and teams', 'CsChannels'], ['AI bot hands over to staff', 'AI for customers', 'CsAI'],
        ['Agent assignment', 'Ticket rules', 'CsRules'], ['Priority routing', 'Ticket rules', 'CsRules'], ['SLA timers', 'SLA board above', '#'], ['Automatic escalation', 'Escalation rules above', '#'],
        ['Customer history', 'Customer in support', 'Customers'], ['Complaint categories', 'Categories above', '#'], ['Refunds and claims', 'Insurance and claims', 'InsuranceClaims'], ['Satisfaction ratings', 'Support reports', 'CsReports'],
        ['Customer notes', 'Customer in support', 'Customers'], ['Support dashboard', 'Support reports', 'CsReports'], ['Help centre', 'Playbook', 'CsPlaybook'], ['Disputes', 'Approvals above', '#'],
    ];
    $d['cov'] = array_map(fn($c, $i) => ['n' => $i + 1, 't' => $c[0], 'where' => $c[1], 'href' => $c[2] === '#' ? '#' : sl_route($c[2]), 'st' => $c[2] === '#' ? 'Here' : 'Built', 'pc' => $c[2] === '#' ? 'onway' : 'delivered', 'here' => $c[2] === '#'], $cov, array_keys($cov));
    return $d;
}
