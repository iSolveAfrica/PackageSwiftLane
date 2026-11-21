<?php
/**
 * AuthHandler – login, logout, current-user endpoints.
 *
 * POST  /api/v1/auth/login   → issue API token (two steps when OTP is on:
 *                               first call returns otp_required + otp_challenge_id,
 *                               second call adds otp_challenge_id + otp_code)
 * POST  /api/v1/auth/logout  → revoke current token
 * GET   /api/v1/auth/me      → return authenticated user profile
 */
class AuthHandler
{
    // ── POST /api/v1/auth/login ───────────────────────────────────────────────

    public function login(): void
    {
        $data   = ApiResponse::getRequestData();
        $errors = ApiValidator::validate($data, [
            'username' => 'required|string|min:1|max:100',
            'password' => 'required|string|min:1|max:255',
        ]);

        if (!empty($errors)) {
            ApiResponse::validationError($errors);
        }

        $user     = new User();
        $username = cdp_sanitize($data['username']);

        // Brute-force protection shared with the web login (lib/User.php):
        // too many recent failures for this account or address are refused
        // before the password is even checked.
        if ($user->cdp_loginThrottled($username)) {
            ApiResponse::error('too_many_attempts', 'Too many failed sign-in attempts. Please wait 15 minutes and try again.', 429);
        }

        $status = $user->cdp_checkStatus($username, $data['password']);

        // 0 = wrong credentials, 2 = inactive
        if ($status === 0) {
            $user->cdp_loginRecordFailure($username);
            ApiResponse::error('invalid_credentials', 'Incorrect username or password.', 401);
        }
        if ($status === 2) {
            ApiResponse::error('account_inactive', 'Your account is not active.', 403);
        }
        $user->cdp_loginClearFailures($username);

        // Load user info
        $userInfo = $user->cdp_getUserInfo($username);
        if (!$userInfo) {
            ApiResponse::serverError('Could not load user profile.');
        }

        // Security: when one-time codes are switched on, the API must ask for
        // the same second factor as the web login; otherwise a stolen password
        // alone would buy a bearer token (up to 30 days) and bypass the 2FA.
        // Step 1: POST username + password            -> 401 otp_required + otp_challenge_id
        // Step 2: POST username + password + otp_challenge_id + otp_code -> token
        if (!function_exists('cdp_otpEnabled')) {
            require_once __DIR__ . '/../../../helpers/otp_settings.php';
        }
        if (cdp_otpEnabled()) {
            if (!class_exists('OtpService')) {
                require_once __DIR__ . '/../../../lib/OtpService.php';
            }
            $otp = new OtpService();
            $challengeId = isset($data['otp_challenge_id']) ? (int)$data['otp_challenge_id'] : 0;
            $otpCode     = isset($data['otp_code']) && is_scalar($data['otp_code']) ? trim((string)$data['otp_code']) : '';

            if ($challengeId > 0 && $otpCode !== '') {
                // Security: check the challenge belongs to this account before
                // verifying, so valid credentials for one account cannot burn the
                // attempts of (and lock) another user's pending login code.
                $chk = new Conexion();
                $chk->cdp_query("SELECT user_id FROM cdb_auth_otp_challenges WHERE id = :id AND purpose = 'login' LIMIT 1");
                $chk->bind(':id', $challengeId);
                $owner = $chk->cdp_registro();
                if (!$owner || (int)$owner->user_id !== (int)$userInfo->id) {
                    ApiResponse::error('otp_invalid', 'Invalid or used OTP request.', 401);
                }
                $verify = $otp->verifyChallenge($challengeId, $otpCode, 'login');
                if (empty($verify['ok']) || (int)($verify['user_id'] ?? 0) !== (int)$userInfo->id) {
                    ApiResponse::error('otp_invalid', $verify['error'] ?? 'Invalid one-time code.', 401);
                }
            } else {
                $challenge = $otp->createChallenge((int)$userInfo->id, 'login', [
                    'email' => $userInfo->email,
                    'api'   => true,
                ]);
                if (!empty($challenge['blocked'])) {
                    if (!empty($challenge['existing_id'])) {
                        // A code was sent less than a minute ago and is still valid.
                        ApiResponse::send([
                            'success' => false,
                            'error'   => 'otp_required',
                            'message' => 'Enter the one-time code already sent to you.',
                            'data'    => ['otp_challenge_id' => (int)$challenge['existing_id']],
                        ], 401);
                    }
                    ApiResponse::error('otp_rate_limited', (string)($challenge['error'] ?? 'Please wait before requesting a new code.'), 429);
                }
                $fullName = trim($userInfo->fname . ' ' . $userInfo->lname);
                $otp->sendOtpEmail($userInfo->email, $fullName, $challenge['code'], 'login');
                $otp->sendOtpWhatsApp($userInfo->email, $fullName, $challenge['code'], 'login');
                ApiResponse::send([
                    'success' => false,
                    'error'   => 'otp_required',
                    'message' => 'A one-time code has been sent. Repeat the request with otp_challenge_id and otp_code.',
                    'data'    => ['otp_challenge_id' => (int)$challenge['id']],
                ], 401);
            }
        }

        // Issue token
        $ttl      = isset($data['remember_me']) && $data['remember_me'] ? 24 * 30 : ApiAuth::DEFAULT_TTL_HOURS;
        $tokenName = isset($data['name']) ? cdp_sanitize($data['name']) : '';
        $auth      = new ApiAuth();
        $tokenData = $auth->createToken((int)$userInfo->id, $ttl, $tokenName);

        // Update last login
        $db = new Conexion();
        $db->cdp_query('UPDATE cdb_users SET lastlogin=:ts, lastip=:ip WHERE id=:id');
        $db->bind(':ts', date('Y-m-d H:i:s'));
        $db->bind(':ip', $_SERVER['REMOTE_ADDR'] ?? '');
        $db->bind(':id', (int)$userInfo->id);
        $db->cdp_execute();

        ApiResponse::success([
            'token'      => $tokenData['token'],
            'token_type' => 'Bearer',
            'expires_at' => $tokenData['expires_at'],
            'user'       => self::formatUser($userInfo),
        ]);
    }

