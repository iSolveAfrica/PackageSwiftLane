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
 *     message per channel: every status change is its own trigger
 *     (status:<cdb_styles.id>, helpers/comms_triggers.php) with the text
 *     written for that status (saved, or drafted for its name in
 *     helpers/notify_drafts.php), plus unclaimed packages, unpaid balances,
 *     the rider's "Arriving" and the satisfaction survey. Detection reads the
 *     tracking history (cdb_courier_track) past a watermark, so no shipment
 *     screen had to change and nothing that happened before the module was
 *     switched on is ever announced.
 *   - One switch for every message: helpers/comms_gate.php lets the old
 *     per-screen senders be switched off per channel, and hands status
 *     messages to this engine (comms.status_owner) without double sends.
 *   - Every message goes through one outbox (cdb_comm_outbox) with a dedupe
 *     key, quiet hours, retries and a re-check of the customer's preferences
 *     at the moment of sending. Sending uses the app's own primitives
 *     (UltraMsg / Hubtel / mNotify / PHPMailer), or the WhatsApp Business
 *     Cloud API when that provider is chosen, so cdb_message_log keeps
 *     recording every attempt.
 *   - Templates per event and channel, customer segments, consent-based
 *     campaigns, preferences and opt-out (STOP / START keywords, signed
 *     preference links), two-way WhatsApp with an AI first responder that
 *     hands over to a person, satisfaction surveys. (Delivery-slot booking
 *     was retired in schema 2.)
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
    define('CDP_COMMS_SCHEMA', '2');
}
require_once __DIR__ . '/comms_triggers.php';
require_once __DIR__ . '/comms_gate.php';

// ---------------------------------------------------------------------------
// Catalogue
// ---------------------------------------------------------------------------

if (!function_exists('cdp_commChannels')) {

    function cdp_commChannels()
    {
        return ['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'E-mail'];
    }

    /**
     * Communication Center events that are not a status. Statuses are their
     * own triggers (status:<id>, helpers/comms_triggers.php). 'urgent'
     * messages ignore quiet hours. 'survey' stays as the built-in survey until
     * helpers/comms_surveys.php (cdp_commSurveyOnStatus) takes over.
     */
    function cdp_commEvents()
    {
        return [
            'claim_reminder'    => ['label' => 'Package claiming reminder', 'group' => 'Reminders', 'urgent' => false,
                'help' => 'A package has been ready for pickup for a number of days and nobody has collected it.'],
            'payment_reminder'  => ['label' => 'Payment reminder', 'group' => 'Reminders', 'urgent' => false,
                'help' => 'A balance has been outstanding for a number of days.'],
            'driver_arrival'    => ['label' => 'Driver arrival', 'group' => 'Delivery', 'urgent' => true,
                'help' => 'The rider taps "Arriving" in the driver workspace.'],
            'survey'            => ['label' => 'Satisfaction survey', 'group' => 'Feedback', 'urgent' => false,
                'help' => 'After a package is delivered or picked up (once per customer per day).'],
        ];
    }

    /**
     * Default template text per event and channel. Placeholders in {braces}.
     * Only the cdp_commEvents() keys are still sent; the others (status
     * events of schema 1) are kept for the v2 migration and as the generic
     * status text ('shipment_alert') when the drafts switch is off.
     */
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
            'driver_arrival' => [
                'subject' => 'Your driver is on the way',
                'text' => "Hello {first_name}, {driver_name} is on the way with *{tracking}*. {eta}Driver: {driver_phone}" . $foot,
                'sms' => '{company}: {driver_name} is on the way with {tracking}. {eta}Driver: {driver_phone}'],
            'failed_delivery' => [
                'subject' => 'We could not deliver {tracking}',
                'text' => "Hello {first_name}, we could not deliver *{tracking}* today ({status}). Reply to this message or call us to arrange a new time." . $foot,
                'sms' => '{company}: delivery of {tracking} failed ({status}). Reply or call us to arrange a new time.'],
            'survey' => [
                'subject' => 'How did we do?',
                'text' => "Hello {first_name}, thank you for choosing {company}! How was your experience with *{tracking}*? Reply with a number from 1 (poor) to 5 (excellent), or rate us here: {survey_url}",
                'sms' => '{company}: how did we do with {tracking}? Rate us 1-5: {survey_url}'],
        ];
    }

    /** Placeholders the template editor lists. */
    function cdp_commPlaceholders()
    {
        return ['name', 'first_name', 'locker', 'tracking', 'status', 'company', 'company_phone', 'pay_url', 'track_url', 'branch', 'days', 'balance', 'currency',
            'survey_url', 'prefs_url', 'driver_name', 'driver_phone', 'eta', 'date'];
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
            if (cdp_opsSetting('comms', 'migrated_v2', '') === '') {
                cdp_commMigrateV2();   // an earlier upgrade stopped before the data step
            }
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
        // Schema 2 (migrations/2026_11_27_comms_v2.sql): e-mail canvas designs,
        // bodies larger than 64 KB, campaigns with an audience and a text per channel.
        if ($ok) {
            $ok = cdp_opsDdl(cdp_commSchemaV2Ddl());
        }
        if ($ok) {
            cdp_commSeedTemplates();
            cdp_opsSettingSet('comms', 'schema_version', CDP_COMMS_SCHEMA, 0);
            cdp_commMigrateV2();
        }
        return $ready = $ok;
    }

    /**
     * The schema-2 column changes still missing in this database (idempotent:
     * a column that exists is not added again; MODIFY is safe to repeat).
     *
     * @return string[]
     */
    function cdp_commSchemaV2Ddl()
    {
        $out = [];
        $add = [
            'cdb_comm_templates' => ['design_json' => 'MEDIUMTEXT NULL'],
            'cdb_comm_campaigns' => ['mode' => "VARCHAR(12) NOT NULL DEFAULT 'campaign'", 'audience' => 'TEXT NULL', 'bodies' => 'MEDIUMTEXT NULL',
                'design_json' => 'MEDIUMTEXT NULL', 'wa_template_name' => 'VARCHAR(120) NULL', 'recipients_total' => 'INT NULL', 'launch_cursor' => 'INT NOT NULL DEFAULT 0'],
        ];
        foreach ($add as $table => $cols) {
            foreach ($cols as $col => $def) {
                if (!cdp_opsColumnExists($table, $col)) {
                    $out[] = "ALTER TABLE $table ADD COLUMN $col $def";
                }
            }
        }
        foreach (['cdb_comm_templates', 'cdb_comm_outbox', 'cdb_comm_campaigns'] as $table) {
            $type = cdp_opsOne("SELECT DATA_TYPE t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = 'body'", [':t' => $table]);
            if ($type && strtolower((string) $type->t) !== 'mediumtext') {
                $out[] = "ALTER TABLE $table MODIFY body MEDIUMTEXT NOT NULL";
            }
        }
        return $out;
    }

    function cdp_commSeedTemplates()
    {
        $now = cdp_opsNow();
        foreach (array_intersect_key(cdp_commDefaultTemplates(), cdp_commEvents()) as $ev => $t) {
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
            'rules' => '{}',                  // {"<trigger key>": {"whatsapp":1,"sms":0,"email":1}} (helpers/comms_triggers.php)
            'status_owner' => 'legacy',       // legacy | comms: who sends status messages (cdp_commSetStatusOwner)
            'status_map' => '{}',             // RETIRED (schema 1): only the old Communication Center screen still reads it
            'quiet_start' => '21:00', 'quiet_end' => '07:00',
            'batch_size' => '60',
            'claim_after_days' => '3', 'claim_every_days' => '4', 'claim_max' => '3',
            'pay_after_days' => '7', 'pay_every_days' => '7', 'pay_max' => '4', 'pay_min_amount' => '1',
            'currency' => 'GHS',
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
            // rules: cdp_commSaveRules; status_owner: cdp_commSetStatusOwner (refused without a running worker).
            if (in_array($k, ['rules', 'status_owner', 'status_map'], true) || !array_key_exists($k, $in)) {
                continue;
            }
            $v = trim((string) $in[$k]);
            if (in_array($k, $secret, true) && $v === '') {
                continue;
            }
            if (in_array($k, ['quiet_start', 'quiet_end'], true) && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v)) {
                continue;
            }
            if (in_array($k, ['batch_size', 'claim_after_days', 'claim_every_days', 'claim_max', 'pay_after_days', 'pay_every_days', 'pay_max', 'bot_daily_cap'], true)) {
                $v = (string) max(0, min(10000, (int) $v));
            }
            if ($k === 'pay_min_amount') {
                $v = (string) max(0, (float) $v);
            }
            if ($k === 'wa_provider' && !in_array($v, ['ultramsg', 'cloud'], true)) {
                continue;
            }
            if (in_array($k, ['enabled', 'bot_enabled', 'campaign_needs_approval'], true)) {
                $v = $v === '1' ? '1' : '0';
            }
            if ($k === 'enabled' && $v === '1' && cdp_commSetting('enabled') !== '1') {
                // Switching automations on: start from now. While they were off the
                // worker did not scan, so the watermark is stale; without this every
                // status changed in the meantime (already announced by the old screens)
                // would be sent again.
                cdp_opsSettingSet('comms', 'wm_track', (string) (int) cdp_opsVal("SELECT COALESCE(MAX(id),0) FROM cdb_courier_track"), 0);
            }
            cdp_opsSettingSet('comms', $k, mb_substr($v, 0, 4000));
        }
        if (cdp_commSetting('webhook_key') === '') {
            cdp_opsSettingSet('comms', 'webhook_key', bin2hex(random_bytes(16)));
        }
        return true;
    }

    /**
     * Save trigger switches. $rules: {trigger key: {channel: 0|1}}. Keys are
     * trigger keys (status:<id>, status:*, the cdp_commEvents() keys,
     * legacy:<source>); anything else is ignored. Only the keys given change,
     * every other switch is kept (the Automations page saves one row at a
     * time). $replace = true replaces the whole set.
     */
    function cdp_commSaveRules(array $rules, $replace = false)
    {
        $clean = $replace ? [] : cdp_commRules();
        foreach ($rules as $key => $chs) {
            $key = (string) $key;
            if (!cdp_commTriggerKeyValid($key) || !is_array($chs)) {
                continue;
            }
            foreach (array_keys(cdp_commChannels()) as $ch) {
                if (array_key_exists($ch, $chs)) {
                    $clean[$key][$ch] = !empty($chs[$ch]) && $chs[$ch] !== '0' ? 1 : 0;
                } elseif (!isset($clean[$key][$ch])) {
                    $clean[$key][$ch] = strpos($key, 'legacy:') === 0 ? 1 : 0;
                }
            }
        }
        return cdp_opsSettingSet('comms', 'rules', cdp_opsJson($clean));
    }

    /** Switch one trigger channel on or off. */
    function cdp_commSaveRule($key, $channel, $on)
    {
        if (!cdp_commTriggerKeyValid((string) $key) || !isset(cdp_commChannels()[$channel])) {
            return false;
        }
        return cdp_commSaveRules([(string) $key => [$channel => $on ? 1 : 0]]);
    }

    /**
     * Channels switched on for a trigger. A status with no channel of its own
     * falls back to "any other status" (status:*).
     *
     * @return string[]
     */
    function cdp_commTriggerChannels($key, array $rules = null)
    {
        $rules = $rules ?? cdp_commRules();
        $on = array_keys(array_filter((array) ($rules[(string) $key] ?? [])));
        if (!$on && cdp_commTriggerStatusId($key) > 0) {
            $on = array_keys(array_filter((array) ($rules['status:*'] ?? [])));
        }
        return array_values(array_intersect($on, array_keys(cdp_commChannels())));
    }

    /**
     * Retired (schema 1): statuses are no longer mapped to events. Kept so an
     * old caller cannot fail; it changes nothing.
     */
    function cdp_commSaveStatusMap(array $map)
    {
        return false;
    }
}

