<?php
/**
 * ============================================================================
 * Communications > Outbox and Customer history: one read-only view of every
 * message that went out, is waiting to go, was blocked, or came in.
 *
 * cdp_commOutboxFeed() is a UNION over
 *   log    cdb_message_log      every attempt at a send primitive, from any
 *                               origin (old screens, King, the inbox, campaigns,
 *                               broadcasts, support, delivery requests...).
 *                               Attempts that belong to a Communication Center
 *                               outbox row (outbox_id) are shown inside that row.
 *   queue  cdb_comm_outbox      Communication Center messages: waiting, quiet
 *                               hours, retrying, cancelled, and the sent ones
 *                               whose attempts are linked by outbox_id.
 *   cs     cdb_cs_outbox        Support desk notices still waiting (sent ones
 *                               are in the log as customer_service).
 *   dlv    cdb_delivery_outbox  delivery-request notices still waiting.
 *   in     cdb_comm_messages    WhatsApp messages customers sent us.
 *
 * Unified statuses: sent, failed, skipped, queued, scheduled, retrying,
 * sending, cancelled, received. Origins: automation, campaign, individual,
 * inbox, king, support, account, delivery.
 *
 * Used by comms_outbox.php / comms_customers.php (views/sl/data/comms_outbox.php)
 * and ajax/comms/outbox_ajax.php. Writes only through the retry / cancel /
 * resend helpers at the end of the file.
 * ============================================================================
 */

require_once __DIR__ . '/comms.php';
require_once __DIR__ . '/message_log.php';
require_once __DIR__ . '/message_log_query.php';

