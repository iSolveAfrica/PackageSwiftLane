<?php
/** Approve, block or unblock a customer account (Customers page). */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
if (CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
$op = (string) ($_POST['op'] ?? '');
$id = (int) ($_POST['id'] ?? 0);
$target = sl_row('SELECT id, fname, lname, email, userlevel, active FROM cdb_users WHERE id = :id AND userlevel = 1', [':id' => $id]);
if (!$target) {
    sl_json(['success' => false, 'message' => 'Customer not found'], 404);
}
if (function_exists('cdp_getAgencyContext')) {
    $ctx = cdp_getAgencyContext();
    if (!empty($ctx['is_restricted'])) {
        $ag = (int) sl_scalar('SELECT agency_id FROM cdb_users WHERE id = :id', [':id' => $id]);
        if ($ag !== (int) $ctx['agency_id']) {
            sl_json(['success' => false, 'message' => 'This customer belongs to another agency'], 403);
        }
    }
}
$db = new Conexion();
$actor = (int) $_SESSION['userid'];
$name = trim($target->fname . ' ' . $target->lname);
switch ($op) {
    case 'approve':
        require_permission(['approve_client', 'edit_client']);
        if (!sl_columnExists('cdb_users', 'approve')) {
            sl_json(['success' => false, 'message' => 'Approval is not enabled on this database (run the upgrade)'], 409);
        }
        $db->cdp_query('UPDATE cdb_users SET approve = 1 WHERE id = :id');
        $db->bind(':id', $id);
        $ok = $db->cdp_execute();
        if ($ok && function_exists('cdp_activityLog')) {
            cdp_activityLog(['module' => 'customers', 'action' => 'approve', 'action_label' => 'Approved customer', 'verb' => 'update', 'outcome' => 'ok', 'summary' => "approved $name", 'entity_type' => 'customer', 'entity_id' => $id, 'entity_label' => $name]);
        }
        sl_json(['success' => (bool) $ok, 'message' => $ok ? "$name approved" : 'Could not approve']);
        break;
    case 'block':
        require_permission('edit_client');
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 190);
        $hold = (int) !empty($_POST['hold']);
        $notify = (int) !empty($_POST['notify']);
        if ($reason === '') {
            sl_json(['success' => false, 'message' => 'Pick a reason', 'errors' => ['reason' => 'Required']], 422);
        }
        $db->cdp_query('UPDATE cdb_users SET active = 0 WHERE id = :id');
        $db->bind(':id', $id);
        $ok = $db->cdp_execute();
        if ($ok && sl_tableExists('cdb_customer_blocks')) {
            $db->cdp_query('INSERT INTO cdb_customer_blocks (user_id, reason, hold_parcels, notify_customer, blocked_by, blocked_at) VALUES (:u, :r, :h, :n, :b, NOW())');
            $db->bind(':u', $id); $db->bind(':r', $reason); $db->bind(':h', $hold); $db->bind(':n', $notify); $db->bind(':b', $actor);
            $db->cdp_execute();
        }
        if ($ok && $hold) {
            // parcels not yet delivered are held (status 29 "Held" if present, else 12 Rejected is too strong: keep status, flag note)
            if ((int) sl_scalar('SELECT COUNT(*) FROM cdb_styles WHERE id = 29') > 0) {
                $db->cdp_query('UPDATE cdb_add_order SET status_courier = 29 WHERE sender_id = :u AND is_deleted = 0 AND status_courier IN (4,6,32)');
                $db->bind(':u', $id);
                $db->cdp_execute();
            }
        }
        if ($ok && $notify && !empty($target->email) && function_exists('cdp_sendMail')) {
            @cdp_sendMail($target->email, 'Your Swift Lane account', "Hello $name, your account has been blocked: $reason. Please contact us.");
        }
        if ($ok && function_exists('cdp_activityLog')) {
            cdp_activityLog(['module' => 'customers', 'action' => 'block', 'action_label' => 'Blocked customer', 'verb' => 'update', 'outcome' => 'ok', 'summary' => "blocked $name ($reason)", 'entity_type' => 'customer', 'entity_id' => $id, 'entity_label' => $name]);
        }
        sl_json(['success' => (bool) $ok, 'message' => $ok ? "$name blocked" : 'Could not block']);
        break;
    case 'unblock':
        require_permission('edit_client');
        $db->cdp_query('UPDATE cdb_users SET active = 1 WHERE id = :id');
        $db->bind(':id', $id);
        $ok = $db->cdp_execute();
        if ($ok && sl_tableExists('cdb_customer_blocks')) {
            $db->cdp_query('UPDATE cdb_customer_blocks SET unblocked_at = NOW(), unblocked_by = :b WHERE user_id = :u AND unblocked_at IS NULL');
            $db->bind(':b', $actor); $db->bind(':u', $id);
            $db->cdp_execute();
            if ((int) sl_scalar('SELECT COUNT(*) FROM cdb_styles WHERE id = 29') > 0) {
                $db->cdp_query('UPDATE cdb_add_order SET status_courier = 6 WHERE sender_id = :u AND is_deleted = 0 AND status_courier = 29');
                $db->bind(':u', $id);
                $db->cdp_execute();
            }
        }
        if ($ok && function_exists('cdp_activityLog')) {
            cdp_activityLog(['module' => 'customers', 'action' => 'unblock', 'action_label' => 'Unblocked customer', 'verb' => 'update', 'outcome' => 'ok', 'summary' => "unblocked $name", 'entity_type' => 'customer', 'entity_id' => $id, 'entity_label' => $name]);
        }
        sl_json(['success' => (bool) $ok, 'message' => $ok ? "$name unblocked" : 'Could not unblock']);
        break;
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
