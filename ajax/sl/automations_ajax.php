<?php
/** Automations: save, toggle, duplicate, delete, test run. */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../helpers/automations.php';
require_login();
require_permission('manage_automations');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
if (CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
if (!cdp_automationsReady()) {
    sl_json(['success' => false, 'message' => 'Run the database upgrade (2026_11_06_messaging_settings) first'], 409);
}
$op = (string) ($_POST['op'] ?? '');
$id = (int) ($_POST['id'] ?? 0);
$db = new Conexion();
$actor = (int) $_SESSION['userid'];
$row = $id ? sl_row('SELECT * FROM cdb_automations WHERE id = :id', [':id' => $id]) : null;
if ($id && !$row) {
    sl_json(['success' => false, 'message' => 'Automation not found'], 404);
}
$log = function ($action, $summary, $eid, $label) {
    cdp_activityLog(['module' => 'automations', 'action' => $action, 'verb' => $action === 'delete' ? 'delete' : 'update', 'outcome' => 'ok', 'summary' => $summary, 'entity_type' => 'automation', 'entity_id' => $eid, 'entity_label' => $label]);
};
switch ($op) {
    case 'save':
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 150);
        $trigger = (string) ($_POST['trigger_key'] ?? '');
        $conds = json_decode((string) ($_POST['conditions'] ?? '[]'), true);
        $acts = json_decode((string) ($_POST['actions'] ?? '[]'), true);
        $errors = [];
        if ($name === '') $errors['name'] = 'Give it a name';
        if (!isset(cdp_automationTriggers()[$trigger])) $errors['trigger_key'] = 'Pick what starts it';
        $okConds = [];
        foreach ((array) $conds as $c) {
            if (isset(cdp_automationConditions()[$c['key'] ?? ''])) $okConds[] = ['key' => (string) $c['key'], 'value' => mb_substr((string) ($c['value'] ?? ''), 0, 120)];
        }
        $okActs = [];
        foreach ((array) $acts as $a) {
            if (isset(cdp_automationActions()[$a['type'] ?? ''])) $okActs[] = ['type' => (string) $a['type'], 'subject' => mb_substr((string) ($a['subject'] ?? ''), 0, 190), 'message' => mb_substr((string) ($a['message'] ?? ''), 0, 1000)];
        }
        if (!$okActs) $errors['actions'] = 'Choose at least one action';
        if ($errors) sl_json(['success' => false, 'message' => reset($errors), 'errors' => $errors], 422);
        if ($row) {
            $db->cdp_query('UPDATE cdb_automations SET name = :n, trigger_key = :t, conditions = :c, actions = :a, updated_at = NOW() WHERE id = :id');
            $db->bind(':id', $id);
        } else {
            $db->cdp_query('INSERT INTO cdb_automations (name, trigger_key, conditions, actions, enabled, created_by, created_at, updated_at) VALUES (:n, :t, :c, :a, 1, :u, NOW(), NOW())');
            $db->bind(':u', $actor);
        }
        $db->bind(':n', $name); $db->bind(':t', $trigger); $db->bind(':c', json_encode($okConds)); $db->bind(':a', json_encode($okActs));
        $ok = $db->cdp_execute();
        $newId = $row ? $id : (int) sl_scalar('SELECT MAX(id) FROM cdb_automations');
        if ($ok) $log($row ? 'edit' : 'create', ($row ? 'edited' : 'created') . " automation \"$name\"", $newId, $name);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Automation saved' : 'Could not save', 'redirect' => 'automations.php?edit=' . $newId]);
        break;
    case 'toggle':
        if (!$row) sl_json(['success' => false, 'message' => 'Automation not found'], 404);
        $on = (int) !empty($_POST['value']);
        $db->cdp_query('UPDATE cdb_automations SET enabled = :e, updated_at = NOW() WHERE id = :id');
        $db->bind(':e', $on); $db->bind(':id', $id);
        $ok = $db->cdp_execute();
        if ($ok) $log('toggle', ($on ? 'switched on' : 'paused') . " automation \"{$row->name}\"", $id, $row->name);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? ($on ? 'Switched on' : 'Paused') : 'Could not save']);
        break;
    case 'duplicate':
        if (!$row) sl_json(['success' => false, 'message' => 'Automation not found'], 404);
        $db->cdp_query('INSERT INTO cdb_automations (name, trigger_key, conditions, actions, enabled, created_by, created_at, updated_at) VALUES (:n, :t, :c, :a, 0, :u, NOW(), NOW())');
        $db->bind(':n', mb_substr($row->name . ' (copy)', 0, 150)); $db->bind(':t', $row->trigger_key); $db->bind(':c', $row->conditions); $db->bind(':a', $row->actions); $db->bind(':u', $actor);
        $ok = $db->cdp_execute();
        if ($ok) $log('duplicate', "duplicated automation \"{$row->name}\"", $id, $row->name);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Copy created (paused)' : 'Could not duplicate']);
        break;
    case 'delete':
        if (!$row) sl_json(['success' => false, 'message' => 'Automation not found'], 404);
        $db->cdp_query('DELETE FROM cdb_automations WHERE id = :id');
        $db->bind(':id', $id);
        $ok = $db->cdp_execute();
        if ($ok) $log('delete', "deleted automation \"{$row->name}\"", $id, $row->name);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Automation deleted' : 'Could not delete', 'redirect' => 'automations.php']);
        break;
    case 'test':
        if (!$row) sl_json(['success' => false, 'message' => 'Save the automation first'], 422);
        // Dry run against the most recent shipment: nothing is sent.
        $s = sl_row('SELECT order_id, order_prefix, order_no, sender_id, status_courier, total_order, is_pickup, status_invoice FROM cdb_add_order WHERE is_deleted = 0 ORDER BY order_id DESC');
        $ctx = $s ? ['entity_type' => 'shipment', 'entity_id' => (int) $s->order_id, 'entity_label' => $s->order_prefix . $s->order_no, 'tracking' => $s->order_prefix . $s->order_no,
            'user_id' => (int) $s->sender_id, 'status_id' => (int) $s->status_courier, 'mode' => 'air', 'amount' => (float) $s->total_order, 'is_pickup' => (int) $s->is_pickup === 1, 'unpaid' => (int) $s->status_invoice === 23] : [];
        $runs = cdp_automationsFire($row->trigger_key, $ctx, true, $id);
        $r = $runs[0] ?? null;
        sl_json(['success' => true, 'message' => $r ? 'Test on ' . ($ctx['entity_label'] ?? 'sample') . ': ' . $r['outcome'] . ' · ' . $r['detail'] : 'Nothing matched']);
        break;
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
