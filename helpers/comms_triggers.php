<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************

/**
 * Communication Center v2: the trigger model.
 *
 * Everything that sends a customer a message is a TRIGGER with a key:
 *
 *   status:<cdb_styles.id>   a shipment / package / consolidation moved to that status
 *   status:*                 any status that has no channel switched on of its own
 *                            (off by default; replaces the old "shipment_alert")
 *   claim_reminder, payment_reminder, driver_arrival, survey
 *                            the remaining Communication Center events
 *   legacy:<source>          a message still sent by the old per-screen code
 *                            (registration, sign-up, invoice ...): only its
 *                            on/off switch per channel lives here, enforced by
 *                            helpers/comms_gate.php; its text stays in the legacy
 *                            template tables
 *   manual, campaign         sends typed by staff (no rule, never switched)
 *
 * Switches: cdb_ops_settings ns=comms key=rules, JSON {key: {whatsapp:0|1, sms:0|1, email:0|1}}.
 * Texts:    cdb_comm_templates (event_key = trigger key, channel, language).
 *           With no saved row a status sends the message drafted for it in
 *           helpers/notify_drafts.php (chosen by the status NAME, never the id).
 *
 * Statuses are never classified into events any more (the old name-regex
 * classifier sent "No Picked up" a satisfaction survey and "Not Shipped" a
 * departure notice). Each status is its own trigger with its own text.
 */

require_once __DIR__ . '/ops_db.php';

