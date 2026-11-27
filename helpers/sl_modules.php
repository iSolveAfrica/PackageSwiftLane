<?php
/**
 * Module Controller: which parts of Swift Lane are switched on, and the one place that enforces it.
 *
 * State lives in cdb_sl_settings as module_<id> = '1' | '0' (absent = on). The financial module reuses
 * the existing finance switch (helpers/finance_switch.php, 'finance_enabled'), so the Billing hub
 * toggle and this page are the same switch.
 *
 * "Off" means, for every user including super admins:
 *   navigation   sl_navItemEnabled() (called by helpers/sl_nav.php for every menu item) hides the item,
 *                both by the explicit nav ids below and by the item's board -> page -> module mapping
 *   pages        sl_moduleGuardRequest() answers 403 "This module is switched off". sl_gate() calls it
 *                ('gate'); public and legacy pages that do not call sl_gate are covered only once
 *                loader.php calls it ('any', see sl_moduleCentralGuard()).
 *   AJAX         the same guard ('any' from loader.php) answers JSON 403 {error: module_disabled} for ajax/
 *   cron jobs    the same guard ('any' from loader.php) makes a job under cron/ print "skipped" and exit 0
 *   API          the same guard ('any' from loader.php) answers 403 module_disabled for an /api/v1
 *                resource of a switched-off module (sl_apiResourceEnabled)
 *   dashboards   sl_moduleEnabled('id') for widgets (only where a data builder calls it)
 * Wiring the central guard: one line at the end of loader.php (coordinator, not this package):
 *     if (is_file(CDP_ROOT . '/helpers/sl_modules.php')) { require_once CDP_ROOT . '/helpers/sl_modules.php'; sl_moduleGuardRequest('any'); }
 * The path -> module map is sl_moduleRouteMap(). The guard also locks the developer console pages
 * (helpers/dev_access.php): locked -> dev_access.php unlock form; section not granted -> 403.
 */
require_once __DIR__ . '/sl_view.php';

