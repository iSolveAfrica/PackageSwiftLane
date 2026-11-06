<?php
/**
 * Shared helpers for the operations pages (sea packages, pre-alerts, pickups,
 * consolidations, warehouse). Pure read helpers; writes live in ajax/sl/*.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/querys.php';
require_once CDP_ROOT . '/helpers/handover.php';
require_once CDP_ROOT . '/helpers/finance_switch.php';
require_once CDP_ROOT . '/helpers/sl_charts.php';

/** Row scope for the current user: drivers see their rows, customers theirs, agencies their agency. */
function sl_opsScope(string $alias = 'a', string $ownerCol = 'sender_id'): string
{
    $uid = (int) ($_SESSION['userid'] ?? 0);
    if (sl_userIs('client')) {
        return " AND $alias.$ownerCol = $uid";
    }
    if (sl_userIs('driver')) {
        return " AND $alias.driver_id = $uid";
    }
    $ctx = function_exists('cdp_getAgencyContext') ? cdp_getAgencyContext() : ['is_restricted' => false];
    if (!empty($ctx['is_restricted']) && !empty($ctx['agency_id'])) {
        return " AND $alias.agency = " . (int) $ctx['agency_id'];
    }
    return '';
}

/** Is this row inside the current user's scope (for endpoints)? */
function sl_opsInScope(object $row): bool
{
    $uid = (int) ($_SESSION['userid'] ?? 0);
    if (sl_userIs('client')) {
        return (int) ($row->sender_id ?? 0) === $uid;
    }
    if (sl_userIs('driver')) {
        return (int) ($row->driver_id ?? 0) === $uid;
    }
    $ctx = function_exists('cdp_getAgencyContext') ? cdp_getAgencyContext() : ['is_restricted' => false];
    if (!empty($ctx['is_restricted']) && !empty($ctx['agency_id'])) {
        return (int) ($row->agency ?? 0) === (int) $ctx['agency_id'];
    }
    return true;
}

/** cdb_styles rows keyed by id. */
function sl_statusMap(): array
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (sl_rows('SELECT id, mod_style, color FROM cdb_styles ORDER BY id') as $r) {
            $map[(int) $r->id] = $r;
        }
    }
    return $map;
}

function sl_statusLabel($id): string
{
    $m = sl_statusMap();
    return isset($m[(int) $id]) ? str_replace('_', ' ', $m[(int) $id]->mod_style) : '—';
}

/** Payment pill for status_invoice (1 paid, 2 pending, 3 to verify, 23 unpaid). */
function sl_payPill($statusInvoice): array
{
    switch ((int) $statusInvoice) {
        case 1: return ['Paid', 'paid'];
        case 3: return ['Verify payment', 'onway'];
        case 0: case 2: return ['Pending', 'pending'];
        default: return ['Unpaid', 'overdue'];
    }
}

/** Stage of a package for the 5-step bar: 1 registered/warehouse, 2 loaded, 3 in transit, 4 ready, 5 handed over. */
function sl_stageOf($status, bool $inConsolidation = false): int
{
    $s = (int) $status;
    if (cdp_isHandedOver($s)) return 5;
    if (in_array($s, [6, 32], true)) return 4;
    if (in_array($s, [3, 5, 7, 14], true)) return 3;
    if ($s === 13 || $inConsolidation) return 2;
    return 1;
}

function sl_weight($value, int $dec = 1): string
{
    global $core;
    return number_format((float) $value, $dec) . ' ' . ($core->weight_p ?? 'lb');
}

/** Users (customers) keyed by id for a set of ids, one query. */
function sl_usersById(array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) {
        return [];
    }
    $out = [];
    foreach (sl_rows('SELECT id, fname, lname, email, phone, locker, avatar FROM cdb_users WHERE id IN (' . implode(',', $ids) . ')') as $u) {
        $out[(int) $u->id] = $u;
    }
    return $out;
}

function sl_userName(?object $u): string
{
    return $u ? trim(($u->fname ?? '') . ' ' . ($u->lname ?? '')) ?: ($u->username ?? '—') : '—';
}

/** Consolidation membership for a set of order ids: order_id => [code, consolidate_id, status]. */
function sl_consolidationsFor(array $orderIds, bool $sea): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
    if (!$ids) {
        return [];
    }
    $detail = $sea ? 'cdb_consolidate_packages_detail' : 'cdb_consolidate_detail';
    $head = $sea ? 'cdb_consolidate_packages' : 'cdb_consolidate';
    $out = [];
    foreach (sl_rows("SELECT d.order_id, c.consolidate_id, c.c_prefix, c.c_no, c.status_courier FROM $detail d INNER JOIN $head c ON c.consolidate_id = d.consolidate_id WHERE d.order_id IN (" . implode(',', $ids) . ') ORDER BY c.consolidate_id DESC') as $r) {
        if (!isset($out[(int) $r->order_id])) {
            $out[(int) $r->order_id] = ['code' => $r->c_prefix . $r->c_no, 'id' => (int) $r->consolidate_id, 'status' => (int) $r->status_courier];
        }
    }
    return $out;
}

