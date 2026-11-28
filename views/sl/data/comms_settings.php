<?php
/**
 * Data for Notifications & Messages > Settings (comms_settings.php,
 * views/sl/pages/CommsSettings.php). One page for everything about the
 * channels: providers (WhatsApp UltraMsg / Meta Cloud API, SMS Hubtel /
 * mNotify, e-mail SMTP / PHP mail), sending rules, consent and opt-out,
 * King (the WhatsApp bot), costs and a test send per channel.
 *
 * Secrets never reach the page: only whether one is saved. Saves go to
 * ajax/comms/settings_ajax.php.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/querys.php';
require_once CDP_ROOT . '/helpers/comms.php';
require_once CDP_ROOT . '/helpers/hubtel_sms.php';
require_once CDP_ROOT . '/helpers/mnotify_sms.php';
require_once CDP_ROOT . '/helpers/message_log_query.php';
require_once CDP_ROOT . '/helpers/message_cost.php';
if (is_file(CDP_ROOT . '/helpers/comms_ai.php')) {
    require_once CDP_ROOT . '/helpers/comms_ai.php';
}

/** Who may do what on the Settings page. */
function sl_commsSettingsAccess($user): array
{
    $rank = cdp_opsRank($user);
    $super = sl_userIs('superadmin');
    return [
        'view' => $rank >= 3,
        // Provider keys and secrets: super admin, or the API settings permission (as API connections).
        'secrets' => $rank >= 3 && ($super || $user->cdp_hasPermission('manage_api_settings')),
        'super' => $super,
    ];
}

/** Where the "Link your phone (QR)" button goes: the in-app WhatsApp screen (whatsapp_web.php), '' when it is not installed. */
function sl_commsWhatsAppConnectUrl(): string
{
    $routes = sl_routes();
    foreach (['WhatsAppWeb', 'CommsWhatsApp', 'CommsWhatsAppConnect'] as $b) {
        if (isset($routes[$b]) && is_file(CDP_ROOT . '/' . strtok($routes[$b], '?#'))) {
            return sl_route($b);
        }
    }
    return '';
}

/**
 * May a provider address (WhatsApp instance, SMTP host) point at this host?
 * Public addresses only: loopback, private (RFC 1918), link-local (cloud
 * metadata) and reserved ranges are refused, so a provider "check" cannot be
 * aimed at the server's own network or used to send a saved secret there.
 * CDP_ALLOW_LOCAL_PROVIDERS (config) lifts this for a local test provider.
 *
 * @return array{ok:bool,error:string,ips:string[]} ips: the checked IPv4 addresses (pin them for the request)
 */
function sl_commsHostCheck(string $host): array
{
    $host = strtolower(trim($host, " \t\n\r\0\x0B[]"));
    if ($host === '') {
        return ['ok' => false, 'error' => 'No server address.', 'ips' => []];
    }
    if (defined('CDP_ALLOW_LOCAL_PROVIDERS') && CDP_ALLOW_LOCAL_PROVIDERS === true) {
        return ['ok' => true, 'error' => '', 'ips' => []];
    }
    $public = fn($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        return $public($host) ? ['ok' => true, 'error' => '', 'ips' => filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? [$host] : []]
            : ['ok' => false, 'error' => 'This address is on a private or local network. Use the provider\'s public address.', 'ips' => []];
    }
    if ($host === 'localhost' || substr($host, -6) === '.local' || substr($host, -10) === '.localhost' || substr($host, -9) === '.internal' || strpos($host, '.') === false) {
        return ['ok' => false, 'error' => 'This address is on a private or local network. Use the provider\'s public address.', 'ips' => []];
    }
    $ips = @gethostbynamel($host) ?: [];
    $v6 = [];
    if (function_exists('dns_get_record')) {
        foreach ((@dns_get_record($host, DNS_AAAA) ?: []) as $r) {
            if (!empty($r['ipv6'])) {
                $v6[] = $r['ipv6'];
            }
        }
    }
    foreach (array_merge($ips, $v6) as $ip) {
        if (!$public($ip)) {
            return ['ok' => false, 'error' => 'This address leads to a private or local network. Use the provider\'s public address.', 'ips' => []];
        }
    }
    return ['ok' => true, 'error' => '', 'ips' => $ips];
}

