<?php
// Counter hand-out (HandOut artboard). Legacy tracking search kept at ?legacy=1 (views/pickup_client.php).
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['view_warehouse_delivery', 'warehouse_deliver_package', 'view_shipment_list']);
if (!empty($_GET['legacy'])) { $permissions = $user->cdp_getUserPermissions(); include 'views/pickup_client.php'; exit; }
if (sl_userIs('client')) { header('Location: error403.php'); exit; }
require_once 'views/sl/data/warehouse.php';
// ?tracking= jumps straight to the owner of that ready parcel
if (!empty($_GET['tracking']) && empty($_GET['customer'])) {
    $t = trim((string) $_GET['tracking']);
    $o = sl_scalar("SELECT sender_id FROM cdb_add_order WHERE CONCAT(order_prefix, order_no) = :t AND is_deleted = 0 UNION SELECT sender_id FROM cdb_customers_packages WHERE CONCAT(order_prefix, order_no) = :t2 OR tracking_purchase = :t3", [':t' => $t, ':t2' => $t, ':t3' => $t], 0);
    if ($o) { header('Location: pickup_client.php?customer=' . (int) $o); exit; }
}
sl_render('HandOut', ['id' => 'handout', 'title' => 'Hand out packages', 'board' => 'HandOut', 'styles' => ['assets/swiftlane/pages/ops.css']], sl_handOutData($user, $core));
