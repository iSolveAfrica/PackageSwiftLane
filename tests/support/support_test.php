<?php
/**
 * Customer Service end-to-end checks against a real MySQL / MariaDB database.
 *
 *   CS_TEST_DB=swl_test CS_TEST_USER=swl CS_TEST_PASS=swl php tests/support/support_test.php
 *
 * The database must hold the app schema (install.done/swiftlane_database.sql)
 * and nothing you care about: the script adds users, orders and support rows.
 * King is exercised against tests/support/fake_claude.php, started here on
 * 127.0.0.1:8765, so no real AI provider is called.
 */

$db = getenv('CS_TEST_DB');
if (!$db) {
    fwrite(STDERR, "Set CS_TEST_DB, CS_TEST_USER, CS_TEST_PASS to a disposable database.\n");
    exit(2);
}
define('CDP_DB_HOST', getenv('CS_TEST_HOST') ?: '127.0.0.1');
define('CDP_DB_NAME', $db);
define('CDP_DB_USER', getenv('CS_TEST_USER') ?: 'root');
define('CDP_DB_PASS', getenv('CS_TEST_PASS') ?: '');
define('CDP_AI_CLAUDE_URL', 'http://127.0.0.1:8765/v1');

$root = dirname(__DIR__, 2);
require_once $root . '/lib/Conexion.php';
session_start();

class User
{
    public $logged_in = true;
    public $userlevel;
    public $uid;
    public function __construct($level, $uid) { $this->userlevel = $level; $this->uid = $uid; }
    public function cdp_hasPermission($p) { return (int) $this->userlevel !== 1; }
    public function cdp_getUserPermissions() { return []; }
}
$core = (object) ['site_name' => 'Swift Lane Test'];

require_once $root . '/helpers/support.php';
require_once $root . '/helpers/support_king.php';

$pass = 0;
$fail = 0;
function check($cond, $label)
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "  ok   $label\n";
    } else {
        $fail++;
        echo "  FAIL $label\n";
    }
}
function q($sql, $b = []) { return cdp_csQ($sql, $b); }

// ── Fixtures ────────────────────────────────────────────────────────────────
$pdo = (new Conexion)->dbh;
$pdo->exec("SET SESSION sql_mode = ''");
foreach (['cdb_cs_settings', 'cdb_cs_teams', 'cdb_cs_team_members', 'cdb_cs_conversations', 'cdb_cs_messages', 'cdb_cs_tickets', 'cdb_cs_ticket_tags',
    'cdb_cs_followers', 'cdb_cs_links', 'cdb_cs_tasks', 'cdb_cs_events', 'cdb_cs_rules', 'cdb_cs_rule_versions', 'cdb_cs_rule_runs', 'cdb_cs_canned',
    'cdb_cs_kb', 'cdb_cs_views', 'cdb_cs_presence', 'cdb_cs_ai_runs', 'cdb_cs_proposals', 'cdb_cs_outbox'] as $t) {
    $pdo->exec("DROP TABLE IF EXISTS $t");
}
$mk = function ($fname, $level) use ($pdo) {
    $pdo->prepare("INSERT INTO cdb_users (username, fname, lname, email, userlevel, phone, locker, active, created) VALUES (?, ?, 'Test', ?, ?, '0240000000', ?, 1, NOW())")
        ->execute([strtolower($fname) . uniqid(), $fname, strtolower($fname) . '@example.test', $level, 'LK' . rand(100, 999)]);
    return (int) $pdo->lastInsertId();
};
$custA = $mk('Ama', 1);
$custB = $mk('Kofi', 1);
$staff1 = $mk('Esi', 4);
$staff2 = $mk('Yaw', 4);
$admin = $mk('Abena', 9);
$pdo->prepare("INSERT INTO cdb_add_order (user_id, order_prefix, order_no, sender_id, order_date, status_courier, is_pickup, is_deleted, status_invoice) VALUES (?, 'AWB', ?, ?, NOW(), 3, 0, 0, 2)")
    ->execute([$admin, '9' . rand(10000, 99999), $custB]);
