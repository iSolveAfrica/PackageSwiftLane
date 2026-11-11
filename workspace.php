<?php
// My Workspace: the screen for the person's job role (warehouse operator,
// finance officer, dispatch officer, customer service, branch manager,
// driver, partner agent), with their shift, tasks and announcements.
// See helpers/staff_workspace.php.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
require_once 'helpers/querys.php';
require_once 'helpers/staffops.php';
if (cdp_opsRank($user) < 1) {
    header('Location: index.php');
    exit;
}
cdp_opsRender('Workspace', 'workspace', 'My Workspace', ['dataJs/workspace.js'], ['ready' => cdp_staffEnsureTables()]);
