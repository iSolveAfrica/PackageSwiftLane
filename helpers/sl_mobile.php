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
        // Staff on a phone (design: M-Home tab bar): Home, Shipments, Scan raised in the middle, Inbox, then the full menu.
        $links = [['Home', 'grid', sl_route('Main')]];
        if ($user->cdp_hasPermission('view_shipment_list')) $links[] = ['Shipments', 'plane', sl_route('AirShipments')];
        if ($user->cdp_hasPermission(['package_intake', 'add_package'])) $links[] = ['Scan', 'scan', sl_route('PackageIntake')];
        elseif ($user->cdp_hasPermission(['warehouse_view', 'view_warehouse_delivery'])) $links[] = ['Scan', 'scan', sl_route('Warehouse')];
        $inbox = function_exists('sl_navAllowed') && sl_navAllowed('ops:staff', []);
        $links[] = $inbox ? ['Inbox', 'message', sl_route('CommsInbox')] : ['Alerts', 'bell', sl_route('Notifications')];
        $links[] = ['Menu', 'more', '#menu'];
        return $links;
    }
    // Customer app (design: M-CHome tab bar): Home, Parcels, Track raised in the middle, Bills (or Support when
    // finance is off), then the full menu, which also holds the account, pickup codes and settings.
    $fourth = cdp_financeEnabled() && $user->cdp_hasPermission('view_my_bills') ? ['Bills', 'receipt', sl_route('MyBills')] : ['Support', 'message', 'support.php'];
    return [['Home', 'grid', sl_route('Main')], ['Parcels', 'box', sl_route('MyParcels')], ['Track', 'route', sl_route('LiveTracking')], $fourth, ['Menu', 'more', '#menu']];
}
