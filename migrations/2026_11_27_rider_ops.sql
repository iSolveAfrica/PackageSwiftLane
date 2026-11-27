-- Rider app work-flow screens: when a rider went on or off duty, and the parcels a rider ticked off at pickup.
-- The on/off duty flag itself is cdb_users.rider_status (2026_11_06_people_staff). Additive and re-runnable.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS cdb_rider_status_log (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, online TINYINT NOT NULL,
 changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY rider_status_user (user_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- A checklist only: scanning never changes a parcel or job status.
CREATE TABLE IF NOT EXISTS cdb_rider_scans (
 user_id INT NOT NULL, module VARCHAR(8) NOT NULL, order_id INT NOT NULL, tracking VARCHAR(400) NOT NULL,
 scanned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (user_id,module,order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO cdb_schema_migrations(name,applied_at) VALUES ('2026_11_27_rider_ops',NOW());
SELECT 'ok: 2026_11_27_rider_ops' AS result;
