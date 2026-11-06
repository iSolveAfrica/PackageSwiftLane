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
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_shipping_fees' AND COLUMN_NAME = 'min_charge');
SET @sql := IF(@has_col = 0, 'ALTER TABLE cdb_shipping_fees ADD COLUMN min_charge DECIMAL(12,2) NOT NULL DEFAULT 0, ADD COLUMN valid_from DATE NULL, ADD COLUMN valid_until DATE NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

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
