-- Developer console: request metrics (helpers/dev_metrics.php).
--
-- loader.php arms a sampled recorder (cdp_reqmArm). At the end of every web request a shutdown
-- function decides whether to keep the request: every 5xx, fatal error and slow request (>= 1 s)
-- is kept with weight 1; other requests are kept with probability CDP_REQ_METRICS_RATE (default
-- 0.5) and weight round(1 / rate), so SUM(w) estimates the real request count without bias.
-- No query string, no user id, no IP is stored: only the script path, method, status and timings.
--
-- Raw rows are kept CDP_REQ_METRICS_DAYS days (default 8); finished days are rolled up into
-- cdb_dev_request_daily (one row per day and route plus a '*' row for the whole site) and kept
-- 400 days. The helper creates both tables on first use too, so this file is for installations
-- that apply migrations by hand.

CREATE TABLE IF NOT EXISTS cdb_dev_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ts DATETIME NOT NULL,
    route VARCHAR(120) NOT NULL,
    method VARCHAR(7) NOT NULL DEFAULT 'GET',
    status SMALLINT UNSIGNED NOT NULL DEFAULT 200,
    ms INT UNSIGNED NOT NULL DEFAULT 0,
    mem_kb INT UNSIGNED NULL,
    w SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    kind VARCHAR(8) NOT NULL DEFAULT 'page',
    fatal TINYINT NOT NULL DEFAULT 0,
    load1 DECIMAL(6,2) NULL,
    mem_pct DECIMAL(5,1) NULL,
    PRIMARY KEY (id),
    KEY idx_ts (ts),
    KEY idx_route_ts (route, ts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_dev_request_daily (
    day DATE NOT NULL,
    route VARCHAR(120) NOT NULL,
    n INT UNSIGNED NOT NULL DEFAULT 0,
    samples INT UNSIGNED NOT NULL DEFAULT 0,
    err5 INT UNSIGNED NOT NULL DEFAULT 0,
    err4 INT UNSIGNED NOT NULL DEFAULT 0,
    sum_ms BIGINT UNSIGNED NOT NULL DEFAULT 0,
    max_ms INT UNSIGNED NOT NULL DEFAULT 0,
    p50_ms INT UNSIGNED NULL,
    p95_ms INT UNSIGNED NULL,
    mem_kb_max INT UNSIGNED NULL,
    active_min INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (day, route)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
