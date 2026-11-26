<?php
/**
 * Security Centre (security_center.php) endpoint.
 *
 * Read (GET, view_system_logs: the page's own permission):
 *   op=user_detail&id=N   one staff account: remembered devices, passkeys, last 15 sign-ins
 *                         (views/sl/data/security.php sl_securityUserDetail), as JSON.
 * Writes (POST + CSRF, edit_user; only a super admin may act on a super admin):
 *   op=revoke_devices&id=N            forget every remembered device of the user
 *   op=revoke_device&id=N&device=D    forget one device; D must belong to N
 *   op=force_reset / clear_reset&id=N set / clear the force-password-reset flag
 * Writes sent with from=detail (the detail pop-up) answer without reload, so the pop-up refreshes in place.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../lib/OtpService.php';
require_login();
// Core applies the app's time zone (cdb_settings.timezone, date_default_timezone_set), as every
// page does: the rows here were written in that zone, so "5 min ago", the 30-day and 180-day
// windows and revoked_at must use the same clock.
$core = new Core();

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$readOps = ['user_detail'];

// ── Read: GET only, the page's permission ─────────────────────────────────
if (!$isPost) {
    $op = (string) ($_GET['op'] ?? '');
    if (!in_array($op, $readOps, true)) {
        sl_json(['success' => false, 'message' => 'POST required'], 405);
    }
    require_permission('view_system_logs');
    // Read-only: keep the automatic audit entry (helpers/activity_log.php) out of the trail.
    $GLOBALS['cdp_activity_logged'] = true;
    require_once __DIR__ . '/../../views/sl/data/security.php';
    $detail = sl_securityUserDetail((int) ($_GET['id'] ?? 0), $user->cdp_hasPermission('edit_user'), (int) ($_SESSION['userlevel'] ?? 0) === 9);
    if (!$detail) {
        sl_json(['success' => false, 'message' => 'Staff account not found'], 404);
    }
    sl_json(['success' => true] + $detail);
}

// ── Writes: POST, edit_user ───────────────────────────────────────────────
$op = (string) ($_POST['op'] ?? '');
if (in_array($op, $readOps, true)) {
    sl_json(['success' => false, 'message' => 'Use GET for this'], 405);
}
if (CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
require_permission('edit_user');
$id = (int) ($_POST['id'] ?? 0);
$reload = (string) ($_POST['from'] ?? '') !== 'detail';
$u = sl_row("SELECT id, fname, lname, username, userlevel FROM cdb_users WHERE id = :id", [':id' => $id]);
if (!$u) {
    sl_json(['success' => false, 'message' => 'User not found'], 404);
}
// Only a super admin may act on a super admin.
if ((int) $u->userlevel === 9 && (int) ($_SESSION['userlevel'] ?? 0) !== 9) {
    sl_json(['success' => false, 'message' => 'Only a super admin can change another super admin'], 403);
}
$name = trim($u->fname . ' ' . $u->lname) ?: $u->username;
$log = function (string $action, string $label, string $summary) use ($id, $name) {
    if (function_exists('cdp_activityLog')) {
        cdp_activityLog(['module' => 'security', 'action' => $action, 'action_label' => $label, 'verb' => 'update', 'outcome' => 'ok', 'summary' => $summary, 'entity_type' => 'user', 'entity_id' => $id, 'entity_label' => $name]);
    }
};
switch ($op) {
    case 'revoke_devices':
        $n = (int) sl_scalar('SELECT COUNT(*) FROM cdb_auth_trusted_devices WHERE user_id = :u AND revoked_at IS NULL', [':u' => $id]);
        // revokeAllTrustedDevices() also clears the trusted_device cookie of the current browser;
        // only let it do so when acting on yourself.
        if ($id === (int) $_SESSION['userid']) {
            (new OtpService())->revokeAllTrustedDevices($id);
        } else {
            $db = new Conexion();
            $db->cdp_query('UPDATE cdb_auth_trusted_devices SET revoked_at = :now WHERE user_id = :u AND revoked_at IS NULL');
            $db->bind(':now', date('Y-m-d H:i:s')); $db->bind(':u', $id);
            $db->cdp_execute();
        }
        $log('revoke_devices', 'Revoked trusted devices', "revoked $n trusted devices of $name");
        sl_json(['success' => true, 'message' => "$n trusted devices of $name revoked: a code is asked at the next sign-in", 'reload' => $reload]);
        break;
    case 'revoke_device':
        $deviceId = (int) ($_POST['device'] ?? 0);
        $dev = sl_row('SELECT id, selector FROM cdb_auth_trusted_devices WHERE id = :d AND user_id = :u AND revoked_at IS NULL', [':d' => $deviceId, ':u' => $id]);
        if (!$dev) {
            sl_json(['success' => false, 'message' => 'That device is not remembered for ' . $name], 404);
        }
        $db = new Conexion();
        $db->cdp_query('UPDATE cdb_auth_trusted_devices SET revoked_at = :now WHERE id = :d AND user_id = :u AND revoked_at IS NULL');
        $db->bind(':now', date('Y-m-d H:i:s')); $db->bind(':d', $deviceId); $db->bind(':u', $id);
        $ok = $db->cdp_execute();
        // Forgetting the browser you are using: drop its cookie too (same flags as OtpService sets it with).
        $cookie = (string) ($_COOKIE['trusted_device'] ?? '');
        if ($ok && $id === (int) $_SESSION['userid'] && $cookie !== '' && hash_equals((string) $dev->selector, explode(':', $cookie)[0])) {
            setcookie('trusted_device', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'secure' => true, 'samesite' => 'Lax']);
        }
        if ($ok) $log('revoke_device', 'Revoked a trusted device', "forgot trusted device #$deviceId of $name");
        sl_json(['success' => (bool) $ok, 'message' => $ok ? "Device forgotten: $name gets a code at the next sign-in from it" : 'Could not save', 'reload' => $reload]);
        break;
    case 'force_reset':
    case 'clear_reset':
        if (!sl_columnExists('cdb_users', 'force_password_reset')) {
            sl_json(['success' => false, 'message' => 'Run migrations/2026_11_06_freight_devops.sql to add the flag'], 409);
        }
        $on = $op === 'force_reset' ? 1 : 0;
        $db = new Conexion();
        $db->cdp_query('UPDATE cdb_users SET force_password_reset = :f WHERE id = :u');
        $db->bind(':f', $on); $db->bind(':u', $id);
        $ok = $db->cdp_execute();
        if ($ok) $log($op, $on ? 'Forced password reset' : 'Cleared password reset', ($on ? 'flagged ' : 'cleared flag for ') . $name);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? ($on ? "$name must choose a new password" : "Flag cleared for $name") : 'Could not save', 'reload' => $reload]);
        break;
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
