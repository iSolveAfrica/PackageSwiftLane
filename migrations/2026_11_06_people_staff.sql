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
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_users' AND COLUMN_NAME = 'vehicle_type');
SET @sql := IF(@has_col = 0, 'ALTER TABLE cdb_users ADD COLUMN vehicle_type VARCHAR(20) NULL, ADD COLUMN zone VARCHAR(80) NULL, ADD COLUMN rider_status VARCHAR(12) NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
-- Proof of delivery on orders (DriverApp) -------------------------------------
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order' AND COLUMN_NAME = 'pod_photo');
SET @sql := IF(@has_col = 0, 'ALTER TABLE cdb_add_order ADD COLUMN pod_photo VARCHAR(350) NULL, ADD COLUMN pod_receiver VARCHAR(150) NULL, ADD COLUMN pod_at DATETIME NULL, ADD COLUMN assigned_at DATETIME NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

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
