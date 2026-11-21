<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************
// Access rules shared by every print_*.php and send_email_pdf*.php controller.
//
// These pages render full documents (names, addresses, phones, charges) for a
// record picked by a sequential id or number in the query string. Logging in is
// not enough: without an ownership check any customer could walk the ids and
// read everyone's invoices and labels. The rule is the one used by
// sl_opsInScope() (views/sl/data/ops_common.php):
//   - customers, drivers and agency users (role rank < 2) only reach records
//     they sent (sender_id), carry (driver_id) or that belong to their agency;
//   - staff tied to an agency (cdp_getAgencyContext() restricted) only reach
//     their agency's records, as on the lists they printed from;
//   - other staff reach everything, as before.

require_once __DIR__ . '/rbac.php';

if (!function_exists('cdp_printLoginRedirect')) {
    /**
     * Send an anonymous caller to the login page and bring them back to this
     * document afterwards (login.php accepts a relative ?next= and validates it).
     */
    function cdp_printLoginRedirect(): void
    {
        $next = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
        if ($next !== '' && $qs !== '') {
            $next .= '?' . $qs;
        }
        header('location: login.php' . ($next !== '' ? '?next=' . rawurlencode($next) : ''));
        exit;
    }
}

if (!function_exists('cdp_printAgencyBranchOf')) {
    /**
     * Branch id an agency user (userlevel 6) is tied to through cdb_users.name_off.
     * The legacy lists scope agency users by this branch (courier_list_ajax.php), not
     * only by cdb_users.agency_id, so a document reachable from those lists must be
     * printable under the same rule (accept/edit_courier_ajax.php accept it too).
     */
    function cdp_printAgencyBranchOf(int $uid): int
    {
        static $cache = [];
        if ($uid <= 0 || !function_exists('cdp_getAgencyBranchIdForUser')) {
            return 0;
        }
        if (!isset($cache[$uid])) {
            $db = new Conexion;
            $db->cdp_query('SELECT name_off FROM cdb_users WHERE id = :id LIMIT 1');
            $db->bind(':id', $uid);
            $db->cdp_execute();
            $u = $db->cdp_registro();
            $cache[$uid] = $u ? (int) cdp_getAgencyBranchIdForUser($u->name_off ?? '') : 0;
        }
        return $cache[$uid];
    }
}

if (!function_exists('cdp_printRowInScope')) {
    /** True when the caller may see this record (needs sender_id, driver_id, agency). */
    function cdp_printRowInScope($row, int $uid, int $userlevel): bool
    {
        if (!$row) {
            return false;
        }
        $agencyCtx = function_exists('cdp_getAgencyContext') ? cdp_getAgencyContext() : ['is_restricted' => false, 'agency_id' => null];
        $agencyMatch = !empty($agencyCtx['is_restricted']) && $agencyCtx['agency_id'] !== null
            && (int) ($row->agency ?? 0) === (int) $agencyCtx['agency_id'];

        if (cdp_roleRankById($userlevel) < 2) {
            return ($uid > 0 && (int) ($row->sender_id ?? 0) === $uid)
                || ($uid > 0 && (int) ($row->driver_id ?? 0) === $uid)
                || $agencyMatch
                || ($userlevel === 6 && (int) ($row->agency ?? 0) > 0
                    && (int) ($row->agency ?? 0) === cdp_printAgencyBranchOf($uid));
        }
        // Staff: only an agency-bound account is narrowed, to its own agency.
        if (!empty($agencyCtx['is_restricted']) && $agencyCtx['agency_id'] !== null) {
            return $agencyMatch;
        }
        return true;
    }
}

