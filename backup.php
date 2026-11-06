<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************
// Database backup and restore (Swift Lane design: views/sl/pages/Backups.php).
// ?do=backup&create=1 makes a backup (helpers/backups_function.php doBackup(), then ?backupok=1).
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate();
if (!$user->cdp_is_Admin()) {
    header('Location: login.php');
    exit;
}
if (CDP_APP_MODE_DEMO === false && isset($_GET['create']) && $_GET['create'] == '1') {
    require_once 'helpers/backups_function.php';
    doBackup(); // writes backups/backup_<date>.sql and redirects to backup.php?do=backup&backupok=1
    exit;
}
require_once 'views/sl/data/account_edit.php';
sl_render('Backups', ['id' => 'general', 'title' => 'Backup and restore', 'board' => ''], sl_backupData($core));
