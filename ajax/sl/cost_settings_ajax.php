<?php
/** Save / add / remove operating cost lines (super admin). Every change is logged to cdb_operating_cost_log. */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
if (CDP_APP_MODE_DEMO === true) sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
$user = new User();
if (!sl_userIs('superadmin') && !$user->cdp_hasPermission('manage_operating_costs')) sl_json(['success' => false, 'message' => 'Only the super admin can change operating costs'], 403);
if (!sl_tableExists('cdb_operating_costs')) sl_json(['success' => false, 'message' => 'Run the database upgrade first'], 409);
$cats = ['infra', 'messages', 'rent', 'staff', 'fuel', 'software', 'customs', 'other'];
$freqs = ['monthly', 'yearly', 'weekly', 'daily', 'once'];
$uid = (int) $_SESSION['userid'];
$db = new Conexion();
$log = function ($id, $name, $old, $new) use ($db, $uid) {
    $db->cdp_query('INSERT INTO cdb_operating_cost_log (cost_id, cost_name, old_amount, new_amount, changed_by, changed_at) VALUES (:i, :n, :o, :w, :u, NOW())');
    $db->bind(':i', $id); $db->bind(':n', mb_substr($name, 0, 120)); $db->bind(':o', $old); $db->bind(':w', $new); $db->bind(':u', $uid);
    $db->cdp_execute();
};
$clean = function (array $r) use ($cats, $freqs) {
    $amt = str_replace(',', '', trim((string) ($r['amt'] ?? '')));
    return ['name' => mb_substr(trim((string) ($r['name'] ?? '')), 0, 120), 'amt' => is_numeric($amt) ? round((float) $amt, 2) : null, 'cur' => in_array($r['cur'] ?? '', ['GHS', 'USD'], true) ? $r['cur'] : 'GHS', 'cat' => in_array($r['cat'] ?? '', $cats, true) ? $r['cat'] : 'other', 'freq' => in_array($r['freq'] ?? '', $freqs, true) ? $r['freq'] : 'monthly', 'bill' => empty($r['bill']) ? 0 : 1];
};
$op = (string) ($_POST['op'] ?? '');
if ($op === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $row = sl_row('SELECT * FROM cdb_operating_costs WHERE id = :id AND is_active = 1', [':id' => $id]);
    if (!$row) sl_json(['success' => false, 'message' => 'Cost not found'], 404);
    $db->cdp_query('UPDATE cdb_operating_costs SET is_active = 0, updated_by = :u, updated_at = NOW() WHERE id = :id');
    $db->bind(':u', $uid); $db->bind(':id', $id); $db->cdp_execute();
    $log($id, $row->name, $row->amount, null);
    cdp_activityLog(['module' => 'finance', 'action' => 'cost_delete', 'action_label' => 'Removed operating cost', 'verb' => 'delete', 'outcome' => 'ok', 'summary' => 'removed operating cost ' . $row->name, 'entity_type' => 'operating_cost', 'entity_id' => $id, 'entity_label' => $row->name]);
    sl_json(['success' => true, 'message' => $row->name . ' removed']);
}
if ($op !== 'save') sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
$changed = 0;
$errors = [];
foreach ((array) ($_POST['cost'] ?? []) as $id => $r) {
    $id = (int) $id; $c = $clean((array) $r);
    if ($c['name'] === '' || $c['amt'] === null || $c['amt'] < 0) { $errors["cost[$id][amt]"] = 'Enter a name and an amount'; continue; }
    $old = sl_row('SELECT * FROM cdb_operating_costs WHERE id = :id AND is_active = 1', [':id' => $id]);
    if (!$old) continue;
    if ($old->name === $c['name'] && (float) $old->amount === $c['amt'] && $old->currency === $c['cur'] && $old->category === $c['cat'] && $old->frequency === $c['freq'] && (int) $old->on_billing === $c['bill']) continue;
    $db->cdp_query('UPDATE cdb_operating_costs SET name = :n, amount = :a, currency = :c, category = :k, frequency = :f, on_billing = :b, updated_by = :u, updated_at = NOW() WHERE id = :id');
    $db->bind(':n', $c['name']); $db->bind(':a', $c['amt']); $db->bind(':c', $c['cur']); $db->bind(':k', $c['cat']); $db->bind(':f', $c['freq']); $db->bind(':b', $c['bill']); $db->bind(':u', $uid); $db->bind(':id', $id);
    $db->cdp_execute();
    if ((float) $old->amount !== $c['amt']) $log($id, $c['name'], $old->amount, $c['amt']);
    $changed++;
}
$new = $clean((array) ($_POST['new'] ?? []));
if ($new['name'] !== '' || ($new['amt'] ?? 0) > 0) {
    if ($new['name'] === '' || $new['amt'] === null) { $errors['new[amt]'] = 'Enter a name and an amount'; }
    else {
        $db->cdp_query('INSERT INTO cdb_operating_costs (name, category, amount, currency, frequency, on_billing, applies_from, is_active, created_by, created_at) VALUES (:n, :k, :a, :c, :f, 1, CURDATE(), 1, :u, NOW())');
        $db->bind(':n', $new['name']); $db->bind(':k', $new['cat']); $db->bind(':a', $new['amt']); $db->bind(':c', $new['cur']); $db->bind(':f', $new['freq']); $db->bind(':u', $uid);
        $db->cdp_execute();
        $log((int) $db->dbh->lastInsertId(), $new['name'], null, $new['amt']);
        $changed++;
    }
}
if ($errors) sl_json(['success' => false, 'message' => 'Check the highlighted costs', 'errors' => $errors], 422);
if ($changed) cdp_activityLog(['module' => 'finance', 'action' => 'cost_save', 'action_label' => 'Saved operating costs', 'verb' => 'update', 'outcome' => 'ok', 'summary' => "saved $changed operating cost line(s)", 'entity_type' => 'operating_cost']);
sl_json(['success' => true, 'message' => $changed ? "Saved $changed change" . ($changed > 1 ? 's' : '') : 'Nothing changed']);
