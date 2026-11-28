<?php
// *************************************************************************
// * Notifications & Messages > Settings (comms_settings.php).             *
// *                                                                       *
// * POST only, CSRF checked by require_login. Administrators (rank 3+)    *
// * change sending rules, the status-message owner and King. Provider     *
// * keys (WhatsApp, SMS, e-mail), the webhook key and the connection      *
// * checks need a super admin or the manage_api_settings permission.      *
// * Secrets are never sent back: a blank secret field keeps the saved one.*
// * Every save writes one activity-log entry (no secret values in it).    *
// * Only the fields in the POST change; clear_secrets=a,b removes saved   *
// * secrets on purpose. Provider addresses must be public https hosts     *
// * (sl_commsHostCheck); a changed host needs its secret typed again, so  *
// * a saved token or password is never sent to a new server.             *
// *************************************************************************

require_once(__DIR__ . '/../../loader.php');
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_once(__DIR__ . '/../../helpers/querys.php');
require_once(__DIR__ . '/../../helpers/comms.php');
require_once(__DIR__ . '/../../helpers/whatsapp.php');
require_once(__DIR__ . '/../../helpers/message_log.php');
require_once(__DIR__ . '/../../helpers/hubtel_sms.php');
require_once(__DIR__ . '/../../helpers/mnotify_sms.php');
require_once(__DIR__ . '/../../views/sl/data/comms_settings.php');
if (is_file(__DIR__ . '/../../helpers/activity_log.php')) {
    require_once(__DIR__ . '/../../helpers/activity_log.php');
}

header('Content-Type: application/json; charset=utf-8');

$out = function ($ok, $message, array $extra = [], $code = 200) {
    http_response_code($code);
    echo json_encode(array_merge(['success' => (bool) $ok, 'ok' => (bool) $ok, 'message' => (string) $message], $extra));
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    $out(false, 'Use POST.', [], 405);
}
$acc = sl_commsSettingsAccess($user);
if (!$acc['view']) {
    $out(false, 'Administrators only.', [], 403);
}
$action = (string) ($_POST['action'] ?? '');
// Switching a channel on or off needs no keys: every administrator may do it (set_channel).
$secretActions = ['save_whatsapp', 'save_sms', 'save_email', 'rotate_key', 'check_whatsapp', 'check_smtp', 'mnotify_balance'];
if (in_array($action, $secretActions, true) && !$acc['secrets']) {
    $out(false, 'Only a super admin (or someone with the API settings permission) can change provider keys.', [], 403);
}
$demo = defined('CDP_APP_MODE_DEMO') && CDP_APP_MODE_DEMO === true;
if ($demo && in_array($action, ['save_whatsapp', 'save_sms', 'save_email', 'rotate_key'], true)) {
    $out(false, 'Demo mode: provider settings cannot be changed.');
}
if (!cdp_commEnsureTables()) {
    $out(false, 'The message tables could not be created. The database user needs the CREATE privilege once.', [], 500);
}

$uid = (int) ($_SESSION['userid'] ?? 0);
$P = function ($k, $d = '') { return trim((string) ($_POST[$k] ?? $d)); };
$has = function ($k) { return array_key_exists($k, $_POST); };
$clear = array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['clear_secrets'] ?? '')))));
$cleared = function ($k) use ($clear) { return in_array($k, $clear, true); };
$hostOf = function ($url) { return strtolower((string) parse_url((string) $url, PHP_URL_HOST)); };
$on = function ($k) { return (string) ($_POST[$k] ?? '0') === '1'; };
/** One activity-log line per save; $fields are names only, never values. */
$log = function ($what, array $fields = [], array $meta = []) {
    if (function_exists('cdp_activityLog')) {
        cdp_activityLog(['module' => 'settings', 'verb' => 'update', 'action' => 'comms.settings.' . $what,
            'label' => 'Message settings · ' . $what, 'meta' => array_merge(['fields' => array_values($fields)], $meta)]);
    }
};
/** UPDATE cdb_settings with bound values (single-row table). */
$saveCore = function (array $cols) {
    if (!$cols) {
        return true;
    }
    $set = [];
    $bind = [];
    foreach ($cols as $c => $v) {
        $c = preg_replace('/[^a-z_]/', '', $c);
        $set[] = "$c = :$c";
        $bind[":$c"] = $v;
    }
    $db = new Conexion;
    $db->cdp_query('UPDATE cdb_settings SET ' . implode(', ', $set));
    foreach ($bind as $k => $v) {
        $db->bind($k, $v);
    }
    return $db->cdp_execute() !== false;
};
/** A GET with a short timeout; never throws. $pin: IPv4 addresses the host was checked against (no DNS rebinding). */
$httpGet = function ($url, array $headers = [], array $pin = []) {
    if (!function_exists('curl_init')) {
        return ['code' => 0, 'body' => '', 'err' => 'cURL is not installed on this server.'];
    }
    $ch = curl_init($url);
    $opt = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 12, CURLOPT_HTTPHEADER => $headers, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP];
    $u = parse_url($url);
    if ($pin && !empty($u['host'])) {
        $port = (int) ($u['port'] ?? (strtolower((string) ($u['scheme'] ?? '')) === 'http' ? 80 : 443));
        $opt[CURLOPT_RESOLVE] = [$u['host'] . ':' . $port . ':' . $pin[0]];
        $opt[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
    }
    curl_setopt_array($ch, $opt);
    $body = curl_exec($ch);
    $r = ['code' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE), 'body' => (string) $body, 'err' => curl_error($ch)];
    curl_close($ch);
    return $r;
};

