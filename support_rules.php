<?php
/** Ticket Rules (CsRules.dc.html). Staff rank 2+; administrators (rank 3+) change settings. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
require_once 'helpers/support_king.php';
sl_gate();
if (!cdp_csIsAgent($user)) {
    header('Location: error403.php');
    exit;
}
require_once 'views/sl/data/support.php';
sl_render('CsRules', ['id' => 'csrules', 'title' => 'Ticket Rules', 'board' => 'CsRules', 'scripts' => ['dataJs/sl/cs_common.js', 'dataJs/sl/cs_rules.js']], sl_csRulesData($user, $core));
