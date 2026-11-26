<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************

/**
 * Notification Studio: one place to see and edit what every status and every
 * action sends, per channel: e-mail (built from blocks), SMS, WhatsApp and the
 * in-app notification.
 *
 * Trigger keys
 *   status:<cdb_styles.id>   a shipment or package is moved to that status
 *   action:<name>            a platform action (e.g. action:shipment.registered)
 *   comms:<event>            a Communication Center automation (helpers/comms.php);
 *                            its texts live in cdb_comm_templates, the studio only
 *                            keeps the e-mail blocks here so they can be edited again.
 *
 * Storage: cdb_notify_content, one row per (trigger_key, channel).
 *   No row      = a status sends the message drafted for it in the message
 *                 library (helpers/notify_drafts.php), written for that status
 *                 by name. An action, or every status while the studio switch
 *                 "Status-specific messages" is off (cdb_ops_settings
 *                 notify.status_drafts = 0), sends the old built-in message.
 *   enabled = 0 = the channel is switched off for that trigger.
 *   enabled = 1 = the saved subject/body is sent instead.
 *
 * Send points call cdp_notifyResolve($key, $channel, $vars) and get
 *   ['on' => bool, 'custom' => bool, 'source' => 'saved'|'draft'|'builtin',
 *    'subject' => string, 'body' => string]. custom = true means "send this
 *   subject/body instead of the built-in message" (saved or drafted).
 *
 * A line whose placeholders are all empty is left out when a message is sent
 * (an empty {eta}, {comment}, {company_phone} ... leaves no dangling label).
 *
 * Placeholders are {braces}. In e-mail the values are HTML-escaped, except the
 * ones ending in _html, which the send point builds itself (items table,
 * pickup code box). Studio text is never HTML: e-mail bodies are compiled from
 * blocks here, so an editor cannot inject markup.
 */

require_once __DIR__ . '/querys.php';
require_once __DIR__ . '/notify_drafts.php';

if (!defined('CDP_NOTIFY_HTML_MARK')) {
    // Prefix on a Communication Center e-mail body that was compiled from blocks:
    // helpers/comms.php renders and sends it as HTML instead of escaping it.
    define('CDP_NOTIFY_HTML_MARK', '<!--sl-html-->');
}

