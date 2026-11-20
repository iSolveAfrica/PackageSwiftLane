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
require_once 'helpers/database_import.php';
sl_gate();
if (!$user->cdp_is_Admin()) {
    header('Location: login.php');
    exit;
}
$migrationMessage = '';
$migrationPlan = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $migrationMessage = 'The upload exceeded the server request limit. Set upload_max_filesize to 128M and post_max_size above 128M.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migration_action'])) {
    if ((int)$user->userlevel !== 9 || !defined('CDP_DB_IMPORT_ENABLED') || CDP_DB_IMPORT_ENABLED !== true || !cdp_csrf_validate_request() || CDP_APP_MODE_DEMO !== false) {
        http_response_code(403);
        exit('Database import is available only to the super administrator on an enabled temp environment.');
    }
    try {
        @set_time_limit(600);
        $db = new Conexion();
        if (!($db->dbh instanceof PDO)) throw new RuntimeException('Database connection unavailable.');
        $db->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($_POST['migration_action'] === 'preview') {
            if (!isset($_FILES['database_file']) || $_FILES['database_file']['error'] !== UPLOAD_ERR_OK || $_FILES['database_file']['size'] > 128 * 1024 * 1024 || $_FILES['database_file']['size'] === 0) {
                throw new RuntimeException('Select a .sql, .zip, or .sql.gz file up to 128 MB. Check the server upload limit if the upload fails.');
            }
            $name = (string)$_FILES['database_file']['name'];
            if (!preg_match('/\.(sql|zip|sql\.gz)$/i', $name)) throw new RuntimeException('Only .sql, .zip, and .sql.gz files are accepted.');
            if (!empty($_SESSION['migration_upload']['path']) && is_file($_SESSION['migration_upload']['path'])) unlink($_SESSION['migration_upload']['path']);
            $path = tempnam(sys_get_temp_dir(), 'swl_import_');
            if (!$path) throw new RuntimeException('Could not store the uploaded file.');
            try {
                if (preg_match('/\.zip$/i', $name)) {
                    cdp_import_extract_zip($_FILES['database_file']['tmp_name'], $path);
                } elseif (preg_match('/\.sql\.gz$/i', $name)) {
                    cdp_import_extract_gzip($_FILES['database_file']['tmp_name'], $path);
                } elseif (!move_uploaded_file($_FILES['database_file']['tmp_name'], $path)) {
                    throw new RuntimeException('Could not store the uploaded file.');
                }
            } catch (Throwable $e) {
                unlink($path);
                throw $e;
            }
            chmod($path, 0600);
            $_SESSION['migration_upload'] = ['path' => $path, 'hash' => hash_file('sha256', $path), 'time' => time(), 'name' => $name];
            $migrationPlan = cdp_import_plan($path, $db->dbh);
            $migrationMessage = 'Upload checked against the current temp database.';
        } elseif ($_POST['migration_action'] === 'apply') {
            $upload = $_SESSION['migration_upload'] ?? null;
            if (!$upload || time() - $upload['time'] > 3600 || !is_file($upload['path']) || !hash_equals($upload['hash'], hash_file('sha256', $upload['path']))) throw new RuntimeException('Upload expired or changed. Upload the file again.');
            if (($_POST['confirm_import'] ?? '') !== 'IMPORT') throw new RuntimeException('Type IMPORT to confirm.');
            $tableCount = cdp_import_apply($upload['path'], $db->dbh);
            unlink($upload['path']);
            unset($_SESSION['migration_upload']);
            $migrationMessage = "Imported data into $tableCount tables successfully.";
        }
    } catch (Throwable $e) {
        $migrationMessage = $e->getMessage();
    }
}
if (CDP_APP_MODE_DEMO === false && isset($_GET['create']) && $_GET['create'] == '1') {
    require_once 'helpers/backups_function.php';
    doBackup(); // writes backups/backup_<date>.sql and redirects to backup.php?do=backup&backupok=1
    exit;
}
require_once 'views/sl/data/account_edit.php';
$data = sl_backupData($core);
$data['migrationEnabled'] = (int)$user->userlevel === 9 && defined('CDP_DB_IMPORT_ENABLED') && CDP_DB_IMPORT_ENABLED === true;
$data['migrationMessage'] = $migrationMessage;
$data['migrationPlan'] = $migrationPlan;
sl_render('Backups', ['id' => 'general', 'title' => 'Backup and restore', 'board' => ''], $data);
