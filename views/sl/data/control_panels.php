<?php
/**
 * Data for Control panels (ControlPanels.dc.html, and the Mobile boards at phone width).
 * Replaces the legacy dashboard_admin_*.php / dashboard_warehouse.php panels; counts reuse
 * helpers/dashboard_data.php (cdp_dashCount / cdp_dashStatusCounts) and the nav agency scope.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/sl_nav.php';
require_once CDP_ROOT . '/helpers/finance_switch.php';
require_once CDP_ROOT . '/helpers/rbac.php';
require_once CDP_ROOT . '/helpers/dashboard_data.php';

function sl_controlPanelsData($user, $core): array
{
    $d = [];
    $w = sl_navAgencyWhere('a');
    $ok = fn($perm) => $perm === null || sl_navAllowed($perm, []);
    $finance = cdp_financeEnabled();
    $money = cdp_canViewMoney($user, 'dashboard');
    // Each queue: how big, how old (age buckets), how fast it fills (new per day) and whether it is
    // growing (open now vs open a week ago). $from / $where / $col describe the open items.
    $queue = function (string $from, string $where, string $col, array $item) {
        $r = sl_row("SELECT COUNT(*) AS n,
                SUM($col >= NOW() - INTERVAL 1 DAY) AS d1,
                SUM($col < NOW() - INTERVAL 1 DAY AND $col >= NOW() - INTERVAL 7 DAY) AS d7,
                SUM($col < NOW() - INTERVAL 7 DAY AND $col >= NOW() - INTERVAL 30 DAY) AS d30,
                SUM($col < NOW() - INTERVAL 30 DAY) AS old, MIN($col) AS oldest,
                SUM($col < CURDATE() - INTERVAL 6 DAY) AS weekAgo
            FROM $from WHERE $where");
        $n = (int) ($r->n ?? 0);
        $new = [];
        $map = [];
        foreach (sl_rows("SELECT DATE($col) AS d, COUNT(*) AS c FROM $from WHERE $where AND $col >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE($col)") as $x) $map[$x->d] = (int) $x->c;
        for ($i = 6; $i >= 0; $i--) $new[] = $map[date('Y-m-d', strtotime("-$i days"))] ?? 0;
        $ages = [['< 1 day', (int) ($r->d1 ?? 0)], ['1–7 days', (int) ($r->d7 ?? 0)], ['7–30 days', (int) ($r->d30 ?? 0)], ['30 days +', (int) ($r->old ?? 0)]];
        $weekAgo = (int) ($r->weekAgo ?? 0); // still open and already there a week ago
        return $item + ['n' => $n, 'ages' => $ages, 'new' => $new, 'new7' => array_sum($new),
            'oldest' => !empty($r->oldest) ? max(0, (int) floor((time() - strtotime($r->oldest)) / 86400)) : null,
            'stale' => $n ? (int) round(((int) ($r->d30 ?? 0) + (int) ($r->old ?? 0)) / $n * 100) : 0, 'carried' => $weekAgo];
    };
    $attn = [];
    if ($ok('view_client_list') && sl_columnExists('cdb_users', 'approve')) {
        $attn[] = $queue('cdb_users', 'userlevel = 1 AND approve = 0 AND active = 1', 'created', ['href' => sl_route('Customers', ['filter' => 'unapproved']), 'l' => 'Awaiting approval', 'why' => 'New customers cannot ship until approved', 'icon' => 'i-shield']);
    }
    if ($ok(['warehouse_view', 'view_shipment_list'])) {
        $attn[] = $queue('cdb_add_order a', "a.is_deleted = 0 AND a.status_courier IN (12,25,28,29) $w", 'a.order_date', ['href' => sl_route('Warehouse', ['filter' => 'exceptions']), 'l' => 'Held parcels', 'why' => 'Rejected, held or not shipped', 'icon' => 'i-alert']);
    }
    if ($ok('view_pickup_list')) {
        $attn[] = $queue('cdb_add_order a', "a.is_deleted = 0 AND a.is_pickup = 1 AND a.status_courier IN (1,14) AND (a.driver_id IS NULL OR a.driver_id = 0) $w", 'a.order_date', ['href' => sl_route('Pickups'), 'l' => 'Unassigned pickups', 'why' => 'Booked but no rider yet', 'icon' => 'i-truck']);
    }
    if ($ok('prealert_list')) {
        $attn[] = $queue('cdb_pre_alert', 'is_package = 0', 'prealert_date', ['href' => sl_route('PreAlerts'), 'l' => 'Open pre-alerts', 'why' => 'Announced by customers, not yet received', 'icon' => 'i-inbox']);
    }
    $d['attn'] = $attn;
    // health
    $gwOn = (int) sl_scalar("SELECT COUNT(*) FROM cdb_met_payment WHERE is_active = 1 AND id <> 1 AND COALESCE(secret_key,'') <> ''");
    @include_once CDP_ROOT . '/helpers/hubtel_sms.php';
    $sms = function_exists('cdp_smsReady') ? (bool) cdp_smsReady() : false;
    $fails = sl_tableExists('cdb_message_log') ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_message_log WHERE status = 'failed' AND created_at >= NOW() - INTERVAL 1 DAY") : 0;
    $sent = sl_tableExists('cdb_message_log') ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_message_log WHERE created_at >= NOW() - INTERVAL 1 DAY") : 0;
    $failPct = $sent ? (int) round($fails / $sent * 100) : 0;
    $aiOn = function_exists('cdp_aiSetting') && (string) cdp_aiSetting('enabled', '0') === '1';
    $logOn = sl_tableExists('cdb_activity_log');
    $lastLog = $logOn ? sl_scalar('SELECT MAX(created_at) FROM cdb_activity_log', [], null) : null;
    $d['health'] = [
        ['t' => 'API and web app', 's' => 'Operational', 'c' => '', 'i' => 'i-server', 'x' => $lastLog ? 'Last action ' . sl_ago($lastLog) : 'Serving requests', 'href' => sl_route('DevOverview')],
        ['t' => 'Payment gateways', 's' => $finance ? ($gwOn ? 'Operational' : 'No keys set') : 'Finance off', 'c' => $finance && $gwOn ? '' : 'w', 'i' => 'i-card', 'x' => $finance ? $gwOn . ' gateway' . ($gwOn === 1 ? '' : 's') . ' with keys' : 'Payments are not taken online', 'href' => sl_route('Payments')],
        ['t' => 'SMS delivery', 's' => !$sms ? 'Not configured' : ($sent && $fails / max(1, $sent) > 0.2 ? 'Degraded' : 'Operational'), 'c' => !$sms || ($sent && $fails / max(1, $sent) > 0.2) ? 'w' : '', 'i' => 'i-message', 'x' => $sent ? (100 - $failPct) . '% delivered in 24 h' : 'Nothing sent in 24 h', 'href' => sl_route('CommsHub')],
        ['t' => 'Messages failed (24 h)', 's' => sl_num($fails) . ' of ' . sl_num($sent), 'c' => $failPct > 20 ? 'b' : ($fails ? 'w' : ''), 'i' => 'i-send', 'x' => $sent ? $failPct . '% failure rate' : 'No messages', 'pct' => $failPct, 'href' => sl_route('CommsHub')],
        ['t' => 'AI assistant', 's' => $aiOn ? 'On' : 'Off', 'c' => $aiOn ? '' : 'w', 'i' => 'i-ai', 'x' => $aiOn ? 'Staff and customers can ask' : 'Switch it on in the AI center', 'href' => sl_route('AiCenter')],
        ['t' => 'Activity trail', 's' => $logOn ? 'Recording' : 'Not set up', 'c' => $logOn ? '' : 'w', 'i' => 'i-activity', 'x' => $logOn ? 'Every change is logged' : 'Run the activity log migration', 'href' => sl_route('ActivityLogs')],
    ];
    $c = fn(string $sql) => sl_num(sl_scalar($sql));
    $sections = sl_controlPanelSections($c, $w, $money, fn() => $sent);
    $d['sections'] = [];
    foreach ($sections as $s) {
        if (!empty($s['finance']) && !$finance) continue;
        $rows = [];
        foreach ($s['rows'] as [$board, $icon, $label, $sub, $perm, $count]) {
            if ($board === 'Payments' && !$finance) continue;
            if (!$ok($perm)) continue;
            $rows[] = ['href' => sl_route($board), 'icon' => $icon, 'label' => $label, 'sub' => $sub, 'n' => $count ? (string) $count() : ''];
        }
        if ($rows) $d['sections'][] = ['id' => $s['id'], 'icon' => $s['icon'], 't' => $s['t'], 's' => $s['s'], 'featured' => !empty($s['featured']), 'rows' => $rows];
    }
    $pins = [['NewShipment', 'i-newdoc', 'New shipment', 'add_shipment'], ['Warehouse', 'i-scan', 'Scan package', ['warehouse_view', 'view_warehouse_delivery']], ['RecordPayment', 'i-card', 'Record payment', ['fs_record_payment', 'manage_account_payments']], ['PublicTracking', 'i-route', 'Track', null], ['CsInbox', 'i-ticket', 'New ticket', 'cs:agent']];
    $d['pins'] = [];
    foreach ($pins as [$b, $i, $l, $p]) if ($ok($p) && ($b !== 'RecordPayment' || $finance)) $d['pins'][] = ['href' => sl_route($b), 'icon' => $i, 'label' => $l];
    $d['updated'] = date('H:i');
    return $d;
}

/**
 * Every module of the workspace, grouped, with a one-line description: [board, icon, label, description,
 * permission, count]. Counts are closures, so callers that only need the catalogue (the AI site index,
 * helpers/ai_site_index.php) pay nothing for them.
 */