switch ($action) {

    // ---------------------------------------------------------------- WhatsApp
    case 'save_whatsapp':
        $cs = cdp_getSettingsCourier();
        $s = cdp_commSettings(true);
        $prov = $has('wa_provider') ? ($P('wa_provider') === 'cloud' ? 'cloud' : 'ultramsg') : ($s['wa_provider'] === 'cloud' ? 'cloud' : 'ultramsg');
        $active = $has('active_whatsapp') ? $on('active_whatsapp') : (int) ($cs->active_whatsapp ?? 0) === 1;
        $errors = [];
        $core = [];
        $opsSave = [];
        $opsClear = [];
        if ($has('active_whatsapp')) {
            $core['active_whatsapp'] = $active ? 1 : 0;
        }
        if ($prov === 'ultramsg') {
            // Only the UltraMsg fields are checked and saved: the hidden Cloud fields are left as they are.
            $savedUrl = rtrim(trim((string) ($cs->api_ws_url ?? '')), '/');
            $savedTok = (string) ($cs->api_ws_token ?? '');
            $url = $has('api_ws_url') ? rtrim($P('api_ws_url'), '/') : $savedUrl;
            $token = $P('api_ws_token');
            if ($url !== '' && $url !== $savedUrl && ($e = sl_commsProviderUrlError($url)) !== '') {
                $errors['api_ws_url'] = $e;
            }
            if ($token !== '' && !preg_match('/^[\x21-\x7E]{4,200}$/', $token)) {
                $errors['api_ws_token'] = 'The token has characters UltraMsg does not issue.';
            }
            // A saved token only ever goes to the server it was typed for.
            if ($url !== '' && $token === '' && $savedTok !== '' && !$cleared('api_ws_token') && $hostOf($url) !== $hostOf($savedUrl)) {
                $errors['api_ws_token'] = 'The instance address changed: type the token again.';
            }
            $tokAfter = $cleared('api_ws_token') ? '' : ($token !== '' ? $token : $savedTok);
            if ($active && ($url === '' || $tokAfter === '')) {
                $errors['active_whatsapp'] = 'Enter the instance address and token before switching WhatsApp on.';
            }
            if ($has('api_ws_url')) {
                $core['api_ws_url'] = $url;
            }
            if ($token !== '') {
                $core['api_ws_token'] = $token;
            } elseif ($cleared('api_ws_token')) {
                $core['api_ws_token'] = '';
            }
        } else {
            // Only the Cloud fields are checked and saved.
            $phoneId = $has('wa_cloud_phone_id') ? $P('wa_cloud_phone_id') : (string) $s['wa_cloud_phone_id'];
            if ($phoneId !== '' && !preg_match('/^\d{5,30}$/', $phoneId)) {
                $errors['wa_cloud_phone_id'] = 'The phone number ID is digits only.';
            }
            $ver = $has('wa_cloud_version') ? $P('wa_cloud_version') : (string) $s['wa_cloud_version'];
            if ($ver !== '' && !preg_match('/^v\d{1,3}\.\d{1,2}$/', $ver)) {
                $errors['wa_cloud_version'] = 'Like v20.0.';
            }
            $cloudTok = $P('wa_cloud_token');
            if ($cloudTok !== '' && !preg_match('/^[\x21-\x7E]{10,1000}$/', $cloudTok)) {
                $errors['wa_cloud_token'] = 'The access token has characters Meta does not issue.';
            }
            $tokAfter = $cleared('wa_cloud_token') ? '' : ($cloudTok !== '' ? $cloudTok : (string) $s['wa_cloud_token']);
            if ($active && ($phoneId === '' || $tokAfter === '')) {
                $errors['active_whatsapp'] = 'Enter the phone number ID and access token before switching WhatsApp on.';
            }
            if ($has('wa_cloud_phone_id')) {
                $opsSave['wa_cloud_phone_id'] = $phoneId;
            }
            if ($has('wa_cloud_version')) {
                $opsSave['wa_cloud_version'] = $ver ?: 'v20.0';
            }
            foreach (['wa_cloud_token', 'wa_cloud_app_secret'] as $k) {
                if ($P($k) !== '') {
                    $opsSave[$k] = $P($k);
                } elseif ($cleared($k)) {
                    $opsClear[] = $k;
                }
            }
            if ($has('wa_cloud_verify')) {
                $verify = $P('wa_cloud_verify');
                $opsSave['wa_cloud_verify'] = $verify !== '' && preg_match('/^•+$/u', $verify) ? (string) $s['wa_cloud_verify'] : $verify;
            }
        }
        if ($errors) {
            $out(false, reset($errors), ['errors' => $errors]);
        }
        if ($has('wa_provider')) {
            $opsSave['wa_provider'] = $prov;
        }
        $ok = $saveCore($core);
        cdp_commSaveSettings($opsSave);
        foreach ($opsClear as $k) {
            $ok = cdp_opsSettingSet('comms', $k, '', $uid) && $ok;
        }
        $log('whatsapp', array_merge(array_keys($core), array_keys($opsSave), $opsClear), ['provider' => $prov, 'active' => $active, 'cleared' => $opsClear ?: ($cleared('api_ws_token') ? ['api_ws_token'] : [])]);
        $out($ok, $ok ? 'WhatsApp settings saved.' : 'Not saved.', ['status' => sl_commsChannelStatus()]);

    case 'set_channel':
        // The on/off switch alone (no keys involved), for every administrator: an emergency stop needs no secret.
        $key = $P('key');
        if (!in_array($key, ['active_whatsapp', 'active_sms'], true)) {
            $out(false, 'Unknown switch.', [], 400);
        }
        $want = $P('value') === '1';
        $st = sl_commsChannelStatus();
        if ($want && $key === 'active_whatsapp' && $st['whatsapp']['state'] === 'Not set up') {
            $out(false, 'WhatsApp has no provider keys yet. Someone with the API settings permission enters them first.');
        }
        if ($want && $key === 'active_sms') {
            $any = false;
            foreach (array_keys(cdp_smsProviders()) as $p) {
                $any = $any || cdp_smsProviderReady($p);
            }
            if (!$any) {
                $out(false, 'No SMS provider is set up and switched on yet. Someone with the API settings permission does that first.');
            }
        }
        $ok = $saveCore([$key => $want ? 1 : 0]);
        $log($key === 'active_whatsapp' ? 'whatsapp switch' : 'sms switch', [$key], ['on' => $want]);
        $label = $key === 'active_whatsapp' ? 'WhatsApp' : 'SMS';
        $out($ok, $ok ? $label . ' switched ' . ($want ? 'on.' : 'off.') : 'Not saved.', ['status' => sl_commsChannelStatus()]);

    case 'rotate_key':
        $ok = cdp_opsSettingSet('comms', 'webhook_key', bin2hex(random_bytes(16)), $uid);
        $log('webhook key rotated');
        $c = cdp_commConnection(true);
        $msg = 'New key made. Paste the new address in the provider dashboard.';
        $pushed = false;
        // UltraMsg: give the instance the new address straight away, so inbound messages and ticks keep arriving.
        if ($ok && cdp_commSetting('wa_provider') !== 'cloud' && is_file(__DIR__ . '/../../helpers/ultramsg.php')) {
            require_once(__DIR__ . '/../../helpers/ultramsg.php');
            $GLOBALS['cdp_um_cfg_fresh'] = true;
            $um = function_exists('cdp_umConfig') ? cdp_umConfig() : ['configured' => false];
            if (!empty($um['configured']) && sl_commsProviderUrlError((string) $um['base']) === '' && function_exists('cdp_umWebhookSetup')) {
                $r = cdp_umWebhookSetup((string) $c['webhook_url']);
                $pushed = !empty($r['ok']);
                $msg = $pushed ? 'New key made and sent to UltraMsg: nothing to paste.'
                    : 'New key made, but UltraMsg did not take the new address (' . mb_substr((string) ($r['error'] ?? 'no answer'), 0, 120) . '). Paste it in the UltraMsg dashboard.';
            }
        }
        $out($ok, $ok ? $msg : 'Not saved.', ['webhook' => (string) $c['webhook_url'], 'pushed' => $pushed]);

    case 'check_whatsapp':
        $cs = cdp_getSettingsCourier();
        $s = cdp_commSettings(true);
        if ($s['wa_provider'] === 'cloud') {
            if ($s['wa_cloud_phone_id'] === '' || $s['wa_cloud_token'] === '') {
                $out(false, 'Save the phone number ID and access token first.');
            }
            $r = $httpGet('https://graph.facebook.com/' . preg_replace('/[^a-z0-9.]/i', '', $s['wa_cloud_version'] ?: 'v20.0') . '/' . rawurlencode($s['wa_cloud_phone_id'])
                . '?fields=display_phone_number,verified_name,quality_rating', ['Authorization: Bearer ' . $s['wa_cloud_token']]);
            $j = json_decode($r['body'], true);
            if ($r['code'] >= 200 && $r['code'] < 300 && is_array($j) && !empty($j['display_phone_number'])) {
                $out(true, 'Connected: ' . $j['display_phone_number'] . (!empty($j['verified_name']) ? ' (' . $j['verified_name'] . ')' : '') . '.');
            }
            $out(false, 'Meta did not accept the keys: ' . mb_substr((string) ($j['error']['message'] ?? ($r['err'] !== '' ? $r['err'] : 'HTTP ' . $r['code'])), 0, 200));
        }
        $base = rtrim(trim((string) ($cs->api_ws_url ?? '')), '/');
        $tok = (string) ($cs->api_ws_token ?? '');
        if ($base === '' || $tok === '') {
            $out(false, 'Save the instance address and token first.');
        }
        // The token only goes to a public https provider address.
        if (($e = sl_commsProviderUrlError($base)) !== '') {
            $out(false, 'The saved instance address is not allowed: ' . $e);
        }
        $hc = sl_commsHostCheck($hostOf($base));
        $r = $httpGet($base . '/instance/status?token=' . rawurlencode($tok), [], $hc['ips']);
        $j = json_decode($r['body'], true);
        if ($r['err'] !== '' || $r['code'] === 0) {
            $out(false, 'UltraMsg could not be reached: ' . mb_substr($r['err'] ?: 'no answer', 0, 160) . '.');
        }
        if (!is_array($j)) {
            $out(false, 'UltraMsg answered HTTP ' . $r['code'] . ' with something unexpected. Check the instance address.');
        }
        if (!empty($j['error'])) {
            $e = is_scalar($j['error']) ? (string) $j['error'] : json_encode($j['error']);
            $out(false, 'UltraMsg: ' . mb_substr($e, 0, 200));
        }
        $st = $j['status']['accountStatus']['status'] ?? ($j['accountStatus']['status'] ?? ($j['status'] ?? ''));
        $sub = $j['status']['accountStatus']['substatus'] ?? ($j['accountStatus']['substatus'] ?? '');
        $st = is_scalar($st) ? strtolower((string) $st) : '';
        if ($st === 'authenticated') {
            $out(true, 'Connected: the phone is linked' . ($sub !== '' && is_scalar($sub) ? ' (' . mb_substr((string) $sub, 0, 40) . ')' : '') . '.', ['state' => 'authenticated']);
        }
        $out(false, $st === 'qr' || $st === 'loading' || $st === 'initialize'
            ? 'The keys work, but no phone is linked. Use "Link your phone" and scan the QR code.'
            : 'UltraMsg says: ' . ($st !== '' ? mb_substr($st, 0, 40) : 'unknown state') . '.', ['state' => $st]);

    // --------------------------------------------------------------------- SMS
    case 'save_sms':
        $providers = cdp_smsProviders();
        $cs = cdp_getSettingsCourier();
        $active = $has('active_sms') ? ($on('active_sms') ? 1 : 0) : (int) ($cs->active_sms ?? 0);
        $fallback = $has('sms_fallback') ? ($on('sms_fallback') ? '1' : '0') : (cdp_smsFallbackOn() ? '1' : '0');
        $default = $has('sms_default_provider') ? $P('sms_default_provider') : cdp_smsDefaultProvider();
        // Fields missing from the POST keep their saved value.
        $in = [];
        foreach (['hubtel_client_id', 'hubtel_client_secret', 'hubtel_sender_id', 'mnotify_api_key', 'mnotify_sender_id'] as $k) {
            $in[$k] = $has($k) ? $P($k) : (in_array($k, ['hubtel_client_secret', 'mnotify_api_key'], true) ? '' : (string) cdp_smsSetting($k));
        }
        $enabled = [];
        foreach (array_keys($providers) as $p) {
            $enabled[$p] = $has($p . '_enabled') ? ($on($p . '_enabled') ? '1' : '0') : (cdp_smsProviderEnabled($p) ? '1' : '0');
        }
        // Same rules as Tools > SMS (ajax/tools/config_sms_hubtel_ajax.php).
        $errors = [];
        if ($in['hubtel_client_id'] !== '' && !preg_match('/^[A-Za-z0-9_\-]{1,128}$/', $in['hubtel_client_id'])) {
            $errors['hubtel_client_id'] = 'The Client ID can only hold letters, digits, - and _.';
        }
        if ($in['hubtel_client_secret'] !== '' && !preg_match('/^[\x21-\x7E]{1,128}$/', $in['hubtel_client_secret'])) {
            $errors['hubtel_client_secret'] = 'The Client Secret has characters Hubtel does not issue.';
        }
        if ($in['mnotify_api_key'] !== '' && !preg_match('/^[A-Za-z0-9_\-]{8,128}$/', $in['mnotify_api_key'])) {
            $errors['mnotify_api_key'] = 'The API key can only hold letters, digits, - and _.';
        }
        foreach (['hubtel_sender_id', 'mnotify_sender_id'] as $f) {
            if ($in[$f] !== '' && !preg_match('/^[A-Za-z0-9 ]{1,11}$/', $in[$f])) {
                $errors[$f] = 'The Sender ID is up to 11 letters, digits or spaces.';
            }
        }
        if (!array_key_exists($default, $providers)) {
            $errors['sms_default_provider'] = 'Choose the default SMS provider.';
        }
        $hub = cdp_hubtelSmsConfig();
        $mno = cdp_mnotifySmsConfig();
        $hubSecret = $cleared('hubtel_client_secret') ? '' : ($in['hubtel_client_secret'] !== '' ? $in['hubtel_client_secret'] : (string) $hub['client_secret']);
        $mnKey = $cleared('mnotify_api_key') ? '' : ($in['mnotify_api_key'] !== '' ? $in['mnotify_api_key'] : (string) ($mno['api_key'] ?? ''));
        $final = [
            'hubtel' => $in['hubtel_client_id'] !== '' && $hubSecret !== '' && $in['hubtel_sender_id'] !== '',
            'mnotify' => $mnKey !== '' && $in['mnotify_sender_id'] !== '',
        ];
        foreach ($providers as $p => $label) {
            if (($enabled[$p] ?? '0') === '1' && empty($final[$p])) {
                $errors[$p . '_enabled'] = 'Enter every ' . $label . ' credential before switching ' . $label . ' on.';
            }
        }
        if (!isset($errors['sms_default_provider']) && ($enabled[$default] ?? '0') !== '1' && in_array('1', $enabled, true)) {
            $errors['sms_default_provider'] = 'The default provider must be switched on.';
        }
        if ($active === 1 && !in_array('1', $enabled, true)) {
            $errors['active_sms'] = 'Switch on at least one SMS provider before switching SMS on.';
        }
        if ($errors) {
            $out(false, reset($errors), ['errors' => $errors]);
        }
        if (!cdp_smsSettingsEnsureTable()) {
            $out(false, 'The SMS settings table could not be created.');
        }
        $ok = true;
        $changed = [];
        foreach (['hubtel_client_id', 'hubtel_sender_id', 'mnotify_sender_id'] as $k) {
            if ($has($k)) {
                $ok = cdp_smsSettingSet($k, $in[$k], $uid) && $ok;
                $changed[] = $k;
            }
        }
        foreach (['hubtel_client_secret', 'mnotify_api_key'] as $k) {
            if ($in[$k] !== '' || $cleared($k)) {
                $ok = cdp_smsSettingSet($k, $cleared($k) && $in[$k] === '' ? '' : $in[$k], $uid) && $ok;
                $changed[] = $k . ($in[$k] === '' ? ' (removed)' : '');
            }
        }
        foreach ($enabled as $p => $v) {
            if ($has($p . '_enabled')) {
                $ok = cdp_smsSettingSet($p . '_enabled', $v, $uid) && $ok;
                $changed[] = $p . '_enabled';
            }
        }
        if ($has('sms_default_provider')) {
            $ok = cdp_smsSettingSet('sms_default_provider', $default, $uid) && $ok;
            $changed[] = 'sms_default_provider';
        }
        if ($has('sms_fallback')) {
            $ok = cdp_smsSettingSet('sms_fallback', $fallback, $uid) && $ok;
            $changed[] = 'sms_fallback';
        }
        if ($has('active_sms')) {
            $ok = $saveCore(['active_sms' => $active]) && $ok;
            $changed[] = 'active_sms';
        }
        $log('sms', $changed, ['active' => $active, 'default' => $default]);
        $out($ok, $ok ? 'SMS settings saved.' : 'Some SMS settings were not saved.', ['status' => sl_commsChannelStatus()]);

    case 'mnotify_balance':
        $typed = $P('mnotify_api_key');
        $r = cdp_mnotifySmsBalance($typed !== '' ? $typed : null);
        if (empty($r['success'])) {
            $out(false, (string) ($r['message'] ?? 'mNotify did not answer.'));
        }
        $out(true, 'mNotify key works. Balance: ' . number_format((float) $r['balance']) . ' SMS (bonus ' . number_format((float) ($r['bonus'] ?? 0)) . ').');

    // ------------------------------------------------------------------ E-mail
    case 'save_email':
        $cs = cdp_getSettingsCourier();
        // Fields missing from the POST keep their saved value.
        $sv = function ($k, $col) use ($has, $P, $cs) { return $has($k) ? $P($k) : trim((string) ($cs->$col ?? '')); };
        $mailer = $has('mailer') ? ($P('mailer') === 'PHP' ? 'PHP' : 'SMTP') : (strtoupper((string) ($cs->mailer ?? '')) === 'PHP' ? 'PHP' : 'SMTP');
        $from = $sv('email_address', 'email_address');
        $names = mb_substr($sv('smtp_names', 'smtp_names'), 0, 120);
        $host = $sv('smtp_host', 'smtp_host');
        $userN = $sv('smtp_user', 'smtp_user');
        $pass = (string) ($_POST['smtp_password'] ?? '');
        $savedPass = (string) ($cs->smtp_password ?? '');
        $port = (int) ($has('smtp_port') ? $P('smtp_port') : ((int) ($cs->smtp_port ?? 0) ?: 587));
        $secIn = $has('smtp_secure') ? $P('smtp_secure') : (trim((string) ($cs->smtp_secure ?? '')) ?: 'none');
        $secure = in_array($secIn, ['tls', 'ssl', 'none'], true) ? $secIn : 'tls';
        $savedHost = strtolower(trim((string) ($cs->smtp_host ?? '')));
        $errors = [];
        if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $errors['email_address'] = 'Enter the address e-mails come from.';
        }
        if ($names === '') {
            $errors['smtp_names'] = 'Enter the name e-mails come from.';
        }
        if ($mailer === 'SMTP') {
            if ($host === '' || !preg_match('/^[A-Za-z0-9.\-]{1,253}$/', $host)) {
                $errors['smtp_host'] = 'Enter the SMTP host, e.g. smtp.yourmail.com.';
            } elseif (strtolower($host) !== $savedHost && !($hc = sl_commsHostCheck($host))['ok']) {
                $errors['smtp_host'] = $hc['error'];
            }
            if ($userN === '') {
                $errors['smtp_user'] = 'Enter the SMTP user name.';
            }
            $passAfter = $cleared('smtp_password') ? '' : ($pass !== '' ? $pass : $savedPass);
            if ($passAfter === '') {
                $errors['smtp_password'] = 'Enter the SMTP password.';
            } elseif ($pass === '' && $savedPass !== '' && strtolower($host) !== $savedHost) {
                // A saved password only ever goes to the server it was typed for.
                $errors['smtp_password'] = 'The SMTP host changed: type the password again.';
            }
            if ($port < 1 || $port > 65535) {
                $errors['smtp_port'] = 'A port between 1 and 65535.';
            }
        }
        if ($errors) {
            $out(false, reset($errors), ['errors' => $errors]);
        }
        $cols = ['mailer' => $mailer, 'smtp_names' => $names, 'email_address' => $from, 'smtp_host' => $host, 'smtp_user' => $userN,
            'smtp_port' => (string) $port, 'smtp_secure' => $secure === 'none' ? '' : $secure];
        if ($pass !== '') {
            $cols['smtp_password'] = $pass;
        } elseif ($cleared('smtp_password')) {
            $cols['smtp_password'] = '';
        }
        if ($has('sync_site_email') && $on('sync_site_email')) {
            $cols['site_email'] = $from;
        }
        $ok = $saveCore($cols);
        $log('email', array_keys($cols), ['mailer' => $mailer]);
        $out($ok, $ok ? 'E-mail settings saved.' : 'Not saved.', ['status' => sl_commsChannelStatus()]);

    case 'check_smtp':
        // Checks what is on screen (the saved value for anything not sent); the saved password only goes to the saved host.
        $cs = cdp_getSettingsCourier();
        $mailer = $has('mailer') ? ($P('mailer') === 'PHP' ? 'PHP' : 'SMTP') : (strtoupper((string) ($cs->mailer ?? '')) === 'PHP' ? 'PHP' : 'SMTP');
        if ($mailer === 'PHP') {
            $out(true, 'PHP mail() is chosen: there is no server to check. Save, then send a test e-mail below.');
        }
        $host = $has('smtp_host') ? $P('smtp_host') : trim((string) ($cs->smtp_host ?? ''));
        $userN = $has('smtp_user') ? $P('smtp_user') : (string) ($cs->smtp_user ?? '');
        $port = (int) ($has('smtp_port') ? $P('smtp_port') : ((int) ($cs->smtp_port ?? 0) ?: 587));
        $sec = strtolower($has('smtp_secure') ? $P('smtp_secure') : trim((string) ($cs->smtp_secure ?? '')));
        $pass = (string) ($_POST['smtp_password'] ?? '');
        if ($pass === '') {
            if ((string) ($cs->smtp_password ?? '') !== '' && strtolower($host) !== strtolower(trim((string) ($cs->smtp_host ?? '')))) {
                $out(false, 'The SMTP host changed: type the password to check the new server.', ['errors' => ['smtp_password' => 'Type the password for the new server.']]);
            }
            $pass = (string) ($cs->smtp_password ?? '');
        }
        if ($host === '' || $userN === '' || $pass === '') {
            $out(false, 'Enter the SMTP host, user name and password first.');
        }
        if (!preg_match('/^[A-Za-z0-9.\-]{1,253}$/', $host)) {
            $out(false, 'Enter the SMTP host, e.g. smtp.yourmail.com.', ['errors' => ['smtp_host' => 'Enter the SMTP host, e.g. smtp.yourmail.com.']]);
        }
        $hc = sl_commsHostCheck($host);
        if (!$hc['ok']) {
            $out(false, $hc['error'], ['errors' => ['smtp_host' => $hc['error']]]);
        }
        if ($port < 1 || $port > 65535) {
            $out(false, 'A port between 1 and 65535.', ['errors' => ['smtp_port' => 'A port between 1 and 65535.']]);
        }
        require_once(__DIR__ . '/../../helpers/phpmailer/class.phpmailer.php');
        require_once(__DIR__ . '/../../helpers/phpmailer/class.smtp.php');
        try {
            $m = new PHPMailer(true);
            $m->isSMTP();
            $m->Host = $host;
            $m->Port = $port;
            $m->SMTPAuth = true;
            $m->Username = $userN;
            $m->Password = $pass;
            $m->SMTPSecure = in_array($sec, ['tls', 'ssl'], true) ? $sec : '';
            $m->Timeout = 10;
            $ok = $m->smtpConnect();
            if ($ok) {
                $m->smtpClose();
                $out(true, 'The server accepted the user name and password.' . ($has('smtp_host') ? ' Save to keep these settings.' : ''));
            }
            $out(false, 'The server did not accept the connection.');
        } catch (Throwable $e) {
            $out(false, 'The server did not accept the connection: ' . mb_substr($e->getMessage(), 0, 160));
        }

    // ----------------------------------------------------------- Sending rules
    case 'save_rules':
        $errors = [];
        foreach (['quiet_start', 'quiet_end'] as $k) {
            if ($has($k) && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $P($k))) {
                $errors[$k] = 'A time like 21:00.';
            }
        }
        // Whole numbers in a range; a negative or empty value is refused, never quietly changed.
        $ranges = ['batch_size' => [1, 10000, 'At least 1.'], 'claim_after_days' => [0, 365, 'Between 0 and 365 days.'], 'claim_every_days' => [1, 365, 'At least 1 day.'],
            'claim_max' => [0, 50, 'Between 0 and 50.'], 'pay_after_days' => [0, 365, 'Between 0 and 365 days.'], 'pay_every_days' => [1, 365, 'At least 1 day.'], 'pay_max' => [0, 50, 'Between 0 and 50.']];
        foreach ($ranges as $k => [$lo, $hi, $msg]) {
            if ($has($k) && (!preg_match('/^\d+$/', $P($k)) || (int) $P($k) < $lo || (int) $P($k) > $hi)) {
                $errors[$k] = $msg;
            }
        }
        if ($has('pay_min_amount') && ($P('pay_min_amount') === '' || !is_numeric($P('pay_min_amount')) || (float) $P('pay_min_amount') < 0)) {
            $errors['pay_min_amount'] = 'An amount of 0 or more.';
        }
        if ($has('currency') && !preg_match('/^[A-Za-z₵$€£ .]{1,8}$/u', $P('currency'))) {
            $errors['currency'] = 'Up to 8 letters, e.g. GHS.';
        }
        if ($errors) {
            $out(false, reset($errors), ['errors' => $errors]);
        }
        $keys = ['enabled', 'quiet_start', 'quiet_end', 'batch_size', 'claim_after_days', 'claim_every_days', 'claim_max',
            'pay_after_days', 'pay_every_days', 'pay_max', 'pay_min_amount', 'currency', 'campaign_needs_approval'];
        $in = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $_POST)) {
                $in[$k] = in_array($k, ['enabled', 'campaign_needs_approval'], true) ? ($on($k) ? '1' : '0') : $P($k);
            }
        }
        $wasOn = cdp_commSetting('enabled') === '1';
        cdp_commSaveSettings($in);
        $note = '';
        // Switching automations off while they own status messages would leave customers without them.
        if ($wasOn && ($in['enabled'] ?? '1') === '0' && function_exists('cdp_commStatusOwner') && cdp_commStatusOwner() === 'comms') {
            $r = cdp_commSetStatusOwner('legacy', $uid);
            if (!empty($r['ok'])) {
                $note = ' Status messages went back to the old screens.';
            }
        }
        $log('sending rules', array_keys($in), ['enabled' => $in['enabled'] ?? null]);
        $out(true, 'Sending rules saved.' . $note, ['status' => sl_commsChannelStatus(), 'owner' => function_exists('cdp_commStatusOwner') ? cdp_commStatusOwner() : 'legacy']);

    case 'set_owner':
        if (!function_exists('cdp_commSetStatusOwner')) {
            $out(false, 'The status-message switch is not installed.');
        }
        $want = $P('owner') === 'comms' ? 'comms' : 'legacy';
        $r = cdp_commSetStatusOwner($want, $uid);
        if (empty($r['ok'])) {
            $out(false, (string) ($r['error'] ?? 'Not saved.'), ['owner' => cdp_commStatusOwner()]);
        }
        $log('status owner', ['status_owner'], ['owner' => $want]);
        $out(true, $want === 'comms' ? 'The Automation Center now sends status messages. The old screens stay silent for them.' : 'The old screens send status messages again.',
            ['owner' => $want, 'status' => sl_commsChannelStatus()]);

    // -------------------------------------------------------------------- King
    case 'save_king':
        $in = [];
        if ($has('bot_daily_cap')) {
            $capRaw = $P('bot_daily_cap');
            if (!preg_match('/^\d+$/', $capRaw) || (int) $capRaw > 10000) {
                $out(false, 'Between 0 and 10000.', ['errors' => ['bot_daily_cap' => 'Between 0 and 10000.']]);
            }
            $in['bot_daily_cap'] = (string) (int) $capRaw;
        }
        if ($has('bot_enabled')) {
            $in['bot_enabled'] = $on('bot_enabled') ? '1' : '0';
        }
        if ($has('bot_instructions')) {
            $in['bot_instructions'] = mb_substr((string) $_POST['bot_instructions'], 0, 4000);
        }
        cdp_commSaveSettings($in);
        $log('king', array_keys($in), ['on' => ($in['bot_enabled'] ?? null) === '1']);
        $out(true, cdp_kingName() . ' settings saved.', ['status' => sl_commsChannelStatus()]);

    // -------------------------------------------------------------------- Test
    case 'test':
        $ch = $P('test_channel');
        if (!in_array($ch, ['whatsapp', 'sms', 'email'], true)) {
            $out(false, 'Choose a channel.');
        }
        // Each SMS / WhatsApp test costs money and skips the sending gate: at most 10 an hour per person.
        $now = time();
        $tries = array_values(array_filter((array) ($_SESSION['cs_test_sends'] ?? []), fn($t) => (int) $t > $now - 3600));
        if (count($tries) >= 10) {
            $wait = max(1, (int) ceil((min($tries) + 3600 - $now) / 60));
            $out(false, 'You sent 10 tests in the last hour. Try again in ' . $wait . ' minute' . ($wait === 1 ? '' : 's') . '.', [], 429);
        }
        $core = new Core;
        $site = (string) ($core->site_name ?? 'Swiftlane');
        if (function_exists('cdp_msgSetContext')) {
            cdp_msgSetContext(['source' => 'comms_test', 'source_label' => 'Settings test send', 'entity_type' => 'test']);
        }
        /** A test refused before reaching a provider is still recorded in the Outbox (skipped, with the reason). */
        $refuse = function ($channel, $to, $reason, $body, $subject = '') use ($out) {
            if (function_exists('cdp_msgLog')) {
                cdp_msgLog(['channel' => $channel, 'status' => 'skipped', 'status_detail' => mb_substr($reason, 0, 250), 'recipient_to' => (string) $to,
                    'body' => $body, 'subject' => $subject, 'source' => 'comms_test', 'source_label' => 'Settings test send', 'entity_type' => 'test']);
            }
            $out(false, $reason);
        };
        if ($ch === 'email') {
            $to = $P('test_email');
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $out(false, 'Enter a valid e-mail address.', ['errors' => ['test_email' => 'Enter a valid e-mail address.']]);
            }
            $_SESSION['cs_test_sends'] = array_merge($tries, [$now]);
            $subject = 'Test e-mail from ' . $site;
            $body = "This is a test e-mail from the message settings of $site.\nYour e-mail settings are working.";
            $csE = cdp_getSettingsCourier();
            if (!filter_var(trim((string) ($csE->email_address ?? '')), FILTER_VALIDATE_EMAIL)) {
                $refuse('email', $to, 'Save a From address under E-mail first: every e-mail is sent from it.', $body, $subject);
            }
            $r = cdp_commSendEmail((object) ['id' => 0, 'email' => $to, 'fname' => '', 'lname' => ''], $subject, $body);
            $out(!empty($r['success']), !empty($r['success']) ? 'Test e-mail sent to ' . $to . '.' : 'Not sent: ' . (string) ($r['message'] ?? 'unknown error'));
        }
        $raw = $P('test_phone');
        $to = $raw !== '' ? (string) cdp_normalizePhone($raw, 'GH') : '';
        if ($to === '' || strlen(preg_replace('/\D+/', '', $to)) < 9 || strlen(preg_replace('/\D+/', '', $to)) > 15) {
            $out(false, 'Enter a valid phone number.', ['errors' => ['test_phone' => 'Enter a valid phone number.']]);
        }
        $_SESSION['cs_test_sends'] = array_merge($tries, [$now]);
        if ($ch === 'sms') {
            $prov = $P('test_provider');
            $text = 'Test SMS from ' . $site . '. Your SMS settings are working.';
            if ($prov !== '') {
                $label = cdp_smsProviders()[$prov] ?? null;
                if ($label === null) {
                    $out(false, 'Unknown provider.');
                }
                if (!cdp_smsProviderConfigured($prov)) {
                    $refuse('sms', $to, 'Save the ' . $label . ' keys first.', $text);
                }
                $r = cdp_sendSmsVia($prov, $to, $text, ['id' => 0, 'name' => 'Settings test']);
            } else {
                if (!cdp_smsReady()) {
                    $refuse('sms', $to, 'SMS is not set up or switched off. Save a provider and switch SMS on, or pick a provider to test it.', $text);
                }
                $r = cdp_sendSms($to, $text, ['id' => 0, 'name' => 'Settings test']);
            }
            $ok = !empty($r['success']);
            $out($ok, $ok ? 'Test SMS sent to +' . ltrim($to, '+') . '.' : 'Not sent: ' . (string) ($r['message'] ?? 'unknown error'));
        }
        $text = '*Test message* from ' . $site . '. Your WhatsApp settings are working.';
        if (cdp_commSetting('wa_provider') === 'cloud' && (int) (cdp_getSettingsCourier()->active_whatsapp ?? 0) !== 1) {
            $refuse('whatsapp', $to, 'WhatsApp is switched off. Switch it on under WhatsApp first.', $text);
        }
        $r = cdp_commSendWhatsApp((object) ['id' => 0, 'phone' => $to, 'country' => 'GH', 'fname' => 'Test', 'lname' => '', 'email' => ''], $text);
        $ok = !empty($r['success']);
        $out($ok, $ok ? 'Test WhatsApp sent to +' . ltrim($to, '+') . '.' : 'Not sent: ' . (string) ($r['message'] ?: 'WhatsApp is not set up or switched off.'));

    case 'status':
        $out(true, '', ['status' => sl_commsChannelStatus()]);
}

$out(false, 'Unknown action.', [], 400);
