<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************

/**
 * Communication Center — WhatsApp, SMS and e-mail automation.
 *
 * What it does
 *   - Watches the system for things customers should hear about and queues a
 *     message per channel: warehouse receipt, status changes (departure,
 *     arrival, customs, ready, out for delivery, failed delivery, handed
 *     over), unclaimed packages, unpaid balances. Detection reads the
 *     tracking history (cdb_courier_track) and new warehouse packages
 *     (cdb_customers_packages) past a watermark, so no shipment screen had to
 *     change and nothing that happened before the module was switched on is
 *     ever announced.
 *   - Every message goes through one outbox (cdb_comm_outbox) with a dedupe
 *     key, quiet hours, retries and a re-check of the customer's preferences
 *     at the moment of sending. Sending uses the app's own primitives
 *     (UltraMsg / Hubtel / mNotify / PHPMailer), or the WhatsApp Business
 *     Cloud API when that provider is chosen, so cdb_message_log keeps
 *     recording every attempt.
 *   - Templates per event and channel, customer segments, consent-based
 *     campaigns, preferences and opt-out (STOP / START keywords, signed
 *     preference links), two-way WhatsApp with an AI first responder that
 *     hands over to a person, satisfaction surveys and delivery-slot booking.
 *
 * Nothing is sent until an administrator switches an automation on.
 * Marketing (campaigns) only ever reaches customers who gave consent for that
 * channel. Transactional messages respect the per-channel opt-outs.
 *
 * Worker: cron/comms_worker.php every 5 minutes (docs/COMMUNICATIONS.md).
 */

require_once __DIR__ . '/ops_db.php';
require_once __DIR__ . '/rbac.php';

if (!defined('CDP_COMMS_SCHEMA')) {
    define('CDP_COMMS_SCHEMA', '1');
}

// ---------------------------------------------------------------------------
// Catalogue
// ---------------------------------------------------------------------------

