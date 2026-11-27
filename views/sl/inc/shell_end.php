      </div>
    </main>
  </div>
</div>
<?php include CDP_ROOT . "/views/sl/inc/mobile_nav.php"; ?>
<?php include CDP_ROOT . "/views/sl/inc/app_install.php"; ?>
<div id="sl-toast" class="sl-toast" role="status" aria-live="polite" hidden></div>
<?php if (!empty($slPasskeys)) include CDP_ROOT . '/views/sl/inc/passkey_manage.php'; ?>
<?php if (!empty($slAiPopup)) include CDP_ROOT . '/views/sl/inc/ai_page.php'; ?>
<?php if (!empty($slIsClient)) include CDP_ROOT . '/views/sl/inc/survey_prompt.php'; ?>
<script src="<?= h(cdp_asset('assets/swiftlane/swiftlane.js')) ?>"></script>
<script src="<?= h(cdp_asset('assets/swiftlane/shortcuts.js')) ?>"></script>
<?php if (!empty($slPasskeys)): ?><script src="<?= h(cdp_asset('dataJs/sl/passkey.js')) ?>"></script><?php endif; ?>
<?php if (!empty($slAiPopup)): ?><script src="<?= h(cdp_asset('dataJs/sl/ai_page.js')) ?>"></script><?php endif; ?>
<?php if (($slKingChat ?? '') === 'dock'): $kcMode = 'dock'; include CDP_ROOT . '/views/sl/inc/king_chat.php'; ?>
<?php elseif (!empty($slIsClient) && sl_self() !== 'support.php' && ($slKingChat ?? '') === ''): ?>
<a class="sl-support-link" href="support.php#hp-chat" aria-label="Chat with support"><i class="ic i-message" aria-hidden="true"></i><span>Help</span></a>
<?php endif; ?>
<?php foreach ((array) ($slPage['scripts'] ?? []) as $slJs): ?>
<script src="<?= h(cdp_asset($slJs)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
// Scheduled automations without cron: at most every 15 minutes, run after the page is sent (staff pages only).
if (empty($slIsClient) && !sl_userIs('driver')) {
    try { require_once CDP_ROOT . '/helpers/automations.php'; if (function_exists('cdp_automationsTick')) cdp_automationsTick(); } catch (Throwable $e) { error_log('[automations] ' . $e->getMessage()); }
}
