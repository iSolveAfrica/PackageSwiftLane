<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************
// Shipping labels (Swift Lane UI: views/sl/pages/Label4x6.php / Label1x2.php).
// ?id=N or ?ids=1,2,3 · &size=46|12 (default from Label Studio) · &type=air|sea
// ?legacy=1 (the previous TCPDF/HTML label) is retired and renders the same label.
require_once("loader.php");
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('print_label');
require_once 'helpers/activity_log.php';
require_once 'views/sl/data/labels.php';
cdp_activityLogDocument();
$ids = array_filter(array_map('intval', explode(',', (string) ($_GET['ids'] ?? ($_GET['id'] ?? '')))));
$ids = array_slice(array_values(array_unique($ids)), 0, 100);
$opt = sl_labelOptions();
$size = (string) ($_GET['size'] ?? ($opt['big']['size'] === '12' ? '12' : '46'));
$size = $size === '12' ? '12' : '46';
$type = ($_GET['type'] ?? 'air') === 'sea' ? 'sea' : 'air';
$board = $size === '12' ? 'Label1x2' : 'Label4x6';
sl_render($board, ['id' => 'air', 'title' => 'Label', 'board' => $board, 'plain' => true], sl_labelPrintData($ids, $size, $type));
