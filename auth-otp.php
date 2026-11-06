<?php
require_once("loader.php");
require_once("helpers/querys.php");
require_once("lib/OtpService.php");
require_once("helpers/activity_log.php");
require_once("helpers/otp_settings.php");
require_once("helpers/csrf.php");

$user = new User();
$core = new Core();
$db   = new Conexion();
$otp  = new OtpService();

$flow = isset($_GET['flow']) ? $_GET['flow'] : 'login';

$message     = '';
$error       = '';
$otpSuccess  = false;
$otpRedirect = 'index.php';

$sessionKey  = 'otp_' . $flow . '_challenge';
$challengeId = isset($_SESSION[$sessionKey]) ? (int) $_SESSION[$sessionKey] : 0;

// ── System-wide OTP switch OFF: complete the flow without a code ─────────────
// (password reset is excluded on purpose: the code is the proof of ownership).
if (!cdp_otpEnabled() && $flow !== 'forgot') {
    if ($flow === 'login' && !empty($_SESSION['otp_login_user_id'])) {
        $user->cdp_finalizeLoginById((int) $_SESSION['otp_login_user_id']);
        unset($_SESSION['otp_login_challenge'], $_SESSION['otp_login_user_id'], $_SESSION['otp_login_remember']);
        header('Location: index.php');
        exit;
    }
    if ($flow === 'signup' && !empty($_SESSION['signup_user_id'])) {
        $suid = (int) $_SESSION['signup_user_id'];
        $db->cdp_query("SELECT email, fname, lname, username, locker FROM cdb_users WHERE id=:id LIMIT 1");
        $db->bind(':id', $suid);
        $su = $db->cdp_registro();
        $db->cdp_query("UPDATE cdb_users SET registration_complete = 1 WHERE id = :id");
        $db->bind(':id', $suid);
        $db->cdp_execute();
        if ($su) {
            cdp_sendTemplateEmail(26, $su->email, [
                '[NAME]'     => trim($su->fname . ' ' . $su->lname),
                '[USERNAME]' => $su->username,
                '[LOCKER]'   => $su->locker,
                '[EMAIL]'    => $su->email,
            ]);
        }
        unset($_SESSION['otp_signup_challenge'], $_SESSION['signup_user_id']);
        header('Location: login.php?notice=registration_complete');
        exit;
    }
}

// Passed to JS so the timer can resume correctly after a page reload/resend
// Holds the Unix timestamp (seconds) when the current challenge was created,
// or 0 if there is no active challenge yet.
$challengeCreatedAt = 0;

/**
 * Move a file from the temp folder to the real uploads folder.
 * Returns the final relative path, or '' if no temp file was set.
 */
function cdp_commitUpload($tempDir, $tempName, $uploadDir, $prefix) {
    if (empty($tempName) || !file_exists($tempDir . $tempName)) {
        return '';
    }
    $ext       = pathinfo($tempName, PATHINFO_EXTENSION);
    $finalName = $prefix . '_' . uniqid() . '.' . $ext;
    rename($tempDir . $tempName, $uploadDir . $finalName);
    return $uploadDir . $finalName;
}

/**
 * Delete the temp folder and everything in it.
 */
