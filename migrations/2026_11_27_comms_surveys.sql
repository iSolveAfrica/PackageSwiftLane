-- Communications > Surveys (comms_surveys.php, helpers/comms_surveys.php).
--
--   cdb_comm_survey_forms     a survey: questions (JSON: key, type rating|nps|choice|multi|text, label,
--                             required, options), intro, thank-you line, the statuses that send it
--                             (JSON ids; [] = every delivered / picked-up status), delay in hours,
--                             channels (inapp,whatsapp,sms,email), once per customer per N days
--   cdb_comm_survey_answers   one row per answer (a multi-choice answer is one row per option ticked);
--                             the question's type and label are kept as asked
--   cdb_comm_surveys          + form_id, status_id, track_id, entity_type, order_id, rider_id, nps,
--                             prompted_count / last_prompted_at / dismissed_at (the in-app pop-up),
--                             UNIQUE (track_id, user_id) so a status row asks a customer once,
--                             never_ask ("Don't ask me again"), followup_ticket_id (low score -> Customer Care),
--                             KEY idx_sent (sent_at) for the Results period (schema 2)
--   cdb_comm_survey_forms     + archived_at (deleted after it was sent: switched off, answers kept)
--
-- helpers/comms_surveys.php (cdp_surveyEnsure) applies the same change on first use and seeds the
-- default form "How did we do?" (rating + comment), so running this file is optional.
-- Additive and re-runnable: every statement checks first.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_comm_survey_forms (
    id INT NOT NULL AUTO_INCREMENT, name VARCHAR(120) NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1,
    questions MEDIUMTEXT NULL, intro VARCHAR(500) NULL, thanks VARCHAR(500) NULL,
    trigger_statuses TEXT NULL, delay_hours INT NOT NULL DEFAULT 2, channels VARCHAR(120) NULL,
    frequency_days INT NOT NULL DEFAULT 7, sort_order INT NOT NULL DEFAULT 0,
    created_by INT NULL, updated_by INT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
    PRIMARY KEY (id), KEY idx_active (active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_comm_survey_answers (
    id BIGINT NOT NULL AUTO_INCREMENT, survey_id INT NOT NULL, form_id INT NULL, question_key VARCHAR(20) NOT NULL,
    question_type VARCHAR(10) NOT NULL, question_label VARCHAR(200) NULL, value_num INT NULL, value_text TEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id), KEY idx_survey (survey_id), KEY idx_form_q (form_id, question_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_comm_surveys (
    id INT NOT NULL AUTO_INCREMENT, user_id INT NOT NULL, entity_ref VARCHAR(80) NULL, channel VARCHAR(12) NULL,
    score TINYINT NULL, comment TEXT NULL, sent_at DATETIME NOT NULL, responded_at DATETIME NULL, branch_id INT NULL,
    PRIMARY KEY (id), KEY idx_user (user_id, sent_at), KEY idx_resp (responded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_surveys' AND COLUMN_NAME = 'form_id');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_comm_surveys ADD COLUMN form_id INT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_surveys' AND COLUMN_NAME = 'status_id');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_comm_surveys ADD COLUMN status_id INT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_surveys' AND COLUMN_NAME = 'track_id');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_comm_surveys ADD COLUMN track_id INT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_surveys' AND COLUMN_NAME = 'entity_type');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_comm_surveys ADD COLUMN entity_type VARCHAR(20) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_surveys' AND COLUMN_NAME = 'order_id');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_comm_surveys ADD COLUMN order_id INT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_surveys' AND COLUMN_NAME = 'rider_id');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_comm_surveys ADD COLUMN rider_id INT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_surveys' AND COLUMN_NAME = 'nps');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_comm_surveys ADD COLUMN nps TINYINT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_surveys' AND COLUMN_NAME = 'prompted_count');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_comm_surveys ADD COLUMN prompted_count INT NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_surveys' AND COLUMN_NAME = 'last_prompted_at');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_comm_surveys ADD COLUMN last_prompted_at DATETIME NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_surveys' AND COLUMN_NAME = 'dismissed_at');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_comm_surveys ADD COLUMN dismissed_at DATETIME NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_surveys' AND COLUMN_NAME = 'never_ask');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_comm_surveys ADD COLUMN never_ask TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_surveys' AND COLUMN_NAME = 'followup_ticket_id');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_comm_surveys ADD COLUMN followup_ticket_id INT NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_survey_forms' AND COLUMN_NAME = 'archived_at');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_comm_survey_forms ADD COLUMN archived_at DATETIME NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_surveys' AND INDEX_NAME = 'uq_track');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_comm_surveys ADD UNIQUE KEY uq_track (track_id, user_id)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_surveys' AND INDEX_NAME = 'idx_form');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_comm_surveys ADD KEY idx_form (form_id, sent_at)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
-- Schema 2: the Results period filters on sent_at.
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_comm_surveys' AND INDEX_NAME = 'idx_sent');
SET @sql := IF(@c = 0, 'ALTER TABLE cdb_comm_surveys ADD KEY idx_sent (sent_at)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- The default form, only when there is none (inapp plus the channels the old "survey" rule had on are
-- added by cdp_surveyEnsure; here the in-app pop-up only).
INSERT INTO cdb_comm_survey_forms (name, active, questions, intro, thanks, trigger_statuses, delay_hours, channels, frequency_days, sort_order, created_by, created_at, updated_at)
SELECT 'How did we do?', 1,
       '[{"key":"q1","type":"rating","label":"How would you rate your experience with this delivery?","required":true,"options":[]},{"key":"q2","type":"text","label":"Anything you would like to tell us?","required":false,"options":[]}]',
       'Your package has arrived. Tell us in a few seconds how it went.', 'Thank you! Your feedback helps us serve you better.',
       '[]', 2, 'inapp', 7, 0, 0, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_comm_survey_forms);
