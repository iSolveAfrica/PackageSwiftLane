<?php
/**
 * Single sign-on: "Continue with Google" / "Continue with Microsoft" on the login page.
 *
 * OpenID Connect authorization-code flow with PKCE (sso.php). The ID token is taken straight from the
 * provider's token endpoint over TLS with our client secret, so its signature check is not needed
 * (OIDC Core 3.1.3.7); issuer, audience, expiry and nonce are checked.
 *
 * Only EXISTING, ACTIVE accounts can sign in this way: the provider's verified email must match one
 * account's email. Nothing is created here (new customers still register and get approved).
 * After the first match the provider's user id is linked in cdb_user_sso, so later sign-ins do not
 * depend on the email.
 *
 * Settings (cdb_sl_settings, Settings › Login page): auth_google_on, auth_google_client_id,
 * auth_google_secret, auth_ms_on, auth_ms_client_id, auth_ms_secret, auth_ms_tenant.
 */

if (!function_exists('sl_slSetting')) require_once CDP_ROOT . '/views/sl/data/ops_common.php';

function cdp_ssoProviders(): array
{
    $tenant = preg_replace('/[^a-z0-9.\-]/i', '', (string) sl_slSetting('auth_ms_tenant', 'common')) ?: 'common';
    return [
        'google' => [
            'label' => 'Google', 'on' => sl_slSetting('auth_google_on', '0') === '1',
            'client_id' => (string) sl_slSetting('auth_google_client_id', ''), 'secret' => (string) sl_slSetting('auth_google_secret', ''),
            'auth' => 'https://accounts.google.com/o/oauth2/v2/auth', 'token' => 'https://oauth2.googleapis.com/token',
            'scope' => 'openid email profile',
        ],
        'microsoft' => [
            'label' => 'Microsoft', 'on' => sl_slSetting('auth_ms_on', '0') === '1', 'tenant' => $tenant,
            'client_id' => (string) sl_slSetting('auth_ms_client_id', ''), 'secret' => (string) sl_slSetting('auth_ms_secret', ''),
            'auth' => 'https://login.microsoftonline.com/' . $tenant . '/oauth2/v2.0/authorize', 'token' => 'https://login.microsoftonline.com/' . $tenant . '/oauth2/v2.0/token',
            'scope' => 'openid email profile',
        ],
    ];
}

/** Providers switched on and fully configured (shown on the login page). */
function cdp_ssoActive(): array
{
    if (!function_exists('sl_tableExists') || !sl_tableExists('cdb_sl_settings')) return [];
    return array_filter(cdp_ssoProviders(), fn($p) => $p['on'] && $p['client_id'] !== '' && $p['secret'] !== '');
}

/** The address the provider sends people back to. Paste it into the Google / Microsoft app settings. */
function cdp_ssoRedirectUri(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $dir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    $dir = preg_replace('#/ajax(/.*)?$#', '', $dir);
    return ($https ? 'https' : 'http') . '://' . $host . $dir . '/sso.php';
}

function cdp_ssoEnsureTable(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    if (sl_tableExists('cdb_user_sso')) return;
    $db = new Conexion;
    $db->cdp_query("CREATE TABLE IF NOT EXISTS cdb_user_sso (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        provider VARCHAR(20) NOT NULL,
        subject VARCHAR(190) NOT NULL,
        email VARCHAR(190) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL,
        last_used_at DATETIME NULL,
        UNIQUE KEY uq_sso_subject (provider, subject),
        KEY idx_sso_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->cdp_execute();
}

/** POST to a token endpoint; returns the decoded JSON or throws. */
function cdp_ssoPost(string $url, array $fields): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($fields), CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) throw new RuntimeException('Could not reach the sign-in provider (' . $err . ')');
    $j = json_decode((string) $body, true);
    if (!is_array($j) || $code >= 400) throw new RuntimeException('The sign-in provider refused the request' . (is_array($j) && !empty($j['error']) ? ' (' . $j['error'] . ')' : ''));
    return $j;
}

function cdp_ssoClaims(string $idToken): array
{
    $parts = explode('.', $idToken);
    if (count($parts) !== 3) throw new RuntimeException('Bad ID token');
    $pad = strlen($parts[1]) % 4;
    $json = base64_decode(strtr($parts[1], '-_', '+/') . ($pad ? str_repeat('=', 4 - $pad) : ''), true);
    $c = $json === false ? null : json_decode($json, true);
    if (!is_array($c)) throw new RuntimeException('Bad ID token');
    return $c;
}

/**
 * Check the ID token for $key and return [subject, email]. The email is only returned when the
 * provider vouches for it (Google: email_verified; Microsoft: personal accounts or a single
 * organisation tenant, never "any organisation" where admins can set unverified emails).
 */
function cdp_ssoIdentity(string $key, array $p, array $c, string $nonce): array
{
    if (($c['aud'] ?? '') !== $p['client_id'] && !(is_array($c['aud'] ?? null) && in_array($p['client_id'], $c['aud'], true))) throw new RuntimeException('Token was not issued for this site');
    if ((int) ($c['exp'] ?? 0) < time() - 60) throw new RuntimeException('Sign-in took too long; try again');
    if ($nonce === '' || !hash_equals($nonce, (string) ($c['nonce'] ?? ''))) throw new RuntimeException('Sign-in could not be confirmed; try again');
    $sub = (string) ($c['sub'] ?? '');
    if ($sub === '') throw new RuntimeException('No user id in the token');
    if ($key === 'google') {
        if (!in_array($c['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true)) throw new RuntimeException('Token from an unexpected issuer');
        $email = !empty($c['email_verified']) ? (string) ($c['email'] ?? '') : '';
        return [$sub, $email];
    }
    // Microsoft: issuer is https://login.microsoftonline.com/{tenant id}/v2.0
    $tid = (string) ($c['tid'] ?? '');
    if (!preg_match('#^https://login\.microsoftonline\.com/([0-9a-f-]{36})/v2\.0$#', (string) ($c['iss'] ?? ''), $m) || $m[1] !== $tid) throw new RuntimeException('Token from an unexpected issuer');
    $tenant = strtolower($p['tenant']);
    if (!in_array($tenant, ['common', 'organizations', 'consumers'], true) && $tenant !== $tid && !str_contains($tenant, '.')) throw new RuntimeException('This Microsoft account belongs to another organisation');
    $personal = $tid === '9188040d-6c67-4c5b-b112-36a304b66dad';
    $singleOrg = !in_array($tenant, ['common', 'organizations', 'consumers'], true);
    $email = ($personal || $singleOrg || !empty($c['xms_edov'])) ? (string) ($c['email'] ?? '') : '';
    return [$m[1] . ':' . (string) ($c['oid'] ?? $sub), $email];
}
