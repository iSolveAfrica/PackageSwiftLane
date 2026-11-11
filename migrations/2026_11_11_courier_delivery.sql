-- Customer-approved last-mile deliveries. Additive and re-runnable.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS cdb_delivery_config (
 id INT NOT NULL PRIMARY KEY, config_json LONGTEXT NOT NULL, revision INT NOT NULL DEFAULT 1,
 updated_by INT NULL, updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO cdb_delivery_config VALUES (1,'{"enabled":false}',1,NULL,NOW());
CREATE TABLE IF NOT EXISTS cdb_delivery_zone_rules (
 zone_id INT UNSIGNED NOT NULL PRIMARY KEY, priority INT NOT NULL DEFAULT 100,
 polygon_json TEXT NULL, centre_lat DECIMAL(10,7) NULL, centre_lng DECIMAL(10,7) NULL,
 radius_km DECIMAL(10,2) NULL, agency_id INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cdb_delivery_requests (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 customer_id INT NOT NULL, agency_id INT NOT NULL DEFAULT 0, module VARCHAR(8) NOT NULL,
 parcel_json TEXT NOT NULL, address VARCHAR(700) NOT NULL, place_id VARCHAR(255) NOT NULL,
 lat DECIMAL(10,7) NOT NULL, lng DECIMAL(10,7) NOT NULL,
 origin_json TEXT NOT NULL, distance_m INT UNSIGNED NOT NULL,
 zone_id INT UNSIGNED NULL, pricing_mode VARCHAR(20) NOT NULL, currency CHAR(3) NOT NULL,
 amount_cents BIGINT UNSIGNED NOT NULL, rider_cents BIGINT UNSIGNED NOT NULL,
 breakdown_json TEXT NOT NULL, config_revision INT NOT NULL,
 state VARCHAR(20) NOT NULL DEFAULT 'quoted', driver_id INT NULL,
 contact_name VARCHAR(150) NOT NULL, contact_phone VARCHAR(40) NOT NULL, instructions VARCHAR(500) NOT NULL DEFAULT '',
 expires_at DATETIME NOT NULL, approved_at DATETIME NULL, assigned_at DATETIME NULL,
 receiver_name VARCHAR(150) NULL, proof_photo VARCHAR(300) NULL,
 fee_collected TINYINT NOT NULL DEFAULT 0, completed_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY delivery_customer (customer_id,created_at), KEY delivery_driver (driver_id,state), KEY delivery_queue (state,agency_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cdb_delivery_items (
 request_id BIGINT UNSIGNED NOT NULL, module VARCHAR(8) NOT NULL, order_id INT NOT NULL,
 tracking VARCHAR(400) NOT NULL, PRIMARY KEY(request_id,module,order_id), KEY delivery_parcel(module,order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- A single active delivery per parcel, enforced even during concurrent approvals.
CREATE TABLE IF NOT EXISTS cdb_delivery_claims (
 module VARCHAR(8) NOT NULL, order_id INT NOT NULL, request_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(module,order_id), KEY delivery_claim(request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS cdb_delivery_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, request_id BIGINT UNSIGNED NOT NULL,
 actor_id INT NOT NULL, state VARCHAR(20) NOT NULL, note VARCHAR(700) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY delivery_event(request_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Durable notifications are sent by the CLI worker, never inside a business transaction.
CREATE TABLE IF NOT EXISTS cdb_delivery_outbox (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, event_key VARCHAR(190) NOT NULL,
 recipient_id INT NOT NULL, subject VARCHAR(200) NOT NULL, message TEXT NOT NULL,
 state VARCHAR(20) NOT NULL DEFAULT 'pending', attempts INT NOT NULL DEFAULT 0,
 available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, locked_at DATETIME NULL,
 result_json TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY delivery_message(event_key,recipient_id), KEY delivery_outbox(state,available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO cdb_schema_migrations(name,applied_at) VALUES ('2026_11_11_courier_delivery',NOW());
SELECT 'ok: 2026_11_11_courier_delivery' AS result;
