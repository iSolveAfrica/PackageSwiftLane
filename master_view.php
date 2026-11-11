<?php
// Gerald Master View: management and administrative dashboard. Administrators
// see the whole business; branch managers and finance officers their own
// branches. See helpers/mgmt.php and docs/MASTER_VIEW.md.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
require_once 'helpers/querys.php';
require_once 'helpers/mgmt.php';
if (!cdp_mgmtCanView($user)) {
    header('Location: error403.php');
    exit;
}
cdp_opsRender('MasterView', 'masterview', 'Gerald Master View', ['dataJs/master_view.js'],
    ['ready' => cdp_mgmtEnsureTables(), 'isAdmin' => cdp_opsRank($user) >= 3, 'currency' => (string) ($core->currency ?? '')]);
