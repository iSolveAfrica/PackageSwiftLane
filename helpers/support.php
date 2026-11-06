<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************

/**
 * Customer Service — the helpdesk core.
 *
 * Customers talk to King (the AI agent, helpers/support_king.php) or ask for a
 * person at any time. Staff work every case from one inbox. This file holds
 * everything that is not the language model:
 *
 *   - storage (cdb_cs_* tables, created on first use like the AI assistant's)
 *   - settings, access rules, public references
 *   - conversations, messages and the ownership state machine
 *       ai_active -> handoff_queued -> human_active -> resolved
 *     which is separate from the ticket status and is what stops King from
 *     answering once a person owns the conversation
 *   - tickets: lifecycle, version checks, tags, followers, links, tasks, merge
 *   - SLA clocks (first response / resolution, pause while waiting on the
 *     customer, breaches that are never erased)
 *   - routing and automation rules (trigger -> conditions -> actions) with a
 *     dry-run simulator, run log, loop and frequency guards
 *   - notifications to customers and staff through an outbox, delivered with
 *     the app's own WhatsApp / e-mail / SMS senders (helpers/push_notify.php)
 *   - knowledge base, canned replies, saved views, presence, reports
 *
 * Identity always comes from the session. An id in a request is only ever
 * used inside the caller's own scope: customers reach their own
 * conversations, tickets, shipments and packages; staff (rank 2+) reach the
 * desk. Settings are for administrators (rank 3+).
 *
 * Times are stored in the server's timezone like the rest of the app.
 */

require_once __DIR__ . '/rbac.php';

/** Bump when a CREATE TABLE below changes, so the DDL runs once more. */
if (!defined('CDP_CS_SCHEMA')) {
    define('CDP_CS_SCHEMA', '1');
}

// ---------------------------------------------------------------------------
// Small database helpers
// ---------------------------------------------------------------------------

if (!function_exists('cdp_csNow')) {

    function cdp_csNow($offsetSeconds = 0)
    {
        return date('Y-m-d H:i:s', time() + (int) $offsetSeconds);
    }

    /** Runs a SELECT, returns rows as objects ([] on failure). */
    function cdp_csQ($sql, array $bind = [])
    {
        try {
            $db = new Conexion;
            $db->cdp_query($sql);
            foreach ($bind as $k => $v) {
                $db->bind($k, $v);
            }
            $rows = $db->cdp_registros();
            return is_array($rows) ? $rows : [];
        } catch (Throwable $e) {
            error_log('CS_Q ' . $e->getMessage());
            return [];
        }
    }

    function cdp_csOne($sql, array $bind = [])
    {
        $r = cdp_csQ($sql, $bind);
        return $r ? $r[0] : null;
    }

    /** Runs a write. Returns ['ok' => bool, 'rows' => affected, 'id' => last insert id]. */
    function cdp_csX($sql, array $bind = [])
    {
        try {
            $db = new Conexion;
            $db->cdp_query($sql);
            foreach ($bind as $k => $v) {
                $db->bind($k, $v);
            }
            $ok = $db->cdp_execute() !== false;
            return ['ok' => $ok, 'rows' => $ok ? (int) $db->cdp_rowCount() : 0, 'id' => $ok ? (int) $db->dbh->lastInsertId() : 0,
                'error' => $ok ? '' : $db->cdp_error()];
        } catch (Throwable $e) {
            error_log('CS_X ' . $e->getMessage());
            return ['ok' => false, 'rows' => 0, 'id' => 0, 'error' => $e->getMessage()];
        }
    }

    function cdp_csJson($v)
    {
        return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    function cdp_csDecode($s, $default = [])
    {
        if ($s === null || $s === '') {
            return $default;
        }
        $v = json_decode((string) $s, true);
        return is_array($v) ? $v : $default;
    }

    function cdp_csClip($v, $n = 160)
    {
        $v = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $v)));
        return mb_strlen($v) > $n ? mb_substr($v, 0, $n - 1) . '…' : $v;
    }

    function cdp_csEsc($v)
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}

// ---------------------------------------------------------------------------
// Tables
// ---------------------------------------------------------------------------

if (!function_exists('cdp_csEnsureTables')) {

    /** Creates the helpdesk tables when missing. True when they exist. */
    function cdp_csEnsureTables()
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        // Fast path: one indexed read once the tables exist at this schema
        // version, so polling requests never re-run the DDL below.
        $v = cdp_csOne("SELECT setting_value AS v FROM cdb_cs_settings WHERE setting_key = 'schema_version'");
        if ($v && (string) $v->v === CDP_CS_SCHEMA) {
            cdp_csTablesExist(true);
            return $ready = true;
        }
        $t = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';
        $ddl = [
            "CREATE TABLE IF NOT EXISTS cdb_cs_settings (
                setting_key VARCHAR(64) NOT NULL, setting_value TEXT NULL, updated_by INT NULL, updated_at DATETIME NULL,
                PRIMARY KEY (setting_key))$t",

            "CREATE TABLE IF NOT EXISTS cdb_cs_teams (
                id INT NOT NULL AUTO_INCREMENT, name VARCHAR(120) NOT NULL, description VARCHAR(255) NULL,
                assign_mode VARCHAR(20) NOT NULL DEFAULT 'manual', timezone VARCHAR(64) NULL, is_default TINYINT(1) NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL,
                PRIMARY KEY (id))$t",

            "CREATE TABLE IF NOT EXISTS cdb_cs_team_members (
                team_id INT NOT NULL, user_id INT NOT NULL, is_lead TINYINT(1) NOT NULL DEFAULT 0,
                capacity INT NOT NULL DEFAULT 20, available TINYINT(1) NOT NULL DEFAULT 1, last_assigned_at DATETIME NULL,
                PRIMARY KEY (team_id, user_id), KEY idx_user (user_id))$t",

            "CREATE TABLE IF NOT EXISTS cdb_cs_conversations (
                id INT NOT NULL AUTO_INCREMENT, public_ref VARCHAR(20) NOT NULL, customer_id INT NULL,
                channel VARCHAR(20) NOT NULL DEFAULT 'web', subject VARCHAR(190) NULL,
                owner_state VARCHAR(20) NOT NULL DEFAULT 'ai_active', owner_seq INT NOT NULL DEFAULT 0, owner_user_id INT NULL,
                ticket_id INT NULL, language VARCHAR(12) NULL, ai_turns INT NOT NULL DEFAULT 0, handover_reason VARCHAR(255) NULL,
                handover_at DATETIME NULL, accepted_at DATETIME NULL, resolved_at DATETIME NULL, ai_outcome VARCHAR(20) NULL,
                rating TINYINT NULL, last_customer_at DATETIME NULL, last_message_at DATETIME NULL,
                customer_read_id INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY uq_ref (public_ref), KEY idx_customer (customer_id, updated_at),
                KEY idx_state (owner_state, updated_at), KEY idx_ticket (ticket_id))$t",

            "CREATE TABLE IF NOT EXISTS cdb_cs_messages (
                id INT NOT NULL AUTO_INCREMENT, conversation_id INT NULL, ticket_id INT NULL,
                author_type VARCHAR(12) NOT NULL, author_id INT NULL, visibility VARCHAR(10) NOT NULL DEFAULT 'public',
                channel VARCHAR(20) NOT NULL DEFAULT 'web', body MEDIUMTEXT NULL, attachments TEXT NULL, meta TEXT NULL,
                client_msg_id VARCHAR(64) NULL, delivery_state VARCHAR(16) NOT NULL DEFAULT 'sent', created_at DATETIME NOT NULL,
                PRIMARY KEY (id), KEY idx_conv (conversation_id, id), KEY idx_ticket (ticket_id, id),
                UNIQUE KEY uq_client (author_type, author_id, client_msg_id))$t",

            "CREATE TABLE IF NOT EXISTS cdb_cs_tickets (
                id INT NOT NULL AUTO_INCREMENT, public_ref VARCHAR(20) NOT NULL, conversation_id INT NULL, customer_id INT NULL,
                contact_name VARCHAR(190) NULL, contact_email VARCHAR(190) NULL, contact_phone VARCHAR(40) NULL,
                subject VARCHAR(190) NOT NULL, description TEXT NULL, category VARCHAR(40) NOT NULL DEFAULT 'general',
                priority VARCHAR(10) NOT NULL DEFAULT 'normal', status VARCHAR(20) NOT NULL DEFAULT 'new',
                disposition VARCHAR(10) NOT NULL DEFAULT 'none', channel VARCHAR(20) NOT NULL DEFAULT 'web',
                language VARCHAR(12) NOT NULL DEFAULT 'en', language_source VARCHAR(10) NULL, team_id INT NULL, assignee_id INT NULL,
                is_internal TINYINT(1) NOT NULL DEFAULT 0, pending_reason VARCHAR(255) NULL, resolution_code VARCHAR(40) NULL,
                resolution_summary TEXT NULL, summary MEDIUMTEXT NULL, merged_into INT NULL, parent_id INT NULL,
                sla_policy VARCHAR(40) NULL, sla_first_due DATETIME NULL, sla_resolve_due DATETIME NULL,
                sla_paused_at DATETIME NULL, sla_paused_minutes INT NOT NULL DEFAULT 0,
                first_breached_at DATETIME NULL, resolve_breached_at DATETIME NULL, sla_warned TINYINT(1) NOT NULL DEFAULT 0,
                first_response_at DATETIME NULL, human_first_response_at DATETIME NULL, resolved_at DATETIME NULL, closed_at DATETIME NULL,
                reopen_count INT NOT NULL DEFAULT 0, csat_score TINYINT NULL, csat_comment TEXT NULL, csat_at DATETIME NULL,
                last_customer_at DATETIME NULL, last_activity_at DATETIME NOT NULL, ack_sent TINYINT(1) NOT NULL DEFAULT 0,
                version INT NOT NULL DEFAULT 1, created_by INT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY uq_ref (public_ref), KEY idx_customer (customer_id, status),
                KEY idx_queue (disposition, status, assignee_id, last_activity_at), KEY idx_team (team_id, status),
                KEY idx_created (created_at))$t",

            "CREATE TABLE IF NOT EXISTS cdb_cs_ticket_tags (
                ticket_id INT NOT NULL, tag VARCHAR(40) NOT NULL, PRIMARY KEY (ticket_id, tag), KEY idx_tag (tag))$t",

            "CREATE TABLE IF NOT EXISTS cdb_cs_followers (
                ticket_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY (ticket_id, user_id), KEY idx_user (user_id))$t",

            "CREATE TABLE IF NOT EXISTS cdb_cs_links (
                id INT NOT NULL AUTO_INCREMENT, ticket_id INT NOT NULL, entity_type VARCHAR(20) NOT NULL, entity_id INT NOT NULL,
                entity_ref VARCHAR(80) NULL, created_by INT NULL, created_at DATETIME NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY uq_link (ticket_id, entity_type, entity_id), KEY idx_entity (entity_type, entity_id))$t",

            "CREATE TABLE IF NOT EXISTS cdb_cs_tasks (
                id INT NOT NULL AUTO_INCREMENT, ticket_id INT NULL, title VARCHAR(255) NOT NULL, due_at DATETIME NULL,
                assignee_id INT NULL, done_at DATETIME NULL, done_by INT NULL, created_by INT NULL, created_at DATETIME NOT NULL,
                PRIMARY KEY (id), KEY idx_ticket (ticket_id), KEY idx_assignee (assignee_id, done_at))$t",

            "CREATE TABLE IF NOT EXISTS cdb_cs_events (
                id BIGINT NOT NULL AUTO_INCREMENT, ticket_id INT NULL, conversation_id INT NULL, event_type VARCHAR(40) NOT NULL,
                actor_type VARCHAR(12) NOT NULL DEFAULT 'system', actor_id INT NULL, data TEXT NULL, created_at DATETIME NOT NULL,
                PRIMARY KEY (id), KEY idx_ticket (ticket_id, id), KEY idx_conv (conversation_id, id), KEY idx_type (event_type, created_at))$t",

            "CREATE TABLE IF NOT EXISTS cdb_cs_rules (
                id INT NOT NULL AUTO_INCREMENT, name VARCHAR(160) NOT NULL, kind VARCHAR(12) NOT NULL DEFAULT 'automation',
                trigger_event VARCHAR(40) NOT NULL, match_mode VARCHAR(4) NOT NULL DEFAULT 'all', conditions TEXT NULL, actions TEXT NULL,
                status VARCHAR(12) NOT NULL DEFAULT 'draft', sort_order INT NOT NULL DEFAULT 100, stop_processing TINYINT(1) NOT NULL DEFAULT 0,
                max_per_ticket INT NOT NULL DEFAULT 1, version INT NOT NULL DEFAULT 1, run_count INT NOT NULL DEFAULT 0,
                fail_count INT NOT NULL DEFAULT 0, last_run_at DATETIME NULL, template_key VARCHAR(40) NULL,
                created_by INT NULL, updated_by INT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
                PRIMARY KEY (id), KEY idx_trigger (status, trigger_event, sort_order))$t",

            "CREATE TABLE IF NOT EXISTS cdb_cs_rule_versions (
                id INT NOT NULL AUTO_INCREMENT, rule_id INT NOT NULL, version INT NOT NULL, snapshot TEXT NOT NULL,
                saved_by INT NULL, saved_at DATETIME NOT NULL, PRIMARY KEY (id), KEY idx_rule (rule_id, version))$t",

            "CREATE TABLE IF NOT EXISTS cdb_cs_rule_runs (
                id BIGINT NOT NULL AUTO_INCREMENT, rule_id INT NOT NULL, rule_version INT NOT NULL DEFAULT 1, ticket_id INT NULL,
                event VARCHAR(40) NOT NULL, dedupe_key VARCHAR(120) NULL, outcome VARCHAR(12) NOT NULL, steps TEXT NULL, created_at DATETIME NOT NULL,
                PRIMARY KEY (id), KEY idx_rule (rule_id, id), UNIQUE KEY uq_dedupe (rule_id, dedupe_key))$t",

            "CREATE TABLE IF NOT EXISTS cdb_cs_canned (
                id INT NOT NULL AUTO_INCREMENT, title VARCHAR(160) NOT NULL, body TEXT NOT NULL, shortcut VARCHAR(40) NULL,
                shared TINYINT(1) NOT NULL DEFAULT 1, created_by INT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
                PRIMARY KEY (id))$t",

            "CREATE TABLE IF NOT EXISTS cdb_cs_kb (
                id INT NOT NULL AUTO_INCREMENT, title VARCHAR(190) NOT NULL, body MEDIUMTEXT NOT NULL, category VARCHAR(60) NULL,
                audience VARCHAR(10) NOT NULL DEFAULT 'customer', status VARCHAR(12) NOT NULL DEFAULT 'draft', version INT NOT NULL DEFAULT 1,
                owner_id INT NULL, approved_by INT NULL, approved_at DATETIME NULL, review_at DATE NULL, source_ticket_id INT NULL,
                helpful INT NOT NULL DEFAULT 0, not_helpful INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
                PRIMARY KEY (id), KEY idx_status (status, audience))$t",

            "CREATE TABLE IF NOT EXISTS cdb_cs_views (
                id INT NOT NULL AUTO_INCREMENT, user_id INT NOT NULL, name VARCHAR(80) NOT NULL, filters TEXT NOT NULL,
                shared TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, PRIMARY KEY (id), KEY idx_user (user_id))$t",

            "CREATE TABLE IF NOT EXISTS cdb_cs_presence (
                ticket_id INT NOT NULL, user_id INT NOT NULL, seen_at DATETIME NOT NULL, typing_at DATETIME NULL,
                PRIMARY KEY (ticket_id, user_id))$t",

            "CREATE TABLE IF NOT EXISTS cdb_cs_ai_runs (
                id BIGINT NOT NULL AUTO_INCREMENT, conversation_id INT NULL, ticket_id INT NULL, kind VARCHAR(20) NOT NULL,
                provider VARCHAR(20) NULL, model VARCHAR(120) NULL, prompt_version VARCHAR(20) NULL, tokens_in INT NULL, tokens_out INT NULL,
                latency_ms INT NULL, tools TEXT NULL, outcome VARCHAR(30) NOT NULL, detail VARCHAR(255) NULL, user_id INT NULL, created_at DATETIME NOT NULL,
                PRIMARY KEY (id), KEY idx_kind (kind, created_at), KEY idx_conv (conversation_id))$t",

            "CREATE TABLE IF NOT EXISTS cdb_cs_proposals (
                id INT NOT NULL AUTO_INCREMENT, ticket_id INT NOT NULL, kind VARCHAR(30) NOT NULL, details TEXT NOT NULL,
                payload_hash CHAR(64) NOT NULL, status VARCHAR(12) NOT NULL DEFAULT 'pending', requested_by_type VARCHAR(12) NOT NULL,
                requested_by INT NULL, decided_by INT NULL, decided_at DATETIME NULL, decision_note TEXT NULL, ticket_version INT NULL,
                expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL,
                PRIMARY KEY (id), KEY idx_ticket (ticket_id, status))$t",

            "CREATE TABLE IF NOT EXISTS cdb_cs_files (
                id INT NOT NULL AUTO_INCREMENT, token CHAR(32) NOT NULL, uploaded_by INT NOT NULL, stored_name VARCHAR(80) NOT NULL,
                original_name VARCHAR(190) NOT NULL, mime VARCHAR(80) NOT NULL, size_bytes INT NOT NULL, created_at DATETIME NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY uq_token (token), KEY idx_user (uploaded_by))$t",

            "CREATE TABLE IF NOT EXISTS cdb_cs_outbox (
                id BIGINT NOT NULL AUTO_INCREMENT, ticket_id INT NULL, user_id INT NOT NULL, kind VARCHAR(20) NOT NULL,
                channel VARCHAR(12) NOT NULL, subject VARCHAR(190) NULL, body TEXT NOT NULL, dedupe_key VARCHAR(160) NOT NULL,
                status VARCHAR(12) NOT NULL DEFAULT 'queued', attempts INT NOT NULL DEFAULT 0, send_after DATETIME NOT NULL,
                last_error VARCHAR(255) NULL, sent_at DATETIME NULL, created_at DATETIME NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY uq_dedupe (dedupe_key), KEY idx_due (status, send_after), KEY idx_ticket (ticket_id))$t",
        ];
        try {
            $db = new Conexion;
            foreach ($ddl as $sql) {
                $db->cdp_query($sql);
                if ($db->cdp_execute() === false) {
                    return $ready = false;
                }
            }
            $ready = true;
            cdp_csTablesExist(true);
            cdp_csSeed();
            cdp_csSettingSet('schema_version', CDP_CS_SCHEMA);
        } catch (Throwable $e) {
            $ready = false;
        }
        return $ready;
    }

    function cdp_csTablesExist($known = null)
    {
        static $exists = null;
        if ($known !== null) {
            return $exists = (bool) $known;
        }
        if ($exists !== null) {
            return $exists;
        }
        $r = cdp_csOne("SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_cs_settings'");
        return $exists = ($r && (int) $r->c > 0);
    }

    /**
     * First-run starting points: one default team (nobody in it yet), and the
     * rule templates as drafts. Nothing is published or switched on.
     */
    function cdp_csSeed()
    {
        if (cdp_csSetting('seeded', '0') === '1') {
            return;
        }
        $now = cdp_csNow();
        if (!cdp_csOne("SELECT id FROM cdb_cs_teams LIMIT 1")) {
            cdp_csX("INSERT INTO cdb_cs_teams (name, description, assign_mode, is_default, created_at) VALUES ('Customer Support', 'Default team: receives every case no rule routes elsewhere. Add members on Settings > Teams.', 'least_loaded', 1, :n)", [':n' => $now]);
        }
        cdp_csSettingSet('seeded', '1');
    }
}

// ---------------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------------

