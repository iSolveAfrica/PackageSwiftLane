<?php
// Air Consolidations (Consolidations artboard, air mode: cdb_consolidate). Sea lives in consolidate_package_list.php.
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['view_consolidate_list', 'view_consolidate_package_list']);
// Old links (?mode=sea) and sea-only staff go to Sea Consolidations.
$toSea = ($_GET['mode'] ?? '') === 'sea' || !$user->cdp_hasPermission('view_consolidate_list');
// A search link that names a sea consolidation (freight boards, exceptions) opens on the sea page.
$q = trim((string) ($_GET['q'] ?? ''));
if (!$toSea && $q !== '' && $user->cdp_hasPermission('view_consolidate_package_list')) {
    $byCode = "CONCAT(c_prefix, c_no) = :q OR awb_no = :q";
    $toSea = !sl_scalar("SELECT COUNT(*) FROM cdb_consolidate WHERE $byCode", [':q' => $q]) && sl_scalar("SELECT COUNT(*) FROM cdb_consolidate_packages WHERE $byCode", [':q' => $q]);
}
if ($toSea) {
    $keep = array_diff_key($_GET, ['mode' => 1]);
    header('Location: ' . sl_route('ConsolidationsSea', $keep));
    exit;
}
require_once 'views/sl/data/consolidations.php';
// Board: Air / Sea statistics + consolidation console; wide view with the menu collapsed.
sl_render('Consolidations', ['id' => 'consol', 'title' => 'Consolidations', 'board' => 'Consolidations', 'styles' => ['assets/swiftlane/pages/ops.css', 'assets/swiftlane/overview-hero.css']], sl_consolidationsBoard($user, $core));
