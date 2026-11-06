<?php
/**
 * API connections: save credentials into their existing columns (blank secret = keep), switch on/off,
 * and test the connection against the provider. Secrets never leave the server.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../views/sl/data/api_connections.php';
require_login();
require_permission('manage_api_settings');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
$prov = sl_apiProviders();
$k = (string) ($_POST['provider'] ?? '');
if (!isset($prov[$k])) {
    sl_json(['success' => false, 'message' => 'Unknown connection'], 422);
}
$p = $prov[$k];
$op = (string) ($_POST['op'] ?? '');
$uid = (int) $_SESSION['userid'];

function sl_apiWrite(string $pkey, array $src, string $value, int $uid): bool
{
    $db = new Conexion();
    switch ($src[0]) {
        case 'settings':
            if (!preg_match('/^[a-z_]+$/', $src[1]) || !sl_columnExists('cdb_settings', $src[1])) return false;
            $db->cdp_query("UPDATE cdb_settings SET {$src[1]} = :v");
            $db->bind(':v', $value);
            return (bool) $db->cdp_execute();
        case 'metpay':
            if (!in_array($src[2], ['public_key', 'secret_key', 'paypal_client_id', 'is_active'], true)) return false;
            $db->cdp_query("UPDATE cdb_met_payment SET {$src[2]} = :v WHERE id = :id");
            $db->bind(':v', $value); $db->bind(':id', (int) $src[1]);
            return (bool) $db->cdp_execute();
        case 'sms':
            require_once CDP_ROOT . '/helpers/hubtel_sms.php';
            return cdp_smsSettingsEnsureTable() && cdp_smsSettingSet($src[1], $value, $uid);
        case 'ai':
            require_once CDP_ROOT . '/helpers/ai_assistant.php';
            return cdp_aiSettingSet($src[1], $value, $uid);
        case 'conn':
            if (!sl_tableExists('cdb_api_connections')) return false;
            $r = sl_apiConnRow($pkey);
            $cfg = $r ? (json_decode((string) $r->config, true) ?: []) : [];
            $sec = $r->secret_value ?? null;
            $en = (int) ($r->enabled ?? 0);
            if ($src[1] === 'secret') $sec = $value; elseif ($src[1] === 'enabled') $en = (int) ($value === '1'); else $cfg[$src[1]] = $value;
            $db->cdp_query('INSERT INTO cdb_api_connections (provider_key, category, enabled, config, secret_value, updated_by, updated_at) VALUES (:k, :c, :e, :cfg, :s, :u, NOW())
                ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), config = VALUES(config), secret_value = VALUES(secret_value), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)');
            $db->bind(':k', $pkey); $db->bind(':c', strtolower(sl_apiProviders()[$pkey]['cat'])); $db->bind(':e', $en); $db->bind(':cfg', json_encode($cfg)); $db->bind(':s', $sec); $db->bind(':u', $uid);
            return (bool) $db->cdp_execute();
    }
    return false;
}

/** HTTP check with a short timeout; returns [ok, ms, detail]. */
function sl_apiHttp(string $url, array $headers, ?string $auth = null): array
{
    $t = microtime(true);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_HTTPHEADER => $headers]);
    if ($auth !== null) curl_setopt($ch, CURLOPT_USERPWD, $auth);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $ms = (int) round((microtime(true) - $t) * 1000);
    if ($code === 0) return [false, $ms, 'No answer: ' . mb_substr($err, 0, 120)];
    return [$code >= 200 && $code < 300, $ms, 'HTTP ' . $code . ($code === 401 || $code === 403 ? ' · key refused' : '')];
}

