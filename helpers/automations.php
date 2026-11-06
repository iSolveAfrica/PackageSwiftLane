<?php
/**
 * Automations: "when <trigger> [only if <conditions>] then <actions>".
 *
 * Rules live in cdb_automations (JSON conditions/actions), every evaluation
 * that matched a rule is written to cdb_automation_runs.
 *
 * Firing a trigger from application code (one line, safe on any schema):
 *
 *     @include_once CDP_ROOT . '/helpers/automations.php';
 *     if (function_exists('cdp_automationsFire')) {
 *         cdp_automationsFire('shipment.status', [
 *             'entity_type' => 'shipment', 'entity_id' => $orderId, 'entity_label' => $tracking,
 *             'user_id' => $senderId, 'status_id' => $status, 'tracking' => $tracking, 'mode' => 'air',
 *         ]);
 *     }
 *
 * Wired today: shipment.status (ajax/courier/add_courier_tracking.php and
 * ajax/courier/courier_update_multiple_ajax.php). The other triggers in
 * cdp_automationTriggers() list, in 'hook', the file where the same call
 * belongs; they fire as soon as that line is added there.
 *
 * Delivery reuses helpers/push_notify.php (WhatsApp template 12, e-mail
 * template 29, SMS through the configured provider), so every message an
 * automation sends lands in cdb_message_log with source "automation".
 */
require_once __DIR__ . '/sl_view.php';

function cdp_automationTriggers(): array
{
    return [
        'shipment.status'    => ['label' => 'Shipment status changes', 'icon' => 'i-plane', 'entity' => 'shipment', 'hook' => 'ajax/courier/add_courier_tracking.php, ajax/courier/courier_update_multiple_ajax.php (wired)'],
        'shipment.created'   => ['label' => 'Shipment registered', 'icon' => 'i-newdoc', 'entity' => 'shipment', 'hook' => 'ajax/courier/add_courier_ajax.php after cdp_insertCourierShipment'],
        'package.status'     => ['label' => 'Sea package status changes', 'icon' => 'i-ship', 'entity' => 'package', 'hook' => 'ajax/customers_packages/customers_packages_update_multiple_ajax.php'],
        'package.created'    => ['label' => 'Sea package registered', 'icon' => 'i-box', 'entity' => 'package', 'hook' => 'ajax/customers_packages/add_customers_packages_ajax.php'],
        'pickup.requested'   => ['label' => 'Pickup requested', 'icon' => 'i-truck', 'entity' => 'shipment', 'hook' => 'ajax/pickup/add_pickup_ajax.php, add_pickup_client_ajax.php'],
        'payment.received'   => ['label' => 'Payment received', 'icon' => 'i-card', 'entity' => 'payment', 'hook' => 'ajax/gateway/* after the payment row is written'],
        'invoice.overdue'    => ['label' => 'Invoice overdue (daily check)', 'icon' => 'i-receipt', 'entity' => 'shipment', 'hook' => 'cron/pickup_aging or a daily cron calling cdp_automationsFire per overdue order'],
        'customer.signed_up' => ['label' => 'Customer signs up', 'icon' => 'i-user', 'entity' => 'customer', 'hook' => 'ajax/sign_up_ajax.php after the account is created'],
        'prealert.created'   => ['label' => 'Pre-alert created', 'icon' => 'i-inbox', 'entity' => 'prealert', 'hook' => 'ajax/pre_alerts/* after insert'],
    ];
}

function cdp_automationConditions(): array
{
    return [
        'status_is'   => ['label' => 'status is', 'input' => 'status', 'hint' => 'Pick a status'],
        'mode_is'     => ['label' => 'shipping mode is', 'input' => 'select', 'options' => ['air' => 'Air', 'sea' => 'Sea']],
        'has_phone'   => ['label' => 'customer has a phone number', 'input' => 'none'],
        'has_email'   => ['label' => 'customer has an e-mail address', 'input' => 'none'],
        'amount_over' => ['label' => 'amount is over', 'input' => 'number', 'hint' => 'In the site currency'],
        'is_pickup'   => ['label' => 'it is a pickup', 'input' => 'none'],
        'unpaid'      => ['label' => 'invoice is unpaid', 'input' => 'none'],
    ];
}

