-- One gateway (non-cash) payment reference may be booked in cdb_fs_payments only
-- once. The app checks for an existing row before inserting (fs_reference_taken),
-- but two concurrent Record Payment / Clear Debt / checkout completions could both
-- pass that check and book the same Paystack/Hubtel payment twice. This unique key
-- makes the database refuse the second insert; the app reports it as
-- 'duplicate_reference'.
--
-- ref_online_key is the reference for online modes and NULL for cash (cash receipt
-- numbers may legitimately repeat; NULLs never collide in a unique key). It uses the
-- table's case-insensitive collation, so references differing only in letter case
-- or trailing spaces count as the same payment.
--
-- If the ledger ALREADY holds duplicate online references, the unique key is not
-- added (it would fail); the last SELECT lists them. Reconcile those rows (e.g. move
-- the reference of the wrong duplicate into its note), then re-run this file.
-- Additive and re-runnable.
SET NAMES utf8mb4;

SET @t := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_fs_payments');

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_fs_payments' AND COLUMN_NAME = 'ref_online_key');
SET @sql := IF(@t = 1 AND @c = 0,
    'ALTER TABLE cdb_fs_payments ADD COLUMN ref_online_key VARCHAR(190) GENERATED ALWAYS AS (IF(mode = ''cash'' OR reference = '''', NULL, reference)) STORED',
    'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @dups := IF(@t = 1, (SELECT COUNT(*) FROM (
    SELECT ref_online_key FROM cdb_fs_payments WHERE ref_online_key IS NOT NULL
    GROUP BY ref_online_key HAVING COUNT(*) > 1) d), 0);
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_fs_payments' AND INDEX_NAME = 'uq_fs_ref_online');
SET @sql := IF(@t = 1 AND @i = 0 AND @dups = 0,
    'ALTER TABLE cdb_fs_payments ADD UNIQUE KEY uq_fs_ref_online (ref_online_key)',
    'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Only mark the migration applied once the key really exists.
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_fs_payments' AND INDEX_NAME = 'uq_fs_ref_online');
INSERT INTO cdb_schema_migrations (name, applied_at) SELECT '2026_11_22_payment_reference_unique', NOW() FROM DUAL WHERE @i > 0 AND NOT EXISTS (SELECT 1 FROM cdb_schema_migrations WHERE name = '2026_11_22_payment_reference_unique');

SET @sql := IF(@t = 1 AND @i = 0,
    'SELECT ref_online_key AS duplicate_reference, COUNT(*) AS rows_booked, GROUP_CONCAT(id ORDER BY id) AS payment_ids FROM cdb_fs_payments WHERE ref_online_key IS NOT NULL GROUP BY ref_online_key HAVING COUNT(*) > 1',
    'SELECT IF(@t = 1, ''ok: 2026_11_22_payment_reference_unique'', ''skipped: cdb_fs_payments not installed yet'') AS result');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
