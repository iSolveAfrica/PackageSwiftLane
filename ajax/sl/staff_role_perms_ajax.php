<?php
/** Switch permission areas on/off for a role (Staff & Roles page). Rank-guarded. */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/sl_people.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once CDP_ROOT . '/views/sl/data/staff.php';
require_login();
require_permission('view_role_assignment');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
if (CDP_APP_MODE_DEMO === true) sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
$roleId = (int) ($_POST['role_id'] ?? 0);
$roles = sl_staffRoles();
if (!isset($roles[$roleId])) sl_json(['success' => false, 'message' => 'Unknown role'], 404);
if (!cdp_canAssignRole($user, $roleId)) sl_json(['success' => false, 'message' => 'You cannot change a role at or above your own rank'], 403);
if (cdp_roleHasFlag($roleId, 'is_superadmin')) sl_json(['success' => false, 'message' => 'The super admin role always has every permission'], 422);
$areas = (array) ($_POST['area'] ?? []);
$db = new Conexion();
$changed = [];
foreach (sl_staffAreas() as $key => $a) {
    if (!array_key_exists($key, $areas)) continue;
    $on = (int) !empty($areas[$key]);
    foreach ($a[2] as $action) {
        $aid = (int) sl_scalar('SELECT id FROM cdb_user_module_actions WHERE action_name = :a', [':a' => $action]);
        if (!$aid) continue;
        $db->cdp_query('INSERT INTO cdb_user_role_permissions (role_id, module_action_id, permitted, created_at) VALUES (:r, :a, :p, NOW()) ON DUPLICATE KEY UPDATE permitted = VALUES(permitted)');
        $db->bind(':r', $roleId); $db->bind(':a', $aid); $db->bind(':p', $on);
        $db->cdp_execute();
    }
    $changed[] = $a[0] . ($on ? ' on' : ' off');
}
cdp_activityLog(['module' => 'access', 'action' => 'role_areas', 'action_label' => 'Changed role permissions', 'verb' => 'update', 'outcome' => 'ok', 'summary' => 'set ' . $roles[$roleId]->role_name . ' areas: ' . implode(', ', $changed), 'entity_type' => 'role', 'entity_id' => $roleId, 'entity_label' => $roles[$roleId]->role_name, 'meta' => ['areas' => $areas]]);
sl_json(['success' => true, 'message' => $roles[$roleId]->role_name . ' permissions saved']);