function cdp_automationActions(): array
{
    return [
        'whatsapp'    => ['label' => 'Send WhatsApp to the customer', 'icon' => 'i-message', 'fields' => ['message']],
        'sms'         => ['label' => 'Send SMS to the customer', 'icon' => 'i-phone', 'fields' => ['message']],
        'email'       => ['label' => 'E-mail the customer', 'icon' => 'i-mail', 'fields' => ['subject', 'message']],
        'in_app'      => ['label' => 'In-app notification', 'icon' => 'i-bell', 'fields' => ['message']],
        'staff_email' => ['label' => 'E-mail the team', 'icon' => 'i-users', 'fields' => ['subject', 'message']],
        'ticket'      => ['label' => 'Open a support ticket', 'icon' => 'i-ticket', 'fields' => ['subject']],
        'log'         => ['label' => 'Write to Activity Logs', 'icon' => 'i-clock', 'fields' => []],
    ];
}

/** Variables an automation message may use. */
function cdp_automationVars(): array
{
    return ['[NAME]', '[FIRST_NAME]', '[TRACKING]', '[STATUS]', '[AMOUNT]', '[LOCKER]', '[SITE_NAME]', '[URL]'];
}

function cdp_automationsReady(): bool
{
    return sl_tableExists('cdb_automations') && sl_tableExists('cdb_automation_runs');
}

/** Decode one DB row into an array with parsed JSON. */
function cdp_automationRow($r): array
{
    $a = (array) $r;
    $a['conditions'] = json_decode((string) ($a['conditions'] ?? '[]'), true) ?: [];
    $a['actions'] = json_decode((string) ($a['actions'] ?? '[]'), true) ?: [];
    return $a;
}

function cdp_automationsAll(bool $enabledOnly = false, ?string $trigger = null): array
{
    if (!cdp_automationsReady()) {
        return [];
    }
    $w = '1=1';
    $b = [];
    if ($enabledOnly) {
        $w .= ' AND enabled = 1';
    }
    if ($trigger !== null) {
        $w .= ' AND trigger_key = :t';
        $b[':t'] = $trigger;
    }
    return array_map('cdp_automationRow', sl_rows("SELECT * FROM cdb_automations WHERE $w ORDER BY id DESC", $b));
}

/** Personalise a message for the automation context. */
function cdp_automationText(string $text, array $ctx, $user, $settings): string
{
    $name = trim((string) (($user->fname ?? '') . ' ' . ($user->lname ?? '')));
    $first = trim((string) ($user->fname ?? '')) ?: $name;
    $amount = isset($ctx['amount']) ? (function_exists('sl_money') ? sl_money($ctx['amount']) : (string) $ctx['amount']) : '';
    return str_replace(
        ['[NAME]', '[FIRST_NAME]', '[TRACKING]', '[STATUS]', '[AMOUNT]', '[LOCKER]', '[SITE_NAME]', '[URL]'],
        [$name, $first, (string) ($ctx['tracking'] ?? ($ctx['entity_label'] ?? '')), (string) ($ctx['status_name'] ?? ''), $amount,
            (string) ($user->locker ?? ''), (string) ($settings->site_name ?? 'Swift Lane'), rtrim((string) ($settings->site_url ?? ''), '/')],
        $text
    );
}

/** Evaluate one rule's conditions against the context. */
function cdp_automationMatches(array $auto, array $ctx, $user): bool
{
    foreach ($auto['conditions'] as $c) {
        $k = (string) ($c['key'] ?? '');
        $v = $c['value'] ?? '';
        switch ($k) {
            case 'status_is':
                $want = array_map('intval', is_array($v) ? $v : explode(',', (string) $v));
                if (!in_array((int) ($ctx['status_id'] ?? 0), $want, true)) return false;
                break;
            case 'mode_is':
                if (strtolower((string) ($ctx['mode'] ?? '')) !== strtolower((string) $v)) return false;
                break;
            case 'has_phone':
                if (trim((string) ($user->phone ?? '')) === '') return false;
                break;
            case 'has_email':
                if (!filter_var((string) ($user->email ?? ''), FILTER_VALIDATE_EMAIL)) return false;
                break;
            case 'amount_over':
                if ((float) ($ctx['amount'] ?? 0) <= (float) $v) return false;
                break;
            case 'is_pickup':
                if (empty($ctx['is_pickup'])) return false;
                break;
            case 'unpaid':
                if (empty($ctx['unpaid'])) return false;
                break;
        }
    }
    return true;
}

