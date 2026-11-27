-- Route planner (helpers/route_planner.php, route_planner.php, driver_app.php): vehicle capacity,
-- parcel details, allocation plans, optimised routes with stops and on-board inventory, rider
-- position pings and activity (time on the way / loading / waiting). Idempotent. The same DDL
-- also runs from rp_ensureSchema() so the pages self-install.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_rp_settings (
 id INT NOT NULL PRIMARY KEY,
 settings_json LONGTEXT NOT NULL,
 revision INT NOT NULL DEFAULT 1,
 updated_by INT NULL,
 updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_rp_vehicles (
 driver_id INT NOT NULL PRIMARY KEY,
 vehicle_type VARCHAR(12) NOT NULL DEFAULT 'motorbike',
 plate VARCHAR(20) NOT NULL DEFAULT '',
 make_model VARCHAR(80) NOT NULL DEFAULT '',
 max_kg DECIMAL(8,2) NOT NULL DEFAULT 0,
 max_l DECIMAL(9,2) NOT NULL DEFAULT 0,
 max_parcels SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 cargo_l_cm SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 cargo_w_cm SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 cargo_h_cm SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 can_fragile TINYINT(1) NOT NULL DEFAULT 0,
 can_cold TINYINT(1) NOT NULL DEFAULT 0,
 can_high_value TINYINT(1) NOT NULL DEFAULT 0,
 docs_json TEXT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 updated_by INT NULL,
 updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_rp_parcel_meta (
 module VARCHAR(8) NOT NULL,
 order_id INT NOT NULL,
 length_cm DECIMAL(7,1) NULL,
 width_cm DECIMAL(7,1) NULL,
 height_cm DECIMAL(7,1) NULL,
 weight_kg DECIMAL(8,2) NULL,
 volume_l DECIMAL(9,2) NULL,
 contents VARCHAR(250) NULL,
 handling SET('fragile','cold','high_value','oversize','liquid') NOT NULL DEFAULT '',
 value_cents BIGINT UNSIGNED NULL,
 currency CHAR(3) NULL,
 customer_note VARCHAR(500) NULL,
 window_start TIME NULL,
 window_end TIME NULL,
 priority TINYINT NOT NULL DEFAULT 0,
 updated_by INT NULL,
 updated_at DATETIME NOT NULL,
 PRIMARY KEY (module, order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_rp_plans (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 plan_date DATE NOT NULL,
 agency_id INT NOT NULL DEFAULT 0,
 status VARCHAR(12) NOT NULL DEFAULT 'draft',
 start_json TEXT NOT NULL,
 params_json TEXT NOT NULL,
 proposal_json LONGTEXT NOT NULL,
 created_by INT NOT NULL,
 confirmed_by INT NULL,
 created_at DATETIME NOT NULL,
 confirmed_at DATETIME NULL,
 KEY idx_rp_plan_date (plan_date, agency_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_rp_routes (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 plan_id INT UNSIGNED NOT NULL DEFAULT 0,
 driver_id INT NOT NULL,
 route_date DATE NOT NULL,
 locked TINYINT(1) NOT NULL DEFAULT 0,
 status VARCHAR(12) NOT NULL DEFAULT 'planned',
 start_json TEXT NOT NULL,
 provider VARCHAR(12) NOT NULL DEFAULT 'estimate',
 total_m INT UNSIGNED NOT NULL DEFAULT 0,
 total_s INT UNSIGNED NOT NULL DEFAULT 0,
 load_kg DECIMAL(9,2) NOT NULL DEFAULT 0,
 load_l DECIMAL(10,2) NOT NULL DEFAULT 0,
 load_n SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 version INT UNSIGNED NOT NULL DEFAULT 1,
 polyline MEDIUMTEXT NULL,
 started_at DATETIME NULL,
 finished_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 UNIQUE KEY uq_rp_route (driver_id, route_date, plan_id),
 KEY idx_rp_route_date (route_date, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_rp_stops (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 route_id INT UNSIGNED NOT NULL,
 request_id BIGINT UNSIGNED NOT NULL,
 seq SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 lat DECIMAL(10,7) NOT NULL,
 lng DECIMAL(10,7) NOT NULL,
 address VARCHAR(700) NOT NULL DEFAULT '',
 contact_name VARCHAR(150) NOT NULL DEFAULT '',
 contact_phone VARCHAR(40) NOT NULL DEFAULT '',
 window_start TIME NULL,
 window_end TIME NULL,
 priority TINYINT NOT NULL DEFAULT 0,
 leg_m INT UNSIGNED NOT NULL DEFAULT 0,
 leg_s INT UNSIGNED NOT NULL DEFAULT 0,
 eta DATETIME NULL,
 status VARCHAR(12) NOT NULL DEFAULT 'pending',
 arrived_at DATETIME NULL,
 done_at DATETIME NULL,
 fail_reason VARCHAR(300) NULL,
 KEY idx_rp_stop_route (route_id, seq),
 KEY idx_rp_stop_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_rp_load (
 route_id INT UNSIGNED NOT NULL,
 module VARCHAR(8) NOT NULL,
 order_id INT NOT NULL,
 request_id BIGINT UNSIGNED NOT NULL,
 stop_id INT UNSIGNED NOT NULL,
 tracking VARCHAR(400) NOT NULL DEFAULT '',
 load_state VARCHAR(8) NOT NULL DEFAULT 'pending',
 loaded_at DATETIME NULL,
 loaded_by INT NULL,
 outcome VARCHAR(10) NOT NULL DEFAULT 'onboard',
 outcome_at DATETIME NULL,
 PRIMARY KEY (route_id, module, order_id),
 KEY idx_rp_load_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_rp_pings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 driver_id INT NOT NULL,
 route_id INT UNSIGNED NULL,
 lat DECIMAL(10,7) NOT NULL,
 lng DECIMAL(10,7) NOT NULL,
 accuracy_m SMALLINT UNSIGNED NULL,
 speed_mps DECIMAL(6,2) NULL,
 heading SMALLINT NULL,
 battery TINYINT UNSIGNED NULL,
 recorded_at DATETIME NOT NULL,
 received_at DATETIME NOT NULL,
 KEY idx_rp_ping_driver (driver_id, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_rp_activity (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 driver_id INT NOT NULL,
 route_id INT UNSIGNED NULL,
 state VARCHAR(10) NOT NULL,
 started_at DATETIME NOT NULL,
 ended_at DATETIME NULL,
 KEY idx_rp_activity_driver (driver_id, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_rp_geocache (
 hash CHAR(40) NOT NULL PRIMARY KEY,
 address VARCHAR(700) NOT NULL,
 lat DECIMAL(10,7) NOT NULL,
 lng DECIMAL(10,7) NOT NULL,
 source VARCHAR(12) NOT NULL DEFAULT 'google',
 created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO cdb_schema_migrations(name,applied_at) VALUES ('2026_11_26_route_planner',NOW());
SELECT 'ok: 2026_11_26_route_planner' AS result;
