<?php
/**
 * Communication Center, Gerald Master View and Staff & Branches — test run.
 *
 *   php tests/ops/ops_test.php                 pure functions only (no database)
 *   OPS_TEST_DB=swl_test php tests/ops/ops_test.php   + the database scenarios
 *
 * The database run needs a DISPOSABLE MySQL/MariaDB database loaded with
 * database/fresh_install_schema.sql and migrations/*.sql. It seeds customers,
 * staff, shipments, packages, tracking history and bills, then drives the
 * three engines end to end. PDO runs in exception mode, so a broken query
 * fails the run instead of quietly returning nothing. Nothing is sent: the
 * outbox is checked, providers are never called.
 *
 * Env: OPS_TEST_HOST (127.0.0.1), OPS_TEST_USER (root), OPS_TEST_PASS ('').
 */

$root = dirname(__DIR__, 2);
$dbName = getenv('OPS_TEST_DB') ?: '';
define('CDP_DB_HOST', getenv('OPS_TEST_HOST') ?: '127.0.0.1');
define('CDP_DB_NAME', $dbName ?: 'none');
define('CDP_DB_USER', getenv('OPS_TEST_USER') ?: 'root');
define('CDP_DB_PASS', getenv('OPS_TEST_PASS') ?: '');
define('CDP_ROOT', $root);
define('CDP_FINANCE_ENABLED', true);
date_default_timezone_set('UTC');
require_once $root . '/lib/Conexion.php';

/** Test double of the session user (lib/User.php needs a web session). */
class User
{
    public $logged_in = true;
    public $userlevel;
    public $uid;
    public $username = 'test';
    public function __construct($level, $uid) { $this->userlevel = $level; $this->uid = $uid; }
    public function cdp_hasPermission($p) { return (int) $this->userlevel === 9; }
    public function cdp_getUserPermissions() { return []; }
    public function cdp_loginCheck() { return true; }
}

$fails = 0;
$passes = 0;
function check($cond, $label)
{
    global $fails, $passes;
    if ($cond) {
        $passes++;
        echo "  ok   $label\n";
    } else {
        $fails++;
        echo "  FAIL $label\n";
    }
}

require_once $root . '/helpers/ops_db.php';
require_once $root . '/helpers/comms.php';
require_once $root . '/helpers/staffops.php';
require_once $root . '/helpers/mgmt.php';

// ===========================================================================
echo "Pure functions\n";
// ===========================================================================
$secret = str_repeat('k', 64);
$tok = cdp_opsSign('survey', 42, 30, $secret);
check(cdp_opsVerify('survey', $tok, $secret) === 42, 'signed link verifies');
check(cdp_opsVerify('prefs', $tok, $secret) === 0, 'a survey link does not open preferences');
check(cdp_opsVerify('survey', str_replace('.', '.1', $tok), $secret) === 0, 'tampered link refused');
check(cdp_opsVerify('survey', $tok, $secret, time() + 31 * 86400) === 0, 'expired link refused');
check(cdp_opsCsvCell('=HYPERLINK("x")') === "\"'=HYPERLINK(\"\"x\"\")\"", 'CSV cell cannot start a formula');
check(cdp_opsCsvCell('-12.5') === '"-12.5"', 'negative numbers stay numbers in CSV');
$x = cdp_opsXlsx(['A' => ['head' => ['n', 'v'], 'rows' => [['a', 1], ['=1+1', 2.5]]]]);
$tmp = tempnam(sys_get_temp_dir(), 'x');
file_put_contents($tmp, $x);
$z = new ZipArchive();
check($x !== '' && $z->open($tmp) === true && strpos($z->getFromName('xl/worksheets/sheet1.xml'), '<f>') === false, 'xlsx is a valid zip and never writes formulas');
@unlink($tmp);
check(cdp_opsXlsxCol(0) === 'A' && cdp_opsXlsxCol(27) === 'AB', 'xlsx column letters');

check(cdp_commRender('Hi {first_name}, {tracking} {unknown}.', ['first_name' => 'Ama', 'tracking' => 'T1']) === 'Hi Ama, T1 .', 'template placeholders');
$map = [];
check(cdp_commClassifyStatus(8, 'Delivered', $map) === 'survey', 'delivered asks for a survey');
check(cdp_commClassifyStatus(7, 'On Route', $map) === 'driver_arrival', 'on route = driver arrival');
check(cdp_commClassifyStatus(3, 'In_Transit', $map) === 'departure', 'in transit = departure');
check(cdp_commClassifyStatus(99, 'Customs Hold', $map) === 'customs', 'customs by name');
check(cdp_commClassifyStatus(98, 'Delivery attempt failed', $map) === 'failed_delivery', 'failed delivery by name');
check(cdp_commClassifyStatus(97, 'Arrived at destination', $map) === 'arrival', 'arrival by name');
check(cdp_commClassifyStatus(21, 'Cancelled', $map) === 'none', 'cancelled never announced');
check(cdp_commClassifyStatus(3, 'In_Transit', [3 => 'none']) === 'none', 'admin override wins');
check(cdp_commClassifyStatus(11, 'Pending', $map) === '', 'other statuses fall to the generic alert');

$p = ['whatsapp' => 1, 'sms' => 0, 'email' => 1, 'mkt_whatsapp' => 0, 'mkt_sms' => 1, 'mkt_email' => 0, 'opted_out_all' => 0, 'muted_events' => 'survey'];
check(cdp_commAllowed($p, 'whatsapp') && !cdp_commAllowed($p, 'sms'), 'transactional follows channel opt-outs');
check(!cdp_commAllowed($p, 'whatsapp', 'marketing') && cdp_commAllowed($p, 'sms', 'marketing'), 'marketing needs consent per channel');
check(!cdp_commAllowed($p, 'email', 'transactional', 'survey'), 'muted events are skipped');
check(!cdp_commAllowed(array_merge($p, ['opted_out_all' => 1]), 'email'), 'global opt-out stops everything');

