<?php
// Package detail (PackageDetail artboard). Legacy view (retired): views/customer_packages/customer_packages_view.php.
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['view_package_details', 'view_package_list']);
require_once 'views/sl/data/packages.php';
$d = sl_packageDetailData($user, $core, (int) ($_GET['id'] ?? 0));
if (!$d) { header('Location: ' . sl_route('SeaPackages')); exit; }
sl_render('PackageDetail', ['id' => 'sea', 'title' => 'Package ' . $d['trk'], 'board' => 'PackageDetail', 'styles' => ['assets/swiftlane/pages/ops.css']], $d);
