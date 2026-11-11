<?php
/** Shared mobile navigation. Width/pointer media queries select the layout, not user-agent strings. */
require_once __DIR__ . '/sl_view.php';
require_once __DIR__ . '/finance_switch.php';
function sl_mobileRole(): string
{
    if (empty($_SESSION['userid'])) return '';
    return sl_userIs('client') ? 'customer' : (sl_userIs('driver') ? 'rider' : '');
}
/**
 * The role the phone shell is laid out for: customer and rider keep their
 * installed-app identity; signed-in staff get the same shell ('staff') so the
 * bottom tab bar and app spacing apply to them too. The PWA keeps using
 * sl_mobileRole(), which stays '' for staff.
 */
function sl_bodyRole(): string
{
    $role = sl_mobileRole();
    if ($role !== '') return $role;
    return empty($_SESSION['userid']) ? '' : 'staff';
}
function sl_mobileLinks($user): array
{
    if (sl_mobileRole() === 'rider') return [
        ['Jobs', 'box', sl_route('DriverApp')],
        ['Route', 'route', sl_route('DriverApp', ['tab' => 'route'])],
        ['Earnings', 'dollar', sl_route('DriverApp', ['tab' => 'pay'])],
        ['Alerts', 'bell', sl_route('Notifications')],
        ['Account', 'user', sl_route('AiStudio')],
    ];
    if (sl_mobileRole() !== 'customer') {
        if (sl_bodyRole() !== 'staff') return [];
        // Staff on a phone: the four places they go most, then the full menu.
        $links = [['Home', 'grid', sl_route('Main')]];
        if ($user->cdp_hasPermission('view_shipment_list')) $links[] = ['Shipments', 'plane', sl_route('AirShipments')];
        if ($user->cdp_hasPermission(['warehouse_view', 'view_warehouse_delivery'])) $links[] = ['Scan', 'scan', sl_route('Warehouse')];
        $links[] = ['Alerts', 'bell', sl_route('Notifications')];
        $links[] = ['Menu', 'sidebar', '#menu'];
        return $links;
    }
    $links = [['Home', 'grid', sl_route('Main')], ['Parcels', 'box', sl_route('MyParcels')], ['Codes', 'key', sl_route('PickupCodes')]];
    if (cdp_financeEnabled() && $user->cdp_hasPermission('view_my_bills')) $links[] = ['Bills', 'card', sl_route('MyBills')];
    $links[] = ['Support', 'message', 'support.php'];
    return $links;
}
