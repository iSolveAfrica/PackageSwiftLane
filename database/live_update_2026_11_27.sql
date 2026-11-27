-- Swift Lane: database changes added on 27 Nov 2026 (phone app, rider app, route planning, load plans).
-- Safe to run more than once. If you are not sure the earlier updates are on the live database,
-- run database/upgrade_existing_database.sql instead: it contains these and every earlier change.
-- Back up first:  mysqldump --single-transaction --routines --triggers -u USER -p DBNAME > backup.sql
-- Then:           mysql -u USER -p DBNAME < database/live_update_2026_11_27.sql

-- ===== migrations/2026_11_26_route_planner.sql =====
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

-- ===== migrations/2026_11_27_consolidation_load_plans.sql =====
CREATE TABLE IF NOT EXISTS cdb_consolidation_load_plans (
 mode VARCHAR(3) NOT NULL,
 consolidation_id INT NOT NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 0,
 positions_json LONGTEXT NOT NULL,
 updated_by INT NOT NULL,
 updated_at DATETIME NOT NULL,
 PRIMARY KEY (mode,consolidation_id)
) ENGINE=InnoDB;

-- ===== migrations/2026_11_27_rider_app.sql =====
-- =============================================================================
-- 2026-11-27  Rider app: wallet, quests, shifts, safety and account screens
-- Run:  mysql -u USER -p DBNAME < migrations/2026_11_27_rider_app.sql
-- Safe to run again (CREATE TABLE IF NOT EXISTS). Nothing is dropped.
-- Code: helpers/rider_money.php, views/sl/data/rider_money.php, ajax/sl/rider_app_ajax.php.
-- =============================================================================
SET NAMES utf8mb4;

-- A rider asks to be paid; staff approve, decline and mark paid. No money moves by itself.
CREATE TABLE IF NOT EXISTS cdb_rider_payout_requests (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rider_id    INT NOT NULL,
    amount      DECIMAL(12,2) NOT NULL,
    note        VARCHAR(255) NULL,
    status      VARCHAR(12) NOT NULL DEFAULT 'requested',   -- requested | approved | paid | declined | cancelled
    staff_note  VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_by  INT NULL,
    decided_at  DATETIME NULL,
    paid_by     INT NULL,
    paid_at     DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_payout_rider (rider_id, status),
    KEY idx_payout_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Staff-defined quests: finish target_trips between starts_at and ends_at, earn reward.
CREATE TABLE IF NOT EXISTS cdb_rider_quests (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title         VARCHAR(120) NOT NULL,
    target_trips  INT UNSIGNED NOT NULL,
    reward        DECIMAL(12,2) NOT NULL DEFAULT 0,
    starts_at     DATETIME NOT NULL,
    ends_at       DATETIME NOT NULL,
    active        TINYINT(1) NOT NULL DEFAULT 1,
    created_by    INT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_quest_window (active, starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Availability blocks a rider books for themselves.
CREATE TABLE IF NOT EXISTS cdb_rider_shifts (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rider_id    INT NOT NULL,
    day         DATE NOT NULL,
    start_time  TIME NOT NULL,
    end_time    TIME NOT NULL,
    hub         VARCHAR(120) NOT NULL DEFAULT '',
    status      VARCHAR(12) NOT NULL DEFAULT 'booked',      -- booked | cancelled
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cancelled_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_shift_rider (rider_id, day, status),
    KEY idx_shift_day (day, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Per-rider preferences and details (nav_app, offer_sounds, payout_*, ec_* emergency contact, checklist).
-- rider_id 0 holds site-wide rider app settings (emergency_number).
CREATE TABLE IF NOT EXISTS cdb_rider_prefs (
    rider_id    INT NOT NULL,
    pref_key    VARCHAR(40) NOT NULL,
    pref_value  VARCHAR(500) NOT NULL DEFAULT '',
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (rider_id, pref_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO cdb_schema_migrations (name, applied_at)
SELECT '2026_11_27_rider_app', NOW() FROM DUAL
WHERE EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_schema_migrations')
  AND NOT EXISTS (SELECT 1 FROM cdb_schema_migrations WHERE name = '2026_11_27_rider_app');

SELECT 'ok: 2026_11_27_rider_app' AS result;

-- ===== migrations/2026_11_27_rider_ops.sql =====
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

-- ===== migrations/2026_11_27_rider_positions.sql =====
CREATE TABLE IF NOT EXISTS cdb_rider_positions (
 driver_id INT NOT NULL PRIMARY KEY,
 request_id BIGINT UNSIGNED NOT NULL,
 latitude DECIMAL(10,7) NOT NULL,
 longitude DECIMAL(10,7) NOT NULL,
 recorded_at DATETIME NOT NULL,
 INDEX (request_id)
) ENGINE=InnoDB;

