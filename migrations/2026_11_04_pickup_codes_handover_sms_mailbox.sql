-- ============================================================================
-- 2026-11-04  Pickup codes in the ready message, Picked up vs Delivered,
--             China virtual mailbox addresses.
--
-- Run once against the live database, e.g.
--   mysql -u USER -p DBNAME < migrations/2026_11_04_pickup_codes_handover_sms_mailbox.sql
-- Every step is safe to run again.
--
-- No step is needed for the SMS change: it is code only (helpers/hubtel_sms.php).
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- 1. Pickup codes (helpers/pickup_code.php)
--    The app creates these tables on first use; this makes sure they exist and
--    adds last_sent_at to tables made before codes went into the ready message.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cdb_pickup_codes (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code         VARCHAR(10)  NOT NULL,
    module       VARCHAR(8)   NOT NULL,
    owner_id     INT          NOT NULL,
    trackings    VARCHAR(2000) NOT NULL DEFAULT '',
    context      VARCHAR(40)  NOT NULL DEFAULT '',
    status       VARCHAR(12)  NOT NULL DEFAULT 'active',
    attempts     INT          NOT NULL DEFAULT 0,
    channels     VARCHAR(255) NOT NULL DEFAULT '',
    created_by   INT          NULL,
    created_at   DATETIME     NOT NULL,
    expires_at   DATETIME     NOT NULL,
    last_sent_at DATETIME     NULL,
    verified_by  INT          NULL,
    verified_at  DATETIME     NULL,
    PRIMARY KEY (id),
    KEY idx_owner (owner_id),
    KEY idx_status (status, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cdb_pickup_code_items (
    code_id     INT UNSIGNED NOT NULL,
    module      VARCHAR(8)   NOT NULL,
    order_id    INT          NOT NULL,
    used_at     DATETIME     NULL,
    used_by     INT          NULL,
    PRIMARY KEY (code_id, order_id),
    KEY idx_pkg (module, order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_pickup_codes' AND COLUMN_NAME = 'last_sent_at');
SET @sql := IF(@has_col = 0,
               'ALTER TABLE cdb_pickup_codes ADD COLUMN last_sent_at DATETIME NULL AFTER expires_at',
               'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Codes from the old 3-minute flow that were never used are finished.
UPDATE cdb_pickup_codes SET status = 'expired'
WHERE status = 'active' AND expires_at < NOW();

-- ----------------------------------------------------------------------------
-- 2. Picked up and Delivered are two statuses, each named for what it is.
--    15 Picked up = the customer collected it from us.
--    8  Delivered = we took it to the customer.
-- ----------------------------------------------------------------------------
INSERT INTO cdb_styles (id, mod_style, detail, color)
SELECT 15, 'Picked up', 'Picked up package', '#00adf2'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_styles WHERE id = 15);

INSERT INTO cdb_styles (id, mod_style, detail, color)
SELECT 8, 'Delivered', 'Deliveries delivered', '#43bd00'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_styles WHERE id = 8);

UPDATE cdb_styles SET mod_style = 'Picked up', detail = 'Picked up by the customer (collected from us)' WHERE id = 15;
UPDATE cdb_styles SET mod_style = 'Delivered', detail = 'Delivered to the customer (taken to them)'   WHERE id = 8;

-- ----------------------------------------------------------------------------
-- 3. China virtual mailbox addresses (cdb_virtual_mailbox_addresses)
--    The first two China addresses (lowest id first) are replaced, in order:
--      1st -> Guangzhou air warehouse (normal)
--      2nd -> Sea address
--    and the Beijing air express address is added after them.
--    "(LOCKER ID)" is replaced by each customer's locker when shown.
--    As on the existing China rows, city stays 'China' and the postcode
--    field carries the warehouse phone number.
-- ----------------------------------------------------------------------------
SET @cn1 := (SELECT v.id FROM cdb_virtual_mailbox_addresses v
             LEFT JOIN cdb_countries c ON c.id = v.cdb_countries_id
             WHERE c.iso2 = 'CN' OR c.name LIKE '%China%'
             ORDER BY v.id LIMIT 1);
SET @cn2 := (SELECT v.id FROM cdb_virtual_mailbox_addresses v
             LEFT JOIN cdb_countries c ON c.id = v.cdb_countries_id
             WHERE (c.iso2 = 'CN' OR c.name LIKE '%China%') AND v.id <> IFNULL(@cn1, 0)
             ORDER BY v.id LIMIT 1);

UPDATE cdb_virtual_mailbox_addresses
SET address = '广州空运普快地址：GUANGZHOU AIR WAREHOUSE ADDRESS (NORMAL)

广东省佛山市南海区里水镇贤僚村贤僚通道18号航威云仓D233库
SFSWIFT(LOCKER ID)
收件人：SFSWIFT(LOCKER ID)
电话：18122021422',
    city = 'China',
    postcode = '18122021422'
WHERE id = @cn1;

UPDATE cdb_virtual_mailbox_addresses
SET address = 'Sea Address

广东省广州市花都区新雅街道镜湖大道南自编79号
(泽贤汽车检测站内) ACCSWIFT(LOCKER ID)
联系人：ACCSWIFT (LOCKER ID  )
仓库电话：19924601872',
    city = 'China',
    postcode = '19924601872'
WHERE id = @cn2;

-- Beijing: a copy of the first China row (same country and any other columns),
-- with its own address. Skipped when it is already there.
SET @has_bj := (SELECT COUNT(*) FROM cdb_virtual_mailbox_addresses
                WHERE address LIKE '%北京市大兴区空港新苑二区6号院8号楼2-103室%');

SET @new_id := (SELECT IFNULL(MAX(id), 0) + 1 FROM cdb_virtual_mailbox_addresses);

DROP TEMPORARY TABLE IF EXISTS tmp_mailbox_bj;
CREATE TEMPORARY TABLE tmp_mailbox_bj SELECT * FROM cdb_virtual_mailbox_addresses WHERE id = @cn1 AND @has_bj = 0;
UPDATE tmp_mailbox_bj
SET id = @new_id,
    address = '顺丰北京空运快件地址变更为： Beijing Air Express Address

北京市大兴区空港新苑二区6号院8号楼2-103室
SFSWIFT(LOCKER ID )
收件人：SFSWIFT(LOCKER ID )
电话： 17611390952',
    city = 'China',
    postcode = '17611390952',
    default_address = 0;
INSERT INTO cdb_virtual_mailbox_addresses SELECT * FROM tmp_mailbox_bj;
DROP TEMPORARY TABLE IF EXISTS tmp_mailbox_bj;

-- Check: the China addresses as they now stand.
SELECT v.id, c.name AS country, v.city, v.postcode, v.address
FROM cdb_virtual_mailbox_addresses v
LEFT JOIN cdb_countries c ON c.id = v.cdb_countries_id
WHERE c.iso2 = 'CN' OR c.name LIKE '%China%'
ORDER BY v.id;
