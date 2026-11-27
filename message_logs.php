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
$q = $_SERVER['QUERY_STRING'] ?? '';
header('Location: comms_outbox.php' . ($q !== '' ? '?' . $q : ''), true, 302);
exit;
