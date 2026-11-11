<?php
require_once CDP_ROOT . '/helpers/sl_view.php';
function sl_pickupCodesData(int $owner): array
{
    if (!sl_tableExists('cdb_pickup_codes') || !sl_tableExists('cdb_pickup_code_items')) return ['codes' => []];
    // Check both code ownership and each remaining parcel's ownership. Consumed,
    // expired and void codes must never appear in the customer wallet.
    $rows = sl_rows("SELECT c.id, c.code, c.module, c.expires_at,
        GROUP_CONCAT(CONCAT(o.order_prefix, o.order_no) ORDER BY o.order_id SEPARATOR ', ') AS parcels
        FROM cdb_pickup_codes c JOIN cdb_pickup_code_items i ON i.code_id = c.id
        JOIN cdb_add_order o ON o.order_id = i.order_id AND i.module = 'air'
        WHERE c.owner_id = :u AND o.sender_id = :owner AND o.is_deleted = 0
        AND c.status IN ('active','verified') AND c.expires_at > NOW() AND i.used_at IS NULL AND o.status_courier NOT IN (8,15,21)
        GROUP BY c.id, c.code, c.module, c.expires_at
        UNION ALL
        SELECT c.id, c.code, c.module, c.expires_at,
        GROUP_CONCAT(CONCAT(o.order_prefix, o.order_no) ORDER BY o.order_id SEPARATOR ', ') AS parcels
        FROM cdb_pickup_codes c JOIN cdb_pickup_code_items i ON i.code_id = c.id
        JOIN cdb_customers_packages o ON o.order_id = i.order_id AND i.module = 'sea'
        WHERE c.owner_id = :s AND o.sender_id = :sender AND c.status IN ('active','verified')
        AND c.expires_at > NOW() AND i.used_at IS NULL AND o.status_courier NOT IN (8,15,21)
        GROUP BY c.id, c.code, c.module, c.expires_at ORDER BY expires_at", [':u' => $owner, ':owner' => $owner, ':s' => $owner, ':sender' => $owner]);
    return ['codes' => $rows];
}
