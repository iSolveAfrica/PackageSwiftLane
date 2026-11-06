<?php
/** Data for the Settings hub (SettingsHub.dc.html): cdb_settings, SMS settings and the index of every legacy settings page. */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/querys.php';

function sl_settingsIndex($user): array
{
    $g = [
        ['label' => 'Company and system', 'icon' => 'i-cog', 'items' => [
            ['Company and system', 'Name, address, sign-up rules, lockers', 'settings_form.php?f=system', 'view_tools'], ['Language, currency and rate', 'Language, time zone, money format, USD rate', 'tools.php#language', 'view_tools'],
            ['Logo and images', 'Logo, web logo, favicon', 'settings_form.php?f=logo', 'view_tools'], ['Search and sharing (SEO)', 'Meta description, keywords, share card', 'settings_form.php?f=seo', 'view_tools'], ['Google Maps', 'API key and map centre', 'settings_form.php?f=google', 'view_tools'], ['Taxes and fees', 'Tax, insurance, declared value, weight units', 'taxesadnfees.php', 'view_tools'],
            ['Tracking and invoice numbers', 'Prefixes, digits, terms and signatures', 'track_invoice.php', 'view_tools'], ['New shipment defaults', 'Default service, packaging, courier, payment', 'info_ship_default.php', 'view_tools'],
            ['Backup', 'Download or restore a database backup', 'backup.php', 'view_tools']]],
        ['label' => 'Messaging', 'icon' => 'i-message', 'items' => [
            ['SMS providers', 'Hubtel, mNotify, default and fallback', 'tools.php#messaging', 'view_tools'], ['SMS (legacy Nexmo / Twilio)', 'Older SMS gateways', 'config_sms.php', 'view_tools'],
            ['Twilio', 'Twilio account', 'config_twilio.php', 'view_tools'], ['WhatsApp API', 'URL and token', 'api_connections.php?p=whatsapp', 'manage_api_settings'],
            ['Switch SMS on or off', 'Global SMS switch', 'activate_sms.php', 'view_tools'], ['Switch WhatsApp on or off', 'Global WhatsApp switch', 'activate_whatsapp.php', 'view_tools'],
            ['E-mail (SMTP)', 'Mail server for every e-mail', 'api_connections.php?p=smtp', 'manage_api_settings'], ['E-mail server form', 'Mailer, host, port, user and password', 'settings_form.php?f=smtp', 'view_tools'], ['Notification Center', 'Which event sends which channel', 'notification_center.php', 'view_tools']]],
        ['label' => 'Logistics lists', 'icon' => 'i-truck', 'items' => [
            ['Offices', 'Offices and hubs', 'offices_list.php', 'view_tools'], ['Branch offices', 'Agencies and branches', 'branchoffices_list.php', 'view_tools'], ['Courier companies', 'Carriers you work with', 'courier_company_list.php', 'view_tools'],
            ['Packaging', 'Box and bag types', 'packaging_list.php', 'view_tools'], ['Shipping modes', 'Air, sea, express…', 'shipping_mode_list.php', 'view_tools'], ['Delivery times', 'Service levels', 'delivery_time_list.php', 'view_tools'],
            ['Shipment statuses', 'Names and colours of statuses', 'status_courier_list.php', 'view_tools'], ['Item categories', 'What customers ship', 'category_list.php', 'view_tools'], ['Incoterms', 'Trade terms', 'incoterms_list.php', 'view_tools'],
            ['Shipping lines', 'Sea carriers', 'shipline_list.php', 'view_tools'], ['Shipping addresses', 'Warehouse addresses customers ship to', 'shipping_addresses.php', 'view_tools']]],
        ['label' => 'Payments', 'icon' => 'i-card', 'items' => [
            ['Payment gateways', 'Stripe, PayPal, Paystack, Hubtel, wire', 'payment_mode_list.php', 'view_tools'], ['Payment methods', 'Cash, card, mobile money…', 'payment_methods_list.php', 'view_tools'], ['Gateway keys', 'Masked keys and live checks', 'api_connections.php?p=stripe', 'manage_api_settings']]],
        ['label' => 'Places', 'icon' => 'i-globe', 'items' => [
            ['Countries', 'Country list', 'countries_list.php', 'view_tools'], ['States and regions', 'State list', 'states_list.php', 'view_tools'], ['Cities', 'City list', 'cities_list.php', 'view_tools']]],
        ['label' => 'Templates', 'icon' => 'i-file', 'items' => [
            ['SMS templates', 'Text messages', 'templates_sms.php', 'list_sms_templates'], ['WhatsApp templates', 'WhatsApp messages', 'templates_whatsapp.php', 'manage_whatsapp_templates'],
            ['E-mail templates', 'System e-mails', 'templates_email.php', 'view_tools'], ['Default templates', 'Template per event', 'templates_default.php', 'view_tools'], ['E-mail builder', 'Drag-and-drop newsletters', 'newsletter.php', 'manage_newsletter']]],
    ];
    $q = trim((string) ($_GET['sq'] ?? ''));
    $grp = (string) ($_GET['grp'] ?? '');
    $out = [];
    $all = 0;
    $chips = [];
    foreach ($g as $x) {
        $items = [];
        foreach ($x['items'] as $i) {
            if (!$user->cdp_hasPermission($i[3])) continue;
            $all++;
            if ($q !== '' && stripos($i[0] . ' ' . $i[1], $q) === false) continue;
            $items[] = ['t' => $i[0], 'd' => $i[1], 'href' => $i[2]];
        }
        $chips[] = ['label' => $x['label'], 'icon' => $x['icon'], 'n' => count($items), 'cls' => $grp === $x['label'] ? 'on' : '', 'href' => sl_url(['grp' => $grp === $x['label'] ? null : $x['label']]) . '#all'];
        if ($items && ($grp === '' || $grp === $x['label'])) $out[] = ['label' => $x['label'], 'icon' => $x['icon'], 'items' => $items];
    }
    return ['sets' => $out, 'grps' => $chips, 'allN' => $all, 'sq' => $q, 'noSets' => !$out];
}

