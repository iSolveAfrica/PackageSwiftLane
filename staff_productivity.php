<?php
// Staff productivity (Swift Lane UI). cdp_spCanView(): permission AND an admin-type role.
require_once 'loader.php';
require_once 'helpers/staff_activity.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_staff_productivity');
if (!cdp_spCanView($user)) {
    header('Location: error403.php');
    exit;
}
require_once 'views/sl/data/staff_activity.php';
sl_render('StaffProductivity', ['id' => 'staff', 'title' => 'Staff productivity', 'board' => 'StaffProductivity', 'wide' => true], sl_staffProductivityData($user, $core));
