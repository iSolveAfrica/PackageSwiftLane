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
/** A GET with a short timeout; never throws. */
$httpGet = function ($url, array $headers = []) {
    if (!function_exists('curl_init')) {
        return ['code' => 0, 'body' => '', 'err' => 'cURL is not installed on this server.'];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 12, CURLOPT_HTTPHEADER => $headers]);
    $body = curl_exec($ch);
    $r = ['code' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE), 'body' => (string) $body, 'err' => curl_error($ch)];
    curl_close($ch);
    return $r;
};

switch ($action) {

    // ---------------------------------------------------------------- WhatsApp
    case 'save_whatsapp':
        $cs = cdp_getSettingsCourier();
        $prov = $P('wa_provider') === 'cloud' ? 'cloud' : 'ultramsg';
        $url = rtrim($P('api_ws_url'), '/');
        $token = $P('api_ws_token');
        $errors = [];
        if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $url))) {
            $errors['api_ws_url'] = 'Enter the full instance address, e.g. https://api.ultramsg.com/instance12345.';
        }
        if ($token !== '' && !preg_match('/^[\x21-\x7E]{4,200}$/', $token)) {
            $errors['api_ws_token'] = 'The token has characters UltraMsg does not issue.';
        }
        $active = $on('active_whatsapp');
        $s = cdp_commSettings(true);
        if ($active && $prov === 'ultramsg' && ($url === '' || ($token === '' && (string) ($cs->api_ws_token ?? '') === ''))) {
            $errors['active_whatsapp'] = 'Enter the instance address and token before switching WhatsApp on.';
        }
        $phoneId = $P('wa_cloud_phone_id');
        if ($phoneId !== '' && !preg_match('/^\d{5,30}$/', $phoneId)) {
            $errors['wa_cloud_phone_id'] = 'The phone number ID is digits only.';
        }
        $ver = $P('wa_cloud_version', 'v20.0');
        if ($ver !== '' && !preg_match('/^v\d{1,3}\.\d{1,2}$/', $ver)) {
            $errors['wa_cloud_version'] = 'Like v20.0.';
        }
        $verify = $P('wa_cloud_verify');
        if ($verify !== '' && preg_match('/^•+$/u', $verify)) {
            $verify = (string) $s['wa_cloud_verify'];
        }
        if ($active && $prov === 'cloud' && ($phoneId === '' || ($P('wa_cloud_token') === '' && $s['wa_cloud_token'] === ''))) {
            $errors['active_whatsapp'] = 'Enter the phone number ID and access token before switching WhatsApp on.';
        }
        if ($errors) {
            $out(false, reset($errors), ['errors' => $errors]);
        }
        $core = ['api_ws_url' => $url, 'active_whatsapp' => $active ? 1 : 0];
        if ($token !== '') {
            $core['api_ws_token'] = $token;
        }
        $ok = $saveCore($core);
        cdp_commSaveSettings(['wa_provider' => $prov, 'wa_cloud_phone_id' => $phoneId, 'wa_cloud_token' => $P('wa_cloud_token'),
            'wa_cloud_app_secret' => $P('wa_cloud_app_secret'), 'wa_cloud_verify' => $verify, 'wa_cloud_version' => $ver ?: 'v20.0']);
        $log('whatsapp', array_keys(array_filter(['api_ws_url' => 1, 'api_ws_token' => $token !== '', 'active_whatsapp' => 1, 'wa_provider' => 1,
            'wa_cloud_token' => $P('wa_cloud_token') !== '', 'wa_cloud_app_secret' => $P('wa_cloud_app_secret') !== ''])), ['provider' => $prov, 'active' => $active]);
        $out($ok, $ok ? 'WhatsApp settings saved.' : 'Not saved.', ['status' => sl_commsChannelStatus()]);

    case 'rotate_key':
        $ok = cdp_opsSettingSet('comms', 'webhook_key', bin2hex(random_bytes(16)), $uid);
        $log('webhook key rotated');
        $c = cdp_commConnection(true);
        $out($ok, $ok ? 'New key made. Paste the new address in the provider dashboard.' : 'Not saved.', ['webhook' => (string) $c['webhook_url']]);

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
            $out(false, 'Meta did not accept the keys: ' . ($j['error']['message'] ?? ($r['err'] !== '' ? $r['err'] : 'HTTP ' . $r['code'])));
        }
        $base = rtrim(trim((string) ($cs->api_ws_url ?? '')), '/');
        $tok = (string) ($cs->api_ws_token ?? '');
        if ($base === '' || $tok === '') {
            $out(false, 'Save the instance address and token first.');
        }
        $r = $httpGet($base . '/instance/status?token=' . rawurlencode($tok));
        $j = json_decode($r['body'], true);
        if ($r['err'] !== '' || $r['code'] === 0) {
            $out(false, 'UltraMsg could not be reached: ' . ($r['err'] ?: 'no answer') . '.');
        }
        if (!is_array($j)) {
            $out(false, 'UltraMsg answered HTTP ' . $r['code'] . ' with something unexpected. Check the instance address.');
        }
        if (!empty($j['error'])) {
            $e = is_scalar($j['error']) ? (string) $j['error'] : json_encode($j['error']);
            $out(false, 'UltraMsg: ' . $e);
        }
        $st = $j['status']['accountStatus']['status'] ?? ($j['accountStatus']['status'] ?? ($j['status'] ?? ''));
        $sub = $j['status']['accountStatus']['substatus'] ?? ($j['accountStatus']['substatus'] ?? '');
        $st = is_scalar($st) ? strtolower((string) $st) : '';
        if ($st === 'authenticated') {
            $out(true, 'Connected: the phone is linked' . ($sub !== '' && is_scalar($sub) ? ' (' . $sub . ')' : '') . '.', ['state' => 'authenticated']);
        }
        $out(false, $st === 'qr' || $st === 'loading' || $st === 'initialize'
            ? 'The keys work, but no phone is linked. Use "Link your phone" and scan the QR code.'
            : 'UltraMsg says: ' . ($st !== '' ? $st : 'unknown state') . '.', ['state' => $st]);

    // --------------------------------------------------------------------- SMS
    case 'save_sms':
        $providers = cdp_smsProviders();
        $active = $on('active_sms') ? 1 : 0;
        $fallback = $on('sms_fallback') ? '1' : '0';
        $default = $P('sms_default_provider', 'hubtel');
        $in = ['hubtel_client_id' => $P('hubtel_client_id'), 'hubtel_client_secret' => $P('hubtel_client_secret'), 'hubtel_sender_id' => $P('hubtel_sender_id'),
            'mnotify_api_key' => $P('mnotify_api_key'), 'mnotify_sender_id' => $P('mnotify_sender_id')];
        $enabled = [];
        foreach (array_keys($providers) as $p) {
            $enabled[$p] = $on($p . '_enabled') ? '1' : '0';
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
        $final = [
            'hubtel' => $in['hubtel_client_id'] !== '' && ($in['hubtel_client_secret'] !== '' || (string) $hub['client_secret'] !== '') && $in['hubtel_sender_id'] !== '',
            'mnotify' => ($in['mnotify_api_key'] !== '' || (string) ($mno['api_key'] ?? '') !== '') && $in['mnotify_sender_id'] !== '',
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
        foreach (['hubtel_client_id', 'hubtel_sender_id', 'mnotify_sender_id'] as $k) {
            $ok = cdp_smsSettingSet($k, $in[$k], $uid) && $ok;
        }
        foreach (['hubtel_client_secret', 'mnotify_api_key'] as $k) {
            if ($in[$k] !== '') {
                $ok = cdp_smsSettingSet($k, $in[$k], $uid) && $ok;
            }
        }
        foreach ($enabled as $p => $v) {
            $ok = cdp_smsSettingSet($p . '_enabled', $v, $uid) && $ok;
        }
        $ok = cdp_smsSettingSet('sms_default_provider', $default, $uid) && cdp_smsSettingSet('sms_fallback', $fallback, $uid) && $ok;
        $ok = $saveCore(['active_sms' => $active]) && $ok;
        $log('sms', array_keys(array_filter(['hubtel_client_secret' => $in['hubtel_client_secret'] !== '', 'mnotify_api_key' => $in['mnotify_api_key'] !== '',
            'hubtel_client_id' => 1, 'sender_ids' => 1, 'providers' => 1])), ['active' => $active, 'default' => $default]);
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
        $mailer = $P('mailer') === 'PHP' ? 'PHP' : 'SMTP';
        $from = $P('email_address');
        $names = mb_substr($P('smtp_names'), 0, 120);
        $host = $P('smtp_host');
        $userN = $P('smtp_user');
        $pass = (string) ($_POST['smtp_password'] ?? '');
        $port = (int) $P('smtp_port', '587');
        $secure = in_array($P('smtp_secure'), ['tls', 'ssl', 'none'], true) ? $P('smtp_secure') : 'tls';
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
            }
            if ($userN === '') {
                $errors['smtp_user'] = 'Enter the SMTP user name.';
            }
            if ($pass === '' && (string) ($cs->smtp_password ?? '') === '') {
                $errors['smtp_password'] = 'Enter the SMTP password.';
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
        }
        if ($on('sync_site_email')) {
            $cols['site_email'] = $from;
        }
        $ok = $saveCore($cols);
        $log('email', array_merge(array_keys($cols)), ['mailer' => $mailer]);
        $out($ok, $ok ? 'E-mail settings saved.' : 'Not saved.', ['status' => sl_commsChannelStatus()]);

    case 'check_smtp':
        $cs = cdp_getSettingsCourier();
        $host = trim((string) ($cs->smtp_host ?? ''));
        if (strtoupper((string) ($cs->mailer ?? '')) === 'PHP') {
            $out(true, 'PHP mail() is used: there is no server to check. Send a test e-mail below.');
        }
        if ($host === '' || (string) ($cs->smtp_user ?? '') === '' || (string) ($cs->smtp_password ?? '') === '') {
            $out(false, 'Save the SMTP host, user name and password first.');
        }
        require_once(__DIR__ . '/../../helpers/phpmailer/class.phpmailer.php');
        require_once(__DIR__ . '/../../helpers/phpmailer/class.smtp.php');
        $sec = strtolower(trim((string) ($cs->smtp_secure ?? '')));
        try {
            $m = new PHPMailer(true);
            $m->isSMTP();
            $m->Host = $host;
            $m->Port = (int) ($cs->smtp_port ?? 0) ?: 587;
            $m->SMTPAuth = true;
            $m->Username = (string) $cs->smtp_user;
            $m->Password = (string) $cs->smtp_password;
            $m->SMTPSecure = in_array($sec, ['tls', 'ssl'], true) ? $sec : '';
            $m->Timeout = 10;
            $ok = $m->smtpConnect();
            if ($ok) {
                $m->smtpClose();
                $out(true, 'The server accepted the user name and password.');
            }
            $out(false, 'The server did not accept the connection.');
        } catch (Throwable $e) {
            $out(false, 'The server did not accept the connection: ' . mb_substr($e->getMessage(), 0, 200));
        }

    // ----------------------------------------------------------- Sending rules
    case 'save_rules':
        $errors = [];
        foreach (['quiet_start', 'quiet_end'] as $k) {
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $P($k))) {
                $errors[$k] = 'A time like 21:00.';
            }
        }
        if ((int) $P('batch_size') < 1) {
            $errors['batch_size'] = 'At least 1.';
        }
        foreach (['claim_every_days', 'pay_every_days'] as $k) {
            if ((int) $P($k) < 1) {
                $errors[$k] = 'At least 1 day.';
            }
        }
        if (!preg_match('/^[A-Za-z₵$€£ .]{1,8}$/u', $P('currency', 'GHS'))) {
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
        $cap = (int) $P('bot_daily_cap', '300');
        if ($cap < 0 || $cap > 10000) {
            $out(false, 'Between 0 and 10000.', ['errors' => ['bot_daily_cap' => 'Between 0 and 10000.']]);
        }
        cdp_commSaveSettings(['bot_enabled' => $on('bot_enabled') ? '1' : '0', 'bot_daily_cap' => (string) $cap,
            'bot_instructions' => mb_substr((string) ($_POST['bot_instructions'] ?? ''), 0, 4000)]);
        $log('king', ['bot_enabled', 'bot_daily_cap', 'bot_instructions'], ['on' => $on('bot_enabled')]);
        $out(true, cdp_kingName() . ' settings saved.', ['status' => sl_commsChannelStatus()]);

    // -------------------------------------------------------------------- Test
    case 'test':
        $ch = $P('test_channel');
        if (!in_array($ch, ['whatsapp', 'sms', 'email'], true)) {
            $out(false, 'Choose a channel.');
        }
        $core = new Core;
        $site = (string) ($core->site_name ?? 'Swiftlane');
        if (function_exists('cdp_msgSetContext')) {
            cdp_msgSetContext(['source' => 'comms_test', 'source_label' => 'Settings test send', 'entity_type' => 'test']);
        }
        if ($ch === 'email') {
            $to = $P('test_email');
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $out(false, 'Enter a valid e-mail address.', ['errors' => ['test_email' => 'Enter a valid e-mail address.']]);
            }
            $csE = cdp_getSettingsCourier();
            if (!filter_var(trim((string) ($csE->email_address ?? '')), FILTER_VALIDATE_EMAIL)) {
                $out(false, 'Save a From address under E-mail first: every e-mail is sent from it.');
            }
            $r = cdp_commSendEmail((object) ['id' => 0, 'email' => $to, 'fname' => '', 'lname' => ''], 'Test e-mail from ' . $site,
                "This is a test e-mail from the message settings of $site.\nYour e-mail settings are working.");
            $out(!empty($r['success']), !empty($r['success']) ? 'Test e-mail sent to ' . $to . '.' : 'Not sent: ' . (string) ($r['message'] ?? 'unknown error'));
        }
        $raw = $P('test_phone');
        $to = $raw !== '' ? (string) cdp_normalizePhone($raw, 'GH') : '';
        if ($to === '' || strlen(preg_replace('/\D+/', '', $to)) < 9 || strlen(preg_replace('/\D+/', '', $to)) > 15) {
            $out(false, 'Enter a valid phone number.', ['errors' => ['test_phone' => 'Enter a valid phone number.']]);
        }
        if ($ch === 'sms') {
            $prov = $P('test_provider');
            $text = 'Test SMS from ' . $site . '. Your SMS settings are working.';
            if ($prov !== '') {
                $label = cdp_smsProviders()[$prov] ?? null;
                if ($label === null) {
                    $out(false, 'Unknown provider.');
                }
                if (!cdp_smsProviderConfigured($prov)) {
                    $out(false, 'Save the ' . $label . ' keys first.');
                }
                $r = cdp_sendSmsVia($prov, $to, $text, ['id' => 0, 'name' => 'Settings test']);
            } else {
                if (!cdp_smsReady()) {
                    $out(false, 'SMS is not set up or switched off. Save a provider and switch SMS on, or pick a provider to test it.');
                }
                $r = cdp_sendSms($to, $text, ['id' => 0, 'name' => 'Settings test']);
            }
            $ok = !empty($r['success']);
            $out($ok, $ok ? 'Test SMS sent to +' . ltrim($to, '+') . '.' : 'Not sent: ' . (string) ($r['message'] ?? 'unknown error'));
        }
        $r = cdp_commSendWhatsApp((object) ['id' => 0, 'phone' => $to, 'country' => 'GH', 'fname' => 'Test', 'lname' => '', 'email' => ''],
            '*Test message* from ' . $site . '. Your WhatsApp settings are working.');
        $ok = !empty($r['success']);
        $out($ok, $ok ? 'Test WhatsApp sent to +' . ltrim($to, '+') . '.' : 'Not sent: ' . (string) ($r['message'] ?: 'WhatsApp is not set up or switched off.'));

    case 'status':
        $out(true, '', ['status' => sl_commsChannelStatus()]);
}

$out(false, 'Unknown action.', [], 400);
