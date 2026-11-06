<?php
/** Retired: payments now live on the global Payments page (filtered by type). */
require_once 'loader.php';
header('Location: global_payments_gateways.php?type=package', true, 302);
exit;
