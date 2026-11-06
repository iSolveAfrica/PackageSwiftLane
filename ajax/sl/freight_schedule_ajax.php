<?php
/** Create / update departures, book space, change status (FreightSchedule page). */
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
if (!sl_tableExists('cdb_freight_schedules')) {
    sl_json(['success' => false, 'message' => 'Freight tables are missing: run migrations/2026_11_06_freight_devops.sql'], 409);
}
$db = new Conexion();
$actor = (int) $_SESSION['userid'];
$op = (string) ($_POST['op'] ?? 'create');
$id = (int) ($_POST['id'] ?? 0);
$dt = function ($v): ?string {
    $v = trim((string) $v);
    if ($v === '') return null;
    $t = strtotime($v);
    return $t ? date('Y-m-d H:i:s', $t) : null;
};
$log = function (string $action, string $label, string $summary, int $eid, string $elabel) {
    if (function_exists('cdp_activityLog')) {
        cdp_activityLog(['module' => 'freight', 'action' => $action, 'action_label' => $label, 'verb' => $action === 'create' ? 'create' : 'update', 'outcome' => 'ok', 'summary' => $summary, 'entity_type' => 'freight_schedule', 'entity_id' => $eid, 'entity_label' => $elabel]);
    }
};
switch ($op) {
    case 'create':
    case 'update':
        $errors = [];
        $mode = ($_POST['mode'] ?? 'air') === 'sea' ? 'sea' : 'air';
        $voyage = mb_substr(trim((string) ($_POST['voyage_no'] ?? '')), 0, 60);
        $origin = mb_substr(trim((string) ($_POST['origin'] ?? '')), 0, 80);
        $dest = mb_substr(trim((string) ($_POST['destination'] ?? '')), 0, 80);
        $departs = $dt($_POST['departs_at'] ?? '');
        $cutoff = $dt($_POST['cutoff_at'] ?? '');
        $arrives = $dt($_POST['arrives_at'] ?? '');
        if ($voyage === '') $errors['voyage_no'] = 'Flight or vessel number is required';
        if ($origin === '') $errors['origin'] = 'Required';
        if ($dest === '') $errors['destination'] = 'Required';
        if (!$departs) $errors['departs_at'] = 'Pick a departure date and time';
        if ($cutoff && $departs && $cutoff > $departs) $errors['cutoff_at'] = 'Cut-off must be before departure';
        if ($arrives && $departs && $arrives < $departs) $errors['arrives_at'] = 'Arrival must be after departure';
        $capacity = $_POST['capacity_total'] !== '' ? max(0, (float) $_POST['capacity_total']) : null;
        $unit = in_array($_POST['capacity_unit'] ?? 'pkgs', ['pkgs', 'kg', 'ft3'], true) ? $_POST['capacity_unit'] : 'pkgs';
        $partner = (int) ($_POST['partner_id'] ?? 0) ?: null;
        $carrier = mb_substr(trim((string) ($_POST['carrier_name'] ?? '')), 0, 150);
        if ($partner && sl_tableExists('cdb_freight_partners')) {
            $carrier = (string) sl_scalar('SELECT name FROM cdb_freight_partners WHERE id = :id', [':id' => $partner], $carrier);
        }
        $cons = mb_substr(trim((string) ($_POST['consolidation_ids'] ?? '')), 0, 500);
        $notes = mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 2000);
        if ($errors) {
            sl_json(['success' => false, 'message' => 'Check the form', 'errors' => $errors], 422);
        }
        if ($op === 'create') {
            $db->cdp_query('INSERT INTO cdb_freight_schedules (mode, partner_id, carrier_name, voyage_no, origin, destination, cutoff_at, departs_at, arrives_at, status, capacity_total, capacity_unit, consolidation_ids, notes, created_by, created_at) VALUES (:m, :p, :c, :v, :o, :d, :cu, :de, :ar, :st, :ct, :un, :co, :n, :by, NOW())');
            $db->bind(':st', 'scheduled');
            $db->bind(':by', $actor);
        } else {
            if (!sl_row('SELECT id FROM cdb_freight_schedules WHERE id = :id', [':id' => $id])) {
                sl_json(['success' => false, 'message' => 'Departure not found'], 404);
            }
            $db->cdp_query('UPDATE cdb_freight_schedules SET mode = :m, partner_id = :p, carrier_name = :c, voyage_no = :v, origin = :o, destination = :d, cutoff_at = :cu, departs_at = :de, arrives_at = :ar, capacity_total = :ct, capacity_unit = :un, consolidation_ids = :co, notes = :n, updated_at = NOW() WHERE id = :id');
            $db->bind(':id', $id);
        }
        $db->bind(':m', $mode); $db->bind(':p', $partner); $db->bind(':c', $carrier ?: null); $db->bind(':v', $voyage); $db->bind(':o', $origin); $db->bind(':d', $dest);
        $db->bind(':cu', $cutoff); $db->bind(':de', $departs); $db->bind(':ar', $arrives); $db->bind(':ct', $capacity); $db->bind(':un', $unit); $db->bind(':co', $cons ?: null); $db->bind(':n', $notes ?: null);
        $ok = $db->cdp_execute();
        if (!$ok) {
            sl_json(['success' => false, 'message' => 'Could not save the departure']);
        }
        if ($op === 'create') {
            $id = (int) $db->dbh->lastInsertId();
        }
        $log($op, $op === 'create' ? 'Scheduled departure' : 'Updated departure', ($op === 'create' ? 'scheduled ' : 'updated ') . "$voyage $origin → $dest", $id, $voyage);
        sl_json(['success' => true, 'message' => "$voyage saved", 'redirect' => sl_route('FreightSchedule', ['id' => $id])]);
        break;
    case 'book':
        $row = sl_row('SELECT * FROM cdb_freight_schedules WHERE id = :id', [':id' => $id]);
        if (!$row) sl_json(['success' => false, 'message' => 'Departure not found'], 404);
        $qty = (float) ($_POST['qty'] ?? 0);
        if ($qty <= 0) sl_json(['success' => false, 'message' => 'Enter how much space to book', 'errors' => ['qty' => 'More than zero']], 422);
        $cons = mb_substr(trim((string) ($_POST['consolidation'] ?? '')), 0, 100);
        $newCons = $row->consolidation_ids;
        if ($cons !== '' && stripos((string) $row->consolidation_ids, $cons) === false) {
            $newCons = trim((string) $row->consolidation_ids) === '' ? $cons : $row->consolidation_ids . ', ' . $cons;
        }
        $db->cdp_query('UPDATE cdb_freight_schedules SET capacity_booked = capacity_booked + :q, consolidation_ids = :c, updated_at = NOW() WHERE id = :id');
        $db->bind(':q', $qty); $db->bind(':c', $newCons); $db->bind(':id', $id);
        $ok = $db->cdp_execute();
        if ($ok) $log('book', 'Booked space', "booked $qty {$row->capacity_unit} on {$row->voyage_no}" . ($cons ? " for $cons" : ''), $id, $row->voyage_no);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? "Booked $qty " . $row->capacity_unit . ' on ' . $row->voyage_no : 'Could not book', 'reload' => true]);
        break;
    case 'status':
        $row = sl_row('SELECT * FROM cdb_freight_schedules WHERE id = :id', [':id' => $id]);
        if (!$row) sl_json(['success' => false, 'message' => 'Departure not found'], 404);
        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, ['scheduled', 'boarding', 'in_transit', 'arrived', 'delayed', 'cancelled'], true)) {
            sl_json(['success' => false, 'message' => 'Unknown status'], 422);
        }
        $db->cdp_query('UPDATE cdb_freight_schedules SET status = :s, arrived_at = CASE WHEN :s2 = \'arrived\' THEN COALESCE(arrived_at, NOW()) ELSE arrived_at END, updated_at = NOW() WHERE id = :id');
        $db->bind(':s', $status); $db->bind(':s2', $status); $db->bind(':id', $id);
        $ok = $db->cdp_execute();
        if ($ok) $log('status', 'Departure status', "{$row->voyage_no} marked $status", $id, $row->voyage_no);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? $row->voyage_no . ' marked ' . str_replace('_', ' ', $status) : 'Could not update', 'reload' => true]);
        break;
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
