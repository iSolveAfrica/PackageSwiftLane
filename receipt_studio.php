<?php
// Receipt Studio (Swift Lane UI: views/sl/pages/ReceiptStudio.php): choose which sections the
// package receipt shows, drag them into order, preview it live. Saved in cdb_sl_settings receipt_options.
require_once("loader.php");
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_tools');
require_once 'views/sl/data/labels.php';
$d = sl_receiptStudioData($user, (int) ($_GET['id'] ?? 0), trim((string) ($_GET['q'] ?? '')), (string) ($_GET['as'] ?? ''));
sl_render('ReceiptStudio', ['id' => 'rcptstudio', 'title' => 'Receipt Studio', 'board' => 'ReceiptStudio'], $d);
