<?php
// Add a tracking event - Swift Lane UI: views/sl/pages/TrackingUpdate.php.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('track_package');

require_once 'views/sl/data/ops_forms.php';
$d = sl_trackUpdateData($user, $core, 'package', (int) ($_GET['id'] ?? 0));
sl_render('TrackingUpdate', ['id' => 'shiptrack', 'title' => 'Tracking ' . $d['sum']['track'], 'board' => 'ShipmentTracking', 'scripts' => ['dataJs/sl/ops_forms.js']], $d);
