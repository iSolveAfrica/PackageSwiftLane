<?php
/** Swift Lane shell: computes the shell variables, prints <head>, body and the frame. */
require CDP_ROOT . '/views/sl/inc/shell_vars.php';
?>
<!DOCTYPE html>
<html lang="<?= h($config_lang ?? 'en') ?>" dir="<?= h($direction_layout ?? 'ltr') ?>">
<head>
<?php include CDP_ROOT . '/views/sl/inc/head.php'; ?>
</head>
<body class="sl-body-page">
<?php include CDP_ROOT . '/views/sl/inc/shell_frame.php'; ?>
