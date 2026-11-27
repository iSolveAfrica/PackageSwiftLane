<?php
/** Swift Lane shell: computes the shell variables, prints <head>, body and the frame. */
require CDP_ROOT . '/views/sl/inc/shell_vars.php';
// Customers on a computer: the page in the app window (helpers/sl_appframe.php).
if (!empty($slIsClient) && empty($slPage['legacy'])) {
    require_once CDP_ROOT . '/helpers/sl_appframe.php';
    if (sl_appFrameWanted()) { include CDP_ROOT . '/views/sl/inc/app_frame.php'; exit; }
}
?>
<!DOCTYPE html>
<html lang="<?= h($config_lang ?? 'en') ?>" dir="<?= h($direction_layout ?? 'ltr') ?>">
<head>
<?php include CDP_ROOT . '/views/sl/inc/head.php'; ?>
</head>
<?php require_once CDP_ROOT . "/helpers/sl_mobile.php"; ?>
<body data-mobile-role="<?= h(sl_bodyRole()) ?>" class="sl-body-page">
<?php include CDP_ROOT . '/views/sl/inc/shell_frame.php'; ?>
