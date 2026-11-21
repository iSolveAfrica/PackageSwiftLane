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
    // The four cards at the top: air shipments, sea packages, deliveries and AI insights.
    // Each card: a headline number, a split (stacked bar + legend), a 7-day series and a footer.
    $days7 = [];
    for ($i = 6; $i >= 0; $i--) $days7[] = date('Y-m-d', strtotime("-$i days"));
    $series = function (string $sql, array $b = []) use ($days7) {
        $m = [];
        foreach (sl_rows($sql, $b + [':d0' => $days7[0]]) as $x) $m[$x->d] = (int) $x->c;
        return array_map(fn($d) => $m[$d] ?? 0, $days7);
    };
    $ageDays = fn($dt) => $dt ? max(0, (int) floor((time() - strtotime((string) $dt)) / 86400)) : null;
    // open parcels of one kind, split by where they are
    $parcels = function (string $tbl, string $del, string $label, string $icon, string $tone, string $href, string $unpaidHref) use ($w, $series, $ageDays) {
        $open = "a.status_courier NOT IN (8,15,21)$del $w";
        $r = sl_row("SELECT COUNT(*) AS n,
                SUM(a.status_courier IN (3,5,14)) AS transit,
                SUM(a.status_courier IN (4,6,32)) AS ready,
                SUM(a.status_courier = 7) AS out_now,
                SUM(a.status_courier IN (12,25,28,29)) AS held,
                SUM(a.status_invoice NOT IN (1,19)) AS unpaid,
                MIN(a.order_date) AS oldest
            FROM $tbl a WHERE $open");
        $n = (int) ($r->n ?? 0);
        $transit = (int) ($r->transit ?? 0); $ready = (int) ($r->ready ?? 0) + (int) ($r->out_now ?? 0); $held = (int) ($r->held ?? 0);
        $origin = max(0, $n - $transit - $ready - $held);
        $reg = $series("SELECT DATE(a.order_date) AS d, COUNT(*) AS c FROM $tbl a WHERE a.order_date >= :d0$del $w GROUP BY DATE(a.order_date)");
        $unpaid = (int) ($r->unpaid ?? 0);
        return ['key' => $label, 'l' => $label, 'icon' => $icon, 'tone' => $tone, 'href' => $href, 'n' => $n, 'unit' => 'on the way',
            'badge' => '+' . sl_num(array_sum($reg)) . ' this week', 'why' => sl_num($unpaid) . ' not paid yet' . ($held ? ' · ' . sl_num($held) . ' held' : ''), 'whyHref' => $unpaidHref,
            'mix' => [['At origin', $origin, 'm0'], ['In transit', $transit, 'm1'], ['Arrived / ready', $ready, 'm2'], ['Held', $held, 'm3']],
            'bars' => $reg, 'barsName' => $label . ' registered', 'footL' => 'Registered / day · 7 d', 'footR' => ($o = $ageDays($r->oldest ?? null)) !== null ? 'Oldest open ' . $o . ' d' : 'Nothing open'];
    };
    $attn = [];
    if ($ok(['view_shipment_list', 'view_general_shipments'])) {
        $attn[] = $parcels('cdb_add_order', ' AND a.is_deleted = 0 AND a.is_pickup = 0', 'Air shipments', 'i-plane', 'blue', sl_route('AirShipments'), sl_route('AirShipments', ['filter' => 'unpaid']));
    }
    if ($ok(['view_package_list', 'view_general_package_records'])) {
        $attn[] = $parcels('cdb_customers_packages', '', 'Sea packages', 'i-ship', 'violet', sl_route('SeaPackages'), sl_route('SeaPackages', ['filter' => 'unpaid']));
    }
    if ($ok(['view_pickup_list', 'dispatch_riders', 'view_warehouse_delivery'])) {
        $outNow = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier = 7 $w");
        $readyN = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (6,32) $w");
        $booked = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.is_pickup = 1 AND a.status_courier IN (1,14) $w");
        $noRider = (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.is_pickup = 1 AND a.status_courier IN (1,14) AND (a.driver_id IS NULL OR a.driver_id = 0) $w");
        $trk = 'FROM cdb_courier_track t' . ($w !== '' ? ' JOIN cdb_add_order a ON t.order_track = CONCAT(a.order_prefix, a.order_no)' : '') . ' WHERE t.status_courier IN (8,15)' . ($w !== '' ? " $w" : '');
        $handed = $series("SELECT DATE(t.t_date) AS d, COUNT(DISTINCT t.order_track) AS c $trk AND t.t_date >= :d0 GROUP BY DATE(t.t_date)");
        $riders = (int) sl_scalar('SELECT COUNT(*) FROM cdb_users WHERE userlevel = 3 AND active = 1');
        $attn[] = ['key' => 'Deliveries', 'l' => 'Deliveries', 'icon' => 'i-truck', 'tone' => 'amber', 'href' => sl_route('RiderDispatch'), 'n' => $outNow, 'unit' => 'out for delivery',
            'badge' => sl_num(end($handed)) . ' handed over today', 'why' => sl_num($riders) . ' active rider' . ($riders === 1 ? '' : 's') . ($noRider ? ' · ' . sl_num($noRider) . ' pickups need a rider' : ''), 'whyHref' => sl_route('Pickups'),
            'mix' => [['Out now', $outNow, 'm1'], ['Ready to hand out', $readyN, 'm2'], ['Pickups booked', $booked - $noRider, 'm0'], ['No rider', $noRider, 'm3']],
            'bars' => $handed, 'barsName' => 'Handed over', 'footL' => 'Handed over / day · 7 d', 'footR' => sl_num(array_sum($handed)) . ' this week'];
    }
    if (sl_navAllowed('ai:use', [])) {
        $hasQ = sl_tableExists('cdb_ai_questions'); $hasC = sl_tableExists('cdb_cs_conversations');
        $staffQ = $hasQ ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_ai_questions WHERE created_at >= CURDATE() - INTERVAL 6 DAY') : 0;
        $unans = $hasQ ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_ai_questions WHERE created_at >= CURDATE() - INTERVAL 6 DAY AND outcome <> 'answered'") : 0;
        $chats = $hasC ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_cs_conversations WHERE created_at >= CURDATE() - INTERVAL 6 DAY') : 0;
        $handedC = $hasC ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_cs_conversations WHERE created_at >= CURDATE() - INTERVAL 6 DAY AND handover_at IS NOT NULL') : 0;
        $perDay = array_fill(0, 7, 0);
        if ($hasQ) foreach ($series('SELECT DATE(created_at) AS d, COUNT(*) AS c FROM cdb_ai_questions WHERE created_at >= :d0 GROUP BY DATE(created_at)') as $i => $v) $perDay[$i] += $v;
        if ($hasC) foreach ($series('SELECT DATE(created_at) AS d, COUNT(*) AS c FROM cdb_cs_conversations WHERE created_at >= :d0 GROUP BY DATE(created_at)') as $i => $v) $perDay[$i] += $v;
        $aiOn = function_exists('cdp_aiSetting') && (string) cdp_aiSetting('enabled', '0') === '1';
        $attn[] = ['key' => 'AI insights', 'l' => 'AI insights', 'icon' => 'i-ai', 'tone' => 'mint', 'href' => sl_navAllowed('ai:manage', []) ? sl_route('AiCenter') : sl_route('StaffAI'),
            'n' => $staffQ + $chats, 'unit' => 'questions this week', 'badge' => $chats ? sl_pct($chats - $handedC, $chats) . '% solved by AI' : ($aiOn ? 'Assistant on' : 'Assistant off'),
            'why' => '', 'whyHref' => '', 'insights' => [],
            'mix' => [['Solved by AI', max(0, $chats - $handedC), 'm2'], ['Handed to staff', $handedC, 'm1'], ['Staff questions', max(0, $staffQ - $unans), 'm0'], ['Unanswered', $unans, 'm3']],
            'bars' => $perDay, 'barsName' => 'AI questions and chats', 'footL' => 'Questions + chats / day · 7 d', 'footR' => $aiOn ? 'Ask with Ctrl J' : 'Switch on in AI center'];
    }
    // AI insights: plain-language observations drawn from the other cards (no model call on page load)
    foreach ($attn as $k => $c) {
        if (($c['key'] ?? '') !== 'AI insights') continue;
        $tips = [];
        foreach ($attn as $o) {
            if (empty($o['mix']) || ($o['key'] ?? '') === 'AI insights') continue;
            $mix = array_column($o['mix'], 1, 0);
            if (!empty($mix['Held'])) $tips[] = [sl_num($mix['Held']) . ' ' . strtolower($o['l']) . ' are held: clear them before the next departure.', $o['href']];
            if (!empty($mix['No rider'])) $tips[] = [sl_num($mix['No rider']) . ' pickups have no rider yet.', sl_route('RiderDispatch')];
            if (!empty($mix['Arrived / ready']) && $o['n'] && $mix['Arrived / ready'] / max(1, $o['n']) > .5) $tips[] = [sl_pct($mix['Arrived / ready'], $o['n']) . '% of open ' . strtolower($o['l']) . ' are waiting for collection: send reminders.', sl_route('HandOut')];
            if (str_contains((string) $o['footR'], 'Oldest open') && (int) filter_var($o['footR'], FILTER_SANITIZE_NUMBER_INT) > 90) $tips[] = [$o['l'] . ' has records open for over 90 days: close or follow them up.', $o['href']];
        }
        if (!$tips) $tips[] = ['Nothing unusual right now. Ask the assistant about any page with Ctrl J.', $c['href']];
        $attn[$k]['insights'] = array_slice($tips, 0, 3);
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
        ['t' => cdp_kingName(), 's' => $aiOn ? 'On' : 'Off', 'c' => $aiOn ? '' : 'w', 'i' => 'i-ai', 'x' => $aiOn ? 'Staff and customers can ask ' . cdp_kingName() : 'Switch it on in the AI center', 'href' => sl_route('AiCenter')],
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