/** The module catalogue: id => [label, group, desc, nav ids, customer, api, jobs, core?]. */
function sl_modules(): array
{
    return [
        // Operations
        'air'           => ['label' => 'Air shipping', 'group' => 'ops', 'desc' => 'Air freight shipments, labels, invoices, tracking and air consolidations', 'nav' => ['air', 'shiptrack'], 'customer' => true, 'api' => ['shipments'], 'jobs' => []],
        'sea'           => ['label' => 'Sea shipping', 'group' => 'ops', 'desc' => 'Sea packages registered to lockers and sea consolidations', 'nav' => ['sea'], 'customer' => true, 'api' => ['packages'], 'jobs' => []],
        'consolidation' => ['label' => 'Consolidations', 'group' => 'ops', 'desc' => 'Air and sea consolidations: group shipments and packages into one load', 'nav' => ['consol'], 'customer' => false, 'api' => ['consolidations'], 'jobs' => []],
        'prealerts'     => ['label' => 'Pre-alerts', 'group' => 'ops', 'desc' => 'Customers announce parcels before they arrive', 'nav' => ['prealert'], 'customer' => true, 'api' => ['pre-alerts'], 'jobs' => []],
        'pickups'       => ['label' => 'Pickups', 'group' => 'ops', 'desc' => 'Home pickup requests, pickup aging and pickup reports', 'nav' => ['pickup'], 'customer' => true, 'api' => ['pickups'], 'jobs' => ['cron/pickup_aging.php']],
        'warehouse'     => ['label' => 'Warehouse', 'group' => 'ops', 'desc' => 'Package intake, hand-out, warehouse delivery, pickup codes and locker search', 'nav' => ['warehouse', 'whpick', 'handout', 'locker', 'intake', 'codes'], 'customer' => false, 'api' => [], 'jobs' => []],
        'delivery'      => ['label' => 'Delivery system', 'group' => 'ops', 'desc' => 'Last mile, customers\' delivery requests, rider dispatch, delivery tracking and rider fees', 'nav' => ['lastmile', 'deliverydispatch', 'dispatch', 'dtrack', 'rfees', 'deliveries'], 'customer' => true, 'api' => ['deliveries'], 'jobs' => []],
        'freight'       => ['label' => 'Freight', 'group' => 'ops', 'desc' => 'Freight hub, departures and arrivals, waybills, cargo exceptions, partners', 'nav' => ['frhub', 'frsched', 'frwb', 'frex', 'frpart'], 'customer' => false, 'api' => [], 'jobs' => []],
        'insurance'     => ['label' => 'Insurance', 'group' => 'ops', 'desc' => 'Cargo insurance settings and claims', 'nav' => ['frins'], 'customer' => false, 'api' => [], 'jobs' => []],
        // People
        'customers'     => ['label' => 'Customers', 'group' => 'people', 'desc' => 'Customer accounts and recipients', 'nav' => ['cust', 'recip'], 'customer' => false, 'api' => ['customers', 'recipients'], 'jobs' => [], 'core' => true],
        'drivers'       => ['label' => 'Drivers and riders', 'group' => 'people', 'desc' => 'Rider accounts and the rider app', 'nav' => ['drivers', 'drvapp'], 'customer' => false, 'api' => [], 'jobs' => []],
        'staff'         => ['label' => 'Staff and roles', 'group' => 'people', 'desc' => 'Staff accounts, roles and permissions', 'nav' => ['staff', 'perm'], 'customer' => false, 'api' => [], 'jobs' => [], 'core' => true],
        // Finance
        'finance'       => ['label' => 'Financial', 'group' => 'finance', 'desc' => 'Billing, payments, transactions, receivables, financial overview, My bills', 'nav' => ['billhub', 'trans', 'sendp', 'ar', 'fin', 'pay', 'mybills'], 'customer' => true, 'api' => ['payments', 'invoices'], 'jobs' => []],
        'loyalty'       => ['label' => 'Loyalty and referrals', 'group' => 'finance', 'desc' => 'Points, tiers, coupons', 'nav' => ['loyalty'], 'customer' => true, 'api' => [], 'jobs' => []],
        // Messaging
        'messaging'     => ['label' => 'Communications', 'group' => 'messaging', 'desc' => 'Messages, WhatsApp inbox, campaigns, push, templates, message logs and costs', 'nav' => ['commshub', 'commsinbox', 'commscenter', 'campaigns', 'messages', 'notifc', 'notifystudio', 'ebuild', 'msgtpl', 'bcast', 'compose', 'devbilling'], 'customer' => false, 'api' => ['notifications'], 'jobs' => ['cron/comms_worker.php']],
        'automations'   => ['label' => 'Automations', 'group' => 'messaging', 'desc' => 'When this happens, do that', 'nav' => ['auto'], 'customer' => false, 'api' => [], 'jobs' => []],
        // Support and AI
        'support'       => ['label' => 'Customer care', 'group' => 'support', 'desc' => 'Support desk, tickets, rules, playbooks, channels, customer chat', 'nav' => ['supporthub', 'servicepanel', 'tickets', 'csrules', 'playbook', 'knowledge', 'csrep', 'cschan', 'cssupai'], 'customer' => true, 'api' => [], 'jobs' => ['cron/support_worker.php']],
        'ai'            => ['label' => 'AI and King', 'group' => 'support', 'desc' => 'Ask King staff assistant and the AI Automation Center', 'nav' => ['aicenter', 'csai'], 'customer' => false, 'api' => [], 'jobs' => []],
        // Insights and developer
        'reports'       => ['label' => 'Reports', 'group' => 'insights', 'desc' => 'The Reports page and every report list, print and Excel export (activity logs and the Security Center are not part of it)', 'nav' => ['reports'], 'customer' => false, 'api' => [], 'jobs' => []],
        'api'           => ['label' => 'REST API', 'group' => 'insights', 'desc' => 'Partner API keys, monitoring and live traffic', 'nav' => ['apis', 'apitraffic'], 'customer' => true, 'api' => ['*'], 'jobs' => []],
        'developer'     => ['label' => 'Developer console', 'group' => 'insights', 'desc' => 'Switched off only by the locked shutdown on Developer access (needs an unlock code to return)', 'nav' => [], 'customer' => false, 'api' => [], 'jobs' => [], 'core' => true],
        // Customer portal only
        'portal_signup' => ['label' => 'Customer sign-up', 'group' => 'portal', 'desc' => 'New customers create their own account', 'nav' => [], 'customer' => true, 'api' => [], 'jobs' => []],
        'portal_track'  => ['label' => 'Public tracking', 'group' => 'portal', 'desc' => 'Track a parcel without signing in', 'nav' => ['track'], 'customer' => true, 'api' => ['tracking'], 'jobs' => []],
        'pwa_install'   => ['label' => 'Install the phone app', 'group' => 'portal', 'desc' => 'On phones, everyone (customers, riders, staff) gets a full-screen prompt to install the Swift Lane app before using the site in the browser', 'nav' => [], 'customer' => true, 'api' => [], 'jobs' => []],
        'portal_quote'  => ['label' => 'Quote calculator', 'group' => 'portal', 'desc' => 'Price a shipment before booking', 'nav' => [], 'customer' => true, 'api' => [], 'jobs' => []],
    ];
}

