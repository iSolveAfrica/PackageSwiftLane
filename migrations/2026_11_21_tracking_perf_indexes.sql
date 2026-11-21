-- Read-path indexes for the Overview, Live Tracking console, public tracking and
-- the nav badges. cdb_courier_track is looked up by tracking code
-- (sl_trackHistory, the track console, cdp_getTrackEvents, portal) and had no key
-- on order_track, so every lookup was a full scan of the event log.
-- cdb_customers_packages status counts (nav badges, overview) had no key led by
-- status_courier. Additive and re-runnable.
SET NAMES utf8mb4;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_courier_track' AND INDEX_NAME = 'idx_track_code');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_courier_track ADD KEY idx_track_code (order_track, t_date)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_customers_packages' AND INDEX_NAME = 'idx_pkg_status');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_customers_packages ADD KEY idx_pkg_status (status_courier)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

INSERT INTO cdb_schema_migrations (name, applied_at) SELECT '2026_11_21_tracking_perf_indexes', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_schema_migrations WHERE name = '2026_11_21_tracking_perf_indexes');
SELECT 'ok: 2026_11_21_tracking_perf_indexes' AS result;
