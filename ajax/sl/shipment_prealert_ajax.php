<?php
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/querys.php';
require_once __DIR__ . '/../../helpers/rbac.php';
require_login();
require_permission('add_shipment');
$tracking = trim((string) ($_GET['tracking'] ?? ''));
$ctx = cdp_getAgencyContext();
$where = '';
$bind = [':tracking' => $tracking];
if ($ctx['is_restricted']) {
    $where = ' AND u.agency_id = :agency';
    $bind[':agency'] = (int) ($ctx['agency_id'] ?? -1);
}
if (cdp_roleIsClient((int) ($_SESSION['userlevel'] ?? 0))) {
    $where .= ' AND p.customer_id = :customer';
    $bind[':customer'] = (int) $_SESSION['userid'];
}
require_once __DIR__ . '/../../helpers/prealert_receive.php';
cdp_preAlertEnsureColumns();
$sql = 'SELECT p.pre_alert_id AS id, p.tracking, p.customer_id, p.courier_com AS courier_id, p.package_description AS description, p.purchase_price AS price, p.provider_shop AS shop, p.estimated_date AS eta, p.prealert_date, p.is_package, p.received_at, p.received_by, p.received_module, p.received_order_id, c.name_com AS courier, u.fname, u.lname, u.locker
    FROM cdb_pre_alert p JOIN cdb_users u ON u.id = p.customer_id LEFT JOIN cdb_courier_com c ON c.id = p.courier_com WHERE REPLACE(UPPER(p.tracking), \' \', \'\') = :tracking' . $where;
$bind[':tracking'] = strtoupper(preg_replace('/\s+/', '', $tracking));
$p = $tracking === '' ? null : sl_row($sql . ' AND p.is_package = 0 ORDER BY p.pre_alert_id DESC LIMIT 1', $bind);
// an already received pre-alert is reported (not linked) so staff see it was keyed in before
$received = (!$p && $tracking !== '') ? sl_row($sql . ' AND p.is_package = 1 ORDER BY p.pre_alert_id DESC LIMIT 1', $bind) : null;
$out = function ($r) {
    if (!$r) return null;
    return ['id' => (int) $r->id, 'tracking' => $r->tracking, 'customer_id' => (int) $r->customer_id, 'customer' => trim($r->fname . ' ' . $r->lname), 'locker' => (string) $r->locker,
        'courier_id' => (int) $r->courier_id, 'courier' => (string) ($r->courier ?? ''), 'desc' => (string) $r->description, 'description' => (string) $r->description, 'price' => (float) $r->price,
        'shop' => (string) $r->shop, 'eta' => $r->eta && $r->eta !== '0000-00-00' ? (string) $r->eta : '', 'sent' => $r->prealert_date ? date('M j, Y', strtotime($r->prealert_date)) : '',
        'receipt' => cdp_preAlertReceipt($r)];
};
sl_json(['success' => true, 'prealert' => $out($p), 'received' => $out($received)]);
