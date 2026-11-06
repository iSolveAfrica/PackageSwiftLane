<?php
/** Create, edit, rate update and archive freight partners (FreightPartners page). */
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
if (!sl_tableExists('cdb_freight_partners')) {
    sl_json(['success' => false, 'message' => 'Freight tables are missing: run migrations/2026_11_06_freight_devops.sql'], 409);
}
require_once __DIR__ . '/../../views/sl/data/freight.php';
$db = new Conexion();
$actor = (int) $_SESSION['userid'];
$op = (string) ($_POST['op'] ?? 'create');
$id = (int) ($_POST['id'] ?? 0);
$log = function (string $action, string $label, string $summary, int $eid, string $elabel) {
    if (function_exists('cdp_activityLog')) {
        cdp_activityLog(['module' => 'freight', 'action' => 'partner_' . $action, 'action_label' => $label, 'verb' => $action === 'create' ? 'create' : 'update', 'outcome' => 'ok', 'summary' => $summary, 'entity_type' => 'freight_partner', 'entity_id' => $eid, 'entity_label' => $elabel]);
    }
};
switch ($op) {
    case 'create':
    case 'update':
        $errors = [];
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 150);
        $type = array_key_exists($_POST['type'] ?? '', sl_frTypes()) ? $_POST['type'] : '';
        if ($name === '') $errors['name'] = 'Partner name is required';
        if ($type === '') $errors['type'] = 'Pick a type';
        $email = trim((string) ($_POST['contact_email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['contact_email'] = 'Not a valid e-mail';
        $rateV = trim((string) ($_POST['rate_value'] ?? '')) === '' ? null : max(0, (float) $_POST['rate_value']);
        $ends = trim((string) ($_POST['contract_ends'] ?? ''));
        $endsDt = $ends !== '' ? (strtotime($ends) ? date('Y-m-d', strtotime($ends)) : null) : null;
        if ($ends !== '' && !$endsDt) $errors['contract_ends'] = 'Not a date';
        if ($errors) {
            sl_json(['success' => false, 'message' => 'Check the form', 'errors' => $errors], 422);
        }
        $vals = [':n' => $name, ':t' => $type, ':c' => mb_substr(trim((string) ($_POST['code'] ?? '')), 0, 20) ?: null, ':cn' => mb_substr(trim((string) ($_POST['contact_name'] ?? '')), 0, 150) ?: null, ':cp' => mb_substr(trim((string) ($_POST['contact_phone'] ?? '')), 0, 60) ?: null, ':ce' => $email ?: null, ':l' => mb_substr(trim((string) ($_POST['lanes'] ?? '')), 0, 255) ?: null, ':rv' => $rateV, ':ru' => mb_substr(trim((string) ($_POST['rate_unit'] ?? '')), 0, 30) ?: null, ':rc' => mb_substr(strtoupper(trim((string) ($_POST['rate_currency'] ?? ''))), 0, 5) ?: null, ':ends' => $endsDt, ':api' => mb_substr(trim((string) ($_POST['api_link'] ?? '')), 0, 190) ?: null, ':notes' => mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 2000) ?: null];
        if ($op === 'create') {
            $db->cdp_query('INSERT INTO cdb_freight_partners (name, type, code, contact_name, contact_phone, contact_email, lanes, rate_value, rate_unit, rate_currency, contract_ends, api_link, notes, active, created_by, created_at) VALUES (:n, :t, :c, :cn, :cp, :ce, :l, :rv, :ru, :rc, :ends, :api, :notes, 1, :by, NOW())');
            $db->bind(':by', $actor);
        } else {
            if (!sl_row('SELECT id FROM cdb_freight_partners WHERE id = :id', [':id' => $id])) sl_json(['success' => false, 'message' => 'Partner not found'], 404);
            $db->cdp_query('UPDATE cdb_freight_partners SET name = :n, type = :t, code = :c, contact_name = :cn, contact_phone = :cp, contact_email = :ce, lanes = :l, rate_value = :rv, rate_unit = :ru, rate_currency = :rc, contract_ends = :ends, api_link = :api, notes = :notes, updated_at = NOW() WHERE id = :id');
            $db->bind(':id', $id);
        }
        foreach ($vals as $k => $v) $db->bind($k, $v);
        $ok = $db->cdp_execute();
        if (!$ok) sl_json(['success' => false, 'message' => 'Could not save the partner']);
        if ($op === 'create') $id = (int) $db->dbh->lastInsertId();
        $log($op, $op === 'create' ? 'Added freight partner' : 'Updated freight partner', ($op === 'create' ? 'added ' : 'updated ') . "$name ($type)", $id, $name);
        sl_json(['success' => true, 'message' => "$name saved", 'redirect' => sl_route('FreightPartners', ['id' => $id])]);
        break;
    case 'rate':
        $row = sl_row('SELECT * FROM cdb_freight_partners WHERE id = :id', [':id' => $id]);
        if (!$row) sl_json(['success' => false, 'message' => 'Partner not found'], 404);
        if (trim((string) ($_POST['rate_value'] ?? '')) === '') sl_json(['success' => false, 'message' => 'Enter the new rate', 'errors' => ['rate_value' => 'Required']], 422);
        $db->cdp_query('UPDATE cdb_freight_partners SET rate_value = :rv, rate_unit = :ru, rate_currency = :rc, updated_at = NOW() WHERE id = :id');
        $db->bind(':rv', max(0, (float) $_POST['rate_value'])); $db->bind(':ru', mb_substr(trim((string) ($_POST['rate_unit'] ?? $row->rate_unit)), 0, 30) ?: null); $db->bind(':rc', mb_substr(strtoupper(trim((string) ($_POST['rate_currency'] ?? $row->rate_currency))), 0, 5) ?: null); $db->bind(':id', $id);
        $ok = $db->cdp_execute();
        if ($ok) $log('rate', 'Updated partner rate', "rate for {$row->name}: {$row->rate_value} → " . (float) $_POST['rate_value'], $id, $row->name);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? 'Rate updated for ' . $row->name : 'Could not update', 'reload' => true]);
        break;
    case 'archive':
        $row = sl_row('SELECT * FROM cdb_freight_partners WHERE id = :id', [':id' => $id]);
        if (!$row) sl_json(['success' => false, 'message' => 'Partner not found'], 404);
        $db->cdp_query('UPDATE cdb_freight_partners SET active = 0, updated_at = NOW() WHERE id = :id');
        $db->bind(':id', $id);
        $ok = $db->cdp_execute();
        if ($ok) $log('archive', 'Archived freight partner', "archived {$row->name}", $id, $row->name);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? $row->name . ' archived' : 'Could not archive', 'redirect' => sl_route('FreightPartners')]);
        break;
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