// ---------------------------------------------------------------------------
// Status classification
// ---------------------------------------------------------------------------

if (!function_exists('cdp_commClassifyStatus')) {

    /**
     * SCHEMA 1 ONLY: which old automation event a status belonged to. Used by
     * the one-time v2 migration (cdp_commMigrateV2) to carry an administrator's
     * old switches over to status:<id>. Never used to choose what is sent: it
     * called "No Picked up" a survey and "Not Shipped" a departure. Each status
     * is now its own trigger (helpers/comms_triggers.php).
     */
    function cdp_commClassifyStatus($statusId, $name, array $map = null)
    {
        $statusId = (int) $statusId;
        $map = $map ?? cdp_opsDecode((string) cdp_opsSetting('comms', 'status_map', '{}'), []);
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

    /**
     * Save the text of one trigger channel (cdb_comm_templates).
     *
     * $d: event_key (a trigger key from helpers/comms_triggers.php, or
     *     'campaign'; legacy:* texts live in the legacy tables), channel,
     *     language, subject, body, active (0 = send nothing on this channel),
     *     wa_template_name / wa_template_lang / wa_template_params,
     *     and for e-mail built on the canvas: design_json (the canvas design)
     *     plus html (the browser-rendered e-mail). The HTML is sanitised
     *     (cdp_commSanitizeEmailHtml) and stored as '<!--sl-html-->' . html;
     *     placeholders are filled escaped when it is sent.
     *
     * @return array{ok:bool,error:string,id?:int}
     */
    function cdp_commSaveTemplate(array $d)
    {
        $event = (string) ($d['event_key'] ?? '');
        $channel = (string) ($d['channel'] ?? '');
        $lang = (string) ($d['language'] ?? 'en');
        $lang = preg_match('/^[a-z]{2}$/', $lang) ? $lang : 'en';
        if ($event !== 'campaign' && (!cdp_commTriggerKeyValid($event) || strpos($event, 'legacy:') === 0 || $event === 'status:*')) {
            return ['ok' => false, 'error' => 'Unknown trigger.'];
        }
        if (!isset(cdp_commChannels()[$channel])) {
            return ['ok' => false, 'error' => 'Unknown channel.'];
        }
        if (!cdp_commEnsureTables()) {
            return ['ok' => false, 'error' => 'The message tables are not ready.'];
        }
        $active = !isset($d['active']) || (!empty($d['active']) && $d['active'] !== '0') ? 1 : 0;
        $design = null;
        $html = isset($d['html']) ? (string) $d['html'] : '';
        if ($channel === 'email' && $html !== '') {
            $clean = cdp_commSanitizeEmailHtml($html);
            if (trim(strip_tags($clean, '<img>')) === '') {
                return ['ok' => false, 'error' => 'The e-mail is empty.'];
            }
            $body = '<!--sl-html-->' . $clean;
            if (isset($d['design_json']) && (string) $d['design_json'] !== '') {
                $j = json_decode((string) $d['design_json'], true);
                if (!is_array($j) || !isset($j['blocks']) || !is_array($j['blocks'])) {
                    return ['ok' => false, 'error' => 'The e-mail design could not be read.'];
                }
                $design = json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        } else {
            $body = trim(str_replace("\r\n", "\n", (string) ($d['body'] ?? '')));
            if (strpos($body, '<!--sl-html-->') === 0) {
                $body = $channel === 'email' ? '<!--sl-html-->' . cdp_commSanitizeEmailHtml(substr($body, 14)) : '';
            }
        }
        if ($body === '' && $active) {
            return ['ok' => false, 'error' => 'The message cannot be empty.'];
        }
        $limits = ['sms' => 640, 'whatsapp' => 4096, 'email' => 1500000];
        if (mb_strlen($body) > $limits[$channel]) {
            return ['ok' => false, 'error' => $channel === 'sms' ? 'SMS templates are limited to 640 characters (4 parts).'
                : ($channel === 'whatsapp' ? 'WhatsApp messages are limited to 4,096 characters.' : 'The e-mail is too large.')];
        }
        $subject = mb_substr(trim(preg_replace('/\s+/', ' ', (string) ($d['subject'] ?? ''))), 0, 190);
        if ($channel === 'email' && $active && $subject === '') {
            return ['ok' => false, 'error' => 'The e-mail needs a subject.'];
        }
        $params = preg_replace('/[^a-z_,]/', '', strtolower((string) ($d['wa_template_params'] ?? '')));
        $r = cdp_opsX("INSERT INTO cdb_comm_templates (event_key, channel, language, subject, body, design_json, wa_template_name, wa_template_lang, wa_template_params, active, updated_by, updated_at)
                       VALUES (:e, :c, :l, :s, :b, :dj, :wn, :wl, :wp, :a, :u, :n)
                       ON DUPLICATE KEY UPDATE subject = VALUES(subject), body = VALUES(body), design_json = VALUES(design_json), wa_template_name = VALUES(wa_template_name),
                         wa_template_lang = VALUES(wa_template_lang), wa_template_params = VALUES(wa_template_params), active = VALUES(active),
                         updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)",
            [':e' => $event, ':c' => $channel, ':l' => $lang, ':s' => $subject, ':b' => $body, ':dj' => $design,
                ':wn' => preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($d['wa_template_name'] ?? ''))) ?: null,
                ':wl' => preg_replace('/[^A-Za-z_]/', '', (string) ($d['wa_template_lang'] ?? '')) ?: null, ':wp' => $params ?: null,
                ':a' => $active, ':u' => cdp_opsUid() ?: null, ':n' => cdp_opsNow()]);
        $id = $r['ok'] ? (int) cdp_opsVal("SELECT id FROM cdb_comm_templates WHERE event_key = :e AND channel = :c AND language = :l", [':e' => $event, ':c' => $channel, ':l' => $lang]) : 0;
        return ['ok' => $r['ok'], 'error' => $r['ok'] ? '' : ($r['error'] ?: 'Not saved.'), 'id' => $id];
    }

    /**
     * Forget the saved text of one trigger channel: a status goes back to the
     * message drafted for it, an event to its built-in text.
     */
    function cdp_commResetTemplate($key, $channel, $lang = 'en')
    {
        if (!cdp_commTriggerKeyValid((string) $key) || !isset(cdp_commChannels()[$channel])) {
            return ['ok' => false, 'error' => 'Unknown trigger.'];
        }
        $lang = preg_match('/^[a-z]{2}$/', (string) $lang) ? (string) $lang : 'en';
        $r = cdp_opsX("DELETE FROM cdb_comm_templates WHERE event_key = :e AND channel = :c AND language = :l", [':e' => (string) $key, ':c' => $channel, ':l' => $lang]);
        if ($r['ok'] && $lang === 'en' && isset(cdp_commEvents()[(string) $key])) {
            cdp_commSeedTemplates();   // events keep a stored row (old screens read it)
        }
        return ['ok' => $r['ok'], 'error' => $r['ok'] ? '' : 'Not reset.'];
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
        static $companyVars = null;
        if ($companyVars === null) {
            $companyVars = ['company_phone' => '', 'pay_url' => ''];
            try {
                require_once __DIR__ . '/notify_studio.php';
                $companyVars = cdp_notifyCompanyVars();
            } catch (Throwable $e) {
                error_log('comms company vars: ' . $e->getMessage());
            }
        }
        return [
            'name' => $name !== '' ? $name : 'Customer', 'first_name' => $first !== '' ? $first : 'Customer',
            'locker' => (string) ($u->locker ?? ''), 'company' => $company !== '' ? $company : 'Our Company',
            'currency' => cdp_commSetting('currency'), 'date' => date('Y-m-d'),
            'prefs_url' => !empty($u->id) ? cdp_commPrefsUrl((int) $u->id) : '',
        ] + $companyVars;
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
     * Queue one trigger for one customer on every channel switched on for it.
     *
     * $event: a trigger key (status:<id>, claim_reminder, payment_reminder,
     *   driver_arrival, survey) or a typed send (manual, campaign).
     * $vars: placeholder values (cdp_commBaseVars are added).
     * $o:
     *   channels      string[]  override the rule (manual sends, campaigns)
     *   body/subject  string    one text for every channel
     *   bodies[ch]/subjects[ch] a text per channel (wins over body/subject); an
     *                           e-mail body starting with <!--sl-html--> is HTML
     *   wa_template   array     {name, lang, params:[placeholder names]} Cloud API approved template
     *   kind          transactional | marketing (marketing needs the channel's consent)
     *   entity_type, entity_ref, campaign_id, dedupe (base key), send_after (ts), language
     * Without a body the text comes from cdp_commTriggerTemplate(): a saved
     * template, else the message drafted for the status, else the built-in one.
     *
     * @return array{queued:int,skipped:array<string,string>,ids:int[]}
     */
    function cdp_commQueueEvent($event, $userId, array $vars, array $o = [])
    {
        $out = ['queued' => 0, 'skipped' => [], 'ids' => []];
        if (!cdp_commEnsureTables()) {
            return $out;
        }
        $event = (string) $event;
        $u = cdp_commUser((int) $userId);
        if (!$u || (isset($u->active) && (int) $u->active !== 1)) {
            $out['skipped']['all'] = 'Unknown or inactive customer';
            return $out;
        }
        $prefs = cdp_commPrefs((int) $u->id);
        $kind = ($o['kind'] ?? 'transactional') === 'marketing' ? 'marketing' : 'transactional';
        $channels = $o['channels'] ?? cdp_commTriggerChannels($event);
        $vars = array_merge(cdp_commBaseVars($u), $vars);
        $lang = (string) ($o['language'] ?? ($prefs['language'] ?: 'en'));
        foreach (array_unique((array) $channels) as $ch) {
            if (!isset(cdp_commChannels()[$ch])) {
                continue;
            }
            if (!cdp_commAllowed($prefs, $ch, $kind, cdp_commMuteKey($event))) {
                $out['skipped'][$ch] = 'Customer preferences';
                continue;
            }
            $to = $ch === 'email' ? trim((string) $u->email) : trim((string) $u->phone);
            if ($to === '' || ($ch === 'email' && !filter_var($to, FILTER_VALIDATE_EMAIL))) {
                $out['skipped'][$ch] = 'No ' . ($ch === 'email' ? 'e-mail address' : 'phone number');
                continue;
            }
            $msg = cdp_commBuildMessage($event, $ch, $vars, $o, $lang);
            if ($msg === null) {
                $out['skipped'][$ch] = 'No active template';
                continue;
            }
            $id = cdp_commInsertOutbox((int) $u->id, $to, $event, $ch, $kind, $msg, $vars, $o);
            if ($id > 0) {
                $out['queued']++;
                $out['ids'][] = $id;
            } else {
                $out['skipped'][$ch] = $id === 0 ? 'Already queued' : 'Database error';
            }
        }
        return $out;
    }

    /**
     * Queue a message to a typed phone number or e-mail address that may
     * belong to no account (a WhatsApp contact, a pasted list). When the
     * address belongs to a customer, that customer's preferences apply
     * (STOP / opt-out is honoured); a marketing message to an address with no
     * account needs $o['consent'] = true (the caller records where it came
     * from). E-mail addresses on the newsletter unsubscribe list are skipped.
     *
     * $o: as cdp_commQueueEvent (body/bodies, subject/subjects, kind, campaign_id,
     *     entity_type, entity_ref, dedupe, send_after, wa_template), plus
     *     event (default 'manual'), name (for {name} / {first_name}), consent.
     *
     * @return array{queued:int,skipped:array<string,string>,ids:int[]}
     */
    function cdp_commQueueRaw($to, $channel, array $vars = [], array $o = [])
    {
        $out = ['queued' => 0, 'skipped' => [], 'ids' => []];
        $ch = (string) $channel;
        if (!cdp_commEnsureTables() || !isset(cdp_commChannels()[$ch])) {
            $out['skipped'][$ch ?: 'all'] = 'Unknown channel';
            return $out;
        }
        $to = trim((string) $to);
        if ($ch === 'email') {
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $out['skipped'][$ch] = 'Not a valid e-mail address';
                return $out;
            }
        } else {
            $digits = preg_replace('/\D+/', '', $to);
            if (strlen($digits) < 7 || strlen($digits) > 15) {
                $out['skipped'][$ch] = 'Not a valid phone number';
                return $out;
            }
            $to = (strpos($to, '+') === 0 ? '+' : '') . $digits;
        }
        $event = (string) ($o['event'] ?? 'manual');
        $kind = ($o['kind'] ?? 'transactional') === 'marketing' ? 'marketing' : 'transactional';
        // An address that belongs to a customer follows that customer's choices.
        $owner = $ch === 'email' ? cdp_opsOne("SELECT * FROM cdb_users WHERE email = :e LIMIT 1", [':e' => $to]) : cdp_commFindUserByPhone($to);
        if ($owner) {
            if (!cdp_commAllowed(cdp_commPrefs((int) $owner->id), $ch, $kind, cdp_commMuteKey($event))) {
                $out['skipped'][$ch] = 'Customer preferences';
                return $out;
            }
        } elseif ($kind === 'marketing' && empty($o['consent'])) {
            $out['skipped'][$ch] = 'No consent recorded for this address';
            return $out;
        }
        if ($ch === 'email' && cdp_opsTableExists('cdb_newsletter_unsubscribes')
            && cdp_opsOne("SELECT 1 x FROM cdb_newsletter_unsubscribes WHERE email = :e LIMIT 1", [':e' => strtolower($to)])) {
            $out['skipped'][$ch] = 'Unsubscribed';
            return $out;
        }
        $name = trim((string) ($o['name'] ?? ''));
        $base = $owner ? cdp_commBaseVars($owner) : cdp_commBaseVars((object) ['fname' => $name !== '' ? strtok($name, ' ') : '', 'lname' => '']);
        if (!$owner && $name !== '') {
            $base['name'] = $name;
        }
        $vars = array_merge($base, $vars);
        $msg = cdp_commBuildMessage($event, $ch, $vars, $o, 'en');
        if ($msg === null) {
            $out['skipped'][$ch] = 'No message';
            return $out;
        }
        $id = cdp_commInsertOutbox($owner ? (int) $owner->id : 0, $to, $event, $ch, $kind, $msg, $vars, $o);
        if ($id > 0) {
            $out['queued'] = 1;
            $out['ids'][] = $id;
        } else {
            $out['skipped'][$ch] = $id === 0 ? 'Already queued' : 'Database error';
        }
        return $out;
    }

    /** The key a customer can mute in their preferences (statuses are muted as a group). */
    function cdp_commMuteKey($event)
    {
        return strpos((string) $event, 'status:') === 0 ? 'status' : (string) $event;
    }

    /**
     * Subject and body of one channel, placeholders filled.
     * Status triggers go through the Notification Studio builder
     * (cdp_notifyBuild): empty optional lines are left out, the pickup code is
     * added when the text has no {pickup_code}, an SMS code line is shortened.
     *
     * @return array{subject:string,body:string,template_id:?int,wa_params:?string}|null
     */
    function cdp_commBuildMessage($event, $ch, array $vars, array $o, $lang = 'en')
    {
        $body = null;
        $subject = null;
        if (isset($o['bodies'][$ch])) {
            $body = (string) $o['bodies'][$ch];
        } elseif (isset($o['body'])) {
            $body = (string) $o['body'];
        }
        if (isset($o['subjects'][$ch])) {
            $subject = (string) $o['subjects'][$ch];
        } elseif (isset($o['subject'])) {
            $subject = (string) $o['subject'];
        }
        $tpl = null;
        if ($body === null) {
            $tpl = cdp_commTriggerTemplate($event, $ch, $lang);
            if (!$tpl) {
                return null;
            }
            $body = (string) $tpl['body'];
            $subject = $subject ?? (string) $tpl['subject'];
        }
        $subject = (string) $subject;
        if (trim($body) === '') {
            return null;
        }
        $mark = '<!--sl-html-->';
        $isHtml = $ch === 'email' && strpos($body, $mark) === 0;
        if (cdp_commTriggerStatusId($event) > 0) {
            require_once __DIR__ . '/notify_studio.php';
            $fit = cdp_notifyFitVars($event, $vars);
            // Drafted e-mails keep their blocks, so empty optional lines are left out with the values known.
            $blocks = $ch === 'email' && $tpl && !empty($tpl['blocks']) ? $tpl['blocks'] : null;
            $built = cdp_notifyBuild($ch, $subject, $isHtml ? substr($body, strlen($mark)) : ($ch === 'email' ? nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'), false) : $body),
                $blocks, $fit);
            $text = $ch === 'email' ? $mark . $built['body'] : $built['body'];
            $subject = $built['subject'];
            $isHtml = $ch === 'email';
        } elseif ($isHtml) {
            require_once __DIR__ . '/notify_studio.php';
            $text = $mark . cdp_notifyRender(substr($body, strlen($mark)), $vars, true);
            $subject = cdp_commRender($subject, $vars);
        } else {
            $text = cdp_commRender($body, $vars);
            $subject = cdp_commRender($subject, $vars);
        }
        if (trim(preg_replace('/^' . preg_quote($mark, '/') . '/', '', $text)) === '') {
            return null;
        }
        if (($o['kind'] ?? '') === 'marketing') {
            $prefs = (string) ($vars['prefs_url'] ?? '');
            if ($isHtml) {
                $text .= '<p style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#8A867E;text-align:center">'
                    . ($prefs !== '' ? 'Unsubscribe or change what you receive: <a href="' . htmlspecialchars($prefs, ENT_QUOTES, 'UTF-8') . '">your preferences</a>' : 'Reply to this e-mail to unsubscribe.') . '</p>';
            } else {
                $text .= $ch === 'email' ? "\n\n" . ($prefs !== '' ? 'Unsubscribe or change what you receive: ' . $prefs : 'Reply to this e-mail to unsubscribe.') : "\n\nReply STOP to opt out.";
            }
        }
        $wa = null;
        if ($ch === 'whatsapp') {
            $w = $o['wa_template'] ?? null;
            if (!$w && $tpl && $tpl['wa_template_name'] !== '') {
                $w = ['name' => $tpl['wa_template_name'], 'lang' => $tpl['wa_template_lang'], 'params' => array_filter(explode(',', $tpl['wa_template_params']))];
            }
            if (is_array($w) && !empty($w['name'])) {
                $wa = cdp_opsJson(['name' => preg_replace('/[^a-z0-9_]/', '', strtolower((string) $w['name'])), 'lang' => (string) (($w['lang'] ?? '') ?: 'en'),
                    'params' => array_values(array_map(function ($p) use ($vars) { return (string) ($vars[trim((string) $p)] ?? ''); }, (array) ($w['params'] ?? [])))]);
            }
        }
        return ['subject' => $subject, 'body' => $text, 'template_id' => $tpl && $tpl['id'] ? (int) $tpl['id'] : null, 'wa_params' => $wa];
    }

    /**
     * One outbox row. Returns its id, 0 when the dedupe key already exists,
     * -1 on a database error.
     */
    function cdp_commInsertOutbox($userId, $to, $event, $ch, $kind, array $msg, array $vars, array $o)
    {
        $urgent = !empty(cdp_commEvents()[$event]['urgent']);
        $sid = cdp_commTriggerStatusId($event);
        if ($sid > 0) {
            $urgent = in_array(cdp_commStatusKind($sid), cdp_commUrgentKinds(), true);
        }
        $sendAfter = $o['send_after'] ?? ($urgent ? time() : cdp_commQuietUntil());
        $dedupe = substr(($o['dedupe'] ?? ($event . ':' . ($o['entity_ref'] ?? '') . ':' . date('YmdHis') . ':' . mt_rand())) . ':' . $ch, 0, 190);
        $r = cdp_opsX("INSERT IGNORE INTO cdb_comm_outbox (user_id, recipient_to, event_key, channel, kind, subject, body, template_id, wa_params, entity_type, entity_ref,
                         campaign_id, dedupe_key, status, send_after, created_by, created_at)
                       VALUES (:u, :to, :e, :c, :k, :s, :b, :t, :wp, :et, :er, :cid, :d, 'queued', :sa, :by, :n)",
            [':u' => (int) $userId, ':to' => mb_substr((string) $to, 0, 190), ':e' => mb_substr((string) $event, 0, 40), ':c' => $ch, ':k' => $kind,
                ':s' => mb_substr((string) $msg['subject'], 0, 190), ':b' => (string) $msg['body'], ':t' => $msg['template_id'], ':wp' => $msg['wa_params'],
                ':et' => $o['entity_type'] ?? null, ':er' => isset($o['entity_ref']) ? mb_substr((string) $o['entity_ref'], 0, 80) : null,
                ':cid' => isset($o['campaign_id']) ? (int) $o['campaign_id'] : null, ':d' => $dedupe,
                ':sa' => date('Y-m-d H:i:s', (int) $sendAfter), ':by' => cdp_opsUid() ?: null, ':n' => cdp_opsNow()]);
        if (!$r['ok']) {
            return -1;
        }
        return $r['rows'] > 0 ? (int) $r['id'] : 0;
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
            return ['success' => false, 'message' => 'WhatsApp Cloud API is not configured.', 'early' => true];
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
            // The Settings master switch stops Cloud sends too (UltraMsg checks it in sendNotificationWhatsApp_v2).
            $csw = function_exists('cdp_getSettingsCourier') ? cdp_getSettingsCourier() : null;
            if (!$csw || (int) ($csw->active_whatsapp ?? 0) !== 1) {
                return ['success' => false, 'message' => 'WhatsApp is switched off'];
            }
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

    /**
     * E-mail through the configured mailer (PHP mail() or SMTP with the
     * configured host, port and security). $o: campaign_id (adds open / click
     * tracking and a List-Unsubscribe header), unsubscribe_url.
     */
    function cdp_commSendEmail($u, $subject, $text, array $o = [])
    {
        cdp_commLoadSenders();
        require_once __DIR__ . '/phpmailer/class.phpmailer.php';
        require_once __DIR__ . '/phpmailer/class.smtp.php';
        $settings = cdp_getSettingsCourier();
        $to = trim((string) $u->email);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'No valid e-mail address.', 'early' => true];
        }
        if (!filter_var(trim((string) ($settings->email_address ?? '')), FILTER_VALIDATE_EMAIL)) {
            // Same wording as the old screens, instead of PHPMailer's "Invalid address: (setFrom)".
            return ['success' => false, 'message' => 'No sender e-mail address is configured (Settings > E-mail)'];
        }
        $isHtml = strpos((string) $text, '<!--sl-html-->') === 0;
        if ($isHtml) {
            // Built in the editor: already HTML with escaped values.
            $html = substr((string) $text, strlen('<!--sl-html-->'));
            $text = trim(html_entity_decode(strip_tags(preg_replace('~<(br|/p|/tr|/div|/h[1-6])[^>]*>~i', "\n", preg_replace('~<(style|head)[^>]*>.*?</\1>~is', '', $html))), ENT_QUOTES, 'UTF-8'));
        } else {
            $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#1f2937">'
                . preg_replace('~(https?://[^\s<]+)~', '<a href="$1">$1</a>', nl2br(htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8')))
                . '</div>';
        }
        $unsub = (string) ($o['unsubscribe_url'] ?? '');
        if (!empty($o['campaign_id'])) {
            require_once __DIR__ . '/newsletter.php';
            require_once __DIR__ . '/message_tracking.php';
            if (function_exists('cdp_mtInject')) {
                $html = cdp_mtInject($html, 'campaign-' . (int) $o['campaign_id'], $to, cdp_opsSiteUrl(), $unsub);
            }
        }
        $full = stripos($html, '<html') !== false ? $html : '<html><body>' . $html . '</body></html>';
        try {
            if (($settings->mailer ?? '') === 'PHP') {
                $from = (string) $settings->email_address;
                $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: {$from}\r\n";
                if ($unsub !== '') {
                    $headers .= 'List-Unsubscribe: <' . str_replace(["\r", "\n", '>'], '', $unsub) . ">\r\n";
                }
                $ok = @mail($to, $subject, $full, $headers);
                cdp_msgLogPhpMail($ok, $to, $subject, $full);
                return ['success' => (bool) $ok, 'message' => $ok ? 'Sent' : 'PHP mail() returned false'];
            }
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $settings->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $settings->smtp_user;
            $mail->Password = $settings->smtp_password;
            $sec = strtolower(trim((string) ($settings->smtp_secure ?? '')));
            $mail->SMTPSecure = in_array($sec, ['tls', 'ssl'], true) ? $sec : 'tls';
            $port = (int) ($settings->smtp_port ?? 0);
            $mail->Port = $port > 0 ? $port : ($mail->SMTPSecure === 'ssl' ? 465 : 587);
            $mail->Timeout = 15;
            $mail->setFrom($settings->email_address, $settings->smtp_names);
            $mail->addAddress($to);
            if ($unsub !== '') {
                $mail->addCustomHeader('List-Unsubscribe', '<' . str_replace(["\r", "\n", '>'], '', $unsub) . '>');
            }
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $full;
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
            return ['success' => false, 'message' => 'SMS is not configured (Communications > Settings).', 'early' => true];
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
            if (!cdp_commAllowed($prefs, $row->channel, $row->kind, cdp_commMuteKey($row->event_key))) {
                cdp_opsX("UPDATE cdb_comm_outbox SET status = 'cancelled', last_error = 'Customer preferences changed' WHERE id = :id", [':id' => (int) $row->id]);
                return 'cancelled';
            }
            // Send to the address the customer has now, not the one at queue time.
        }
        $ctx = cdp_commDeliverContext($row, $u);
        cdp_commLoadSenders();
        if (function_exists('cdp_msgSetContext')) {
            cdp_msgSetContext($ctx);
        }
        try {
            if ($row->channel === 'whatsapp') {
                $res = cdp_commSendWhatsApp($u, (string) $row->body, cdp_opsDecode($row->wa_params, []) ?: null);
            } elseif ($row->channel === 'sms') {
                $res = cdp_commSendSms($u, (string) $row->body);
            } else {
                $o = [];
                if ($row->campaign_id) {
                    $o = ['campaign_id' => (int) $row->campaign_id, 'unsubscribe_url' => (int) $u->id > 0 ? cdp_commPrefsUrl((int) $u->id) : ''];
                }
                $res = cdp_commSendEmail($u, (string) ($row->subject ?: 'Notification'), (string) $row->body, $o);
            }
        } catch (Throwable $e) {
            $res = ['success' => false, 'message' => $e->getMessage(), 'early' => true];
        }
        if (empty($res['success']) && !empty($res['early']) && function_exists('cdp_msgLog')) {
            // Failed before reaching a provider: nothing else writes the message log.
            cdp_msgLog(['channel' => (string) $row->channel, 'status' => 'failed', 'status_detail' => mb_substr((string) ($res['message'] ?? 'Not sent'), 0, 250),
                'recipient_to' => $row->channel === 'email' ? (string) ($u->email ?? '') : (string) ($u->phone ?? ''), 'body' => (string) $row->body]);
        }
        if (function_exists('cdp_msgClearContext')) {
            cdp_msgClearContext();
        }
        if (!empty($res['success'])) {
            cdp_opsX("UPDATE cdb_comm_outbox SET status = 'sent', sent_at = :n, last_error = NULL WHERE id = :id", [':n' => cdp_opsNow(), ':id' => (int) $row->id]);
            if ($row->channel === 'whatsapp' && !empty($u->phone)) {
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

    /**
     * Message-log context of one outbox row: source comms_<trigger> (never
     * blocked by the gate), the trigger's label, outbox_id, the customer, the
     * shipment / package id, the template and the campaign batch.
     */
    function cdp_commDeliverContext($row, $u = null)
    {
        $entityId = '';
        if (in_array((string) $row->entity_type, ['shipment', 'package'], true) && (string) $row->entity_ref !== '') {
            $info = cdp_commResolveTrack((string) $row->entity_ref);
            $entityId = !empty($info['order_id']) ? (string) (int) $info['order_id'] : '';
        } elseif ((string) $row->entity_type === 'campaign' && $row->campaign_id) {
            $entityId = (string) (int) $row->campaign_id;
        }
        $label = cdp_commTriggerLabel((string) $row->event_key);
        if ($row->campaign_id) {
            $c = cdp_opsOne("SELECT name FROM cdb_comm_campaigns WHERE id = :id", [':id' => (int) $row->campaign_id]);
            $label = 'Campaign: ' . ($c ? (string) $c->name : '#' . (int) $row->campaign_id);
        } elseif ((string) $row->event_key === 'manual' && (int) ($row->created_by ?? 0) > 0) {
            $label = 'Message from ' . cdp_opsUserName((int) $row->created_by);
        }
        return ['source' => cdp_commTriggerSource((string) $row->event_key), 'source_label' => mb_substr('Communication Center: ' . $label, 0, 120),
            'outbox_id' => (int) $row->id, 'recipient_user_id' => (int) $row->user_id,
            'recipient_name' => $u && (int) ($u->id ?? 0) > 0 ? trim((string) $u->fname . ' ' . (string) $u->lname) : '',
            'entity_type' => (string) $row->entity_type, 'entity_id' => $entityId, 'entity_label' => (string) $row->entity_ref,
            'template_id' => $row->template_id ? (int) $row->template_id : null, 'subject' => (string) $row->subject,
            'batch_id' => $row->campaign_id ? 'campaign-' . (int) $row->campaign_id : ''];
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
     * @return array{type:string, owners:int[], driver_id:int, branch:string, order_id:int, module:string}
     */
    function cdp_commResolveTrack($track)
    {
        $track = (string) $track;
        $o = cdp_opsOne("SELECT o.order_id, o.sender_id, o.driver_id, f.name_off FROM cdb_add_order o LEFT JOIN cdb_offices f ON f.id = o.origin_off
                         WHERE CONCAT(COALESCE(o.order_prefix,''), o.order_no) = :t LIMIT 1", [':t' => $track]);
        if ($o) {
            return ['type' => 'shipment', 'owners' => [(int) $o->sender_id], 'driver_id' => (int) $o->driver_id, 'branch' => (string) $o->name_off,
                'order_id' => (int) $o->order_id, 'module' => 'air'];
        }
        $p = cdp_opsOne("SELECT order_id, sender_id FROM cdb_customers_packages WHERE CONCAT(COALESCE(order_prefix,''), order_no) = :t LIMIT 1", [':t' => $track]);
        if ($p) {
            return ['type' => 'package', 'owners' => [(int) $p->sender_id], 'driver_id' => 0, 'branch' => '', 'order_id' => (int) $p->order_id, 'module' => 'sea'];
        }
        foreach (['cdb_consolidate' => 'consolidate', 'cdb_consolidate_packages' => 'consolidate_packages'] as $table => $module) {
            if (!cdp_opsTableExists($table)) {
                continue;
            }
            $c = cdp_opsOne("SELECT consolidate_id FROM $table WHERE CONCAT(COALESCE(c_prefix,''), c_no) = :t LIMIT 1", [':t' => $track]);
            if ($c) {
                require_once __DIR__ . '/push_notify.php';
                return ['type' => 'consolidation', 'owners' => cdp_pushConsolidationOwnerIds((int) $c->consolidate_id, $module), 'driver_id' => 0, 'branch' => '',
                    'order_id' => (int) $c->consolidate_id, 'module' => $module];
            }
        }
        return ['type' => '', 'owners' => [], 'driver_id' => 0, 'branch' => '', 'order_id' => 0, 'module' => ''];
    }

    /**
     * New tracking-history rows -> one status message per owner. Each status
     * is its own trigger (status:<id>); a status with no channel of its own
     * uses "any other status" (status:*). The text is the status's own
     * (saved, or drafted for its name). The first run only sets the watermark:
     * history before the module was switched on is never announced.
     *
     * After the status message, the survey hook runs (helpers/comms_surveys.php
     * cdp_commSurveyOnStatus when present, else the built-in survey for
     * delivered / picked-up statuses).
     */
    function cdp_commScanStatusHistory($max = 300)
    {
        $n = 0;
        $last = (int) cdp_opsSetting('comms', 'wm_track', '-1');
        if ($last < 0) {
            cdp_opsSettingSet('comms', 'wm_track', (string) (int) cdp_opsVal("SELECT COALESCE(MAX(id),0) FROM cdb_courier_track"), 0);
            return 0;
        }
        $rows = cdp_opsQ("SELECT t.id, t.order_id, t.order_track, t.status_courier, t.t_date, t.comments, t.t_city, t.t_dest, t.office_id, s.mod_style, f.name_off
                          FROM cdb_courier_track t LEFT JOIN cdb_styles s ON s.id = t.status_courier LEFT JOIN cdb_offices f ON f.id = t.office_id
                          WHERE t.id > :l ORDER BY t.id LIMIT " . (int) $max, [':l' => $last]);
        $rules = cdp_commRules();
        foreach ($rows as $r) {
            $sid = (int) $r->status_courier;
            $key = 'status:' . $sid;
            $channels = $sid > 0 ? cdp_commTriggerChannels($key, $rules) : [];
            $surveyOn = $sid > 0 && (function_exists('cdp_commSurveyOnStatus') || array_filter((array) ($rules['survey'] ?? [])));
            if ($channels || $surveyOn) {
                $info = cdp_commResolveTrack((string) $r->order_track);
                foreach (array_unique($info['owners']) as $owner) {
                    if ($owner <= 0) {
                        continue;
                    }
                    $queued = [];
                    if ($channels) {
                        $q = cdp_commEmitStatus($r, (int) $owner, $info, $channels);
                        $n += $q['queued'];
                        $queued = $q['channels'];
                    }
                    if ($surveyOn) {
                        $n += cdp_commSurveyHook($sid, (int) $owner, $r, $queued, $info, $rules);
                    }
                }
            }
            cdp_opsSettingSet('comms', 'wm_track', (string) (int) $r->id, 0);
        }
        return $n;
    }

    /**
     * The values a status message fills: tracking, status, link, office,
     * note, location, package facts (carrier number, ETA, weight, items,
     * amount due), company phone, payment link, the pickup code (statuses that
     * carry one) and the rider (out for delivery).
     */
    function cdp_commStatusVars($r, $owner, array $info)
    {
        require_once __DIR__ . '/notify_studio.php';
        $sid = (int) $r->status_courier;
        $name = (string) ($r->mod_style ?? '');
        $kind = cdp_commStatusKind($sid, $name);
        $track = (string) $r->order_track;
        $loc = trim((string) ($r->t_city ?? ''));
        if ($loc === '') {
            $loc = trim((string) ($r->name_off ?? ''));
        }
        $vars = ['tracking' => $track, 'status' => $name !== '' ? cdp_commStatusLabel($name) : 'Updated', 'track_url' => cdp_commTrackUrl($track),
            'branch' => $info['branch'] ?: 'our office', 'comment' => trim((string) ($r->comments ?? '')), 'location' => $loc];
        $vars += cdp_notifyCompanyVars();
        if (in_array($info['type'], ['shipment', 'package'], true) && $info['order_id'] > 0) {
            $vars = array_merge($vars, cdp_notifyPackageVars($info['order_id'], $info['module']));
            if (in_array($kind, cdp_notifyDraftCodeKinds(), true)) {
                try {
                    require_once __DIR__ . '/pickup_code.php';
                    if (function_exists('cdp_pickupCodeReadyLine')) {
                        $line = (string) cdp_pickupCodeReadyLine($info['module'], [$info['order_id']], $sid, $name);
                        $vars['pickup_code'] = $line;
                        $vars['pickup_code_html'] = $line !== '' ? (string) cdp_pickupCodeReadyLine($info['module'], [$info['order_id']], $sid, $name, true) : '';
                    }
                } catch (Throwable $e) {
                    error_log('comms pickup code: ' . $e->getMessage());
                }
            }
        }
        if (in_array($kind, ['out_for_delivery', 'ready_delivery'], true)) {
            $vars = array_merge($vars, cdp_commDriverVars($info['driver_id']));
            $vars['eta'] = $vars['eta'] ?? '';
        }
        return $vars;
    }

    /**
     * Queue one status message for one owner on $channels.
     * @return array{queued:int,channels:string[]}
     */
    function cdp_commEmitStatus($r, $owner, array $info, array $channels)
    {
        $vars = cdp_commStatusVars($r, $owner, $info);
        $q = cdp_commQueueEvent('status:' . (int) $r->status_courier, $owner, $vars, ['entity_type' => $info['type'] ?: 'shipment', 'entity_ref' => (string) $r->order_track,
            'dedupe' => 'trk' . (int) $r->id . ':' . (int) $owner . ':status', 'channels' => $channels]);
        $done = [];
        foreach ($channels as $ch) {
            if (!isset($q['skipped'][$ch]) || $q['skipped'][$ch] === 'Already queued') {
                $done[] = $ch;
            }
        }
        return ['queued' => $q['queued'], 'channels' => $done];
    }

    /**
     * After a status message: the satisfaction survey. helpers/comms_surveys.php
     * (cdp_commSurveyOnStatus) decides when it exists. Until then the
     * built-in survey: only statuses whose name means delivered or picked up
     * ("No Picked up" is a reminder, never a survey), once per customer per
     * day, and the survey row exists only when a channel was queued.
     */
    function cdp_commSurveyHook($statusId, $owner, $r, array $queuedChannels, array $info, array $rules)
    {
        if (function_exists('cdp_commSurveyOnStatus')) {
            try {
                return (int) cdp_commSurveyOnStatus((int) $statusId, (int) $owner, $r, $queuedChannels);
            } catch (Throwable $e) {
                error_log('comms survey hook: ' . $e->getMessage());
                return 0;
            }
        }
        $channels = array_keys(array_filter((array) ($rules['survey'] ?? [])));
        if (!$channels || !in_array(cdp_commStatusKind($statusId, (string) ($r->mod_style ?? '')), ['delivered', 'picked_up'], true)) {
            return 0;
        }
        if (cdp_opsOne("SELECT id FROM cdb_comm_surveys WHERE user_id = :u AND sent_at >= :d", [':u' => (int) $owner, ':d' => date('Y-m-d 00:00:00')])) {
            return 0;
        }
        $sid = cdp_opsX("INSERT INTO cdb_comm_surveys (user_id, entity_ref, channel, sent_at) VALUES (:u, :r, :c, :n)",
            [':u' => (int) $owner, ':r' => mb_substr((string) $r->order_track, 0, 80), ':c' => $channels[0], ':n' => cdp_opsNow()])['id'];
        if (!$sid) {
            return 0;
        }
        $vars = ['tracking' => (string) $r->order_track, 'track_url' => cdp_commTrackUrl((string) $r->order_track),
            'survey_url' => cdp_opsSiteUrl() . '/survey.php?t=' . rawurlencode(cdp_opsSign('survey', $sid, 30))];
        $q = cdp_commQueueEvent('survey', (int) $owner, $vars, ['entity_type' => $info['type'] ?: 'shipment', 'entity_ref' => (string) $r->order_track,
            'dedupe' => 'trk' . (int) $r->id . ':' . (int) $owner . ':survey', 'channels' => $channels]);
        if ($q['queued'] === 0) {
            cdp_opsX("DELETE FROM cdb_comm_surveys WHERE id = :id AND responded_at IS NULL", [':id' => (int) $sid]);
        }
        return $q['queued'];
    }

    function cdp_commDriverVars($driverId, $etaMinutes = null)
    {
        $d = $driverId > 0 ? cdp_commUser((int) $driverId) : null;
        return ['driver_name' => $d ? trim($d->fname . ' ' . $d->lname) : 'Our driver', 'driver_phone' => $d ? (string) $d->phone : '',
            'eta' => $etaMinutes ? 'Expected in about ' . (int) $etaMinutes . ' minutes. ' : ''];
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
            // Warehouse receipts are status rows too (a sea registration writes one),
            // so the status scan covers them: no separate warehouse scan.
            $out['detected'] += cdp_commScanStatusHistory();
            if (cdp_opsSetting('comms', 'reminders_day', '') !== date('Y-m-d') && (int) date('G') >= 9) {
                $out['detected'] += cdp_commScanClaimReminders();
                $out['detected'] += cdp_commScanPaymentReminders();
                cdp_opsSettingSet('comms', 'reminders_day', date('Y-m-d'), 0);
            }
        }
        // Campaigns and surveys run whatever the automations switch says: a
        // campaign was approved by a person; a due survey was promised.
        foreach (['cdp_commCampaignTick' => 'campaigns', 'cdp_commSurveyTick' => 'surveys'] as $fn => $what) {
            if (function_exists($fn)) {
                try {
                    $out[$what] = $fn();
                } catch (Throwable $e) {
                    error_log('comms worker ' . $what . ': ' . $e->getMessage());
                }
            } elseif ($fn === 'cdp_commCampaignTick') {
                cdp_commLaunchDueCampaigns();   // until helpers/comms_campaigns.php takes over
            }
        }
        // Delivery runs even with automations off: manual sends and campaigns use the outbox too.
        $out['delivered'] = cdp_commDeliverDue();
        cdp_opsSettingSet('comms', 'worker_last_run', cdp_opsNow(), 0);
        return $out;
    }
}

// ---------------------------------------------------------------------------
// Delivery slots (RETIRED) and surveys (public links)
// ---------------------------------------------------------------------------

if (!function_exists('cdp_commCreateSlotLink')) {

    /*
     * Delivery bookings are retired (schema 2): nothing sends a booking link
     * any more and cdb_comm_delivery_slots is only kept for its history. The
     * slot functions stay defined, unused by the engine, only so the old
     * delivery_schedule.php page and comms_ajax.php keep working until they
     * are replaced by the "this link has closed" stub; delete them then.
     */

    /** @deprecated delivery bookings are retired. */
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
        $days = max(1, (int) ($days ?? 7));
        $skipSunday = $skipSunday ?? true;
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
        if ($kw === 'score' && $uid && function_exists('cdp_surveyReplyScore')) {
            // Surveys: the open window, sent invitations only, saved on the form's rating question.
            $sr = cdp_surveyReplyScore($uid, (int) $m['body']);
            if ($sr) {
                cdp_commReply($t, $sr['score'] >= 4 ? 'Thank you for your rating!' : 'Thank you. We are sorry it was not better - a member of our team will look into it.', 'system');
                if ($sr['score'] <= 2) {
                    cdp_commHandoff($t, 'Low satisfaction score');
                }
                return;
            }
        } elseif ($kw === 'score' && $uid) {
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
        $system = "You are " . cdp_kingName() . ", the WhatsApp support agent of {$company}, a shipping and parcel forwarding company. " . cdp_kingPersona(cdp_kingName())
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

    /**
     * WHERE clause shared by the inbox list and its counts, so the two always
     * agree. $status: '' (all), 'open' (waiting for a person), 'mine'
     * (assigned to me, not closed), 'bot' (with King), 'closed'.
     */
    function cdp_commThreadWhere($status, $q, array &$b)
    {
        $w = '1=1';
        if ($status === 'mine') {
            $w .= " AND t.assignee_id = :me AND t.status <> 'closed'";
            $b[':me'] = cdp_opsUid();
        } elseif (in_array($status, ['bot', 'open', 'closed'], true)) {
            $w .= ' AND t.status = :s';
            $b[':s'] = $status;
        }
        $q = trim((string) $q);
        if ($q !== '') {
            $w .= " AND (t.phone LIKE :q1 OR t.contact_name LIKE :q2 OR t.last_message LIKE :q3 OR u.locker LIKE :q4 OR CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')) LIKE :q5";
            // % and _ in the search text are literal characters, not wildcards.
            $like = '%' . addcslashes($q, '%_\\') . '%';
            foreach ([1, 2, 3, 4, 5] as $i) {
                $b[':q' . $i] = $like;
            }
            $digits = preg_replace('/\D+/', '', $q);
            if (strlen($digits) >= 3) {
                $w .= ' OR t.phone LIKE :qd';
                $b[':qd'] = '%' . $digits . '%';
            }
            $w .= ')';
        }
        return $w;
    }

    function cdp_commThreads($status = '', $q = '', $limit = 100)
    {
        $b = [];
        $w = cdp_commThreadWhere((string) $status, (string) $q, $b);
        return cdp_opsQ("SELECT t.*, u.locker, TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) customer_name,
                                TRIM(CONCAT(COALESCE(a.fname,''),' ',COALESCE(a.lname,''))) assignee_name
                         FROM cdb_comm_threads t LEFT JOIN cdb_users u ON u.id = t.user_id LEFT JOIN cdb_users a ON a.id = t.assignee_id
                         WHERE $w ORDER BY t.updated_at DESC, t.id DESC LIMIT " . (int) $limit, $b);
    }

    /** Counts per inbox filter (same search as the list): all, open, mine, bot, closed. */
    function cdp_commThreadCounts($q = '')
    {
        $b = [];
        $w = cdp_commThreadWhere('', (string) $q, $b);
        $b[':me'] = cdp_opsUid();
        $r = cdp_opsOne("SELECT COUNT(*) a, SUM(t.status = 'open') o, SUM(t.status = 'bot') bo, SUM(t.status = 'closed') c,
                                SUM(t.status <> 'closed' AND t.assignee_id = :me) m
                         FROM cdb_comm_threads t LEFT JOIN cdb_users u ON u.id = t.user_id WHERE $w", $b);
        return ['all' => (int) ($r->a ?? 0), 'open' => (int) ($r->o ?? 0), 'mine' => (int) ($r->m ?? 0), 'bot' => (int) ($r->bo ?? 0), 'closed' => (int) ($r->c ?? 0)];
    }

    /**
     * Conversations waiting for a person: the sidebar badge, the Communications
     * tab and the inbox's "Waiting for a person" filter all use this one figure.
     * Definition: status 'open'. An unread message in a thread King is handling
     * (status 'bot') is being answered by King, so it does not wait for a
     * person; a handoff or a failed bot answer sets the status to 'open'.
     * One COUNT per request (static cache).
     */
    function cdp_commWaitingCount()
    {
        static $n = null;
        if ($n === null) {
            $n = cdp_opsTableExists('cdb_comm_threads')
                ? (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_comm_threads WHERE status = 'open'", [], 0) : 0;
        }
        return $n;
    }

    /** The URL a WhatsApp provider posts inbound messages to (without the secret key). */
    function cdp_commWebhookBase()
    {
        $base = defined('CDP_APP_URL') && CDP_APP_URL !== '' ? (string) CDP_APP_URL : cdp_opsSiteUrl();
        if ($base === '' && !empty($_SERVER['HTTP_HOST'])) {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            $base = ($https ? 'https://' : 'http://') . preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) $_SERVER['HTTP_HOST']);
        }
        return rtrim($base, '/') . '/webhooks/whatsapp.php';
    }

    /**
     * Is WhatsApp connected? What the inbox status strip shows. Never returns
     * a secret: only whether one is set (the admin view adds the webhook key).
     */
    function cdp_commConnection($isAdmin = false)
    {
        $s = cdp_commSettings();
        $cloud = $s['wa_provider'] === 'cloud';
        if ($cloud) {
            $inbound = $s['wa_cloud_verify'] !== '' && $s['wa_cloud_app_secret'] !== '';
            $outbound = $s['wa_cloud_phone_id'] !== '' && $s['wa_cloud_token'] !== '';
        } else {
            $inbound = $s['webhook_key'] !== '';
            $cs = function_exists('cdp_getSettingsCourier') ? cdp_getSettingsCourier() : null;
            $outbound = $cs && (int) ($cs->active_whatsapp ?? 0) === 1;
        }
        $aiOk = null;
        if ($s['bot_enabled'] === '1') {
            require_once __DIR__ . '/ai_assistant.php';
            $aiOk = function_exists('cdp_aiConfigured') ? (bool) cdp_aiConfigured() : false;
        }
        $base = cdp_commWebhookBase();
        return [
            'provider' => $cloud ? 'cloud' : 'ultramsg',
            'provider_label' => $cloud ? 'Meta WhatsApp Cloud API' : 'UltraMsg',
            'inbound' => $inbound,
            'outbound' => $outbound,
            'bot_on' => $s['bot_enabled'] === '1',
            'bot_ai_ok' => $aiOk,
            'connected' => $inbound && $outbound,
            // Admins get the full address once the key exists; nobody gets a placeholder they could copy by mistake.
            'webhook_url' => $cloud ? $base : ($isAdmin ? ($s['webhook_key'] !== '' ? $base . '?key=' . $s['webhook_key'] : $base) : $base . '?key=…'),
            'webhook_has_key' => $s['webhook_key'] !== '',
            'threads' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_comm_threads", [], 0),
        ];
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
            'worker_last_run' => cdp_opsSetting('comms', 'worker_last_run', ''),
            'enabled' => cdp_commSetting('enabled') === '1',
        ];
    }
}

// ---------------------------------------------------------------------------
// Schema 2: one-time data migration (statuses become their own triggers)
// ---------------------------------------------------------------------------

if (!function_exists('cdp_commMigrateV2')) {

    /**
     * Status kinds (cdp_notifyDraftKind) an old status event may carry over
     * to. The old classifier mapped by id and by loose words; a switch moves
     * to status:<id> only when the status NAME really means that event, so
     * "No Picked up" (old: survey) and "Not Shipped" (old: departure) keep no
     * wrong message.
     */
    function cdp_commV2EventKinds()
    {
        return [
            'departure' => ['accepted_airline', 'departed', 'in_transit'],
            'arrival' => ['arrived', 'distribution'],
            'customs' => ['customs', 'customs_cleared', 'ready_customs'],
            'ready_pickup' => ['ready_pickup', 'ready_customs'],
            'driver_arrival' => ['out_for_delivery'],
            'failed_delivery' => ['failed_delivery'],
            'warehouse_received' => ['received', 'in_warehouse'],
            'delivery_schedule' => ['ready_delivery'],
        ];
    }

    /**
     * Pure planning step of the migration (unit tested): old rules + status
     * map + statuses -> new rules, which old event moved to which statuses,
     * and the statuses deliberately not carried over.
     *
     * @param array $rules    old rules {event: {ch: 0|1}}
     * @param array $statuses id => name
     * @param array $map      old status_map {id: event|none}
     * @return array{rules:array,moved:array<string,int[]>,skipped:array<int,string>}
     */
    function cdp_commV2PlanRules(array $rules, array $statuses, array $map)
    {
        require_once __DIR__ . '/notify_drafts.php';
        $kinds = cdp_commV2EventKinds();
        $new = $rules;
        $moved = [];
        $skipped = [];
        foreach ($statuses as $id => $name) {
            $ev = cdp_commClassifyStatus((int) $id, (string) $name, $map);
            if (!isset($kinds[$ev])) {
                if ($ev === 'survey' && !in_array(cdp_notifyDraftKind((string) $name), ['delivered', 'picked_up'], true)) {
                    $skipped[(int) $id] = cdp_notifyDraftLabel((string) $name) . ' was treated as "survey"; it no longer asks for a rating.';
                }
                continue;
            }
            $kind = cdp_notifyDraftKind((string) $name);
            if (!in_array($kind, $kinds[$ev], true)) {
                $skipped[(int) $id] = cdp_notifyDraftLabel((string) $name) . ' was treated as "' . $ev . '"; that message was not carried over.';
                continue;
            }
            $moved[$ev][] = (int) $id;
            if (!empty($rules[$ev]) && is_array($rules[$ev]) && !isset($rules['status:' . (int) $id])) {
                foreach (['whatsapp', 'sms', 'email'] as $ch) {
                    $new['status:' . (int) $id][$ch] = !empty($rules[$ev][$ch]) ? 1 : 0;
                }
            }
        }
        if (!empty($rules['shipment_alert']) && is_array($rules['shipment_alert']) && !isset($rules['status:*'])) {
            foreach (['whatsapp', 'sms', 'email'] as $ch) {
                $new['status:*'][$ch] = !empty($rules['shipment_alert'][$ch]) ? 1 : 0;
            }
        }
        // driver_arrival stays: it is still the rider's "Arriving" button.
        foreach (array_merge(array_diff(array_keys($kinds), ['driver_arrival']), ['shipment_alert']) as $ev) {
            unset($new[$ev]);
        }
        foreach (cdp_commLegacyTriggers() as $src => $t) {
            if (!isset($new['legacy:' . $src])) {
                $new['legacy:' . $src] = array_fill_keys($t['channels'], 1);
            }
        }
        return ['rules' => $new, 'moved' => $moved, 'skipped' => $skipped];
    }

    /**
     * Run once (setting comms.migrated_v2): rules, customised event texts and
     * Notification Studio status texts move to status:<id>; delivery bookings
     * are retired; campaigns keep their audience; status messages stay with
     * the old screens (status_owner = legacy) until an administrator switches.
     * The old rules and status map are kept in comms.v2_backup.
     *
     * @return array{ok:bool,ran:bool,notes?:string[]}
     */
    function cdp_commMigrateV2($force = false)
    {
        static $running = false;
        if ($running || (!$force && cdp_opsSetting('comms', 'migrated_v2', '') !== '')) {
            return ['ok' => true, 'ran' => false];
        }
        $running = true;
        $notes = [];
        try {
            require_once __DIR__ . '/notify_drafts.php';
            $oldRules = cdp_opsDecode((string) cdp_opsSetting('comms', 'rules', '{}'), []);
            $map = cdp_opsDecode((string) cdp_opsSetting('comms', 'status_map', '{}'), []);
            cdp_opsSettingSet('comms', 'v2_backup', cdp_opsJson(['rules' => $oldRules, 'status_map' => $map, 'at' => cdp_opsNow()]), 0);
            $statuses = cdp_commStatusNames();

            // 1. Rules
            $plan = cdp_commV2PlanRules($oldRules, $statuses, $map);
            $rules = $plan['rules'];
            foreach ($plan['skipped'] as $note) {
                $notes[] = $note;
            }

            // 2. Customised texts of a moved event -> each of its statuses.
            $now = cdp_opsNow();
            foreach ($plan['moved'] as $ev => $ids) {
                foreach (cdp_opsQ("SELECT * FROM cdb_comm_templates WHERE event_key = :e AND updated_by IS NOT NULL", [':e' => $ev]) as $t) {
                    foreach ($ids as $id) {
                        cdp_opsX("INSERT IGNORE INTO cdb_comm_templates (event_key, channel, language, subject, body, wa_template_name, wa_template_lang, wa_template_params, active, updated_by, updated_at)
                                  VALUES (:e, :c, :l, :s, :b, :wn, :wl, :wp, :a, :u, :n)",
                            [':e' => 'status:' . $id, ':c' => $t->channel, ':l' => $t->language, ':s' => $t->subject, ':b' => $t->body, ':wn' => $t->wa_template_name,
                                ':wl' => $t->wa_template_lang, ':wp' => $t->wa_template_params, ':a' => (int) $t->active, ':u' => (int) $t->updated_by, ':n' => $now]);
                    }
                }
            }

            // 3. Notification Studio status texts.
            $intent = [];
            if (cdp_opsTableExists('cdb_notify_content')) {
                require_once __DIR__ . '/notify_studio.php';
                foreach (cdp_opsQ("SELECT * FROM cdb_notify_content WHERE trigger_key LIKE 'status:%' AND channel IN ('email','sms','whatsapp')") as $n) {
                    $key = (string) $n->trigger_key;
                    if (cdp_commTriggerStatusId($key) <= 0) {
                        continue;
                    }
                    $ch = (string) $n->channel;
                    if ((int) $n->enabled !== 1) {
                        $rules[$key][$ch] = 0;
                        foreach (['whatsapp', 'sms', 'email'] as $c) {
                            $rules[$key][$c] = $rules[$key][$c] ?? 0;
                        }
                        continue;
                    }
                    $design = null;
                    $body = (string) $n->body;
                    if ($ch === 'email') {
                        $blocks = json_decode((string) $n->blocks, true);
                        if (is_array($blocks) && $blocks) {
                            $body = cdp_notifyCompileBlocks($blocks);
                            $design = json_encode(cdp_commBlocksToCanvas($blocks), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        }
                        $body = CDP_NOTIFY_HTML_MARK . $body;
                    }
                    if (trim($body) === '') {
                        continue;
                    }
                    cdp_opsX("INSERT IGNORE INTO cdb_comm_templates (event_key, channel, language, subject, body, design_json, active, updated_by, updated_at)
                              VALUES (:e, :c, 'en', :s, :b, :d, 1, :u, :n)",
                        [':e' => $key, ':c' => $ch, ':s' => (string) $n->subject, ':b' => $body, ':d' => $design, ':u' => (int) ($n->updated_by ?: 0), ':n' => $now]);
                    $intent[$key][$ch] = 1;
                }
            }
            // Studio texts were sent by the old screens. They become Automation
            // Center switches only when an administrator hands status messages
            // to it (cdp_commSetStatusOwner), never while the old screens still
            // send them, so nothing is sent twice.
            if ($intent) {
                cdp_opsSettingSet('comms', 'v2_status_intent', cdp_opsJson($intent), 0);
            }

            // 4. Delivery bookings retired.
            cdp_opsX("UPDATE cdb_comm_templates SET active = 0 WHERE event_key = 'delivery_schedule'");
            $oldFailed = [
                'whatsapp' => "Hello {first_name}, we could not deliver *{tracking}* today ({status}). Choose a new time here: {schedule_url}\n\n{company}",
                'email' => "Hello {first_name}, we could not deliver {tracking} today ({status}). Choose a new time here: {schedule_url}\n\n{company}",
                'sms' => '{company}: delivery of {tracking} failed ({status}). Re-book: {schedule_url}',
            ];
            $newFailed = cdp_commDefaultTemplates()['failed_delivery'];
            foreach ($oldFailed as $ch => $old) {
                cdp_opsX("UPDATE cdb_comm_templates SET body = :nb WHERE event_key = 'failed_delivery' AND channel = :c AND body = :ob",
                    [':nb' => $ch === 'sms' ? $newFailed['sms'] : ($ch === 'email' ? str_replace('*', '', $newFailed['text']) : $newFailed['text']), ':c' => $ch, ':ob' => $old]);
            }
            unset($rules['delivery_schedule']);

            // 5. Campaigns keep the audience their segment described.
            if (cdp_opsColumnExists('cdb_comm_campaigns', 'audience')) {
                cdp_opsX("UPDATE cdb_comm_campaigns c JOIN cdb_comm_segments s ON s.id = c.segment_id SET c.audience = s.definition WHERE c.audience IS NULL");
            }

            cdp_opsSettingSet('comms', 'rules', cdp_opsJson($rules), 0);
            cdp_opsX("DELETE FROM cdb_ops_settings WHERE ns = 'comms' AND setting_key = 'status_map'");
            cdp_opsSettings('comms', true);
            if (cdp_opsSetting('comms', 'status_owner', '') === '') {
                cdp_opsSettingSet('comms', 'status_owner', 'legacy', 0);
            }
            if ((string) cdp_opsSetting('comms', 'enabled', '0') === '1' && array_diff_key($oldRules, $plan['rules'])) {
                $notes[] = 'The old Communication Center sent some status messages itself; those switches now sit on each status (Status messages).';
            }
            cdp_opsSettingSet('comms', 'v2_notes', cdp_opsJson($notes), 0);
            cdp_opsSettingSet('comms', 'migrated_v2', cdp_opsNow(), 0);
            return ['ok' => true, 'ran' => true, 'notes' => $notes];
        } catch (Throwable $e) {
            error_log('comms v2 migration: ' . $e->getMessage());
            return ['ok' => false, 'ran' => true, 'notes' => [$e->getMessage()]];
        } finally {
            $running = false;
        }
    }
}

// Survey hooks (cdp_commSurveyOnStatus, cdp_commSurveyTick) load with the engine so the
// worker and the status scan find them (helpers/comms_surveys.php requires this file back).
if (is_file(__DIR__ . '/comms_surveys.php')) {
    require_once __DIR__ . '/comms_surveys.php';
}
// Campaign sending (cdp_commCampaignTick) loads with the engine too, so every worker entry point
// runs it instead of the legacy cdp_commLaunchDueCampaigns (helpers/comms_campaigns.php requires this file back).
if (is_file(__DIR__ . '/comms_campaigns.php')) {
    require_once __DIR__ . '/comms_campaigns.php';
}
