<?php
/**
 * Scheduled automations and the suggested-rules catalogue (extends helpers/automations.php).
 *
 * Scheduled triggers are checks that look for records that have waited too long (pre-alert past its date,
 * parcel not collected, ...). cdp_automationsScheduled() runs every published rule on a scheduled trigger
 * against the records it finds; a record is handled once per rule (cdb_automation_runs is the memory).
 * It runs from cron/automations.php (every 15 minutes) and, as a fallback without cron, at most every
 * 15 minutes after a staff page has been sent (cdp_automationsTick, views/sl/inc/shell_end.php).
 *
 * Suggested rules (cdp_automationSuggestions) are written to cdb_automations with origin 'suggested' and
 * review_status 'suggested' (off). Staff approve and publish them on the Automations page; publishing
 * switches them on. "Close overdue pre-alerts" ships published (asked for by the business).
 *
 * cdp_systemRules() lists the rules built into the code, so every rule the system applies is visible.
 */

/** Scheduled triggers: label, entity, the "days" the check is measured in, and the default. */
function cdp_automationScheduledTriggers(): array
{
    return [
        'prealert.overdue'     => ['label' => 'Pre-alert still not arrived', 'icon' => 'i-inbox', 'entity' => 'prealert', 'schedule' => true, 'unit' => 'days after the expected date', 'hook' => 'scheduled check (cron/automations.php)'],
        'parcel.uncollected'   => ['label' => 'Parcel ready but not collected', 'icon' => 'i-box', 'entity' => 'shipment', 'schedule' => true, 'unit' => 'days since it became ready', 'hook' => 'scheduled check (cron/automations.php)'],
        'warehouse.aging'      => ['label' => 'Parcel waiting at the US warehouse', 'icon' => 'i-warehouse', 'entity' => 'shipment', 'schedule' => true, 'unit' => 'days since it was received', 'hook' => 'scheduled check (cron/automations.php)'],
        'consolidation.late'   => ['label' => 'Consolidation past its ETA', 'icon' => 'i-layers', 'entity' => 'consolidation', 'schedule' => true, 'unit' => 'days past the ETA', 'hook' => 'scheduled check (cron/automations.php)'],
        'invoice.unpaid'       => ['label' => 'Invoice still unpaid', 'icon' => 'i-receipt', 'entity' => 'shipment', 'schedule' => true, 'unit' => 'days since the shipment was created', 'hook' => 'scheduled check (cron/automations.php)'],
        'pickup.unassigned'    => ['label' => 'Home pickup without a rider', 'icon' => 'i-truck', 'entity' => 'shipment', 'schedule' => true, 'unit' => 'days since it was requested', 'hook' => 'scheduled check (cron/automations.php)'],
        'customer.inactive'    => ['label' => 'Customer has not shipped for a while', 'icon' => 'i-user', 'entity' => 'customer', 'schedule' => true, 'unit' => 'days since the last shipment', 'hook' => 'scheduled check (cron/automations.php)'],
    ];
}

/**
 * Records a scheduled trigger finds, each a fire context with 'days'. $min is the smallest "days"
 * any published rule on the trigger needs, so the query only returns records that can match.
 */
