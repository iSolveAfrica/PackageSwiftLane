<?php
// Partner Agent Portal: the agent's shipments, commission rule, statements
// and announcements. Agent accounts see their own; administrators can open
// any agent's (?agent_id=). See helpers/staff_workspace.php.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
require_once 'helpers/querys.php';
require_once 'helpers/staffops.php';
if (cdp_staffJobRole($user) !== 'agent' && cdp_opsRank($user) < 3) {
    header('Location: error403.php');
    exit;
}
cdp_opsRender('AgentPortal', 'agentportal', 'Partner Portal', ['dataJs/agent_portal.js'], ['ready' => cdp_staffEnsureTables(), 'agentId' => (int) ($_GET['agent_id'] ?? 0)]);
