<?php
// Package Intake (Swift Lane UI: views/sl/pages/PackageIntake.php): register a
// package by carrier tracking number, prefilled from the matching pre-alert.
require_once("loader.php");
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['package_intake', 'add_package']);
require_once 'views/sl/data/intake.php';
sl_render('PackageIntake', ['id' => 'sea', 'title' => 'Package Intake', 'board' => 'PackageIntake', 'scripts' => ['dataJs/sl/package_intake.js']], sl_intakeData($user, $core));