    // ── POST /api/v1/auth/logout ──────────────────────────────────────────────

    public function logout(): void
    {
        $rawToken = ApiAuth::extractBearerToken();
        if (empty($rawToken)) {
            ApiResponse::unauthorized('No token to revoke.');
        }

        $auth = new ApiAuth();
        $auth->revokeToken($rawToken);

        ApiResponse::success(['revoked' => true]);
    }

    // ── GET /api/v1/auth/me ───────────────────────────────────────────────────

    public function me(): void
    {
        $userRow = ApiAuth::requireAuth();

        $db = new Conexion();
        $db->cdp_query('SELECT * FROM cdb_users WHERE id = :id LIMIT 1');
        $db->bind(':id', (int)$userRow->id);
        $db->cdp_execute();
        $full = $db->cdp_registro();

        if (!$full) {
            ApiResponse::notFound('User not found.');
        }

        ApiResponse::success(self::formatUser($full));
    }

    // ── Formatter ─────────────────────────────────────────────────────────────

    public static function formatUser(object $row): array
    {
        return [
            'id'        => (int)$row->id,
            'username'  => $row->username,
            'email'     => $row->email,
            'first_name'=> $row->fname,
            'last_name' => $row->lname,
            'phone'     => $row->phone ?? null,
            'userlevel' => (int)$row->userlevel,
            'active'    => (bool)(int)$row->active,
            'locker'    => $row->locker ?? null,
            'name_off'  => $row->name_off ?? null,
            'agency_id' => isset($row->agency_id) ? (int)$row->agency_id : null,
            'created'   => $row->created ?? null,
        ];
    }
}
