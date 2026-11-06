<?php
// Consolidation detail, air or sea (ConsolidationDetail / ConsolidationAir artboards): consolidate_view.php?id=&type=sea|air.
// Consolidation detail (design system).
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
$type = ($_GET['type'] ?? 'air') === 'sea' ? 'sea' : 'air';
sl_gate($type === 'sea' ? ['view_consolidate_package', 'view_consolidate_package_list'] : ['view_consolidate', 'view_consolidate_list']);
// The old ?legacy=1 view is retired; the parameter is ignored and the design page renders.
require_once 'views/sl/data/consolidations.php';
$d = sl_consolidationDetailData($user, $core, (int) ($_GET['id'] ?? 0), $type);
if (!$d) { header('Location: ' . sl_route('Consolidations', ['mode' => $type])); exit; }
$board = $type === 'sea' ? 'ConsolidationDetail' : 'ConsolidationAir';
sl_render($board, ['id' => 'consol', 'title' => $d['code'], 'board' => $board, 'styles' => ['assets/swiftlane/pages/ops.css']], $d);
