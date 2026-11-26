-- Pre-alerts: edit, convert / link to a registered package, auto-close after 30 days overdue.
-- Automations: suggested rules (review_status suggested -> approved -> published), scheduled checks
-- (helpers/automations.php cdp_automationsScheduled, cron/automations.php) with editable params.
-- cdb_pre_alert.is_package: 0 open, 1 arrived / linked to a package, 2 closed (not arrived).
-- Additive and re-runnable.
SET NAMES utf8mb4;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_pre_alert' AND COLUMN_NAME = 'package_ref');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_pre_alert ADD COLUMN package_ref VARCHAR(60) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_pre_alert' AND COLUMN_NAME = 'package_module');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_pre_alert ADD COLUMN package_module VARCHAR(10) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_pre_alert' AND COLUMN_NAME = 'package_id');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_pre_alert ADD COLUMN package_id INT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_pre_alert' AND COLUMN_NAME = 'linked_at');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_pre_alert ADD COLUMN linked_at DATETIME NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_pre_alert' AND COLUMN_NAME = 'closed_at');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_pre_alert ADD COLUMN closed_at DATETIME NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_pre_alert' AND COLUMN_NAME = 'closed_reason');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_pre_alert ADD COLUMN closed_reason VARCHAR(200) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_pre_alert' AND COLUMN_NAME = 'updated_at');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_pre_alert ADD COLUMN updated_at DATETIME NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_pre_alert' AND COLUMN_NAME = 'updated_by');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_pre_alert ADD COLUMN updated_by INT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_automations' AND COLUMN_NAME = 'description');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_automations ADD COLUMN description VARCHAR(500) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_automations' AND COLUMN_NAME = 'origin');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_automations ADD COLUMN origin VARCHAR(20) NOT NULL DEFAULT ''custom''', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_automations' AND COLUMN_NAME = 'review_status');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_automations ADD COLUMN review_status VARCHAR(20) NOT NULL DEFAULT ''published''', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_automations' AND COLUMN_NAME = 'suggest_key');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_automations ADD COLUMN suggest_key VARCHAR(60) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_automations' AND COLUMN_NAME = 'params');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_automations ADD COLUMN params TEXT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_automations' AND INDEX_NAME = 'uq_automation_suggest');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_automations ADD UNIQUE KEY uq_automation_suggest (suggest_key)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_pre_alert' AND INDEX_NAME = 'idx_prealert_tracking');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_pre_alert ADD KEY idx_prealert_tracking (tracking(40), is_package)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_automation_runs' AND INDEX_NAME = 'idx_auto_runs_entity');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_automation_runs ADD KEY idx_auto_runs_entity (automation_id, entity_type, entity_id)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Permission: edit any pre-alert (customers can always edit their own open ones).
INSERT INTO cdb_user_module_permissions (module_name, description) SELECT 'SWIFTLANE_UI', 'Swift Lane interface, logs, finance and warehouse permissions' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_user_module_permissions WHERE module_name = 'SWIFTLANE_UI');
SET @mod := (SELECT id FROM cdb_user_module_permissions WHERE module_name = 'SWIFTLANE_UI' LIMIT 1);
INSERT INTO cdb_user_module_actions (module_id, action_name, description_module)
SELECT @mod, 'prealert_edit', 'Pre-alerts: edit, close, reopen and link any pre-alert to a package' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_user_module_actions x WHERE x.action_name = 'prealert_edit');
INSERT INTO cdb_user_role_permissions (role_id, module_action_id, permitted, created_at)
SELECT r.role_id, ma.id, 1, NOW() FROM cdb_user_module_actions ma JOIN cdb_user_roles r ON r.role_id IN (9, 2, 4)
WHERE ma.action_name = 'prealert_edit'
  AND NOT EXISTS (SELECT 1 FROM cdb_user_role_permissions p WHERE p.role_id = r.role_id AND p.module_action_id = ma.id);

INSERT INTO cdb_schema_migrations (name, applied_at) SELECT '2026_11_27_prealerts_automations', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_schema_migrations WHERE name = '2026_11_27_prealerts_automations');
SELECT 'ok: 2026_11_27_prealerts_automations' AS result;
