-- Consolidation attachments in cdb_order_files record WHICH consolidation table owns them.
-- order_id holds a consolidate_id of either cdb_consolidate (air) or cdb_consolidate_packages
-- (sea), and the two tables' ids collide. Every consolidation file used to be stored with
-- is_consolidate = 1, so air #N's attachments showed on sea #N (and vice versa) and the
-- delete endpoints could not tell which agency a file belonged to.
--
-- From now on: is_consolidate = 0 shipment file, 1 = air consolidation, 2 = sea consolidation.
-- Backfill: a 1-row whose order_id exists ONLY in cdb_consolidate_packages is a sea file -> 2.
-- Rows whose id exists in both tables (or in neither) are ambiguous and stay 1; the app
-- treats those fail-closed (see ajax/consolidate*/consolidate_files_uploads_delete_ajax.php).
-- The index serves the per-consolidation file lists. Additive and re-runnable.
SET NAMES utf8mb4;

SET @t := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('cdb_order_files', 'cdb_consolidate', 'cdb_consolidate_packages'));

SET @sql := IF(@t = 3,
    'UPDATE cdb_order_files f SET f.is_consolidate = 2 WHERE f.is_consolidate = 1 AND EXISTS (SELECT 1 FROM cdb_consolidate_packages p WHERE p.consolidate_id = f.order_id) AND NOT EXISTS (SELECT 1 FROM cdb_consolidate c WHERE c.consolidate_id = f.order_id)',
    'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @f := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_order_files');
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_order_files' AND INDEX_NAME = 'idx_order_files_order');
SET @sql := IF(@f = 1 AND @c = 0, 'ALTER TABLE cdb_order_files ADD KEY idx_order_files_order (order_id, is_consolidate)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

INSERT INTO cdb_schema_migrations (name, applied_at) SELECT '2026_11_23_consolidation_file_kind', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_schema_migrations WHERE name = '2026_11_23_consolidation_file_kind');
SELECT 'ok: 2026_11_23_consolidation_file_kind' AS result;