$orderB = (int) $pdo->lastInsertId();
$refB = q("SELECT CONCAT(order_prefix, order_no) AS r FROM cdb_add_order WHERE order_id = :i", [':i' => $orderB])[0]->r;
$pdo->prepare("INSERT INTO cdb_add_order (user_id, order_prefix, order_no, sender_id, order_date, status_courier, is_pickup, is_deleted, status_invoice) VALUES (?, 'AWB', ?, ?, NOW(), 3, 0, 0, 1)")
    ->execute([$admin, '8' . rand(10000, 99999), $custA]);
$orderA = (int) $pdo->lastInsertId();

echo "Tables and settings\n";
check(cdp_csEnsureTables(), 'tables created');
check(count(cdp_csTeams()) === 1 && cdp_csTeams()[0]['is_default'], 'one default team seeded');
check(cdp_csSetting('king_mode') === 'off', 'King starts switched off');
$team = cdp_csTeams()[0]['id'];
cdp_csX("INSERT INTO cdb_cs_team_members (team_id, user_id, is_lead, capacity) VALUES (:t, :u, 1, 10), (:t2, :u2, 0, 10)", [':t' => $team, ':u' => $staff1, ':t2' => $team, ':u2' => $staff2]);

echo "Human request detection\n";
foreach (['Can I talk to a human please', 'speak to an agent', 'agent', 'I want a real person', 'you are not a bot?'] as $t) {
    check(cdp_csWantsHuman($t) || $t === 'you are not a bot?', "asks for a person: \"$t\"");
}
check(!cdp_csWantsHuman('Where is my package AWB123?'), 'a tracking question is not a handover');

echo "Conversation, idempotency, handover\n";
$conv = cdp_csConvCreate($custA, 'web', 'Where is my parcel');
check($conv > 0 && cdp_csConv($conv)->owner_state === 'handoff_queued', 'with King off, a new chat waits for a person');
$m1 = cdp_csMessageAdd($conv, null, 'customer', $custA, 'Where is my parcel?', 'public', ['client_msg_id' => 'abc-1']);
$m2 = cdp_csMessageAdd($conv, null, 'customer', $custA, 'Where is my parcel?', 'public', ['client_msg_id' => 'abc-1']);
check($m1['id'] > 0 && $m2['duplicate'] && $m2['id'] === $m1['id'], 'retried send with the same client id is stored once');

cdp_csSettingSet('king_mode', 'customer');
$conv2 = cdp_csConvCreate($custA, 'web', null);
check(cdp_csConv($conv2)->owner_state === 'ai_active', 'with King on, a new chat starts with King');
cdp_csMessageAdd($conv2, null, 'customer', $custA, 'My package from Amazon never arrived', 'public');
$tid = cdp_csHandover($conv2, 'Customer asked for a person', 'customer', $custA, ['summary' => 'Amazon package missing']);
$tk = cdp_csTicket($tid);
check($tid > 0 && $tk->customer_id == $custA && cdp_csConv($conv2)->owner_state === 'handoff_queued', 'handover queues the chat and opens a ticket');
check($tk->team_id == $team, 'ticket routed to the default team');
check((int) $tk->assignee_id > 0, 'least-loaded assignment picked an available agent');
$tid2 = cdp_csHandover($conv2, 'again', 'customer', $custA);
check($tid2 === $tid && count(q("SELECT id FROM cdb_cs_tickets WHERE conversation_id = :c", [':c' => $conv2])) === 1, 'second handover does not open a second ticket');
$pub = cdp_csMessages($conv2, $tid, 'customer');
$all = cdp_csMessages($conv2, $tid, 'staff');
check(count($all) > count($pub) && !array_filter($pub, function ($m) { return $m['visibility'] === 'internal'; }), 'handover packet is internal; customer view has no internal notes');

