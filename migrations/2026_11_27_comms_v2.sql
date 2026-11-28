-- Communication Center schema 2 (helpers/comms.php CDP_COMMS_SCHEMA = '2').
--
-- Statuses become their own message triggers (status:<cdb_styles.id>, helpers/comms_triggers.php);
-- the e-mail of a trigger can be built on the drag-and-drop canvas; campaigns carry their own
-- audience and a text per channel.
--
--   cdb_comm_templates.design_json   MEDIUMTEXT NULL   canvas design of a canvas-built e-mail
--   cdb_comm_templates.body          TEXT -> MEDIUMTEXT (compiled e-mails can pass 64 KB)
--   cdb_comm_outbox.body             TEXT -> MEDIUMTEXT
--   cdb_comm_campaigns.mode          VARCHAR(12)  'campaign' (one person / several people are not campaigns)
--   cdb_comm_campaigns.audience      TEXT NULL    audience rules JSON (replaces the saved segment)
--   cdb_comm_campaigns.bodies        MEDIUMTEXT   {"whatsapp": "...", "sms": "...", "email": "<!--sl-html-->..."}
--   cdb_comm_campaigns.design_json   MEDIUMTEXT   canvas design of the campaign e-mail
--   cdb_comm_campaigns.wa_template_name VARCHAR(120)  WhatsApp Cloud API approved template
--   cdb_comm_campaigns.recipients_total INT, launch_cursor INT  queued in passes of 500 by the worker
--   cdb_comm_campaigns.body          TEXT -> MEDIUMTEXT
--
-- The tables are created on demand by cdp_commEnsureTables(); on an install where they do not
-- exist yet every statement below is skipped and the code creates them at the new shape.
-- The DATA part of the change (rules per status, Notification Studio texts, retiring delivery
-- bookings) is the PHP migration cdp_commMigrateV2(), run once by cdp_commEnsureTables() and
-- recorded in cdb_ops_settings (ns 'comms', key 'migrated_v2'). Additive and re-runnable.
SET NAMES utf8mb4;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_templates' AND COLUMN_NAME = 'design_json');
SET @t := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_templates');
SET @sql := IF(@t = 1 AND @c = 0, 'ALTER TABLE cdb_comm_templates ADD COLUMN design_json MEDIUMTEXT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_templates' AND COLUMN_NAME = 'body' AND DATA_TYPE <> 'mediumtext');
SET @sql := IF(@c = 1, 'ALTER TABLE cdb_comm_templates MODIFY body MEDIUMTEXT NOT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_outbox' AND COLUMN_NAME = 'body' AND DATA_TYPE <> 'mediumtext');
SET @sql := IF(@c = 1, 'ALTER TABLE cdb_comm_outbox MODIFY body MEDIUMTEXT NOT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_campaigns' AND COLUMN_NAME = 'mode');
SET @t := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_campaigns');
SET @sql := IF(@t = 1 AND @c = 0, 'ALTER TABLE cdb_comm_campaigns ADD COLUMN mode VARCHAR(12) NOT NULL DEFAULT ''campaign''', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_campaigns' AND COLUMN_NAME = 'audience');
SET @t := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_campaigns');
SET @sql := IF(@t = 1 AND @c = 0, 'ALTER TABLE cdb_comm_campaigns ADD COLUMN audience TEXT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_campaigns' AND COLUMN_NAME = 'bodies');
SET @t := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_campaigns');
SET @sql := IF(@t = 1 AND @c = 0, 'ALTER TABLE cdb_comm_campaigns ADD COLUMN bodies MEDIUMTEXT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_campaigns' AND COLUMN_NAME = 'design_json');
SET @t := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_campaigns');
SET @sql := IF(@t = 1 AND @c = 0, 'ALTER TABLE cdb_comm_campaigns ADD COLUMN design_json MEDIUMTEXT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_campaigns' AND COLUMN_NAME = 'wa_template_name');
SET @t := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_campaigns');
SET @sql := IF(@t = 1 AND @c = 0, 'ALTER TABLE cdb_comm_campaigns ADD COLUMN wa_template_name VARCHAR(120) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_campaigns' AND COLUMN_NAME = 'recipients_total');
SET @t := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_campaigns');
SET @sql := IF(@t = 1 AND @c = 0, 'ALTER TABLE cdb_comm_campaigns ADD COLUMN recipients_total INT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_campaigns' AND COLUMN_NAME = 'launch_cursor');
SET @t := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_campaigns');
SET @sql := IF(@t = 1 AND @c = 0, 'ALTER TABLE cdb_comm_campaigns ADD COLUMN launch_cursor INT NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_campaigns' AND COLUMN_NAME = 'body' AND DATA_TYPE <> 'mediumtext');
SET @sql := IF(@c = 1, 'ALTER TABLE cdb_comm_campaigns MODIFY body MEDIUMTEXT NOT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
