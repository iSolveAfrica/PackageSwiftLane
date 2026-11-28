-- Warehouse menu: hand-out log, manager skip of the pickup code, pre-alerts marked received. Additive and re-runnable.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_warehouse_handovers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    module VARCHAR(4) NOT NULL, order_id INT NOT NULL, tracking VARCHAR(80) NOT NULL DEFAULT '',
    sender_id INT NOT NULL DEFAULT 0, status_courier INT NOT NULL DEFAULT 15, handed_by INT NOT NULL DEFAULT 0,
    code_method VARCHAR(12) NOT NULL DEFAULT 'code', skip_reason VARCHAR(255) NULL,
    paid TINYINT(1) NOT NULL DEFAULT 0, weight DECIMAL(12,2) NOT NULL DEFAULT 0, amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    agency INT NOT NULL DEFAULT 0, source VARCHAR(24) NOT NULL DEFAULT 'handout', created_at DATETIME NOT NULL,
    KEY idx_wh_created (created_at), KEY idx_wh_by (handed_by, created_at), KEY idx_wh_pkg (module, order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Backfill past warehouse hand-overs once (only while the log is empty).
SET @empty := (SELECT COUNT(*) = 0 FROM cdb_warehouse_handovers);
INSERT INTO cdb_warehouse_handovers (module, order_id, tracking, sender_id, status_courier, handed_by, code_method, paid, weight, amount, agency, source, created_at)
SELECT 'air', a.order_id, t.order_track, a.sender_id, t.status_courier, COALESCE(t.user_id, 0), 'legacy', a.status_invoice IN (1,19), COALESCE(a.total_weight, 0), COALESCE(a.total_order, 0), COALESCE(a.agency, 0), 'backfill', t.t_date
FROM cdb_courier_track t JOIN cdb_add_order a ON CONCAT(a.order_prefix, a.order_no) = t.order_track
WHERE @empty AND t.status_courier IN (8,15) AND (t.comments LIKE '%(Warehouse)%' OR t.comments LIKE '%via Warehouse Delivery%');
INSERT INTO cdb_warehouse_handovers (module, order_id, tracking, sender_id, status_courier, handed_by, code_method, paid, weight, amount, agency, source, created_at)
SELECT 'sea', p.order_id, t.order_track, p.sender_id, t.status_courier, COALESCE(t.user_id, 0), 'legacy', p.status_invoice IN (1,19), COALESCE(p.total_weight, 0), COALESCE(p.total_order, 0), COALESCE(p.agency, 0), 'backfill', t.t_date
FROM cdb_courier_track t JOIN cdb_customers_packages p ON CONCAT(p.order_prefix, p.order_no) = t.order_track
LEFT JOIN cdb_add_order a ON CONCAT(a.order_prefix, a.order_no) = t.order_track
WHERE @empty AND a.order_id IS NULL AND t.status_courier IN (8,15) AND (t.comments LIKE '%(Warehouse)%' OR t.comments LIKE '%via Warehouse Delivery%');

-- Pre-alert: when and by whom the parcel was keyed in, and the shipment / package it became.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_pre_alert' AND COLUMN_NAME = 'received_at');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_pre_alert ADD COLUMN received_at DATETIME NULL, ADD COLUMN received_by INT NULL, ADD COLUMN received_module VARCHAR(4) NULL, ADD COLUMN received_order_id INT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Permissions -----------------------------------------------------------------
INSERT INTO cdb_user_module_permissions (module_name, description) SELECT 'SWIFTLANE_UI', 'Swift Lane interface, logs, finance and warehouse permissions' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_user_module_permissions WHERE module_name = 'SWIFTLANE_UI');
SET @mod := (SELECT id FROM cdb_user_module_permissions WHERE module_name = 'SWIFTLANE_UI' LIMIT 1);
INSERT INTO cdb_user_module_actions (module_id, action_name, description_module)
SELECT @mod, a.name, a.descr FROM (
    SELECT 'warehouse_skip_pickup_code' AS name, 'Warehouse: hand over without the pickup code (managers)' AS descr UNION ALL
    SELECT 'view_handover_log', 'Warehouse: hand-out log and staff statistics'
) a WHERE NOT EXISTS (SELECT 1 FROM cdb_user_module_actions x WHERE x.action_name = a.name);
INSERT INTO cdb_user_role_permissions (role_id, module_action_id, permitted, created_at)
SELECT r.role_id, ma.id, 1, NOW() FROM cdb_user_module_actions ma JOIN cdb_user_roles r ON r.role_id IN (9, 2)
WHERE ma.action_name IN ('warehouse_skip_pickup_code', 'view_handover_log')
  AND NOT EXISTS (SELECT 1 FROM cdb_user_role_permissions p WHERE p.role_id = r.role_id AND p.module_action_id = ma.id);

INSERT INTO cdb_schema_migrations (name, applied_at) SELECT '2026_11_21_warehouse_handover_log', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_schema_migrations WHERE name = '2026_11_21_warehouse_handover_log');
SELECT 'ok: 2026_11_21_warehouse_handover_log' AS result;