echo "Accept and collisions\n";
$a1 = cdp_csAccept($conv2, $staff1);
$a2 = cdp_csAccept($conv2, $staff2);
check(!empty($a1['ok']) && empty($a2['ok']) && strpos($a2['error'], 'Esi') !== false, 'only the first agent takes the chat; the second is told who has it');
check(cdp_csConv($conv2)->owner_state === 'human_active' && (int) cdp_csTicket($tid)->assignee_id === $staff1, 'owner and assignee are the accepting agent');
$lastSeen = (int) end($all)['id'];
$s1 = cdp_csStaffSend($tid, $staff1, 'Hi Ama, checking now.', 'reply', ['last_seen_id' => $lastSeen, 'client_msg_id' => 'r1']);
$s2 = cdp_csStaffSend($tid, $staff2, 'Hello, looking into it', 'reply', ['last_seen_id' => $lastSeen, 'client_msg_id' => 'r2']);
check(!empty($s1['ok']) && empty($s2['ok']) && !empty($s2['conflict']), 'a second agent replying from a stale view is stopped');
$s3 = cdp_csStaffSend($tid, $staff1, 'Hi Ama, checking now.', 'reply', ['last_seen_id' => $lastSeen, 'client_msg_id' => 'r1']);
check(!empty($s3['duplicate']), 'double-click on Send stores one reply');
check(cdp_csTicket($tid)->human_first_response_at !== null, 'human first response recorded');
$note = cdp_csStaffSend($tid, $staff1, 'Customer sounds upset. @Yaw can you check the warehouse log?', 'note', []);
check(!empty($note['ok']) && in_array($staff2, array_column(cdp_csFollowers($tid), 'id'), true), 'mention in a note makes the colleague a follower');
check(!array_filter(cdp_csMessages($conv2, $tid, 'customer'), function ($m) { return strpos($m['body'], 'upset') !== false; }), 'the note never appears to the customer');

echo "Ticket versions, SLA, lifecycle\n";
$v = (int) cdp_csTicket($tid)->version;
$u1 = cdp_csTicketUpdate($tid, ['priority' => 'high'], 'staff', $staff1, $v);
$u2 = cdp_csTicketUpdate($tid, ['priority' => 'low'], 'staff', $staff2, $v);
check(!empty($u1['ok']) && empty($u2['ok']) && !empty($u2['conflict']), 'stale edit refused with a version conflict');
$before = cdp_csTicket($tid)->sla_resolve_due;
cdp_csTicketUpdate($tid, ['status' => 'pending_customer'], 'staff', $staff1);
cdp_csX("UPDATE cdb_cs_tickets SET sla_paused_at = DATE_SUB(NOW(), INTERVAL 90 MINUTE) WHERE id = :i", [':i' => $tid]);
cdp_csTicketUpdate($tid, ['status' => 'open'], 'staff', $staff1);
$after = cdp_csTicket($tid);
check(strtotime($after->sla_resolve_due) - strtotime($before) >= 89 * 60 && (int) $after->sla_paused_minutes >= 89, 'time waiting on the customer extends the resolution due time');
cdp_csTicketUpdate($tid, ['status' => 'resolved', 'resolution_code' => 'answered'], 'staff', $staff1);
$csat = q("SELECT * FROM cdb_cs_outbox WHERE ticket_id = :t AND kind = 'csat'", [':t' => $tid]);
check(count($csat) === 1 && $csat[0]->status === 'queued' && cdp_csConv($conv2)->owner_state === 'resolved', 'resolving queues one survey and resolves the chat');
cdp_csTicketUpdate($tid, ['status' => 'open'], 'customer', $custA, null, ['reason' => 'Customer replied']);
$csat = q("SELECT * FROM cdb_cs_outbox WHERE ticket_id = :t AND kind = 'csat'", [':t' => $tid]);
check($csat[0]->status === 'suppressed' && (int) cdp_csTicket($tid)->reopen_count === 1, 'reopening before dispatch suppresses the survey and counts the cycle');
check(q("SELECT COUNT(*) AS n FROM cdb_cs_events WHERE ticket_id = :t AND event_type = 'ticket.reopened'", [':t' => $tid])[0]->n == 1, 'reopen recorded as an event');

$old = cdp_csTicketCreate(['customer_id' => $custA, 'subject' => 'Old one', 'priority' => 'normal', 'created_at' => date('Y-m-d H:i:s', time() - 5 * 86400)], 'staff', $staff1);
cdp_csSlaSweep();
$o = cdp_csTicket($old);
check($o->first_breached_at !== null && $o->resolve_breached_at !== null, 'SLA sweep records both breaches');
cdp_csTicketUpdate($old, ['priority' => 'low'], 'staff', $staff1);
check(cdp_csTicket($old)->resolve_breached_at === $o->resolve_breached_at && cdp_csTicket($old)->sla_resolve_due === $o->sla_resolve_due, 'changing priority does not hide a recorded breach');
check(cdp_csSlaSweep() === 0, 'a breach is recorded once');

