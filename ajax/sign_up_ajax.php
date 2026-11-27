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
require_once("../helpers/querys.php");

$user = new User;
$core = new Core;
$db = new Conexion;

// Registration closed by the admin: sign-up.php already redirects away, but this
// endpoint creates the account, so it must refuse direct POSTs as well.
if (isset($core->reg_allowed) && (string) $core->reg_allowed === '0') {
    http_response_code(403);
    echo json_encode(['success' => false, 'errors' => 'Registration is currently closed.']);
    exit;
}

$error = "";

// Max file size: 5MB (adjust to taste)
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024);

/**
 * Security: a pending (not yet usable) row may only be resumed by whoever submits
 * the SAME email it was created with. A username or document number held by a
 * pending row under a different email must not be taken over, otherwise anyone
 * who knows it could overwrite that row's email and password.
 */
// Unverified sign-ups older than this are treated as abandoned: they no longer
// hold their username / document number and may be resumed by a new applicant.
define('SIGNUP_PENDING_HOLD_SEC', 24 * 3600);

function cdp_signupPendingHeldByOther($db, $field, $value, $email)
{
    // Only a live pending row holds the value: email-verified (awaiting approval)
    // or started recently. Otherwise an abandoned attempt would block the same
    // person retrying with a corrected email forever.
    $db->cdp_query("SELECT id FROM cdb_users WHERE $field = :v AND active = 0 AND approve = 0 AND email <> :e
                      AND (registration_complete = 1 OR created >= :cutoff) LIMIT 1");
    $db->bind(':v', $value);
    $db->bind(':e', $email);
    $db->bind(':cutoff', date('Y-m-d H:i:s', time() - SIGNUP_PENDING_HOLD_SEC));
    $db->cdp_execute();
    return $db->cdp_registro() ? true : false;
}

/**
 * True only when a *usable* account already uses this field value — i.e. one that
 * is approved and/or active. An account still in onboarding (approve=0 AND
 * active=0, whether email-unverified or verified-pending-approval) is NOT a
 * blocker; it gets resumed below. This keeps email, username and document_number
 * consistent: existing-but-not-yet-usable details just mean "send another OTP".
 */
function cdp_signupBlockingExists($db, $field, $value)
{
    $db->cdp_query("SELECT id FROM cdb_users WHERE $field = :v AND (active = 1 OR approve = 1) LIMIT 1");
    $db->bind(':v', $value);
    $db->cdp_execute();
    return $db->cdp_registro() ? true : false;
}

$requiredFields = array('terms', 'country', 'state', 'city', 'address', 'postal', 'username', 'email', 'phone', 'fname', 'lname', 'document_number', 'document_type', 'pass', 'pass2');
foreach ($requiredFields as $field) {
    if (empty($_POST[$field]) || !is_string($_POST[$field])) {
        $error = 'Please enter ' . str_replace('_', ' ', $field);
    }
}

if (empty($error)) {
    if (strlen($_POST['username']) < 4 || !ctype_alnum($_POST['username'])) {
        $error = $lang['messagesform80'];
    } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $error = $lang['messagesform79'];
    } elseif (!$user->cdp_isValidEmail($_POST['email'])) {
        $error = $lang['messagesform77'];
    } elseif (empty($_POST['pass'])) {
        $error = $lang['messagesform76'];
    } elseif (strlen($_POST['pass']) < 8) {
        $error = $lang['messagesform75'];
    } elseif ($_POST['pass'] != $_POST['pass2']) {
        $error = $lang['messagesform74'];
    } elseif (cdp_signupBlockingExists($db, 'username', cdp_sanitize($_POST['username']))) {
        $error = $lang['messagesform81'];
    } elseif (cdp_signupBlockingExists($db, 'document_number', cdp_sanitize($_POST['document_number']))) {
        $error = $lang['messagesform82'];
    } elseif (cdp_signupBlockingExists($db, 'email', cdp_sanitize($_POST['email']))) {
        $error = $lang['messagesform78'];
    } elseif (cdp_signupPendingHeldByOther($db, 'username', cdp_sanitize($_POST['username']), cdp_sanitize($_POST['email']))) {
        $error = $lang['messagesform81'];
    } elseif (cdp_signupPendingHeldByOther($db, 'document_number', cdp_sanitize($_POST['document_number']), cdp_sanitize($_POST['email']))) {
        $error = $lang['messagesform82'];
    } else {
        // An email-verified registration awaiting approval is not re-opened: a
        // resubmit would reset its password and verification state.
        $db->cdp_query("SELECT id FROM cdb_users WHERE email = :e AND active = 0 AND approve = 0 AND registration_complete = 1 LIMIT 1");
        $db->bind(':e', cdp_sanitize($_POST['email']));
        $db->cdp_execute();
        if ($db->cdp_registro()) {
            $error = $lang['messagesform78'];
        }
    }
}