if (!function_exists('cdp_commChannels')) {

    function cdp_commChannels()
    {
        return ['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'E-mail'];
    }

    /**
     * Automated events. 'urgent' messages ignore quiet hours. 'kind' is
     * transactional (opt-out per channel) or marketing (needs consent).
     */
    function cdp_commEvents()
    {
        return [
            'shipment_alert'    => ['label' => 'Shipment status alert', 'group' => 'Shipments', 'urgent' => false,
                'help' => 'Any status change that no more specific automation below covers.'],
            'warehouse_received' => ['label' => 'Package received at warehouse', 'group' => 'Warehouse', 'urgent' => false,
                'help' => 'A package is registered in the warehouse for a customer.'],
            'claim_reminder'    => ['label' => 'Package claiming reminder', 'group' => 'Warehouse', 'urgent' => false,
                'help' => 'A package has been ready for pickup for a number of days and nobody has collected it.'],
            'departure'         => ['label' => 'Departure announcement', 'group' => 'Shipments', 'urgent' => false,
                'help' => 'Status that means the cargo left: departed, in transit, shipped, loaded, sailed.'],
            'arrival'           => ['label' => 'Cargo arrival notification', 'group' => 'Shipments', 'urgent' => false,
                'help' => 'Status that means the cargo arrived at destination.'],
            'customs'           => ['label' => 'Customs clearance update', 'group' => 'Shipments', 'urgent' => false,
                'help' => 'Status that mentions customs, clearance or duty.'],
            'ready_pickup'      => ['label' => 'Ready for pickup', 'group' => 'Shipments', 'urgent' => false,
                'help' => 'Ready for pickup / available at the office.'],
            'payment_reminder'  => ['label' => 'Payment reminder', 'group' => 'Billing', 'urgent' => false,
                'help' => 'A balance has been outstanding for a number of days.'],
            'delivery_schedule' => ['label' => 'Delivery scheduling', 'group' => 'Delivery', 'urgent' => false,
                'help' => 'Ready for delivery: the customer gets a link to choose a delivery day and time window.'],
            'driver_arrival'    => ['label' => 'Driver arrival', 'group' => 'Delivery', 'urgent' => true,
                'help' => 'Out for delivery / on route, or the driver taps "Arriving" in the driver workspace.'],
            'failed_delivery'   => ['label' => 'Failed delivery alert', 'group' => 'Delivery', 'urgent' => true,
                'help' => 'Status that means a delivery attempt failed or was returned. Carries a re-booking link.'],
            'survey'            => ['label' => 'Satisfaction survey', 'group' => 'Feedback', 'urgent' => false,
                'help' => 'After a package is delivered or picked up (once per customer per day).'],
        ];
    }

    /** Default template text per event and channel. Placeholders in {braces}. */
    function cdp_commDefaultTemplates()
    {
        $foot = "\n\n{company}";
        return [
            'shipment_alert' => [
                'subject' => 'Update on {tracking}: {status}',
                'text' => "Hello {first_name}, your shipment *{tracking}* is now: *{status}*.\nTrack it: {track_url}" . $foot,
                'sms' => '{company}: {tracking} is now {status}. Track: {track_url}'],
            'warehouse_received' => [
                'subject' => 'We received your package {tracking}',
                'text' => "Hello {first_name}, we have received your package *{tracking}* at our warehouse.\nLocker: {locker}\nTrack it: {track_url}" . $foot,
                'sms' => '{company}: package {tracking} received at our warehouse. Locker {locker}.'],
            'claim_reminder' => [
                'subject' => 'Your package {tracking} is waiting for you',
                'text' => "Hello {first_name}, your package *{tracking}* has been ready for pickup for {days} days. Please collect it soon to avoid storage charges." . $foot,
                'sms' => '{company}: {tracking} has been ready for pickup for {days} days. Please collect it soon.'],
            'departure' => [
                'subject' => '{tracking} has departed',
                'text' => "Good news {first_name}! Your cargo *{tracking}* has departed ({status}).\nTrack it: {track_url}" . $foot,
                'sms' => '{company}: {tracking} has departed. Track: {track_url}'],
            'arrival' => [
                'subject' => '{tracking} has arrived',
                'text' => "Hello {first_name}, your cargo *{tracking}* has arrived ({status}). We will let you know when it is ready." . $foot,
                'sms' => '{company}: {tracking} has arrived ({status}).'],
            'customs' => [
                'subject' => 'Customs update for {tracking}',
                'text' => "Hello {first_name}, customs update for *{tracking}*: {status}.\nTrack it: {track_url}" . $foot,
                'sms' => '{company}: customs update for {tracking}: {status}.'],
            'ready_pickup' => [
                'subject' => '{tracking} is ready for pickup',
                'text' => "Hello {first_name}, your package *{tracking}* is ready for pickup at {branch}." . $foot,
                'sms' => '{company}: {tracking} is ready for pickup at {branch}.'],
            'payment_reminder' => [
                'subject' => 'Payment reminder: {currency} {balance} outstanding',
                'text' => "Hello {first_name}, this is a friendly reminder that {currency} {balance} is outstanding on your account. Please pay to avoid delays in releasing your packages." . $foot,
                'sms' => '{company}: {currency} {balance} is outstanding on your account. Please pay to avoid delays.'],
            'delivery_schedule' => [
                'subject' => 'Choose a delivery time for {tracking}',
                'text' => "Hello {first_name}, *{tracking}* is ready for delivery. Choose a day and time that suits you: {schedule_url}" . $foot,
                'sms' => '{company}: {tracking} is ready for delivery. Choose a time: {schedule_url}'],
            'driver_arrival' => [
                'subject' => 'Your driver is on the way',
                'text' => "Hello {first_name}, {driver_name} is on the way with *{tracking}*. {eta}Driver: {driver_phone}" . $foot,
                'sms' => '{company}: {driver_name} is on the way with {tracking}. {eta}Driver: {driver_phone}'],
            'failed_delivery' => [
                'subject' => 'We could not deliver {tracking}',
                'text' => "Hello {first_name}, we could not deliver *{tracking}* today ({status}). Choose a new time here: {schedule_url}" . $foot,
                'sms' => '{company}: delivery of {tracking} failed ({status}). Re-book: {schedule_url}'],
            'survey' => [
                'subject' => 'How did we do?',
                'text' => "Hello {first_name}, thank you for choosing {company}! How was your experience with *{tracking}*? Reply with a number from 1 (poor) to 5 (excellent), or rate us here: {survey_url}",
                'sms' => '{company}: how did we do with {tracking}? Rate us 1-5: {survey_url}'],
        ];
    }

    /** Placeholders the template editor lists. */
    function cdp_commPlaceholders()
    {
        return ['name', 'first_name', 'locker', 'tracking', 'status', 'company', 'track_url', 'branch', 'days', 'balance', 'currency',
            'schedule_url', 'survey_url', 'prefs_url', 'driver_name', 'driver_phone', 'eta', 'date'];
    }

    /**
     * Fill {placeholders}. Unknown ones become empty; values are plain text
     * (e-mail bodies are escaped by the caller that builds the HTML).
     */
    function cdp_commRender($text, array $vars)
    {
        return preg_replace_callback('/\{([a-z_]+)\}/', function ($m) use ($vars) {
            return array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : '';
        }, (string) $text);
    }
}

// ---------------------------------------------------------------------------
// Tables
// ---------------------------------------------------------------------------

if (!function_exists('cdp_commEnsureTables')) {

    function cdp_commEnsureTables()
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        if (cdp_opsSetting('comms', 'schema_version', '') === CDP_COMMS_SCHEMA) {
            return $ready = true;
        }
        $t = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';
        $ok = cdp_opsDdl([
            "CREATE TABLE IF NOT EXISTS cdb_comm_templates (
                id INT NOT NULL AUTO_INCREMENT, event_key VARCHAR(40) NOT NULL, channel VARCHAR(12) NOT NULL,
                language VARCHAR(8) NOT NULL DEFAULT 'en', subject VARCHAR(190) NULL, body TEXT NOT NULL,
                wa_template_name VARCHAR(120) NULL, wa_template_lang VARCHAR(12) NULL, wa_template_params VARCHAR(255) NULL,
                active TINYINT(1) NOT NULL DEFAULT 1, updated_by INT NULL, updated_at DATETIME NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY uq_tpl (event_key, channel, language))$t",

            "CREATE TABLE IF NOT EXISTS cdb_comm_prefs (
                user_id INT NOT NULL, whatsapp TINYINT(1) NOT NULL DEFAULT 1, sms TINYINT(1) NOT NULL DEFAULT 1, email TINYINT(1) NOT NULL DEFAULT 1,
                mkt_whatsapp TINYINT(1) NOT NULL DEFAULT 0, mkt_sms TINYINT(1) NOT NULL DEFAULT 0, mkt_email TINYINT(1) NOT NULL DEFAULT 0,
                opted_out_all TINYINT(1) NOT NULL DEFAULT 0, muted_events VARCHAR(255) NULL, language VARCHAR(8) NULL,
                consent_at DATETIME NULL, consent_source VARCHAR(40) NULL, updated_at DATETIME NOT NULL,
                PRIMARY KEY (user_id))$t",

            "CREATE TABLE IF NOT EXISTS cdb_comm_consent_log (
                id BIGINT NOT NULL AUTO_INCREMENT, user_id INT NOT NULL, field VARCHAR(30) NOT NULL, value TINYINT(1) NOT NULL,
                source VARCHAR(40) NOT NULL, actor_id INT NULL, ip VARCHAR(45) NULL, created_at DATETIME NOT NULL,
                PRIMARY KEY (id), KEY idx_user (user_id, id))$t",

            "CREATE TABLE IF NOT EXISTS cdb_comm_outbox (
                id BIGINT NOT NULL AUTO_INCREMENT, user_id INT NOT NULL DEFAULT 0, recipient_to VARCHAR(190) NULL,
                event_key VARCHAR(40) NOT NULL, channel VARCHAR(12) NOT NULL, kind VARCHAR(16) NOT NULL DEFAULT 'transactional',
                subject VARCHAR(190) NULL, body TEXT NOT NULL, template_id INT NULL, wa_params TEXT NULL,
                entity_type VARCHAR(30) NULL, entity_ref VARCHAR(80) NULL, campaign_id INT NULL,
                dedupe_key VARCHAR(190) NOT NULL, status VARCHAR(12) NOT NULL DEFAULT 'queued', attempts INT NOT NULL DEFAULT 0,
                send_after DATETIME NOT NULL, last_error VARCHAR(255) NULL, sent_at DATETIME NULL, created_by INT NULL, created_at DATETIME NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY uq_dedupe (dedupe_key), KEY idx_due (status, send_after), KEY idx_user (user_id, id),
                KEY idx_campaign (campaign_id, status), KEY idx_event (event_key, created_at))$t",

            "CREATE TABLE IF NOT EXISTS cdb_comm_segments (
                id INT NOT NULL AUTO_INCREMENT, name VARCHAR(120) NOT NULL, description VARCHAR(255) NULL, definition TEXT NOT NULL,
                last_count INT NULL, counted_at DATETIME NULL, created_by INT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
                PRIMARY KEY (id))$t",

            "CREATE TABLE IF NOT EXISTS cdb_comm_campaigns (
                id INT NOT NULL AUTO_INCREMENT, name VARCHAR(160) NOT NULL, segment_id INT NULL, channels VARCHAR(40) NOT NULL,
                subject VARCHAR(190) NULL, body TEXT NOT NULL, status VARCHAR(12) NOT NULL DEFAULT 'draft', scheduled_at DATETIME NULL,
                queued_count INT NOT NULL DEFAULT 0, excluded_count INT NOT NULL DEFAULT 0, created_by INT NULL, approved_by INT NULL,
                created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, launched_at DATETIME NULL,
                PRIMARY KEY (id), KEY idx_status (status, scheduled_at))$t",

            "CREATE TABLE IF NOT EXISTS cdb_comm_threads (
                id INT NOT NULL AUTO_INCREMENT, phone VARCHAR(32) NOT NULL, user_id INT NULL, contact_name VARCHAR(150) NULL,
                status VARCHAR(12) NOT NULL DEFAULT 'bot', assignee_id INT NULL, bot_active TINYINT(1) NOT NULL DEFAULT 1,
                unread INT NOT NULL DEFAULT 0, last_in_at DATETIME NULL, last_out_at DATETIME NULL, last_message VARCHAR(255) NULL,
                handoff_reason VARCHAR(190) NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY uq_phone (phone), KEY idx_status (status, updated_at), KEY idx_user (user_id))$t",

            "CREATE TABLE IF NOT EXISTS cdb_comm_messages (
                id BIGINT NOT NULL AUTO_INCREMENT, thread_id INT NOT NULL, direction VARCHAR(3) NOT NULL, author_type VARCHAR(10) NOT NULL,
                author_id INT NULL, body TEXT NULL, media_url VARCHAR(500) NULL, provider_id VARCHAR(120) NULL, status VARCHAR(12) NOT NULL DEFAULT 'sent',
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id), KEY idx_thread (thread_id, id), UNIQUE KEY uq_provider (provider_id))$t",

            "CREATE TABLE IF NOT EXISTS cdb_comm_surveys (
                id INT NOT NULL AUTO_INCREMENT, user_id INT NOT NULL, entity_ref VARCHAR(80) NULL, channel VARCHAR(12) NULL,
                score TINYINT NULL, comment TEXT NULL, sent_at DATETIME NOT NULL, responded_at DATETIME NULL, branch_id INT NULL,
                PRIMARY KEY (id), KEY idx_user (user_id, sent_at), KEY idx_resp (responded_at))$t",

            "CREATE TABLE IF NOT EXISTS cdb_comm_delivery_slots (
                id INT NOT NULL AUTO_INCREMENT, user_id INT NOT NULL, entity_ref VARCHAR(80) NOT NULL, reason VARCHAR(20) NOT NULL DEFAULT 'ready',
                slot_date DATE NULL, slot_window VARCHAR(20) NULL, address_note VARCHAR(255) NULL, phone VARCHAR(40) NULL,
                status VARCHAR(12) NOT NULL DEFAULT 'pending', driver_id INT NULL, created_at DATETIME NOT NULL, chosen_at DATETIME NULL, updated_at DATETIME NOT NULL,
                PRIMARY KEY (id), KEY idx_status (status, slot_date), KEY idx_ref (entity_ref))$t",

            "CREATE TABLE IF NOT EXISTS cdb_comm_reminders (
                kind VARCHAR(20) NOT NULL, ref VARCHAR(80) NOT NULL, user_id INT NOT NULL, sent_count INT NOT NULL DEFAULT 0,
                last_sent_at DATETIME NULL, PRIMARY KEY (kind, ref))$t",
        ]);
        if ($ok) {
            cdp_commSeedTemplates();
            cdp_opsSettingSet('comms', 'schema_version', CDP_COMMS_SCHEMA, 0);
        }
        return $ready = $ok;
    }

    function cdp_commSeedTemplates()
    {
        $now = cdp_opsNow();
        foreach (cdp_commDefaultTemplates() as $ev => $t) {
            foreach (['whatsapp' => $t['text'], 'sms' => $t['sms'], 'email' => $t['text']] as $ch => $body) {
                cdp_opsX("INSERT IGNORE INTO cdb_comm_templates (event_key, channel, language, subject, body, updated_at)
                          VALUES (:e, :c, 'en', :s, :b, :n)",
                    [':e' => $ev, ':c' => $ch, ':s' => $t['subject'], ':b' => $ch === 'email' ? str_replace('*', '', $body) : $body, ':n' => $now]);
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Settings and access
// ---------------------------------------------------------------------------

if (!function_exists('cdp_commSettings')) {

    function cdp_commSettingDefaults()
    {
        return [
            'enabled' => '0',                 // master switch for automations
            'rules' => '{}',                  // {"event": {"whatsapp":1,"sms":0,"email":1}}
            'status_map' => '{}',             // {"status_id": "event_key" | "none"}
            'quiet_start' => '21:00', 'quiet_end' => '07:00',
            'batch_size' => '60',
            'claim_after_days' => '3', 'claim_every_days' => '4', 'claim_max' => '3',
            'pay_after_days' => '7', 'pay_every_days' => '7', 'pay_max' => '4', 'pay_min_amount' => '1',
            'currency' => 'GHS',
            'schedule_days' => '7', 'schedule_skip_sunday' => '1',
            'wa_provider' => 'ultramsg',      // ultramsg | cloud
            'wa_cloud_phone_id' => '', 'wa_cloud_token' => '', 'wa_cloud_app_secret' => '', 'wa_cloud_verify' => '',
            'wa_cloud_version' => 'v20.0',
            'webhook_key' => '',
            'bot_enabled' => '0', 'bot_daily_cap' => '300', 'bot_instructions' => '',
            'campaign_needs_approval' => '1',
        ];
    }

    function cdp_commSettings($fresh = false)
    {
        return array_merge(cdp_commSettingDefaults(), cdp_opsSettings('comms', $fresh));
    }

    function cdp_commSetting($k)
    {
        $s = cdp_commSettings();
        return $s[$k] ?? '';
    }

    function cdp_commRules()
    {
        return cdp_opsDecode(cdp_commSetting('rules'), []);
    }

    /** Is this event switched on for this channel? */
    function cdp_commRuleOn($event, $channel)
    {
        $r = cdp_commRules();
        return !empty($r[$event][$channel]);
    }

    /** Staff who may work the communication center (rank 2+). */
    function cdp_commIsStaff($u = null)
    {
        return cdp_opsRank($u) >= 2;
    }

    /** Administrators change settings, automations and approve campaigns (rank 3+). */
    function cdp_commIsAdmin($u = null)
    {
        return cdp_opsRank($u) >= 3;
    }

    /** Fields an admin may save from the settings form. Secrets kept when posted blank. */
    function cdp_commSaveSettings(array $in)
    {
        $defaults = cdp_commSettingDefaults();
        $secret = ['wa_cloud_token', 'wa_cloud_app_secret'];
        foreach ($defaults as $k => $def) {
            if (in_array($k, ['rules', 'status_map'], true) || !array_key_exists($k, $in)) {
                continue;
            }
            $v = trim((string) $in[$k]);
            if (in_array($k, $secret, true) && $v === '') {
                continue;
            }
            if (in_array($k, ['quiet_start', 'quiet_end'], true) && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v)) {
                continue;
            }
            if (in_array($k, ['batch_size', 'claim_after_days', 'claim_every_days', 'claim_max', 'pay_after_days', 'pay_every_days', 'pay_max', 'schedule_days', 'bot_daily_cap'], true)) {
                $v = (string) max(0, min(10000, (int) $v));
            }
            if ($k === 'pay_min_amount') {
                $v = (string) max(0, (float) $v);
            }
            if ($k === 'wa_provider' && !in_array($v, ['ultramsg', 'cloud'], true)) {
                continue;
            }
            if (in_array($k, ['enabled', 'bot_enabled', 'schedule_skip_sunday', 'campaign_needs_approval'], true)) {
                $v = $v === '1' ? '1' : '0';
            }
            cdp_opsSettingSet('comms', $k, mb_substr($v, 0, 4000));
        }
        if (cdp_commSetting('webhook_key') === '') {
            cdp_opsSettingSet('comms', 'webhook_key', bin2hex(random_bytes(16)));
        }
        return true;
    }

    function cdp_commSaveRules(array $rules)
    {
        $clean = [];
        foreach (cdp_commEvents() as $ev => $meta) {
            foreach (array_keys(cdp_commChannels()) as $ch) {
                $clean[$ev][$ch] = !empty($rules[$ev][$ch]) ? 1 : 0;
            }
        }
        return cdp_opsSettingSet('comms', 'rules', cdp_opsJson($clean));
    }

    function cdp_commSaveStatusMap(array $map)
    {
        $clean = [];
        $events = cdp_commEvents();
        foreach ($map as $sid => $ev) {
            $sid = (int) $sid;
            $ev = (string) $ev;
            if ($sid > 0 && ($ev === 'none' || isset($events[$ev]))) {
                $clean[$sid] = $ev;
            }
        }
        return cdp_opsSettingSet('comms', 'status_map', cdp_opsJson($clean));
    }
}

// ---------------------------------------------------------------------------
// Status classification
// ---------------------------------------------------------------------------

if (!function_exists('cdp_commClassifyStatus')) {

    /**
     * Which automation a status belongs to. Admin overrides (status_map) win;
     * otherwise the known ids, then the status name. '' = generic alert only.
     */
    function cdp_commClassifyStatus($statusId, $name, array $map = null)
    {
        $statusId = (int) $statusId;
        $map = $map ?? cdp_opsDecode(cdp_commSetting('status_map'), []);
        if (isset($map[$statusId])) {
            return $map[$statusId] === 'none' ? 'none' : $map[$statusId];
        }
        if (in_array($statusId, [8, 15], true)) {
            return 'survey';
        }
        if ($statusId === 7) {
            return 'driver_arrival';
        }
        if (in_array($statusId, [32, 6], true)) {
            return 'ready_pickup';
        }
        if ($statusId === 21) {
            return 'none';            // cancelled: never announced automatically
        }
        $n = strtolower(str_replace(['_', '-'], ' ', (string) $name));
        $rules = [
            'failed_delivery'   => '/fail|unsuccess|undeliver|not\s*deliver|attempt|refused|returned/',
            'customs'           => '/custom|clearance|duty|duties/',
            'driver_arrival'    => '/on\s*route|out\s*for\s*delivery|en\s*route|driver/',
            'delivery_schedule' => '/ready\s*for\s*delivery|awaiting\s*delivery/',
            'ready_pickup'      => '/ready\s*for\s*pick\s*up|available\s*at|ready\s*for\s*collection/',
            'survey'            => '/delivered|picked\s*up|collected/',
            'arrival'           => '/arriv|landed|at\s*destination|discharged/',
            'departure'         => '/depart|in\s*transit|shipped|dispatched|loaded|sailed|on\s*board|airborne/',
            'warehouse_received' => '/warehouse|received/',
        ];
        foreach ($rules as $ev => $re) {
            if (preg_match($re, $n)) {
                return $ev;
            }
        }
        return '';
    }

    /** Status vocabulary for the mapping screen. */
    function cdp_commStatuses()
    {
        return cdp_opsQ("SELECT id, mod_style AS name, color FROM cdb_styles ORDER BY id");
    }
}

// ---------------------------------------------------------------------------
// Preferences and consent
// ---------------------------------------------------------------------------

if (!function_exists('cdp_commPrefs')) {

    /** Effective preferences (defaults when the customer never set any). */
    function cdp_commPrefs($userId)
    {
        $d = ['user_id' => (int) $userId, 'whatsapp' => 1, 'sms' => 1, 'email' => 1, 'mkt_whatsapp' => 0, 'mkt_sms' => 0, 'mkt_email' => 0,
            'opted_out_all' => 0, 'muted_events' => '', 'language' => 'en', 'consent_at' => null, 'consent_source' => null, 'stored' => false];
        $r = $userId > 0 ? cdp_opsOne("SELECT * FROM cdb_comm_prefs WHERE user_id = :u", [':u' => (int) $userId]) : null;
        if ($r) {
            foreach ($d as $k => $v) {
                if (isset($r->$k)) {
                    $d[$k] = is_int($v) ? (int) $r->$k : $r->$k;
                }
            }
            $d['stored'] = true;
        }
        return $d;
    }

    /**
     * May this customer receive this message? Marketing needs the channel's
     * consent; transactional needs the channel not switched off. A global
     * opt-out stops both; an event the customer muted is skipped.
     */
    function cdp_commAllowed(array $prefs, $channel, $kind = 'transactional', $event = '')
    {
        if (!empty($prefs['opted_out_all'])) {
            return false;
        }
        if ($kind === 'marketing') {
            return !empty($prefs['mkt_' . $channel]);
        }
        if (empty($prefs[$channel])) {
            return false;
        }
        if ($event !== '' && !empty($prefs['muted_events'])) {
            return !in_array($event, array_map('trim', explode(',', (string) $prefs['muted_events'])), true);
        }
        return true;
    }

    /**
     * Save preferences; every change of a consent field is written to the
     * consent log with its source (customer, staff, keyword, link).
     */
    function cdp_commSavePrefs($userId, array $in, $source, $actorId = null)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return false;
        }
        $cur = cdp_commPrefs($userId);
        $fields = ['whatsapp', 'sms', 'email', 'mkt_whatsapp', 'mkt_sms', 'mkt_email', 'opted_out_all'];
        $next = $cur;
        foreach ($fields as $f) {
            if (array_key_exists($f, $in)) {
                $next[$f] = !empty($in[$f]) && $in[$f] !== '0' ? 1 : 0;
            }
        }
        if (array_key_exists('muted_events', $in)) {
            $ev = array_intersect(array_map('trim', (array) (is_array($in['muted_events']) ? $in['muted_events'] : explode(',', (string) $in['muted_events']))), array_keys(cdp_commEvents()));
            $next['muted_events'] = implode(',', $ev);
        }
        if (isset($in['language']) && preg_match('/^[a-z]{2}$/', (string) $in['language'])) {
            $next['language'] = (string) $in['language'];
        }
        $now = cdp_opsNow();
        $consentGiven = false;
        foreach ($fields as $f) {
            if ((int) $cur[$f] !== (int) $next[$f]) {
                cdp_opsX("INSERT INTO cdb_comm_consent_log (user_id, field, value, source, actor_id, ip, created_at) VALUES (:u, :f, :v, :s, :a, :ip, :n)",
                    [':u' => $userId, ':f' => $f, ':v' => (int) $next[$f], ':s' => substr($source, 0, 40), ':a' => $actorId === null ? cdp_opsUid() : (int) $actorId,
                        ':ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), ':n' => $now]);
                if (strpos($f, 'mkt_') === 0 && (int) $next[$f] === 1) {
                    $consentGiven = true;
                }
            }
        }
        $r = cdp_opsX("INSERT INTO cdb_comm_prefs (user_id, whatsapp, sms, email, mkt_whatsapp, mkt_sms, mkt_email, opted_out_all, muted_events, language, consent_at, consent_source, updated_at)
                       VALUES (:u, :w, :s, :e, :mw, :ms, :me, :o, :mu, :l, :ca, :cs, :n)
                       ON DUPLICATE KEY UPDATE whatsapp = VALUES(whatsapp), sms = VALUES(sms), email = VALUES(email), mkt_whatsapp = VALUES(mkt_whatsapp),
                         mkt_sms = VALUES(mkt_sms), mkt_email = VALUES(mkt_email), opted_out_all = VALUES(opted_out_all), muted_events = VALUES(muted_events),
                         language = VALUES(language), consent_at = COALESCE(VALUES(consent_at), consent_at), consent_source = COALESCE(VALUES(consent_source), consent_source),
                         updated_at = VALUES(updated_at)",
            [':u' => $userId, ':w' => $next['whatsapp'], ':s' => $next['sms'], ':e' => $next['email'], ':mw' => $next['mkt_whatsapp'],
                ':ms' => $next['mkt_sms'], ':me' => $next['mkt_email'], ':o' => $next['opted_out_all'], ':mu' => (string) $next['muted_events'],
                ':l' => (string) $next['language'], ':ca' => $consentGiven ? $now : null, ':cs' => $consentGiven ? substr($source, 0, 40) : null, ':n' => $now]);
        if ($r['ok'] && (int) $next['opted_out_all'] === 1) {
            cdp_opsX("UPDATE cdb_comm_outbox SET status = 'cancelled', last_error = 'Customer opted out' WHERE user_id = :u AND status = 'queued'", [':u' => $userId]);
        }
        return $r['ok'];
    }

    function cdp_commPrefsUrl($userId)
    {
        return cdp_opsSiteUrl() . '/comms_preferences.php?t=' . rawurlencode(cdp_opsSign('prefs', (int) $userId, 365));
    }
}

// ---------------------------------------------------------------------------
// Templates and variables
// ---------------------------------------------------------------------------

if (!function_exists('cdp_commTemplate')) {

    function cdp_commTemplate($event, $channel, $language = 'en')
    {
        $t = cdp_opsOne("SELECT * FROM cdb_comm_templates WHERE event_key = :e AND channel = :c AND language = :l AND active = 1",
            [':e' => $event, ':c' => $channel, ':l' => $language]);
        if (!$t && $language !== 'en') {
            $t = cdp_opsOne("SELECT * FROM cdb_comm_templates WHERE event_key = :e AND channel = :c AND language = 'en' AND active = 1",
                [':e' => $event, ':c' => $channel]);
        }
        return $t;
    }

    function cdp_commTemplates()
    {
        return cdp_opsQ("SELECT * FROM cdb_comm_templates ORDER BY event_key, channel, language");
    }

    function cdp_commSaveTemplate(array $d)
    {
        $event = (string) ($d['event_key'] ?? '');
        $channel = (string) ($d['channel'] ?? '');
        $lang = preg_match('/^[a-z]{2}$/', (string) ($d['language'] ?? 'en')) ? (string) $d['language'] : 'en';
        if (!isset(cdp_commEvents()[$event]) && $event !== 'campaign') {
            return ['ok' => false, 'error' => 'Unknown event.'];
        }
        if (!isset(cdp_commChannels()[$channel])) {
            return ['ok' => false, 'error' => 'Unknown channel.'];
        }
        $body = trim((string) ($d['body'] ?? ''));
        if ($body === '') {
            return ['ok' => false, 'error' => 'The message cannot be empty.'];
        }
        if ($channel === 'sms' && mb_strlen($body) > 640) {
            return ['ok' => false, 'error' => 'SMS templates are limited to 640 characters (4 parts).'];
        }
        $params = preg_replace('/[^a-z_,]/', '', strtolower((string) ($d['wa_template_params'] ?? '')));
        $r = cdp_opsX("INSERT INTO cdb_comm_templates (event_key, channel, language, subject, body, wa_template_name, wa_template_lang, wa_template_params, active, updated_by, updated_at)
                       VALUES (:e, :c, :l, :s, :b, :wn, :wl, :wp, :a, :u, :n)
                       ON DUPLICATE KEY UPDATE subject = VALUES(subject), body = VALUES(body), wa_template_name = VALUES(wa_template_name),
                         wa_template_lang = VALUES(wa_template_lang), wa_template_params = VALUES(wa_template_params), active = VALUES(active),
                         updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)",
            [':e' => $event, ':c' => $channel, ':l' => $lang, ':s' => mb_substr(trim((string) ($d['subject'] ?? '')), 0, 190), ':b' => $body,
                ':wn' => preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($d['wa_template_name'] ?? ''))) ?: null,
                ':wl' => preg_replace('/[^A-Za-z_]/', '', (string) ($d['wa_template_lang'] ?? '')) ?: null, ':wp' => $params ?: null,
                ':a' => !isset($d['active']) || !empty($d['active']) ? 1 : 0, ':u' => cdp_opsUid(), ':n' => cdp_opsNow()]);
        return ['ok' => $r['ok'], 'error' => $r['error']];
    }

    function cdp_commUser($userId)
    {
        return $userId > 0 ? cdp_opsOne("SELECT * FROM cdb_users WHERE id = :id", [':id' => (int) $userId]) : null;
    }

    /** Variables shared by every message to a customer. */
    function cdp_commBaseVars($u)
    {
        $company = '';
        if (function_exists('cdp_getSettingsCourier')) {
            $s = cdp_getSettingsCourier();
            $company = (string) ($s->site_name ?? '');
        }
        $first = trim((string) ($u->fname ?? ''));
        $name = trim($first . ' ' . (string) ($u->lname ?? ''));
        return [
            'name' => $name !== '' ? $name : 'Customer', 'first_name' => $first !== '' ? $first : 'Customer',
            'locker' => (string) ($u->locker ?? ''), 'company' => $company !== '' ? $company : 'Our Company',
            'currency' => cdp_commSetting('currency'), 'date' => date('Y-m-d'),
            'prefs_url' => !empty($u->id) ? cdp_commPrefsUrl((int) $u->id) : '',
        ];
    }

    function cdp_commTrackUrl($tracking)
    {
        return cdp_opsSiteUrl() . '/track.php?order_track=' . rawurlencode((string) $tracking);
    }
}