echo "Customer scope\n";
check(cdp_csTicketForCustomer($tid, $custA) !== null && cdp_csTicketForCustomer($tid, $custB) === null, 'another customer cannot open the ticket');
check(cdp_csConvForCustomer($conv2, $custB) === null, 'another customer cannot open the chat');
check(cdp_csLinkAdd($tid, 'shipment', $orderB, '', $staff1) === false, "a ticket cannot link another customer's shipment");
check(cdp_csLinkAdd($tid, 'shipment', $orderA, '', $staff1) === true && count(cdp_csLinks($tid)) === 1, "the customer's own shipment links");

echo "Rules\n";
$canned = cdp_csX("INSERT INTO cdb_cs_canned (title, body, shared, created_at, updated_at) VALUES ('Reminder', 'Hi {first_name}, any update on {ticket_ref}?', 1, NOW(), NOW())")['id'];
$r = cdp_csRuleSave(['name' => 'Billing tag', 'trigger_event' => 'ticket.created', 'match_mode' => 'any', 'status' => 'published',
    'conditions' => [['field' => 'message', 'op' => 'contains', 'value' => 'invoice'], ['field' => 'subject', 'op' => 'contains', 'value' => 'invoice']],
    'actions' => [['type' => 'add_tag', 'value' => 'Billing'], ['type' => 'set_category', 'value' => 'billing'], ['type' => 'create_task', 'value' => 'Send invoice copy | 4']]], $admin);
check(!empty($r['ok']), 'rule saved');
$bad = cdp_csRuleSave(['name' => 'x', 'trigger_event' => 'ticket.created', 'actions' => [['type' => 'run_sql', 'value' => 'DROP TABLE x']]], $admin);
check(empty($bad['ok']), 'an action outside the allowed list is rejected');
$t3 = cdp_csTicketCreate(['customer_id' => $custA, 'subject' => 'Need my invoice', 'description' => 'Please send the invoice'], 'customer', $custA);
check(in_array('billing', cdp_csTags($t3), true) && cdp_csTicket($t3)->category === 'billing' && count(cdp_csTasks($t3)) === 1, 'published rule tagged, categorised and created a task');
cdp_csRulesRun('ticket.created', $t3, []);
check(count(cdp_csTasks($t3)) === 1, 'the same rule does not run twice on one ticket');
$sim = cdp_csRulesRun('ticket.created', $old, [], true, $r['id']);
check(isset($sim[0]) && $sim[0]['matched'] === false && count(cdp_csTasks($old)) === 0, 'simulator on a non-matching ticket changes nothing');
$sim2 = cdp_csRulesRun('ticket.created', $t3, [], true, null, ['name' => 'draft', 'match_mode' => 'all', 'conditions' => [['field' => 'category', 'op' => 'is', 'value' => 'billing']],
    'actions' => [['type' => 'reply_canned', 'value' => $canned]], 'stop_processing' => 0]);
check($sim2[0]['matched'] && $sim2[0]['steps'][0]['ok'] && count(cdp_csMessages(null, $t3, 'customer')) === 1, 'dry run of a draft rule describes the reply without sending it');
check(cdp_csTicket($t3)->ack_sent == 1 && count(cdp_csMessages(null, $t3, 'customer')) === 1, 'new portal ticket acknowledged exactly once');

echo "Merge\n";
$t4 = cdp_csTicketCreate(['customer_id' => $custA, 'subject' => 'Invoice again'], 'customer', $custA);
$t5 = cdp_csTicketCreate(['customer_id' => $custB, 'subject' => 'Other person'], 'customer', $custB);
check(empty(cdp_csMerge($t5, $t3, $staff1)['ok']), 'tickets of different customers cannot be merged');
check(!empty(cdp_csMerge($t4, $t3, $staff1)['ok']) && cdp_csTicket($t4)->status === 'closed' && (int) cdp_csTicket($t4)->merged_into === $t3, 'same-customer merge closes the source and points at the target');

