-- Swift Lane database package, generated 2026-11-08 by scripts/build_database_package.php
-- Engine: MySQL 8.0+ (tested on 9.7) or MariaDB 10.6+. Character set utf8mb4.
-- UPGRADE of an existing Swift Lane database (baseline: the vendor dump of 2026-02-28 or any later production schema).
-- Every statement is idempotent; the file can be run again after a failure.
-- Take a backup first (see database/README.md).

-- >>> migrations/2026_11_06_swiftlane_ui_core.sql
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


-- >>> migrations/2026_11_06_legacy_drift.sql
-- =============================================================================
-- 2026-11-06  Legacy drift: tables and columns the application code already
--             expects but the shipped install dump (install.done/*.sql,
--             MySQL 8.0.40, 2026-02-28) does not contain. On a live database
--             most of these already exist; every step is guarded and safe to
--             run again.
-- Run:  mysql -u USER -p DBNAME < migrations/2026_11_06_legacy_drift.sql
-- =============================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_schema_migrations (
    name        VARCHAR(190) NOT NULL,
    applied_at  DATETIME NOT NULL,
    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- Column guards: one block per column (table, column, DDL)
-- ---------------------------------------------------------------------------
-- cdb_add_order
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order' AND COLUMN_NAME = 'recipient_type');
SET @sql := IF(@c = 0, "ALTER TABLE cdb_add_order ADD COLUMN recipient_type VARCHAR(20) NOT NULL DEFAULT 'recipient'", 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order' AND COLUMN_NAME = 'notify_whatsapp_sender');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order ADD COLUMN notify_whatsapp_sender TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order' AND COLUMN_NAME = 'courier_notes');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order ADD COLUMN courier_notes TEXT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order' AND COLUMN_NAME = 'is_dangerous_good');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order ADD COLUMN is_dangerous_good TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order' AND COLUMN_NAME = 'discount_type');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order ADD COLUMN discount_type VARCHAR(20) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order' AND COLUMN_NAME = 'fs_cleared_for_delivery');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order ADD COLUMN fs_cleared_for_delivery TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order' AND COLUMN_NAME = 'fs_cleared_at');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order ADD COLUMN fs_cleared_at DATETIME NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order' AND COLUMN_NAME = 'fs_cleared_by');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order ADD COLUMN fs_cleared_by INT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order' AND COLUMN_NAME = 'tracking_purchase');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order ADD COLUMN tracking_purchase VARCHAR(300) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order' AND COLUMN_NAME = 'provider_purchase');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order ADD COLUMN provider_purchase VARCHAR(300) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order' AND COLUMN_NAME = 'price_purchase');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order ADD COLUMN price_purchase DECIMAL(12,2) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order' AND COLUMN_NAME = 'estimated_eta');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order ADD COLUMN estimated_eta DATE NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order' AND INDEX_NAME = 'idx_order_sender_status');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order ADD KEY idx_order_sender_status (sender_id, status_courier, is_deleted)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order' AND INDEX_NAME = 'idx_order_date');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order ADD KEY idx_order_date (order_date)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order' AND INDEX_NAME = 'idx_order_status');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order ADD KEY idx_order_status (status_courier, is_deleted)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order' AND INDEX_NAME = 'idx_order_driver');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order ADD KEY idx_order_driver (driver_id)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- cdb_add_order_item
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order_item' AND COLUMN_NAME = 'custom_price');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order_item ADD COLUMN custom_price DECIMAL(12,2) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order_item' AND COLUMN_NAME = 'priced_at');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order_item ADD COLUMN priced_at DATETIME NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order_item' AND COLUMN_NAME = 'order_item_weight_group');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order_item ADD COLUMN order_item_weight_group VARCHAR(40) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- cdb_customers_packages
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_customers_packages' AND COLUMN_NAME = 'receiver_id');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_customers_packages ADD COLUMN receiver_id INT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_customers_packages' AND COLUMN_NAME = 'receiver_address_id');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_customers_packages ADD COLUMN receiver_address_id INT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_customers_packages' AND COLUMN_NAME = 'recipient_type');
SET @sql := IF(@c = 0, "ALTER TABLE cdb_customers_packages ADD COLUMN recipient_type VARCHAR(20) NOT NULL DEFAULT 'recipient'", 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_customers_packages' AND COLUMN_NAME = 'notify_whatsapp_sender');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_customers_packages ADD COLUMN notify_whatsapp_sender TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_customers_packages' AND COLUMN_NAME = 'estimated_eta');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_customers_packages ADD COLUMN estimated_eta DATE NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_customers_packages' AND INDEX_NAME = 'idx_pkg_sender_status');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_customers_packages ADD KEY idx_pkg_sender_status (sender_id, status_courier)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_customers_packages' AND INDEX_NAME = 'idx_pkg_date');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_customers_packages ADD KEY idx_pkg_date (order_date)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_customers_packages' AND INDEX_NAME = 'idx_pkg_tracking');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_customers_packages ADD KEY idx_pkg_tracking (tracking_purchase(64))', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- cdb_consolidate / cdb_consolidate_packages
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_consolidate' AND COLUMN_NAME = 'awb_no');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_consolidate ADD COLUMN awb_no VARCHAR(40) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_consolidate' AND COLUMN_NAME = 'estimated_eta');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_consolidate ADD COLUMN estimated_eta DATE NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_consolidate' AND COLUMN_NAME = 'recipient_type');
SET @sql := IF(@c = 0, "ALTER TABLE cdb_consolidate ADD COLUMN recipient_type VARCHAR(20) NOT NULL DEFAULT 'recipient'", 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_consolidate' AND COLUMN_NAME = 'is_dangerous_good');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_consolidate ADD COLUMN is_dangerous_good TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_consolidate_packages' AND COLUMN_NAME = 'awb_no');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_consolidate_packages ADD COLUMN awb_no VARCHAR(40) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_consolidate_packages' AND COLUMN_NAME = 'estimated_eta');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_consolidate_packages ADD COLUMN estimated_eta DATE NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_consolidate_packages' AND COLUMN_NAME = 'recipient_type');
SET @sql := IF(@c = 0, "ALTER TABLE cdb_consolidate_packages ADD COLUMN recipient_type VARCHAR(20) NOT NULL DEFAULT 'recipient'", 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_consolidate_packages' AND COLUMN_NAME = 'is_consolidate');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_consolidate_packages ADD COLUMN is_consolidate TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- cdb_courier_track
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_courier_track' AND COLUMN_NAME = 'flight_no');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_courier_track ADD COLUMN flight_no VARCHAR(40) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_courier_track' AND COLUMN_NAME = 'awb_no');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_courier_track ADD COLUMN awb_no VARCHAR(40) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_courier_track' AND INDEX_NAME = 'idx_track_order');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_courier_track ADD KEY idx_track_order (order_id)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_courier_track' AND INDEX_NAME = 'idx_track_date');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_courier_track ADD KEY idx_track_date (t_date)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_courier_track' AND INDEX_NAME = 'idx_track_status');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_courier_track ADD KEY idx_track_status (status_courier, t_date)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- cdb_user_roles: typed roles and inheritance (helpers/rbac.php)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_user_roles' AND COLUMN_NAME = 'parent_role_id');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_user_roles ADD COLUMN parent_role_id INT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_user_roles' AND COLUMN_NAME = 'is_superadmin');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_user_roles ADD COLUMN is_superadmin TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_user_roles' AND COLUMN_NAME = 'is_admin');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_user_roles ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_user_roles' AND COLUMN_NAME = 'is_staff');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_user_roles ADD COLUMN is_staff TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_user_roles' AND COLUMN_NAME = 'is_driver');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_user_roles ADD COLUMN is_driver TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_user_roles' AND COLUMN_NAME = 'is_client');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_user_roles ADD COLUMN is_client TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_user_roles' AND COLUMN_NAME = 'is_agency');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_user_roles ADD COLUMN is_agency TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_user_roles' AND COLUMN_NAME = 'dashboard_type');
SET @sql := IF(@c = 0, "ALTER TABLE cdb_user_roles ADD COLUMN dashboard_type VARCHAR(12) NOT NULL DEFAULT ''", 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
UPDATE cdb_user_roles SET is_superadmin = 1, is_admin = 1, is_staff = 1, dashboard_type = 'admin' WHERE role_id = 9 AND is_superadmin = 0 AND is_admin = 0 AND is_staff = 0;
UPDATE cdb_user_roles SET is_admin = 1, is_staff = 1, dashboard_type = 'admin' WHERE role_id = 2 AND is_admin = 0 AND is_staff = 0;
UPDATE cdb_user_roles SET is_staff = 1, dashboard_type = 'admin' WHERE role_id = 4 AND is_staff = 0 AND is_admin = 0;
UPDATE cdb_user_roles SET is_driver = 1, dashboard_type = 'driver' WHERE role_id = 3 AND is_driver = 0;
UPDATE cdb_user_roles SET is_agency = 1, dashboard_type = 'roles' WHERE role_id = 6 AND is_agency = 0;
UPDATE cdb_user_roles SET is_client = 1, dashboard_type = 'client' WHERE role_id = 1 AND is_client = 0;

-- cdb_countries.iso2 (used by SMS/WhatsApp country lookups)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_countries' AND COLUMN_NAME = 'iso2');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_countries ADD COLUMN iso2 CHAR(2) NULL AFTER iso3', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- cdb_recipients.agency_id (older installs)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_recipients' AND COLUMN_NAME = 'agency_id');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_recipients ADD COLUMN agency_id INT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------------------
-- Tables the helpers create lazily (so a fresh install has them up front)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cdb_package_pickup_aging (
    order_id      INT NOT NULL,
    order_track   VARCHAR(100) NULL,
    sender_id     INT NULL,
    ready_at      DATETIME NULL,
    notified_at   DATETIME NULL,
    notified_by   INT NULL,
    messages_log  TEXT NULL,
    not_picked_at DATETIME NULL,
    auction_at    DATETIME NULL,
    PRIMARY KEY (order_id),
    KEY idx_ready (ready_at),
    KEY idx_sender (sender_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_package_tracking_number (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id        INT NOT NULL,
    user_id         INT NULL,
    tracking_number VARCHAR(120) NULL,
    estimated_eta   DATE NULL,
    PRIMARY KEY (id),
    KEY idx_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_user_details_update_check (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT NOT NULL,
    update_address  TINYINT(1) NOT NULL DEFAULT 0,
    update_phone    TINYINT(1) NOT NULL DEFAULT 0,
    update_document TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_users_multiple_addresses (
    id_addresses INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT NOT NULL,
    country      VARCHAR(190) NULL,
    state        VARCHAR(190) NULL,
    city         VARCHAR(190) NULL,
    zip_code     VARCHAR(40) NULL,
    address      VARCHAR(300) NULL,
    PRIMARY KEY (id_addresses),
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

-- Older installs may have a narrower cdb_virtual_mailbox_addresses: add any missing base column.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_virtual_mailbox_addresses' AND COLUMN_NAME = 'cdb_countries_id');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_virtual_mailbox_addresses ADD COLUMN cdb_countries_id BIGINT UNSIGNED NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_virtual_mailbox_addresses' AND COLUMN_NAME = 'country');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_virtual_mailbox_addresses ADD COLUMN country VARCHAR(190) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_virtual_mailbox_addresses' AND COLUMN_NAME = 'city');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_virtual_mailbox_addresses ADD COLUMN city VARCHAR(190) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_virtual_mailbox_addresses' AND COLUMN_NAME = 'postcode');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_virtual_mailbox_addresses ADD COLUMN postcode VARCHAR(40) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_virtual_mailbox_addresses' AND COLUMN_NAME = 'address');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_virtual_mailbox_addresses ADD COLUMN address TEXT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_virtual_mailbox_addresses' AND COLUMN_NAME = 'default_address');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_virtual_mailbox_addresses ADD COLUMN default_address TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS cdb_pickup_codes (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code         VARCHAR(10)  NOT NULL,
    module       VARCHAR(8)   NOT NULL,
    owner_id     INT          NOT NULL,
    trackings    VARCHAR(2000) NOT NULL DEFAULT '',
    context      VARCHAR(40)  NOT NULL DEFAULT '',
    status       VARCHAR(12)  NOT NULL DEFAULT 'active',
    attempts     INT          NOT NULL DEFAULT 0,
    channels     VARCHAR(255) NOT NULL DEFAULT '',
    created_by   INT          NULL,
    created_at   DATETIME     NOT NULL,
    expires_at   DATETIME     NOT NULL,
    last_sent_at DATETIME     NULL,
    verified_by  INT          NULL,
    verified_at  DATETIME     NULL,
    PRIMARY KEY (id),
    KEY idx_owner (owner_id),
    KEY idx_status (status, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS cdb_pickup_code_items (
    code_id     INT UNSIGNED NOT NULL,
    module      VARCHAR(8)   NOT NULL,
    order_id    INT          NOT NULL,
    used_at     DATETIME     NULL,
    used_by     INT          NULL,
    PRIMARY KEY (code_id, order_id),
    KEY idx_pkg (module, order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_sms_settings (setting_key VARCHAR(64) NOT NULL, setting_value TEXT NULL, updated_by INT NULL, updated_at DATETIME NULL, PRIMARY KEY (setting_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS cdb_message_cost_rates (id INT UNSIGNED NOT NULL AUTO_INCREMENT, channel VARCHAR(12) NOT NULL, unit_cost DECIMAL(14,6) NOT NULL DEFAULT 0, effective_from DATE NOT NULL, created_at DATETIME NULL, created_by INT NOT NULL DEFAULT 0, PRIMARY KEY (id), UNIQUE KEY uq_channel_from (channel, effective_from)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cdb_message_cost_settings (setting_key VARCHAR(40) NOT NULL, setting_value VARCHAR(190) NOT NULL DEFAULT '', updated_at DATETIME NULL, updated_by INT NOT NULL DEFAULT 0, PRIMARY KEY (setting_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cdb_newsletter_designs (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, subject VARCHAR(255) NOT NULL DEFAULT '', preheader VARCHAR(255) NOT NULL DEFAULT '', design_json MEDIUMTEXT NOT NULL, html MEDIUMTEXT NOT NULL, created_by INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, last_sent_at DATETIME NULL, KEY idx_updated (updated_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cdb_newsletter_unsubscribes (email VARCHAR(190) NOT NULL PRIMARY KEY, created_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cdb_newsletter_meta (k VARCHAR(64) NOT NULL PRIMARY KEY, v TEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cdb_profile_update_history (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, update_by INT NOT NULL, prev_document VARCHAR(350) NULL, remarks VARCHAR(255) NULL, datetime DATETIME NOT NULL, INDEX idx_profile_history_user (user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cdb_profile_phone_verified (user_id INT NOT NULL PRIMARY KEY, phone VARCHAR(32) NOT NULL, method VARCHAR(20) NOT NULL, verified_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cdb_unique_ids (scope VARCHAR(40) NOT NULL, value VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (scope, value)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cdb_whatsapp_number_cache (phone VARCHAR(32) NOT NULL PRIMARY KEY, is_whatsapp TINYINT(1) NOT NULL DEFAULT 0, checked_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cdb_package_edit_locks (order_id INT NOT NULL PRIMARY KEY, locked_by INT NOT NULL, locked_by_name VARCHAR(150) NULL, locked_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cdb_auth_otp_challenges (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, purpose VARCHAR(20) NOT NULL, channel VARCHAR(20) NOT NULL DEFAULT 'email', code_hash VARCHAR(255) NOT NULL, code_salt VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, max_attempts TINYINT NOT NULL DEFAULT 5, attempts TINYINT NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'pending', metadata TEXT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_user_purpose_status (user_id, purpose, status), INDEX idx_expires_at (expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cdb_auth_trusted_devices (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, selector VARCHAR(24) NOT NULL UNIQUE, verifier_hash VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL, last_used_at DATETIME NULL, revoked_at DATETIME NULL, INDEX idx_user_expires (user_id, expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cdb_password_reset_sessions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, token_hash VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL, consumed_at DATETIME NULL, created_at DATETIME NOT NULL, INDEX idx_user_expiry (user_id, expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cdb_api_tokens (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id INT NOT NULL, token_hash VARCHAR(128) NOT NULL, name VARCHAR(100) NULL, expires_at DATETIME NOT NULL, last_used_at DATETIME NULL, created_at DATETIME NOT NULL, revoked_at DATETIME NULL, PRIMARY KEY (id), UNIQUE KEY uq_token_hash (token_hash), KEY idx_user_id (user_id), KEY idx_expires (expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permission actions the code checks that the dump never seeded ---------------
SET @mod := (SELECT MIN(id) FROM cdb_user_module_permissions);
INSERT INTO cdb_user_module_permissions (module_name, description) SELECT 'SWIFTLANE_UI', 'Swift Lane interface, logs, finance and warehouse permissions' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_user_module_permissions WHERE module_name = 'SWIFTLANE_UI');
SET @mod := (SELECT id FROM cdb_user_module_permissions WHERE module_name = 'SWIFTLANE_UI' LIMIT 1);
INSERT INTO cdb_user_module_actions (module_id, action_name, description_module)
SELECT @mod, a.name, a.descr FROM (
    SELECT 'approve_client' AS name, 'Approve customer accounts (KYC)' AS descr UNION ALL
    SELECT 'client_virtual_mail_box_addresses', 'Customer: see virtual mailbox addresses' UNION ALL
    SELECT 'edit_client_avatar', 'Edit customer photo' UNION ALL
    SELECT 'edit_client_document', 'Edit customer ID document' UNION ALL
    SELECT 'export_activity_logs', 'Export activity logs' UNION ALL
    SELECT 'export_message_logs', 'Export message logs' UNION ALL
    SELECT 'export_staff_productivity', 'Export staff productivity' UNION ALL
    SELECT 'financial_sheet', 'Billing and finance hub' UNION ALL
    SELECT 'fs_check_payment', 'Check gateway payment status' UNION ALL
    SELECT 'fs_clear_delivery', 'Clear parcels for delivery after payment' UNION ALL
    SELECT 'pay_my_bills', 'Customer: pay bills online' UNION ALL
    SELECT 'push_notifications', 'Send push notifications' UNION ALL
    SELECT 'top_users_air_print', 'Print top air customers' UNION ALL
    SELECT 'top_users_sea_print', 'Print top sea customers' UNION ALL
    SELECT 'view_activity_logs', 'View activity logs' UNION ALL
    SELECT 'view_advanced_shipping_reports', 'View advanced shipping reports' UNION ALL
    SELECT 'view_financial_overview', 'View financial overview' UNION ALL
    SELECT 'view_message_logs', 'View message logs and costs' UNION ALL
    SELECT 'view_my_bills', 'Customer: view my bills' UNION ALL
    SELECT 'view_pickup_aging', 'View pickup aging' UNION ALL
    SELECT 'view_staff_productivity', 'View staff productivity' UNION ALL
    SELECT 'view_system_logs', 'View system logs and security centre' UNION ALL
    SELECT 'view_top_users_air', 'View top air customers' UNION ALL
    SELECT 'view_top_users_sea', 'View top sea customers' UNION ALL
    SELECT 'view_transactions', 'View transactions' UNION ALL
    SELECT 'view_warehouse_delivery', 'Warehouse delivery and hand out' UNION ALL
    SELECT 'warehouse_deliver_package', 'Hand out a package' UNION ALL
    SELECT 'warehouse_deliver_user', 'Hand out all packages of a customer' UNION ALL
    SELECT 'warehouse_undo_delivery', 'Undo a warehouse delivery' UNION ALL
    SELECT 'warehouse_view', 'View the warehouse' UNION ALL
    SELECT 'manage_api_settings', 'Manage API connections' UNION ALL
    SELECT 'manage_api_clients', 'Manage API clients' UNION ALL
    SELECT 'view_api_sessions', 'View API sessions' UNION ALL
    SELECT 'view_api_logs', 'View API traffic' UNION ALL
    SELECT 'manage_loyalty', 'Manage loyalty and referrals' UNION ALL
    SELECT 'manage_automations', 'Manage automations'
) a WHERE NOT EXISTS (SELECT 1 FROM cdb_user_module_actions x WHERE x.action_name = a.name);
-- Grant the new actions to the super admin (9) and administrator (2) roles.
INSERT INTO cdb_user_role_permissions (role_id, module_action_id, permitted, created_at)
SELECT r.role_id, ma.id, 1, NOW() FROM cdb_user_module_actions ma JOIN cdb_user_roles r ON r.role_id IN (9, 2)
WHERE ma.module_id = @mod AND ma.action_name NOT IN ('client_virtual_mail_box_addresses', 'pay_my_bills', 'view_my_bills')
  AND NOT EXISTS (SELECT 1 FROM cdb_user_role_permissions p WHERE p.role_id = r.role_id AND p.module_action_id = ma.id);
-- Customer-facing actions go to the customer role (1).
INSERT INTO cdb_user_role_permissions (role_id, module_action_id, permitted, created_at)
SELECT 1, ma.id, 1, NOW() FROM cdb_user_module_actions ma
WHERE ma.action_name IN ('client_virtual_mail_box_addresses', 'pay_my_bills', 'view_my_bills')
  AND NOT EXISTS (SELECT 1 FROM cdb_user_role_permissions p WHERE p.role_id = 1 AND p.module_action_id = ma.id);


-- One-time code e-mail templates used by lib/OtpService.php (ids 27 forgot, 28 login, 30 sign-up).
-- Only inserted when missing; an existing template with the same id is never changed.
INSERT INTO cdb_email_templates (id, name, subject, help, body) SELECT 27, 'Password reset code', 'Your [SITE_NAME] password reset code', 'Placeholders: [USERNAME] [SITE_NAME] [PASSWORD] (the code) [IP] [URL] [TTL]', '<p>Hello [USERNAME],</p><p>Use this code to reset your [SITE_NAME] password: <b style="font-size:20px;letter-spacing:3px">[PASSWORD]</b></p><p>The code expires in [TTL]. If you did not ask for it, ignore this e-mail. Request from IP [IP].</p>' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_email_templates WHERE id = 27);
INSERT INTO cdb_email_templates (id, name, subject, help, body) SELECT 28, 'Login code', 'Your [SITE_NAME] sign-in code', 'Placeholders: [USERNAME] [SITE_NAME] [PASSWORD] (the code) [IP] [URL] [TTL]', '<p>Hello [USERNAME],</p><p>Your [SITE_NAME] sign-in code is <b style="font-size:20px;letter-spacing:3px">[PASSWORD]</b></p><p>The code expires in [TTL]. If this was not you, change your password. Request from IP [IP].</p>' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_email_templates WHERE id = 28);
INSERT INTO cdb_email_templates (id, name, subject, help, body) SELECT 30, 'Sign-up verification code', 'Confirm your [SITE_NAME] account', 'Placeholders: [USERNAME] [SITE_NAME] [PASSWORD] (the code) [IP] [URL] [TTL]', '<p>Welcome [USERNAME],</p><p>Enter this code to confirm your [SITE_NAME] account: <b style="font-size:20px;letter-spacing:3px">[PASSWORD]</b></p><p>The code expires in [TTL].</p>' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_email_templates WHERE id = 30);

INSERT INTO cdb_schema_migrations (name, applied_at) SELECT '2026_11_06_legacy_drift', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_schema_migrations WHERE name = '2026_11_06_legacy_drift');
SELECT 'ok: 2026_11_06_legacy_drift' AS result;


-- >>> migrations/2026_11_04_pickup_codes_handover_sms_mailbox.sql
-- ============================================================================
-- 2026-11-04  Pickup codes in the ready message, Picked up vs Delivered,
--             China virtual mailbox addresses.
--
-- Run once against the live database, e.g.
--   mysql -u USER -p DBNAME < migrations/2026_11_04_pickup_codes_handover_sms_mailbox.sql
-- Every step is safe to run again.
--
-- No step is needed for the SMS change: it is code only (helpers/hubtel_sms.php).
-- ============================================================================

SET NAMES utf8mb4;

-- Guard added 2026-11-06: cdb_countries.iso2 and cdb_virtual_mailbox_addresses
-- are referenced below but do not exist on the vendor baseline dump.
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_countries' AND COLUMN_NAME = 'iso2');
SET @sql := IF(@has_col = 0, 'ALTER TABLE cdb_countries ADD COLUMN iso2 CHAR(2) NULL AFTER iso3', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
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

-- ----------------------------------------------------------------------------
-- 1. Pickup codes (helpers/pickup_code.php)
--    The app creates these tables on first use; this makes sure they exist and
--    adds last_sent_at to tables made before codes went into the ready message.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cdb_pickup_codes (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code         VARCHAR(10)  NOT NULL,
    module       VARCHAR(8)   NOT NULL,
    owner_id     INT          NOT NULL,
    trackings    VARCHAR(2000) NOT NULL DEFAULT '',
    context      VARCHAR(40)  NOT NULL DEFAULT '',
    status       VARCHAR(12)  NOT NULL DEFAULT 'active',
    attempts     INT          NOT NULL DEFAULT 0,
    channels     VARCHAR(255) NOT NULL DEFAULT '',
    created_by   INT          NULL,
    created_at   DATETIME     NOT NULL,
    expires_at   DATETIME     NOT NULL,
    last_sent_at DATETIME     NULL,
    verified_by  INT          NULL,
    verified_at  DATETIME     NULL,
    PRIMARY KEY (id),
    KEY idx_owner (owner_id),
    KEY idx_status (status, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_pickup_code_items (
    code_id     INT UNSIGNED NOT NULL,
    module      VARCHAR(8)   NOT NULL,
    order_id    INT          NOT NULL,
    used_at     DATETIME     NULL,
    used_by     INT          NULL,
    PRIMARY KEY (code_id, order_id),
    KEY idx_pkg (module, order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_pickup_codes' AND COLUMN_NAME = 'last_sent_at');
SET @sql := IF(@has_col = 0,
               'ALTER TABLE cdb_pickup_codes ADD COLUMN last_sent_at DATETIME NULL AFTER expires_at',
               'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Codes from the old 3-minute flow that were never used are finished.
UPDATE cdb_pickup_codes SET status = 'expired'
WHERE status = 'active' AND expires_at < NOW();

-- ----------------------------------------------------------------------------
-- 2. Picked up and Delivered are two statuses, each named for what it is.
--    15 Picked up = the customer collected it from us.
--    8  Delivered = we took it to the customer.
-- ----------------------------------------------------------------------------
INSERT INTO cdb_styles (id, mod_style, detail, color)
SELECT 15, 'Picked up', 'Picked up package', '#00adf2'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_styles WHERE id = 15);

INSERT INTO cdb_styles (id, mod_style, detail, color)
SELECT 8, 'Delivered', 'Deliveries delivered', '#43bd00'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_styles WHERE id = 8);

UPDATE cdb_styles SET mod_style = 'Picked up', detail = 'Picked up by the customer (collected from us)' WHERE id = 15;
UPDATE cdb_styles SET mod_style = 'Delivered', detail = 'Delivered to the customer (taken to them)'   WHERE id = 8;

-- ----------------------------------------------------------------------------
-- 3. China virtual mailbox addresses (cdb_virtual_mailbox_addresses)
--    The first two China addresses (lowest id first) are replaced, in order:
--      1st -> Guangzhou air warehouse (normal)
--      2nd -> Sea address
--    and the Beijing air express address is added after them.
--    "(LOCKER ID)" is replaced by each customer's locker when shown.
--    As on the existing China rows, city stays 'China' and the postcode
--    field carries the warehouse phone number.
-- ----------------------------------------------------------------------------
SET @cn1 := (SELECT v.id FROM cdb_virtual_mailbox_addresses v
             LEFT JOIN cdb_countries c ON c.id = v.cdb_countries_id
             WHERE c.iso2 = 'CN' OR c.name LIKE '%China%'
             ORDER BY v.id LIMIT 1);
SET @cn2 := (SELECT v.id FROM cdb_virtual_mailbox_addresses v
             LEFT JOIN cdb_countries c ON c.id = v.cdb_countries_id
             WHERE (c.iso2 = 'CN' OR c.name LIKE '%China%') AND v.id <> IFNULL(@cn1, 0)
             ORDER BY v.id LIMIT 1);

UPDATE cdb_virtual_mailbox_addresses
SET address = '广州空运普快地址：GUANGZHOU AIR WAREHOUSE ADDRESS (NORMAL)

广东省佛山市南海区里水镇贤僚村贤僚通道18号航威云仓D233库
SFSWIFT(LOCKER ID)
收件人：SFSWIFT(LOCKER ID)
电话：18122021422',
    city = 'China',
    postcode = '18122021422'
WHERE id = @cn1;

UPDATE cdb_virtual_mailbox_addresses
SET address = 'Sea Address

广东省广州市花都区新雅街道镜湖大道南自编79号
(泽贤汽车检测站内) ACCSWIFT(LOCKER ID)
联系人：ACCSWIFT (LOCKER ID  )
仓库电话：19924601872',
    city = 'China',
    postcode = '19924601872'
WHERE id = @cn2;

-- Beijing: a copy of the first China row (same country and any other columns),
-- with its own address. Skipped when it is already there.
SET @has_bj := (SELECT COUNT(*) FROM cdb_virtual_mailbox_addresses
                WHERE address LIKE '%北京市大兴区空港新苑二区6号院8号楼2-103室%');

SET @new_id := (SELECT IFNULL(MAX(id), 0) + 1 FROM cdb_virtual_mailbox_addresses);

DROP TEMPORARY TABLE IF EXISTS tmp_mailbox_bj;
CREATE TEMPORARY TABLE tmp_mailbox_bj SELECT * FROM cdb_virtual_mailbox_addresses WHERE id = @cn1 AND @has_bj = 0;
UPDATE tmp_mailbox_bj
SET id = @new_id,
    address = '顺丰北京空运快件地址变更为： Beijing Air Express Address

北京市大兴区空港新苑二区6号院8号楼2-103室
SFSWIFT(LOCKER ID )
收件人：SFSWIFT(LOCKER ID )
电话： 17611390952',
    city = 'China',
    postcode = '17611390952',
    default_address = 0;
INSERT INTO cdb_virtual_mailbox_addresses SELECT * FROM tmp_mailbox_bj;
DROP TEMPORARY TABLE IF EXISTS tmp_mailbox_bj;

-- Check: the China addresses as they now stand.
SELECT v.id, c.name AS country, v.city, v.postcode, v.address
FROM cdb_virtual_mailbox_addresses v
LEFT JOIN cdb_countries c ON c.id = v.cdb_countries_id
WHERE c.iso2 = 'CN' OR c.name LIKE '%China%'
ORDER BY v.id;

-- Ledger (added 2026-11-06)
CREATE TABLE IF NOT EXISTS cdb_schema_migrations (name VARCHAR(190) NOT NULL, applied_at DATETIME NOT NULL, PRIMARY KEY (name)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO cdb_schema_migrations (name, applied_at) SELECT '2026_11_04_pickup_codes_handover_sms_mailbox', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_schema_migrations WHERE name = '2026_11_04_pickup_codes_handover_sms_mailbox');


-- >>> migrations/2026_11_06_auth_portal.sql
-- =============================================================================
-- 2026-11-06  Swift Lane UI: authentication, public pages and customer portal
-- Run:  mysql -u USER -p DBNAME < migrations/2026_11_06_auth_portal.sql
-- Every step is safe to run again (guards on information_schema / WHERE NOT EXISTS).
-- Requires 2026_11_06_swiftlane_ui_core.sql (cdb_sl_settings, cdb_user_preferences,
-- cdb_users.password_changed_at, cdb_schema_migrations).
-- =============================================================================
SET NAMES utf8mb4;

-- Per-channel notification preferences of a customer (CustomerNotifications) ---
-- channel: whatsapp | sms | email      event_key: '*' = channel master switch,
-- otherwise one of the keys in views/sl/data/portal.php (sl_notificationEvents()).
CREATE TABLE IF NOT EXISTS cdb_user_notification_prefs (
    user_id     INT NOT NULL,
    channel     VARCHAR(20) NOT NULL,
    event_key   VARCHAR(40) NOT NULL,
    enabled     TINYINT(1) NOT NULL DEFAULT 1,
    updated_at  DATETIME NULL,
    PRIMARY KEY (user_id, channel, event_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Password rotation (PasswordReset board): 0 = off, N = must change every N days.
INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_at)
    SELECT 'password_rotation_days', '0', NOW() FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM cdb_sl_settings WHERE setting_key = 'password_rotation_days');

-- Login page pictures and captions (LoginSettings board) are stored as JSON in
-- cdb_sl_settings.login_slides; the look settings keep their own keys so an
-- operator can edit them by hand.
INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_at)
    SELECT 'login_blur', '50', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_sl_settings WHERE setting_key = 'login_blur');
INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_at)
    SELECT 'login_dim', '30', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_sl_settings WHERE setting_key = 'login_dim');
INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_at)
    SELECT 'login_interval', '6', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_sl_settings WHERE setting_key = 'login_interval');
INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_at)
    SELECT 'login_captions', '1', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_sl_settings WHERE setting_key = 'login_captions');
INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_at)
    SELECT 'login_pages', 'login,register,forgot', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_sl_settings WHERE setting_key = 'login_pages');

-- Users who never changed their password count from the day the column arrived,
-- so enabling rotation does not lock everybody out at once.
UPDATE cdb_users SET password_changed_at = NOW() WHERE password_changed_at IS NULL;

INSERT INTO cdb_schema_migrations (name, applied_at) SELECT '2026_11_06_auth_portal', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_schema_migrations WHERE name = '2026_11_06_auth_portal');
SELECT 'ok: 2026_11_06_auth_portal' AS result;


-- >>> migrations/2026_11_06_finance_reports.sql
-- =============================================================================
-- 2026-11-06  Swift Lane UI: finance, tariffs, reports and control panels
-- Run:  mysql -u USER -p DBNAME < migrations/2026_11_06_finance_reports.sql
-- Every step is safe to run again (guards on information_schema). Never drops.
-- =============================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_schema_migrations (
    name        VARCHAR(190) NOT NULL,
    applied_at  DATETIME NOT NULL,
    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Financial module ledgers (helpers/fs_payments.php, fs_status.php, fs_reports.php,
-- dashboard_data.php and ajax/reports/financial_sheet_ajax.php). These tables had
-- no DDL in the repository; the columns below are the ones that code reads/writes.
CREATE TABLE IF NOT EXISTS cdb_consolidate_customer_billing (
    consolidate_id  INT NOT NULL,
    sender_id       INT NOT NULL,
    amount_usd      DECIMAL(14,2) NOT NULL DEFAULT 0,
    amount_ghs      DECIMAL(14,2) NULL,
    handling_ghs    DECIMAL(14,2) NULL,
    discount_ghs    DECIMAL(14,2) NULL,
    paid_ghs        DECIMAL(14,2) NULL,
    paid_at         DATETIME NULL,
    paid_by         INT NULL,
    exchange_rate   DECIMAL(12,4) NOT NULL DEFAULT 0,
    billed_by       INT NULL,
    billed_at       DATETIME NULL,
    PRIMARY KEY (consolidate_id, sender_id),
    KEY idx_sender (sender_id),
    KEY idx_billed (billed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_consolidate_billing_notes (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    consolidate_id  INT NOT NULL,
    sender_id       INT NOT NULL,
    note            TEXT NULL,
    created_by      INT NULL,
    created_at      DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_cs (consolidate_id, sender_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_fs_payments (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    consolidate_id       INT NOT NULL DEFAULT 0,
    sender_id            INT NOT NULL,
    amount_ghs           DECIMAL(14,2) NOT NULL DEFAULT 0,
    refunded_ghs         DECIMAL(14,2) NULL,
    refunded_at          DATETIME NULL,
    mode                 VARCHAR(20) NOT NULL DEFAULT 'cash',
    reference            VARCHAR(190) NULL,
    gateway_status       VARCHAR(30) NULL,
    gateway_raw_status   VARCHAR(60) NULL,
    gateway_response     VARCHAR(255) NULL,
    gateway_channel      VARCHAR(60) NULL,
    gateway_currency     VARCHAR(10) NULL,
    gateway_fees_ghs     DECIMAL(14,2) NULL,
    gateway_customer     VARCHAR(190) NULL,
    gateway_paid_at      DATETIME NULL,
    gateway_checked_at   DATETIME NULL,
    gateway_payload      MEDIUMTEXT NULL,
    reversed_at          DATETIME NULL,
    reversal_reason      VARCHAR(255) NULL,
    cleared_for_delivery TINYINT(1) NOT NULL DEFAULT 0,
    cleared_orders       TEXT NULL,
    note                 VARCHAR(500) NULL,
    exchange_rate        DECIMAL(12,4) NOT NULL DEFAULT 0,
    recorded_by          INT NULL,
    recorded_at          DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_cs (consolidate_id, sender_id),
    KEY idx_recorded (recorded_at),
    KEY idx_reference (reference),
    KEY idx_status (gateway_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_fs_payment_intents (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference      VARCHAR(190) NOT NULL,
    consolidate_id INT NOT NULL DEFAULT 0,
    sender_id      INT NOT NULL,
    amount_ghs     DECIMAL(14,2) NOT NULL DEFAULT 0,
    exchange_rate  DECIMAL(12,4) NOT NULL DEFAULT 0,
    mode           VARCHAR(20) NOT NULL DEFAULT 'paystack',
    orders         TEXT NULL,
    status         VARCHAR(20) NOT NULL DEFAULT 'pending',
    checkout_url   VARCHAR(500) NULL,
    payment_id     INT UNSIGNED NULL,
    fail_reason    VARCHAR(255) NULL,
    gateway_payload MEDIUMTEXT NULL,
    created_by     INT NULL,
    created_at     DATETIME NOT NULL,
    completed_at   DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reference (reference),
    KEY idx_cs (consolidate_id, sender_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_fs_payment_events (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_id  INT UNSIGNED NULL,
    reference   VARCHAR(190) NULL,
    source      VARCHAR(40) NOT NULL DEFAULT 'unknown',
    event       VARCHAR(60) NULL,
    old_status  VARCHAR(30) NULL,
    new_status  VARCHAR(30) NULL,
    amount_ghs  DECIMAL(14,2) NULL,
    message     VARCHAR(255) NULL,
    payload     TEXT NULL,
    actor_id    INT NULL,
    created_at  DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_payment (payment_id),
    KEY idx_reference (reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_fs_discounts (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    consolidate_id INT NOT NULL DEFAULT 0,
    sender_id      INT NOT NULL,
    order_id       INT NULL,
    amount_ghs     DECIMAL(14,2) NOT NULL DEFAULT 0,
    disc_type      VARCHAR(10) NOT NULL DEFAULT 'fixed',
    reason         VARCHAR(255) NULL,
    exchange_rate  DECIMAL(12,4) NOT NULL DEFAULT 0,
    applied_by     INT NULL,
    applied_at     DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_cs (consolidate_id, sender_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Operating costs (Cost Settings, super admin) ---------------------------------
CREATE TABLE IF NOT EXISTS cdb_operating_costs (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(120) NOT NULL,
    category     VARCHAR(30) NOT NULL DEFAULT 'other',
    amount       DECIMAL(14,2) NOT NULL DEFAULT 0,
    currency     VARCHAR(5) NOT NULL DEFAULT 'GHS',
    frequency    VARCHAR(10) NOT NULL DEFAULT 'monthly',
    unit_label   VARCHAR(40) NULL,
    on_billing   TINYINT(1) NOT NULL DEFAULT 1,
    applies_from DATE NULL,
    is_active    TINYINT(1) NOT NULL DEFAULT 1,
    created_by   INT NULL,
    created_at   DATETIME NOT NULL,
    updated_by   INT NULL,
    updated_at   DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_active (is_active, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_operating_cost_log (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cost_id     INT UNSIGNED NULL,
    cost_name   VARCHAR(120) NOT NULL,
    old_amount  DECIMAL(14,2) NULL,
    new_amount  DECIMAL(14,2) NULL,
    changed_by  INT NULL,
    changed_at  DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_changed (changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tariff rows: minimum charge and validity window shown by the rate editor -----
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_shipping_fees' AND COLUMN_NAME = 'min_charge');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_shipping_fees ADD COLUMN min_charge DECIMAL(12,2) NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_shipping_fees' AND COLUMN_NAME = 'valid_from');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_shipping_fees ADD COLUMN valid_from DATE NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_shipping_fees' AND COLUMN_NAME = 'valid_until');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_shipping_fees ADD COLUMN valid_until DATE NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Settings read by the new pages (finance switch, targets) -------------------
CREATE TABLE IF NOT EXISTS cdb_sl_settings (
    setting_key   VARCHAR(64) NOT NULL,
    setting_value TEXT NULL,
    updated_by    INT NULL,
    updated_at    DATETIME NULL,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_at) SELECT 'finance_enabled', '1', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_sl_settings WHERE setting_key = 'finance_enabled');
INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_at) SELECT 'margin_target_pct', '42', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_sl_settings WHERE setting_key = 'margin_target_pct');
INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_at) SELECT 'price_message_template', 'Hello [NAME], your package [TRACKING] is priced at [PRICE]. Reply to this message or pay online: [URL]', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_sl_settings WHERE setting_key = 'price_message_template');

-- Permissions ---------------------------------------------------------------
INSERT INTO cdb_user_module_permissions (module_name, description) SELECT 'SWIFTLANE_UI', 'Swift Lane interface, logs, finance and warehouse permissions' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_user_module_permissions WHERE module_name = 'SWIFTLANE_UI');
SET @mod := (SELECT id FROM cdb_user_module_permissions WHERE module_name = 'SWIFTLANE_UI' LIMIT 1);
INSERT INTO cdb_user_module_actions (module_id, action_name, description_module)
SELECT @mod, a.name, a.descr FROM (
    SELECT 'fs_price_items' AS name, 'Finance: price package items' AS descr UNION ALL
    SELECT 'fs_bill_customer', 'Finance: bill a customer' UNION ALL
    SELECT 'fs_record_payment', 'Finance: record a payment' UNION ALL
    SELECT 'fs_apply_discount', 'Finance: apply or remove a discount' UNION ALL
    SELECT 'view_monetary_values', 'See money figures on dashboards and reports' UNION ALL
    SELECT 'view_money_dashboard', 'See money on the dashboards' UNION ALL
    SELECT 'view_money_finance', 'See money on the financial overview' UNION ALL
    SELECT 'manage_operating_costs', 'Edit operating costs (super admin)' UNION ALL
    SELECT 'send_prices', 'Send package prices to customers' UNION ALL
    SELECT 'manage_finance_switch', 'Switch the finance module on or off' UNION ALL
    SELECT 'view_exec_dashboard', 'View the management dashboard'
) a WHERE NOT EXISTS (SELECT 1 FROM cdb_user_module_actions x WHERE x.action_name = a.name);
INSERT INTO cdb_user_role_permissions (role_id, module_action_id, permitted, created_at)
SELECT r.role_id, ma.id, 1, NOW() FROM cdb_user_module_actions ma JOIN cdb_user_roles r ON r.role_id IN (9, 2)
WHERE ma.action_name IN ('fs_price_items','fs_bill_customer','fs_record_payment','fs_apply_discount','view_monetary_values','view_money_dashboard','view_money_finance','manage_operating_costs','send_prices','manage_finance_switch','view_exec_dashboard')
  AND NOT EXISTS (SELECT 1 FROM cdb_user_role_permissions p WHERE p.role_id = r.role_id AND p.module_action_id = ma.id);

INSERT INTO cdb_schema_migrations (name, applied_at) SELECT '2026_11_06_finance_reports', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_schema_migrations WHERE name = '2026_11_06_finance_reports');
SELECT 'ok: 2026_11_06_finance_reports' AS result;


-- >>> migrations/2026_11_06_freight_devops.sql
-- =============================================================================
-- 2026-11-06  Swift Lane UI: freight operations, last mile, security and the
--             developer console (new capabilities, no legacy equivalent)
-- Run:  mysql -u USER -p DBNAME < migrations/2026_11_06_freight_devops.sql
-- Every step is safe to run again (CREATE IF NOT EXISTS, WHERE NOT EXISTS).
-- Never drops or truncates anything.
-- =============================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_schema_migrations (
    name        VARCHAR(190) NOT NULL,
    applied_at  DATETIME NOT NULL,
    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Freight partners: airlines, shipping lines, trucking, customs brokers, ... --
CREATE TABLE IF NOT EXISTS cdb_freight_partners (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name           VARCHAR(150) NOT NULL,
    type           VARCHAR(30) NOT NULL DEFAULT 'airline',   -- airline | shipping_line | trucking | customs_broker | ground_handler | us_carrier | insurer
    code           VARCHAR(20) NULL,                          -- IATA / SCAC code
    contact_name   VARCHAR(150) NULL,
    contact_phone  VARCHAR(60) NULL,
    contact_email  VARCHAR(190) NULL,
    lanes          VARCHAR(255) NULL,                         -- e.g. "JFK → ACC, IAD → ACC"
    rate_value     DECIMAL(12,4) NULL,
    rate_unit      VARCHAR(30) NULL,                          -- per kg | per ft3 | per container | per declaration
    rate_currency  VARCHAR(5) NULL,
    contract_ends  DATE NULL,
    api_link       VARCHAR(190) NULL,
    notes          TEXT NULL,
    active         TINYINT(1) NOT NULL DEFAULT 1,
    created_by     INT NULL,
    created_at     DATETIME NOT NULL,
    updated_at     DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_type (type, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Departures and arrivals -----------------------------------------------------
CREATE TABLE IF NOT EXISTS cdb_freight_schedules (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    mode              VARCHAR(5) NOT NULL DEFAULT 'air',      -- air | sea
    partner_id        INT UNSIGNED NULL,
    carrier_name      VARCHAR(150) NULL,
    voyage_no         VARCHAR(60) NOT NULL,                   -- flight or vessel number
    origin            VARCHAR(80) NOT NULL,
    destination       VARCHAR(80) NOT NULL,
    cutoff_at         DATETIME NULL,
    departs_at        DATETIME NOT NULL,
    arrives_at        DATETIME NULL,                          -- carrier ETA
    arrived_at        DATETIME NULL,                          -- actual arrival
    status            VARCHAR(20) NOT NULL DEFAULT 'scheduled', -- scheduled | boarding | in_transit | arrived | delayed | cancelled
    capacity_total    DECIMAL(12,2) NULL,
    capacity_booked   DECIMAL(12,2) NOT NULL DEFAULT 0,
    capacity_unit     VARCHAR(10) NOT NULL DEFAULT 'pkgs',    -- pkgs | kg | ft3
    consolidation_ids VARCHAR(500) NULL,                      -- comma separated c_no values
    notes             TEXT NULL,
    created_by        INT NULL,
    created_at        DATETIME NOT NULL,
    updated_at        DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_departs (departs_at, status),
    KEY idx_partner (partner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Master / house waybills and manifests --------------------------------------
CREATE TABLE IF NOT EXISTS cdb_waybills (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kind             VARCHAR(10) NOT NULL DEFAULT 'master',   -- master | house
    waybill_no       VARCHAR(60) NOT NULL,
    parent_id        INT UNSIGNED NULL,                       -- house → master
    schedule_id      INT UNSIGNED NULL,
    consolidation_no VARCHAR(100) NULL,
    carrier          VARCHAR(150) NULL,
    origin           VARCHAR(80) NULL,
    destination      VARCHAR(80) NULL,
    status           VARCHAR(12) NOT NULL DEFAULT 'draft',    -- draft | issued | sent | closed
    pieces           INT NOT NULL DEFAULT 0,
    chargeable_kg    DECIMAL(12,2) NOT NULL DEFAULT 0,
    rate_per_kg      DECIMAL(12,4) NOT NULL DEFAULT 0,
    surcharge_per_kg DECIMAL(12,4) NOT NULL DEFAULT 0,
    total_cost       DECIMAL(12,2) NOT NULL DEFAULT 0,
    container_no     VARCHAR(40) NULL,
    seal_no          VARCHAR(40) NULL,
    container_ft3    DECIMAL(12,2) NULL,
    issued_at        DATETIME NULL,
    issued_by        INT NULL,
    sent_at          DATETIME NULL,
    sent_to          VARCHAR(190) NULL,
    notes            TEXT NULL,
    created_by       INT NULL,
    created_at       DATETIME NOT NULL,
    updated_at       DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_no (waybill_no),
    KEY idx_schedule (schedule_id),
    KEY idx_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_waybill_items (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    waybill_id    INT UNSIGNED NOT NULL,
    source        VARCHAR(14) NOT NULL DEFAULT 'shipment',    -- shipment (cdb_add_order) | package (cdb_customers_packages) | consolidation
    order_track   VARCHAR(100) NOT NULL,
    customer_name VARCHAR(190) NULL,
    description   VARCHAR(255) NULL,
    pieces        INT NOT NULL DEFAULT 1,
    weight_kg     DECIMAL(12,2) NOT NULL DEFAULT 0,
    charge        DECIMAL(12,2) NOT NULL DEFAULT 0,
    slot          INT NULL,                                   -- container loading position
    fragile       TINYINT(1) NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_waybill (waybill_id),
    KEY idx_track (order_track)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Cargo exceptions (damaged, missing, customs hold, partial, misrouted, hand-over)
CREATE TABLE IF NOT EXISTS cdb_cargo_exceptions (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    type            VARCHAR(20) NOT NULL,                     -- damaged | missing | customs_hold | partial | misrouted | delayed | handover
    entity_type     VARCHAR(20) NOT NULL DEFAULT 'shipment',  -- shipment | package | consolidation | schedule | waybill
    entity_ref      VARCHAR(100) NOT NULL,                    -- tracking / c_no / voyage
    title           VARCHAR(190) NOT NULL,
    severity        VARCHAR(10) NOT NULL DEFAULT 'medium',    -- low | medium | high | urgent
    status          VARCHAR(14) NOT NULL DEFAULT 'open',      -- open | in_progress | resolved | closed
    owner_id        INT NULL,
    customer_id     INT NULL,
    pieces_expected INT NULL,
    pieces_arrived  INT NULL,
    handed_from     VARCHAR(150) NULL,
    handed_to       VARCHAR(150) NULL,
    notify_customer TINYINT(1) NOT NULL DEFAULT 1,
    notes           TEXT NULL,
    photos          TEXT NULL,                                -- JSON list of upload paths
    claim_id        INT UNSIGNED NULL,
    created_by      INT NULL,
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME NULL,
    resolved_at     DATETIME NULL,
    resolved_by     INT NULL,
    PRIMARY KEY (id),
    KEY idx_status (status, severity),
    KEY idx_entity (entity_type, entity_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insurance claims ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cdb_insurance_claims (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    claim_no       VARCHAR(20) NOT NULL,
    order_id       INT NULL,
    order_track    VARCHAR(100) NOT NULL,
    customer_id    INT NULL,
    customer_name  VARCHAR(190) NULL,
    type           VARCHAR(10) NOT NULL DEFAULT 'damaged',    -- lost | damaged
    declared_value DECIMAL(12,2) NOT NULL DEFAULT 0,
    insured_value  DECIMAL(12,2) NOT NULL DEFAULT 0,
    claim_amount   DECIMAL(12,2) NOT NULL DEFAULT 0,
    offer_amount   DECIMAL(12,2) NULL,
    status         VARCHAR(12) NOT NULL DEFAULT 'reported',   -- reported | reviewing | approved | paid | rejected
    exception_id   INT UNSIGNED NULL,
    documents      TEXT NULL,                                 -- JSON list of upload paths
    timeline       TEXT NULL,                                 -- JSON list of {at, by, text}
    notes          TEXT NULL,
    created_by     INT NULL,
    created_at     DATETIME NOT NULL,
    updated_at     DATETIME NULL,
    paid_at        DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_claim (claim_no),
    KEY idx_status (status),
    KEY idx_track (order_track)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Last mile: delivery zones matched by recipient city -------------------------
CREATE TABLE IF NOT EXISTS cdb_delivery_zones (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    city_match  VARCHAR(500) NOT NULL,                        -- comma separated city names / keywords
    fee         DECIMAL(12,2) NOT NULL DEFAULT 0,
    rider_id    INT NULL,
    active      TINYINT(1) NOT NULL DEFAULT 1,
    created_by  INT NULL,
    created_at  DATETIME NOT NULL,
    updated_at  DATETIME NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Developer console -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS cdb_cron_runs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job         VARCHAR(80) NOT NULL,
    started_at  DATETIME NOT NULL,
    finished_at DATETIME NULL,
    status      VARCHAR(10) NOT NULL DEFAULT 'running',       -- running | ok | failed
    duration_ms INT NULL,
    message     VARCHAR(500) NULL,
    host        VARCHAR(100) NULL,
    PRIMARY KEY (id),
    KEY idx_job (job, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_dev_changelog (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    version     VARCHAR(40) NOT NULL,
    kind        VARCHAR(12) NOT NULL DEFAULT 'change',        -- feature | fix | change | security | migration
    title       VARCHAR(190) NOT NULL,
    body        TEXT NULL,
    ref         VARCHAR(100) NULL,                            -- commit, PR or ticket
    author      VARCHAR(150) NULL,
    environment VARCHAR(20) NOT NULL DEFAULT 'production',
    released_at DATETIME NOT NULL,
    created_by  INT NULL,
    created_at  DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_released (released_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_dev_alert_rules (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_key    VARCHAR(40) NOT NULL,
    label       VARCHAR(150) NOT NULL,
    metric      VARCHAR(30) NOT NULL,                         -- disk_pct | failed_cron | failed_logins | queue_backlog | php_errors | otp_failures | cron_stale_min
    comparator  VARCHAR(2) NOT NULL DEFAULT '>',
    threshold   DECIMAL(12,2) NOT NULL DEFAULT 0,
    window_min  INT NOT NULL DEFAULT 60,
    severity    VARCHAR(10) NOT NULL DEFAULT 'warning',       -- warning | critical
    notify      VARCHAR(20) NOT NULL DEFAULT 'email',         -- email | none
    enabled     TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL,
    updated_at  DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rule (rule_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Alerts: same table the live DevOps dashboard (helpers/devops.php) creates on
-- first use, so both the collector and the rule engine write one list.
CREATE TABLE IF NOT EXISTS cdb_dev_alerts (
    id INT NOT NULL AUTO_INCREMENT, alert_key VARCHAR(80) NOT NULL, severity VARCHAR(12) NOT NULL, service VARCHAR(60) NOT NULL,
    title VARCHAR(160) NOT NULL, detail VARCHAR(500) NULL, context TEXT NULL, status VARCHAR(12) NOT NULL DEFAULT 'active',
    first_seen DATETIME NOT NULL, last_seen DATETIME NOT NULL, resolved_at DATETIME NULL, ack_by INT NULL, ack_at DATETIME NULL,
    PRIMARY KEY (id), KEY idx_key (alert_key, status), KEY idx_status (status, last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_dev_issues (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ref          VARCHAR(16) NOT NULL,
    title        VARCHAR(190) NOT NULL,
    type         VARCHAR(10) NOT NULL DEFAULT 'bug',          -- bug | error | request | task
    priority     VARCHAR(4) NOT NULL DEFAULT 'p2',            -- p1 | p2 | p3
    area         VARCHAR(60) NULL,
    page         VARCHAR(120) NULL,
    status       VARCHAR(12) NOT NULL DEFAULT 'open',         -- open | in_progress | review | done
    assignee_id  INT NULL,
    reporter_id  INT NULL,
    steps        TEXT NULL,
    log_excerpt  TEXT NULL,
    fix_version  VARCHAR(40) NULL,
    created_at   DATETIME NOT NULL,
    updated_at   DATETIME NULL,
    closed_at    DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ref (ref),
    KEY idx_status (status, priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_dev_issue_events (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    issue_id   INT UNSIGNED NOT NULL,
    user_id    INT NULL,
    user_name  VARCHAR(150) NULL,
    kind       VARCHAR(10) NOT NULL DEFAULT 'comment',        -- comment | status | assign | create
    body       TEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_issue (issue_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Security Centre: flag that makes a user choose a new password ---------------
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_users' AND COLUMN_NAME = 'force_password_reset');
SET @sql := IF(@has_col = 0, 'ALTER TABLE cdb_users ADD COLUMN force_password_reset TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Default alert rules (edit on the Alerts page) --------------------------------
INSERT INTO cdb_dev_alert_rules (rule_key, label, metric, comparator, threshold, window_min, severity, notify, enabled, created_at)
SELECT 'disk', 'Disk usage above 85%', 'disk_pct', '>', 85, 0, 'critical', 'email', 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_dev_alert_rules WHERE rule_key = 'disk');
INSERT INTO cdb_dev_alert_rules (rule_key, label, metric, comparator, threshold, window_min, severity, notify, enabled, created_at)
SELECT 'failed_cron', 'A cron job failed in the last 24 h', 'failed_cron', '>', 0, 1440, 'warning', 'email', 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_dev_alert_rules WHERE rule_key = 'failed_cron');
INSERT INTO cdb_dev_alert_rules (rule_key, label, metric, comparator, threshold, window_min, severity, notify, enabled, created_at)
SELECT 'failed_logins', 'Failed sign-ins above 20 in 10 min', 'failed_logins', '>', 20, 10, 'critical', 'email', 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_dev_alert_rules WHERE rule_key = 'failed_logins');
INSERT INTO cdb_dev_alert_rules (rule_key, label, metric, comparator, threshold, window_min, severity, notify, enabled, created_at)
SELECT 'queue_backlog', 'Notification outbox backlog above 200', 'queue_backlog', '>', 200, 0, 'warning', 'email', 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_dev_alert_rules WHERE rule_key = 'queue_backlog');
INSERT INTO cdb_dev_alert_rules (rule_key, label, metric, comparator, threshold, window_min, severity, notify, enabled, created_at)
SELECT 'php_errors', 'PHP errors above 50 in 60 min', 'php_errors', '>', 50, 60, 'warning', 'email', 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_dev_alert_rules WHERE rule_key = 'php_errors');
INSERT INTO cdb_dev_alert_rules (rule_key, label, metric, comparator, threshold, window_min, severity, notify, enabled, created_at)
SELECT 'cron_stale', 'No cron run recorded for 180 min', 'cron_stale_min', '>', 180, 0, 'warning', 'email', 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_dev_alert_rules WHERE rule_key = 'cron_stale');

-- Permissions ------------------------------------------------------------------
INSERT INTO cdb_user_module_permissions (module_name, description) SELECT 'FREIGHT_DEVOPS', 'Freight operations, insurance and the developer console' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_user_module_permissions WHERE module_name = 'FREIGHT_DEVOPS');
SET @mod := (SELECT id FROM cdb_user_module_permissions WHERE module_name = 'FREIGHT_DEVOPS' LIMIT 1);
INSERT INTO cdb_user_module_actions (module_id, action_name, description_module)
SELECT @mod, a.name, a.descr FROM (
    SELECT 'view_freight' AS name, 'See freight schedules, waybills, exceptions, claims and partners' AS descr UNION ALL
    SELECT 'manage_freight', 'Create and edit schedules, waybills, exceptions and partners' UNION ALL
    SELECT 'manage_insurance', 'Create, review and pay insurance claims' UNION ALL
    SELECT 'view_developer', 'Open the developer console (services, logs, metrics, alerts, issues)'
) a WHERE NOT EXISTS (SELECT 1 FROM cdb_user_module_actions x WHERE x.action_name = a.name);
INSERT INTO cdb_user_role_permissions (role_id, module_action_id, permitted, created_at)
SELECT r.role_id, ma.id, 1, NOW() FROM cdb_user_module_actions ma JOIN cdb_user_roles r ON r.role_id IN (9, 2)
WHERE ma.action_name IN ('view_freight', 'manage_freight', 'manage_insurance', 'view_developer')
  AND NOT EXISTS (SELECT 1 FROM cdb_user_role_permissions p WHERE p.role_id = r.role_id AND p.module_action_id = ma.id);

INSERT INTO cdb_schema_migrations (name, applied_at) SELECT '2026_11_06_freight_devops', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_schema_migrations WHERE name = '2026_11_06_freight_devops');
SELECT 'ok: 2026_11_06_freight_devops' AS result;


-- >>> migrations/2026_11_06_messaging_settings.sql
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


-- >>> migrations/2026_11_06_people_staff.sql
-- =============================================================================
-- 2026-11-06  Swift Lane UI: people, staff, riders and audit logs
-- Run:  mysql -u USER -p DBNAME < migrations/2026_11_06_people_staff.sql
-- Every step is safe to run again (CREATE TABLE IF NOT EXISTS, guards on
-- information_schema, seeds with WHERE NOT EXISTS). Nothing is dropped.
-- =============================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_schema_migrations (
    name        VARCHAR(190) NOT NULL,
    applied_at  DATETIME NOT NULL,
    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Departments (permission layer between role and user) -----------------------
-- Columns inferred from ajax/tools/permissions/departments_*_ajax.php,
-- lib/User.php (cdp_getUserPermissions) and ajax/reports/system_logs_ajax.php.
CREATE TABLE IF NOT EXISTS cdb_departments (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(100) NOT NULL,
    description   VARCHAR(255) NULL,
    base_role_id  INT NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dept_name (name),
    KEY idx_dept_role (base_role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_department_members (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    department_id  INT UNSIGNED NOT NULL,
    user_id        INT NOT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dept_user (department_id, user_id),
    KEY idx_dm_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_department_permissions (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    department_id     INT UNSIGNED NOT NULL,
    module_action_id  INT NOT NULL,
    permitted         TINYINT(1) NOT NULL DEFAULT 1,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dept_action (department_id, module_action_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Per-user permission overrides (ajax/users/users_overrides_save_ajax.php) ----
CREATE TABLE IF NOT EXISTS cdb_user_permission_overrides (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id           INT NOT NULL,
    module_action_id  INT NOT NULL,
    permitted         TINYINT(1) NOT NULL DEFAULT 1,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_action (user_id, module_action_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Staff presence beacon and productivity settings (helpers/staff_activity.php)
CREATE TABLE IF NOT EXISTS cdb_staff_presence (
    user_id    INT NOT NULL,
    minute_at  DATETIME NOT NULL,
    page       VARCHAR(120) NULL,
    PRIMARY KEY (user_id, minute_at),
    KEY idx_presence_minute (minute_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_staff_productivity_settings (
    setting_key    VARCHAR(40) NOT NULL,
    setting_value  VARCHAR(190) NOT NULL DEFAULT '',
    updated_at     DATETIME NULL,
    updated_by     INT NULL,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Rider fee rules (RiderSettings) and settlements (RiderDetail) --------------
CREATE TABLE IF NOT EXISTS cdb_rider_fee_rules (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_key     VARCHAR(40) NOT NULL,
    label        VARCHAR(120) NOT NULL,
    rule_type    VARCHAR(20) NOT NULL DEFAULT 'per_delivery',  -- per_delivery | per_pickup | per_km | bonus | penalty
    amount       DECIMAL(12,2) NOT NULL DEFAULT 0,
    rider_pct    DECIMAL(5,2) NOT NULL DEFAULT 70,              -- rider share of the fee
    condition_json TEXT NULL,
    active       TINYINT(1) NOT NULL DEFAULT 1,
    sort_order   INT NOT NULL DEFAULT 0,
    updated_by   INT NULL,
    updated_at   DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rule_key (rule_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_rider_settlements (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    driver_id     INT NOT NULL,
    period_start  DATE NOT NULL,
    period_end    DATE NOT NULL,
    deliveries    INT NOT NULL DEFAULT 0,
    pickups       INT NOT NULL DEFAULT 0,
    gross         DECIMAL(12,2) NOT NULL DEFAULT 0,
    rider_share   DECIMAL(12,2) NOT NULL DEFAULT 0,
    status        VARCHAR(12) NOT NULL DEFAULT 'pending',       -- pending | paid
    paid_at       DATETIME NULL,
    paid_by       INT NULL,
    note          VARCHAR(255) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_settle_driver (driver_id, period_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Rider profile columns on cdb_users ------------------------------------------
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_users' AND COLUMN_NAME = 'vehicle_type');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_users ADD COLUMN vehicle_type VARCHAR(20) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_users' AND COLUMN_NAME = 'zone');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_users ADD COLUMN zone VARCHAR(80) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_users' AND COLUMN_NAME = 'rider_status');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_users ADD COLUMN rider_status VARCHAR(12) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
-- Proof of delivery on orders (DriverApp) -------------------------------------
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order' AND COLUMN_NAME = 'pod_photo');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order ADD COLUMN pod_photo VARCHAR(350) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order' AND COLUMN_NAME = 'pod_receiver');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order ADD COLUMN pod_receiver VARCHAR(150) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order' AND COLUMN_NAME = 'pod_at');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order ADD COLUMN pod_at DATETIME NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order' AND COLUMN_NAME = 'assigned_at');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order ADD COLUMN assigned_at DATETIME NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Default fee rules ------------------------------------------------------------
INSERT INTO cdb_rider_fee_rules (rule_key, label, rule_type, amount, rider_pct, active, sort_order, created_at)
SELECT 'delivery_base', 'Delivery in the city', 'per_delivery', 8.00, 70, 1, 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_rider_fee_rules WHERE rule_key = 'delivery_base');
INSERT INTO cdb_rider_fee_rules (rule_key, label, rule_type, amount, rider_pct, active, sort_order, created_at)
SELECT 'pickup_base', 'Pickup from a customer', 'per_pickup', 6.00, 70, 1, 2, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_rider_fee_rules WHERE rule_key = 'pickup_base');
INSERT INTO cdb_rider_fee_rules (rule_key, label, rule_type, amount, rider_pct, active, sort_order, created_at)
SELECT 'per_km', 'Distance over 10 km (per km)', 'per_km', 0.50, 100, 1, 3, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_rider_fee_rules WHERE rule_key = 'per_km');
INSERT INTO cdb_rider_fee_rules (rule_key, label, rule_type, amount, rider_pct, active, sort_order, created_at)
SELECT 'on_time_bonus', 'On-time bonus (90%+ in a week)', 'bonus', 20.00, 100, 1, 4, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_rider_fee_rules WHERE rule_key = 'on_time_bonus');
INSERT INTO cdb_rider_fee_rules (rule_key, label, rule_type, amount, rider_pct, active, sort_order, created_at)
SELECT 'failed_attempt', 'Failed delivery attempt', 'penalty', 2.00, 100, 1, 5, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_rider_fee_rules WHERE rule_key = 'failed_attempt');

INSERT INTO cdb_rider_fee_rules (rule_key, label, rule_type, amount, rider_pct, active, sort_order, created_at)
SELECT 'veh_motorbike', 'Motorbike', 'vehicle', 0, 60, 1, 10, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_rider_fee_rules WHERE rule_key = 'veh_motorbike');
INSERT INTO cdb_rider_fee_rules (rule_key, label, rule_type, amount, rider_pct, active, sort_order, created_at)
SELECT 'veh_van', 'Van', 'vehicle', 0, 65, 1, 11, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_rider_fee_rules WHERE rule_key = 'veh_van');
INSERT INTO cdb_rider_fee_rules (rule_key, label, rule_type, amount, rider_pct, active, sort_order, created_at)
SELECT 'veh_car', 'Car', 'vehicle', 0, 65, 1, 12, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_rider_fee_rules WHERE rule_key = 'veh_car');

-- Permissions ------------------------------------------------------------------
INSERT INTO cdb_user_module_permissions (module_name, description) SELECT 'SWIFTLANE_UI', 'Swift Lane interface, logs, finance and warehouse permissions' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_user_module_permissions WHERE module_name = 'SWIFTLANE_UI');
SET @mod := (SELECT id FROM cdb_user_module_permissions WHERE module_name = 'SWIFTLANE_UI' LIMIT 1);
INSERT INTO cdb_user_module_actions (module_id, action_name, description_module)
SELECT @mod, a.name, a.descr FROM (
    SELECT 'view_staff_activity' AS name, 'View activity per staff member' AS descr UNION ALL
    SELECT 'dispatch_riders', 'Dispatch board: assign riders to pickups and deliveries' UNION ALL
    SELECT 'view_rider_detail', 'View rider profile, route and settlements' UNION ALL
    SELECT 'manage_rider_fees', 'Manage rider fee rules and settlements' UNION ALL
    SELECT 'manage_departments', 'Manage departments and per-user permission overrides' UNION ALL
    SELECT 'invite_staff', 'Send staff invitation e-mails'
) a WHERE NOT EXISTS (SELECT 1 FROM cdb_user_module_actions x WHERE x.action_name = a.name);
INSERT INTO cdb_user_role_permissions (role_id, module_action_id, permitted, created_at)
SELECT r.role_id, ma.id, 1, NOW() FROM cdb_user_module_actions ma JOIN cdb_user_roles r ON r.role_id IN (9, 2)
WHERE ma.action_name IN ('view_staff_activity', 'dispatch_riders', 'view_rider_detail', 'manage_rider_fees', 'manage_departments', 'invite_staff')
  AND NOT EXISTS (SELECT 1 FROM cdb_user_role_permissions p WHERE p.role_id = r.role_id AND p.module_action_id = ma.id);

-- Ledger --------------------------------------------------------------------
INSERT INTO cdb_schema_migrations (name, applied_at) SELECT '2026_11_06_people_staff', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_schema_migrations WHERE name = '2026_11_06_people_staff');
SELECT 'people_staff migration applied' AS result;


-- >>> migrations/2026_11_06_shipments.sql
-- =============================================================================
-- 2026-11-06  Swift Lane UI: air shipments, tracking, labels and documents
-- Run:  mysql -u USER -p DBNAME < migrations/2026_11_06_shipments.sql
-- Every step is safe to run again (guards on information_schema).
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

-- Tracking events: where a milestone came from (staff, import, rider, api) ---
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_courier_track' AND COLUMN_NAME = 'source');
SET @sql := IF(@has_col = 0, 'ALTER TABLE cdb_courier_track ADD COLUMN source VARCHAR(40) NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Status disputes raised from the tracking page --------------------------------
CREATE TABLE IF NOT EXISTS cdb_tracking_disputes (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id    INT NOT NULL,
    order_track VARCHAR(100) NOT NULL,
    reason      VARCHAR(190) NOT NULL,
    note        VARCHAR(500) NULL,
    raised_by   INT NULL,
    raised_at   DATETIME NOT NULL,
    resolved_by INT NULL,
    resolved_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_order (order_id, resolved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Label Studio options and public tracking privacy (cdb_sl_settings) --------
INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_at)
SELECT 'label_options', '{"size":"46","sender":1,"fragile":0,"phone":1,"qr":1,"contents":1,"value":1,"customs":0,"code":1,"postage":0,"care":1,"ret":0,"copies":1,"printer":"Default printer"}', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_sl_settings WHERE setting_key = 'label_options');
INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_at)
SELECT 'label_small_options', '{"use":"locker","size":"17","tag":1,"barcode":1,"code":0}', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_sl_settings WHERE setting_key = 'label_small_options');
INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_at)
SELECT 'tracking_public_privacy', '{"name":0,"address":0,"items":0,"expires":"30"}', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_sl_settings WHERE setting_key = 'tracking_public_privacy');

-- Permissions: package intake (packages module) and documents hub (reports) --
INSERT INTO cdb_user_module_actions (module_id, action_name, description_module)
SELECT 1, 'package_intake', 'Register packages by tracking number (Package Intake)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_user_module_actions WHERE action_name = 'package_intake');
INSERT INTO cdb_user_module_actions (module_id, action_name, description_module)
SELECT 5, 'view_documents', 'Documents and exports hub' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_user_module_actions WHERE action_name = 'view_documents');
INSERT INTO cdb_user_role_permissions (role_id, module_action_id, permitted, created_at)
SELECT r.role_id, a.id, 1, NOW() FROM cdb_user_roles r JOIN cdb_user_module_actions a ON a.action_name IN ('package_intake', 'view_documents')
WHERE r.role_id IN (9, 2) AND NOT EXISTS (SELECT 1 FROM cdb_user_role_permissions p WHERE p.role_id = r.role_id AND p.module_action_id = a.id);

INSERT INTO cdb_schema_migrations (name, applied_at) SELECT '2026_11_06_shipments', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_schema_migrations WHERE name = '2026_11_06_shipments');
SELECT 'ok: 2026_11_06_shipments' AS result;


-- >>> migrations/2026_11_06_support_ai_loyalty.sql
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


-- >>> migrations/2026_11_06_warehouse_packages.sql
-- =============================================================================
-- 2026-11-06  Swift Lane UI: sea packages, pre-alerts, pickups, consolidations
--             and the warehouse (SeaPackages … LockerSearch artboards)
-- Run:  mysql -u USER -p DBNAME < migrations/2026_11_06_warehouse_packages.sql
-- Every step is safe to run again (guards on information_schema).
-- =============================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_schema_migrations (
    name        VARCHAR(190) NOT NULL,
    applied_at  DATETIME NOT NULL,
    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Pickup codes (helpers/pickup_code.php creates these lazily; the new
-- Warehouse pages read them on every load, so they exist up front) ----------
CREATE TABLE IF NOT EXISTS cdb_pickup_codes (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code         VARCHAR(10)   NOT NULL,
    module       VARCHAR(8)    NOT NULL,
    owner_id     INT           NOT NULL,
    trackings    VARCHAR(2000) NOT NULL DEFAULT '',
    context      VARCHAR(40)   NOT NULL DEFAULT '',
    status       VARCHAR(12)   NOT NULL DEFAULT 'active',
    attempts     INT           NOT NULL DEFAULT 0,
    channels     VARCHAR(255)  NOT NULL DEFAULT '',
    created_by   INT           NULL,
    created_at   DATETIME      NOT NULL,
    expires_at   DATETIME      NOT NULL,
    last_sent_at DATETIME      NULL,
    verified_by  INT           NULL,
    verified_at  DATETIME      NULL,
    PRIMARY KEY (id),
    KEY idx_owner (owner_id),
    KEY idx_status (status, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS cdb_pickup_code_items (
    code_id     INT UNSIGNED NOT NULL,
    module      VARCHAR(8)   NOT NULL,
    order_id    INT          NOT NULL,
    used_at     DATETIME     NULL,
    used_by     INT          NULL,
    PRIMARY KEY (code_id, order_id),
    KEY idx_pkg (module, order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_pickup_codes' AND COLUMN_NAME = 'last_sent_at');
SET @sql := IF(@has_col = 0, 'ALTER TABLE cdb_pickup_codes ADD COLUMN last_sent_at DATETIME NULL AFTER expires_at', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Warehouse bays: where a parcel sits while it waits (Warehouse, HandOut) ----
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order' AND COLUMN_NAME = 'warehouse_bay');
SET @sql := IF(@has_col = 0, 'ALTER TABLE cdb_add_order ADD COLUMN warehouse_bay VARCHAR(12) NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_customers_packages' AND COLUMN_NAME = 'warehouse_bay');
SET @sql := IF(@has_col = 0, 'ALTER TABLE cdb_customers_packages ADD COLUMN warehouse_bay VARCHAR(12) NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Warehouse scans: the "Recent scans" feed and the receive / move-bay log ----
CREATE TABLE IF NOT EXISTS cdb_warehouse_scans (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    module      VARCHAR(8)   NOT NULL DEFAULT 'air',
    order_id    INT          NOT NULL,
    tracking    VARCHAR(120) NOT NULL DEFAULT '',
    action      VARCHAR(20)  NOT NULL DEFAULT 'receive',
    bay         VARCHAR(12)  NULL,
    note        VARCHAR(255) NULL,
    user_id     INT          NULL,
    created_at  DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_created (created_at),
    KEY idx_pkg (module, order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Settings the warehouse pages read (bay capacity) ---------------------------
CREATE TABLE IF NOT EXISTS cdb_sl_settings (
    setting_key   VARCHAR(64) NOT NULL,
    setting_value TEXT NULL,
    updated_by    INT NULL,
    updated_at    DATETIME NULL,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_at) SELECT 'warehouse_bays', '50', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_sl_settings WHERE setting_key = 'warehouse_bays');
INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_at) SELECT 'warehouse_bay_prefix', 'B-', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_sl_settings WHERE setting_key = 'warehouse_bay_prefix');

-- Permissions the new pages check -------------------------------------------
INSERT INTO cdb_user_module_permissions (module_name, description) SELECT 'SWIFTLANE_UI', 'Swift Lane interface, logs, finance and warehouse permissions' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_user_module_permissions WHERE module_name = 'SWIFTLANE_UI');
SET @mod := (SELECT id FROM cdb_user_module_permissions WHERE module_name = 'SWIFTLANE_UI' LIMIT 1);
INSERT INTO cdb_user_module_actions (module_id, action_name, description_module)
SELECT @mod, a.name, a.descr FROM (
    SELECT 'view_warehouse_pickups' AS name, 'Warehouse: handed-out history' AS descr UNION ALL
    SELECT 'warehouse_receive', 'Warehouse: receive parcels and move bays' UNION ALL
    SELECT 'warehouse_view', 'View the warehouse' UNION ALL
    SELECT 'view_warehouse_delivery', 'Warehouse delivery and hand out' UNION ALL
    SELECT 'warehouse_deliver_package', 'Hand out a package' UNION ALL
    SELECT 'warehouse_deliver_user', 'Hand out all packages of a customer' UNION ALL
    SELECT 'warehouse_undo_delivery', 'Undo a warehouse delivery' UNION ALL
    SELECT 'view_pickup_aging', 'View pickup aging'
) a WHERE NOT EXISTS (SELECT 1 FROM cdb_user_module_actions x WHERE x.action_name = a.name);
INSERT INTO cdb_user_role_permissions (role_id, module_action_id, permitted, created_at)
SELECT r.role_id, ma.id, 1, NOW() FROM cdb_user_module_actions ma JOIN cdb_user_roles r ON r.role_id IN (9, 2)
WHERE ma.action_name IN ('view_warehouse_pickups', 'warehouse_receive', 'warehouse_view', 'view_warehouse_delivery', 'warehouse_deliver_package', 'warehouse_deliver_user', 'warehouse_undo_delivery', 'view_pickup_aging')
  AND NOT EXISTS (SELECT 1 FROM cdb_user_role_permissions p WHERE p.role_id = r.role_id AND p.module_action_id = ma.id);

INSERT INTO cdb_schema_migrations (name, applied_at) SELECT '2026_11_06_warehouse_packages', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_schema_migrations WHERE name = '2026_11_06_warehouse_packages');
SELECT 'ok: 2026_11_06_warehouse_packages' AS result;


-- >>> migrations/2026_11_08_support_rule_templates.sql
-- Support > Automate: 20 ready-made rules for Swift Lane, installed as DRAFTS.
-- Nothing runs until an admin reviews and publishes each rule. Safe to re-run.

INSERT INTO cdb_cs_rules (name, kind, trigger_event, match_mode, conditions, actions, status, sort_order, stop_processing, max_per_ticket, version, run_count, fail_count, template_key, created_at, updated_at)
SELECT 'SLA nearly missed: tell the leads', 'automation', 'sla.warning', 'all', '[]', '[{"type":"notify_leads","value":"SLA deadline is near"},{"type":"add_tag","value":"sla-risk"}]', 'draft', 100, 0, 1, 1, 0, 0, 'sla_escalate', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_cs_rules WHERE template_key = 'sla_escalate');
INSERT INTO cdb_cs_rules (name, kind, trigger_event, match_mode, conditions, actions, status, sort_order, stop_processing, max_per_ticket, version, run_count, fail_count, template_key, created_at, updated_at)
SELECT 'Billing questions go to Billing', 'automation', 'ticket.created', 'any', '[{"field":"category","op":"is","value":"billing"},{"field":"message","op":"contains","value":"invoice"},{"field":"message","op":"contains","value":"payment"}]', '[{"type":"set_category","value":"billing"},{"type":"add_tag","value":"billing"}]', 'draft', 100, 0, 1, 1, 0, 0, 'billing_route', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_cs_rules WHERE template_key = 'billing_route');
INSERT INTO cdb_cs_rules (name, kind, trigger_event, match_mode, conditions, actions, status, sort_order, stop_processing, max_per_ticket, version, run_count, fail_count, template_key, created_at, updated_at)
SELECT 'Missing warehouse reference', 'automation', 'ticket.created', 'any', '[{"field":"category","op":"is","value":"warehouse"},{"field":"message","op":"contains","value":"delivered but"}]', '[{"type":"set_category","value":"warehouse"},{"type":"add_tag","value":"warehouse-check"},{"type":"create_task","value":"Check warehouse receipt against carrier tracking | 24"}]', 'draft', 100, 0, 1, 1, 0, 0, 'warehouse_missing_ref', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_cs_rules WHERE template_key = 'warehouse_missing_ref');
INSERT INTO cdb_cs_rules (name, kind, trigger_event, match_mode, conditions, actions, status, sort_order, stop_processing, max_per_ticket, version, run_count, fail_count, template_key, created_at, updated_at)
SELECT 'Lost or damaged: high priority', 'automation', 'ticket.created', 'any', '[{"field":"category","op":"is","value":"claim"},{"field":"message","op":"contains","value":"damaged"},{"field":"message","op":"contains","value":"lost"}]', '[{"type":"set_priority","value":"high"},{"type":"add_tag","value":"claim"}]', 'draft', 100, 0, 1, 1, 0, 0, 'claims_priority', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_cs_rules WHERE template_key = 'claims_priority');
INSERT INTO cdb_cs_rules (name, kind, trigger_event, match_mode, conditions, actions, status, sort_order, stop_processing, max_per_ticket, version, run_count, fail_count, template_key, created_at, updated_at)
SELECT 'Human requested: assign and summarise once', 'automation', 'handover.requested', 'all', '[{"field":"assignee","op":"is","value":"none"}]', '[{"type":"auto_assign","value":"least_loaded"},{"type":"ai_summarize","value":""}]', 'draft', 100, 0, 1, 1, 0, 0, 'handover_route', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_cs_rules WHERE template_key = 'handover_route');
INSERT INTO cdb_cs_rules (name, kind, trigger_event, match_mode, conditions, actions, status, sort_order, stop_processing, max_per_ticket, version, run_count, fail_count, template_key, created_at, updated_at)
SELECT 'Detect language and suggest tags', 'automation', 'ticket.created', 'all', '[]', '[{"type":"ai_classify","value":""}]', 'draft', 100, 0, 1, 1, 0, 0, 'classify_new', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_cs_rules WHERE template_key = 'classify_new');
INSERT INTO cdb_cs_rules (name, kind, trigger_event, match_mode, conditions, actions, status, sort_order, stop_processing, max_per_ticket, version, run_count, fail_count, template_key, created_at, updated_at)
SELECT 'One reminder after 48h without a reply', 'automation', 'schedule.no_reply', 'all', '[{"field":"status","op":"is","value":"pending_customer"},{"field":"hours_since_customer","op":"gt","value":"48"}]', '[{"type":"reply_canned","value":""}]', 'draft', 100, 0, 1, 1, 0, 0, 'no_reply_reminder', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_cs_rules WHERE template_key = 'no_reply_reminder');
INSERT INTO cdb_cs_rules (name, kind, trigger_event, match_mode, conditions, actions, status, sort_order, stop_processing, max_per_ticket, version, run_count, fail_count, template_key, created_at, updated_at)
SELECT 'Poor rating: supervisor follow-up', 'automation', 'csat.poor', 'all', '[]', '[{"type":"notify_leads","value":"Customer rated support poorly"},{"type":"create_task","value":"Service recovery call | 24"}]', 'draft', 100, 0, 1, 1, 0, 0, 'poor_csat', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_cs_rules WHERE template_key = 'poor_csat');
INSERT INTO cdb_cs_rules (name, kind, trigger_event, match_mode, conditions, actions, status, sort_order, stop_processing, max_per_ticket, version, run_count, fail_count, template_key, created_at, updated_at)
SELECT 'Where is my parcel: shipment desk', 'automation', 'ticket.created', 'any', '[{"field":"message","op":"contains","value":"where is my"},{"field":"message","op":"contains","value":"tracking"},{"field":"message","op":"contains","value":"not moved"}]', '[{"type":"set_category","value":"shipment"},{"type":"add_tag","value":"tracking"},{"type":"auto_assign","value":"least_loaded"}]', 'draft', 100, 0, 1, 1, 0, 0, 'tracking_question', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_cs_rules WHERE template_key = 'tracking_question');
INSERT INTO cdb_cs_rules (name, kind, trigger_event, match_mode, conditions, actions, status, sort_order, stop_processing, max_per_ticket, version, run_count, fail_count, template_key, created_at, updated_at)
SELECT 'Charged twice: urgent billing check', 'automation', 'ticket.created', 'any', '[{"field":"message","op":"contains","value":"charged twice"},{"field":"message","op":"contains","value":"double charge"},{"field":"message","op":"contains","value":"refund"}]', '[{"type":"set_category","value":"billing"},{"type":"set_priority","value":"urgent"},{"type":"add_tag","value":"refund-check"},{"type":"create_task","value":"Check payment gateway and Financial Sheet for duplicate payment | 4"}]', 'draft', 100, 0, 1, 1, 0, 0, 'double_charge', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_cs_rules WHERE template_key = 'double_charge');
INSERT INTO cdb_cs_rules (name, kind, trigger_event, match_mode, conditions, actions, status, sort_order, stop_processing, max_per_ticket, version, run_count, fail_count, template_key, created_at, updated_at)
SELECT 'Pickup requests go to dispatch', 'automation', 'ticket.created', 'any', '[{"field":"message","op":"contains","value":"pickup"},{"field":"message","op":"contains","value":"pick up"},{"field":"message","op":"contains","value":"collect"}]', '[{"type":"set_category","value":"delivery"},{"type":"add_tag","value":"pickup"},{"type":"create_task","value":"Schedule the pickup and confirm a time with the customer | 8"}]', 'draft', 100, 0, 1, 1, 0, 0, 'pickup_request', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_cs_rules WHERE template_key = 'pickup_request');
INSERT INTO cdb_cs_rules (name, kind, trigger_event, match_mode, conditions, actions, status, sort_order, stop_processing, max_per_ticket, version, run_count, fail_count, template_key, created_at, updated_at)
SELECT 'Wrong delivery address: hold and confirm', 'automation', 'ticket.created', 'any', '[{"field":"message","op":"contains","value":"wrong address"},{"field":"message","op":"contains","value":"change address"},{"field":"message","op":"contains","value":"deliver to"}]', '[{"type":"set_category","value":"delivery"},{"type":"set_priority","value":"high"},{"type":"add_tag","value":"address-change"},{"type":"add_note","value":"Confirm the new address with the customer before the rider leaves."}]', 'draft', 100, 0, 1, 1, 0, 0, 'wrong_address', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_cs_rules WHERE template_key = 'wrong_address');
INSERT INTO cdb_cs_rules (name, kind, trigger_event, match_mode, conditions, actions, status, sort_order, stop_processing, max_per_ticket, version, run_count, fail_count, template_key, created_at, updated_at)
SELECT 'Customs and duty questions', 'automation', 'ticket.created', 'any', '[{"field":"message","op":"contains","value":"customs"},{"field":"message","op":"contains","value":"duty"},{"field":"message","op":"contains","value":"clearance"}]', '[{"type":"set_category","value":"shipment"},{"type":"add_tag","value":"customs"}]', 'draft', 100, 0, 1, 1, 0, 0, 'customs_duty', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_cs_rules WHERE template_key = 'customs_duty');
INSERT INTO cdb_cs_rules (name, kind, trigger_event, match_mode, conditions, actions, status, sort_order, stop_processing, max_per_ticket, version, run_count, fail_count, template_key, created_at, updated_at)
SELECT 'US / UK mailbox address help', 'automation', 'ticket.created', 'any', '[{"field":"message","op":"contains","value":"mailbox"},{"field":"message","op":"contains","value":"shipping address"},{"field":"message","op":"contains","value":"locker"}]', '[{"type":"set_category","value":"account"},{"type":"add_tag","value":"mailbox"},{"type":"reply_canned","value":""}]', 'draft', 100, 0, 1, 1, 0, 0, 'mailbox_address', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_cs_rules WHERE template_key = 'mailbox_address');
INSERT INTO cdb_cs_rules (name, kind, trigger_event, match_mode, conditions, actions, status, sort_order, stop_processing, max_per_ticket, version, run_count, fail_count, template_key, created_at, updated_at)
SELECT 'Login, OTP or password trouble', 'automation', 'ticket.created', 'any', '[{"field":"message","op":"contains","value":"password"},{"field":"message","op":"contains","value":"otp"},{"field":"message","op":"contains","value":"cannot log"}]', '[{"type":"set_category","value":"technical"},{"type":"add_tag","value":"login"}]', 'draft', 100, 0, 1, 1, 0, 0, 'login_problem', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_cs_rules WHERE template_key = 'login_problem');
INSERT INTO cdb_cs_rules (name, kind, trigger_event, match_mode, conditions, actions, status, sort_order, stop_processing, max_per_ticket, version, run_count, fail_count, template_key, created_at, updated_at)
SELECT 'Business and bulk shippers: priority', 'automation', 'ticket.created', 'any', '[{"field":"category","op":"is","value":"business"},{"field":"message","op":"contains","value":"bulk"},{"field":"message","op":"contains","value":"container"}]', '[{"type":"set_category","value":"business"},{"type":"set_priority","value":"high"},{"type":"notify_leads","value":"New business / bulk enquiry"}]', 'draft', 100, 0, 1, 1, 0, 0, 'business_account', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_cs_rules WHERE template_key = 'business_account');
INSERT INTO cdb_cs_rules (name, kind, trigger_event, match_mode, conditions, actions, status, sort_order, stop_processing, max_per_ticket, version, run_count, fail_count, template_key, created_at, updated_at)
SELECT 'Weekend message: set expectations', 'automation', 'ticket.created', 'all', '[{"field":"business_hours","op":"is","value":"no"},{"field":"channel","op":"any_of","value":"web,portal,whatsapp,email"}]', '[{"type":"reply_canned","value":""},{"type":"add_tag","value":"after-hours"}]', 'draft', 100, 0, 1, 1, 0, 0, 'after_hours', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_cs_rules WHERE template_key = 'after_hours');
INSERT INTO cdb_cs_rules (name, kind, trigger_event, match_mode, conditions, actions, status, sort_order, stop_processing, max_per_ticket, version, run_count, fail_count, template_key, created_at, updated_at)
SELECT 'Reopened twice: escalate to leads', 'automation', 'ticket.reopened', 'all', '[{"field":"reopen_count","op":"gt","value":"1"}]', '[{"type":"set_priority","value":"high"},{"type":"notify_leads","value":"Ticket reopened more than once"},{"type":"ai_summarize","value":""}]', 'draft', 100, 0, 1, 1, 0, 0, 'reopened_escalate', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_cs_rules WHERE template_key = 'reopened_escalate');
INSERT INTO cdb_cs_rules (name, kind, trigger_event, match_mode, conditions, actions, status, sort_order, stop_processing, max_per_ticket, version, run_count, fail_count, template_key, created_at, updated_at)
SELECT 'SLA missed: urgent and tagged', 'automation', 'sla.breached', 'all', '[]', '[{"type":"set_priority","value":"urgent"},{"type":"add_tag","value":"sla-breached"},{"type":"notify_leads","value":"SLA deadline missed"}]', 'draft', 100, 0, 1, 1, 0, 0, 'sla_breached', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_cs_rules WHERE template_key = 'sla_breached');
INSERT INTO cdb_cs_rules (name, kind, trigger_event, match_mode, conditions, actions, status, sort_order, stop_processing, max_per_ticket, version, run_count, fail_count, template_key, created_at, updated_at)
SELECT 'Close after 7 days without a reply', 'automation', 'schedule.no_reply', 'all', '[{"field":"status","op":"is","value":"pending_customer"},{"field":"hours_since_customer","op":"gt","value":"168"}]', '[{"type":"add_note","value":"Closed automatically: no customer reply for 7 days."},{"type":"set_status","value":"resolved"}]', 'draft', 100, 0, 1, 1, 0, 0, 'auto_close_stale', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_cs_rules WHERE template_key = 'auto_close_stale');

INSERT INTO cdb_schema_migrations (name, applied_at) SELECT '2026_11_08_support_rule_templates', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_schema_migrations WHERE name = '2026_11_08_support_rule_templates');
SELECT 'ok: 2026_11_08_support_rule_templates' AS result;

