<?php
// Sea packages list (SeaPackages artboard). Legacy view: views/customer_packages/customer_packages_list.php.
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_package_list');
require_once 'views/sl/data/packages.php';
$d = sl_seaPackagesData($user, $core);
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sea_packages_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Package', 'Date', 'Customer', 'Locker', 'Container', 'Items', 'Contents', 'Weight', 'Total', 'Payment', 'Status']);
    foreach ($d['rows'] as $r) { fputcsv($out, [$r['trk'], $r['date'], $r['name'], $r['locker'], $r['box'], $r['items'], $r['what'], $r['w'], $r['amt'], $r['pay'], $r['st']]); }
    exit;
}
if (in_array($_GET['panel'] ?? '', ['tower','tracking'], true) && !$d['isClient']) {
    require_once 'views/sl/data/sea_tower.php';
    require_once 'helpers/sl_nav.php';
    $d['tower'] = sl_seaTowerData($user, $core, $d);
    $vector = 'assets/template/assets/extra-libs/jvector/';
    sl_render('SeaTower', [
        'id'=>'sea', 'title'=>'Sea shipping control tower', 'board'=>'SeaPackages',
        'styles'=>['assets/swiftlane/pages/SeaTower.css', $vector.'jquery-jvectormap-2.0.2.css'],
        'scripts'=>['assets/js/jquery.min.js', $vector.'jquery-jvectormap-2.0.2.min.js',
            $vector.'jquery-jvectormap-world-mill-en.js','dataJs/sl/sea_tower.js']
    ], $d);
    return;
}
sl_render('SeaPackages', ['id' => 'sea', 'title' => sl_userIs('client') ? 'My sea shipments' : 'Sea Packages', 'board' => 'SeaPackages', 'styles' => ['assets/swiftlane/pages/ops.css']], $d);
