<?php
/**
 * Rider app writes (wallet, shifts, safety, account). Tables: migrations/2026_11_27_rider_app.sql.
 *
 * The signed-in rider, always on their own rows ($_SESSION['userid']):
 *   key=nav_app|offer_sounds value=…        one preference (switches and the navigation picker post {key, value})
 *   op=payout_account                        method, provider, name, number
 *   op=emergency_contact                     name, phone, relation (empty name + phone removes it)
 *   op=checklist                             item, value (today's "before you ride" list)
 *   op=payout_request                        amount, note   (one open request at a time, amount <= available)
 *   op=payout_cancel                         id            (only while still "requested")
 *   op=shift_book                            day, start_time, end_time, hub
 *   op=shift_cancel                          id
 * Staff with manage_rider_fees:
 *   op=payout_approve | payout_decline | payout_paid   id, note
 *   op=quest_save                            id (0 = new), title, target_trips, reward, starts_at, ends_at, active
 *   op=site_setting                          key=emergency_number, value
 * No operation moves money: "paid" records that staff paid the rider outside the system.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../helpers/rider_money.php';
require_login();
$core = new Core(); // system timezone and currency for dates and sl_money()
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
if (CDP_APP_MODE_DEMO === true) sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
require_once CDP_ROOT . '/views/sl/data/riders.php';

$me = (int) ($_SESSION['userid'] ?? 0);
if ($me <= 0) sl_json(['success' => false, 'message' => 'Sign in again'], 401);
$op = (string) ($_POST['op'] ?? '');
if ($op === '' && isset($_POST['key'])) $op = 'pref';
$db = new Conexion();
$now = date('Y-m-d H:i:s');
$need = function (string $table) {
    if (!sl_tableExists($table)) sl_json(['success' => false, 'message' => 'This is not set up yet. Ask the office to run the database upgrade.'], 409);
};
$text = fn(string $k, int $max) => mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($_POST[$k] ?? ''))), 0, $max);
$log = function (string $action, string $label, string $summary, int $entityId, string $verb = 'update') {
    if (function_exists('cdp_activityLog')) {
        cdp_activityLog(['module' => 'drivers', 'verb' => $verb, 'action' => $action, 'action_label' => $label, 'outcome' => 'ok', 'entity_type' => 'user', 'entity_id' => $entityId, 'summary' => $summary]);
    }
};

/* ---------- staff operations ---------- */
if (in_array($op, ['payout_approve', 'payout_decline', 'payout_paid', 'quest_save', 'site_setting'], true)) {
    require_permission('manage_rider_fees');
    if ($op === 'site_setting') {
        $need('cdb_rider_prefs');
        if ((string) ($_POST['key'] ?? '') !== 'emergency_number') sl_json(['success' => false, 'message' => 'Unknown setting'], 422);
        $v = preg_replace('/[^0-9+]/', '', (string) ($_POST['value'] ?? ''));
        if ($v !== '' && !preg_match('/^\+?\d{2,15}$/', $v)) sl_json(['success' => false, 'message' => 'Enter the emergency number in digits, for example 112', 'errors' => ['value' => 'Digits only']], 422);
        sl_riderPrefSet(0, 'emergency_number', $v);
        $log('rider.emergency_number', 'Rider app emergency number', $v !== '' ? "set the rider app emergency number to $v" : 'removed the rider app emergency number', 0);
        sl_json(['success' => true, 'message' => $v !== '' ? 'Emergency number saved' : 'Emergency number removed']);
    }
    if ($op === 'quest_save') {
        $need('cdb_rider_quests');
        $id = (int) ($_POST['id'] ?? 0);
        $title = $text('title', 120);
        $target = (int) ($_POST['target_trips'] ?? 0);
        $reward = round((float) str_replace(',', '.', (string) ($_POST['reward'] ?? 0)), 2);
        $s = strtotime((string) ($_POST['starts_at'] ?? ''));
        $e = strtotime((string) ($_POST['ends_at'] ?? ''));
        $errors = [];
        if ($title === '') $errors['title'] = 'Give the quest a name';
        if ($target < 1 || $target > 10000) $errors['target_trips'] = 'Between 1 and 10,000 trips';
        if ($reward < 0 || $reward > 1000000) $errors['reward'] = 'Enter the reward amount';
        if (!$s) $errors['starts_at'] = 'Pick a start date and time';
        if (!$e || ($s && $e <= $s)) $errors['ends_at'] = 'The end must be after the start';
        if ($errors) sl_json(['success' => false, 'message' => 'Check the quest details', 'errors' => $errors], 422);
        $active = (int) !empty($_POST['active']);
        if ($id > 0) {
            if (!sl_row('SELECT id FROM cdb_rider_quests WHERE id = :id', [':id' => $id])) sl_json(['success' => false, 'message' => 'Quest not found'], 404);
            $db->cdp_query('UPDATE cdb_rider_quests SET title = :t, target_trips = :n, reward = :r, starts_at = :s, ends_at = :e, active = :a WHERE id = :id');
            $db->bind(':id', $id);
        } else {
            $db->cdp_query('INSERT INTO cdb_rider_quests (title, target_trips, reward, starts_at, ends_at, active, created_by, created_at) VALUES (:t, :n, :r, :s, :e, :a, :u, :c)');
            $db->bind(':u', $me); $db->bind(':c', $now);
        }
        $db->bind(':t', $title); $db->bind(':n', $target); $db->bind(':r', $reward); $db->bind(':s', date('Y-m-d H:i:s', $s)); $db->bind(':e', date('Y-m-d H:i:s', $e)); $db->bind(':a', $active);
        if (!$db->cdp_execute()) sl_json(['success' => false, 'message' => 'Could not save the quest. Try again.'], 500);
        $log('rider.quest', 'Rider quest', ($id ? 'changed' : 'added') . " rider quest \"$title\": $target trips for " . sl_money($reward), 0);
        sl_json(['success' => true, 'message' => 'Quest saved', 'reload' => true]);
    }
    $need('cdb_rider_payout_requests');
    $row = sl_row('SELECT * FROM cdb_rider_payout_requests WHERE id = :id', [':id' => (int) ($_POST['id'] ?? 0)]);
    $riders = sl_rdRiders(); // agency-independent list of rider accounts
    if (!$row || !isset($riders[(int) $row->rider_id])) sl_json(['success' => false, 'message' => 'Payout request not found'], 404);
    $name = $riders[(int) $row->rider_id]->name;
    $note = $text('note', 255);
    $amount = sl_money($row->amount);
    if ($op === 'payout_approve') {
        if ($row->status !== 'requested') sl_json(['success' => false, 'message' => 'Only a waiting request can be approved'], 422);
        $db->cdp_query("UPDATE cdb_rider_payout_requests SET status = 'approved', staff_note = :n, decided_by = :u, decided_at = :t WHERE id = :id AND status = 'requested'");
    } elseif ($op === 'payout_decline') {
        if (!in_array($row->status, SL_RIDER_PAYOUT_OPEN, true)) sl_json(['success' => false, 'message' => 'This request is already closed'], 422);
        if ($note === '') sl_json(['success' => false, 'message' => 'Tell the rider why the request is declined', 'errors' => ['note' => 'Required']], 422);
        $db->cdp_query("UPDATE cdb_rider_payout_requests SET status = 'declined', staff_note = :n, decided_by = :u, decided_at = :t WHERE id = :id AND status IN ('requested','approved')");
    } else {
        if (!in_array($row->status, SL_RIDER_PAYOUT_OPEN, true)) sl_json(['success' => false, 'message' => 'This request is already closed'], 422);
        // Paying more than the rider is owed is refused: the amount was valid when asked, but a settlement may have been paid since.
        $owed = sl_rmBalance((int) $row->rider_id)['owed'];
        if ((float) $row->amount > $owed + 0.005) sl_json(['success' => false, 'message' => "$name is now owed " . sl_money($owed) . ', less than this request. Decline it and ask for a new one.'], 422);
        $db->cdp_query("UPDATE cdb_rider_payout_requests SET status = 'paid', staff_note = :n, decided_by = COALESCE(decided_by, :u), decided_at = COALESCE(decided_at, :t), paid_by = :u2, paid_at = :t2 WHERE id = :id AND status IN ('requested','approved')");
        $db->bind(':u2', $me); $db->bind(':t2', $now);
    }
    $db->bind(':n', $note !== '' ? $note : ($row->staff_note ?? null)); $db->bind(':u', $me); $db->bind(':t', $now); $db->bind(':id', (int) $row->id);
    if (!$db->cdp_execute()) sl_json(['success' => false, 'message' => 'Could not update the request. Try again.'], 500);
    $word = ['payout_approve' => 'approved', 'payout_decline' => 'declined', 'payout_paid' => 'marked paid'][$op];
    $log('rider.' . $op, 'Rider payout request', "$word payout request #" . (int) $row->id . " of $name ($amount)" . ($note !== '' ? ": $note" : ''), (int) $row->rider_id, $op === 'payout_paid' ? 'payment' : 'update');
    sl_json(['success' => true, 'message' => "Request $word", 'reload' => true]);
}

