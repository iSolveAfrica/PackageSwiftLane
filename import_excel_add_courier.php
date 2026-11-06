<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************
// Import shipments from Excel/CSV (Swift Lane UI: views/sl/pages/ImportData.php).
require_once("loader.php");
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('add_multiple_shipments');
sl_render('ImportData', ['id' => 'air', 'title' => 'Import from Excel', 'board' => 'ImportData'], []);
