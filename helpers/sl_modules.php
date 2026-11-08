<?php
/**
 * Module Controller: which parts of Swift Lane are switched on.
 *
 * State lives in cdb_sl_settings as module_<id> = '1' | '0' (absent = on).
 * sl_moduleEnabled('freight') is meant to be called by helpers/sl_nav.php
 * (hide the item when its module is off) and by page controllers that want a
 * 404 "module_disabled" for a switched-off area.
 */
require_once __DIR__ . '/sl_view.php';

/** The module catalogue: id => [label, group, desc, nav ids, flags]. */
function sl_modules(): array
{
    return [
        // Operations
        'air'           => ['label' => 'Air shipments', 'group' => 'ops', 'desc' => 'Air freight parcels, labels and tracking', 'nav' => ['air', 'shiptrack', 'dtrack'], 'customer' => true, 'api' => ['shipments', 'tracking']],
        'sea'           => ['label' => 'Sea packages', 'group' => 'ops', 'desc' => 'Sea freight packages registered to lockers', 'nav' => ['sea'], 'customer' => true, 'api' => []],
        'prealerts'     => ['label' => 'Pre-alerts', 'group' => 'ops', 'desc' => 'Customers announce parcels before they arrive', 'nav' => ['prealert'], 'customer' => true, 'api' => ['pre-alerts']],
        'pickups'       => ['label' => 'Pickups', 'group' => 'ops', 'desc' => 'Pickup requests, aging and riders', 'nav' => ['pickup', 'lastmile', 'drvapp'], 'customer' => true, 'api' => []],
        'consolidation' => ['label' => 'Consolidations', 'group' => 'ops', 'desc' => 'Group shipments and packages into one load', 'nav' => ['consol'], 'customer' => false, 'api' => []],
        'warehouse'     => ['label' => 'Warehouse', 'group' => 'ops', 'desc' => 'Intake, hand-out and locker search', 'nav' => ['warehouse', 'handout', 'locker'], 'customer' => false, 'api' => []],
        'freight'       => ['label' => 'Freight', 'group' => 'ops', 'desc' => 'Departures, waybills, exceptions, insurance, partners', 'nav' => ['frhub', 'frsched', 'frwb', 'frex', 'frins', 'frpart'], 'customer' => false, 'api' => []],
        // People
        'customers'     => ['label' => 'Customers', 'group' => 'people', 'desc' => 'Customer accounts and recipients', 'nav' => ['cust', 'recip'], 'customer' => false, 'api' => ['customers', 'recipients'], 'core' => true],
        'drivers'       => ['label' => 'Drivers and riders', 'group' => 'people', 'desc' => 'Driver accounts and the rider app', 'nav' => ['drivers', 'drvapp'], 'customer' => false, 'api' => []],
        'staff'         => ['label' => 'Staff and roles', 'group' => 'people', 'desc' => 'Staff accounts, roles and permissions', 'nav' => ['staff', 'perm'], 'customer' => false, 'api' => [], 'core' => true],
        // Finance
        'finance'       => ['label' => 'Billing and finance', 'group' => 'finance', 'desc' => 'Payments, receivables, financial overview', 'nav' => ['billhub', 'pay', 'ar', 'fin'], 'customer' => true, 'api' => []],
        'loyalty'       => ['label' => 'Loyalty and referrals', 'group' => 'finance', 'desc' => 'Points, tiers, coupons', 'nav' => ['loyalty'], 'customer' => true, 'api' => []],
        // Messaging
        'messaging'     => ['label' => 'Messages and automation', 'group' => 'messaging', 'desc' => 'Push notifications, templates, logs, costs', 'nav' => ['commshub', 'messages', 'notifc', 'ebuild', 'msgtpl', 'bcast'], 'customer' => false, 'api' => ['notifications']],
        'automations'   => ['label' => 'Automations', 'group' => 'messaging', 'desc' => 'When this happens, do that', 'nav' => ['auto'], 'customer' => false, 'api' => []],
        // Support and AI
        'support'       => ['label' => 'Customer service', 'group' => 'support', 'desc' => 'Tickets, rules, playbook, channels', 'nav' => ['supporthub', 'servicepanel', 'tickets', 'csrules', 'playbook', 'knowledge', 'csrep', 'cschan', 'cssupai'], 'customer' => true, 'api' => []],
        'ai'            => ['label' => 'AI assistant', 'group' => 'support', 'desc' => 'Staff assistant and AI Centre', 'nav' => ['aicenter', 'csai'], 'customer' => false, 'api' => []],
        // Insights and developer
        'reports'       => ['label' => 'Reports and logs', 'group' => 'insights', 'desc' => 'Reports, activity and system logs', 'nav' => ['reports', 'activity', 'execdash', 'security'], 'customer' => false, 'api' => []],
        'developer'     => ['label' => 'Developer pages', 'group' => 'insights', 'desc' => 'Dev overview, services, deployments, alerts', 'nav' => ['devov', 'devsvc', 'devlogs', 'devmet', 'devinfra', 'devdep', 'devalert', 'devissues', 'devint'], 'customer' => false, 'api' => []],
        'api'           => ['label' => 'REST API', 'group' => 'insights', 'desc' => 'Partner API keys, monitoring and live traffic', 'nav' => ['apis', 'apitraffic'], 'customer' => true, 'api' => ['*']],
        // Customer portal only
        'portal_track'  => ['label' => 'Public tracking', 'group' => 'portal', 'desc' => 'Track a parcel without signing in', 'nav' => [], 'customer' => true, 'api' => ['tracking']],
        'portal_quote'  => ['label' => 'Quote calculator', 'group' => 'portal', 'desc' => 'Price a shipment before booking', 'nav' => [], 'customer' => true, 'api' => []],
    ];
}