if (CDP_APP_MODE_DEMO === true && $op !== 'test') {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
switch ($op) {
    case 'save':
        $changed = [];
        foreach ($p['fields'] as $f) {
            $v = trim((string) ($_POST[$f[0]] ?? ''));
            if ($f[2] && $v === '') continue; // secret left blank: keep the stored one
            if (!$f[2] && $v === sl_apiRead($k, $f[3])) continue;
            if (mb_strlen($v) > 500) sl_json(['success' => false, 'message' => $f[1] . ' is too long', 'errors' => [$f[0] => 'Too long']], 422);
            if (!sl_apiWrite($k, $f[3], $v, $uid)) sl_json(['success' => false, 'message' => 'Could not save ' . $f[1]], 500);
            $changed[] = $f[1];
        }
        // Names of the fields only: values (secrets) never go to the audit log.
        cdp_activityLog(['module' => 'settings', 'action' => 'api_connection', 'verb' => 'update', 'outcome' => 'ok', 'summary' => 'updated ' . $p['name'] . ($changed ? ': ' . implode(', ', $changed) : ' (no change)'), 'entity_type' => 'api_connection', 'entity_id' => $k, 'entity_label' => $p['name']]);
        sl_json(['success' => true, 'message' => $changed ? $p['name'] . ' saved' : 'Nothing changed']);
        break;
    case 'toggle':
        if (!$p['on']) sl_json(['success' => false, 'message' => 'This connection is on whenever it is configured'], 422);
        $on = !empty($_POST['value']) ? '1' : '0';
        $ok = sl_apiWrite($k, $p['on'], $on, $uid);
        if ($ok) cdp_activityLog(['module' => 'settings', 'action' => 'api_connection', 'verb' => 'update', 'outcome' => 'ok', 'summary' => ($on === '1' ? 'switched on ' : 'switched off ') . $p['name'], 'entity_type' => 'api_connection', 'entity_id' => $k, 'entity_label' => $p['name']]);
        sl_json(['success' => $ok, 'message' => $ok ? $p['name'] . ($on === '1' ? ' on' : ' off') : 'Could not save']);
        break;
    case 'test':
        $v = fn($i) => sl_apiRead($k, $p['fields'][$i][3]);
        switch ($k) {
            case 'stripe': $r = $v(1) ? sl_apiHttp('https://api.stripe.com/v1/balance', [], $v(1) . ':') : null; break;
            case 'paystack': $r = $v(1) ? sl_apiHttp('https://api.paystack.co/balance', ['Authorization: Bearer ' . $v(1)]) : null; break;
            case 'paypal': $r = $v(0) && $v(1) ? sl_apiHttp('https://api-m.paypal.com/v1/oauth2/token?grant_type=client_credentials', [], $v(0) . ':' . $v(1)) : null; break;
            case 'claude': $r = $v(0) ? sl_apiHttp('https://api.anthropic.com/v1/models', ['x-api-key: ' . $v(0), 'anthropic-version: 2023-06-01']) : null; break;
            case 'openai': $r = $v(0) ? sl_apiHttp('https://api.openai.com/v1/models', ['Authorization: Bearer ' . $v(0)]) : null; break;
            case 'openrouter': $r = $v(0) ? sl_apiHttp('https://openrouter.ai/api/v1/key', ['Authorization: Bearer ' . $v(0)]) : null; break;
            case 'twilio': $r = $v(0) && $v(1) ? sl_apiHttp('https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($v(0)) . '.json', [], $v(0) . ':' . $v(1)) : null; break;
            case 'google': $r = $v(0) ? sl_apiHttp('https://maps.googleapis.com/maps/api/geocode/json?address=Accra&key=' . rawurlencode($v(0)), []) : null; break;
            case 'whatsapp': $r = $v(0) ? sl_apiHttp($v(0), ['Authorization: Bearer ' . $v(1)]) : null; if ($r && preg_match('/HTTP (404|405)/', $r[2])) $r[0] = true; break;
            case 'mnotify':
                require_once CDP_ROOT . '/helpers/hubtel_sms.php';
                $t = microtime(true); $b = cdp_mnotifySmsBalance();
                $r = [!empty($b['success']), (int) round((microtime(true) - $t) * 1000), !empty($b['success']) ? 'Balance ' . number_format($b['balance']) : (string) $b['message']]; break;
            case 'smtp':
                $host = $v(0); $port = (int) $v(1) ?: 587;
                if ($host) { $t = microtime(true); $fp = @fsockopen(($v(2) === 'ssl' ? 'ssl://' : '') . $host, $port, $en, $es, 6); $r = [(bool) $fp, (int) round((microtime(true) - $t) * 1000), $fp ? 'Server answered on port ' . $port : 'No answer: ' . $es]; if ($fp) fclose($fp); } else $r = null; break;
            case 'slack': $r = $v(0) ? sl_apiHttp($v(0), []) : null; if ($r && strpos($r[2], 'HTTP 400') !== false) { $r[0] = true; $r[2] = 'Webhook reachable'; } break;
            default: $r = ['skip', 0, 'No live check for this carrier yet: tracking pulls use the saved key.'];
        }
        if ($r === null) sl_json(['success' => false, 'message' => 'Save the credentials first']);
        if ($r[0] === 'skip') sl_json(['success' => true, 'message' => $r[2]]);
        if (sl_tableExists('cdb_api_connections')) {
            $db = new Conexion();
            $db->cdp_query('INSERT INTO cdb_api_connections (provider_key, category, last_check_at, last_check_ok, last_check_ms, last_check_detail) VALUES (:k, :c, NOW(), :o, :ms, :d)
                ON DUPLICATE KEY UPDATE last_check_at = VALUES(last_check_at), last_check_ok = VALUES(last_check_ok), last_check_ms = VALUES(last_check_ms), last_check_detail = VALUES(last_check_detail)');
            $db->bind(':k', $k); $db->bind(':c', strtolower($p['cat'])); $db->bind(':o', (int) $r[0]); $db->bind(':ms', $r[1]); $db->bind(':d', mb_substr($r[2], 0, 250));
            $db->cdp_execute();
        }
        sl_json(['success' => (bool) $r[0], 'message' => $p['name'] . ': ' . ($r[0] ? 'connected' : 'failed') . ' · ' . $r[2] . ' · ' . $r[1] . ' ms']);
        break;
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
