<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
/**
 * PasswordReset board (views/sl/pages/PasswordReset.php).
 *
 * Two ways in:
 *   1. Forgot-password flow: auth-otp.php?flow=forgot verified the code and left
 *      $_SESSION['forgot_reset_token'] (lib/OtpService.php, 15 minutes).
 *   2. Password rotation: a logged-in user whose password is older than
 *      cdb_sl_settings.password_rotation_days (login.php / auth-otp.php send
 *      them here with ?expired=1; any logged-in user may also change it here).
 * Both set cdb_users.password_changed_at and can sign out every trusted device.
 */
require_once("loader.php");
require_once("lib/OtpService.php");
require_once("helpers/csrf.php");
require_once("helpers/activity_log.php");
require_once 'helpers/sl_view.php';
require_once 'helpers/sl_auth.php';

$user = new User();
$core = new Core();
$db = new Conexion();
$otp = new OtpService();
$loggedIn = $user->cdp_loginCheck();
$mode = $loggedIn ? 'rotate' : 'forgot';

if (!$loggedIn && empty($_SESSION['forgot_reset_token'])) {
    header('Location: forgot-password.php');
    exit;
}
$row = $loggedIn ? sl_row('SELECT id, username, fname, lname, created' . (sl_columnExists('cdb_users', 'password_changed_at') ? ', password_changed_at' : '') . ' FROM cdb_users WHERE id = :id', [':id' => (int) $_SESSION['userid']]) : null;
$age = $loggedIn ? sl_passwordAge($row) : null;
$error = '';
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $pass2 = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';
    $signOut = !empty($_POST['sign_out_others']);
    if (!cdp_csrf_validate_request()) {
        $error = 'Your form session expired. Please try again.';
    } elseif (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[0-9]/', $pass) || !preg_match('/[^A-Za-z0-9]/', $pass)) {
        $error = 'Use at least one number and one symbol.';
    } elseif ($pass !== $pass2) {
        $error = 'Passwords do not match.';
    } elseif (CDP_APP_MODE_DEMO === true) {
        $error = 'This is a demo version, this action is not allowed.';
    } else {
        $userId = 0;
        if ($mode === 'forgot') {
            $userId = (int) $otp->consumeResetSession($_SESSION['forgot_reset_token']);
            if (!$userId) {
                $error = 'Reset session expired. Please restart the forgot-password flow.';
            }
        } else {
            $userId = (int) $_SESSION['userid'];
        }
        if ($userId > 0) {
            $hasCol = sl_columnExists('cdb_users', 'password_changed_at');
            $db->cdp_query('UPDATE cdb_users SET password = :password' . ($hasCol ? ', password_changed_at = NOW()' : '') . (sl_columnExists('cdb_users', 'force_password_reset') ? ', force_password_reset = 0' : '') . ' WHERE id = :id');
            $db->bind(':password', password_hash($pass, PASSWORD_DEFAULT));
            $db->bind(':id', $userId);
            $db->cdp_execute();
            if ($mode === 'forgot' || $signOut) {
                $otp->revokeAllTrustedDevices($userId);
            }
            unset($_SESSION['forgot_reset_token']);
            cdp_activityLog(['module' => 'auth', 'action' => 'password.change', 'action_label' => 'Password changed', 'verb' => 'update', 'outcome' => 'ok',
                'summary' => $mode === 'forgot' ? 'Password reset with a one-time code' : 'Password changed' . ($age && $age['expired'] ? ' (rotation)' : ''),
                'entity_type' => 'user', 'entity_id' => $userId, 'user_id' => $userId]);
            $done = true;
            if ($loggedIn) {
                $row = sl_row('SELECT id, username, fname, lname, created, password_changed_at FROM cdb_users WHERE id = :id', [':id' => $userId]);
                $age = sl_passwordAge($row);
            }
        }
    }
}

$period = sl_passwordRotationDays();
$pics = sl_loginPicturesHtml('forgot', 'fs');
sl_render('PasswordReset', ['title' => $done ? 'Password updated' : 'Choose a new password', 'board' => 'PasswordReset', 'plain' => true, 'scripts' => ['dataJs/sl/auth.js']], [
    'mode' => $mode,
    'done' => $done,
    'error' => $error,
    'expired' => $age ? $age['expired'] : (!empty($_GET['expired'])),
    'age' => $age,
    'period' => $period,
    'nextDue' => $period > 0 ? sl_date(date('Y-m-d', time() + $period * 86400)) : '',
    'signedOut' => $mode === 'forgot' || !empty($_POST['sign_out_others']),
    'continue' => $loggedIn ? sl_route('Main') : sl_route('Login') . '?notice=password_updated',
    'back' => $loggedIn ? sl_route('Main') : sl_route('ForgotPassword'),
    'help' => $loggedIn && sl_userIs('client') ? 'support.php' : sl_route('CsInbox'),
    'pics' => $pics,
    'site' => $core->site_name ?? 'Swift Lane',
]);