// ---------------------------------------------------------------------------
// Queueing
// ---------------------------------------------------------------------------

if (!function_exists('cdp_commQuietUntil')) {

    /** When a non-urgent message may go out: now, or the end of quiet hours. */
    function cdp_commQuietUntil($now = null, $start = null, $end = null)
    {
        $now = $now ?? time();
        $start = $start ?? cdp_commSetting('quiet_start');
        $end = $end ?? cdp_commSetting('quiet_end');
        if (!preg_match('/^\d{2}:\d{2}$/', (string) $start) || !preg_match('/^\d{2}:\d{2}$/', (string) $end) || $start === $end) {
            return $now;
        }
        $hm = date('H:i', $now);
        $inQuiet = $start < $end ? ($hm >= $start && $hm < $end) : ($hm >= $start || $hm < $end);
        if (!$inQuiet) {
            return $now;
        }
        $endTs = strtotime(date('Y-m-d', $now) . ' ' . $end . ':00');
        if ($endTs <= $now) {
            $endTs = strtotime('+1 day', $endTs);
        }
        return $endTs;
    }

    /**
     * Queue one event for one customer on every channel switched on for it.
     * $vars: placeholder values; $o: entity_ref, dedupe (base key), channels
     * (override the rule, for manual sends), kind, send_after.
     * Returns ['queued' => n, 'skipped' => [channel => reason]].
     */
    function cdp_commQueueEvent($event, $userId, array $vars, array $o = [])
    {
        $out = ['queued' => 0, 'skipped' => []];
        if (!cdp_commEnsureTables()) {
            return $out;
        }
        $u = cdp_commUser((int) $userId);
        if (!$u || (isset($u->active) && (int) $u->active !== 1)) {
            $out['skipped']['all'] = 'Unknown or inactive customer';
            return $out;
        }
        $prefs = cdp_commPrefs((int) $u->id);
        $kind = $o['kind'] ?? 'transactional';
        $channels = $o['channels'] ?? array_keys(array_filter(cdp_commRules()[$event] ?? []));
        $events = cdp_commEvents();
        $urgent = !empty($events[$event]['urgent']);
        $sendAfter = $o['send_after'] ?? ($urgent ? time() : cdp_commQuietUntil());
        $vars = array_merge(cdp_commBaseVars($u), $vars);
        foreach ($channels as $ch) {
            if (!isset(cdp_commChannels()[$ch])) {
                continue;
            }
            if (!cdp_commAllowed($prefs, $ch, $kind, $event)) {
                $out['skipped'][$ch] = 'Customer preferences';
                continue;
            }
            $to = $ch === 'email' ? trim((string) $u->email) : trim((string) $u->phone);
            if ($to === '' || ($ch === 'email' && !filter_var($to, FILTER_VALIDATE_EMAIL))) {
                $out['skipped'][$ch] = 'No ' . ($ch === 'email' ? 'e-mail address' : 'phone number');
                continue;
            }
            $tpl = isset($o['body']) ? null : cdp_commTemplate($event, $ch, (string) ($prefs['language'] ?: 'en'));
            $body = isset($o['body']) ? (string) $o['body'] : ($tpl ? (string) $tpl->body : '');
            if (trim($body) === '') {
                $out['skipped'][$ch] = 'No active template';
                continue;
            }
            $subject = cdp_commRender(isset($o['subject']) ? (string) $o['subject'] : ($tpl ? (string) $tpl->subject : ''), $vars);
            $text = cdp_commRender($body, $vars);
            if ($kind === 'marketing') {
                $text .= $ch === 'email' ? "\n\nUnsubscribe or change what you receive: " . $vars['prefs_url'] : "\n\nReply STOP to opt out.";
            }
            $waParams = null;
            if ($tpl && $ch === 'whatsapp' && !empty($tpl->wa_template_name)) {
                $waParams = cdp_opsJson(['name' => $tpl->wa_template_name, 'lang' => $tpl->wa_template_lang ?: 'en',
                    'params' => array_map(function ($p) use ($vars) { return (string) ($vars[trim($p)] ?? ''); }, array_filter(explode(',', (string) $tpl->wa_template_params)))]);
            }
            $dedupe = substr(($o['dedupe'] ?? ($event . ':' . ($o['entity_ref'] ?? '') . ':' . date('YmdHis') . ':' . mt_rand())) . ':' . $ch, 0, 190);
            $r = cdp_opsX("INSERT IGNORE INTO cdb_comm_outbox (user_id, recipient_to, event_key, channel, kind, subject, body, template_id, wa_params, entity_type, entity_ref,
                             campaign_id, dedupe_key, status, send_after, created_by, created_at)
                           VALUES (:u, :to, :e, :c, :k, :s, :b, :t, :wp, :et, :er, :cid, :d, 'queued', :sa, :by, :n)",
                [':u' => (int) $u->id, ':to' => mb_substr($to, 0, 190), ':e' => $event, ':c' => $ch, ':k' => $kind, ':s' => mb_substr($subject, 0, 190),
                    ':b' => $text, ':t' => $tpl ? (int) $tpl->id : null, ':wp' => $waParams, ':et' => $o['entity_type'] ?? null,
                    ':er' => isset($o['entity_ref']) ? mb_substr((string) $o['entity_ref'], 0, 80) : null, ':cid' => $o['campaign_id'] ?? null,
                    ':d' => $dedupe, ':sa' => date('Y-m-d H:i:s', (int) $sendAfter), ':by' => cdp_opsUid() ?: null, ':n' => cdp_opsNow()]);
            if ($r['ok'] && $r['rows'] > 0) {
                $out['queued']++;
            } else {
                $out['skipped'][$ch] = $r['ok'] ? 'Already queued' : 'Database error';
            }
        }
        return $out;
    }
}

