<?php
/**
 * Legacy bridge (top bar): legacy content pages are wrapped in the Swift Lane
 * shell. This include opens the shell (header, rail, sidebar, <main>);
 * views/inc/left_sidebar.php is intentionally empty and views/inc/footer.php
 * closes the shell at shutdown. New pages use views/sl/inc/shell_start.php.
 */
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/sl_nav.php';
require_once __DIR__ . '/page_header.php';
if (!isset($slPage) || !is_array($slPage)) {
    $slPage = [];
}
$slPage += ['id' => sl_navIdForBoard(sl_boardForScript()), 'title' => '', 'board' => '', 'legacy' => true, 'mainClass' => 'sl-legacy', 'wide' => true];
if (!isset($user) || !($user instanceof User)) {
    $user = new User();
}
if (!isset($core) || !($core instanceof Core)) {
    $core = new Core();
}
require __DIR__ . '/../sl/inc/shell_vars.php';
require_once CDP_ROOT . '/helpers/sl_mobile.php';
echo '<script>document.body.dataset.mobileRole=' . json_encode(sl_bodyRole()) . ';</script>';
include __DIR__ . '/../sl/inc/shell_frame.php';
register_shutdown_function(function () use ($user, $core, $slAiPopup, $slIsClient, $slFirst) {
    include CDP_ROOT . "/views/sl/inc/mobile_nav.php";
    include CDP_ROOT . "/views/sl/inc/app_install.php";
    echo "\n</div></main></div></div>\n<div id=\"sl-toast\" class=\"sl-toast\" role=\"status\" aria-live=\"polite\" hidden></div>\n<script src=\"" . h(cdp_asset('assets/swiftlane/swiftlane.js')) . "\"></script>\n";
    // the AI pop-up on legacy pages too (same as views/sl/inc/shell_end.php)
    if (!empty($slAiPopup)) {
        $slPage = ['board' => '', 'title' => trim(strip_tags((string) ($GLOBALS['title'] ?? ''))) ?: ($core->site_name ?? 'this page')];
        echo '<link rel="stylesheet" href="' . h(cdp_asset('assets/swiftlane/ai-page.css')) . '">';
        include CDP_ROOT . '/views/sl/inc/ai_page.php';
        echo '<script src="' . h(cdp_asset('dataJs/sl/ai_page.js')) . '"></script>' . "\n";
    }
    echo "</body></html>\n";
});
