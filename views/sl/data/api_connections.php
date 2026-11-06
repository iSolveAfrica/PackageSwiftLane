<?php
/**
 * API connections: one registry of outside services. Each field reads and writes the column that
 * already holds it (cdb_settings, cdb_met_payment, cdb_sms_settings, cdb_ai_settings); services with
 * no legacy home (couriers, maps without a column, Slack) use cdb_api_connections. Secrets are only
 * ever returned masked.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/querys.php';

function sl_apiProviders(): array
{
    $g = sl_columnExists('cdb_settings', 'apikey') ? ['settings', 'apikey'] : ['conn', 'secret'];
    return [
        'stripe'   => ['name' => 'Stripe', 'short' => 'S', 'cat' => 'Payments', 'use' => 'Card payments', 'color' => '#635BFF', 'on' => ['metpay', 3, 'is_active'], 'fields' => [['public_key', 'Publishable key', false, ['metpay', 3, 'public_key']], ['secret_key', 'Secret key', true, ['metpay', 3, 'secret_key']]]],
        'paystack' => ['name' => 'Paystack', 'short' => 'P', 'cat' => 'Payments', 'use' => 'Cards and mobile money', 'color' => '#0BA4DB', 'on' => ['metpay', 4, 'is_active'], 'fields' => [['public_key', 'Public key', false, ['metpay', 4, 'public_key']], ['secret_key', 'Secret key', true, ['metpay', 4, 'secret_key']]]],
        'paypal'   => ['name' => 'PayPal', 'short' => 'PP', 'cat' => 'Payments', 'use' => 'PayPal checkout', 'color' => '#003087', 'on' => ['metpay', 2, 'is_active'], 'fields' => [['paypal_client_id', 'Client ID', false, ['metpay', 2, 'paypal_client_id']], ['secret_key', 'Secret', true, ['metpay', 2, 'secret_key']]]],
        'smtp'     => ['name' => 'E-mail (SMTP)', 'short' => '@', 'cat' => 'Messages', 'use' => 'Every e-mail', 'color' => '#161615', 'on' => null, 'fields' => [['smtp_host', 'Host', false, ['settings', 'smtp_host']], ['smtp_port', 'Port', false, ['settings', 'smtp_port']], ['smtp_secure', 'Security (tls / ssl)', false, ['settings', 'smtp_secure']], ['smtp_user', 'User', false, ['settings', 'smtp_user']], ['smtp_password', 'Password', true, ['settings', 'smtp_password']], ['email_address', 'From address', false, ['settings', 'email_address']], ['smtp_names', 'From name', false, ['settings', 'smtp_names']]]],
        'whatsapp' => ['name' => 'WhatsApp API', 'short' => 'W', 'cat' => 'Messages', 'use' => 'WhatsApp notifications', 'color' => '#25D366', 'on' => ['settings', 'active_whatsapp'], 'fields' => [['api_ws_url', 'API URL', false, ['settings', 'api_ws_url']], ['api_ws_token', 'Token', true, ['settings', 'api_ws_token']]]],
        'hubtel'   => ['name' => 'Hubtel SMS', 'short' => 'H', 'cat' => 'Messages', 'use' => 'SMS in Ghana', 'color' => '#F7941D', 'on' => ['sms', 'hubtel_enabled'], 'fields' => [['hubtel_client_id', 'Client ID', false, ['sms', 'hubtel_client_id']], ['hubtel_client_secret', 'Client secret', true, ['sms', 'hubtel_client_secret']], ['hubtel_sender_id', 'Sender ID', false, ['sms', 'hubtel_sender_id']]]],
        'mnotify'  => ['name' => 'mNotify SMS', 'short' => 'mN', 'cat' => 'Messages', 'use' => 'SMS in Ghana', 'color' => '#E2231A', 'on' => ['sms', 'mnotify_enabled'], 'fields' => [['mnotify_api_key', 'API key', true, ['sms', 'mnotify_api_key']], ['mnotify_sender_id', 'Sender ID', false, ['sms', 'mnotify_sender_id']]]],
        'twilio'   => ['name' => 'Twilio SMS', 'short' => 'T', 'cat' => 'Messages', 'use' => 'International SMS', 'color' => '#F22F46', 'on' => null, 'fields' => [['twilio_sms_sid', 'Account SID', false, ['settings', 'twilio_sms_sid']], ['twilio_sms_token', 'Auth token', true, ['settings', 'twilio_sms_token']], ['twilio_sms_number', 'From number', false, ['settings', 'twilio_sms_number']]]],
        'claude'   => ['name' => 'Claude (Anthropic)', 'short' => 'AI', 'cat' => 'AI', 'use' => 'AI assistant', 'color' => '#D97757', 'on' => null, 'fields' => [['key_claude', 'API key', true, ['ai', 'key_claude']]]],
        'openai'   => ['name' => 'OpenAI', 'short' => 'O', 'cat' => 'AI', 'use' => 'AI assistant', 'color' => '#10A37F', 'on' => null, 'fields' => [['key_openai', 'API key', true, ['ai', 'key_openai']]]],
        'openrouter' => ['name' => 'OpenRouter', 'short' => 'OR', 'cat' => 'AI', 'use' => 'AI assistant', 'color' => '#6467F2', 'on' => null, 'fields' => [['key_openrouter', 'API key', true, ['ai', 'key_openrouter']]]],
        'google'   => ['name' => 'Google Maps', 'short' => 'G', 'cat' => 'Maps', 'use' => 'Maps and routes', 'color' => '#4285F4', 'on' => ['conn', 'enabled'], 'fields' => [['apikey', 'API key', true, $g]]],
        'dhl'      => ['name' => 'DHL', 'short' => 'D', 'cat' => 'Couriers', 'use' => 'Carrier tracking', 'color' => '#FFCC00', 'on' => ['conn', 'enabled'], 'fields' => [['account', 'Account number', false, ['conn', 'account']], ['api_key', 'API key', true, ['conn', 'secret']]]],
        'fedex'    => ['name' => 'FedEx', 'short' => 'F', 'cat' => 'Couriers', 'use' => 'Carrier tracking', 'color' => '#4D148C', 'on' => ['conn', 'enabled'], 'fields' => [['account', 'Account number', false, ['conn', 'account']], ['api_key', 'API key', true, ['conn', 'secret']]]],
        'ups'      => ['name' => 'UPS', 'short' => 'U', 'cat' => 'Couriers', 'use' => 'Carrier tracking', 'color' => '#351C15', 'on' => ['conn', 'enabled'], 'fields' => [['account', 'Account number', false, ['conn', 'account']], ['api_key', 'API key', true, ['conn', 'secret']]]],
        'usps'     => ['name' => 'USPS', 'short' => 'US', 'cat' => 'Couriers', 'use' => 'Carrier tracking', 'color' => '#333366', 'on' => ['conn', 'enabled'], 'fields' => [['account', 'User ID', false, ['conn', 'account']], ['api_key', 'API key', true, ['conn', 'secret']]]],
        'slack'    => ['name' => 'Slack', 'short' => 'Sl', 'cat' => 'Team', 'use' => 'Team alerts', 'color' => '#4A154B', 'on' => ['conn', 'enabled'], 'fields' => [['webhook', 'Incoming webhook URL', true, ['conn', 'secret']]]],
    ];
}

function sl_apiConnRow(string $key)
{
    return sl_tableExists('cdb_api_connections') ? sl_row('SELECT * FROM cdb_api_connections WHERE provider_key = :k', [':k' => $key]) : null;
}

/** Raw value of one field source (server side only; never sent to the browser unmasked). */
function sl_apiRead(string $pkey, array $src)
{
    static $s = null, $mp = [];
    if ($s === null) $s = cdp_getSettingsCourier();
    switch ($src[0]) {
        case 'settings': return (string) ($s->{$src[1]} ?? '');
        case 'metpay':
            if (!isset($mp[$src[1]])) $mp[$src[1]] = sl_row('SELECT * FROM cdb_met_payment WHERE id = :id', [':id' => (int) $src[1]]);
            return (string) ($mp[$src[1]]->{$src[2]} ?? '');
        case 'sms':
            @include_once CDP_ROOT . '/helpers/hubtel_sms.php';
            return function_exists('cdp_smsSetting') ? (string) cdp_smsSetting($src[1]) : '';
        case 'ai':
            @include_once CDP_ROOT . '/helpers/ai_assistant.php';
            return function_exists('cdp_aiSetting') ? (string) cdp_aiSetting($src[1]) : '';
        case 'conn':
            $r = sl_apiConnRow($pkey);
            if (!$r) return '';
            if ($src[1] === 'secret') return (string) $r->secret_value;
            if ($src[1] === 'enabled') return (string) $r->enabled;
            $c = json_decode((string) $r->config, true) ?: [];
            return (string) ($c[$src[1]] ?? '');
    }
    return '';
}

