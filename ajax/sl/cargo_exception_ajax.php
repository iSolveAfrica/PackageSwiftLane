<?php
/**
 * Cargo exceptions: create (damaged / missing / customs hold / partial / misrouted /
 * delayed / hand-over), change status, assign, add a note, split a partial
 * shipment, and bulk status updates of shipments through the existing helpers
 * in helpers/querys.php (cdp_updateStatusCourierMultiple + cdp_updateShipTrackingMultiple).
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../helpers/querys.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
if (CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
require_permission('manage_freight');
require_once __DIR__ . '/../../views/sl/data/freight.php';
$db = new Conexion();
$actor = (int) $_SESSION['userid'];
$op = (string) ($_POST['op'] ?? 'create');
$id = (int) ($_POST['id'] ?? 0);
$log = function (string $action, string $label, string $summary, $eid, string $elabel, array $meta = []) {
    if (function_exists('cdp_activityLog')) {
        cdp_activityLog(['module' => 'freight', 'action' => $action, 'action_label' => $label, 'verb' => $action === 'exception_create' ? 'create' : 'update', 'outcome' => 'ok', 'summary' => $summary, 'entity_type' => 'cargo_exception', 'entity_id' => $eid, 'entity_label' => $elabel, 'meta' => $meta]);
    }
};
if ($op !== 'bulk_status' && !sl_tableExists('cdb_cargo_exceptions')) {
    sl_json(['success' => false, 'message' => 'Exception table is missing: run migrations/2026_11_06_freight_devops.sql'], 409);
}
$load = function (int $id) {
    $e = sl_row('SELECT * FROM cdb_cargo_exceptions WHERE id = :id', [':id' => $id]);
    if (!$e) sl_json(['success' => false, 'message' => 'Exception not found'], 404);
    return $e;
};
$appendNote = function (int $id, string $note) use ($db, $actor) {
    $who = trim((string) sl_scalar("SELECT CONCAT(fname,' ',lname) FROM cdb_users WHERE id = :u", [':u' => $actor], ''));
    $db->cdp_query("UPDATE cdb_cargo_exceptions SET notes = CONCAT(COALESCE(notes,''), CASE WHEN notes IS NULL OR notes = '' THEN '' ELSE '\n' END, :n), updated_at = NOW() WHERE id = :id");
    $db->bind(':n', '[' . date('M j H:i') . ' ' . $who . '] ' . $note); $db->bind(':id', $id);
    $db->cdp_execute();
};
switch ($op) {
    case 'create':
        $errors = [];
        $type = array_key_exists($_POST['type'] ?? '', sl_frExTypes()) ? $_POST['type'] : '';
        $etype = (in_array($_POST['entity_type'] ?? 'shipment', ['shipment', 'package', 'consolidation', 'schedule', 'waybill'], true) ? ($_POST['entity_type'] ?? 'shipment') : 'shipment');
        $ref = mb_substr(trim((string) ($_POST['entity_ref'] ?? '')), 0, 100);
        $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 190);
        $sev = (in_array($_POST['severity'] ?? 'medium', ['low', 'medium', 'high', 'urgent'], true) ? ($_POST['severity'] ?? 'medium') : 'medium');
        if ($type === '') $errors['type'] = 'Pick a type';
        if ($ref === '') $errors['entity_ref'] = 'Tracking, consolidation or voyage number';
        $customer = null;
        if ($ref !== '' && $etype === 'shipment') {
            $o = sl_row('SELECT order_id, sender_id FROM cdb_add_order WHERE order_no = :t AND is_deleted = 0', [':t' => $ref]);
            if (!$o) $errors['entity_ref'] = 'No shipment with this tracking number';
            else $customer = (int) $o->sender_id;
        }
        $exp = trim((string) ($_POST['pieces_expected'] ?? '')) === '' ? null : max(0, (int) $_POST['pieces_expected']);
        $arr = trim((string) ($_POST['pieces_arrived'] ?? '')) === '' ? null : max(0, (int) $_POST['pieces_arrived']);
        if ($type === 'partial' && (!$exp || $arr === null)) $errors['pieces_expected'] = 'Partial shipments need pieces expected and arrived';
        if ($type === 'partial' && $exp && $arr !== null && $arr >= $exp) $errors['pieces_arrived'] = 'Fewer than expected, or it is not partial';
        $from = mb_substr(trim((string) ($_POST['handed_from'] ?? '')), 0, 150);
        $to = mb_substr(trim((string) ($_POST['handed_to'] ?? '')), 0, 150);
        if ($type === 'handover' && ($from === '' || $to === '')) $errors['handed_to'] = 'Who handed over and who received';
        if ($errors) sl_json(['success' => false, 'message' => 'Check the form', 'errors' => $errors], 422);
        if ($title === '') {
            $title = $type === 'handover' ? "Hand-over $from → $to" : (sl_frExTypes()[$type] . ($type === 'partial' ? " · $arr of $exp pieces" : ''));
        }
        // photos (optional, images only)
        $photos = [];
        if (!empty($_FILES['photos']['name'][0])) {
            $dir = CDP_ROOT . '/uploads/freight';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            foreach ((array) $_FILES['photos']['tmp_name'] as $i => $tmp) {
                if (!is_uploaded_file($tmp) || $_FILES['photos']['size'][$i] > 8 * 1048576) continue;
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
                $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
                if (!$ext) continue;
                $name = 'ex_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($tmp, $dir . '/' . $name)) $photos[] = 'uploads/freight/' . $name;
            }
        }
        $owner = (int) ($_POST['owner_id'] ?? 0) ?: $actor;
        $db->cdp_query('INSERT INTO cdb_cargo_exceptions (type, entity_type, entity_ref, title, severity, status, owner_id, customer_id, pieces_expected, pieces_arrived, handed_from, handed_to, notify_customer, notes, photos, created_by, created_at) VALUES (:t, :et, :r, :ti, :s, :st, :o, :c, :pe, :pa, :hf, :ht, :nc, :n, :ph, :by, NOW())');
        $db->bind(':t', $type); $db->bind(':et', $etype); $db->bind(':r', $ref); $db->bind(':ti', $title); $db->bind(':s', $sev);
        $db->bind(':st', $type === 'handover' ? 'resolved' : 'open'); $db->bind(':o', $owner); $db->bind(':c', $customer); $db->bind(':pe', $exp); $db->bind(':pa', $arr);
        $db->bind(':hf', $from ?: null); $db->bind(':ht', $to ?: null); $db->bind(':nc', (int) !empty($_POST['notify_customer'])); $db->bind(':n', mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 4000) ?: null);
        $db->bind(':ph', $photos ? json_encode($photos) : null); $db->bind(':by', $actor);
        if (!$db->cdp_execute()) sl_json(['success' => false, 'message' => 'Could not save the exception']);
        $id = (int) $db->dbh->lastInsertId();
        $log('exception_create', 'Opened cargo exception', "$type on $ref: $title", $id, $ref);
        sl_json(['success' => true, 'message' => 'Exception recorded on ' . $ref, 'redirect' => sl_route('CargoExceptions', ['id' => $id])]);
        break;
    case 'status':
        $e = $load($id);
        $st = in_array($_POST['status'] ?? '', ['open', 'in_progress', 'resolved', 'closed'], true) ? $_POST['status'] : '';
        if ($st === '') sl_json(['success' => false, 'message' => 'Unknown status'], 422);
        $db->cdp_query('UPDATE cdb_cargo_exceptions SET status = :s, resolved_at = CASE WHEN :s2 IN (\'resolved\',\'closed\') THEN NOW() ELSE NULL END, resolved_by = CASE WHEN :s3 IN (\'resolved\',\'closed\') THEN :by ELSE NULL END, updated_at = NOW() WHERE id = :id');
        $db->bind(':s', $st); $db->bind(':s2', $st); $db->bind(':s3', $st); $db->bind(':by', $actor); $db->bind(':id', $id);
        $ok = $db->cdp_execute();
        if ($ok) {
            $appendNote($id, 'Status → ' . str_replace('_', ' ', $st));
            $log('exception_status', 'Exception status', "{$e->entity_ref}: {$e->status} → $st", $id, $e->entity_ref);
        }
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Marked ' . str_replace('_', ' ', $st) : 'Could not update', 'reload' => true]);
        break;
    case 'assign':
        $e = $load($id);
        $owner = (int) ($_POST['owner_id'] ?? 0);
        $name = trim((string) sl_scalar("SELECT CONCAT(fname,' ',lname) FROM cdb_users WHERE id = :u AND userlevel <> 1", [':u' => $owner], ''));
        if ($name === '') sl_json(['success' => false, 'message' => 'Pick a staff member', 'errors' => ['owner_id' => 'Required']], 422);
        $db->cdp_query("UPDATE cdb_cargo_exceptions SET owner_id = :o, status = CASE WHEN status = 'open' THEN 'in_progress' ELSE status END, updated_at = NOW() WHERE id = :id");
        $db->bind(':o', $owner); $db->bind(':id', $id);
        $ok = $db->cdp_execute();
        if ($ok) {
            $appendNote($id, 'Assigned to ' . $name);
            $log('exception_assign', 'Exception assigned', "{$e->entity_ref} assigned to $name", $id, $e->entity_ref);
        }
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Assigned to ' . $name : 'Could not assign', 'reload' => true]);
        break;
    case 'note':
        $e = $load($id);
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 2000);
        if ($note === '') sl_json(['success' => false, 'message' => 'Write a note', 'errors' => ['note' => 'Required']], 422);
        $appendNote($id, $note);
        $log('exception_note', 'Exception note', "note on {$e->entity_ref}", $id, $e->entity_ref);
        sl_json(['success' => true, 'message' => 'Note added', 'reload' => true]);
        break;
    case 'arrived':
        // partial shipment: more pieces arrived
        $e = $load($id);
        if ($e->type !== 'partial') sl_json(['success' => false, 'message' => 'Only partial shipments track pieces'], 422);
        $n = max(1, (int) ($_POST['n'] ?? 1));
        $new = min((int) $e->pieces_expected, (int) $e->pieces_arrived + $n);
        $done = $new >= (int) $e->pieces_expected;
        $db->cdp_query('UPDATE cdb_cargo_exceptions SET pieces_arrived = :a, status = :s, resolved_at = CASE WHEN :d = 1 THEN NOW() ELSE resolved_at END, updated_at = NOW() WHERE id = :id');
        $db->bind(':a', $new); $db->bind(':s', $done ? 'resolved' : $e->status); $db->bind(':d', $done ? 1 : 0); $db->bind(':id', $id);
        $ok = $db->cdp_execute();
        if ($ok) {
            $appendNote($id, "$new of {$e->pieces_expected} pieces arrived");
            $log('exception_pieces', 'Partial pieces arrived', "{$e->entity_ref}: $new of {$e->pieces_expected}", $id, $e->entity_ref);
        }
        sl_json(['success' => (bool) $ok, 'message' => $done ? 'All pieces arrived: exception resolved' : "$new of {$e->pieces_expected} pieces arrived", 'reload' => true]);
        break;
    case 'split':
        // Mark the partial shipment as split: arrived pieces may be handed out now.
        $e = $load($id);
        if ($e->type !== 'partial') sl_json(['success' => false, 'message' => 'Only partial shipments can be split'], 422);
        $db->cdp_query("UPDATE cdb_cargo_exceptions SET status = 'in_progress', updated_at = NOW() WHERE id = :id");
        $db->bind(':id', $id);
        $db->cdp_execute();
        $appendNote($id, 'Split: ' . (int) $e->pieces_arrived . ' arrived pieces released for hand-out; ' . ((int) $e->pieces_expected - (int) $e->pieces_arrived) . ' follow');
        $log('exception_split', 'Split partial shipment', "split {$e->entity_ref}", $id, $e->entity_ref);
        sl_json(['success' => true, 'message' => $e->entity_ref . ' split: arrived pieces can be handed out', 'reload' => true]);
        break;
    case 'bulk_status':
        $status = (int) ($_POST['status'] ?? 0);
        $style = sl_row('SELECT id, mod_style FROM cdb_styles WHERE id = :s', [':s' => $status]);
        if (!$style) sl_json(['success' => false, 'message' => 'Pick the new status', 'errors' => ['status' => 'Required']], 422);
        $tracks = array_values(array_unique(array_filter(array_map('trim', (array) ($_POST['track'] ?? [])))));
        if (!$tracks) sl_json(['success' => false, 'message' => 'Select at least one shipment'], 422);
        if (count($tracks) > 500) sl_json(['success' => false, 'message' => 'At most 500 shipments at a time'], 422);
        $comment = mb_substr(trim((string) ($_POST['comment'] ?? '')), 0, 250) ?: ('Bulk update: ' . str_replace('_', ' ', $style->mod_style));
        $office = (int) sl_scalar('SELECT COALESCE(MIN(id),0) FROM cdb_offices');
        $n = 0;
        foreach ($tracks as $t) {
            if (!sl_row('SELECT order_id FROM cdb_add_order WHERE order_no = :t AND is_deleted = 0', [':t' => $t])) continue;
            if (cdp_updateStatusCourierMultiple($t, $status)) {
                cdp_updateShipTrackingMultiple($t, $status, $comment, $office ?: null, $actor);
                $n++;
            }
        }
        if (function_exists('cdp_activityLog')) {
            cdp_activityLog(['module' => 'freight', 'action' => 'bulk_status', 'action_label' => 'Bulk status update', 'verb' => 'update', 'outcome' => 'ok', 'summary' => "$n shipments set to {$style->mod_style}", 'entity_type' => 'shipment', 'entity_id' => '', 'entity_label' => $n . ' shipments', 'status_id' => $status, 'status_name' => $style->mod_style, 'meta' => ['count' => $n, 'trackings' => array_slice($tracks, 0, 50)]]);
        }
        sl_json(['success' => $n > 0, 'message' => "$n shipments set to \"" . str_replace('_', ' ', $style->mod_style) . '" with a tracking entry each', 'reload' => true]);
        break;
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
