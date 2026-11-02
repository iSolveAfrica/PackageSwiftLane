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
    if (empty($errors) && !$user->cdp_emailExists($email)) $errors['email'] = 'The email address you entered does not exist.';

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => implode(' ', $errors)]);
        exit;
    }

    $db->cdp_query("SELECT id,fname,lname,email FROM cdb_users WHERE email=:email LIMIT 1");
    $db->bind(':email', $email);
    $u = $db->cdp_registro();
    if (!$u) {
        echo json_encode(['success' => false, 'errors' => 'Unable to process request.']);
        exit;
    }

    $otp = new OtpService;
    $challenge = $otp->createChallenge((int)$u->id, 'forgot', ['email' => $u->email]);

    if (!empty($challenge['blocked'])) {
        // Rate-limited. If a reset code was sent recently and is still pending,
        // reuse it so the user can continue instead of being stuck.
        if (!empty($challenge['existing_id'])) {
            $_SESSION['otp_forgot_challenge'] = (int)$challenge['existing_id'];
            $_SESSION['otp_forgot_user_id']   = (int)$u->id;
            echo json_encode([
                'success'  => true,
                'messages' => 'A reset code was already sent recently. Enter it to continue.',
                'redirect' => 'auth-otp.php?flow=forgot'
            ]);
            exit;
        }
        echo json_encode(['success' => false, 'errors' => $challenge['error']]);
        exit;
    }

    $emailResult = $otp->sendOtpEmail($u->email, $u->fname . ' ' . $u->lname, $challenge['code'], 'password reset');
    $otp->sendOtpWhatsApp($u->email, $u->fname . ' ' . $u->lname, $challenge['code'], 'password reset');
    $_SESSION['otp_forgot_challenge'] = $challenge['id'];
    $_SESSION['otp_forgot_user_id']   = (int)$u->id;

    // Email is the primary channel for password reset — surface a failure instead
    // of silently claiming success.
    if (empty($emailResult['ok'])) {
        echo json_encode([
            'success' => false,
            'errors'  => 'Could not send the reset code by email: ' . (isset($emailResult['error']) ? $emailResult['error'] : 'unknown error') . '.'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'messages' => 'OTP sent to your email. Verify it to continue password reset.',
        'redirect' => 'auth-otp.php?flow=forgot'
    ]);