// ---------------------------------------------------------------------------
// Sending
// ---------------------------------------------------------------------------

if (!function_exists('cdp_commSendNow')) {

    function cdp_commLoadSenders()
    {
        require_once __DIR__ . '/querys.php';
        require_once __DIR__ . '/message_log.php';
        require_once __DIR__ . '/whatsapp.php';
        require_once __DIR__ . '/hubtel_sms.php';
        require_once dirname(__DIR__) . '/ajax/notify_whatsapp/api_whatsapp_service_v2.php';
    }

    /** WhatsApp Business Cloud API (Meta). Text, or an approved template when given. */
    function cdp_commWaCloudSend($to, $text, array $template = null, array $s = null)
    {
        $s = $s ?? cdp_commSettings();
        $phoneId = trim((string) $s['wa_cloud_phone_id']);
        $token = trim((string) $s['wa_cloud_token']);
        if ($phoneId === '' || $token === '') {
            return ['success' => false, 'message' => 'WhatsApp Cloud API is not configured.'];
        }
        $to = preg_replace('/\D+/', '', (string) $to);
        if ($template && !empty($template['name'])) {
            $payload = ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template', 'template' => [
                'name' => $template['name'], 'language' => ['code' => $template['lang'] ?? 'en'],
                'components' => [['type' => 'body', 'parameters' => array_map(function ($p) {
                    return ['type' => 'text', 'text' => mb_substr((string) $p, 0, 1000)];
                }, (array) ($template['params'] ?? []))]]]];
            if (empty($template['params'])) {
                unset($payload['template']['components']);
            }
        } else {
            $payload = ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'text', 'text' => ['preview_url' => true, 'body' => mb_substr((string) $text, 0, 4096)]];
        }
        $url = 'https://graph.facebook.com/' . preg_replace('/[^a-z0-9.]/i', '', $s['wa_cloud_version'] ?: 'v20.0') . '/' . rawurlencode($phoneId) . '/messages';
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($payload)]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $j = json_decode((string) $resp, true);
        $ok = $code >= 200 && $code < 300 && !empty($j['messages'][0]['id']);
        $msg = $ok ? 'Sent' : ($j['error']['message'] ?? ($err !== '' ? $err : 'HTTP ' . $code));
        if (function_exists('cdp_msgLog')) {
            cdp_msgLog(['channel' => 'whatsapp', 'status' => $ok ? 'sent' : 'failed', 'status_detail' => $ok ? 'WhatsApp Cloud API' : mb_substr($msg, 0, 250),
                'recipient_to' => $to, 'body' => (string) $text, 'provider_response' => $resp]);
        }
        return ['success' => $ok, 'message' => $msg, 'provider_id' => $ok ? $j['messages'][0]['id'] : null];
    }

    /** WhatsApp to a user row (or a bare phone in ->phone) through the chosen provider. */
    function cdp_commSendWhatsApp($u, $text, array $template = null)
    {
        cdp_commLoadSenders();
        if (cdp_commSetting('wa_provider') === 'cloud') {
            $to = function_exists('cdp_normalizePhone') ? cdp_normalizePhone((string) $u->phone, $u->country ?? null) : (string) $u->phone;
            // Outside the 24-hour customer-service window Meta only accepts an approved template.
            $open = cdp_commWaWindowOpen($to);
            return cdp_commWaCloudSend($to, $text, $open ? null : $template);
        }
        $r = sendNotificationWhatsApp_v2($u, $text, null, ['allow_sms' => false]);
        return ['success' => !empty($r['success']), 'message' => (string) ($r['message'] ?? '')];
    }

    /** Has this number written to us in the last 24 hours? */
    function cdp_commWaWindowOpen($phone)
    {
        $phone = preg_replace('/\D+/', '', (string) $phone);
        return $phone !== '' && (bool) cdp_opsOne("SELECT id FROM cdb_comm_threads WHERE phone = :p AND last_in_at >= :c", [':p' => $phone, ':c' => cdp_opsNow(-86400)]);
    }

    function cdp_commSendEmail($u, $subject, $text)
    {
        cdp_commLoadSenders();
        require_once __DIR__ . '/phpmailer/class.phpmailer.php';
        require_once __DIR__ . '/phpmailer/class.smtp.php';
        $settings = cdp_getSettingsCourier();
        $to = trim((string) $u->email);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'No valid e-mail address.'];
        }
        $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#1f2937">'
            . preg_replace('~(https?://[^\s<]+)~', '<a href="$1">$1</a>', nl2br(htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8')))
            . '</div>';
        try {
            if (($settings->mailer ?? '') === 'PHP') {
                $from = (string) $settings->email_address;
                $ok = @mail($to, $subject, $html, "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: {$from}\r\n");
                cdp_msgLogPhpMail($ok, $to, $subject, $html);
                return ['success' => (bool) $ok, 'message' => $ok ? 'Sent' : 'PHP mail() returned false'];
            }
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $settings->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $settings->smtp_user;
            $mail->Password = $settings->smtp_password;
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            $mail->Timeout = 15;
            $mail->setFrom($settings->email_address, $settings->smtp_names);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = '<html><body>' . $html . '</body></html>';
            $mail->AltBody = (string) $text;
            $mail->send();
            return ['success' => true, 'message' => 'Sent'];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => mb_substr($e->getMessage(), 0, 250)];
        }
    }

    function cdp_commSendSms($u, $text)
    {
        cdp_commLoadSenders();
        if (!function_exists('cdp_smsReady') || !cdp_smsReady()) {
            return ['success' => false, 'message' => 'SMS is not configured (Tools > SMS).'];
        }
        $r = cdp_smsToRecipient($u, $text);
        return ['success' => !empty($r['success']), 'message' => (string) ($r['message'] ?? '')];
    }

    /**
     * Deliver one outbox row. Claimed with a conditional update first so two
     * workers can never send the same row. Preferences are checked again here:
     * an opt-out between queueing and sending wins.
     */
    function cdp_commDeliver($row)
    {
        $claim = cdp_opsX("UPDATE cdb_comm_outbox SET status = 'sending', attempts = attempts + 1 WHERE id = :id AND status = 'queued'", [':id' => (int) $row->id]);
        if (!$claim['ok'] || $claim['rows'] !== 1) {
            return 'claimed';
        }
        $u = (int) $row->user_id > 0 ? cdp_commUser((int) $row->user_id) : null;
        if (!$u) {
            $u = (object) ['id' => 0, 'fname' => '', 'lname' => '', 'phone' => (string) $row->recipient_to, 'email' => (string) $row->recipient_to];
        } else {
            $prefs = cdp_commPrefs((int) $u->id);
            if (!cdp_commAllowed($prefs, $row->channel, $row->kind, $row->event_key)) {
                cdp_opsX("UPDATE cdb_comm_outbox SET status = 'cancelled', last_error = 'Customer preferences changed' WHERE id = :id", [':id' => (int) $row->id]);
                return 'cancelled';
            }
            // Send to the address the customer has now, not the one at queue time.
        }
        if (function_exists('cdp_msgSetContext')) {
            cdp_msgSetContext(['source' => 'comms_' . $row->event_key, 'source_label' => 'Communication Center: ' . (cdp_commEvents()[$row->event_key]['label'] ?? $row->event_key),
                'entity_type' => (string) $row->entity_type, 'entity_label' => (string) $row->entity_ref, 'subject' => (string) $row->subject,
                'batch_id' => $row->campaign_id ? 'campaign-' . (int) $row->campaign_id : '']);
        }
        try {
            if ($row->channel === 'whatsapp') {
                $res = cdp_commSendWhatsApp($u, (string) $row->body, cdp_opsDecode($row->wa_params, []) ?: null);
            } elseif ($row->channel === 'sms') {
                $res = cdp_commSendSms($u, (string) $row->body);
            } else {
                $res = cdp_commSendEmail($u, (string) ($row->subject ?: 'Notification'), (string) $row->body);
            }
        } catch (Throwable $e) {
            $res = ['success' => false, 'message' => $e->getMessage()];
        }
        if (function_exists('cdp_msgClearContext')) {
            cdp_msgClearContext();
        }
        if (!empty($res['success'])) {
            cdp_opsX("UPDATE cdb_comm_outbox SET status = 'sent', sent_at = :n, last_error = NULL WHERE id = :id", [':n' => cdp_opsNow(), ':id' => (int) $row->id]);
            if ($row->channel === 'whatsapp' && (int) $row->user_id > 0 && !empty($u->phone)) {
                cdp_commThreadLogOutbound((string) $u->phone, (int) $row->user_id, (string) $row->body, 'system', null, $res['provider_id'] ?? null);
            }
            return 'sent';
        }
        $attempts = (int) $row->attempts + 1;
        $final = $attempts >= 4;
        $backoff = [0, 300, 1800, 7200][min(3, $attempts)];
        cdp_opsX("UPDATE cdb_comm_outbox SET status = :s, last_error = :e, send_after = :sa WHERE id = :id",
            [':s' => $final ? 'failed' : 'queued', ':e' => mb_substr((string) ($res['message'] ?? 'Not sent'), 0, 250),
                ':sa' => cdp_opsNow($backoff), ':id' => (int) $row->id]);
        return $final ? 'failed' : 'retry';
    }

    /** Deliver what is due. Returns counts per outcome. */
    function cdp_commDeliverDue($limit = null)
    {
        $limit = (int) ($limit ?? cdp_commSetting('batch_size'));
        $limit = max(1, min(500, $limit));
        // A row stuck in 'sending' (worker died mid-send) is not resent: the
        // provider may have delivered it. It is closed as failed for review.
        cdp_opsX("UPDATE cdb_comm_outbox SET status = 'failed', last_error = 'Interrupted while sending - check the message log before retrying'
                  WHERE status = 'sending' AND send_after < :c", [':c' => cdp_opsNow(-1800)]);
        $rows = cdp_opsQ("SELECT * FROM cdb_comm_outbox WHERE status = 'queued' AND send_after <= :n ORDER BY campaign_id IS NOT NULL, id LIMIT " . $limit, [':n' => cdp_opsNow()]);
        $c = ['sent' => 0, 'failed' => 0, 'retry' => 0, 'cancelled' => 0, 'claimed' => 0];
        foreach ($rows as $r) {
            $res = cdp_commDeliver($r);
            $c[$res] = ($c[$res] ?? 0) + 1;
        }
        return $c;
    }
}

// ---------------------------------------------------------------------------
// Detection (worker)
// ---------------------------------------------------------------------------

