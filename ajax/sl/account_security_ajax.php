<?php
/**
 * My Account Security (views/sl/pages/ProfileEdit.php): the signed-in user forgets their own remembered
 * devices. Acts only on the session user's rows in cdb_auth_trusted_devices.
 *   op=revoke_device       id = a device of the signed-in user
 *   op=revoke_all_devices  every remembered device of the signed-in user (OtpService, also clears this browser's cookie)
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../lib/OtpService.php';
require_login();
$core = new Core(); // applies the system timezone, so revoked_at matches OtpService (which also builds Core)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
if (CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
$uid = (int) ($_SESSION['userid'] ?? 0);
if ($uid <= 0) {
    sl_json(['success' => false, 'message' => 'Sign in again'], 401);
}
if (!sl_tableExists('cdb_auth_trusted_devices')) {
    sl_json(['success' => false, 'message' => 'There are no remembered devices on this system'], 404);
}
$me = sl_row('SELECT id, fname, lname, username FROM cdb_users WHERE id = :id', [':id' => $uid]);
$name = $me ? (trim($me->fname . ' ' . $me->lname) ?: $me->username) : ('#' . $uid);
$log = function (string $action, string $label, string $summary) use ($uid, $name) {
    if (function_exists('cdp_activityLog')) {
        // module 'auth' + outcome 'success': the customer timeline files it under "Sign-ins & security"
        // as the account owner's own action (views/sl/data/user_360.php), not as a staff change.
        cdp_activityLog(['module' => 'auth', 'action' => $action, 'label' => $label, 'verb' => 'update', 'outcome' => 'success', 'summary' => $summary, 'entity_type' => 'user', 'entity_id' => $uid, 'entity_label' => $name]);
    }
};
$cookieSelector = explode(':', (string) ($_COOKIE['trusted_device'] ?? ''), 2)[0];

switch ((string) ($_POST['op'] ?? '')) {
    case 'revoke_device':
        $id = (int) ($_POST['id'] ?? 0);
        $row = $id > 0 ? sl_row('SELECT id, selector FROM cdb_auth_trusted_devices WHERE id = :id AND user_id = :u AND revoked_at IS NULL', [':id' => $id, ':u' => $uid]) : null;
        if (!$row) {
            sl_json(['success' => false, 'message' => 'That device is not remembered on your account'], 404);
        }
        $db = new Conexion();
        $db->cdp_query('UPDATE cdb_auth_trusted_devices SET revoked_at = :now WHERE id = :id AND user_id = :u AND revoked_at IS NULL');
        $db->bind(':now', date('Y-m-d H:i:s'));
        $db->bind(':id', (int) $row->id);
        $db->bind(':u', $uid);
        if (!$db->cdp_execute()) {
            sl_json(['success' => false, 'message' => 'Could not forget the device. Try again.'], 500);
        }
        $current = $cookieSelector !== '' && hash_equals((string) $row->selector, $cookieSelector);
        if ($current) {
            // Same cookie attributes as OtpService::rememberTrustedDevice().
            setcookie('trusted_device', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'secure' => true, 'samesite' => 'Lax']);
        }
        $log('device.forget', 'Forgot a remembered device', 'Forgot remembered device #' . (int) $row->id . ($current ? ' (this browser)' : ''));
        sl_json(['success' => true, 'message' => $current ? 'This browser is forgotten: it asks for a sign-in code next time' : 'Device forgotten: it asks for a sign-in code next time', 'reload' => true]);
        break;
    case 'revoke_all_devices':
        $n = (int) sl_scalar('SELECT COUNT(*) FROM cdb_auth_trusted_devices WHERE user_id = :u AND revoked_at IS NULL', [':u' => $uid]);
        (new OtpService())->revokeAllTrustedDevices($uid);
        $log('device.forget_all', 'Forgot all remembered devices', "Forgot $n remembered devices");
        sl_json(['success' => true, 'message' => $n === 1 ? '1 device forgotten: it asks for a sign-in code next time' : "$n devices forgotten: each asks for a sign-in code next time", 'reload' => true]);
        break;
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