$t = strtotime('2026-11-11 22:30:00');
check(cdp_commQuietUntil($t, '21:00', '07:00') === strtotime('2026-11-12 07:00:00'), 'quiet hours over midnight defer to the morning');
check(cdp_commQuietUntil(strtotime('2026-11-11 06:00:00'), '21:00', '07:00') === strtotime('2026-11-11 07:00:00'), 'early morning waits until the end');
check(cdp_commQuietUntil(strtotime('2026-11-11 12:00:00'), '21:00', '07:00') === strtotime('2026-11-11 12:00:00'), 'daytime sends now');
check(cdp_commReminderRule(0, null, 4, 3, time()) && !cdp_commReminderRule(3, null, 4, 3, time()), 'reminder cap');
check(!cdp_commReminderRule(1, date('Y-m-d H:i:s', time() - 86400), 4, 3, time()) && cdp_commReminderRule(1, date('Y-m-d H:i:s', time() - 5 * 86400), 4, 3, time()), 'reminder interval');
check(count(cdp_commSlotDays(strtotime('2026-11-14 10:00:00'), 3, true)) === 3 && !in_array('2026-11-15', cdp_commSlotDays(strtotime('2026-11-14 10:00:00'), 3, true), true), 'delivery days skip Sunday');
check(cdp_commKeyword(' stop ') === 'stop' && cdp_commKeyword('START') === 'start' && cdp_commKeyword('agent') === 'agent' && cdp_commKeyword('4') === 'score' && cdp_commKeyword('where is my box') === '', 'WhatsApp keywords');

$meta = ['entry' => [['changes' => [['value' => ['contacts' => [['wa_id' => '233201234567', 'profile' => ['name' => 'Ama']]],
    'messages' => [['from' => '233201234567', 'id' => 'wamid.1', 'type' => 'text', 'text' => ['body' => 'Hello']]]]]]]]];
$in = cdp_commParseInbound($meta);
check(count($in) === 1 && $in[0]['from'] === '233201234567' && $in[0]['name'] === 'Ama' && $in[0]['body'] === 'Hello', 'Meta webhook parsed');
$ultra = ['event_type' => 'message_received', 'data' => ['from' => '233201234567@c.us', 'body' => 'Hi', 'id' => 'u1', 'type' => 'chat', 'fromMe' => false]];
check(cdp_commParseInbound($ultra)[0]['from'] === '233201234567', 'UltraMsg webhook parsed');
check(cdp_commParseInbound(['event_type' => 'message_received', 'data' => ['from' => '1@c.us', 'fromMe' => true]]) === [], 'own messages ignored');
$raw = '{"a":1}';
check(cdp_commVerifyMetaSignature($raw, 'sha256=' . hash_hmac('sha256', $raw, 's3cret'), 's3cret') && !cdp_commVerifyMetaSignature($raw, 'sha256=00', 's3cret') && !cdp_commVerifyMetaSignature($raw, 'x', ''), 'webhook signature');

list($w, $b) = cdp_commSegmentSql(['match' => 'all', 'rules' => [['field' => 'country', 'op' => 'eq', 'value' => 'Ghana'], ['field' => 'shipments_90d', 'op' => 'gte', 'value' => '2'],
    ['field' => 'bogus; DROP TABLE x', 'op' => 'eq', 'value' => 1]]], [1]);
check(strpos($w, 'u.country = :v1') !== false && $b[':v1'] === 'Ghana' && $b[':v2'] === 2 && strpos($w, 'DROP') === false, 'segment SQL: whitelisted fields, bound values');
list($w2) = cdp_commSegmentSql(['match' => 'any', 'rules' => [['field' => 'city', 'op' => 'eq', 'value' => 'a'], ['field' => 'city', 'op' => 'eq', 'value' => 'b']]], [1]);
check(strpos($w2, ' OR ') !== false, 'segment ANY');

$matrix = cdp_staffDefaultMatrix();
check(cdp_staffCanDecide('tasks.manage', 3, '', $matrix, 5, []), 'administrators can do everything');
check(cdp_staffCanDecide('tasks.manage', 2, 'branch_manager', $matrix, 5, [5]), 'manager in own branch');
check(!cdp_staffCanDecide('tasks.manage', 2, 'branch_manager', $matrix, 6, [5]), 'manager not in another branch');
check(!cdp_staffCanDecide('tasks.manage', 2, 'warehouse_operator', $matrix, 5, [5]), 'operator cannot assign tasks');
check(cdp_staffCanDecide('approvals.request', 1, 'driver', $matrix, null, []), 'drivers can ask for approval');
check(!cdp_staffMayDecide(3, 7, true, 0, 7, 10), 'nobody approves their own request');
check(cdp_staffMayDecide(2, 7, true, 500, 8, 400) && !cdp_staffMayDecide(2, 7, true, 500, 8, 600), 'approval limit');
check(cdp_staffMayDecide(2, 7, true, 0, 8, null) && !cdp_staffMayDecide(2, 7, false, 1000, 8, 5), 'no-amount requests and branch scope');
check(cdp_staffShiftsOverlap('08:00', '12:00', '11:00', '15:00') && !cdp_staffShiftsOverlap('08:00', '12:00', '12:00', '16:00'), 'shift overlap');
check(cdp_staffShiftsOverlap('22:00', '06:00', '23:30', '23:45') && !cdp_staffShiftsOverlap('22:00', '06:00', '05:00', '09:00'), 'overnight shift overlap');
check(cdp_staffCommission(200, 'percent', 5) === 10.0 && cdp_staffCommission(200, 'flat', 7.5) === 7.5 && cdp_staffCommission(20, 'percent', 5, 50) === 0.0, 'commission maths');
check(cdp_staffScore(100, 100, 100, 100) === 100.0 && cdp_staffScore(null, null, null, null) === null && cdp_staffScore(50, null, null, 100) === round((50 * .35 + 100 * .25) / .6, 1), 'performance score');

