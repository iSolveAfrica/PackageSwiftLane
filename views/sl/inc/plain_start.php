<?php
/** Shell-less wrapper for auth, public and print pages of the design. */
require_once CDP_ROOT . '/helpers/sl_view.php';
?>
<!DOCTYPE html>
<html lang="<?= h($config_lang ?? 'en') ?>" dir="<?= h($direction_layout ?? 'ltr') ?>">
<head>
<?php include CDP_ROOT . '/views/sl/inc/head.php'; ?>
</head>
<body class="sl-body-page sl-plain">
<?php if (!empty($_SESSION['sl_flash'])): $slFlash = $_SESSION['sl_flash']; unset($_SESSION['sl_flash']); ?>
<div class="sl-banner <?= h($slFlash['type'] ?? 'ok') ?> sl-flash-float" role="status"><?= h($slFlash['text'] ?? '') ?></div>
<?php endif; ?>
