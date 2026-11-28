<?php
/**
 * The Email builder moved into the Send page (comms_send.php, Communications):
 * a campaign by e-mail; ?email= addresses become a direct send to those
 * addresses. Saved designs (cdb_newsletter_designs) open in the Send page's
 * e-mail canvas through ajax/tools/newsletter_ajax.php.
 */
require_once 'loader.php';
header('Location: comms_send.php?' . (isset($_GET['email']) ? http_build_query(['mode' => 'several', 'channel' => 'email', 'email' => (string) $_GET['email']]) : 'mode=campaign&channel=email'), true, 302);
exit;
