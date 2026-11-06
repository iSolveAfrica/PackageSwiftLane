-- =============================================================================
-- 2026-11-06  Swift Lane UI: customer service, AI assistant and loyalty
-- Run:  mysql -u USER -p DBNAME < migrations/2026_11_06_support_ai_loyalty.sql
-- Every step is safe to run again (CREATE TABLE IF NOT EXISTS, WHERE NOT EXISTS,
-- guards on information_schema). Nothing is dropped or truncated.
--
-- 1. The helpdesk tables (cdb_cs_*) and the AI assistant tables (cdb_ai_*) are
--    created here so a fresh install does not rely on the lazy creation in
--    helpers/support.php / helpers/ai_assistant.php (which stays as a fallback).
--    The DDL below is a verbatim copy of those helpers.
-- 2. Loyalty, referrals and rewards (helpers/loyalty.php, loyalty_rewards.php).
-- 3. Settings seeds in cdb_sl_settings and the schema ledger.
-- =============================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_schema_migrations (
    name        VARCHAR(190) NOT NULL,
    applied_at  DATETIME NOT NULL,
    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_sl_settings (
    setting_key   VARCHAR(64) NOT NULL,
    setting_value TEXT NULL,
    updated_by    INT NULL,
    updated_at    DATETIME NULL,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Customer service (copy of helpers/support.php, CDP_CS_SCHEMA 1) -----------

CREATE TABLE IF NOT EXISTS cdb_cs_settings (
    setting_key VARCHAR(64) NOT NULL, setting_value TEXT NULL, updated_by INT NULL, updated_at DATETIME NULL,
    PRIMARY KEY (setting_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_cs_teams (
    id INT NOT NULL AUTO_INCREMENT, name VARCHAR(120) NOT NULL, description VARCHAR(255) NULL,
    assign_mode VARCHAR(20) NOT NULL DEFAULT 'manual', timezone VARCHAR(64) NULL, is_default TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL,
    PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_cs_team_members (
    team_id INT NOT NULL, user_id INT NOT NULL, is_lead TINYINT(1) NOT NULL DEFAULT 0,
    capacity INT NOT NULL DEFAULT 20, available TINYINT(1) NOT NULL DEFAULT 1, last_assigned_at DATETIME NULL,
    PRIMARY KEY (team_id, user_id), KEY idx_user (user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_cs_conversations (
    id INT NOT NULL AUTO_INCREMENT, public_ref VARCHAR(20) NOT NULL, customer_id INT NULL,
    channel VARCHAR(20) NOT NULL DEFAULT 'web', subject VARCHAR(190) NULL,
    owner_state VARCHAR(20) NOT NULL DEFAULT 'ai_active', owner_seq INT NOT NULL DEFAULT 0, owner_user_id INT NULL,
    ticket_id INT NULL, language VARCHAR(12) NULL, ai_turns INT NOT NULL DEFAULT 0, handover_reason VARCHAR(255) NULL,
    handover_at DATETIME NULL, accepted_at DATETIME NULL, resolved_at DATETIME NULL, ai_outcome VARCHAR(20) NULL,
    rating TINYINT NULL, last_customer_at DATETIME NULL, last_message_at DATETIME NULL,
    customer_read_id INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
    PRIMARY KEY (id), UNIQUE KEY uq_ref (public_ref), KEY idx_customer (customer_id, updated_at),
    KEY idx_state (owner_state, updated_at), KEY idx_ticket (ticket_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_cs_messages (
    id INT NOT NULL AUTO_INCREMENT, conversation_id INT NULL, ticket_id INT NULL,
    author_type VARCHAR(12) NOT NULL, author_id INT NULL, visibility VARCHAR(10) NOT NULL DEFAULT 'public',
    channel VARCHAR(20) NOT NULL DEFAULT 'web', body MEDIUMTEXT NULL, attachments TEXT NULL, meta TEXT NULL,
    client_msg_id VARCHAR(64) NULL, delivery_state VARCHAR(16) NOT NULL DEFAULT 'sent', created_at DATETIME NOT NULL,
    PRIMARY KEY (id), KEY idx_conv (conversation_id, id), KEY idx_ticket (ticket_id, id),
    UNIQUE KEY uq_client (author_type, author_id, client_msg_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_cs_tickets (
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
    KEY idx_created (created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_cs_ticket_tags (
    ticket_id INT NOT NULL, tag VARCHAR(40) NOT NULL, PRIMARY KEY (ticket_id, tag), KEY idx_tag (tag)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_cs_followers (
    ticket_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY (ticket_id, user_id), KEY idx_user (user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_cs_links (
    id INT NOT NULL AUTO_INCREMENT, ticket_id INT NOT NULL, entity_type VARCHAR(20) NOT NULL, entity_id INT NOT NULL,
    entity_ref VARCHAR(80) NULL, created_by INT NULL, created_at DATETIME NOT NULL,
    PRIMARY KEY (id), UNIQUE KEY uq_link (ticket_id, entity_type, entity_id), KEY idx_entity (entity_type, entity_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_cs_tasks (
    id INT NOT NULL AUTO_INCREMENT, ticket_id INT NULL, title VARCHAR(255) NOT NULL, due_at DATETIME NULL,
    assignee_id INT NULL, done_at DATETIME NULL, done_by INT NULL, created_by INT NULL, created_at DATETIME NOT NULL,
    PRIMARY KEY (id), KEY idx_ticket (ticket_id), KEY idx_assignee (assignee_id, done_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_cs_events (
    id BIGINT NOT NULL AUTO_INCREMENT, ticket_id INT NULL, conversation_id INT NULL, event_type VARCHAR(40) NOT NULL,
    actor_type VARCHAR(12) NOT NULL DEFAULT 'system', actor_id INT NULL, data TEXT NULL, created_at DATETIME NOT NULL,
    PRIMARY KEY (id), KEY idx_ticket (ticket_id, id), KEY idx_conv (conversation_id, id), KEY idx_type (event_type, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_cs_rules (
    id INT NOT NULL AUTO_INCREMENT, name VARCHAR(160) NOT NULL, kind VARCHAR(12) NOT NULL DEFAULT 'automation',
    trigger_event VARCHAR(40) NOT NULL, match_mode VARCHAR(4) NOT NULL DEFAULT 'all', conditions TEXT NULL, actions TEXT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'draft', sort_order INT NOT NULL DEFAULT 100, stop_processing TINYINT(1) NOT NULL DEFAULT 0,
    max_per_ticket INT NOT NULL DEFAULT 1, version INT NOT NULL DEFAULT 1, run_count INT NOT NULL DEFAULT 0,
    fail_count INT NOT NULL DEFAULT 0, last_run_at DATETIME NULL, template_key VARCHAR(40) NULL,
    created_by INT NULL, updated_by INT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
    PRIMARY KEY (id), KEY idx_trigger (status, trigger_event, sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_cs_rule_versions (
    id INT NOT NULL AUTO_INCREMENT, rule_id INT NOT NULL, version INT NOT NULL, snapshot TEXT NOT NULL,
    saved_by INT NULL, saved_at DATETIME NOT NULL, PRIMARY KEY (id), KEY idx_rule (rule_id, version)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_cs_rule_runs (
    id BIGINT NOT NULL AUTO_INCREMENT, rule_id INT NOT NULL, rule_version INT NOT NULL DEFAULT 1, ticket_id INT NULL,
    event VARCHAR(40) NOT NULL, dedupe_key VARCHAR(120) NULL, outcome VARCHAR(12) NOT NULL, steps TEXT NULL, created_at DATETIME NOT NULL,
    PRIMARY KEY (id), KEY idx_rule (rule_id, id), UNIQUE KEY uq_dedupe (rule_id, dedupe_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_cs_canned (
    id INT NOT NULL AUTO_INCREMENT, title VARCHAR(160) NOT NULL, body TEXT NOT NULL, shortcut VARCHAR(40) NULL,
    shared TINYINT(1) NOT NULL DEFAULT 1, created_by INT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
    PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_cs_kb (
    id INT NOT NULL AUTO_INCREMENT, title VARCHAR(190) NOT NULL, body MEDIUMTEXT NOT NULL, category VARCHAR(60) NULL,
    audience VARCHAR(10) NOT NULL DEFAULT 'customer', status VARCHAR(12) NOT NULL DEFAULT 'draft', version INT NOT NULL DEFAULT 1,
    owner_id INT NULL, approved_by INT NULL, approved_at DATETIME NULL, review_at DATE NULL, source_ticket_id INT NULL,
    helpful INT NOT NULL DEFAULT 0, not_helpful INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
    PRIMARY KEY (id), KEY idx_status (status, audience)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_cs_views (
    id INT NOT NULL AUTO_INCREMENT, user_id INT NOT NULL, name VARCHAR(80) NOT NULL, filters TEXT NOT NULL,
    shared TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, PRIMARY KEY (id), KEY idx_user (user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_cs_presence (
    ticket_id INT NOT NULL, user_id INT NOT NULL, seen_at DATETIME NOT NULL, typing_at DATETIME NULL,
    PRIMARY KEY (ticket_id, user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_cs_ai_runs (
    id BIGINT NOT NULL AUTO_INCREMENT, conversation_id INT NULL, ticket_id INT NULL, kind VARCHAR(20) NOT NULL,
    provider VARCHAR(20) NULL, model VARCHAR(120) NULL, prompt_version VARCHAR(20) NULL, tokens_in INT NULL, tokens_out INT NULL,
    latency_ms INT NULL, tools TEXT NULL, outcome VARCHAR(30) NOT NULL, detail VARCHAR(255) NULL, user_id INT NULL, created_at DATETIME NOT NULL,
    PRIMARY KEY (id), KEY idx_kind (kind, created_at), KEY idx_conv (conversation_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_cs_proposals (
    id INT NOT NULL AUTO_INCREMENT, ticket_id INT NOT NULL, kind VARCHAR(30) NOT NULL, details TEXT NOT NULL,
    payload_hash CHAR(64) NOT NULL, status VARCHAR(12) NOT NULL DEFAULT 'pending', requested_by_type VARCHAR(12) NOT NULL,
    requested_by INT NULL, decided_by INT NULL, decided_at DATETIME NULL, decision_note TEXT NULL, ticket_version INT NULL,
    expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL,
    PRIMARY KEY (id), KEY idx_ticket (ticket_id, status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_cs_files (
    id INT NOT NULL AUTO_INCREMENT, token CHAR(32) NOT NULL, uploaded_by INT NOT NULL, stored_name VARCHAR(80) NOT NULL,
    original_name VARCHAR(190) NOT NULL, mime VARCHAR(80) NOT NULL, size_bytes INT NOT NULL, created_at DATETIME NOT NULL,
    PRIMARY KEY (id), UNIQUE KEY uq_token (token), KEY idx_user (uploaded_by)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_cs_outbox (
    id BIGINT NOT NULL AUTO_INCREMENT, ticket_id INT NULL, user_id INT NOT NULL, kind VARCHAR(20) NOT NULL,
    channel VARCHAR(12) NOT NULL, subject VARCHAR(190) NULL, body TEXT NOT NULL, dedupe_key VARCHAR(160) NOT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'queued', attempts INT NOT NULL DEFAULT 0, send_after DATETIME NOT NULL,
    last_error VARCHAR(255) NULL, sent_at DATETIME NULL, created_at DATETIME NOT NULL,
    PRIMARY KEY (id), UNIQUE KEY uq_dedupe (dedupe_key), KEY idx_due (status, send_after), KEY idx_ticket (ticket_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- First-run seeds the helper would otherwise write on first use
INSERT INTO cdb_cs_teams (name, description, assign_mode, is_default, active, created_at)
SELECT 'Customer Support', 'Default team: receives every case no rule routes elsewhere. Add members on Channels & Teams.', 'least_loaded', 1, 1, NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_cs_teams);
INSERT INTO cdb_cs_settings (setting_key, setting_value, updated_at) SELECT 'seeded', '1', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_cs_settings WHERE setting_key = 'seeded');
INSERT INTO cdb_cs_settings (setting_key, setting_value, updated_at) SELECT 'schema_version', '1', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_cs_settings WHERE setting_key = 'schema_version');

-- AI assistant (copy of helpers/ai_assistant.php) ----------------------------

CREATE TABLE IF NOT EXISTS cdb_ai_settings (
    setting_key   VARCHAR(64) NOT NULL,
    setting_value TEXT NULL,
    updated_by    INT NULL,
    updated_at    DATETIME NULL,
    PRIMARY KEY (setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_ai_conversations (
    id          INT NOT NULL AUTO_INCREMENT,
    user_id     INT NOT NULL,
    title       VARCHAR(190) NOT NULL DEFAULT 'New chat',
    created_at  DATETIME NOT NULL,
    updated_at  DATETIME NOT NULL,
    is_deleted  TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_user (user_id, is_deleted, updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_ai_messages (
    id              INT NOT NULL AUTO_INCREMENT,
    conversation_id INT NOT NULL,
    role            VARCHAR(16) NOT NULL,
    content         MEDIUMTEXT NULL,
    tools           TEXT NULL,
    provider        VARCHAR(20) NULL,
    model           VARCHAR(120) NULL,
    tokens_in       INT NULL,
    tokens_out      INT NULL,
    created_at      DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_conv (conversation_id, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_ai_questions (
    id          INT NOT NULL AUTO_INCREMENT,
    user_id     INT NOT NULL,
    role_id     INT NULL,
    role_name   VARCHAR(100) NULL,
    question    TEXT NOT NULL,
    tools_used  VARCHAR(500) NULL,
    outcome     VARCHAR(20) NOT NULL DEFAULT 'answered',
    created_at  DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_ai_knowledge (
    id          INT NOT NULL AUTO_INCREMENT,
    note        TEXT NOT NULL,
    source      VARCHAR(20) NOT NULL DEFAULT 'admin',
    active      TINYINT(1) NOT NULL DEFAULT 1,
    created_by  INT NULL,
    created_at  DATETIME NOT NULL,
    PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_ai_page_ideas (
    id                INT NOT NULL AUTO_INCREMENT,
    title             VARCHAR(190) NOT NULL,
    purpose           TEXT NULL,
    audience          VARCHAR(190) NULL,
    data_needed       TEXT NULL,
    rationale         TEXT NULL,
    example_questions TEXT NULL,
    source            VARCHAR(20) NOT NULL DEFAULT 'chat',
    status            VARCHAR(20) NOT NULL DEFAULT 'new',
    votes             INT NOT NULL DEFAULT 1,
    created_by        INT NULL,
    created_at        DATETIME NOT NULL,
    updated_at        DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Loyalty, referrals and rewards ---------------------------------------------
CREATE TABLE IF NOT EXISTS cdb_loyalty_accounts (
    user_id         INT NOT NULL,
    referral_code   VARCHAR(24) NOT NULL,
    points_balance  INT NOT NULL DEFAULT 0,
    lifetime_points INT NOT NULL DEFAULT 0,
    tier            VARCHAR(12) NOT NULL DEFAULT 'standard',
    referred_by     INT NULL,
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME NOT NULL,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_code (referral_code),
    KEY idx_tier (tier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_loyalty_events (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       INT NOT NULL,
    kind          VARCHAR(16) NOT NULL,            -- earn | redeem | adjust | referral | expire
    points        INT NOT NULL,                    -- signed
    entity_type   VARCHAR(20) NULL,                -- shipment | package | referral | reward
    entity_id     INT NULL,
    note          VARCHAR(190) NULL,
    created_by    INT NULL,
    created_at    DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_once (kind, entity_type, entity_id, user_id),
    KEY idx_user (user_id, id),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_referrals (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    referrer_id   INT NOT NULL,
    referee_id    INT NOT NULL,
    code          VARCHAR(24) NOT NULL,
    status        VARCHAR(12) NOT NULL DEFAULT 'signed_up',  -- signed_up | shipped | rewarded
    channel       VARCHAR(20) NULL,
    rewarded_at   DATETIME NULL,
    created_at    DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_referee (referee_id),
    KEY idx_referrer (referrer_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_rewards (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code          VARCHAR(40) NOT NULL,
    title         VARCHAR(160) NOT NULL,
    kind          VARCHAR(12) NOT NULL DEFAULT 'coupon',    -- coupon | voucher | birthday
    value_type    VARCHAR(8) NOT NULL DEFAULT 'amount',     -- amount | percent
    value_amount  DECIMAL(12,2) NOT NULL DEFAULT 0,
    points_cost   INT NOT NULL DEFAULT 0,                   -- 0 = not redeemable with points
    max_uses      INT NOT NULL DEFAULT 0,                   -- 0 = unlimited
    used_count    INT NOT NULL DEFAULT 0,
    active        TINYINT(1) NOT NULL DEFAULT 1,
    starts_at     DATE NULL,
    ends_at       DATE NULL,
    created_by    INT NULL,
    created_at    DATETIME NOT NULL,
    updated_at    DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_code (code),
    KEY idx_active (active, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Loyalty rules (read by helpers/loyalty.php; edited on loyalty_rewards.php)
INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_at) SELECT 'loyalty_enabled', '1', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_sl_settings WHERE setting_key = 'loyalty_enabled');
INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_at) SELECT 'loyalty_points_air', '1', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_sl_settings WHERE setting_key = 'loyalty_points_air');
INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_at) SELECT 'loyalty_points_sea', '0.5', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_sl_settings WHERE setting_key = 'loyalty_points_sea');
INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_at) SELECT 'loyalty_redeem_points', '100', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_sl_settings WHERE setting_key = 'loyalty_redeem_points');
INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_at) SELECT 'loyalty_redeem_value', '10', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_sl_settings WHERE setting_key = 'loyalty_redeem_value');
INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_at) SELECT 'loyalty_tier_silver', '1000', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_sl_settings WHERE setting_key = 'loyalty_tier_silver');
INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_at) SELECT 'loyalty_tier_gold', '3000', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_sl_settings WHERE setting_key = 'loyalty_tier_gold');
INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_at) SELECT 'referral_enabled', '1', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_sl_settings WHERE setting_key = 'referral_enabled');
INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_at) SELECT 'referral_friend_reward', '20 off the first air shipment', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_sl_settings WHERE setting_key = 'referral_friend_reward');
INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_at) SELECT 'referral_referrer_points', '200', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_sl_settings WHERE setting_key = 'referral_referrer_points');

-- Permission: manage_loyalty is created by 2026_11_06_legacy_drift.sql (not duplicated here).

INSERT INTO cdb_schema_migrations (name, applied_at) SELECT '2026_11_06_support_ai_loyalty', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_schema_migrations WHERE name = '2026_11_06_support_ai_loyalty');
SELECT 'ok: 2026_11_06_support_ai_loyalty' AS result;