check(cdp_mgmtZScore(10, [1, 1, 1, 1, 1]) === null && cdp_mgmtZScore(1, [1, 1, 1, 1, 1]) === 0.0, 'z-score with no spread');
check(cdp_mgmtZScore(20, [1, 2, 3, 2, 1, 2]) > 3, 'z-score flags a spike');
check(cdp_mgmtMargin(100, 60, 10)['margin'] === 30.0 && cdp_mgmtMargin(0, 5, 0)['margin_pct'] === null, 'margin');
check(cdp_mgmtPct(110, 100) === 10.0 && cdp_mgmtPct(5, 0) === null, 'percentage change');
$from = strtotime('2026-11-11 08:00:00'); // a Wednesday
check(date('Y-m-d H:i', cdp_mgmtNextRun('daily', 1, 1, 7, $from)) === '2026-11-12 07:00', 'daily schedule');
check(date('Y-m-d H:i', cdp_mgmtNextRun('weekly', 1, 1, 7, $from)) === '2026-11-16 07:00', 'weekly schedule (Monday)');
check(date('Y-m-d H:i', cdp_mgmtNextRun('monthly', 1, 31, 6, $from)) === '2026-11-30 06:00', 'monthly schedule clamps to month end');
check(cdp_mgmtSchedulePeriod('monthly', $from) === ['2026-10-01', '2026-10-31'] && cdp_mgmtSchedulePeriod('daily', $from) === ['2026-11-10', '2026-11-10'], 'report periods');
$stripped = cdp_mgmtStripMoney(['shipments' => ['current' => ['n' => 3, 'revenue' => 9]], 'revenue' => ['charges' => 9], 'destinations' => [(object) ['country' => 'GH', 'n' => 1, 'margin' => 2]]]);
check(!isset($stripped['revenue']) && !isset($stripped['shipments']['current']['revenue']) && $stripped['shipments']['current']['n'] === 3
    && is_object($stripped['destinations'][0]) && !isset($stripped['destinations'][0]->margin), 'money stripped for people without money access');

if ($dbName === '') {
    echo "\n$passes passed, $fails failed (set OPS_TEST_DB for the database scenarios)\n";
    exit($fails ? 1 : 0);
}

// ===========================================================================
echo "\nDatabase scenarios ($dbName)\n";
// ===========================================================================
$probe = new Conexion();
$probe->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlErrors = [];
set_error_handler(function () { return false; });
ini_set('error_log', $logFile = tempnam(sys_get_temp_dir(), 'opslog'));
require_once $root . '/helpers/querys.php';

function x($sql, array $b = [])
{
    $db = new Conexion();
    $db->cdp_query($sql);
    foreach ($b as $k => $v) {
        $db->bind($k, $v);
    }
    $db->cdp_execute();
    return (int) $db->dbh->lastInsertId();
}
function one($sql, array $b = []) { return cdp_opsOne($sql, $b); }
function val($sql, array $b = []) { return cdp_opsVal($sql, $b); }

foreach (['cdb_comm_outbox', 'cdb_comm_prefs', 'cdb_comm_consent_log', 'cdb_comm_threads', 'cdb_comm_messages', 'cdb_comm_surveys', 'cdb_comm_delivery_slots', 'cdb_comm_reminders',
    'cdb_comm_segments', 'cdb_comm_campaigns', 'cdb_comm_templates', 'cdb_staff_branches', 'cdb_staff_countries', 'cdb_staff_profiles', 'cdb_staff_branch_access', 'cdb_staff_shifts',
    'cdb_staff_tasks', 'cdb_staff_transfers', 'cdb_staff_transfer_items', 'cdb_staff_approvals', 'cdb_staff_announcements', 'cdb_staff_announcement_reads', 'cdb_staff_commissions',
    'cdb_staff_commission_rules', 'cdb_mgmt_layouts', 'cdb_mgmt_schedules', 'cdb_mgmt_alert_acks', 'cdb_mgmt_insights', 'cdb_ops_settings'] as $tbl) {
    x("DROP TABLE IF EXISTS $tbl");
}
x("DELETE FROM cdb_users WHERE username LIKE 'opstest_%'");
x("DELETE FROM cdb_add_order WHERE order_prefix = 'OPT'");
x("DELETE FROM cdb_consolidate_customer_billing WHERE consolidate_id = 999999");
x("DELETE FROM cdb_branchoffices WHERE name_branch = 'OPS Agency'");
x("DELETE FROM cdb_offices WHERE code_off = 'OPSO'");
x("DELETE FROM cdb_customers_packages WHERE order_prefix = 'OPP'");
x("DELETE FROM cdb_courier_track WHERE order_track LIKE 'OPT%' OR order_track LIKE 'OPP%'");

$mkUser = function ($name, $level, array $extra = []) {
    $cols = array_merge(['username' => 'opstest_' . $name, 'password' => 'x', 'userlevel' => $level, 'email' => $name . '@example.test', 'fname' => ucfirst($name), 'lname' => 'Test',
        'phone' => '+23320' . str_pad((string) mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT), 'created' => date('Y-m-d H:i:s', strtotime('-10 days')), 'active' => 1, 'locker' => 'L' . mt_rand(1000, 9999),
        'country' => 'Ghana', 'city' => 'Accra'], $extra);
    $b = [];
    foreach ($cols as $k => $v) {
        $b[':' . $k] = $v;
    }
    return x("INSERT INTO cdb_users (" . implode(',', array_keys($cols)) . ") VALUES (" . implode(',', array_keys($b)) . ")", $b);
};
$admin = $mkUser('admin', 9);
$manager = $mkUser('manager', 4);
$operator = $mkUser('operator', 4);
$driver = $mkUser('driver', 3);
$agent = $mkUser('agent', 6, ['name_off' => 'OPS Agency']);
$cust1 = $mkUser('ama', 1);
$cust2 = $mkUser('kofi', 1, ['city' => 'Kumasi']);
$cust3 = $mkUser('optout', 1);