function cdp_cleanupTempDir($tempDir) {
    if (!is_dir($tempDir)) return;
    foreach (glob($tempDir . '*') as $f) {
        unlink($f);
    }
    rmdir($tempDir);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !cdp_csrf_validate_request()) {
    $error = 'Your session expired. Reload the page and try again.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Resend ────────────────────────────────────────────────────────────────
    if (isset($_POST['resend'])) {
        $uid = 0;

        if ($flow === 'login' && !empty($_SESSION['otp_login_user_id'])) {
            $uid = (int) $_SESSION['otp_login_user_id'];

        } elseif ($flow === 'forgot' && !empty($_SESSION['otp_forgot_user_id'])) {
            $uid = (int) $_SESSION['otp_forgot_user_id'];

        } elseif ($flow === 'signup' && !empty($_SESSION['signup_user_id'])) {
            $suid = (int) $_SESSION['signup_user_id'];
            $db->cdp_query("SELECT email, fname, lname FROM cdb_users WHERE id=:id AND registration_complete=0 LIMIT 1");
            $db->bind(':id', $suid);
            $su = $db->cdp_registro();

            if ($su) {
                $challenge = $otp->createChallenge($suid, 'signup', ['email' => $su->email]);

                if (!empty($challenge['blocked'])) {
                    $error = $challenge['error'];
                } else {
                    $_SESSION[$sessionKey] = $challenge['id'];
                    $challengeId           = $challenge['id'];
                    $emailResult = $otp->sendOtpEmail(
                        $su->email,
                        $su->fname . ' ' . $su->lname,
                        $challenge['code'],
                        'signup'
                    );
                    if (!empty($emailResult['ok'])) {
                        $message = 'A new OTP has been sent to your email.';
                    } else {
                        $error = 'Email could not be sent: ' . (isset($emailResult['error']) ? $emailResult['error'] : 'unknown error') . '.';
                    }
                }
            }

        } elseif ($challengeId > 0) {
            $db->cdp_query("SELECT user_id FROM cdb_auth_otp_challenges WHERE id=:id LIMIT 1");
            $db->bind(':id', $challengeId);
            $ch = $db->cdp_registro();
            if ($ch) {
                $uid = (int) $ch->user_id;
            }
        }

        if ($uid > 0) {
            $db->cdp_query("SELECT * FROM cdb_users WHERE id=:id LIMIT 1");
            $db->bind(':id', $uid);
            $u = $db->cdp_registro();
            if ($u) {
                $challenge = $otp->createChallenge($u->id, $flow, [
                    'remember_me' => !empty($_SESSION['otp_login_remember']),
                ]);

                if (!empty($challenge['blocked'])) {
                    $error = $challenge['error'];
                } else {
                    $_SESSION[$sessionKey] = $challenge['id'];
                    $challengeId           = $challenge['id'];

                    $purpose     = ($flow === 'forgot') ? 'password reset' : $flow;
                    $emailResult = $otp->sendOtpEmail($u->email, $u->fname . ' ' . $u->lname, $challenge['code'], $purpose);
                    $waResult    = $otp->sendOtpWhatsApp($u->email, $u->fname . ' ' . $u->lname, $challenge['code'], $purpose);

                    $sentTo = [];
                    if (!empty($emailResult['ok'])) { $sentTo[] = 'email'; }
                    if (!empty($waResult['ok']))    { $sentTo[] = 'WhatsApp'; }

                    if ($sentTo) {
                        $message = 'A new OTP has been sent to your ' . implode(' and ', $sentTo) . '.';
                    }
                    // Surface a failed channel instead of silently swallowing it
                    if (empty($emailResult['ok'])) {
                        $error = 'Email could not be sent: ' . (isset($emailResult['error']) ? $emailResult['error'] : 'unknown error') . '.';
                    }
                }
            }
        }

    // ── Verify ────────────────────────────────────────────────────────────────
    } elseif (isset($_POST['otp_code'])) {
        $verify = $otp->verifyChallenge($challengeId, $_POST['otp_code'], $flow);

        if ($verify['ok']) {

            // ── Login flow ────────────────────────────────────────────────────
            if ($flow === 'login') {
                $user->cdp_finalizeLoginById($verify['user_id']);

                cdp_activityLogLogin(
                    true,
                    (string) ($_SESSION['username'] ?? ''),
                    (int) $verify['user_id']
                );

                if (!empty($_SESSION['otp_login_remember'])) {
                    $otp->rememberTrustedDevice($verify['user_id']);
                }

                unset(
                    $_SESSION['otp_login_challenge'],
                    $_SESSION['otp_login_remember'],
                    $_SESSION['otp_login_user_id']
                );

                $otpSuccess  = true;
                $otpRedirect = 'index.php';
                require_once 'helpers/sl_auth.php';
                $slAge = sl_passwordAge(sl_row('SELECT password_changed_at, created FROM cdb_users WHERE id = :id', [':id' => (int) $verify['user_id']]));
                if ($slAge && $slAge['expired']) {
                    $otpRedirect = 'forgot-reset.php?expired=1';
                }

            // ── Sign-up flow ──────────────────────────────────────────────────
            } elseif ($flow === 'signup') {
                // The account row already exists (created at sign-up, marked
                // incomplete). Verifying the email just completes the registration;
                // the account stays inactive + unapproved until an admin approves.
                $suid = (int) $verify['user_id'];
                if ($suid <= 0 && !empty($_SESSION['signup_user_id'])) {
                    $suid = (int) $_SESSION['signup_user_id'];
                }

                if ($suid <= 0) {
                    $error = 'Session expired. Please register again.';
                } else {
                    $db->cdp_query("SELECT email, fname, lname, username, locker FROM cdb_users WHERE id=:id LIMIT 1");
                    $db->bind(':id', $suid);
                    $su = $db->cdp_registro();

                    $db->cdp_query("UPDATE cdb_users SET registration_complete = 1 WHERE id = :id");
                    $db->bind(':id', $suid);
                    $db->cdp_execute();

                    // "Registration Received": account created, awaiting approval.
                    // Includes the generated locker id + virtual locker address.
                    if ($su) {
                        cdp_sendTemplateEmail(26, $su->email, [
                            '[NAME]'     => trim($su->fname . ' ' . $su->lname),
                            '[USERNAME]' => $su->username,
                            '[LOCKER]'   => $su->locker,
                            '[EMAIL]'    => $su->email,
                        ]);
                    }

                    unset($_SESSION['otp_signup_challenge'], $_SESSION['signup_user_id']);
                    $otpSuccess  = true;
                    $otpRedirect = 'login.php?notice=registration_complete';
                }

            // ── Forgot-password flow ──────────────────────────────────────────
            } elseif ($flow === 'forgot') {
                $token = $otp->createResetSession($verify['user_id'], 900);
                $_SESSION['forgot_reset_token'] = $token;
                unset($_SESSION['otp_forgot_challenge']);
                $otpSuccess  = true;
                $otpRedirect = 'forgot-reset.php';
            }

        } else {
            $error = $verify['error'];
        }
    }
}

