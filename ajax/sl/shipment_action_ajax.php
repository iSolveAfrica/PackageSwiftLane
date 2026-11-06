<?php
/**
 * Air shipment actions for the Swift Lane pages (list, detail, tracking):
 * cancel, delete, assign driver (one or many), bulk status update, status
 * dispute, public-link privacy and per-user form defaults. Reuses helpers/querys.php.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/querys.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../helpers/handover.php';
require_once __DIR__ . '/../../views/sl/data/ops_common.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
if (CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
$core = new Core();
$op = (string) ($_POST['op'] ?? '');
$actor = (int) $_SESSION['userid'];
$db = new Conexion();

/** Shipments by id list, all inside the user's scope (403 otherwise). */
$loadMany = function (string $ids): array {
    $out = [];
    foreach (array_unique(array_filter(array_map('intval', explode(',', $ids)))) as $id) {
        $row = cdp_getCourier($id);
        if (!$row || (int) ($row->is_deleted ?? 0) === 1) {
            sl_json(['success' => false, 'message' => "Shipment #$id not found"], 404);
        }
        if (!sl_opsInScope($row)) {
            sl_json(['success' => false, 'message' => "Shipment #$id belongs to another agency"], 403);
        }
        $out[$id] = $row;
    }
    if (!$out) {
        sl_json(['success' => false, 'message' => 'Pick at least one shipment'], 422);
    }
    return $out;
};

