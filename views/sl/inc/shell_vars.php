<?php
/**
 * Application shell (header, rail, sidebar) of the Swift Lane design.
 * Markup mirrors Main.dc.html / ClientDashboard.dc.html; data comes from the session,
 * cdb_settings and the navigation registry.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/sl_nav.php';
require_once CDP_ROOT . '/helpers/activity_log.php';
if (function_exists('cdp_activityPageView') && !defined('SL_PAGEVIEW_LOGGED')) {
    define('SL_PAGEVIEW_LOGGED', true);
    cdp_activityPageView();
}
if (!isset($userData) || !$userData) {
    $userData = $user->cdp_getUserData();
}
$slIsClient = sl_userIs('client');
$slName = trim(($userData->fname ?? '') . ' ' . ($userData->lname ?? '')) ?: ($userData->username ?? 'User');
$slFirst = $userData->fname ?: $slName;
$slInitials = sl_initials($slName);
$slRole = '';
try {
    $slRole = (string) sl_scalar('SELECT role_name FROM cdb_user_roles WHERE role_id = :r', [':r' => (int) $userData->userlevel], '');
} catch (Throwable $e) {
}
if (sl_userIs('driver')) $slRole = 'Rider';
$slOffice = $userData->name_off ?: '';
$slSub = $slIsClient
    ? 'Customer' . ($userData->locker ? ' · Locker ' . $userData->locker : '')
    : trim($slRole . ($slOffice ? ' · ' . $slOffice : ''));
$slAvatar = !empty($userData->avatar) && $userData->avatar !== 'uploads/blank.png' ? 'assets/' . ltrim($userData->avatar, '/') : '';
$slPicStyle = $slAvatar ? 'background-image:url(' . h($slAvatar) . ');background-size:cover;' : 'background:var(--ink);';
$slPic = function (int $px, bool $dot = true) use ($slAvatar, $slPicStyle, $slInitials) {
    return '<span class="me-pic" style="width:' . $px . 'px;height:' . $px . 'px;font-size:' . max(10, (int) ($px * 0.36)) . 'px;' . $slPicStyle . '">'
        . ($slAvatar ? '' : h($slInitials)) . ($dot ? '<i class="me-on" aria-hidden="true"></i>' : '') . '</span>';
};
$slNav = sl_navResolve($slPage['id'] ?? '');
$slUnread = 0;
try {
    $slUnread = (int) sl_scalar('SELECT COUNT(*) FROM cdb_notifications_users WHERE user_id = :u AND notification_read = 0', [':u' => (int) $_SESSION['userid']]);
} catch (Throwable $e) {
}
$slOpenTickets = 0;
if (!$slIsClient && sl_tableExists('cdb_cs_tickets')) {
    $slOpenTickets = (int) sl_navCount("SELECT COUNT(*) FROM cdb_cs_tickets WHERE status NOT IN ('closed','resolved')");
}
$slRate = isset($core->exchange_rate) ? (float) $core->exchange_rate : 0;
$slRateDelta = '';
if ($slRate > 0 && sl_tableExists('cdb_exchange_rate_log')) {
    $prev = sl_row('SELECT old_rate, new_rate FROM cdb_exchange_rate_log ORDER BY changed_at DESC');
    if ($prev && (float) $prev->old_rate > 0) {
        $pct = ((float) $prev->new_rate - (float) $prev->old_rate) / (float) $prev->old_rate * 100;
        $slRateDelta = ($pct >= 0 ? '▲ ' : '▼ ') . number_format(abs($pct), 1) . '%';
    }
}
$slPrimary = $slPage['primary'] ?? ($slIsClient
    ? ($user->cdp_hasPermission('courier_add_client') ? ['href' => 'courier_add_client.php', 'label' => 'New Shipment'] : null)
    : ($user->cdp_hasPermission('add_shipment') ? ['href' => sl_route('NewShipment'), 'label' => 'New Shipment'] : null));
$slTheme = trim('sl-app ' . ($slPage['theme'] ?? ''));
$slHome = sl_route('Main');
$slHub = $slIsClient ? 'My account' : ($slOffice ?: ($core->c_city ?? 'Hub'));
$slProfileUrl = sl_route('AiStudio');
$slHelpUrl = $slIsClient ? 'support.php' : sl_route('CsInbox');

// Customer help opens the customer service workspace; staff AI needs its own access.
$slCanAskAI = !$slIsClient && sl_navAllowed('ai:use', []);
$slHasSupport = $slIsClient || sl_navAllowed('cs:agent', []);
$slAssistantUrl = $slCanAskAI ? sl_route('StaffAI', ['q' => 'Explain this page', 'page' => sl_self()]) : $slHelpUrl;
