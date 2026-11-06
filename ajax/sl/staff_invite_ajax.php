<?php
/** Create a staff account with a role and e-mail the invitation (InviteStaff). */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/sl_people.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../helpers/querys.php';
require_login();
require_permission('add_user');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
if (CDP_APP_MODE_DEMO === true) sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
$errors = [];
$name = trim((string) ($_POST['name'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$phone = trim((string) ($_POST['phone'] ?? ''));
$branch = trim((string) ($_POST['branch_office'] ?? ''));
$role = (int) ($_POST['role'] ?? 0);
$username = preg_replace('/[^A-Za-z0-9]/', '', (string) ($_POST['username'] ?? ''));
if ($username === '') $username = preg_replace('/[^A-Za-z0-9]/', '', explode('@', $email)[0] ?? '');
if ($name === '' || !str_contains($name, ' ')) $errors['name'] = 'First and last name';
if (!$user->cdp_isValidEmail($email)) $errors['email'] = 'Valid e-mail required';
elseif ($user->cdp_emailExists($email)) $errors['email'] = 'This e-mail already has an account';
if ($phone === '') $errors['phone'] = 'Required';
if ($branch === '' || !sl_scalar('SELECT COUNT(*) FROM cdb_offices WHERE name_off = :b', [':b' => $branch])) $errors['branch_office'] = 'Pick a branch';
$roles = sl_staffRoles();
if (!isset($roles[$role])) $errors['role'] = 'Pick a role';
elseif (!cdp_canAssignRole($user, $role)) $errors['role'] = 'You cannot assign a role at or above your own rank';
if (strlen($username) < 4) $errors['username'] = 'At least 4 letters or digits';
elseif ($user->cdp_usernameExists($username)) $errors['username'] = 'Username taken';
if ($errors) sl_json(['success' => false, 'message' => 'Please check the form', 'errors' => $errors], 422);
[$fname, $lname] = array_map('trim', explode(' ', $name, 2));
$password = bin2hex(random_bytes(5));
$data = ['username' => $username, 'branch_office' => $branch, 'email' => $email, 'fname' => cdp_sanitize($fname), 'lname' => cdp_sanitize($lname), 'newsletter' => 0, 'notes' => cdp_sanitize((string) ($_POST['notes'] ?? '')), 'phone' => cdp_sanitize($phone), 'gender' => '', 'userlevel' => $role, 'active' => 1, 'password' => password_hash($password, PASSWORD_DEFAULT), 'created' => date('Y-m-d H:i:s')];
$isDriver = cdp_roleHasFlag($role, 'is_driver');
if ($isDriver) {
    $data['enrollment'] = cdp_sanitize((string) ($_POST['enrollment'] ?? ''));
    $data['vehiclecode'] = cdp_sanitize((string) ($_POST['vehicle_type'] ?? ''));
    $ok = cdp_insertDrivers1fcoe($data);
} else {
    $ok = cdp_insertUserfp40f($data);
}
if (!$ok) sl_json(['success' => false, 'message' => 'Could not create the account'], 500);
$newId = (int) sl_scalar('SELECT id FROM cdb_users WHERE username = :u', [':u' => $username]);
$db = new Conexion();
if ($isDriver && sl_columnExists('cdb_users', 'zone')) {
    $db->cdp_query('UPDATE cdb_users SET zone = :z, vehicle_type = :v WHERE id = :id');
    $db->bind(':z', mb_substr(trim((string) ($_POST['zone'] ?? '')), 0, 80)); $db->bind(':v', mb_substr(trim((string) ($_POST['vehicle_type'] ?? '')), 0, 20)); $db->bind(':id', $newId);
    $db->cdp_execute();
}
if (!empty($_POST['money']) && sl_tableExists('cdb_user_permission_overrides')) {
    $aid = (int) sl_scalar("SELECT id FROM cdb_user_module_actions WHERE action_name = 'view_financial_overview'");
    if ($aid && $user->cdp_hasPermission('view_financial_overview')) {
        $db->cdp_query('INSERT INTO cdb_user_permission_overrides (user_id, module_action_id, permitted) VALUES (:u, :a, 1) ON DUPLICATE KEY UPDATE permitted = 1');
        $db->bind(':u', $newId); $db->bind(':a', $aid); $db->cdp_execute();
    }
}
$sent = false;
if (!empty($_POST['notify'])) {
    $sent = sl_staffInviteMail((object) ['username' => $username, 'fname' => $fname, 'lname' => $lname, 'email' => $email, 'name_off' => $branch], $password);
}
cdp_activityLog(['module' => 'users', 'verb' => 'create', 'action' => 'invite', 'action_label' => 'Invited staff', 'outcome' => 'ok', 'entity_type' => 'user', 'entity_id' => $newId, 'entity_label' => $name, 'summary' => "invited $name ($email) as " . $roles[$role]->role_name . ($sent ? ', e-mail sent' : ''), 'meta' => ['username' => $username, 'role_id' => $role, 'email' => $email, 'mail_sent' => $sent]]);
sl_json(['success' => true, 'message' => $sent ? "Invitation sent to $email" : "Account created for $name" . (!empty($_POST['notify']) ? ' (e-mail could not be sent; share the username ' . $username . ' with them and reset the password)' : ''), 'redirect' => sl_route('StaffRoles')]);
