<?php
/** Phone frame, top: <head> of every page plus the phone app stylesheet, then the design's .mx frame. */
require_once CDP_ROOT . '/helpers/sl_phone.php';
require_once CDP_ROOT . '/helpers/sl_mobile.php';
require_once CDP_ROOT . '/helpers/sl_mx.php';   // card, row, KPI and switch helpers every template may use
require_once CDP_ROOT . '/helpers/sl_nav.php';
$slPhone = ($slPage['phone'] ?? []) + ['nav' => '', 'tab' => '', 'cls' => '', 'scripts' => []];
$slPage['styles'] = array_merge((array) ($slPage['styles'] ?? []), ['assets/swiftlane/pwa/mx.css']);
$slPage['board'] = ''; // the web page's own stylesheet is for the web layout
?>
<!DOCTYPE html>
<html lang="<?= h($config_lang ?? 'en') ?>" dir="<?= h($direction_layout ?? 'ltr') ?>" class="mx-html">
<head>
<?php include CDP_ROOT . '/views/sl/inc/head.php'; ?>
</head>
<body class="mx-page" data-mobile-role="<?= h(sl_bodyRole()) ?>" data-phone-view="<?= h($slPhoneView) ?>">
<?php if (!empty($_SESSION['sl_flash'])): $slFlash = $_SESSION['sl_flash']; unset($_SESSION['sl_flash']); ?>
<div class="sl-banner <?= h($slFlash['type'] ?? 'ok') ?> sl-flash-float" role="status"><?= h($slFlash['text'] ?? '') ?></div>
<?php endif; ?>
<?php ob_start(); ?>
