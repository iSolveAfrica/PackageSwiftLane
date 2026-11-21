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
                || $agencyMatch;
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
     *        ?data=[...] multiple-print pages). With 'no', every row carrying
     *        that number is checked. Keys that match no record are left to the
     *        view, which already shows nothing for them.
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
            foreach ($rows ?: [] as $row) {
                if (!cdp_printRowInScope($row, $uid, $userlevel)) {
                    http_response_code(403);
                    exit('You do not have access to this document.');
                }
            }
        }
    }
}
