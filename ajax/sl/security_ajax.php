<?php
/** Security Centre actions: revoke all trusted devices of a user (OtpService::revokeAllTrustedDevices), set / clear the force-password-reset flag. */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../lib/OtpService.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
if (CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
require_permission('edit_user');
$op = (string) ($_POST['op'] ?? '');
$id = (int) ($_POST['id'] ?? 0);
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
        sl_json(['success' => true, 'message' => "$n trusted devices of $name revoked: a code is asked at the next sign-in", 'reload' => true]);
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
        sl_json(['success' => (bool) $ok, 'message' => $ok ? ($on ? "$name must choose a new password" : "Flag cleared for $name") : 'Could not save', 'reload' => true]);
        break;
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
