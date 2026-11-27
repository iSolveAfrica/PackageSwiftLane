<?php
/** Phone frame, bottom: wraps the template in .mx with its classes, adds the tab bar, the install prompt and scripts. */
$slPhoneBody = ob_get_clean();
$slNav = $slPhone['nav'] !== '' ? $slPhone['nav'] : sl_phoneNavFor();
$slCls = trim('mx ' . $slPhone['cls'] . ($slNav === 'dev' ? ' dk' : '') . ($slNav === 'rider' && strpos($slPhone['cls'], 'rs-r') === false ? ' rs-r' : ''));
?>
<div class="<?= h($slCls) ?>" id="sl-app"><?= $slPhoneBody ?><?= $slNav !== 'none' ? sl_phoneNav($slNav, (string) $slPhone['tab']) : '' ?></div>
<?php include CDP_ROOT . '/views/sl/inc/app_install.php'; ?>
<div id="sl-toast" class="sl-toast" role="status" aria-live="polite" hidden></div>
<script src="<?= h(cdp_asset('assets/swiftlane/swiftlane.js')) ?>"></script>
<script src="<?= h(cdp_asset('dataJs/sl/mx.js')) ?>"></script>
<?php foreach (array_unique(array_merge((array) ($slPage['phoneScripts'] ?? []), (array) $slPhone['scripts'])) as $slJs): ?>
<script src="<?= h(cdp_asset($slJs)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
