<?php
// Add a tracking event - Swift Lane UI: views/sl/pages/TrackingUpdate.php.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('track_consolidate_package');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // The old view's POST handler, moved verbatim (views/sl/handlers/consolidate_package_tracking.php); replies JSON.
    require_once 'helpers/sl_legacy_post.php';
    $db = new Conexion;
    sl_legacyPostBegin();
    include 'views/sl/handlers/consolidate_package_tracking.php';
    sl_legacyPostEnd($errors ?? [], ['message' => 'Tracking event saved']);
}
require_once 'views/sl/data/ops_forms.php';
$d = sl_trackUpdateData($user, $core, 'consol_pkg', (int) ($_GET['id'] ?? 0));
sl_render('TrackingUpdate', ['id' => 'shiptrack', 'title' => 'Tracking ' . $d['sum']['track'], 'board' => 'ShipmentTracking', 'scripts' => ['dataJs/sl/ops_forms.js']], $d);
