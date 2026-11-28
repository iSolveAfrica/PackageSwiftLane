<?php
/**
 * Phone app screens (design canvas "Swift Lane UI Redesign", Page 2, the M-* artboards).
 *
 * On a phone (helpers/sl_device.php sl_isPhone()) sl_render('X', ...) renders views/sl/phone/X.php inside the
 * design's own frame (<div class="mx">: app bar, scrolling body, tab bar) instead of the web page
 * views/sl/pages/X.php. Both get the same $d from the controller, so the phone screen shows the same data.
 * Computers and tablets never see these files.
 *
 * A phone template prints, in this order (exactly the design's markup, filled with real data):
 *   <header class="mx-top">…</header>           or sl_phoneTop(...)  (none on sign-in screens)
 *   <main class="mx-body">…</main>
 *   <div class="mx-foot">…</div>                  optional sticky actions
 * and may set, before or while printing:
 *   $slPhone['nav']   'staff' | 'dev' | 'rider' | 'customer' | 'none'   (default: by role)
 *   $slPhone['tab']   label of the lit tab, e.g. 'Shipments' (default: matched from the URL)
 *   $slPhone['cls']   extra classes on .mx, e.g. 'dk' (developer), 'mx-bare' (sign-in), 'rs-r' (rider)
 *   $slPhone['scripts'] extra scripts
 */
require_once __DIR__ . '/sl_device.php';
require_once __DIR__ . '/sl_routes.php';

/** Phone template file for a view, or '' when the view has none (the web page is shown then). */
function sl_phoneView(string $view): string
{
    if (!function_exists('sl_isPhone') || !sl_isPhone()) {
        return '';
    }
    // A view that serves different design screens per role has <View>.<role>.php (customer | rider | staff).
    $role = sl_phoneNavFor();
    $role = $role === 'none' ? 'public' : $role;
    foreach ([$view . '.' . $role, $view] as $name) {
        $file = CDP_ROOT . '/views/sl/phone/' . $name . '.php';
        if (is_file($file)) {
            return $file;
        }
    }
    return '';
}

/** Tab bars of the design. Each tab: [label, icon, url, middle?]. Permission-aware for staff. */
function sl_phoneTabs(string $set): array
{
    global $user;
    $can = fn($p) => isset($user) && $user->cdp_hasPermission($p);
    switch ($set) {
        case 'rider':
            return [['Home', 'grid', sl_route('RiderHome')], ['Jobs', 'box', sl_route('RiderQueue')], ['Scan', 'scan', sl_route('RiderScan')], ['Earnings', 'dollar', sl_route('RiderEarnings')], ['Account', 'user', sl_route('RiderAccount')]];
        case 'customer':
            $fin = function_exists('cdp_financeEnabled') && cdp_financeEnabled() && $can('view_my_bills');
            return [['Home', 'grid', sl_route('Main')], ['Parcels', 'box', sl_route('MyParcels')], ['Track', 'route', sl_route('LiveTracking')], $fin ? ['Bills', 'receipt', sl_route('MyBills')] : ['Help', 'message', 'support.php'], ['Menu', 'more', sl_route('MyAccount')]];
        case 'dev':
            return [['Console', 'terminal', sl_route('DevOverview')], ['Logs', 'activity', sl_route('DevLogs')], ['Alerts', 'alert', sl_route('DevAlerts'), true], ['Issues', 'bug', sl_route('DevIssues')], ['Menu', 'more', 'menu.php']];
        case 'staff':
            require_once __DIR__ . '/sl_nav.php';
            // The artifact's staff tab bar (ControlPanelsMobile / AiChatMobile): five flat tabs, no raised button.
            // Home · Shipments · Inbox · Assistant · Panels; each falls back to a page this person may open.
            $nav = fn($k) => function_exists('sl_navAllowed') && sl_navAllowed($k, []);
            $tabs = [['Home', 'grid', sl_route('Main')]];
            $tabs[] = $can('view_shipment_list') ? ['Shipments', 'plane', sl_route('AirShipments')] : ['Search', 'search', sl_route('LockerSearch')];
            $tabs[] = $nav('cs:agent') ? ['Inbox', 'ticket', sl_route('CsInbox')] : ($nav('ops:staff') ? ['Inbox', 'message', sl_route('CommsInbox')] : ['Alerts', 'bell', sl_route('Notifications')]);
            $tabs[] = $nav('ai:use') ? ['Assistant', 'spark', sl_route('StaffAI')] : ['Scan', 'scan', $can(['package_intake', 'add_package']) ? sl_route('PackageIntake') : sl_route('LockerSearch')];
            $tabs[] = ['Panels', 'layers', sl_route('ControlPanels')];
            return $tabs;
    }
    return [];
}

