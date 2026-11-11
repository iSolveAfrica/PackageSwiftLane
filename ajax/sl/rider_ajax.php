<?php
/**
 * Rider writes: assign / unassign a job (dispatch board), mark a pickup collected
 * or a delivery failed (rider app), and record rider settlements.
 * Deliveries with photo + receiver go through the legacy endpoint
 * ajax/courier/add_courier_delivered_ajax.php (pickup-code gate, notifications).
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../helpers/querys.php';
require_once CDP_ROOT . '/views/sl/data/riders.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
if (CDP_APP_MODE_DEMO === true) sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
$op = (string) ($_POST['op'] ?? '');
$db = new Conexion();
$me = (int) $_SESSION['userid'];

/** Load a job inside the agency scope. */
$job = function (int $id) {
    $j = sl_row('SELECT o.order_id, o.order_prefix, o.order_no, o.driver_id, o.status_courier, o.is_pickup, o.sender_id FROM cdb_add_order o WHERE o.order_id = :id AND o.is_deleted = 0' . sl_rdScope(), [':id' => $id]);
    if (!$j) sl_json(['success' => false, 'message' => 'Package not found'], 404);
    return $j;
};
$track = function ($j, int $status, string $comment) use ($db, $me) {
    $db->cdp_query('INSERT INTO cdb_courier_track (order_id, order_track, comments, t_date, status_courier, user_id) VALUES (:o, :t, :c, NOW(), :s, :u)');
    $db->bind(':o', $j->order_id); $db->bind(':t', $j->order_prefix . $j->order_no); $db->bind(':c', $comment); $db->bind(':s', $status); $db->bind(':u', $me);
    $db->cdp_execute();
};

