<?php
// Edit shipment (Swift Lane UI: views/sl/pages/ShipmentForm.php, mode "edit").
// Saves through the legacy endpoint the old view used; see views/sl/data/ops_forms.php.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('edit_shipment');
if ((int) $user->userlevel === 1) { header('Location: error403.php'); exit; } // customers are view-only
require_once 'views/sl/data/ops_forms.php';
$d = sl_shipFormData($user, $core, 'edit', (int) ($_GET['id'] ?? 0));
sl_render('ShipmentForm', ['id' => 'air', 'title' => $d['title'], 'board' => 'NewShipment', 'scripts' => ['dataJs/sl/ops_forms.js', 'dataJs/sl/shipment_form.js']], $d);