switch ($op) {
    case 'cancel':
    case 'delete':
        require_permission($op === 'delete' ? 'delete_shipment' : 'cancel_shipment');
        $rows = $loadMany((string) ($_POST['id'] ?? ''));
        $n = 0;
        foreach ($rows as $id => $row) {
            if ($op === 'delete' && ((int) $row->is_consolidate === 1 || cdp_isHandedOver($row->status_courier))) {
                sl_json(['success' => false, 'message' => 'Consolidated or handed-over shipments cannot be deleted'], 409);
            }
            if ($op === 'cancel' && in_array((int) $row->status_courier, [21, 12], true)) {
                continue;
            }
            $track = $row->order_prefix . $row->order_no;
            if (!cdp_deleteCourier(['id' => $id, 'message' => mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 500) ?: null])) {
                continue;
            }
            if ($op === 'delete') {
                $db->cdp_query('UPDATE cdb_add_order SET is_deleted = 1 WHERE order_id = :id');
                $db->bind(':id', $id);
                $db->cdp_execute();
            }
            cdp_insertCourierShipmentTrack(['user_id' => $actor, 'order_id' => $id, 'order_track' => $track, 't_date' => date('Y-m-d H:i:s'), 'status_courier' => 21, 'comments' => $op === 'delete' ? 'Deleted' : 'Cancelled', 'office' => (int) $row->origin_off]);
            cdp_activityLog(['module' => 'shipments', 'verb' => $op === 'delete' ? 'delete' : 'update', 'action' => 'shipment.' . $op, 'entity_type' => 'shipment', 'entity_id' => $id, 'entity_label' => $track, 'summary' => ucfirst($op === 'delete' ? 'deleted' : 'cancelled') . " shipment $track"]);
            $n++;
        }
        sl_json(['success' => true, 'message' => $n . ($op === 'delete' ? ' shipment(s) deleted' : ' shipment(s) cancelled')]);
        break;

    case 'driver':
        require_permission('assign_drivers');
        $driverId = (int) ($_POST['driver_id'] ?? 0);
        $driver = $driverId > 0 ? sl_row('SELECT id, fname, lname FROM cdb_users WHERE id = :id AND userlevel = 3 AND active = 1', [':id' => $driverId]) : null;
        if (!$driver) {
            sl_json(['success' => false, 'message' => 'Choose a driver', 'errors' => ['driver_id' => 'Required']], 422);
        }
        $rows = $loadMany((string) ($_POST['ids'] ?? $_POST['id'] ?? ''));
        $n = 0;
        $employees = cdp_getUsersAdminEmployees();
        foreach ($rows as $id => $row) {
            if (cdp_isHandedOver($row->status_courier) || in_array((int) $row->status_courier, [21, 12], true)) {
                continue;
            }
            if (!cdp_updateDriverCourier(['id_shipment' => $id, 'driver_id' => $driverId])) {
                continue;
            }
            if (sl_columnExists('cdb_add_order', 'assigned_at')) {
                $db->cdp_query('UPDATE cdb_add_order SET assigned_at = NOW() WHERE order_id = :id');
                $db->bind(':id', $id);
                $db->cdp_execute();
            }
            $track = $row->order_prefix . $row->order_no;
            cdp_insertNotification(['user_id' => $actor, 'order_id' => $id, 'order_track' => $track, 'notification_description' => 'Driver assigned to shipment ' . $track, 'shipping_type' => '1', 'notification_date' => date('Y-m-d H:i:s')]);
            $nid = (int) $db->dbh->lastInsertId();
            if ($nid > 0) {
                cdp_insertNotificationsUsers($nid, $driverId);
                foreach ($employees as $u) {
                    cdp_insertNotificationsUsers($nid, $u->id);
                }
            }
            cdp_activityLog(['module' => 'shipments', 'verb' => 'update', 'action' => 'shipment.assign_driver', 'entity_type' => 'shipment', 'entity_id' => $id, 'entity_label' => $track, 'summary' => "assigned $track to " . trim($driver->fname . ' ' . $driver->lname), 'meta' => ['driver_id' => $driverId]]);
            $n++;
        }
        sl_json(['success' => true, 'message' => $n . ' shipment(s) assigned to ' . trim($driver->fname . ' ' . $driver->lname)]);
        break;

    case 'status_bulk':
        require_permission('track_shipment');
        $status = (int) ($_POST['status_courier'] ?? 0);
        $style = $status > 0 ? cdp_getStyleById($status) : null;
        if (!$style) {
            sl_json(['success' => false, 'message' => 'Choose a status', 'errors' => ['status_courier' => 'Required']], 422);
        }
        require_once __DIR__ . '/../../helpers/pickup_code.php';
        if (cdp_pickupCodeGatedStatus($status)) {
            sl_json(['success' => false, 'message' => 'Hand-over statuses need the pickup code: use the shipment page'], 409);
        }
        $rows = $loadMany((string) ($_POST['ids'] ?? ''));
        $comment = mb_substr(trim((string) ($_POST['comments'] ?? '')), 0, 250);
        $n = 0;
        foreach ($rows as $id => $row) {
            if (cdp_isHandedOver($row->status_courier)) {
                continue;
            }
            $track = $row->order_prefix . $row->order_no;
            $prev = cdp_activityStatusName((int) $row->status_courier);
            if (!updateCourierStatusFromTracking($status, $id)) {
                continue;
            }
            cdp_insertCourierShipmentTrack(['user_id' => $actor, 'order_id' => $id, 'order_track' => $track, 't_date' => date('Y-m-d H:i:s'), 'status_courier' => $status, 'comments' => $comment ?: 'Status updated', 'office' => (int) $row->origin_off]);
            cdp_insertCourierShipmentUserHistory(['user_id' => $actor, 'order_id' => $id, 'order_track' => $track, 'action' => 'Status: ' . str_replace('_', ' ', $style->mod_style), 'date_history' => date('Y-m-d H:i:s')]);
            cdp_activityLogStatus('shipments', 'shipment', $id, $track, $status, cdp_activityStatusName($status), $prev);
            $n++;
        }
        sl_json(['success' => true, 'message' => $n . ' shipment(s) set to ' . str_replace('_', ' ', $style->mod_style)]);
        break;

    case 'dispute':
        $rows = $loadMany((string) ($_POST['id'] ?? ''));
        $row = reset($rows);
        $id = (int) key($rows);
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 190);
        if ($reason === '') {
            sl_json(['success' => false, 'message' => 'Pick a reason', 'errors' => ['reason' => 'Required']], 422);
        }
        if (!sl_tableExists('cdb_tracking_disputes')) {
            sl_json(['success' => false, 'message' => 'Disputes need the database upgrade (migrations/2026_11_06_shipments.sql)'], 409);
        }
        $track = $row->order_prefix . $row->order_no;
        $db->cdp_query('INSERT INTO cdb_tracking_disputes (order_id, order_track, reason, note, raised_by, raised_at) VALUES (:o, :t, :r, :n, :u, NOW())');
        $db->bind(':o', $id); $db->bind(':t', $track); $db->bind(':r', $reason); $db->bind(':n', mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 500) ?: null); $db->bind(':u', $actor);
        $ok = $db->cdp_execute();
        if ($ok) {
            cdp_insertNotification(['user_id' => $actor, 'order_id' => $id, 'order_track' => $track, 'notification_description' => 'Status disputed on ' . $track . ': ' . $reason, 'shipping_type' => '1', 'notification_date' => date('Y-m-d H:i:s')]);
            $nid = (int) $db->dbh->lastInsertId();
            foreach (cdp_getUsersAdminEmployees() as $u) {
                cdp_insertNotificationsUsers($nid, $u->id);
            }
            cdp_activityLog(['module' => 'shipments', 'verb' => 'create', 'action' => 'shipment.dispute', 'entity_type' => 'shipment', 'entity_id' => $id, 'entity_label' => $track, 'summary' => "disputed status of $track ($reason)"]);
        }
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Dispute logged. The team has been notified.' : 'Could not save the dispute']);
        break;

    case 'privacy':
        require_permission('view_tools');
        $cur = json_decode((string) sl_slSetting('tracking_public_privacy', '{}'), true) ?: [];
        $key = (string) ($_POST['key'] ?? '');
        if (!in_array($key, ['name', 'address', 'items', 'customs', 'expires'], true)) {
            sl_json(['success' => false, 'message' => 'Unknown setting'], 422);
        }
        $cur[$key] = $key === 'expires' ? (in_array((string) $_POST['value'], ['7', '30', '90', 'never'], true) ? (string) $_POST['value'] : '30') : (int) !empty($_POST['value']);
        $db->cdp_query('INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_by, updated_at) VALUES (:k, :v, :u, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()');
        $db->bind(':k', 'tracking_public_privacy'); $db->bind(':v', json_encode($cur)); $db->bind(':u', $actor);
        $ok = $db->cdp_execute();
        if ($ok) {
            cdp_activityLog(['module' => 'settings', 'verb' => 'update', 'action' => 'tracking.privacy', 'entity_type' => 'setting', 'entity_id' => 'tracking_public_privacy', 'summary' => "public tracking: $key = " . json_encode($cur[$key])]);
        }
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Public tracking updated' : 'Not saved']);
        break;

    case 'prefs':
        $prefs = json_decode((string) ($_POST['prefs'] ?? '{}'), true);
        if (!is_array($prefs)) {
            sl_json(['success' => false, 'message' => 'Bad payload'], 422);
        }
        $clean = [];
        foreach (['order_package', 'order_courier', 'order_service_options', 'order_deli_time', 'order_payment_method', 'order_pay_mode', 'status_courier', 'origin_off', 'agency', 'quick', 'notify_whatsapp_sender', 'notify_sms_receiver', 'create_invoice'] as $k) {
            if (isset($prefs[$k])) $clean[$k] = (int) $prefs[$k];
        }
        $db->cdp_query('INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_by, updated_at) VALUES (:k, :v, :u, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()');
        $db->bind(':k', 'new_shipment_defaults_' . $actor); $db->bind(':v', json_encode($clean)); $db->bind(':u', $actor);
        sl_json(['success' => (bool) $db->cdp_execute(), 'message' => 'Saved as your defaults']);
        break;

    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
