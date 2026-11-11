<?php
/**
 * Consolidation writes (air: cdb_consolidate, sea: cdb_consolidate_packages) for the
 * Consolidations / ConsolidationDetail / NewConsolidation pages. Mirrors views/consolidate/consolidate_add.php and views/consolidate_packages/consolidate_add.php
 * (head insert, detail rows, is_consolidate flag, cdp_notifyConsolidationPackageSenders, ETA, files)
 * and reuses cdp_deleteConsolidate(+Packages), cdp_updateConsolidateDelete(+Packages), cdp_updateDriverConsolidate(+Packages).
 *
 * POST op=create | add_items | remove_item | delete | driver ; type=air|sea
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/sl_auth.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../helpers/unique_ids.php';
require_once CDP_ROOT . '/views/sl/data/consolidations.php';
$user = new User; $core = new Core;
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
if (CDP_APP_MODE_DEMO === true) sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
if (sl_userIs('client')) sl_json(['success' => false, 'message' => 'Not allowed'], 403);
$op = (string) ($_POST['op'] ?? '');
$M = sl_conMode((string) ($_POST['type'] ?? 'air'));
$uid = (int) $_SESSION['userid'];

function slc_head(array $M, int $id): object
{
    $c = sl_row("SELECT * FROM {$M['head']} WHERE consolidate_id = :id", [':id' => $id]);
    if (!$c || !sl_opsInScope($c)) sl_json(['success' => false, 'message' => 'Consolidation not found'], 404);
    return $c;
}
/** Open packages of this mode, in scope, not in a consolidation; keyed by order_id. */
function slc_pick(array $M, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return [];
    $rows = sl_rows("SELECT a.*, (SELECT COALESCE(SUM(i.order_item_length),0) FROM {$M['items']} i WHERE i.order_id = a.order_id) AS l, (SELECT COALESCE(SUM(i.order_item_width),0) FROM {$M['items']} i WHERE i.order_id = a.order_id) AS wd, (SELECT COALESCE(SUM(i.order_item_height),0) FROM {$M['items']} i WHERE i.order_id = a.order_id) AS ht FROM {$M['orders']} a WHERE a.order_id IN (" . implode(',', $ids) . ") AND a.is_consolidate = 0 AND a.status_courier NOT IN (8,12,14,15,21)" . ($M['sea'] ? '' : ' AND a.is_deleted = 0'));
    $out = [];
    foreach ($rows as $r) if (sl_opsInScope($r)) $out[(int) $r->order_id] = $r;
    return $out;
}
function slc_addDetails(array $M, int $cid, array $pkgs, $core): float
{
    $db = new Conexion;
    $meter = (float) ($core->meter ?: 500);
    $w = 0;
    foreach ($pkgs as $p) {
        $vol = (float) $p->l * (float) $p->wd * (float) $p->ht / max(1, $meter);
        $db->cdp_query("INSERT INTO {$M['detail']} (consolidate_id, order_id, order_prefix, order_no, weight, length, width, height, weight_vol) VALUES (:c, :o, :p, :n, :w, :l, :wd, :h, :v)");
        $db->bind(':c', $cid); $db->bind(':o', (int) $p->order_id); $db->bind(':p', $p->order_prefix); $db->bind(':n', $p->order_no);
        $db->bind(':w', (string) (float) $p->total_weight); $db->bind(':l', (string) (float) $p->l); $db->bind(':wd', (string) (float) $p->wd); $db->bind(':h', (string) (float) $p->ht); $db->bind(':v', (string) round($vol, 2));
        $db->cdp_execute();
        $db->cdp_query("UPDATE {$M['orders']} SET is_consolidate = 1 WHERE order_id = :id");
        $db->bind(':id', (int) $p->order_id);
        $db->cdp_execute();
        $w += (float) $p->total_weight;
    }
    return $w;
}
function slc_recalcWeight(array $M, int $cid): void
{
    $db = new Conexion;
    $db->cdp_query("UPDATE {$M['head']} SET total_weight = (SELECT COALESCE(SUM(CAST(weight AS DECIMAL(12,2))),0) FROM {$M['detail']} WHERE consolidate_id = :c) WHERE consolidate_id = :c2");
    $db->bind(':c', $cid); $db->bind(':c2', $cid);
    $db->cdp_execute();
}
function slc_notify(array $M, int $cid, string $code, int $status, ?array $only = null): void
{
    try {
        require_once CDP_ROOT . '/helpers/whatsapp.php';
        if (function_exists('cdp_notifyConsolidationPackageSenders')) {
            cdp_notifyConsolidationPackageSenders($M['module'], $cid, $code, cdp_wa_lookupName('cdb_styles', 'mod_style', $status), $only);
        }
    } catch (Throwable $e) {
        error_log('sl consolidation notify: ' . $e->getMessage());
    }
}