function sl_controlPanelSections(callable $c, string $w = '', bool $money = false, ?callable $sent = null): array
{
    $sent = $sent ?? fn() => 0;
    return [
        ['id' => 'command-centers', 'icon' => 'i-grid', 't' => 'Command Centers', 's' => 'Key controls and oversight in one place', 'featured' => true, 'rows' => [
            ['ExecDashboard', 'i-chart', 'Management Center', 'Business performance and decisions', ['main_dashboard_index', 'view_exec_dashboard'], null],
            ['AiCenter', 'i-ai', 'AI Automation Center', 'AI tools, rules and automation', 'ai:manage', null],
            ['NotificationCenter', 'i-bell', 'Notification Control', 'Delivery rules and message settings', ['manage_notification_rules', 'view_tools'], null],
            ['Payments', 'i-card', 'Payment Gateways', 'Payments and gateway activity', 'view_global_payments', null],
            ['LoyaltyRewards', 'i-star', 'Loyalty & Referrals', 'Rewards, points and referrals', 'manage_loyalty', null],
            ['SupportHub', 'i-ticket', 'Customer Care', 'Service activity and customer needs', 'cs:agent', null],
            ['CommsHub', 'i-message', 'Communications', 'Customer messages and campaigns', ['push_notifications', 'manage_newsletter', 'view_message_logs'], null],
            ['SecurityCenter', 'i-shield', 'Security Center', 'System access, policies and checks', 'view_system_logs', null],
            ['AccountSecurity', 'i-lock', 'My Account Security', 'Update your password and sign-in details', 'customers_profile_edit', null],
        ]],
        ['id' => 'developer', 'icon' => 'i-terminal', 't' => 'Developer', 's' => 'Platform health and technical controls', 'featured' => true, 'rows' => [
            ['DevOverview', 'i-terminal', 'Developer Console', 'Services, logs, metrics and deploys', 'view_developer', null],
            ['DevServices', 'i-server', 'Services', 'Service status and diagnostics', 'view_developer', null],
            ['DevMetrics', 'i-activity', 'Metrics', 'Performance and capacity', 'view_developer', null],
            ['DevDeployments', 'i-rocket', 'Deployments', 'Release activity', 'view_developer', null],
        ]],
        ['id' => 'support', 'icon' => 'i-ticket', 't' => 'Support', 's' => 'Tickets, service quality and team guidance', 'featured' => true, 'rows' => [
            ['CsInbox', 'i-inbox', 'Support Desk', 'Conversations and active tickets', 'cs:agent', fn() => sl_tableExists('cdb_cs_tickets') ? $c("SELECT COUNT(*) FROM cdb_cs_tickets WHERE status NOT IN ('closed','resolved')") : ''],
            ['SupportHub', 'i-grid', 'Service Panel', 'Team activity and channel health', 'cs:agent', null],
            ['CsReports', 'i-chart', 'Service Reports', 'Response, resolution and satisfaction', 'cs:manage', null],
            ['CsPlaybook', 'i-book', 'Playbooks', 'Rules and saved replies', 'cs:agent', null],
            ['CsKnowledgeBase', 'i-file', 'Knowledge Base', 'Published help articles', 'cs:agent', null],
            ['CsChannels', 'i-sliders', 'Support Settings', 'Channels, teams and service rules', 'cs:manage', null],
        ]],
        ['id' => 'shipments', 'icon' => 'i-plane', 't' => 'Shipping & freight', 's' => 'Everything moving between the US and Ghana', 'rows' => [
            ['AirShipments', 'i-plane', 'Air shipments', 'Not yet delivered', 'view_shipment_list', fn() => $c("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.is_pickup = 0 AND a.status_courier NOT IN (8,15,21) $w")],
            ['SeaPackages', 'i-ship', 'Sea packages', 'Containers and LCL boxes', 'view_package_list', fn() => $c("SELECT COUNT(*) FROM cdb_customers_packages a WHERE a.status_courier NOT IN (8,15,21) $w")],
            ['Consolidations', 'i-layers', 'Consolidations', 'Open air and sea consolidations', ['view_consolidate_list', 'view_consolidate_package_list'], fn() => sl_num((int) sl_scalar("SELECT COUNT(*) FROM cdb_consolidate WHERE status_courier NOT IN ('8','15','21')") + (int) sl_scalar("SELECT COUNT(*) FROM cdb_consolidate_packages WHERE status_courier NOT IN ('8','15','21')"))],
            ['PreAlerts', 'i-inbox', 'Pre-alerts', 'Parcels customers told us about', 'prealert_list', fn() => $c('SELECT COUNT(*) FROM cdb_pre_alert WHERE is_package = 0')],
            ['PublicTracking', 'i-route', 'Public tracking', 'What customers see', null, null],
        ]],
        ['id' => 'warehouse', 'icon' => 'i-warehouse', 't' => 'Warehouse & dispatch', 's' => 'Bays, lockers, riders and routes', 'rows' => [
            ['Warehouse', 'i-warehouse', 'Warehouse', 'Parcels in the bays', ['warehouse_view', 'view_warehouse_delivery'], fn() => sl_num((int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (4,6,32) $w") + (int) sl_scalar("SELECT COUNT(*) FROM cdb_customers_packages a WHERE a.status_courier IN (4,6,32) $w"))],
            ['HandOut', 'i-box', 'Hand out', 'Ready for pickup', ['view_warehouse_delivery', 'warehouse_deliver_package'], fn() => $c("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (6,32) $w")],
            ['LockerSearch', 'i-search', 'Smart search', 'Search anything: tracking, customers, lockers, containers', 'view_shipment_list', null],
            ['Pickups', 'i-truck', 'Pickups', 'Today’s pickups done / booked', 'view_pickup_list', fn() => $c("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.is_pickup = 1 AND DATE(a.order_date) = CURDATE() AND a.status_courier NOT IN (1,14) $w") . ' / ' . $c("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.is_pickup = 1 AND DATE(a.order_date) = CURDATE() $w")],
            ['Drivers', 'i-wheel', 'Riders', 'On route / all riders', 'view_user_list', fn() => $c("SELECT COUNT(DISTINCT a.driver_id) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier = 7 AND a.driver_id > 0 $w") . ' / ' . $c('SELECT COUNT(*) FROM cdb_users WHERE userlevel = 3 AND active = 1')],
        ]],
        ['id' => 'customers', 'icon' => 'i-users', 't' => 'Customers & people', 's' => 'Who ships with us and who works here', 'rows' => [
            ['Customers', 'i-users', 'Customers', 'Profiles and lockers', 'view_client_list', fn() => $c('SELECT COUNT(*) FROM cdb_users WHERE userlevel = 1')],
            ['Recipients', 'i-user', 'Recipients', 'People receiving parcels', 'view_recipients', null],
            ['StaffRoles', 'i-shield', 'Staff & roles', 'Permissions and access', ['view_user_list', 'view_role_assignment'], fn() => $c('SELECT COUNT(*) FROM cdb_users WHERE userlevel NOT IN (1,3)')],
        ]],
        ['id' => 'account', 'icon' => 'i-dollar', 't' => 'Money', 's' => 'What comes in, what is owed, what it costs', 'finance' => true, 'rows' => [
            ['Payments', 'i-card', 'Payments', 'All payments received', 'view_global_payments', null],
            ['AccountsReceivable', 'i-receipt', 'Accounts receivable', 'Unpaid invoices', 'view_receivable_accounts', fn() => $c("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_invoice = 23 AND a.status_courier <> 21 $w")],
            ['FinancialOverview', 'i-chart', 'Financial overview', $money ? 'Revenue and margin' : 'Counts only for your role', 'view_financial_overview', null],
            ['BillingHub', 'i-dollar', 'Billing & finance', 'Financial sheet and switch', 'financial_sheet', null],
        ]],
        ['id' => 'messages', 'icon' => 'i-message', 't' => 'Messages & support', 's' => 'Every conversation with customers', 'rows' => [
            ['CsInbox', 'i-ticket', 'Support inbox', 'Open tickets', 'cs:agent', fn() => sl_tableExists('cdb_cs_tickets') ? $c("SELECT COUNT(*) FROM cdb_cs_tickets WHERE status NOT IN ('closed','resolved')") : ''],
            ['NotificationCenter', 'i-send', 'Messaging', 'SMS, WhatsApp and e-mail', 'view_tools', null],
            ['MessageLogs', 'i-clock', 'Message logs', 'Sent in the last 24 hours', 'view_message_logs', fn() => sl_num($sent())],
            ['SendPrices', 'i-tag', 'Send prices', 'Tariff sheet to customers', ['send_prices', 'push_notifications'], null],
        ]],
        ['id' => 'insights', 'icon' => 'i-activity', 't' => 'Insights & audit', 's' => 'Reports and the record of who did what', 'rows' => [
            ['ExecDashboard', 'i-chart', 'Management dashboard', 'The business at a glance', ['main_dashboard_index', 'view_exec_dashboard'], null],
            ['Reports', 'i-folder', 'Reports', 'Every legacy report, one hub', 'view_general_reports', null],
            ['ActivityLogs', 'i-activity', 'Activity logs', 'Actions today', 'view_activity_logs', fn() => sl_tableExists('cdb_activity_log') ? $c("SELECT COUNT(*) FROM cdb_activity_log WHERE DATE(created_at) = CURDATE() AND action <> 'page_view'") : ''],
            ['CsReports', 'i-chart', 'Support reports', 'Resolution and satisfaction', 'cs:manage', null],
        ]],
        ['id' => 'platform', 'icon' => 'i-terminal', 't' => 'Platform', 's' => 'The system behind the workspace', 'rows' => [
            ['DevOverview', 'i-terminal', 'Developer overview', 'Services, logs and deploys', 'view_developer', null],
            ['CostSettings', 'i-dollar', 'Operating costs', 'What it costs to run', 'superadmin', null],
            ['ApiConnections', 'i-puzzle', 'Integrations', 'Webhooks and API keys', 'manage_api_settings', null],
            ['SettingsHub', 'i-cog', 'Settings', 'Company, brand and system', 'view_tools', null],
        ]],
    ];
}
