<?php
    ini_set('display_errors', 0);

    require_once("../loader.php");
    require_once __DIR__ . '/../helpers/csrf.php';
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !cdp_csrf_validate_request()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'errors' => 'Your form session expired. Refresh this page and try again.']);
        exit;
    }
    require_once("../lib/OtpService.php");

    $user = new User;
    $db = new Conexion;
    $errors = array();

    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    if (empty($email)) $errors['email'] = 'Enter a valid email address';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'The email address you entered is invalid.';

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => implode(' ', $errors)]);
        exit;
    }

    // Security: answer the same way whether or not the address has an account,
    // so this endpoint cannot be used to discover registered emails. A code is
    // only created and sent when the account exists.
    $genericReply = [
        'success'  => true,
        'messages' => 'If an account exists for that email, a reset code has been sent to it.',
        'redirect' => 'auth-otp.php?flow=forgot'
    ];

    $db->cdp_query("SELECT id,fname,lname,email FROM cdb_users WHERE email=:email LIMIT 1");
    $db->bind(':email', $email);
    $u = $db->cdp_registro();
    // Security: an unknown address (or a request we refuse) gets a decoy code
    // context, so auth-otp.php shows the same code form instead of bouncing to
    // "code expired" (which told an attacker the email had no account). Any code
    // entered against the decoy is simply rejected.
    $startDecoy = function () use ($email) {
        unset($_SESSION['otp_forgot_challenge'], $_SESSION['otp_forgot_user_id']);
        $_SESSION['otp_forgot_decoy'] = ['email' => $email, 'at' => time(), 'attempts' => 0];
    };

    if (!$u) {
        $startDecoy();
        echo json_encode($genericReply);
        exit;
    }
    unset($_SESSION['otp_forgot_decoy']);

    $otp = new OtpService;
    $challenge = $otp->createChallenge((int)$u->id, 'forgot', ['email' => $u->email]);

    if (!empty($challenge['blocked'])) {
        // Rate-limited. If a reset code was sent recently and is still pending,
        // reuse it so the user can continue instead of being stuck.
        if (!empty($challenge['existing_id'])) {
            $_SESSION['otp_forgot_challenge'] = (int)$challenge['existing_id'];
            $_SESSION['otp_forgot_user_id']   = (int)$u->id;
            echo json_encode($genericReply);
            exit;
        }
        // Hard cap reached: answer exactly like the unknown-email path so the
        // limit cannot be used to confirm the account exists; log it instead.
        error_log('forgot-password: OTP request refused for user ' . (int)$u->id . ': ' . (string)($challenge['error'] ?? ''));
        $startDecoy();
        echo json_encode($genericReply);
        exit;
    }

    $emailResult = $otp->sendOtpEmail($u->email, $u->fname . ' ' . $u->lname, $challenge['code'], 'password reset');
    $otp->sendOtpWhatsApp($u->email, $u->fname . ' ' . $u->lname, $challenge['code'], 'password reset');
    $_SESSION['otp_forgot_challenge'] = $challenge['id'];
    $_SESSION['otp_forgot_user_id']   = (int)$u->id;

    // A mail failure is logged server-side rather than shown: a distinct error
    // here would only ever appear for registered emails (account enumeration).
    // The user can still use Resend on the code page.
    if (empty($emailResult['ok'])) {
        error_log('forgot-password: reset code email failed for user ' . (int)$u->id . ': ' . (isset($emailResult['error']) ? $emailResult['error'] : 'unknown error'));
    }

    echo json_encode($genericReply);