if (!function_exists('cdp_csSettings')) {

    function cdp_csSettingDefaults()
    {
        return [
            'seeded'            => '0',
            'king_mode'         => 'off',        // off | copilot | customer
            'king_name'         => 'King',
            'king_greeting'     => "Hi, I'm King, the Swift Lane support assistant. I can check your shipments, packages and bills, explain where things stand, and get you to a person whenever you want.",
            'king_instructions' => '',
            'king_max_turns'    => '12',         // King turns in one conversation before it hands over
            'king_max_rounds'   => '6',          // tool lookups per answer
            'king_daily_budget' => '0',          // AI answers per day across all customers; 0 = no cap
            'copilot_enabled'   => '1',
            'languages'         => 'en',         // languages King answers in (comma list)
            'business_hours'    => '',           // shown to customers when queued; nothing is shown when empty
            'offline_note'      => 'A member of our team will reply here as soon as they are available. You do not need to stay on this page: we will notify you.',
            'ack_enabled'       => '1',          // acknowledge new tickets once
            'notify_channels'   => 'email',      // outbound copies of staff replies: email, whatsapp, sms
            'csat_enabled'      => '1',
            'csat_delay_min'    => '60',
            'sla_pause_pending' => '1',          // pause the resolution clock while waiting on the customer
            // Provisional development targets, minutes (first response) and hours (resolution).
            'sla_first_urgent'  => '30',  'sla_resolve_urgent' => '8',
            'sla_first_high'    => '60',  'sla_resolve_high'   => '24',
            'sla_first_normal'  => '240', 'sla_resolve_normal' => '48',
            'sla_first_low'     => '480', 'sla_resolve_low'    => '72',
            'sla_approved'      => '0',          // targets are provisional until an administrator approves them
            'sla_warn_pct'      => '80',
            'auto_close_days'   => '0',          // close resolved tickets after N days; 0 = never (needs an approved policy)
            'ref_prefix'        => 'CS',
        ];
    }

    function cdp_csSettings($fresh = false)
    {
        static $cache = null;
        if ($cache !== null && !$fresh) {
            return $cache;
        }
        $cache = cdp_csSettingDefaults();
        if (!cdp_csTablesExist()) {
            return $cache;
        }
        foreach (cdp_csQ("SELECT setting_key, setting_value FROM cdb_cs_settings") as $r) {
            $cache[(string) $r->setting_key] = (string) $r->setting_value;
        }
        return $cache;
    }

    function cdp_csSetting($key, $default = '')
    {
        $all = cdp_csSettings();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    function cdp_csSettingSet($key, $value, $userId = null)
    {
        $r = cdp_csX("INSERT INTO cdb_cs_settings (setting_key, setting_value, updated_by, updated_at) VALUES (:k, :v, :u, :t)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)",
            [':k' => (string) $key, ':v' => (string) $value, ':u' => $userId === null ? null : (int) $userId, ':t' => cdp_csNow()]);
        cdp_csSettings(true);
        return $r['ok'];
    }
}

// ---------------------------------------------------------------------------
// Access
// ---------------------------------------------------------------------------

if (!function_exists('cdp_csIsAgent')) {

    /** Staff, administrators and super admins work the desk. */
    function cdp_csIsAgent($user)
    {
        return is_object($user) && !empty($user->logged_in) && cdp_roleRankById((int) $user->userlevel) >= 2;
    }

    /** Administrators configure it (teams, rules, SLA, King, knowledge publishing). */
    function cdp_csIsAdmin($user)
    {
        return is_object($user) && !empty($user->logged_in) && cdp_roleRankById((int) $user->userlevel) >= 3;
    }

    /** Customers use the Support Hub. Drivers and agencies do not. */
    function cdp_csIsCustomer($user)
    {
        if (!is_object($user) || empty($user->logged_in)) {
            return false;
        }
        $lvl = (int) $user->userlevel;
        return cdp_roleRankById($lvl) === 0 && !cdp_roleHasFlag($lvl, 'is_driver') && !cdp_roleHasFlag($lvl, 'is_agency');
    }

    function cdp_csUid()
    {
        return (int) ($_SESSION['userid'] ?? 0);
    }

    function cdp_csUserName($id)
    {
        static $cache = [];
        $id = (int) $id;
        if ($id <= 0) {
            return '';
        }
        if (!isset($cache[$id])) {
            $r = cdp_csOne("SELECT fname, lname FROM cdb_users WHERE id = :i", [':i' => $id]);
            $cache[$id] = $r ? trim($r->fname . ' ' . $r->lname) : '';
        }
        return $cache[$id];
    }

    /** Staff accounts that can be assigned work. */
    function cdp_csAgents()
    {
        $out = [];
        foreach (cdp_csQ("SELECT id, fname, lname, userlevel, email FROM cdb_users WHERE COALESCE(active,1) = 1 ORDER BY fname, lname") as $u) {
            if (cdp_roleRankById((int) $u->userlevel) >= 2) {
                $out[] = ['id' => (int) $u->id, 'name' => trim($u->fname . ' ' . $u->lname), 'email' => (string) $u->email];
            }
        }
        return $out;
    }
}

// ---------------------------------------------------------------------------
// Vocabularies
// ---------------------------------------------------------------------------

if (!function_exists('cdp_csStatuses')) {

    function cdp_csStatuses()
    {
        return [
            'new'              => 'New',
            'open'             => 'Open',
            'pending_customer' => 'Waiting on customer',
            'pending_internal' => 'Waiting on our team',
            'on_hold'          => 'On hold',
            'resolved'         => 'Resolved',
            'closed'           => 'Closed',
        ];
    }

    /** What a customer sees: no internal labels. */
    function cdp_csPublicStatus($status)
    {
        $map = [
            'new' => 'Received', 'open' => 'In progress', 'pending_customer' => 'Waiting for your reply',
            'pending_internal' => 'In progress', 'on_hold' => 'In progress', 'resolved' => 'Resolved', 'closed' => 'Closed',
        ];
        return $map[$status] ?? 'In progress';
    }

    function cdp_csPriorities()
    {
        return ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];
    }

    /** Issue families from the build brief; editable labels, fixed keys. */
    function cdp_csCategories()
    {
        return [
            'general'    => 'General question',
            'account'    => 'Account & login',
            'warehouse'  => 'US warehouse / missing reference',
            'shipment'   => 'Shipment status & delays',
            'billing'    => 'Invoice & payment',
            'delivery'   => 'Pickup & delivery',
            'claim'      => 'Lost / damaged / claim',
            'complaint'  => 'Complaint',
            'business'   => 'Business shipping',
            'technical'  => 'Technical / system (staff)',
        ];
    }

    function cdp_csChannels()
    {
        return ['web' => 'Web chat', 'portal' => 'Support Hub form', 'staff' => 'Created by staff', 'email' => 'E-mail', 'whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'phone' => 'Phone call'];
    }

    function cdp_csDispositions()
    {
        return ['none' => '', 'archived' => 'Archived', 'spam' => 'Spam', 'trash' => 'Trash'];
    }

    /** How a case ended, for reporting (chosen when resolving). */
    function cdp_csResolutionCodes()
    {
        return ['answered' => 'Question answered', 'fixed' => 'Problem fixed', 'located' => 'Package located / delivered', 'refunded' => 'Refund or credit given',
            'claim' => 'Claim opened', 'customer_error' => 'No fault found / customer error', 'duplicate' => 'Duplicate', 'no_reply' => 'Customer did not reply',
            'not_possible' => 'Could not be done', 'other' => 'Other'];
    }

    function cdp_csLanguages()
    {
        return ['en' => 'English', 'fr' => 'French', 'es' => 'Spanish', 'pt' => 'Portuguese', 'zh' => 'Chinese', 'ar' => 'Arabic',
            'tw' => 'Twi', 'ee' => 'Ewe', 'ha' => 'Hausa', 'ga' => 'Ga', 'yo' => 'Yoruba', 'de' => 'German'];
    }

    function cdp_csOwnerStates()
    {
        return ['ai_active' => 'King is answering', 'handoff_queued' => 'Waiting for a person', 'human_active' => 'With a person', 'resolved' => 'Resolved'];
    }

    /** Normalised tag: lower case, letters, digits and dashes, max 40. */
    function cdp_csTag($t)
    {
        $t = mb_strtolower(trim((string) $t));
        $t = preg_replace('/[^\p{L}\p{N}]+/u', '-', $t);
        return mb_substr(trim($t, '-'), 0, 40);
    }
}

// ---------------------------------------------------------------------------
// References and events
// ---------------------------------------------------------------------------

if (!function_exists('cdp_csNewRef')) {

    /** CS-7KQ4M2 (tickets) / CH-… (conversations): short, unambiguous, unique. */
    function cdp_csNewRef($table, $prefix)
    {
        $alpha = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        for ($i = 0; $i < 12; $i++) {
            $s = '';
            for ($j = 0; $j < 6; $j++) {
                $s .= $alpha[random_int(0, strlen($alpha) - 1)];
            }
            $ref = $prefix . '-' . $s;
            if (!cdp_csOne("SELECT id FROM $table WHERE public_ref = :r", [':r' => $ref])) {
                return $ref;
            }
        }
        return $prefix . '-' . strtoupper(bin2hex(random_bytes(5)));
    }

    /** Audit trail + reporting source. Every state change writes one. */
    function cdp_csEvent($type, $ticketId, $convId, $actorType, $actorId, array $data = [])
    {
        cdp_csX("INSERT INTO cdb_cs_events (ticket_id, conversation_id, event_type, actor_type, actor_id, data, created_at)
            VALUES (:t, :c, :e, :at, :a, :d, :n)", [
            ':t' => $ticketId ? (int) $ticketId : null, ':c' => $convId ? (int) $convId : null, ':e' => (string) $type,
            ':at' => (string) $actorType, ':a' => $actorId ? (int) $actorId : null,
            ':d' => $data ? cdp_csJson($data) : null, ':n' => cdp_csNow(),
        ]);
    }
}

// ---------------------------------------------------------------------------
// Conversations and messages
// ---------------------------------------------------------------------------

if (!function_exists('cdp_csConvCreate')) {

    function cdp_csConvCreate($customerId, $channel = 'web', $subject = null)
    {
        $now = cdp_csNow();
        $state = cdp_csSetting('king_mode') === 'customer' ? 'ai_active' : 'handoff_queued';
        $r = cdp_csX("INSERT INTO cdb_cs_conversations (public_ref, customer_id, channel, subject, owner_state, created_at, updated_at, last_message_at)
            VALUES (:r, :c, :ch, :s, :st, :n, :n2, :n3)", [
            ':r' => cdp_csNewRef('cdb_cs_conversations', 'CH'), ':c' => $customerId ? (int) $customerId : null,
            ':ch' => (string) $channel, ':s' => $subject !== null ? cdp_csClip($subject, 190) : null, ':st' => $state,
            ':n' => $now, ':n2' => $now, ':n3' => $now,
        ]);
        if (!$r['ok']) {
            return 0;
        }
        cdp_csEvent('conversation.started', null, $r['id'], $customerId ? 'customer' : 'system', $customerId, ['channel' => $channel, 'owner' => $state]);
        return $r['id'];
    }

    function cdp_csConv($id)
    {
        return cdp_csOne("SELECT * FROM cdb_cs_conversations WHERE id = :i", [':i' => (int) $id]);
    }

    /** The customer's own conversation, or null. */
    function cdp_csConvForCustomer($id, $customerId)
    {
        return cdp_csOne("SELECT * FROM cdb_cs_conversations WHERE id = :i AND customer_id = :c", [':i' => (int) $id, ':c' => (int) $customerId]);
    }

    function cdp_csConvListForCustomer($customerId, $limit = 30)
    {
        $rows = cdp_csQ("SELECT c.id, c.public_ref, c.subject, c.owner_state, c.updated_at, c.ticket_id, t.public_ref AS ticket_ref, t.status AS ticket_status,
                (SELECT COUNT(*) FROM cdb_cs_messages m WHERE m.conversation_id = c.id AND m.visibility = 'public' AND m.author_type IN ('king','staff','system') AND m.id > c.customer_read_id) AS unread
            FROM cdb_cs_conversations c LEFT JOIN cdb_cs_tickets t ON t.id = c.ticket_id
            WHERE c.customer_id = :c ORDER BY c.updated_at DESC LIMIT " . (int) $limit, [':c' => (int) $customerId]);
        return array_map(function ($r) {
            return [
                'id' => (int) $r->id, 'ref' => (string) $r->public_ref, 'subject' => (string) ($r->subject ?: 'Conversation'),
                'owner' => (string) $r->owner_state, 'updated_at' => (string) $r->updated_at, 'unread' => (int) $r->unread,
                'ticket_ref' => (string) $r->ticket_ref, 'ticket_status' => $r->ticket_status ? cdp_csPublicStatus($r->ticket_status) : '',
            ];
        }, $rows);
    }

    /**
     * Adds a message. Idempotent per author on client_msg_id: a retried send
     * returns the first message instead of creating a second one.
     * Returns ['id' => int, 'duplicate' => bool].
     */
    function cdp_csMessageAdd($convId, $ticketId, $authorType, $authorId, $body, $visibility = 'public', array $opt = [])
    {
        $clientId = isset($opt['client_msg_id']) ? substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $opt['client_msg_id']), 0, 64) : null;
        if ($clientId === '') {
            $clientId = null;
        }
        if ($clientId !== null) {
            $dup = cdp_csOne("SELECT id FROM cdb_cs_messages WHERE author_type = :t AND author_id <=> :a AND client_msg_id = :c",
                [':t' => $authorType, ':a' => $authorId ? (int) $authorId : null, ':c' => $clientId]);
            if ($dup) {
                return ['id' => (int) $dup->id, 'duplicate' => true];
            }
        }
        $now = cdp_csNow();
        $r = cdp_csX("INSERT INTO cdb_cs_messages (conversation_id, ticket_id, author_type, author_id, visibility, channel, body, attachments, meta, client_msg_id, delivery_state, created_at)
            VALUES (:c, :t, :at, :a, :v, :ch, :b, :att, :m, :cid, :ds, :n)", [
            ':c' => $convId ? (int) $convId : null, ':t' => $ticketId ? (int) $ticketId : null,
            ':at' => (string) $authorType, ':a' => $authorId ? (int) $authorId : null,
            ':v' => $visibility === 'internal' ? 'internal' : 'public', ':ch' => (string) ($opt['channel'] ?? 'web'),
            ':b' => (string) $body, ':att' => !empty($opt['attachments']) ? cdp_csJson($opt['attachments']) : null,
            ':m' => !empty($opt['meta']) ? cdp_csJson($opt['meta']) : null, ':cid' => $clientId,
            ':ds' => (string) ($opt['delivery_state'] ?? 'sent'), ':n' => $now,
        ]);
        if (!$r['ok']) {
            // A concurrent retry with the same client id lost the unique-key race: return the winner.
            if ($clientId !== null) {
                $dup = cdp_csOne("SELECT id FROM cdb_cs_messages WHERE author_type = :t AND author_id <=> :a AND client_msg_id = :c",
                    [':t' => $authorType, ':a' => $authorId ? (int) $authorId : null, ':c' => $clientId]);
                if ($dup) {
                    return ['id' => (int) $dup->id, 'duplicate' => true];
                }
            }
            return ['id' => 0, 'duplicate' => false];
        }
        if ($convId) {
            $sets = 'last_message_at = :n, updated_at = :n2';
            $b = [':n' => $now, ':n2' => $now, ':i' => (int) $convId];
            if ($authorType === 'customer') {
                $sets .= ', last_customer_at = :n3';
                $b[':n3'] = $now;
            }
            cdp_csX("UPDATE cdb_cs_conversations SET $sets WHERE id = :i", $b);
        }
        if ($ticketId) {
            $sets = 'last_activity_at = :n';
            $b = [':n' => $now, ':i' => (int) $ticketId];
            if ($authorType === 'customer') {
                $sets .= ', last_customer_at = :n2';
                $b[':n2'] = $now;
            }
            cdp_csX("UPDATE cdb_cs_tickets SET $sets WHERE id = :i", $b);
        }
        return ['id' => $r['id'], 'duplicate' => false];
    }

    /**
     * Messages for display. $audience 'customer' never returns internal notes.
     * Either a conversation or a ticket (a ticket shows its conversation too).
     */
    function cdp_csMessages($convId, $ticketId, $audience = 'customer', $afterId = 0, $limit = 300)
    {
        $where = [];
        $b = [':after' => (int) $afterId];
        if ($convId && $ticketId) {
            $where[] = '(m.conversation_id = :c OR m.ticket_id = :t)';
            $b[':c'] = (int) $convId;
            $b[':t'] = (int) $ticketId;
        } elseif ($convId) {
            $where[] = 'm.conversation_id = :c';
            $b[':c'] = (int) $convId;
        } else {
            $where[] = 'm.ticket_id = :t';
            $b[':t'] = (int) $ticketId;
        }
        if ($audience === 'customer') {
            $where[] = "m.visibility = 'public'";
        }
        $rows = cdp_csQ("SELECT m.*, TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) AS author_name
            FROM cdb_cs_messages m LEFT JOIN cdb_users u ON u.id = m.author_id
            WHERE " . implode(' AND ', $where) . " AND m.id > :after ORDER BY m.id ASC LIMIT " . (int) $limit, $b);
        $king = cdp_csSetting('king_name', 'King');
        return array_map(function ($m) use ($audience, $king) {
            $name = (string) $m->author_name;
            if ($m->author_type === 'king') {
                $name = $king;
            } elseif ($m->author_type === 'system') {
                $name = 'Swift Lane';
            } elseif ($m->author_type === 'staff' && $audience === 'customer') {
                $parts = preg_split('/\s+/', $name);
                $name = $parts[0] !== '' ? $parts[0] : 'Support team'; // first name only to customers
            }
            $meta = cdp_csDecode($m->meta);
            if ($audience === 'customer') {
                unset($meta['sources'], $meta['tools'], $meta['ai_run']);
            }
            return [
                'id' => (int) $m->id, 'conversation_id' => (int) $m->conversation_id, 'ticket_id' => (int) $m->ticket_id,
                'author_type' => (string) $m->author_type, 'author_id' => (int) $m->author_id, 'author' => $name,
                'visibility' => (string) $m->visibility, 'channel' => (string) $m->channel, 'body' => (string) $m->body,
                'attachments' => cdp_csDecode($m->attachments), 'meta' => $meta,
                'delivery_state' => (string) $m->delivery_state, 'created_at' => (string) $m->created_at,
            ];
        }, $rows);
    }
}

// ---------------------------------------------------------------------------
// Ownership state machine (separate from ticket status)
// ---------------------------------------------------------------------------

