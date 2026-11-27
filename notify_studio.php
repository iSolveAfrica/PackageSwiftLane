<?php
/**
 * notify_studio.php: the Notification Studio is replaced by Communications >
 * Status messages (comms_statuses.php), which reuses its engine
 * (helpers/notify_studio.php, helpers/notify_drafts.php). Deep links keep working:
 *   ?key= / ?t= status:<id>               -> comms_statuses.php?status=<id>
 *   comms:<event>                         -> comms_automations.php?edit=<event>
 *   action:shipment.registered            -> the "Shipment registered" old-screen message
 */
$k = (string) ($_GET['key'] ?? ($_GET['t'] ?? ''));
$to = 'comms_statuses.php';
if (preg_match('/^status:([0-9]{1,6})$/', $k, $m)) {
    $to = 'comms_statuses.php?status=' . (int) $m[1];
} elseif (preg_match('/^comms:([a-z_]{3,40})$/', $k, $m)) {
    $to = 'comms_automations.php?edit=' . rawurlencode($m[1]);
} elseif ($k === 'action:shipment.registered') {
    $to = 'comms_automations.php?edit=' . rawurlencode('legacy:shipment_registered');
}
header('Location: ' . $to, true, 302);
exit;
