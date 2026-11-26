<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************
// Packing list of one consolidation (air or sea): customer -> packages -> items, with weights, dangerous
// goods in their own section. ?format=csv downloads it for Excel; the page itself prints / saves as PDF.
// Data: views/sl/data/consolidations.php (sl_packingListData). Staff only.
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
$type = ($_GET['type'] ?? 'air') === 'sea' ? 'sea' : 'air';
sl_gate($type === 'sea' ? ['print_consolidate_package', 'view_consolidate_package', 'view_consolidate_package_list'] : ['print_consolidate', 'view_consolidate', 'view_consolidate_list']);
require_once 'views/sl/data/consolidations.php';
$d = sl_packingListData($user, $core, (int) ($_GET['id'] ?? 0), $type);
if (!$d) { header('Location: error403.php'); exit; }
require_once 'helpers/activity_log.php';
if (function_exists('cdp_activityLogDocument')) cdp_activityLogDocument();

if (($_GET['format'] ?? '') === 'csv') {
    $name = 'packing-list-' . preg_replace('/[^A-Za-z0-9_-]/', '', $d['code']) . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 marker so Excel reads names correctly
    // A leading = + - @ would run as a formula in Excel: prefix such cells with an apostrophe.
    $cell = fn($v) => is_string($v) && $v !== '' && strpos('=+-@', $v[0]) !== false ? "'" . $v : $v;
    $row = fn(array $r) => fputcsv($out, array_map($cell, $r));
    $row([$d['site'] . ' packing list', $d['code'], ($d['sea'] ? 'Sea' : 'Air') . ' consolidation', $d['status']]);
    foreach ($d['facts'] as [$k, $v]) $row([$k, $v]);
    $row([]);
    $u = $d['unit'];
    foreach (['normal' => 'Normal goods', 'dg' => 'Dangerous goods'] as $sk => $sl) {
        if (empty($d['sections'][$sk])) continue;
        $row([strtoupper($sl)]);
        $row(['Customer', 'Locker', 'S/N', 'Swift tracking', 'Postal tracking', 'Package weight (' . $u . ')', 'Qty', 'Item', 'Item weight (' . $u . ')']);
        foreach ($d['sections'][$sk] as $c) {
            foreach ($c['pk'] as $p) {
                $its = $p['items'] ?: [['qty' => '', 'what' => '', 'w' => '']];
                foreach ($its as $i => $it) $row([$c['name'], $c['locker'], $i ? '' : $p['sn'], $i ? '' : $p['code'], $i ? '' : $p['post'], $i ? '' : number_format($p['w'], 2, '.', ''), $it['qty'], $it['what'], $it['w'] === '' ? '' : number_format((float) $it['w'], 2, '.', '')]);
            }
            $row(['', '', '', $c['name'] . ' total', count($c['pk']) . ' packages', number_format($c['w'], 2, '.', ''), $c['pcs']]);
        }
        $t = $d['totals'][$sk];
        $row(['', '', '', $sl . ' total', $t['pk'] . ' packages', number_format($t['w'], 2, '.', ''), $t['pcs'], $t['cust'] . ($t['cust'] === 1 ? ' customer' : ' customers')]);
        $row([]);
    }
    $t = $d['totals']['all'];
    $row(['', '', '', 'GRAND TOTAL', $t['pk'] . ' packages', number_format($t['w'], 2, '.', ''), $t['pcs'], $t['cust'] . ($t['cust'] === 1 ? ' customer' : ' customers')]);
    fclose($out);
    exit;
}
sl_render('PackingList', ['id' => 'consol', 'title' => 'Packing list ' . $d['code'], 'board' => 'PackingList', 'plain' => true], $d);
