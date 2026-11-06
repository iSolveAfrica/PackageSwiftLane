<?php
/**
 * Permission writes for the Staff Permissions page. Same tables and SQL as the
 * legacy endpoints (ajax/tools/permissions/*, ajax/users/users_overrides_save_ajax.php)
 * plus the rank rules of helpers/rbac.php: a user never edits a role at or above
 * their own rank, never a super admin role, and only overrides for people they manage.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/sl_people.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_login();
require_permission('view_role_assignment');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
if (CDP_APP_MODE_DEMO === true) sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
$op = (string) ($_POST['op'] ?? '');
$db = new Conexion();
$validIds = array_map(fn($r) => (int) $r->id, sl_rows('SELECT id FROM cdb_user_module_actions'));
$roles = cdp_roleFlagsMap();
$log = function ($summary, $type, $id, $label, $meta = []) {
    cdp_activityLog(['module' => 'access', 'verb' => 'update', 'action' => 'access.permissions', 'action_label' => 'Changed permissions', 'outcome' => 'ok', 'entity_type' => $type, 'entity_id' => $id, 'entity_label' => $label, 'summary' => $summary, 'meta' => $meta]);
};
switch ($op) {
    case 'role_save':
        $roleId = (int) ($_POST['role_id'] ?? 0);
        if (!isset($roles[$roleId])) sl_json(['success' => false, 'message' => 'Unknown role'], 404);
        if (!cdp_canAssignRole($user, $roleId) || cdp_roleHasFlag($roleId, 'is_superadmin')) sl_json(['success' => false, 'message' => 'You cannot edit a role at or above your own rank'], 403);
        $granted = array_values(array_intersect($validIds, array_map('intval', array_filter(explode(',', (string) ($_POST['granted'] ?? '')), 'strlen'))));
        // Mirror asingpermissions_update_ajax.php: rewrite the role's grant set.
        $db->cdp_query('DELETE FROM cdb_user_role_permissions WHERE role_id = :r'); $db->bind(':r', $roleId); $db->cdp_execute();
        foreach ($granted as $aid) {
            $db->cdp_query('INSERT INTO cdb_user_role_permissions (role_id, module_action_id, permitted, created_at) VALUES (:r, :a, 1, NOW())');
            $db->bind(':r', $roleId); $db->bind(':a', $aid); $db->cdp_execute();
        }
        $log('rewrote the permission set for role ' . $roles[$roleId]->role_name . ' (' . count($granted) . ' actions)', 'role', $roleId, $roles[$roleId]->role_name, ['granted_count' => count($granted)]);
        sl_json(['success' => true, 'message' => $roles[$roleId]->role_name . ' permissions saved']);
    case 'user_save':
        require_permission('edit_user');
        if (!sl_tableExists('cdb_user_permission_overrides')) sl_json(['success' => false, 'message' => 'Overrides table missing (run the upgrade)'], 409);
        $uid = (int) ($_POST['user_id'] ?? 0);
        $target = sl_managedUser($user, $uid);
        if (!$target || $uid === (int) $_SESSION['userid']) sl_json(['success' => false, 'message' => 'No permission to manage this user'], 403);
        $changes = json_decode((string) ($_POST['changes'] ?? '{}'), true) ?: [];
        $n = 0;
        foreach ($changes as $aid => $state) {
            $aid = (int) $aid;
            if (!in_array($aid, $validIds, true)) continue;
            if ($state === 'inherit') { $db->cdp_query('DELETE FROM cdb_user_permission_overrides WHERE user_id = :u AND module_action_id = :a'); $db->bind(':u', $uid); $db->bind(':a', $aid); $db->cdp_execute(); continue; }
            if ($state !== 'allow' && $state !== 'deny') continue;
            $db->cdp_query('INSERT INTO cdb_user_permission_overrides (user_id, module_action_id, permitted) VALUES (:u, :a, :p) ON DUPLICATE KEY UPDATE permitted = VALUES(permitted)');
            $db->bind(':u', $uid); $db->bind(':a', $aid); $db->bind(':p', $state === 'allow' ? 1 : 0); $db->cdp_execute(); $n++;
        }
        $name = trim($target->fname . ' ' . $target->lname);
        $log("set $n permission overrides for $name", 'user', $uid, $name);
        sl_json(['success' => true, 'message' => "Overrides saved for $name"]);
    case 'user_role':
        require_permission('edit_user');
        $uid = (int) ($_POST['user_id'] ?? 0);
        $roleId = (int) ($_POST['role_id'] ?? 0);
        $target = sl_managedUser($user, $uid);
        if (!$target || $uid === (int) $_SESSION['userid']) sl_json(['success' => false, 'message' => 'No permission to manage this user'], 403);
        if (!isset($roles[$roleId]) || !cdp_canAssignRole($user, $roleId) || cdp_roleIsClient($roleId)) sl_json(['success' => false, 'message' => 'You cannot assign that role'], 403);
        $db->cdp_query('UPDATE cdb_users SET userlevel = :r WHERE id = :id'); $db->bind(':r', $roleId); $db->bind(':id', $uid); $db->cdp_execute();
        $name = trim($target->fname . ' ' . $target->lname);
        $log("changed role of $name to " . $roles[$roleId]->role_name, 'user', $uid, $name, ['from' => (int) $target->userlevel, 'to' => $roleId]);
        sl_json(['success' => true, 'message' => "$name is now " . $roles[$roleId]->role_name]);
    case 'dept_create':
        if (!sl_tableExists('cdb_departments')) sl_json(['success' => false, 'message' => 'Departments need the database upgrade'], 409);
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 100);
        $base = (int) ($_POST['base_role_id'] ?? 0);
        if ($name === '') sl_json(['success' => false, 'errors' => ['name' => 'Required'], 'message' => 'Name the department'], 422);
        if (!isset($roles[$base]) || !cdp_canAssignRole($user, $base)) sl_json(['success' => false, 'message' => 'Pick a base role below your rank'], 403);
        if (sl_scalar('SELECT COUNT(*) FROM cdb_departments WHERE name = :n', [':n' => $name])) sl_json(['success' => false, 'message' => 'A department with that name exists'], 422);
        $db->cdp_query('INSERT INTO cdb_departments (name, description, base_role_id) VALUES (:n, :d, :r)');
        $db->bind(':n', $name); $db->bind(':d', mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 255)); $db->bind(':r', $base); $db->cdp_execute();
        $id = (int) sl_scalar('SELECT id FROM cdb_departments WHERE name = :n', [':n' => $name]);
        $log("created department $name", 'department', $id, $name);
        sl_json(['success' => true, 'message' => "Department $name created", 'redirect' => sl_route('StaffPermissions', ['tab' => 'dept', 'dept' => $id])]);
    case 'dept_delete':
        $id = (int) ($_POST['department_id'] ?? 0);
        $dept = sl_row('SELECT * FROM cdb_departments WHERE id = :id', [':id' => $id]);
        if (!$dept) sl_json(['success' => false, 'message' => 'Department not found'], 404);
        if (!cdp_canAssignRole($user, (int) $dept->base_role_id)) sl_json(['success' => false, 'message' => 'This department outranks you'], 403);
        foreach (['cdb_department_members', 'cdb_department_permissions', 'cdb_departments'] as $t) { $db->cdp_query("DELETE FROM $t WHERE " . ($t === 'cdb_departments' ? 'id' : 'department_id') . ' = :id'); $db->bind(':id', $id); $db->cdp_execute(); }
        $log("deleted department {$dept->name}", 'department', $id, $dept->name);
        sl_json(['success' => true, 'message' => 'Department deleted']);
    case 'dept_member':
        $id = (int) ($_POST['department_id'] ?? 0); $uid = (int) ($_POST['user_id'] ?? 0); $member = (int) !empty($_POST['value']);
        $dept = sl_row('SELECT * FROM cdb_departments WHERE id = :id', [':id' => $id]);
        if (!$dept || !cdp_canAssignRole($user, (int) $dept->base_role_id)) sl_json(['success' => false, 'message' => 'No permission for this department'], 403);
        $target = sl_managedUser($user, $uid);
        if (!$target) sl_json(['success' => false, 'message' => 'No permission to manage this user'], 403);
        if ($member) { $db->cdp_query('INSERT IGNORE INTO cdb_department_members (department_id, user_id) VALUES (:d, :u)'); } else { $db->cdp_query('DELETE FROM cdb_department_members WHERE department_id = :d AND user_id = :u'); }
        $db->bind(':d', $id); $db->bind(':u', $uid); $db->cdp_execute();
        $name = trim($target->fname . ' ' . $target->lname);
        $log(($member ? 'added ' : 'removed ') . "$name " . ($member ? 'to' : 'from') . " department {$dept->name}", 'department', $id, $dept->name);
        sl_json(['success' => true, 'message' => $name . ($member ? ' added' : ' removed')]);
    case 'dept_save':
        $id = (int) ($_POST['department_id'] ?? 0);
        $dept = sl_row('SELECT * FROM cdb_departments WHERE id = :id', [':id' => $id]);
        if (!$dept || !cdp_canAssignRole($user, (int) $dept->base_role_id)) sl_json(['success' => false, 'message' => 'No permission for this department'], 403);
        $changes = json_decode((string) ($_POST['changes'] ?? '{}'), true) ?: [];
        $n = 0;
        foreach ($changes as $aid => $state) {
            $aid = (int) $aid;
            if (!in_array($aid, $validIds, true)) continue;
            if ($state === 'inherit') { $db->cdp_query('DELETE FROM cdb_department_permissions WHERE department_id = :d AND module_action_id = :a'); $db->bind(':d', $id); $db->bind(':a', $aid); $db->cdp_execute(); continue; }
            if ($state !== 'allow' && $state !== 'deny') continue;
            $db->cdp_query('INSERT INTO cdb_department_permissions (department_id, module_action_id, permitted) VALUES (:d, :a, :p) ON DUPLICATE KEY UPDATE permitted = VALUES(permitted)');
            $db->bind(':d', $id); $db->bind(':a', $aid); $db->bind(':p', $state === 'allow' ? 1 : 0); $db->cdp_execute(); $n++;
        }
        $log("set $n permission rules on department {$dept->name}", 'department', $id, $dept->name);
        sl_json(['success' => true, 'message' => "{$dept->name} saved"]);
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
