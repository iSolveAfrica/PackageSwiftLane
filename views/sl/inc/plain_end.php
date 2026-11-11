<?php include CDP_ROOT . "/views/sl/inc/mobile_nav.php"; ?>
<?php include CDP_ROOT . "/views/sl/inc/app_install.php"; ?>
<div id="sl-toast" class="sl-toast" role="status" aria-live="polite" hidden></div>
<script src="<?= h(cdp_asset('assets/swiftlane/swiftlane.js')) ?>"></script>
<?php foreach ((array) ($slPage['scripts'] ?? []) as $slJs): ?>
<script src="<?= h(cdp_asset($slJs)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
