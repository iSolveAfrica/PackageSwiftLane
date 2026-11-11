<?php
/**
 * Navigation registry for the Swift Lane shell.
 *
 * Groups and items follow the approved design (sidebar of Main.dc.html and
 * ClientDashboard.dc.html). Every item carries the permission(s) that unlock it
 * and, where the design shows a counter, a callback that computes the live
 * figure from the database. Unknown permission keys hide the item for everyone
 * except the super admin, so a module that is not yet licensed to a role
 * never appears in its menu.
 */
require_once __DIR__ . '/sl_view.php';

/** Lightweight count helper used by nav counters (errors return 0). */
function sl_navCount(string $sql, array $binds = []): int
{
    try {
        return (int) sl_scalar($sql, $binds, 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function sl_navAgencyWhere(string $alias = 'a'): string
{
    if (!function_exists('cdp_getAgencyContext')) {
        return '';
    }
    $ctx = cdp_getAgencyContext();
    if (!empty($ctx['is_restricted']) && !empty($ctx['agency_id'])) {
        return " AND {$alias}.agency = " . (int) $ctx['agency_id'];
    }
    return '';
}

/** Staff navigation (admin, employee, agency, driver). */
function sl_navStaff(): array
{
    $w = sl_navAgencyWhere('a');
    return [
        ['label' => 'Operations', 'items' => [
            ['id' => 'dash', 'label' => 'Overview', 'icon' => 'grid', 'board' => 'Main', 'perm' => null],
            ['id' => 'panels', 'label' => 'Control Panels', 'icon' => 'gauge', 'board' => 'ControlPanels', 'perm' => ['view_dashboard_ship', 'view_dashboard_pack', 'view_dashboard_pick', 'view_dashboard_shipments', 'view_dashboard']],
            ['id' => 'air', 'label' => 'Air Shipments', 'icon' => 'plane', 'board' => 'AirShipments', 'perm' => 'view_shipment_list',
                'count' => fn() => sl_navCount("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier NOT IN (8,15,21) $w")],
            ['id' => 'sea', 'label' => 'Sea Packages', 'icon' => 'ship', 'board' => 'SeaPackages', 'perm' => 'view_package_list',
                'count' => fn() => sl_navCount("SELECT COUNT(*) FROM cdb_customers_packages a WHERE a.status_courier NOT IN (8,15,21) $w")],
            ['id' => 'prealert', 'label' => 'Pre-Alerts', 'icon' => 'inbox', 'board' => 'PreAlerts', 'perm' => 'prealert_list',
                'count' => fn() => sl_navCount("SELECT COUNT(*) FROM cdb_pre_alert WHERE is_package = 0")],
            ['id' => 'pickup', 'label' => 'Pickups', 'icon' => 'truck', 'board' => 'Pickups', 'perm' => 'view_pickup_list',
                'count' => fn() => sl_navCount("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_pickup = 1 AND a.is_deleted = 0 AND a.status_courier IN (1,14) $w"),
                'count2' => fn() => '/ ' . sl_navCount("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_pickup = 1 AND a.is_deleted = 0 AND DATE(a.order_date) = CURDATE() $w")],
            ['id' => 'dtrack', 'label' => 'Delivery Tracking', 'icon' => 'route', 'board' => 'DeliveryTracking', 'perm' => 'view_shipment_list',
                'count' => fn() => sl_navCount("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier = 7 $w")],
            ['id' => 'shiptrack', 'label' => 'Shipment Tracking', 'icon' => 'track', 'board' => 'ShipmentTracking', 'perm' => 'track_shipment'],
            ['id' => 'consol', 'label' => 'Consolidations', 'icon' => 'layers', 'board' => 'Consolidations', 'perm' => ['view_consolidate_list', 'view_consolidate_package_list'],
                'count' => fn() => sl_navCount("SELECT (SELECT COUNT(*) FROM cdb_consolidate WHERE status_courier NOT IN ('8','15','21')) + (SELECT COUNT(*) FROM cdb_consolidate_packages WHERE status_courier NOT IN ('8','15','21'))")],
            ['id' => 'warehouse', 'label' => 'Warehouse', 'icon' => 'warehouse', 'board' => 'Warehouse', 'perm' => ['warehouse_view', 'view_warehouse_delivery']],
            ['id' => 'whpick', 'label' => 'Warehouse Pickups', 'icon' => 'check', 'board' => 'WarehousePickups', 'perm' => ['view_warehouse_pickups', 'view_warehouse_delivery']],
            ['id' => 'handout', 'label' => 'Hand Out Packages', 'icon' => 'box', 'board' => 'HandOut', 'perm' => ['view_warehouse_delivery', 'warehouse_deliver_package'],
                'count' => fn() => sl_navCount("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier IN (6,32) $w")],
            ['id' => 'intake', 'label' => 'Package Intake', 'icon' => 'scan', 'board' => 'PackageIntake', 'perm' => ['package_intake', 'add_package']],
            ['id' => 'import', 'label' => 'Import Shipments', 'icon' => 'upload', 'board' => 'ImportData', 'perm' => 'add_multiple_shipments'],
            ['id' => 'docs', 'label' => 'Documents', 'icon' => 'file', 'board' => 'Documents', 'perm' => ['view_documents', 'view_general_reports']],
            ['id' => 'locker', 'label' => 'Locker Search', 'icon' => 'lock', 'board' => 'LockerSearch', 'perm' => 'view_shipment_list'],
        ]],
        ['label' => 'Command Centers', 'items' => [
            ['id' => 'execdash', 'label' => 'Management Center', 'icon' => 'chart', 'board' => 'ExecDashboard', 'perm' => ['main_dashboard_index', 'view_exec_dashboard']],
            ['id' => 'aicenter', 'label' => 'AI Automation Center', 'icon' => 'ai', 'board' => 'AiCenter', 'perm' => 'ai:manage'],
            ['id' => 'notifc', 'label' => 'Notification Control', 'icon' => 'bell', 'board' => 'NotificationCenter', 'perm' => ['manage_notification_rules', 'view_tools']],
            ['id' => 'pay', 'label' => 'Payment Gateways', 'icon' => 'card', 'board' => 'Payments', 'perm' => 'view_global_payments', 'finance' => true],
            ['id' => 'loyalty', 'label' => 'Loyalty & Referrals', 'icon' => 'star', 'board' => 'LoyaltyRewards', 'perm' => 'manage_loyalty'],
            ['id' => 'supporthub', 'label' => 'Customer Care', 'icon' => 'ticket', 'board' => 'SupportHub', 'perm' => 'cs:agent'],
            ['id' => 'commshub', 'label' => 'Communications', 'icon' => 'message', 'board' => 'CommsHub', 'perm' => ['push_notifications', 'manage_newsletter', 'view_message_logs']],
            ['id' => 'security', 'label' => 'Security Center', 'icon' => 'shield', 'board' => 'SecurityCenter', 'perm' => 'view_system_logs'],
            ['id' => 'accountsec', 'label' => 'My Account Security', 'icon' => 'lock', 'board' => 'AccountSecurity', 'perm' => 'customers_profile_edit'],
        ]],
        ['label' => 'Developer', 'items' => [
            ['id' => 'devov', 'label' => 'Developer Console', 'icon' => 'terminal', 'board' => 'DevOverview', 'perm' => 'view_developer'],
            ['id' => 'devsvc', 'label' => 'Services', 'icon' => 'server', 'board' => 'DevServices', 'perm' => 'view_developer'],
            ['id' => 'devlogs', 'label' => 'Logs', 'icon' => 'activity', 'board' => 'DevLogs', 'perm' => 'view_developer'],
            ['id' => 'devmet', 'label' => 'Metrics', 'icon' => 'cpu', 'board' => 'DevMetrics', 'perm' => 'view_developer'],
            ['id' => 'devinfra', 'label' => 'Infrastructure', 'icon' => 'cloud', 'board' => 'DevInfrastructure', 'perm' => 'view_developer'],
            ['id' => 'devdep', 'label' => 'Deployments', 'icon' => 'rocket', 'board' => 'DevDeployments', 'perm' => 'view_developer'],
            ['id' => 'devalert', 'label' => 'Alerts', 'icon' => 'alert', 'board' => 'DevAlerts', 'perm' => 'view_developer',
                'count' => fn() => sl_tableExists('cdb_dev_alerts') ? sl_navCount("SELECT COUNT(*) FROM cdb_dev_alerts WHERE status = 'active'") : 0],
            ['id' => 'devissues', 'label' => 'Issues & Change Log', 'icon' => 'bug', 'board' => 'DevIssues', 'perm' => 'view_developer',
                'count' => fn() => sl_tableExists('cdb_dev_issues') ? sl_navCount("SELECT COUNT(*) FROM cdb_dev_issues WHERE status IN ('open','in_progress')") : 0],
            ['id' => 'devint', 'label' => 'Integrations', 'icon' => 'puzzle', 'board' => 'DevIntegrations', 'perm' => 'view_developer'],
        ]],
        ['label' => 'Support', 'items' => [
            ['id' => 'tickets', 'label' => 'Support Desk', 'icon' => 'ticket', 'board' => 'CsInbox', 'perm' => 'cs:agent',
                'count' => fn() => sl_tableExists('cdb_cs_tickets') ? sl_num(sl_navCount("SELECT COUNT(*) FROM cdb_cs_tickets WHERE status NOT IN ('closed','resolved')")) : 0],
            ['id' => 'servicepanel', 'label' => 'Service Panel', 'icon' => 'grid', 'board' => 'SupportHub', 'perm' => 'cs:agent'],
            ['id' => 'csrep', 'label' => 'Service Reports', 'icon' => 'chart', 'board' => 'CsReports', 'perm' => 'cs:manage'],
            ['id' => 'playbook', 'label' => 'Playbooks', 'icon' => 'book', 'board' => 'CsPlaybook', 'perm' => 'cs:agent'],
            ['id' => 'knowledge', 'label' => 'Knowledge Base', 'icon' => 'file', 'board' => 'CsPlaybook', 'href' => sl_route('CsPlaybook') . '#knowledge-base', 'perm' => 'cs:agent'],
            ['id' => 'cschan', 'label' => 'Support Settings', 'icon' => 'sliders', 'board' => 'CsChannels', 'perm' => 'cs:manage'],
            ['id' => 'csrules', 'label' => 'Ticket Rules', 'icon' => 'filter', 'board' => 'CsRules', 'perm' => 'cs:manage'],
            ['id' => 'csai', 'label' => 'AI Assistant', 'icon' => 'ai', 'board' => 'StaffAI', 'perm' => 'ai:use'],
            ['id' => 'cssupai', 'label' => 'AI for Customers', 'icon' => 'spark', 'board' => 'CsAI', 'perm' => 'cs:manage'],
        ]],
        ['label' => 'Freight', 'items' => [
            ['id' => 'lastmile', 'label' => 'Last-Mile Delivery', 'icon' => 'truck', 'board' => 'LastMile', 'perm' => ['view_freight', 'view_pickup_list']],
            ['id' => 'frhub', 'label' => 'Freight Hub', 'icon' => 'plane', 'board' => 'FreightHub', 'perm' => 'view_freight'],
            ['id' => 'frsched', 'label' => 'Departures & Arrivals', 'icon' => 'calendar', 'board' => 'FreightSchedule', 'perm' => 'view_freight',
                'count' => fn() => sl_tableExists('cdb_freight_schedules') ? sl_navCount("SELECT COUNT(*) FROM cdb_freight_schedules WHERE status NOT IN ('arrived','cancelled','completed')") : 0],
            ['id' => 'frwb', 'label' => 'Waybills & Manifests', 'icon' => 'file', 'board' => 'Waybills', 'perm' => 'view_freight'],
            ['id' => 'frex', 'label' => 'Cargo Exceptions', 'icon' => 'alert', 'board' => 'CargoExceptions', 'perm' => 'view_freight',
                'count' => fn() => sl_tableExists('cdb_cargo_exceptions') ? sl_navCount("SELECT COUNT(*) FROM cdb_cargo_exceptions WHERE status = 'open'") : 0],
            ['id' => 'frins', 'label' => 'Insurance & Claims', 'icon' => 'shield', 'board' => 'InsuranceClaims', 'perm' => 'view_freight',
                'count' => fn() => sl_tableExists('cdb_insurance_claims') ? sl_navCount("SELECT COUNT(*) FROM cdb_insurance_claims WHERE status IN ('open','review')") : 0],
            ['id' => 'frpart', 'label' => 'Freight Partners', 'icon' => 'users', 'board' => 'FreightPartners', 'perm' => 'view_freight'],
        ]],
        ['label' => 'People', 'items' => [
            ['id' => 'cust', 'label' => 'Customers', 'icon' => 'users', 'board' => 'Customers', 'perm' => 'view_client_list',
                'count' => fn() => sl_num(sl_navCount("SELECT COUNT(*) FROM cdb_users WHERE userlevel = 1"))],
            ['id' => 'recip', 'label' => 'Recipients', 'icon' => 'pin', 'board' => 'Recipients', 'perm' => 'view_recipients'],
            ['id' => 'drivers', 'label' => 'Drivers', 'icon' => 'wheel', 'board' => 'Drivers', 'perm' => 'view_user_list',
                'count' => fn() => sl_navCount("SELECT COUNT(*) FROM cdb_users WHERE userlevel = 3 AND active = 1"),
                'count2' => fn() => '/ ' . sl_navCount("SELECT COUNT(*) FROM cdb_users WHERE userlevel = 3")],
            ['id' => 'deliverydispatch', 'label' => 'Delivery requests', 'icon' => 'truck', 'board' => 'DeliveryDispatch', 'perm' => ['manage_freight','dispatch_riders','assign_drivers','manage_rider_fees']],
            ['id' => 'drvapp', 'label' => 'Rider App', 'icon' => 'phone', 'board' => 'DriverApp', 'perm' => ['view_user_list', 'driver:self']],
            ['id' => 'dispatch', 'label' => 'Dispatch Board', 'icon' => 'route', 'board' => 'RiderDispatch', 'perm' => 'dispatch_riders'],
            ['id' => 'staffact', 'label' => 'Staff Activity', 'icon' => 'clock', 'board' => 'StaffActivity', 'perm' => 'view_staff_activity'],
            ['id' => 'staff', 'label' => 'Staff & Roles', 'icon' => 'shield', 'board' => 'StaffRoles', 'perm' => ['view_user_list', 'view_role_assignment']],
        ]],
        ['label' => 'Finance', 'finance' => true, 'items' => [
            ['id' => 'billhub', 'label' => 'Billing & Finance', 'icon' => 'dollar', 'board' => 'BillingHub', 'perm' => ['financial_sheet', 'manage_finance_switch']],
            ['id' => 'trans', 'label' => 'Transactions', 'icon' => 'activity', 'board' => 'Transactions', 'perm' => 'view_transactions'],
            ['id' => 'sendp', 'label' => 'Send Prices', 'icon' => 'send', 'board' => 'SendPrices', 'perm' => 'send_prices'],
            ['id' => 'ar', 'label' => 'Accounts Receivable', 'icon' => 'receipt', 'board' => 'AccountsReceivable', 'perm' => 'view_receivable_accounts'],
            ['id' => 'fin', 'label' => 'Financial Overview', 'icon' => 'dollar', 'board' => 'FinancialOverview', 'perm' => 'view_financial_overview', 'ok' => true,
                'count' => fn() => sl_money(sl_navCount("SELECT COALESCE(SUM(a.total_order),0) FROM cdb_add_order a WHERE a.is_deleted = 0 AND MONTH(a.order_date) = MONTH(CURDATE()) AND YEAR(a.order_date) = YEAR(CURDATE()) $w"))],
        ]],
        ['label' => 'Insights', 'items' => [
            ['id' => 'reports', 'label' => 'Reports', 'icon' => 'chart', 'board' => 'Reports', 'perm' => 'view_general_reports'],
            ['id' => 'activity', 'label' => 'Activity Logs', 'icon' => 'clock', 'board' => 'ActivityLogs', 'perm' => 'view_activity_logs'],
            ['id' => 'messages', 'label' => 'Message Logs', 'icon' => 'archive', 'board' => 'MessageLogs', 'perm' => 'view_message_logs'],
        ]],
        ['label' => 'Settings', 'items' => [
            ['id' => 'general', 'label' => 'General', 'icon' => 'cog', 'board' => 'SettingsHub', 'perm' => 'view_tools'],
            ['id' => 'apis', 'label' => 'API Connections', 'icon' => 'link', 'board' => 'ApiConnections', 'perm' => 'manage_api_settings',
                'count' => fn() => sl_tableExists('cdb_api_connections') ? sl_navCount("SELECT COUNT(*) FROM cdb_api_connections") : 0],
            ['id' => 'apitraffic', 'label' => 'Live API Traffic', 'icon' => 'activity', 'board' => 'ApiTraffic', 'perm' => 'view_api_logs'],
            ['id' => 'ebuild', 'label' => 'Email Builder', 'icon' => 'mail', 'board' => 'EmailBuilder', 'perm' => 'manage_newsletter'],
            ['id' => 'msgtpl', 'label' => 'SMS & WhatsApp', 'icon' => 'message', 'board' => 'MessageTemplates', 'perm' => ['list_sms_templates', 'manage_whatsapp_templates']],
            ['id' => 'compose', 'label' => 'Compose Message', 'icon' => 'mail', 'board' => 'ComposeMessage', 'perm' => ['push_notifications', 'manage_newsletter']],
            ['id' => 'bcast', 'label' => 'Push Notifications', 'icon' => 'send', 'board' => 'Broadcast', 'perm' => 'push_notifications'],
            ['id' => 'costs', 'label' => 'Operating Costs', 'icon' => 'dollar', 'board' => 'CostSettings', 'perm' => 'superadmin'],
            ['id' => 'addr', 'label' => 'Shipping Addresses', 'icon' => 'pin', 'board' => 'ShippingAddresses', 'perm' => 'view_tools'],
            ['id' => 'rfees', 'label' => 'Rider Fees', 'icon' => 'truck', 'board' => 'RiderSettings', 'perm' => ['manage_rider_fees', 'view_tools']],
            ['id' => 'loginpg', 'label' => 'Login Page', 'icon' => 'image', 'board' => 'LoginSettings', 'perm' => 'view_tools'],
            ['id' => 'devmod', 'label' => 'Modules', 'icon' => 'sliders', 'board' => 'ModuleController', 'perm' => 'superadmin'],
            ['id' => 'perm', 'label' => 'Permissions', 'icon' => 'key', 'board' => 'StaffPermissions', 'perm' => 'view_role_assignment'],
            ['id' => 'lblstudio', 'label' => 'Label Printing', 'icon' => 'print', 'board' => 'LabelStudio', 'perm' => ['view_tools', 'print_label']],
            ['id' => 'auto', 'label' => 'Automations', 'icon' => 'bolt', 'board' => 'Automations', 'perm' => 'manage_automations'],
        ]],
    ];
}

/** Customer navigation (ClientDashboard.dc.html sidebar). */
function sl_navCustomer(): array
{
    $uid = (int) ($_SESSION['userid'] ?? 0);
    return [
        ['label' => 'My shipping', 'items' => [
            ['id' => 'dash', 'label' => 'Dashboard', 'icon' => 'grid', 'board' => 'Main', 'perm' => null],
            ['id' => 'myaccount', 'label' => 'My account', 'icon' => 'user', 'board' => 'UserHome', 'perm' => null],
            ['id' => 'air', 'label' => 'My Shipments', 'icon' => 'plane', 'board' => 'AirShipments', 'perm' => 'view_shipment_list',
                'count' => fn() => sl_navCount("SELECT COUNT(*) FROM cdb_add_order WHERE sender_id = :u AND is_deleted = 0 AND status_courier NOT IN (8,15,21)", [':u' => $uid])],
            ['id' => 'sea', 'label' => 'My Packages', 'icon' => 'box', 'board' => 'SeaPackages', 'perm' => 'view_package_list',
                'count' => fn() => sl_navCount("SELECT COUNT(*) FROM cdb_customers_packages WHERE sender_id = :u AND status_courier NOT IN (8,15,21)", [':u' => $uid])],
            ['id' => 'prealert', 'label' => 'Pre-Alerts', 'icon' => 'inbox', 'board' => 'PreAlerts', 'perm' => 'prealert_list',
                'count' => fn() => sl_navCount("SELECT COUNT(*) FROM cdb_pre_alert WHERE customer_id = :u AND is_package = 0", [':u' => $uid])],
            ['id' => 'deliveries', 'label' => 'Request delivery', 'icon' => 'truck', 'board' => 'DeliveryRequest', 'perm' => null],
            ['id' => 'codes', 'label' => 'Pickup codes', 'icon' => 'key', 'board' => 'PickupCodes', 'perm' => null],
            ['id' => 'track', 'label' => 'Track a parcel', 'icon' => 'route', 'board' => 'PublicTracking', 'perm' => null],
            ['id' => 'pickup', 'label' => 'My pickups', 'icon' => 'truck', 'board' => 'Pickups', 'perm' => ['view_pickup_list', 'pickup_add']],
        ]],
        ['label' => 'Account', 'items' => [
            ['id' => 'recip', 'label' => 'Recipients', 'icon' => 'user', 'board' => 'RecipientsMine', 'perm' => 'view_recipients',
                'count' => fn() => sl_navCount("SELECT COUNT(*) FROM cdb_recipients WHERE sender_id = :u", [':u' => $uid])],
            ['id' => 'pay', 'label' => 'Payments', 'icon' => 'card', 'board' => 'Payments', 'perm' => 'view_payment_shipment_list'],
            ['id' => 'profile', 'label' => 'Profile', 'icon' => 'sliders', 'board' => 'AiStudio', 'perm' => null],
            ['id' => 'mybills', 'label' => 'My bills', 'icon' => 'receipt', 'board' => 'MyBills', 'perm' => 'view_my_bills', 'finance' => true],
            ['id' => 'notif', 'label' => 'Notifications', 'icon' => 'bell', 'board' => 'Notifications', 'perm' => null,
                'count' => fn() => sl_navCount("SELECT COUNT(*) FROM cdb_notifications_users WHERE user_id = :u AND notification_read = 0", [':u' => $uid])],
            ['id' => 'notifset', 'label' => 'Notification settings', 'icon' => 'sliders', 'board' => 'CustomerNotifications', 'perm' => null],
        ]],
    ];
}

/** Permission test used by the nav. Special keys: ai:use, ai:manage, cs:agent, cs:manage, superadmin, driver:self, finance flags. */
function sl_navAllowed($perm, array $ctx): bool
{
    global $user;
    if ($perm === null) {
        return true;
    }
    foreach ((array) $perm as $p) {
        switch ($p) {
            case 'superadmin':
                if (sl_userIs('superadmin')) return true;
                break;
            case 'ai:use':
                if ((function_exists('cdp_aiCanUse') && cdp_aiCanUse($user)) || (function_exists('cdp_aiCanManage') && cdp_aiCanManage($user))) return true;
                break;
            case 'ai:manage':
                if (function_exists('cdp_aiCanManage') && cdp_aiCanManage($user)) return true;
                break;
            case 'cs:agent':
                if (function_exists('cdp_csIsAgent') && cdp_csIsAgent($user)) return true;
                break;
            case 'cs:manage':
                if (sl_userIs('admin')) return true;
                break;
            case 'driver:self':
                if (sl_userIs('driver')) return true;
                break;
            default:
                if ($user->cdp_hasPermission($p)) return true;
        }
    }
    return false;
}

/** Resolve the navigation for the current user into renderable groups. */
function sl_navResolve(string $activeId): array
{
    global $user;
    require_once CDP_ROOT . '/helpers/finance_switch.php';
    if (function_exists('cdp_aiCanUse')) {
        // helpers already loaded elsewhere
    } else {
        @include_once CDP_ROOT . '/helpers/ai_assistant.php';
        @include_once CDP_ROOT . '/helpers/support.php';
    }
    if (is_file(CDP_ROOT . '/helpers/sl_modules.php')) {
        require_once CDP_ROOT . '/helpers/sl_modules.php';
    }
    $groups = sl_userIs('client') ? sl_navCustomer() : sl_navStaff();
    $finance = cdp_financeEnabled();
    $out = [];
    foreach ($groups as $g) {
        if (!empty($g['finance']) && !$finance) {
            continue;
        }
        $items = [];
        foreach ($g['items'] as $it) {
            if (!empty($it['finance']) && !$finance) {
                continue;
            }
            if (!sl_navAllowed($it['perm'], $it)) {
                continue;
            }
            if (function_exists('sl_navItemEnabled') && !sl_navItemEnabled($it['id'])) {
                continue;
            }
            $c1 = isset($it['count']) ? (string) $it['count']() : '';
            $c2 = isset($it['count2']) ? (string) $it['count2']() : '';
            if ($c1 === '0') {
                $c1 = '';
                $c2 = '';
            }
            $items[] = [
                'id' => $it['id'],
                'href' => $it['href'] ?? sl_route($it['board']),
                'label' => $it['label'],
                'icon' => 'i-' . $it['icon'],
                'cls' => $it['id'] === $activeId ? 'is-active' : '',
                'ok' => !empty($it['ok']) ? 'ok' : '',
                'c1' => $c1,
                'c2' => $c2,
            ];
        }
        if ($items) {
            $out[] = ['label' => $g['label'], 'items' => $items, 'open' => in_array($g['label'], ['Operations', 'My shipping'], true)];
        }
    }
    return $out;
}

/** Board name served by the current script (reverse of sl_route), or ''. */
function sl_boardForScript(?string $script = null): string
{
    $script = $script ?? sl_self();
    foreach (sl_routes() as $board => $url) {
        if (strtok($url, '?') === $script) {
            return $board;
        }
    }
    return '';
}

/** Navigation item id that links to a board, or ''. */
function sl_navIdForBoard(string $board): string
{
    if ($board === '') {
        return '';
    }
    $groups = sl_userIs('client') ? sl_navCustomer() : sl_navStaff();
    foreach ($groups as $g) {
        foreach ($g['items'] as $it) {
            if ($it['board'] === $board) {
                return $it['id'];
            }
        }
    }
    return '';
}
