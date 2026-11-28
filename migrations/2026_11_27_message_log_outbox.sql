-- Message log <-> Communication Center outbox (Communications > Outbox, comms_outbox.php).
--
--   cdb_message_log.outbox_id  BIGINT NULL   the cdb_comm_outbox row this attempt delivered
--                                            (cdp_msgLog writes it from the context key outbox_id,
--                                            which cdp_commDeliverContext sets for every outbox send)
--   KEY idx_batch (batch_id)                 campaign / broadcast reports and opens + clicks
--   KEY idx_source (source, created_at)      "sent this month" per trigger, Outbox origin filter
--   KEY idx_entity (entity_type, entity_id)  messages of one shipment / package / consolidation
--   KEY idx_outbox (outbox_id)               the attempts of one outbox row
--
-- helpers/message_log.php (cdp_msgEnsureOutboxColumn) applies the same change on first use, so
-- running this file is optional. Additive and re-runnable: every statement checks first.
SET NAMES utf8mb4;

SET @t := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_message_log');

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_message_log' AND COLUMN_NAME = 'outbox_id');
SET @sql := IF(@t = 1 AND @c = 0, 'ALTER TABLE cdb_message_log ADD COLUMN outbox_id BIGINT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_message_log' AND INDEX_NAME = 'idx_batch');
SET @sql := IF(@t = 1 AND @c = 0, 'ALTER TABLE cdb_message_log ADD KEY idx_batch (batch_id)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_message_log' AND INDEX_NAME = 'idx_source');
SET @sql := IF(@t = 1 AND @c = 0, 'ALTER TABLE cdb_message_log ADD KEY idx_source (source, created_at)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_message_log' AND INDEX_NAME = 'idx_entity');
SET @sql := IF(@t = 1 AND @c = 0, 'ALTER TABLE cdb_message_log ADD KEY idx_entity (entity_type, entity_id)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_message_log' AND INDEX_NAME = 'idx_outbox');
SET @sql := IF(@t = 1 AND @c = 0, 'ALTER TABLE cdb_message_log ADD KEY idx_outbox (outbox_id)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
