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
 *   No row      = the built-in message the trigger already sends. Nothing changes.
 *   enabled = 0 = the channel is switched off for that trigger.
 *   enabled = 1 = the saved subject/body is sent instead of the built-in one.
 *
 * Send points call cdp_notifyResolve($key, $channel, $vars) and get
 *   ['on' => bool, 'custom' => bool, 'subject' => string, 'body' => string].
 *
 * Placeholders are {braces}. In e-mail the values are HTML-escaped, except the
 * ones ending in _html, which the send point builds itself (items table,
 * pickup code box). Studio text is never HTML: e-mail bodies are compiled from
 * blocks here, so an editor cannot inject markup.
 */

require_once __DIR__ . '/querys.php';

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
            'date'             => ['Date and time of the message', 'all'],
            'status'           => ['New status', 'status'],
            'location'         => ['Where the update was made', 'status'],
            'comment'          => ['Note typed with the update', 'status'],
            'carrier_tracking' => ['Postal / carrier tracking number', 'status'],
            'eta'              => ['Estimated delivery (consolidation ETA when consolidated)', 'status'],
            'weight'           => ['Original total weight', 'status'],
            'items'            => ['Items, one per line', 'status'],
            'items_html'       => ['Items as a table (e-mail only)', 'status'],
            'pickup_code'      => ['Pickup code line (ready for pickup only)', 'status'],
            'pickup_code_html' => ['Pickup code box (e-mail, ready for pickup only)', 'status'],
            'service'          => ['Shipping service', 'action'],
            'courier'          => ['Courier company', 'action'],
            'delivery_time'    => ['Delivery time', 'action'],
            'origin_office'    => ['Origin office', 'action'],
            'details'          => ['Weight, contents and carrier lines', 'action'],
        ];
    }

    /** Sample values for previews and test messages. */
    function cdp_notifySampleVars()
    {
        $core = class_exists('Core') ? new Core : null;
        $company = trim((string) ($core->site_name ?? '')) ?: 'Swift Lane';
        $site = rtrim((string) ($core->site_url ?? ''), '/');
        return [
            'name' => 'Efua Owusu (SL-1042)', 'first_name' => 'Efua', 'locker' => 'SL-1042',
            'tracking' => 'SL-100482', 'track_url' => ($site !== '' ? $site : 'https://example.com') . '/track.php?order_track=SL-100482',
            'company' => $company, 'date' => date('M j, Y · g:i A'),
            'status' => 'Ready for pickup', 'location' => 'Accra office', 'comment' => 'Collect from counter 2',
            'carrier_tracking' => '9400 1112 0261 3450 9822 18', 'eta' => date('M j, Y', strtotime('+3 days')), 'weight' => '4.20 lbs',
            'items' => "2 x Running shoes\n1 x Phone case",
            'items_html' => '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px"><tr><td style="padding:6px 0;border-bottom:1px solid #eee">Running shoes</td><td style="padding:6px 0;border-bottom:1px solid #eee;text-align:right">× 2</td></tr><tr><td style="padding:6px 0">Phone case</td><td style="padding:6px 0;text-align:right">× 1</td></tr></table>',
            'pickup_code' => 'Pickup code: *482193*. Show this code when you collect your package. Do not share it with anyone else.',
            'pickup_code_html' => '<p style="margin:12px 0 4px 0;">Your pickup code:</p><p style="margin:0 0 8px 0;font-size:28px;font-weight:700;letter-spacing:6px;color:#1a1a1a;">482193</p><p style="margin:0 0 12px 0;">Show this code when you collect your package. Do not share it with anyone else.</p>',
            'service' => 'Air express', 'courier' => 'USPS', 'delivery_time' => '5 to 7 days', 'origin_office' => 'New York warehouse',
            'details' => "• Weight: 4.20 lb\n• Contents: Running shoes",
        ];
    }

    /**
     * Base values every send point fills: customer, tracking, link, company, date.
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
        ];
    }

    /**
     * Package values for a status message: carrier number, ETA, weight and items,
     * from the shared enrichment (helpers/notify_placeholders.php). Never money.
     */
    function cdp_notifyPackageVars($orderId, $module = 'air')
    {
        $out = ['carrier_tracking' => '', 'eta' => '', 'weight' => '', 'items' => '', 'items_html' => ''];
        try {
            require_once __DIR__ . '/notify_placeholders.php';
            if (function_exists('cdp_buildPackageNotifyPlaceholders') && (int) $orderId > 0) {
                $p = (array) cdp_buildPackageNotifyPlaceholders((int) $orderId, $module === 'sea' ? 'sea' : 'air');
                $out['carrier_tracking'] = (string) ($p['[POSTAL_TRACKING]'] ?? '');
                $out['eta'] = (string) ($p['[ETA]'] ?? '');
                $out['weight'] = (string) ($p['[WEIGHT]'] ?? '');
                $out['items'] = (string) ($p['[ITEMS]'] ?? '');
                $out['items_html'] = (string) ($p['[ITEMS_HTML]'] ?? '');
            }
        } catch (Throwable $e) {
            error_log('notify studio package vars: ' . $e->getMessage());
        }
        return $out;
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
        return preg_replace_callback('/\{([a-z_]+)\}/', function ($m) use ($vars, $html) {
            if (!array_key_exists($m[1], $vars)) {
                return '';
            }
            $v = (string) $vars[$m[1]];
            if (!$html || substr($m[1], -5) === '_html') {
                return $v;
            }
            return nl2br(htmlspecialchars($v, ENT_QUOTES, 'UTF-8'), false);
        }, (string) $text);
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
     */
    function cdp_notifyCompileBlocks(array $blocks)
    {
        $e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
        $para = function ($s) use ($e) { return nl2br($e($s), false); };
        $rows = '';
        foreach (cdp_notifyCleanBlocks($blocks) as $b) {
            $t = $b['text'] ?? '';
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
                    $line = function ($k, $v) use ($e) {
                        return '<tr><td style="padding:6px 0;font-size:13px;color:#77736B;width:42%">' . $e($k) . '</td><td style="padding:6px 0;font-size:15px;font-weight:600;color:#1E1D1A">' . $v . '</td></tr>';
                    };
                    $rows .= '<tr><td style="padding:12px 28px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F7F5F0;border-radius:10px;padding:8px 16px">'
                        . $line('Tracking', '{tracking}') . $line('Carrier tracking', '{carrier_tracking}') . $line('Status', '{status}') . $line('Estimated delivery', '{eta}')
                        . '</table></td></tr>';
                    break;
                case 'button':
                    $url = cdp_notifySafeUrl($b['url'] ?? '') ?: '{track_url}';
                    $rows .= '<tr><td style="padding:16px 28px"><a href="' . $e($url) . '" style="display:inline-block;background:#1E1D1A;color:#ffffff;text-decoration:none;font-size:15px;font-weight:600;padding:12px 22px;border-radius:10px">' . $e($t !== '' ? $t : 'Track your shipment') . '</a></td></tr>';
                    break;
                case 'items':
                    $rows .= '<tr><td style="padding:10px 28px"><div style="font-size:13px;color:#77736B;margin-bottom:4px">Items</div>{items_html}</td></tr>';
                    break;
                case 'pickup_code':
                    $rows .= '<tr><td style="padding:10px 28px">{pickup_code_html}</td></tr>';
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
    // Defaults (what a new custom message starts from)
    // ------------------------------------------------------------------

    function cdp_notifyDefault($key, $channel)
    {
        $isStatus = strpos($key, 'status:') === 0;
        if ($key === 'action:shipment.registered') {
            return ['subject' => 'Shipment {tracking} registered', 'blocks' => [],
                'body' => "Hello {name},\n\nYour shipment *{tracking}* has been registered.\n\n- Service: {service}\n- Courier: {courier}\n- Delivery time: {delivery_time}\n- From: {origin_office}\n{details}\nTrack it any time: {track_url}\n\nThank you, *{company}* Team"];
        }
        if ($channel === 'email') {
            return ['subject' => $isStatus ? 'Update on {tracking}: {status}' : 'Update on {tracking}', 'body' => '', 'blocks' => [
                ['type' => 'header', 'text' => '{company}'],
                ['type' => 'heading', 'text' => $isStatus ? 'Your shipment is now {status}' : 'An update on your shipment'],
                ['type' => 'text', 'text' => "Hello {first_name},\n\nThere is an update on your shipment {tracking}."],
                ['type' => 'status_card'],
                ['type' => 'pickup_code'],
                ['type' => 'items'],
                ['type' => 'button', 'text' => 'Track your shipment', 'url' => '{track_url}'],
                ['type' => 'footer', 'text' => "Thank you for shipping with {company}.\nYou receive this message because you have a shipment with us."],
            ]];
        }
        if ($channel === 'sms') {
            return ['subject' => '', 'blocks' => [], 'body' => '{company}: {tracking} is now {status}. Track: {track_url}'];
        }
        if ($channel === 'inapp') {
            return ['subject' => '', 'blocks' => [], 'body' => 'Shipment {tracking} is now {status}.'];
        }
        return ['subject' => '', 'blocks' => [], 'body' => "Hello {name},\n\nThere is a tracking update on your shipment *{tracking}*.\n\n- *Status:* *{status}*\n- *Location:* {location}\n\nTrack your shipment at any time:\n{track_url}\n\nThank you, *{company}* Team"];
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

    /** Back to the built-in message for one channel. */
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
     * A ready-for-pickup message always carries the pickup code: when the saved
     * text has no {pickup_code...} placeholder and the send point supplied a code,
     * it is added at the end.
     *
     * @return array{on:bool,custom:bool,subject:string,body:string}
     */
    function cdp_notifyResolve($key, $channel = '', array $vars = [])
    {
        static $cache = [];
        if ($key === null) {
            $cache = [];
            return ['on' => true, 'custom' => false, 'subject' => '', 'body' => ''];
        }
        $none = ['on' => true, 'custom' => false, 'subject' => '', 'body' => ''];
        try {
            if (!array_key_exists($key, $cache)) {
                $cache[$key] = cdp_notifyGet($key);
            }
            $row = $cache[$key][$channel] ?? null;
        } catch (Throwable $e) {
            error_log('notify studio resolve: ' . $e->getMessage());
            return $none;
        }
        if (!$row) {
            return $none;
        }
        if ((int) $row->enabled !== 1) {
            return ['on' => false, 'custom' => false, 'subject' => '', 'body' => ''];
        }
        $html = $channel === 'email';
        $tpl = (string) $row->body;
        $hasCode = strpos($tpl, '{pickup_code') !== false;
        $body = cdp_notifyRender($tpl, $vars, $html);
        if (!$hasCode) {
            if ($html && trim((string) ($vars['pickup_code_html'] ?? '')) !== '') {
                $body .= '<div style="max-width:600px;margin:0 auto;padding:0 28px;font-family:Arial,Helvetica,sans-serif">' . $vars['pickup_code_html'] . '</div>';
            } elseif (!$html && trim((string) ($vars['pickup_code'] ?? '')) !== '') {
                $body .= "\n\n" . $vars['pickup_code'];
            }
        }
        return [
            'on' => true, 'custom' => true,
            'subject' => cdp_notifyRender((string) $row->subject, $vars, false),
            'body' => $body,
        ];
    }
}