function sl_moduleGroups(): array
{
    return [
        'ops' => ['label' => 'Operations', 'icon' => 'i-box', 'desc' => 'Shipping, consolidation, pickups, warehouse, delivery and freight'],
        'people' => ['label' => 'People', 'icon' => 'i-users', 'desc' => 'Customers, riders and staff'],
        'finance' => ['label' => 'Finance', 'icon' => 'i-dollar', 'desc' => 'Billing, payments and loyalty'],
        'messaging' => ['label' => 'Communications', 'icon' => 'i-message', 'desc' => 'Messages and automations'],
        'support' => ['label' => 'Support and AI', 'icon' => 'i-ticket', 'desc' => 'Customer care and assistants'],
        'insights' => ['label' => 'Insights and developer', 'icon' => 'i-chart', 'desc' => 'Reports, logs, API and the developer console'],
        'portal' => ['label' => 'Customer portal', 'icon' => 'i-globe', 'desc' => 'Public and customer-only features'],
    ];
}

/**
 * Path (relative to the app root) -> modules it belongs to. A path belongs to every matching rule;
 * it is refused when ANY of its modules is off (a sea consolidation needs Sea AND Consolidations).
 * Payment webhooks (ajax/gateway/) are deliberately not listed: a payment under way is still booked.
 */