function sl_apiMask(string $v): string
{
    $n = strlen($v);
    if ($n === 0) return '';
    if ($n <= 8) return str_repeat('•', $n);
    return substr($v, 0, 4) . str_repeat('•', 8) . substr($v, -4);
}

function sl_apiConnectionsData($user, $core): array
{
    $prov = sl_apiProviders();
    $cur = (string) ($_GET['p'] ?? 'stripe');
    if (!isset($prov[$cur])) $cur = 'stripe';
    $groups = [];
    $con = 0; $ok = 0; $warn = 0;
    foreach ($prov as $k => $p) {
        $vals = array_map(fn($f) => sl_apiRead($k, $f[3]), $p['fields']);
        $configured = count(array_filter($vals, fn($v) => trim($v) !== '')) === count($vals) || ($k === 'smtp' && (cdp_getSettingsCourier()->mailer ?? '') === 'PHP');
        $on = $p['on'] ? sl_apiRead($k, $p['on']) === '1' : $configured;
        $row = sl_apiConnRow($k);
        $check = $row && $row->last_check_at ? ((int) $row->last_check_ok === 1 ? 'g' : 'r') : '';
        $connected = $configured && $on;
        $con += $connected ? 1 : 0;
        $ok += $check === 'g' ? 1 : 0;
        $warn += $check === 'r' || ($configured && !$on) ? 1 : 0;
        $groups[$p['cat']]['title'] = $p['cat'];
        $groups[$p['cat']]['items'][] = ['k' => $k, 'name' => $p['name'], 'short' => $p['short'], 'use' => $p['use'], 'lsty' => 'background:' . $p['color'] . ';color:#fff', 'cls' => $k === $cur ? 'on' : '',
            'dot' => $connected ? ($check ?: 'g') : ($configured ? 'y' : ''), 'lat' => $row && $row->last_check_ms ? $row->last_check_ms . ' ms' : ($connected ? 'on' : ($configured ? 'off' : '—')), 'href' => sl_url(['p' => $k]),
            'configured' => $configured, 'on' => $on];
    }
    $p = $prov[$cur];
    $row = sl_apiConnRow($cur);
    $fields = [];
    foreach ($p['fields'] as $f) {
        $v = sl_apiRead($cur, $f[3]);
        $fields[] = ['name' => $f[0], 'label' => $f[1], 'secret' => $f[2], 'val' => $f[2] ? '' : $v, 'ph' => $f[2] ? ($v !== '' ? sl_apiMask($v) . '  (saved)' : 'not set') : '', 'hint' => $f[2] ? 'Leave blank to keep the saved value' : ''];
    }
    $item = null;
    foreach ($groups[$p['cat']]['items'] as $it) if ($it['k'] === $cur) $item = $it;
    $d = ['groups' => array_values($groups), 'cur' => ['k' => $cur, 'name' => $p['name'], 'short' => $p['short'], 'lsty' => 'background:' . $p['color'] . ';color:#fff', 'desc' => $p['cat'] . ' · ' . $p['use'],
        'status' => $item['configured'] ? ($item['on'] ? 'Connected' : 'Switched off') : 'Not connected', 'pc' => $item['configured'] ? ($item['on'] ? 'delivered' : 'pending') : 'plain', 'on' => $item['on'], 'hasSwitch' => $p['on'] !== null],
        'fields' => $fields, 'conN' => $con, 'allN' => count($prov), 'offN' => count($prov) - $con, 'okN' => $ok, 'warnN' => $warn, 'env' => $row->environment ?? 'live', 'isNew' => isset($_GET['new']),
        'check' => $row && $row->last_check_at ? ['ok' => (int) $row->last_check_ok === 1, 'ms' => (int) $row->last_check_ms, 'detail' => (string) $row->last_check_detail, 'at' => sl_ago($row->last_check_at)] : null];
    $d['calls'] = sl_tableExists('cdb_api_request_log') ? sl_num(sl_scalar('SELECT COUNT(*) FROM cdb_api_request_log WHERE created_at >= CURDATE()')) : '0';
    $d['avg'] = sl_tableExists('cdb_api_request_log') ? round((float) sl_scalar('SELECT AVG(duration_ms) FROM cdb_api_request_log WHERE created_at >= NOW() - INTERVAL 5 MINUTE')) . ' ms' : '—';
    $d['recent'] = sl_tableExists('cdb_api_request_log') ? array_map(fn($r) => ['at' => date('H:i:s', strtotime($r->created_at)), 'code' => (int) $r->status, 'cc' => $r->status >= 400 ? 'r' : '', 'ep' => $r->method . ' ' . $r->path, 'ms' => $r->duration_ms . ' ms'], sl_rows('SELECT * FROM cdb_api_request_log ORDER BY id DESC LIMIT 6')) : [];
    $d['canEdit'] = $user->cdp_hasPermission('manage_api_settings');
    return $d;
}
