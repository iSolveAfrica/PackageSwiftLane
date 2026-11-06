-- =============================================================================
-- 2026-11-06  Swift Lane UI: core tables and columns for the new interface
-- Run:  mysql -u USER -p DBNAME < migrations/2026_11_06_swiftlane_ui_core.sql
-- Every step is safe to run again (guards on information_schema).
-- =============================================================================
SET NAMES utf8mb4;

-- Migration ledger ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cdb_schema_migrations (
    name        VARCHAR(190) NOT NULL,
    applied_at  DATETIME NOT NULL,
    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Per-user UI preferences (theme, density) -----------------------------------
CREATE TABLE IF NOT EXISTS cdb_user_preferences (
    user_id     INT NOT NULL,
    pref_key    VARCHAR(40) NOT NULL,
    pref_value  VARCHAR(190) NOT NULL DEFAULT '',
    updated_at  DATETIME NULL,
    PRIMARY KEY (user_id, pref_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Generic settings for the new modules (revenue target, bay capacity, ...) ----
CREATE TABLE IF NOT EXISTS cdb_sl_settings (
    setting_key   VARCHAR(64) NOT NULL,
    setting_value TEXT NULL,
    updated_by    INT NULL,
    updated_at    DATETIME NULL,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Customer blocks (Customers page) -------------------------------------------
CREATE TABLE IF NOT EXISTS cdb_customer_blocks (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT NOT NULL,
    reason          VARCHAR(190) NOT NULL,
    hold_parcels    TINYINT(1) NOT NULL DEFAULT 1,
    notify_customer TINYINT(1) NOT NULL DEFAULT 0,
    blocked_by      INT NULL,
    blocked_at      DATETIME NOT NULL,
    unblocked_by    INT NULL,
    unblocked_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_user_open (user_id, unblocked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- cdb_users: columns the new sign-up and customer pages use ------------------
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_users' AND COLUMN_NAME = 'approve');
SET @sql := IF(@has_col = 0, 'ALTER TABLE cdb_users ADD COLUMN approve TINYINT(1) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_users' AND COLUMN_NAME = 'registration_complete');
SET @sql := IF(@has_col = 0, 'ALTER TABLE cdb_users ADD COLUMN registration_complete TINYINT(1) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_users' AND COLUMN_NAME = 'document_photo');
SET @sql := IF(@has_col = 0, 'ALTER TABLE cdb_users ADD COLUMN document_photo VARCHAR(350) NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_users' AND COLUMN_NAME = 'company');
SET @sql := IF(@has_col = 0, 'ALTER TABLE cdb_users ADD COLUMN company VARCHAR(190) NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_users' AND COLUMN_NAME = 'address');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_users ADD COLUMN address VARCHAR(300) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_users' AND COLUMN_NAME = 'city');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_users ADD COLUMN city VARCHAR(120) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_users' AND COLUMN_NAME = 'country');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_users ADD COLUMN country VARCHAR(120) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_users' AND COLUMN_NAME = 'postal');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_users ADD COLUMN postal VARCHAR(40) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_users' AND COLUMN_NAME = 'signup_source');
SET @sql := IF(@has_col = 0, 'ALTER TABLE cdb_users ADD COLUMN signup_source VARCHAR(40) NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_users' AND COLUMN_NAME = 'password_changed_at');
SET @sql := IF(@has_col = 0, 'ALTER TABLE cdb_users ADD COLUMN password_changed_at DATETIME NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- cdb_settings: exchange rate and OTP switch used by the shell ---------------
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_settings' AND COLUMN_NAME = 'exchange_rate');
SET @sql := IF(@has_col = 0, 'ALTER TABLE cdb_settings ADD COLUMN exchange_rate DECIMAL(12,4) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_settings' AND COLUMN_NAME = 'active_otp');
SET @sql := IF(@has_col = 0, 'ALTER TABLE cdb_settings ADD COLUMN active_otp TINYINT(1) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
CREATE TABLE IF NOT EXISTS cdb_exchange_rate_log (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    old_rate    DECIMAL(12,4) NOT NULL,
    new_rate    DECIMAL(12,4) NOT NULL,
    changed_by  INT NULL,
    changed_at  DATETIME NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Status rows the warehouse and hold workflows use ---------------------------
INSERT INTO cdb_styles (id, mod_style, detail, color) SELECT 28, 'Held', 'Parcel held at the warehouse', '#f57c00' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_styles WHERE id = 28);
INSERT INTO cdb_styles (id, mod_style, detail, color) SELECT 29, 'On Hold', 'Customer account on hold', '#e65100' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_styles WHERE id = 29);
INSERT INTO cdb_styles (id, mod_style, detail, color) SELECT 32, 'Ready for PickUp', 'Parcel ready to be collected', '#2e7d32' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_styles WHERE id = 32);
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_styles' AND COLUMN_NAME = 'status_type');
SET @sql := IF(@has_col = 0, 'ALTER TABLE cdb_styles ADD COLUMN status_type INT NOT NULL DEFAULT 3', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Activity log (helpers/activity_log.php; was sql/activity_log.sql) ----------
CREATE TABLE IF NOT EXISTS cdb_activity_log (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    created_at      DATETIME NOT NULL,
    user_id         INT NULL,
    actor_name      VARCHAR(150) NULL,
    actor_username  VARCHAR(100) NULL,
    role_id         INT NULL,
    role_name       VARCHAR(100) NULL,
    impersonated_by INT NULL,
    module          VARCHAR(60) NULL,
    action          VARCHAR(90) NULL,
    action_label    VARCHAR(160) NULL,
    verb            VARCHAR(20) NULL,
    outcome         VARCHAR(12) NULL,
    summary         VARCHAR(500) NULL,
    entity_type     VARCHAR(60) NULL,
    entity_id       VARCHAR(64) NULL,
    entity_label    VARCHAR(190) NULL,
    status_id       INT NULL,
    status_name     VARCHAR(120) NULL,
    changes         MEDIUMTEXT NULL,
    meta            MEDIUMTEXT NULL,
    ip              VARCHAR(45) NULL,
    user_agent      VARCHAR(255) NULL,
    method          VARCHAR(10) NULL,
    endpoint        VARCHAR(190) NULL,
    PRIMARY KEY (id),
    KEY idx_created (created_at),
    KEY idx_user (user_id, created_at),
    KEY idx_entity (entity_type, entity_id),
    KEY idx_action (module, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Message log (helpers/message_log.php; was sql/message_log.sql) -------------
CREATE TABLE IF NOT EXISTS cdb_message_log (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    created_at        DATETIME NOT NULL,
    channel           VARCHAR(12) NOT NULL,
    source            VARCHAR(80) NULL,
    source_label      VARCHAR(120) NULL,
    template_id       INT NULL,
    subject           VARCHAR(255) NULL,
    body              MEDIUMTEXT NULL,
    recipient_user_id INT NULL,
    recipient_name    VARCHAR(150) NULL,
    recipient_to      VARCHAR(190) NULL,
    entity_type       VARCHAR(60) NULL,
    entity_id         VARCHAR(64) NULL,
    entity_label      VARCHAR(190) NULL,
    batch_id          VARCHAR(40) NULL,
    status            VARCHAR(20) NOT NULL DEFAULT 'sent',
    status_detail     VARCHAR(255) NULL,
    provider_response MEDIUMTEXT NULL,
    sent_by           INT NULL,
    sent_by_name      VARCHAR(150) NULL,
    sent_by_role      VARCHAR(100) NULL,
    ip                VARCHAR(45) NULL,
    endpoint          VARCHAR(190) NULL,
    PRIMARY KEY (id),
    KEY idx_created (created_at),
    KEY idx_recipient (recipient_user_id, created_at),
    KEY idx_channel (channel, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO cdb_schema_migrations (name, applied_at) SELECT '2026_11_06_swiftlane_ui_core', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_schema_migrations WHERE name = '2026_11_06_swiftlane_ui_core');
SELECT 'ok: 2026_11_06_swiftlane_ui_core' AS result;
