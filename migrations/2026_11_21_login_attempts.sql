-- Failed sign-in log used by User::cdp_loginThrottled() (web and API login
-- brute-force protection). lib/User.php also creates it on demand; this file
-- makes the table part of the normal migration run. Idempotent.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS cdb_login_attempts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(190) NOT NULL,
 ip VARCHAR(45) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL,
 KEY idx_login_attempt_user (username, created_at),
 KEY idx_login_attempt_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO cdb_schema_migrations(name,applied_at) VALUES ('2026_11_21_login_attempts',NOW());
SELECT 'ok: 2026_11_21_login_attempts' AS result;
