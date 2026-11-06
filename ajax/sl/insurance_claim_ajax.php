<?php
/** Insurance claims: create from a shipment (declared / insured value from cdb_add_order), advance, reject, note, attach documents. */
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
require_permission('manage_insurance');
if (!sl_tableExists('cdb_insurance_claims')) {
    sl_json(['success' => false, 'message' => 'Claims table is missing: run migrations/2026_11_06_freight_devops.sql'], 409);
}
$db = new Conexion();
$actor = (int) $_SESSION['userid'];
$actorName = trim((string) sl_scalar("SELECT CONCAT(fname,' ',lname) FROM cdb_users WHERE id = :u", [':u' => $actor], '')) ?: 'Staff';
$op = (string) ($_POST['op'] ?? 'create');
$id = (int) ($_POST['id'] ?? 0);
$log = function (string $action, string $label, string $summary, int $eid, string $elabel) {
    if (function_exists('cdp_activityLog')) {
        cdp_activityLog(['module' => 'insurance', 'action' => $action, 'action_label' => $label, 'verb' => $action === 'claim_create' ? 'create' : 'update', 'outcome' => 'ok', 'summary' => $summary, 'entity_type' => 'insurance_claim', 'entity_id' => $eid, 'entity_label' => $elabel]);
    }
};
$event = function (int $id, string $text) use ($db, $actorName) {
    $tl = json_decode((string) sl_scalar('SELECT timeline FROM cdb_insurance_claims WHERE id = :id', [':id' => $id], '[]'), true) ?: [];
    $tl[] = ['at' => date('Y-m-d H:i:s'), 'by' => $actorName, 'text' => $text];
    $db->cdp_query('UPDATE cdb_insurance_claims SET timeline = :t, updated_at = NOW() WHERE id = :id');
    $db->bind(':t', json_encode($tl, JSON_UNESCAPED_UNICODE)); $db->bind(':id', $id);
    $db->cdp_execute();
};
$upload = function (): array {
    $out = [];
    if (empty($_FILES['documents']['name'][0])) return $out;
    $dir = CDP_ROOT . '/uploads/claims';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    foreach ((array) $_FILES['documents']['tmp_name'] as $i => $tmp) {
        if (!is_uploaded_file($tmp) || $_FILES['documents']['size'][$i] > 10 * 1048576) continue;
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'][$mime] ?? null;
        if (!$ext) continue;
        $name = 'claim_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (move_uploaded_file($tmp, $dir . '/' . $name)) $out[] = ['path' => 'uploads/claims/' . $name, 'name' => mb_substr(basename((string) $_FILES['documents']['name'][$i]), 0, 120)];
    }
    return $out;
};
$order = ['reported', 'reviewing', 'approved', 'paid'];
switch ($op) {
    case 'create':
        $errors = [];
        $track = mb_substr(trim((string) ($_POST['order_track'] ?? '')), 0, 100);
        $type = ($_POST['type'] ?? '') === 'lost' ? 'lost' : 'damaged';
        $o = $track !== '' ? sl_row("SELECT o.order_id, o.order_no, o.sender_id, o.declared_value, o.total_declared_value, o.total_insured_value, CONCAT(u.fname,' ',u.lname) AS cust FROM cdb_add_order o LEFT JOIN cdb_users u ON u.id = o.sender_id WHERE o.order_no = :t AND o.is_deleted = 0", [':t' => $track]) : null;
        if ($track === '') $errors['order_track'] = 'Tracking number is required';
        elseif (!$o) $errors['order_track'] = 'No shipment with this tracking number';
        elseif (sl_row("SELECT id FROM cdb_insurance_claims WHERE order_track = :t AND status NOT IN ('rejected')", [':t' => $track])) $errors['order_track'] = 'This shipment already has an open or paid claim';
        $amount = (float) ($_POST['claim_amount'] ?? 0);
        if ($amount <= 0) $errors['claim_amount'] = 'Enter the amount claimed';
        $declared = $o ? (float) ($o->total_declared_value ?: $o->declared_value) : 0;
        $insured = $o ? (float) $o->total_insured_value : 0;
        $cap = $insured > 0 ? $insured : $declared;
        if ($o && $cap > 0 && $amount > $cap) $errors['claim_amount'] = 'More than the declared / insured value (' . sl_money($cap) . ')';
        if ($errors) sl_json(['success' => false, 'message' => 'Check the form', 'errors' => $errors], 422);
        $next = (int) sl_scalar('SELECT COALESCE(MAX(id),0) + 1 FROM cdb_insurance_claims');
        $no = 'CLM-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        $docs = $upload();
        $exc = (int) ($_POST['exception_id'] ?? 0) ?: null;
        $db->cdp_query('INSERT INTO cdb_insurance_claims (claim_no, order_id, order_track, customer_id, customer_name, type, declared_value, insured_value, claim_amount, status, exception_id, documents, notes, created_by, created_at) VALUES (:no, :oid, :t, :c, :cn, :ty, :dv, :iv, :ca, :st, :ex, :docs, :n, :by, NOW())');
        $db->bind(':no', $no); $db->bind(':oid', (int) $o->order_id); $db->bind(':t', $track); $db->bind(':c', (int) $o->sender_id); $db->bind(':cn', trim((string) $o->cust) ?: null);
        $db->bind(':ty', $type); $db->bind(':dv', $declared); $db->bind(':iv', $insured); $db->bind(':ca', $amount); $db->bind(':st', 'reported'); $db->bind(':ex', $exc);
        $db->bind(':docs', $docs ? json_encode($docs) : null); $db->bind(':n', mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 4000) ?: null); $db->bind(':by', $actor);
        if (!$db->cdp_execute()) sl_json(['success' => false, 'message' => 'Could not save the claim']);
        $id = (int) $db->dbh->lastInsertId();
        $event($id, 'Claim reported: ' . $type . ', ' . sl_money($amount));
        if ($exc && sl_tableExists('cdb_cargo_exceptions')) {
            $db->cdp_query('UPDATE cdb_cargo_exceptions SET claim_id = :c WHERE id = :e');
            $db->bind(':c', $id); $db->bind(':e', $exc);
            $db->cdp_execute();
        }
        $log('claim_create', 'Opened insurance claim', "$no on $track ($type, $amount)", $id, $no);
        sl_json(['success' => true, 'message' => "$no opened", 'redirect' => sl_route('InsuranceClaims', ['id' => $id])]);
        break;
    case 'advance':
        $c = sl_row('SELECT * FROM cdb_insurance_claims WHERE id = :id', [':id' => $id]);
        if (!$c) sl_json(['success' => false, 'message' => 'Claim not found'], 404);
        $i = array_search($c->status, $order, true);
        if ($i === false || $i >= count($order) - 1) sl_json(['success' => false, 'message' => 'This claim is ' . $c->status], 409);
        $next = $order[$i + 1];
        $offer = trim((string) ($_POST['offer_amount'] ?? '')) === '' ? null : max(0, (float) $_POST['offer_amount']);
        if ($next === 'approved' && $offer === null) $offer = (float) $c->claim_amount;
        if ($offer !== null && $offer > (float) $c->claim_amount) sl_json(['success' => false, 'message' => 'Offer cannot exceed the amount claimed', 'errors' => ['offer_amount' => 'Too high']], 422);
        $db->cdp_query('UPDATE cdb_insurance_claims SET status = :s, offer_amount = COALESCE(:o, offer_amount), paid_at = CASE WHEN :s2 = \'paid\' THEN NOW() ELSE paid_at END, updated_at = NOW() WHERE id = :id');
        $db->bind(':s', $next); $db->bind(':o', $offer); $db->bind(':s2', $next); $db->bind(':id', $id);
        $ok = $db->cdp_execute();
        if ($ok) {
            $event($id, 'Moved to ' . $next . ($offer !== null ? ' · offer ' . sl_money($offer) : ''));
            $log('claim_' . $next, 'Claim ' . $next, "{$c->claim_no}: {$c->status} → $next", $id, $c->claim_no);
        }
        sl_json(['success' => (bool) $ok, 'message' => $ok ? "{$c->claim_no} " . $next : 'Could not update', 'reload' => true]);
        break;
    case 'reject':
        $c = sl_row('SELECT * FROM cdb_insurance_claims WHERE id = :id', [':id' => $id]);
        if (!$c) sl_json(['success' => false, 'message' => 'Claim not found'], 404);
        if (in_array($c->status, ['paid', 'rejected'], true)) sl_json(['success' => false, 'message' => 'This claim is ' . $c->status], 409);
        $why = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 300);
        if ($why === '') sl_json(['success' => false, 'message' => 'Give a reason', 'errors' => ['reason' => 'Required']], 422);
        $db->cdp_query("UPDATE cdb_insurance_claims SET status = 'rejected', updated_at = NOW() WHERE id = :id");
        $db->bind(':id', $id);
        $ok = $db->cdp_execute();
        if ($ok) {
            $event($id, 'Rejected: ' . $why);
            $log('claim_reject', 'Claim rejected', "{$c->claim_no} rejected: $why", $id, $c->claim_no);
        }
        sl_json(['success' => (bool) $ok, 'message' => $ok ? "{$c->claim_no} rejected" : 'Could not update', 'reload' => true]);
        break;
    case 'note':
        $c = sl_row('SELECT * FROM cdb_insurance_claims WHERE id = :id', [':id' => $id]);
        if (!$c) sl_json(['success' => false, 'message' => 'Claim not found'], 404);
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 1000);
        $docs = $upload();
        if ($note === '' && !$docs) sl_json(['success' => false, 'message' => 'Write a note or attach a document', 'errors' => ['note' => 'Required']], 422);
        if ($docs) {
            $all = array_merge(json_decode((string) $c->documents, true) ?: [], $docs);
            $db->cdp_query('UPDATE cdb_insurance_claims SET documents = :d WHERE id = :id');
            $db->bind(':d', json_encode($all)); $db->bind(':id', $id);
            $db->cdp_execute();
        }
        $event($id, trim($note . ($docs ? ' · ' . count($docs) . ' document(s) attached' : '')));
        $log('claim_note', 'Claim note', "note on {$c->claim_no}", $id, $c->claim_no);
        sl_json(['success' => true, 'message' => 'Added to the claim', 'reload' => true]);
        break;
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
