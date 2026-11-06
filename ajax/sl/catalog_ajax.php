<?php
/** Save or delete a row of a reference table (catalog.php). */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/sl_catalogs.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
if (CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
$key = (string) ($_POST['c'] ?? '');
$cat = sl_catalog($key);
if (!$cat) {
    sl_json(['success' => false, 'message' => 'Unknown list'], 404);
}
$t = $cat['table'];
$op = (string) ($_POST['op'] ?? '');
$id = (int) ($_POST['id'] ?? 0);
$db = new Conexion();
$log = function (string $action, string $summary, int $rid) use ($key, $cat) {
    if (function_exists('cdp_activityLog')) {
        cdp_activityLog(['module' => 'settings', 'action' => $key . '_' . $action, 'action_label' => ucfirst($action) . ' ' . $cat['noun'], 'verb' => $action === 'delete' ? 'delete' : ($action === 'create' ? 'create' : 'update'), 'outcome' => 'ok', 'summary' => $summary, 'entity_type' => $key, 'entity_id' => $rid, 'entity_label' => $summary]);
    }
};

if ($op === 'delete') {
    require_permission($cat['perm']['edit']);
    if (in_array($id, $cat['locked'] ?? [], true)) {
        sl_json(['success' => false, 'message' => 'This ' . $cat['noun'] . ' is used by the system and cannot be deleted'], 409);
    }
    foreach ($cat['refs'] as [$rt, $rc]) {
        if (sl_tableExists($rt) && sl_columnExists($rt, $rc) && (int) sl_scalar("SELECT COUNT(*) FROM `$rt` WHERE `$rc` = :id", [':id' => $id]) > 0) {
            sl_json(['success' => false, 'message' => 'This ' . $cat['noun'] . ' is in use and cannot be deleted'], 409);
        }
    }
    $db->cdp_query("DELETE FROM `$t` WHERE id = :id");
    $db->bind(':id', $id);
    $ok = $db->cdp_execute();
    if ($ok) {
        $log('delete', 'deleted ' . $cat['noun'] . ' #' . $id, $id);
    }
    sl_json(['success' => (bool) $ok, 'message' => $ok ? ucfirst($cat['noun']) . ' deleted' : 'Could not delete']);
}

if ($op !== 'save') {
    sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
require_permission($id > 0 ? $cat['perm']['edit'] : $cat['perm']['create']);
$vals = [];
$errors = [];
foreach ($cat['fields'] as $col => $f) {
    $v = trim((string) ($_POST[$col] ?? ''));
    if (!empty($f[2]) && $v === '') {
        $errors[$col] = 'Required';
        continue;
    }
    switch ($f[1]) {
        case 'number':
        case 'lookup':
            $v = $v === '' ? null : (int) $v;
            break;
        case 'select':
            if (!array_key_exists($v, $f[3])) {
                $errors[$col] = 'Choose a value';
            }
            break;
        case 'color':
            if ($v !== '' && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $v)) {
                $errors[$col] = 'Use a colour like #1a73e8';
            }
            break;
        default:
            $v = mb_substr($v, 0, 1000);
    }
    $vals[$col] = $v;
}
if ($errors) {
    sl_json(['success' => false, 'message' => 'Check the highlighted fields', 'errors' => $errors], 422);
}
$cols = array_keys($vals);
if ($id > 0) {
    $set = implode(', ', array_map(fn($c) => "`$c` = :$c", $cols));
    $db->cdp_query("UPDATE `$t` SET $set WHERE id = :id");
    $db->bind(':id', $id);
} else {
    $db->cdp_query("INSERT INTO `$t` (`" . implode('`, `', $cols) . "`) VALUES (:" . implode(', :', $cols) . ")");
}
foreach ($vals as $c => $v) {
    $db->bind(':' . $c, $v);
}
$ok = $db->cdp_execute();
if ($ok) {
    $rid = $id > 0 ? $id : (int) $db->dbh->lastInsertId();
    $label = (string) reset($vals);
    $log($id > 0 ? 'update' : 'create', ($id > 0 ? 'updated ' : 'added ') . $cat['noun'] . ' ' . $label, $rid);
}
sl_json(['success' => (bool) $ok, 'message' => $ok ? ucfirst($cat['noun']) . ' saved' : 'Could not save: ' . $db->cdp_error()]);
