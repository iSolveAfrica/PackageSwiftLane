<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************
// Live tracking (Swift Lane UI: views/sl/pages/LiveTracking.php).
// One page for everyone: staff see every air shipment, sea package and consolidation their role and
// agency may see; customers see their own; riders see what is assigned to them.
require_once("loader.php");
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
require_once 'views/sl/data/track_console.php';
$client = sl_userIs('client');
$source = $client ? 'mine' : 'all';
if (!$client && !sl_tcKinds($user, $source)) {
    header('Location: error403.php');
    exit;
}
$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
$filter = (string) ($_GET['f'] ?? 'all');
$tc = sl_trackConsoleData($user, $core, $source, 60, ['q' => $q, 'filter' => $filter]);
$d = ['tc' => $tc, 'client' => $client, 'q' => $q, 'filter' => $tc['filter']];
sl_render('LiveTracking', ['id' => 'livetrack', 'title' => $client ? 'Track my parcels' : 'Live tracking', 'board' => 'LiveTracking', 'wide' => true], $d);