// ── Signup: issue the OTP the moment the user lands here (GET) ──────────────
// Created on arrival, not at form submit — otherwise the success modal on the
// sign-up page could sit open long enough for the code to expire before the user
// reaches this page (the exact complaint behind "OTP not working"). Reuses a live
// challenge on refresh.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $flow === 'signup' && !empty($_SESSION['signup_user_id'])) {
    $suid = (int) $_SESSION['signup_user_id'];

    $challengeLive = false;
    if ($challengeId > 0) {
        $db->cdp_query("SELECT status, expires_at FROM cdb_auth_otp_challenges WHERE id=:id LIMIT 1");
        $db->bind(':id', $challengeId);
        $cur = $db->cdp_registro();
        if ($cur && $cur->status === 'pending' && strtotime($cur->expires_at) > time()) {
            $challengeLive = true;
        }
    }

    if (!$challengeLive) {
        $db->cdp_query("SELECT email, fname, lname FROM cdb_users WHERE id=:id AND registration_complete=0 LIMIT 1");
        $db->bind(':id', $suid);
        $su = $db->cdp_registro();
        if ($su) {
            $challenge = $otp->createChallenge($suid, 'signup', ['email' => $su->email]);
            if (empty($challenge['blocked'])) {
                $_SESSION[$sessionKey] = $challenge['id'];
                $challengeId           = $challenge['id'];
                $otp->sendOtpEmail($su->email, $su->fname . ' ' . $su->lname, $challenge['code'], 'signup');
            } elseif (!empty($challenge['existing_id'])) {
                $_SESSION[$sessionKey] = (int) $challenge['existing_id'];
                $challengeId           = (int) $challenge['existing_id'];
            }
        }
    }
}

// ── Success: go straight to the destination, no interstitial popup ──
if ($otpSuccess) {
    header('Location: ' . $otpRedirect);
    exit;
}

