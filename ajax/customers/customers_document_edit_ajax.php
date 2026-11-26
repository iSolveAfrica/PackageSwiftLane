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



ini_set('display_errors', 0);
require_once("../../loader.php");
require_once("../../helpers/profile.php");
require_once("../../helpers/querys.php");
require_once("../../helpers/ajax_guard.php");
require_login();
require_permission('edit_client_document'); // was login-only



$db = new Conexion;
$user = new User;
$core = new Core;
$errors = array();

$userData = $user->cdp_getUserData();

if (CDP_APP_MODE_DEMO === true) {
?>

    <div class="alert alert-warning" id="success-alert">
        <p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>
            <span>Error! </span> There was an error processing the request
        <ul class="error">

            <li>
                <i class="icon-double-angle-right"></i>
                This is a demo version, this action is not allowed. Contact iSolveAfrica Ltd. to enable the full version of Swiftlane.

            </li>


        </ul>
        </p>
    </div>
    <?php
} else {
    

    // Verifica si hay errores en el formulario (si tienes esta lógica implementada)
    if (empty($errors)) {
        header('Content-type: application/json; charset=UTF-8');
        $response = array();

        // Ruta donde se guardarán las imágenes del document (ajusta según tu estructura)
        $upload_dir = realpath('../../assets/uploads/documents') . '/';

        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // side=front (default) writes cdb_users.document_photo, side=back writes document_photo_back
        // (that column exists once migrations/2026_11_26_id_document_back.sql ran).
        $side = (($_POST['side'] ?? 'front') === 'back') ? 'back' : 'front';
        $column = $side === 'back' ? 'document_photo_back' : 'document_photo';
        $db->cdp_query("SELECT COUNT(*) AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_users' AND COLUMN_NAME = 'document_photo_back'");
        $hasBackCol = (int) ($db->cdp_registro()->n ?? 0) > 0;

        // Verifica si se envió un archivo
        if ($side === 'back' && !$hasBackCol) {
            $response = array('success' => false, 'message' => 'The back of the ID cannot be stored yet: run migrations/2026_11_26_id_document_back.sql.');
        } elseif (isset($_FILES['document']) && $_FILES['document']['error'] == UPLOAD_ERR_OK) {
            // Obtiene la información del archivo
            $file_name = $_FILES['document']['name'];
            $file_tmp = $_FILES['document']['tmp_name'];
            $file_type = $_FILES['document']['type'];

            // The file must really be an image (the browser's type and the name are not trusted), and it
            // is stored under a name we choose with the extension of the detected type, so nothing
            // uploaded here can run as code or overwrite someone else's file.
            $user_id = (int) ($_POST['id'] ?? 0);
            $img = @getimagesize($file_tmp);
            $exts = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
            require_once __DIR__ . '/../../helpers/rbac.php';
            $isCustomer = cdp_roleRankById((int) $user->userlevel) < 2; // customers, drivers, agencies: own document only
            // Someone else's document: the caller must outrank the account and, when agency-restricted,
            // stay inside their own agency (as ajax/save_profile_document_ajax.php does).
            $db->cdp_query('SELECT id, userlevel, agency_id, document_photo' . ($hasBackCol ? ', document_photo_back' : '') . ' FROM cdb_users WHERE id = :id LIMIT 1');
            $db->bind(':id', $user_id);
            $target = $user_id > 0 ? $db->cdp_registro() : null;
            if (!$target || ($isCustomer && $user_id !== (int) ($userData->id ?? 0))
                || ($user_id !== (int) ($userData->id ?? 0)
                    && (!cdp_canManageUser($user, (int) $target->userlevel) || !cdp_ajaxRowInAgencyScope((object) ['agency' => $target->agency_id])))) {
                $response = array('success' => false, 'message' => 'You can only change your own document.');
            } elseif ((int) ($_FILES['document']['size'] ?? 0) > 5242880) {
                $response = array('success' => false, 'message' => 'The image must be under 5MB.');
            } elseif (!$img || !isset($exts[$img[2]])) {
                $response = array('success' => false, 'message' => 'File type not allowed. Upload a JPEG, PNG, GIF or WEBP image.');
            } else {
                // The previous image for the history comes from the database, not from the form.
                $current_document = (string) ($target->{$column} ?? '');
                $file_name = $user_id . '_' . ($side === 'back' ? 'back_' : '') . time() . '_' . bin2hex(random_bytes(6)) . '.' . $exts[$img[2]];

                // Ruta completa donde se guardará el archivo
                $upload_path = $upload_dir . $file_name;

                // Mueve el archivo al directorio de carga
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    // Actualiza directamente la base de datos con la nueva ruta del document
                    $db->cdp_query('UPDATE cdb_users SET ' . $column . ' = :document_photo WHERE id = :id');

                    $db->bind(':document_photo', 'uploads/documents/' . $file_name);
                    $db->bind(':id', $user_id);
                    $db->cdp_execute();

                    // History table is created on demand; logging never breaks the save.
                    cdp_profileHistoryLog($user_id, $userData->id, $current_document, $side === 'back' ? 'Back of ID updated' : 'Document updated');

                    // if ($record_history) {
                    //     $response = array('success' => true, 'message' => 'Document successfully updated.');
                    // }
                    
                    $response = array('success' => true, 'message' => $side === 'back' ? 'Back of the ID saved.' : 'Front of the ID saved.', 'side' => $side);

                } else {
                    // Error al mover el archivo
                    $response = array('success' => false, 'message' => 'Error uploading file.');
                }
            }
        } else {
            // No se envió ningún archivo
            $response = array('success' => false, 'message' => 'No file was selected. Click on the Image to select your document.');
        }

        echo json_encode($response);
    } else {
        // Lógica para manejar errores en el formulario si es necesario
        $response = array('success' => false, 'message' => 'There were errors on the form.');
        echo json_encode($response);
    }



    if (!empty($errors)) {
    ?>
        <div class="alert alert-danger" id="success-alert">
            <p><span class="icon-minus-sign"></span><i class="close icon-remove-circle"></i>
                <?php echo $lang['message_ajax_error2']; ?>
            <ul class="error">
                <?php
                foreach ($errors as $error) { ?>
                    <li>
                        <i class="icon-double-angle-right"></i>
                        <?php
                        echo $error;

                        ?>

                    </li>
                <?php

                }
                ?>


            </ul>
            </p>
        </div>



    <?php
    }

    if (isset($messages)) {

    ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <p><span class="icon-info-sign"></span>
                <?php
                foreach ($messages as $message) {
                    echo $message;
                }
                ?>
            </p>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>

<?php
    }
}

?>