/** Resolve the customer of a context (user object, or user_id). */
function cdp_automationUser(array $ctx)
{
    if (!empty($ctx['user']) && is_object($ctx['user'])) {
        return $ctx['user'];
    }
    $uid = (int) ($ctx['user_id'] ?? 0);
    if ($uid <= 0 && !empty($ctx['entity_type']) && $ctx['entity_type'] === 'shipment' && !empty($ctx['entity_id'])) {
        $uid = (int) sl_scalar('SELECT sender_id FROM cdb_add_order WHERE order_id = :id', [':id' => (int) $ctx['entity_id']]);
    }
    if ($uid <= 0 && !empty($ctx['entity_type']) && $ctx['entity_type'] === 'package' && !empty($ctx['entity_id'])) {
        $uid = (int) sl_scalar('SELECT sender_id FROM cdb_customers_packages WHERE order_id = :id', [':id' => (int) $ctx['entity_id']]);
    }
    return $uid > 0 ? sl_row('SELECT * FROM cdb_users WHERE id = :id', [':id' => $uid]) : null;
}

/** Run one action; returns a short outcome string. */
function cdp_automationRunAction(array $act, array $auto, array $ctx, $user, $settings, bool $dryRun): string
{
    $type = (string) ($act['type'] ?? '');
    $msg = cdp_automationText((string) ($act['message'] ?? ''), $ctx, $user ?: (object) [], $settings);
    $subject = cdp_automationText((string) ($act['subject'] ?? $auto['name']), $ctx, $user ?: (object) [], $settings);
    if ($dryRun) {
        return $type . ': would run' . ($msg !== '' ? ' ("' . mb_substr($msg, 0, 60) . '…")' : '');
    }
    switch ($type) {
        case 'whatsapp':
        case 'sms':
        case 'email':
            if (!$user) return $type . ': no customer';
            if ($msg === '') return $type . ': empty message';
            require_once __DIR__ . '/push_notify.php';
            require_once __DIR__ . '/message_log.php';
            $mctx = ['source' => 'automation', 'source_label' => 'Automation · ' . $auto['name'], 'entity_type' => (string) ($ctx['entity_type'] ?? ''),
                'entity_id' => (string) ($ctx['entity_id'] ?? ''), 'entity_label' => (string) ($ctx['entity_label'] ?? ''), 'batch_id' => 'auto' . (int) $auto['id']];
            $r = cdp_pushNotifyUser($user, $subject, $msg, $settings, $mctx, $type);
            cdp_msgClearContext();
            return $type . ': ' . $r[$type]['status'] . ($r[$type]['detail'] !== '' ? ' (' . $r[$type]['detail'] . ')' : '');
        case 'in_app':
            if (!$user) return 'in_app: no customer';
            require_once __DIR__ . '/querys.php';
            $data = ['user_id' => (int) $user->id, 'order_id' => (int) ($ctx['entity_id'] ?? 0), 'notification_description' => mb_substr($msg !== '' ? $msg : $subject, 0, 340),
                'shipping_type' => (string) ($ctx['shipping_type'] ?? '1'), 'notification_date' => date('Y-m-d H:i:s')];
            $nid = function_exists('cdp_insertNotification') ? cdp_insertNotification($data) : 0;
            if ($nid && function_exists('cdp_insertNotificationsUsers')) {
                cdp_insertNotificationsUsers($nid, (int) $user->id);
            }
            return 'in_app: ' . ($nid ? 'created' : 'failed');
        case 'staff_email':
            $to = (string) ($settings->site_email ?? '');
            if ($to === '' || !function_exists('cdp_sendMail')) return 'staff_email: no site e-mail';
            $ok = @cdp_sendMail($to, $subject, nl2br(h($msg)));
            return 'staff_email: ' . ($ok ? 'sent' : 'failed');
        case 'ticket':
            if (!sl_tableExists('cdb_cs_tickets') || !$user) return 'ticket: support module not installed';
            try {
                $db = new Conexion();
                $db->cdp_query('INSERT INTO cdb_cs_tickets (subject, customer_id, status, priority, channel, created_at, updated_at) VALUES (:s, :c, :st, :p, :ch, NOW(), NOW())');
                $db->bind(':s', mb_substr($subject, 0, 190)); $db->bind(':c', (int) $user->id); $db->bind(':st', 'open'); $db->bind(':p', 'normal'); $db->bind(':ch', 'automation');
                return 'ticket: ' . ($db->cdp_execute() ? 'opened' : 'failed');
            } catch (Throwable $e) {
                return 'ticket: ' . $e->getMessage();
            }
        case 'log':
            require_once __DIR__ . '/activity_log.php';
            if (function_exists('cdp_activityLog')) {
                cdp_activityLog(['module' => 'automations', 'verb' => 'notify', 'action' => 'automations.run', 'label' => 'Automation · ' . $auto['name'], 'summary' => $subject,
                    'entity_type' => (string) ($ctx['entity_type'] ?? ''), 'entity_id' => (string) ($ctx['entity_id'] ?? ''), 'entity_label' => (string) ($ctx['entity_label'] ?? '')]);
            }
            return 'log: written';
    }
    return $type . ': unknown action';
}

