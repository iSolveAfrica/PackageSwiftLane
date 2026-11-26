-- Remembered ("trusted") sign-in devices get the address and browser they were remembered from,
-- so the Security Centre and My account security can describe a device ("Chrome on Windows,
-- 102.176.4.18") instead of showing an anonymous row.
--
--   cdb_auth_trusted_devices.ip          VARCHAR(45)  NULL  address of the last sign-in from the device
--   cdb_auth_trusted_devices.user_agent  VARCHAR(255) NULL  browser of the last sign-in from the device
--
-- lib/OtpService.php writes both when a device is remembered and refreshes them each time the
-- device skips the one-time code. Older rows stay NULL; the pages then fall back to the user's
-- nearest sign-in in cdb_activity_log. The code checks the columns exist, so it works before this
-- runs. The table itself is normally created on demand by OtpService; it is created here too
-- (same definition) so a fresh install can run this file first. Additive and re-runnable.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_auth_trusted_devices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    selector VARCHAR(24) NOT NULL UNIQUE,
    verifier_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    revoked_at DATETIME NULL,
    INDEX idx_user_expires (user_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_auth_trusted_devices' AND COLUMN_NAME = 'ip');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_auth_trusted_devices ADD COLUMN ip VARCHAR(45) NULL AFTER revoked_at', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_auth_trusted_devices' AND COLUMN_NAME = 'user_agent');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_auth_trusted_devices ADD COLUMN user_agent VARCHAR(255) NULL AFTER ip', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

INSERT IGNORE INTO cdb_schema_migrations (name, applied_at) VALUES ('2026_11_26_trusted_device_details', NOW());
SELECT 'ok: 2026_11_26_trusted_device_details' AS result;