function sl_moduleRouteMap(): array
{
    return [
        // Air shipping (not courier_company_*: that is a settings catalogue)
        ['~^courier_(?!company)~', ['air']],
        ['~^(print_label_ship|print_inv_ship|send_email_pdf\.php|dashboard_admin_shipments|report_top_users_air|top_users_air_|add_payment_gateways_courier|payments_gateways_courier_list)~', ['air']],
        ['~^ajax/(courier/|dashboard/shipments/|validate_track_number_courier|sl/shipment_action_ajax)~', ['air']],
        ['~^(consolidate_deliver_shipment|consolidate_shipment_tracking|print_consolidate\.php|print_label_consolidate(\.php|_multiple\.php)|send_email_pdf_consolidate\.php|dashboard_admin_consolidated|report_consolidate_(?!packages_)|payments_gateways_consolidate_list|add_payment_gateways_consolidate\.php|push_notifications_consolidation)~', ['air', 'consolidation']],
        ['~^ajax/(consolidate/|dashboard/consolidated/|validate_track_number_consolidate|select2_user_consolidation)~', ['air', 'consolidation']],
        // Sea shipping
        ['~^(customer_packages?_|print_label_package|print_invoice_package|print_customer_package_track|send_email_pdf_packages|report_top_users_sea|top_users_sea_|dashboard_admin_packages_customers|add_payment_gateways_package\.php|payments_gateways_list)~', ['sea']],
        ['~^ajax/(customers_packages/|validate_track_number_customer_package|sl/package_ajax)~', ['sea']],
        ['~^(consolidate_package_|print_consolidate_package|print_label_consolidate_package|print_label_consolidate_multiple_package|send_email_pdf_consolidate_packages|dashboard_admin_package_consolidated|report_consolidate_packages_|payments_gateways_package_consolidate|add_payment_gateways_consolidate_package)~', ['sea', 'consolidation']],
        ['~^ajax/(consolidate_packages/|dashboard/consolidated_package/)~', ['sea', 'consolidation']],
        // Consolidations hub (air and sea in one page)
        ['~^(consolidate_list|consolidate_view|consolidate_add|consolidate_edit|consolidate_parking_list|packing_list)~', ['consolidation']],
        ['~^ajax/sl/consolidation_ajax~', ['consolidation']],
        // Pre-alerts
        ['~^(prealert_|customer_packages_add_from_prealert)~', ['prealerts']],
        ['~^ajax/(pre_alerts/|sl/prealert_ajax|sl/shipment_prealert_ajax)~', ['prealerts']],
        // Pickups (pickup_client.php and pickup_codes.php are warehouse hand-out)
        ['~^(pickup_(add|add_full|list|accept|aging)\.php|dashboard_admin_pickup|report_pickup_)~', ['pickups']],
        ['~^ajax/(pickup/|pickup_aging/|dashboard/pickup/|sl/pickup_ajax)~', ['pickups']],
        ['~^cron/pickup_aging\.php~', ['pickups']],
        // Warehouse
        ['~^(warehouse|package_intake|pickup_client|pickup_codes|locker_search)~', ['warehouse']],
        ['~^ajax/(sl/warehouse_ajax|sl/package_intake_ajax|locker/|pickup_code/|pickup_client_ajax)~', ['warehouse']],
        // Delivery system
        ['~^(last_mile|delivery_(dispatch|request|job|proof|schedule|settings|tracking)|rider_(dispatch|detail|settings))\.php~', ['delivery']],
        ['~^ajax/sl/(delivery_ajax|delivery_settings_ajax|delivery_zone_ajax|rider_ajax|rider_fees_ajax)~', ['delivery']],
        // Freight and insurance
        ['~^(freight_|waybills|waybill_print|cargo_exceptions)~', ['freight']],
        ['~^ajax/sl/(freight_|waybill_ajax|cargo_exception_ajax)~', ['freight']],
        ['~^insurance_claims~', ['insurance']],
        ['~^ajax/sl/insurance_~', ['insurance']],
        // Drivers
        ['~^(drivers_(add|edit|list)|driver_app)\.php~', ['drivers']],
        ['~^ajax/drivers/~', ['drivers']],
        // Financial (finance_switch_ajax.php stays reachable so the Billing hub can switch it back on)
        ['~^(financial_(?!sheet\.php)|transactions|accounts_receivable|global_payments_gateways|my_bills|pay_checkout|send_prices|print_charge|report_customers_balance_|report_payments_received_)~', ['finance']],
        ['~^ajax/(accounts_receivable/|customer/my_bills_ajax|global_payments_gateways_ajax|dashboard/account_receivable/|reports/financial_|sl/ar_remind_ajax|sl/record_payment_ajax|sl/send_prices_ajax)~', ['finance']],
        // Loyalty
        ['~^loyalty_~', ['loyalty']],
        ['~^ajax/sl/loyalty_ajax~', ['loyalty']],
        // Communications
        ['~^(comms_(center|hub|inbox|preferences)|compose_message|push_notifications\.php|newsletter\.php|templates_|notification_center|notify_studio|campaigns|message_logs|message_costs)~', ['messaging']],
        ['~^ajax/(comms/|notify_sms/|notify_whatsapp/|sl/compose_ajax|sl/notify_studio_ajax|sl/notification_rules_ajax|sl/templates_ajax|reports/message_)~', ['messaging']],
        ['~^cron/comms_worker\.php~', ['messaging']],
        ['~^automations~', ['automations']],
        ['~^ajax/sl/automations_ajax~', ['automations']],
        // Customer care and AI
        ['~^support(?!_files)~', ['support']],
        ['~^ajax/support/~', ['support']],
        ['~^cron/support_worker\.php~', ['support']],
        ['~^ai_(assistant|center)~', ['ai']],
        ['~^ajax/ai_assistant/~', ['ai']],
        // Reports (report_* pages also belong to the module of what they report on)
        ['~^(reports\.php|report_)~', ['reports']],
        ['~^ajax/reports/report_~', ['reports']],
        // REST API pages
        ['~^api_(access|connections|monitor|traffic)~', ['api']],
        ['~^ajax/sl/(api_connections_ajax|api_tokens_ajax)~', ['api']],
        // Customer portal
        ['~^sign-up\.php~', ['portal_signup']],
        ['~^ajax/sign_up_ajax~', ['portal_signup']],
        ['~^(track|tracking)\.php~', ['portal_track']],
        ['~^cotizar\.php~', ['portal_quote']],
    ];
}

