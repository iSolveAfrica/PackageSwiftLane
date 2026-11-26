-- Pickup code skip at handover (helpers/pickup_code.php cdp_pickupCodeSkip, ajax/sl/warehouse_ajax.php
-- code_skip / skip_customer). Granted to Super admin, Administrator and to roles named like warehouse administrator,
-- district administrator or finance; change it per role in Settings › Permissions. Idempotent.
SET NAMES utf8mb4;
INSERT INTO cdb_user_module_permissions (module_name, description) SELECT 'SWIFTLANE_UI', 'Swift Lane interface, logs, finance and warehouse permissions' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_user_module_permissions WHERE module_name = 'SWIFTLANE_UI');
SET @mod := (SELECT id FROM cdb_user_module_permissions WHERE module_name = 'SWIFTLANE_UI' LIMIT 1);
INSERT INTO cdb_user_module_actions (module_id, action_name, description_module)
SELECT @mod, 'pickup_code_skip', 'Hand out: skip the pickup code (warehouse / district administrators, finance)' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM cdb_user_module_actions x WHERE x.action_name = 'pickup_code_skip');
INSERT INTO cdb_user_role_permissions (role_id, module_action_id, permitted, created_at)
SELECT r.role_id, ma.id, 1, NOW() FROM cdb_user_module_actions ma JOIN cdb_user_roles r
  ON (r.role_id IN (9, 2) OR LOWER(r.role_name) LIKE '%warehouse%admin%' OR LOWER(r.role_name) LIKE '%district%admin%' OR LOWER(r.role_name) LIKE '%financ%')
WHERE ma.action_name = 'pickup_code_skip'
  AND NOT EXISTS (SELECT 1 FROM cdb_user_role_permissions p WHERE p.role_id = r.role_id AND p.module_action_id = ma.id);
INSERT IGNORE INTO cdb_schema_migrations (name, applied_at) VALUES ('2026_11_26_pickup_code_skip', NOW());
SELECT 'ok: 2026_11_26_pickup_code_skip' AS result;
