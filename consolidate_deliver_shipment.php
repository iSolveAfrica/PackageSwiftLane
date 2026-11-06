<?php
// Hand over (deliver / picked up) - Swift Lane UI: views/sl/pages/Handover.php.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('deliver_consolidate');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // The old view's POST handler, moved verbatim (views/sl/handlers/consolidate_deliver.php); replies JSON.
    require_once 'helpers/sl_legacy_post.php';
    $db = new Conexion;
    sl_legacyPostBegin();
    include 'views/sl/handlers/consolidate_deliver.php';
    sl_legacyPostEnd($errors ?? [], ['message' => 'Hand-over saved']);
}
require_once 'views/sl/data/ops_forms.php';
$d = sl_handoverData($user, $core, 'consol', (int) ($_GET['id'] ?? 0));
sl_render('Handover', ['id' => 'consol', 'title' => 'Hand over ' . $d['sum']['track'], 'board' => 'HandOut', 'styles' => ['assets/swiftlane/pages/ops.css'], 'scripts' => ['dataJs/sl/ops_forms.js']], $d);
