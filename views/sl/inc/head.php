<?php
/** <head> for every Swift Lane design page. Expects $slPage (title, board, styles, theme). */
$slTitle = ($slPage['title'] ?? 'Swift Lane') . ' | ' . ($core->site_name ?? 'Swift Lane');
$slBoardCss = $slPage['board'] ? 'assets/swiftlane/pages/' . $slPage['board'] . '.css' : '';
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-param" content="<?= h(cdp_csrf_param()) ?>">
<meta name="csrf-token" content="<?= h(cdp_csrf_token()) ?>">
<meta name="color-scheme" content="light dark">
<title><?= h($slTitle) ?></title>
<?php if (!empty($core->favicon)): ?><link rel="icon" href="assets/<?= h($core->favicon) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/swiftlane.css')) ?>">
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/app.css')) ?>">
<?php if ($slBoardCss && is_file(CDP_ROOT . '/' . $slBoardCss)): ?>
<link rel="stylesheet" href="<?= h(cdp_asset($slBoardCss)) ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/chart-interactions.css')) ?>">
<?php foreach ((array) ($slPage['styles'] ?? []) as $slCss): ?>
<link rel="stylesheet" href="<?= h(cdp_asset($slCss)) ?>">
<?php endforeach; ?>
<script>
(function(){try{var t=localStorage.getItem('sl.theme');if(t==='dark'||(t==='auto'&&matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('sl-dark');}}catch(e){}})();
</script>
