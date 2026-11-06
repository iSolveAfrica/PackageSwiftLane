<?php
// Hand over (deliver / picked up) - Swift Lane UI: views/sl/pages/Handover.php.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('deliver_shipment');

require_once 'views/sl/data/ops_forms.php';
$d = sl_handoverData($user, $core, 'shipment', (int) ($_GET['id'] ?? 0));
sl_render('Handover', ['id' => 'air', 'title' => 'Hand over ' . $d['sum']['track'], 'board' => 'HandOut', 'styles' => ['assets/swiftlane/pages/ops.css'], 'scripts' => ['dataJs/sl/ops_forms.js']], $d);
