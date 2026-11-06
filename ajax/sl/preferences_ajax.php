<?php
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_login();
$theme = $_POST['theme'] ?? '';
if (!in_array($theme, ['light', 'dark', 'auto'], true)) {
    sl_json(['success' => false, 'message' => 'Unknown theme'], 422);
}
if (sl_tableExists('cdb_user_preferences')) {
    $db = new Conexion();
    $db->cdp_query('INSERT INTO cdb_user_preferences (user_id, pref_key, pref_value, updated_at) VALUES (:u, :k, :v, NOW()) ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value), updated_at = NOW()');
    $db->bind(':u', (int) $_SESSION['userid']);
    $db->bind(':k', 'theme');
    $db->bind(':v', $theme);
    $db->cdp_execute();
}
sl_json(['success' => true]);