// ── Guard: no active OTP context (session expired or page visited directly) ──
// Without a flow in the session this page is a dead-end — the resend button has
// no user to send to and there's no challenge to count down to, so it just sits
// disabled with no timer. Send the user back to where they can start over.
$hasContext =
    ($flow === 'login'  && (!empty($_SESSION['otp_login_user_id'])  || $challengeId > 0)) ||
    ($flow === 'forgot' && (!empty($_SESSION['otp_forgot_user_id']) || $challengeId > 0)) ||
    ($flow === 'signup' && (!empty($_SESSION['signup_user_id'])     || $challengeId > 0));

if (!$hasContext) {
    $startPages = [
        'login'  => 'login.php',
        'forgot' => 'forgot-password.php',
        'signup' => 'sign-up.php',
    ];
    $dest = isset($startPages[$flow]) ? $startPages[$flow] : 'login.php';
    header('Location: ' . $dest . '?notice=otp_expired');
    exit;
}

// ── Fetch the created_at of the current challenge so the JS timer can resume ──
// NOTE: no status filter on purpose. The resend cooldown is keyed off when the
// code was *sent*, which is what the server rate-limit enforces too — so even
// after the code expires or the challenge gets locked, the timer must keep
// counting down and then re-enable Resend, instead of leaving it stuck disabled.
if ($challengeId > 0) {
    $db->cdp_query("SELECT created_at FROM cdb_auth_otp_challenges
        WHERE id=:id LIMIT 1");
    $db->bind(':id', $challengeId);
    $activeChallenge = $db->cdp_registro();
    if ($activeChallenge) {
        $challengeCreatedAt = strtotime($activeChallenge->created_at);
    }
}
?>
<?php
// ── Swift Lane design: LoginOtp board (login / sign-up code) or ForgotPassword board step 2 (reset code) ──
require_once 'helpers/sl_view.php';
require_once 'helpers/sl_auth.php';
$otpUser = null;
$otpUid = 0;
if ($flow === 'login' && !empty($_SESSION['otp_login_user_id'])) {
    $otpUid = (int) $_SESSION['otp_login_user_id'];
} elseif ($flow === 'forgot' && !empty($_SESSION['otp_forgot_user_id'])) {
    $otpUid = (int) $_SESSION['otp_forgot_user_id'];
} elseif ($flow === 'signup' && !empty($_SESSION['signup_user_id'])) {
    $otpUid = (int) $_SESSION['signup_user_id'];
} elseif ($challengeId > 0) {
    $otpUid = (int) sl_scalar('SELECT user_id FROM cdb_auth_otp_challenges WHERE id = :id', [':id' => $challengeId]);
}
if ($otpUid > 0) {
    $otpUser = sl_row('SELECT id, email, phone, fname, lname, created' . (sl_columnExists('cdb_users', 'password_changed_at') ? ', password_changed_at' : '') . ' FROM cdb_users WHERE id = :id', [':id' => $otpUid]);
}
$pics = sl_loginPicturesHtml($flow === 'forgot' ? 'forgot' : 'otp', 'fs');
$d = [
    'flow' => $flow,
    'step' => 2,
    'message' => $message,
    'error' => $error,
    'email' => sl_maskEmail((string) ($otpUser->email ?? '')),
    'phone' => sl_maskPhone((string) ($otpUser->phone ?? '')),
    'hasPhone' => !empty($otpUser->phone) && !empty($core->active_whatsapp),
    'countdownEnd' => $challengeCreatedAt > 0 ? $challengeCreatedAt + 60 : 0,
    'canResend' => $challengeCreatedAt <= 0 || $challengeCreatedAt + 60 <= time(),
    'remember' => !empty($_SESSION['otp_login_remember']),
    'age' => $flow === 'login' ? sl_passwordAge($otpUser) : null,
    'pics' => $pics,
    'site' => $core->site_name ?? 'Swift Lane',
    'back' => $flow === 'signup' ? sl_route('SignUp') : sl_route('Login'),
    'backLabel' => $flow === 'signup' ? 'Back to sign up' : 'Back to log in',
];
$board = $flow === 'forgot' ? 'ForgotPassword' : 'LoginOtp';
sl_render($board, ['title' => $flow === 'forgot' ? 'Verify your code' : 'Enter your login code', 'board' => $board, 'plain' => true, 'scripts' => ['dataJs/sl/auth.js']], $d);
