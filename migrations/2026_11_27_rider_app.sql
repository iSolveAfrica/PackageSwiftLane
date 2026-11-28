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
