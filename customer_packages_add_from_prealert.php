<?php
// Register a package from a pre-alert (Swift Lane UI: views/sl/pages/ShipmentForm.php, mode "prealert").
// Saves through the legacy endpoint the old view used; see views/sl/data/ops_forms.php.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('create_pre_alert');
if (sl_userIs('client')) { header('Location: error403.php'); exit; }
require_once 'views/sl/data/ops_forms.php';
$d = sl_shipFormData($user, $core, 'prealert', (int) ($_GET['id'] ?? 0));
sl_render('ShipmentForm', ['id' => 'sea', 'title' => $d['title'], 'board' => 'NewShipment', 'scripts' => ['dataJs/sl/ops_forms.js', 'dataJs/sl/shipment_form.js']], $d);
