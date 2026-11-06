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
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order ADD COLUMN fs_cleared_for_delivery TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN fs_cleared_at DATETIME NULL, ADD COLUMN fs_cleared_by INT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order' AND COLUMN_NAME = 'tracking_purchase');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order ADD COLUMN tracking_purchase VARCHAR(300) NULL, ADD COLUMN provider_purchase VARCHAR(300) NULL, ADD COLUMN price_purchase DECIMAL(12,2) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order' AND COLUMN_NAME = 'estimated_eta');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order ADD COLUMN estimated_eta DATE NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order' AND INDEX_NAME = 'idx_order_sender_status');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order ADD KEY idx_order_sender_status (sender_id, status_courier, is_deleted), ADD KEY idx_order_date (order_date), ADD KEY idx_order_status (status_courier, is_deleted), ADD KEY idx_order_driver (driver_id)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- cdb_add_order_item
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order_item' AND COLUMN_NAME = 'custom_price');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_add_order_item ADD COLUMN custom_price DECIMAL(12,2) NULL, ADD COLUMN priced_at DATETIME NULL, ADD COLUMN order_item_weight_group VARCHAR(40) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- cdb_customers_packages
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_customers_packages' AND COLUMN_NAME = 'receiver_id');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_customers_packages ADD COLUMN receiver_id INT NULL, ADD COLUMN receiver_address_id INT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_customers_packages' AND COLUMN_NAME = 'recipient_type');
SET @sql := IF(@c = 0, "ALTER TABLE cdb_customers_packages ADD COLUMN recipient_type VARCHAR(20) NOT NULL DEFAULT 'recipient'", 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_customers_packages' AND COLUMN_NAME = 'notify_whatsapp_sender');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_customers_packages ADD COLUMN notify_whatsapp_sender TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_customers_packages' AND COLUMN_NAME = 'estimated_eta');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_customers_packages ADD COLUMN estimated_eta DATE NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_customers_packages' AND INDEX_NAME = 'idx_pkg_sender_status');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_customers_packages ADD KEY idx_pkg_sender_status (sender_id, status_courier), ADD KEY idx_pkg_date (order_date), ADD KEY idx_pkg_tracking (tracking_purchase(64))', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

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
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_courier_track ADD COLUMN flight_no VARCHAR(40) NULL, ADD COLUMN awb_no VARCHAR(40) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_courier_track' AND INDEX_NAME = 'idx_track_order');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_courier_track ADD KEY idx_track_order (order_id), ADD KEY idx_track_date (t_date), ADD KEY idx_track_status (status_courier, t_date)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- cdb_user_roles: typed roles and inheritance (helpers/rbac.php)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_user_roles' AND COLUMN_NAME = 'parent_role_id');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_user_roles ADD COLUMN parent_role_id INT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_user_roles' AND COLUMN_NAME = 'is_superadmin');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_user_roles ADD COLUMN is_superadmin TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN is_staff TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN is_driver TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN is_client TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN is_agency TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
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

INSERT INTO cdb_schema_migrations (name, applied_at) SELECT '2026_11_06_legacy_drift', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_schema_migrations WHERE name = '2026_11_06_legacy_drift');
SELECT 'ok: 2026_11_06_legacy_drift' AS result;
