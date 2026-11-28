-- WhatsApp screen inside the app (whatsapp_web.php, helpers/ultramsg.php).
-- The page reads and writes the linked WhatsApp number through UltraMsg and
-- keeps a local copy so the screen opens fast and history stays when the
-- provider is unreachable. cdp_umEnsureTables() creates the same tables on
-- first use; running this file first is optional and safe (IF NOT EXISTS).

-- Chats of the linked number (one row per WhatsApp chat id: 233...@c.us or ...@g.us).
-- For a one-to-one chat the WhatsApp Inbox conversation (cdb_comm_threads, same
-- phone digits) holds status, assignee and King; status / assignee_id here are
-- used for groups only. Tags are kept here for both.
CREATE TABLE IF NOT EXISTS cdb_wa_chats (
  id INT NOT NULL AUTO_INCREMENT,
  chat_id VARCHAR(80) NOT NULL,
  is_group TINYINT(1) NOT NULL DEFAULT 0,
  name VARCHAR(190) NULL,
  phone VARCHAR(32) NULL,
  user_id INT NULL,
  user_checked TINYINT(1) NOT NULL DEFAULT 0,
  unread INT NOT NULL DEFAULT 0,
  last_ts BIGINT NOT NULL DEFAULT 0,
  last_preview VARCHAR(255) NULL,
  last_from_me TINYINT(1) NOT NULL DEFAULT 0,
  last_ack VARCHAR(12) NULL,
  pinned TINYINT(1) NOT NULL DEFAULT 0,
  muted TINYINT(1) NOT NULL DEFAULT 0,
  archived TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(12) NOT NULL DEFAULT 'open',
  assignee_id INT NULL,
  tags VARCHAR(255) NULL,
  participants INT NULL,
  msgs_synced_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_chat (chat_id),
  KEY idx_last (last_ts),
  KEY idx_user (user_id),
  KEY idx_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Messages (provider message id is unique, so a webhook and a poll never store one twice).
CREATE TABLE IF NOT EXISTS cdb_wa_messages (
  id BIGINT NOT NULL AUTO_INCREMENT,
  msg_id VARCHAR(190) NOT NULL,
  chat_id VARCHAR(80) NOT NULL,
  from_me TINYINT(1) NOT NULL DEFAULT 0,
  author VARCHAR(80) NULL,
  author_name VARCHAR(150) NULL,
  type VARCHAR(20) NOT NULL DEFAULT 'text',
  body TEXT NULL,
  media_url VARCHAR(1000) NULL,
  thumb MEDIUMTEXT NULL,
  filename VARCHAR(255) NULL,
  ack VARCHAR(12) NULL,
  ts BIGINT NOT NULL DEFAULT 0,
  sent_by INT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_msg (msg_id),
  KEY idx_chat (chat_id, ts),
  KEY idx_author (author)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- The phone's address book (from UltraMsg contacts).
CREATE TABLE IF NOT EXISTS cdb_wa_contacts_cache (
  id INT NOT NULL AUTO_INCREMENT,
  wa_id VARCHAR(80) NOT NULL,
  phone VARCHAR(32) NULL,
  name VARCHAR(190) NULL,
  pushname VARCHAR(190) NULL,
  is_business TINYINT(1) NOT NULL DEFAULT 0,
  is_my_contact TINYINT(1) NOT NULL DEFAULT 0,
  synced_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wa (wa_id),
  KEY idx_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Groups and their participants (JSON [{id, phone, admin}]).
CREATE TABLE IF NOT EXISTS cdb_wa_groups (
  chat_id VARCHAR(80) NOT NULL,
  name VARCHAR(190) NULL,
  participants MEDIUMTEXT NULL,
  participant_count INT NOT NULL DEFAULT 0,
  source VARCHAR(12) NOT NULL DEFAULT 'api',
  synced_at DATETIME NOT NULL,
  PRIMARY KEY (chat_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Every export and every "save as contacts" (who, what, how many).
-- saved_count: numbers campaigns can reach (saved or updated, minus opted out);
-- opted_out_count: numbers saved as opted out (STOP).
CREATE TABLE IF NOT EXISTS cdb_wa_exports (
  id INT NOT NULL AUTO_INCREMENT,
  kind VARCHAR(20) NOT NULL,
  target VARCHAR(190) NULL,
  format VARCHAR(10) NOT NULL,
  row_count INT NOT NULL DEFAULT 0,
  saved_count INT NOT NULL DEFAULT 0,
  opted_out_count INT NOT NULL DEFAULT 0,
  user_id INT NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Contacts saved from WhatsApp (chats, the address book, group participants).
-- The owner chose that campaigns may reach them (consent = 1, with a note);
-- a STOP from the number sets opted_out and nothing ever clears it except a
-- START from the same number. Campaign audiences read this table through
-- helpers/comms_campaigns.php cdp_cmpContactsInfo() (source filter:
-- whatsapp_group:<name>, whatsapp_contacts, whatsapp_chat).
CREATE TABLE IF NOT EXISTS cdb_comm_contacts (
  id INT NOT NULL AUTO_INCREMENT,
  phone VARCHAR(32) NOT NULL,
  name VARCHAR(190) NULL,
  email VARCHAR(190) NULL,
  source VARCHAR(190) NOT NULL,
  sources TEXT NULL,
  opted_out TINYINT(1) NOT NULL DEFAULT 0,
  opted_out_at DATETIME NULL,
  user_id INT NULL,
  consent TINYINT(1) NOT NULL DEFAULT 1,
  consent_note VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_phone (phone),
  KEY idx_source (source),
  KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Schema 2 (installations that ran the first version of this file):
-- ALTER TABLE cdb_wa_messages ADD KEY idx_author (author);
-- ALTER TABLE cdb_wa_exports ADD COLUMN opted_out_count INT NOT NULL DEFAULT 0 AFTER saved_count;
-- cdp_umEnsureTables() applies both when they are missing.
