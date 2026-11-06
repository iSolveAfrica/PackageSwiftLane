-- =============================================================================
-- 2026-11-06  Swift Lane UI: messaging, automations, settings and API admin
-- Run:  mysql -u USER -p DBNAME < migrations/2026_11_06_messaging_settings.sql
-- Every step is safe to run again (guards on information_schema).
-- Pages: Notification Center, Compose, Broadcast, Email Builder, Templates,
--        Message Logs / Costs, Comms Hub, Automations, Settings Hub,
--        Shipping Addresses, Module Controller, API Connections / Access /
--        Monitor / Traffic.
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

-- Notification Center: which system event goes out on which channel ---------
-- One row per (event, channel). Absent row = the channel's default (on for
-- the events the legacy senders already cover). template_id points at
-- cdb_email_templates / cdb_sms_templates / whatsapp_templates by channel.
CREATE TABLE IF NOT EXISTS cdb_notification_rules (
    event_key    VARCHAR(60) NOT NULL,
    channel      VARCHAR(12) NOT NULL,
    enabled      TINYINT(1) NOT NULL DEFAULT 1,
    template_id  INT NULL,
    updated_by   INT NULL,
    updated_at   DATETIME NULL,
    PRIMARY KEY (event_key, channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Automations: "when <trigger> [only if <conditions>] then <actions>" --------
CREATE TABLE IF NOT EXISTS cdb_automations (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(150) NOT NULL,
    trigger_key   VARCHAR(60) NOT NULL,
    conditions    TEXT NULL,            -- JSON list [{key,op,value}]
    actions       TEXT NULL,            -- JSON list [{type, ...params}]
    enabled       TINYINT(1) NOT NULL DEFAULT 1,
    run_count     INT UNSIGNED NOT NULL DEFAULT 0,
    last_run_at   DATETIME NULL,
    created_by    INT NULL,
    created_at    DATETIME NOT NULL,
    updated_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_trigger (trigger_key, enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_automation_runs (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    automation_id  INT UNSIGNED NOT NULL,
    trigger_key    VARCHAR(60) NOT NULL,
    entity_type    VARCHAR(40) NULL,
    entity_id      VARCHAR(64) NULL,
    entity_label   VARCHAR(190) NULL,
    outcome        VARCHAR(12) NOT NULL DEFAULT 'ok',   -- ok | skipped | failed | test
    detail         VARCHAR(500) NULL,
    created_at     DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_auto (automation_id, created_at),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- API connections: state and non-secret config per outside service ----------
-- Secrets of payments / SMS / WhatsApp / AI stay in their own tables
-- (cdb_met_payment, cdb_sms_settings, cdb_settings, cdb_ai_settings); only
-- providers without a legacy home (couriers, maps, Slack) keep a secret here.
CREATE TABLE IF NOT EXISTS cdb_api_connections (
    provider_key      VARCHAR(40) NOT NULL,
    category          VARCHAR(20) NOT NULL DEFAULT 'other',
    enabled           TINYINT(1) NOT NULL DEFAULT 0,
    config            TEXT NULL,          -- JSON of non-secret fields
    secret_value      VARCHAR(500) NULL,  -- only for providers with no legacy column
    environment       VARCHAR(10) NOT NULL DEFAULT 'live',
    last_check_at     DATETIME NULL,
    last_check_ok     TINYINT(1) NULL,
    last_check_ms     INT NULL,
    last_check_detail VARCHAR(255) NULL,
    updated_by        INT NULL,
    updated_at        DATETIME NULL,
    PRIMARY KEY (provider_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- REST API request log (api/v1/index.php writes one lean row per request) ---
CREATE TABLE IF NOT EXISTS cdb_api_request_log (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    created_at   DATETIME NOT NULL,
    direction    VARCHAR(4) NOT NULL DEFAULT 'in',   -- in = our API was called
    method       VARCHAR(8) NOT NULL,
    path         VARCHAR(190) NOT NULL,
    status       SMALLINT NOT NULL DEFAULT 0,
    duration_ms  INT NOT NULL DEFAULT 0,
    user_id      INT NULL,
    token_id     BIGINT UNSIGNED NULL,
    ip           VARCHAR(45) NULL,
    user_agent   VARCHAR(120) NULL,
    error        VARCHAR(190) NULL,
    PRIMARY KEY (id),
    KEY idx_created (created_at),
    KEY idx_path (path(60), created_at),
    KEY idx_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- cdb_api_tokens: the table ApiAuth uses (created on first use by the API) ---
CREATE TABLE IF NOT EXISTS cdb_api_tokens (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT NOT NULL,
    token_hash   VARCHAR(128) NOT NULL,
    name         VARCHAR(100) NULL,
    expires_at   DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    created_at   DATETIME NOT NULL,
    revoked_at   DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_token_hash (token_hash),
    KEY idx_user_id (user_id),
    KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shipping addresses: columns the admin page adds (label, mode, on/off) -----
CREATE TABLE IF NOT EXISTS cdb_virtual_mailbox_addresses (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cdb_countries_id BIGINT UNSIGNED NULL,
    country          VARCHAR(190) NULL,
    city             VARCHAR(190) NULL,
    postcode         VARCHAR(40) NULL,
    address          TEXT NULL,
    default_address  TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_virtual_mailbox_addresses' AND COLUMN_NAME = 'label');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_virtual_mailbox_addresses ADD COLUMN label VARCHAR(120) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_virtual_mailbox_addresses' AND COLUMN_NAME = 'shipping_mode');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_virtual_mailbox_addresses ADD COLUMN shipping_mode VARCHAR(10) NOT NULL DEFAULT ''air''', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_virtual_mailbox_addresses' AND COLUMN_NAME = 'transit_days');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_virtual_mailbox_addresses ADD COLUMN transit_days VARCHAR(40) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_virtual_mailbox_addresses' AND COLUMN_NAME = 'is_active');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_virtual_mailbox_addresses ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_virtual_mailbox_addresses' AND COLUMN_NAME = 'sort_order');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_virtual_mailbox_addresses ADD COLUMN sort_order INT NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_virtual_mailbox_addresses' AND COLUMN_NAME = 'updated_at');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_virtual_mailbox_addresses ADD COLUMN updated_at DATETIME NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Permissions -----------------------------------------------------------------
SET @mod := (SELECT id FROM cdb_user_module_permissions WHERE module_name = 'CONFIGURACION_GENERAL' LIMIT 1);
SET @mod_api := (SELECT id FROM cdb_user_module_permissions WHERE module_name = 'API_ADMINISTRATION' LIMIT 1);
INSERT INTO cdb_user_module_actions (module_id, action_name, description_module)
SELECT a.m, a.name, a.d FROM (
    SELECT IFNULL(@mod, 10) AS m, 'push_notifications' AS name, 'Send push notifications to customers' AS d UNION ALL
    SELECT IFNULL(@mod, 10), 'view_message_logs', 'View message logs and message costs' UNION ALL
    SELECT IFNULL(@mod, 10), 'manage_automations', 'Create and edit automations' UNION ALL
    SELECT IFNULL(@mod, 10), 'manage_notification_rules', 'Switch system notifications on or off per channel' UNION ALL
    SELECT IFNULL(@mod, 10), 'manage_shipping_addresses', 'Manage the warehouse shipping addresses shown to customers' UNION ALL
    SELECT IFNULL(@mod_api, 19), 'manage_api_settings', 'Manage API connections to outside services' UNION ALL
    SELECT IFNULL(@mod_api, 19), 'view_api_logs', 'View API request logs and live traffic' UNION ALL
    SELECT IFNULL(@mod_api, 19), 'use_api_tokens', 'Create and revoke own REST API tokens'
) a WHERE NOT EXISTS (SELECT 1 FROM cdb_user_module_actions x WHERE x.action_name = a.name);
INSERT INTO cdb_user_role_permissions (role_id, module_action_id, permitted, created_at)
SELECT r.role_id, ma.id, 1, NOW()
FROM cdb_user_module_actions ma
JOIN (SELECT 9 AS role_id UNION ALL SELECT 2) r
WHERE ma.action_name IN ('push_notifications', 'view_message_logs', 'manage_automations', 'manage_notification_rules',
                         'manage_shipping_addresses', 'manage_api_settings', 'view_api_logs', 'use_api_tokens')
  AND NOT EXISTS (SELECT 1 FROM cdb_user_role_permissions p WHERE p.role_id = r.role_id AND p.module_action_id = ma.id);

-- Default notification rules (match what the legacy senders do today) --------
INSERT INTO cdb_notification_rules (event_key, channel, enabled, template_id, updated_at)
SELECT a.k, a.c, a.e, a.t, NOW() FROM (
    SELECT 'shipment.registered' AS k, 'email' AS c, 1 AS e, 16 AS t UNION ALL
    SELECT 'shipment.registered', 'sms', 1, 2 UNION ALL
    SELECT 'shipment.registered', 'whatsapp', 1, 8 UNION ALL
    SELECT 'shipment.tracking', 'email', 1, 10 UNION ALL
    SELECT 'shipment.tracking', 'sms', 0, 4 UNION ALL
    SELECT 'shipment.tracking', 'whatsapp', 1, 6 UNION ALL
    SELECT 'shipment.delivered', 'email', 1, 18 UNION ALL
    SELECT 'shipment.delivered', 'sms', 1, 5 UNION ALL
    SELECT 'shipment.delivered', 'whatsapp', 1, 7 UNION ALL
    SELECT 'package.registered', 'email', 1, 21 UNION ALL
    SELECT 'package.registered', 'sms', 1, 7 UNION ALL
    SELECT 'package.registered', 'whatsapp', 1, 8 UNION ALL
    SELECT 'package.tracking', 'email', 1, 22 UNION ALL
    SELECT 'package.tracking', 'sms', 0, 8 UNION ALL
    SELECT 'package.tracking', 'whatsapp', 1, 6 UNION ALL
    SELECT 'package.delivered', 'email', 1, 23 UNION ALL
    SELECT 'package.delivered', 'sms', 1, 9 UNION ALL
    SELECT 'package.delivered', 'whatsapp', 1, 7 UNION ALL
    SELECT 'pickup.requested', 'email', 1, 24 UNION ALL
    SELECT 'pickup.requested', 'sms', 1, 11 UNION ALL
    SELECT 'pickup.requested', 'whatsapp', 0, NULL UNION ALL
    SELECT 'consolidation.created', 'email', 1, 1 UNION ALL
    SELECT 'consolidation.created', 'sms', 0, 13 UNION ALL
    SELECT 'consolidation.created', 'whatsapp', 0, NULL UNION ALL
    SELECT 'consolidation.tracking', 'email', 1, 19 UNION ALL
    SELECT 'consolidation.tracking', 'sms', 0, NULL UNION ALL
    SELECT 'consolidation.tracking', 'whatsapp', 0, NULL UNION ALL
    SELECT 'consolidation.delivered', 'email', 1, 14 UNION ALL
    SELECT 'consolidation.delivered', 'sms', 0, 17 UNION ALL
    SELECT 'consolidation.delivered', 'whatsapp', 0, NULL UNION ALL
    SELECT 'prealert.created', 'email', 1, 20 UNION ALL
    SELECT 'prealert.created', 'sms', 0, NULL UNION ALL
    SELECT 'prealert.created', 'whatsapp', 0, NULL UNION ALL
    SELECT 'payment.pending', 'email', 0, NULL UNION ALL
    SELECT 'payment.pending', 'sms', 0, NULL UNION ALL
    SELECT 'payment.pending', 'whatsapp', 1, 1 UNION ALL
    SELECT 'account.welcome', 'email', 1, 7 UNION ALL
    SELECT 'account.welcome', 'sms', 0, NULL UNION ALL
    SELECT 'account.welcome', 'whatsapp', 0, NULL UNION ALL
    SELECT 'account.activated', 'email', 1, 17 UNION ALL
    SELECT 'account.activated', 'sms', 0, NULL UNION ALL
    SELECT 'account.activated', 'whatsapp', 0, NULL UNION ALL
    SELECT 'account.password_reset', 'email', 1, 2 UNION ALL
    SELECT 'account.password_reset', 'sms', 0, NULL UNION ALL
    SELECT 'account.password_reset', 'whatsapp', 0, NULL UNION ALL
    SELECT 'admin.new_user', 'email', 1, 13 UNION ALL
    SELECT 'admin.new_user', 'sms', 0, NULL UNION ALL
    SELECT 'admin.new_user', 'whatsapp', 0, NULL UNION ALL
    SELECT 'push.manual', 'email', 1, 29 UNION ALL
    SELECT 'push.manual', 'sms', 1, NULL UNION ALL
    SELECT 'push.manual', 'whatsapp', 1, 12
) a WHERE NOT EXISTS (SELECT 1 FROM cdb_notification_rules r WHERE r.event_key = a.k AND r.channel = a.c);

INSERT INTO cdb_schema_migrations (name, applied_at) SELECT '2026_11_06_messaging_settings', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_schema_migrations WHERE name = '2026_11_06_messaging_settings');
SELECT 'ok: 2026_11_06_messaging_settings' AS result;