function cdp_automationRecordRun(int $autoId, string $trigger, array $ctx, string $outcome, string $detail): void
{
    try {
        $db = new Conexion();
        $db->cdp_query('INSERT INTO cdb_automation_runs (automation_id, trigger_key, entity_type, entity_id, entity_label, outcome, detail, created_at) VALUES (:a, :t, :et, :ei, :el, :o, :d, NOW())');
        $db->bind(':a', $autoId); $db->bind(':t', $trigger); $db->bind(':et', (string) ($ctx['entity_type'] ?? '')); $db->bind(':ei', (string) ($ctx['entity_id'] ?? ''));
        $db->bind(':el', mb_substr((string) ($ctx['entity_label'] ?? ''), 0, 190)); $db->bind(':o', $outcome); $db->bind(':d', mb_substr($detail, 0, 500));
        $db->cdp_execute();
        if ($outcome !== 'test') {
            $db->cdp_query('UPDATE cdb_automations SET run_count = run_count + 1, last_run_at = NOW() WHERE id = :id');
            $db->bind(':id', $autoId);
            $db->cdp_execute();
        }
    } catch (Throwable $e) {
        // never break the caller
    }
}

/**
 * Fire a trigger. Returns the list of runs [[id, name, outcome, detail]].
 * Never throws: an automation must not break the business action that fired it.
 */
function cdp_automationsFire(string $trigger, array $ctx = [], bool $dryRun = false, ?int $onlyId = null): array
{
    static $depth = 0;
    $out = [];
    if ($depth > 1 || !cdp_automationsReady()) {
        return $out;
    }
    $depth++;
    try {
        if (!isset($ctx['status_name']) && !empty($ctx['status_id'])) {
            $ctx['status_name'] = (string) sl_scalar('SELECT mod_style FROM cdb_styles WHERE id = :id', [':id' => (int) $ctx['status_id']], '');
        }
        $rules = $onlyId ? array_filter(cdp_automationsAll(false), fn($a) => (int) $a['id'] === $onlyId) : cdp_automationsAll(true, $trigger);
        if (!$rules) {
            $depth--;
            return $out;
        }
        $user = cdp_automationUser($ctx);
        $settings = function_exists('cdp_getSettingsCourier') ? cdp_getSettingsCourier() : (object) [];
        foreach ($rules as $auto) {
            if (!cdp_automationMatches($auto, $ctx, $user ?: (object) [])) {
                if ($dryRun) {
                    $out[] = ['id' => (int) $auto['id'], 'name' => $auto['name'], 'outcome' => 'skipped', 'detail' => 'conditions not met for the sample'];
                }
                continue;
            }
            $details = [];
            $failed = false;
            foreach ($auto['actions'] as $act) {
                try {
                    $details[] = cdp_automationRunAction($act, $auto, $ctx, $user, $settings, $dryRun);
                } catch (Throwable $e) {
                    $failed = true;
                    $details[] = ($act['type'] ?? '?') . ': ' . $e->getMessage();
                }
            }
            $outcome = $dryRun ? 'test' : ($failed ? 'failed' : 'ok');
            $detail = implode(' · ', $details) ?: 'no actions';
            cdp_automationRecordRun((int) $auto['id'], $trigger, $ctx, $outcome, $detail);
            $out[] = ['id' => (int) $auto['id'], 'name' => $auto['name'], 'outcome' => $outcome, 'detail' => $detail];
        }
    } catch (Throwable $e) {
        error_log('[automations] ' . $e->getMessage());
    }
    $depth--;
    return $out;
}