/* ---------- rider operations: the signed-in rider's own rows only ---------- */
if (!sl_userIs('driver') || !isset(sl_rdRiders()[$me])) sl_json(['success' => false, 'message' => 'Only a rider account can do this'], 403);

switch ($op) {
    case 'pref':
        $need('cdb_rider_prefs');
        $key = (string) ($_POST['key'] ?? '');
        $value = (string) ($_POST['value'] ?? '');
        $allowed = ['nav_app' => ['google', 'waze'], 'offer_sounds' => ['0', '1']];
        if (!isset($allowed[$key]) || !in_array($value, $allowed[$key], true)) sl_json(['success' => false, 'message' => 'That setting cannot be changed here'], 422);
        if (!sl_riderPrefSet($me, $key, $value)) sl_json(['success' => false, 'message' => 'Not saved. Try again.'], 500);
        sl_json(['success' => true, 'message' => $key === 'nav_app' ? 'Directions open in ' . ($value === 'waze' ? 'Waze' : 'Google Maps') : ($value === '1' ? 'Offer sounds on' : 'Offer sounds off')]);

    case 'payout_account':
        $need('cdb_rider_prefs');
        $method = (string) ($_POST['method'] ?? '');
        if (!in_array($method, ['momo', 'bank', 'cash'], true)) sl_json(['success' => false, 'message' => 'Choose how you want to be paid', 'errors' => ['method' => 'Choose one']], 422);
        $provider = $text('provider', 60); $name = $text('name', 100); $number = preg_replace('/[^0-9A-Za-z+ -]/', '', $text('number', 40));
        $errors = [];
        if ($method !== 'cash') {
            if ($provider === '') $errors['provider'] = $method === 'momo' ? 'Enter the network, for example MTN' : 'Enter the bank name';
            if ($name === '') $errors['name'] = 'Enter the name on the account';
            if (strlen(preg_replace('/\D/', '', $number)) < 6) $errors['number'] = $method === 'momo' ? 'Enter the mobile money number' : 'Enter the account number';
        } else {
            $provider = $name = $number = '';
        }
        if ($errors) sl_json(['success' => false, 'message' => 'Check the payout details', 'errors' => $errors], 422);
        foreach (['payout_method' => $method, 'payout_provider' => $provider, 'payout_name' => $name, 'payout_number' => $number] as $k => $v) sl_riderPrefSet($me, $k, $v);
        $log('rider.payout_account', 'Rider payout account', 'changed their payout account (' . $method . ($number !== '' ? ' ' . sl_rmMask($number) : '') . ')', $me);
        sl_json(['success' => true, 'message' => 'Payout account saved', 'reload' => true]);

    case 'emergency_contact':
        $need('cdb_rider_prefs');
        $name = $text('name', 80); $relation = $text('relation', 40); $phone = preg_replace('/[^0-9+ ]/', '', $text('phone', 30));
        if ($name === '' && $phone === '') {
            foreach (['ec_name', 'ec_phone', 'ec_relation'] as $k) sl_riderPrefSet($me, $k, '');
            sl_json(['success' => true, 'message' => 'Emergency contact removed', 'reload' => true]);
        }
        $errors = [];
        if ($name === '') $errors['name'] = 'Enter their name';
        if (strlen(preg_replace('/\D/', '', $phone)) < 7) $errors['phone'] = 'Enter a phone number with at least 7 digits';
        if ($errors) sl_json(['success' => false, 'message' => 'Check the contact details', 'errors' => $errors], 422);
        foreach (['ec_name' => $name, 'ec_phone' => $phone, 'ec_relation' => $relation] as $k => $v) sl_riderPrefSet($me, $k, $v);
        sl_json(['success' => true, 'message' => 'Emergency contact saved', 'reload' => true]);

    case 'checklist':
        $need('cdb_rider_prefs');
        $items = ['helmet', 'vest', 'bike', 'phone'];
        $item = (string) ($_POST['item'] ?? '');
        if (!in_array($item, $items, true)) sl_json(['success' => false, 'message' => 'Unknown check'], 422);
        $cur = json_decode((string) sl_riderPref($me, 'checklist', ''), true);
        $done = is_array($cur) && ($cur['d'] ?? '') === date('Y-m-d') ? array_values(array_intersect($items, (array) ($cur['items'] ?? []))) : [];
        $done = !empty($_POST['value']) ? array_values(array_unique(array_merge($done, [$item]))) : array_values(array_diff($done, [$item]));
        sl_riderPrefSet($me, 'checklist', json_encode(['d' => date('Y-m-d'), 'items' => $done]));
        sl_json(['success' => true, 'done' => count($done), 'total' => count($items)]);

    case 'payout_request':
        $need('cdb_rider_payout_requests');
        $raw = str_replace([',', ' '], ['.', ''], trim((string) ($_POST['amount'] ?? '')));
        if (!preg_match('/^\d{1,9}(\.\d{1,2})?$/', $raw) || (float) $raw <= 0) sl_json(['success' => false, 'message' => 'Enter the amount you want paid, for example 50.00', 'errors' => ['amount' => 'Enter an amount above zero']], 422);
        $amount = round((float) $raw, 2);
        if (sl_rmOpenRequest($me)) sl_json(['success' => false, 'message' => 'You already have a payout request waiting. Cancel it or wait for the office to answer.'], 422);
        $bal = sl_rmBalance($me);
        if ($amount > $bal['available'] + 0.005) sl_json(['success' => false, 'message' => 'You can ask for up to ' . sl_money($bal['available']) . ' right now', 'errors' => ['amount' => 'More than your balance']], 422);
        // The insert re-checks "no open request" in SQL, so two taps cannot create two requests.
        $db->cdp_query("INSERT INTO cdb_rider_payout_requests (rider_id, amount, note, status, created_at) SELECT :r, :a, :n, 'requested', :t FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_rider_payout_requests WHERE rider_id = :r2 AND status IN ('requested','approved'))");
        $db->bind(':r', $me); $db->bind(':r2', $me); $db->bind(':a', $amount); $db->bind(':n', $text('note', 255) ?: null); $db->bind(':t', $now);
        if (!$db->cdp_execute()) sl_json(['success' => false, 'message' => 'Could not send the request. Try again.'], 500);
        $log('rider.payout_request', 'Rider payout request', 'asked for a payout of ' . sl_money($amount), $me, 'create');
        sl_json(['success' => true, 'message' => 'Request sent. The office pays you and marks it paid here.', 'reload' => true]);

    case 'payout_cancel':
        $need('cdb_rider_payout_requests');
        $row = sl_row("SELECT id, status FROM cdb_rider_payout_requests WHERE id = :id AND rider_id = :r", [':id' => (int) ($_POST['id'] ?? 0), ':r' => $me]);
        if (!$row) sl_json(['success' => false, 'message' => 'Payout request not found'], 404);
        if ($row->status !== 'requested') sl_json(['success' => false, 'message' => $row->status === 'approved' ? 'The office already approved this request, so it cannot be cancelled here. Call the office.' : 'This request is already closed'], 422);
        $db->cdp_query("UPDATE cdb_rider_payout_requests SET status = 'cancelled', decided_at = :t WHERE id = :id AND rider_id = :r AND status = 'requested'");
        $db->bind(':t', $now); $db->bind(':id', (int) $row->id); $db->bind(':r', $me);
        if (!$db->cdp_execute()) sl_json(['success' => false, 'message' => 'Could not cancel. Try again.'], 500);
        sl_json(['success' => true, 'message' => 'Request cancelled', 'reload' => true]);

    case 'shift_book':
        $need('cdb_rider_shifts');
        $day = (string) ($_POST['day'] ?? ''); $start = (string) ($_POST['start_time'] ?? ''); $end = (string) ($_POST['end_time'] ?? '');
        $errors = [];
        $dts = preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) ? strtotime($day) : false;
        if (!$dts || date('Y-m-d', $dts) !== $day || $day < date('Y-m-d') || $day > date('Y-m-d', strtotime('+13 day'))) sl_json(['success' => false, 'message' => 'Pick a day in the next 14 days'], 422);
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start)) $errors['start_time'] = 'Pick a start time';
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end)) $errors['end_time'] = 'Pick an end time';
        if (!$errors) {
            $len = (strtotime("$day $end") - strtotime("$day $start")) / 60;
            if ($len <= 0) $errors['end_time'] = 'The end must be after the start, on the same day';
            elseif ($len < 30) $errors['end_time'] = 'A block is at least 30 minutes';
            elseif ($len > 12 * 60) $errors['end_time'] = 'A block is at most 12 hours';
            elseif (strtotime("$day $start") < time()) $errors['start_time'] = 'That time has passed. Pick a later start.';
        }
        if ($errors) sl_json(['success' => false, 'message' => reset($errors), 'errors' => $errors], 422);
        $hub = $text('hub', 120);
        require_once CDP_ROOT . '/views/sl/data/rider_money.php';
        if ($hub !== '' && !in_array($hub, sl_rmHubs(sl_rdRiders()[$me]), true)) sl_json(['success' => false, 'message' => 'Pick an area from the list', 'errors' => ['hub' => 'Not in the list']], 422);
        $clash = sl_row("SELECT start_time, end_time FROM cdb_rider_shifts WHERE rider_id = :r AND day = :d AND status = 'booked' AND start_time < :e AND end_time > :s", [':r' => $me, ':d' => $day, ':e' => $end . ':00', ':s' => $start . ':00']);
        if ($clash) sl_json(['success' => false, 'message' => 'This overlaps your block ' . substr((string) $clash->start_time, 0, 5) . ' – ' . substr((string) $clash->end_time, 0, 5) . '. Pick another time or cancel that block.', 'errors' => ['start_time' => 'Overlaps a booked block']], 422);
        $db->cdp_query("INSERT INTO cdb_rider_shifts (rider_id, day, start_time, end_time, hub, status, created_at) SELECT :r, :d, :s, :e, :h, 'booked', :t FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_rider_shifts WHERE rider_id = :r2 AND day = :d2 AND status = 'booked' AND start_time < :e2 AND end_time > :s2)");
        $db->bind(':r', $me); $db->bind(':d', $day); $db->bind(':s', $start . ':00'); $db->bind(':e', $end . ':00'); $db->bind(':h', $hub); $db->bind(':t', $now);
        $db->bind(':r2', $me); $db->bind(':d2', $day); $db->bind(':e2', $end . ':00'); $db->bind(':s2', $start . ':00');
        if (!$db->cdp_execute()) sl_json(['success' => false, 'message' => 'Could not book the block. Try again.'], 500);
        sl_json(['success' => true, 'message' => 'Booked ' . date('D j M', $dts) . " $start – $end", 'reload' => true]);

    case 'shift_cancel':
        $need('cdb_rider_shifts');
        $row = sl_row("SELECT id, day, start_time, end_time FROM cdb_rider_shifts WHERE id = :id AND rider_id = :r AND status = 'booked'", [':id' => (int) ($_POST['id'] ?? 0), ':r' => $me]);
        if (!$row) sl_json(['success' => false, 'message' => 'That block is not booked'], 404);
        if (strtotime($row->day . ' ' . $row->end_time) <= time()) sl_json(['success' => false, 'message' => 'That block has already ended'], 422);
        $db->cdp_query("UPDATE cdb_rider_shifts SET status = 'cancelled', cancelled_at = :t WHERE id = :id AND rider_id = :r AND status = 'booked'");
        $db->bind(':t', $now); $db->bind(':id', (int) $row->id); $db->bind(':r', $me);
        if (!$db->cdp_execute()) sl_json(['success' => false, 'message' => 'Could not cancel. Try again.'], 500);
        sl_json(['success' => true, 'message' => 'Block cancelled', 'reload' => true]);

    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
