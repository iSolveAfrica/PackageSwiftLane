<?php
/**
 * Hand Out Packages (views/sl/pages/HandOut.php, dataJs/sl/handout.js) live lookups. GET, read-only.
 *   op=search&q=              customers with ready parcels matching name / locker / phone / tracking / carrier tracking
 *                             -> {rows, total, open}; open = customer to open straight away (exact tracking hit or single match)
 *   op=panel&customer=        HTML of the customer panel (views/sl/pages/partials/handout_panel.php)
 *   op=package&module=&id=    one package for the pop-up: items, payment, status, history
 * Writes stay in ajax/sl/warehouse_ajax.php (verify_customer, send_customer, handover).
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/handover_log.php';
require_once CDP_ROOT . '/views/sl/data/warehouse.php';
$user = new User; $core = new Core;
require_login();
require_permission(['view_warehouse_delivery', 'warehouse_deliver_package', 'view_shipment_list']);
if (sl_userIs('client')) sl_json(['success' => false, 'message' => 'Not allowed'], 403);
$op = (string) ($_GET['op'] ?? '');

switch ($op) {
    case 'search':
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
        sl_json(['success' => true] + sl_hoSearch($q));
        break;

    case 'panel':
        $c = sl_whCustomer((int) ($_GET['customer'] ?? 0));
        if (!$c) sl_json(['success' => false, 'message' => 'This customer has no parcel ready.'], 404);
        $d = sl_handOutFlags($user);
        ob_start();
        include CDP_ROOT . '/views/sl/pages/partials/handout_panel.php';
        sl_json(['success' => true, 'html' => ob_get_clean(), 'customer' => $c['id'], 'name' => $c['name'], 'verified' => $c['verifiedItems']]);
        break;

    case 'package':
        $p = sl_hoPackage(($_GET['module'] ?? '') === 'sea' ? 'sea' : 'air', (int) ($_GET['id'] ?? 0));
        if (!$p) sl_json(['success' => false, 'message' => 'Package not found'], 404);
        sl_json(['success' => true, 'package' => $p]);
        break;

    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
