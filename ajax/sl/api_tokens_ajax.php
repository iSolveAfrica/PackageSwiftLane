<?php
/** API Access: create / revoke the signed-in user's own REST API tokens (api/v1/ApiAuth.php, cdb_api_tokens). */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../api/v1/ApiAuth.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
if (CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
$uid = (int) $_SESSION['userid'];
$op = (string) ($_POST['op'] ?? '');
$admin = $user->cdp_hasPermission(['revoke_api_tokens', 'manage_api_clients']);
switch ($op) {
    case 'create':
        require_permission(['use_api_tokens', 'manage_api_clients', 'manage_api_settings']);
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 100);
        $days = max(1, min(365, (int) ($_POST['days'] ?? 90)));
        if ($name === '') {
            sl_json(['success' => false, 'message' => 'Name the key (e.g. "Shopify store")', 'errors' => ['name' => 'Required']], 422);
        }
        $t = (new ApiAuth())->createToken($uid, $days * 24, $name);
        cdp_activityLog(['module' => 'api', 'action' => 'token_create', 'verb' => 'create', 'outcome' => 'ok', 'summary' => "created API key \"$name\" ($days days)", 'entity_type' => 'api_token', 'entity_label' => $name]);
        // The raw token is returned once and never stored (only its SHA-256 hash is).
        sl_json(['success' => true, 'message' => 'Key created. Copy it now, it is shown only once.', 'token' => $t['token'], 'expires_at' => $t['expires_at']]);
        break;
    case 'revoke':
        $id = (int) ($_POST['id'] ?? 0);
        $row = sl_row('SELECT id, user_id, name FROM cdb_api_tokens WHERE id = :id', [':id' => $id]);
        if (!$row || ((int) $row->user_id !== $uid && !$admin)) {
            sl_json(['success' => false, 'message' => 'Key not found'], 404);
        }
        $db = new Conexion();
        $db->cdp_query('UPDATE cdb_api_tokens SET revoked_at = NOW() WHERE id = :id AND revoked_at IS NULL');
        $db->bind(':id', $id);
        $ok = $db->cdp_execute();
        cdp_activityLog(['module' => 'api', 'action' => 'token_revoke', 'verb' => 'update', 'outcome' => 'ok', 'summary' => 'revoked API key "' . $row->name . '"', 'entity_type' => 'api_token', 'entity_id' => $id, 'entity_label' => (string) $row->name]);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Key revoked' : 'Could not revoke']);
        break;
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
