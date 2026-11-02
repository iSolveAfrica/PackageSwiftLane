<?php
// *************************************************************************
// * The chat button for customers (bottom right on every page except      *
// * Help & Support itself). It is only a button until it is opened: the   *
// * chat is built and its settings fetched on the first click, so pages   *
// * that never use it pay nothing. Included from views/inc/footer.php.    *
// *************************************************************************

require_once __DIR__ . '/../../helpers/rbac.php';
if (!function_exists('cdp_roleRankById') || !isset($user) || !is_object($user) || empty($user->logged_in)) {
    return;
}
$lvl = (int) $user->userlevel;
if (cdp_roleRankById($lvl) !== 0 || cdp_roleHasFlag($lvl, 'is_driver') || cdp_roleHasFlag($lvl, 'is_agency')) {
    return;
}
if (basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'support.php') {
    return;
}
?>
<button type="button" class="swl-cs-launch" id="cs_launch" aria-label="Chat with support" aria-expanded="false" aria-controls="cs_pop">
    <iconify-icon icon="solar:chat-round-dots-bold"></iconify-icon>
    <span class="swl-cs-launch-badge" id="cs_launch_badge" aria-hidden="true"></span>
</button>
<div class="swl-cs-pop" id="cs_pop" role="dialog" aria-label="Support chat"></div>
<script>window.CDP_CS_WIDGET = { url: 'ajax/support/customer_ajax.php', page: 'support.php' };</script>
<script src="<?= cdp_asset('dataJs/support_common.js') ?>"></script>
<script src="<?= cdp_asset('dataJs/support_chat.js') ?>"></script>
<script src="<?= cdp_asset('dataJs/support_widget.js') ?>"></script>