// Optional referral code (sign-up.php?ref=CODE fills it): an unknown code is said plainly before anything is created.
$referralCode = '';
if (empty($error) && trim((string) ($_POST['referral_code'] ?? '')) !== '') {
    require_once __DIR__ . '/../helpers/sl_view.php';
    require_once __DIR__ . '/../helpers/loyalty.php';
    if (sl_loyaltyReferralOn()) {
        $referralCode = sl_loyaltyCleanCode((string) $_POST['referral_code']);
        if (!sl_loyaltyReferrer($referralCode)) {
            $error = 'We could not find the referral code ' . htmlspecialchars($referralCode, ENT_QUOTES, 'UTF-8') . '. Check it with your friend, or leave the field empty.';
            $referralCode = '';
        }
    }
}

if (empty($error)) {
    $settings = cdp_getSettingsCourier();
    $prefixlk = $settings->prefix_locker;

    // Uniqueness: the locker digits pre-filled on the form are only a suggestion;
    // claim them (or the next free ones) so no two customers share a locker.
    require_once __DIR__ . '/../helpers/unique_ids.php';
    $_POST['locker'] = cdp_claimLockerDigits($_POST['locker'] ?? '');

    $allowedMimeTypes  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    // Uploads go straight to the real folder; stored path mirrors the legacy
    // format ('../assets/uploads/users/...') so existing display code works. Empty
    // string when nothing was uploaded.
    $uploadDir = '../assets/uploads/users/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $avatarPath = '';
    if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['avatar']['size'] > MAX_UPLOAD_BYTES) {
            echo json_encode(['success' => false, 'errors' => 'Avatar image must be under 5MB.']);
            exit;
        }
        $mime = mime_content_type($_FILES['avatar']['tmp_name']);
        $ext  = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        if (!in_array($mime, $allowedMimeTypes) || !in_array($ext, $allowedExtensions)) {
            echo json_encode(['success' => false, 'errors' => 'Invalid avatar file type.']);
            exit;
        }
        $finalName = 'avatar_' . uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $finalName);
        $avatarPath = '../assets/uploads/users/' . $finalName;
    }

    $documentPhotoPath = '';
    if (!empty($_FILES['document_photo']['name']) && $_FILES['document_photo']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['document_photo']['size'] > MAX_UPLOAD_BYTES) {
            echo json_encode(['success' => false, 'errors' => 'Document photo must be under 5MB.']);
            exit;
        }
        $mime = mime_content_type($_FILES['document_photo']['tmp_name']);
        $ext  = strtolower(pathinfo($_FILES['document_photo']['name'], PATHINFO_EXTENSION));
        if (!in_array($mime, $allowedMimeTypes) || !in_array($ext, $allowedExtensions)) {
            echo json_encode(['success' => false, 'errors' => 'Invalid document photo file type.']);
            exit;
        }
        $finalName = 'docphoto_' . uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['document_photo']['tmp_name'], $uploadDir . $finalName);
        $documentPhotoPath = '../assets/uploads/users/' . $finalName;
    }

    // Resume an existing not-yet-verified account registered with this SAME email
    // instead of erroring — it just means "needs (another) OTP". Matching on
    // username or document alone would let a stranger hijack the row (see
    // cdp_signupPendingHeldByOther above); usable accounts were rejected above.
    $db->cdp_query("SELECT id FROM cdb_users
                    WHERE active = 0 AND approve = 0 AND registration_complete = 0
                      AND email = :e
                    ORDER BY id DESC LIMIT 1");
    $db->bind(':e', cdp_sanitize($_POST['email']));
    $resume = $db->cdp_registro();
    $reusedAbandoned = false;

    if (!$resume) {
        // An abandoned (never email-verified, older than the hold window) row
        // with this username or document is reused rather than duplicated; it
        // gets the new email and must be verified again by OTP, so no live
        // registration can be taken over this way.
        $db->cdp_query("SELECT id FROM cdb_users
                        WHERE active = 0 AND approve = 0 AND registration_complete = 0
                          AND (created < :cutoff OR created IS NULL)
                          AND (username = :u OR document_number = :d)
                        ORDER BY id DESC LIMIT 1");
        $db->bind(':cutoff', date('Y-m-d H:i:s', time() - SIGNUP_PENDING_HOLD_SEC));
        $db->bind(':u', cdp_sanitize($_POST['username']));
        $db->bind(':d', cdp_sanitize($_POST['document_number']));
        $resume = $db->cdp_registro();
        if ($resume) {
            $reusedAbandoned = true;
            // Restart the hold window so the reused row is held for this applicant.
            $db->cdp_query("UPDATE cdb_users SET created = :c WHERE id = :id");
            $db->bind(':c', date('Y-m-d H:i:s'));
            $db->bind(':id', (int) $resume->id);
            $db->cdp_execute();
        }
    }

    if ($resume) {
        // ── Resume: refresh the editable fields on the existing incomplete row ──
        $userId = (int) $resume->id;

        // Reset registration_complete to 0: they're re-doing the flow, so the email
        // must be re-verified by OTP (which sets it back to 1 on success).
        $db->cdp_query('UPDATE cdb_users SET
                username = :username, password = :password, email = :email,
                fname = :fname, lname = :lname, document_number = :document_number,
                document_type = :document_type, phone = :phone, company = :company,
                terms = :terms, registration_complete = 0
            WHERE id = :id');
        $db->bind(':username',        cdp_sanitize($_POST['username']));
        $db->bind(':password',        password_hash($_POST['pass'], PASSWORD_DEFAULT));
        $db->bind(':email',           cdp_sanitize($_POST['email']));
        $db->bind(':fname',           cdp_sanitize($_POST['fname']));
        $db->bind(':lname',           cdp_sanitize($_POST['lname']));
        $db->bind(':document_number', cdp_sanitize($_POST['document_number']));
        $db->bind(':document_type',   cdp_sanitize($_POST['document_type']));
        $db->bind(':phone',           cdp_sanitize($_POST['phone']));
        $db->bind(':company',         cdp_sanitize($_POST['company']));
        $db->bind(':terms',           isset($_POST['terms']) ? $_POST['terms'] : '');
        $db->bind(':id',              $userId);
        $db->cdp_execute();

        if ($reusedAbandoned) {
            // The abandoned row may belong to a different person: drop their
            // avatar and ID photo (uploads are optional, so they would otherwise
            // survive) and give the row the locker just claimed for this applicant.
            $db->cdp_query('UPDATE cdb_users SET avatar = :a, document_photo = :d, locker = :l WHERE id = :id');
            $db->bind(':a', $avatarPath);
            $db->bind(':d', $documentPhotoPath);
            $db->bind(':l', cdp_sanitize($prefixlk . ' ' . $_POST['locker']));
            $db->bind(':id', $userId);
            $db->cdp_execute();
        } elseif ($avatarPath !== '') {
            $db->cdp_query('UPDATE cdb_users SET avatar = :a WHERE id = :id');
            $db->bind(':a', $avatarPath);
            $db->bind(':id', $userId);
            $db->cdp_execute();
        }
        if (!$reusedAbandoned && $documentPhotoPath !== '') {
            $db->cdp_query('UPDATE cdb_users SET document_photo = :d WHERE id = :id');
            $db->bind(':d', $documentPhotoPath);
            $db->bind(':id', $userId);
            $db->cdp_execute();
        }

        // Replace the address with the latest submission.
        $db->cdp_query('DELETE FROM cdb_senders_addresses WHERE user_id = :id');
        $db->bind(':id', $userId);
        $db->cdp_execute();

        // Ensure the onboarding checklist row exists (update_phone=0 forces the
        // WhatsApp-number step on first login after approval).
        $db->cdp_query("SELECT id FROM cdb_user_details_update_check WHERE user_id = :id LIMIT 1");
        $db->bind(':id', $userId);
        if (!$db->cdp_registro()) {
            $db->cdp_query("INSERT INTO cdb_user_details_update_check
                (user_id, update_address, update_phone, update_document) VALUES (:id, 1, 0, 1)");
            $db->bind(':id', $userId);
            $db->cdp_execute();
        }
    } else {
        // ── Fresh: create the incomplete account row (all flags 0) ─────────────
        $db->cdp_query('INSERT INTO cdb_users
            (username, password, locker, userlevel, email, fname, lname,
             document_number, document_type, created, phone, active, approve,
             registration_complete, terms, avatar, document_photo, company)
            VALUES
            (:username, :password, :locker, :userlevel, :email, :fname, :lname,
             :document_number, :document_type, :created, :phone, :active, :approve,
             :registration_complete, :terms, :avatar, :document_photo, :company)');
        $db->bind(':username',               cdp_sanitize($_POST['username']));
        $db->bind(':password',               password_hash($_POST['pass'], PASSWORD_DEFAULT));
        $db->bind(':locker',                 cdp_sanitize($prefixlk . ' ' . $_POST['locker']));
        $db->bind(':userlevel',              1);
        $db->bind(':email',                  cdp_sanitize($_POST['email']));
        $db->bind(':fname',                  cdp_sanitize($_POST['fname']));
        $db->bind(':lname',                  cdp_sanitize($_POST['lname']));
        $db->bind(':document_number',        cdp_sanitize($_POST['document_number']));
        $db->bind(':document_type',          cdp_sanitize($_POST['document_type']));
        $db->bind(':created',                date('Y-m-d H:i:s'));
        $db->bind(':phone',                  cdp_sanitize($_POST['phone']));
        $db->bind(':active',                 0);
        $db->bind(':approve',                0);
        $db->bind(':registration_complete',  0);
        $db->bind(':terms',                  isset($_POST['terms']) ? $_POST['terms'] : '');
        $db->bind(':avatar',                 $avatarPath);
        $db->bind(':document_photo',         $documentPhotoPath);
        $db->bind(':company',                cdp_sanitize($_POST['company']));
        $db->cdp_execute();

        $userId = (int) $db->dbh->lastInsertId();

        if (!$userId) {
            echo json_encode(['success' => false, 'errors' => 'Could not start your registration. Please try again.']);
            exit;
        }

        // address + document captured at signup (1 = done); phone left at 0 so the
        // post-approval login forces WhatsApp-number verification (account setup).
        $db->cdp_query("INSERT INTO cdb_user_details_update_check
            (user_id, update_address, update_phone, update_document) VALUES (:user_id, 1, 0, 1)");
        $db->bind(':user_id', $userId);
        $db->cdp_execute();
    }

    cdp_insertAddressCustomer([
        'user_id' => $userId,
        'address' => cdp_sanitize($_POST['address']),
        'country' => cdp_sanitize($_POST['country']),
        'city'    => cdp_sanitize($_POST['city']),
        'state'   => cdp_sanitize($_POST['state']),
        'postal'  => cdp_sanitize($_POST['postal']),
    ]);

    // The friend who brought this customer gets the referral once the new account ships (helpers/loyalty.php).
    if ($referralCode !== '') {
        sl_loyaltyLinkReferral($userId, $referralCode, 'signup');
    }

    // The OTP is created + emailed when the user lands on the OTP page (see
    // auth-otp.php), so the code and its countdown start then — not now, where a
    // success modal could sit open and let the code expire before they arrive.
    // System-wide OTP switch OFF: no email code — the registration is complete
    // now (still inactive until an admin approves it, exactly as after a code).
    require_once __DIR__ . '/../helpers/otp_settings.php';
    if (!cdp_otpEnabled()) {
        $db->cdp_query("UPDATE cdb_users SET registration_complete = 1 WHERE id = :id");
        $db->bind(':id', $userId);
        $db->cdp_execute();
        $db->cdp_query("SELECT email, fname, lname, username, locker FROM cdb_users WHERE id = :id LIMIT 1");
        $db->bind(':id', $userId);
        $su = $db->cdp_registro();
        if ($su && function_exists('cdp_sendTemplateEmail')) {
            cdp_sendTemplateEmail(26, $su->email, [
                '[NAME]'     => trim($su->fname . ' ' . $su->lname),
                '[USERNAME]' => $su->username,
                '[LOCKER]'   => $su->locker,
                '[EMAIL]'    => $su->email,
            ]);
        }
        unset($_SESSION['signup_user_id'], $_SESSION['otp_signup_challenge']);
        echo json_encode([
            'success'  => true,
            'messages' => 'Success! Your registration has been received and is awaiting approval.',
            'redirect' => 'login.php?notice=registration_complete'
        ]);
        exit;
    }

    $_SESSION['signup_user_id'] = $userId;
    unset($_SESSION['otp_signup_challenge']);

    echo json_encode([
        'success'  => true,
        'messages' => 'Success! Verify your email to complete your registration.',
        'redirect' => 'auth-otp.php?flow=signup'
    ]);

    exit;
}

echo json_encode([
    'success' => false,
    'errors'  => $error
]);
