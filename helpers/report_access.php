<?php
/**
 * Who may download or print the legacy report exports (report_*_excel.php, report_*_print.php,
 * top_users_*). These pages used to check only that someone was logged in, so any customer could
 * download company-wide shipments, payments and customer balances. Now: a staff, agency or admin
 * account (never a customer or rider) holding the report permission. A logged-in user without it
 * gets 403 instead of the login redirect, so there is no redirect loop.
 */
require_once __DIR__ . '/rbac.php';

function cdp_reportExportAllowed($user, string $page): bool
{
    if (!$user || empty($user->logged_in)) return false;
    $user->cdp_getUserPermissions();
    $rank = cdp_roleRankById((int) $user->userlevel);
    if ($rank < 1 || (function_exists('cdp_roleHasFlag') && cdp_roleHasFlag((int) $user->userlevel, 'is_driver')) || (int) $user->userlevel === 3) {
        return false;
    }
    if (str_starts_with($page, 'top_users_air')) $need = ['top_users_air_print', 'view_top_users_air', 'view_general_reports'];
    elseif (str_starts_with($page, 'top_users_sea')) $need = ['top_users_sea_print', 'view_top_users_sea', 'view_general_reports'];
    else $need = ['view_general_reports'];
    return $user->cdp_hasPermission($need);
}

/** Stop a logged-in user who may not export reports. */
function cdp_reportExportGuard($user, string $page): void
{
    if ($user->cdp_loginCheck() == true && !cdp_reportExportAllowed($user, $page)) {
        http_response_code(403);
        exit('You do not have access to this report.');
    }
}
