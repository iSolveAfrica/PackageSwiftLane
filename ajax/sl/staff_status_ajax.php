<?php
/** Suspend, reactivate, delete or re-invite a staff account (Staff & Roles page). */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/sl_people.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
if (CDP_APP_MODE_DEMO === true) sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
$op = (string) ($_POST['op'] ?? '');
$id = (int) ($_POST['id'] ?? 0);
if ($id === (int) $_SESSION['userid']) sl_json(['success' => false, 'message' => 'You cannot change your own account here'], 403);
$target = sl_row('SELECT id, username, fname, lname, email, userlevel, active, name_off FROM cdb_users WHERE id = :id', [':id' => $id]);
if (!$target || cdp_roleIsClient((int) $target->userlevel)) sl_json(['success' => false, 'message' => 'Staff account not found'], 404);
if (!cdp_canManageUser($user, (int) $target->userlevel)) sl_json(['success' => false, 'message' => 'This account outranks you'], 403);
$name = trim($target->fname . ' ' . $target->lname) ?: $target->username;
$db = new Conexion();
switch ($op) {
    case 'suspend':
    case 'activate':
        require_permission('edit_user');
        $active = $op === 'activate' ? 1 : 0;
        $db->cdp_query('UPDATE cdb_users SET active = :a WHERE id = :id');
        $db->bind(':a', $active); $db->bind(':id', $id);
        $ok = $db->cdp_execute();
        if ($ok) cdp_activityLog(['module' => 'users', 'action' => $op, 'action_label' => $active ? 'Reactivated staff' : 'Suspended staff', 'verb' => 'update', 'outcome' => 'ok', 'summary' => ($active ? 'reactivated ' : 'suspended ') . $name, 'entity_type' => 'user', 'entity_id' => $id, 'entity_label' => $name]);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? "$name " . ($active ? 'reactivated' : 'suspended') : 'Could not update']);
        break;
    case 'delete':
        require_permission('delete_user');
        $db->cdp_query('DELETE FROM cdb_users WHERE id = :id');
        $db->bind(':id', $id);
        $ok = $db->cdp_execute();
        if ($ok) {
            foreach (['cdb_user_permission_overrides' => 'user_id', 'cdb_department_members' => 'user_id'] as $t => $c) {
                if (sl_tableExists($t)) { $db->cdp_query("DELETE FROM $t WHERE $c = :id"); $db->bind(':id', $id); $db->cdp_execute(); }
            }
            cdp_activityLog(['module' => 'users', 'action' => 'delete', 'action_label' => 'Deleted staff', 'verb' => 'delete', 'outcome' => 'ok', 'summary' => "deleted account of $name ({$target->username})", 'entity_type' => 'user', 'entity_id' => $id, 'entity_label' => $name]);
        }
        sl_json(['success' => (bool) $ok, 'message' => $ok ? "$name deleted" : 'Could not delete']);
        break;
    case 'reinvite':
        require_permission(['invite_staff', 'add_user']);
        $password = bin2hex(random_bytes(5));
        $db->cdp_query('UPDATE cdb_users SET password = :p, password_changed_at = NULL WHERE id = :id');
        $db->bind(':p', password_hash($password, PASSWORD_DEFAULT)); $db->bind(':id', $id);
        $db->cdp_execute();
        $sent = sl_staffInviteMail($target, $password);
        cdp_activityLog(['module' => 'users', 'action' => 'reinvite', 'action_label' => 'Re-sent invitation', 'verb' => 'notify', 'outcome' => $sent ? 'ok' : 'failure', 'summary' => "re-sent invitation to $name", 'entity_type' => 'user', 'entity_id' => $id, 'entity_label' => $name]);
        sl_json(['success' => $sent, 'message' => $sent ? "Invitation sent to {$target->email}" : 'Mail could not be sent (check the mailer settings)']);
        break;
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