if (!function_exists('cdp_commOutboxFeed')) {

    /** Origin types, in the order the filter shows them. */
    function cdp_commOutboxOrigins()
    {
        return [
            'automation' => 'Automatic (statuses and triggers)',
            'campaign'   => 'Campaigns and broadcasts',
            'individual' => 'Sent by staff',
            'inbox'      => 'WhatsApp Inbox',
            'king'       => 'King (automatic replies)',
            'support'    => 'Support desk',
            'delivery'   => 'Delivery requests',
            'account'    => 'Account and security',
        ];
    }

    /** Unified statuses: value => [label, pill class]. */
    function cdp_commOutboxStatuses()
    {
        return [
            'sent'      => ['Sent', 'delivered'],
            'failed'    => ['Failed', 'overdue'],
            'skipped'   => ['Not sent', 'skipped'],
            'queued'    => ['Waiting', 'sorting'],
            'scheduled' => ['Scheduled', 'sorting'],
            'retrying'  => ['Retrying', 'pending'],
            'sending'   => ['Sending', 'sorting'],
            'cancelled' => ['Cancelled', 'plain'],
            'received'  => ['Received', 'transit'],
        ];
    }

    /** Status filter choices (several unified statuses share one choice). */
    function cdp_commOutboxStatusFilters()
    {
        return ['sent' => 'Sent', 'failed' => 'Failed', 'skipped' => 'Not sent (blocked or skipped)', 'waiting' => 'Waiting to go',
            'cancelled' => 'Cancelled', 'received' => 'Received from customers'];
    }

    /**
     * Normalised filters from a request array. Also reads the old Message Logs
     * names (filter, search, recipient_id, batch_id) so old links keep working.
     */
    function cdp_commOutboxFilters(array $in = null)
    {
        $in = $in ?? $_GET;
        $s = function ($k, $max = 120) use ($in) {
            $v = isset($in[$k]) && !is_array($in[$k]) ? trim((string) $in[$k]) : '';
            return mb_substr($v, 0, $max);
        };
        $date = function ($k) use ($s) {
            $v = $s($k, 10);
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
        };
        $f = [
            'channel'  => preg_replace('/[^a-z]/', '', strtolower($s('channel', 12))),
            'status'   => preg_replace('/[^a-z]/', '', strtolower($s('status', 12))),
            'origin'   => preg_replace('/[^a-z]/', '', strtolower($s('origin', 12))),
            'source'   => preg_replace('/[^a-z0-9_]/', '', strtolower($s('source', 80))),
            'campaign' => preg_replace('/[^a-zA-Z0-9_\-]/', '', $s('campaign', 40)),
            'customer' => max(0, (int) ($in['customer'] ?? 0)),
            'q'        => $s('q', 120),
            'from'     => $date('from'),
            'to'       => $date('to'),
            'sent_by'  => (int) ($in['sent_by'] ?? 0),
            'template' => max(0, (int) ($in['template_id'] ?? 0)),
        ];
        // Old Message Logs names
        $old = $s('filter', 12);
        if ($f['channel'] === '' && in_array($old, ['sms', 'whatsapp', 'email'], true)) {
            $f['channel'] = $old;
        } elseif ($f['status'] === '' && $old === 'failed') {
            $f['status'] = 'failed';
        }
        if ($f['q'] === '' && $s('search') !== '') {
            $f['q'] = $s('search');
        }
        if (!$f['customer'] && (int) ($in['recipient_id'] ?? 0) > 0) {
            $f['customer'] = (int) $in['recipient_id'];
        }
        if ($f['campaign'] === '' && $s('batch_id', 40) !== '') {
            $f['campaign'] = preg_replace('/[^a-zA-Z0-9_\-]/', '', $s('batch_id', 40));
        }
        if (!isset(cdp_commChannels()[$f['channel']])) {
            $f['channel'] = '';
        }
        // Old Message Logs and queue status names
        $oldStatus = ['queued' => 'waiting', 'pending' => 'waiting', 'scheduled' => 'waiting', 'retrying' => 'waiting', 'retry' => 'waiting', 'sending' => 'waiting',
            'delivered' => 'sent', 'error' => 'failed', 'blocked' => 'skipped', 'suppressed' => 'cancelled', 'inbound' => 'received'];
        if (isset($oldStatus[$f['status']])) {
            $f['status'] = $oldStatus[$f['status']];
        }
        if (!isset(cdp_commOutboxStatusFilters()[$f['status']])) {
            $f['status'] = '';
        }
        if (!isset(cdp_commOutboxOrigins()[$f['origin']])) {
            $f['origin'] = '';
        }
        return $f;
    }

    /**
     * The Outbox list opens on the last 30 days (the log has no other bound
     * and grows forever). ?range=any, any date, a customer or a campaign
     * lifts it. Returns [filters, true when the default was applied].
     */
    function cdp_commOutboxDefaultRange(array $f, array $in = null)
    {
        $in = $in ?? $_GET;
        $any = isset($in['range']) && $in['range'] === 'any';
        if ($any || $f['from'] !== '' || $f['to'] !== '' || $f['customer'] || $f['campaign'] !== '') {
            return [$f, false];
        }
        $f['from'] = date('Y-m-d', strtotime('-30 days'));
        return [$f, true];
    }

    /** True when any filter narrows the list. */
    function cdp_commOutboxFiltered(array $f)
    {
        foreach ($f as $v) {
            if ($v !== '' && $v !== 0) {
                return true;
            }
        }
        return false;
    }

    /** SQL CASE giving the origin of a cdb_message_log row (alias l). */
    function cdp_commOutboxLogOriginSql()
    {
        return "CASE
            WHEN COALESCE(l.batch_id,'') LIKE 'campaign-%' OR COALESCE(l.batch_id,'') LIKE 'nl-%'
                 OR l.source IN ('newsletter','push_notification','push_notification_consolidation','push_notification_invoice') THEN 'campaign'
            WHEN l.source = 'comms_whatsapp_inbox' AND COALESCE(l.sent_by,0) = 0 THEN 'king'
            WHEN l.source = 'comms_whatsapp_inbox' THEN 'inbox'
            WHEN l.source IN ('comms_manual','comms_test','manual','sms_test','send_prices','general_notice','comms_custom','comms_whatsapp_web') OR l.source LIKE 'compose%' THEN 'individual'
            WHEN l.source = 'customer_service' OR l.entity_type = 'cs_ticket' THEN 'support'
            WHEN l.source = 'delivery_request' OR COALESCE(l.batch_id,'') LIKE 'delivery-outbox-%' THEN 'delivery'
            WHEN l.source IN ('otp','signup','user_account') THEN 'account'
            ELSE 'automation' END";
    }

    /** SQL CASE giving the unified status of a cdb_comm_outbox row (alias o). */
    function cdp_commOutboxQueueStatusSql()
    {
        // The queue's own clock (cdp_opsNow, PHP time) writes send_after, so compare with it, not the database's NOW().
        $now = preg_replace('/[^0-9: \-]/', '', (string) cdp_opsNow());
        return "CASE
            WHEN o.status = 'queued' AND o.attempts > 0 THEN 'retrying'
            WHEN o.status = 'queued' AND o.send_after > '" . $now . "' THEN 'scheduled'
            ELSE o.status END";
    }

    /** Is outbox_id on cdb_message_log (migrations/2026_11_27_message_log_outbox.sql)? */
    function cdp_commOutboxLinked()
    {
        static $ok = null;
        if ($ok === null) {
            // Check only: the page never alters the log table (the migration adds the column and its indexes).
            $ok = function_exists('cdp_msgEnsureOutboxColumn') ? (bool) cdp_msgEnsureOutboxColumn(false) : false;
        }
        return $ok;
    }

    /**
     * A search term as stored: a phone number typed with spaces or dashes loses
     * them, and a local Ghana number (0541234567) loses its trunk 0, so it also
     * finds +233541234567 and 233541234567.
     */
    function cdp_commOutboxSearchTerm($q)
    {
        $q = trim((string) $q);
        if (preg_match('/^\+?[\d\s().\-]{7,}$/', $q)) {
            $digits = preg_replace('/\D+/', '', $q);
            if (preg_match('/^0\d{8,10}$/', $digits)) {
                return substr($digits, 1);
            }
            return $digits;
        }
        return $q;
    }

    /** The Support desk queue's clock (it writes cdb_cs_outbox.send_after with cdp_csNow). */
    function cdp_commOutboxCsNow()
    {
        if (!function_exists('cdp_csNow') && is_file(__DIR__ . '/support.php')) {
            require_once __DIR__ . '/support.php';
        }
        return function_exists('cdp_csNow') ? (string) cdp_csNow() : (string) cdp_opsNow();
    }

    /** The database clock (the delivery-request worker writes available_at with NOW()). */
    function cdp_commOutboxDbNow()
    {
        static $now = null;
        if ($now === null) {
            $now = (string) cdp_opsVal('SELECT NOW()', [], '');
            if ($now === '') {
                $now = (string) cdp_opsNow();
            }
        }
        return $now;
    }

    /** "Now" as the queue that owns a row writes its send time. */
    function cdp_commOutboxQueueNow($k)
    {
        return $k === 'cs' ? cdp_commOutboxCsNow() : ($k === 'dlv' ? cdp_commOutboxDbNow() : (string) cdp_opsNow());
    }

    /**
     * The sub-queries of the union, each with its own binds (names prefixed so
     * the same filter can appear in every part). A part that cannot match the
     * filters is left out. Each: [select, from_where, binds].
     */
    function cdp_commOutboxParts(array $f)
    {
        $parts = [];
        $q = $f['q'] !== '' ? '%' . cdp_commOutboxSearchTerm($f['q']) . '%' : '';
        $statusIn = function ($want, array $map) {
            // $map: unified filter value => SQL condition for this part ('' = never)
            return $map[$want] ?? '0=1';
        };
        $waiting = ['queued', 'scheduled', 'retrying', 'sending'];

        // ---- log -------------------------------------------------------------
        if (cdp_msgTableReady() && !in_array($f['status'], ['waiting', 'cancelled', 'received'], true)) {
            $origin = cdp_commOutboxLogOriginSql();
            $w = ['1=1'];
            $b = [];
            if (cdp_commOutboxLinked()) {
                $w[] = '(l.outbox_id IS NULL OR l.outbox_id = 0)';
            }
            if ($f['channel'] !== '') { $w[] = 'l.channel = :l_ch'; $b[':l_ch'] = $f['channel']; }
            if ($f['status'] !== '') { $w[] = 'l.status = :l_st'; $b[':l_st'] = $f['status']; }
            if ($f['origin'] !== '') { $w[] = "($origin) = :l_or"; $b[':l_or'] = $f['origin']; }
            if ($f['source'] !== '') { $w[] = 'l.source = :l_src'; $b[':l_src'] = $f['source']; }
            if ($f['campaign'] !== '') {
                $w[] = 'l.batch_id = :l_bt';
                $b[':l_bt'] = ctype_digit($f['campaign']) ? 'campaign-' . $f['campaign'] : $f['campaign'];
            }
            if ($f['customer']) { $w[] = 'l.recipient_user_id = :l_u'; $b[':l_u'] = $f['customer']; }
            if ($f['from'] !== '') { $w[] = 'l.created_at >= :l_from'; $b[':l_from'] = $f['from'] . ' 00:00:00'; }
            if ($f['to'] !== '') { $w[] = 'l.created_at <= :l_to'; $b[':l_to'] = $f['to'] . ' 23:59:59'; }
            if ($f['sent_by'] > 0) { $w[] = 'l.sent_by = :l_by'; $b[':l_by'] = $f['sent_by']; }
            if ($f['template'] > 0) { $w[] = 'l.template_id = :l_tpl'; $b[':l_tpl'] = $f['template']; }
            if ($q !== '') {
                $w[] = '(l.recipient_name LIKE :l_q1 OR l.recipient_to LIKE :l_q2 OR l.subject LIKE :l_q3 OR l.entity_label LIKE :l_q4 OR l.source_label LIKE :l_q5 OR l.status_detail LIKE :l_q6 OR l.body LIKE :l_q7)';
                for ($i = 1; $i <= 7; $i++) { $b[':l_q' . $i] = $q; }
            }
            $parts['log'] = ["SELECT 'log' AS k, l.id AS id, l.created_at AS at, l.channel AS channel, l.status AS st, ($origin) AS origin,
                    l.source AS source, l.source_label AS label, COALESCE(l.recipient_user_id,0) AS uid, l.recipient_name AS name, l.recipient_to AS rto,
                    l.subject AS subject, LEFT(l.body, 3000) AS body, 1 AS attempts, l.status_detail AS detail, NULL AS campaign_id, l.batch_id AS batch,
                    l.entity_type AS etype, l.entity_id AS eid, l.entity_label AS elabel, l.sent_by_name AS by_name, l.template_id AS tpl, NULL AS send_after",
                'FROM cdb_message_log l WHERE ' . implode(' AND ', $w), $b];
        }

        // ---- Communication Center outbox --------------------------------------
        if (cdp_opsTableExists('cdb_comm_outbox') && !in_array($f['status'], ['skipped', 'received'], true)
            && !in_array($f['origin'], ['inbox', 'king', 'support', 'delivery', 'account'], true)) {
            $st = cdp_commOutboxQueueStatusSql();
            $originSql = "CASE WHEN o.campaign_id IS NOT NULL AND o.campaign_id > 0 THEN 'campaign' WHEN o.event_key IN ('manual','custom') THEN 'individual' ELSE 'automation' END";
            $w = [];
            $b = [];
            // Sent rows without linked attempts were sent before outbox_id existed: the log part shows them.
            $w[] = cdp_commOutboxLinked()
                ? "(o.status <> 'sent' OR EXISTS (SELECT 1 FROM cdb_message_log lx WHERE lx.outbox_id = o.id))"
                : "o.status <> 'sent'";
            if ($f['channel'] !== '') { $w[] = 'o.channel = :o_ch'; $b[':o_ch'] = $f['channel']; }
            if ($f['status'] === 'waiting') {
                $w[] = "o.status IN ('queued','sending')";
            } elseif ($f['status'] !== '') {
                $w[] = 'o.status = :o_st';
                $b[':o_st'] = $f['status'];
            }
            if ($f['origin'] !== '') { $w[] = "($originSql) = :o_or"; $b[':o_or'] = $f['origin']; }
            if ($f['source'] !== '') {
                $keys = [];
                foreach (cdp_opsQ("SELECT DISTINCT event_key FROM cdb_comm_outbox") as $r) {
                    if (cdp_commTriggerSource((string) $r->event_key) === $f['source']) {
                        $keys[] = (string) $r->event_key;
                    }
                }
                if (!$keys) {
                    $w[] = '0=1';
                } else {
                    $in = [];
                    foreach ($keys as $i => $k) { $in[] = ':o_k' . $i; $b[':o_k' . $i] = $k; }
                    $w[] = 'o.event_key IN (' . implode(',', $in) . ')';
                }
            }
            if ($f['campaign'] !== '') {
                if (ctype_digit($f['campaign']) || preg_match('/^campaign-(\d+)$/', $f['campaign'], $m)) {
                    $w[] = 'o.campaign_id = :o_cid';
                    $b[':o_cid'] = (int) (ctype_digit($f['campaign']) ? $f['campaign'] : $m[1]);
                } else {
                    $w[] = '0=1';
                }
            }
            if ($f['customer']) { $w[] = 'o.user_id = :o_u'; $b[':o_u'] = $f['customer']; }
            if ($f['from'] !== '') { $w[] = 'o.created_at >= :o_from'; $b[':o_from'] = $f['from'] . ' 00:00:00'; }
            if ($f['to'] !== '') { $w[] = 'o.created_at <= :o_to'; $b[':o_to'] = $f['to'] . ' 23:59:59'; }
            if ($f['sent_by'] > 0) { $w[] = 'o.created_by = :o_by'; $b[':o_by'] = $f['sent_by']; }
            if ($f['template'] > 0) { $w[] = 'o.template_id = :o_tpl'; $b[':o_tpl'] = $f['template']; }
            if ($q !== '') {
                $w[] = "(o.recipient_to LIKE :o_q1 OR o.entity_ref LIKE :o_q2 OR o.subject LIKE :o_q3 OR o.body LIKE :o_q4 OR o.last_error LIKE :o_q5
                         OR CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')) LIKE :o_q6 OR u.locker LIKE :o_q7)";
                for ($i = 1; $i <= 7; $i++) { $b[':o_q' . $i] = $q; }
            }
            $parts['queue'] = ["SELECT 'queue' AS k, o.id AS id, COALESCE(o.sent_at, o.created_at) AS at, o.channel AS channel, ($st) AS st, ($originSql) AS origin,
                    o.event_key AS source, '' AS label, o.user_id AS uid, TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) AS name,
                    COALESCE(NULLIF(o.recipient_to,''), CASE WHEN o.channel = 'email' THEN u.email ELSE u.phone END) AS rto,
                    o.subject AS subject, LEFT(o.body, 3000) AS body, o.attempts AS attempts, o.last_error AS detail, o.campaign_id AS campaign_id,
                    CASE WHEN o.campaign_id > 0 THEN CONCAT('campaign-', o.campaign_id) ELSE '' END AS batch,
                    o.entity_type AS etype, '' AS eid, o.entity_ref AS elabel, CAST(o.created_by AS CHAR) AS by_name, o.template_id AS tpl, o.send_after AS send_after",
                'FROM cdb_comm_outbox o LEFT JOIN cdb_users u ON u.id = o.user_id WHERE ' . implode(' AND ', $w), $b];
        }

        // ---- Support desk queue (waiting rows only) -----------------------------
        if (cdp_opsTableExists('cdb_cs_outbox') && in_array($f['status'], ['', 'waiting', 'cancelled'], true)
            && in_array($f['origin'], ['', 'support'], true) && ($f['source'] === '' || $f['source'] === 'customer_service')
            && $f['campaign'] === '' && $f['sent_by'] <= 0 && !$f['template']) {
            $w = ["c.status IN ('queued','retry','sending','suppressed')"];
            $b = [];
            if ($f['status'] === 'waiting') { $w[] = "c.status IN ('queued','retry','sending')"; }
            if ($f['status'] === 'cancelled') { $w[] = "c.status = 'suppressed'"; }
            if ($f['channel'] !== '') { $w[] = 'c.channel = :c_ch'; $b[':c_ch'] = $f['channel']; }
            if ($f['customer']) { $w[] = 'c.user_id = :c_u'; $b[':c_u'] = $f['customer']; }
            if ($f['from'] !== '') { $w[] = 'c.created_at >= :c_from'; $b[':c_from'] = $f['from'] . ' 00:00:00'; }
            if ($f['to'] !== '') { $w[] = 'c.created_at <= :c_to'; $b[':c_to'] = $f['to'] . ' 23:59:59'; }
            if ($q !== '') {
                $w[] = "(c.subject LIKE :c_q1 OR c.body LIKE :c_q2 OR CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')) LIKE :c_q3 OR u.email LIKE :c_q4 OR u.phone LIKE :c_q5)";
                for ($i = 1; $i <= 5; $i++) { $b[':c_q' . $i] = $q; }
            }
            // The Support desk writes send_after with its own clock (cdp_csNow), so compare with that.
            $csNow = preg_replace('/[^0-9: \-]/', '', cdp_commOutboxCsNow());
            $parts['cs'] = ["SELECT 'cs' AS k, c.id AS id, c.created_at AS at, c.channel AS channel,
                    CASE c.status WHEN 'retry' THEN 'retrying' WHEN 'suppressed' THEN 'cancelled' WHEN 'queued' THEN CASE WHEN c.send_after > '" . $csNow . "' THEN 'scheduled' ELSE 'queued' END ELSE c.status END AS st,
                    'support' AS origin, 'customer_service' AS source, CONCAT('Support desk: ', c.kind) AS label, c.user_id AS uid,
                    TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) AS name, CASE WHEN c.channel = 'email' THEN u.email ELSE u.phone END AS rto,
                    c.subject AS subject, LEFT(c.body, 3000) AS body, c.attempts AS attempts, c.last_error AS detail, NULL AS campaign_id, '' AS batch,
                    'cs_ticket' AS etype, CAST(c.ticket_id AS CHAR) AS eid, '' AS elabel, '' AS by_name, NULL AS tpl, c.send_after AS send_after",
                'FROM cdb_cs_outbox c LEFT JOIN cdb_users u ON u.id = c.user_id WHERE ' . implode(' AND ', $w), $b];
        }

        // ---- Delivery-request notices (waiting rows only; all of the customer's channels) ----
        if (cdp_opsTableExists('cdb_delivery_outbox') && in_array($f['status'], ['', 'waiting', 'cancelled'], true)
            && in_array($f['origin'], ['', 'delivery'], true) && ($f['source'] === '' || $f['source'] === 'delivery_request')
            && $f['channel'] === '' && ($f['campaign'] === '' || preg_match('/^delivery-outbox-\d+$/', $f['campaign'])) && $f['sent_by'] <= 0 && !$f['template']) {
            $w = ["d.state IN ('pending','sending','cancelled')"];
            $b = [];
            if ($f['campaign'] !== '') {
                // "All its messages" of a delivery notice: the notice itself and its logged attempts.
                $w[] = 'd.id = :d_id';
                $b[':d_id'] = (int) substr($f['campaign'], 16);
            }
            if ($f['status'] === 'waiting') { $w[] = "d.state IN ('pending','sending')"; }
            if ($f['status'] === 'cancelled') { $w[] = "d.state = 'cancelled'"; }
            if ($f['customer']) { $w[] = 'd.recipient_id = :d_u'; $b[':d_u'] = $f['customer']; }
            if ($f['from'] !== '') { $w[] = 'd.created_at >= :d_from'; $b[':d_from'] = $f['from'] . ' 00:00:00'; }
            if ($f['to'] !== '') { $w[] = 'd.created_at <= :d_to'; $b[':d_to'] = $f['to'] . ' 23:59:59'; }
            if ($q !== '') {
                $w[] = "(d.subject LIKE :d_q1 OR d.message LIKE :d_q2 OR CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')) LIKE :d_q3)";
                for ($i = 1; $i <= 3; $i++) { $b[':d_q' . $i] = $q; }
            }
            $parts['dlv'] = ["SELECT 'dlv' AS k, d.id AS id, d.created_at AS at, '' AS channel,
                    CASE WHEN d.state = 'sending' THEN 'sending' WHEN d.state = 'cancelled' THEN 'cancelled' WHEN d.attempts > 0 THEN 'retrying'
                         WHEN d.available_at > NOW() THEN 'scheduled' ELSE 'queued' END AS st,
                    'delivery' AS origin, 'delivery_request' AS source, 'Delivery request notice' AS label, d.recipient_id AS uid,
                    TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) AS name, COALESCE(NULLIF(u.phone,''), u.email) AS rto,
                    d.subject AS subject, LEFT(d.message, 3000) AS body, d.attempts AS attempts, '' AS detail, NULL AS campaign_id,
                    CONCAT('delivery-outbox-', d.id) AS batch, 'delivery_request' AS etype, '' AS eid, d.event_key AS elabel, '' AS by_name, NULL AS tpl, d.available_at AS send_after",
                'FROM cdb_delivery_outbox d LEFT JOIN cdb_users u ON u.id = d.recipient_id WHERE ' . implode(' AND ', $w), $b];
        }

        // ---- What customers sent us (WhatsApp) -----------------------------------
        if (cdp_opsTableExists('cdb_comm_messages') && in_array($f['status'], ['', 'received'], true)
            && in_array($f['origin'], ['', 'inbox'], true) && in_array($f['channel'], ['', 'whatsapp'], true)
            && ($f['source'] === '' || $f['source'] === 'comms_whatsapp_inbox') && $f['campaign'] === '' && $f['sent_by'] <= 0 && !$f['template']) {
            $w = ["m.direction = 'in'"];
            $b = [];
            if ($f['customer']) { $w[] = 't.user_id = :m_u'; $b[':m_u'] = $f['customer']; }
            if ($f['from'] !== '') { $w[] = 'm.created_at >= :m_from'; $b[':m_from'] = $f['from'] . ' 00:00:00'; }
            if ($f['to'] !== '') { $w[] = 'm.created_at <= :m_to'; $b[':m_to'] = $f['to'] . ' 23:59:59'; }
            if ($q !== '') {
                $w[] = '(m.body LIKE :m_q1 OR t.phone LIKE :m_q2 OR t.contact_name LIKE :m_q3)';
                for ($i = 1; $i <= 3; $i++) { $b[':m_q' . $i] = $q; }
            }
            $parts['in'] = ["SELECT 'in' AS k, m.id AS id, m.created_at AS at, 'whatsapp' AS channel, 'received' AS st, 'inbox' AS origin,
                    'comms_whatsapp_inbox' AS source, 'Customer message' AS label, COALESCE(t.user_id,0) AS uid,
                    COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))),''), t.contact_name) AS name, CONCAT('+', t.phone) AS rto,
                    '' AS subject, LEFT(m.body, 3000) AS body, 0 AS attempts, '' AS detail, NULL AS campaign_id, '' AS batch,
                    'whatsapp_thread' AS etype, CAST(t.id AS CHAR) AS eid, CONCAT('+', t.phone) AS elabel, '' AS by_name, NULL AS tpl, NULL AS send_after",
                'FROM cdb_comm_messages m JOIN cdb_comm_threads t ON t.id = m.thread_id LEFT JOIN cdb_users u ON u.id = t.user_id WHERE ' . implode(' AND ', $w), $b];
        }
        return $parts;
    }

    /**
     * One page of the unified list, newest first.
     * @return array{rows:array,total:int,page:int,per:int,pages:int,by_part:array}
     */
    function cdp_commOutboxFeed(array $f, $page = 1, $per = 50)
    {
        $page = max(1, (int) $page);
        $per = max(1, min(10000, (int) $per));
        $parts = cdp_commOutboxParts($f);
        $out = ['rows' => [], 'total' => 0, 'page' => $page, 'per' => $per, 'pages' => 1, 'by_part' => []];
        if (!$parts) {
            return $out;
        }
        $sel = [];
        $binds = [];
        $top = $page * $per;
        foreach ($parts as $name => $p) {
            $n = (int) cdp_opsVal('SELECT COUNT(*) ' . $p[1], $p[2]);
            $out['by_part'][$name] = $n;
            $out['total'] += $n;
            if ($n > 0) {
                // each part already sorted and cut to what the page can need
                $sel[] = '(' . $p[0] . ' ' . $p[1] . ' ORDER BY at DESC, id DESC LIMIT ' . $top . ')';
                $binds += $p[2];
            }
        }
        $out['pages'] = max(1, (int) ceil($out['total'] / $per));
        if (!$sel) {
            return $out;
        }
        $rows = cdp_opsQ('SELECT * FROM (' . implode(' UNION ALL ', $sel) . ') x ORDER BY at DESC, k ASC, id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per), $binds);
        $out['rows'] = cdp_commOutboxDecorate($rows);
        return $out;
    }

    /** Plain-text excerpt of a body: e-mail head and styles removed. */
    function cdp_commOutboxExcerpt($body, $n = 140)
    {
        $b = (string) $body;
        $b = str_replace('<!--sl-html-->', '', $b);
        $b = preg_replace('~<(head|style|script|title)\b[^>]*>.*?(</\1>|$)~is', ' ', $b);
        $b = preg_replace('~<br\s*/?>|</p>|</div>|</h\d>~i', ' ', $b);
        $b = html_entity_decode(strip_tags($b), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return cdp_opsClip($b, $n);
    }

    /** Billable units: 1, or the SMS part count when Message Costs bills per part. */
    function cdp_commOutboxUnits($channel, $body)
    {
        if ($channel !== 'sms') {
            return 1;
        }
        static $parts = null;
        if ($parts === null) {
            $parts = function_exists('cdp_mcSettings') ? (int) (cdp_mcSettings()['sms_parts'] ?? 0) === 1 : false;
        }
        if (!$parts) {
            return 1;
        }
        $len = mb_strlen((string) $body);
        return $len > 160 ? (int) ceil($len / 153) : 1;
    }

    /** Per-message cost of a sent row, or null when no rate is set. */
    function cdp_commOutboxCost($channel, $status, $body)
    {
        if ($status !== 'sent' || !in_array($channel, ['whatsapp', 'sms', 'email'], true)) {
            return null;
        }
        if (!function_exists('cdp_notifyCostPerMessage')) {
            if (is_file(__DIR__ . '/message_cost.php')) {
                require_once __DIR__ . '/message_cost.php';
            }
            if (!function_exists('cdp_notifyCostPerMessage')) {
                return null;
            }
        }
        try {
            $rate = (float) cdp_notifyCostPerMessage($channel);
        } catch (Throwable $e) {
            return null;
        }
        return $rate > 0 ? round($rate * cdp_commOutboxUnits($channel, $body), 4) : null;
    }

    /** Provider named in a result line ("Accepted by UltraMsg (id 8046)" -> UltraMsg). */
    function cdp_commOutboxProvider($detail, $channel = '')
    {
        $d = (string) $detail;
        if (preg_match('/Accepted by ([A-Za-z][\w .-]*?)(?:\s+server)?(?:\s*\(|\.|$)/', $d, $m)) {
            return trim($m[1]);
        }
        foreach (['UltraMsg', 'Cloud API', 'Meta', 'Hubtel', 'mNotify', 'SMTP', 'PHP mail()'] as $p) {
            if (stripos($d, $p) !== false) {
                return $p;
            }
        }
        return '';
    }

    /** Campaign / broadcast name of a batch id. */
    function cdp_commOutboxBatchName($batch, array $campaignNames = [])
    {
        $batch = (string) $batch;
        if ($batch === '') {
            return '';
        }
        if (preg_match('/^campaign-(\d+)$/', $batch, $m)) {
            return $campaignNames[(int) $m[1]] ?? ('Campaign #' . (int) $m[1]);
        }
        if (strpos($batch, 'nl-') === 0) {
            return 'Newsletter ' . substr($batch, 3, 6);
        }
        if (strpos($batch, 'push-') === 0) {
            return 'Broadcast ' . substr($batch, 5, 6);
        }
        if (strpos($batch, 'con-') === 0) {
            return 'Consolidation update';
        }
        if (strpos($batch, 'delivery-outbox-') === 0) {
            return 'Delivery notice ' . substr($batch, 16);
        }
        return $batch;
    }

    /** Names of the comms campaigns in a set of rows. */
    function cdp_commOutboxCampaignNames(array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids || !cdp_opsTableExists('cdb_comm_campaigns')) {
            return [];
        }
        $out = [];
        foreach (cdp_opsQ('SELECT id, name FROM cdb_comm_campaigns WHERE id IN (' . implode(',', $ids) . ')') as $r) {
            $out[(int) $r->id] = (string) $r->name;
        }
        return $out;
    }

    /** Opens and clicks of e-mail rows that carry a batch id: "batch|to" => [opens, clicks]. */
    function cdp_commOutboxEngagement(array $pairs)
    {
        $out = [];
        if (!$pairs || !cdp_opsTableExists('cdb_message_events')) {
            return $out;
        }
        $batches = array_values(array_unique(array_map(function ($p) { return $p[0]; }, $pairs)));
        $in = [];
        $b = [];
        foreach (array_slice($batches, 0, 200) as $i => $bt) {
            $in[] = ':eb' . $i;
            $b[':eb' . $i] = $bt;
        }
        foreach (cdp_opsQ("SELECT batch_id, LOWER(recipient_to) rto, SUM(event = 'open') opens, SUM(event = 'click') clicks FROM cdb_message_events
                           WHERE batch_id IN (" . implode(',', $in) . ") GROUP BY batch_id, LOWER(recipient_to)", $b) as $r) {
            $out[$r->batch_id . '|' . $r->rto] = [(int) $r->opens, (int) $r->clicks];
        }
        return $out;
    }

    /** Link to the record a message is about, or ''. */
    function cdp_commOutboxRecordLink($type, $id, $label)
    {
        $type = (string) $type;
        $id = (string) $id;
        $label = (string) $label;
        if ($type === 'whatsapp_thread') {
            if ((int) $id <= 0 && $label !== '' && cdp_opsTableExists('cdb_comm_threads')) {
                $t = cdp_opsOne('SELECT id FROM cdb_comm_threads WHERE phone = :p LIMIT 1', [':p' => ltrim($label, '+')]);
                $id = $t ? (string) (int) $t->id : '';
            }
            return (int) $id > 0 ? sl_route('CommsInbox') . '#c=' . (int) $id : '';
        }
        if ($type === 'cs_ticket') {
            return (int) $id > 0 ? sl_route('CsTicket', ['id' => (int) $id]) : '';
        }
        if ($type === 'campaign') {
            return (int) $id > 0 ? sl_route('Campaigns', ['batch' => 'campaign-' . (int) $id]) : '';
        }
        if ($type === 'delivery_request') {
            return (int) $id > 0 ? sl_route('DeliveryDispatch', ['state' => 'all', 'id' => (int) $id]) : '';
        }
        $boards = ['shipment' => 'ShipmentDetail', 'order' => 'ShipmentDetail', 'package' => 'PackageDetail', 'consolidation' => 'ConsolidationDetail'];
        if (isset($boards[$type]) && (int) $id > 0) {
            return sl_route($boards[$type], ['id' => (int) $id]);
        }
        // Communication Center rows name the tracking number only (older rows, manual sends).
        if ($label !== '' && preg_match('/^[A-Za-z0-9\-]{4,40}$/', $label) && !in_array($type, ['broadcast', 'delivery_request'], true)) {
            $info = cdp_commResolveTrack($label);
            if (!empty($info['order_id'])) {
                if ($info['type'] === 'shipment') {
                    return sl_route('ShipmentDetail', ['id' => (int) $info['order_id']]);
                }
                if ($info['type'] === 'package') {
                    return sl_route('PackageDetail', ['id' => (int) $info['order_id']]);
                }
                if ($info['type'] === 'consolidation') {
                    // consolidate_packages consolidations have their own view (consolidate_package_view.php).
                    if (($info['module'] ?? '') === 'consolidate_packages') {
                        return 'consolidate_package_view.php?' . http_build_query(['id' => (int) $info['order_id']]);
                    }
                    return sl_route('ConsolidationDetail', ['id' => (int) $info['order_id']]);
                }
            }
        }
        return '';
    }

    /** Human origin line of a row: "Status: Arrived in Ghana", "Campaign: Black Friday", "Sent by Kofi". */
    function cdp_commOutboxOriginLine($r, array $campaignNames)
    {
        $label = trim((string) ($r->label ?? ''));
        $label = preg_replace('/^Communication Center:\s*/', '', $label);
        if ($r->k === 'queue') {
            if ((int) $r->campaign_id > 0) {
                return 'Campaign: ' . ($campaignNames[(int) $r->campaign_id] ?? '#' . (int) $r->campaign_id);
            }
            if (in_array((string) $r->source, ['manual', 'custom'], true)) {
                return (int) $r->by_name > 0 ? 'Sent by ' . cdp_opsUserName((int) $r->by_name) : 'Message from our team';
            }
            return cdp_commTriggerLabel((string) $r->source);
        }
        if ($r->origin === 'campaign' && (string) $r->batch !== '') {
            $n = cdp_commOutboxBatchName((string) $r->batch, $campaignNames);
            if (strpos((string) $r->batch, 'campaign-') === 0) {
                return 'Campaign: ' . $n;
            }
            return $label !== '' ? $label : $n;
        }
        if ($r->origin === 'king') {
            return cdp_commAiNameSafe() . ' (automatic reply)';
        }
        if ($r->origin === 'inbox' && $r->k === 'log') {
            return 'Inbox reply' . ((string) $r->by_name !== '' ? ' by ' . $r->by_name : '');
        }
        if ($r->origin === 'individual' && (string) $r->by_name !== '' && stripos((string) $r->by_name, 'system') !== 0) {
            return ($label !== '' ? $label : 'Message') . ' · ' . $r->by_name;
        }
        if ($label === '' && (string) $r->source !== '') {
            $label = function_exists('cdp_msgSourceLabel') ? cdp_msgSourceLabel((string) $r->source) : (string) $r->source;
        }
        if ($r->k === 'log' && $r->origin === 'automation' && strpos((string) $r->source, 'comms_') !== 0 && $label !== '') {
            return $label . ' (old screen)';
        }
        return $label !== '' ? $label : 'Message';
    }

    function cdp_commAiNameSafe()
    {
        return function_exists('cdp_kingName') ? cdp_kingName() : 'King';
    }

    /** Lookup key of an address: lower-case e-mail, or the last 9 digits of a phone number. */
    function cdp_commOutboxAddrKey($to)
    {
        $to = trim((string) $to);
        if ($to === '') {
            return '';
        }
        if (strpos($to, '@') !== false) {
            return 'e:' . strtolower($to);
        }
        $digits = preg_replace('/\D+/', '', $to);
        return strlen($digits) >= 7 ? 'p:' . substr($digits, -9) : '';
    }

    /**
     * Customers behind rows that carry only an address (campaign sends to a
     * number): one query per page. Returns addrKey => [id, name]; an address
     * shared by two accounts stays unknown.
     */
    function cdp_commOutboxRecipients(array $rows)
    {
        $mails = [];
        $tails = [];
        foreach ($rows as $r) {
            if ((int) $r->uid > 0 || trim((string) $r->name) !== '' || !in_array($r->k, ['log', 'queue'], true)) {
                continue;
            }
            $k = cdp_commOutboxAddrKey((string) $r->rto);
            if (strpos($k, 'e:') === 0) {
                $mails[substr($k, 2)] = true;
            } elseif (strpos($k, 'p:') === 0) {
                $tails[substr($k, 2)] = true;
            }
        }
        if (!$mails && !$tails) {
            return [];
        }
        $w = [];
        $b = [];
        $i = 0;
        if ($mails) {
            $in = [];
            foreach (array_slice(array_keys($mails), 0, 60) as $m) { $in[] = ':re' . $i; $b[':re' . $i++] = $m; }
            $w[] = 'LOWER(email) IN (' . implode(',', $in) . ')';
        }
        if ($tails) {
            $in = [];
            foreach (array_slice(array_keys($tails), 0, 60) as $t) { $in[] = ':rp' . $i; $b[':rp' . $i++] = (string) $t; }
            $w[] = "RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone,''),'+',''),' ',''),'-',''),'(',''),')',''), 9) IN (" . implode(',', $in) . ')';
        }
        $out = [];
        $seen = [];
        foreach (cdp_opsQ('SELECT id, fname, lname, email, phone FROM cdb_users WHERE ' . implode(' OR ', $w) . ' LIMIT 300', $b) as $u) {
            $name = trim($u->fname . ' ' . $u->lname);
            foreach ([cdp_commOutboxAddrKey((string) $u->email), cdp_commOutboxAddrKey((string) $u->phone)] as $k) {
                if ($k === '') {
                    continue;
                }
                $seen[$k] = ($seen[$k] ?? 0) + 1;
                $out[$k] = [(int) $u->id, $name];
            }
        }
        foreach ($seen as $k => $n) {
            if ($n > 1) {
                unset($out[$k]);
            }
        }
        return $out;
    }

    /** Channels each delivery-request notice was tried on (result_json keys): id => [channels]. */
    function cdp_commOutboxDeliveryChannels(array $rows)
    {
        $ids = [];
        foreach ($rows as $r) {
            if ($r->k === 'dlv') {
                $ids[] = (int) $r->id;
            }
        }
        if (!$ids || !cdp_opsTableExists('cdb_delivery_outbox')) {
            return [];
        }
        $out = [];
        foreach (cdp_opsQ('SELECT id, result_json FROM cdb_delivery_outbox WHERE id IN (' . implode(',', array_map('intval', array_slice($ids, 0, 500))) . ')') as $x) {
            $j = json_decode((string) $x->result_json, true);
            $out[(int) $x->id] = is_array($j) ? array_values(array_intersect(['whatsapp', 'sms', 'email'], array_keys($j))) : [];
        }
        return $out;
    }

    /** Rows from the union -> arrays for the page and the endpoint. */
    function cdp_commOutboxDecorate(array $rows)
    {
        $cids = [];
        $pairs = [];
        foreach ($rows as $r) {
            if ((int) $r->campaign_id > 0) {
                $cids[] = (int) $r->campaign_id;
            }
            if (preg_match('/^campaign-(\d+)$/', (string) $r->batch, $m)) {
                $cids[] = (int) $m[1];
            }
            if ($r->channel === 'email' && (string) $r->batch !== '' && (string) $r->rto !== '') {
                $pairs[] = [(string) $r->batch, strtolower((string) $r->rto)];
            }
        }
        $names = cdp_commOutboxCampaignNames($cids);
        $eng = cdp_commOutboxEngagement($pairs);
        $who = cdp_commOutboxRecipients($rows);
        $dlvChannels = cdp_commOutboxDeliveryChannels($rows);
        $st = cdp_commOutboxStatuses();
        $origins = cdp_commOutboxOrigins();
        $out = [];
        foreach ($rows as $r) {
            $status = (string) $r->st;
            $e = $eng[(string) $r->batch . '|' . strtolower((string) $r->rto)] ?? null;
            $cost = cdp_commOutboxCost((string) $r->channel, $status, (string) $r->body);
            $name = trim((string) $r->name);
            $uid = (int) $r->uid;
            if ($uid <= 0 && $name === '' && in_array($r->k, ['log', 'queue'], true)) {
                $hit = $who[cdp_commOutboxAddrKey((string) $r->rto)] ?? null;
                if ($hit) {
                    [$uid, $name] = $hit;
                }
            }
            // Delivery-request notices: the request they are about, the channels actually tried.
            if ($r->k === 'dlv') {
                $r = clone $r;
                $ek = (string) $r->elabel;
                if (preg_match('/^(\d+):/', $ek, $m)) {
                    $r->elabel = 'Delivery request #' . (int) $m[1];
                    $r->eid = (string) (int) $m[1];
                } elseif (strpos($ek, 'ready:') === 0) {
                    $r->elabel = 'Ready for pickup';
                    $r->eid = '';
                }
            }
            $batch = (string) $r->batch;
            $isCampaign = (int) $r->campaign_id > 0 || preg_match('/^(campaign-\d+|nl-.+|push-.+)$/', $batch);
            $chLabel = $r->channel === '' ? 'All channels' : cdp_mlChannelLabel($r->channel);
            if ($r->k === 'dlv') {
                $tried = $dlvChannels[(int) $r->id] ?? [];
                $chLabel = $tried ? implode(', ', array_map('cdp_mlChannelLabel', $tried)) : 'Customer\'s channels';
            }
            // Waiting well past its send time: the worker that sends it may not be running.
            $overdue = false;
            if (in_array($r->k, ['queue', 'cs', 'dlv'], true) && in_array($status, ['queued', 'retrying'], true) && (string) $r->send_after !== '') {
                $overdue = strtotime((string) $r->send_after) < strtotime(cdp_commOutboxQueueNow((string) $r->k)) - 15 * 60;
            }
            $out[] = [
                'k' => (string) $r->k, 'id' => (int) $r->id, 'ref' => $r->k . '-' . (int) $r->id,
                'at' => (string) $r->at, 'when' => sl_date($r->at, 'M j, H:i'), 'ago' => sl_ago($r->at),
                'channel' => (string) $r->channel, 'channel_label' => $chLabel,
                'status' => $status, 'status_label' => $overdue ? 'Overdue' : ($st[$status][0] ?? ucfirst($status)), 'cls' => $overdue ? 'overdue' : ($st[$status][1] ?? 'plain'),
                'overdue' => $overdue,
                'origin' => (string) $r->origin, 'origin_label' => $origins[$r->origin] ?? ucfirst((string) $r->origin),
                'what' => cdp_commOutboxOriginLine($r, $names),
                'uid' => $uid, 'name' => $name !== '' ? $name : '', 'to' => (string) $r->rto,
                'subject' => (string) $r->subject, 'excerpt' => cdp_commOutboxExcerpt($r->body),
                'attempts' => (int) $r->attempts,
                'detail' => $overdue && (string) $r->detail === '' ? 'Should have gone out ' . sl_date($r->send_after, 'M j, H:i') . '. The worker that sends these may not be running.' : (string) $r->detail,
                'short' => $overdue ? 'Due ' . sl_ago($r->send_after) . ' - is the worker running?' : (function_exists('sl_msgShortDetail') ? sl_msgShortDetail((string) $r->detail) : cdp_opsClip($r->detail, 42)),
                'provider' => cdp_commOutboxProvider((string) $r->detail, (string) $r->channel),
                'campaign' => (int) $r->campaign_id > 0 ? ($names[(int) $r->campaign_id] ?? '') : ($isCampaign ? cdp_commOutboxBatchName($batch, $names) : ''),
                'batch_label' => !$isCampaign && $batch !== '' ? cdp_commOutboxBatchName($batch, $names) : '',
                'batch' => (string) $r->batch, 'record' => (string) $r->elabel, 'etype' => (string) $r->etype, 'eid' => (string) $r->eid,
                'by' => $r->k === 'queue' ? ((int) $r->by_name > 0 ? cdp_opsUserName((int) $r->by_name) : 'System') : (string) ($r->by_name ?: ($r->k === 'in' ? '' : 'System')),
                'cost' => $cost, 'opens' => $e ? $e[0] : null, 'clicks' => $e ? $e[1] : null,
                'send_after' => (string) ($r->send_after ?? ''),
                'can_retry' => $r->k === 'queue' && in_array($status, ['failed', 'cancelled'], true),
                'can_cancel' => in_array($r->k, ['queue', 'cs', 'dlv'], true) && in_array($status, ['queued', 'scheduled', 'retrying'], true),
                // Cancellations are deliberate (staff, opt-out, a reopened ticket): nothing to explain.
                'can_explain' => in_array($status, ['failed', 'skipped', 'retrying'], true) || $overdue,
            ];
        }
        return $out;
    }

    /** Which tile a unified status counts in. */
    function cdp_commOutboxTileOf($status)
    {
        $map = ['sent' => 'sent', 'failed' => 'failed', 'skipped' => 'skipped', 'cancelled' => 'cancelled', 'received' => 'received',
            'queued' => 'waiting', 'scheduled' => 'waiting', 'retrying' => 'waiting', 'sending' => 'waiting'];
        return $map[(string) $status] ?? 'skipped';
    }

    /**
     * Tiles above the list: the same filters except status (the tiles are the
     * status filter), counted by outcome with one grouped query per part. The
     * cost of what was sent comes from the same grouped counts (rate x units).
     * "total" is every row, so it equals the list's total when no status is chosen.
     */
    function cdp_commOutboxStats(array $f)
    {
        $s = ['total' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'waiting' => 0, 'cancelled' => 0, 'received' => 0, 'cost' => 0.0, 'currency' => ''];
        $g = $f;
        $g['status'] = '';
        $costs = is_file(__DIR__ . '/message_cost.php');
        if ($costs) {
            require_once __DIR__ . '/message_cost.php';
            $costs = function_exists('cdp_notifyCostPerMessage');
        }
        $perPart = $costs && function_exists('cdp_mcSettings') && (int) (cdp_mcSettings()['sms_parts'] ?? 0) === 1;
        $units = $perPart ? "CASE WHEN x.channel = 'sms' AND CHAR_LENGTH(x.body) > 160 THEN CEIL(CHAR_LENGTH(x.body) / 153) ELSE 1 END" : '1';
        $rates = [];
        foreach (cdp_commOutboxParts($g) as $name => $p) {
            $sql = in_array($name, ['log', 'queue'], true)
                ? 'SELECT x.st, x.channel, COUNT(*) n, SUM(' . $units . ') u FROM (' . $p[0] . ' ' . $p[1] . ') x GROUP BY x.st, x.channel'
                : 'SELECT x.st, x.channel, COUNT(*) n, 0 u FROM (' . $p[0] . ' ' . $p[1] . ') x GROUP BY x.st, x.channel';
            foreach (cdp_opsQ($sql, $p[2]) as $r) {
                $n = (int) $r->n;
                $s[cdp_commOutboxTileOf($r->st)] += $n;
                $s['total'] += $n;
                if ($costs && $r->st === 'sent' && in_array((string) $r->channel, ['whatsapp', 'sms', 'email'], true)) {
                    $ch = (string) $r->channel;
                    if (!isset($rates[$ch])) {
                        try {
                            $rates[$ch] = (float) cdp_notifyCostPerMessage($ch);
                        } catch (Throwable $e) {
                            $rates[$ch] = 0.0;
                        }
                    }
                    $s['cost'] += $rates[$ch] * (int) $r->u;
                }
            }
        }
        if ($costs && function_exists('cdp_notifyCostCurrency')) {
            $s['currency'] = cdp_notifyCostCurrency();
        }
        $s['cost'] = round($s['cost'], 2);
        return $s;
    }

    /** Filter choices: triggers / sources seen, campaigns and batches seen. */
    function cdp_commOutboxSourceOptions()
    {
        $out = [];
        if (cdp_msgTableReady()) {
            foreach (cdp_opsQ("SELECT source, MAX(source_label) label FROM cdb_message_log WHERE source IS NOT NULL AND source <> '' AND created_at >= :f GROUP BY source ORDER BY source LIMIT 300",
                [':f' => date('Y-m-d', strtotime('-365 days'))]) as $r) {
                $l = preg_replace('/^Communication Center:\s*/', '', (string) $r->label);
                if (strpos((string) $r->source, 'comms_status_') === 0 || in_array((string) $r->source, ['comms_manual'], true)) {
                    $l = preg_replace('/^Message from .*/', 'Message from our team', $l);
                }
                $out[(string) $r->source] = $l !== '' ? $l : cdp_msgSourceLabel((string) $r->source);
            }
        }
        if (cdp_opsTableExists('cdb_comm_outbox')) {
            foreach (cdp_opsQ("SELECT DISTINCT event_key FROM cdb_comm_outbox LIMIT 300") as $r) {
                $src = cdp_commTriggerSource((string) $r->event_key);
                if (!isset($out[$src])) {
                    $out[$src] = cdp_commTriggerLabel((string) $r->event_key);
                }
            }
        }
        foreach (['comms_manual' => 'Message from our team', 'campaign' => null] as $k => $v) {
            if ($v !== null && isset($out[$k])) {
                $out[$k] = $v;
            }
        }
        if (isset($out['comms_campaign'])) {
            $out['comms_campaign'] = 'Campaign';
        }
        asort($out, SORT_NATURAL | SORT_FLAG_CASE);
        return $out;
    }

    function cdp_commOutboxCampaignOptions()
    {
        $out = [];
        if (cdp_opsTableExists('cdb_comm_campaigns')) {
            foreach (cdp_opsQ("SELECT id, name, status FROM cdb_comm_campaigns ORDER BY id DESC LIMIT 100") as $r) {
                $out['campaign-' . (int) $r->id] = (string) $r->name;
            }
        }
        if (cdp_msgTableReady()) {
            foreach (cdp_opsQ("SELECT batch_id, MIN(created_at) t, COUNT(*) n FROM cdb_message_log
                               WHERE (batch_id LIKE 'nl-%' OR batch_id LIKE 'push-%') AND created_at >= :f GROUP BY batch_id ORDER BY t DESC LIMIT 60",
                [':f' => date('Y-m-d', strtotime('-365 days'))]) as $r) {
                $out[(string) $r->batch_id] = (strpos((string) $r->batch_id, 'nl-') === 0 ? 'Newsletter' : 'Broadcast') . ' of ' . sl_date($r->t, 'M j, Y H:i') . ' (' . (int) $r->n . ')';
            }
        }
        return $out;
    }

    /** One customer's name for a filter chip. */
    function cdp_commOutboxCustomerName($uid)
    {
        $u = (int) $uid > 0 ? cdp_opsOne('SELECT fname, lname, locker FROM cdb_users WHERE id = :id', [':id' => (int) $uid]) : null;
        return $u ? trim($u->fname . ' ' . $u->lname) . ($u->locker ? ' (' . $u->locker . ')' : '') : '';
    }

    // -------------------------------------------------------------------------
    // Detail
    // -------------------------------------------------------------------------

    /** A log row as the detail shows an attempt. */
    function cdp_commOutboxAttempt($l)
    {
        return ['id' => (int) $l->id, 'at' => (string) $l->created_at, 'when' => sl_date($l->created_at, 'M j, Y H:i:s'), 'status' => (string) $l->status,
            'status_label' => cdp_commOutboxStatuses()[$l->status][0] ?? ucfirst((string) $l->status), 'cls' => cdp_commOutboxStatuses()[$l->status][1] ?? 'plain',
            'detail' => (string) $l->status_detail, 'provider' => cdp_commOutboxProvider((string) $l->status_detail, (string) $l->channel),
            'response' => (string) ($l->provider_response ?? ''), 'to' => (string) $l->recipient_to, 'endpoint' => (string) ($l->endpoint ?? ''), 'ip' => (string) ($l->ip ?? '')];
    }

    /**
     * An e-mail as staff may look at it: no open pixel and no tracked or
     * unsubscribe links, so opening the drawer never records an open or a
     * click for the customer (msg_track.php) or unsubscribes them; a CSP meta
     * keeps scripts, frames and forms out of the preview.
     */
    function cdp_commOutboxSafeHtml($html)
    {
        $html = (string) $html;
        $html = preg_replace('~<img\b[^>]*msg_track\.php[^>]*>~i', '', $html);
        $html = preg_replace_callback('~href\s*=\s*(["\'])([^"\']*)\1~i', function ($m) {
            $url = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
            if (stripos($url, 'msg_track.php') !== false) {
                $q = [];
                parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
                $orig = isset($q['u']) && is_string($q['u']) ? (string) base64_decode(strtr($q['u'], '-_', '+/')) : '';
                $url = preg_match('~^https?://~i', $orig) ? $orig : '#';
            } elseif (stripos($url, 'unsubscribe') !== false) {
                $url = '#';
            }
            return 'href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"';
        }, $html);
        $csp = '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src data: https:; style-src \'unsafe-inline\' https:; font-src data: https:">';
        return $csp . $html;
    }

    /** Is this body an HTML e-mail? */
    function cdp_commOutboxIsHtml($channel, $body)
    {
        return $channel === 'email' && preg_match('/<[a-z][\s\S]*>/i', (string) $body);
    }

    /**
     * Everything about one row. $kind: log | queue | cs | dlv | in.
     * @return array|null
     */
    function cdp_commOutboxDetail($kind, $id)
    {
        $id = (int) $id;
        $d = null;
        if ($kind === 'log' && cdp_msgTableReady()) {
            $l = cdp_opsOne('SELECT * FROM cdb_message_log WHERE id = :id', [':id' => $id]);
            if (!$l) {
                return null;
            }
            $origin = (string) cdp_opsVal('SELECT ' . cdp_commOutboxLogOriginSql() . ' FROM cdb_message_log l WHERE l.id = :id', [':id' => $id], 'automation');
            $row = (object) ['k' => 'log', 'id' => $l->id, 'at' => $l->created_at, 'channel' => $l->channel, 'st' => $l->status, 'origin' => $origin, 'source' => $l->source,
                'label' => $l->source_label, 'uid' => (int) $l->recipient_user_id, 'name' => $l->recipient_name, 'rto' => $l->recipient_to, 'subject' => $l->subject,
                'body' => $l->body, 'attempts' => 1, 'detail' => $l->status_detail, 'campaign_id' => null, 'batch' => $l->batch_id, 'etype' => $l->entity_type,
                'eid' => $l->entity_id, 'elabel' => $l->entity_label, 'by_name' => $l->sent_by_name, 'tpl' => $l->template_id, 'send_after' => null];
            $d = cdp_commOutboxDecorate([$row])[0];
            $d['body'] = (string) $l->body;
            $d['attempt_list'] = [cdp_commOutboxAttempt($l)];
            $d['sent_by'] = trim((string) $l->sent_by_name . ((string) $l->sent_by_role !== '' ? ' (' . $l->sent_by_role . ')' : ''));
            $d['source'] = (string) $l->source;
            $d['template_id'] = $l->template_id ? (int) $l->template_id : null;
            $d['can_resend'] = in_array($l->status, ['failed', 'skipped'], true) && in_array($l->channel, ['whatsapp', 'sms', 'email'], true) && (string) $l->recipient_to !== ''
                && !in_array((string) $l->source, ['otp', 'signup', 'pickup_code'], true)
                && $origin !== 'campaign';   // campaigns are sent from the campaign (consent, unsubscribe)
            if (!empty($l->outbox_id)) {
                // An attempt of a queued message: Retry belongs to that message.
                $d['outbox_id'] = (int) $l->outbox_id;
                $d['parent_ref'] = 'queue-' . (int) $l->outbox_id;
                $d['can_resend'] = false;
            }
            if ($d['can_resend'] && cdp_opsTableExists('cdb_comm_outbox')) {
                $again = cdp_commOutboxResent((int) $l->id, (string) $l->channel);
                if ($again) {
                    $d['can_resend'] = false;
                    $d['resent_ref'] = 'queue-' . (int) $again->id;
                }
            }
        } elseif ($kind === 'queue' && cdp_opsTableExists('cdb_comm_outbox')) {
            $o = cdp_opsOne('SELECT o.*, TRIM(CONCAT(COALESCE(u.fname,\'\'),\' \',COALESCE(u.lname,\'\'))) uname, u.email uemail, u.phone uphone,
                                    (' . cdp_commOutboxQueueStatusSql() . ') ust
                             FROM cdb_comm_outbox o LEFT JOIN cdb_users u ON u.id = o.user_id WHERE o.id = :id', [':id' => $id]);
            if (!$o) {
                return null;
            }
            $row = (object) ['k' => 'queue', 'id' => $o->id, 'at' => $o->sent_at ?: $o->created_at, 'channel' => $o->channel, 'st' => $o->ust,
                'origin' => (int) $o->campaign_id > 0 ? 'campaign' : (in_array($o->event_key, ['manual', 'custom'], true) ? 'individual' : 'automation'),
                'source' => $o->event_key, 'label' => '', 'uid' => (int) $o->user_id, 'name' => $o->uname,
                'rto' => (string) $o->recipient_to !== '' ? $o->recipient_to : ($o->channel === 'email' ? $o->uemail : $o->uphone), 'subject' => $o->subject, 'body' => $o->body,
                'attempts' => $o->attempts, 'detail' => $o->last_error, 'campaign_id' => $o->campaign_id, 'batch' => (int) $o->campaign_id > 0 ? 'campaign-' . (int) $o->campaign_id : '',
                'etype' => $o->entity_type, 'eid' => '', 'elabel' => $o->entity_ref, 'by_name' => (string) (int) $o->created_by, 'tpl' => $o->template_id, 'send_after' => $o->send_after];
            $d = cdp_commOutboxDecorate([$row])[0];
            $d['body'] = (string) $o->body;
            $d['source'] = cdp_commTriggerSource((string) $o->event_key);
            $d['trigger'] = (string) $o->event_key;
            $d['kind_label'] = $o->kind === 'marketing' ? 'Marketing' : 'Transactional';
            $d['queued_at'] = sl_date($o->created_at, 'M j, Y H:i:s');
            $d['send_after_label'] = sl_date($o->send_after, 'M j, Y H:i');
            $d['sent_at'] = $o->sent_at ? sl_date($o->sent_at, 'M j, Y H:i:s') : '';
            $d['template_id'] = $o->template_id ? (int) $o->template_id : null;
            $d['wa_template'] = (string) ($o->wa_params ?? '');
            $d['attempt_list'] = [];
            $logs = cdp_commOutboxLinked() ? cdp_opsQ('SELECT * FROM cdb_message_log WHERE outbox_id = :o ORDER BY id', [':o' => $id]) : [];
            foreach ($logs as $l) {
                $d['attempt_list'][] = cdp_commOutboxAttempt($l);
            }
            $last = $logs ? end($logs) : null;
            if ($last) {
                $d['provider'] = cdp_commOutboxProvider((string) $last->status_detail, (string) $last->channel);
                if ((string) $last->entity_id !== '') {
                    $d['eid'] = (string) $last->entity_id;
                }
                if ($d['body'] === '' || cdp_commOutboxIsHtml($o->channel, $last->body)) {
                    $d['body'] = (string) $last->body;   // the filled text as it went out
                }
            }
            $d['sent_by'] = $d['by'];
            // The provider may already have this message (an attempt reported "sent"): retrying would send it twice.
            $d['sent_attempt'] = false;
            foreach ($logs as $l) {
                if ($l->status === 'sent') {
                    $d['sent_attempt'] = true;
                }
            }
        } elseif ($kind === 'cs' && cdp_opsTableExists('cdb_cs_outbox')) {
            $c = cdp_opsOne('SELECT c.*, TRIM(CONCAT(COALESCE(u.fname,\'\'),\' \',COALESCE(u.lname,\'\'))) uname, u.email uemail, u.phone uphone FROM cdb_cs_outbox c LEFT JOIN cdb_users u ON u.id = c.user_id WHERE c.id = :id', [':id' => $id]);
            if (!$c) {
                return null;
            }
            $st = ['retry' => 'retrying', 'suppressed' => 'cancelled', 'queued' => (string) $c->send_after > cdp_commOutboxCsNow() ? 'scheduled' : 'queued'][$c->status] ?? (string) $c->status;
            $row = (object) ['k' => 'cs', 'id' => $c->id, 'at' => $c->created_at, 'channel' => $c->channel, 'st' => $st, 'origin' => 'support', 'source' => 'customer_service',
                'label' => 'Support desk: ' . $c->kind, 'uid' => (int) $c->user_id, 'name' => $c->uname, 'rto' => $c->channel === 'email' ? $c->uemail : $c->uphone,
                'subject' => $c->subject, 'body' => $c->body, 'attempts' => $c->attempts, 'detail' => $c->last_error, 'campaign_id' => null, 'batch' => '',
                'etype' => 'cs_ticket', 'eid' => (string) (int) $c->ticket_id, 'elabel' => $c->ticket_id ? 'Ticket #' . (int) $c->ticket_id : '', 'by_name' => '', 'tpl' => null, 'send_after' => $c->send_after];
            $d = cdp_commOutboxDecorate([$row])[0];
            $d['body'] = (string) $c->body;
            $d['source'] = 'customer_service';
            $d['send_after_label'] = sl_date($c->send_after, 'M j, Y H:i');
            $d['attempt_list'] = [];
            $d['note'] = 'The Support desk sends this notice. Its settings are in Customer Care.';
        } elseif ($kind === 'dlv' && cdp_opsTableExists('cdb_delivery_outbox')) {
            $x = cdp_opsOne('SELECT d.*, TRIM(CONCAT(COALESCE(u.fname,\'\'),\' \',COALESCE(u.lname,\'\'))) uname, u.email uemail, u.phone uphone FROM cdb_delivery_outbox d LEFT JOIN cdb_users u ON u.id = d.recipient_id WHERE d.id = :id', [':id' => $id]);
            if (!$x) {
                return null;
            }
            $st = $x->state === 'sending' ? 'sending' : ($x->state === 'pending' ? ((int) $x->attempts > 0 ? 'retrying' : ((string) $x->available_at > cdp_commOutboxDbNow() ? 'scheduled' : 'queued')) : (string) $x->state);
            $row = (object) ['k' => 'dlv', 'id' => $x->id, 'at' => $x->created_at, 'channel' => '', 'st' => $st, 'origin' => 'delivery', 'source' => 'delivery_request',
                'label' => 'Delivery request notice', 'uid' => (int) $x->recipient_id, 'name' => $x->uname, 'rto' => $x->uphone ?: $x->uemail, 'subject' => $x->subject,
                'body' => $x->message, 'attempts' => $x->attempts, 'detail' => '', 'campaign_id' => null, 'batch' => 'delivery-outbox-' . (int) $x->id,
                'etype' => 'delivery_request', 'eid' => '', 'elabel' => $x->event_key, 'by_name' => '', 'tpl' => null, 'send_after' => $x->available_at];
            $d = cdp_commOutboxDecorate([$row])[0];
            $d['body'] = (string) $x->message;
            $d['source'] = 'delivery_request';
            $d['send_after_label'] = sl_date($x->available_at, 'M j, Y H:i');
            $d['response_json'] = (string) ($x->result_json ?? '');
            $d['attempt_list'] = [];
            if (cdp_msgTableReady()) {
                foreach (cdp_opsQ('SELECT * FROM cdb_message_log WHERE batch_id = :b ORDER BY id', [':b' => 'delivery-outbox-' . (int) $x->id]) as $l) {
                    $d['attempt_list'][] = cdp_commOutboxAttempt($l);
                }
            }
            $d['note'] = 'Sent on every channel the customer has, by the delivery-request worker (scripts/delivery_notifications.php).';
        } elseif ($kind === 'in' && cdp_opsTableExists('cdb_comm_messages')) {
            $m = cdp_opsOne('SELECT m.*, t.phone, t.contact_name, t.user_id, t.id tid, TRIM(CONCAT(COALESCE(u.fname,\'\'),\' \',COALESCE(u.lname,\'\'))) uname
                             FROM cdb_comm_messages m JOIN cdb_comm_threads t ON t.id = m.thread_id LEFT JOIN cdb_users u ON u.id = t.user_id WHERE m.id = :id AND m.direction = \'in\'', [':id' => $id]);
            if (!$m) {
                return null;
            }
            $row = (object) ['k' => 'in', 'id' => $m->id, 'at' => $m->created_at, 'channel' => 'whatsapp', 'st' => 'received', 'origin' => 'inbox', 'source' => 'comms_whatsapp_inbox',
                'label' => 'Customer message', 'uid' => (int) $m->user_id, 'name' => trim((string) $m->uname) !== '' ? $m->uname : $m->contact_name, 'rto' => '+' . $m->phone,
                'subject' => '', 'body' => $m->body, 'attempts' => 0, 'detail' => '', 'campaign_id' => null, 'batch' => '', 'etype' => 'whatsapp_thread',
                'eid' => (string) (int) $m->tid, 'elabel' => '+' . $m->phone, 'by_name' => '', 'tpl' => null, 'send_after' => null];
            $d = cdp_commOutboxDecorate([$row])[0];
            $d['body'] = (string) $m->body;
            $media = trim((string) ($m->media_url ?? ''));
            $d['media_url'] = preg_match('~^https?://~i', $media) ? $media : '';
            $d['source'] = 'comms_whatsapp_inbox';
            $d['attempt_list'] = [];
        }
        if (!$d) {
            return null;
        }
        $d['kind'] = $kind;
        $d['is_html'] = cdp_commOutboxIsHtml($d['channel'], $d['body']);
        if ($d['is_html']) {
            $d['body'] = cdp_commOutboxSafeHtml(str_replace('<!--sl-html-->', '', $d['body']));
        }
        $d['record_link'] = cdp_commOutboxRecordLink($d['etype'], $d['eid'], $d['record']);
        $d['customer_link'] = $d['uid'] > 0 ? sl_route('CommsCustomers', ['user' => $d['uid']]) : '';
        $d['profile_link'] = $d['uid'] > 0 ? sl_route('CustomerProfile', ['id' => $d['uid']]) : '';
        $d['campaign_link'] = '';
        if (preg_match('/^(campaign-\d+|nl-.+|push-.+)$/', (string) $d['batch'])) {
            $d['campaign_link'] = sl_route('Campaigns', ['batch' => $d['batch']]);
        }
        $d['batch_link'] = (string) $d['batch'] !== '' && $kind !== 'dlv' ? sl_route('CommsOutbox', ['campaign' => $d['batch']]) : '';
        $d['currency'] = function_exists('cdp_notifyCostCurrency') ? cdp_notifyCostCurrency() : '';
        $d['events'] = [];
        if ($d['channel'] === 'email' && (string) $d['batch'] !== '' && cdp_opsTableExists('cdb_message_events')) {
            foreach (cdp_opsQ('SELECT event, url, created_at FROM cdb_message_events WHERE batch_id = :b AND LOWER(recipient_to) = :t ORDER BY id DESC LIMIT 30',
                [':b' => $d['batch'], ':t' => strtolower((string) $d['to'])]) as $ev) {
                $d['events'][] = ['event' => (string) $ev->event, 'url' => (string) $ev->url, 'when' => sl_date($ev->created_at, 'M j, H:i')];
            }
        }
        return $d;
    }

    // -------------------------------------------------------------------------
    // Customer history
    // -------------------------------------------------------------------------

    /**
     * Everything said to and heard from one customer, newest first: every
     * Outbox row of the customer (log attempts, Communication Center and
     * Support desk queues, delivery notices, WhatsApp messages they sent us;
     * each with its ref, so the same detail drawer opens), plus survey
     * answers and preference changes.
     * Items: at, when, dir (out|in|note), channel, channel_label, what,
     * subject, body, status, status_label, cls, detail, by, ref, attempts.
     */
    function cdp_commOutboxHistory($userId, $limit = 200)
    {
        $userId = (int) $userId;
        $limit = max(1, min(1000, (int) $limit));
        $items = [];
        if ($userId <= 0) {
            return $items;
        }
        $f = cdp_commOutboxFilters([]);
        $f['customer'] = $userId;
        $feed = cdp_commOutboxFeed($f, 1, $limit);
        foreach ($feed['rows'] as $r) {
            $items[] = ['at' => $r['at'], 'dir' => $r['k'] === 'in' ? 'in' : 'out', 'channel' => $r['channel'], 'channel_label' => $r['channel_label'],
                'what' => $r['what'], 'subject' => $r['subject'], 'body' => $r['excerpt'], 'status' => $r['status'], 'status_label' => $r['status_label'],
                'cls' => $r['cls'], 'detail' => $r['status'] === 'scheduled' && $r['send_after'] !== '' ? 'Goes out ' . sl_date($r['send_after'], 'M j, H:i') : $r['short'],
                'by' => $r['by'] === 'System' ? '' : $r['by'], 'ref' => $r['ref'], 'attempts' => $r['attempts'], 'opens' => $r['opens'], 'clicks' => $r['clicks']];
        }
        if (cdp_opsTableExists('cdb_comm_surveys')) {
            foreach (cdp_opsQ('SELECT responded_at, score, comment, entity_ref FROM cdb_comm_surveys WHERE user_id = :u AND responded_at IS NOT NULL ORDER BY id DESC LIMIT 50', [':u' => $userId]) as $r) {
                $items[] = ['at' => $r->responded_at, 'dir' => 'in', 'channel' => 'survey', 'channel_label' => 'Survey', 'what' => 'Survey answer', 'subject' => (string) $r->entity_ref,
                    'body' => ($r->score !== null ? (int) $r->score . '/5' : '') . ($r->comment ? ' - ' . $r->comment : ''), 'status' => 'received', 'status_label' => 'Answered', 'cls' => 'transit',
                    'detail' => '', 'by' => '', 'ref' => '', 'attempts' => 0, 'opens' => null, 'clicks' => null];
            }
        }
        if (cdp_opsTableExists('cdb_comm_consent_log')) {
            $names = ['whatsapp' => 'WhatsApp updates', 'sms' => 'SMS updates', 'email' => 'E-mail updates', 'mkt_whatsapp' => 'WhatsApp offers', 'mkt_sms' => 'SMS offers',
                'mkt_email' => 'E-mail offers', 'opted_out_all' => 'Opted out of everything'];
            foreach (cdp_opsQ('SELECT created_at, field, value, source, actor_id FROM cdb_comm_consent_log WHERE user_id = :u ORDER BY id DESC LIMIT 50', [':u' => $userId]) as $r) {
                $items[] = ['at' => $r->created_at, 'dir' => 'note', 'channel' => 'preferences', 'channel_label' => 'Preferences', 'what' => 'Preference changed', 'subject' => '',
                    'body' => ($names[$r->field] ?? $r->field) . ': ' . ($r->value ? 'on' : 'off') . ' (' . str_replace(['staff:', '_'], ['staff, ', ' '], (string) $r->source) . ')',
                    'status' => '', 'status_label' => '', 'cls' => 'plain', 'detail' => '', 'by' => (int) $r->actor_id > 0 ? cdp_opsUserName((int) $r->actor_id) : '', 'ref' => '',
                    'attempts' => 0, 'opens' => null, 'clicks' => null];
            }
        }
        usort($items, function ($a, $b) { return strcmp((string) $b['at'], (string) $a['at']); });
        $items = array_slice($items, 0, $limit);
        foreach ($items as &$it) {
            $it['when'] = sl_date($it['at'], 'M j, Y H:i');
            $it['ago'] = sl_ago($it['at']);
        }
        unset($it);
        return $items;
    }

    /** Customers recently messaged, for the history page's start screen. */
    function cdp_commOutboxRecentCustomers($limit = 12)
    {
        if (!cdp_msgTableReady()) {
            return [];
        }
        $roles = implode(',', array_map('intval', cdp_commClientRoles())) ?: '0';
        $rows = cdp_opsQ("SELECT l.recipient_user_id uid, MAX(l.created_at) last_at, COUNT(*) n FROM cdb_message_log l
                          WHERE l.recipient_user_id > 0 AND l.created_at >= :f GROUP BY l.recipient_user_id ORDER BY last_at DESC LIMIT 40",
            [':f' => date('Y-m-d', strtotime('-120 days'))]);
        $out = [];
        foreach ($rows as $r) {
            $u = cdp_opsOne("SELECT id, fname, lname, locker, phone, email FROM cdb_users WHERE id = :id AND userlevel IN ($roles)", [':id' => (int) $r->uid]);
            if (!$u) {
                continue;
            }
            $out[] = ['id' => (int) $u->id, 'name' => trim($u->fname . ' ' . $u->lname), 'locker' => (string) $u->locker, 'phone' => (string) $u->phone,
                'email' => (string) $u->email, 'n' => (int) $r->n, 'last' => sl_ago($r->last_at)];
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /** Customer search (name, locker, phone, e-mail), as the Communication Center's. */
    function cdp_commOutboxFindCustomers($q, $limit = 20)
    {
        $q = trim((string) $q);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $roles = implode(',', array_map('intval', cdp_commClientRoles())) ?: '0';
        $like = '%' . cdp_commOutboxSearchTerm($q) . '%';
        $rows = cdp_opsQ("SELECT id, fname, lname, locker, phone, email FROM cdb_users WHERE userlevel IN ($roles)
                          AND (CONCAT(fname,' ',lname) LIKE :q1 OR locker LIKE :q2 OR phone LIKE :q3 OR email LIKE :q4) ORDER BY fname LIMIT " . (int) $limit,
            [':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like]);
        return array_map(function ($r) {
            return ['id' => (int) $r->id, 'name' => trim($r->fname . ' ' . $r->lname), 'locker' => (string) $r->locker, 'phone' => (string) $r->phone, 'email' => (string) $r->email];
        }, $rows);
    }

    // -------------------------------------------------------------------------
    // Actions
    // -------------------------------------------------------------------------

    /**
     * Put a failed or cancelled Communication Center message back in the queue.
     * When one of its attempts was reported sent (e.g. "Interrupted while
     * sending"), the provider may already have it: $confirmed must be true.
     */
    function cdp_commOutboxRetry($kind, $id, $confirmed = false)
    {
        $id = (int) $id;
        if ($kind === 'queue') {
            $o = cdp_opsOne('SELECT id, status FROM cdb_comm_outbox WHERE id = :id', [':id' => $id]);
            if (!$o) {
                return ['ok' => false, 'error' => 'That message is no longer there.', 'code' => 'not_found'];
            }
            if (!in_array($o->status, ['failed', 'cancelled'], true)) {
                return ['ok' => false, 'error' => 'This message is no longer failed or cancelled.'];
            }
            if (!$confirmed && cdp_commOutboxLinked()
                && cdp_opsOne("SELECT 1 x FROM cdb_message_log WHERE outbox_id = :id AND status = 'sent' LIMIT 1", [':id' => $id])) {
                return ['ok' => false, 'code' => 'needs_confirm',
                    'error' => 'One attempt of this message was accepted by the provider, so the customer may already have it. Retrying sends it again.'];
            }
            $r = cdp_opsX("UPDATE cdb_comm_outbox SET status = 'queued', attempts = 0, send_after = :n, last_error = NULL WHERE id = :id AND status IN ('failed','cancelled')",
                [':n' => cdp_opsNow(), ':id' => $id]);
            return ['ok' => $r['rows'] === 1, 'error' => $r['rows'] === 1 ? '' : 'This message is no longer failed or cancelled.'];
        }
        if ($kind === 'in') {
            return ['ok' => false, 'error' => 'Received messages cannot be retried.'];
        }
        if ($kind === 'cs' || $kind === 'dlv') {
            return ['ok' => false, 'error' => 'The Support desk and delivery-request workers retry their own notices.'];
        }
        if ($kind === 'log') {
            $l = cdp_msgTableReady() ? cdp_opsOne('SELECT id, outbox_id FROM cdb_message_log WHERE id = :id', [':id' => $id]) : null;
            if (!$l) {
                return ['ok' => false, 'error' => 'That message is no longer there.', 'code' => 'not_found'];
            }
            if (!empty($l->outbox_id)) {
                return ['ok' => false, 'error' => 'This is one attempt of queued message queue-' . (int) $l->outbox_id . '. Retry that message instead.'];
            }
            return ['ok' => false, 'error' => 'Logged messages are not queued. Use "Send again" for this one.'];
        }
        return ['ok' => false, 'error' => 'Unknown message.'];
    }

    /** Stop a message that has not gone yet. */
    function cdp_commOutboxCancel($kind, $id)
    {
        $id = (int) $id;
        if ($kind === 'queue') {
            $r = cdp_opsX("UPDATE cdb_comm_outbox SET status = 'cancelled', last_error = 'Cancelled by staff' WHERE id = :id AND status = 'queued'", [':id' => $id]);
        } elseif ($kind === 'cs') {
            $r = cdp_opsX("UPDATE cdb_cs_outbox SET status = 'suppressed', last_error = 'Cancelled by staff' WHERE id = :id AND status IN ('queued','retry')", [':id' => $id]);
        } elseif ($kind === 'dlv') {
            $r = cdp_opsX("UPDATE cdb_delivery_outbox SET state = 'cancelled' WHERE id = :id AND state = 'pending'", [':id' => $id]);
        } else {
            return ['ok' => false, 'error' => 'This message cannot be cancelled.'];
        }
        return ['ok' => $r['rows'] === 1, 'error' => $r['rows'] === 1 ? '' : 'This message is already on its way or finished.'];
    }

    /** The stable dedupe key of "Send again" for one log row: a second click finds the first copy. */
    function cdp_commOutboxResendKey($logId)
    {
        return 'resend-' . (int) $logId;
    }

    /** The queue row an earlier "Send again" of this log row made (cdp_commInsertOutbox adds ":<channel>"), or null. */
    function cdp_commOutboxResent($logId, $channel)
    {
        if (!cdp_opsTableExists('cdb_comm_outbox')) {
            return null;
        }
        return cdp_opsOne('SELECT id FROM cdb_comm_outbox WHERE dedupe_key = :k LIMIT 1', [':k' => cdp_commOutboxResendKey($logId) . ':' . $channel]);
    }

    /**
     * Send a logged message that failed or was skipped again, same text to the
     * same address, through the Communication Center queue (which honours the
     * customer's preferences and logs the new attempt). Once per log row; the
     * delivery runs after the response.
     * Campaign, newsletter and broadcast rows are refused: they go out from the
     * campaign, which applies marketing consent and the unsubscribe header.
     */
    function cdp_commOutboxResend($logId)
    {
        $l = cdp_msgTableReady() ? cdp_opsOne('SELECT l.*, (' . cdp_commOutboxLogOriginSql() . ') AS origin FROM cdb_message_log l WHERE l.id = :id', [':id' => (int) $logId]) : null;
        if (!$l) {
            return ['ok' => false, 'error' => 'That message is no longer in the log.'];
        }
        if (!in_array($l->status, ['failed', 'skipped'], true)) {
            return ['ok' => false, 'error' => 'Only a failed or skipped message can be sent again.'];
        }
        if (!empty($l->outbox_id)) {
            return ['ok' => false, 'error' => 'This is one attempt of queued message queue-' . (int) $l->outbox_id . '. Retry that message instead.'];
        }
        if ((string) $l->origin === 'campaign') {
            return ['ok' => false, 'error' => 'Campaign and broadcast messages are sent again from the campaign, so consent and unsubscribe rules apply.'];
        }
        if (in_array((string) $l->source, ['otp', 'signup', 'pickup_code'], true)) {
            return ['ok' => false, 'error' => 'Codes are never sent again from the Outbox. Ask the customer to request a new one.'];
        }
        $body = (string) $l->body;
        if (trim($body) === '' || strpos($body, '••••') !== false) {
            return ['ok' => false, 'error' => 'The logged text is empty or hides a code, so it cannot be sent again.'];
        }
        $key = cdp_commOutboxResendKey((int) $l->id);
        $prev = cdp_commOutboxResent((int) $l->id, (string) $l->channel);
        if ($prev) {
            return ['ok' => false, 'code' => 'already', 'id' => (int) $prev->id, 'error' => 'Already sent again: see queue-' . (int) $prev->id . '.'];
        }
        if ($l->channel === 'email' && preg_match('/<[a-z][\s\S]*>/i', $body) && strpos($body, '<!--sl-html-->') !== 0) {
            $body = '<!--sl-html-->' . cdp_commSanitizeEmailHtml($body);
        }
        // Braces in the logged text are not placeholders any more.
        $vars = [];
        $o = ['event' => 'manual', 'body' => $body, 'subject' => $l->channel === 'email' ? (string) ($l->subject ?: 'Message from us') : '',
            'entity_type' => (string) $l->entity_type ?: 'manual', 'entity_ref' => mb_substr((string) $l->entity_label, 0, 80), 'name' => (string) $l->recipient_name,
            'send_after' => time(), 'dedupe' => $key];
        $q = cdp_commQueueRaw((string) $l->recipient_to, (string) $l->channel, $vars, $o);
        if (empty($q['queued'])) {
            if (in_array('Already queued', $q['skipped'], true)) {
                $prev = cdp_commOutboxResent((int) $l->id, (string) $l->channel);
                return ['ok' => false, 'code' => 'already', 'id' => $prev ? (int) $prev->id : 0, 'error' => 'Already sent again.'];
            }
            return ['ok' => false, 'error' => 'Not queued: ' . implode('; ', array_map(function ($k, $v) { return $k . ' - ' . $v; }, array_keys($q['skipped']), $q['skipped']))];
        }
        return ['ok' => true, 'id' => (int) ($q['ids'][0] ?? 0)];
    }

    /** An address with most of it hidden, for text that leaves the server (King). */
    function cdp_commOutboxMaskTo($to)
    {
        $to = trim((string) $to);
        if ($to === '') {
            return '';
        }
        if (strpos($to, '@') !== false) {
            return '(an address at ' . substr(strrchr($to, '@'), 1) . ')';
        }
        $digits = preg_replace('/\D+/', '', $to);
        return strlen($digits) >= 4 ? '(a number ending ' . substr($digits, -3) . ', ' . strlen($digits) . ' digits' . (strpos($to, '+') === 0 ? ', with +' : '') . ')' : '(a short number)';
    }

    /** Personal data and secrets out of a provider response before King sees it. */
    function cdp_commOutboxRedact($s)
    {
        $s = (string) $s;
        $s = preg_replace('/((?:pass(?:word)?|pwd|secret|token|api[_-]?key|auth(?:orization)?)["\']?\s*(?:=>|=|:)\s*)(["\']?)[^"\'\s,}&]{3,}\2/i', '$1$2[hidden]$2', $s);
        $s = preg_replace('/\b[\w.+-]+@[\w-]+\.[\w.]+\b/', '[e-mail]', $s);
        $s = preg_replace('/\+?\d[\d\s().-]{6,}\d/', '[number]', $s);
        $s = preg_replace('/\b[A-Za-z0-9_\-]{32,}\b/', '[token]', $s);
        return $s;
    }

    /** Facts for King's "Explain this failure". */
    function cdp_commOutboxExplainContext(array $d)
    {
        $att = $d['attempt_list'] ?? [];
        $last = $att ? end($att) : null;
        return ['channel' => $d['channel'], 'provider' => $d['provider'] ?: ($last['provider'] ?? ''), 'status' => $d['status_label'],
            'error' => cdp_commOutboxRedact($d['detail'] !== '' ? $d['detail'] : ($last['detail'] ?? '')),
            'detail' => $last ? mb_substr(cdp_commOutboxRedact((string) $last['response']), 0, 500) : '',
            'to' => cdp_commOutboxMaskTo($d['to']), 'attempts' => (string) $d['attempts']];
    }
}