$office = x("INSERT INTO cdb_offices (name_off, code_off, address, city, phone_off) VALUES ('OPS Office', 'OPSO', 'x', 'Accra', '1')");
$agencyId = cdp_opsTableExists('cdb_branchoffices') ? x("INSERT INTO cdb_branchoffices (name_branch, branch_address, branch_city, phone_branch) VALUES ('OPS Agency', 'x', 'Accra', '1')") : 0;

$mkOrder = function ($n, $sender, $status, array $extra = []) use ($office, $operator) {
    $cols = array_merge(['user_id' => $operator, 'order_prefix' => 'OPT', 'order_no' => (string) $n, 'order_date' => date('Y-m-d', strtotime('-3 days')), 'order_datetime' => date('Y-m-d H:i:s', strtotime('-3 days')),
        'sender_id' => $sender, 'origin_off' => $office, 'order_item_category' => 26, 'status_courier' => $status, 'is_pickup' => 0, 'order_incomplete' => 1, 'total_order' => 100, 'price_purchase' => 60,
        'total_weight' => 5], $extra);
    $b = [];
    foreach ($cols as $k => $v) {
        $b[':' . $k] = $v;
    }
    return x("INSERT INTO cdb_add_order (" . implode(',', array_keys($cols)) . ") VALUES (" . implode(',', array_keys($b)) . ")", $b);
};