/** Developer console pages -> section (helpers/dev_access.php). dev_access.php handles itself. */
function sl_moduleDevPages(): array
{
    return [
        'dev_overview.php' => 'overview', 'dev_services.php' => 'services', 'dev_logs.php' => 'logs', 'dev_metrics.php' => 'metrics',
        'dev_infrastructure.php' => 'infrastructure', 'dev_overrides.php' => 'overrides', 'dev_alerts.php' => 'alerts',
        'dev_deployments.php' => 'deployments', 'dev_github.php' => 'github', 'dev_integrations.php' => 'github',
        'dev_database.php' => 'database', 'dev_issues.php' => 'issues', 'dev_changelog.php' => 'issues', 'module_controller.php' => 'modules',
        // legacy pages that belong to the developer side
        'backup.php' => 'database', 'message_costs.php' => 'logs',
    ];
}

/** Main-navigation item id -> developer section. */
function sl_moduleDevNav(): array
{
    return [
        'devov' => 'overview', 'devsvc' => 'services', 'devlogs' => 'logs', 'devmet' => 'metrics', 'devinfra' => 'infrastructure',
        'devdep' => 'deployments', 'devalert' => 'alerts', 'devissues' => 'issues', 'devint' => 'github', 'devgh' => 'github',
        'devdb' => 'database', 'devover' => 'overrides', 'devmod' => 'modules', 'devaccess' => 'access', 'devbilling' => 'logs',
    ];
}

/** All module states: id => bool. */
function sl_moduleStates(bool $fresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$fresh) {
        return $cache;
    }
    $cache = [];
    foreach (sl_modules() as $id => $m) {
        $cache[$id] = true;
    }
    try {
        if (sl_tableExists('cdb_sl_settings')) {
            foreach (sl_rows("SELECT setting_key, setting_value FROM cdb_sl_settings WHERE setting_key LIKE 'module\\_%'") as $r) {
                $id = substr((string) $r->setting_key, 7);
                if (isset($cache[$id])) {
                    $cache[$id] = (string) $r->setting_value !== '0';
                }
            }
        }
    } catch (Throwable $e) {
        // defaults stand: everything on
    }
    require_once __DIR__ . '/finance_switch.php';
    $cache['finance'] = cdp_financeEnabled();
    return $cache;
}

/** True when the module is on (unknown ids are on, core modules are always on). */
function sl_moduleEnabled(string $id): bool
{
    $mods = sl_modules();
    if (!isset($mods[$id]) || !empty($mods[$id]['core'])) {
        return true;
    }
    return sl_moduleStates()[$id];
}

/** Modules a relative path (e.g. "ajax/courier/x.php", "courier_list.php") belongs to. */
function sl_moduleScriptModules(string $rel): array
{
    $rel = strtolower(ltrim(str_replace('\\', '/', $rel), '/'));
    $out = [];
    foreach (sl_moduleRouteMap() as [$re, $mods]) {
        if (preg_match($re, $rel)) {
            foreach ($mods as $m) {
                $out[$m] = true;
            }
        }
    }
    return array_keys($out);
}

/** First switched-off module that serves this path, or ''. */
function sl_moduleOffFor(string $rel): string
{
    foreach (sl_moduleScriptModules($rel) as $m) {
        if (!sl_moduleEnabled($m)) {
            return $m;
        }
    }
    return '';
}

