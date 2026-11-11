<?php
/** Shared mobile navigation. Width/pointer media queries select the layout, not user-agent strings. */
require_once __DIR__ . '/sl_view.php';
require_once __DIR__ . '/finance_switch.php';
function sl_mobileRole(): string
{
    if (empty($_SESSION['userid'])) return '';
    return sl_userIs('client') ? 'customer' : (sl_userIs('driver') ? 'rider' : '');
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
    if (sl_mobileRole() !== 'customer') return [];
    $links = [['Home', 'grid', sl_route('Main')], ['Parcels', 'box', sl_route('MyParcels')], ['Codes', 'key', sl_route('PickupCodes')]];
    if (cdp_financeEnabled() && $user->cdp_hasPermission('view_my_bills')) $links[] = ['Bills', 'card', sl_route('MyBills')];
    $links[] = ['Support', 'message', 'support.php'];
    return $links;
}
