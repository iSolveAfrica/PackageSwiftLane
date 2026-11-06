<?php
// Register a sea package (NewPackage artboard). Legacy view: views/customer_packages/customer_packages_add.php.
require_once 'loader.php';
$user = new User(); $core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('add_package');
if (sl_userIs('client')) { header('Location: error403.php'); exit; }
require_once 'views/sl/data/packages.php';
sl_render('NewPackage', ['id' => 'sea', 'title' => 'New Package', 'board' => 'NewPackage', 'styles' => ['assets/swiftlane/pages/ops.css']], sl_newPackageData($user, $core));
