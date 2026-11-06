<?php
/**
 * Compose message: send one message by WhatsApp, SMS or e-mail to customers, a segment or a pasted list.
 * Delivery: helpers/push_notify.php (cdp_pushNotifyChunk), so every attempt lands in cdb_message_log.
 * The page posts repeatedly with after_id/batch_id until done (CDP_PUSH_CHUNK recipients per request).
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../helpers/push_notify.php';
require_once __DIR__ . '/../../helpers/push_compose.php';
require_once __DIR__ . '/../../views/sl/data/compose.php';
require_login();
require_permission('push_notifications');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
$op = (string) ($_POST['op'] ?? 'send');
if ($op === 'search') {
    $q = trim((string) ($_POST['q'] ?? ''));
    $rows = mb_strlen($q) < 2 ? [] : sl_rows("SELECT id, fname, lname, email, phone, locker FROM cdb_users WHERE userlevel = 1 AND active = 1 AND (CONCAT(fname,' ',lname) LIKE :q OR email LIKE :q OR phone LIKE :q OR locker LIKE :q) ORDER BY fname LIMIT 8", [':q' => '%' . $q . '%']);
    sl_json(['success' => true, 'items' => array_map(fn($r) => ['id' => (int) $r->id, 'name' => trim($r->fname . ' ' . $r->lname), 'sub' => trim(($r->locker ? $r->locker . ' · ' : '') . ($r->phone ?: $r->email))], $rows)]);
}
$channel = (string) ($_POST['channel'] ?? 'whatsapp');
if (!in_array($channel, ['whatsapp', 'sms', 'email'], true)) {
    sl_json(['success' => false, 'message' => 'Pick a channel'], 422);
}
$ids = array_filter(explode(',', (string) ($_POST['ids'] ?? '')));
$recipients = sl_composeRecipients($ids, (string) ($_POST['segment'] ?? ''), (string) ($_POST['list'] ?? ''));
if ($op === 'estimate') {
    $a = cdp_pushAudienceCounts($recipients);
    $rates = cdp_pushCostRates();
    $reach = $channel === 'email' ? $a['with_email'] : $a['with_phone'];
    sl_json(['success' => true, 'total' => $a['total'], 'reach' => $reach, 'unit' => $rates[$channel], 'currency' => $rates['currency'], 'cost' => round($reach * $rates[$channel], 4)]);
}
if (CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
$subject = mb_substr(trim((string) ($_POST['subject'] ?? '')), 0, 190);
$message = trim((string) ($_POST['message'] ?? ''));
$errors = [];
if ($message === '') $errors['message'] = 'Write the message';
if ($channel === 'email' && $subject === '') $errors['subject'] = 'E-mail needs a subject';
if ($op === 'test') {
    $recipients = [(int) $_SESSION['userid']];
} elseif (!$recipients) {
    $errors['to'] = 'Nobody matches: pick customers, a segment or paste addresses';
}
if ($errors) {
    sl_json(['success' => false, 'message' => reset($errors), 'errors' => $errors], 422);
}
$afterId = max(0, (int) ($_POST['after_id'] ?? 0));
$ctx = ['source' => 'general_notice', 'source_label' => 'Compose message', 'batch_id' => cdp_pushBatchIdFromRequest($_POST['batch_id'] ?? ''), 'entity_type' => 'broadcast', 'entity_label' => count($recipients) . ' recipient(s)'];
if ($op === 'test') {
    $me = cdp_pushFetchUsers($recipients);
    $r = $me ? cdp_pushNotifyUser($me[0], '[TEST] ' . ($subject ?: 'Message'), $message, cdp_getSettingsCourier(), $ctx, $channel) : null;
    $st = $r[$channel]['status'] ?? 'failed';
    sl_json(['success' => $st === 'sent', 'message' => $st === 'sent' ? 'Test sent to you' : 'Test ' . $st . (!empty($r[$channel]['detail']) ? ': ' . $r[$channel]['detail'] : '')]);
}
$sum = cdp_pushNotifyChunk($recipients, $afterId, $subject ?: 'Message from ' . ($core->site_name ?? 'Swift Lane'), $message, cdp_getSettingsCourier(), $ctx, $channel);
if ($afterId === 0) {
    cdp_activityLog(['module' => 'notifications', 'verb' => 'notify', 'action' => 'notifications.compose', 'label' => 'Notifications · Message Sent', 'summary' => sprintf('Compose: "%s" by %s to %d recipient(s) — batch %s', mb_substr($subject ?: $message, 0, 60), $channel, $sum['total'], $sum['batch_id']), 'entity_type' => 'broadcast', 'meta' => ['batch_id' => $sum['batch_id'], 'channel' => $channel, 'recipients' => $sum['total']]]);
}
sl_json(['success' => true, 'done' => $sum['done'], 'after_id' => $sum['next_after_id'], 'batch_id' => $sum['batch_id'], 'total' => $sum['total'], 'sent' => $sum[$channel]['sent'], 'failed' => $sum[$channel]['failed'], 'skipped' => $sum[$channel]['skipped'], 'lines' => $sum['lines']]);
