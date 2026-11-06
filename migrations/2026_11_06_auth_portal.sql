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
