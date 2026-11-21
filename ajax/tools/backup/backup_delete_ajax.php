<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software and its source code are proprietary and confidential    *
// * property of iSolveAfrica Ltd. and were developed specifically for     *
// * Swiftlane.                                                            *
// *                                                                       *
// * The software may not be copied, reproduced, modified, distributed,    *
// * sublicensed, published, or used in whole or in part except as         *
// * expressly permitted under the applicable license or written           *
// * agreement with iSolveAfrica Ltd. Any permitted copies or derivative   *
// * works must retain this copyright notice and all applicable            *
// * proprietary notices.                                                  *
// *                                                                       *
// *************************************************************************

require_once(__DIR__ . '/../../../loader.php');
require_once(__DIR__ . '/../../../helpers/ajax_guard.php');
require_login();
require_permission('view_tools');

// Same gate as backup.php: only administrators manage backups.
if (!$user->cdp_is_Admin()) {
    _ajax_guard_send(403, ['success' => false, 'error' => 'Forbidden', 'message' => 'Administrators only']);
}

  /* == Delete SQL Backup == */
  if (isset($_POST['deleteBackup'])):
      // The name comes from the client: strip any directory part and accept only a
      // plain .sql file that exists in backups/, so "../config/config.php" cannot
      // delete files outside the backups folder.
      $backupDir = realpath(__DIR__ . '/../../../backups');
      $backupName = basename(trim((string) $_POST['deleteBackup']));
      $backupPath = $backupDir ? $backupDir . DIRECTORY_SEPARATOR . $backupName : '';
      if (!$backupDir || !preg_match('/^[A-Za-z0-9._-]+\.sql(\.gz)?$/', $backupName) || !is_file($backupPath)) {
          ?>
	<div class="alert alert-danger" id="success-alert">
        <p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>
            Backup file not found.
       </p>
    </div>
    <?php
          exit;
      }
      $action = @unlink($backupPath);

      ?>

	<div class="alert alert-info" id="success-alert">
        <p><span class="icon-info-sign"></span><i class="close icon-remove-circle"></i>
            Backup deleted successfully!
       </p>
    </div>
    <?php

      
  endif;