/** Open (not handed over, not cancelled) consolidations for a select. */
function sl_openConsolidations(bool $sea): array
{
    $head = $sea ? 'cdb_consolidate_packages' : 'cdb_consolidate';
    return sl_rows("SELECT consolidate_id, c_prefix, c_no, c_date, status_courier FROM $head WHERE status_courier NOT IN (8,15,21) ORDER BY consolidate_id DESC LIMIT 100");
}

/** Lookup lists shared by the forms. */
function sl_opsLookups(): array
{
    return [
        'agencies'  => sl_rows('SELECT id, name_branch AS label FROM cdb_branchoffices ORDER BY name_branch'),
        'offices'   => sl_rows('SELECT id, name_off AS label, city FROM cdb_offices ORDER BY name_off'),
        'couriers'  => sl_rows('SELECT id, name_com AS label FROM cdb_courier_com ORDER BY name_com'),
        'packaging' => sl_rows('SELECT id, name_pack AS label FROM cdb_packaging ORDER BY name_pack'),
        'categories'=> sl_rows('SELECT id, name_item AS label FROM cdb_category ORDER BY name_item'),
        'delitimes' => sl_rows('SELECT id, delitime AS label FROM cdb_delivery_time ORDER BY id'),
        'services'  => sl_rows('SELECT id, ship_mode AS label FROM cdb_shipping_mode ORDER BY id'),
        'payments'  => sl_rows('SELECT id, name_pay AS label FROM cdb_met_payment WHERE is_active = 1 ORDER BY id'),
        'drivers'   => sl_rows('SELECT id, fname, lname FROM cdb_users WHERE userlevel = 3 AND active = 1 ORDER BY fname'),
        'statuses'  => sl_rows('SELECT id, mod_style AS label FROM cdb_styles ORDER BY id'),
        'paymethods'=> sl_rows('SELECT id, label, days FROM cdb_payment_methods ORDER BY id'),
    ];
}

/** Customers in scope for a select (id, name, locker). */
function sl_opsCustomers(int $limit = 500): array
{
    $ctx = function_exists('cdp_getAgencyContext') ? cdp_getAgencyContext() : ['is_restricted' => false];
    $scope = !empty($ctx['is_restricted']) && !empty($ctx['agency_id']) ? ' AND agency_id = ' . (int) $ctx['agency_id'] : '';
    if (sl_userIs('client')) {
        $scope = ' AND id = ' . (int) $_SESSION['userid'];
    }
    return sl_rows("SELECT id, fname, lname, locker, phone FROM cdb_users WHERE userlevel = 1 AND active = 1 $scope ORDER BY fname, lname LIMIT $limit");
}

/** Sender addresses of a customer with the city/state/country names resolved. */
function sl_opsAddresses(int $userId): array
{
    $rows = sl_rows('SELECT sa.id_addresses AS id, sa.address, sa.zip_code, c.name AS city, s.name AS state, k.name AS country FROM cdb_senders_addresses sa LEFT JOIN cdb_cities c ON c.id = sa.city LEFT JOIN cdb_states s ON s.id = sa.state LEFT JOIN cdb_countries k ON k.id = sa.country WHERE sa.user_id = :u ORDER BY sa.id_addresses', [':u' => $userId]);
    $out = [];
    foreach ($rows as $r) {
        $out[] = ['id' => (int) $r->id, 'label' => trim(implode(', ', array_filter([$r->address, $r->city, $r->state, $r->country])))];
    }
    return $out;
}

/** Tracking history rows of a tracking number, newest first. */
function sl_trackHistory(string $orderTrack): array
{
    return sl_rows('SELECT t.t_date, t.status_courier, t.comments, t.t_city, t.t_dest, s.mod_style FROM cdb_courier_track t LEFT JOIN cdb_styles s ON s.id = t.status_courier WHERE t.order_track = :t ORDER BY t.t_date DESC, t.id DESC LIMIT 30', [':t' => $orderTrack]);
}

/** Pickup-code state for packages (cheap, never throws). */
function sl_pickupCodeInfo(string $module, array $orderIds): array
{
    try {
        require_once CDP_ROOT . '/helpers/pickup_code.php';
        if (!sl_tableExists('cdb_pickup_codes')) {
            return ['verified' => false, 'live' => null];
        }
        return cdp_pickupCodeState($module, $orderIds);
    } catch (Throwable $e) {
        return ['verified' => false, 'live' => null];
    }
}

function sl_slSetting(string $key, $default = null)
{
    if (!sl_tableExists('cdb_sl_settings')) {
        return $default;
    }
    $v = sl_scalar('SELECT setting_value FROM cdb_sl_settings WHERE setting_key = :k', [':k' => $key], null);
    return $v === null ? $default : $v;
}
