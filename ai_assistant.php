<?php
/** Staff AI assistant (StaffAI.dc.html; AiChatMobile is the same page at phone width). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
require_once 'helpers/ai_assistant.php';
sl_gate();
// Super admins reach the page even while it is switched off, to set it up.
if (!cdp_aiCanUse($user) && !cdp_aiCanManage($user)) {
    header('Location: error403.php');
    exit;
}
require_once 'views/sl/data/ai.php';
sl_render('StaffAI', ['id' => 'csai', 'title' => 'AI Assistant', 'board' => 'StaffAI', 'wide' => true, 'styles' => ['assets/swiftlane/pages/AiChatMobile.sheet.css', 'assets/swiftlane/ai-response.css'], 'scripts' => ['assets/vendor/libs/marked/marked.min.js', 'assets/vendor/libs/dompurify/purify.min.js', 'dataJs/sl/ai_response.js', 'dataJs/sl/ai_chat.js']], sl_aiStaffData($user, $core, $userData));
