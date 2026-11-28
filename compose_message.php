<?php
/**
 * Compose message moved to the Send page (comms_send.php, Communications):
 * the same query (ids, id, segment, channel) opens it there. The old page
 * (views/sl/pages/ComposeMessage.php) is no longer reachable from here.
 */
require_once 'loader.php';
header('Location: comms_send.php' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 302);
exit;
