<?php
// Rider detail (Swift Lane UI): profile, today's run sheet, split and settlements.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['view_rider_detail', 'view_user_list']);
require_once 'views/sl/data/riders.php';
$d = sl_riderDetailData($user, $core, (int) ($_GET['id'] ?? 0));
if (!$d) {
    header('Location: ' . sl_route('Drivers'));
    exit;
}
sl_render('RiderDetail', ['id' => 'drivers', 'title' => $d['name'], 'board' => 'RiderDetail'], $d);