if (!function_exists('cdp_commTriggerKeyValid')) {

    /** Is $key a trigger key a rule or a template may use? */
    function cdp_commTriggerKeyValid($key)
    {
        $key = (string) $key;
        if (preg_match('/^status:([0-9]{1,6}|\*)$/', $key) || preg_match('/^legacy:[a-z][a-z0-9_]{1,38}$/', $key)) {
            return true;
        }
        return function_exists('cdp_commEvents') && isset(cdp_commEvents()[$key]);
    }

    /**
     * Status id of a status key: N for status:N, 0 for status:*, -1 for any
     * other key.
     */
    function cdp_commTriggerStatusId($key)
    {
        if (preg_match('/^status:([0-9]{1,6})$/', (string) $key, $m)) {
            return (int) $m[1];
        }
        return (string) $key === 'status:*' ? 0 : -1;
    }

    /** cdb_styles.id => name, one query per request. */
    function cdp_commStatusNames()
    {
        static $names = null;
        if ($names === null) {
            $names = [];
            foreach (cdp_opsQ("SELECT id, mod_style FROM cdb_styles ORDER BY id") as $r) {
                $names[(int) $r->id] = (string) $r->mod_style;
            }
        }
        return $names;
    }

    function cdp_commStatusName($id)
    {
        return cdp_commStatusNames()[(int) $id] ?? '';
    }

    /** Human label of a status name ("Pending_Collection" -> "Pending Collection"). */
    function cdp_commStatusLabel($name)
    {
        require_once __DIR__ . '/notify_drafts.php';
        return cdp_notifyDraftLabel($name);
    }

    /**
     * Kind of a status (cdp_notifyDraftKind: delivered, picked_up, not_picked_up,
     * ready_pickup, out_for_delivery ...). 'fallback' when nothing matches.
     */
    function cdp_commStatusKind($statusId, $name = null)
    {
        require_once __DIR__ . '/notify_drafts.php';
        $name = $name ?? cdp_commStatusName((int) $statusId);
        return cdp_notifyDraftKind((string) $name);
    }

    /** Kinds whose messages ignore quiet hours (time-critical). */
    function cdp_commUrgentKinds()
    {
        return ['out_for_delivery', 'failed_delivery'];
    }

    // ------------------------------------------------------------------
    // Legacy triggers (still sent by the per-screen code)
    // ------------------------------------------------------------------

    /**
     * Messages the old per-screen code sends, keyed by their message-log
     * source (cdp_msgInferSource / cdp_msgSetContext). Each can be switched
     * off per channel (rules['legacy:<source>']); helpers/comms_gate.php
     * enforces the switch at the transport.
     *
     * status_family: a status announcement; with comms.status_owner = comms the
     *                Automation Center sends it instead (the gate blocks the old send).
     * locked:        security messages that are always sent.
     * templates:     where the text lives: channel => ['table' => email|sms|whatsapp, 'ids' => [...]]
     *                (legacy tables cdb_email_templates, cdb_sms_templates, whatsapp_templates).
     * tags:          the [TAGS] the sender really fills.
     *
     * @return array<string,array>
     */
    function cdp_commLegacyTriggers()
    {
        $statusTags = ['[NAME]', '[TRACKING]', '[COURIER]', '[DELIVERY_TIME]', '[NEW_ADDRESS]', '[COMMENT]', '[URL]', '[URL_SHIP]', '[SITE_NAME]'];
        return [
            // Status announcements (owned by status_owner)
            'tracking_update' => ['label' => 'Status update (single shipment / package)', 'group' => 'statuses', 'status_family' => true, 'locked' => false,
                'desc' => 'The status screens of a shipment or package (air and sea).', 'channels' => ['email', 'sms', 'whatsapp'],
                'templates' => ['email' => ['table' => 'email', 'ids' => [10]], 'sms' => ['table' => 'sms', 'ids' => [3, 4]], 'whatsapp' => ['table' => 'whatsapp', 'ids' => [11]]], 'tags' => $statusTags],
            'bulk_status_update' => ['label' => 'Status update (several at once)', 'group' => 'statuses', 'status_family' => true, 'locked' => false,
                'desc' => 'Bulk status changes from the shipment and package lists.', 'channels' => ['email', 'sms', 'whatsapp'],
                'templates' => ['email' => ['table' => 'email', 'ids' => [35]], 'whatsapp' => ['table' => 'whatsapp', 'ids' => [11]]], 'tags' => $statusTags],
            'delivery' => ['label' => 'Delivered / picked up', 'group' => 'statuses', 'status_family' => true, 'locked' => false,
                'desc' => 'Deliver and hand-over screens.', 'channels' => ['email', 'sms', 'whatsapp'],
                'templates' => ['email' => ['table' => 'email', 'ids' => [10, 23]], 'whatsapp' => ['table' => 'whatsapp', 'ids' => [3]]], 'tags' => $statusTags],
            'consolidation_update' => ['label' => 'Consolidation update (package owners)', 'group' => 'statuses', 'status_family' => true, 'locked' => false,
                'desc' => 'A consolidation changes status: every package owner inside it is told.', 'channels' => ['email', 'whatsapp'],
                'templates' => ['email' => ['table' => 'email', 'ids' => [36]], 'whatsapp' => ['table' => 'whatsapp', 'ids' => [11]]],
                'tags' => ['[NAME]', '[TRACKING]', '[CHANGED_FIELDS]', '[PACKAGES_SECTION]', '[TOTAL_AMOUNT]', '[URL_SHIP]']],
            'shipment_updated' => ['label' => 'Shipment edited', 'group' => 'shipments', 'status_family' => true, 'locked' => false,
                'desc' => 'A shipment or package is edited (its status can change with it).', 'channels' => ['email', 'whatsapp'],
                'templates' => ['email' => ['table' => 'email', 'ids' => [34, 36]], 'whatsapp' => ['table' => 'whatsapp', 'ids' => [13]]],
                'tags' => ['[NAME]', '[TRACKING]', '[CHANGED_FIELDS]', '[URL_SHIP]', '[SITE_NAME]']],

            // Shipments
            'shipment_registered' => ['label' => 'Shipment registered', 'group' => 'shipments', 'status_family' => false, 'locked' => false,
                'desc' => 'A new shipment or warehouse package is created for a customer.', 'channels' => ['email', 'sms', 'whatsapp'],
                'templates' => ['email' => ['table' => 'email', 'ids' => [16]], 'whatsapp' => ['table' => 'whatsapp', 'ids' => [4]]],
                'tags' => ['[CUSTOMER_FULLNAME]', '[TRACKING_NUMBER]', '[SERVICE_TYPE]', '[COURIER_NAME]', '[DELIVERY_TIME]', '[ORIGIN_OFFICE]', '[EXTRA_DETAILS]', '[TRACK_URL]', '[COMPANY_NAME]']],
            'pickup_code' => ['label' => 'Pickup code', 'group' => 'shipments', 'status_family' => false, 'locked' => false,
                'desc' => 'The pickup code sent on its own (resend from the hand-over screen).', 'channels' => ['email', 'sms', 'whatsapp'],
                'templates' => ['email' => ['table' => 'email', 'ids' => [29]]], 'tags' => ['[USERNAME]', '[MESSAGE]', '[URL]', '[SITE_NAME]']],
            'pickup_aging' => ['label' => 'Pickup aging reminder', 'group' => 'shipments', 'status_family' => false, 'locked' => false,
                'desc' => 'Packages waiting at the office for a long time.', 'channels' => ['email', 'sms', 'whatsapp'],
                'templates' => [], 'tags' => ['[NAME]', '[TRACKING]', '[DAYS]', '[SITE_NAME]']],
            'prealert' => ['label' => 'Pre-alert', 'group' => 'shipments', 'status_family' => false, 'locked' => false,
                'desc' => 'A customer pre-alert is received or turned into a package.', 'channels' => ['email', 'whatsapp'],
                'templates' => ['email' => ['table' => 'email', 'ids' => [33]], 'whatsapp' => ['table' => 'whatsapp', 'ids' => [16]]], 'tags' => ['[NAME]', '[TRACKING]', '[URL_SHIP]', '[SITE_NAME]']],
            'driver_assignment' => ['label' => 'Driver assigned', 'group' => 'delivery', 'status_family' => false, 'locked' => false,
                'desc' => 'A rider is assigned to a delivery.', 'channels' => ['email', 'sms', 'whatsapp'], 'templates' => [], 'tags' => []],
            'delivery_request' => ['label' => 'Delivery request notices', 'group' => 'delivery', 'status_family' => false, 'locked' => false,
                'desc' => 'A customer asks for home delivery; updates on that request.', 'channels' => ['email', 'sms', 'whatsapp'], 'templates' => [], 'tags' => []],

            // Money
            'pending_payments' => ['label' => 'Pending payments reminder', 'group' => 'money', 'status_family' => false, 'locked' => false,
                'desc' => 'The pending payments reminder run.', 'channels' => ['email', 'sms', 'whatsapp'], 'templates' => [], 'tags' => []],
            'ar_reminder' => ['label' => 'Accounts receivable reminder', 'group' => 'money', 'status_family' => false, 'locked' => false,
                'desc' => 'Finance reminders for unpaid invoices.', 'channels' => ['email', 'sms', 'whatsapp'], 'templates' => [], 'tags' => []],
            'push_notification_invoice' => ['label' => 'Invoice notice', 'group' => 'money', 'status_family' => false, 'locked' => false,
                'desc' => 'An invoice is pushed to the customer.', 'channels' => ['email', 'sms', 'whatsapp'],
                'templates' => ['email' => ['table' => 'email', 'ids' => [29]], 'whatsapp' => ['table' => 'whatsapp', 'ids' => [12]]], 'tags' => ['[USERNAME]', '[SUBJECT]', '[MESSAGE]', '[URL]', '[SITE_NAME]']],
            'record_payment' => ['label' => 'Payment received', 'group' => 'money', 'status_family' => false, 'locked' => false,
                'desc' => 'A payment is recorded against a bill.', 'channels' => ['email', 'sms', 'whatsapp'], 'templates' => [], 'tags' => []],

            // Account
            'signup' => ['label' => 'Sign-up welcome', 'group' => 'account', 'status_family' => false, 'locked' => false,
                'desc' => 'A customer creates an account.', 'channels' => ['email'], 'templates' => ['email' => ['table' => 'email', 'ids' => [26]]], 'tags' => ['[USERNAME]', '[SITE_NAME]', '[URL]', '[VIRTUAL_LOCKER]']],
            'user_account' => ['label' => 'Account activated / created by staff', 'group' => 'account', 'status_family' => false, 'locked' => false,
                'desc' => 'Staff create or activate an account.', 'channels' => ['email'], 'templates' => ['email' => ['table' => 'email', 'ids' => [17, 3]]], 'tags' => ['[USERNAME]', '[NAME]', '[SITE_NAME]', '[URL]']],
            'otp' => ['label' => 'One-time codes and password reset', 'group' => 'account', 'status_family' => false, 'locked' => true,
                'desc' => 'Security codes: always sent.', 'channels' => ['email', 'sms', 'whatsapp'], 'templates' => ['email' => ['table' => 'email', 'ids' => [27, 28, 30]]],
                'tags' => ['[USERNAME]', '[SITE_NAME]', '[PASSWORD]', '[IP]', '[URL]', '[TTL]']],

            // Support
            'customer_service' => ['label' => 'Support ticket notices and CSAT', 'group' => 'support', 'status_family' => false, 'locked' => false,
                'desc' => 'Customer Care sends these; its own settings decide the wording.', 'channels' => ['email', 'sms', 'whatsapp'], 'templates' => [], 'tags' => []],
        ];
    }

    /** Sources a status_owner=comms switch takes over. */
    function cdp_commStatusFamilySources()
    {
        return array_keys(array_filter(cdp_commLegacyTriggers(), function ($t) { return !empty($t['status_family']); }));
    }

    // ------------------------------------------------------------------
    // Labels and placeholders
    // ------------------------------------------------------------------

    /** What a trigger key is called in lists, the outbox and the message log. */
    function cdp_commTriggerLabel($key)
    {
        $key = (string) $key;
        $sid = cdp_commTriggerStatusId($key);
        if ($sid > 0) {
            $name = cdp_commStatusName($sid);
            return 'Status: ' . ($name !== '' ? cdp_commStatusLabel($name) : '#' . $sid);
        }
        if ($sid === 0) {
            return 'Status: any other status';
        }
        if (strpos($key, 'legacy:') === 0) {
            $src = substr($key, 7);
            return (cdp_commLegacyTriggers()[$src]['label'] ?? ucwords(str_replace('_', ' ', $src))) . ' (old screen)';
        }
        if (function_exists('cdp_commEvents') && isset(cdp_commEvents()[$key])) {
            return cdp_commEvents()[$key]['label'];
        }
        $old = ['shipment_alert' => 'Shipment status alert', 'manual' => 'Message from our team', 'campaign' => 'Campaign', 'custom' => 'Message from our team',
            'warehouse_received' => 'Package received at warehouse', 'departure' => 'Departure announcement', 'arrival' => 'Cargo arrival notification',
            'customs' => 'Customs clearance update', 'ready_pickup' => 'Ready for pickup', 'failed_delivery' => 'Failed delivery alert',
            'delivery_schedule' => 'Delivery scheduling (closed)'];
        return $old[$key] ?? ucwords(str_replace('_', ' ', $key));
    }

    /**
     * Message-log source of a Communication Center send: comms_<key> with the
     * key made a slug (status:12 -> comms_status_12). The gate never blocks
     * a comms_* source.
     */
    function cdp_commTriggerSource($key)
    {
        $slug = trim(preg_replace('/[^a-z0-9_]+/', '_', strtolower((string) $key)), '_');
        return substr('comms_' . ($slug !== '' ? $slug : 'message'), 0, 80);
    }

    /** Placeholders filled for every message to a customer. */
    function cdp_commBasePlaceholders()
    {
        return [
            'name' => 'Customer name', 'first_name' => 'Customer first name', 'locker' => 'Locker ID', 'company' => 'Company name',
            'company_phone' => 'Company phone (the line is left out when empty)', 'pay_url' => 'Link to pay bills online',
            'currency' => 'Currency', 'date' => 'Date of the message', 'prefs_url' => 'Link to the customer\'s message preferences',
        ];
    }

    /**
     * Placeholders a trigger fills, name => description. $channel '' = all;
     * *_html placeholders are e-mail only.
     *
     * @return array<string,string>
     */
    function cdp_commTriggerPlaceholders($key, $channel = '')
    {
        $key = (string) $key;
        $base = cdp_commBasePlaceholders();
        $sid = cdp_commTriggerStatusId($key);
        $out = $base;
        if ($sid >= 0) {
            require_once __DIR__ . '/notify_studio.php';
            foreach (cdp_notifyPlaceholders() as $k => $d) {
                if ($d[1] !== 'action') {
                    $out[$k] = $d[0];
                }
            }
            $out['branch'] = 'Office the shipment belongs to';
            $kind = $sid > 0 ? cdp_commStatusKind($sid) : 'fallback';
            if ($sid > 0 && !in_array($kind, cdp_notifyDraftCodeKinds(), true)) {
                unset($out['pickup_code'], $out['pickup_code_html']);
            }
            if ($sid > 0 && !in_array($kind, cdp_notifyDraftPayKinds(), true)) {
                unset($out['amount_due']);
            }
            if ($sid === 0 || in_array($kind, ['out_for_delivery', 'ready_delivery'], true)) {
                $out['driver_name'] = 'Rider\'s name';
                $out['driver_phone'] = 'Rider\'s phone';
            }
        } else {
            $extra = [
                'claim_reminder' => ['tracking' => 'Tracking number', 'track_url' => 'Tracking link', 'days' => 'Days waiting', 'branch' => 'Office'],
                'payment_reminder' => ['balance' => 'Outstanding balance'],
                'driver_arrival' => ['tracking' => 'Tracking number', 'track_url' => 'Tracking link', 'driver_name' => 'Rider\'s name', 'driver_phone' => 'Rider\'s phone',
                    'eta' => 'Arrival estimate sentence ("Expected in about 20 minutes. ")'],
                'survey' => ['tracking' => 'Tracking number', 'track_url' => 'Tracking link', 'survey_url' => 'Link to the survey'],
                'manual' => ['tracking' => 'Tracking number (when given)', 'track_url' => 'Tracking link (when given)'],
                'campaign' => [],
            ][$key] ?? [];
            $out += $extra;
            if (strpos($key, 'legacy:') === 0) {
                return [];   // legacy texts use their own [TAGS] (cdp_commLegacyTriggers()[src]['tags'])
            }
        }
        if ($channel !== '' && $channel !== 'email') {
            foreach (array_keys($out) as $k) {
                if (substr($k, -5) === '_html') {
                    unset($out[$k]);
                }
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Templates: saved row -> drafted message -> built-in default
    // ------------------------------------------------------------------

    /**
     * The text a trigger channel sends, and what the editor opens with.
     *
     *   1. a saved cdb_comm_templates row (language, then 'en'); a saved row
     *      with active = 0 means "no message" (null is returned);
     *   2. status:N with nothing saved: the message drafted for that status
     *      name (helpers/notify_drafts.php), while the drafts switch
     *      (notify.status_drafts) is on; e-mail comes back as studio blocks,
     *      compiled HTML and a canvas design;
     *   3. an event: its built-in default (cdp_commDefaultTemplates()).
     *
     * status:* has no text of its own: each status sends its own message.
     * legacy:* texts live in the legacy tables: null.
     *
     * @return array{id:?int,source:string,language:string,subject:string,body:string,blocks:?array,design_json:?string,
     *               wa_template_name:string,wa_template_lang:string,wa_template_params:string}|null
     */
    function cdp_commTriggerTemplate($key, $channel, $lang = 'en')
    {
        $key = (string) $key;
        $lang = preg_match('/^[a-z]{2}$/', (string) $lang) ? (string) $lang : 'en';
        if (!in_array($channel, ['whatsapp', 'sms', 'email'], true) || strpos($key, 'legacy:') === 0) {
            return null;
        }
        $shape = function ($source, array $d) use ($lang) {
            return [
                'id' => isset($d['id']) ? (int) $d['id'] : null, 'source' => $source, 'language' => (string) ($d['language'] ?? $lang),
                'subject' => (string) ($d['subject'] ?? ''), 'body' => (string) ($d['body'] ?? ''),
                'blocks' => isset($d['blocks']) && is_array($d['blocks']) ? $d['blocks'] : null,
                'design_json' => isset($d['design_json']) && $d['design_json'] !== '' ? (string) $d['design_json'] : null,
                'wa_template_name' => (string) ($d['wa_template_name'] ?? ''), 'wa_template_lang' => (string) ($d['wa_template_lang'] ?? ''),
                'wa_template_params' => (string) ($d['wa_template_params'] ?? ''),
            ];
        };
        $row = null;
        if (cdp_opsTableExists('cdb_comm_templates')) {
            $row = cdp_opsOne("SELECT * FROM cdb_comm_templates WHERE event_key = :e AND channel = :c AND language = :l", [':e' => $key, ':c' => $channel, ':l' => $lang]);
            if (!$row && $lang !== 'en') {
                $row = cdp_opsOne("SELECT * FROM cdb_comm_templates WHERE event_key = :e AND channel = :c AND language = 'en'", [':e' => $key, ':c' => $channel]);
            }
        }
        if ($row) {
            if ((int) $row->active !== 1) {
                return null;
            }
            return $shape('saved', (array) $row);
        }
        $sid = cdp_commTriggerStatusId($key);
        if ($sid > 0) {
            require_once __DIR__ . '/notify_studio.php';
            $name = cdp_commStatusName($sid);
            if ($name === '') {
                return null;
            }
            if (!cdp_notifyDraftsOn()) {
                $d = cdp_commDefaultTemplates()['shipment_alert'] ?? null;
                return $d ? $shape('default', ['subject' => $d['subject'], 'body' => $channel === 'sms' ? $d['sms'] : ($channel === 'email' ? str_replace('*', '', $d['text']) : $d['text'])]) : null;
            }
            $draft = cdp_notifyDraft($name, $channel);
            if ($channel === 'email') {
                $blocks = cdp_notifyCleanBlocks($draft['blocks'] ?? []);
                if (!$blocks) {
                    return null;
                }
                return $shape('draft', ['subject' => $draft['subject'], 'body' => CDP_NOTIFY_HTML_MARK . cdp_notifyCompileBlocks($blocks),
                    'blocks' => $blocks, 'design_json' => json_encode(cdp_commBlocksToCanvas($blocks), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            }
            return trim((string) $draft['body']) === '' ? null : $shape('draft', ['subject' => '', 'body' => (string) $draft['body']]);
        }
        if ($sid === 0) {
            return null;
        }
        $defs = function_exists('cdp_commDefaultTemplates') ? cdp_commDefaultTemplates() : [];
        if (isset($defs[$key])) {
            $d = $defs[$key];
            return $shape('default', ['subject' => $d['subject'], 'body' => $channel === 'sms' ? $d['sms'] : ($channel === 'email' ? str_replace('*', '', $d['text']) : $d['text'])]);
        }
        return null;
    }

    // ------------------------------------------------------------------
    // Catalogue (the Automations page)
    // ------------------------------------------------------------------

    /**
     * Every status with its kind, channel switches and whether each channel's
     * text is predefined (drafted) or customised (saved).
     *
     * @return array<int,array{id:int,key:string,name:string,label:string,kind:string,kind_label:string,
     *               rules:array,custom:array,urgent:bool}>
     */
    function cdp_commStatusTriggers()
    {
        require_once __DIR__ . '/notify_drafts.php';
        $rules = function_exists('cdp_commRules') ? cdp_commRules() : [];
        $saved = [];
        if (cdp_opsTableExists('cdb_comm_templates')) {
            foreach (cdp_opsQ("SELECT event_key, channel, active FROM cdb_comm_templates WHERE event_key LIKE 'status:%' AND language = 'en'") as $r) {
                $saved[(string) $r->event_key][(string) $r->channel] = (int) $r->active === 1 ? 'custom' : 'off';
            }
        }
        $labels = cdp_notifyDraftKindLabels();
        $out = [];
        foreach (cdp_commStatusNames() as $id => $name) {
            $key = 'status:' . $id;
            $kind = cdp_notifyDraftKind($name);
            $r = [];
            $c = [];
            foreach (['whatsapp', 'sms', 'email'] as $ch) {
                $r[$ch] = !empty($rules[$key][$ch]) ? 1 : 0;
                $c[$ch] = $saved[$key][$ch] ?? 'predefined';
            }
            $out[] = ['id' => $id, 'key' => $key, 'name' => $name, 'label' => cdp_notifyDraftLabel($name), 'kind' => $kind,
                'kind_label' => $labels[$kind] ?? $kind, 'rules' => $r, 'custom' => $c, 'urgent' => in_array($kind, cdp_commUrgentKinds(), true)];
        }
        return $out;
    }

    /**
     * Everything that sends a message, grouped for the Automations page.
     * Each trigger: key, label, desc, engine (comms | legacy), channels it can
     * use, rules (channel => 0|1), locked, tags (placeholders or legacy [TAGS]),
     * templates (legacy locator), link (another page owns it).
     *
     * @return array<int,array{id:string,label:string,desc:string,triggers:array}>
     */
    function cdp_commTriggerCatalogue()
    {
        $rules = function_exists('cdp_commRules') ? cdp_commRules() : [];
        $chs = ['whatsapp', 'sms', 'email'];
        $ruleOf = function ($key, array $channels) use ($rules) {
            $r = [];
            foreach ($channels as $ch) {
                $r[$ch] = !empty($rules[$key][$ch]) ? 1 : 0;
            }
            return $r;
        };
        $legacyRule = function ($src, array $channels) use ($rules) {
            $r = [];
            foreach ($channels as $ch) {
                // Legacy switches are on unless an administrator switched them off.
                $r[$ch] = (isset($rules['legacy:' . $src][$ch]) && (int) $rules['legacy:' . $src][$ch] === 0) ? 0 : 1;
            }
            return $r;
        };
        $statuses = cdp_commStatusTriggers();
        $on = count(array_filter($statuses, function ($s) { return array_sum($s['rules']) > 0; }));
        $events = function_exists('cdp_commEvents') ? cdp_commEvents() : [];
        $ev = function ($key) use ($events, $ruleOf, $chs) {
            return ['key' => $key, 'label' => $events[$key]['label'] ?? $key, 'desc' => $events[$key]['help'] ?? '', 'engine' => 'comms',
                'channels' => $chs, 'rules' => $ruleOf($key, $chs), 'locked' => false, 'tags' => array_keys(cdp_commTriggerPlaceholders($key)), 'templates' => [], 'link' => ''];
        };
        $legacy = cdp_commLegacyTriggers();
        $leg = function ($src) use ($legacy, $legacyRule) {
            $t = $legacy[$src];
            return ['key' => 'legacy:' . $src, 'label' => $t['label'], 'desc' => $t['desc'], 'engine' => 'legacy', 'channels' => $t['channels'],
                'rules' => $t['locked'] ? array_fill_keys($t['channels'], 1) : $legacyRule($src, $t['channels']), 'locked' => (bool) $t['locked'],
                'tags' => $t['tags'], 'templates' => $t['templates'], 'link' => '', 'status_family' => (bool) $t['status_family']];
        };
        $inGroup = function ($g) use ($legacy, $leg) {
            $out = [];
            foreach ($legacy as $src => $t) {
                if ($t['group'] === $g) {
                    $out[] = $leg($src);
                }
            }
            return $out;
        };
        $owner = function_exists('cdp_commStatusOwner') ? cdp_commStatusOwner() : (string) cdp_opsSetting('comms', 'status_owner', 'legacy');
        return [
            ['id' => 'statuses', 'label' => 'Shipment and package statuses', 'desc' => 'One message per status, written for that status. '
                . ($owner === 'comms' ? 'Sent by the Automation Center.' : 'Sent today by the old per-screen code; switch to the Automation Center in Settings.'),
                'triggers' => array_merge([
                    ['key' => 'status:*', 'label' => 'Status messages', 'desc' => $on . ' of ' . count($statuses) . ' statuses have a channel on.', 'engine' => 'comms', 'channels' => $chs,
                        'rules' => $ruleOf('status:*', $chs), 'locked' => false, 'tags' => [], 'templates' => [], 'link' => 'comms_statuses.php', 'summary' => ['on' => $on, 'total' => count($statuses)]],
                ], $inGroup('statuses'))],
            ['id' => 'reminders', 'label' => 'Reminders', 'desc' => 'Timing is set in Settings.', 'triggers' => [$ev('claim_reminder'), $ev('payment_reminder')]],
            ['id' => 'delivery', 'label' => 'Delivery', 'desc' => '', 'triggers' => array_merge([$ev('driver_arrival')], $inGroup('delivery'))],
            ['id' => 'surveys', 'label' => 'Surveys', 'desc' => 'Questions, timing and channels are set on the Surveys page.', 'triggers' => [
                ['key' => 'survey', 'label' => 'Satisfaction survey', 'desc' => 'After delivery or pickup.', 'engine' => 'comms', 'channels' => $chs, 'rules' => $ruleOf('survey', $chs),
                    'locked' => false, 'tags' => array_keys(cdp_commTriggerPlaceholders('survey')), 'templates' => [], 'link' => 'comms_surveys.php']]],
            ['id' => 'account', 'label' => 'Account', 'desc' => '', 'triggers' => $inGroup('account')],
            ['id' => 'shipments', 'label' => 'Shipments (old screens)', 'desc' => 'Sent by the screens themselves; switch a channel off here.', 'triggers' => $inGroup('shipments')],
            ['id' => 'money', 'label' => 'Money (old screens)', 'desc' => '', 'triggers' => $inGroup('money')],
            ['id' => 'support', 'label' => 'Support', 'desc' => 'Wording is set in Customer Care.', 'triggers' => $inGroup('support')],
            ['id' => 'custom', 'label' => 'Custom rules', 'desc' => 'Your own if-this-then-that rules.', 'triggers' => [
                ['key' => 'custom_rules', 'label' => 'Custom automation rules', 'desc' => (cdp_opsTableExists('cdb_automations') ? (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_automations") : 0) . ' rules',
                    'engine' => 'custom', 'channels' => [], 'rules' => [], 'locked' => false, 'tags' => [], 'templates' => [], 'link' => 'automations.php']]],
        ];
    }

    // ------------------------------------------------------------------
    // E-mail: studio blocks -> drag-and-drop canvas design, and sanitising
    // ------------------------------------------------------------------

    /**
     * Notification Studio blocks (helpers/notify_studio.php) -> the e-mail
     * canvas design (dataJs/sl/email_builder.js: {settings, blocks:[{id,type,props}]}).
     * header/heading/text/button/divider/spacer/footer/image map one to one;
     * status_card, items and pickup_code become html blocks holding the same
     * markup the studio compiles (with {placeholders}; rows marked data-ns-opt
     * disappear when their value is empty).
     */
    function cdp_commBlocksToCanvas(array $blocks)
    {
        require_once __DIR__ . '/notify_studio.php';
        $e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
        $paras = function ($t) use ($e) {
            $out = '';
            foreach (preg_split("/\n{2,}/", str_replace("\r\n", "\n", (string) $t)) as $p) {
                if (trim($p) !== '') {
                    $out .= '<p>' . nl2br($e($p), false) . '</p>';
                }
            }
            return $out !== '' ? $out : '<p></p>';
        };
        $n = 0;
        $id = function () use (&$n) { return 'b' . substr(md5('sl' . (++$n)), 0, 8); };
        $pad = ['bg' => '', 'pt' => 8, 'pb' => 8, 'px' => 32];
        $out = [];
        foreach (cdp_notifyCleanBlocks($blocks) as $b) {
            $t = (string) ($b['text'] ?? '');
            switch ($b['type']) {
                case 'header':
                    $out[] = ['id' => $id(), 'type' => 'heading', 'props' => ['text' => $t !== '' ? $t : '{company}', 'size' => 20, 'color' => '', 'align' => 'left', 'weight' => '700'] + ['bg' => '', 'pt' => 24, 'pb' => 12, 'px' => 32]];
                    break;
                case 'heading':
                    $out[] = ['id' => $id(), 'type' => 'heading', 'props' => ['text' => $t, 'size' => 26, 'color' => '', 'align' => 'left', 'weight' => '700'] + ['bg' => '', 'pt' => 16, 'pb' => 8, 'px' => 32]];
                    break;
                case 'text':
                    $out[] = ['id' => $id(), 'type' => 'text', 'props' => ['html' => $paras($t), 'size' => 16, 'color' => '', 'align' => 'left', 'lh' => 1.6] + $pad];
                    break;
                case 'footer':
                    $out[] = ['id' => $id(), 'type' => 'footer', 'props' => ['html' => $paras($t), 'size' => 12, 'color' => '#8392A5', 'align' => 'center', 'bg' => '', 'pt' => 24, 'pb' => 24, 'px' => 32]];
                    break;
                case 'button':
                    $out[] = ['id' => $id(), 'type' => 'button', 'props' => ['label' => $t !== '' ? $t : 'Track your shipment', 'href' => cdp_notifySafeUrl($b['url'] ?? '') ?: '{track_url}',
                        'color' => '', 'textColor' => '', 'align' => 'left', 'radius' => 8, 'size' => 16, 'full' => false, 'bg' => '', 'pt' => 16, 'pb' => 16, 'px' => 32]];
                    break;
                case 'image':
                    $url = cdp_notifySafeUrl($b['url'] ?? '');
                    if ($url !== '') {
                        $out[] = ['id' => $id(), 'type' => 'image', 'props' => ['src' => $url, 'alt' => (string) ($b['alt'] ?? ''), 'width' => 100, 'align' => 'center', 'href' => '', 'radius' => 8, 'bg' => '', 'pt' => 8, 'pb' => 8, 'px' => 32]];
                    }
                    break;
                case 'divider':
                    $out[] = ['id' => $id(), 'type' => 'divider', 'props' => ['color' => '#E1E5EA', 'thickness' => 1, 'bg' => '', 'pt' => 16, 'pb' => 16, 'px' => 32]];
                    break;
                case 'spacer':
                    $out[] = ['id' => $id(), 'type' => 'spacer', 'props' => ['height' => 24, 'bg' => '']];
                    break;
                case 'status_card':
                    $line = function ($k, $v, $opt) use ($e) {
                        return '<tr' . ($opt ? ' data-ns-opt="row"' : '') . '><td style="padding:6px 0;font-size:13px;color:#77736B;width:42%">' . $e($k)
                            . '</td><td style="padding:6px 0;font-size:15px;font-weight:600;color:#1E1D1A">' . $v . '</td></tr>';
                    };
                    $code = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F7F5F0;border-radius:10px;padding:8px 16px">'
                        . $line('Tracking', '{tracking}', false) . $line('Status', '{status}', false) . $line('Location', '{location}', true)
                        . $line('Carrier tracking', '{carrier_tracking}', true) . $line('Weight', '{weight}', true) . $line('Estimated arrival', '{eta}', true) . '</table>';
                    $out[] = ['id' => $id(), 'type' => 'html', 'props' => ['code' => $code, 'slBlock' => 'status_card'] + $pad];
                    break;
                case 'items':
                    $out[] = ['id' => $id(), 'type' => 'html', 'props' => ['code' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr data-ns-opt="items"><td><div style="font-size:13px;color:#77736B;margin-bottom:4px">Items</div>{items_html}</td></tr></table>', 'slBlock' => 'items'] + $pad];
                    break;
                case 'pickup_code':
                    $out[] = ['id' => $id(), 'type' => 'html', 'props' => ['code' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr data-ns-opt="code"><td>{pickup_code_html}</td></tr></table>', 'slBlock' => 'pickup_code'] + $pad];
                    break;
            }
        }
        return ['settings' => ['bodyBg' => '#F4F2EE', 'contentBg' => '#FFFFFF', 'font' => 'Arial, Helvetica, sans-serif', 'textColor' => '#3A3935',
            'headingColor' => '#1E1D1A', 'linkColor' => '#0077B6', 'accent' => '#1E1D1A'], 'blocks' => $out];
    }

    /**
     * Make browser-built e-mail HTML safe to store and send (the PHP port of
     * email_builder.js clean()): drops script, iframe, object, embed, form
     * controls, link, meta and base; every on* attribute, contenteditable and
     * data-nlb-*; javascript:/vbscript:/data: (non-image) links; CSS
     * expression()/@import/javascript:. {placeholders} and [TAGS] survive.
     * A full document stays a full document; a fragment stays a fragment.
     */
    function cdp_commSanitizeEmailHtml($html)
    {
        $html = (string) $html;
        if (strlen($html) > 2000000) {
            $html = substr($html, 0, 2000000);
        }
        if (trim($html) === '') {
            return '';
        }
        // Placeholders inside href/src would be URL-escaped by the serialiser: park them.
        $ph = [];
        $html = preg_replace_callback('/\{([a-z_]{1,40})\}/', function ($m) use (&$ph) {
            $tok = 'SLPH' . count($ph) . 'X' . $m[1] . 'X';
            $ph[$tok] = $m[0];
            return $tok;
        }, $html);
        $isDoc = (bool) preg_match('/<html[\s>]/i', $html);
        $prev = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML('<?xml encoding="utf-8"?>' . ($isDoc ? $html : '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>'),
            LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $xp = new DOMXPath($doc);
        $drop = ['script', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form', 'input', 'button', 'textarea', 'select', 'option', 'link', 'base', 'svg', 'math', 'noscript', 'template'];
        foreach ($drop as $tag) {
            foreach (iterator_to_array($doc->getElementsByTagName($tag)) as $n) {
                $n->parentNode->removeChild($n);
            }
        }
        foreach (iterator_to_array($doc->getElementsByTagName('meta')) as $n) {
            // keep <meta charset> / viewport only
            $keep = $n->hasAttribute('charset') || in_array(strtolower($n->getAttribute('name')), ['viewport', 'x-apple-disable-message-reformatting'], true)
                || strtolower($n->getAttribute('http-equiv')) === 'content-type';
            if (!$keep) {
                $n->parentNode->removeChild($n);
            }
        }
        foreach ($xp->query('//*') as $el) {
            foreach (iterator_to_array($el->attributes) as $a) {
                $n = strtolower($a->nodeName);
                $v = strtolower(preg_replace('/[\x00-\x20]+/', '', html_entity_decode((string) $a->nodeValue, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                if (strpos($n, 'on') === 0 || $n === 'contenteditable' || strpos($n, 'data-nlb') === 0 || $n === 'srcdoc' || $n === 'formaction' || $n === 'xmlns:xlink') {
                    $el->removeAttribute($a->nodeName);
                    continue;
                }
                if (in_array($n, ['href', 'src', 'background', 'action', 'poster', 'xlink:href', 'lowsrc', 'dynsrc'], true)
                    && preg_match('/^(javascript|vbscript|data|livescript):/', $v) && !preg_match('/^data:image\/(png|jpe?g|gif|webp);/', $v)) {
                    $el->removeAttribute($a->nodeName);
                    continue;
                }
                if ($n === 'style' && preg_match('/expression\s*\(|javascript:|vbscript:|@import|behavior\s*:|-moz-binding/i', $v)) {
                    $el->removeAttribute($a->nodeName);
                }
            }
        }
        foreach (iterator_to_array($doc->getElementsByTagName('style')) as $st) {
            if (preg_match('/expression\s*\(|javascript:|vbscript:|@import|behavior\s*:|-moz-binding|<\//i', $st->textContent)) {
                $st->parentNode->removeChild($st);
            }
        }
        if ($isDoc) {
            $out = '';
            foreach ($doc->childNodes as $c) {
                if ($c->nodeType === XML_PI_NODE) {
                    continue;
                }
                $out .= $doc->saveHTML($c);
            }
            if (stripos($out, '<!DOCTYPE') === false) {
                $out = "<!DOCTYPE html>\n" . $out;
            }
        } else {
            $out = '';
            $body = $doc->getElementsByTagName('body')->item(0);
            if ($body) {
                foreach ($body->childNodes as $c) {
                    $out .= $doc->saveHTML($c);
                }
            }
        }
        return strtr($out, $ph);
    }
}
