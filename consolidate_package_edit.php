<?php
// Edit a consolidation - Swift Lane UI: views/sl/pages/ConsolidationEdit.php.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('edit_consolidate_package');
// Same scope as the detail page (consolidate_view.php): office / agency / driver rules of sl_opsInScope.
require_once 'views/sl/data/ops_common.php';
$slCon = sl_row('SELECT consolidate_id, agency, sender_id, driver_id FROM cdb_consolidate_packages WHERE consolidate_id = :id', [':id' => (int) ($_GET['id'] ?? 0)]);
if ($slCon && !sl_opsInScope($slCon)) { header('Location: ' . sl_route('Consolidations', ['mode' => 'sea'])); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // The old view's POST handler, moved verbatim (views/sl/handlers/consolidate_package_edit.php); replies JSON.
    require_once 'helpers/sl_legacy_post.php';
    $db = new Conexion;
    sl_legacyPostBegin();
    include 'views/sl/handlers/consolidate_package_edit.php';
    sl_legacyPostEnd([], ['message' => $message ?? null, 'ok' => isset($success_script), 'bad' => isset($error_script), 'requireOk' => true, 'redirect' => 'consolidate_package_view.php?id=' . (int) ($_GET['id'] ?? 0)]);
}
require_once 'views/sl/data/ops_forms.php';
$d = sl_consolEditData($user, $core, 'consol_pkg', (int) ($_GET['id'] ?? 0));
sl_render('ConsolidationEdit', ['id' => 'consol_sea', 'title' => 'Edit ' . $d['sum']['track'], 'board' => 'ConsolidationDetail', 'scripts' => ['dataJs/sl/ops_forms.js']], $d);
