-- The back of the customer's ID card. cdb_users.document_photo stays the FRONT of the card;
-- document_photo_back holds the photo of the back (same format: a path under assets/, e.g.
-- "uploads/users/document_back_19_....jpg"). Written by ajax/save_profile_document_ajax.php
-- (document_photo_back) and ajax/customers/customers_document_edit_ajax.php (side=back).
-- The app checks for the column before reading or writing it, so it keeps working before this runs.
-- Additive and re-runnable.
SET NAMES utf8mb4;

SET @t := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_users');
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_users' AND COLUMN_NAME = 'document_photo_back');
SET @sql := IF(@t = 1 AND @c = 0, 'ALTER TABLE cdb_users ADD COLUMN document_photo_back VARCHAR(255) NULL DEFAULT NULL AFTER document_photo', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

INSERT INTO cdb_schema_migrations (name, applied_at) SELECT '2026_11_26_id_document_back', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_schema_migrations WHERE name = '2026_11_26_id_document_back');
SELECT 'ok: 2026_11_26_id_document_back' AS result;
