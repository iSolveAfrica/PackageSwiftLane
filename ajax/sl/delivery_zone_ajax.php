<?php
/** Delivery zones (LastMile page): create / update / archive, and assign unassigned deliveries to each zone's rider by recipient city. */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
if (CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
require_permission('manage_freight');
if (!sl_tableExists('cdb_delivery_zones')) {
    sl_json(['success' => false, 'message' => 'Zones table is missing: run migrations/2026_11_06_freight_devops.sql'], 409);
}
require_once __DIR__ . '/../../views/sl/data/lastmile.php';
$db = new Conexion();
$actor = (int) $_SESSION['userid'];
$op = (string) ($_POST['op'] ?? 'save');
$id = (int) ($_POST['id'] ?? 0);
$log = function (string $action, string $label, string $summary, $eid, string $elabel, array $meta = []) {
    if (function_exists('cdp_activityLog')) {
        cdp_activityLog(['module' => 'last_mile', 'action' => $action, 'action_label' => $label, 'verb' => $action === 'zone_create' ? 'create' : 'update', 'outcome' => 'ok', 'summary' => $summary, 'entity_type' => 'delivery_zone', 'entity_id' => $eid, 'entity_label' => $elabel, 'meta' => $meta]);
    }
};
switch ($op) {
    case 'save':
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 100);
        $match = mb_substr(trim((string) ($_POST['city_match'] ?? '')), 0, 500);
        $errors = [];
        if ($name === '') $errors['name'] = 'Zone name is required';
        if ($match === '') $errors['city_match'] = 'List at least one city';
        $rider = (int) ($_POST['rider_id'] ?? 0) ?: null;
        if ($rider && !sl_row('SELECT id FROM cdb_users WHERE id = :u AND userlevel = 3', [':u' => $rider])) $errors['rider_id'] = 'Not a rider';
        if ($errors) sl_json(['success' => false, 'message' => 'Check the form', 'errors' => $errors], 422);
        if ($id) {
            $db->cdp_query('UPDATE cdb_delivery_zones SET name = :n, city_match = :m, fee = :f, rider_id = :r, updated_at = NOW() WHERE id = :id');
            $db->bind(':id', $id);
        } else {
            $db->cdp_query('INSERT INTO cdb_delivery_zones (name, city_match, fee, rider_id, active, created_by, created_at) VALUES (:n, :m, :f, :r, 1, :by, NOW())');
            $db->bind(':by', $actor);
        }
        $db->bind(':n', $name); $db->bind(':m', $match); $db->bind(':f', max(0, (float) ($_POST['fee'] ?? 0))); $db->bind(':r', $rider);
        if (!$db->cdp_execute()) sl_json(['success' => false, 'message' => 'Could not save the zone']);
        $isNew = !$id;
        $id = $id ?: (int) $db->dbh->lastInsertId();
        $log($isNew ? 'zone_create' : 'zone_update', 'Saved delivery zone', "zone $name: $match", $id, $name);
        sl_json(['success' => true, 'message' => "Zone $name saved", 'reload' => true]);
        break;
    case 'archive':
        $z = sl_row('SELECT name FROM cdb_delivery_zones WHERE id = :id', [':id' => $id]);
        if (!$z) sl_json(['success' => false, 'message' => 'Zone not found'], 404);
        $db->cdp_query('UPDATE cdb_delivery_zones SET active = 0, updated_at = NOW() WHERE id = :id');
        $db->bind(':id', $id);
        $ok = $db->cdp_execute();
        if ($ok) $log('zone_archive', 'Archived delivery zone', "archived zone {$z->name}", $id, $z->name);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Zone removed' : 'Could not remove', 'reload' => true]);
        break;
    case 'assign':
        require_once CDP_ROOT . '/views/sl/data/riders.php';
        $activeRiders=sl_rdRiders(true);
        $zones = sl_lmZones();
        $ctx = function_exists('cdp_getAgencyContext') ? cdp_getAgencyContext() : ['is_restricted' => false];
        $scope = !empty($ctx['is_restricted']) && !empty($ctx['agency_id']) ? ' AND o.agency = ' . (int) $ctx['agency_id'] : '';
        if (sl_tableExists('cdb_delivery_claims')) $scope .= " AND NOT EXISTS (SELECT 1 FROM cdb_delivery_claims dc WHERE dc.module='air' AND dc.order_id=o.order_id)";
        $n = 0;
        $per = [];
        foreach (sl_rows("SELECT o.order_id, o.order_no, a.recipient_city AS city FROM cdb_add_order o LEFT JOIN cdb_address_shipments a ON a.order_id = o.order_id WHERE o.is_deleted = 0 AND o.status_courier IN (5,6,7) AND COALESCE(o.driver_id,0) = 0 $scope LIMIT 1000") as $r) {
            $z = sl_lmZoneFor((string) $r->city, $zones);
            if (!$z || !$z['rider'] || !isset($activeRiders[$z['rider']])) continue;
            $db->cdp_query('UPDATE cdb_add_order SET driver_id = :d WHERE order_id = :o AND COALESCE(driver_id,0) = 0');
            $db->bind(':d', $z['rider']); $db->bind(':o', (int) $r->order_id);
            if ($db->cdp_execute()) {
                $n++;
                $per[$z['name']] = ($per[$z['name']] ?? 0) + 1;
            }
        }
        $log('zone_assign', 'Assigned riders by zone', "$n deliveries assigned by recipient city", '', $n . ' deliveries', ['per_zone' => $per]);
        sl_json(['success' => true, 'message' => $n ? "$n deliveries assigned: " . implode(', ', array_map(fn($k, $v) => "$k $v", array_keys($per), $per)) : 'Nothing to assign: every matching delivery already has a rider', 'reload' => true]);
        break;
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
