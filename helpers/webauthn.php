<?php
/**
 * Passkeys (WebAuthn): sign in with the phone's fingerprint, face or screen lock.
 *
 * Plain PHP + OpenSSL, no extra library. Supports the two algorithms phones and
 * laptops use: ES256 (COSE -7, P-256) and RS256 (COSE -257). Attestation is not
 * requested ("none"), so registration trusts the browser's own user check.
 *
 * Storage: cdb_passkeys (migrations/2026_11_26_passkeys_sso.sql; created on demand).
 * Flow: ajax/sl/passkey_ajax.php issues one-time challenges kept in the session and
 * calls cdp_waVerifyRegistration() / cdp_waVerifyAssertion() below.
 *
 * Passkeys only work on https (or http://localhost) and are bound to the host name
 * (the "relying party id"), so they keep working as long as the site keeps its domain.
 */

function cdp_waB64uEncode(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function cdp_waB64uDecode(string $s): string
{
    $s = strtr($s, '-_', '+/');
    $pad = strlen($s) % 4;
    if ($pad) $s .= str_repeat('=', 4 - $pad);
    $out = base64_decode($s, true);
    return $out === false ? '' : $out;
}

/** Feature switch (Settings › Login page). On unless turned off. */
function cdp_passkeysEnabled(): bool
{
    if (!function_exists('sl_slSetting')) require_once CDP_ROOT . '/views/sl/data/ops_common.php';
    return (string) sl_slSetting('auth_passkeys', '1') !== '0';
}

/** Relying party id: the host name the browser sees (no port). */
function cdp_waRpId(): string
{
    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost')));
    return preg_replace('/:\d+$/', '', $host) ?: 'localhost';
}

/** The browser reports its origin; it must be this host over https (or http on localhost). */
function cdp_waOriginOk(string $origin): bool
{
    $p = parse_url($origin);
    if (!$p || empty($p['host']) || empty($p['scheme'])) return false;
    if (strtolower($p['host']) !== cdp_waRpId()) return false;
    $local = in_array($p['host'], ['localhost', '127.0.0.1'], true);
    return $p['scheme'] === 'https' || ($local && $p['scheme'] === 'http');
}

function cdp_waEnsureTable(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    if (!function_exists('sl_tableExists')) require_once CDP_ROOT . '/helpers/sl_view.php';
    if (sl_tableExists('cdb_passkeys')) return;
    $db = new Conexion;
    $db->cdp_query("CREATE TABLE IF NOT EXISTS cdb_passkeys (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        credential_id VARCHAR(255) NOT NULL,
        public_key TEXT NOT NULL,
        alg INT NOT NULL DEFAULT -7,
        sign_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
        label VARCHAR(120) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL,
        last_used_at DATETIME NULL,
        UNIQUE KEY uq_passkey_cred (credential_id),
        KEY idx_passkey_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->cdp_execute();
}

/** One-time challenge kept in the session for $purpose ('reg' or 'login'), valid 5 minutes. */
function cdp_waNewChallenge(string $purpose): string
{
    $c = cdp_waB64uEncode(random_bytes(32));
    $_SESSION['wa_challenge'][$purpose] = ['c' => $c, 't' => time()];
    return $c;
}

function cdp_waTakeChallenge(string $purpose): string
{
    $row = $_SESSION['wa_challenge'][$purpose] ?? null;
    unset($_SESSION['wa_challenge'][$purpose]);
    if (!is_array($row) || time() - (int) $row['t'] > 300) return '';
    return (string) $row['c'];
}

// ── CBOR (only what WebAuthn needs) ──────────────────────────────────────────

/** Decode one CBOR item from $s at $o; returns [value, newOffset]. Throws on malformed input. */
function cdp_cborDecode(string $s, int $o = 0): array
{
    $len = strlen($s);
    if ($o >= $len) throw new RuntimeException('cbor: truncated');
    $b = ord($s[$o++]);
    $mt = $b >> 5;
    $ai = $b & 31;
    $arg = function () use (&$o, $s, $len, $ai) {
        if ($ai < 24) return $ai;
        $n = [24 => 1, 25 => 2, 26 => 4, 27 => 8][$ai] ?? 0;
        if (!$n || $o + $n > $len) throw new RuntimeException('cbor: bad length');
        $v = 0;
        for ($i = 0; $i < $n; $i++) $v = ($v << 8) | ord($s[$o + $i]);
        $o += $n;
        return $v;
    };
    switch ($mt) {
        case 0: return [$arg(), $o];
        case 1: return [-1 - $arg(), $o];
        case 2:
        case 3:
            $n = $arg();
            if ($o + $n > $len) throw new RuntimeException('cbor: truncated string');
            return [substr($s, $o, $n), $o + $n];
        case 4:
            $n = $arg(); $out = [];
            for ($i = 0; $i < $n; $i++) { [$v, $o] = cdp_cborDecode($s, $o); $out[] = $v; }
            return [$out, $o];
        case 5:
            $n = $arg(); $out = [];
            for ($i = 0; $i < $n; $i++) { [$k, $o] = cdp_cborDecode($s, $o); [$v, $o] = cdp_cborDecode($s, $o); $out[is_scalar($k) ? $k : (string) $i] = $v; }
            return [$out, $o];
        case 6: $arg(); return cdp_cborDecode($s, $o); // tag: return the tagged item
        case 7:
            if ($ai === 20) return [false, $o];
            if ($ai === 21) return [true, $o];
            if ($ai === 22 || $ai === 23) return [null, $o];
            if ($ai >= 25 && $ai <= 27) { $n = [25 => 2, 26 => 4, 27 => 8][$ai]; return [null, $o + $n]; }
            throw new RuntimeException('cbor: unsupported simple value');
    }
    throw new RuntimeException('cbor: unsupported type');
}

// ── keys ────────────────────────────────────────────────────────────────────

function cdp_derLen(int $n): string
{
    if ($n < 128) return chr($n);
    $b = '';
    while ($n > 0) { $b = chr($n & 255) . $b; $n >>= 8; }
    return chr(0x80 | strlen($b)) . $b;
}

function cdp_derInt(string $bytes): string
{
    $bytes = ltrim($bytes, "\0");
    if ($bytes === '' || (ord($bytes[0]) & 0x80)) $bytes = "\0" . $bytes;
    return "\x02" . cdp_derLen(strlen($bytes)) . $bytes;
}

/** COSE public key (decoded CBOR map) to [PEM, alg]. Only EC2/P-256 (ES256) and RSA (RS256). */
function cdp_waCoseToPem(array $k): array
{
    $kty = $k[1] ?? null;
    $alg = (int) ($k[3] ?? 0);
    if ($kty === 2 && $alg === -7 && ($k[-1] ?? null) === 1) {
        $x = (string) ($k[-2] ?? ''); $y = (string) ($k[-3] ?? '');
        if (strlen($x) !== 32 || strlen($y) !== 32) throw new RuntimeException('Bad EC key');
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . "\x04" . $x . $y;
    } elseif ($kty === 3 && $alg === -257) {
        $n = (string) ($k[-1] ?? ''); $e = (string) ($k[-2] ?? '');
        if ($n === '' || $e === '') throw new RuntimeException('Bad RSA key');
        $rsa = cdp_derInt($n) . cdp_derInt($e);
        $rsa = "\x30" . cdp_derLen(strlen($rsa)) . $rsa;
        $bit = "\x03" . cdp_derLen(strlen($rsa) + 1) . "\0" . $rsa;
        $algId = hex2bin('300d06092a864886f70d0101010500');
        $der = "\x30" . cdp_derLen(strlen($algId) + strlen($bit)) . $algId . $bit;
    } else {
        throw new RuntimeException('This device uses a key type we do not support');
    }
    return ["-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n", $alg];
}

/** Parse authenticator data. Returns rpIdHash, flags, signCount and, when present, credId + COSE key. */
function cdp_waAuthData(string $ad): array
{
    if (strlen($ad) < 37) throw new RuntimeException('Short authenticator data');
    $out = ['rpIdHash' => substr($ad, 0, 32), 'flags' => ord($ad[32]), 'signCount' => unpack('N', substr($ad, 33, 4))[1]];
    if ($out['flags'] & 0x40) { // attested credential data
        if (strlen($ad) < 55) throw new RuntimeException('Short credential data');
        $idLen = unpack('n', substr($ad, 53, 2))[1];
        $out['credId'] = substr($ad, 55, $idLen);
        [$key] = cdp_cborDecode($ad, 55 + $idLen);
        if (!is_array($key)) throw new RuntimeException('Bad credential key');
        $out['cose'] = $key;
    }
    return $out;
}

/** Checks shared by both ceremonies on clientDataJSON and the rpId hash / flags. */
function cdp_waCheckClient(string $clientJson, string $type, string $challenge): void
{
    $c = json_decode($clientJson, true);
    if (!is_array($c)) throw new RuntimeException('Bad client data');
    if (($c['type'] ?? '') !== $type) throw new RuntimeException('Wrong ceremony');
    if ($challenge === '' || !hash_equals($challenge, (string) ($c['challenge'] ?? ''))) throw new RuntimeException('The request expired. Try again.');
    if (!cdp_waOriginOk((string) ($c['origin'] ?? ''))) throw new RuntimeException('This sign-in came from another website');
}

function cdp_waCheckAuth(array $a): void
{
    if (!hash_equals(hash('sha256', cdp_waRpId(), true), $a['rpIdHash'])) throw new RuntimeException('Passkey belongs to another website');
    if (!($a['flags'] & 0x01)) throw new RuntimeException('No user presence');
    if (!($a['flags'] & 0x04)) throw new RuntimeException('Fingerprint, face or screen lock was not used');
}

/**
 * Registration. $p holds base64url clientDataJSON and attestationObject from the browser.
 * Returns ['credId' => base64url, 'pem' => ..., 'alg' => ..., 'signCount' => ...].
 */
function cdp_waVerifyRegistration(array $p, string $challenge): array
{
    $clientJson = cdp_waB64uDecode((string) ($p['clientDataJSON'] ?? ''));
    cdp_waCheckClient($clientJson, 'webauthn.create', $challenge);
    [$att] = cdp_cborDecode(cdp_waB64uDecode((string) ($p['attestationObject'] ?? '')));
    if (!is_array($att) || !isset($att['authData'])) throw new RuntimeException('Bad attestation');
    $a = cdp_waAuthData((string) $att['authData']);
    cdp_waCheckAuth($a);
    if (empty($a['credId']) || empty($a['cose'])) throw new RuntimeException('No credential in the response');
    [$pem, $alg] = cdp_waCoseToPem($a['cose']);
    return ['credId' => cdp_waB64uEncode($a['credId']), 'pem' => $pem, 'alg' => $alg, 'signCount' => (int) $a['signCount']];
}

/**
 * Sign-in. $p holds base64url id, clientDataJSON, authenticatorData, signature. $row is the stored
 * cdb_passkeys row for that credential id. Returns the new sign counter.
 */
function cdp_waVerifyAssertion(array $p, string $challenge, object $row): int
{
    $clientJson = cdp_waB64uDecode((string) ($p['clientDataJSON'] ?? ''));
    cdp_waCheckClient($clientJson, 'webauthn.get', $challenge);
    $authData = cdp_waB64uDecode((string) ($p['authenticatorData'] ?? ''));
    $a = cdp_waAuthData($authData);
    cdp_waCheckAuth($a);
    $sig = cdp_waB64uDecode((string) ($p['signature'] ?? ''));
    $ok = openssl_verify($authData . hash('sha256', $clientJson, true), $sig, (string) $row->public_key, OPENSSL_ALGO_SHA256);
    if ($ok !== 1) throw new RuntimeException('Passkey signature did not match');
    // A counter that goes backwards means a cloned key. Synced passkeys (iCloud, Google) always send 0.
    $count = (int) $a['signCount'];
    if ($count > 0 && (int) $row->sign_count > 0 && $count <= (int) $row->sign_count) throw new RuntimeException('This passkey looks cloned; it was blocked');
    return $count;
}

/** A readable name for the device that created a passkey, from the user agent. */
function cdp_waDeviceLabel(): string
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $os = preg_match('/iPhone/i', $ua) ? 'iPhone' : (preg_match('/iPad/i', $ua) ? 'iPad' : (preg_match('/Android/i', $ua) ? 'Android phone' : (preg_match('/Mac OS X/i', $ua) ? 'Mac' : (preg_match('/Windows/i', $ua) ? 'Windows PC' : 'This device'))));
    $br = preg_match('/Edg\//', $ua) ? 'Edge' : (preg_match('/Chrome\//', $ua) ? 'Chrome' : (preg_match('/Safari\//', $ua) ? 'Safari' : (preg_match('/Firefox\//', $ua) ? 'Firefox' : '')));
    return $os . ($br ? ' · ' . $br : '');
}
