      </div>
    </main>
  </div>
</div>
<div id="sl-toast" class="sl-toast" role="status" aria-live="polite" hidden></div>
<script src="<?= h(cdp_asset('assets/swiftlane/swiftlane.js')) ?>"></script>
<?php if (!empty($slIsClient) && sl_self() !== 'support.php'): ?>
<a class="sl-support-link" href="support.php#hp-chat" aria-label="Chat with support"><i class="ic i-message" aria-hidden="true"></i><span>Help</span></a>
<?php endif; ?>
<?php foreach ((array) ($slPage['scripts'] ?? []) as $slJs): ?>
<script src="<?= h(cdp_asset($slJs)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