function cdp_automationScheduledFind(string $trigger, int $min, int $limit = 200): array
{
    $min = max(0, $min);
    $out = [];
    $lastTrack = "(SELECT MAX(t.t_date) FROM cdb_courier_track t WHERE t.order_track = CONCAT(o.order_prefix, o.order_no))";
    switch ($trigger) {
        case 'prealert.overdue':
            foreach (sl_rows("SELECT pre_alert_id, tracking, customer_id, purchase_price, DATEDIFF(CURDATE(), estimated_date) AS d FROM cdb_pre_alert
                              WHERE is_package = 0 AND estimated_date IS NOT NULL AND estimated_date > '1000-01-01' AND DATEDIFF(CURDATE(), estimated_date) >= :m ORDER BY estimated_date LIMIT $limit", [':m' => $min]) as $r) {
                $out[] = ['entity_type' => 'prealert', 'entity_id' => (int) $r->pre_alert_id, 'entity_label' => (string) $r->tracking, 'tracking' => (string) $r->tracking,
                    'user_id' => (int) $r->customer_id, 'amount' => (float) $r->purchase_price, 'days' => (int) $r->d, 'shipping_type' => '3'];
            }
            break;
        case 'parcel.uncollected':
        case 'warehouse.aging':
            $st = $trigger === 'parcel.uncollected' ? '6, 32' : '2, 4';
            foreach ([['cdb_add_order', 'air', 'shipment', ' AND o.is_deleted = 0'], ['cdb_customers_packages', 'sea', 'package', '']] as [$tbl, $mode, $et, $extra]) {
                foreach (sl_rows("SELECT o.order_id, o.order_prefix, o.order_no, o.sender_id, o.status_courier, DATEDIFF(NOW(), COALESCE($lastTrack, o.order_datetime)) AS d
                                  FROM $tbl o WHERE o.status_courier IN ($st)$extra HAVING d >= :m ORDER BY d DESC LIMIT $limit", [':m' => $min]) as $r) {
                    $out[] = ['entity_type' => $et, 'entity_id' => (int) $r->order_id, 'entity_label' => $r->order_prefix . $r->order_no, 'tracking' => $r->order_prefix . $r->order_no,
                        'user_id' => (int) $r->sender_id, 'status_id' => (int) $r->status_courier, 'mode' => $mode, 'days' => (int) $r->d];
                }
            }
            break;
        case 'consolidation.late':
            foreach ([['cdb_consolidate', 'air'], ['cdb_consolidate_packages', 'sea']] as [$tbl, $mode]) {
                foreach (sl_rows("SELECT consolidate_id, c_prefix, c_no, status_courier, DATEDIFF(CURDATE(), estimated_eta) AS d FROM $tbl
                                  WHERE status_courier NOT IN (6, 8, 15, 21, 32) AND estimated_eta IS NOT NULL AND estimated_eta > '1000-01-01' AND DATEDIFF(CURDATE(), estimated_eta) >= :m LIMIT $limit", [':m' => max(1, $min)]) as $r) {
                    $out[] = ['entity_type' => 'consolidation', 'entity_id' => $mode . ':' . (int) $r->consolidate_id, 'entity_label' => $r->c_prefix . $r->c_no, 'tracking' => $r->c_prefix . $r->c_no,
                        'status_id' => (int) $r->status_courier, 'mode' => $mode, 'days' => (int) $r->d];
                }
            }
            break;
        case 'invoice.unpaid':
            foreach (sl_rows("SELECT o.order_id, o.order_prefix, o.order_no, o.sender_id, o.total_order, DATEDIFF(NOW(), o.order_datetime) AS d FROM cdb_add_order o
                              WHERE o.is_deleted = 0 AND o.status_invoice = 23 AND o.status_courier NOT IN (21) AND o.total_order > 0 AND DATEDIFF(NOW(), o.order_datetime) >= :m LIMIT $limit", [':m' => $min]) as $r) {
                $out[] = ['entity_type' => 'shipment', 'entity_id' => (int) $r->order_id, 'entity_label' => $r->order_prefix . $r->order_no, 'tracking' => $r->order_prefix . $r->order_no,
                    'user_id' => (int) $r->sender_id, 'amount' => (float) $r->total_order, 'unpaid' => true, 'mode' => 'air', 'days' => (int) $r->d];
            }
            break;
        case 'pickup.unassigned':
            foreach (sl_rows("SELECT o.order_id, o.order_prefix, o.order_no, o.sender_id, DATEDIFF(NOW(), o.order_datetime) AS d FROM cdb_add_order o
                              WHERE o.is_deleted = 0 AND o.is_pickup = 1 AND o.status_courier IN (14, 1) AND (o.driver_id IS NULL OR o.driver_id = 0) AND DATEDIFF(NOW(), o.order_datetime) >= :m LIMIT $limit", [':m' => $min]) as $r) {
                $out[] = ['entity_type' => 'shipment', 'entity_id' => (int) $r->order_id, 'entity_label' => $r->order_prefix . $r->order_no, 'tracking' => $r->order_prefix . $r->order_no,
                    'user_id' => (int) $r->sender_id, 'is_pickup' => true, 'mode' => 'air', 'days' => (int) $r->d];
            }
            break;
        case 'customer.inactive':
            $last = "GREATEST(COALESCE((SELECT MAX(o.order_datetime) FROM cdb_add_order o WHERE o.sender_id = u.id AND o.is_deleted = 0), '1000-01-01'), COALESCE((SELECT MAX(p.order_datetime) FROM cdb_customers_packages p WHERE p.sender_id = u.id), '1000-01-01'))";
            foreach (sl_rows("SELECT u.id, u.fname, u.lname, u.locker, $last AS lastAt, DATEDIFF(NOW(), $last) AS d FROM cdb_users u
                              WHERE u.userlevel = 1 AND u.active = 1 HAVING lastAt > '1000-01-01' AND d >= :m LIMIT $limit", [':m' => max(1, $min)]) as $r) {
                $out[] = ['entity_type' => 'customer', 'entity_id' => (int) $r->id, 'entity_label' => trim($r->fname . ' ' . $r->lname) . ($r->locker ? ' · ' . $r->locker : ''), 'user_id' => (int) $r->id, 'days' => (int) $r->d];
            }
            break;
    }
    return $out;
}

/** The suggested rules. 'published' => true ships switched on. */
function cdp_automationSuggestions(): array
{
    $days = fn($n) => ['key' => 'days_over', 'value' => (string) $n];
    return [
        ['key' => 'prealert_close_overdue', 'name' => 'Close pre-alerts 30 days overdue', 'trigger' => 'prealert.overdue', 'published' => true,
            'description' => 'A pre-alert still not received 30 days after its expected date is closed as "not arrived", and the customer is told so they know it is not coming.',
            'conditions' => [$days(30)],
            'actions' => [['type' => 'close_prealert', 'subject' => 'Not arrived [DAYS] days after the expected date', 'message' => ''],
                ['type' => 'in_app', 'subject' => '', 'message' => 'Your pre-alert [TRACKING] has not reached our warehouse [DAYS] days after the expected date, so we have closed it. If the parcel is still on its way, contact us and we will reopen it.'],
                ['type' => 'email', 'subject' => 'Pre-alert [TRACKING] closed: parcel not received', 'message' => 'Hi [FIRST_NAME], your pre-alert [TRACKING] has not reached our warehouse [DAYS] days after the expected date, so we have closed it. If the parcel is still on its way, reply to this e-mail and we will reopen it. [SITE_NAME]'],
                ['type' => 'whatsapp', 'subject' => '', 'message' => 'Hi [FIRST_NAME], your pre-alert [TRACKING] has not reached our warehouse [DAYS] days after the expected date, so we have closed it. If it is still coming, reply here and we will reopen it.']]],
        ['key' => 'prealert_remind_7', 'name' => 'Ask for an update on late pre-alerts', 'trigger' => 'prealert.overdue',
            'description' => 'Seven days after the expected date, ask the customer to check the shop\'s tracking and update the pre-alert.',
            'conditions' => [$days(7)],
            'actions' => [['type' => 'in_app', 'subject' => '', 'message' => 'Your pre-alert [TRACKING] is [DAYS] days late. Please check the carrier tracking and update the expected date if it changed.'],
                ['type' => 'whatsapp', 'subject' => '', 'message' => 'Hi [FIRST_NAME], we have not received [TRACKING] yet ([DAYS] days after the expected date). Please check the carrier tracking and update your pre-alert if the date changed.']]],
        ['key' => 'uncollected_reminder_3', 'name' => 'Remind customers to collect ready parcels', 'trigger' => 'parcel.uncollected',
            'description' => 'A parcel ready for pickup for 3 days gets a reminder with the pickup details.',
            'conditions' => [$days(3)],
            'actions' => [['type' => 'whatsapp', 'subject' => '', 'message' => 'Hi [FIRST_NAME], your parcel [TRACKING] has been ready for pickup for [DAYS] days. Please collect it with your pickup code. [SITE_NAME]'],
                ['type' => 'in_app', 'subject' => '', 'message' => 'Reminder: [TRACKING] is ready for pickup. Bring your pickup code.']]],
        ['key' => 'uncollected_staff_14', 'name' => 'Tell the team about parcels uncollected for 14 days', 'trigger' => 'parcel.uncollected',
            'description' => 'Uncollected for two weeks: the team is alerted so someone calls the customer (storage charges may apply).',
            'conditions' => [$days(14)],
            'actions' => [['type' => 'staff_in_app', 'subject' => '', 'message' => '[TRACKING] for [NAME] has been ready for [DAYS] days and not collected. Call the customer.'], ['type' => 'log', 'subject' => 'Uncollected for 14 days', 'message' => '']]],
        ['key' => 'warehouse_aging_5', 'name' => 'Flag parcels sitting at the US warehouse', 'trigger' => 'warehouse.aging',
            'description' => 'Received at the US warehouse 5 days ago and still not consolidated or shipped: the team is told.',
            'conditions' => [$days(5)],
            'actions' => [['type' => 'staff_in_app', 'subject' => '', 'message' => '[TRACKING] for [NAME] has been at the US warehouse for [DAYS] days without moving.']]],
        ['key' => 'consolidation_late_2', 'name' => 'Alert when a consolidation is 2 days past its ETA', 'trigger' => 'consolidation.late',
            'description' => 'An air load or sea container still not arrived 2 days after its ETA is flagged to the team.',
            'conditions' => [$days(2)],
            'actions' => [['type' => 'staff_in_app', 'subject' => '', 'message' => 'Consolidation [TRACKING] is past its ETA and has not arrived. Check with the airline / shipping line.'], ['type' => 'log', 'subject' => 'Consolidation past ETA', 'message' => '']]],
        ['key' => 'invoice_unpaid_7', 'name' => 'Payment reminder after 7 days', 'trigger' => 'invoice.unpaid',
            'description' => 'An unpaid invoice 7 days after the shipment was created gets a polite reminder.',
            'conditions' => [$days(7)],
            'actions' => [['type' => 'email', 'subject' => 'Payment reminder for [TRACKING]', 'message' => 'Hi [FIRST_NAME], the invoice for [TRACKING] ([AMOUNT]) is still open. You can pay it from your account at [URL].'],
                ['type' => 'in_app', 'subject' => '', 'message' => 'The invoice for [TRACKING] ([AMOUNT]) is still unpaid.']]],
        ['key' => 'pickup_unassigned_1', 'name' => 'Home pickups waiting a day for a rider', 'trigger' => 'pickup.unassigned',
            'description' => 'A home pickup with no rider a day after it was requested is flagged to dispatch.',
            'conditions' => [$days(1)],
            'actions' => [['type' => 'staff_in_app', 'subject' => '', 'message' => 'Pickup [TRACKING] for [NAME] has no rider after a day. Assign one on the dispatch board.']]],
        ['key' => 'customer_winback_90', 'name' => 'Win back customers after 90 quiet days', 'trigger' => 'customer.inactive',
            'description' => 'Customers who shipped before but not in 90 days get a friendly note with their US address.',
            'conditions' => [$days(90)],
            'actions' => [['type' => 'email', 'subject' => 'We miss you at [SITE_NAME]', 'message' => 'Hi [FIRST_NAME], your US address is still ready for your next order. Shop and ship with your locker [LOCKER]. [URL]']]],
        ['key' => 'prealert_thanks', 'name' => 'Confirm a new pre-alert to the customer', 'trigger' => 'prealert.created',
            'description' => 'When a customer raises a pre-alert, confirm it so they know we are watching for the parcel.',
            'conditions' => [],
            'actions' => [['type' => 'in_app', 'subject' => '', 'message' => 'We got your pre-alert [TRACKING]. We will tell you as soon as it reaches the warehouse.']]],
        ['key' => 'held_alert', 'name' => 'Tell the team when a parcel is put on hold', 'trigger' => 'shipment.status',
            'description' => 'A shipment moved to Held / On hold is flagged so someone follows up.',
            'conditions' => [['key' => 'status_is', 'value' => '28,29']],
            'actions' => [['type' => 'staff_in_app', 'subject' => '', 'message' => '[TRACKING] for [NAME] is on hold.'], ['type' => 'log', 'subject' => 'Parcel on hold', 'message' => '']]],
        ['key' => 'delivered_thanks', 'name' => 'Thank customers after delivery', 'trigger' => 'shipment.status',
            'description' => 'A thank-you message when a shipment is delivered or picked up.',
            'conditions' => [['key' => 'status_is', 'value' => '8,15']],
            'actions' => [['type' => 'whatsapp', 'subject' => '', 'message' => 'Hi [FIRST_NAME], [TRACKING] has been handed over. Thank you for shipping with [SITE_NAME].']]],
    ];
}

/** Rules built into the code: always on, shown on the Automations page so every rule is visible. */
function cdp_systemRules(): array
{
    $ttl = defined('CDP_PICKUP_VERIFIED_TTL') ? (int) round(CDP_PICKUP_VERIFIED_TTL / 60) : 30;
    return [
        ['area' => 'Pre-alerts', 'icon' => 'i-inbox', 'rule' => 'A parcel registered with the same carrier tracking number as an open pre-alert takes it over: the pre-alert shows the package number and is marked arrived.', 'where' => 'Package Intake, New package, New shipment'],
        ['area' => 'Pre-alerts', 'icon' => 'i-inbox', 'rule' => 'Typing a tracking number on Package Intake or New package lists matching pre-alerts to convert.', 'where' => 'Package Intake, New package'],
        ['area' => 'Pre-alerts', 'icon' => 'i-inbox', 'rule' => 'Customers can edit their own pre-alerts while they are open; staff with "edit pre-alerts" can edit, close, reopen and link any.', 'where' => 'Settings › Permissions'],
        ['area' => 'Pickup codes', 'icon' => 'i-lock', 'rule' => 'Every parcel gets a 6-digit pickup code when it is registered. The code is sent to the customer only when the parcel is ready.', 'where' => 'helpers/pickup_code.php'],
        ['area' => 'Pickup codes', 'icon' => 'i-lock', 'rule' => 'A handover needs the code. A verified code allows the handover for ' . $ttl . ' minutes; 5 wrong entries void the code.', 'where' => 'Warehouse Delivery, Hand Out'],
        ['area' => 'Pickup codes', 'icon' => 'i-lock', 'rule' => 'Warehouse / district administrators and finance can skip the code with a reason; the skip is logged.', 'where' => 'Settings › Permissions (skip the pickup code)'],
        ['area' => 'Customs', 'icon' => 'i-shield', 'rule' => 'A parcel awaiting customs clearance cannot be handed over, even with a valid code.', 'where' => 'Finance switch'],
        ['area' => 'Consolidations', 'icon' => 'i-layers', 'rule' => 'Consolidation codes are generated automatically: air uses the air prefix, sea uses C + the origin country code.', 'where' => 'New consolidation'],
        ['area' => 'Consolidations', 'icon' => 'i-layers', 'rule' => 'Only approved packages are offered for a new consolidation by default; dangerous goods are listed apart on the packing list.', 'where' => 'New consolidation, Packing list'],
        ['area' => 'Weights', 'icon' => 'i-scale', 'rule' => 'Charges use the greater of the actual and the volumetric weight (in lbs).', 'where' => 'Settings › Taxes and weights'],
        ['area' => 'Sign-in', 'icon' => 'i-lock', 'rule' => 'Staff and customers can sign in with a passkey or the phone\'s fingerprint once they have added one.', 'where' => 'Profile › Fingerprint sign-in'],
    ];
}

/** Insert missing suggestions (idempotent). */
function cdp_automationSuggestionsSync(): void
{
    if (!cdp_automationsReady() || !cdp_automationsReviewReady()) return;
    $have = [];
    foreach (sl_rows("SELECT suggest_key FROM cdb_automations WHERE suggest_key IS NOT NULL") as $r) $have[(string) $r->suggest_key] = true;
    foreach (cdp_automationSuggestions() as $s) {
        if (isset($have[$s['key']])) continue;
        $pub = !empty($s['published']);
        $db = new Conexion();
        $db->cdp_query('INSERT INTO cdb_automations (name, description, trigger_key, conditions, actions, enabled, origin, review_status, suggest_key, created_by, created_at, updated_at)
                        VALUES (:n, :ds, :t, :c, :a, :e, :o, :rs, :k, NULL, NOW(), NOW())');
        $db->bind(':n', $s['name']); $db->bind(':ds', $s['description']); $db->bind(':t', $s['trigger']); $db->bind(':c', json_encode($s['conditions'])); $db->bind(':a', json_encode($s['actions']));
        $db->bind(':e', $pub ? 1 : 0); $db->bind(':o', $pub ? 'system' : 'suggested'); $db->bind(':rs', $pub ? 'published' : 'suggested'); $db->bind(':k', $s['key']);
        try { $db->cdp_execute(); } catch (Throwable $e) { /* another request inserted it */ }
    }
}

function cdp_automationsReviewReady(): bool
{
    static $ok = null;
    if ($ok === null) {
        $ok = (int) sl_scalar("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_automations' AND COLUMN_NAME IN ('review_status','suggest_key','description')") === 3;
    }
    return $ok;
}

/** A rule parameter from a suggested rule (e.g. the days of "Close pre-alerts 30 days overdue"). */
function cdp_automationParam(string $suggestKey, string $param, $default)
{
    if (!cdp_automationsReady() || !cdp_automationsReviewReady()) return $default;
    $r = sl_row('SELECT conditions FROM cdb_automations WHERE suggest_key = :k', [':k' => $suggestKey]);
    if (!$r) return $default;
    foreach ((array) json_decode((string) $r->conditions, true) as $c) {
        if ($param === 'days' && ($c['key'] ?? '') === 'days_over') return (int) $c['value'];
    }
    return $default;
}

/** Staff who receive "tell the team" notifications. */
function cdp_automationStaffIds(): array
{
    require_once __DIR__ . '/querys.php';
    $ids = [];
    foreach ((array) (function_exists('cdp_getUsersAdminEmployees') ? cdp_getUsersAdminEmployees() : []) as $u) $ids[] = (int) $u->id;
    return array_values(array_unique(array_filter($ids)));
}

/**
 * Run every published rule on a scheduled trigger. Returns ['rules' => n, 'handled' => n].
 * A record is handled once per rule; "days" conditions pick which records match.
 */
function cdp_automationsScheduled(): array
{
    $sum = ['rules' => 0, 'handled' => 0];
    if (!cdp_automationsReady()) return $sum;
    $sched = cdp_automationScheduledTriggers();
    foreach (array_keys($sched) as $trigger) {
        $rules = cdp_automationsAll(true, $trigger);
        if (!$rules) continue;
        $min = PHP_INT_MAX;
        foreach ($rules as $a) {
            $d = 0;
            foreach ($a['conditions'] as $c) if (($c['key'] ?? '') === 'days_over') $d = (int) $c['value'];
            $min = min($min, $d);
        }
        $found = cdp_automationScheduledFind($trigger, $min === PHP_INT_MAX ? 0 : $min);
        foreach ($rules as $a) {
            $sum['rules']++;
            foreach ($found as $ctx) {
                $done = sl_scalar("SELECT id FROM cdb_automation_runs WHERE automation_id = :a AND entity_type = :t AND entity_id = :i AND outcome IN ('ok','failed') LIMIT 1",
                    [':a' => (int) $a['id'], ':t' => (string) $ctx['entity_type'], ':i' => (string) $ctx['entity_id']], null);
                if ($done) continue;
                $runs = cdp_automationsFire($trigger, $ctx, false, (int) $a['id']);
                if ($runs) $sum['handled']++;
            }
        }
    }
    return $sum;
}

/** Fallback scheduler without cron: at most every 15 minutes, after the page has been sent. */
function cdp_automationsTick(): void
{
    $f = rtrim(sys_get_temp_dir(), '/') . '/swiftlane_automations_' . substr(md5(CDP_ROOT), 0, 8) . '.tick';
    $last = @filemtime($f);
    if ($last && time() - $last < 900) return;
    @touch($f);
    register_shutdown_function(function () {
        if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
        try { cdp_automationsScheduled(); } catch (Throwable $e) { error_log('[automations tick] ' . $e->getMessage()); }
    });
}
