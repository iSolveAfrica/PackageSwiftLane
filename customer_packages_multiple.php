<?php
// Bulk packages (one package per row) (Swift Lane UI: views/sl/pages/ShipmentForm.php, mode "package_multiple").
// Saves through the legacy endpoint the old view used; see views/sl/data/ops_forms.php.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('add_multiple_packages');
if (sl_userIs('client')) { header('Location: error403.php'); exit; }
require_once 'views/sl/data/ops_forms.php';
$d = sl_shipFormData($user, $core, 'package_multiple', (int) ($_GET['id'] ?? 0));
sl_render('ShipmentForm', ['id' => 'sea', 'title' => $d['title'], 'board' => 'NewShipment', 'scripts' => ['dataJs/sl/ops_forms.js', 'dataJs/sl/shipment_form.js']], $d);
