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
    // Rider app (design: M-RHome tab bar): Home, Jobs, Scan raised in the middle, Earnings, Account.
    if (sl_mobileRole() === 'rider') return [
        ['Home', 'grid', sl_route('RiderHome')],
        ['Jobs', 'box', sl_route('RiderQueue')],
        ['Scan', 'scan', sl_route('RiderScan')],
        ['Earnings', 'dollar', sl_route('RiderEarnings')],
        ['Account', 'user', sl_route('RiderAccount')],
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

/**
 * Pages that keep a tab lit although they are not the tab's own page (the screens one level below it),
 * per role: tab label => controllers.
 */
function sl_mobileTabFamily(): array
{
    $role = sl_bodyRole();
    if ($role === 'rider') return [
        'Jobs' => ['rider_jobs.php', 'rider_tasks.php', 'rider_offer.php', 'rider_route.php', 'rider_nav.php', 'rider_done.php', 'delivery_job.php'],
        'Scan' => ['rider_scan.php'],
        'Earnings' => ['rider_earnings.php', 'rider_wallet.php', 'rider_quests.php', 'rider_shifts.php'],
        'Account' => ['rider_account.php', 'rider_ratings.php', 'rider_safety.php', 'rider_chat.php', 'account_security.php', 'notifications_list.php'],
    ];
    if ($role === 'customer') return [
        'Parcels' => ['my_parcels.php', 'courier_view.php', 'customer_packages_view.php', 'courier_shipment_tracking.php', 'customer_package_tracking.php'],
        'Track' => ['live_tracking.php', 'track.php', 'tracking.php'],
        'Bills' => ['my_bills.php', 'pay_checkout.php', 'payment_return.php'],
    ];
    return [
        'Shipments' => ['courier_list.php', 'courier_view.php', 'courier_edit.php', 'courier_add.php', 'courier_shipment_tracking.php'],
    ];
}