/** The running script relative to the app root ("ajax/sl/x.php", "cron/y.php"). */
function sl_moduleRelPath(): string
{
    $file = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
    $real = $file !== '' ? realpath($file) : false;
    $root = realpath(CDP_ROOT);
    if ($real && $root && strpos($real, $root . DIRECTORY_SEPARATOR) === 0) {
        return str_replace('\\', '/', substr($real, strlen($root) + 1));
    }
    return ltrim(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/');
}

/**
 * Central guard: refuse a request served by a switched-off module, and keep the developer console
 * locked / per-section. Safe to call from loader.php ('any': every page, AJAX endpoint, public page,
 * cron job and /api/v1 request), sl_gate() ('gate': signed-in pages, renders inside the app shell),
 * ajax_guard require_login() and cron scripts.
 */
function sl_moduleGuardRequest(string $ctx = 'any'): void
{
    static $done = [];
    if (isset($done[$ctx])) {
        return;
    }
    $done[$ctx] = true;
    if ($ctx !== 'gate') {
        $GLOBALS['sl_module_guard_central'] = true; // the Modules page reports whether the central guard is wired
    }
    $rel = sl_moduleRelPath();
    $cli = PHP_SAPI === 'cli';
    $json = !$cli && (strpos($rel, 'ajax/') === 0 || strpos($rel, 'api/') === 0
        || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest');

    // 1. switched-off business module
    $off = sl_moduleOffFor($rel);
    if ($off === '' && !$cli && strpos($rel, 'api/v1/') === 0) {
        $off = sl_moduleApiOff();
    }
    if ($off !== '') {
        $label = sl_modules()[$off]['label'];
        if ($cli) {
            echo '[' . date('Y-m-d H:i:s') . "] skipped: the {$label} module is switched off (Module Controller).\n";
            exit(0);
        }
        if ($json) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Cache-Control: no-store');
            sl_json(['success' => false, 'ok' => false, 'error' => 'module_disabled', 'module' => $off, 'message' => "The {$label} module is switched off."], 403);
        }
        if ($ctx !== 'gate') {
            sl_moduleLoadUser();
        }
        sl_moduleOffPage($off, true);
    }

    // 2. developer console pages: locked shutdown and per-section access
    if ($cli) {
        return;
    }
    $dev = sl_moduleDevPages()[$rel] ?? null;
    if ($dev === null) {
        return;
    }
    require_once __DIR__ . '/devops.php';
    $user = $ctx === 'gate' ? ($GLOBALS['user'] ?? null) : sl_moduleLoadUser();
    if (!($user instanceof User) || empty($user->logged_in)) {
        return; // signed out: the page's own sl_gate() sends the visitor to login
    }
    if (cdp_devLocked()) {
        header('Location: ' . (cdp_devAssigned($user) ? 'dev_access.php' : 'error403.php'));
        exit;
    }
    if (!cdp_devCanUse($user) || !cdp_devSectionAllowed($user, $dev)) {
        header('Location: error403.php');
        exit;
    }
}

/** The signed-in User for the guard when it runs before the page created one (loader.php). */
function sl_moduleLoadUser(): ?User
{
    if (isset($GLOBALS['user']) && $GLOBALS['user'] instanceof User) {
        return $GLOBALS['user'];
    }
    if (!class_exists('User')) {
        return null;
    }
    try {
        $GLOBALS['user'] = new User();
        return $GLOBALS['user'];
    } catch (Throwable $e) {
        return null;
    }
}

/** For /api/v1/<resource>/...: the switched-off module serving that resource, or ''. */
function sl_moduleApiOff(): string
{
    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if (!preg_match('~/api/v1/([a-z0-9_-]+)~i', $path, $m)) {
        return '';
    }
    $res = strtolower($m[1]);
    if (in_array($res, ['auth', 'docs'], true)) {
        return sl_moduleEnabled('api') ? '' : 'api';
    }
    foreach (sl_modules() as $id => $mod) {
        if (in_array($res, $mod['api'], true) && !sl_moduleEnabled($id)) {
            return $id;
        }
    }
    return sl_moduleEnabled('api') ? '' : 'api';
}

/** Has loader.php run the central guard on this request (AJAX, public pages, cron, API covered)? */
function sl_moduleCentralGuard(): bool
{
    return !empty($GLOBALS['sl_module_guard_central']);
}

/** The friendly "This module is switched off" answer (HTTP 403). */
function sl_moduleOffPage(string $id, bool $shell = true): void
{
    global $user, $core;
    $m = sl_modules()[$id] ?? ['label' => $id, 'desc' => ''];
    http_response_code(403);
    header('Cache-Control: no-store');
    $canSwitch = false;
    if (isset($user) && $user instanceof User && !empty($user->logged_in)) {
        require_once __DIR__ . '/devops.php';
        $canSwitch = cdp_devCanUse($user) && cdp_devSectionAllowed($user, 'modules');
    }
    if ($shell && isset($user) && $user instanceof User && !empty($user->logged_in)) {
        if (!isset($core) || !($core instanceof Core)) {
            $core = new Core();
        }
        sl_render('ModuleController', ['id' => '', 'title' => 'Module switched off', 'board' => 'ModuleController'],
            ['moduleOff' => true, 'label' => $m['label'], 'desc' => $m['desc'], 'canSwitch' => $canSwitch, 'id' => $id]);
        exit;
    }
    $home = (isset($user) && $user instanceof User && !empty($user->logged_in)) ? 'index.php' : 'login.php';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Module switched off</title>'
        . '<style>:root{--bg:#F6F4EF;--card:#fff;--ink:#1C1A17;--mut:#6B665D;--line:#E6E1D8;--acc:#1C1A17;--acc-ink:#fff}'
        . '@media (prefers-color-scheme:dark){:root{--bg:#121110;--card:#1B1A18;--ink:#F1EEE8;--mut:#A59F94;--line:#2E2C28;--acc:#F1EEE8;--acc-ink:#121110}}'
        . 'body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--bg);color:var(--ink);font:16px/1.5 system-ui,-apple-system,Segoe UI,sans-serif;padding:16px;box-sizing:border-box}'
        . '.c{max-width:440px;width:100%;background:var(--card);border:1px solid var(--line);border-radius:18px;padding:28px}h1{font-size:21px;margin:0 0 8px}p{margin:0 0 18px;color:var(--mut)}'
        . 'a{display:inline-block;padding:10px 16px;border-radius:10px;background:var(--acc);color:var(--acc-ink);text-decoration:none;font-weight:500}</style></head><body><main class="c">'
        . '<h1>This module is switched off</h1><p>' . h($m['label']) . ' is not available on this system right now. If you need it, ask your administrator.</p>'
        . '<a href="' . h($home) . '">Go back</a></main></body></html>';
    exit;
}

/** Board name -> modules (via its page), for nav items without an explicit module. */
function sl_moduleNavBoardMap(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $map = [];
    $groups = [];
    if (function_exists('sl_navStaff')) {
        $groups = array_merge($groups, sl_navStaff());
    }
    if (function_exists('sl_navCustomer')) {
        $groups = array_merge($groups, sl_navCustomer());
    }
    foreach ($groups as $g) {
        foreach ($g['items'] ?? [] as $it) {
            if (empty($it['board']) || isset($map[$it['id']])) {
                continue;
            }
            $url = strtok((string) ($it['href'] ?? sl_route($it['board'])), '?#');
            $map[$it['id']] = sl_moduleScriptModules((string) $url);
        }
    }
    return $map;
}

/** Is a nav item hidden by a switched-off module or a developer section the user lacks? (helpers/sl_nav.php) */
function sl_navItemEnabled(string $navId): bool
{
    foreach (sl_modules() as $id => $m) {
        if (in_array($navId, $m['nav'], true) && !sl_moduleEnabled($id)) {
            return false;
        }
    }
    static $resolving = false;
    if (!$resolving) {
        $resolving = true;
        $mods = sl_moduleNavBoardMap()[$navId] ?? [];
        $resolving = false;
        foreach ($mods as $mid) {
            if (!sl_moduleEnabled($mid)) {
                return false;
            }
        }
    }
    $dev = sl_moduleDevNav()[$navId] ?? null;
    if ($dev !== null) {
        global $user;
        require_once __DIR__ . '/devops.php';
        if (!isset($user) || !is_object($user) || !cdp_devMenuVisible($user)) {
            return false;
        }
        if (cdp_devLocked()) {
            return $navId === 'devov'; // the one way in: it leads to the unlock form
        }
        if (cdp_devOwnerId() === 0) {
            return $navId === 'devov'; // nobody assigned yet: the overview assigns the first super admin
        }
        if ($navId === 'devaccess') {
            return cdp_devSectionAllowed($user, 'access') || cdp_devSectionAllowed($user, 'shutdown');
        }
        return cdp_devSectionAllowed($user, $dev);
    }
    return true;
}

/** Is an /api/v1 resource (first path segment) served by an enabled module? */
function sl_apiResourceEnabled(string $resource): bool
{
    foreach (sl_modules() as $id => $m) {
        if (in_array($resource, $m['api'], true) && !sl_moduleEnabled($id)) {
            return false;
        }
    }
    return sl_moduleEnabled('api');
}

/** Every page, endpoint and job of the app -> its modules (computed once per request). */
function sl_moduleFileIndex(): array
{
    static $idx = null;
    if ($idx !== null) {
        return $idx;
    }
    $files = [];
    foreach (glob(CDP_ROOT . '/*.php') ?: [] as $f) {
        $files[] = basename($f);
    }
    if (is_dir(CDP_ROOT . '/ajax')) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(CDP_ROOT . '/ajax', FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (substr((string) $f, -4) === '.php') {
                $files[] = 'ajax/' . str_replace('\\', '/', substr((string) $f, strlen(CDP_ROOT . '/ajax/')));
            }
        }
    }
    foreach (glob(CDP_ROOT . '/cron/*.php') ?: [] as $f) {
        $files[] = 'cron/' . basename($f);
    }
    $idx = [];
    foreach ($files as $rel) {
        $m = sl_moduleScriptModules($rel);
        if ($m) {
            $idx[$rel] = $m;
        }
    }
    return $idx;
}