if (!function_exists('cdp_csOwnerTransitions')) {

    function cdp_csOwnerTransitions()
    {
        return [
            'ai_active'      => ['handoff_queued', 'human_active', 'resolved'],
            'handoff_queued' => ['human_active', 'resolved', 'ai_active'],
            'human_active'   => ['handoff_queued', 'resolved', 'ai_active'],
            'resolved'       => ['ai_active', 'handoff_queued', 'human_active'],
        ];
    }

    /**
     * Moves a conversation's ownership. Atomic: the UPDATE only applies when
     * the state is still the one read (and the sequence when given), so two
     * racing transitions cannot both win. Returns true when this call moved it.
     */
    function cdp_csOwnerMove($convId, $to, $actorType, $actorId, $reason = '', $expectSeq = null, $ownerUserId = null)
    {
        $conv = cdp_csConv($convId);
        if (!$conv) {
            return false;
        }
        $from = (string) $conv->owner_state;
        $allowed = cdp_csOwnerTransitions();
        if ($from === $to || !in_array($to, $allowed[$from] ?? [], true)) {
            return false;
        }
        $now = cdp_csNow();
        $sets = 'owner_state = :to, owner_seq = owner_seq + 1, updated_at = :n';
        $b = [':to' => $to, ':n' => $now, ':i' => (int) $convId, ':from' => $from];
        if ($to === 'handoff_queued') {
            $sets .= ', handover_at = COALESCE(handover_at, :h), handover_reason = :r, owner_user_id = NULL';
            $b[':h'] = $now;
            $b[':r'] = cdp_csClip($reason, 255);
        } elseif ($to === 'human_active') {
            $sets .= ', owner_user_id = :u, accepted_at = COALESCE(accepted_at, :a)';
            $b[':u'] = $ownerUserId ? (int) $ownerUserId : null;
            $b[':a'] = $now;
        } elseif ($to === 'resolved') {
            $sets .= ', resolved_at = :rs';
            $b[':rs'] = $now;
        } elseif ($to === 'ai_active') {
            $sets .= ', owner_user_id = NULL, ai_turns = 0';
        }
        $where = 'id = :i AND owner_state = :from';
        if ($expectSeq !== null) {
            $where .= ' AND owner_seq = :seq';
            $b[':seq'] = (int) $expectSeq;
        }
        $r = cdp_csX("UPDATE cdb_cs_conversations SET $sets WHERE $where", $b);
        if (!$r['ok'] || $r['rows'] !== 1) {
            return false;
        }
        cdp_csEvent('ownership.' . $to, $conv->ticket_id, $convId, $actorType, $actorId, ['from' => $from, 'to' => $to, 'reason' => cdp_csClip($reason, 255)]);
        return true;
    }

    /** Does this text ask for a person? Checked before King is called. */
    function cdp_csWantsHuman($text)
    {
        $t = mb_strtolower((string) $text);
        return (bool) preg_match('/\b(talk|speak|chat|connect|transfer)\b.{0,30}\b(human|person|agent|someone|somebody|staff|representative|real person|customer service)\b'
            . '|\b(human|real person|live agent|customer care|representative)\s*(please|now)?\s*$|^\s*(agent|human|person)\s*!*\s*$|\bnot (a )?(bot|robot)\b/u', $t);
    }

    /**
     * Hands a conversation to people: queues it, makes sure a ticket exists
     * (with the handover packet as an internal note), routes it, and tells the
     * customer honestly what happens next. Safe to call twice: only the first
     * call moves the state. Returns the ticket id.
     */
    function cdp_csHandover($convId, $reason, $actorType = 'customer', $actorId = null, array $packet = [])
    {
        $conv = cdp_csConv($convId);
        if (!$conv) {
            return 0;
        }
        $moved = in_array($conv->owner_state, ['handoff_queued', 'human_active'], true)
            ? false
            : cdp_csOwnerMove($convId, 'handoff_queued', $actorType, $actorId, $reason);

        $ticketId = (int) $conv->ticket_id;
        $newTicket = !$ticketId;
        if (!$ticketId) {
            $first = cdp_csOne("SELECT body FROM cdb_cs_messages WHERE conversation_id = :c AND author_type = 'customer' ORDER BY id ASC LIMIT 1", [':c' => (int) $convId]);
            $subject = trim((string) ($packet['subject'] ?? '')) ?: cdp_csClip($first ? $first->body : ($conv->subject ?: 'Chat with support'), 120);
            $ticketId = cdp_csTicketCreate([
                'conversation_id' => (int) $convId,
                'customer_id'     => (int) $conv->customer_id,
                'subject'         => $subject,
                'description'     => (string) ($packet['summary'] ?? ''),
                'category'        => (string) ($packet['category'] ?? 'general'),
                'priority'        => (string) ($packet['priority'] ?? 'normal'),
                'channel'         => (string) $conv->channel,
                'language'        => (string) ($conv->language ?: 'en'),
            ], $actorType, $actorId, ['skip_ack' => true]);
        }
        if (($moved || $newTicket) && $ticketId) {
            // The handover packet: what the person picking this up needs, so the customer never repeats themselves.
            $lines = ["**Handover from " . cdp_csSetting('king_name', 'King') . "**", '', '- Reason: ' . ($reason ?: 'Customer asked for a person')];
            foreach (['summary' => 'Summary', 'facts' => 'Verified facts', 'attempted' => 'Already tried', 'outstanding' => 'Still needed', 'entities' => 'Records'] as $k => $label) {
                if (!empty($packet[$k])) {
                    $v = is_array($packet[$k]) ? implode('; ', array_map('strval', $packet[$k])) : (string) $packet[$k];
                    $lines[] = '- ' . $label . ': ' . cdp_csClip($v, 1200);
                }
            }
            cdp_csMessageAdd($convId, $ticketId, 'system', null, implode("\n", $lines), 'internal', ['meta' => ['kind' => 'handover_packet']]);

            $hours = trim((string) cdp_csSetting('business_hours', ''));
            $note = "You're in the queue for a member of our team. They'll see this whole conversation, so you won't need to repeat anything.";
            if ($hours !== '') {
                $note .= "\n\nOur support hours: " . $hours;
            }
            $note .= "\n\n" . trim((string) cdp_csSetting('offline_note', ''));
            $tk = cdp_csTicket($ticketId);
            if ($tk) {
                $note .= "\n\nYour ticket reference is **" . $tk->public_ref . "**.";
            }
            cdp_csMessageAdd($convId, $ticketId, 'system', null, trim($note), 'public', ['meta' => ['kind' => 'handover']]);
            if ($tk && in_array($tk->status, ['resolved', 'closed'], true)) {
                cdp_csTicketUpdate($ticketId, ['status' => 'open'], $actorType, $actorId, null, ['reason' => 'Customer asked for a person again']);
            }
            cdp_csRulesRun('handover.requested', $ticketId, ['reason' => $reason]);
            cdp_csNotifyTeam($ticketId, 'Chat waiting for a person: ' . ($tk ? $tk->subject : ''));
        }
        return $ticketId;
    }

    /**
     * A staff member takes the conversation. King stops answering from this
     * moment. When another person already has it, only an explicit $takeover
     * (a lead reassigning the chat) moves it.
     */
    function cdp_csAccept($convId, $agentId, $takeover = false)
    {
        $conv = cdp_csConv($convId);
        if (!$conv) {
            return ['ok' => false, 'error' => 'Conversation not found.'];
        }
        if ($conv->owner_state === 'human_active' && (int) $conv->owner_user_id === (int) $agentId) {
            return ['ok' => true, 'already' => true];
        }
        if ($conv->owner_state === 'human_active' && $conv->owner_user_id) {
            if (!$takeover) {
                return ['ok' => false, 'owned' => true, 'error' => cdp_csUserName($conv->owner_user_id) . ' already took this conversation.'];
            }
            $r = cdp_csX("UPDATE cdb_cs_conversations SET owner_user_id = :u, owner_seq = owner_seq + 1, updated_at = :n WHERE id = :i AND owner_seq = :s",
                [':u' => (int) $agentId, ':n' => cdp_csNow(), ':i' => (int) $convId, ':s' => (int) $conv->owner_seq]);
            if ($r['rows'] !== 1) {
                return ['ok' => false, 'error' => 'Someone else changed this conversation; reload it.'];
            }
            cdp_csEvent('ownership.transferred', $conv->ticket_id, $convId, 'staff', $agentId, ['from_user' => (int) $conv->owner_user_id]);
        } elseif (!cdp_csOwnerMove($convId, 'human_active', 'staff', $agentId, 'Accepted', (int) $conv->owner_seq, $agentId)) {
            $now = cdp_csConv($convId);
            $who = $now && $now->owner_user_id ? cdp_csUserName($now->owner_user_id) : '';
            return ['ok' => false, 'error' => $who !== '' ? $who . ' already took this conversation.' : 'Someone else changed this conversation; reload it.'];
        }
        $ticketId = (int) $conv->ticket_id;
        if ($ticketId) {
            $tk = cdp_csTicket($ticketId);
            if ($tk && (int) $tk->assignee_id !== (int) $agentId) {
                cdp_csTicketUpdate($ticketId, ['assignee_id' => (int) $agentId] + ($tk->status === 'new' ? ['status' => 'open'] : []), 'staff', $agentId);
            }
        }
        $first = explode(' ', cdp_csUserName($agentId))[0] ?: 'A member of our team';
        cdp_csMessageAdd($convId, $ticketId ?: null, 'system', null, $first . ' from our support team has joined the chat.', 'public', ['meta' => ['kind' => 'joined']]);
        return ['ok' => true];
    }
}

// ---------------------------------------------------------------------------
// Tickets
// ---------------------------------------------------------------------------