/** Default tab set for the signed-in person. */
function sl_phoneNavFor(): string
{
    if (empty($_SESSION['userid'])) return 'none';
    if (function_exists('sl_userIs') && sl_userIs('client')) return 'customer';
    if (function_exists('sl_userIs') && sl_userIs('driver')) return 'rider';
    return 'staff';
}

/** The design's tab bar: .mx-tab (flat labelled tabs; the developer set keeps a raised middle) for staff, customers and developer, .mx-pill (icons) for riders. */
function sl_phoneNav(string $set, string $current = ''): string
{
    $tabs = sl_phoneTabs($set);
    if (!$tabs) return '';
    $here = basename(strtok((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '?'));
    if ($current === '') {
        foreach ($tabs as $t) {
            if (basename(strtok($t[2], '?#')) === $here) { $current = $t[0]; break; }
        }
    }
    $pill = $set === 'rider';   // customers and staff get the artifact's flat labelled tab bar
    $out = '<nav class="' . ($pill ? 'mx-pill' : 'mx-tab') . '" aria-label="App">';
    foreach ($tabs as $t) {
        $on = $t[0] === $current;
        $cur = $on ? ' aria-current="page"' : '';
        if ($pill) {
            $out .= '<a class="' . ($on ? 'on' : '') . '" href="' . h($t[2]) . '" aria-label="' . h($t[0]) . '"' . $cur . '><i class="ic i-' . h($t[1]) . '" aria-hidden="true"></i></a>';
        } elseif (!empty($t[3])) {
            $out .= '<a class="' . ($on ? 'on ' : '') . 'mx-mid" href="' . h($t[2]) . '"' . $cur . '><b><i class="ic i-' . h($t[1]) . '" aria-hidden="true"></i></b><span>' . h($t[0]) . '</span></a>';
        } else {
            $out .= '<a class="' . ($on ? 'on' : '') . '" href="' . h($t[2]) . '"' . $cur . '><i class="ic i-' . h($t[1]) . '" aria-hidden="true"></i><span>' . h($t[0]) . '</span></a>';
        }
    }
    return $out . '</nav>';
}

/**
 * App bar (.mx-top). $lead: ['back' => url] | ['avatar' => 'KA', 'href' => url, 'online' => bool] | null.
 * $actions: icon buttons from sl_phoneIcon().
 */
function sl_phoneTop(string $title, string $sub = '', ?array $lead = null, string $actions = ''): string
{
    $out = '<header class="mx-top">';
    if (isset($lead['back'])) {
        $out .= '<a class="mx-ib" href="' . h($lead['back']) . '" data-mx-back aria-label="Back"><i class="ic i-left" aria-hidden="true"></i></a>';
    } elseif (isset($lead['avatar'])) {
        $out .= '<a class="mx-av" href="' . h($lead['href'] ?? '#') . '" aria-label="My account">' . h($lead['avatar']) . (!empty($lead['online']) ? '<i aria-hidden="true"></i>' : '') . '</a>';
    }
    $out .= '<div class="mx-ttl"><h1>' . h($title) . '</h1>' . ($sub !== '' ? '<small>' . h($sub) . '</small>' : '') . '</div>';
    return $out . $actions . '</header>';
}

/** Icon button for the app bar; $badge > 0 shows the orange count. */
function sl_phoneIcon(string $icon, string $label, string $href, int $badge = 0): string
{
    return '<a class="mx-ib" href="' . h($href) . '" aria-label="' . h($label) . ($badge > 0 ? ', ' . $badge . ' new' : '') . '"><i class="ic i-' . h($icon) . '" aria-hidden="true"></i>' . ($badge > 0 ? '<b>' . ($badge > 99 ? '99+' : $badge) . '</b>' : '') . '</a>';
}

/** Initials of the signed-in person, for the avatar in the app bar. */
function sl_phoneMe(): array
{
    global $userData;
    $name = trim((string) (($userData->fname ?? '') . ' ' . ($userData->lname ?? ''))) ?: (string) ($userData->username ?? '');
    $first = trim((string) ($userData->fname ?? '')) ?: $name;
    return ['name' => $name, 'first' => $first, 'ini' => function_exists('sl_initials') ? sl_initials($name ?: '?') : '?'];
}
