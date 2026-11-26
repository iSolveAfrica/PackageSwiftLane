-- Fingerprint / face sign-in (passkeys, helpers/webauthn.php). The table is also created on
-- first use; this file makes it part of the normal migration run. Idempotent.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS cdb_passkeys (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 credential_id VARCHAR(255) NOT NULL,
 public_key TEXT NOT NULL,
 alg INT NOT NULL DEFAULT -7,
 sign_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
 label VARCHAR(120) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL,
 last_used_at DATETIME NULL,
 UNIQUE KEY uq_passkey_cred (credential_id),
 KEY idx_passkey_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO cdb_schema_migrations(name,applied_at) VALUES ('2026_11_26_passkeys',NOW());
SELECT 'ok: 2026_11_26_passkeys' AS result;
