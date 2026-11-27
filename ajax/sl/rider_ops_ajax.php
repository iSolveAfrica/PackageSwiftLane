<?php
/**
 * Rider app work-flow writes of the signed-in rider:
 *   op=online  value=1|0   go on or off duty (cdb_users.rider_status; dispatch cannot assign door deliveries to an offline rider)
 *   op=scan    code=...    is this tracking number on one of my open jobs? Ticks it on the pickup checklist.
 *   op=unscan  parcel=air:12   untick one parcel
 * Scanning is a checklist only: job and parcel statuses change through rider_ajax.php / delivery_ajax.php.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
$user = new User();
$core = new Core();
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
if (CDP_APP_MODE_DEMO === true) sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
require_once CDP_ROOT . '/views/sl/data/rider_ops.php';
$me = sl_userIs('driver') ? ro_me() : null;
if (!$me) sl_json(['success' => false, 'message' => 'Sign in as a rider to do this'], 403);
$id = (int) $me->id;
$op = (string) ($_POST['op'] ?? '');
$db = new Conexion();

if ($op === 'online') {
    $duty = ro_duty($me);
    if (!$duty['ready']) sl_json(['success' => false, 'message' => 'Going on and off duty needs the database upgrade. Ask the office.'], 409);
    if ($duty['suspended']) sl_json(['success' => false, 'message' => 'Your rider account is suspended. Contact the office.'], 403);
    $on = (string) ($_POST['value'] ?? '') === '1';
    if ($on !== $duty['online']) {
        $db->cdp_query('UPDATE cdb_users SET rider_status = :s WHERE id = :u');
        $db->bind(':s', $on ? 'online' : 'offline'); $db->bind(':u', $id);
        $db->cdp_execute();
        if (sl_tableExists('cdb_rider_status_log')) {
            $db->cdp_query('INSERT INTO cdb_rider_status_log (user_id, online, changed_at) VALUES (:u, :o, :t)');
            $db->bind(':u', $id); $db->bind(':o', $on ? 1 : 0); $db->bind(':t', date('Y-m-d H:i:s'));
            $db->cdp_execute();
        }
        cdp_activityLog(['module' => 'drivers', 'verb' => 'update', 'action' => 'rider.duty', 'action_label' => $on ? 'Rider online' : 'Rider offline', 'outcome' => 'ok', 'entity_type' => 'user', 'entity_id' => $id, 'entity_label' => $me->name, 'summary' => $me->name . ' went ' . ($on ? 'online' : 'offline')]);
    }
    sl_json(['success' => true, 'message' => $on ? 'You are online. Dispatch can send you jobs.' : 'You are offline. Jobs you already have stay with you.', 'reload' => true]);
}

if ($op === 'scan' || $op === 'unscan') {
    if (!sl_tableExists('cdb_rider_scans')) sl_json(['success' => false, 'message' => 'The pickup checklist needs the database upgrade. Ask the office.'], 409);
    $open = array_values(array_filter(ro_stops($me), fn($s) => in_array($s['state'], ['active', 'new'], true)));
    $found = null;
    if ($op === 'scan') {
        // A label may carry only the number, or a link that ends with it.
        $code = strtoupper(mb_substr(trim((string) ($_POST['code'] ?? '')), 0, 300));
        if (mb_strlen($code) < 3) sl_json(['success' => false, 'message' => 'Enter the tracking number printed on the label'], 422);
        foreach ($open as $s) foreach ($s['parcels'] as $p) {
            $trk = strtoupper($p['trk']);
            if ($trk !== '' && ($code === $trk || preg_match('/(?:^|[^A-Z0-9])' . preg_quote($trk, '/') . '(?:[^A-Z0-9]|$)/', $code))) { $found = [$s, $p]; break 2; }
        }
        if (!$found) sl_json(['success' => false, 'match' => false, 'message' => mb_substr($code, 0, 40) . ' is not on one of your open jobs. Do not take it; check with the desk.']);
        $db->cdp_query('INSERT INTO cdb_rider_scans (user_id, module, order_id, tracking, scanned_at) VALUES (:u, :m, :o, :t, :at) ON DUPLICATE KEY UPDATE scanned_at = VALUES(scanned_at)');
        $db->bind(':u', $id); $db->bind(':m', $found[1]['module']); $db->bind(':o', $found[1]['id']); $db->bind(':t', $found[1]['trk']); $db->bind(':at', date('Y-m-d H:i:s'));
        $db->cdp_execute();
    } else {
        if (!preg_match('/^(air|sea):([0-9]{1,12})$/', (string) ($_POST['parcel'] ?? ''), $m)) sl_json(['success' => false, 'message' => 'Parcel not found'], 422);
        foreach ($open as $s) foreach ($s['parcels'] as $p) if ($p['module'] === $m[1] && $p['id'] === (int) $m[2]) { $found = [$s, $p]; break 2; }
        if (!$found) sl_json(['success' => false, 'message' => 'Parcel not found'], 404);
        $db->cdp_query('DELETE FROM cdb_rider_scans WHERE user_id = :u AND module = :m AND order_id = :o');
        $db->bind(':u', $id); $db->bind(':m', $m[1]); $db->bind(':o', (int) $m[2]);
        $db->cdp_execute();
    }
    [$s, $p] = $found;
    $already = $op === 'scan' && $p['scanned'];
    $scanned = $s['scannedN'] + ($op === 'scan' ? ($p['scanned'] ? 0 : 1) : ($p['scanned'] ? -1 : 0));
    sl_json(['success' => true, 'match' => true, 'on' => $op === 'scan', 'key' => $s['key'], 'parcel' => $p['module'] . ':' . $p['id'], 'trk' => $p['trk'], 'scanned' => $scanned, 'total' => count($s['parcels']), 'jobReady' => $scanned >= count($s['parcels']),
        'message' => $op === 'unscan' ? $p['trk'] . ' unticked' : ($already ? $p['trk'] . ' was already ticked' : $p['trk'] . ' · ' . $s['name'] . ' · ticked')]);
}
sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
