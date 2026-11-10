<?php
/** Swift Lane shell: computes the shell variables, prints <head>, body and the frame. */
require CDP_ROOT . '/views/sl/inc/shell_vars.php';
?>
<!DOCTYPE html>
<html lang="<?= h($config_lang ?? 'en') ?>" dir="<?= h($direction_layout ?? 'ltr') ?>">
<head>
<?php include CDP_ROOT . '/views/sl/inc/head.php'; ?>
</head>
<?php require_once CDP_ROOT . "/helpers/sl_mobile.php"; ?>
<body data-mobile-role="<?= h(sl_mobileRole()) ?>" class="sl-body-page">
<?php include CDP_ROOT . '/views/sl/inc/shell_frame.php'; ?>
