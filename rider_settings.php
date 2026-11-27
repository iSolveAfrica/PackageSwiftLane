<?php
// Rider fees and split (Swift Lane UI): cdb_rider_fee_rules.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['manage_rider_fees', 'view_tools']);
require_once 'views/sl/data/riders.php';
require_once 'views/sl/data/rider_admin.php';
$d = sl_riderSettingsData($user, $core);
$d['ra'] = sl_riderAdminData($user);
sl_render('RiderSettings', ['id' => 'rfees', 'title' => 'Rider fees and split', 'board' => 'RiderSettings'], $d);
