<?php
/**
 * AI profile studio (AiStudio board): the signed-in user's own name, phone (staff only), icon style and photo reset.
 * Photo upload goes to the existing endpoints (ajax/customers/customers_avatar_edit_ajax.php or
 * ajax/users/users_avatar_edit_ajax.php). Customers change their WhatsApp number through the OTP flow
 * on the profile page, never here.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/sl_auth.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../helpers/profile.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
if (CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
$uid = (int) $_SESSION['userid'];
$row = sl_row('SELECT id, fname, lname, phone, avatar, userlevel FROM cdb_users WHERE id = :id', [':id' => $uid]);
if (!$row) {
    sl_json(['success' => false, 'message' => 'Account not found'], 404);
}
$db = new Conexion();
$op = (string) ($_POST['op'] ?? '');
switch ($op) {
    case 'profile':
        $fname = preg_replace('/\s+/u', ' ', trim((string) ($_POST['fname'] ?? '')));
        $lname = preg_replace('/\s+/u', ' ', trim((string) ($_POST['lname'] ?? '')));
        $errors = [];
        if (mb_strlen($fname) < 2 || mb_strlen($fname) > 60 || !preg_match("/^[\p{L}\p{M} .'-]+$/u", $fname)) {
            $errors['fname'] = 'Use 2 to 60 letters';
        }
        if (mb_strlen($lname) < 2 || mb_strlen($lname) > 60 || !preg_match("/^[\p{L}\p{M} .'-]+$/u", $lname)) {
            $errors['lname'] = 'Use 2 to 60 letters';
        }
        $phone = null;
        if (!sl_userIs('client') && array_key_exists('phone', $_POST)) {
            $phone = trim((string) $_POST['phone']);
            if ($phone !== '' && !preg_match('/^\+?[0-9 ()-]{6,20}$/', $phone)) {
                $errors['phone'] = 'Enter a valid phone number';
            }
        }
        if ($errors) {
            sl_json(['success' => false, 'message' => 'Please check the highlighted fields', 'errors' => $errors], 422);
        }
        $db->cdp_query('UPDATE cdb_users SET fname = :f, lname = :l' . ($phone !== null ? ', phone = :p' : '') . ' WHERE id = :id');
        $db->bind(':f', $fname);
        $db->bind(':l', $lname);
        if ($phone !== null) {
            $db->bind(':p', $phone);
        }
        $db->bind(':id', $uid);
        $ok = $db->cdp_execute();
        if ($ok) {
            $_SESSION['name'] = $fname . ' ' . $lname;
            cdp_activityLog(['module' => 'profile', 'action' => 'profile.name', 'action_label' => 'Profile · Name changed', 'verb' => 'update', 'outcome' => 'ok', 'summary' => 'Changed own name', 'entity_type' => 'user', 'entity_id' => $uid, 'entity_label' => $fname . ' ' . $lname,
                'changes' => ['name' => ['from' => trim($row->fname . ' ' . $row->lname), 'to' => $fname . ' ' . $lname]] + ($phone !== null ? ['phone' => ['from' => (string) $row->phone, 'to' => $phone]] : [])]);
        }
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Saved. Your name now shows across the app.' : 'Could not save', 'reload' => (bool) $ok]);
        break;
    case 'icon':
        $style = (string) ($_POST['style'] ?? '');
        if (!in_array($style, ['init', 'ring', 'photo'], true)) {
            sl_json(['success' => false, 'message' => 'Unknown icon'], 422);
        }
        if ($style === 'photo' && (empty($row->avatar) || strpos((string) $row->avatar, 'blank') !== false)) {
            sl_json(['success' => false, 'message' => 'Upload a photo first'], 422);
        }
        sl_prefSet($uid, 'avatar_style', $style);
        sl_json(['success' => true, 'message' => 'Icon saved', 'reload' => true]);
        break;
    case 'reset_photo':
        if (empty($row->avatar) || strpos((string) $row->avatar, 'blank') !== false) {
            sl_json(['success' => true, 'message' => 'You are already using your initials']);
        }
        $db->cdp_query("UPDATE cdb_users SET avatar = '' WHERE id = :id");
        $db->bind(':id', $uid);
        $ok = $db->cdp_execute();
        if ($ok) {
            if (function_exists('cdp_profileHistoryLog')) {
                cdp_profileHistoryLog($uid, $uid, (string) $row->avatar, 'Avatar removed');
            }
            sl_prefSet($uid, 'avatar_style', 'init');
            cdp_activityLog(['module' => 'profile', 'action' => 'profile.avatar', 'action_label' => 'Profile · Avatar removed', 'verb' => 'update', 'outcome' => 'ok', 'summary' => 'Removed own profile photo (initials shown)', 'entity_type' => 'user', 'entity_id' => $uid,
                'changes' => ['avatar' => ['from' => (string) $row->avatar, 'to' => '']]]);
        }
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Photo removed. Your initials are shown instead.' : 'Could not reset the photo', 'reload' => (bool) $ok]);
        break;
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