function sl_moduleGroups(): array
{
    return [
        'ops' => ['label' => 'Operations', 'icon' => 'i-box', 'desc' => 'Parcels, pickups, warehouse and freight'],
        'people' => ['label' => 'People', 'icon' => 'i-users', 'desc' => 'Customers, drivers and staff'],
        'finance' => ['label' => 'Finance', 'icon' => 'i-dollar', 'desc' => 'Billing, payments and loyalty'],
        'messaging' => ['label' => 'Messaging', 'icon' => 'i-message', 'desc' => 'Notifications and automations'],
        'support' => ['label' => 'Support and AI', 'icon' => 'i-ticket', 'desc' => 'Customer service and assistants'],
        'insights' => ['label' => 'Insights and developer', 'icon' => 'i-chart', 'desc' => 'Reports, logs, API and dev pages'],
        'portal' => ['label' => 'Customer portal', 'icon' => 'i-globe', 'desc' => 'Public and customer-only features'],
    ];
}

/** All module states: id => bool. */
function sl_moduleStates(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    foreach (sl_modules() as $id => $m) {
        $cache[$id] = true;
    }
    try {
        if (sl_tableExists('cdb_sl_settings')) {
            foreach (sl_rows("SELECT setting_key, setting_value FROM cdb_sl_settings WHERE setting_key LIKE 'module_%'") as $r) {
                $id = substr((string) $r->setting_key, 7);
                if (isset($cache[$id])) {
                    $cache[$id] = (string) $r->setting_value !== '0';
                }
            }
        }
    } catch (Throwable $e) {
        // defaults stand: everything on
    }
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

/** Is a nav item id hidden by a switched-off module? (for helpers/sl_nav.php) */
function sl_navItemEnabled(string $navId): bool
{
    foreach (sl_modules() as $id => $m) {
        if (in_array($navId, $m['nav'], true) && !sl_moduleEnabled($id)) {
            return false;
        }
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

/** Persist one state; returns bool. */
function sl_moduleSet(string $id, bool $on, int $userId): bool
{
    if (!isset(sl_modules()[$id])) {
        return false;
    }
    $db = new Conexion();
    $db->cdp_query('INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_by, updated_at) VALUES (:k, :v, :u, NOW())
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)');
    $db->bind(':k', 'module_' . $id);
    $db->bind(':v', $on ? '1' : '0');
    $db->bind(':u', $userId);
    return (bool) $db->cdp_execute();
}
