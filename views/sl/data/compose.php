<?php
/** Compose message: segments and recipients shared by the endpoint and the page. */
require_once CDP_ROOT . '/helpers/sl_view.php';

function sl_composeSegments(): array
{
    $q90 = "SELECT sender_id FROM cdb_add_order WHERE is_deleted = 0 AND order_date >= CURDATE() - INTERVAL 90 DAY";
    return [
        'all'    => ['label' => 'All active customers', 'sql' => "SELECT id FROM cdb_users WHERE userlevel = 1 AND active = 1"],
        'active' => ['label' => 'Shipped in the last 90 days', 'sql' => "SELECT id FROM cdb_users WHERE userlevel = 1 AND active = 1 AND id IN ($q90)"],
        'quiet'  => ['label' => 'Quiet 90 days (win-back)', 'sql' => "SELECT id FROM cdb_users WHERE userlevel = 1 AND active = 1 AND id NOT IN ($q90)"],
        'due'    => ['label' => 'Customers with overdue bills', 'sql' => "SELECT id FROM cdb_users WHERE userlevel = 1 AND active = 1 AND id IN (SELECT sender_id FROM cdb_add_order WHERE is_deleted = 0 AND status_invoice = 23 AND status_courier <> 21)"],
        'ready'  => ['label' => 'Parcels ready for pickup', 'sql' => "SELECT id FROM cdb_users WHERE userlevel = 1 AND active = 1 AND id IN (SELECT sender_id FROM cdb_add_order WHERE is_deleted = 0 AND status_courier = 32)"],
        'new'    => ['label' => 'Joined this month', 'sql' => "SELECT id FROM cdb_users WHERE userlevel = 1 AND active = 1 AND created >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"],
    ];
}

/** Recipient ids from customer ids, a segment and a pasted list of e-mails/phones. Agency-scoped. */
function sl_composeRecipients(array $ids, string $segment, string $list): array
{
    $out = [];
    $ctx = function_exists('cdp_getAgencyContext') ? cdp_getAgencyContext() : [];
    $scope = !empty($ctx['is_restricted']) && !empty($ctx['agency_id']) ? ' AND agency_id = ' . (int) $ctx['agency_id'] : '';
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if ($ids) {
        foreach (sl_rows('SELECT id FROM cdb_users WHERE userlevel = 1 AND active = 1 AND id IN (' . implode(',', $ids) . ")$scope") as $r) $out[] = (int) $r->id;
    }
    $segs = sl_composeSegments();
    if (isset($segs[$segment])) {
        foreach (sl_rows($segs[$segment]['sql'] . $scope) as $r) $out[] = (int) $r->id;
    }
    foreach (preg_split('/[\s,;]+/', $list) ?: [] as $tok) {
        $tok = trim($tok);
        if ($tok === '') continue;
        $digits = preg_replace('/\D/', '', $tok);
        $r = strpos($tok, '@') !== false
            ? sl_row("SELECT id FROM cdb_users WHERE active = 1 AND email = :e$scope", [':e' => $tok])
            : (strlen($digits) >= 7 ? sl_row("SELECT id FROM cdb_users WHERE active = 1 AND REPLACE(REPLACE(REPLACE(phone,'+',''),' ',''),'-','') LIKE :p$scope", [':p' => '%' . substr($digits, -9)]) : null);
        if ($r) $out[] = (int) $r->id;
    }
    return array_values(array_unique($out));
}
