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
$p = $tracking === '' ? null : sl_row('SELECT p.pre_alert_id AS id, p.tracking, p.customer_id, p.courier_com AS courier_id, p.package_description AS description, p.purchase_price AS price, u.fname, u.lname, u.locker FROM cdb_pre_alert p JOIN cdb_users u ON u.id = p.customer_id WHERE p.is_package = 0 AND p.tracking = :tracking' . $where . ' ORDER BY p.pre_alert_id DESC LIMIT 1', $bind);
if ($p) { $p->customer = trim($p->fname . ' ' . $p->lname); $p->desc = $p->description; }
sl_json(['success' => true, 'prealert' => $p]);
