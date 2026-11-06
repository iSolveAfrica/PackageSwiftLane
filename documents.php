<?php
// Documents and exports hub (Swift Lane UI: views/sl/pages/Documents.php).
require_once("loader.php");
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['view_documents', 'view_general_reports']);
if (sl_userIs('client')) {
    header('Location: ' . sl_route('AirShipments'));
    exit;
}
require_once 'views/sl/data/documents.php';
sl_render('Documents', ['id' => '', 'title' => 'Documents and exports', 'board' => 'Documents'], sl_documentsData($user, $core));