/** Is a provider base URL acceptable (https, public host)? '' when it is, else the reason. */
function sl_commsProviderUrlError(string $url): string
{
    $local = defined('CDP_ALLOW_LOCAL_PROVIDERS') && CDP_ALLOW_LOCAL_PROVIDERS === true;
    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match($local ? '~^https?://~i' : '~^https://~i', $url)) {
        return 'Enter the full https address, e.g. https://api.ultramsg.com/instance12345.';
    }
    $p = parse_url($url);
    if (!empty($p['user']) || !empty($p['pass']) || (isset($p['port']) && !$local && (int) $p['port'] !== 443)) {
        return 'Enter the address without a user name or port, e.g. https://api.ultramsg.com/instance12345.';
    }
    $h = sl_commsHostCheck((string) ($p['host'] ?? ''));
    return $h['ok'] ? '' : $h['error'];
}

/** One line per channel for the status strip (also used by the Overview). */
function sl_commsChannelStatus(): array
{
    $cs = cdp_getSettingsCourier();
    $s = cdp_commSettings(true);
    $out = [];
    // WhatsApp
    $cloud = $s['wa_provider'] === 'cloud';
    $waKeys = $cloud ? ($s['wa_cloud_phone_id'] !== '' && $s['wa_cloud_token'] !== '') : (trim((string) ($cs->api_ws_url ?? '')) !== '' && trim((string) ($cs->api_ws_token ?? '')) !== '');
    $waOn = (int) ($cs->active_whatsapp ?? 0) === 1;
    $out['whatsapp'] = ['label' => 'WhatsApp', 'icon' => 'i-wa', 'provider' => $cloud ? 'Meta Cloud API' : 'UltraMsg',
        'state' => !$waKeys ? 'Not set up' : ($waOn ? 'On' : 'Switched off'), 'ok' => $waKeys && $waOn, 'warn' => $waKeys && !$waOn];
    // SMS
    $smsOn = (int) ($cs->active_sms ?? 0) === 1;
    $smsReady = function_exists('cdp_smsReady') && cdp_smsReady();
    $prov = [];
    foreach (cdp_smsProviders() as $p => $l) {
        if (cdp_smsProviderReady($p)) {
            $prov[] = $l;
        }
    }
    $out['sms'] = ['label' => 'SMS', 'icon' => 'i-message', 'provider' => $prov ? implode(' + ', $prov) : 'Hubtel / mNotify',
        'state' => !$prov ? 'Not set up' : ($smsOn && $smsReady ? 'On' : 'Switched off'), 'ok' => $prov && $smsOn && $smsReady, 'warn' => $prov && !($smsOn && $smsReady)];
    // E-mail
    $mailer = strtoupper((string) ($cs->mailer ?? ''));
    $from = trim((string) ($cs->email_address ?? ''));
    $smtpOk = $mailer === 'SMTP' && trim((string) ($cs->smtp_host ?? '')) !== '' && trim((string) ($cs->smtp_user ?? '')) !== '' && (string) ($cs->smtp_password ?? '') !== '';
    $emailOk = $from !== '' && ($mailer === 'PHP' || $smtpOk);
    $out['email'] = ['label' => 'E-mail', 'icon' => 'i-mail', 'provider' => $mailer === 'PHP' ? 'PHP mail()' : 'SMTP',
        'state' => $emailOk ? 'On' : 'Not set up', 'ok' => $emailOk, 'warn' => false];
    // Automations worker
    $last = (string) cdp_opsSetting('comms', 'worker_last_run', '');
    $fresh = $last !== '' && strtotime($last) >= time() - 900;
    $on = $s['enabled'] === '1';
    $out['worker'] = ['label' => 'Automatic messages', 'icon' => 'i-bolt', 'provider' => $last !== '' ? 'Worker ran ' . sl_ago($last) : 'Worker never ran',
        'state' => !$on ? 'Switched off' : ($fresh ? 'Running' : 'Worker late'), 'ok' => $on && $fresh, 'warn' => $on && !$fresh];
    // King
    $ai = function_exists('cdp_commAiAvailable') ? cdp_commAiAvailable() : ['ok' => false];
    $bot = $s['bot_enabled'] === '1';
    $out['king'] = ['label' => cdp_kingName(), 'icon' => 'i-ai', 'provider' => !empty($ai['ok']) ? 'AI ready' : 'AI not set up',
        'state' => $bot ? (!empty($ai['ok']) ? 'Answering' : 'On, no AI') : 'Off', 'ok' => $bot && !empty($ai['ok']), 'warn' => $bot && empty($ai['ok'])];
    return $out;
}