if (!function_exists('cdp_printOwnerOr403')) {
    /**
     * Stop with 403 unless every record named by $keys is in the caller's scope.
     *
     * $kind: shipment | package | consolidate | consolidate_package | charge
     * $by:   'id' (primary key, the ?id= pages) or 'no' (order_no / c_no, the
     *        ?data=[...] multiple-print pages). With 'no', a number is checked
     *        only when it matches exactly one row, the only case the views print.
     *        Keys that match no record are left to the view, which shows nothing.
     * Pass each key exactly as the view passes it to its query.
     */
    function cdp_printOwnerOr403(string $kind, array $keys, string $by = 'id', ?int $uid = null, ?int $userlevel = null): void
    {
        // Prefer the values cdp_loginCheck() just read from the database over the
        // session copy, so a role change takes effect at once.
        global $user;
        $loggedIn = isset($user) && is_object($user) && !empty($user->uid);
        $uid = $uid ?? ($loggedIn ? (int) $user->uid : (int) ($_SESSION['userid'] ?? 0));
        $userlevel = $userlevel ?? ($loggedIn ? (int) $user->userlevel : (int) ($_SESSION['userlevel'] ?? 0));

        $tables = [
            'shipment'            => ['cdb_add_order', 'order_id', 'order_no'],
            'package'             => ['cdb_customers_packages', 'order_id', 'order_no'],
            'consolidate'         => ['cdb_consolidate', 'consolidate_id', 'c_no'],
            'consolidate_package' => ['cdb_consolidate_packages', 'consolidate_id', 'c_no'],
        ];

        if ($kind === 'charge') {
            // A charge belongs to the shipment it was paid against.
            $sql = 'SELECT o.sender_id, o.driver_id, o.agency FROM cdb_charges_order c'
                . ' LEFT JOIN cdb_add_order o ON o.order_id = c.order_id WHERE c.id_charge = :k';
            $by = 'id';
        } elseif (isset($tables[$kind])) {
            [$table, $pk, $no] = $tables[$kind];
            $col = $by === 'no' ? $no : $pk;
            $sql = "SELECT sender_id, driver_id, agency FROM $table WHERE $col = :k";
        } else {
            http_response_code(403);
            exit('You do not have access to this document.');
        }

        $db = new Conexion;
        foreach ($keys as $key) {
            if (!is_scalar($key)) {
                // The views cannot use a non-scalar key either; refuse rather than guess.
                http_response_code(403);
                exit('You do not have access to this document.');
            }
            $db->cdp_query($sql);
            // Bind the key exactly as the view will, so both see the same rows.
            $db->bind(':k', $key);
            $db->cdp_execute();
            $rows = $db->cdp_registros();
            if ($by === 'id' && !$rows) {
                // ?id= pages: an unknown id shows nothing anyway; for callers below
                // staff rank keep the original answer (no hint whether it exists).
                if (cdp_roleRankById($userlevel) < 2) {
                    http_response_code(403);
                    exit('You do not have access to this document.');
                }
                continue;
            }
            if ($by === 'no' && count($rows ?: []) !== 1) {
                // The multiple-print views only render a number that matches exactly
                // one row (rowCount !== 1 is skipped), so an ambiguous number prints
                // nothing; refusing the whole batch over it would only hurt the caller.
                continue;
            }
            foreach ($rows ?: [] as $row) {
                if (!cdp_printRowInScope($row, $uid, $userlevel)) {
                    http_response_code(403);
                    exit('You do not have access to this document.');
                }
            }
        }
    }
}

if (!function_exists('cdp_printRequireAppRequest')) {
    /**
     * The send_email_pdf*.php controllers e-mail a document to an address taken from the request,
     * and the screens call them with a jQuery GET. Without this a cross-site link
     * (send_email_pdf.php?id=N&sendto=attacker@x) would make a signed-in user's browser mail that
     * document out. Same rule as require_safe_write() (helpers/ajax_guard.php): a valid CSRF token,
     * or a same-origin XHR (a cross-site link, form or <img> cannot add X-Requested-With, and
     * browsers mark cross-site requests in Sec-Fetch-Site).
     */
    function cdp_printRequireAppRequest(): void
    {
        require_once __DIR__ . '/csrf.php';
        if (cdp_csrf_validate_request()) {
            return;
        }
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $xrw  = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $site = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
        if ($method !== 'GET' || $xrw !== 'xmlhttprequest' || ($site !== '' && $site !== 'same-origin')) {
            http_response_code(403);
            exit('This action must be sent from the application.');
        }
    }
}