if (!function_exists('cdp_csTicketCreate')) {

    function cdp_csTicket($id)
    {
        return cdp_csOne("SELECT * FROM cdb_cs_tickets WHERE id = :i", [':i' => (int) $id]);
    }

    function cdp_csTicketByRef($ref)
    {
        return cdp_csOne("SELECT * FROM cdb_cs_tickets WHERE public_ref = :r", [':r' => strtoupper(trim((string) $ref))]);
    }

    /** The customer's own ticket (never an internal one), or null. */
    function cdp_csTicketForCustomer($id, $customerId)
    {
        return cdp_csOne("SELECT * FROM cdb_cs_tickets WHERE id = :i AND customer_id = :c AND is_internal = 0 AND disposition IN ('none','archived')",
            [':i' => (int) $id, ':c' => (int) $customerId]);
    }

    /** SLA targets for a priority: [first response minutes, resolution hours]. */
    function cdp_csSlaTargets($priority)
    {
        $p = array_key_exists($priority, cdp_csPriorities()) ? $priority : 'normal';
        return [max(1, (int) cdp_csSetting('sla_first_' . $p, '240')), max(1, (int) cdp_csSetting('sla_resolve_' . $p, '48'))];
    }

    /**
     * Opens a ticket. $d: customer_id, conversation_id, subject, description,
     * category, priority, channel, language, team_id, assignee_id, is_internal,
     * contact_*, tags[], links[[type,id,ref]]. Returns the new id (0 on failure).
     */
    function cdp_csTicketCreate(array $d, $actorType, $actorId, array $opt = [])
    {
        $now = cdp_csNow();
        $cats = cdp_csCategories();
        $pri = array_key_exists($d['priority'] ?? '', cdp_csPriorities()) ? $d['priority'] : 'normal';
        list($firstMin, $resolveH) = cdp_csSlaTargets($pri);
        $created = !empty($d['created_at']) ? (string) $d['created_at'] : $now; // original request time is kept
        $base = strtotime($created) ?: time();
        $contact = null;
        if (!empty($d['customer_id'])) {
            $contact = cdp_csOne("SELECT fname, lname, email, phone FROM cdb_users WHERE id = :i", [':i' => (int) $d['customer_id']]);
        }
        $r = cdp_csX("INSERT INTO cdb_cs_tickets (public_ref, conversation_id, customer_id, contact_name, contact_email, contact_phone, subject, description,
                category, priority, status, channel, language, team_id, assignee_id, is_internal, parent_id, sla_policy, sla_first_due, sla_resolve_due,
                last_activity_at, created_by, created_at, updated_at)
            VALUES (:ref, :conv, :cust, :cn, :ce, :cp, :subj, :descr, :cat, :pri, 'new', :ch, :lang, :team, :asg, :int, :par, :pol, :fd, :rd, :la, :cb, :ca, :ua)", [
            ':ref'   => cdp_csNewRef('cdb_cs_tickets', preg_replace('/[^A-Z]/', '', strtoupper(cdp_csSetting('ref_prefix', 'CS'))) ?: 'CS'),
            ':conv'  => !empty($d['conversation_id']) ? (int) $d['conversation_id'] : null,
            ':cust'  => !empty($d['customer_id']) ? (int) $d['customer_id'] : null,
            ':cn'    => $contact ? trim($contact->fname . ' ' . $contact->lname) : cdp_csClip($d['contact_name'] ?? '', 190),
            ':ce'    => $contact ? (string) $contact->email : cdp_csClip($d['contact_email'] ?? '', 190),
            ':cp'    => $contact ? (string) $contact->phone : cdp_csClip($d['contact_phone'] ?? '', 40),
            ':subj'  => cdp_csClip(($d['subject'] ?? '') !== '' ? $d['subject'] : 'Support request', 190),
            ':descr' => (string) ($d['description'] ?? ''),
            ':cat'   => isset($cats[$d['category'] ?? '']) ? $d['category'] : 'general',
            ':pri'   => $pri,
            ':ch'    => array_key_exists($d['channel'] ?? '', cdp_csChannels()) ? $d['channel'] : 'portal',
            ':lang'  => preg_match('/^[a-z]{2,3}(-[A-Za-z]{2})?$/', (string) ($d['language'] ?? '')) ? $d['language'] : 'en',
            ':team'  => !empty($d['team_id']) ? (int) $d['team_id'] : null,
            ':asg'   => !empty($d['assignee_id']) ? (int) $d['assignee_id'] : null,
            ':int'   => !empty($d['is_internal']) ? 1 : 0,
            ':par'   => !empty($d['parent_id']) ? (int) $d['parent_id'] : null,
            ':pol'   => $pri . (cdp_csSetting('sla_approved') === '1' ? '' : ' (provisional)'),
            ':fd'    => date('Y-m-d H:i:s', $base + $firstMin * 60),
            ':rd'    => date('Y-m-d H:i:s', $base + $resolveH * 3600),
            ':la'    => $now, ':cb' => $actorId ? (int) $actorId : null, ':ca' => $created, ':ua' => $now,
        ]);
        if (!$r['ok']) {
            error_log('CS_TICKET_CREATE ' . $r['error']);
            return 0;
        }
        $id = $r['id'];
        if (!empty($d['conversation_id'])) {
            cdp_csX("UPDATE cdb_cs_conversations SET ticket_id = :t WHERE id = :c AND ticket_id IS NULL", [':t' => $id, ':c' => (int) $d['conversation_id']]);
            cdp_csX("UPDATE cdb_cs_messages SET ticket_id = :t WHERE conversation_id = :c AND ticket_id IS NULL", [':t' => $id, ':c' => (int) $d['conversation_id']]);
        }
        foreach ((array) ($d['tags'] ?? []) as $tag) {
            cdp_csTagAdd($id, $tag);
        }
        foreach ((array) ($d['links'] ?? []) as $l) {
            if (is_array($l) && count($l) >= 2) {
                cdp_csLinkAdd($id, $l[0], (int) $l[1], $l[2] ?? '', $actorId);
            }
        }
        cdp_csEvent('ticket.created', $id, $d['conversation_id'] ?? null, $actorType, $actorId, ['channel' => $d['channel'] ?? '', 'category' => $d['category'] ?? 'general', 'priority' => $pri]);

        // Default team when nothing routed it, then the routing / automation rules.
        cdp_csRulesRun('ticket.created', $id, []);
        $tk = cdp_csTicket($id);
        if ($tk && !$tk->team_id) {
            $def = cdp_csOne("SELECT id FROM cdb_cs_teams WHERE is_default = 1 AND active = 1 ORDER BY id LIMIT 1");
            if ($def) {
                cdp_csTicketUpdate($id, ['team_id' => (int) $def->id], 'system', null);
                cdp_csAutoAssign($id);
            }
        }
        // One acknowledgement, with the real reference.
        if (empty($opt['skip_ack']) && empty($d['is_internal']) && !empty($d['customer_id']) && cdp_csSetting('ack_enabled', '1') === '1') {
            cdp_csAcknowledge($id);
        }
        return $id;
    }

    function cdp_csAcknowledge($ticketId)
    {
        $r = cdp_csX("UPDATE cdb_cs_tickets SET ack_sent = 1 WHERE id = :i AND ack_sent = 0", [':i' => (int) $ticketId]);
        if ($r['rows'] !== 1) {
            return false;
        }
        $tk = cdp_csTicket($ticketId);
        $body = "Thanks, we've received your request. Your ticket reference is **" . $tk->public_ref . "**. You can follow it and reply in Help & Support.";
        $mid = cdp_csMessageAdd($tk->conversation_id, $ticketId, 'system', null, $body, 'public', ['meta' => ['kind' => 'ack']]);
        cdp_csEvent('ticket.acknowledged', $ticketId, $tk->conversation_id, 'system', null, ['message_id' => $mid['id']]);
        // An acknowledgement is not a first response: first_response_at stays empty.
        cdp_csNotifyCustomer($ticketId, 'ack', 'We received your request ' . $tk->public_ref, strip_tags(str_replace('**', '', $body)));
        return true;
    }

    /**
     * Changes ticket fields. Pass $expectVersion to refuse a stale edit.
     * Returns ['ok' => bool, 'error' => string, 'version' => int, 'changed' => [...]].
     */
    function cdp_csTicketUpdate($id, array $ch, $actorType, $actorId, $expectVersion = null, array $opt = [])
    {
        $tk = cdp_csTicket($id);
        if (!$tk) {
            return ['ok' => false, 'error' => 'Ticket not found.'];
        }
        if ($expectVersion !== null && (int) $expectVersion !== (int) $tk->version) {
            return ['ok' => false, 'error' => 'Someone changed this ticket since you opened it. Reload to see their change.', 'conflict' => true, 'version' => (int) $tk->version];
        }
        $allowed = [
            'status'      => array_keys(cdp_csStatuses()),
            'priority'    => array_keys(cdp_csPriorities()),
            'category'    => array_keys(cdp_csCategories()),
            'disposition' => array_keys(cdp_csDispositions()),
        ];
        $now = cdp_csNow();
        $sets = [];
        $b = [];
        $changed = [];
        foreach ($ch as $k => $v) {
            if (isset($allowed[$k])) {
                if (!in_array($v, $allowed[$k], true)) {
                    return ['ok' => false, 'error' => 'Unknown ' . $k . ': ' . $v];
                }
            } elseif (in_array($k, ['team_id', 'assignee_id', 'parent_id', 'merged_into'], true)) {
                $v = $v ? (int) $v : null;
            } elseif ($k === 'language') {
                if (!preg_match('/^[a-z]{2,3}(-[A-Za-z]{2})?$/', (string) $v)) {
                    continue;
                }
            } elseif (in_array($k, ['subject', 'pending_reason', 'resolution_code', 'resolution_summary', 'summary', 'language_source', 'description'], true)) {
                $v = $k === 'subject' ? cdp_csClip($v, 190) : (string) $v;
            } else {
                continue;
            }
            $old = $tk->$k;
            if ((string) $old === (string) $v && !($old === null && $v !== null)) {
                continue;
            }
            $sets[] = "$k = :$k";
            $b[":$k"] = $v;
            $changed[$k] = ['from' => $old, 'to' => $v];
        }
        if (!$changed) {
            return ['ok' => true, 'version' => (int) $tk->version, 'changed' => []];
        }

        // Lifecycle side effects.
        if (isset($changed['status'])) {
            $from = $tk->status;
            $to = $changed['status']['to'];
            if ($to === 'resolved' || $to === 'closed') {
                if (!in_array($from, ['resolved', 'closed'], true)) {
                    $sets[] = 'resolved_at = :ra';
                    $b[':ra'] = $now;
                }
                if ($to === 'closed') {
                    $sets[] = 'closed_at = :ca';
                    $b[':ca'] = $now;
                }
            }
            if (in_array($from, ['resolved', 'closed'], true) && !in_array($to, ['resolved', 'closed'], true)) {
                $sets[] = 'reopen_count = reopen_count + 1, resolved_at = NULL, closed_at = NULL';
            }
            // Resolution clock pause while waiting on the customer (approved policy setting).
            if (cdp_csSetting('sla_pause_pending', '1') === '1') {
                if ($to === 'pending_customer' && $from !== 'pending_customer') {
                    $sets[] = 'sla_paused_at = :pa';
                    $b[':pa'] = $now;
                } elseif ($from === 'pending_customer' && $tk->sla_paused_at) {
                    $mins = max(0, (int) round((time() - strtotime($tk->sla_paused_at)) / 60));
                    $sets[] = 'sla_paused_at = NULL, sla_paused_minutes = sla_paused_minutes + :pm'
                        . ($tk->resolve_breached_at ? '' : ', sla_resolve_due = DATE_ADD(sla_resolve_due, INTERVAL :pm2 MINUTE)');
                    $b[':pm'] = $mins;
                    if (!$tk->resolve_breached_at) {
                        $b[':pm2'] = $mins;
                    }
                }
            }
        }
        if (isset($changed['priority']) && !in_array($tk->status, ['resolved', 'closed'], true)) {
            // New targets from the original request time; a breach already recorded stays recorded.
            list($fm, $rh) = cdp_csSlaTargets($changed['priority']['to']);
            $base = strtotime($tk->created_at);
            if (!$tk->first_response_at && !$tk->first_breached_at) {
                $sets[] = 'sla_first_due = :fd';
                $b[':fd'] = date('Y-m-d H:i:s', $base + $fm * 60);
            }
            if (!$tk->resolve_breached_at) {
                $sets[] = 'sla_resolve_due = :rd';
                $b[':rd'] = date('Y-m-d H:i:s', $base + $rh * 3600 + (int) $tk->sla_paused_minutes * 60);
            }
            $sets[] = 'sla_policy = :pol, sla_warned = 0';
            $b[':pol'] = $changed['priority']['to'] . (cdp_csSetting('sla_approved') === '1' ? '' : ' (provisional)');
        }
        $sets[] = 'version = version + 1, updated_at = :ua, last_activity_at = :la';
        $b[':ua'] = $now;
        $b[':la'] = $now;
        $b[':id'] = (int) $id;
        $b[':v'] = (int) $tk->version;
        $r = cdp_csX('UPDATE cdb_cs_tickets SET ' . implode(', ', $sets) . ' WHERE id = :id AND version = :v', $b);
        if (!$r['ok']) {
            return ['ok' => false, 'error' => 'The change could not be saved.'];
        }
        if ($r['rows'] !== 1) {
            return ['ok' => false, 'error' => 'Someone changed this ticket at the same moment. Reload and try again.', 'conflict' => true];
        }
        $data = ['changes' => $changed];
        if (!empty($opt['reason'])) {
            $data['reason'] = cdp_csClip($opt['reason'], 255);
        }
        cdp_csEvent('ticket.updated', $id, $tk->conversation_id, $actorType, $actorId, $data);

        if (isset($changed['status'])) {
            $to = $changed['status']['to'];
            $from = $tk->status;
            if (in_array($from, ['resolved', 'closed'], true) && !in_array($to, ['resolved', 'closed'], true)) {
                cdp_csEvent('ticket.reopened', $id, $tk->conversation_id, $actorType, $actorId, ['cycle' => (int) $tk->reopen_count + 1]);
                cdp_csX("UPDATE cdb_cs_outbox SET status = 'suppressed', last_error = 'Ticket reopened before dispatch' WHERE ticket_id = :t AND kind = 'csat' AND status = 'queued'", [':t' => (int) $id]);
                if (empty($opt['no_rules'])) {
                    cdp_csRulesRun('ticket.reopened', $id, []);
                }
            }
            if ($to === 'resolved' && !in_array($from, ['resolved', 'closed'], true)) {
                cdp_csEvent('ticket.resolved', $id, $tk->conversation_id, $actorType, $actorId, []);
                if ($tk->conversation_id) {
                    $conv = cdp_csConv($tk->conversation_id);
                    if ($conv && $conv->owner_state !== 'resolved') {
                        cdp_csOwnerMove($tk->conversation_id, 'resolved', $actorType, $actorId, 'Ticket resolved');
                    }
                }
                cdp_csScheduleCsat($id);
                if (empty($opt['no_rules'])) {
                    cdp_csRulesRun('ticket.resolved', $id, []);
                }
            }
            if ($to === 'pending_customer' && empty($opt['no_rules'])) {
                cdp_csRulesRun('ticket.awaiting_customer', $id, []);
            }
        }
        if (isset($changed['assignee_id']) && $changed['assignee_id']['to']) {
            cdp_csEvent('ticket.assigned', $id, $tk->conversation_id, $actorType, $actorId, ['to' => (int) $changed['assignee_id']['to']]);
            if ((int) $changed['assignee_id']['to'] !== (int) $actorId) {
                cdp_csNotifyStaff((int) $changed['assignee_id']['to'], $id, 'Ticket ' . $tk->public_ref . ' was assigned to you');
            }
        }
        return ['ok' => true, 'version' => (int) $tk->version + 1, 'changed' => $changed];
    }

    function cdp_csTagAdd($ticketId, $tag)
    {
        $t = cdp_csTag($tag);
        if ($t === '') {
            return false;
        }
        return cdp_csX("INSERT IGNORE INTO cdb_cs_ticket_tags (ticket_id, tag) VALUES (:i, :t)", [':i' => (int) $ticketId, ':t' => $t])['rows'] === 1;
    }

    function cdp_csTagRemove($ticketId, $tag)
    {
        return cdp_csX("DELETE FROM cdb_cs_ticket_tags WHERE ticket_id = :i AND tag = :t", [':i' => (int) $ticketId, ':t' => cdp_csTag($tag)])['ok'];
    }

    function cdp_csTags($ticketId)
    {
        return array_map(function ($r) { return (string) $r->tag; }, cdp_csQ("SELECT tag FROM cdb_cs_ticket_tags WHERE ticket_id = :i ORDER BY tag", [':i' => (int) $ticketId]));
    }

    /**
     * Links a shipment / package / pickup / consolidation to a ticket. For a
     * customer ticket the record must belong to that customer.
     */
    function cdp_csLinkAdd($ticketId, $type, $entityId, $ref = '', $actorId = null)
    {
        $ent = cdp_csEntity($type, $entityId);
        if (!$ent) {
            return false;
        }
        $tk = cdp_csTicket($ticketId);
        if ($tk && $tk->customer_id && $ent['owner'] && (int) $ent['owner'] !== (int) $tk->customer_id) {
            return false;
        }
        return cdp_csX("INSERT IGNORE INTO cdb_cs_links (ticket_id, entity_type, entity_id, entity_ref, created_by, created_at) VALUES (:t, :ty, :e, :r, :u, :n)", [
            ':t' => (int) $ticketId, ':ty' => $type, ':e' => (int) $entityId, ':r' => $ent['ref'], ':u' => $actorId ? (int) $actorId : null, ':n' => cdp_csNow(),
        ])['ok'];
    }

    /** Entity types a ticket can link to, how to read them and where staff / customers open them. */
    function cdp_csEntityTypes()
    {
        return [
            'shipment' => ['label' => 'Shipment', 'table' => 'cdb_add_order', 'id' => 'order_id', 'ref' => "CONCAT(COALESCE(order_prefix,''), COALESCE(order_no,''))", 'owner' => 'sender_id', 'where' => 'COALESCE(a.is_pickup,0) = 0 AND COALESCE(a.is_deleted,0) = 0', 'url' => 'courier_view.php?id='],
            'pickup'   => ['label' => 'Pickup', 'table' => 'cdb_add_order', 'id' => 'order_id', 'ref' => "CONCAT(COALESCE(order_prefix,''), COALESCE(order_no,''))", 'owner' => 'sender_id', 'where' => 'a.is_pickup = 1 AND COALESCE(a.is_deleted,0) = 0', 'url' => 'courier_view.php?id='],
            'package'  => ['label' => 'Package', 'table' => 'cdb_customers_packages', 'id' => 'order_id', 'ref' => "CONCAT(COALESCE(order_prefix,''), COALESCE(order_no,''))", 'owner' => 'sender_id', 'where' => '1 = 1', 'url' => 'customer_packages_view.php?id='],
            'consolidation' => ['label' => 'Consolidation', 'table' => 'cdb_consolidate', 'id' => 'consolidate_id', 'ref' => "CONCAT(COALESCE(c_prefix,''), COALESCE(c_no,''))", 'owner' => 'sender_id', 'where' => '1 = 1', 'url' => 'consolidate_view.php?id='],
        ];
    }

    /** ['type','id','ref','owner','status','url','label'] or null. */
    function cdp_csEntity($type, $id)
    {
        $types = cdp_csEntityTypes();
        if (!isset($types[$type])) {
            return null;
        }
        $m = $types[$type];
        $r = cdp_csOne("SELECT a.{$m['id']} AS id, {$m['ref']} AS ref, a.{$m['owner']} AS owner, a.status_courier AS status_id, st.mod_style AS status_name,
                a.status_invoice AS invoice
            FROM {$m['table']} a LEFT JOIN cdb_styles st ON st.id = a.status_courier WHERE a.{$m['id']} = :i AND ({$m['where']})", [':i' => (int) $id]);
        if (!$r) {
            return null;
        }
        return ['type' => $type, 'id' => (int) $r->id, 'ref' => (string) $r->ref, 'owner' => (int) $r->owner,
            'status' => str_replace('_', ' ', (string) $r->status_name), 'invoice' => $r->invoice === null ? null : (int) $r->invoice,
            'url' => $m['url'] . (int) $r->id, 'label' => $m['label']];
    }

    function cdp_csLinks($ticketId)
    {
        $out = [];
        foreach (cdp_csQ("SELECT * FROM cdb_cs_links WHERE ticket_id = :t ORDER BY id", [':t' => (int) $ticketId]) as $l) {
            $e = cdp_csEntity($l->entity_type, $l->entity_id);
            $out[] = ['id' => (int) $l->id, 'type' => (string) $l->entity_type, 'entity_id' => (int) $l->entity_id,
                'ref' => $e ? $e['ref'] : (string) $l->entity_ref, 'status' => $e ? $e['status'] : 'Record no longer exists',
                'url' => $e ? $e['url'] : '', 'label' => $e ? $e['label'] : ucfirst($l->entity_type)];
        }
        return $out;
    }

    /** The customer's own recent records, for "this is about…" pickers. */
    function cdp_csCustomerEntities($customerId, $limit = 25)
    {
        $out = [];
        foreach (['shipment', 'package', 'pickup'] as $type) {
            $m = cdp_csEntityTypes()[$type];
            foreach (cdp_csQ("SELECT a.{$m['id']} AS id, {$m['ref']} AS ref, st.mod_style AS status_name, a.order_date AS d
                FROM {$m['table']} a LEFT JOIN cdb_styles st ON st.id = a.status_courier
                WHERE a.{$m['owner']} = :c AND ({$m['where']}) ORDER BY a.{$m['id']} DESC LIMIT " . (int) $limit, [':c' => (int) $customerId]) as $r) {
                $out[] = ['type' => $type, 'id' => (int) $r->id, 'ref' => (string) $r->ref, 'status' => str_replace('_', ' ', (string) $r->status_name), 'date' => (string) $r->d, 'label' => $m['label']];
            }
        }
        usort($out, function ($a, $b) { return strcmp($b['date'], $a['date']); });
        return array_slice($out, 0, $limit);
    }

    function cdp_csFollow($ticketId, $userId, $on = true)
    {
        if ($on) {
            return cdp_csX("INSERT IGNORE INTO cdb_cs_followers (ticket_id, user_id) VALUES (:t, :u)", [':t' => (int) $ticketId, ':u' => (int) $userId])['ok'];
        }
        return cdp_csX("DELETE FROM cdb_cs_followers WHERE ticket_id = :t AND user_id = :u", [':t' => (int) $ticketId, ':u' => (int) $userId])['ok'];
    }

    function cdp_csFollowers($ticketId)
    {
        return array_map(function ($r) { return ['id' => (int) $r->user_id, 'name' => cdp_csUserName($r->user_id)]; },
            cdp_csQ("SELECT user_id FROM cdb_cs_followers WHERE ticket_id = :t", [':t' => (int) $ticketId]));
    }

    function cdp_csTaskAdd($ticketId, $title, $dueAt, $assigneeId, $actorId)
    {
        $title = cdp_csClip($title, 255);
        if ($title === '') {
            return 0;
        }
        $due = $dueAt && strtotime($dueAt) ? date('Y-m-d H:i:s', strtotime($dueAt)) : null;
        $r = cdp_csX("INSERT INTO cdb_cs_tasks (ticket_id, title, due_at, assignee_id, created_by, created_at) VALUES (:t, :ti, :d, :a, :c, :n)", [
            ':t' => $ticketId ? (int) $ticketId : null, ':ti' => $title, ':d' => $due, ':a' => $assigneeId ? (int) $assigneeId : null,
            ':c' => $actorId ? (int) $actorId : null, ':n' => cdp_csNow(),
        ]);
        if ($r['ok']) {
            cdp_csEvent('task.created', $ticketId, null, $actorId ? 'staff' : 'system', $actorId, ['task' => $r['id'], 'title' => $title, 'due' => $due]);
            if ($assigneeId && (int) $assigneeId !== (int) $actorId) {
                cdp_csNotifyStaff((int) $assigneeId, $ticketId, 'New support task: ' . $title);
            }
        }
        return $r['id'];
    }

    function cdp_csTasks($ticketId)
    {
        return array_map(function ($r) {
            return ['id' => (int) $r->id, 'title' => (string) $r->title, 'due_at' => (string) $r->due_at, 'assignee' => cdp_csUserName($r->assignee_id),
                'assignee_id' => (int) $r->assignee_id, 'done' => $r->done_at !== null, 'overdue' => $r->done_at === null && $r->due_at && strtotime($r->due_at) < time()];
        }, cdp_csQ("SELECT * FROM cdb_cs_tasks WHERE ticket_id = :t ORDER BY done_at IS NOT NULL, due_at IS NULL, due_at, id", [':t' => (int) $ticketId]));
    }

    /** Open tickets of the same customer, newest first: "is this a duplicate?" */
    function cdp_csSimilarOpen($customerId, $excludeId = 0)
    {
        if (!$customerId) {
            return [];
        }
        return array_map(function ($r) {
            return ['id' => (int) $r->id, 'ref' => (string) $r->public_ref, 'subject' => (string) $r->subject, 'status' => (string) $r->status, 'created_at' => (string) $r->created_at];
        }, cdp_csQ("SELECT id, public_ref, subject, status, created_at FROM cdb_cs_tickets WHERE customer_id = :c AND id <> :x AND disposition = 'none'
            AND status NOT IN ('resolved','closed') ORDER BY id DESC LIMIT 5", [':c' => (int) $customerId, ':x' => (int) $excludeId]));
    }

    /**
     * Merges $sourceId into $targetId. Only tickets of the same customer.
     * Messages keep their original times and visibility; the source is closed
     * with resolution "merged" and points at the target.
     */
    function cdp_csMerge($sourceId, $targetId, $actorId)
    {
        $s = cdp_csTicket($sourceId);
        $t = cdp_csTicket($targetId);
        if (!$s || !$t || $s->id === $t->id) {
            return ['ok' => false, 'error' => 'Choose two different tickets.'];
        }
        if ((int) $s->customer_id !== (int) $t->customer_id) {
            return ['ok' => false, 'error' => 'Only tickets of the same customer can be merged.'];
        }
        if ($s->merged_into) {
            return ['ok' => false, 'error' => 'That ticket was already merged.'];
        }
        cdp_csX("UPDATE cdb_cs_messages SET ticket_id = :t WHERE ticket_id = :s", [':t' => (int) $targetId, ':s' => (int) $sourceId]);
        cdp_csX("INSERT IGNORE INTO cdb_cs_ticket_tags (ticket_id, tag) SELECT :t, tag FROM cdb_cs_ticket_tags WHERE ticket_id = :s", [':t' => (int) $targetId, ':s' => (int) $sourceId]);
        cdp_csX("INSERT IGNORE INTO cdb_cs_links (ticket_id, entity_type, entity_id, entity_ref, created_by, created_at) SELECT :t, entity_type, entity_id, entity_ref, created_by, created_at FROM cdb_cs_links WHERE ticket_id = :s", [':t' => (int) $targetId, ':s' => (int) $sourceId]);
        cdp_csX("INSERT IGNORE INTO cdb_cs_followers (ticket_id, user_id) SELECT :t, user_id FROM cdb_cs_followers WHERE ticket_id = :s", [':t' => (int) $targetId, ':s' => (int) $sourceId]);
        cdp_csTicketUpdate($sourceId, ['status' => 'closed', 'resolution_code' => 'merged', 'merged_into' => (int) $targetId,
            'resolution_summary' => 'Merged into ' . $t->public_ref], 'staff', $actorId, null, ['no_rules' => true]);
        cdp_csMessageAdd(null, $targetId, 'system', null, 'Ticket ' . $s->public_ref . ' ("' . $s->subject . '") was merged into this one by ' . cdp_csUserName($actorId) . '.', 'internal', ['meta' => ['kind' => 'merge']]);
        cdp_csEvent('ticket.merged', $targetId, null, 'staff', $actorId, ['source' => (int) $sourceId, 'source_ref' => $s->public_ref]);
        return ['ok' => true];
    }
}

// ---------------------------------------------------------------------------
// Routing: teams and assignment
// ---------------------------------------------------------------------------

if (!function_exists('cdp_csTeams')) {

    function cdp_csTeams($activeOnly = false)
    {
        $rows = cdp_csQ("SELECT t.*, (SELECT COUNT(*) FROM cdb_cs_team_members m WHERE m.team_id = t.id) AS members FROM cdb_cs_teams t"
            . ($activeOnly ? ' WHERE t.active = 1' : '') . " ORDER BY t.is_default DESC, t.name");
        return array_map(function ($t) {
            return ['id' => (int) $t->id, 'name' => (string) $t->name, 'description' => (string) $t->description, 'assign_mode' => (string) $t->assign_mode,
                'timezone' => (string) $t->timezone, 'is_default' => (int) $t->is_default === 1, 'active' => (int) $t->active === 1, 'members' => (int) $t->members];
        }, $rows);
    }

    function cdp_csTeamMembers($teamId)
    {
        return array_map(function ($m) {
            return ['user_id' => (int) $m->user_id, 'name' => cdp_csUserName($m->user_id), 'is_lead' => (int) $m->is_lead === 1,
                'capacity' => (int) $m->capacity, 'available' => (int) $m->available === 1, 'open' => (int) $m->open_load];
        }, cdp_csQ("SELECT m.*, (SELECT COUNT(*) FROM cdb_cs_tickets t WHERE t.assignee_id = m.user_id AND t.disposition = 'none' AND t.status NOT IN ('resolved','closed')) AS open_load
            FROM cdb_cs_team_members m WHERE m.team_id = :t ORDER BY m.is_lead DESC", [':t' => (int) $teamId]));
    }

    /**
     * Picks an agent in a team: available, under capacity (checked now, not
     * when the rule was written). 'round_robin' takes the longest since last
     * assignment, 'least_loaded' the fewest open tickets. 0 when nobody fits:
     * the ticket then stays in the team queue, never back to King.
     */
    function cdp_csPickAgent($teamId, $mode)
    {
        $cands = array_values(array_filter(cdp_csTeamMembers($teamId), function ($m) { return $m['available'] && $m['open'] < $m['capacity']; }));
        if (!$cands) {
            return 0;
        }
        if ($mode === 'least_loaded') {
            usort($cands, function ($a, $b) { return $a['open'] <=> $b['open']; });
            return $cands[0]['user_id'];
        }
        $last = [];
        foreach (cdp_csQ("SELECT user_id, last_assigned_at FROM cdb_cs_team_members WHERE team_id = :t", [':t' => (int) $teamId]) as $r) {
            $last[(int) $r->user_id] = $r->last_assigned_at ? strtotime($r->last_assigned_at) : 0;
        }
        usort($cands, function ($a, $b) use ($last) { return ($last[$a['user_id']] ?? 0) <=> ($last[$b['user_id']] ?? 0); });
        return $cands[0]['user_id'];
    }

    /** Assigns by the ticket team's mode when the ticket has no assignee. */
    function cdp_csAutoAssign($ticketId, $mode = null)
    {
        $tk = cdp_csTicket($ticketId);
        if (!$tk || $tk->assignee_id || !$tk->team_id) {
            return 0;
        }
        $team = cdp_csOne("SELECT assign_mode FROM cdb_cs_teams WHERE id = :t", [':t' => (int) $tk->team_id]);
        $mode = $mode ?: ($team ? $team->assign_mode : 'manual');
        if ($mode === 'manual') {
            return 0;
        }
        $agent = cdp_csPickAgent($tk->team_id, $mode);
        if ($agent) {
            cdp_csTicketUpdate($ticketId, ['assignee_id' => $agent], 'system', null);
            cdp_csX("UPDATE cdb_cs_team_members SET last_assigned_at = :n WHERE team_id = :t AND user_id = :u", [':n' => cdp_csNow(), ':t' => (int) $tk->team_id, ':u' => $agent]);
        }
        return $agent;
    }
}

// ---------------------------------------------------------------------------
// Rules: routing and automation (trigger -> conditions -> actions)
// ---------------------------------------------------------------------------

if (!function_exists('cdp_csRuleTriggers')) {

    function cdp_csRuleTriggers()
    {
        return [
            'ticket.created'          => 'A ticket is created',
            'message.customer'        => 'The customer writes',
            'handover.requested'      => 'A chat is handed to a person',
            'ticket.awaiting_customer'=> 'A ticket starts waiting on the customer',
            'ticket.resolved'         => 'A ticket is resolved',
            'ticket.reopened'         => 'A ticket is reopened',
            'sla.warning'             => 'An SLA deadline is near',
            'sla.breached'            => 'An SLA deadline is missed',
            'schedule.no_reply'       => 'No customer reply (checked every run of the worker)',
            'csat.poor'               => 'The customer rates support 1 or 2',
            'manual'                  => 'Run by hand from a ticket',
        ];
    }

    /** Facts a condition can test. */
    function cdp_csRuleFields()
    {
        return [
            'channel'        => ['label' => 'Channel', 'type' => 'enum', 'options' => cdp_csChannels()],
            'category'       => ['label' => 'Category', 'type' => 'enum', 'options' => cdp_csCategories()],
            'priority'       => ['label' => 'Priority', 'type' => 'enum', 'options' => cdp_csPriorities()],
            'status'         => ['label' => 'Status', 'type' => 'enum', 'options' => cdp_csStatuses()],
            'language'       => ['label' => 'Language', 'type' => 'enum', 'options' => cdp_csLanguages()],
            'team_id'        => ['label' => 'Team', 'type' => 'team'],
            'assignee'       => ['label' => 'Assignee', 'type' => 'enum', 'options' => ['none' => 'Nobody', 'any' => 'Somebody']],
            'subject'        => ['label' => 'Subject', 'type' => 'text'],
            'message'        => ['label' => 'Latest customer message', 'type' => 'text'],
            'tags'           => ['label' => 'Tags', 'type' => 'tags'],
            'customer'       => ['label' => 'Customer account', 'type' => 'enum', 'options' => ['signed_in' => 'Signed-in customer', 'unknown' => 'No account']],
            'has_link'       => ['label' => 'Linked record', 'type' => 'enum', 'options' => ['shipment' => 'Shipment', 'package' => 'Package', 'pickup' => 'Pickup', 'none' => 'None']],
            'hours_since_customer' => ['label' => 'Hours since the customer last wrote', 'type' => 'number'],
            'hours_open'     => ['label' => 'Hours since created', 'type' => 'number'],
            'reopen_count'   => ['label' => 'Times reopened', 'type' => 'number'],
            'csat'           => ['label' => 'Rating (1-5)', 'type' => 'number'],
            'business_hours' => ['label' => 'Within business days (Mon-Fri)', 'type' => 'enum', 'options' => ['yes' => 'Yes', 'no' => 'No']],
            'handover_reason'=> ['label' => 'Handover reason', 'type' => 'text'],
        ];
    }

    function cdp_csRuleOps()
    {
        return [
            'is' => 'is', 'is_not' => 'is not', 'contains' => 'contains', 'not_contains' => 'does not contain',
            'any_of' => 'is any of (comma list)', 'gt' => 'more than', 'lt' => 'less than', 'empty' => 'is empty', 'not_empty' => 'is not empty',
        ];
    }

    /** What a rule may do. No arbitrary code, SQL or outbound URL. */
    function cdp_csRuleActions()
    {
        return [
            'assign_team'   => ['label' => 'Route to team', 'value' => 'team'],
            'assign_agent'  => ['label' => 'Assign to person', 'value' => 'agent'],
            'auto_assign'   => ['label' => 'Assign inside the team', 'value' => 'enum', 'options' => ['round_robin' => 'Round robin', 'least_loaded' => 'Least loaded']],
            'set_priority'  => ['label' => 'Set priority', 'value' => 'enum', 'options' => cdp_csPriorities()],
            'set_status'    => ['label' => 'Set status', 'value' => 'enum', 'options' => cdp_csStatuses()],
            'set_category'  => ['label' => 'Set category', 'value' => 'enum', 'options' => cdp_csCategories()],
            'add_tag'       => ['label' => 'Add tag', 'value' => 'text'],
            'reply_canned'  => ['label' => 'Send a saved reply to the customer', 'value' => 'canned'],
            'add_note'      => ['label' => 'Add internal note', 'value' => 'textarea'],
            'create_task'   => ['label' => 'Create a task (due in hours: "Title | 24")', 'value' => 'text'],
            'notify_team'   => ['label' => 'Notify the team', 'value' => 'text'],
            'notify_leads'  => ['label' => 'Notify team leads (escalate)', 'value' => 'text'],
            'ai_classify'   => ['label' => 'King: detect language, suggest tags & category', 'value' => 'none'],
            'ai_summarize'  => ['label' => 'King: write the case summary', 'value' => 'none'],
        ];
    }

    /** Starting points shipped with the module. Drafts until someone publishes them. */
    function cdp_csRuleTemplates()
    {
        return [
            'billing_route' => ['name' => 'Billing questions go to Billing', 'trigger_event' => 'ticket.created', 'match_mode' => 'any',
                'conditions' => [['field' => 'category', 'op' => 'is', 'value' => 'billing'], ['field' => 'message', 'op' => 'contains', 'value' => 'invoice'], ['field' => 'message', 'op' => 'contains', 'value' => 'payment']],
                'actions' => [['type' => 'set_category', 'value' => 'billing'], ['type' => 'add_tag', 'value' => 'billing']]],
            'warehouse_missing_ref' => ['name' => 'Missing warehouse reference', 'trigger_event' => 'ticket.created', 'match_mode' => 'any',
                'conditions' => [['field' => 'category', 'op' => 'is', 'value' => 'warehouse'], ['field' => 'message', 'op' => 'contains', 'value' => 'delivered but']],
                'actions' => [['type' => 'set_category', 'value' => 'warehouse'], ['type' => 'add_tag', 'value' => 'warehouse-check'], ['type' => 'create_task', 'value' => 'Check warehouse receipt against carrier tracking | 24']]],
            'claims_priority' => ['name' => 'Lost or damaged: high priority', 'trigger_event' => 'ticket.created', 'match_mode' => 'any',
                'conditions' => [['field' => 'category', 'op' => 'is', 'value' => 'claim'], ['field' => 'message', 'op' => 'contains', 'value' => 'damaged'], ['field' => 'message', 'op' => 'contains', 'value' => 'lost']],
                'actions' => [['type' => 'set_priority', 'value' => 'high'], ['type' => 'add_tag', 'value' => 'claim']]],
            'handover_route' => ['name' => 'Human requested: assign and summarise once', 'trigger_event' => 'handover.requested', 'match_mode' => 'all',
                'conditions' => [['field' => 'assignee', 'op' => 'is', 'value' => 'none']],
                'actions' => [['type' => 'auto_assign', 'value' => 'least_loaded'], ['type' => 'ai_summarize', 'value' => '']]],
            'classify_new' => ['name' => 'Detect language and suggest tags', 'trigger_event' => 'ticket.created', 'match_mode' => 'all', 'conditions' => [],
                'actions' => [['type' => 'ai_classify', 'value' => '']]],
            'no_reply_reminder' => ['name' => 'One reminder after 48h without a reply', 'trigger_event' => 'schedule.no_reply', 'match_mode' => 'all',
                'conditions' => [['field' => 'status', 'op' => 'is', 'value' => 'pending_customer'], ['field' => 'hours_since_customer', 'op' => 'gt', 'value' => '48']],
                'actions' => [['type' => 'reply_canned', 'value' => '']]],
            'sla_escalate' => ['name' => 'SLA nearly missed: tell the leads', 'trigger_event' => 'sla.warning', 'match_mode' => 'all', 'conditions' => [],
                'actions' => [['type' => 'notify_leads', 'value' => 'SLA deadline is near'], ['type' => 'add_tag', 'value' => 'sla-risk']]],
            'poor_csat' => ['name' => 'Poor rating: supervisor follow-up', 'trigger_event' => 'csat.poor', 'match_mode' => 'all', 'conditions' => [],
                'actions' => [['type' => 'notify_leads', 'value' => 'Customer rated support poorly'], ['type' => 'create_task', 'value' => 'Service recovery call | 24']]],
        ];
    }

    /** Everything a condition can look at for one ticket. */
    function cdp_csRuleFacts($tk, array $payload = [])
    {
        $last = cdp_csOne("SELECT body FROM cdb_cs_messages WHERE (ticket_id = :t" . ($tk->conversation_id ? ' OR conversation_id = :c' : '') . ") AND author_type = 'customer' ORDER BY id DESC LIMIT 1",
            $tk->conversation_id ? [':t' => (int) $tk->id, ':c' => (int) $tk->conversation_id] : [':t' => (int) $tk->id]);
        $links = array_map(function ($l) { return $l['type']; }, cdp_csLinks($tk->id));
        $conv = $tk->conversation_id ? cdp_csConv($tk->conversation_id) : null;
        $lastCust = $tk->last_customer_at ?: $tk->created_at;
        return [
            'channel' => (string) $tk->channel, 'category' => (string) $tk->category, 'priority' => (string) $tk->priority,
            'status' => (string) $tk->status, 'language' => (string) $tk->language, 'team_id' => (string) (int) $tk->team_id,
            'assignee' => $tk->assignee_id ? 'any' : 'none', 'subject' => (string) $tk->subject,
            'message' => (string) ($payload['message'] ?? ($last ? $last->body : $tk->description)),
            'tags' => cdp_csTags($tk->id), 'customer' => $tk->customer_id ? 'signed_in' : 'unknown',
            'has_link' => $links ?: ['none'],
            'hours_since_customer' => round((time() - strtotime($lastCust)) / 3600, 1),
            'hours_open' => round((time() - strtotime($tk->created_at)) / 3600, 1),
            'reopen_count' => (int) $tk->reopen_count, 'csat' => $tk->csat_score === null ? '' : (int) $tk->csat_score,
            'business_hours' => in_array((int) date('N'), [6, 7], true) ? 'no' : 'yes',
            'handover_reason' => (string) ($payload['reason'] ?? ($conv ? $conv->handover_reason : '')),
        ];
    }

    /** One condition against the facts. */
    function cdp_csRuleTest(array $c, array $facts)
    {
        $field = (string) ($c['field'] ?? '');
        $op = (string) ($c['op'] ?? 'is');
        $want = trim((string) ($c['value'] ?? ''));
        if (!array_key_exists($field, $facts)) {
            return false;
        }
        $have = $facts[$field];
        $list = is_array($have) ? array_map('strval', $have) : [(string) $have];
        $lower = function ($s) { return mb_strtolower((string) $s); };
        switch ($op) {
            case 'is':          return in_array($lower($want), array_map($lower, $list), true);
            case 'is_not':      return !in_array($lower($want), array_map($lower, $list), true);
            case 'any_of':
                $opts = array_filter(array_map(function ($x) use ($lower) { return $lower(trim($x)); }, explode(',', $want)), 'strlen');
                return (bool) array_intersect($opts, array_map($lower, $list));
            case 'contains':
            case 'not_contains':
                $hit = false;
                foreach ($list as $h) {
                    if ($want !== '' && mb_stripos($h, $want) !== false) {
                        $hit = true;
                        break;
                    }
                }
                return $op === 'contains' ? $hit : !$hit;
            case 'gt':          return is_numeric($list[0]) && is_numeric($want) && (float) $list[0] > (float) $want;
            case 'lt':          return is_numeric($list[0]) && is_numeric($want) && (float) $list[0] < (float) $want;
            case 'empty':       return implode('', $list) === '' || $list === ['none'];
            case 'not_empty':   return implode('', $list) !== '' && $list !== ['none'];
        }
        return false;
    }

    /** [matched, [per-condition true/false]] for ALL / ANY groups. No conditions = always. */
    function cdp_csRuleMatch(array $conditions, $mode, array $facts)
    {
        if (!$conditions) {
            return [true, []];
        }
        $res = [];
        foreach ($conditions as $c) {
            $res[] = cdp_csRuleTest((array) $c, $facts);
        }
        $matched = $mode === 'any' ? in_array(true, $res, true) : !in_array(false, $res, true);
        return [$matched, $res];
    }

    /**
     * Runs the published rules for an event on one ticket.
     * $dryRun: evaluate and describe, change nothing (the simulator).
     * Guards: depth 3 (a rule's action triggering rules), once per ticket per
     * event unless the rule allows more (max_per_ticket), dedupe key per event.
     */
    function cdp_csRulesRun($event, $ticketId, array $payload = [], $dryRun = false, $onlyRuleId = null, array $draftRule = null)
    {
        static $depth = 0;
        if ($depth >= 3) {
            return [['rule' => 0, 'outcome' => 'skipped', 'detail' => 'Loop guard: rules triggered rules 3 levels deep.']];
        }
        $tk = cdp_csTicket($ticketId);
        if (!$tk) {
            return [];
        }
        if ($draftRule) {
            $rules = [(object) $draftRule];
        } else {
            $sql = "SELECT * FROM cdb_cs_rules WHERE trigger_event = :e AND " . ($onlyRuleId ? 'id = :r' : "status = 'published'") . " ORDER BY sort_order, id";
            $b = [':e' => $event];
            if ($onlyRuleId) {
                $b[':r'] = (int) $onlyRuleId;
            }
            $rules = cdp_csQ($sql, $b);
        }
        $out = [];
        $depth++;
        try {
            foreach ($rules as $rule) {
                $tk = cdp_csTicket($ticketId);
                $facts = cdp_csRuleFacts($tk, $payload);
                $conds = is_array($rule->conditions) ? $rule->conditions : cdp_csDecode($rule->conditions);
                $acts = is_array($rule->actions) ? $rule->actions : cdp_csDecode($rule->actions);
                list($matched, $per) = cdp_csRuleMatch($conds, (string) $rule->match_mode, $facts);
                $entry = ['rule' => (int) ($rule->id ?? 0), 'name' => (string) $rule->name, 'matched' => $matched, 'conditions' => $per, 'steps' => []];
                if (!$matched) {
                    $entry['outcome'] = 'no_match';
                    $out[] = $entry;
                    continue;
                }
                $ruleId = (int) ($rule->id ?? 0);
                if (!$dryRun && $ruleId) {
                    $key = $event . ':' . (int) $ticketId . ':' . ($payload['dedupe'] ?? ($payload['message_id'] ?? ''));
                    $max = max(1, (int) ($rule->max_per_ticket ?? 1));
                    $done = cdp_csOne("SELECT COUNT(*) AS n FROM cdb_cs_rule_runs WHERE rule_id = :r AND ticket_id = :t AND outcome IN ('ok','partial')", [':r' => $ruleId, ':t' => (int) $ticketId]);
                    if ($done && (int) $done->n >= $max) {
                        $entry['outcome'] = 'capped';
                        $out[] = $entry;
                        continue;
                    }
                    $claim = cdp_csX("INSERT INTO cdb_cs_rule_runs (rule_id, rule_version, ticket_id, event, dedupe_key, outcome, created_at) VALUES (:r, :v, :t, :e, :k, 'running', :n)",
                        [':r' => $ruleId, ':v' => (int) $rule->version, ':t' => (int) $ticketId, ':e' => $event, ':k' => substr(sha1($key), 0, 40) . ':' . $max . ':' . ((int) ($done->n ?? 0)), ':n' => cdp_csNow()]);
                    if (!$claim['ok']) {
                        $entry['outcome'] = 'duplicate';
                        $out[] = $entry;
                        continue; // the same event already ran this rule
                    }
                    $runId = $claim['id'];
                }
                $fails = 0;
                foreach ($acts as $a) {
                    $step = cdp_csRuleAction((array) $a, $tk, $dryRun);
                    if (empty($step['ok'])) {
                        $fails++;
                    }
                    $entry['steps'][] = $step;
                }
                $entry['outcome'] = $fails === 0 ? 'ok' : ($fails === count($acts) ? 'failed' : 'partial');
                if (!$dryRun && $ruleId) {
                    cdp_csX("UPDATE cdb_cs_rule_runs SET outcome = :o, steps = :s WHERE id = :i", [':o' => $entry['outcome'], ':s' => cdp_csJson($entry['steps']), ':i' => $runId]);
                    cdp_csX("UPDATE cdb_cs_rules SET run_count = run_count + 1, fail_count = fail_count + :f, last_run_at = :n WHERE id = :r",
                        [':f' => $fails ? 1 : 0, ':n' => cdp_csNow(), ':r' => $ruleId]);
                    cdp_csEvent('rule.ran', $ticketId, null, 'rule', $ruleId, ['name' => (string) $rule->name, 'outcome' => $entry['outcome']]);
                }
                $out[] = $entry;
                if (!empty($rule->stop_processing)) {
                    break;
                }
            }
        } finally {
            $depth--;
        }
        return $out;
    }

    /** Performs (or, dry run, describes) one action. */
    function cdp_csRuleAction(array $a, $tk, $dryRun)
    {
        $type = (string) ($a['type'] ?? '');
        $v = trim((string) ($a['value'] ?? ''));
        $defs = cdp_csRuleActions();
        if (!isset($defs[$type])) {
            return ['ok' => false, 'action' => $type, 'detail' => 'Unknown action.'];
        }
        $label = $defs[$type]['label'];
        $say = function ($ok, $detail) use ($type, $label) { return ['ok' => $ok, 'action' => $type, 'label' => $label, 'detail' => $detail]; };
        try {
            switch ($type) {
                case 'assign_team':
                    $team = cdp_csOne("SELECT id, name FROM cdb_cs_teams WHERE id = :t AND active = 1", [':t' => (int) $v]);
                    if (!$team) {
                        return $say(false, 'Team not found or inactive.');
                    }
                    if (!$dryRun) {
                        cdp_csTicketUpdate($tk->id, ['team_id' => (int) $team->id], 'rule', null);
                    }
                    return $say(true, 'Team: ' . $team->name);
                case 'assign_agent':
                    $name = cdp_csUserName((int) $v);
                    if ($name === '') {
                        return $say(false, 'Person not found.');
                    }
                    if (!$dryRun) {
                        cdp_csTicketUpdate($tk->id, ['assignee_id' => (int) $v], 'rule', null);
                    }
                    return $say(true, 'Assignee: ' . $name);
                case 'auto_assign':
                    if (!$tk->team_id) {
                        return $say(false, 'The ticket has no team yet.');
                    }
                    if ($tk->assignee_id) {
                        return $say(true, 'Already assigned; left as is.');
                    }
                    if ($dryRun) {
                        $who = cdp_csPickAgent($tk->team_id, $v ?: 'least_loaded');
                        return $say(true, $who ? 'Would assign ' . cdp_csUserName($who) : 'Nobody available: stays in the team queue.');
                    }
                    $who = cdp_csAutoAssign($tk->id, $v ?: 'least_loaded');
                    return $say(true, $who ? 'Assigned ' . cdp_csUserName($who) : 'Nobody available: stays in the team queue.');
                case 'set_priority':
                case 'set_status':
                case 'set_category':
                    $field = substr($type, 4);
                    if (!$dryRun) {
                        $r = cdp_csTicketUpdate($tk->id, [$field => $v], 'rule', null, null, ['no_rules' => true]);
                        if (empty($r['ok'])) {
                            return $say(false, $r['error'] ?? 'Not changed.');
                        }
                    }
                    return $say(true, ucfirst($field) . ': ' . $v);
                case 'add_tag':
                    if (!$dryRun) {
                        cdp_csTagAdd($tk->id, $v);
                    }
                    return $say(true, 'Tag: ' . cdp_csTag($v));
                case 'reply_canned':
                    $c = cdp_csOne("SELECT * FROM cdb_cs_canned WHERE id = :i", [':i' => (int) $v]);
                    if (!$c) {
                        return $say(false, 'Choose a saved reply.');
                    }
                    if ($tk->is_internal || !$tk->customer_id) {
                        return $say(false, 'Internal ticket: nothing sent to a customer.');
                    }
                    if (!$dryRun) {
                        $body = cdp_csFillVars($c->body, $tk, null);
                        cdp_csMessageAdd($tk->conversation_id, $tk->id, 'system', null, $body, 'public', ['meta' => ['kind' => 'automation', 'canned' => (int) $c->id]]);
                        cdp_csNotifyCustomer($tk->id, 'reply', 'Update on ' . $tk->public_ref, $body, 'rule:' . (int) $c->id);
                    }
                    return $say(true, 'Reply: ' . $c->title);
                case 'add_note':
                    if ($v === '') {
                        return $say(false, 'Empty note.');
                    }
                    if (!$dryRun) {
                        cdp_csMessageAdd(null, $tk->id, 'system', null, $v, 'internal', ['meta' => ['kind' => 'automation']]);
                    }
                    return $say(true, 'Internal note added');
                case 'create_task':
                    $parts = array_map('trim', explode('|', $v));
                    $hours = isset($parts[1]) && is_numeric($parts[1]) ? (float) $parts[1] : 24;
                    if (($parts[0] ?? '') === '') {
                        return $say(false, 'Task needs a title.');
                    }
                    if (!$dryRun) {
                        cdp_csTaskAdd($tk->id, $parts[0], date('Y-m-d H:i:s', time() + (int) ($hours * 3600)), $tk->assignee_id, null);
                    }
                    return $say(true, 'Task: ' . $parts[0] . ' (due in ' . $hours . 'h)');
                case 'notify_team':
                case 'notify_leads':
                    if (!$dryRun) {
                        cdp_csNotifyTeam($tk->id, ($v !== '' ? $v : 'Ticket needs attention') . ': ' . $tk->public_ref, $type === 'notify_leads');
                    }
                    return $say(true, $type === 'notify_leads' ? 'Leads notified' : 'Team notified');
                case 'ai_classify':
                case 'ai_summarize':
                    if ($dryRun) {
                        return $say(true, 'Would ask King (runs only when King is set up).');
                    }
                    require_once __DIR__ . '/support_king.php';
                    $r = $type === 'ai_classify' ? cdp_csCopilotClassify($tk->id, null, true) : cdp_csCopilotSummary($tk->id, null, true);
                    return $say(!empty($r['ok']), !empty($r['ok']) ? 'Done' : ($r['error'] ?? 'King could not run.'));
            }
        } catch (Throwable $e) {
            error_log('CS_RULE_ACTION ' . $type . ' ' . $e->getMessage());
            return $say(false, 'Failed on the server.');
        }
        return $say(false, 'Not handled.');
    }

    /** {customer_name}, {first_name}, {ticket_ref}, {subject}, {agent_name}, {site_name} */
    function cdp_csFillVars($text, $tk, $agentId)
    {
        global $core;
        $name = $tk ? trim((string) $tk->contact_name) : '';
        $first = $name !== '' ? explode(' ', $name)[0] : 'there';
        return strtr((string) $text, [
            '{customer_name}' => $name !== '' ? $name : 'there', '{first_name}' => $first,
            '{ticket_ref}' => $tk ? (string) $tk->public_ref : '', '{subject}' => $tk ? (string) $tk->subject : '',
            '{agent_name}' => $agentId ? explode(' ', cdp_csUserName($agentId))[0] : 'The support team',
            '{site_name}' => is_object($core) && !empty($core->site_name) ? (string) $core->site_name : 'Swift Lane',
        ]);
    }

    function cdp_csRuleSave(array $d, $userId)
    {
        $triggers = cdp_csRuleTriggers();
        if (!isset($triggers[$d['trigger_event'] ?? ''])) {
            return ['ok' => false, 'error' => 'Choose a trigger.'];
        }
        $name = cdp_csClip($d['name'] ?? '', 160);
        if ($name === '') {
            return ['ok' => false, 'error' => 'Give the rule a name.'];
        }
        $fields = cdp_csRuleFields();
        $conds = [];
        foreach ((array) ($d['conditions'] ?? []) as $c) {
            if (isset($fields[$c['field'] ?? '']) && array_key_exists($c['op'] ?? '', cdp_csRuleOps())) {
                $conds[] = ['field' => $c['field'], 'op' => $c['op'], 'value' => cdp_csClip($c['value'] ?? '', 200)];
            }
        }
        $acts = [];
        foreach ((array) ($d['actions'] ?? []) as $a) {
            if (isset(cdp_csRuleActions()[$a['type'] ?? ''])) {
                $acts[] = ['type' => $a['type'], 'value' => mb_substr((string) ($a['value'] ?? ''), 0, 2000)];
            }
        }
        if (!$acts) {
            return ['ok' => false, 'error' => 'Add at least one action.'];
        }
        $status = in_array($d['status'] ?? '', ['draft', 'published', 'paused'], true) ? $d['status'] : 'draft';
        $now = cdp_csNow();
        $b = [':n' => $name, ':k' => ($d['kind'] ?? '') === 'routing' ? 'routing' : 'automation', ':e' => $d['trigger_event'],
            ':m' => ($d['match_mode'] ?? '') === 'any' ? 'any' : 'all', ':c' => cdp_csJson($conds), ':a' => cdp_csJson($acts), ':s' => $status,
            ':o' => (int) ($d['sort_order'] ?? 100), ':sp' => !empty($d['stop_processing']) ? 1 : 0,
            ':mx' => max(1, min(50, (int) ($d['max_per_ticket'] ?? 1))), ':u' => (int) $userId, ':t' => $now];
        $id = (int) ($d['id'] ?? 0);
        if ($id) {
            $old = cdp_csOne("SELECT * FROM cdb_cs_rules WHERE id = :i", [':i' => $id]);
            if (!$old) {
                return ['ok' => false, 'error' => 'Rule not found.'];
            }
            if (isset($d['version']) && (int) $d['version'] !== (int) $old->version) {
                return ['ok' => false, 'error' => 'Someone saved this rule since you opened it. Reload it first.'];
            }
            cdp_csX("INSERT INTO cdb_cs_rule_versions (rule_id, version, snapshot, saved_by, saved_at) VALUES (:r, :v, :s, :u, :t)",
                [':r' => $id, ':v' => (int) $old->version, ':s' => cdp_csJson($old), ':u' => (int) $userId, ':t' => $now]);
            $b[':i'] = $id;
            unset($b[':t']);
            $b[':t2'] = $now;
            cdp_csX("UPDATE cdb_cs_rules SET name = :n, kind = :k, trigger_event = :e, match_mode = :m, conditions = :c, actions = :a, status = :s,
                sort_order = :o, stop_processing = :sp, max_per_ticket = :mx, updated_by = :u, updated_at = :t2, version = version + 1 WHERE id = :i", $b);
        } else {
            $b[':tk'] = isset(cdp_csRuleTemplates()[$d['template_key'] ?? '']) ? $d['template_key'] : null;
            $b[':t2'] = $now;
            $r = cdp_csX("INSERT INTO cdb_cs_rules (name, kind, trigger_event, match_mode, conditions, actions, status, sort_order, stop_processing, max_per_ticket,
                template_key, created_by, updated_by, created_at, updated_at) VALUES (:n, :k, :e, :m, :c, :a, :s, :o, :sp, :mx, :tk, :u, :u, :t, :t2)",
                [':u' => (int) $userId] + $b);
            if (!$r['ok']) {
                return ['ok' => false, 'error' => 'The rule could not be saved.'];
            }
            $id = $r['id'];
        }
        cdp_csEvent('settings.rule_saved', null, null, 'staff', $userId, ['rule' => $id, 'status' => $status]);
        return ['ok' => true, 'id' => $id];
    }

    function cdp_csRules()
    {
        return array_map(function ($r) {
            return ['id' => (int) $r->id, 'name' => (string) $r->name, 'kind' => (string) $r->kind, 'trigger_event' => (string) $r->trigger_event,
                'match_mode' => (string) $r->match_mode, 'conditions' => cdp_csDecode($r->conditions), 'actions' => cdp_csDecode($r->actions),
                'status' => (string) $r->status, 'sort_order' => (int) $r->sort_order, 'stop_processing' => (int) $r->stop_processing === 1,
                'max_per_ticket' => (int) $r->max_per_ticket, 'version' => (int) $r->version, 'run_count' => (int) $r->run_count,
                'fail_count' => (int) $r->fail_count, 'last_run_at' => (string) $r->last_run_at, 'updated_at' => (string) $r->updated_at,
                'updated_by' => cdp_csUserName($r->updated_by)];
        }, cdp_csQ("SELECT * FROM cdb_cs_rules ORDER BY kind DESC, trigger_event, sort_order, id"));
    }
}

// ---------------------------------------------------------------------------
// SLA
// ---------------------------------------------------------------------------

if (!function_exists('cdp_csSlaSweep')) {

    /**
     * Marks warnings and breaches (once each, never erased) and fires the
     * sla.* rules. Cheap; run by the worker and opportunistically by the inbox.
     */
    function cdp_csSlaSweep($limit = 200)
    {
        $now = cdp_csNow();
        $n = 0;
        // First response missed.
        foreach (cdp_csQ("SELECT id FROM cdb_cs_tickets WHERE disposition = 'none' AND is_internal = 0 AND human_first_response_at IS NULL
                AND first_breached_at IS NULL AND sla_first_due < :n AND status NOT IN ('resolved','closed') LIMIT " . (int) $limit, [':n' => $now]) as $r) {
            if (cdp_csX("UPDATE cdb_cs_tickets SET first_breached_at = :n WHERE id = :i AND first_breached_at IS NULL", [':n' => $now, ':i' => (int) $r->id])['rows'] === 1) {
                cdp_csEvent('sla.breached', $r->id, null, 'system', null, ['target' => 'first_response']);
                cdp_csRulesRun('sla.breached', $r->id, ['dedupe' => 'first']);
                $n++;
            }
        }
        // Resolution missed (not while paused).
        foreach (cdp_csQ("SELECT id FROM cdb_cs_tickets WHERE disposition = 'none' AND is_internal = 0 AND resolve_breached_at IS NULL AND sla_paused_at IS NULL
                AND sla_resolve_due < :n AND status NOT IN ('resolved','closed') LIMIT " . (int) $limit, [':n' => $now]) as $r) {
            if (cdp_csX("UPDATE cdb_cs_tickets SET resolve_breached_at = :n WHERE id = :i AND resolve_breached_at IS NULL", [':n' => $now, ':i' => (int) $r->id])['rows'] === 1) {
                cdp_csEvent('sla.breached', $r->id, null, 'system', null, ['target' => 'resolution']);
                cdp_csRulesRun('sla.breached', $r->id, ['dedupe' => 'resolve']);
                $n++;
            }
        }
        // Warning at N% of the resolution window.
        $pct = max(10, min(99, (int) cdp_csSetting('sla_warn_pct', '80'))) / 100;
        foreach (cdp_csQ("SELECT id FROM cdb_cs_tickets WHERE disposition = 'none' AND is_internal = 0 AND sla_warned = 0 AND resolve_breached_at IS NULL
                AND sla_paused_at IS NULL AND status NOT IN ('resolved','closed')
                AND TIMESTAMPDIFF(SECOND, created_at, :n) >= :p * TIMESTAMPDIFF(SECOND, created_at, sla_resolve_due) LIMIT " . (int) $limit, [':n' => $now, ':p' => $pct]) as $r) {
            if (cdp_csX("UPDATE cdb_cs_tickets SET sla_warned = 1 WHERE id = :i AND sla_warned = 0", [':i' => (int) $r->id])['rows'] === 1) {
                cdp_csEvent('sla.warning', $r->id, null, 'system', null, []);
                cdp_csRulesRun('sla.warning', $r->id, []);
                $n++;
            }
        }
        return $n;
    }

    /** Clock state for display: ['label','due','state' => ok|warning|breached|paused|met]. */
    function cdp_csSlaClock($tk)
    {
        $out = [];
        $firstMet = $tk->human_first_response_at ?: null;
        $out['first'] = [
            'label' => 'First response', 'due' => (string) $tk->sla_first_due,
            'state' => $tk->first_breached_at ? 'breached' : ($firstMet ? 'met' : (strtotime($tk->sla_first_due) < time() ? 'breached' : 'ok')),
            'met_at' => (string) $firstMet,
        ];
        $resolved = in_array($tk->status, ['resolved', 'closed'], true);
        $st = $tk->resolve_breached_at ? 'breached' : ($resolved ? 'met' : ($tk->sla_paused_at ? 'paused' : (strtotime($tk->sla_resolve_due) < time() ? 'breached' : ($tk->sla_warned ? 'warning' : 'ok'))));
        $out['resolve'] = ['label' => 'Resolution', 'due' => (string) $tk->sla_resolve_due, 'state' => $st, 'paused_minutes' => (int) $tk->sla_paused_minutes];
        $out['policy'] = (string) $tk->sla_policy;
        return $out;
    }
}

// ---------------------------------------------------------------------------
// Notifications: in-app now, other channels through the outbox
// ---------------------------------------------------------------------------

if (!function_exists('cdp_csNotifyCustomer')) {

    /** In-app bell entry (the app's own notifications table). */
    function cdp_csInApp($userId, $ticketId, $text)
    {
        if (!$userId) {
            return;
        }
        $r = cdp_csX("INSERT INTO cdb_notifications (user_id, order_id, notification_description, shipping_type, notification_date) VALUES (:u, :o, :d, 9, :n)",
            [':u' => (int) $userId, ':o' => (int) $ticketId, ':d' => cdp_csEsc(cdp_csClip($text, 240)), ':n' => cdp_csNow()]);
        if ($r['ok'] && $r['id']) {
            cdp_csX("INSERT INTO cdb_notifications_users (user_id, notification_id) VALUES (:u, :n)", [':u' => (int) $userId, ':n' => $r['id']]);
        }
    }

    /**
     * Tells the customer: in-app immediately, then one outbox row per enabled
     * channel. The dedupe key stops the same update going out twice.
     */
    function cdp_csNotifyCustomer($ticketId, $kind, $subject, $body, $dedupe = null, $sendAfter = null)
    {
        $tk = cdp_csTicket($ticketId);
        if (!$tk || !$tk->customer_id || $tk->is_internal) {
            return 0;
        }
        cdp_csInApp($tk->customer_id, $ticketId, $subject);
        $channels = array_intersect(array_map('trim', explode(',', (string) cdp_csSetting('notify_channels', 'email'))), ['email', 'whatsapp', 'sms']);
        if ($kind === 'csat') {
            $channels = $channels ?: ['email'];
        }
        $n = 0;
        foreach ($channels as $ch) {
            $key = 'cs:' . $kind . ':' . (int) $ticketId . ':' . $ch . ':' . ($dedupe ?? substr(sha1((string) $body), 0, 16));
            $r = cdp_csX("INSERT IGNORE INTO cdb_cs_outbox (ticket_id, user_id, kind, channel, subject, body, dedupe_key, status, send_after, created_at)
                VALUES (:t, :u, :k, :c, :s, :b, :d, 'queued', :sa, :n)", [
                ':t' => (int) $ticketId, ':u' => (int) $tk->customer_id, ':k' => $kind, ':c' => $ch, ':s' => cdp_csClip($subject, 190),
                ':b' => (string) $body, ':d' => substr($key, 0, 160), ':sa' => $sendAfter ?: cdp_csNow(), ':n' => cdp_csNow(),
            ]);
            $n += $r['rows'];
        }
        return $n;
    }

    function cdp_csNotifyStaff($userId, $ticketId, $text)
    {
        cdp_csInApp($userId, $ticketId, $text);
    }

    /** The ticket's team (or its leads), plus followers. */
    function cdp_csNotifyTeam($ticketId, $text, $leadsOnly = false)
    {
        $tk = cdp_csTicket($ticketId);
        if (!$tk) {
            return;
        }
        $ids = [];
        if ($tk->team_id) {
            foreach (cdp_csQ("SELECT user_id FROM cdb_cs_team_members WHERE team_id = :t" . ($leadsOnly ? ' AND is_lead = 1' : ' AND available = 1'), [':t' => (int) $tk->team_id]) as $m) {
                $ids[(int) $m->user_id] = true;
            }
        }
        foreach (cdp_csQ("SELECT user_id FROM cdb_cs_followers WHERE ticket_id = :t", [':t' => (int) $ticketId]) as $f) {
            $ids[(int) $f->user_id] = true;
        }
        if ($tk->assignee_id) {
            $ids[(int) $tk->assignee_id] = true;
        }
        foreach (array_keys($ids) as $uid) {
            cdp_csNotifyStaff($uid, $ticketId, $text);
        }
    }

    function cdp_csScheduleCsat($ticketId)
    {
        if (cdp_csSetting('csat_enabled', '1') !== '1') {
            return;
        }
        $tk = cdp_csTicket($ticketId);
        if (!$tk || $tk->csat_score !== null || !$tk->customer_id || $tk->is_internal) {
            return;
        }
        $delay = max(0, (int) cdp_csSetting('csat_delay_min', '60')) * 60;
        cdp_csNotifyCustomer($ticketId, 'csat', 'How did we do on ' . $tk->public_ref . '?',
            "Your ticket " . $tk->public_ref . " (" . $tk->subject . ") was resolved. Please rate our help in Help & Support: it takes a few seconds.",
            'cycle' . (int) $tk->reopen_count, cdp_csNow($delay));
    }

    /**
     * Delivers due outbox rows through the app's senders. Re-checks each row
     * before sending (a survey for a reopened ticket is suppressed). Bounded
     * retries with back-off; after 5 attempts a row is dead.
     */
    function cdp_csOutboxRun($limit = 30)
    {
        $rows = cdp_csQ("SELECT * FROM cdb_cs_outbox WHERE status IN ('queued','retry') AND send_after <= :n ORDER BY id LIMIT " . (int) $limit, [':n' => cdp_csNow()]);
        if (!$rows) {
            return ['sent' => 0, 'failed' => 0, 'suppressed' => 0];
        }
        $stats = ['sent' => 0, 'failed' => 0, 'suppressed' => 0];
        $canSend = false;
        try {
            require_once __DIR__ . '/push_notify.php';
            $canSend = function_exists('cdp_pushNotifyUser') && function_exists('cdp_getSettingsCourier');
        } catch (Throwable $e) {
            $canSend = false;
        }
        $settings = $canSend ? cdp_getSettingsCourier() : null;
        foreach ($rows as $o) {
            // Claim the row so two workers never send it twice.
            if (cdp_csX("UPDATE cdb_cs_outbox SET status = 'sending', attempts = attempts + 1 WHERE id = :i AND status IN ('queued','retry')", [':i' => (int) $o->id])['rows'] !== 1) {
                continue;
            }
            $tk = $o->ticket_id ? cdp_csTicket($o->ticket_id) : null;
            if ($o->kind === 'csat' && (!$tk || $tk->status !== 'resolved' || $tk->csat_score !== null)) {
                cdp_csX("UPDATE cdb_cs_outbox SET status = 'suppressed', last_error = 'No longer resolved or already rated' WHERE id = :i", [':i' => (int) $o->id]);
                $stats['suppressed']++;
                continue;
            }
            $u = cdp_csOne("SELECT * FROM cdb_users WHERE id = :i", [':i' => (int) $o->user_id]);
            $err = '';
            $ok = false;
            if (!$canSend || !$settings) {
                $err = 'Messaging is not configured on this server.';
            } elseif (!$u) {
                $err = 'Customer account not found.';
            } else {
                try {
                    $res = cdp_pushNotifyUser($u, (string) $o->subject, (string) $o->body, $settings,
                        ['source' => 'customer_service', 'entity_type' => 'cs_ticket', 'entity_id' => (int) $o->ticket_id], $o->channel);
                    $st = $res[$o->channel]['status'] ?? 'failed';
                    $ok = $st === 'sent';
                    if ($st === 'skipped') {
                        cdp_csX("UPDATE cdb_cs_outbox SET status = 'suppressed', last_error = :e WHERE id = :i", [':e' => cdp_csClip($res[$o->channel]['detail'] ?? 'Skipped', 255), ':i' => (int) $o->id]);
                        $stats['suppressed']++;
                        continue;
                    }
                    $err = $ok ? '' : (string) ($res[$o->channel]['detail'] ?? 'Provider refused the message.');
                } catch (Throwable $e) {
                    $err = $e->getMessage();
                }
            }
            if ($ok) {
                // "sent" means the provider accepted it, not that the customer read it.
                cdp_csX("UPDATE cdb_cs_outbox SET status = 'sent', sent_at = :n, last_error = NULL WHERE id = :i", [':n' => cdp_csNow(), ':i' => (int) $o->id]);
                $stats['sent']++;
            } else {
                $att = (int) $o->attempts + 1;
                $dead = $att >= 5;
                cdp_csX("UPDATE cdb_cs_outbox SET status = :s, last_error = :e, send_after = :sa WHERE id = :i", [
                    ':s' => $dead ? 'dead' : 'retry', ':e' => cdp_csClip($err, 255), ':sa' => cdp_csNow(min(3600 * 6, 60 * (2 ** $att))), ':i' => (int) $o->id]);
                $stats['failed']++;
            }
        }
        return $stats;
    }
}

// ---------------------------------------------------------------------------
// Inbox: views, filters, list
// ---------------------------------------------------------------------------

if (!function_exists('cdp_csInboxViews')) {

    /** Built-in views: key => [label, SQL condition]. Disposition views exclude each other. */
    function cdp_csInboxViews($uid)
    {
        $u = (int) $uid;
        $live = "t.disposition = 'none'";
        $openish = "t.status NOT IN ('resolved','closed')";
        return [
            'all'         => ['All open', "$live AND $openish"],
            'mine'        => ['Mine', "$live AND $openish AND t.assignee_id = $u"],
            'unassigned'  => ['Unassigned', "$live AND $openish AND t.assignee_id IS NULL"],
            'queued'      => ['Waiting for a person', "$live AND c.owner_state = 'handoff_queued'"],
            'ai'          => ['King is answering', "$live AND c.owner_state = 'ai_active' AND $openish"],
            'new'         => ['New', "$live AND t.status = 'new'"],
            'open'        => ['Open', "$live AND t.status = 'open'"],
            'pending_customer' => ['Waiting on customer', "$live AND t.status = 'pending_customer'"],
            'pending_internal' => ['Waiting on our team', "$live AND t.status = 'pending_internal'"],
            'on_hold'     => ['On hold', "$live AND t.status = 'on_hold'"],
            'sla_risk'    => ['SLA at risk', "$live AND $openish AND (t.sla_warned = 1 OR t.sla_resolve_due < DATE_ADD(NOW(), INTERVAL 2 HOUR)) AND t.resolve_breached_at IS NULL"],
            'overdue'     => ['Overdue', "$live AND $openish AND (t.first_breached_at IS NOT NULL OR t.resolve_breached_at IS NOT NULL)"],
            'followed'    => ['Following', "$live AND EXISTS (SELECT 1 FROM cdb_cs_followers f WHERE f.ticket_id = t.id AND f.user_id = $u)"],
            'internal'    => ['Staff requests', "$live AND t.is_internal = 1 AND $openish"],
            'resolved'    => ['Resolved', "$live AND t.status = 'resolved'"],
            'closed'      => ['Closed', "$live AND t.status = 'closed'"],
            'archived'    => ['Archived', "t.disposition = 'archived'"],
            'spam'        => ['Spam', "t.disposition = 'spam'"],
            'trash'       => ['Trash', "t.disposition = 'trash'"],
        ];
    }

    /** Filter conditions from a request, all bound. */
    function cdp_csInboxWhere(array $f, $uid, array &$b)
    {
        $views = cdp_csInboxViews($uid);
        $view = isset($views[$f['view'] ?? '']) ? $f['view'] : 'all';
        $w = ['(' . $views[$view][1] . ')'];
        foreach (['status' => cdp_csStatuses(), 'priority' => cdp_csPriorities(), 'category' => cdp_csCategories(), 'channel' => cdp_csChannels()] as $k => $opts) {
            if (!empty($f[$k]) && isset($opts[$f[$k]])) {
                $w[] = "t.$k = :f_$k";
                $b[":f_$k"] = $f[$k];
            }
        }
        if (!empty($f['team_id'])) {
            $w[] = 't.team_id = :f_team';
            $b[':f_team'] = (int) $f['team_id'];
        }
        if (isset($f['assignee_id']) && $f['assignee_id'] !== '') {
            if ((string) $f['assignee_id'] === '0') {
                $w[] = 't.assignee_id IS NULL';
            } else {
                $w[] = 't.assignee_id = :f_asg';
                $b[':f_asg'] = (int) $f['assignee_id'];
            }
        }
        if (!empty($f['tag'])) {
            $w[] = 'EXISTS (SELECT 1 FROM cdb_cs_ticket_tags g WHERE g.ticket_id = t.id AND g.tag = :f_tag)';
            $b[':f_tag'] = cdp_csTag($f['tag']);
        }
        if (!empty($f['language'])) {
            $w[] = 't.language = :f_lang';
            $b[':f_lang'] = (string) $f['language'];
        }
        if (($d = (string) ($f['from'] ?? '')) !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            $w[] = 't.created_at >= :f_from';
            $b[':f_from'] = $d . ' 00:00:00';
        }
        if (($d = (string) ($f['to'] ?? '')) !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            $w[] = 't.created_at <= :f_to';
            $b[':f_to'] = $d . ' 23:59:59';
        }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            // Ticket text, references, customer name / e-mail / phone / locker, linked tracking numbers.
            $w[] = "(t.public_ref LIKE :q1 OR t.subject LIKE :q2 OR t.contact_name LIKE :q3 OR t.contact_email LIKE :q4 OR t.contact_phone LIKE :q5
                OR cu.locker LIKE :q6 OR EXISTS (SELECT 1 FROM cdb_cs_links l WHERE l.ticket_id = t.id AND l.entity_ref LIKE :q7)
                OR EXISTS (SELECT 1 FROM cdb_cs_messages m WHERE m.ticket_id = t.id AND m.body LIKE :q8))";
            $like = '%' . $q . '%';
            for ($i = 1; $i <= 8; $i++) {
                $b[":q$i"] = $like;
            }
        }
        return implode(' AND ', $w);
    }

    function cdp_csInboxList(array $f, $uid, $page = 1, $per = 25)
    {
        $b = [];
        $where = cdp_csInboxWhere($f, $uid, $b);
        $from = "cdb_cs_tickets t LEFT JOIN cdb_cs_conversations c ON c.id = t.conversation_id LEFT JOIN cdb_users cu ON cu.id = t.customer_id";
        $total = cdp_csOne("SELECT COUNT(*) AS n FROM $from WHERE $where", $b);
        $sorts = [
            'activity' => 't.last_activity_at DESC', 'created' => 't.created_at DESC', 'oldest' => 't.created_at ASC',
            'priority' => "FIELD(t.priority,'urgent','high','normal','low'), t.last_activity_at DESC", 'sla' => 't.sla_resolve_due ASC',
        ];
        $order = $sorts[$f['sort'] ?? ''] ?? $sorts['activity'];
        $per = max(5, min(100, (int) $per));
        $page = max(1, (int) $page);
        $rows = cdp_csQ("SELECT t.*, c.owner_state, cu.locker,
                (SELECT m.body FROM cdb_cs_messages m WHERE m.ticket_id = t.id AND m.visibility = 'public' ORDER BY m.id DESC LIMIT 1) AS preview,
                (SELECT m.author_type FROM cdb_cs_messages m WHERE m.ticket_id = t.id AND m.visibility = 'public' ORDER BY m.id DESC LIMIT 1) AS last_author,
                (SELECT GROUP_CONCAT(g.tag ORDER BY g.tag SEPARATOR ',') FROM cdb_cs_ticket_tags g WHERE g.ticket_id = t.id) AS tags,
                (SELECT l.entity_ref FROM cdb_cs_links l WHERE l.ticket_id = t.id ORDER BY l.id LIMIT 1) AS link_ref
            FROM $from WHERE $where ORDER BY $order LIMIT " . (($page - 1) * $per) . ", $per", $b);
        $teams = [];
        foreach (cdp_csTeams() as $tm) {
            $teams[$tm['id']] = $tm['name'];
        }
        return [
            'total' => (int) ($total->n ?? 0), 'page' => $page, 'per' => $per,
            'rows' => array_map(function ($t) use ($teams) { return cdp_csTicketRow($t, $teams); }, $rows),
        ];
    }

    function cdp_csTicketRow($t, array $teams = [])
    {
        $clock = cdp_csSlaClock($t);
        return [
            'id' => (int) $t->id, 'ref' => (string) $t->public_ref, 'subject' => (string) $t->subject,
            'customer' => (string) ($t->contact_name ?: 'No account'), 'customer_email' => (string) $t->contact_email,
            'customer_id' => (int) $t->customer_id, 'locker' => (string) ($t->locker ?? ''),
            'preview' => cdp_csClip($t->preview ?? '', 110), 'awaiting_us' => ($t->last_author ?? '') === 'customer',
            'status' => (string) $t->status, 'priority' => (string) $t->priority, 'category' => (string) $t->category,
            'channel' => (string) $t->channel, 'language' => (string) $t->language, 'owner_state' => (string) ($t->owner_state ?? ''),
            'team' => $teams[(int) $t->team_id] ?? '', 'team_id' => (int) $t->team_id,
            'assignee' => cdp_csUserName($t->assignee_id), 'assignee_id' => (int) $t->assignee_id,
            'tags' => $t->tags ? explode(',', $t->tags) : [], 'link_ref' => (string) ($t->link_ref ?? ''),
            'is_internal' => (int) $t->is_internal === 1, 'disposition' => (string) $t->disposition,
            'sla' => $clock['resolve']['state'] === 'met' ? $clock['first'] : $clock['resolve'],
            'sla_first' => $clock['first'], 'sla_resolve' => $clock['resolve'],
            'last_activity_at' => (string) $t->last_activity_at, 'created_at' => (string) $t->created_at, 'version' => (int) $t->version,
        ];
    }

    /** Counts for every built-in view in one pass per view (indexed, small). */
    function cdp_csInboxCounts($uid)
    {
        $out = [];
        foreach (cdp_csInboxViews($uid) as $k => $v) {
            if (in_array($k, ['resolved', 'closed', 'archived', 'spam', 'trash'], true)) {
                continue; // unbounded archives: no badge
            }
            $r = cdp_csOne("SELECT COUNT(*) AS n FROM cdb_cs_tickets t LEFT JOIN cdb_cs_conversations c ON c.id = t.conversation_id WHERE " . $v[1]);
            $out[$k] = (int) ($r->n ?? 0);
        }
        return $out;
    }

    function cdp_csSavedViews($uid)
    {
        return array_map(function ($v) use ($uid) {
            return ['id' => (int) $v->id, 'name' => (string) $v->name, 'filters' => cdp_csDecode($v->filters), 'shared' => (int) $v->shared === 1,
                'mine' => (int) $v->user_id === (int) $uid, 'owner' => cdp_csUserName($v->user_id)];
        }, cdp_csQ("SELECT * FROM cdb_cs_views WHERE user_id = :u OR shared = 1 ORDER BY shared, name", [':u' => (int) $uid]));
    }
}

// ---------------------------------------------------------------------------
// Presence and collision
// ---------------------------------------------------------------------------

if (!function_exists('cdp_csPresencePing')) {

    function cdp_csPresencePing($ticketId, $uid, $typing = false)
    {
        $now = cdp_csNow();
        cdp_csX("INSERT INTO cdb_cs_presence (ticket_id, user_id, seen_at, typing_at) VALUES (:t, :u, :n, :ty)
            ON DUPLICATE KEY UPDATE seen_at = VALUES(seen_at), typing_at = " . ($typing ? 'VALUES(typing_at)' : 'typing_at'),
            [':t' => (int) $ticketId, ':u' => (int) $uid, ':n' => $now, ':ty' => $typing ? $now : null]);
    }

    /** Other staff on this ticket in the last 40 seconds, and whether they are typing. */
    function cdp_csPresence($ticketId, $exceptUid)
    {
        return array_map(function ($p) {
            return ['id' => (int) $p->user_id, 'name' => cdp_csUserName($p->user_id),
                'typing' => $p->typing_at && strtotime($p->typing_at) > time() - 8];
        }, cdp_csQ("SELECT * FROM cdb_cs_presence WHERE ticket_id = :t AND user_id <> :u AND seen_at > :since",
            [':t' => (int) $ticketId, ':u' => (int) $exceptUid, ':since' => cdp_csNow(-40)]));
    }
}

// ---------------------------------------------------------------------------
// Staff reply (with collision control) and proposals
// ---------------------------------------------------------------------------

if (!function_exists('cdp_csStaffSend')) {

    /**
     * A staff reply or note. $lastSeenId: the newest message the agent had on
     * screen; when another person replied publicly after it, the send is
     * refused (unless $force) so two agents do not answer at cross purposes.
     * A public reply on a chat King owns takes the chat first, atomically.
     */
    function cdp_csStaffSend($ticketId, $agentId, $body, $mode, array $opt = [])
    {
        $tk = cdp_csTicket($ticketId);
        if (!$tk) {
            return ['ok' => false, 'error' => 'Ticket not found.'];
        }
        $body = trim((string) $body);
        $atts = (array) ($opt['attachments'] ?? []);
        if ($body === '' && !$atts) {
            return ['ok' => false, 'error' => 'Write something first.'];
        }
        $isNote = $mode === 'note';
        if (!$isNote && ($tk->is_internal || !$tk->customer_id)) {
            return ['ok' => false, 'error' => 'This ticket has no customer to reply to. Use an internal note.'];
        }
        if (!$isNote) {
            $body = cdp_csFillVars($body, $tk, $agentId); // {first_name} etc. from saved replies typed by hand
        }
        $lastSeen = (int) ($opt['last_seen_id'] ?? 0);
        if (!$isNote && empty($opt['force']) && $lastSeen > 0) {
            $newer = cdp_csOne("SELECT m.author_id, m.author_type FROM cdb_cs_messages m WHERE (m.ticket_id = :t" . ($tk->conversation_id ? ' OR m.conversation_id = :c' : '') . ")
                AND m.id > :s AND m.visibility = 'public' AND m.author_type IN ('staff','king') AND NOT (m.author_type = 'staff' AND m.author_id = :u) ORDER BY m.id DESC LIMIT 1",
                ($tk->conversation_id ? [':c' => (int) $tk->conversation_id] : []) + [':t' => (int) $ticketId, ':s' => $lastSeen, ':u' => (int) $agentId]);
            if ($newer) {
                $who = $newer->author_type === 'king' ? cdp_csSetting('king_name', 'King') : cdp_csUserName($newer->author_id);
                return ['ok' => false, 'conflict' => true, 'error' => $who . ' replied to the customer after you opened this. Read their reply, then send again (or send anyway).'];
            }
        }
        if (!$isNote && $tk->conversation_id) {
            $conv = cdp_csConv($tk->conversation_id);
            if ($conv && $conv->owner_state !== 'human_active') {
                cdp_csOwnerMove($conv->id, 'human_active', 'staff', $agentId, 'Staff replied', null, $agentId);
            }
        }
        $m = cdp_csMessageAdd($tk->conversation_id, $ticketId, 'staff', $agentId, $body, $isNote ? 'internal' : 'public', [
            'client_msg_id' => $opt['client_msg_id'] ?? null, 'attachments' => $atts, 'channel' => 'web',
            'meta' => !empty($opt['ai_assisted']) ? ['ai_assisted' => true] : [],
        ]);
        if (!$m['id']) {
            return ['ok' => false, 'error' => 'The message could not be saved.'];
        }
        if ($m['duplicate']) {
            return ['ok' => true, 'message_id' => $m['id'], 'duplicate' => true];
        }
        $changes = [];
        if (!$isNote) {
            $now = cdp_csNow();
            cdp_csX("UPDATE cdb_cs_tickets SET first_response_at = COALESCE(first_response_at, :n), human_first_response_at = COALESCE(human_first_response_at, :n2) WHERE id = :i",
                [':n' => $now, ':n2' => $now, ':i' => (int) $ticketId]);
            if (!$tk->assignee_id) {
                $changes['assignee_id'] = (int) $agentId;
            }
            if ($tk->status === 'new') {
                $changes['status'] = 'open';
            }
            cdp_csNotifyCustomer($ticketId, 'reply', 'New reply on ' . $tk->public_ref . ': ' . $tk->subject,
                explode(' ', cdp_csUserName($agentId))[0] . " from support replied:\n\n" . cdp_csClip($body, 1500) . "\n\nReply in Help & Support.", 'msg' . $m['id']);
        } else {
            // @mentions in a note notify those people (never the customer).
            if (strpos($body, '@') !== false) {
                foreach (cdp_csAgents() as $a) {
                    $first = explode(' ', $a['name'])[0];
                    if ($first !== '' && (int) $a['id'] !== (int) $agentId
                        && (preg_match('/@' . preg_quote($a['name'], '/') . '\b/iu', $body) || preg_match('/@' . preg_quote($first, '/') . '\b/iu', $body))) {
                        cdp_csNotifyStaff($a['id'], $ticketId, cdp_csUserName($agentId) . ' mentioned you on ' . $tk->public_ref);
                        cdp_csFollow($ticketId, $a['id']);
                    }
                }
            }
        }
        if (!empty($opt['set_status']) && array_key_exists($opt['set_status'], cdp_csStatuses())) {
            $changes['status'] = $opt['set_status'];
        }
        if ($changes) {
            cdp_csTicketUpdate($ticketId, $changes, 'staff', $agentId);
        } else {
            cdp_csX("UPDATE cdb_cs_tickets SET last_activity_at = :n, version = version + 1 WHERE id = :i", [':n' => cdp_csNow(), ':i' => (int) $ticketId]);
        }
        cdp_csEvent($isNote ? 'note.added' : 'reply.sent', $ticketId, $tk->conversation_id, 'staff', $agentId, ['message_id' => $m['id']]);
        return ['ok' => true, 'message_id' => $m['id']];
    }

    function cdp_csProposalKinds()
    {
        return [
            'refund' => 'Refund', 'discount' => 'Discount or fee waiver', 'payment_review' => 'Payment check / override',
            'claim' => 'Lost or damaged package claim', 'ownership' => 'Package ownership / transfer', 'address_change' => 'Delivery address change',
            'other' => 'Other request needing approval',
        ];
    }

    /**
     * A request that needs an authorised person (King and staff can create;
     * only administrators decide). Bound to the exact details by a hash and
     * to an expiry, so a later "yes" cannot approve something else.
     */
    function cdp_csProposalCreate($ticketId, $kind, array $details, $byType, $byId)
    {
        if (!isset(cdp_csProposalKinds()[$kind])) {
            $kind = 'other';
        }
        $tk = cdp_csTicket($ticketId);
        if (!$tk) {
            return 0;
        }
        $payload = cdp_csJson(['ticket' => (int) $ticketId, 'customer' => (int) $tk->customer_id, 'kind' => $kind, 'details' => $details]);
        $r = cdp_csX("INSERT INTO cdb_cs_proposals (ticket_id, kind, details, payload_hash, status, requested_by_type, requested_by, ticket_version, expires_at, created_at)
            VALUES (:t, :k, :d, :h, 'pending', :bt, :b, :v, :e, :n)", [
            ':t' => (int) $ticketId, ':k' => $kind, ':d' => $payload, ':h' => hash('sha256', $payload), ':bt' => $byType, ':b' => $byId ? (int) $byId : null,
            ':v' => (int) $tk->version, ':e' => cdp_csNow(7 * 86400), ':n' => cdp_csNow(),
        ]);
        if ($r['ok']) {
            cdp_csEvent('proposal.created', $ticketId, $tk->conversation_id, $byType, $byId, ['proposal' => $r['id'], 'kind' => $kind]);
            cdp_csNotifyTeam($ticketId, 'Approval needed (' . cdp_csProposalKinds()[$kind] . ') on ' . $tk->public_ref, true);
        }
        return $r['id'];
    }

    function cdp_csProposals($ticketId)
    {
        return array_map(function ($p) {
            $d = cdp_csDecode($p->details);
            $expired = $p->status === 'pending' && strtotime($p->expires_at) < time();
            return ['id' => (int) $p->id, 'kind' => (string) $p->kind, 'kind_label' => cdp_csProposalKinds()[$p->kind] ?? $p->kind,
                'details' => $d['details'] ?? [], 'status' => $expired ? 'expired' : (string) $p->status, 'hash' => substr((string) $p->payload_hash, 0, 12),
                'requested_by' => $p->requested_by_type === 'king' ? cdp_csSetting('king_name', 'King') : cdp_csUserName($p->requested_by),
                'decided_by' => cdp_csUserName($p->decided_by), 'decision_note' => (string) $p->decision_note, 'created_at' => (string) $p->created_at,
                'expires_at' => (string) $p->expires_at];
        }, cdp_csQ("SELECT * FROM cdb_cs_proposals WHERE ticket_id = :t ORDER BY id DESC", [':t' => (int) $ticketId]));
    }

    /**
     * Records an approval or decline. The decision is a record: the money or
     * ownership change itself is made on the page that owns it (finance,
     * warehouse), never by the helpdesk.
     */
    function cdp_csProposalDecide($proposalId, $decision, $user, $note, $expectHash)
    {
        if (!cdp_csIsAdmin($user)) {
            return ['ok' => false, 'error' => 'Only administrators can approve or decline these requests.'];
        }
        $p = cdp_csOne("SELECT * FROM cdb_cs_proposals WHERE id = :i", [':i' => (int) $proposalId]);
        if (!$p || $p->status !== 'pending') {
            return ['ok' => false, 'error' => 'This request was already decided.'];
        }
        if (strtotime($p->expires_at) < time()) {
            cdp_csX("UPDATE cdb_cs_proposals SET status = 'expired' WHERE id = :i", [':i' => (int) $p->id]);
            return ['ok' => false, 'error' => 'This request expired. Ask for a new one with the current details.'];
        }
        if (!hash_equals(substr((string) $p->payload_hash, 0, 12), (string) $expectHash)) {
            return ['ok' => false, 'error' => 'The request details changed. Reload before deciding.'];
        }
        $status = $decision === 'approve' ? 'approved' : 'declined';
        $uid = cdp_csUid();
        $r = cdp_csX("UPDATE cdb_cs_proposals SET status = :s, decided_by = :u, decided_at = :n, decision_note = :d WHERE id = :i AND status = 'pending'",
            [':s' => $status, ':u' => $uid, ':n' => cdp_csNow(), ':d' => cdp_csClip($note, 1000), ':i' => (int) $p->id]);
        if ($r['rows'] !== 1) {
            return ['ok' => false, 'error' => 'Someone decided this at the same moment.'];
        }
        cdp_csEvent('proposal.' . $status, $p->ticket_id, null, 'staff', $uid, ['proposal' => (int) $p->id, 'note' => cdp_csClip($note, 255)]);
        cdp_csMessageAdd(null, $p->ticket_id, 'system', null, (cdp_csProposalKinds()[$p->kind] ?? $p->kind) . ' request ' . $status . ' by ' . cdp_csUserName($uid)
            . ($note !== '' ? ': ' . $note : '.') . ($status === 'approved' ? ' Make the change on the page that owns it, then tell the customer.' : ''), 'internal', ['meta' => ['kind' => 'proposal']]);
        return ['ok' => true, 'status' => $status];
    }
}

// ---------------------------------------------------------------------------
// Customer 360
// ---------------------------------------------------------------------------

if (!function_exists('cdp_csCustomer360')) {

    function cdp_csCustomer360($customerId, $viewer, $excludeTicket = 0)
    {
        $u = cdp_csOne("SELECT id, fname, lname, email, phone, locker, created, lastlogin, userlevel FROM cdb_users WHERE id = :i", [':i' => (int) $customerId]);
        if (!$u) {
            return null;
        }
        $money = function_exists('cdp_canViewMoney') ? cdp_canViewMoney($viewer, 'customers') : false;
        $records = [];
        foreach (['shipment', 'package', 'pickup'] as $type) {
            $m = cdp_csEntityTypes()[$type];
            foreach (cdp_csQ("SELECT a.{$m['id']} AS id, {$m['ref']} AS ref, st.mod_style AS status_name, a.order_date AS d, a.status_invoice AS inv, a.total_order AS total
                FROM {$m['table']} a LEFT JOIN cdb_styles st ON st.id = a.status_courier WHERE a.{$m['owner']} = :c AND ({$m['where']})
                ORDER BY a.{$m['id']} DESC LIMIT 6", [':c' => (int) $customerId]) as $r) {
                $row = ['type' => $type, 'label' => $m['label'], 'id' => (int) $r->id, 'ref' => (string) $r->ref, 'status' => str_replace('_', ' ', (string) $r->status_name),
                    'date' => (string) $r->d, 'paid' => $r->inv === null ? null : ((int) $r->inv === 1 ? 'Paid' : ((int) $r->inv === 3 ? 'Payment to verify' : 'Unpaid')),
                    'url' => $m['url'] . (int) $r->id];
                if ($money) {
                    $row['total'] = (float) $r->total;
                }
                $records[] = $row;
            }
        }
        usort($records, function ($a, $b) { return strcmp($b['date'], $a['date']); });
        $unpaid = cdp_csOne("SELECT
                (SELECT COUNT(*) FROM cdb_add_order WHERE sender_id = :c1 AND COALESCE(is_deleted,0) = 0 AND (status_invoice IN (0,2) OR status_invoice IS NULL)) +
                (SELECT COUNT(*) FROM cdb_customers_packages WHERE sender_id = :c2 AND (status_invoice IN (0,2) OR status_invoice IS NULL)) AS n",
            [':c1' => (int) $customerId, ':c2' => (int) $customerId]);
        $tickets = array_map(function ($t) {
            return ['id' => (int) $t->id, 'ref' => (string) $t->public_ref, 'subject' => (string) $t->subject, 'status' => (string) $t->status,
                'created_at' => (string) $t->created_at, 'csat' => $t->csat_score === null ? null : (int) $t->csat_score];
        }, cdp_csQ("SELECT id, public_ref, subject, status, created_at, csat_score FROM cdb_cs_tickets WHERE customer_id = :c AND id <> :x ORDER BY id DESC LIMIT 8",
            [':c' => (int) $customerId, ':x' => (int) $excludeTicket]));
        $csat = cdp_csOne("SELECT AVG(csat_score) AS a, COUNT(csat_score) AS n FROM cdb_cs_tickets WHERE customer_id = :c AND csat_score IS NOT NULL", [':c' => (int) $customerId]);
        return [
            'id' => (int) $u->id, 'name' => trim($u->fname . ' ' . $u->lname), 'email' => (string) $u->email, 'phone' => (string) $u->phone,
            'locker' => (string) $u->locker, 'since' => (string) $u->created, 'last_login' => (string) $u->lastlogin,
            'records' => array_slice($records, 0, 10), 'unpaid_count' => (int) ($unpaid->n ?? 0), 'tickets' => $tickets,
            'csat_avg' => $csat && $csat->n ? round((float) $csat->a, 1) : null, 'csat_n' => (int) ($csat->n ?? 0),
            'profile_url' => 'customers_edit.php?user=' . (int) $u->id, 'money_visible' => $money,
        ];
    }
}

// ---------------------------------------------------------------------------
// Knowledge base and canned replies
// ---------------------------------------------------------------------------

if (!function_exists('cdp_csKbSearch')) {

    /**
     * Published articles for an audience, best match first. Customers get
     * 'public' and 'customer' articles; staff get every audience.
     */
    function cdp_csKbSearch($q, array $audiences, $limit = 5)
    {
        $aud = array_values(array_intersect($audiences, ['public', 'customer', 'staff']));
        if (!$aud) {
            return [];
        }
        $in = [];
        $b = [];
        foreach ($aud as $i => $a) {
            $in[] = ":a$i";
            $b[":a$i"] = $a;
        }
        $where = "status = 'published' AND audience IN (" . implode(',', $in) . ") AND (review_at IS NULL OR review_at >= CURDATE() - INTERVAL 365 DAY)";
        $q = trim((string) $q);
        $words = array_slice(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($q)), function ($w) { return mb_strlen($w) >= 3; }), 0, 8);
        $rows = cdp_csQ("SELECT id, title, body, category, audience, version, updated_at, review_at FROM cdb_cs_kb WHERE $where ORDER BY updated_at DESC LIMIT 300", $b);
        $scored = [];
        foreach ($rows as $r) {
            $t = mb_strtolower($r->title);
            $body = mb_strtolower($r->body);
            $s = $words ? 0 : 1;
            foreach ($words as $w) {
                $s += (mb_strpos($t, $w) !== false ? 3 : 0) + min(3, mb_substr_count($body, $w));
            }
            if ($s > 0) {
                $scored[] = [$s, $r];
            }
        }
        usort($scored, function ($a, $b) { return $b[0] <=> $a[0]; });
        return array_map(function ($x) {
            $r = $x[1];
            return ['id' => (int) $r->id, 'title' => (string) $r->title, 'body' => (string) $r->body, 'category' => (string) $r->category,
                'audience' => (string) $r->audience, 'version' => (int) $r->version, 'updated_at' => (string) $r->updated_at, 'score' => $x[0]];
        }, array_slice($scored, 0, $limit));
    }

    function cdp_csKbList($all = true)
    {
        return array_map(function ($r) {
            return ['id' => (int) $r->id, 'title' => (string) $r->title, 'body' => (string) $r->body, 'category' => (string) $r->category,
                'audience' => (string) $r->audience, 'status' => (string) $r->status, 'version' => (int) $r->version,
                'owner' => cdp_csUserName($r->owner_id), 'approved_by' => cdp_csUserName($r->approved_by), 'review_at' => (string) $r->review_at,
                'helpful' => (int) $r->helpful, 'not_helpful' => (int) $r->not_helpful, 'updated_at' => (string) $r->updated_at,
                'needs_review' => $r->review_at && strtotime($r->review_at) < time()];
        }, cdp_csQ("SELECT * FROM cdb_cs_kb" . ($all ? '' : " WHERE status = 'published'") . " ORDER BY status = 'published' DESC, updated_at DESC"));
    }

    function cdp_csKbSave(array $d, $user)
    {
        $title = cdp_csClip($d['title'] ?? '', 190);
        $body = trim((string) ($d['body'] ?? ''));
        if ($title === '' || $body === '') {
            return ['ok' => false, 'error' => 'An article needs a title and text.'];
        }
        $aud = in_array($d['audience'] ?? '', ['public', 'customer', 'staff'], true) ? $d['audience'] : 'customer';
        $status = in_array($d['status'] ?? '', ['draft', 'published', 'retired'], true) ? $d['status'] : 'draft';
        if ($status === 'published' && !cdp_csIsAdmin($user)) {
            $status = 'draft'; // editors draft; administrators publish
        }
        $review = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($d['review_at'] ?? '')) ? $d['review_at'] : null;
        $uid = cdp_csUid();
        $now = cdp_csNow();
        $id = (int) ($d['id'] ?? 0);
        $b = [':t' => $title, ':b' => mb_substr($body, 0, 60000), ':c' => cdp_csClip($d['category'] ?? '', 60), ':a' => $aud, ':s' => $status, ':r' => $review, ':n' => $now];
        if ($id) {
            $b[':i'] = $id;
            $b[':ap'] = $status === 'published' ? $uid : null;
            $b[':at'] = $status === 'published' ? $now : null;
            cdp_csX("UPDATE cdb_cs_kb SET title = :t, body = :b, category = :c, audience = :a, status = :s, review_at = :r, updated_at = :n, version = version + 1,
                approved_by = :ap, approved_at = :at WHERE id = :i", $b);
        } else {
            $b[':o'] = $uid;
            $b[':ap'] = $status === 'published' ? $uid : null;
            $b[':at'] = $status === 'published' ? $now : null;
            $b[':src'] = !empty($d['source_ticket_id']) ? (int) $d['source_ticket_id'] : null;
            $b[':n2'] = $now;
            $id = cdp_csX("INSERT INTO cdb_cs_kb (title, body, category, audience, status, review_at, owner_id, approved_by, approved_at, source_ticket_id, created_at, updated_at)
                VALUES (:t, :b, :c, :a, :s, :r, :o, :ap, :at, :src, :n, :n2)", $b)['id'];
        }
        cdp_csEvent('settings.kb_saved', null, null, 'staff', $uid, ['article' => $id, 'status' => $status]);
        return ['ok' => (bool) $id, 'id' => $id, 'status' => $status];
    }

    function cdp_csCanned($uid)
    {
        return array_map(function ($c) {
            return ['id' => (int) $c->id, 'title' => (string) $c->title, 'body' => (string) $c->body, 'shortcut' => (string) $c->shortcut, 'shared' => (int) $c->shared === 1];
        }, cdp_csQ("SELECT * FROM cdb_cs_canned WHERE shared = 1 OR created_by = :u ORDER BY title", [':u' => (int) $uid]));
    }
}

// ---------------------------------------------------------------------------
// Reports
// ---------------------------------------------------------------------------

if (!function_exists('cdp_csReport')) {

    function cdp_csPercentile(array $values, $p)
    {
        if (!$values) {
            return null;
        }
        sort($values);
        $idx = ($p / 100) * (count($values) - 1);
        $lo = (int) floor($idx);
        $hi = (int) ceil($idx);
        return $values[$lo] + ($values[$hi] - $values[$lo]) * ($idx - $lo);
    }

    function cdp_csStats(array $minutes)
    {
        return ['n' => count($minutes), 'median' => cdp_csPercentile($minutes, 50), 'p90' => cdp_csPercentile($minutes, 90),
            'mean' => $minutes ? array_sum($minutes) / count($minutes) : null];
    }

    /**
     * Service report for tickets created in [from, to] (dates). Every number
     * comes from tickets, conversations and events; empty data gives empty
     * results, never placeholders. Time metrics in minutes, elapsed (calendar)
     * time; the resolution figure excludes time paused on the customer.
     */
    function cdp_csReport($from, $to, array $f = [])
    {
        $b = [':f' => $from . ' 00:00:00', ':t' => $to . ' 23:59:59'];
        $w = "t.created_at BETWEEN :f AND :t AND t.disposition IN ('none','archived') AND t.merged_into IS NULL";
        foreach (['channel', 'category', 'priority'] as $k) {
            if (!empty($f[$k])) {
                $w .= " AND t.$k = :$k";
                $b[":$k"] = $f[$k];
            }
        }
        if (!empty($f['team_id'])) {
            $w .= ' AND t.team_id = :team';
            $b[':team'] = (int) $f['team_id'];
        }
        if (!empty($f['assignee_id'])) {
            $w .= ' AND t.assignee_id = :asg';
            $b[':asg'] = (int) $f['assignee_id'];
        }
        $tickets = cdp_csQ("SELECT t.*, c.owner_state, c.handover_at, c.accepted_at FROM cdb_cs_tickets t LEFT JOIN cdb_cs_conversations c ON c.id = t.conversation_id WHERE $w", $b);
        $mins = function ($a, $z) { return $a && $z ? max(0, (strtotime($z) - strtotime($a)) / 60) : null; };

        $first = $human = $resolve = $handWait = [];
        $slaEligible = $slaMet = 0;
        $firstEligible = $firstMet = 0;
        $resolved = $reopened = $csatN = $csatPos = $fcr = 0;
        $byDay = $byChannel = $byCategory = $byTeam = $byAgent = [];
        $resDay = [];
        $stars = [];
        $now = time();
        foreach ($tickets as $t) {
            $day = substr($t->created_at, 0, 10);
            $byDay[$day] = ($byDay[$day] ?? 0) + 1;
            $byChannel[$t->channel] = ($byChannel[$t->channel] ?? 0) + 1;
            $byCategory[$t->category] = ($byCategory[$t->category] ?? 0) + 1;
            $teamKey = (int) $t->team_id;
            $byTeam[$teamKey] = ($byTeam[$teamKey] ?? 0) + 1;
            if (($v = $mins($t->created_at, $t->first_response_at)) !== null) {
                $first[] = $v;
            }
            if (($v = $mins($t->created_at, $t->human_first_response_at)) !== null) {
                $human[] = $v;
            }
            if ($t->handover_at && ($v = $mins($t->handover_at, $t->accepted_at)) !== null) {
                $handWait[] = $v;
            }
            $isRes = in_array($t->status, ['resolved', 'closed'], true) && $t->resolved_at;
            if ($isRes) {
                $resolved++;
                $rd = substr($t->resolved_at, 0, 10);
                $resDay[$rd] = ($resDay[$rd] ?? 0) + 1;
                $resolve[] = max(0, $mins($t->created_at, $t->resolved_at) - (int) $t->sla_paused_minutes);
                if ((int) $t->reopen_count === 0) {
                    $fcr++;
                }
            }
            if ((int) $t->reopen_count > 0) {
                $reopened++;
            }
            if ($t->csat_score !== null) {
                $csatN++;
                if ((int) $t->csat_score >= 4) {
                    $csatPos++;
                }
            }
            // SLA: eligible = finished, or already past due while open.
            if (!$t->is_internal) {
                if ($t->human_first_response_at || $t->first_breached_at || strtotime($t->sla_first_due) < $now) {
                    $firstEligible++;
                    if (!$t->first_breached_at && $t->human_first_response_at && strtotime($t->human_first_response_at) <= strtotime($t->sla_first_due)) {
                        $firstMet++;
                    }
                }
                if ($isRes || $t->resolve_breached_at) {
                    $slaEligible++;
                    if (!$t->resolve_breached_at) {
                        $slaMet++;
                    }
                }
            }
            $ak = (int) $t->assignee_id;
            if (!isset($byAgent[$ak])) {
                $byAgent[$ak] = ['assigned' => 0, 'resolved' => 0, 'first' => [], 'csat' => [], 'sla_e' => 0, 'sla_m' => 0, 'name' => $ak ? cdp_csUserName($ak) : 'Unassigned'];
            }
            $byAgent[$ak]['assigned']++;
            if ($isRes) {
                $byAgent[$ak]['resolved']++;
            }
            if (($v = $mins($t->created_at, $t->human_first_response_at)) !== null) {
                $byAgent[$ak]['first'][] = $v;
            }
            if ($t->csat_score !== null) {
                $byAgent[$ak]['csat'][] = (int) $t->csat_score;
                $stars[(int) $t->csat_score] = ($stars[(int) $t->csat_score] ?? 0) + 1;
            }
            if (!$t->is_internal && ($isRes || $t->resolve_breached_at)) {
                $byAgent[$ak]['sla_e']++;
                if (!$t->resolve_breached_at) {
                    $byAgent[$ak]['sla_m']++;
                }
            }
        }
        ksort($byDay);
        ksort($resDay);
        // Conversations and King.
        $convs = cdp_csQ("SELECT c.id, c.owner_state, c.ticket_id, c.handover_at, c.ai_turns, c.ai_outcome, c.rating, c.created_at
            FROM cdb_cs_conversations c WHERE c.created_at BETWEEN :f AND :t", [':f' => $b[':f'], ':t' => $b[':t']]);
        $ai = ['conversations' => count($convs), 'engaged' => 0, 'handed_over' => 0, 'contained' => 0, 'confirmed' => 0, 'abandoned' => 0];
        $aiDay = [];
        foreach ($convs as $c) {
            if ((int) $c->ai_turns > 0 || $c->ai_outcome) {
                $ai['engaged']++;
                $ad = substr($c->created_at, 0, 10);
                $aiDay[$ad] = $aiDay[$ad] ?? ['king' => 0, 'handed' => 0];
                if ($c->handover_at) {
                    $ai['handed_over']++;
                    $aiDay[$ad]['handed']++;
                } else {
                    $ai['contained']++;
                    $aiDay[$ad]['king']++;
                    if ($c->ai_outcome === 'confirmed') {
                        $ai['confirmed']++;
                    } elseif ($c->owner_state !== 'resolved') {
                        $ai['abandoned']++;
                    }
                }
            }
        }
        $runs = cdp_csOne("SELECT COUNT(*) AS n, SUM(COALESCE(tokens_in,0)) AS tin, SUM(COALESCE(tokens_out,0)) AS tout, AVG(latency_ms) AS lat,
                SUM(outcome LIKE 'error%') AS errs, SUM(outcome = 'discarded') AS discarded
            FROM cdb_cs_ai_runs WHERE created_at BETWEEN :f AND :t", [':f' => $b[':f'], ':t' => $b[':t']]);
        $reasons = cdp_csQ("SELECT handover_reason AS r, COUNT(*) AS n FROM cdb_cs_conversations WHERE handover_at BETWEEN :f AND :t AND handover_reason IS NOT NULL
            GROUP BY handover_reason ORDER BY n DESC LIMIT 8", [':f' => $b[':f'], ':t' => $b[':t']]);
        $outbox = cdp_csQ("SELECT status, COUNT(*) AS n FROM cdb_cs_outbox WHERE created_at BETWEEN :f AND :t GROUP BY status", [':f' => $b[':f'], ':t' => $b[':t']]);
        $rules = cdp_csQ("SELECT outcome, COUNT(*) AS n FROM cdb_cs_rule_runs WHERE created_at BETWEEN :f AND :t GROUP BY outcome", [':f' => $b[':f'], ':t' => $b[':t']]);
        $backlog = cdp_csQ("SELECT CASE WHEN TIMESTAMPDIFF(HOUR, created_at, NOW()) < 24 THEN '< 1 day' WHEN TIMESTAMPDIFF(HOUR, created_at, NOW()) < 72 THEN '1-3 days'
                WHEN TIMESTAMPDIFF(HOUR, created_at, NOW()) < 168 THEN '3-7 days' ELSE '> 7 days' END AS age, status, COUNT(*) AS n
            FROM cdb_cs_tickets WHERE disposition = 'none' AND status NOT IN ('resolved','closed') GROUP BY age, status");

        $teams = [];
        foreach (cdp_csTeams() as $tm) {
            $teams[$tm['id']] = $tm['name'];
        }
        $agents = [];
        foreach ($byAgent as $k => $a) {
            $s = cdp_csStats($a['first']);
            $agents[] = ['name' => $a['name'], 'assigned' => $a['assigned'], 'resolved' => $a['resolved'], 'first_median' => $s['median'],
                'sla_met' => $a['sla_e'] ? (int) round($a['sla_m'] / $a['sla_e'] * 100) : null,
                'csat' => $a['csat'] ? round(array_sum($a['csat']) / count($a['csat']), 2) : null, 'csat_n' => count($a['csat'])];
        }
        usort($agents, function ($x, $y) { return $y['assigned'] <=> $x['assigned']; });
        $named = function (array $counts, array $labels) {
            $o = [];
            arsort($counts);
            foreach ($counts as $k => $n) {
                $o[] = ['key' => (string) $k, 'label' => $labels[$k] ?? ($k === 0 || $k === '0' ? 'No team' : (string) $k), 'n' => $n];
            }
            return $o;
        };
        return [
            'period' => ['from' => $from, 'to' => $to, 'basis' => 'Elapsed calendar time, server timezone ' . date_default_timezone_get()],
            'tickets' => count($tickets), 'resolved' => $resolved, 'reopened' => $reopened,
            'first_response' => cdp_csStats($first), 'human_first_response' => cdp_csStats($human), 'resolution' => cdp_csStats($resolve),
            'handover_wait' => cdp_csStats($handWait),
            'sla_first' => ['eligible' => $firstEligible, 'met' => $firstMet], 'sla_resolve' => ['eligible' => $slaEligible, 'met' => $slaMet],
            'fcr' => ['eligible' => $resolved, 'met' => $fcr],
            'csat' => ['responses' => $csatN, 'positive' => $csatPos, 'asked' => $resolved],
            'ai' => $ai, 'ai_runs' => ['n' => (int) ($runs->n ?? 0), 'tokens_in' => (int) ($runs->tin ?? 0), 'tokens_out' => (int) ($runs->tout ?? 0),
                'latency_ms' => $runs && $runs->lat !== null ? (int) $runs->lat : null, 'errors' => (int) ($runs->errs ?? 0), 'discarded' => (int) ($runs->discarded ?? 0)],
            'handover_reasons' => array_map(function ($r) { return ['label' => (string) $r->r, 'n' => (int) $r->n]; }, $reasons),
            'by_day' => $byDay,
            'ratings' => ['5' => $stars[5] ?? 0, '4' => $stars[4] ?? 0, '3' => $stars[3] ?? 0, '2' => $stars[2] ?? 0, '1' => $stars[1] ?? 0],
            'daily' => cdp_csDaily($from, $to, $byDay, $resDay, $aiDay),
            'by_channel' => $named($byChannel, cdp_csChannels()), 'by_category' => $named($byCategory, cdp_csCategories()),
            'by_team' => $named($byTeam, $teams), 'agents' => $agents,
            'notifications' => array_map(function ($r) { return ['label' => (string) $r->status, 'n' => (int) $r->n]; }, $outbox),
            'automation' => array_map(function ($r) { return ['label' => (string) $r->outcome, 'n' => (int) $r->n]; }, $rules),
            'backlog' => array_map(function ($r) { return ['age' => (string) $r->age, 'status' => (string) $r->status, 'n' => (int) $r->n]; }, $backlog),
        ];
    }

    /** One row per day of the period (empty days included): created, resolved, King solved, handed to a person. */
    function cdp_csDaily($from, $to, array $created, array $resolved, array $ai)
    {
        $rows = [];
        $d = new DateTime($from);
        $end = new DateTime($to);
        while ($d <= $end && count($rows) < 400) {
            $k = $d->format('Y-m-d');
            $rows[] = [
                'day' => $k,
                'created' => (int) ($created[$k] ?? 0),
                'resolved' => (int) ($resolved[$k] ?? 0),
                'king' => (int) ($ai[$k]['king'] ?? 0),
                'handed' => (int) ($ai[$k]['handed'] ?? 0),
            ];
            $d->modify('+1 day');
        }
        return $rows;
    }

    /** A CSV cell that a spreadsheet will never run as a formula. */
    function cdp_csCsvCell($v)
    {
        $v = (string) $v;
        if ($v !== '' && strpos('=+-@' . "\t\r", $v[0]) !== false) {
            $v = "'" . $v;
        }
        return '"' . str_replace('"', '""', $v) . '"';
    }
}

// ---------------------------------------------------------------------------
// Attachments
// ---------------------------------------------------------------------------

if (!function_exists('cdp_csFileStore')) {

    function cdp_csFileDir()
    {
        return dirname(__DIR__) . '/support_files';
    }

    /** Images, PDF and plain text only, checked by content, 8 MB max. */
    function cdp_csFileTypes()
    {
        return ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'application/pdf' => 'pdf', 'text/plain' => 'txt'];
    }

    /** Stores one uploaded file ($_FILES entry). Returns ['ok', 'file' => [id,token,name,size,mime]] or an error. */
    function cdp_csFileStore(array $f, $uid)
    {
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'] ?? '')) {
            return ['ok' => false, 'error' => 'The upload did not arrive. Try again.'];
        }
        if ((int) $f['size'] <= 0 || (int) $f['size'] > 8 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'Files can be up to 8 MB.'];
        }
        $mime = function_exists('finfo_open') ? (string) finfo_file(finfo_open(FILEINFO_MIME_TYPE), $f['tmp_name']) : '';
        $types = cdp_csFileTypes();
        if (!isset($types[$mime])) {
            return ['ok' => false, 'error' => 'Only photos (JPG, PNG, GIF, WebP), PDF and text files can be attached.'];
        }
        $dir = cdp_csFileDir();
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
            return ['ok' => false, 'error' => 'The server cannot store attachments (support_files is not writable).'];
        }
        if (!file_exists($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
        }
        $token = bin2hex(random_bytes(16));
        $stored = date('Ym') . '_' . $token . '.' . $types[$mime];
        if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $stored)) {
            return ['ok' => false, 'error' => 'The file could not be saved.'];
        }
        $name = preg_replace('/[^\p{L}\p{N} ._()-]+/u', '_', basename((string) $f['name']));
        $name = mb_substr($name !== '' ? $name : 'file.' . $types[$mime], 0, 190);
        $r = cdp_csX("INSERT INTO cdb_cs_files (token, uploaded_by, stored_name, original_name, mime, size_bytes, created_at) VALUES (:t, :u, :s, :n, :m, :z, :c)",
            [':t' => $token, ':u' => (int) $uid, ':s' => $stored, ':n' => $name, ':m' => $mime, ':z' => (int) $f['size'], ':c' => cdp_csNow()]);
        if (!$r['ok']) {
            @unlink($dir . '/' . $stored);
            return ['ok' => false, 'error' => 'The file could not be saved.'];
        }
        return ['ok' => true, 'file' => ['token' => $token, 'name' => $name, 'size' => (int) $f['size'], 'mime' => $mime]];
    }

    /** Attachment list from tokens the same user uploaded (a token from someone else is ignored). */
    function cdp_csFilesFromTokens($tokens, $uid)
    {
        $out = [];
        foreach (array_slice((array) $tokens, 0, 5) as $t) {
            $t = preg_replace('/[^a-f0-9]/', '', (string) $t);
            if (strlen($t) !== 32) {
                continue;
            }
            $r = cdp_csOne("SELECT token, original_name, size_bytes, mime FROM cdb_cs_files WHERE token = :t AND uploaded_by = :u", [':t' => $t, ':u' => (int) $uid]);
            if ($r) {
                $out[] = ['token' => $r->token, 'name' => $r->original_name, 'size' => (int) $r->size_bytes, 'mime' => $r->mime];
            }
        }
        return $out;
    }

    /**
     * May this user download the file? Staff: any attachment of the desk.
     * Customer: their own upload, or a public message in their own chat or ticket.
     */
    function cdp_csFileCanRead($token, $user)
    {
        $f = cdp_csOne("SELECT * FROM cdb_cs_files WHERE token = :t", [':t' => (string) $token]);
        if (!$f) {
            return null;
        }
        if (cdp_csIsAgent($user) || (int) $f->uploaded_by === cdp_csUid()) {
            return $f;
        }
        $uid = cdp_csUid();
        $m = cdp_csOne("SELECT m.id FROM cdb_cs_messages m
                LEFT JOIN cdb_cs_conversations c ON c.id = m.conversation_id
                LEFT JOIN cdb_cs_tickets t ON t.id = m.ticket_id
            WHERE m.visibility = 'public' AND m.attachments LIKE :tk AND (c.customer_id = :u OR (t.customer_id = :u2 AND t.is_internal = 0)) LIMIT 1",
            [':tk' => '%' . $f->token . '%', ':u' => $uid, ':u2' => $uid]);
        return $m ? $f : null;
    }
}
