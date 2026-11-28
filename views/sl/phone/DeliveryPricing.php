<?php
/**
 * M-CDelivPrice. The screen is built from the same components on a phone and on a computer, so the body lives in
 * views/sl/pages/DeliveryPricing.php; here it gets the phone frame (app bar, .mx-body, sticky .mx-foot).
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['tab'] = 'Parcels';
$slPhone['scripts'][] = 'dataJs/sl/customer_app.js';
$caPhone = true;
include CDP_ROOT . '/views/sl/pages/DeliveryPricing.php';
