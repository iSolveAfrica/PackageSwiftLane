<?php
/** Operating costs (CostSettings.dc.html). Everyone with view_tools can read; only the super admin edits. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
if (!sl_userIs('superadmin') && !$user->cdp_hasPermission(['manage_operating_costs', 'view_tools'])) {
    header('Location: error403.php');
    exit;
}
require_once 'views/sl/data/costs.php';
sl_render('CostSettings', ['id' => 'costs', 'title' => 'Operating costs', 'board' => 'CostSettings'], sl_costSettingsData($user, $core));
