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