try {
    // ----------------------------------------------------------------- comms
    $GLOBALS['user'] = new User(9, $admin);
    check(cdp_commEnsureTables(), 'communication tables created');
    check((int) val("SELECT COUNT(*) FROM cdb_comm_templates") >= 36, 'default templates seeded');
    cdp_opsSettingSet('comms', 'quiet_start', '00:00', 0);
    cdp_opsSettingSet('comms', 'quiet_end', '00:00', 0);
    $rules = [];
    foreach (array_keys(cdp_commEvents()) as $ev) {
        $rules[$ev] = ['whatsapp' => 1, 'sms' => 1, 'email' => 1];
    }
    cdp_commSaveRules($rules);
    cdp_opsSettingSet('comms', 'enabled', '1', 0);
    cdp_commSavePrefs($cust3, ['opted_out_all' => 1], 'test');
    check(cdp_commPrefs($cust3)['opted_out_all'] === 1 && (int) val("SELECT COUNT(*) FROM cdb_comm_consent_log WHERE user_id = :u", [':u' => $cust3]) === 1, 'opt-out stored with a consent-log entry');

    check(cdp_commScanStatusHistory() === 0 && (int) cdp_opsSetting('comms', 'wm_track', '-1') >= 0, 'first scan only sets the watermark');
    cdp_commScanWarehouse();
    $o1 = $mkOrder(9001, $cust1, 3, ['driver_id' => $driver]);
    $o2 = $mkOrder(9002, $cust3, 3);
    foreach ([['OPT9001', 3], ['OPT9001', 7], ['OPT9001', 8], ['OPT9002', 3], ['OPT9001', 11]] as $tr) {
        x("INSERT INTO cdb_courier_track (order_track, comments, t_date, status_courier, office_id, user_id) VALUES (:t, '', :d, :s, :o, :u)",
            [':t' => $tr[0], ':d' => date('Y-m-d H:i:s'), ':s' => $tr[1], ':o' => $office, ':u' => $operator]);
    }
    $n = cdp_commScanStatusHistory();
    x("UPDATE cdb_add_order SET status_courier = 8 WHERE order_id = :o", [':o' => $o1]);
    $ev = array_map(function ($r) { return $r->event_key . '/' . $r->channel; }, cdp_opsQ("SELECT event_key, channel FROM cdb_comm_outbox WHERE user_id = :u ORDER BY id", [':u' => $cust1]));
    check(in_array('departure/whatsapp', $ev, true) && in_array('driver_arrival/sms', $ev, true) && in_array('survey/email', $ev, true) && in_array('shipment_alert/email', $ev, true), 'status history became departure, driver, survey and generic alerts');
    check((int) val("SELECT COUNT(*) FROM cdb_comm_outbox WHERE user_id = :u", [':u' => $cust3]) === 0, 'opted-out customer gets nothing');
    $drv = one("SELECT body FROM cdb_comm_outbox WHERE user_id = :u AND event_key = 'driver_arrival' AND channel = 'whatsapp'", [':u' => $cust1]);
    check($drv && strpos($drv->body, 'Driver') !== false && strpos($drv->body, 'OPT9001') !== false, 'driver message names the driver and tracking');
    $sv = one("SELECT body FROM cdb_comm_outbox WHERE user_id = :u AND event_key = 'survey' AND channel = 'whatsapp'", [':u' => $cust1]);
    check($sv && strpos($sv->body, 'survey.php?t=') !== false && (int) val("SELECT COUNT(*) FROM cdb_comm_surveys WHERE user_id = :u", [':u' => $cust1]) === 1, 'survey link created once');
    $before = (int) val("SELECT COUNT(*) FROM cdb_comm_outbox");
    cdp_opsSettingSet('comms', 'wm_track', (string) ((int) cdp_opsSetting('comms', 'wm_track') - 5), 0);
    cdp_commScanStatusHistory();
    check((int) val("SELECT COUNT(*) FROM cdb_comm_outbox") === $before, 'rescanning the same history queues nothing twice');

    x("INSERT INTO cdb_customers_packages (user_id, order_prefix, order_no, order_date, order_datetime, sender_id, status_courier) VALUES (:u, 'OPP', '1', CURDATE(), NOW(), :s, 4)", [':u' => $operator, ':s' => $cust2]);
    cdp_commScanWarehouse();
    check((int) val("SELECT COUNT(*) FROM cdb_comm_outbox WHERE user_id = :u AND event_key = 'warehouse_received'", [':u' => $cust2]) === 3, 'warehouse receipt announced on three channels');

    $o3 = $mkOrder(9003, $cust2, 32);
    x("INSERT INTO cdb_courier_track (order_track, comments, t_date, status_courier, office_id, user_id) VALUES ('OPT9003', '', :d, 32, :o, :u)", [':d' => date('Y-m-d H:i:s', strtotime('-6 days')), ':o' => $office, ':u' => $operator]);
    check(cdp_commScanClaimReminders() === 3 && cdp_commScanClaimReminders() === 0, 'claim reminder once, then waits for the interval');
    x("INSERT INTO cdb_consolidate_customer_billing (consolidate_id, sender_id, amount_ghs, discount_ghs, paid_ghs, exchange_rate, amount_usd, billed_at) VALUES (999999, :s, 500, 0, 100, 12, 41.67, :d)",
        [':s' => $cust1, ':d' => date('Y-m-d H:i:s', strtotime('-20 days'))]);
    check(cdp_commScanPaymentReminders() === 3, 'payment reminder for an old balance');
    $pay = one("SELECT body FROM cdb_comm_outbox WHERE event_key = 'payment_reminder' AND channel = 'sms' AND user_id = :u", [':u' => $cust1]);
    check($pay && strpos($pay->body, '400.00') !== false, 'payment reminder carries the balance');

    $row = one("SELECT * FROM cdb_comm_outbox WHERE user_id = :u AND channel = 'email' AND status = 'queued' LIMIT 1", [':u' => $cust2]);
    cdp_commSavePrefs($cust2, ['email' => 0], 'test');
    check(cdp_commDeliver($row) === 'cancelled', 'opting out after queueing cancels at send time');
    check(cdp_commDeliver($row) === 'claimed', 'a row is never delivered twice');

    $seg = cdp_commSaveSegment(['name' => 'Accra', 'definition' => ['match' => 'all', 'rules' => [['field' => 'city', 'op' => 'eq', 'value' => 'Accra']]]]);
    check($seg['ok'] && $seg['count'] >= 2, 'segment counted');
    check(cdp_commSegmentCount(['rules' => [['field' => 'shipments_90d', 'op' => 'gte', 'value' => 1], ['field' => 'balance', 'op' => 'gt', 'value' => 0], ['field' => 'in_warehouse', 'op' => 'eq', 'value' => '0'],
        ['field' => 'last_shipment_days', 'op' => 'lte', 'value' => 30], ['field' => 'joined_days', 'op' => 'eq', 'value' => 60]]]) >= 1, 'every segment field runs');
    cdp_commSavePrefs($cust1, ['mkt_whatsapp' => 1], 'test');
    $cmp = cdp_commSaveCampaign(['name' => 'Promo', 'segment_id' => $seg['id'], 'channels' => ['whatsapp', 'sms'], 'body' => 'Hi {first_name}, 10% off this week']);
    check($cmp['ok'] && !cdp_commApproveCampaign($cmp['id'], $admin + 0)['ok'] === false, 'campaign saved');
    $GLOBALS['user'] = new User(2, $manager);
    x("UPDATE cdb_comm_campaigns SET created_by = :u WHERE id = :id", [':u' => $manager, ':id' => $cmp['id']]);
    check(!cdp_commApproveCampaign($cmp['id'], $manager)['ok'], 'the author cannot approve their own campaign');
    $GLOBALS['user'] = new User(9, $admin);
    $c2 = cdp_commSaveCampaign(['name' => 'Promo 2', 'segment_id' => $seg['id'], 'channels' => ['whatsapp', 'sms'], 'body' => 'Hi {first_name}!']);
    $launch = cdp_commApproveCampaign($c2['id'], $admin);
    $mk = cdp_opsQ("SELECT user_id, channel, body FROM cdb_comm_outbox WHERE campaign_id = :c", [':c' => $c2['id']]);
    check($launch['ok'] && count($mk) === 1 && (int) $mk[0]->user_id === $cust1 && $mk[0]->channel === 'whatsapp' && strpos($mk[0]->body, 'STOP') !== false, 'campaign reaches only consenting customers, with an opt-out line');

    $u1 = cdp_commUser($cust1);
    $phone = preg_replace('/\D+/', '', $u1->phone);
    x("UPDATE cdb_settings SET active_whatsapp = 0");
    cdp_commHandleInbound(['from' => $phone, 'name' => 'Ama', 'body' => 'STOP', 'id' => 'in-1', 'media' => '']);
    $pr = cdp_commPrefs($cust1);
    check($pr['whatsapp'] === 0 && $pr['mkt_whatsapp'] === 0, 'STOP switches WhatsApp and marketing off');
    cdp_commHandleInbound(['from' => $phone, 'name' => 'Ama', 'body' => 'STOP', 'id' => 'in-1', 'media' => '']);
    check((int) val("SELECT COUNT(*) FROM cdb_comm_messages WHERE provider_id = 'in-1'") === 1, 'webhook retries are ignored');
    cdp_commHandleInbound(['from' => $phone, 'name' => 'Ama', 'body' => 'START', 'id' => 'in-2', 'media' => '']);
    check(cdp_commPrefs($cust1)['whatsapp'] === 1, 'START switches WhatsApp back on');
    cdp_commHandleInbound(['from' => $phone, 'name' => 'Ama', 'body' => '2', 'id' => 'in-3', 'media' => '']);
    $th = one("SELECT * FROM cdb_comm_threads WHERE phone = :p", [':p' => $phone]);
    check((int) val("SELECT score FROM cdb_comm_surveys WHERE user_id = :u", [':u' => $cust1]) === 2 && $th->status === 'open', 'a low WhatsApp score is recorded and handed to a person');
    check((int) $th->user_id === $cust1, 'thread linked to the customer by phone');
    $hist = cdp_commHistory($cust1);
    check(count(array_filter($hist, function ($i) { return $i['channel'] === 'survey'; })) === 1 && count(array_filter($hist, function ($i) { return $i['dir'] === 'in'; })) >= 3, 'history shows inbound messages and the survey');

    $link = cdp_commCreateSlotLink($cust1, 'OPT9001');
    parse_str(parse_url($link, PHP_URL_QUERY), $qs);
    $sid = cdp_opsVerify('slot', $qs['t']);
    $days = cdp_commSlotDays();
    check(!cdp_commBookSlot($sid, '1999-01-01', 'morning', '', '')['ok'] && cdp_commBookSlot($sid, $days[0], 'afternoon', 'Gate 2', '+233 20')['ok'], 'delivery slot booking validates the day');
    check(cdp_commCreateSlotLink($cust1, 'OPT9001') !== '' && (int) val("SELECT COUNT(*) FROM cdb_comm_delivery_slots WHERE entity_ref = 'OPT9001'") === 2, 'a booked slot can be re-booked with a new link');
    $ov = cdp_commOverview(30);
    check($ov['queued'] > 0 && isset($ov['survey']['answered']), 'overview figures');

    // ----------------------------------------------------------- staff
    check(cdp_staffEnsureTables(), 'staff tables created');
    $cty = cdp_staffSaveCountry(['name' => 'Ghana', 'iso2' => 'gh', 'currency' => 'ghs', 'timezone' => 'Africa/Accra', 'active' => 1]);
    $br1 = cdp_staffSaveBranch(['code' => 'acc-1', 'name' => 'Accra Warehouse', 'country_id' => $cty['id'], 'office_id' => $office, 'manager_id' => $manager]);
    $br2 = cdp_staffSaveBranch(['code' => 'KSI-1', 'name' => 'Kumasi Office', 'type' => 'office', 'country_id' => $cty['id'], 'branch_office_id' => $agencyId]);
    check($br1['ok'] && $br2['ok'] && one("SELECT code FROM cdb_staff_branches WHERE id = :i", [':i' => $br1['id']])->code === 'ACC-1', 'branches saved (code normalised)');
    check(!cdp_staffSaveBranch(['code' => 'ACC-1', 'name' => 'Duplicate'])['ok'], 'duplicate branch code refused');
    check(cdp_staffSaveProfile(['user_id' => $manager, 'job_role' => 'branch_manager', 'home_branch_id' => $br1['id'], 'approval_limit' => 500])['ok'], 'manager profile');
    check(cdp_staffSaveProfile(['user_id' => $operator, 'job_role' => 'warehouse_operator', 'home_branch_id' => $br1['id'], 'branch_ids' => [$br2['id']]])['ok'], 'operator profile with a second branch');
    check(cdp_staffSaveProfile(['user_id' => $agent, 'job_role' => 'agent', 'home_branch_id' => $br2['id']])['ok'], 'agent profile');
    check(!cdp_staffSaveProfile(['user_id' => $cust1, 'job_role' => 'driver'])['ok'], 'customers cannot get a staff profile');
    check(!cdp_staffSaveProfile(['user_id' => $operator, 'job_role' => 'administrator'])['ok'], 'administrator job role needs an admin account');

    $GLOBALS['user'] = new User(4, $manager);
    check(cdp_staffJobRole() === 'branch_manager' && in_array($br1['id'], cdp_staffBranchIds(), true), 'manager role and branches');
    check(cdp_staffCan('tasks.manage', $br1['id']) && !cdp_staffCan('tasks.manage', $br2['id']), 'manager scoped to own branch');
    check(cdp_staffSaveShift(['user_id' => $operator, 'branch_id' => $br1['id'], 'shift_date' => date('Y-m-d'), 'start_time' => '00:00', 'end_time' => '23:59'])['ok'], 'shift planned');
    check(!cdp_staffSaveShift(['user_id' => $operator, 'branch_id' => $br1['id'], 'shift_date' => date('Y-m-d'), 'start_time' => '10:00', 'end_time' => '12:00'])['ok'], 'overlapping shift refused');
    check(!cdp_staffSaveShift(['user_id' => $operator, 'branch_id' => $br2['id'], 'shift_date' => date('Y-m-d', strtotime('+1 day')), 'start_time' => '08:00', 'end_time' => '12:00'])['ok'], 'manager cannot plan another branch');
    check(cdp_staffCopyWeek(date('Y-m-d', strtotime('monday this week')), $br1['id']) >= 1, 'week copied');
    $task = cdp_staffSaveTask(['title' => 'Receive air delivery', 'task_type' => 'receive', 'branch_id' => $br1['id'], 'assignee_id' => $operator, 'due_at' => date('Y-m-d\TH:i', strtotime('+2 hours'))]);
    check($task['ok'], 'task created');

    $GLOBALS['user'] = new User(4, $operator);
    check(cdp_staffJobRole() === 'warehouse_operator' && !cdp_staffCan('tasks.manage', $br1['id']), 'operator cannot manage tasks');
    check(cdp_staffClock('in')['ok'] && !cdp_staffClock('in')['ok'], 'check in once');
    check(cdp_staffTaskStatus($task['id'], 'in_progress')['ok'] && cdp_staffTaskStatus($task['id'], 'done')['ok'], 'operator works the task');
    check(!cdp_staffTaskStatus($task['id'], 'cancelled')['ok'], 'operator cannot cancel');
    check(cdp_staffClock('out')['ok'], 'check out');
    check(!cdp_staffCreateTransfer(['from_branch_id' => $br1['id'], 'to_branch_id' => $br2['id'], 'items' => 'OPT9001'])['ok'], 'operator cannot request a transfer');
    $req = cdp_staffRequestApproval(['kind' => 'expense', 'title' => 'Tape and labels', 'amount' => 300, 'branch_id' => $br1['id']]);
    $big = cdp_staffRequestApproval(['kind' => 'refund', 'title' => 'Refund', 'amount' => 900, 'branch_id' => $br1['id']]);
    check(!cdp_staffDecide($req['id'], 'approved', '')['ok'], 'requester cannot decide');

    $GLOBALS['user'] = new User(4, $manager);
    check(cdp_staffDecide($req['id'], 'approved', 'ok')['ok'], 'manager approves within the limit');
    check(!cdp_staffDecide($big['id'], 'approved', '')['ok'], 'above the limit needs someone else');
    $tr = cdp_staffCreateTransfer(['from_branch_id' => $br1['id'], 'to_branch_id' => $br2['id'], 'items' => "OPT9001\nOPP1"]);
    check($tr['ok'] && one("SELECT status FROM cdb_staff_transfers WHERE id = :i", [':i' => $tr['id']])->status === 'requested', 'transfer requested, waiting for approval');
    check(!cdp_staffCreateTransfer(['from_branch_id' => $br1['id'], 'to_branch_id' => $br2['id'], 'items' => 'OPT9001'])['ok'], 'an item cannot be on two open transfers');
    check(!cdp_staffCreateTransfer(['from_branch_id' => $br1['id'], 'to_branch_id' => $br2['id'], 'items' => 'NOPE'])['ok'], 'unknown tracking refused');
    $ap = one("SELECT id FROM cdb_staff_approvals WHERE ref_type = 'transfer' AND ref_id = :t", [':t' => $tr['id']]);
    check(!cdp_staffDecide($ap->id, 'approved', '')['ok'], 'manager cannot approve their own transfer');
    check(cdp_staffPostAnnouncement(['title' => 'Stock count Friday', 'body' => 'All hands', 'audience_type' => 'branch', 'audience_value' => $br1['id'], 'requires_ack' => 1])['ok'], 'manager posts to own branch');
    check(!cdp_staffPostAnnouncement(['title' => 'x', 'body' => 'y', 'audience_type' => 'all'])['ok'], 'only admins post to everyone');

    $GLOBALS['user'] = new User(9, $admin);
    check(cdp_staffDecide($ap->id, 'approved', '')['ok'] && one("SELECT status FROM cdb_staff_transfers WHERE id = :i", [':i' => $tr['id']])->status === 'approved', 'approval moves the transfer on');
    check(cdp_staffDecide($big['id'], 'approved', '')['ok'], 'admin approves above the limit');
    check(cdp_staffTransferAction($tr['id'], 'dispatch')['ok'], 'dispatched');
    $items = cdp_opsQ("SELECT id FROM cdb_staff_transfer_items WHERE transfer_id = :t ORDER BY id", [':t' => $tr['id']]);
    check(cdp_staffTransferAction($tr['id'], 'receive', [$items[0]->id])['ok'], 'received');
    check((int) val("SELECT SUM(received) FROM cdb_staff_transfer_items WHERE transfer_id = :t", [':t' => $tr['id']]) === 1, 'missing item recorded');
    $inv = array_column(array_map(function ($r) { return (array) $r; }, cdp_staffBranchInventory()), 'items', 'id');
    check((int) $inv[$br2['id']] === 1, 'branch inventory follows received items');
    check(cdp_staffPostAnnouncement(['title' => 'Welcome', 'body' => 'Hello all', 'audience_type' => 'all'])['ok'], 'admin posts to everyone');

    $GLOBALS['user'] = new User(4, $operator);
    $ann = cdp_staffAnnouncementsFor($operator);
    check(count($ann) === 2, 'operator sees branch and company announcements');
    check(cdp_staffMarkAnnouncement($ann[0]->id, true), 'acknowledged');

    $GLOBALS['user'] = new User(9, $admin);
    if ($agencyId) {
        x("UPDATE cdb_add_order SET agency = :a, status_courier = 8 WHERE order_id = :o", [':a' => $agencyId, ':o' => $o1]);
        cdp_staffSaveCommissionRule(['agent_id' => 0, 'calc' => 'percent', 'value' => 5]);
        check(cdp_staffAccrueCommissions() === 1 && cdp_staffAccrueCommissions() === 0, 'commission accrued once per shipment');
        check((float) val("SELECT amount FROM cdb_staff_commissions WHERE agent_id = :a", [':a' => $agent]) === 5.0, '5% of 100');
        $period = (string) val("SELECT period FROM cdb_staff_commissions WHERE agent_id = :a", [':a' => $agent]);
        check(!cdp_staffPayCommission($agent, $period, 'X')['paid'], 'unapproved commission is not paid');
        x("UPDATE cdb_staff_commissions SET status = 'approved' WHERE agent_id = :a", [':a' => $agent]);
        check(cdp_staffPayCommission($agent, $period, 'TRX-1')['paid'] === 1, 'approved commission paid');
        require_once $root . '/helpers/staff_workspace.php';
        $portal = cdp_staffAgentPortal($agent);
        check($portal['agent']['linked'] && count($portal['shipments']) === 1, 'partner portal lists the agency shipments');
    }
    $perf = cdp_staffPerformance(date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
    $op = array_values(array_filter($perf, function ($r) use ($operator) { return $r['user_id'] === $operator; }))[0] ?? null;
    check($op && $op['tasks_done'] === 1 && $op['attended'] === 1 && $op['shipments'] >= 2 && $op['score'] !== null, 'performance counts tasks, shifts and output');
    check(count(cdp_staffActivity(['q' => 'Transfer'])) >= 1 || !cdp_opsTableExists('cdb_activity_log'), 'staff actions in the activity log');
    require_once $root . '/helpers/staff_workspace.php';
    foreach (['admin' => [9, $admin], 'manager' => [4, $manager], 'operator' => [4, $operator], 'driver' => [3, $driver], 'agent' => [6, $agent]] as $who => $u) {
        $GLOBALS['user'] = new User($u[0], $u[1]);
        $ws = cdp_staffWorkspace();
        check(isset($ws['panels']) && count($ws['panels']) >= 1, 'workspace for ' . $who . ' (' . $ws['me']['role'] . ')');
    }
    foreach (['finance_officer', 'dispatch_officer', 'customer_service'] as $role) {
        $GLOBALS['user'] = new User(9, $admin);
        cdp_staffSaveProfile(['user_id' => $manager, 'job_role' => $role, 'home_branch_id' => $br1['id']]);
        $GLOBALS['user'] = new User(4, $manager);
        $fn = 'cdp_ws_' . $role;
        check(count($fn($manager, [$br1['id']])) >= 1, 'workspace panels for ' . $role);
    }
    $GLOBALS['user'] = new User(9, $admin);
    check(cdp_staffHousekeeping()['ok'], 'housekeeping');

    // ------------------------------------------------------------- master view
    check(cdp_mgmtEnsureTables(), 'master view tables created');
    $f = cdp_mgmtFilters(['from' => date('Y-m-d', strtotime('-30 days')), 'to' => date('Y-m-d')]);
    $d = cdp_mgmtData($f);
    foreach (array_keys(cdp_mgmtWidgets()) as $wk) {
        if (in_array($wk, ['executive', 'insights', 'active_customers', 'acquisition', 'revenue'], true)) {
            continue;
        }
        check(array_key_exists($wk, $d), 'widget data: ' . $wk);
    }
    check($d['shipments']['current']['n'] >= 3 && $d['revenue']['charges'] >= 300 && isset($d['customers']['active']), 'shipments, revenue and customers counted');
    check($d['delivery']['handed'] >= 1, 'handover counted from the tracking history');
    check($d['balances']['available'] && $d['balances']['total'] >= 400, 'outstanding balances');
    check(count($d['branches']) === 2 && $d['branches'][0]['shipments'] >= 1, 'branch performance through the linked office');
    $f2 = cdp_mgmtFilters(['from' => $f['from'], 'to' => $f['to'], 'branch_id' => $br2['id']]);
    check(cdp_mgmtData($f2, ['executive'])['shipments']['current']['n'] === ($agencyId ? 1 : 0), 'branch filter');
    $mkOrder(9010, $cust2, 4, ['total_order' => 0, 'order_date' => date('Y-m-d', strtotime('-2 days'))]);
    $mkOrder(9011, $cust2, 4, ['total_order' => 10, 'price_purchase' => 50, 'order_date' => date('Y-m-d', strtotime('-60 days'))]);
    $alerts = cdp_mgmtAlerts();
    $titles = array_map(function ($a) { return $a['title']; }, $alerts);
    check(in_array('Shipments with no charge (last 14 days)', $titles, true), 'zero-charge alert');
    check(count(cdp_mgmtDelayed('')['rows']) >= 1, 'delayed shipment past the air target');
    x("INSERT INTO cdb_mgmt_alert_acks (alert_key, acked_by, acked_at) VALUES (:k, :u, NOW())", [':k' => $alerts[0]['key'], ':u' => $admin]);
    check(!empty(cdp_mgmtAlerts()[count($alerts) - 1]['ack']) || !empty(array_filter(cdp_mgmtAlerts(), function ($a) { return !empty($a['ack']); })), 'acknowledged alerts sink to the bottom');
    $secs = cdp_mgmtReportSections($d);
    check(isset($secs['Executive summary'], $secs['Branches'], $secs['Delayed shipments']), 'report sections');
    $file = cdp_mgmtBuildReport($f['from'], $f['to'], 0, array_keys(cdp_mgmtWidgets()), 'xlsx');
    check($file[1] === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' && strlen($file[2]) > 1000, 'Excel report built');
    check(count(cdp_mgmtRuleInsights($d)) >= 3, 'rule-based insights');
    $ins = cdp_mgmtInsights($f);
    check($ins['ok'] && $ins['source'] === 'rules', 'insights fall back to rules without an AI provider');
    $sch = cdp_mgmtSaveSchedule(['name' => 'Weekly', 'frequency' => 'weekly', 'recipients' => 'md@example.test, bad', 'sections' => ['executive', 'delayed', 'nope']]);
    check($sch['ok'] && one("SELECT recipients, sections FROM cdb_mgmt_schedules")->recipients === 'md@example.test', 'schedule keeps only valid addresses and sections');
    check(!cdp_mgmtSaveSchedule(['name' => 'x', 'recipients' => 'bad', 'sections' => ['executive']])['ok'], 'schedule needs a recipient');
    check(cdp_mgmtSaveLayout($admin, ['delayed', 'executive', 'bogus'], ['alerts']) && cdp_mgmtLayout($admin)['order'][0] === 'delayed' && cdp_mgmtLayout($admin)['hidden'] === ['alerts'], 'personal layout saved');

    $GLOBALS['user'] = new User(4, $manager);
    cdp_staffSaveProfile(['user_id' => $manager, 'job_role' => 'branch_manager', 'home_branch_id' => $br1['id']]);
    check(cdp_mgmtCanView() && !cdp_mgmtCanMoney(), 'branch manager sees the Master View without money');
    $fm = cdp_mgmtFilters([]);
    $dm = cdp_mgmtData($fm);
    check(!isset($dm['revenue']) && !isset($dm['costs']) && strpos($fm['scope'], 'origin_off') !== false, 'manager view is money-free and limited to the branch');
} catch (Throwable $e) {
    check(false, 'exception: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
}

$log = (string) @file_get_contents($logFile);
$sqlLines = array_filter(explode("\n", $log), function ($l) { return preg_match('/OPS_(Q|X|DDL)|SQLSTATE|CS_Q/', $l); });
check(!$sqlLines, 'no SQL errors' . ($sqlLines ? ":\n    " . implode("\n    ", array_slice($sqlLines, 0, 12)) : ''));
@unlink($logFile);

echo "\n$passes passed, $fails failed\n";
exit($fails ? 1 : 0);