switch ($op) {
    case 'assign':
    case 'unassign':
        require_permission(['dispatch_riders', 'assign_drivers', 'assign_driver_pickup']);
        $j = $job((int) ($_POST['id'] ?? 0));
        if (sl_tableExists('cdb_delivery_claims') && sl_scalar("SELECT COUNT(*) FROM cdb_delivery_claims WHERE module='air' AND order_id=:i", [':i'=>(int)$j->order_id])) sl_json(['success'=>false,'message'=>'Use Customer delivery requests to manage this parcel'],409);
        $trk = $j->order_prefix . $j->order_no;
        if ($op === 'assign') {
            $rid = (int) ($_POST['driver_id'] ?? 0);
            $riders = sl_rdRiders(true);
            if (!isset($riders[$rid])) sl_json(['success' => false, 'message' => 'Pick an active rider'], 422);
            cdp_updateDriverCourier(['id_shipment' => $j->order_id, 'driver_id' => $rid]);
            if (sl_columnExists('cdb_add_order', 'assigned_at')) { $db->cdp_query('UPDATE cdb_add_order SET assigned_at = NOW() WHERE order_id = :id'); $db->bind(':id', $j->order_id); $db->cdp_execute(); }
            if (function_exists('cdp_insertNotification')) {
                $nid = cdp_insertNotification(['user_id' => $me, 'order_id' => $j->order_id, 'notification_description' => "Package $trk assigned to you", 'shipping_type' => $j->is_pickup ? '3' : '1', 'notification_date' => date('Y-m-d H:i:s')]);
                if ($nid && function_exists('cdp_insertNotificationsUsers')) cdp_insertNotificationsUsers($nid, $rid);
            }
            cdp_activityLog(['module' => 'drivers', 'verb' => 'assign', 'action' => 'dispatch.assign', 'action_label' => 'Assigned rider', 'outcome' => 'ok', 'entity_type' => $j->is_pickup ? 'pickup' : 'shipment', 'entity_id' => $j->order_id, 'entity_label' => $trk, 'summary' => "assigned $trk to " . $riders[$rid]->name, 'meta' => ['driver_id' => $rid, 'from' => (int) $j->driver_id]]);
            sl_json(['success' => true, 'message' => "$trk assigned to " . $riders[$rid]->name]);
        }
        $db->cdp_query('UPDATE cdb_add_order SET driver_id = NULL WHERE order_id = :id'); $db->bind(':id', $j->order_id); $db->cdp_execute();
        cdp_activityLog(['module' => 'drivers', 'verb' => 'assign', 'action' => 'dispatch.unassign', 'action_label' => 'Unassigned rider', 'outcome' => 'ok', 'entity_type' => 'shipment', 'entity_id' => $j->order_id, 'entity_label' => $trk, 'summary' => "unassigned $trk from rider #" . (int) $j->driver_id]);
        sl_json(['success' => true, 'message' => "$trk is back on the board"]);
    case 'collected':
    case 'failed':
    case 'onroute':
        $j = $job((int) ($_POST['id'] ?? 0));
        $isOwn = (int) $j->driver_id === $me;
        if (!$isOwn) require_permission(['dispatch_riders', 'complete_pickup']);
        $trk = $j->order_prefix . $j->order_no;
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 250);
        if ($op === 'collected') {
            if (!(int) $j->is_pickup || !in_array((int) $j->status_courier, SL_RD_OPEN_PICKUP, true)) sl_json(['success' => false, 'message' => 'Only an open pickup can be marked collected'], 422);
            $status = 15;
            $receiver = mb_substr(trim((string) ($_POST['person'] ?? '')), 0, 150);
            if ($receiver === '') sl_json(['success' => false, 'message' => 'Who handed it over?', 'errors' => ['person' => 'Required']], 422);
            $photo = '';
            if (!empty($_FILES['photo']['name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
                if ($_FILES['photo']['size'] > 8 * 1048576 || !@getimagesize($_FILES['photo']['tmp_name'])) sl_json(['success' => false, 'message' => 'Photo must be an image under 8 MB'], 422);
                $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) $ext = 'jpg';
                $photo = 'driver_files/pod_' . (int) $j->order_id . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['photo']['tmp_name'], CDP_ROOT . '/' . $photo);
            }
            $db->cdp_query('UPDATE cdb_add_order SET status_courier = 15, person_receives = :p' . (sl_columnExists('cdb_add_order', 'pod_at') ? ', pod_receiver = :p2, pod_at = NOW()' . ($photo ? ', pod_photo = :ph' : '') : '') . ' WHERE order_id = :id');
            $db->bind(':p', $receiver); if (sl_columnExists('cdb_add_order', 'pod_at')) { $db->bind(':p2', $receiver); if ($photo) $db->bind(':ph', $photo); }
            $db->bind(':id', $j->order_id); $db->cdp_execute();
            $track($j, 15, 'Collected by rider from ' . $receiver . ($note ? ' · ' . $note : ''));
            cdp_activityLogStatus('pickups', 'pickup', $j->order_id, $trk, 15, cdp_activityStatusName(15));
            sl_json(['success' => true, 'message' => "$trk collected", 'reload' => true]);
        }
        if ($op === 'onroute') {
            $db->cdp_query('UPDATE cdb_add_order SET status_courier = 7 WHERE order_id = :id'); $db->bind(':id', $j->order_id); $db->cdp_execute();
            $track($j, 7, 'Out for delivery with rider');
            cdp_activityLogStatus('shipments', 'shipment', $j->order_id, $trk, 7, cdp_activityStatusName(7));
            sl_json(['success' => true, 'message' => "$trk on route", 'reload' => true]);
        }
        // failed attempt: status stays, a track note is written
        $track($j, (int) $j->status_courier, 'Failed attempt: ' . ($note ?: 'customer not available'));
        cdp_activityLog(['module' => $j->is_pickup ? 'pickups' : 'shipments', 'verb' => 'status', 'action' => 'rider.failed', 'action_label' => 'Failed attempt', 'outcome' => 'ok', 'entity_type' => 'shipment', 'entity_id' => $j->order_id, 'entity_label' => $trk, 'summary' => "failed attempt on $trk" . ($note ? ": $note" : '')]);
        sl_json(['success' => true, 'message' => 'Attempt recorded', 'reload' => true]);
    case 'settle':
        require_permission('manage_rider_fees');
        if (!sl_tableExists('cdb_rider_settlements')) sl_json(['success' => false, 'message' => 'Run the database upgrade first'], 409);
        $rid = (int) ($_POST['driver_id'] ?? 0);
        $riders = sl_rdRiders();
        if (!isset($riders[$rid])) sl_json(['success' => false, 'message' => 'Rider not found'], 404);
        $from = (string) ($_POST['from'] ?? ''); $to = (string) ($_POST['to'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $from > $to) sl_json(['success' => false, 'message' => 'Bad period'], 422);
        if (sl_scalar('SELECT COUNT(*) FROM cdb_rider_settlements WHERE driver_id = :d AND period_end >= :f', [':d' => $rid, ':f' => $from])) sl_json(['success' => false, 'message' => 'This period is already settled'], 422);
        $s = sl_rdStats($from, $to, $rid)[$rid];
        $pick = count(array_filter($s['jobs'], fn($x) => $x['st'][2] === 'done' && $x['j']->is_pickup));
        $db->cdp_query('INSERT INTO cdb_rider_settlements (driver_id, period_start, period_end, deliveries, pickups, gross, rider_share, status, paid_at, paid_by, note) VALUES (:d, :f, :t, :n, :p, :g, :r, :st, :pa, :pb, :no)');
        $paid = !empty($_POST['paid']);
        $db->bind(':d', $rid); $db->bind(':f', $from); $db->bind(':t', $to); $db->bind(':n', $s['done'] - $pick); $db->bind(':p', $pick); $db->bind(':g', round($s['fees'], 2)); $db->bind(':r', round($s['rid'], 2));
        $db->bind(':st', $paid ? 'paid' : 'pending'); $db->bind(':pa', $paid ? date('Y-m-d H:i:s') : null); $db->bind(':pb', $paid ? $me : null); $db->bind(':no', mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255));
        $db->cdp_execute();
        cdp_activityLog(['module' => 'drivers', 'verb' => 'payment', 'action' => 'rider.settle', 'action_label' => 'Rider settlement', 'outcome' => 'ok', 'entity_type' => 'user', 'entity_id' => $rid, 'entity_label' => $riders[$rid]->name, 'summary' => 'settled ' . $riders[$rid]->name . " $from – $to: " . sl_money($s['rid']) . ($paid ? ' (paid)' : '')]);
        sl_json(['success' => true, 'message' => 'Settlement recorded', 'reload' => true]);
    case 'settle_paid':
        require_permission('manage_rider_fees');
        $sid = (int) ($_POST['id'] ?? 0);
        $row = sl_row('SELECT * FROM cdb_rider_settlements WHERE id = :id', [':id' => $sid]);
        if (!$row) sl_json(['success' => false, 'message' => 'Settlement not found'], 404);
        $db->cdp_query("UPDATE cdb_rider_settlements SET status = 'paid', paid_at = NOW(), paid_by = :u WHERE id = :id"); $db->bind(':u', $me); $db->bind(':id', $sid); $db->cdp_execute();
        cdp_activityLog(['module' => 'drivers', 'verb' => 'payment', 'action' => 'rider.settle_paid', 'action_label' => 'Rider paid', 'outcome' => 'ok', 'entity_type' => 'user', 'entity_id' => (int) $row->driver_id, 'summary' => 'marked settlement #' . $sid . ' paid (' . sl_money($row->rider_share) . ')']);
        sl_json(['success' => true, 'message' => 'Marked paid']);
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