switch ($op) {
    case 'create':
        require_permission($M['p']['add']);
        $pkgs = slc_pick($M, explode(',', (string) ($_POST['ids'] ?? '')));
        if (!$pkgs) sl_json(['success' => false, 'message' => 'Pick at least one open package'], 422);
        $dg = 0;
        if (!$M['sea'] && sl_columnExists('cdb_add_order', 'is_dangerous_good')) {
            $kinds = array_unique(array_map(fn($p) => (int) ($p->is_dangerous_good ?? 0), $pkgs));
            if (count($kinds) > 1) sl_json(['success' => false, 'message' => 'A consolidation groups either dangerous goods or normal goods, never both.'], 422);
            $dg = (int) reset($kinds);
        }
        $prefix = mb_substr(trim(cdp_sanitize($_POST['code_prefix'] ?? '')), 0, 20) ?: ($M['sea'] ? 'SLC-' : 'AIR-');
        $no = cdp_uidClaim('consolidate_no', (string) ($_POST['order_no'] ?? ''));
        $date = date('Y-m-d', strtotime((string) ($_POST['order_date'] ?? 'now')) ?: time());
        $status = (int) ($_POST['status_courier'] ?? 13) ?: 13;
        if (cdp_isHandedOver($status) || $status === 21) $status = 13;
        // totals: the same arithmetic as the legacy create page, from the packages picked
        $w = array_sum(array_map(fn($p) => (float) $p->total_weight, $pkgs));
        $rate = (float) ($core->value_weight ?? 0); $taxP = 0; $insP = (float) ($core->insurance ?? 0);
        $discP = max(0, min(100, (float) ($_POST['discount_value'] ?? 0))); $insured = max(0, (float) ($_POST['insured_value'] ?? 0)); $dutyP = 0; $reex = max(0, (float) ($_POST['reexpedicion_value'] ?? 0));
        $sub = $w * $rate; $disc = $sub * $discP / 100; $ins = sl_insuranceCharge((float) $insured, (float) $insP); $duty = 0; $tax = 0;
        $total = $sub - $disc + $ins + $duty + $tax + $reex;
        $awbCol = cdp_awbColumnReady();
        $cols = ['user_id' => $uid, 'c_prefix' => $prefix, 'c_no' => $no, 'c_date' => $date, 'sender_id' => $uid, 'sender_address_id' => 0, 'receiver_id' => 0, 'receiver_address_id' => 0,
            'tax_value' => $taxP, 'tax_insurance_value' => $insP, 'total_insured_value' => $insured, 'tax_custom_tariffis_value' => $dutyP, 'value_weight' => $rate, 'volumetric_percentage' => (string) ($core->meter ?: 500),
            'total_weight' => $w, 'sub_total' => $sub, 'tax_discount' => $discP, 'total_tax_insurance' => $ins, 'total_tax_custom_tariffis' => $duty, 'total_tax' => $tax, 'total_tax_discount' => $disc, 'total_reexp' => $reex, 'total_order' => $total,
            'order_datetime' => $date . ' ' . date('H:i:s'), 'agency' => (string) (int) ($_POST['agency'] ?? 0), 'origin_off' => (string) (int) ($_POST['origin_off'] ?? 0), 'order_package' => (string) (int) ($_POST['order_package'] ?? 0),
            'order_item_category' => (string) (int) (cdp_getInfoShipDefault()->logistics_default1 ?? 0), 'order_courier' => (string) (int) ($_POST['order_courier'] ?? 0), 'order_service_options' => (string) (int) ($_POST['order_service_options'] ?? 0),
            'order_deli_time' => (string) (int) ($_POST['order_deli_time'] ?? 0), 'order_pay_mode' => (string) (int) ($_POST['order_pay_mode'] ?? 0), 'status_courier' => (string) $status, 'driver_id' => (int) ($_POST['driver_id'] ?? 0),
            'seals_package' => mb_substr(cdp_sanitize($_POST['seals'] ?? ''), 0, 300), 'status_invoice' => 2, 'recipient_type' => 'recipient'];
        if ($awbCol) $cols['awb_no'] = cdp_awbNormalize($_POST['awb_no'] ?? '');
        if (sl_columnExists($M['head'], 'is_dangerous_good')) $cols['is_dangerous_good'] = $dg;
        $db = new Conexion;
        $db->cdp_query("INSERT INTO {$M['head']} (" . implode(', ', array_keys($cols)) . ') VALUES (:' . implode(', :', array_keys($cols)) . ')');
        foreach ($cols as $k => $v) $db->bind(':' . $k, $v);
        if (!$db->cdp_execute()) sl_json(['success' => false, 'message' => 'The consolidation could not be saved'], 500);
        $cid = (int) $db->dbh->lastInsertId();
        slc_addDetails($M, $cid, $pkgs, $core);
        if (!empty($_POST['estimated_eta'])) cdp_setConsolidationEta($cid, cdp_sanitize($_POST['estimated_eta']), $M['sea']);
        $code = $prefix . $no;
        cdp_insertCourierShipmentTrack(['user_id' => $uid, 'order_id' => $cid, 'order_track' => $code, 't_date' => date('Y-m-d H:i:s'), 'status_courier' => $status, 'comments' => 'Consolidation created with ' . count($pkgs) . ' package(s)', 'office' => (int) ($_POST['origin_off'] ?? 0)]);
        if (!empty($_FILES['files']['name'][0])) {
            foreach ($_FILES['files']['tmp_name'] as $k => $tmp) {
                $ext = strtolower(pathinfo((string) $_FILES['files']['name'][$k], PATHINFO_EXTENSION));
                if (!is_uploaded_file($tmp) || !in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true) || $_FILES['files']['size'][$k] > 5242880) continue;
                $name = preg_replace('/[^A-Za-z0-9_.-]/', '_', $code . '_' . date('Ymd_His') . '_' . $k . '.' . $ext);
                if (move_uploaded_file($tmp, CDP_ROOT . '/order_files/' . $name)) cdp_insertOrdersFiles($cid, 'order_files/' . $name, $name, date('Y-m-d H:i:s'), '1', $ext);
            }
        }
        slc_notify($M, $cid, $code, $status);
        cdp_activityLog(['module' => 'consolidations', 'action' => 'create', 'action_label' => 'Created consolidation', 'verb' => 'create', 'outcome' => 'ok', 'summary' => 'Created ' . ($M['sea'] ? 'sea' : 'air') . ' consolidation ' . $code . ' with ' . count($pkgs) . ' package(s)', 'entity_type' => $M['module'], 'entity_id' => $cid, 'entity_label' => $code, 'status_id' => $status]);
        sl_json(['success' => true, 'message' => 'Consolidation ' . $code . ' created', 'redirect' => sl_route('ConsolidationDetail', ['id' => $cid, 'type' => $M['mode'], 'created' => 1])]);
        break;

    case 'add_items':
        require_permission($M['p']['edit']);
        $c = slc_head($M, (int) ($_POST['id'] ?? 0));
        if (cdp_isHandedOver($c->status_courier) || (int) $c->status_courier === 21) sl_json(['success' => false, 'message' => 'This consolidation is closed'], 409);
        $pkgs = slc_pick($M, explode(',', (string) ($_POST['ids'] ?? '')));
        if (!$pkgs) sl_json(['success' => false, 'message' => 'Pick at least one open package'], 422);
        if (!$M['sea'] && sl_columnExists('cdb_add_order', 'is_dangerous_good')) {
            foreach ($pkgs as $p) if ((int) ($p->is_dangerous_good ?? 0) !== (int) ($c->is_dangerous_good ?? 0)) sl_json(['success' => false, 'message' => 'Dangerous goods and normal goods cannot share a consolidation.'], 422);
        }
        slc_addDetails($M, (int) $c->consolidate_id, $pkgs, $core);
        slc_recalcWeight($M, (int) $c->consolidate_id);
        slc_notify($M, (int) $c->consolidate_id, $c->c_prefix . $c->c_no, (int) $c->status_courier, array_keys($pkgs));
        cdp_activityLog(['module' => 'consolidations', 'action' => 'add_items', 'action_label' => 'Added packages', 'verb' => 'update', 'outcome' => 'ok', 'summary' => count($pkgs) . ' package(s) added to ' . $c->c_prefix . $c->c_no, 'entity_type' => $M['module'], 'entity_id' => (int) $c->consolidate_id, 'entity_label' => $c->c_prefix . $c->c_no]);
        sl_json(['success' => true, 'message' => count($pkgs) . ' package(s) added']);
        break;

    case 'remove_item':
        require_permission($M['p']['edit']);
        $c = slc_head($M, (int) ($_POST['id'] ?? 0));
        $oid = (int) ($_POST['order'] ?? 0);
        $row = sl_row("SELECT d.detail_id, a.status_courier, a.order_prefix, a.order_no FROM {$M['detail']} d LEFT JOIN {$M['orders']} a ON a.order_id = d.order_id WHERE d.consolidate_id = :c AND d.order_id = :o", [':c' => (int) $c->consolidate_id, ':o' => $oid]);
        if (!$row) sl_json(['success' => false, 'message' => 'That package is not in this consolidation'], 404);
        if (cdp_isHandedOver($row->status_courier)) sl_json(['success' => false, 'message' => 'A handed-over package stays on the packing list'], 409);
        $db = new Conexion;
        $db->cdp_query("DELETE FROM {$M['detail']} WHERE consolidate_id = :c AND order_id = :o"); $db->bind(':c', (int) $c->consolidate_id); $db->bind(':o', $oid); $db->cdp_execute();
        $db->cdp_query("UPDATE {$M['orders']} SET is_consolidate = 0 WHERE order_id = :o"); $db->bind(':o', $oid); $db->cdp_execute();
        slc_recalcWeight($M, (int) $c->consolidate_id);
        cdp_activityLog(['module' => 'consolidations', 'action' => 'remove_item', 'action_label' => 'Removed package', 'verb' => 'update', 'outcome' => 'ok', 'summary' => $row->order_prefix . $row->order_no . ' taken out of ' . $c->c_prefix . $c->c_no, 'entity_type' => $M['module'], 'entity_id' => (int) $c->consolidate_id, 'entity_label' => $c->c_prefix . $c->c_no]);
        sl_json(['success' => true, 'message' => $row->order_prefix . $row->order_no . ' removed']);
        break;

    case 'delete':
        require_permission($M['p']['delete']);
        $c = slc_head($M, (int) ($_POST['id'] ?? 0));
        if (cdp_isHandedOver($c->status_courier)) sl_json(['success' => false, 'message' => 'A handed-over consolidation cannot be cancelled'], 409);
        if ($M['sea']) {
            cdp_deleteConsolidatePackages(['id' => (int) $c->consolidate_id]);
            foreach (cdp_getItemdeleteConsolidatePackages(['id' => (int) $c->consolidate_id]) ?: [] as $k) {
                $db = new Conexion; $db->cdp_query('UPDATE cdb_customers_packages SET is_consolidate = 0 WHERE order_id = :id'); $db->bind(':id', (int) $k->order_id); $db->cdp_execute();
            }
        } else {
            cdp_deleteConsolidate(['id' => (int) $c->consolidate_id]);
            foreach (cdp_getItemdeleteConsolidate(['id' => (int) $c->consolidate_id]) ?: [] as $k) cdp_updateConsolidateDelete((int) $k->order_id);
        }
        cdp_activityLog(['module' => 'consolidations', 'action' => 'cancel', 'action_label' => 'Cancelled consolidation', 'verb' => 'update', 'outcome' => 'ok', 'summary' => 'Cancelled ' . $c->c_prefix . $c->c_no, 'entity_type' => $M['module'], 'entity_id' => (int) $c->consolidate_id, 'entity_label' => $c->c_prefix . $c->c_no, 'status_id' => 21]);
        sl_json(['success' => true, 'message' => $c->c_prefix . $c->c_no . ' cancelled', 'redirect' => sl_route('Consolidations', ['mode' => $M['mode']])]);
        break;

    case 'driver':
        require_permission($M['p']['driver']);
        $c = slc_head($M, (int) ($_POST['id'] ?? 0));
        $drv = (int) ($_POST['driver_id'] ?? 0);
        if (!sl_row('SELECT id FROM cdb_users WHERE id = :id AND userlevel = 3', [':id' => $drv])) sl_json(['success' => false, 'message' => 'Pick a driver', 'errors' => ['driver_id' => 'Required']], 422);
        $M['sea'] ? cdp_updateDriverConsolidatePackages(['id_shipment' => (int) $c->consolidate_id, 'driver_id' => $drv]) : cdp_updateDriverConsolidate(['id_shipment' => (int) $c->consolidate_id, 'driver_id' => $drv]);
        cdp_activityLog(['module' => 'consolidations', 'action' => 'assign_driver', 'action_label' => 'Assigned driver', 'verb' => 'update', 'outcome' => 'ok', 'summary' => 'Driver #' . $drv . ' assigned to ' . $c->c_prefix . $c->c_no, 'entity_type' => $M['module'], 'entity_id' => (int) $c->consolidate_id, 'entity_label' => $c->c_prefix . $c->c_no]);
        sl_json(['success' => true, 'message' => 'Driver assigned']);
        break;

    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
