-- Developer console: per-section access and the locked shutdown (helpers/dev_access.php).
--
--   cdb_dev_section_grants   one row per (super admin, developer section) granted. Sections:
--                            overview services logs metrics infrastructure overrides deployments github
--                            database alerts issues modules access shutdown. The owner (first assigned
--                            super admin, cdb_dev_state 'owner_uid') holds every section without rows.
--   cdb_dev_unlock_attempts  every unlock-code attempt (kind 'unlock') and every shutdown password
--                            re-authentication (kind 'password'). Limits: 5 wrong per user per 15 min;
--                            unlock also 10 per address per 15 min and 25 overall per hour (the owner is
--                            exempt from those two). The row is written before the check, so a burst of
--                            parallel requests cannot get past the limit.
--   cdb_dev_state            'console_lock' = {"locked":true,"hash":"<password_hash>",...} while the
--                            console is shut down; recovery: php scripts/dev_unlock.php on the server.
--
-- Module switches need no table: cdb_sl_settings module_<id> = '0' | '1' (absent = on), Financial
-- keeps its existing 'finance_enabled' row. The code creates these tables on first use as well
-- (and adds the 'kind' column to an older cdb_dev_unlock_attempts), and gives developers assigned
-- before this change the observe sections only (overview services logs metrics infrastructure alerts
-- issues deployments github), recorded as one cdb_activity_log row. Owner-only grants: access,
-- shutdown, database.
-- Additive and re-runnable.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_dev_section_grants (
    user_id INT NOT NULL,
    section VARCHAR(32) NOT NULL,
    granted_by INT NULL,
    granted_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, section)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cdb_dev_unlock_attempts (
    id BIGINT NOT NULL AUTO_INCREMENT,
    user_id INT NULL,
    ip VARCHAR(45) NULL,
    kind VARCHAR(16) NOT NULL DEFAULT 'unlock',
    ok TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_user (user_id, created_at),
    KEY idx_ip (ip, created_at),
    KEY idx_at (created_at),
    KEY idx_kind (kind, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
