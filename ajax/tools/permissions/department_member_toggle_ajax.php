<?php
// Add/remove one user to/from a department (instant).
ini_set('display_errors', 0);
header('Content-type: application/json; charset=UTF-8');

require_once("../../../loader.php");
require_once(__DIR__ . '/../../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../../helpers/sl_people.php');
require_login();
require_permission('view_role_assignment');

$db = new Conexion;
$deptId = intval($_POST['department_id'] ?? 0);
$userId = intval($_POST['user_id'] ?? 0);
$member = intval($_POST['member'] ?? 0); // 1 add, 0 remove

if ($deptId < 1 || $userId < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}
if (CDP_APP_MODE_DEMO === true) {
    echo json_encode(['status' => 'error', 'message' => 'Disabled in demo mode.']);
    exit;
}

// Department must exist.
$db->cdp_query("SELECT id, base_role_id FROM cdb_departments WHERE id = :id");
$db->bind(':id', $deptId); $db->cdp_execute();
$dept = $db->cdp_registro();
if (!$dept) {
    echo json_encode(['status' => 'error', 'message' => 'Department not found.']);
    exit;
}

// Department permissions are merged into each member's permissions, so membership is a
// privilege grant. Apply the same rank rules as ajax/sl/staff_permissions_ajax.php
// (dept_member): the department's base role must be below the actor's rank, and the
// target must be someone the actor manages. sl_managedUser() also refuses the actor's
// own account below super admin, so nobody can add themselves to a stronger department.
if (!cdp_canAssignRole($user, (int) $dept->base_role_id)) {
    echo json_encode(['status' => 'error', 'message' => 'No permission for this department.']);
    exit;
}
if (!sl_managedUser($user, $userId)) {
    echo json_encode(['status' => 'error', 'message' => 'No permission to manage this user.']);
    exit;
}

try {
    if ($member === 1) {
        $db->cdp_query("INSERT IGNORE INTO cdb_department_members (department_id, user_id) VALUES (:d, :u)");
    } else {
        $db->cdp_query("DELETE FROM cdb_department_members WHERE department_id = :d AND user_id = :u");
    }
    $db->bind(':d', $deptId);
    $db->bind(':u', $userId);
    $db->cdp_execute();
    echo json_encode(['status' => 'success', 'member' => $member]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Save failed.']);
}