function sl_commsSettingsData($user, $core): array
{
    $acc = sl_commsSettingsAccess($user);
    $ready = cdp_commEnsureTables();
    $cs = cdp_getSettingsCourier();
    $s = cdp_commSettings(true);
    $conn = $ready ? cdp_commConnection(true) : ['webhook_url' => '', 'webhook_has_key' => false];

    // SMS
    $hub = cdp_hubtelSmsConfig();
    $mno = cdp_mnotifySmsConfig();
    $sms = [
        'active' => (int) ($cs->active_sms ?? 0) === 1,
        'hub_id' => (string) $hub['client_id'], 'hub_secret_set' => (string) $hub['client_secret'] !== '', 'hub_sender' => (string) $hub['sender'],
        'mn_key_set' => (string) ($mno['api_key'] ?? '') !== '', 'mn_sender' => (string) cdp_smsSetting('mnotify_sender_id'),
        'hub_on' => cdp_smsProviderEnabled('hubtel'), 'mn_on' => cdp_smsProviderEnabled('mnotify'),
        'hub_ready' => cdp_smsProviderConfigured('hubtel'), 'mn_ready' => cdp_smsProviderConfigured('mnotify'),
        'default' => cdp_smsDefaultProvider(), 'fallback' => cdp_smsFallbackOn(), 'providers' => cdp_smsProviders(),
    ];

    // E-mail
    $sec = strtolower(trim((string) ($cs->smtp_secure ?? '')));
    $email = [
        'mailer' => strtoupper((string) ($cs->mailer ?? '')) === 'PHP' ? 'PHP' : 'SMTP',
        'from_name' => (string) ($cs->smtp_names ?? ''), 'from' => (string) ($cs->email_address ?? ''), 'site_email' => (string) ($cs->site_email ?? ''),
        'host' => (string) ($cs->smtp_host ?? ''), 'user' => (string) ($cs->smtp_user ?? ''), 'pass_set' => (string) ($cs->smtp_password ?? '') !== '',
        'port' => (int) ($cs->smtp_port ?? 0) ?: 587, 'secure' => in_array($sec, ['tls', 'ssl'], true) ? $sec : ($sec === '' || $sec === 'none' ? 'none' : 'tls'),
    ];

    // Sending rules
    $last = (string) cdp_opsSetting('comms', 'worker_last_run', '');
    $owner = function_exists('cdp_commStatusOwner') ? cdp_commStatusOwner() : 'legacy';
    $workerFresh = $last !== '' && strtotime($last) >= time() - 900;

    // Consent
    $consent = ['optout' => 0, 'wa_off' => 0, 'sms_off' => 0, 'email_off' => 0, 'mkt' => ['whatsapp' => 0, 'sms' => 0, 'email' => 0], 'unsub' => 0];
    if ($ready) {
        $r = cdp_opsOne("SELECT SUM(opted_out_all = 1) o, SUM(whatsapp = 0) w, SUM(sms = 0) s, SUM(email = 0) e,
                                SUM(opted_out_all = 0 AND mkt_whatsapp = 1) mw, SUM(opted_out_all = 0 AND mkt_sms = 1) ms, SUM(opted_out_all = 0 AND mkt_email = 1) me FROM cdb_comm_prefs");
        if ($r) {
            $consent['optout'] = (int) $r->o;
            $consent['wa_off'] = (int) $r->w;
            $consent['sms_off'] = (int) $r->s;
            $consent['email_off'] = (int) $r->e;
            $consent['mkt'] = ['whatsapp' => (int) $r->mw, 'sms' => (int) $r->ms, 'email' => (int) $r->me];
        }
    }
    if (sl_tableExists('cdb_newsletter_unsubscribes')) {
        $consent['unsub'] = (int) sl_scalar("SELECT COUNT(*) FROM cdb_newsletter_unsubscribes", [], 0);
    }

    // King
    $ai = function_exists('cdp_commAiAvailable') ? cdp_commAiAvailable($user) : ['ok' => false, 'code' => 'not_configured', 'message' => '', 'setup_url' => sl_route('AiCenter'), 'assistant' => cdp_kingName()];
    $botToday = $ready ? (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_comm_messages WHERE author_type = 'bot' AND created_at >= :f", [':f' => date('Y-m-d 00:00:00')], 0) : 0;

    // Costs (read from Message costs, where they are set)
    $mc = cdp_mcSettings();
    $rates = cdp_mcCurrentRates();
    $spent = ['whatsapp' => 0.0, 'sms' => 0.0, 'email' => 0.0];
    $total = 0.0;
    try {
        $rep = cdp_mcReport(['from' => date('Y-m-01'), 'to' => date('Y-m-d'), 'channel' => '', 'source' => '', 'group' => 'day']);
        foreach ($spent as $ch => $v) {
            $spent[$ch] = (float) ($rep['totals'][$ch]['cost'] ?? 0);
        }
        $total = (float) ($rep['grand']['cost'] ?? array_sum($spent));
    } catch (Throwable $e) {
        // costs are optional
    }

    $siteUrl = cdp_opsSiteUrl();
    return [
        'acc' => $acc,
        'ready' => $ready,
        'demo' => defined('CDP_APP_MODE_DEMO') && CDP_APP_MODE_DEMO === true,
        'status' => sl_commsChannelStatus(),
        'ajax' => 'ajax/comms/settings_ajax.php',
        'wa' => [
            'active' => (int) ($cs->active_whatsapp ?? 0) === 1,
            'provider' => $s['wa_provider'] === 'cloud' ? 'cloud' : 'ultramsg',
            'url' => (string) ($cs->api_ws_url ?? ''), 'token_set' => (string) ($cs->api_ws_token ?? '') !== '',
            'cloud_phone_id' => (string) $s['wa_cloud_phone_id'], 'cloud_token_set' => (string) $s['wa_cloud_token'] !== '',
            'cloud_secret_set' => (string) $s['wa_cloud_app_secret'] !== '', 'cloud_verify' => (string) $s['wa_cloud_verify'],
            'cloud_version' => (string) ($s['wa_cloud_version'] ?: 'v20.0'),
            // The key is a secret: only people who may see secrets get the full address.
            'webhook' => $acc['secrets'] ? (string) ($conn['webhook_url'] ?? '') : cdp_commWebhookBase() . '?key=…',
            'webhook_base' => cdp_commWebhookBase(),
            'webhook_has_key' => !empty($conn['webhook_has_key']),
            'connect_url' => sl_commsWhatsAppConnectUrl(),
            'site_url_missing' => $siteUrl === '' && !(defined('CDP_APP_URL') && CDP_APP_URL !== ''),
        ],
        'sms' => $sms,
        'email' => $email,
        'rules' => [
            'enabled' => $s['enabled'] === '1', 'owner' => $owner, 'last_run' => $last, 'worker_fresh' => $workerFresh,
            'quiet_start' => (string) $s['quiet_start'], 'quiet_end' => (string) $s['quiet_end'], 'batch_size' => (int) $s['batch_size'],
            'claim_after_days' => (int) $s['claim_after_days'], 'claim_every_days' => (int) $s['claim_every_days'], 'claim_max' => (int) $s['claim_max'],
            'pay_after_days' => (int) $s['pay_after_days'], 'pay_every_days' => (int) $s['pay_every_days'], 'pay_max' => (int) $s['pay_max'],
            'pay_min_amount' => (string) $s['pay_min_amount'], 'currency' => (string) $s['currency'],
            'approval' => $s['campaign_needs_approval'] === '1',
        ],
        'consent' => $consent,
        'king' => [
            'name' => cdp_kingName(), 'on' => $s['bot_enabled'] === '1', 'cap' => (int) $s['bot_daily_cap'], 'instructions' => (string) $s['bot_instructions'],
            'ai' => $ai, 'today' => $botToday, 'persona_url' => sl_route('CsAI'),
        ],
        'costs' => [
            'currency' => (string) ($mc['currency'] ?? ''), 'rates' => $rates, 'spent' => $spent, 'total' => $total,
            'sms_parts' => (int) ($mc['sms_parts'] ?? 0) === 1, 'url' => sl_route('MessageCosts'),
            'canEdit' => function_exists('cdp_mcCanEdit') && cdp_mcCanEdit($user),
            'canView' => $user->cdp_hasPermission('view_message_logs'),
        ],
        'links' => [
            'automations' => sl_route('CommsAutomations'), 'statuses' => sl_route('CommsStatuses'), 'customers' => sl_route('CommsCustomers'),
            'surveys' => sl_route('CommsSurveys'), 'outbox' => sl_route('CommsOutbox'), 'apis' => sl_route('ApiConnections'),
            'company' => 'settings_form.php?f=system', 'ai' => sl_route('AiCenter'), 'prefs' => sl_route('NotificationPrefs'),
        ],
        'me' => ['phone' => (string) sl_scalar("SELECT phone FROM cdb_users WHERE id = :u", [':u' => (int) $user->uid], ''), 'email' => (string) ($user->email ?? '')],
    ];
}