/** What switching a module off removes: menu items, pages, endpoints, jobs (for the confirmation). */
function sl_moduleImpact(string $id): array
{
    $pages = $ajax = $jobs = [];
    foreach (sl_moduleFileIndex() as $rel => $mods) {
        if (!in_array($id, $mods, true)) {
            continue;
        }
        if (strpos($rel, 'ajax/') === 0) {
            $ajax[] = $rel;
        } elseif (strpos($rel, 'cron/') === 0) {
            $jobs[] = $rel;
        } else {
            $pages[] = $rel;
        }
    }
    static $groups = null;
    if ($groups === null) {
        $groups = [];
        if (!function_exists('sl_navStaff') && is_file(CDP_ROOT . '/helpers/sl_nav.php')) {
            require_once CDP_ROOT . '/helpers/sl_nav.php';
        }
        if (function_exists('sl_navStaff')) {
            $groups = array_merge(sl_navStaff(), sl_navCustomer());
        }
    }
    $board = sl_moduleNavBoardMap();
    $navIds = sl_modules()[$id]['nav'] ?? [];
    $navLabels = [];
    foreach ($groups as $g) {
        foreach ($g['items'] ?? [] as $itm) {
            if (in_array($itm['id'], $navIds, true) || in_array($id, $board[$itm['id']] ?? [], true)) {
                $navLabels[$itm['label']] = true;
            }
        }
    }
    return ['nav' => array_keys($navLabels), 'pages' => $pages, 'ajax' => $ajax, 'jobs' => $jobs];
}