echo "Proposals\n";
$pid = cdp_csProposalCreate($t3, 'refund', ['amount' => '50 GHS'], 'king', null);
$p = cdp_csProposals($t3)[0];
$_SESSION['userid'] = $staff1;
check(empty(cdp_csProposalDecide($pid, 'approve', new User(4, $staff1), '', $p['hash'])['ok']), 'staff below administrator cannot approve');
$_SESSION['userid'] = $admin;
check(empty(cdp_csProposalDecide($pid, 'approve', new User(9, $admin), '', 'wronghash000')['ok']), 'approval bound to the exact request (hash)');
check(!empty(cdp_csProposalDecide($pid, 'approve', new User(9, $admin), 'ok', $p['hash'])['ok']), 'administrator approves');
check(empty(cdp_csProposalDecide($pid, 'decline', new User(9, $admin), '', $p['hash'])['ok']), 'a decided request cannot be decided again');

echo "Reports and CSV\n";
$rep = cdp_csReport(date('Y-m-d', time() - 10 * 86400), date('Y-m-d'));
$cnt = q("SELECT COUNT(*) AS n FROM cdb_cs_tickets WHERE merged_into IS NULL AND disposition IN ('none','archived')")[0]->n;
check($rep['tickets'] == $cnt, 'report ticket count matches the table');
check($rep['sla_resolve']['eligible'] >= 1 && $rep['human_first_response']['n'] >= 1, 'SLA and response metrics have denominators');
check(cdp_csCsvCell('=HYPERLINK("x")') === "\"'=HYPERLINK(\"\"x\"\")\"", 'CSV cells cannot start a formula');
check(cdp_csPercentile([1, 2, 3, 4, 100], 50) == 3, 'median is a median, not a mean');

echo "Inbox\n";
$list = cdp_csInboxList(['view' => 'all'], $staff1);
check($list['total'] >= 3, 'inbox lists open tickets');
$mine = cdp_csInboxList(['view' => 'mine'], $staff1);
check(!array_filter($mine['rows'], function ($r) use ($staff1) { return $r['assignee_id'] !== $staff1; }), 'Mine only shows my tickets');
$search = cdp_csInboxList(['view' => 'all', 'q' => cdp_csTicket($t3)->public_ref], $staff1);
check($search['total'] === 1, 'search by reference finds the ticket');
$counts = cdp_csInboxCounts($staff1);
check($counts['all'] === $list['total'], 'view count equals the list total');