function sl_settingsHubData($user, $core): array
{
    $s = cdp_getSettingsCourier();
    $d = sl_settingsIndex($user);
    $d['s'] = $s;
    $d['timezones'] = DateTimeZone::listIdentifiers();
    $d['languages'] = ['en' => 'English', 'es' => 'Español', 'fr' => 'Français', 'ar' => 'العربية'];
    $d['formats'] = [1 => '1234.56', 2 => '1,234.56', 3 => '1234,56', 4 => '1.234,56'];
    $d['countries'] = sl_rows('SELECT name FROM cdb_countries ORDER BY name');
    $d['rateLog'] = sl_tableExists('cdb_exchange_rate_log') ? array_map(fn($r) => ['what' => number_format((float) $r->old_rate, 2) . ' → ' . number_format((float) $r->new_rate, 2), 'who' => trim((string) $r->who) ?: 'System', 'when' => sl_ago($r->changed_at)],
        sl_rows("SELECT l.*, CONCAT(u.fname, ' ', u.lname) who FROM cdb_exchange_rate_log l LEFT JOIN cdb_users u ON u.id = l.changed_by ORDER BY l.id DESC LIMIT 5")) : [];
    @include_once CDP_ROOT . '/helpers/hubtel_sms.php';
    $sms = ['ok' => function_exists('cdp_smsSetting')];
    if ($sms['ok']) {
        $hub = cdp_hubtelSmsConfig();
        $mno = function_exists('cdp_mnotifySmsConfig') ? cdp_mnotifySmsConfig() : ['api_key' => '', 'sender' => ''];
        $sms += ['hub_id' => $hub['client_id'], 'hub_secret_set' => $hub['client_secret'] !== '', 'hub_sender' => $hub['sender'], 'mn_key_set' => ($mno['api_key'] ?? '') !== '', 'mn_sender' => (string) cdp_smsSetting('mnotify_sender_id'),
            'hub_on' => cdp_smsProviderEnabled('hubtel') && cdp_smsProviderConfigured('hubtel'), 'mn_on' => cdp_smsProviderEnabled('mnotify') && cdp_smsProviderConfigured('mnotify'), 'default' => cdp_smsDefaultProvider(), 'fallback' => cdp_smsFallbackOn(), 'active' => (int) ($s->active_sms ?? 0) === 1,
            'canManage' => cdp_smsCanManage($user)];
    }
    $d['sms'] = $sms;
    $d['canEdit'] = $user->cdp_hasPermission('view_tools');
    return $d;
}
