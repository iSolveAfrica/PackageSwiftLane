<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software and its source code are proprietary and confidential    *
// * property of iSolveAfrica Ltd. and were developed specifically for     *
// * Swiftlane.                                                            *
// *                                                                       *
// * The software may not be copied, reproduced, modified, distributed,    *
// * sublicensed, published, or used in whole or in part except as         *
// * expressly permitted under the applicable license or written           *
// * agreement with iSolveAfrica Ltd. Any permitted copies or derivative   *
// * works must retain this copyright notice and all applicable            *
// * proprietary notices.                                                  *
// *                                                                       *
// *************************************************************************


require_once("loader.php");
require_once("lib/OtpService.php");
require_once("helpers/activity_log.php");
require_once("helpers/csrf.php");

$login = new User;
$core = new Core;

if ($login->cdp_loginCheck() == true) {
    header("location: index.php");
    exit;
}

$authFormError = '';
$submittedUsername = isset($_POST['username']) && is_string($_POST['username']) ? trim($_POST['username']) : '';
if (isset($_POST['login']) && !cdp_csrf_validate_request()) {
    $authFormError = 'Your sign-in session expired. Please try again.';
} elseif (isset($_POST['login']) && (!is_string($_POST['password'] ?? null) || $submittedUsername === '')) {
    $authFormError = 'Enter your username or email and password.';
} elseif (isset($_POST['login'])) {
    $otpService = new OtpService();
    $result = $login->cdp_login($submittedUsername, $_POST['password'], [
        'otp_service' => $otpService,
        'remember_me' => !empty($_POST['remember_me']),
    ]);

    if ($result === 'otp_required') {
        // Credentials were right; the session is not open until the OTP passes.
        cdp_activityLog([
            'module'     => 'auth',
            'verb'       => 'login',
            'outcome'    => 'success',
            'actor_name' => cdp_activityClip($_POST['username'], 150),
            'summary'    => 'Password accepted - one-time code sent',
        ]);
    } elseif ($result) {
        cdp_activityLogLogin(true, (string) ($_SESSION['username'] ?? $_POST['username']), (int) ($_SESSION['userid'] ?? 0));
    } else {
        cdp_activityLogLogin(false, (string) $_POST['username'], 0, 'wrong username or password');
    }

    if ($result) {
        if ($result === 'otp_required') {
            header("location: auth-otp.php?flow=login");
        } else {
            require_once 'helpers/sl_auth.php';
            $slNext = (string) ($_POST['next'] ?? '');
            $slDest = ($slNext !== '' && preg_match('#^[a-z0-9_./?=&%-]+$#i', $slNext) && strpos($slNext, '//') === false) ? $slNext : 'index.php';
            // Password rotation (PasswordReset board): an expired password is changed before anything else.
            $slAge = sl_passwordAge(sl_row('SELECT password_changed_at, created FROM cdb_users WHERE id = :id', [':id' => (int) ($_SESSION['userid'] ?? 0)]));
            header('location: ' . ($slAge && $slAge['expired'] ? 'forgot-reset.php?expired=1' : $slDest));
        }
        exit;
    }
}
?>


<?php
// ── Swift Lane design: Login board (views/sl/pages/Login.php) ────────────────
require_once 'helpers/sl_view.php';
require_once 'helpers/sl_auth.php';
$user = $login;
$errors = [];
if ($authFormError !== '') {
    $errors[] = $authFormError;
}
foreach ((array) $login->errors as $e) {
    $errors[] = (string) $e;
}
$notice = '';
switch ((string) ($_GET['notice'] ?? '')) {
    case 'otp_expired':
        $notice = 'Your verification session expired. Please log in again to receive a new code.';
        break;
    case 'registration_complete':
        $notice = 'Your email is verified and your registration is complete. Your account is now awaiting approval — you will get an email once it is activated.';
        break;
    case 'password_updated':
        $notice = 'Your password was updated. Log in with the new one.';
        break;
    case 'logged_out':
        $notice = 'You have been signed out.';
        break;
}
$pics = sl_loginPicturesHtml('login', 'lg');
sl_render('Login', ['title' => 'Log in', 'board' => 'Login', 'plain' => true, 'scripts' => ['dataJs/sl/auth.js']], [
    'errors' => $errors,
    'notice' => $notice,
    'username' => $submittedUsername,
    'remember' => !empty($_POST['remember_me']),
    'otp' => cdp_otpEnabled(),
    'pics' => $pics,
    'heading' => $pics['texts'][0] ?? 'Welcome back',
    'button' => $pics['texts'][1] ?? 'Log in',
    'site' => $core->site_name ?? 'Swift Lane',
    'next' => preg_match('#^[a-z0-9_./?=&%-]+$#i', (string) ($_GET['next'] ?? '')) && strpos((string) $_GET['next'], '//') === false ? (string) $_GET['next'] : '',
]);
