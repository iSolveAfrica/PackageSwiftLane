<?php
/**
 * Message Logs moved into Communications > Outbox (comms_outbox.php), which
 * lists every logged attempt plus queued, cancelled and received messages.
 * Old links keep their filters: filter, q / search, channel, status, source,
 * from, to, sent_by, recipient_id, template_id and batch_id are read there
 * (helpers/comms_outbox_view.php cdp_commOutboxFilters).
 * views/sl/pages/MessageLogs.php and views/sl/data/message_logs.php stay for
 * sl_msgShortDetail() and the MessageLogs route name.
 */
require_once 'loader.php';
$in = $_GET;
// Old links put a customer id in q (views/sl/data/user_360.php: MessageLogs?q=<uid>): a short
// number alone is that customer, not a text search (phone numbers and tracking numbers are longer).
if (isset($in['q']) && is_string($in['q']) && preg_match('/^\d{1,6}$/', trim($in['q'])) && empty($in['customer']) && empty($in['recipient_id'])) {
    $in['customer'] = (int) trim($in['q']);
    unset($in['q']);
}
$q = http_build_query($in);
header('Location: comms_outbox.php' . ($q !== '' ? '?' . $q : ''), true, 302);
exit;
