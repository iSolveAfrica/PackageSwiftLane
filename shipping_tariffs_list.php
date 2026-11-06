<?php
/** Shipping tariffs (Tariffs.dc.html): cdb_shipping_fees by mode and route, plus customer-specific rates. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_shipping_tariffs');
require_once 'views/sl/data/tariffs.php';
$d = sl_tariffsData($user, $core);
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rate-card-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Mode', 'Route', 'Band', 'Price', 'Minimum', 'Volumetric divisor', 'Price per mile', 'Customer']);
    foreach ($d['groups'] as $g) foreach ($g['rows'] as $r) fputcsv($out, [$g['name'], $r['route'], $r['band'], $r['price'], $r['min'], $r['vol'], $r['mile'], $r['client']]);
    fclose($out);
    exit;
}
sl_render('Tariffs', ['id' => 'tariffs', 'title' => 'Shipping Tariffs', 'board' => 'Tariffs'], $d);