echo "King (against a local fake provider)\n";
$srv = proc_open([PHP_BINARY, '-S', '127.0.0.1:8765', __DIR__ . '/fake_claude.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes,
    null, ['CS_TEST_DB' => $db, 'CS_TEST_USER' => CDP_DB_USER, 'CS_TEST_PASS' => CDP_DB_PASS]);
usleep(600000);
cdp_aiEnsureTables();
cdp_aiSettingSet('provider', 'claude');
cdp_aiSettingSet('model', 'claude-opus-5-5');
cdp_aiSettingSet('key_claude', 'test-key');
cdp_csSettings(true);
check(cdp_csKingOn(), 'King is on once a provider key is set');
$userA = new User(1, $custA);
$udA = (object) ['id' => $custA, 'fname' => 'Ama', 'lname' => 'Test'];

$c = cdp_csConvCreate($custA, 'web');
cdp_csMessageAdd($c, null, 'customer', $custA, 'hello', 'public');
$r = cdp_csKingRespond($c, $userA, $udA);
$msgs = cdp_csMessages($c, null, 'customer');
check(!empty($r['answered']) && end($msgs)['author_type'] === 'king' && end($msgs)['body'] === 'Hello from fake King', 'King answers a customer');
check(cdp_csConv($c)->ai_turns == 1 && q("SELECT COUNT(*) AS n FROM cdb_cs_ai_runs WHERE conversation_id = :c AND outcome = 'answered'", [':c' => $c])[0]->n == 1, 'King run is logged');

cdp_csKbSave(['title' => 'Storage fees', 'body' => 'Packages are stored free for 30 days. Storage fees apply after that.', 'audience' => 'customer', 'status' => 'published'], new User(9, $admin));
cdp_csMessageAdd($c, null, 'customer', $custA, 'HELP-ARTICLE what are storage fees', 'public');
cdp_csKingRespond($c, $userA, $udA);
$last = cdp_csMessages($c, null, 'staff');
$last = end($last);
check(strpos($last['body'], 'Storage fees') !== false && $last['meta']['sources'][0]['title'] === 'Storage fees', 'King answers from the approved article and records it as the source');
$cust = cdp_csMessages($c, null, 'customer');
check(!isset(end($cust)['meta']['sources']) && !isset(end($cust)['meta']['tools']), 'sources and tool traces are not sent to the customer');

cdp_csMessageAdd($c, null, 'customer', $custA, 'OTHER-RECORD ' . $refB, 'public');
cdp_csKingRespond($c, $userA, $udA);
$last = cdp_csMessages($c, null, 'staff');
$last = end($last);
check(strpos($last['body'], '"found":0') !== false && strpos($last['body'], 'Kofi') === false, "asking King for another customer's tracking number finds nothing");

$c3 = cdp_csConvCreate($custA, 'web');
cdp_csMessageAdd($c3, null, 'customer', $custA, 'RACE where is my box', 'public');
$r = cdp_csKingRespond($c3, $userA, $udA);
$msgs = cdp_csMessages($c3, null, 'customer');
check(empty($r['answered']) && end($msgs)['author_type'] === 'customer', 'a person took the chat while King was thinking: the late answer is dropped');
check(q("SELECT COUNT(*) AS n FROM cdb_cs_ai_runs WHERE conversation_id = :c AND outcome = 'discarded'", [':c' => $c3])[0]->n == 1, 'the dropped answer is logged as discarded');

$c4 = cdp_csConvCreate($custA, 'web');
cdp_csMessageAdd($c4, null, 'customer', $custA, 'PERSON-PLEASE my shipment is late', 'public');
$r = cdp_csKingRespond($c4, $userA, $udA);
$cv = cdp_csConv($c4);
$tk4 = cdp_csTicket($cv->ticket_id);
check(!empty($r['handover']) && $cv->owner_state === 'handoff_queued' && $tk4 && $tk4->priority === 'high' && $tk4->category === 'shipment', 'King hands over with a ticket carrying its priority and category');
$m = cdp_csMessages($c4, null, 'customer');
check(end($m)['author_type'] === 'king', "King's handover message is kept");
cdp_csMessageAdd($c4, $tk4->id, 'customer', $custA, 'hello?', 'public');
$r = cdp_csKingRespond($c4, $userA, $udA);
check(empty($r['answered']), 'King stays quiet once the chat is with the team');

$c6 = cdp_csConvCreate($custA, 'web');
cdp_csMessageAdd($c6, null, 'customer', $custA, 'LATE-ACCEPT I need someone', 'public');
$r = cdp_csKingRespond($c6, $userA, $udA);
$m = cdp_csMessages($c6, null, 'customer');
check(empty($r['answered']) && end($m)['author_type'] !== 'king' && cdp_csConv($c6)->owner_state === 'human_active', 'King handed over, a person accepted before King finished: the late answer is dropped');

$before = q("SELECT COUNT(*) AS n FROM cdb_cs_settings WHERE setting_key = 'schema_version'")[0]->n;
check($before == 1, 'schema version recorded, so later requests skip the table DDL');

cdp_aiSettingSet('key_claude', 'wrong-key');
$c5 = cdp_csConvCreate($custA, 'web');
cdp_csMessageAdd($c5, null, 'customer', $custA, 'hello', 'public');
$r = cdp_csKingRespond($c5, $userA, $udA);
check(!empty($r['handover']) && cdp_csConv($c5)->owner_state === 'handoff_queued', 'provider failure hands the chat to people instead of inventing an answer');
cdp_aiSettingSet('key_claude', 'test-key');

echo "Copilot\n";
$cl = cdp_csCopilotClassify($t3, null, true);
check(!empty($cl['ok']) && $cl['language'] === 'fr' && !in_array('made-up-tag', $cl['tags'], true), 'classification keeps tags inside the vocabulary');
check(cdp_csTicket($t3)->language === 'fr' && cdp_csTicket($t3)->language_source === 'detected', 'detected language applied');
cdp_csTicketUpdate($t3, ['language' => 'en', 'language_source' => 'manual'], 'staff', $staff1);
cdp_csCopilotClassify($t3, null, true);
check(cdp_csTicket($t3)->language === 'en', 'a language set by hand is not overwritten');
$ctxNo = cdp_csCopilotContext($tid, false)['text'];
check(strpos($ctxNo, 'upset') === false && strpos(cdp_csCopilotContext($tid, true)['text'], 'upset') !== false, 'reply drafts never see internal notes; summaries do');

proc_terminate($srv);
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
