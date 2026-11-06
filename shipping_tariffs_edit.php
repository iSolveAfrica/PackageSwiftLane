<?php
/** Rate editor (RateEditor.dc.html). Write: ajax/sl/tariff_save_ajax.php (same validation as ajax/tools/ship_tariffs/*). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('edit_shipping_tariff');
require_once 'views/sl/data/tariffs.php';
$id = (int) ($_GET['id'] ?? 0);
$d = sl_rateEditorData($user, $core, $id);
if ($id && !$d['row']) { header('Location: shipping_tariffs_list.php'); exit; }
sl_render('RateEditor', ['id' => 'tariffs', 'title' => $id ? 'Edit rate' : 'New rate', 'board' => 'RateEditor'], $d);
