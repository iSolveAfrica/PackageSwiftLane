<?php
/** <head> for every Swift Lane design page. Expects $slPage (title, board, styles, theme). */
$slTitle = ($slPage['title'] ?? 'Swift Lane') . ' | ' . ($core->site_name ?? 'Swift Lane');
$slBoardCss = $slPage['board'] ? 'assets/swiftlane/pages/' . $slPage['board'] . '.css' : '';
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-param" content="<?= h(cdp_csrf_param()) ?>">
<meta name="csrf-token" content="<?= h(cdp_csrf_token()) ?>">
<meta name="color-scheme" content="light dark">
<script>window.SL_KING = <?= json_encode(function_exists('cdp_kingName') ? cdp_kingName() : 'King', JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<title><?= h($slTitle) ?></title>
<link rel="icon" type="image/png" href="<?= h(cdp_asset('assets/swiftlane/brand/favicon-64.png')) ?>"><link rel="apple-touch-icon" href="<?= h(cdp_asset('assets/swiftlane/brand/swift-mark.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/swiftlane.css')) ?>">
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/app.css')) ?>">
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/color.css')) ?>">
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/ai-page.css')) ?>">
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/chart-cards.css')) ?>">
<?php if ($slBoardCss && is_file(CDP_ROOT . '/' . $slBoardCss)): ?>
<link rel="stylesheet" href="<?= h(cdp_asset($slBoardCss)) ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/chart-interactions.css')) ?>">
<script defer src="<?= h(cdp_asset('dataJs/sl/chart_interactions.js')) ?>"></script>
<?php foreach ((array) ($slPage['styles'] ?? []) as $slCss): ?>
<link rel="stylesheet" href="<?= h(cdp_asset($slCss)) ?>">
<?php endforeach; ?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/experience.css')) ?>">
<?php require_once CDP_ROOT . '/helpers/export_guard.php'; if (!cdp_canExportData()): /* exports are admin-only: hide every export control (the server refuses them too) */ ?>
<style>a[href*="export=csv"],a[href*="export=xls"],a[href*="export=xlsx"],a[href*="_excel.php"],a[href*="format=csv"],a[href*="format=xls"],a[href*="format=xlsx"],a[href*="action=export"],a[href*="op=export"],a[href*="_export_ajax.php"],[data-rv-export],[data-export],button[onclick*="exportExcel"],button[onclick*="_excel"]{display:none!important}</style>
<?php endif; ?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/surface-colors.css')) ?>">
<script defer src="<?= h(cdp_asset('assets/swiftlane/experience.js')) ?>"></script>
<script>
(function(){try{var t=localStorage.getItem('sl.theme');if(t==='dark'||(t==='auto'&&matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('sl-dark');}}catch(e){}})();
</script>

<?php require_once __DIR__ . '/../../../helpers/pwa.php'; sl_pwa_head(); ?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/auth-mobile.css')) ?>">
<?php if (function_exists('sl_userIs') && sl_userIs('client')): require_once CDP_ROOT . '/helpers/sl_appframe.php'; ?>
<meta name="sl-appframe" content="<?= sl_appFrameable() && ($_COOKIE['sl_appview'] ?? '') !== 'off' && empty($slPage['legacy']) ? '1' : '0' ?>">
<script defer src="<?= h(cdp_asset('dataJs/sl/appframe.js')) ?>"></script>
<?php endif; ?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/shortcuts.css')) ?>">
