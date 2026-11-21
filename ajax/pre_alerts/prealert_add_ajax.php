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
 
  

require_once("../../loader.php");
require_once("../../helpers/querys.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_login();
require_permission('prealert_list');

// Inicializar objetos y variables necesarias
$user = new User;
$core = new Core;
$errors = array();

$response = array();


// Verificar si se recibieron todos los campos necesarios
if (empty($_POST['tracking_prealert']) || empty($_POST['provider_prealert']) || empty($_POST['courier_prealert']) ||
    empty($_POST['price_prealert']) || empty($_POST['description_prealert']) || empty($_POST['date_prealert']) ||
    empty($_FILES['file_invoice']['name'])) {

    $response['status'] = 'error';
    $response['message'] = $lang['messagesform42'];
}

// Si no hay errores, procesar la inserción en la base de datos
if (!isset($response['status'])) {
    // Verificar si el archivo se cargó correctamente
    // pre_alert_files/ is served by the web server and the stored name kept the client's
    // file name and extension, so a customer could store a .php or .html file. Store only
    // an image or PDF (type detected from the contents) under our own name (helpers/uploads.php).
    require_once(__DIR__ . '/../../helpers/uploads.php');
    if ($_FILES['file_invoice']['error'] !== UPLOAD_ERR_OK) {
        $response['status'] = 'error';
        $response['message'] = 'Error uploading attachment.';
    } else {
        $cdpUp = cdp_uploadStore($_FILES['file_invoice'], __DIR__ . '/../../pre_alert_files', 'prealert');
        $image_name = $cdpUp['ok'] ? $cdpUp['name'] : '';
        if (!$cdpUp['ok']) {
            $response['status'] = 'error';
            $response['message'] = $cdpUp['error'];
        } else {
            // Resto de tu código para insertar en la base de datos
            // Crear un arreglo de datos para la inserción en la base de datos

            // Obtener y formatear la fecha
            $date = date('Y-m-d', strtotime(trim($_POST["date_prealert"])));
            $data = array(
                'tracking_prealert'   =>   cdp_sanitize($_POST["tracking_prealert"]),
                'provider_prealert'   =>   cdp_sanitize($_POST["provider_prealert"]),
                'courier_prealert'    =>   cdp_sanitize($_POST["courier_prealert"]),
                'customer_id'         =>   $_SESSION['userid'],
                'price_prealert'      =>   cdp_sanitize($_POST["price_prealert"]),
                'description_prealert'=>   cdp_sanitize($_POST["description_prealert"]),
                'estimated_date'      =>   cdp_sanitize($date),
                'prealert_date'       =>   date("Y-m-d H:i:s"),
                'file_invoice'        =>   'pre_alert_files/' . $image_name
            );

            // Realizar la inserción en la base de datos
            $insert = cdp_insertPreAlert($data);

            // Verificar si la inserción fue exitosa
            if ($insert) {
                // Si es exitoso, generar un mensaje de éxito

                $response['status'] = 'success';
                $response['message'] = $lang['message_ajax_success_add'];

                cdp_activityLog([
                    'module'       => 'prealerts',
                    'verb'         => 'create',
                    'entity_type'  => 'prealert',
                    'entity_id'    => (int) $insert,
                    'entity_label' => $data['tracking_prealert'],
                    'summary'      => 'Raised pre-alert ' . $data['tracking_prealert']
                                      . ' from ' . $data['provider_prealert'],
                    'meta'         => [
                        'courier_id'     => (int) $data['courier_prealert'],
                        'purchase_price' => $data['price_prealert'],
                        'estimated_date' => $data['estimated_date'],
                    ],
                ]);

                $sender_data = cdp_getSenderCourier(intval($_SESSION['userid']));

                // Guardar notificación
                $db->cdp_query("
                    INSERT INTO cdb_notifications 
                    (
                        user_id,
                        notification_description,
                        shipping_type,
                        notification_date
                    )
                    VALUES
                    (
                        :user_id,              
                        :notification_description,
                        :shipping_type,
                        :notification_date                    
                    )
                ");

                $db->bind(':user_id',  $_SESSION['userid']);
                $db->bind(':notification_description', $lang['notification_shipment20']. ' ' . $sender_data->fname . ' ' . $sender_data->lname);
                $db->bind(':shipping_type', '3');
                $db->bind(':notification_date',  date("Y-m-d H:i:s"));

                $db->cdp_execute();

                $notification_id = $db->dbh->lastInsertId();
                $users_employees = cdp_getUsersAdminEmployees();

                foreach ($users_employees as $key) {
                    cdp_insertNotificationsUsers($notification_id, $key->id);
                }

                cdp_insertNotificationsUsers($notification_id, $_SESSION['userid']);
            } else {
                // Si hay un error en la inserción, generar un mensaje de error

                $response['status'] = 'error';
                $response['message'] = $lang['message_ajax_error2'];
            }
        }
    }
}


// Enviar la respuesta como JSON
header('Content-type: application/json; charset=UTF-8');
echo json_encode($response);


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

    <script>
        $("#form_prealert")[0].reset();
    </script>

<?php
}
?>