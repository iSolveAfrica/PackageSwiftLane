-- Smart Routing (smart_routing.php, helpers/smart_routing.php): when a rider's route was saved or sent,
-- staff notes on a stop, and each staff member's starred riders. Idempotent. The same DDL also runs
-- from sr_ensureSchema() so the page installs itself. Routes, stops and vehicles stay in the
-- route planner tables (migrations/2026_11_26_route_planner.sql).
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_sr_route_sends (
 id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 route_id INT UNSIGNED NOT NULL,
 kind VARCHAR(8) NOT NULL DEFAULT 'sent',
 route_version INT UNSIGNED NOT NULL DEFAULT 0,
 stops SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 channels_json TEXT NULL,
 actor_id INT NOT NULL,
 created_at DATETIME NOT NULL,
 KEY idx_sr_send_route (route_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_sr_stop_notes (
 stop_id INT UNSIGNED NOT NULL PRIMARY KEY,
 route_id INT UNSIGNED NOT NULL,
 request_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
 note VARCHAR(1000) NOT NULL DEFAULT '',
 updated_by INT NOT NULL,
 updated_at DATETIME NOT NULL,
 KEY idx_sr_note_route (route_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_sr_favourites (
 user_id INT NOT NULL,
 driver_id INT NOT NULL,
 created_at DATETIME NOT NULL,
 PRIMARY KEY (user_id, driver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
