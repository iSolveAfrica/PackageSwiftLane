<?php
/**
 * Customer app on a computer: customers on a wide screen see the site as the phone app, in an app window
 * (views/sl/inc/app_frame.php). The window shows each page in a phone-width frame, so every page is
 * laid out exactly as on a phone (tab bar Home · Parcels · Menu · Support · Account, cards, sheets ...).
 *
 *  - Only top-level page loads are wrapped (Sec-Fetch-Dest: document); the page inside the window is a
 *    normal request (Sec-Fetch-Dest: iframe), so it never wraps itself. Browsers that do not send the
 *    header simply get the website.
 *  - The browser tells us it is wide with the cookie sl_vw=wide (set by dataJs/sl/appframe.js).
 *  - "Open the full website" sets sl_appview=off (?appview=off); ?appview=on turns the app window back on.
 *  - Payments, receipts, prints and downloads always open as full pages (outside the window).
 */
function sl_appFrameNever(): array
{
    return ['pay_checkout.php', 'payment_return.php', 'track_invoice.php', 'download.php', 'logout.php', 'view_as.php'];
}

/** This page may be shown inside the app window (also used by the page itself to know it can ask for it). */
function sl_appFrameable(): bool
{
    if (!function_exists('sl_userIs') || !sl_userIs('client') || defined('SL_PWA_ROLE')) return false;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return false;
    $self = function_exists('sl_self') ? sl_self() : basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (in_array($self, sl_appFrameNever(), true) || preg_match('/^(print_|send_email|download)/', $self)) return false;
    return true;
}

/** Wrap this top-level page load in the app window? Handles ?appview=on|off. */
function sl_appFrameWanted(): bool
{
    $q = (string) ($_GET['appview'] ?? '');
    if ($q === 'off' || $q === 'on') {
        setcookie('sl_appview', $q, ['expires' => time() + 365 * 86400, 'path' => '/', 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
        $_COOKIE['sl_appview'] = $q;
    }
    if (!sl_appFrameable()) return false;
    if (($_COOKIE['sl_appview'] ?? '') === 'off') return false;
    if (($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') !== 'document') return false;
    return ($_COOKIE['sl_vw'] ?? '') === 'wide';
}
