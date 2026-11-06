<?php
/** Retired: payments now live on the global Payments page (filtered by type). */
require_once 'loader.php';
header('Location: global_payments_gateways.php?type=shipment', true, 302);
exit;
