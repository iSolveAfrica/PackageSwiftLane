<?php
/** Rider fee rules (cdb_rider_fee_rules): save, add, delete. */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_login();
require_permission('manage_rider_fees');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
if (CDP_APP_MODE_DEMO === true) sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
if (!sl_tableExists('cdb_rider_fee_rules')) sl_json(['success' => false, 'message' => 'Run the database upgrade first'], 409);
$db = new Conexion();
$me = (int) $_SESSION['userid'];
$num = fn($v, $min, $max) => max($min, min($max, round((float) str_replace(',', '.', (string) $v), 2)));
$log = fn($s) => cdp_activityLog(['module' => 'settings', 'verb' => 'update', 'action' => 'rider_fees', 'action_label' => 'Changed rider fees', 'outcome' => 'ok', 'entity_type' => 'rider_fee_rule', 'summary' => $s]);
switch ((string) ($_POST['op'] ?? '')) {
    case 'save':
        $changed = [];
        foreach ((array) ($_POST['rule'] ?? []) as $id => $f) {
            $row = sl_row('SELECT * FROM cdb_rider_fee_rules WHERE id = :id', [':id' => (int) $id]);
            if (!$row || !is_array($f)) continue;
            $amount = array_key_exists('amount', $f) ? $num($f['amount'], 0, 100000) : (float) $row->amount;
            $pct = array_key_exists('pct', $f) ? $num($f['pct'], 0, 100) : (float) $row->rider_pct;
            $active = array_key_exists('active', $f) ? (int) !empty($f['active']) : (int) $row->active;
            if ($amount == (float) $row->amount && $pct == (float) $row->rider_pct && $active === (int) $row->active) continue;
            $db->cdp_query('UPDATE cdb_rider_fee_rules SET amount = :a, rider_pct = :p, active = :ac, updated_by = :u, updated_at = NOW() WHERE id = :id');
            $db->bind(':a', $amount); $db->bind(':p', $pct); $db->bind(':ac', $active); $db->bind(':u', $me); $db->bind(':id', (int) $id); $db->cdp_execute();
            $changed[] = $row->label . ': ' . $row->amount . '/' . (float) $row->rider_pct . '% → ' . $amount . '/' . $pct . '%' . ($active ? '' : ' (off)');
        }
        if ($changed) $log('rider fees: ' . implode('; ', $changed));
        sl_json(['success' => true, 'message' => $changed ? 'Rider fees saved' : 'Nothing changed']);
    case 'add':
        $type = (string) ($_POST['new_type'] ?? '');
        if (!in_array($type, ['zone', 'vehicle', 'extra', 'bonus', 'penalty', 'per_km'], true)) sl_json(['success' => false, 'message' => 'Pick a kind'], 422);
        $label = mb_substr(trim((string) ($_POST['new_label'] ?? '')), 0, 120);
        if ($label === '') sl_json(['success' => false, 'message' => 'Give the rule a name', 'errors' => ['new_label' => 'Required']], 422);
        $key = substr($type . '_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($label)), 0, 40);
        if (sl_scalar('SELECT COUNT(*) FROM cdb_rider_fee_rules WHERE rule_key = :k', [':k' => $key])) sl_json(['success' => false, 'message' => 'A rule with that name exists'], 422);
        $areas = mb_substr(trim((string) ($_POST['new_areas'] ?? '')), 0, 250);
        $db->cdp_query('INSERT INTO cdb_rider_fee_rules (rule_key, label, rule_type, amount, rider_pct, condition_json, active, sort_order, updated_by, updated_at, created_at) VALUES (:k, :l, :t, :a, :p, :c, 1, 50, :u, NOW(), NOW())');
        $db->bind(':k', $key); $db->bind(':l', $label); $db->bind(':t', $type); $db->bind(':a', $num($_POST['new_amount'] ?? 0, 0, 100000)); $db->bind(':p', $num($_POST['new_pct'] ?? 70, 0, 100)); $db->bind(':c', $areas !== '' ? json_encode(['areas' => $areas]) : null); $db->bind(':u', $me);
        $db->cdp_execute();
        $log("added $type rule $label");
        sl_json(['success' => true, 'message' => "$label added"]);
    case 'delete':
        $row = sl_row('SELECT * FROM cdb_rider_fee_rules WHERE id = :id', [':id' => (int) ($_POST['id'] ?? 0)]);
        if (!$row) sl_json(['success' => false, 'message' => 'Rule not found'], 404);
        if (in_array($row->rule_type, ['per_delivery', 'per_pickup'], true)) sl_json(['success' => false, 'message' => 'The base fees cannot be removed'], 422);
        // Not a hard delete of history: the rule is switched off and renamed out of the way.
        $db->cdp_query("UPDATE cdb_rider_fee_rules SET active = 0, rule_type = CONCAT('x_', rule_type), rule_key = CONCAT('x', id, '_', LEFT(rule_key, 30)), updated_by = :u, updated_at = NOW() WHERE id = :id");
        $db->bind(':u', $me); $db->bind(':id', (int) $row->id); $db->cdp_execute();
        $log('removed rule ' . $row->label);
        sl_json(['success' => true, 'message' => $row->label . ' removed']);
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
