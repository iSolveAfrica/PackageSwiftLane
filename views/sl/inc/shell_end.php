      </div>
    </main>
    <aside class="sl-shortcuts" data-shortcuts-user="<?= (int) $_SESSION['userid'] ?>" aria-label="My shortcuts"><strong>Shortcuts</strong><div class="sl-shortcuts-list" aria-live="polite"></div><p>Drag a menu item here, or use its pin button.</p></aside>
  </div>
</div>
<?php include CDP_ROOT . "/views/sl/inc/mobile_nav.php"; ?>
<?php include CDP_ROOT . "/views/sl/inc/app_install.php"; ?>
<div id="sl-toast" class="sl-toast" role="status" aria-live="polite" hidden></div>
<script src="<?= h(cdp_asset('assets/swiftlane/swiftlane.js')) ?>"></script>
<script src="<?= h(cdp_asset('assets/swiftlane/shortcuts.js')) ?>"></script>
<?php if (!empty($slIsClient) && sl_self() !== 'support.php'): ?>
<a class="sl-support-link" href="support.php#hp-chat" aria-label="Chat with support"><i class="ic i-message" aria-hidden="true"></i><span>Help</span></a>
<?php endif; ?>
<?php foreach ((array) ($slPage['scripts'] ?? []) as $slJs): ?>
<script src="<?= h(cdp_asset($slJs)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
