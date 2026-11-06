-- =============================================================================
-- 2026-11-06  Swift Lane UI: freight operations, last mile, security and the
--             developer console (new capabilities, no legacy equivalent)
-- Run:  mysql -u USER -p DBNAME < migrations/2026_11_06_freight_devops.sql
-- Every step is safe to run again (CREATE IF NOT EXISTS, WHERE NOT EXISTS).
-- Never drops or truncates anything.
-- =============================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_schema_migrations (
    name        VARCHAR(190) NOT NULL,
    applied_at  DATETIME NOT NULL,
    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Freight partners: airlines, shipping lines, trucking, customs brokers, ... --
CREATE TABLE IF NOT EXISTS cdb_freight_partners (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name           VARCHAR(150) NOT NULL,
    type           VARCHAR(30) NOT NULL DEFAULT 'airline',   -- airline | shipping_line | trucking | customs_broker | ground_handler | us_carrier | insurer
    code           VARCHAR(20) NULL,                          -- IATA / SCAC code
    contact_name   VARCHAR(150) NULL,
    contact_phone  VARCHAR(60) NULL,
    contact_email  VARCHAR(190) NULL,
    lanes          VARCHAR(255) NULL,                         -- e.g. "JFK → ACC, IAD → ACC"
    rate_value     DECIMAL(12,4) NULL,
    rate_unit      VARCHAR(30) NULL,                          -- per kg | per ft3 | per container | per declaration
    rate_currency  VARCHAR(5) NULL,
    contract_ends  DATE NULL,
    api_link       VARCHAR(190) NULL,
    notes          TEXT NULL,
    active         TINYINT(1) NOT NULL DEFAULT 1,
    created_by     INT NULL,
    created_at     DATETIME NOT NULL,
    updated_at     DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_type (type, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Departures and arrivals -----------------------------------------------------
CREATE TABLE IF NOT EXISTS cdb_freight_schedules (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    mode              VARCHAR(5) NOT NULL DEFAULT 'air',      -- air | sea
    partner_id        INT UNSIGNED NULL,
    carrier_name      VARCHAR(150) NULL,
    voyage_no         VARCHAR(60) NOT NULL,                   -- flight or vessel number
    origin            VARCHAR(80) NOT NULL,
    destination       VARCHAR(80) NOT NULL,
    cutoff_at         DATETIME NULL,
    departs_at        DATETIME NOT NULL,
    arrives_at        DATETIME NULL,                          -- carrier ETA
    arrived_at        DATETIME NULL,                          -- actual arrival
    status            VARCHAR(20) NOT NULL DEFAULT 'scheduled', -- scheduled | boarding | in_transit | arrived | delayed | cancelled
    capacity_total    DECIMAL(12,2) NULL,
    capacity_booked   DECIMAL(12,2) NOT NULL DEFAULT 0,
    capacity_unit     VARCHAR(10) NOT NULL DEFAULT 'pkgs',    -- pkgs | kg | ft3
    consolidation_ids VARCHAR(500) NULL,                      -- comma separated c_no values
    notes             TEXT NULL,
    created_by        INT NULL,
    created_at        DATETIME NOT NULL,
    updated_at        DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_departs (departs_at, status),
    KEY idx_partner (partner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Master / house waybills and manifests --------------------------------------
CREATE TABLE IF NOT EXISTS cdb_waybills (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kind             VARCHAR(10) NOT NULL DEFAULT 'master',   -- master | house
    waybill_no       VARCHAR(60) NOT NULL,
    parent_id        INT UNSIGNED NULL,                       -- house → master
    schedule_id      INT UNSIGNED NULL,
    consolidation_no VARCHAR(100) NULL,
    carrier          VARCHAR(150) NULL,
    origin           VARCHAR(80) NULL,
    destination      VARCHAR(80) NULL,
    status           VARCHAR(12) NOT NULL DEFAULT 'draft',    -- draft | issued | sent | closed
    pieces           INT NOT NULL DEFAULT 0,
    chargeable_kg    DECIMAL(12,2) NOT NULL DEFAULT 0,
    rate_per_kg      DECIMAL(12,4) NOT NULL DEFAULT 0,
    surcharge_per_kg DECIMAL(12,4) NOT NULL DEFAULT 0,
    total_cost       DECIMAL(12,2) NOT NULL DEFAULT 0,
    container_no     VARCHAR(40) NULL,
    seal_no          VARCHAR(40) NULL,
    container_ft3    DECIMAL(12,2) NULL,
    issued_at        DATETIME NULL,
    issued_by        INT NULL,
    sent_at          DATETIME NULL,
    sent_to          VARCHAR(190) NULL,
    notes            TEXT NULL,
    created_by       INT NULL,
    created_at       DATETIME NOT NULL,
    updated_at       DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_no (waybill_no),
    KEY idx_schedule (schedule_id),
    KEY idx_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_waybill_items (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    waybill_id    INT UNSIGNED NOT NULL,
    source        VARCHAR(14) NOT NULL DEFAULT 'shipment',    -- shipment (cdb_add_order) | package (cdb_customers_packages) | consolidation
    order_track   VARCHAR(100) NOT NULL,
    customer_name VARCHAR(190) NULL,
    description   VARCHAR(255) NULL,
    pieces        INT NOT NULL DEFAULT 1,
    weight_kg     DECIMAL(12,2) NOT NULL DEFAULT 0,
    charge        DECIMAL(12,2) NOT NULL DEFAULT 0,
    slot          INT NULL,                                   -- container loading position
    fragile       TINYINT(1) NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_waybill (waybill_id),
    KEY idx_track (order_track)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Cargo exceptions (damaged, missing, customs hold, partial, misrouted, hand-over)
CREATE TABLE IF NOT EXISTS cdb_cargo_exceptions (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    type            VARCHAR(20) NOT NULL,                     -- damaged | missing | customs_hold | partial | misrouted | delayed | handover
    entity_type     VARCHAR(20) NOT NULL DEFAULT 'shipment',  -- shipment | package | consolidation | schedule | waybill
    entity_ref      VARCHAR(100) NOT NULL,                    -- tracking / c_no / voyage
    title           VARCHAR(190) NOT NULL,
    severity        VARCHAR(10) NOT NULL DEFAULT 'medium',    -- low | medium | high | urgent
    status          VARCHAR(14) NOT NULL DEFAULT 'open',      -- open | in_progress | resolved | closed
    owner_id        INT NULL,
    customer_id     INT NULL,
    pieces_expected INT NULL,
    pieces_arrived  INT NULL,
    handed_from     VARCHAR(150) NULL,
    handed_to       VARCHAR(150) NULL,
    notify_customer TINYINT(1) NOT NULL DEFAULT 1,
    notes           TEXT NULL,
    photos          TEXT NULL,                                -- JSON list of upload paths
    claim_id        INT UNSIGNED NULL,
    created_by      INT NULL,
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME NULL,
    resolved_at     DATETIME NULL,
    resolved_by     INT NULL,
    PRIMARY KEY (id),
    KEY idx_status (status, severity),
    KEY idx_entity (entity_type, entity_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insurance claims ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cdb_insurance_claims (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    claim_no       VARCHAR(20) NOT NULL,
    order_id       INT NULL,
    order_track    VARCHAR(100) NOT NULL,
    customer_id    INT NULL,
    customer_name  VARCHAR(190) NULL,
    type           VARCHAR(10) NOT NULL DEFAULT 'damaged',    -- lost | damaged
    declared_value DECIMAL(12,2) NOT NULL DEFAULT 0,
    insured_value  DECIMAL(12,2) NOT NULL DEFAULT 0,
    claim_amount   DECIMAL(12,2) NOT NULL DEFAULT 0,
    offer_amount   DECIMAL(12,2) NULL,
    status         VARCHAR(12) NOT NULL DEFAULT 'reported',   -- reported | reviewing | approved | paid | rejected
    exception_id   INT UNSIGNED NULL,
    documents      TEXT NULL,                                 -- JSON list of upload paths
    timeline       TEXT NULL,                                 -- JSON list of {at, by, text}
    notes          TEXT NULL,
    created_by     INT NULL,
    created_at     DATETIME NOT NULL,
    updated_at     DATETIME NULL,
    paid_at        DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_claim (claim_no),
    KEY idx_status (status),
    KEY idx_track (order_track)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Last mile: delivery zones matched by recipient city -------------------------
CREATE TABLE IF NOT EXISTS cdb_delivery_zones (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    city_match  VARCHAR(500) NOT NULL,                        -- comma separated city names / keywords
    fee         DECIMAL(12,2) NOT NULL DEFAULT 0,
    rider_id    INT NULL,
    active      TINYINT(1) NOT NULL DEFAULT 1,
    created_by  INT NULL,
    created_at  DATETIME NOT NULL,
    updated_at  DATETIME NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Developer console -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS cdb_cron_runs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job         VARCHAR(80) NOT NULL,
    started_at  DATETIME NOT NULL,
    finished_at DATETIME NULL,
    status      VARCHAR(10) NOT NULL DEFAULT 'running',       -- running | ok | failed
    duration_ms INT NULL,
    message     VARCHAR(500) NULL,
    host        VARCHAR(100) NULL,
    PRIMARY KEY (id),
    KEY idx_job (job, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_dev_changelog (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    version     VARCHAR(40) NOT NULL,
    kind        VARCHAR(12) NOT NULL DEFAULT 'change',        -- feature | fix | change | security | migration
    title       VARCHAR(190) NOT NULL,
    body        TEXT NULL,
    ref         VARCHAR(100) NULL,                            -- commit, PR or ticket
    author      VARCHAR(150) NULL,
    environment VARCHAR(20) NOT NULL DEFAULT 'production',
    released_at DATETIME NOT NULL,
    created_by  INT NULL,
    created_at  DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_released (released_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_dev_alert_rules (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_key    VARCHAR(40) NOT NULL,
    label       VARCHAR(150) NOT NULL,
    metric      VARCHAR(30) NOT NULL,                         -- disk_pct | failed_cron | failed_logins | queue_backlog | php_errors | otp_failures | cron_stale_min
    comparator  VARCHAR(2) NOT NULL DEFAULT '>',
    threshold   DECIMAL(12,2) NOT NULL DEFAULT 0,
    window_min  INT NOT NULL DEFAULT 60,
    severity    VARCHAR(10) NOT NULL DEFAULT 'warning',       -- warning | critical
    notify      VARCHAR(20) NOT NULL DEFAULT 'email',         -- email | none
    enabled     TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL,
    updated_at  DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rule (rule_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Alerts: same table the live DevOps dashboard (helpers/devops.php) creates on
-- first use, so both the collector and the rule engine write one list.
CREATE TABLE IF NOT EXISTS cdb_dev_alerts (
    id INT NOT NULL AUTO_INCREMENT, alert_key VARCHAR(80) NOT NULL, severity VARCHAR(12) NOT NULL, service VARCHAR(60) NOT NULL,
    title VARCHAR(160) NOT NULL, detail VARCHAR(500) NULL, context TEXT NULL, status VARCHAR(12) NOT NULL DEFAULT 'active',
    first_seen DATETIME NOT NULL, last_seen DATETIME NOT NULL, resolved_at DATETIME NULL, ack_by INT NULL, ack_at DATETIME NULL,
    PRIMARY KEY (id), KEY idx_key (alert_key, status), KEY idx_status (status, last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_dev_issues (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ref          VARCHAR(16) NOT NULL,
    title        VARCHAR(190) NOT NULL,
    type         VARCHAR(10) NOT NULL DEFAULT 'bug',          -- bug | error | request | task
    priority     VARCHAR(4) NOT NULL DEFAULT 'p2',            -- p1 | p2 | p3
    area         VARCHAR(60) NULL,
    page         VARCHAR(120) NULL,
    status       VARCHAR(12) NOT NULL DEFAULT 'open',         -- open | in_progress | review | done
    assignee_id  INT NULL,
    reporter_id  INT NULL,
    steps        TEXT NULL,
    log_excerpt  TEXT NULL,
    fix_version  VARCHAR(40) NULL,
    created_at   DATETIME NOT NULL,
    updated_at   DATETIME NULL,
    closed_at    DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ref (ref),
    KEY idx_status (status, priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_dev_issue_events (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    issue_id   INT UNSIGNED NOT NULL,
    user_id    INT NULL,
    user_name  VARCHAR(150) NULL,
    kind       VARCHAR(10) NOT NULL DEFAULT 'comment',        -- comment | status | assign | create
    body       TEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_issue (issue_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Default alert rules (edit on the Alerts page) --------------------------------
INSERT INTO cdb_dev_alert_rules (rule_key, label, metric, comparator, threshold, window_min, severity, notify, enabled, created_at)
SELECT 'disk', 'Disk usage above 85%', 'disk_pct', '>', 85, 0, 'critical', 'email', 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_dev_alert_rules WHERE rule_key = 'disk');
INSERT INTO cdb_dev_alert_rules (rule_key, label, metric, comparator, threshold, window_min, severity, notify, enabled, created_at)
SELECT 'failed_cron', 'A cron job failed in the last 24 h', 'failed_cron', '>', 0, 1440, 'warning', 'email', 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_dev_alert_rules WHERE rule_key = 'failed_cron');
INSERT INTO cdb_dev_alert_rules (rule_key, label, metric, comparator, threshold, window_min, severity, notify, enabled, created_at)
SELECT 'failed_logins', 'Failed sign-ins above 20 in 10 min', 'failed_logins', '>', 20, 10, 'critical', 'email', 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_dev_alert_rules WHERE rule_key = 'failed_logins');
INSERT INTO cdb_dev_alert_rules (rule_key, label, metric, comparator, threshold, window_min, severity, notify, enabled, created_at)
SELECT 'queue_backlog', 'Notification outbox backlog above 200', 'queue_backlog', '>', 200, 0, 'warning', 'email', 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_dev_alert_rules WHERE rule_key = 'queue_backlog');
INSERT INTO cdb_dev_alert_rules (rule_key, label, metric, comparator, threshold, window_min, severity, notify, enabled, created_at)
SELECT 'php_errors', 'PHP errors above 50 in 60 min', 'php_errors', '>', 50, 60, 'warning', 'email', 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_dev_alert_rules WHERE rule_key = 'php_errors');
INSERT INTO cdb_dev_alert_rules (rule_key, label, metric, comparator, threshold, window_min, severity, notify, enabled, created_at)
SELECT 'cron_stale', 'No cron run recorded for 180 min', 'cron_stale_min', '>', 180, 0, 'warning', 'email', 1, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_dev_alert_rules WHERE rule_key = 'cron_stale');

-- Permissions ------------------------------------------------------------------
INSERT INTO cdb_user_module_permissions (module_name, description) SELECT 'FREIGHT_DEVOPS', 'Freight operations, insurance and the developer console' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_user_module_permissions WHERE module_name = 'FREIGHT_DEVOPS');
SET @mod := (SELECT id FROM cdb_user_module_permissions WHERE module_name = 'FREIGHT_DEVOPS' LIMIT 1);
INSERT INTO cdb_user_module_actions (module_id, action_name, description_module)
SELECT @mod, a.name, a.descr FROM (
    SELECT 'view_freight' AS name, 'See freight schedules, waybills, exceptions, claims and partners' AS descr UNION ALL
    SELECT 'manage_freight', 'Create and edit schedules, waybills, exceptions and partners' UNION ALL
    SELECT 'manage_insurance', 'Create, review and pay insurance claims' UNION ALL
    SELECT 'view_developer', 'Open the developer console (services, logs, metrics, alerts, issues)'
) a WHERE NOT EXISTS (SELECT 1 FROM cdb_user_module_actions x WHERE x.action_name = a.name);
INSERT INTO cdb_user_role_permissions (role_id, module_action_id, permitted, created_at)
SELECT r.role_id, ma.id, 1, NOW() FROM cdb_user_module_actions ma JOIN cdb_user_roles r ON r.role_id IN (9, 2)
WHERE ma.action_name IN ('view_freight', 'manage_freight', 'manage_insurance', 'view_developer')
  AND NOT EXISTS (SELECT 1 FROM cdb_user_role_permissions p WHERE p.role_id = r.role_id AND p.module_action_id = ma.id);

INSERT INTO cdb_schema_migrations (name, applied_at) SELECT '2026_11_06_freight_devops', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_schema_migrations WHERE name = '2026_11_06_freight_devops');
SELECT 'ok: 2026_11_06_freight_devops' AS result;