if (!function_exists('cdp_commResolveTrack')) {

    /**
     * Owner(s) of a tracking reference: a shipment, a warehouse package or a
     * consolidation (whose owners are the customers of the packages inside it).
     * @return array{type:string, owners:int[], driver_id:int, branch:string}
     */
    function cdp_commResolveTrack($track)
    {
        $track = (string) $track;
        $o = cdp_opsOne("SELECT o.order_id, o.sender_id, o.driver_id, f.name_off FROM cdb_add_order o LEFT JOIN cdb_offices f ON f.id = o.origin_off
                         WHERE CONCAT(COALESCE(o.order_prefix,''), o.order_no) = :t LIMIT 1", [':t' => $track]);
        if ($o) {
            return ['type' => 'shipment', 'owners' => [(int) $o->sender_id], 'driver_id' => (int) $o->driver_id, 'branch' => (string) $o->name_off];
        }
        $p = cdp_opsOne("SELECT order_id, sender_id FROM cdb_customers_packages WHERE CONCAT(COALESCE(order_prefix,''), order_no) = :t LIMIT 1", [':t' => $track]);
        if ($p) {
            return ['type' => 'package', 'owners' => [(int) $p->sender_id], 'driver_id' => 0, 'branch' => ''];
        }
        foreach (['cdb_consolidate' => 'consolidate', 'cdb_consolidate_packages' => 'consolidate_packages'] as $table => $module) {
            if (!cdp_opsTableExists($table)) {
                continue;
            }
            $c = cdp_opsOne("SELECT consolidate_id FROM $table WHERE CONCAT(COALESCE(c_prefix,''), c_no) = :t LIMIT 1", [':t' => $track]);
            if ($c) {
                require_once __DIR__ . '/push_notify.php';
                return ['type' => 'consolidation', 'owners' => cdp_pushConsolidationOwnerIds((int) $c->consolidate_id, $module), 'driver_id' => 0, 'branch' => ''];
            }
        }
        return ['type' => '', 'owners' => [], 'driver_id' => 0, 'branch' => ''];
    }

    /**
     * New tracking-history rows -> events. The first run only sets the
     * watermark: history before the module was switched on is never announced.
     */
    function cdp_commScanStatusHistory($max = 300)
    {
        $n = 0;
        $last = (int) cdp_opsSetting('comms', 'wm_track', '-1');
        if ($last < 0) {
            cdp_opsSettingSet('comms', 'wm_track', (string) (int) cdp_opsVal("SELECT COALESCE(MAX(id),0) FROM cdb_courier_track"), 0);
            return 0;
        }
        $rows = cdp_opsQ("SELECT t.id, t.order_track, t.status_courier, t.t_date, s.mod_style FROM cdb_courier_track t
                          LEFT JOIN cdb_styles s ON s.id = t.status_courier WHERE t.id > :l ORDER BY t.id LIMIT " . (int) $max, [':l' => $last]);
        $map = cdp_opsDecode(cdp_commSetting('status_map'), []);
        $rules = cdp_commRules();
        foreach ($rows as $r) {
            $event = cdp_commClassifyStatus((int) $r->status_courier, (string) $r->mod_style, $map);
            if ($event !== 'none') {
                $info = cdp_commResolveTrack($r->order_track);
                $status = ucwords(str_replace('_', ' ', (string) $r->mod_style));
                foreach (array_unique($info['owners']) as $owner) {
                    if ($owner <= 0) {
                        continue;
                    }
                    $n += cdp_commEmitStatusEvent($event, $owner, $r, $status, $info, $rules);
                }
            }
            cdp_opsSettingSet('comms', 'wm_track', (string) (int) $r->id, 0);
        }
        return $n;
    }

    /** Specific automation first; the generic alert covers channels it left out. */
    function cdp_commEmitStatusEvent($event, $owner, $r, $status, array $info, array $rules)
    {
        $vars = ['tracking' => (string) $r->order_track, 'status' => $status, 'track_url' => cdp_commTrackUrl($r->order_track), 'branch' => $info['branch'] ?: 'our office'];
        $base = 'trk' . (int) $r->id . ':' . $owner;
        $specific = $event !== '' ? array_keys(array_filter($rules[$event] ?? [])) : [];
        // Channels the specific automation owns never get the generic alert,
        // even when the specific message is suppressed (one survey a day).
        $owned = $specific;
        $n = 0;
        if ($specific) {
            if ($event === 'delivery_schedule' || $event === 'failed_delivery') {
                $vars['schedule_url'] = cdp_commCreateSlotLink($owner, (string) $r->order_track, $event === 'failed_delivery' ? 'failed' : 'ready');
            }
            if ($event === 'driver_arrival') {
                $vars = array_merge($vars, cdp_commDriverVars($info['driver_id']));
            }
            if ($event === 'survey') {
                if (cdp_opsOne("SELECT id FROM cdb_comm_surveys WHERE user_id = :u AND sent_at >= :d", [':u' => $owner, ':d' => date('Y-m-d 00:00:00')])) {
                    $specific = [];
                } else {
                    $vars['survey_url'] = cdp_commCreateSurveyLink($owner, (string) $r->order_track, $specific[0]);
                }
            }
            if ($specific) {
                $n += cdp_commQueueEvent($event, $owner, $vars, ['entity_type' => $info['type'], 'entity_ref' => $r->order_track, 'dedupe' => $base . ':' . $event, 'channels' => $specific])['queued'];
            }
        }
        $generic = array_diff(array_keys(array_filter($rules['shipment_alert'] ?? [])), $owned);
        if ($generic) {
            $n += cdp_commQueueEvent('shipment_alert', $owner, $vars, ['entity_type' => $info['type'], 'entity_ref' => $r->order_track, 'dedupe' => $base . ':alert', 'channels' => array_values($generic)])['queued'];
        }
        return $n;
    }

    function cdp_commDriverVars($driverId, $etaMinutes = null)
    {
        $d = $driverId > 0 ? cdp_commUser((int) $driverId) : null;
        return ['driver_name' => $d ? trim($d->fname . ' ' . $d->lname) : 'Our driver', 'driver_phone' => $d ? (string) $d->phone : '',
            'eta' => $etaMinutes ? 'Expected in about ' . (int) $etaMinutes . ' minutes. ' : ''];
    }

    /** New warehouse packages -> "received" for their owners. */
    function cdp_commScanWarehouse($max = 300)
    {
        $last = (int) cdp_opsSetting('comms', 'wm_package', '-1');
        if ($last < 0) {
            cdp_opsSettingSet('comms', 'wm_package', (string) (int) cdp_opsVal("SELECT COALESCE(MAX(order_id),0) FROM cdb_customers_packages"), 0);
            return 0;
        }
        $n = 0;
        $rows = cdp_opsQ("SELECT order_id, order_prefix, order_no, sender_id FROM cdb_customers_packages WHERE order_id > :l ORDER BY order_id LIMIT " . (int) $max, [':l' => $last]);
        foreach ($rows as $r) {
            if ((int) $r->sender_id > 0 && cdp_commEventOn('warehouse_received')) {
                $track = $r->order_prefix . $r->order_no;
                $n += cdp_commQueueEvent('warehouse_received', (int) $r->sender_id, ['tracking' => $track, 'track_url' => cdp_commTrackUrl($track)],
                    ['entity_type' => 'package', 'entity_ref' => $track, 'dedupe' => 'wh' . (int) $r->order_id])['queued'];
            }
            cdp_opsSettingSet('comms', 'wm_package', (string) (int) $r->order_id, 0);
        }
        return $n;
    }

    function cdp_commEventOn($event)
    {
        return (bool) array_filter(cdp_commRules()[$event] ?? []);
    }

    /** May a reminder of this kind go out again for this reference? */
    function cdp_commReminderDue($kind, $ref, $everyDays, $maxCount, $now = null)
    {
        $now = $now ?? time();
        $r = cdp_opsOne("SELECT sent_count, last_sent_at FROM cdb_comm_reminders WHERE kind = :k AND ref = :r", [':k' => $kind, ':r' => $ref]);
        return cdp_commReminderRule($r ? (int) $r->sent_count : 0, $r ? $r->last_sent_at : null, $everyDays, $maxCount, $now);
    }

    /** Pure rule, unit tested. */
    function cdp_commReminderRule($sent, $lastAt, $everyDays, $maxCount, $now)
    {
        if ($maxCount > 0 && $sent >= $maxCount) {
            return false;
        }
        return $lastAt === null || strtotime((string) $lastAt) <= $now - max(1, (int) $everyDays) * 86400;
    }

    function cdp_commReminderMark($kind, $ref, $userId)
    {
        cdp_opsX("INSERT INTO cdb_comm_reminders (kind, ref, user_id, sent_count, last_sent_at) VALUES (:k, :r, :u, 1, :n)
                  ON DUPLICATE KEY UPDATE sent_count = sent_count + 1, last_sent_at = VALUES(last_sent_at), user_id = VALUES(user_id)",
            [':k' => $kind, ':r' => $ref, ':u' => (int) $userId, ':n' => cdp_opsNow()]);
    }

    /** Ready-for-pickup packages nobody collected. */
    function cdp_commScanClaimReminders()
    {
        if (!cdp_commEventOn('claim_reminder')) {
            return 0;
        }
        $after = max(1, (int) cdp_commSetting('claim_after_days'));
        $every = (int) cdp_commSetting('claim_every_days');
        $max = (int) cdp_commSetting('claim_max');
        $n = 0;
        $rows = cdp_opsQ("SELECT o.order_id, CONCAT(COALESCE(o.order_prefix,''), o.order_no) trk, o.sender_id, f.name_off,
                                 (SELECT MAX(t.t_date) FROM cdb_courier_track t WHERE t.order_track = CONCAT(COALESCE(o.order_prefix,''), o.order_no) AND t.status_courier = o.status_courier) ready_at
                          FROM cdb_add_order o LEFT JOIN cdb_offices f ON f.id = o.origin_off
                          WHERE o.status_courier IN (32, 6) AND o.sender_id > 0 LIMIT 2000");
        foreach ($rows as $r) {
            $readyTs = $r->ready_at ? strtotime((string) $r->ready_at) : 0;
            if (!$readyTs || $readyTs > time() - $after * 86400) {
                continue;
            }
            if (!cdp_commReminderDue('claim', (string) $r->order_id, $every, $max)) {
                continue;
            }
            $q = cdp_commQueueEvent('claim_reminder', (int) $r->sender_id, ['tracking' => $r->trk, 'days' => (int) floor((time() - $readyTs) / 86400),
                'branch' => $r->name_off ?: 'our office', 'track_url' => cdp_commTrackUrl($r->trk)],
                ['entity_type' => 'shipment', 'entity_ref' => $r->trk, 'dedupe' => 'claim' . (int) $r->order_id . ':' . date('Ymd')]);
            if ($q['queued'] > 0) {
                cdp_commReminderMark('claim', (string) $r->order_id, (int) $r->sender_id);
                $n += $q['queued'];
            }
        }
        return $n;
    }

    /** Outstanding balances from the financial ledger, one reminder per customer. */
    function cdp_commScanPaymentReminders()
    {
        if (!cdp_commEventOn('payment_reminder') || !cdp_opsTableExists('cdb_consolidate_customer_billing')) {
            return 0;
        }
        $after = max(1, (int) cdp_commSetting('pay_after_days'));
        $min = (float) cdp_commSetting('pay_min_amount');
        $n = 0;
        $rows = cdp_opsQ("SELECT sender_id, SUM(GREATEST(0, COALESCE(amount_ghs,0)-COALESCE(discount_ghs,0)-COALESCE(paid_ghs,0))) bal, MIN(billed_at) oldest
                          FROM cdb_consolidate_customer_billing WHERE sender_id > 0
                          GROUP BY sender_id HAVING bal >= :m AND oldest <= :c LIMIT 5000", [':m' => max(0.01, $min), ':c' => cdp_opsNow(-$after * 86400)]);
        foreach ($rows as $r) {
            if (!cdp_commReminderDue('payment', (string) $r->sender_id, (int) cdp_commSetting('pay_every_days'), (int) cdp_commSetting('pay_max'))) {
                continue;
            }
            $q = cdp_commQueueEvent('payment_reminder', (int) $r->sender_id, ['balance' => number_format((float) $r->bal, 2)],
                ['entity_type' => 'balance', 'entity_ref' => 'customer-' . (int) $r->sender_id, 'dedupe' => 'pay' . (int) $r->sender_id . ':' . date('Ymd')]);
            if ($q['queued'] > 0) {
                cdp_commReminderMark('payment', (string) $r->sender_id, (int) $r->sender_id);
                $n += $q['queued'];
            }
        }
        return $n;
    }

    /** One worker pass. */
    function cdp_commRunWorker()
    {
        $out = ['ok' => false, 'detected' => 0, 'delivered' => []];
        if (!cdp_commEnsureTables()) {
            return $out;
        }
        $out['ok'] = true;
        if (cdp_commSetting('enabled') === '1') {
            $out['detected'] += cdp_commScanStatusHistory();
            $out['detected'] += cdp_commScanWarehouse();
            if (cdp_opsSetting('comms', 'reminders_day', '') !== date('Y-m-d') && (int) date('G') >= 9) {
                $out['detected'] += cdp_commScanClaimReminders();
                $out['detected'] += cdp_commScanPaymentReminders();
                cdp_opsSettingSet('comms', 'reminders_day', date('Y-m-d'), 0);
            }
            cdp_commLaunchDueCampaigns();
        }
        // Delivery runs even with automations off: manual sends and campaigns use the outbox too.
        $out['delivered'] = cdp_commDeliverDue();
        cdp_opsSettingSet('comms', 'worker_last_run', cdp_opsNow(), 0);
        return $out;
    }
}

// ---------------------------------------------------------------------------
// Delivery slots and surveys (public links)
// ---------------------------------------------------------------------------

if (!function_exists('cdp_commCreateSlotLink')) {

    function cdp_commCreateSlotLink($userId, $ref, $reason = 'ready')
    {
        $ex = cdp_opsOne("SELECT id FROM cdb_comm_delivery_slots WHERE user_id = :u AND entity_ref = :r AND status = 'pending'", [':u' => (int) $userId, ':r' => $ref]);
        $id = $ex ? (int) $ex->id : cdp_opsX("INSERT INTO cdb_comm_delivery_slots (user_id, entity_ref, reason, status, created_at, updated_at) VALUES (:u, :r, :re, 'pending', :n, :n)",
            [':u' => (int) $userId, ':r' => mb_substr($ref, 0, 80), ':re' => $reason, ':n' => cdp_opsNow()])['id'];
        return $id ? cdp_opsSiteUrl() . '/delivery_schedule.php?t=' . rawurlencode(cdp_opsSign('slot', $id, 14)) : '';
    }

    /** The bookable days: the next N days, Sundays skipped when set. */
    function cdp_commSlotDays($from = null, $days = null, $skipSunday = null)
    {
        $from = $from ?? time();
        $days = max(1, (int) ($days ?? cdp_commSetting('schedule_days')));
        $skipSunday = $skipSunday ?? (cdp_commSetting('schedule_skip_sunday') === '1');
        $out = [];
        for ($i = 1; count($out) < $days && $i < 60; $i++) {
            $ts = strtotime('+' . $i . ' day', $from);
            if ($skipSunday && date('w', $ts) === '0') {
                continue;
            }
            $out[] = date('Y-m-d', $ts);
        }
        return $out;
    }

    function cdp_commSlotWindows()
    {
        return ['morning' => 'Morning (8:00 - 12:00)', 'afternoon' => 'Afternoon (12:00 - 16:00)', 'evening' => 'Evening (16:00 - 19:00)'];
    }

    function cdp_commBookSlot($slotId, $date, $window, $note, $phone)
    {
        $slot = cdp_opsOne("SELECT * FROM cdb_comm_delivery_slots WHERE id = :id", [':id' => (int) $slotId]);
        if (!$slot || !in_array($slot->status, ['pending', 'booked'], true)) {
            return ['ok' => false, 'error' => 'This link is no longer open.'];
        }
        if (!in_array($date, cdp_commSlotDays(), true) || !isset(cdp_commSlotWindows()[$window])) {
            return ['ok' => false, 'error' => 'Please choose one of the days and times shown.'];
        }
        cdp_opsX("UPDATE cdb_comm_delivery_slots SET slot_date = :d, slot_window = :w, address_note = :a, phone = :p, status = 'booked', chosen_at = :n, updated_at = :n WHERE id = :id",
            [':d' => $date, ':w' => $window, ':a' => mb_substr(trim((string) $note), 0, 255), ':p' => mb_substr(preg_replace('/[^0-9+ ]/', '', (string) $phone), 0, 40),
                ':n' => cdp_opsNow(), ':id' => (int) $slot->id]);
        return ['ok' => true, 'slot' => $slot];
    }

    function cdp_commCreateSurveyLink($userId, $ref, $channel)
    {
        $id = cdp_opsX("INSERT INTO cdb_comm_surveys (user_id, entity_ref, channel, sent_at) VALUES (:u, :r, :c, :n)",
            [':u' => (int) $userId, ':r' => mb_substr($ref, 0, 80), ':c' => $channel, ':n' => cdp_opsNow()])['id'];
        return $id ? cdp_opsSiteUrl() . '/survey.php?t=' . rawurlencode(cdp_opsSign('survey', $id, 30)) : '';
    }

    function cdp_commSurveyAnswer($surveyId, $score, $comment)
    {
        $score = (int) $score;
        if ($score < 1 || $score > 5) {
            return false;
        }
        return cdp_opsX("UPDATE cdb_comm_surveys SET score = :s, comment = :c, responded_at = :n WHERE id = :id",
            [':s' => $score, ':c' => mb_substr(trim((string) $comment), 0, 2000), ':n' => cdp_opsNow(), ':id' => (int) $surveyId])['ok'];
    }

    function cdp_commSurveyStats($from, $to)
    {
        $r = cdp_opsOne("SELECT COUNT(*) sent, SUM(responded_at IS NOT NULL) answered, AVG(score) avg_score, SUM(score >= 4) happy, SUM(score <= 2) unhappy
                         FROM cdb_comm_surveys WHERE sent_at BETWEEN :f AND :t", [':f' => $from . ' 00:00:00', ':t' => $to . ' 23:59:59']);
        $answered = (int) ($r->answered ?? 0);
        return ['sent' => (int) ($r->sent ?? 0), 'answered' => $answered, 'avg' => $r && $r->avg_score !== null ? round((float) $r->avg_score, 2) : null,
            'csat' => $answered ? round(100 * (int) $r->happy / $answered, 1) : null, 'unhappy' => (int) ($r->unhappy ?? 0)];
    }
}

// ---------------------------------------------------------------------------
// Segments and campaigns
// ---------------------------------------------------------------------------

if (!function_exists('cdp_commSegmentFields')) {

    function cdp_commSegmentFields()
    {
        return [
            'country' => ['label' => 'Country', 'type' => 'text'],
            'city' => ['label' => 'City', 'type' => 'text'],
            'joined_days' => ['label' => 'Joined within (days)', 'type' => 'number'],
            'last_shipment_days' => ['label' => 'Days since last shipment', 'type' => 'number'],
            'shipments_90d' => ['label' => 'Shipments in last 90 days', 'type' => 'number'],
            'balance' => ['label' => 'Outstanding balance', 'type' => 'number'],
            'in_warehouse' => ['label' => 'Has packages waiting', 'type' => 'bool'],
            'consent' => ['label' => 'Marketing consent on', 'type' => 'channel'],
        ];
    }

    /**
     * WHERE clause for a segment over cdb_users u. Fields are a whitelist;
     * values are bound. Customers only (is_client role), active only.
     * Pure apart from the role ids: unit tested.
     *
     * @return array{0:string,1:array}
     */
    function cdp_commSegmentSql(array $def, array $clientRoles = [1])
    {
        $roles = implode(',', array_map('intval', $clientRoles ?: [1]));
        $parts = [];
        $b = [];
        $i = 0;
        $ops = ['eq' => '=', 'ne' => '<>', 'gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<='];
        $fields = cdp_commSegmentFields();
        foreach ((array) ($def['rules'] ?? []) as $rule) {
            $f = (string) ($rule['field'] ?? '');
            $op = (string) ($rule['op'] ?? 'eq');
            $v = $rule['value'] ?? '';
            if (!isset($fields[$f])) {
                continue;
            }
            $p = ':v' . (++$i);
            $sqlOp = $ops[$op] ?? '=';
            switch ($f) {
                case 'country':
                case 'city':
                    if ($op === 'contains') {
                        $parts[] = "u.$f LIKE $p";
                        $b[$p] = '%' . $v . '%';
                    } else {
                        $parts[] = "u.$f " . ($op === 'ne' ? '<>' : '=') . " $p";
                        $b[$p] = (string) $v;
                    }
                    break;
                case 'joined_days':
                    $parts[] = "u.created >= DATE_SUB(NOW(), INTERVAL $p DAY)";
                    $b[$p] = max(0, (int) $v);
                    break;
                case 'last_shipment_days':
                    $parts[] = "COALESCE(DATEDIFF(NOW(), (SELECT MAX(o.order_date) FROM cdb_add_order o WHERE o.sender_id = u.id AND o.status_courier <> 21)), 99999) $sqlOp $p";
                    $b[$p] = (int) $v;
                    break;
                case 'shipments_90d':
                    $parts[] = "(SELECT COUNT(*) FROM cdb_add_order o WHERE o.sender_id = u.id AND o.status_courier <> 21 AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)) $sqlOp $p";
                    $b[$p] = (int) $v;
                    break;
                case 'balance':
                    $parts[] = "COALESCE((SELECT SUM(GREATEST(0, COALESCE(x.amount_ghs,0)-COALESCE(x.discount_ghs,0)-COALESCE(x.paid_ghs,0))) FROM cdb_consolidate_customer_billing x WHERE x.sender_id = u.id),0) $sqlOp $p";
                    $b[$p] = (float) $v;
                    break;
                case 'in_warehouse':
                    $parts[] = (!empty($v) && $v !== '0' ? '' : 'NOT ') . "EXISTS (SELECT 1 FROM cdb_customers_packages cp WHERE cp.sender_id = u.id AND cp.status_courier NOT IN (8, 15, 21))";
                    unset($b[$p]);
                    break;
                case 'consent':
                    $ch = in_array($v, ['whatsapp', 'sms', 'email'], true) ? $v : 'whatsapp';
                    $parts[] = "EXISTS (SELECT 1 FROM cdb_comm_prefs cpf WHERE cpf.user_id = u.id AND cpf.mkt_$ch = 1 AND cpf.opted_out_all = 0)";
                    unset($b[$p]);
                    break;
            }
        }
        $glue = (($def['match'] ?? 'all') === 'any') ? ' OR ' : ' AND ';
        $where = "u.userlevel IN ($roles) AND u.active = 1" . ($parts ? ' AND (' . implode($glue, $parts) . ')' : '');
        return [$where, $b];
    }

    function cdp_commClientRoles()
    {
        $ids = [];
        foreach (cdp_roleFlagsMap() as $rid => $r) {
            if (cdp_roleHasFlag($rid, 'is_client')) {
                $ids[] = (int) $rid;
            }
        }
        return $ids ?: [1];
    }

    function cdp_commSegmentCount(array $def)
    {
        list($w, $b) = cdp_commSegmentSql($def, cdp_commClientRoles());
        return (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_users u WHERE $w", $b);
    }

    function cdp_commSegmentSample(array $def, $limit = 10)
    {
        list($w, $b) = cdp_commSegmentSql($def, cdp_commClientRoles());
        return cdp_opsQ("SELECT u.id, u.fname, u.lname, u.locker, u.city, u.country FROM cdb_users u WHERE $w ORDER BY u.id DESC LIMIT " . (int) $limit, $b);
    }

    function cdp_commSegmentIds(array $def)
    {
        list($w, $b) = cdp_commSegmentSql($def, cdp_commClientRoles());
        return array_map(function ($r) { return (int) $r->id; }, cdp_opsQ("SELECT u.id FROM cdb_users u WHERE $w ORDER BY u.id LIMIT 100000", $b));
    }

    function cdp_commSaveSegment(array $d)
    {
        $name = trim((string) ($d['name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'error' => 'Give the segment a name.'];
        }
        $def = is_array($d['definition'] ?? null) ? $d['definition'] : cdp_opsDecode($d['definition'] ?? '', []);
        $def = ['match' => (($def['match'] ?? 'all') === 'any') ? 'any' : 'all', 'rules' => array_values(array_slice((array) ($def['rules'] ?? []), 0, 12))];
        $count = cdp_commSegmentCount($def);
        $id = (int) ($d['id'] ?? 0);
        $now = cdp_opsNow();
        if ($id > 0) {
            cdp_opsX("UPDATE cdb_comm_segments SET name = :n, description = :d, definition = :def, last_count = :c, counted_at = :t, updated_at = :t WHERE id = :id",
                [':n' => mb_substr($name, 0, 120), ':d' => mb_substr((string) ($d['description'] ?? ''), 0, 255), ':def' => cdp_opsJson($def), ':c' => $count, ':t' => $now, ':id' => $id]);
        } else {
            $id = cdp_opsX("INSERT INTO cdb_comm_segments (name, description, definition, last_count, counted_at, created_by, created_at, updated_at) VALUES (:n, :d, :def, :c, :t, :u, :t, :t)",
                [':n' => mb_substr($name, 0, 120), ':d' => mb_substr((string) ($d['description'] ?? ''), 0, 255), ':def' => cdp_opsJson($def), ':c' => $count, ':t' => $now, ':u' => cdp_opsUid()])['id'];
        }
        return ['ok' => $id > 0, 'id' => $id, 'count' => $count];
    }

    function cdp_commSaveCampaign(array $d)
    {
        $name = trim((string) ($d['name'] ?? ''));
        $body = trim((string) ($d['body'] ?? ''));
        $channels = array_values(array_intersect((array) ($d['channels'] ?? []), array_keys(cdp_commChannels())));
        if ($name === '' || $body === '' || !$channels) {
            return ['ok' => false, 'error' => 'Name, message and at least one channel are required.'];
        }
        $seg = (int) ($d['segment_id'] ?? 0);
        if (!$seg || !cdp_opsOne("SELECT id FROM cdb_comm_segments WHERE id = :id", [':id' => $seg])) {
            return ['ok' => false, 'error' => 'Choose a segment.'];
        }
        $at = trim((string) ($d['scheduled_at'] ?? ''));
        $at = preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}$/', $at) ? str_replace('T', ' ', $at) . ':00' : null;
        $id = (int) ($d['id'] ?? 0);
        $now = cdp_opsNow();
        $bind = [':n' => mb_substr($name, 0, 160), ':s' => $seg, ':c' => implode(',', $channels), ':sub' => mb_substr((string) ($d['subject'] ?? ''), 0, 190),
            ':b' => $body, ':at' => $at, ':t' => $now];
        if ($id > 0) {
            $cur = cdp_opsOne("SELECT status FROM cdb_comm_campaigns WHERE id = :id", [':id' => $id]);
            if (!$cur || !in_array($cur->status, ['draft', 'pending'], true)) {
                return ['ok' => false, 'error' => 'Only a draft can be edited.'];
            }
            $bind[':id'] = $id;
            cdp_opsX("UPDATE cdb_comm_campaigns SET name = :n, segment_id = :s, channels = :c, subject = :sub, body = :b, scheduled_at = :at, status = 'draft', approved_by = NULL, updated_at = :t WHERE id = :id", $bind);
        } else {
            $bind[':u'] = cdp_opsUid();
            $id = cdp_opsX("INSERT INTO cdb_comm_campaigns (name, segment_id, channels, subject, body, status, scheduled_at, created_by, created_at, updated_at)
                            VALUES (:n, :s, :c, :sub, :b, 'draft', :at, :u, :t, :t)", $bind)['id'];
        }
        return ['ok' => $id > 0, 'id' => $id];
    }

    /**
     * Approve and launch (or schedule). The person who wrote a campaign may
     * not approve it when approval is required, so a broadcast always has two
     * pairs of eyes.
     */
    function cdp_commApproveCampaign($id, $approverId)
    {
        $c = cdp_opsOne("SELECT * FROM cdb_comm_campaigns WHERE id = :id", [':id' => (int) $id]);
        if (!$c || !in_array($c->status, ['draft', 'pending'], true)) {
            return ['ok' => false, 'error' => 'This campaign is not waiting for approval.'];
        }
        if (cdp_commSetting('campaign_needs_approval') === '1' && (int) $c->created_by === (int) $approverId && cdp_opsRank() < 4) {
            return ['ok' => false, 'error' => 'Another administrator must approve a campaign you wrote.'];
        }
        $status = ($c->scheduled_at && strtotime($c->scheduled_at) > time()) ? 'scheduled' : 'launching';
        cdp_opsX("UPDATE cdb_comm_campaigns SET status = :s, approved_by = :a, updated_at = :n WHERE id = :id",
            [':s' => $status, ':a' => (int) $approverId, ':n' => cdp_opsNow(), ':id' => (int) $c->id]);
        if ($status === 'launching') {
            return cdp_commLaunchCampaign((int) $c->id);
        }
        return ['ok' => true, 'status' => $status];
    }

    /** Queue a campaign: consent is checked per recipient per channel. */
    function cdp_commLaunchCampaign($id)
    {
        $c = cdp_opsOne("SELECT * FROM cdb_comm_campaigns WHERE id = :id", [':id' => (int) $id]);
        if (!$c || !in_array($c->status, ['launching', 'scheduled'], true)) {
            return ['ok' => false, 'error' => 'Campaign is not ready to send.'];
        }
        $claim = cdp_opsX("UPDATE cdb_comm_campaigns SET status = 'sending', launched_at = :n WHERE id = :id AND status IN ('launching','scheduled')",
            [':n' => cdp_opsNow(), ':id' => (int) $c->id]);
        if ($claim['rows'] !== 1) {
            return ['ok' => false, 'error' => 'Already launched.'];
        }
        $seg = cdp_opsOne("SELECT definition FROM cdb_comm_segments WHERE id = :id", [':id' => (int) $c->segment_id]);
        $ids = $seg ? cdp_commSegmentIds(cdp_opsDecode($seg->definition, [])) : [];
        $channels = array_filter(explode(',', (string) $c->channels));
        $queued = 0;
        $excluded = 0;
        $sendAfter = cdp_commQuietUntil();
        foreach ($ids as $uid) {
            $r = cdp_commQueueEvent('campaign', $uid, [], ['kind' => 'marketing', 'channels' => $channels, 'body' => (string) $c->body, 'subject' => (string) $c->subject,
                'campaign_id' => (int) $c->id, 'entity_type' => 'campaign', 'entity_ref' => 'campaign-' . (int) $c->id, 'dedupe' => 'cmp' . (int) $c->id . ':' . $uid, 'send_after' => $sendAfter]);
            $queued += $r['queued'];
            $excluded += count($r['skipped']);
        }
        cdp_opsX("UPDATE cdb_comm_campaigns SET status = 'sent', queued_count = :q, excluded_count = :e, updated_at = :n WHERE id = :id",
            [':q' => $queued, ':e' => $excluded, ':n' => cdp_opsNow(), ':id' => (int) $c->id]);
        return ['ok' => true, 'queued' => $queued, 'excluded' => $excluded];
    }

    function cdp_commLaunchDueCampaigns()
    {
        foreach (cdp_opsQ("SELECT id FROM cdb_comm_campaigns WHERE status = 'scheduled' AND scheduled_at <= :n", [':n' => cdp_opsNow()]) as $c) {
            cdp_commLaunchCampaign((int) $c->id);
        }
    }

    function cdp_commCampaigns()
    {
        return cdp_opsQ("SELECT c.*, s.name segment_name,
                           (SELECT COUNT(*) FROM cdb_comm_outbox o WHERE o.campaign_id = c.id AND o.status = 'sent') sent_count,
                           (SELECT COUNT(*) FROM cdb_comm_outbox o WHERE o.campaign_id = c.id AND o.status = 'failed') failed_count
                         FROM cdb_comm_campaigns c LEFT JOIN cdb_comm_segments s ON s.id = c.segment_id ORDER BY c.id DESC LIMIT 200");
    }
}

// ---------------------------------------------------------------------------
// Two-way WhatsApp and the AI bot
// ---------------------------------------------------------------------------

if (!function_exists('cdp_commFindUserByPhone')) {

    /** A customer whose stored phone ends with the same 9 digits (only when unique). */
    function cdp_commFindUserByPhone($phone)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) < 7) {
            return null;
        }
        $tail = substr($digits, -9);
        $rows = cdp_opsQ("SELECT * FROM cdb_users WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,'+',''),' ',''),'-',''),'(',''),')','') LIKE :t LIMIT 2", [':t' => '%' . $tail]);
        return count($rows) === 1 ? $rows[0] : null;
    }

    function cdp_commThread($phone, $name = '')
    {
        $phone = preg_replace('/\D+/', '', (string) $phone);
        if ($phone === '') {
            return null;
        }
        $t = cdp_opsOne("SELECT * FROM cdb_comm_threads WHERE phone = :p", [':p' => $phone]);
        if ($t) {
            return $t;
        }
        $u = cdp_commFindUserByPhone($phone);
        $now = cdp_opsNow();
        cdp_opsX("INSERT IGNORE INTO cdb_comm_threads (phone, user_id, contact_name, status, bot_active, created_at, updated_at) VALUES (:p, :u, :n, :s, :b, :t, :t)",
            [':p' => $phone, ':u' => $u ? (int) $u->id : null, ':n' => mb_substr($u ? trim($u->fname . ' ' . $u->lname) : (string) $name, 0, 150),
                ':s' => cdp_commSetting('bot_enabled') === '1' ? 'bot' : 'open', ':b' => cdp_commSetting('bot_enabled') === '1' ? 1 : 0, ':t' => $now]);
        return cdp_opsOne("SELECT * FROM cdb_comm_threads WHERE phone = :p", [':p' => $phone]);
    }

    function cdp_commThreadLogOutbound($phone, $userId, $body, $authorType, $authorId = null, $providerId = null)
    {
        $t = cdp_commThread($phone);
        if (!$t) {
            return 0;
        }
        $now = cdp_opsNow();
        $id = cdp_opsX("INSERT INTO cdb_comm_messages (thread_id, direction, author_type, author_id, body, provider_id, created_at) VALUES (:t, 'out', :a, :ai, :b, :p, :n)",
            [':t' => (int) $t->id, ':a' => $authorType, ':ai' => $authorId, ':b' => (string) $body, ':p' => $providerId ?: null, ':n' => $now])['id'];
        cdp_opsX("UPDATE cdb_comm_threads SET last_out_at = :n, last_message = :m, updated_at = :n, user_id = COALESCE(user_id, :u) WHERE id = :id",
            [':n' => $now, ':m' => cdp_opsClip($body, 250), ':u' => $userId ?: null, ':id' => (int) $t->id]);
        return $id;
    }

    /**
     * Parse a webhook body from Meta (Cloud API) or UltraMsg into
     * [['from','name','body','id','media'], ...]. Pure: unit tested.
     */
    function cdp_commParseInbound(array $j)
    {
        $out = [];
        foreach ((array) ($j['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $ch) {
                $v = $ch['value'] ?? [];
                $names = [];
                foreach ((array) ($v['contacts'] ?? []) as $c) {
                    $names[$c['wa_id'] ?? ''] = $c['profile']['name'] ?? '';
                }
                foreach ((array) ($v['messages'] ?? []) as $m) {
                    $type = $m['type'] ?? 'text';
                    $body = $type === 'text' ? ($m['text']['body'] ?? '')
                        : ($type === 'button' ? ($m['button']['text'] ?? '')
                        : ($type === 'interactive' ? ($m['interactive']['button_reply']['title'] ?? ($m['interactive']['list_reply']['title'] ?? ''))
                        : ($m[$type]['caption'] ?? '[' . $type . ']')));
                    $out[] = ['from' => preg_replace('/\D+/', '', (string) ($m['from'] ?? '')), 'name' => (string) ($names[$m['from'] ?? ''] ?? ''),
                        'body' => (string) $body, 'id' => (string) ($m['id'] ?? ''), 'media' => $type !== 'text' && $type !== 'button' && $type !== 'interactive' ? $type : ''];
                }
            }
        }
        if (isset($j['event_type'], $j['data']) && in_array($j['event_type'], ['message_received', 'message'], true)) {
            $d = $j['data'];
            if (empty($d['fromMe'])) {
                $out[] = ['from' => preg_replace('/\D+/', '', explode('@', (string) ($d['from'] ?? ''))[0]), 'name' => (string) ($d['pushname'] ?? ''),
                    'body' => (string) ($d['body'] ?? ''), 'id' => (string) ($d['id'] ?? ''), 'media' => ($d['type'] ?? 'chat') !== 'chat' ? (string) $d['type'] : ''];
            }
        }
        return $out;
    }

    /** Meta's X-Hub-Signature-256 over the raw body. */
    function cdp_commVerifyMetaSignature($raw, $header, $appSecret)
    {
        if ($appSecret === '' || !is_string($header) || strpos($header, 'sha256=') !== 0) {
            return false;
        }
        return hash_equals('sha256=' . hash_hmac('sha256', (string) $raw, $appSecret), $header);
    }

    /** Keyword commands. Returns the reply text, or '' when the message is not a command. */
    function cdp_commKeyword($text)
    {
        $k = strtoupper(trim((string) $text));
        if (in_array($k, ['STOP', 'UNSUBSCRIBE', 'STOP ALL', 'OPT OUT', 'OPTOUT'], true)) {
            return 'stop';
        }
        if (in_array($k, ['START', 'SUBSCRIBE', 'UNSTOP', 'OPT IN'], true)) {
            return 'start';
        }
        if (in_array($k, ['AGENT', 'HUMAN', 'PERSON', 'STAFF', 'REPRESENTATIVE'], true)) {
            return 'agent';
        }
        if (preg_match('/^[1-5]$/', $k)) {
            return 'score';
        }
        return '';
    }

    /**
     * Handle one inbound WhatsApp message: store it, apply keywords, record a
     * survey score, and let the bot answer when it owns the thread.
     */
    function cdp_commHandleInbound(array $m)
    {
        if (!cdp_commEnsureTables() || $m['from'] === '') {
            return;
        }
        $t = cdp_commThread($m['from'], $m['name']);
        if (!$t) {
            return;
        }
        $now = cdp_opsNow();
        $ins = cdp_opsX("INSERT IGNORE INTO cdb_comm_messages (thread_id, direction, author_type, body, provider_id, created_at) VALUES (:t, 'in', 'customer', :b, :p, :n)",
            [':t' => (int) $t->id, ':b' => $m['body'], ':p' => $m['id'] !== '' ? $m['id'] : null, ':n' => $now]);
        if ($ins['rows'] === 0) {
            return; // provider retry of a message we already have
        }
        $status = in_array($t->status, ['closed'], true) ? ($t->bot_active ? 'bot' : 'open') : $t->status;
        cdp_opsX("UPDATE cdb_comm_threads SET last_in_at = :n, last_message = :m, unread = unread + 1, status = :s, updated_at = :n WHERE id = :id",
            [':n' => $now, ':m' => cdp_opsClip($m['body'], 250), ':s' => $status, ':id' => (int) $t->id]);
        $t = cdp_opsOne("SELECT * FROM cdb_comm_threads WHERE id = :id", [':id' => (int) $t->id]);

        $kw = cdp_commKeyword($m['body']);
        $uid = (int) $t->user_id;
        if ($kw === 'stop') {
            if ($uid) {
                cdp_commSavePrefs($uid, ['whatsapp' => 0, 'mkt_whatsapp' => 0, 'mkt_sms' => 0, 'mkt_email' => 0], 'whatsapp_keyword', 0);
            }
            cdp_commReply($t, 'You will no longer receive WhatsApp messages from us. Reply START at any time to receive updates again.', 'system');
            return;
        }
        if ($kw === 'start') {
            if ($uid) {
                cdp_commSavePrefs($uid, ['whatsapp' => 1, 'opted_out_all' => 0], 'whatsapp_keyword', 0);
            }
            cdp_commReply($t, 'Welcome back! You will receive shipment updates on WhatsApp again.', 'system');
            return;
        }
        if ($kw === 'score' && $uid) {
            $s = cdp_opsOne("SELECT id FROM cdb_comm_surveys WHERE user_id = :u AND responded_at IS NULL AND sent_at >= :c ORDER BY id DESC LIMIT 1",
                [':u' => $uid, ':c' => cdp_opsNow(-3 * 86400)]);
            if ($s) {
                cdp_commSurveyAnswer((int) $s->id, (int) $m['body'], '');
                cdp_commReply($t, (int) $m['body'] >= 4 ? 'Thank you for your rating!' : 'Thank you. We are sorry it was not better - a member of our team will look into it.', 'system');
                if ((int) $m['body'] <= 2) {
                    cdp_commHandoff($t, 'Low satisfaction score');
                }
                return;
            }
        }
        if ($kw === 'agent') {
            cdp_commHandoff($t, 'Customer asked for a person');
            cdp_commReply($t, 'Thanks - a member of our team will reply here shortly.', 'system');
            return;
        }
        if ((int) $t->bot_active === 1 && $t->status === 'bot' && cdp_commSetting('bot_enabled') === '1') {
            cdp_commBotAnswer($t, $m['body']);
        }
    }

    function cdp_commHandoff($t, $reason)
    {
        cdp_opsX("UPDATE cdb_comm_threads SET status = 'open', bot_active = 0, handoff_reason = :r, updated_at = :n WHERE id = :id",
            [':r' => mb_substr($reason, 0, 190), ':n' => cdp_opsNow(), ':id' => (int) $t->id]);
    }

    /** Send a WhatsApp reply in a thread (staff, bot or system). */
    function cdp_commReply($t, $text, $authorType = 'agent', $authorId = null)
    {
        $u = (int) $t->user_id ? cdp_commUser((int) $t->user_id) : null;
        $target = $u ?: (object) ['id' => 0, 'fname' => (string) $t->contact_name, 'lname' => '', 'phone' => (string) $t->phone];
        if ($u) {
            $target = clone $u;
            $target->phone = (string) $t->phone; // answer the number that wrote
        }
        cdp_commLoadSenders();
        cdp_msgSetContext(['source' => 'comms_whatsapp_inbox', 'source_label' => 'WhatsApp support', 'entity_type' => 'whatsapp_thread', 'entity_label' => '+' . $t->phone]);
        if (cdp_commSetting('wa_provider') === 'cloud') {
            $r = cdp_commWaCloudSend((string) $t->phone, $text);
        } else {
            $raw = sendNotificationWhatsApp_v2($target, $text, null, ['allow_sms' => false]);
            $r = ['success' => !empty($raw['success']), 'message' => (string) ($raw['message'] ?? '')];
        }
        cdp_msgClearContext();
        $now = cdp_opsNow();
        cdp_opsX("INSERT INTO cdb_comm_messages (thread_id, direction, author_type, author_id, body, provider_id, status, created_at) VALUES (:t, 'out', :a, :ai, :b, :p, :s, :n)",
            [':t' => (int) $t->id, ':a' => $authorType, ':ai' => $authorId, ':b' => $text, ':p' => $r['provider_id'] ?? null,
                ':s' => !empty($r['success']) ? 'sent' : 'failed', ':n' => $now]);
        cdp_opsX("UPDATE cdb_comm_threads SET last_out_at = :n, last_message = :m, updated_at = :n" . ($authorType === 'agent' ? ', unread = 0' : '') . " WHERE id = :id",
            [':n' => $now, ':m' => cdp_opsClip($text, 250), ':id' => (int) $t->id]);
        return $r;
    }

    /** Facts the bot may use about this customer. */
    function cdp_commBotContext($t)
    {
        $lines = [];
        $uid = (int) $t->user_id;
        if ($uid) {
            $u = cdp_commUser($uid);
            $lines[] = 'Customer: ' . trim($u->fname . ' ' . $u->lname) . ', locker ' . $u->locker . '.';
            foreach (cdp_opsQ("SELECT CONCAT(COALESCE(o.order_prefix,''), o.order_no) trk, o.order_date, s.mod_style st FROM cdb_add_order o
                               LEFT JOIN cdb_styles s ON s.id = o.status_courier WHERE o.sender_id = :u AND o.status_courier <> 21 ORDER BY o.order_id DESC LIMIT 6", [':u' => $uid]) as $o) {
                $lines[] = "Shipment {$o->trk} (created {$o->order_date}): " . ucwords(str_replace('_', ' ', (string) $o->st));
            }
            foreach (cdp_opsQ("SELECT CONCAT(COALESCE(p.order_prefix,''), p.order_no) trk, p.order_date, s.mod_style st FROM cdb_customers_packages p
                               LEFT JOIN cdb_styles s ON s.id = p.status_courier WHERE p.sender_id = :u ORDER BY p.order_id DESC LIMIT 6", [':u' => $uid]) as $o) {
                $lines[] = "Warehouse package {$o->trk} (received {$o->order_date}): " . ucwords(str_replace('_', ' ', (string) $o->st));
            }
            if (cdp_opsTableExists('cdb_consolidate_customer_billing')) {
                $bal = (float) cdp_opsVal("SELECT SUM(GREATEST(0, COALESCE(amount_ghs,0)-COALESCE(discount_ghs,0)-COALESCE(paid_ghs,0))) FROM cdb_consolidate_customer_billing WHERE sender_id = :u", [':u' => $uid], 0);
                $lines[] = 'Outstanding balance: ' . cdp_commSetting('currency') . ' ' . number_format($bal, 2);
            }
        } else {
            $lines[] = 'The number is not linked to a customer account: do not reveal any shipment details; ask for the tracking number and offer a person.';
        }
        if (cdp_opsTableExists('cdb_cs_kb')) {
            foreach (cdp_opsQ("SELECT title, body FROM cdb_cs_kb WHERE status = 'published' AND audience IN ('customer','all') ORDER BY updated_at DESC LIMIT 8") as $k) {
                $lines[] = 'Help article "' . $k->title . '": ' . cdp_opsClip($k->body, 600);
            }
        }
        return implode("\n", $lines);
    }

    /** The bot answers, or hands over. Never invents: on any failure a person takes over. */
    function cdp_commBotAnswer($t, $question)
    {
        $today = (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_comm_messages WHERE author_type = 'bot' AND created_at >= :d", [':d' => date('Y-m-d 00:00:00')]);
        if ($today >= (int) cdp_commSetting('bot_daily_cap')) {
            cdp_commHandoff($t, 'Bot daily limit reached');
            return;
        }
        require_once __DIR__ . '/ai_assistant.php';
        require_once __DIR__ . '/ai_assistant_llm.php';
        if (!function_exists('cdp_aiConfigured') || !cdp_aiConfigured()) {
            cdp_commHandoff($t, 'AI provider not configured');
            return;
        }
        $history = array_reverse(cdp_opsQ("SELECT direction, author_type, body FROM cdb_comm_messages WHERE thread_id = :t ORDER BY id DESC LIMIT 10", [':t' => (int) $t->id]));
        $conv = '';
        foreach ($history as $h) {
            $conv .= ($h->direction === 'in' ? 'Customer: ' : 'Us: ') . cdp_opsClip($h->body, 500) . "\n";
        }
        $company = cdp_commBaseVars((object) [])['company'];
        $system = "You are the WhatsApp support assistant of {$company}, a shipping and parcel forwarding company. "
            . "Answer briefly (at most 4 short sentences), politely, in the customer's language. Use ONLY the facts below; never guess dates, prices or statuses. "
            . "If the facts do not answer the question, or the customer is upset, asks about refunds, claims, damage, changes of address or ownership, "
            . "or wants a person, reply with one short sentence saying a team member will help, and end your reply with the token [HANDOFF]. "
            . "Customers can reply STOP to opt out.\n" . trim((string) cdp_commSetting('bot_instructions'));
        $prompt = "FACTS:\n" . cdp_commBotContext($t) . "\n\nCONVERSATION:\n" . $conv . "\nWrite the next reply from Us.";
        $r = cdp_aiComplete($system, $prompt, null, null, null, 600);
        if (empty($r['ok']) || trim((string) $r['text']) === '') {
            cdp_commHandoff($t, 'AI error: ' . cdp_opsClip($r['error'] ?? 'empty answer', 120));
            cdp_commReply($t, 'Thanks for your message - a member of our team will reply shortly.', 'system');
            return;
        }
        $text = trim((string) $r['text']);
        $handoff = strpos($text, '[HANDOFF]') !== false;
        $text = trim(str_replace('[HANDOFF]', '', $text));
        cdp_commReply($t, mb_substr($text, 0, 1500), 'bot');
        if ($handoff) {
            cdp_commHandoff($t, 'Bot handed over');
        }
    }

    function cdp_commThreads($status = '', $q = '', $limit = 100)
    {
        $w = '1=1';
        $b = [];
        if (in_array($status, ['bot', 'open', 'closed', 'mine'], true)) {
            if ($status === 'mine') {
                $w .= ' AND t.assignee_id = :me AND t.status <> \'closed\'';
                $b[':me'] = cdp_opsUid();
            } else {
                $w .= ' AND t.status = :s';
                $b[':s'] = $status;
            }
        }
        if ($q !== '') {
            $w .= ' AND (t.phone LIKE :q OR t.contact_name LIKE :q OR t.last_message LIKE :q)';
            $b[':q'] = '%' . $q . '%';
        }
        return cdp_opsQ("SELECT t.*, u.locker FROM cdb_comm_threads t LEFT JOIN cdb_users u ON u.id = t.user_id WHERE $w ORDER BY t.updated_at DESC LIMIT " . (int) $limit, $b);
    }
}

// ---------------------------------------------------------------------------
// History, overview
// ---------------------------------------------------------------------------

if (!function_exists('cdp_commHistory')) {

    /** Everything we said to, and heard from, one customer, newest first. */
    function cdp_commHistory($userId, $limit = 200)
    {
        $userId = (int) $userId;
        $items = [];
        if (cdp_opsTableExists('cdb_message_log')) {
            foreach (cdp_opsQ("SELECT created_at, channel, source_label, subject, body, status, status_detail, sent_by_name FROM cdb_message_log
                               WHERE recipient_user_id = :u ORDER BY id DESC LIMIT " . (int) $limit, [':u' => $userId]) as $r) {
                $items[] = ['at' => $r->created_at, 'dir' => 'out', 'channel' => $r->channel, 'what' => $r->source_label ?: 'Message', 'subject' => $r->subject,
                    'body' => cdp_opsClip($r->body, 600), 'status' => $r->status, 'detail' => $r->status_detail, 'by' => $r->sent_by_name];
            }
        }
        foreach (cdp_opsQ("SELECT m.created_at, m.direction, m.author_type, m.body, m.status FROM cdb_comm_messages m JOIN cdb_comm_threads t ON t.id = m.thread_id
                           WHERE t.user_id = :u AND m.direction = 'in' ORDER BY m.id DESC LIMIT " . (int) $limit, [':u' => $userId]) as $r) {
            $items[] = ['at' => $r->created_at, 'dir' => 'in', 'channel' => 'whatsapp', 'what' => 'Customer message', 'subject' => '', 'body' => cdp_opsClip($r->body, 600),
                'status' => 'received', 'detail' => '', 'by' => ''];
        }
        foreach (cdp_opsQ("SELECT responded_at, score, comment, entity_ref FROM cdb_comm_surveys WHERE user_id = :u AND responded_at IS NOT NULL ORDER BY id DESC LIMIT 50", [':u' => $userId]) as $r) {
            $items[] = ['at' => $r->responded_at, 'dir' => 'in', 'channel' => 'survey', 'what' => 'Survey answer', 'subject' => $r->entity_ref,
                'body' => $r->score . '/5' . ($r->comment ? ' - ' . $r->comment : ''), 'status' => 'received', 'detail' => '', 'by' => ''];
        }
        foreach (cdp_opsQ("SELECT created_at, field, value, source FROM cdb_comm_consent_log WHERE user_id = :u ORDER BY id DESC LIMIT 50", [':u' => $userId]) as $r) {
            $items[] = ['at' => $r->created_at, 'dir' => 'note', 'channel' => 'preferences', 'what' => 'Preference changed', 'subject' => '',
                'body' => $r->field . ' set to ' . ($r->value ? 'on' : 'off') . ' (' . $r->source . ')', 'status' => '', 'detail' => '', 'by' => ''];
        }
        usort($items, function ($a, $b) { return strcmp((string) $b['at'], (string) $a['at']); });
        return array_slice($items, 0, $limit);
    }

    function cdp_commOverview($days = 30)
    {
        $from = cdp_opsNow(-$days * 86400);
        $byStatus = [];
        foreach (cdp_opsQ("SELECT channel, status, COUNT(*) c FROM cdb_comm_outbox WHERE created_at >= :f GROUP BY channel, status", [':f' => $from]) as $r) {
            $byStatus[$r->channel][$r->status] = (int) $r->c;
        }
        $byEvent = cdp_opsQ("SELECT event_key, COUNT(*) c, SUM(status = 'sent') sent FROM cdb_comm_outbox WHERE created_at >= :f GROUP BY event_key ORDER BY c DESC", [':f' => $from]);
        $optouts = (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_comm_prefs WHERE opted_out_all = 1 OR whatsapp = 0");
        $consent = cdp_opsOne("SELECT SUM(mkt_whatsapp) w, SUM(mkt_sms) s, SUM(mkt_email) e FROM cdb_comm_prefs WHERE opted_out_all = 0");
        return [
            'by_status' => $byStatus, 'by_event' => $byEvent,
            'queued' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_comm_outbox WHERE status = 'queued'"),
            'failed' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_comm_outbox WHERE status = 'failed' AND created_at >= :f", [':f' => $from]),
            'open_threads' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_comm_threads WHERE status = 'open'"),
            'bot_threads' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_comm_threads WHERE status = 'bot' AND last_in_at >= :f", [':f' => $from]),
            'optouts' => $optouts,
            'consent' => ['whatsapp' => (int) ($consent->w ?? 0), 'sms' => (int) ($consent->s ?? 0), 'email' => (int) ($consent->e ?? 0)],
            'survey' => cdp_commSurveyStats(date('Y-m-d', strtotime('-' . $days . ' days')), date('Y-m-d')),
            'slots_pending' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_comm_delivery_slots WHERE status = 'booked' AND slot_date >= CURDATE()"),
            'worker_last_run' => cdp_opsSetting('comms', 'worker_last_run', ''),
            'enabled' => cdp_commSetting('enabled') === '1',
        ];
    }
}