if (!function_exists('cdp_notifyChannels')) {

    /** Channels in the order the studio shows them. */
    function cdp_notifyChannels()
    {
        return ['email' => 'E-mail', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'inapp' => 'In-app'];
    }

    /** Longest body each channel accepts. */
    function cdp_notifyLimits()
    {
        return ['email' => 60000, 'sms' => 640, 'whatsapp' => 4000, 'inapp' => 350];
    }

    function cdp_notifyEnsureTable()
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $db = new Conexion;
            $db->cdp_query("CREATE TABLE IF NOT EXISTS cdb_notify_content (
                trigger_key VARCHAR(64)  NOT NULL,
                channel     VARCHAR(12)  NOT NULL,
                enabled     TINYINT(1)   NOT NULL DEFAULT 1,
                subject     VARCHAR(190) NULL,
                body        MEDIUMTEXT   NULL,
                blocks      MEDIUMTEXT   NULL,
                updated_by  INT          NULL,
                updated_at  DATETIME     NOT NULL,
                PRIMARY KEY (trigger_key, channel)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
            $ready = $db->cdp_execute() !== false;
        } catch (Throwable $e) {
            error_log('notify studio table: ' . $e->getMessage());
            $ready = false;
        }
        return $ready;
    }

    /** Is a trigger key well formed? */
    function cdp_notifyKeyValid($key)
    {
        return (bool) preg_match('/^(status:[0-9]{1,6}|action:[a-z_.]{3,40}|comms:[a-z_]{3,40})$/', (string) $key);
    }

    // ------------------------------------------------------------------
    // Catalogue
    // ------------------------------------------------------------------

    /**
     * Platform actions whose messages the studio can edit, with the channels
     * the action really sends (only those are wired to the studio).
     */
    function cdp_notifyActions()
    {
        return [
            'action:shipment.registered' => [
                'label' => 'Shipment registered',
                'desc' => 'Sent to the customer when a new air shipment is created for them.',
                'channels' => ['whatsapp'],
                'vars' => ['service', 'courier', 'delivery_time', 'origin_office', 'details'],
            ],
        ];
    }

    /** Every cdb_styles status, ordered by name. */
    function cdp_notifyStatuses()
    {
        $db = new Conexion;
        $db->cdp_query('SELECT id, mod_style, color FROM cdb_styles ORDER BY mod_style');
        $rows = $db->cdp_registros();
        return is_array($rows) ? $rows : [];
    }

    /** Name (cdb_styles.mod_style) of a status id, '' when unknown. One query per request. */
    function cdp_notifyStatusName($id)
    {
        static $names = null;
        if ($names === null) {
            $names = [];
            try {
                foreach (cdp_notifyStatuses() as $r) {
                    $names[(int) $r->id] = (string) $r->mod_style;
                }
            } catch (Throwable $e) {
                error_log('notify studio statuses: ' . $e->getMessage());
            }
        }
        return $names[(int) $id] ?? '';
    }

    /**
     * Placeholders the editor offers, with what they hold. 'scope' says where a
     * value is filled: all = every trigger, status = status triggers, action = the
     * actions that list it in their 'vars'.
     */
    function cdp_notifyPlaceholders()
    {
        return [
            'name'             => ['Customer name and locker', 'all'],
            'first_name'       => ['Customer first name', 'all'],
            'locker'           => ['Locker ID', 'all'],
            'tracking'         => ['Swift Lane tracking number', 'all'],
            'track_url'        => ['Public tracking link', 'all'],
            'company'          => ['Company name', 'all'],
            'company_phone'    => ['Company phone (Settings > company phone; the line is left out when empty)', 'all'],
            'pay_url'          => ['Link to pay bills online (customer signs in)', 'all'],
            'date'             => ['Date and time of the message', 'all'],
            'status'           => ['New status', 'status'],
            'location'         => ['Where the update was made', 'status'],
            'comment'          => ['Note typed with the update', 'status'],
            'carrier_tracking' => ['Postal / carrier tracking number', 'status'],
            'eta'              => ['Estimated arrival (consolidation ETA when consolidated; empty once the package is in Ghana)', 'status'],
            'weight'           => ['Original total weight', 'status'],
            'items'            => ['Items, one per line', 'status'],
            'items_html'       => ['Items as a table (e-mail only)', 'status'],
            'pickup_code'      => ['Pickup code line (ready for pickup only)', 'status'],
            'pickup_code_html' => ['Pickup code box (e-mail, ready for pickup only)', 'status'],
            'amount_due'       => ['Amount still due on the shipment, e.g. $42.00 (empty when paid; payment statuses only)', 'status'],
            'service'          => ['Shipping service', 'action'],
            'courier'          => ['Courier company', 'action'],
            'delivery_time'    => ['Delivery time', 'action'],
            'origin_office'    => ['Origin office', 'action'],
            'details'          => ['Weight, contents and carrier lines', 'action'],
        ];
    }

    /** Company phone and online payment link, from the company settings. */
    function cdp_notifyCompanyVars($core = null)
    {
        $core = $core ?: (class_exists('Core') ? new Core : null);
        $site = rtrim((string) ($core->site_url ?? ''), '/');
        $phone = trim((string) ($core->c_phone ?? ''));
        if ($phone === '') {
            $phone = trim((string) ($core->cell_phone ?? ''));
        }
        return ['company_phone' => $phone, 'pay_url' => $site !== '' ? $site . '/pay_checkout.php' : ''];
    }

    /**
     * Sample values for previews, tests and the AI check. With a status name
     * the values fit that status: its own name, a pickup code only when the
     * status carries one, a note only where the drafted message shows it.
     */
    function cdp_notifySampleVars($statusName = null)
    {
        $core = class_exists('Core') ? new Core : null;
        $company = trim((string) ($core->site_name ?? '')) ?: 'Swift Lane';
        $site = rtrim((string) ($core->site_url ?? ''), '/');
        $base = ($site !== '' ? $site : 'https://example.com');
        $cv = cdp_notifyCompanyVars($core);
        $vars = [
            'name' => 'Efua Owusu (SL-1042)', 'first_name' => 'Efua', 'locker' => 'SL-1042',
            'tracking' => 'SL-100482', 'track_url' => $base . '/track.php?order_track=SL-100482',
            'company' => $company, 'company_phone' => $cv['company_phone'] !== '' ? $cv['company_phone'] : '+233 30 000 0000',
            'pay_url' => $base . '/pay_checkout.php', 'date' => date('M j, Y · g:i A'),
            'status' => 'Ready for pickup', 'location' => 'Accra, Ghana', 'comment' => 'Collect from counter 2',
            'carrier_tracking' => '9400 1112 0261 3450 9822 18', 'eta' => date('M j, Y', strtotime('+3 days')), 'weight' => '4.20 lbs',
            'items' => "2 x Running shoes\n1 x Phone case",
            'items_html' => '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px"><tr><td style="padding:6px 0;border-bottom:1px solid #eee">Running shoes</td><td style="padding:6px 0;border-bottom:1px solid #eee;text-align:right">× 2</td></tr><tr><td style="padding:6px 0">Phone case</td><td style="padding:6px 0;text-align:right">× 1</td></tr></table>',
            'pickup_code' => 'Pickup code: *482193*. Show this code when you collect your package. Do not share it with anyone else.',
            'pickup_code_html' => '<p style="margin:12px 0 4px 0;">Your pickup code:</p><p style="margin:0 0 8px 0;font-size:28px;font-weight:700;letter-spacing:6px;color:#1a1a1a;">482193</p><p style="margin:0 0 12px 0;">Show this code when you collect your package. Do not share it with anyone else.</p>',
            'service' => 'Air express', 'courier' => 'USPS', 'delivery_time' => '5 to 7 days', 'origin_office' => 'New York warehouse',
            'details' => "• Weight: 4.20 lb\n• Contents: Running shoes",
        ];
        if ($statusName === null || $statusName === '') {
            return $vars;
        }
        $kind = cdp_notifyDraftKind($statusName);
        $vars['status'] = cdp_notifyDraftLabel($statusName);
        $us = ['pending_collection', 'pre_alert', 'received', 'in_warehouse', 'awaiting_approval', 'approved', 'awaiting_consolidation', 'consolidated',
            'accepted_airline', 'departed', 'pickup_scheduled', 'not_shipped', 'returned', 'quotation', 'quote_accepted', 'pending_quote', 'rejected', 'invoice_needed'];
        $vars['location'] = in_array($kind, $us, true) ? 'Miami, United States' : ($kind === 'in_transit' ? 'In the air to Accra' : 'Accra, Ghana');
        if ($kind === 'out_for_delivery' || $kind === 'ready_delivery') {
            $vars['pickup_code'] = 'Pickup code: *482193*. Give this code to our rider when your package arrives. Do not share it with anyone else.';
            $vars['pickup_code_html'] = str_replace('Show this code when you collect your package.', 'Give this code to our rider when your package arrives.', $vars['pickup_code_html']);
        } elseif (!in_array($kind, cdp_notifyDraftCodeKinds(), true)) {
            $vars['pickup_code'] = '';
            $vars['pickup_code_html'] = '';
        }
        if (in_array($kind, cdp_notifyDraftNoEtaKinds(), true)) {
            $vars['eta'] = '';
        }
        $vars['amount_due'] = in_array($kind, cdp_notifyDraftPayKinds(), true) ? '$42.00' : '';
        $vars['comment'] = [
            'on_hold' => 'Customs asked to inspect the parcel; we expect it to be released this week.',
            'invoice_needed' => 'Customs asked for the receipt of the laptop.',
            'rejected' => 'The parcel contains a loose lithium battery, which airlines do not accept.',
            'cancelled' => 'Cancelled at your request.', 'not_shipped' => 'Waiting for the purchase invoice.',
            'returned' => 'The seller asked for the parcel back.', 'failed_delivery' => 'Nobody was at the address at 2:15 PM.',
        ][$kind] ?? '';
        return $vars;
    }

    /**
     * Sample values for a Communication Center automation preview: a status
     * that fits the event, plus the comms-only values (branch, balance, links).
     * One map for the studio page and its endpoint, so both previews agree.
     */
    function cdp_notifyCommsSampleVars($event, array $sample)
    {
        $evStatus = ['departure' => 'Departed US Warehouse', 'arrival' => 'Arrived in Accra', 'customs' => 'Customs Clearance', 'ready_pickup' => 'Ready for pickup',
            'delivery_schedule' => 'Ready for delivery', 'driver_arrival' => 'On Route', 'failed_delivery' => 'Delivery failed', 'survey' => 'Delivered',
            'warehouse_received' => 'Received Office', 'claim_reminder' => 'Ready for pickup', 'payment_reminder' => 'Pending payment',
            'shipment_alert' => 'In Transit'][(string) $event] ?? 'In Transit';
        return ['status' => $evStatus, 'branch' => 'Accra office', 'days' => '5', 'balance' => '42.00', 'currency' => 'USD',
            'schedule_url' => $sample['track_url'] ?? '', 'survey_url' => $sample['track_url'] ?? '', 'prefs_url' => $sample['track_url'] ?? '',
            'driver_name' => 'Kwame', 'driver_phone' => '+233 24 000 0000', 'eta' => 'Expected in about 20 minutes. '] + $sample;
    }

    /**
     * Base values every send point fills: customer, tracking, link, company,
     * company phone, payment link, date.
     *
     * @param object|null $customer row with fname, lname, locker (cdb_users)
     */
    function cdp_notifyBaseVars($customer, $tracking, $trackUrl = '')
    {
        $core = class_exists('Core') ? new Core : null;
        $first = trim((string) ($customer->fname ?? ''));
        $full = function_exists('cdp_nameWithLocker') && $customer ? (string) cdp_nameWithLocker($customer) : trim($first . ' ' . (string) ($customer->lname ?? ''));
        return [
            'name' => $full, 'first_name' => $first !== '' ? $first : $full, 'locker' => (string) ($customer->locker ?? ''),
            'tracking' => (string) $tracking, 'track_url' => (string) $trackUrl,
            'company' => trim((string) ($core->site_name ?? '')), 'date' => date('M j, Y · g:i A'),
        ] + cdp_notifyCompanyVars($core);
    }

    /**
     * Package values for a status message: carrier number, ETA, weight and items,
     * from the shared enrichment (helpers/notify_placeholders.php). Never money.
     * "N/A" (the enrichment's "no value") becomes empty, so the line is left out.
     */
    function cdp_notifyPackageVars($orderId, $module = 'air')
    {
        $out = ['carrier_tracking' => '', 'eta' => '', 'weight' => '', 'items' => '', 'items_html' => '', 'amount_due' => ''];
        try {
            require_once __DIR__ . '/notify_placeholders.php';
            if (function_exists('cdp_buildPackageNotifyPlaceholders') && (int) $orderId > 0) {
                $p = (array) cdp_buildPackageNotifyPlaceholders((int) $orderId, $module === 'sea' ? 'sea' : 'air');
                $val = function ($k) use ($p) {
                    $v = trim((string) ($p[$k] ?? ''));
                    return strtoupper($v) === 'N/A' ? '' : $v;
                };
                $out['carrier_tracking'] = $val('[POSTAL_TRACKING]');
                $out['eta'] = $val('[ETA]');
                $out['weight'] = $val('[WEIGHT]');
                $out['items'] = $val('[ITEMS]');
                $out['items_html'] = $val('[ITEMS_HTML]');
            }
        } catch (Throwable $e) {
            error_log('notify studio package vars: ' . $e->getMessage());
        }
        $out['amount_due'] = cdp_notifyAmountDue($orderId, $module);
        return $out;
    }

    /**
     * What is still due on one shipment / package, formatted ("$42.00"), for the
     * payment messages: total_order while the invoice is unpaid (status_invoice
     * 2 or 23, as My bills and King count it), '' when paid or nothing is due.
     */
    function cdp_notifyAmountDue($orderId, $module = 'air')
    {
        try {
            if ((int) $orderId <= 0 || !class_exists('Conexion')) {
                return '';
            }
            $table = $module === 'sea' ? 'cdb_customers_packages' : 'cdb_add_order';
            $db = new Conexion;
            $db->cdp_query("SELECT total_order, status_invoice FROM {$table} WHERE order_id = :id LIMIT 1");
            $db->bind(':id', (int) $orderId);
            $r = $db->cdp_registro();
            if (!$r || !in_array((int) $r->status_invoice, [2, 23], true) || (float) $r->total_order <= 0.004) {
                return '';
            }
            $amt = (float) $r->total_order;
            return function_exists('sl_money') ? (string) sl_money($amt) : '$' . number_format($amt, 2);
        } catch (Throwable $e) {
            error_log('notify studio amount due: ' . $e->getMessage());
            return '';
        }
    }

    // ------------------------------------------------------------------
    // Rendering
    // ------------------------------------------------------------------

    /**
     * Fill {placeholders}. Unknown ones become empty. In HTML mode values are
     * escaped, except keys ending in _html (built by the send point).
     */
    function cdp_notifyRender($text, array $vars, $html = false)
    {
        $out = preg_replace_callback('/\{([a-z_]+)\}/', function ($m) use ($vars, $html) {
            if (!array_key_exists($m[1], $vars)) {
                return '';
            }
            $v = (string) $vars[$m[1]];
            if (!$html || substr($m[1], -5) === '_html') {
                return $v;
            }
            return nl2br(htmlspecialchars($v, ENT_QUOTES, 'UTF-8'), false);
        }, (string) $text);
        if ($html && strpos($out, 'data-ns-opt') !== false) {
            // Optional e-mail rows (cdp_notifyCompileBlocks) whose value came out empty:
            // a shipment card line, the items table, the pickup code box, a button without a link.
            $out = preg_replace([
                '#<tr data-ns-opt="row"><td[^>]*>[^<]*</td><td[^>]*>\s*</td></tr>#',
                '#<tr data-ns-opt="items"><td[^>]*><div[^>]*>[^<]*</div>\s*</td></tr>#',
                '#<tr data-ns-opt="code"><td[^>]*>\s*</td></tr>#',
                '#<tr data-ns-opt="btn"><td[^>]*><a href=""[^>]*>[^<]*</a></td></tr>#',
            ], '', $out);
        }
        return $out;
    }

    /**
     * Leave out every line whose placeholders are all empty, so an optional fact
     * ("Estimated arrival: {eta}", "Note: {comment}", "Call us: {company_phone}")
     * never leaves a bare label behind. Lines without placeholders are kept.
     */
    function cdp_notifyPruneLines($text, array $vars)
    {
        $text = str_replace("\r\n", "\n", (string) $text);
        if (strpos($text, '{') === false) {
            return $text;
        }
        $out = [];
        foreach (explode("\n", $text) as $line) {
            if (preg_match_all('/\{([a-z_]+)\}/', $line, $m) && $m[1]) {
                $filled = false;
                foreach ($m[1] as $k) {
                    if (trim((string) ($vars[$k] ?? '')) !== '') {
                        $filled = true;
                        break;
                    }
                }
                if (!$filled) {
                    continue;
                }
            }
            $out[] = $line;
        }
        return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $out)), "\n");
    }

    /** SMS parts a text needs (GSM-7: 160 / 153 per part, otherwise 70 / 67). */
    function cdp_notifySmsParts($text)
    {
        $text = (string) $text;
        $len = mb_strlen($text);
        if ($len === 0) {
            return 0;
        }
        $gsm = (bool) preg_match('~^[\n\r @£$¥èéùìòÇØøÅå_ÆæßÉ!"#%&\'()*+,\-./0-9:;<=>?¡A-ZÄÖÑÜ§¿a-zäöñüà^{}\\\\\[\]\~|€]*$~u', $text);
        $one = $gsm ? 160 : 70;
        $many = $gsm ? 153 : 67;
        return $len <= $one ? 1 : (int) ceil($len / $many);
    }

    /** Block types the e-mail builder offers, with their editable fields. */
    function cdp_notifyBlockTypes()
    {
        return [
            'header'      => ['label' => 'Header', 'fields' => ['text' => 'Company line']],
            'heading'     => ['label' => 'Heading', 'fields' => ['text' => 'Heading']],
            'text'        => ['label' => 'Paragraph', 'fields' => ['text' => 'Text']],
            'status_card' => ['label' => 'Shipment card', 'fields' => []],
            'button'      => ['label' => 'Button', 'fields' => ['text' => 'Button text', 'url' => 'Link']],
            'items'       => ['label' => 'Items table', 'fields' => []],
            'pickup_code' => ['label' => 'Pickup code', 'fields' => []],
            'image'       => ['label' => 'Image', 'fields' => ['url' => 'Image address (https://...)', 'alt' => 'Description']],
            'divider'     => ['label' => 'Divider', 'fields' => []],
            'spacer'      => ['label' => 'Space', 'fields' => []],
            'footer'      => ['label' => 'Footer', 'fields' => ['text' => 'Footer text']],
        ];
    }

    /**
     * Keep only known block types and fields, trimmed to sane lengths.
     *
     * @return array<int,array{type:string,text?:string,url?:string,alt?:string}>
     */
    function cdp_notifyCleanBlocks($blocks)
    {
        $types = cdp_notifyBlockTypes();
        $out = [];
        foreach (is_array($blocks) ? $blocks : [] as $b) {
            $type = is_array($b) ? (string) ($b['type'] ?? '') : '';
            if (!isset($types[$type])) {
                continue;
            }
            $clean = ['type' => $type];
            foreach (array_keys($types[$type]['fields']) as $f) {
                $v = trim((string) ($b[$f] ?? ''));
                $clean[$f] = mb_substr($v, 0, $f === 'text' ? 4000 : 600);
            }
            $out[] = $clean;
            if (count($out) >= 40) {
                break;
            }
        }
        return $out;
    }

    /** A link is safe when it is http(s) or starts with a placeholder (e.g. {track_url}). */
    function cdp_notifySafeUrl($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        if ($url[0] === '{' || preg_match('~^https?://~i', $url)) {
            return $url;
        }
        return '';
    }

    /**
     * Compile builder blocks into an e-mail body (table layout, inline styles, so it
     * renders in mail clients). Placeholders are kept for cdp_notifyRender().
     *
     * With $vars (send time), every text is first pruned like a text message
     * (cdp_notifyPruneLines) and a heading, paragraph or footer left empty is
     * dropped. Optional rows (a shipment card line, items, pickup code, a button
     * whose link is a placeholder) are marked so cdp_notifyRender() removes them
     * when their value is empty.
     */
    function cdp_notifyCompileBlocks(array $blocks, array $vars = null)
    {
        $e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
        $para = function ($s) use ($e) { return nl2br($e($s), false); };
        $rows = '';
        foreach (cdp_notifyCleanBlocks($blocks) as $b) {
            $t = $b['text'] ?? '';
            if ($vars !== null && in_array($b['type'], ['header', 'heading', 'text', 'footer', 'button'], true)) {
                $t = cdp_notifyPruneLines($t, $vars);
                if ($t === '' && in_array($b['type'], ['heading', 'text', 'footer'], true)) {
                    continue;
                }
            }
            switch ($b['type']) {
                case 'header':
                    $rows .= '<tr><td style="padding:22px 28px 14px 28px;border-bottom:1px solid #EFEBE4;font-size:17px;font-weight:700;color:#1E1D1A;letter-spacing:-0.01em">' . $para($t !== '' ? $t : '{company}') . '</td></tr>';
                    break;
                case 'heading':
                    $rows .= '<tr><td style="padding:22px 28px 4px 28px;font-size:22px;line-height:1.3;font-weight:700;color:#1E1D1A">' . $para($t) . '</td></tr>';
                    break;
                case 'text':
                    $rows .= '<tr><td style="padding:10px 28px;font-size:15px;line-height:1.6;color:#3A3935">' . $para($t) . '</td></tr>';
                    break;
                case 'status_card':
                    $line = function ($k, $v, $opt) use ($e) {
                        return '<tr' . ($opt ? ' data-ns-opt="row"' : '') . '><td style="padding:6px 0;font-size:13px;color:#77736B;width:42%">' . $e($k) . '</td><td style="padding:6px 0;font-size:15px;font-weight:600;color:#1E1D1A">' . $v . '</td></tr>';
                    };
                    $rows .= '<tr><td style="padding:12px 28px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F7F5F0;border-radius:10px;padding:8px 16px">'
                        . $line('Tracking', '{tracking}', false) . $line('Status', '{status}', false) . $line('Location', '{location}', true)
                        . $line('Carrier tracking', '{carrier_tracking}', true) . $line('Weight', '{weight}', true) . $line('Estimated arrival', '{eta}', true)
                        . '</table></td></tr>';
                    break;
                case 'button':
                    $url = cdp_notifySafeUrl($b['url'] ?? '') ?: '{track_url}';
                    $rows .= '<tr' . ($url[0] === '{' ? ' data-ns-opt="btn"' : '') . '><td style="padding:16px 28px"><a href="' . $e($url) . '" style="display:inline-block;background:#1E1D1A;color:#ffffff;text-decoration:none;font-size:15px;font-weight:600;padding:12px 22px;border-radius:10px">' . $e($t !== '' ? $t : 'Track your shipment') . '</a></td></tr>';
                    break;
                case 'items':
                    $rows .= '<tr data-ns-opt="items"><td style="padding:10px 28px"><div style="font-size:13px;color:#77736B;margin-bottom:4px">Items</div>{items_html}</td></tr>';
                    break;
                case 'pickup_code':
                    $rows .= '<tr data-ns-opt="code"><td style="padding:10px 28px">{pickup_code_html}</td></tr>';
                    break;
                case 'image':
                    $url = cdp_notifySafeUrl($b['url'] ?? '');
                    if ($url !== '') {
                        $rows .= '<tr><td style="padding:10px 28px"><img src="' . $e($url) . '" alt="' . $e($b['alt'] ?? '') . '" style="display:block;max-width:100%;height:auto;border:0;border-radius:8px"></td></tr>';
                    }
                    break;
                case 'divider':
                    $rows .= '<tr><td style="padding:10px 28px"><div style="height:1px;background:#EFEBE4;line-height:1px;font-size:1px">&nbsp;</div></td></tr>';
                    break;
                case 'spacer':
                    $rows .= '<tr><td style="height:20px;line-height:20px;font-size:1px">&nbsp;</td></tr>';
                    break;
                case 'footer':
                    $rows .= '<tr><td style="padding:16px 28px 22px 28px;border-top:1px solid #EFEBE4;font-size:12.5px;line-height:1.5;color:#8A867E">' . $para($t) . '</td></tr>';
                    break;
            }
        }
        return '<div style="background:#F4F2EE;padding:24px 12px;font-family:Arial,Helvetica,sans-serif">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #E7E3DC;border-radius:14px;border-collapse:separate">'
            . $rows . '</table></div>';
    }

    // ------------------------------------------------------------------
    // Defaults: the message library for statuses, the built-in text for actions
    // ------------------------------------------------------------------

    /**
     * What a trigger channel sends while nothing is saved, and what the editor
     * starts from: for a status the message drafted for that status name
     * (helpers/notify_drafts.php), for an action its own built-in text.
     *
     * @param string $statusName used when the status id is not in cdb_styles
     */
    function cdp_notifyDefault($key, $channel, $statusName = '')
    {
        if (strpos((string) $key, 'status:') === 0) {
            $name = cdp_notifyStatusName((int) substr($key, 7));
            return cdp_notifyDraft($name !== '' ? $name : ($statusName !== '' ? $statusName : 'Updated'), $channel);
        }
        if ($key === 'action:shipment.registered') {
            return ['subject' => 'Shipment {tracking} registered', 'blocks' => [],
                'body' => "Hello {name},\n\nYour shipment *{tracking}* has been registered.\n\n- Service: {service}\n- Courier: {courier}\n- Delivery time: {delivery_time}\n- From: {origin_office}\n{details}\nTrack it any time: {track_url}\n\nThank you, *{company}* Team"];
        }
        if ($channel === 'email') {
            return ['subject' => 'Update on {tracking}', 'body' => '', 'blocks' => [
                ['type' => 'header', 'text' => '{company}'],
                ['type' => 'heading', 'text' => 'An update on your shipment'],
                ['type' => 'text', 'text' => "Hello {first_name},\n\nThere is an update on your shipment {tracking}."],
                ['type' => 'button', 'text' => 'Track your shipment', 'url' => '{track_url}'],
                ['type' => 'footer', 'text' => "Thank you for shipping with {company}."],
            ]];
        }
        return ['subject' => '', 'blocks' => [], 'body' => $channel === 'sms' ? '{company}: update on {tracking}. Track: {track_url}' : 'There is an update on shipment {tracking}.'];
    }

    /**
     * The studio switch "Status-specific messages". On (default): a status
     * channel with nothing saved sends its drafted message. Off: it sends the
     * old shared message, as before the library existed. Stored in
     * cdb_ops_settings (ns notify, key status_drafts).
     */
    function cdp_notifyDraftsOn($fresh = false)
    {
        static $on = null;
        if ($on === null || $fresh) {
            $on = true;
            try {
                require_once __DIR__ . '/ops_db.php';
                if (function_exists('cdp_opsSettings')) {
                    $all = cdp_opsSettings('notify', $fresh);
                    $on = ($all['status_drafts'] ?? '1') !== '0';
                }
            } catch (Throwable $e) {
                error_log('notify studio drafts switch: ' . $e->getMessage());
            }
        }
        return $on;
    }

    function cdp_notifyDraftsSet($on, $userId = null)
    {
        require_once __DIR__ . '/ops_db.php';
        $ok = cdp_opsSettingSet('notify', 'status_drafts', $on ? '1' : '0', $userId);
        cdp_notifyDraftsOn(true);
        return (bool) $ok;
    }

    /**
     * Fill a message for sending: prune empty optional lines, compile e-mail
     * blocks, render placeholders, and make sure a ready-for-pickup message
     * carries the pickup code.
     *
     * @param array|null $blocks e-mail blocks (null: use $bodyTpl, an already compiled body)
     * @return array{subject:string,body:string}
     */
    function cdp_notifyBuild($channel, $subjectTpl, $bodyTpl, $blocks, array $vars)
    {
        $html = $channel === 'email';
        // The pickup code sentence says "driver"; our messages call them riders.
        foreach (['pickup_code', 'pickup_code_html'] as $ck) {
            if (isset($vars[$ck])) {
                $vars[$ck] = str_replace('to our driver when', 'to our rider when', (string) $vars[$ck]);
            }
        }
        if ($channel === 'sms' && isset($vars['pickup_code'])) {
            $vars['pickup_code'] = str_replace('*', '', (string) $vars['pickup_code']);   // no bold in a text message
            // Keep a code SMS in one part: the short form of cdp_pickupCodeLine()'s sentence.
            $vars['pickup_code'] = preg_replace([
                '/^Pickup code: (\S+?)\. Show this code when you collect your packages?\. Do not share it with anyone else\./m',
                '/^Pickup code: (\S+?)\. Give this code to our (?:driver|rider) when your packages? arrives?\. Do not share it with anyone else\./m',
            ], ['Pickup code: $1. Show it at the counter; do not share it.', 'Pickup code: $1. Give it to our rider; do not share it.'], $vars['pickup_code']);
        }
        if ($html && is_array($blocks) && $blocks) {
            $tplForCode = cdp_notifyCompileBlocks($blocks);
            $body = cdp_notifyRender(cdp_notifyCompileBlocks($blocks, $vars), $vars, true);
        } elseif ($html) {
            $tplForCode = (string) $bodyTpl;
            $body = cdp_notifyRender($tplForCode, $vars, true);
        } else {
            $tplForCode = (string) $bodyTpl;
            $body = trim(preg_replace("/\n{3,}/", "\n\n", cdp_notifyRender(cdp_notifyPruneLines($tplForCode, $vars), $vars, false)));
        }
        // In-app is one short line in the bell, which staff see too: the code is
        // never added there (the customer gets it by e-mail, SMS and WhatsApp).
        if ($channel !== 'inapp' && strpos($tplForCode, '{pickup_code') === false) {
            if ($html && trim((string) ($vars['pickup_code_html'] ?? '')) !== '') {
                $body .= '<div style="max-width:600px;margin:0 auto;padding:0 28px;font-family:Arial,Helvetica,sans-serif">' . $vars['pickup_code_html'] . '</div>';
            } elseif (!$html && trim((string) ($vars['pickup_code'] ?? '')) !== '') {
                $body .= "\n\n" . $vars['pickup_code'];
            }
        }
        return ['subject' => trim(cdp_notifyRender(cdp_notifyPruneLines((string) $subjectTpl, $vars), $vars, false)), 'body' => $body];
    }

    // ------------------------------------------------------------------
    // Read and write
    // ------------------------------------------------------------------

    /** Saved rows for a trigger: channel => row. */
    function cdp_notifyGet($key)
    {
        if (!cdp_notifyEnsureTable()) {
            return [];
        }
        $db = new Conexion;
        $db->cdp_query('SELECT * FROM cdb_notify_content WHERE trigger_key = :k');
        $db->bind(':k', (string) $key);
        $out = [];
        foreach ((array) $db->cdp_registros() as $r) {
            $out[(string) $r->channel] = $r;
        }
        return $out;
    }

    /** Every saved row, key => channel => row (for the trigger list). */
    function cdp_notifyAll()
    {
        if (!cdp_notifyEnsureTable()) {
            return [];
        }
        $db = new Conexion;
        $db->cdp_query('SELECT trigger_key, channel, enabled, updated_at FROM cdb_notify_content');
        $out = [];
        foreach ((array) $db->cdp_registros() as $r) {
            $out[(string) $r->trigger_key][(string) $r->channel] = $r;
        }
        return $out;
    }

    /**
     * Save one channel of one trigger.
     *
     * @param array $d enabled (0/1), subject, body (text channels), blocks (e-mail, array or JSON)
     * @return array{ok:bool,error?:string}
     */
    function cdp_notifySave($key, $channel, array $d, $userId)
    {
        if (!cdp_notifyKeyValid($key)) {
            return ['ok' => false, 'error' => 'Unknown notification.'];
        }
        if (!isset(cdp_notifyChannels()[$channel])) {
            return ['ok' => false, 'error' => 'Unknown channel.'];
        }
        if (!cdp_notifyEnsureTable()) {
            return ['ok' => false, 'error' => 'The notification table could not be created. The database user needs the CREATE privilege once.'];
        }
        $enabled = !empty($d['enabled']) ? 1 : 0;
        $subject = mb_substr(trim((string) ($d['subject'] ?? '')), 0, 190);
        $blocksJson = null;
        if ($channel === 'email') {
            $blocks = is_array($d['blocks'] ?? null) ? $d['blocks'] : json_decode((string) ($d['blocks'] ?? '[]'), true);
            $blocks = cdp_notifyCleanBlocks($blocks);
            if ($enabled && !$blocks) {
                return ['ok' => false, 'error' => 'Add at least one block to the e-mail.'];
            }
            if ($enabled && $subject === '') {
                return ['ok' => false, 'error' => 'The e-mail needs a subject.'];
            }
            $body = cdp_notifyCompileBlocks($blocks);
            $blocksJson = json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $body = trim(str_replace("\r\n", "\n", (string) ($d['body'] ?? '')));
            if ($enabled && $body === '') {
                return ['ok' => false, 'error' => 'The message cannot be empty.'];
            }
            $max = cdp_notifyLimits()[$channel];
            if (mb_strlen($body) > $max) {
                return ['ok' => false, 'error' => cdp_notifyChannels()[$channel] . ' messages are limited to ' . $max . ' characters.'];
            }
        }
        $db = new Conexion;
        $db->cdp_query('INSERT INTO cdb_notify_content (trigger_key, channel, enabled, subject, body, blocks, updated_by, updated_at)
                        VALUES (:k, :c, :e, :s, :b, :bl, :u, :n)
                        ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), subject = VALUES(subject), body = VALUES(body),
                          blocks = VALUES(blocks), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)');
        $db->bind(':k', (string) $key);
        $db->bind(':c', (string) $channel);
        $db->bind(':e', $enabled);
        $db->bind(':s', $subject !== '' ? $subject : null);
        $db->bind(':b', $body);
        $db->bind(':bl', $blocksJson);
        $db->bind(':u', (int) $userId ?: null);
        $db->bind(':n', date('Y-m-d H:i:s'));
        $ok = $db->cdp_execute() !== false;
        cdp_notifyResolve(null);
        return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'Not saved. Please try again.'];
    }

    /** Remove the saved message of one channel: back to the drafted (status) or built-in message. */
    function cdp_notifyReset($key, $channel)
    {
        if (!cdp_notifyKeyValid($key) || !cdp_notifyEnsureTable()) {
            return ['ok' => false, 'error' => 'Unknown notification.'];
        }
        $db = new Conexion;
        $db->cdp_query('DELETE FROM cdb_notify_content WHERE trigger_key = :k AND channel = :c');
        $db->bind(':k', (string) $key);
        $db->bind(':c', (string) $channel);
        $ok = $db->cdp_execute() !== false;
        cdp_notifyResolve(null);
        return ['ok' => $ok];
    }

    /**
     * What a send point should do for one trigger and channel.
     * Call with $key = null to forget cached rows (after a save).
     *
     *   saved row, enabled   -> the saved message            (custom, source saved)
     *   saved row, disabled  -> nothing on this channel      (on = false)
     *   no row, status       -> the message drafted for that status (custom, source draft),
     *                           unless the studio switch is off
     *   anything else        -> the send point's built-in message (custom = false)
     *
     * A ready-for-pickup message always carries the pickup code: when the text
     * has no {pickup_code...} placeholder and the send point supplied a code,
     * it is added at the end.
     *
     * @return array{on:bool,custom:bool,source:string,subject:string,body:string}
     */
    function cdp_notifyResolve($key, $channel = '', array $vars = [])
    {
        static $cache = [];
        $none = ['on' => true, 'custom' => false, 'source' => 'builtin', 'subject' => '', 'body' => ''];
        if ($key === null) {
            $cache = [];
            return $none;
        }
        try {
            if (!array_key_exists($key, $cache)) {
                $cache[$key] = cdp_notifyGet($key);
            }
            $row = $cache[$key][$channel] ?? null;
        } catch (Throwable $e) {
            error_log('notify studio resolve: ' . $e->getMessage());
            return $none;
        }
        if ($row && (int) $row->enabled !== 1) {
            return ['on' => false, 'custom' => false, 'source' => 'off', 'subject' => '', 'body' => ''];
        }
        $vars = cdp_notifyFitVars($key, $vars);
        try {
            if ($row) {
                $blocks = null;
                if ($channel === 'email' && !empty($row->blocks)) {
                    $blocks = json_decode((string) $row->blocks, true);
                }
                $out = cdp_notifyBuild($channel, (string) $row->subject, (string) $row->body, is_array($blocks) && $blocks ? $blocks : null, $vars);
                return ['on' => true, 'custom' => true, 'source' => 'saved'] + $out;
            }
            if (strpos((string) $key, 'status:') !== 0 || !isset(cdp_notifyChannels()[$channel]) || !cdp_notifyDraftsOn()) {
                return $none;
            }
            $def = cdp_notifyDefault($key, $channel, (string) ($vars['status'] ?? ''));
            if ($channel === 'email' ? !$def['blocks'] : trim((string) $def['body']) === '') {
                return $none;
            }
            $out = cdp_notifyBuild($channel, (string) $def['subject'], (string) $def['body'], $channel === 'email' ? $def['blocks'] : null, $vars);
            if ($out['body'] === '') {
                return $none;
            }
            return ['on' => true, 'custom' => true, 'source' => 'draft'] + $out;
        } catch (Throwable $e) {
            error_log('notify studio resolve build: ' . $e->getMessage());
            return $none;
        }
    }

    /**
     * Send-time values fitted to the status: no estimated arrival once the
     * package is in Ghana, delivered, stopped or listed for auction (the card
     * would show a date that is no longer true), and no "Prefer delivery?"
     * invitation on a message that already says it is being delivered.
     */
    function cdp_notifyFitVars($key, array $vars)
    {
        if (strpos((string) $key, 'status:') !== 0) {
            return $vars;
        }
        $name = cdp_notifyStatusName((int) substr((string) $key, 7));
        if ($name === '') {
            $name = (string) ($vars['status'] ?? '');
        }
        if ($name === '') {
            return $vars;
        }
        $kind = cdp_notifyDraftKind($name);
        if (in_array($kind, cdp_notifyDraftNoEtaKinds(), true)) {
            $vars['eta'] = '';
        }
        if (in_array($kind, ['ready_delivery', 'out_for_delivery'], true)) {
            foreach (['pickup_code' => '/\s*Prefer delivery\?[^\n]*$/', 'pickup_code_html' => '~<p>\s*Prefer delivery\?.*?</p>~s'] as $ck => $re) {
                if (isset($vars[$ck])) {
                    $vars[$ck] = trim((string) preg_replace($re, '', (string) $vars[$ck]));
                }
            }
        }
        return $vars;
    }

    /**
     * What one channel of a trigger sends today, for the studio:
     * draft (drafted for this status, sent) | custom | off | builtin (old shared message).
     */
    function cdp_notifyChannelState($key, $row)
    {
        if ($row) {
            return (int) $row->enabled === 1 ? 'custom' : 'off';
        }
        return strpos((string) $key, 'status:') === 0 && cdp_notifyDraftsOn() ? 'draft' : 'builtin';
    }

    // ------------------------------------------------------------------
    // Rewriting with the AI assistant (King): prompt and validation.
    // The call itself is cdp_aiComplete() (helpers/ai_assistant_llm.php), made by
    // ajax/sl/notify_studio_ajax.php. Nothing here saves: the studio shows the
    // result as a proposal the administrator accepts, retries or discards.
    // ------------------------------------------------------------------

    /** Tone presets offered next to the instruction box. */
    function cdp_notifyAiTones()
    {
        return [
            'keep' => ['Keep tone', 'Keep the tone of the current message.'],
            'warmer' => ['Warmer', 'Make it warmer and more personal, still professional.'],
            'formal' => ['More formal', 'Make it more formal and businesslike.'],
            'shorter' => ['Shorter', 'Make it noticeably shorter; keep only what the customer needs.'],
            'urgent' => ['More urgent', 'Make the call to action clearly urgent, without sounding rude or threatening.'],
            'reassuring' => ['Reassuring', 'Reassure the customer that their package is safe and in good hands.'],
        ];
    }

    /**
     * What the model needs to know about one trigger.
     *
     * @param array $comms for comms:* keys: ['label' => ..., 'help' => ..., 'vars' => [...], 'example' => text]
     * @return array{type:string,key:string,label:string,meaning:string,code:bool,pay:bool,channels:array,vars:array}
     */
    function cdp_notifyAiContext($key, array $comms = [])
    {
        $key = (string) $key;
        $all = cdp_notifyPlaceholders();
        if (strpos($key, 'status:') === 0) {
            $name = cdp_notifyStatusName((int) substr($key, 7)) ?: 'Updated';
            $spec = cdp_notifyDraftSpec($name);
            $vars = [];
            foreach ($all as $k => [$desc, $scope]) {
                if ($scope !== 'action') {
                    $vars[$k] = $desc;
                }
            }
            return ['type' => 'status', 'key' => $key, 'label' => cdp_notifyDraftLabel($name), 'meaning' => $spec['meaning'],
                'code' => in_array($spec['kind'], cdp_notifyDraftCodeKinds(), true), 'pay' => in_array($spec['kind'], cdp_notifyDraftPayKinds(), true),
                'channels' => array_keys(cdp_notifyChannels()), 'vars' => $vars, 'statusName' => $name];
        }
        if (strpos($key, 'action:') === 0) {
            $a = cdp_notifyActions()[$key] ?? ['label' => $key, 'desc' => '', 'channels' => [], 'vars' => []];
            $vars = [];
            foreach ($all as $k => [$desc, $scope]) {
                if ($scope === 'all' || in_array($k, $a['vars'], true)) {
                    $vars[$k] = $desc;
                }
            }
            return ['type' => 'action', 'key' => $key, 'label' => $a['label'], 'meaning' => $a['desc'], 'code' => false, 'pay' => false,
                'channels' => $a['channels'], 'vars' => $vars, 'statusName' => ''];
        }
        $vars = [];
        foreach ((array) ($comms['vars'] ?? []) as $k) {
            $vars[$k] = ucfirst(str_replace('_', ' ', $k));
        }
        return ['type' => 'comms', 'key' => $key, 'label' => (string) ($comms['label'] ?? $key),
            'meaning' => trim((string) ($comms['help'] ?? '') . ' Sent automatically by the Communication Center.' . (!empty($comms['example']) ? ' Its default text was: "' . $comms['example'] . '"' : '')),
            'code' => false, 'pay' => false, 'channels' => ['email', 'sms', 'whatsapp'], 'vars' => $vars, 'statusName' => ''];
    }

    /** Placeholders a channel may use (no *_html outside e-mail). */
    function cdp_notifyAiAllowed(array $ctx, $channel)
    {
        $out = [];
        foreach ($ctx['vars'] as $k => $desc) {
            if (substr($k, -5) === '_html') {
                continue;   // e-mail uses the items / pickup_code blocks; text channels have no HTML
            }
            if ($ctx['type'] === 'status' && (($k === 'pickup_code' && !$ctx['code']) || (in_array($k, ['pay_url', 'amount_due'], true) && !$ctx['pay']))) {
                continue;   // only where the status really carries a code / asks for payment
            }
            $out[$k] = $desc;
        }
        return $out;
    }

    /**
     * System prompt and user prompt for one channel or for every channel.
     *
     * @param array $channels channels to write
     * @param array $current  channel => ['subject' => , 'body' => , 'blocks' => []]
     * @return array{0:string,1:string}
     */
    function cdp_notifyAiPrompt(array $ctx, array $channels, array $current, $tone = 'keep', $instruction = '', $assistant = 'King', $company = 'Swift Lane')
    {
        $tones = cdp_notifyAiTones();
        $system = "You are {$assistant}, the writing assistant of {$company}, a freight forwarder that ships parcels from the United States to Ghana.\n"
            . "How the business works: each customer gets a US warehouse address and a locker ID. They shop online in the US, their parcels arrive at our US warehouse, "
            . "are consolidated and sent to Accra by air or by sea, and then the customer picks them up at our office in Ghana with a pickup code, or one of our riders delivers them.\n"
            . "Customers live in Ghana and in the US. Write in warm, professional, plain English with short sentences. "
            . "No exaggerated marketing, no emojis, no hashtags. Never invent facts: no prices, amounts, dates, opening hours, addresses or deadlines unless the administrator's instruction gives them or a placeholder holds them.\n"
            . "You write the automatic message a customer receives for one event. Everything you write must be about exactly that event and nothing else.\n\n"
            . "PLACEHOLDERS: written in single curly braces, e.g. {tracking}; they are filled in when the message is sent. Use ONLY placeholders from the list you are given. "
            . "Never invent other placeholders and never write [TRACKING] or {{tracking}}. Always use {company} for the company name and {tracking} for the tracking number. "
            . "A line whose placeholders are all empty is removed when the message is sent, so put optional facts ({eta}, {comment}, {location}, {company_phone}, {carrier_tracking}, {weight}) on their own line, never in the middle of a sentence.\n\n"
            . "CHANNEL RULES\n"
            . "- email: a subject (at most 90 characters, no placeholders other than {tracking} and {company}) and \"blocks\", an array of objects, top to bottom. "
            . "Block types: {\"type\":\"header\",\"text\":\"{company}\"}, {\"type\":\"heading\",\"text\":...}, {\"type\":\"text\",\"text\":...} (a paragraph, \\n for line breaks), "
            . "{\"type\":\"status_card\"} (tracking, status, carrier tracking, weight, estimated delivery), {\"type\":\"items\"} (the items in the package), "
            . "{\"type\":\"pickup_code\"} (the pickup code box), {\"type\":\"button\",\"text\":...,\"url\":\"{track_url}\"}, {\"type\":\"divider\"}, {\"type\":\"footer\",\"text\":...}. "
            . "Start with the header, then a heading, then a text block that greets the customer with \"Hello {first_name},\". End with a footer. Plain text only: no HTML, no markdown, no *stars*.\n"
            . "- sms: ONE plain text message of at most 160 characters AFTER the placeholders are filled ({track_url} is about 50 characters, {tracking} about 10, {company} about 12, {first_name} about 8, {pickup_code} about 100). "
            . "No markdown, no stars. Start with \"{company}:\". Leave out the link if it does not fit.\n"
            . "- whatsapp: friendly, short lines, a greeting \"Hello {first_name},\", the key facts in *bold* with single asterisks, a blank line between paragraphs, include {track_url}, sign off with {company}. At most 700 characters.\n"
            . "- inapp: ONE short sentence of at most 120 characters, third person (\"Shipment {tracking} ...\"), no greeting: it is shown in the notification bell, which staff also see.\n\n"
            . "Reply with JSON only. No explanation, no code fences.";

        $lines = [];
        $lines[] = 'EVENT: ' . ($ctx['type'] === 'status' ? 'the shipment or package was moved to the status "' . $ctx['label'] . '"' : $ctx['label']);
        $lines[] = 'WHAT IT MEANS: ' . $ctx['meaning'];
        if ($ctx['type'] === 'status') {
            $lines[] = $ctx['code']
                ? 'This status carries the customer\'s pickup code: use {pickup_code} in text messages and a pickup_code block in the e-mail.'
                : 'This status does NOT carry a pickup code: do not mention or use one.';
            $lines[] = $ctx['pay']
                ? 'This status asks the customer to pay: use {pay_url} for the payment link (an e-mail button "Pay now" with url {pay_url}). For the amount use {amount_due} on its own line (e.g. "Amount due: {amount_due}"); never write a figure yourself.'
                : 'Do not ask for payment and do not use {pay_url} or {amount_due}.';
        }
        $lines[] = '';
        foreach ($channels as $ch) {
            $allowed = cdp_notifyAiAllowed($ctx, $ch);
            $list = [];
            foreach ($allowed as $k => $desc) {
                $list[] = '{' . $k . '} = ' . $desc;
            }
            $lines[] = 'ALLOWED PLACEHOLDERS FOR ' . strtoupper($ch) . ': ' . implode('; ', $list);
        }
        $lines[] = '';
        $lines[] = 'TONE: ' . ($tones[$tone][1] ?? $tones['keep'][1]);
        $instruction = trim(preg_replace('/\s+/', ' ', (string) $instruction));
        if ($instruction !== '') {
            $lines[] = 'INSTRUCTION FROM THE ADMINISTRATOR (follow it, within the rules above): ' . mb_substr($instruction, 0, 400);
        }
        $lines[] = '';
        $lines[] = 'CURRENT MESSAGE (improve it, or rewrite it if it does not fit the event):';
        foreach ($channels as $ch) {
            $c = $current[$ch] ?? [];
            if ($ch === 'email') {
                $lines[] = 'email subject: ' . (string) ($c['subject'] ?? '');
                $lines[] = 'email blocks: ' . json_encode(array_values(cdp_notifyCleanBlocks($c['blocks'] ?? [])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                $lines[] = $ch . ': ' . json_encode((string) ($c['body'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        $lines[] = '';
        if (count($channels) === 1) {
            $ch = $channels[0];
            $lines[] = $ch === 'email'
                ? 'Write the e-mail. Reply with exactly: {"subject": "...", "blocks": [ ... ]}'
                : 'Write the ' . $ch . ' message. Reply with exactly: {"text": "..."}';
        } else {
            $shape = [];
            foreach ($channels as $ch) {
                $shape[] = $ch === 'email' ? '"email": {"subject": "...", "blocks": [ ... ]}' : '"' . $ch . '": "..."';
            }
            $lines[] = 'Write every channel. Reply with exactly: {' . implode(', ', $shape) . '}';
        }
        return [$system, implode("\n", $lines)];
    }

    /** The first JSON object in a model reply (code fences and chatter around it are ignored). */
    function cdp_notifyAiJson($text)
    {
        $t = trim((string) $text);
        $t = preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $t);
        $a = strpos($t, '{');
        $b = strrpos($t, '}');
        if ($a === false || $b === false || $b <= $a) {
            return null;
        }
        $j = json_decode(substr($t, $a, $b - $a + 1), true);
        return is_array($j) ? $j : null;
    }

    /** Cut at a sentence end (or a space) so a text fits $max characters. */
    function cdp_notifyAiCut($text, $max)
    {
        $text = (string) $text;
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max);
        $end = max((int) mb_strrpos($cut, '. '), (int) mb_strrpos($cut, ".\n"));
        if ($end > $max * 0.5) {
            return rtrim(mb_substr($cut, 0, $end + 1));
        }
        $sp = (int) mb_strrpos($cut, ' ');
        return rtrim(mb_substr($cut, 0, $sp > 0 ? $sp : $max), " ,;:-");
    }

    /**
     * Clean one AI text: only allowed placeholders survive, no HTML, no markup
     * where the channel has none, tidy spaces. Adds human warnings to $warn.
     */
    function cdp_notifyAiCleanText($text, array $allowed, $channel, array &$warn, $field = '')
    {
        $t = str_replace(["\r\n", "\r"], "\n", (string) $text);
        $t = strip_tags($t);
        $t = preg_replace('/\{\{\s*([A-Za-z_]+)\s*\}\}/', '{$1}', $t);
        $t = preg_replace_callback('/\[([A-Z_]{3,})\]/', function ($m) use ($allowed) {
            $k = strtolower($m[1]);
            return isset($allowed[$k]) ? '{' . $k . '}' : $m[0];
        }, $t);
        $t = preg_replace_callback('/\{\s*([A-Za-z_]+)\s*\}/', function ($m) use ($allowed, &$warn) {
            $k = strtolower($m[1]);
            if (isset($allowed[$k])) {
                return '{' . $k . '}';
            }
            $warn['{' . $k . '}'] = 'Removed {' . $k . '}: that field is not filled for this message.';
            return '';
        }, $t);
        if (in_array($channel, ['sms', 'inapp', 'subject', 'email'], true)) {
            $t = str_replace('**', '', $t);
            $t = preg_replace('/\*([^*\n]+)\*/', '$1', $t);   // no bold outside WhatsApp
        }
        if ($channel === 'whatsapp') {
            $t = preg_replace('/\*\*([^*\n]+)\*\*/', '*$1*', $t);   // markdown bold -> WhatsApp bold
        }
        $t = preg_replace('/[ \t]+\n/', "\n", $t);
        $t = preg_replace('/[ \t]{2,}/', ' ', $t);
        $t = preg_replace('/ ([.,!?;:])/', '$1', $t);
        $t = preg_replace("/\n{3,}/", "\n\n", $t);
        if ($channel === 'inapp' || $channel === 'subject') {
            $t = preg_replace('/\s*\n\s*/', ' ', $t);
        }
        return trim($t);
    }

    /**
     * Check and tidy the model's reply. Returns per channel:
     * ['subject', 'body', 'blocks', 'warnings' => [], 'chars' => n, 'parts' => n].
     *
     * @return array{ok:bool,error?:string,proposals?:array}
     */
    function cdp_notifyAiParse($reply, array $ctx, array $channels, array $sample)
    {
        $j = cdp_notifyAiJson($reply);
        if (!$j) {
            return ['ok' => false, 'error' => 'The reply could not be read as a message. Please try again.'];
        }
        $types = cdp_notifyBlockTypes();
        $limits = cdp_notifyLimits();
        $out = [];
        foreach ($channels as $ch) {
            $raw = count($channels) === 1 ? $j : ($j[$ch] ?? null);
            $warn = [];
            $allowed = cdp_notifyAiAllowed($ctx, $ch);
            if ($ch === 'email') {
                $raw = is_array($raw) ? $raw : [];
                $subject = cdp_notifyAiCleanText((string) ($raw['subject'] ?? ''), array_intersect_key($allowed, ['tracking' => 1, 'company' => 1, 'first_name' => 1, 'status' => 1]), 'subject', $warn);
                $subject = cdp_notifyAiCut($subject, 150);
                $blocks = [];
                foreach (is_array($raw['blocks'] ?? null) ? $raw['blocks'] : [] as $bl) {
                    if (!is_array($bl) || !isset($types[(string) ($bl['type'] ?? '')])) {
                        continue;
                    }
                    $type = (string) $bl['type'];
                    if ($type === 'image') {
                        $warn['image'] = 'Left out an image block: add images yourself.';
                        continue;
                    }
                    if ($type === 'pickup_code' && !$ctx['code']) {
                        $warn['code'] = 'Left out a pickup code block: this status has no pickup code.';
                        continue;
                    }
                    if (in_array($type, ['status_card', 'items', 'pickup_code'], true) && $ctx['type'] !== 'status') {
                        continue;
                    }
                    $clean = ['type' => $type];
                    foreach (array_keys($types[$type]['fields']) as $f) {
                        $v = (string) ($bl[$f] ?? '');
                        if ($f === 'url') {
                            // Model output is untrusted: a button may only link to the
                            // tracking page or (payment statuses) the payment page.
                            $raw = trim((string) $v);
                            $v = trim(cdp_notifyAiCleanText($v, $allowed, 'subject', $warn));
                            $okUrls = ['{track_url}'];
                            if (isset($allowed['pay_url'])) {
                                $okUrls[] = '{pay_url}';
                            }
                            foreach (['schedule_url', 'survey_url', 'prefs_url'] as $cu) {
                                if (isset($allowed[$cu])) {
                                    $okUrls[] = '{' . $cu . '}';
                                }
                            }
                            if (!in_array($v, $okUrls, true)) {
                                if ($raw !== '' && !in_array($raw, ['{track_url}', '{pay_url}'], true)) {
                                    $warn['url'] = 'Replaced a button link (' . mb_substr($raw, 0, 60) . ') with the tracking link: buttons may only link to {track_url}' . (isset($allowed['pay_url']) ? ' or {pay_url}' : '') . '.';
                                }
                                $v = '{track_url}';
                            }
                        } else {
                            $v = cdp_notifyAiCleanText($v, $allowed, 'email', $warn);
                        }
                        $clean[$f] = $v;
                    }
                    if (in_array($type, ['heading', 'text', 'footer'], true) && ($clean['text'] ?? '') === '') {
                        continue;
                    }
                    $blocks[] = $clean;
                    if (count($blocks) >= 16) {
                        break;
                    }
                }
                $blocks = cdp_notifyCleanBlocks($blocks);
                if (!$blocks || !array_filter($blocks, function ($b) { return in_array($b['type'], ['heading', 'text'], true); })) {
                    return ['ok' => false, 'error' => 'The e-mail that came back had no text. Please try again.'];
                }
                if ($blocks[0]['type'] !== 'header') {
                    array_unshift($blocks, ['type' => 'header', 'text' => '{company}']);
                }
                if ($subject === '') {
                    $warn['subject'] = 'No subject came back; please write one.';
                }
                $html = cdp_notifyRender(cdp_notifyCompileBlocks($blocks, $sample), $sample, true);
                if ($ctx['type'] === 'status' && strpos(json_encode($blocks), '{tracking}') === false && strpos($subject, '{tracking}') === false) {
                    $warn['tracking'] = 'The e-mail does not mention the tracking number.';
                }
                $out[$ch] = ['subject' => $subject, 'body' => '', 'blocks' => $blocks, 'warnings' => array_values($warn),
                    'preview' => ['subject' => cdp_notifyRender(cdp_notifyPruneLines($subject, $sample), $sample), 'html' => $html]];
                continue;
            }
            $text = is_array($raw) ? (string) ($raw['text'] ?? '') : (string) $raw;
            $text = cdp_notifyAiCleanText($text, $allowed, $ch, $warn);
            if ($text === '') {
                return ['ok' => false, 'error' => 'The ' . cdp_notifyChannels()[$ch] . ' message came back empty. Please try again.'];
            }
            $hard = ['sms' => 480, 'whatsapp' => 1500, 'inapp' => 200][$ch] ?? $limits[$ch];
            if (mb_strlen($text) > $hard) {
                $text = cdp_notifyAiCut($text, $hard);
                $warn['cut'] = 'Shortened to ' . $hard . ' characters.';
            }
            $filled = trim(cdp_notifyRender(cdp_notifyPruneLines($text, $sample), $sample));
            $len = mb_strlen($filled);
            $parts = $ch === 'sms' ? cdp_notifySmsParts($filled) : 0;
            if ($ch === 'sms' && $len > 160) {
                $warn['long'] = 'With real values this SMS is about ' . $len . ' characters (' . $parts . ' parts).';
            }
            if ($ch === 'inapp' && $len > 160) {
                $warn['long'] = 'Longer than one short sentence (' . $len . ' characters).';
            }
            if ($ctx['code'] && $ch !== 'inapp' && strpos($text, '{pickup_code}') === false) {
                $warn['code'] = 'No {pickup_code}: the code is added at the end when it is sent.';
            }
            if ($ctx['type'] === 'status' && strpos($text, '{tracking}') === false) {
                $warn['tracking'] = 'The message does not mention {tracking}.';
            }
            $out[$ch] = ['subject' => '', 'body' => $text, 'blocks' => [], 'warnings' => array_values($warn), 'chars' => $len, 'parts' => $parts,
                'preview' => ['text' => $filled]];
        }
        return ['ok' => true, 'proposals' => $out];
    }
}
