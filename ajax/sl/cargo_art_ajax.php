<?php
/**
 * Settings › Status pictures: save which illustration goes with each status (helpers/cargo_art.php).
 *   op=save   air[status_id]=key, sea[status_id]=key   (a value equal to the default is not stored)
 *   op=reset  back to the defaults
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../views/sl/data/ops_common.php';
require_once __DIR__ . '/../../helpers/cargo_art.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_login();
require_permission('view_tools');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
if (CDP_APP_MODE_DEMO === true) sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
if (!sl_tableExists('cdb_sl_settings')) sl_json(['success' => false, 'message' => 'Settings storage is missing: run the database update'], 500);
$op = (string) ($_POST['op'] ?? '');
$map = ['air' => [], 'sea' => []];
if ($op === 'save') {
    $names = [];
    foreach (sl_rows('SELECT id, mod_style, detail FROM cdb_styles') as $st) $names[(int) $st->id] = str_replace('_', ' ', $st->mod_style) . ' ' . $st->detail;
    foreach (['air' => false, 'sea' => true] as $mode => $sea) {
        foreach ((array) ($_POST[$mode] ?? []) as $id => $key) {
            $id = (int) $id; $key = (string) $key;
            if (!isset($names[$id]) || !isset(sl_cargoArtOf($sea)[$key])) continue; // air only air pictures, sea only sea
            if ($key !== sl_cargoArtDefault($id, $names[$id], $sea)) $map[$mode][(string) $id] = $key;
        }
    }
} elseif ($op !== 'reset') {
    sl_json(['success' => false, 'message' => 'Unknown action'], 400);
}
$db = new Conexion();
$db->cdp_query('INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_by, updated_at) VALUES (:k, :v, :u, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()');
$db->bind(':k', 'cargo_art_map'); $db->bind(':v', json_encode($map)); $db->bind(':u', (int) ($_SESSION['userid'] ?? 0));
if (!$db->cdp_execute()) sl_json(['success' => false, 'message' => 'Could not save. Try again.'], 500);
if (function_exists('cdp_activityLog')) {
    cdp_activityLog(['module' => 'settings', 'action' => 'cargo_art.' . $op, 'label' => 'Status pictures', 'verb' => 'update', 'outcome' => 'success',
        'summary' => $op === 'reset' ? 'reset the status pictures to the defaults' : 'changed the status pictures (' . (count($map['air']) + count($map['sea'])) . ' custom)']);
}
sl_json(['success' => true, 'message' => $op === 'reset' ? 'Status pictures reset' : 'Status pictures saved', 'reload' => true]);
