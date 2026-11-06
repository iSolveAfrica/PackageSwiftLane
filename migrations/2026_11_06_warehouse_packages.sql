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