/** Persist one state; returns bool. Financial writes the shared finance switch. */
function sl_moduleSet(string $id, bool $on, int $userId): bool
{
    if (!isset(sl_modules()[$id])) {
        return false;
    }
    $key = 'module_' . $id;
    if ($id === 'finance') {
        require_once __DIR__ . '/finance_switch.php';
        if (cdp_financePinnedByConfig()) {
            return false;
        }
        $key = 'finance_enabled';
    }
    try {
        $db = new Conexion();
        $db->cdp_query('INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_by, updated_at) VALUES (:k, :v, :u, :n)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)');
        $db->bind(':k', $key);
        $db->bind(':v', $on ? '1' : '0');
        $db->bind(':u', $userId);
        $db->bind(':n', sl_moduleNow());
        $ok = (bool) $db->cdp_execute();
    } catch (Throwable $e) {
        error_log('SL_MODULE_SET ' . $e->getMessage());
        $ok = false;
    }
    sl_moduleStates(true);
    return $ok;
}

/**
 * "Now" in the system timezone (cdb_settings.timezone, the zone Core applies on pages), so a switch
 * made from an AJAX endpoint (no Core, PHP default UTC) reads back right on the Modules page.
 */
function sl_moduleNow(): string
{
    if (function_exists('cdp_activityNow')) {
        return cdp_activityNow();
    }
    static $tz = null;
    if ($tz === null) {
        $tz = '';
        try {
            $tz = (string) sl_scalar('SELECT timezone FROM cdb_settings LIMIT 1', [], '');
        } catch (Throwable $e) {
        }
    }
    if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
        return (new DateTime('now', new DateTimeZone($tz)))->format('Y-m-d H:i:s');
    }
    return date('Y-m-d H:i:s');
}

/** When and by whom each module was last switched (cdb_sl_settings.updated_at / updated_by). */
function sl_moduleChanged(): array
{
    $out = [];
    try {
        foreach (sl_rows("SELECT s.setting_key, s.updated_at, u.fname, u.lname, u.username FROM cdb_sl_settings s LEFT JOIN cdb_users u ON u.id = s.updated_by WHERE s.setting_key LIKE 'module\\_%' OR s.setting_key = 'finance_enabled'") as $r) {
            $id = $r->setting_key === 'finance_enabled' ? 'finance' : substr((string) $r->setting_key, 7);
            $out[$id] = ['at' => (string) $r->updated_at, 'by' => trim(($r->fname ?? '') . ' ' . ($r->lname ?? '')) ?: (string) ($r->username ?? '')];
        }
    } catch (Throwable $e) {
    }
    return $out;
}
