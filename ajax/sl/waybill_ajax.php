<?php
/** Master waybills: create, add house bills from shipments / consolidations, issue, mark sent, plan the container load. */
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
if (!sl_tableExists('cdb_waybills') || !sl_tableExists('cdb_waybill_items')) {
    sl_json(['success' => false, 'message' => 'Waybill tables are missing: run migrations/2026_11_06_freight_devops.sql'], 409);
}
$db = new Conexion();
$actor = (int) $_SESSION['userid'];
$op = (string) ($_POST['op'] ?? 'create');
$id = (int) ($_POST['id'] ?? 0);
$log = function (string $action, string $label, string $summary, int $eid, string $elabel, array $meta = []) {
    if (function_exists('cdp_activityLog')) {
        cdp_activityLog(['module' => 'freight', 'action' => 'waybill_' . $action, 'action_label' => $label, 'verb' => $action === 'create' ? 'create' : 'update', 'outcome' => 'ok', 'summary' => $summary, 'entity_type' => 'waybill', 'entity_id' => $eid, 'entity_label' => $elabel, 'meta' => $meta]);
    }
};
$load = function (int $id) {
    $w = sl_row('SELECT * FROM cdb_waybills WHERE id = :id', [':id' => $id]);
    if (!$w) sl_json(['success' => false, 'message' => 'Waybill not found'], 404);
    return $w;
};
$recalc = function (int $id) use ($db) {
    $db->cdp_query('UPDATE cdb_waybills w SET pieces = (SELECT COALESCE(SUM(pieces),0) FROM cdb_waybill_items i WHERE i.waybill_id = w.id), chargeable_kg = (SELECT COALESCE(SUM(weight_kg),0) FROM cdb_waybill_items i WHERE i.waybill_id = w.id), total_cost = ROUND((SELECT COALESCE(SUM(weight_kg),0) FROM cdb_waybill_items i WHERE i.waybill_id = w.id) * (rate_per_kg + surcharge_per_kg), 2), updated_at = NOW() WHERE w.id = :id');
    $db->bind(':id', $id);
    $db->cdp_execute();
};
switch ($op) {
    case 'create':
        $errors = [];
        $no = mb_substr(trim((string) ($_POST['waybill_no'] ?? '')), 0, 60);
        if ($no === '') $errors['waybill_no'] = 'Waybill number is required';
        elseif (sl_row('SELECT id FROM cdb_waybills WHERE waybill_no = :n', [':n' => $no])) $errors['waybill_no'] = 'This number already exists';
        $sched = (int) ($_POST['schedule_id'] ?? 0) ?: null;
        $s = $sched && sl_tableExists('cdb_freight_schedules') ? sl_row('SELECT * FROM cdb_freight_schedules WHERE id = :id', [':id' => $sched]) : null;
        $carrier = mb_substr(trim((string) ($_POST['carrier'] ?? '')), 0, 150) ?: ($s->carrier_name ?? null);
        $origin = mb_substr(trim((string) ($_POST['origin'] ?? '')), 0, 80) ?: ($s->origin ?? null);
        $dest = mb_substr(trim((string) ($_POST['destination'] ?? '')), 0, 80) ?: ($s->destination ?? null);
        if (!$origin) $errors['origin'] = 'Required (or pick a departure)';
        if (!$dest) $errors['destination'] = 'Required (or pick a departure)';
        if ($errors) sl_json(['success' => false, 'message' => 'Check the form', 'errors' => $errors], 422);
        $db->cdp_query('INSERT INTO cdb_waybills (kind, waybill_no, schedule_id, consolidation_no, carrier, origin, destination, status, rate_per_kg, surcharge_per_kg, container_no, seal_no, container_ft3, notes, created_by, created_at) VALUES (:k, :n, :s, :c, :ca, :o, :d, :st, :r, :su, :cn, :se, :ft, :nt, :by, NOW())');
        $db->bind(':k', 'master'); $db->bind(':n', $no); $db->bind(':s', $sched); $db->bind(':c', mb_substr(trim((string) ($_POST['consolidation_no'] ?? '')), 0, 100) ?: ($s->consolidation_ids ?? null));
        $db->bind(':ca', $carrier); $db->bind(':o', $origin); $db->bind(':d', $dest); $db->bind(':st', 'draft');
        $db->bind(':r', max(0, (float) ($_POST['rate_per_kg'] ?? 0))); $db->bind(':su', max(0, (float) ($_POST['surcharge_per_kg'] ?? 0)));
        $db->bind(':cn', mb_substr(trim((string) ($_POST['container_no'] ?? '')), 0, 40) ?: null); $db->bind(':se', mb_substr(trim((string) ($_POST['seal_no'] ?? '')), 0, 40) ?: null);
        $db->bind(':ft', trim((string) ($_POST['container_ft3'] ?? '')) === '' ? null : max(0, (float) $_POST['container_ft3'])); $db->bind(':nt', mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 2000) ?: null); $db->bind(':by', $actor);
        if (!$db->cdp_execute()) sl_json(['success' => false, 'message' => 'Could not create the waybill']);
        $id = (int) $db->dbh->lastInsertId();
        $log('create', 'Created master waybill', "created $no $origin → $dest", $id, $no);
        sl_json(['success' => true, 'message' => "$no created", 'redirect' => sl_route('Waybills', ['id' => $id])]);
        break;
    case 'add_items':
        $w = $load($id);
        if ($w->status === 'closed') sl_json(['success' => false, 'message' => 'This waybill is closed'], 409);
        $list = preg_split('/[\s,;]+/', trim((string) ($_POST['trackings'] ?? ''))) ?: [];
        $cons = mb_substr(trim((string) ($_POST['consolidation_no'] ?? '')), 0, 100);
        $tracks = [];
        if ($cons !== '') {
            foreach (sl_rows('SELECT o.order_no FROM cdb_consolidate c JOIN cdb_consolidate_detail cd ON cd.consolidate_id = c.consolidate_id JOIN cdb_add_order o ON o.order_id = cd.order_id WHERE c.c_no = :c AND o.is_deleted = 0', [':c' => $cons]) as $r) $tracks[] = $r->order_no;
            if (!$tracks) sl_json(['success' => false, 'message' => "No shipments found in consolidation $cons", 'errors' => ['consolidation_no' => 'Nothing in it']], 422);
        }
        foreach ($list as $t) if ($t !== '') $tracks[] = mb_substr($t, 0, 100);
        $tracks = array_values(array_unique($tracks));
        if (!$tracks) sl_json(['success' => false, 'message' => 'Paste tracking numbers or a consolidation number', 'errors' => ['trackings' => 'Required']], 422);
        $added = 0;
        $missing = [];
        foreach ($tracks as $t) {
            if (sl_row('SELECT id FROM cdb_waybill_items WHERE waybill_id = :w AND order_track = :t', [':w' => $id, ':t' => $t])) continue;
            $o = sl_row("SELECT o.order_id, o.order_no, o.total_weight, o.total_order, CONCAT(u.fname,' ',u.lname) AS cust, (SELECT GROUP_CONCAT(order_item_description SEPARATOR ', ') FROM cdb_add_order_item i WHERE i.order_id = o.order_id) AS what, (SELECT COALESCE(SUM(order_item_quantity),0) FROM cdb_add_order_item i WHERE i.order_id = o.order_id) AS pcs FROM cdb_add_order o LEFT JOIN cdb_users u ON u.id = o.sender_id WHERE o.order_no = :t AND o.is_deleted = 0", [':t' => $t]);
            $src = 'shipment';
            if (!$o && sl_tableExists('cdb_customers_packages')) {
                $o = sl_row("SELECT p.order_no, p.total_weight, p.total_order, CONCAT(u.fname,' ',u.lname) AS cust, '' AS what, 1 AS pcs FROM cdb_customers_packages p LEFT JOIN cdb_users u ON u.id = p.sender_id WHERE p.order_no = :t", [':t' => $t]);
                $src = 'package';
            }
            if (!$o) {
                $missing[] = $t;
                continue;
            }
            $db->cdp_query('INSERT INTO cdb_waybill_items (waybill_id, source, order_track, customer_name, description, pieces, weight_kg, charge, created_at) VALUES (:w, :s, :t, :c, :d, :p, :kg, :ch, NOW())');
            $db->bind(':w', $id); $db->bind(':s', $src); $db->bind(':t', $t); $db->bind(':c', mb_substr((string) $o->cust, 0, 190) ?: null); $db->bind(':d', mb_substr((string) $o->what, 0, 255) ?: null);
            $db->bind(':p', max(1, (int) round((float) $o->pcs))); $db->bind(':kg', (float) $o->total_weight); $db->bind(':ch', (float) $o->total_order);
            if ($db->cdp_execute()) $added++;
        }
        $recalc($id);
        $log('items', 'Added house bills', "added $added house bills to {$w->waybill_no}", $id, $w->waybill_no, ['count' => $added]);
        sl_json(['success' => $added > 0 || !$missing, 'message' => "$added house bills added" . ($missing ? ' · not found: ' . implode(', ', array_slice($missing, 0, 5)) : ''), 'reload' => true]);
        break;
    case 'remove_item':
        $w = $load($id);
        $db->cdp_query('DELETE FROM cdb_waybill_items WHERE id = :i AND waybill_id = :w');
        $db->bind(':i', (int) ($_POST['item'] ?? 0)); $db->bind(':w', $id);
        $ok = $db->cdp_execute();
        $recalc($id);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'House bill removed' : 'Could not remove', 'reload' => true]);
        break;
    case 'issue':
        $w = $load($id);
        if ($w->status !== 'draft') sl_json(['success' => false, 'message' => $w->waybill_no . ' is already ' . $w->status], 409);
        $n = (int) sl_scalar('SELECT COUNT(*) FROM cdb_waybill_items WHERE waybill_id = :w', [':w' => $id]);
        if ($n === 0) sl_json(['success' => false, 'message' => 'Add at least one house bill before issuing'], 422);
        $recalc($id);
        $db->cdp_query('UPDATE cdb_waybills SET status = :s, issued_at = NOW(), issued_by = :by, updated_at = NOW() WHERE id = :id');
        $db->bind(':s', 'issued'); $db->bind(':by', $actor); $db->bind(':id', $id);
        $ok = $db->cdp_execute();
        if ($ok) $log('issue', 'Issued master waybill', "issued {$w->waybill_no} with $n house bills", $id, $w->waybill_no);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? "{$w->waybill_no} issued with $n house bills" : 'Could not issue', 'redirect' => sl_route('Waybills', ['id' => $id, 'flash' => "{$w->waybill_no} issued with $n house bills"])]);
        break;
    case 'send':
        $w = $load($id);
        $to = preg_replace('/[^a-z_]/', '', (string) ($_POST['to'] ?? ''));
        if (!in_array($to, ['carrier', 'customs', 'hub'], true)) sl_json(['success' => false, 'message' => 'Unknown recipient'], 422);
        $keys = array_filter(array_map('trim', explode(',', (string) $w->sent_to)));
        if (!in_array($to, $keys, true)) $keys[] = $to;
        $db->cdp_query('UPDATE cdb_waybills SET sent_to = :t, sent_at = NOW(), status = CASE WHEN status IN (\'draft\',\'issued\') THEN \'sent\' ELSE status END, updated_at = NOW() WHERE id = :id');
        $db->bind(':t', implode(',', $keys)); $db->bind(':id', $id);
        $ok = $db->cdp_execute();
        if ($ok) $log('send', 'Manifest marked sent', "manifest {$w->waybill_no} marked sent to $to", $id, $w->waybill_no);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Manifest marked as sent to ' . $to : 'Could not record', 'reload' => true]);
        break;
    case 'close':
        $w = $load($id);
        $db->cdp_query('UPDATE cdb_waybills SET status = :s, updated_at = NOW() WHERE id = :id');
        $db->bind(':s', 'closed'); $db->bind(':id', $id);
        $ok = $db->cdp_execute();
        if ($ok) $log('close', 'Closed waybill', "closed {$w->waybill_no}", $id, $w->waybill_no);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? $w->waybill_no . ' closed' : 'Could not close', 'reload' => true]);
        break;
    case 'plan':
        // Container plan: heavy first (back, low slots), then medium, light, fragile last (door end).
        $w = $load($id);
        $items = sl_rows('SELECT id, weight_kg, fragile FROM cdb_waybill_items WHERE waybill_id = :w ORDER BY fragile ASC, weight_kg DESC, id', [':w' => $id]);
        if (!$items) sl_json(['success' => false, 'message' => 'No house bills to plan'], 422);
        if (count($items) > 72) sl_json(['success' => false, 'message' => 'The grid holds 72 slots; this bill has ' . count($items) . ' pieces. Plan is for one container.'], 422);
        $slot = 1;
        foreach ($items as $i) {
            $db->cdp_query('UPDATE cdb_waybill_items SET slot = :s WHERE id = :i');
            $db->bind(':s', $slot++); $db->bind(':i', (int) $i->id);
            $db->cdp_execute();
        }
        $log('plan', 'Planned container load', 'planned ' . count($items) . " slots on {$w->waybill_no}", $id, $w->waybill_no);
        sl_json(['success' => true, 'message' => count($items) . ' pieces placed: heavy at the back, fragile by the door', 'redirect' => sl_route('Waybills', ['id' => $id, 'tab' => 'load', 'flash' => 'Load plan updated: ' . count($items) . ' pieces placed'])]);
        break;
    case 'fragile':
        $w = $load($id);
        $db->cdp_query('UPDATE cdb_waybill_items SET fragile = :f WHERE id = :i AND waybill_id = :w');
        $db->bind(':f', (int) !empty($_POST['value'])); $db->bind(':i', (int) ($_POST['item'] ?? 0)); $db->bind(':w', $id);
        sl_json(['success' => (bool) $db->cdp_execute(), 'message' => 'Saved']);
        break;
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
