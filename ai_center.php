<?php
/** AI centre (AiCenter.dc.html): providers, masked keys, knowledge, page ideas, question log, usage. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
require_once 'helpers/ai_assistant.php';
sl_gate();
if (!cdp_aiCanManage($user) && !cdp_aiCanSeeInsights($user)) {
    header('Location: error403.php');
    exit;
}
require_once 'views/sl/data/ai.php';
sl_render('AiCenter', ['id' => 'aicenter', 'title' => 'AI centre', 'board' => 'AiCenter', 'wide' => true, 'scripts' => ['dataJs/sl/ai_center.js']], sl_aiCenterData($user, $core));
