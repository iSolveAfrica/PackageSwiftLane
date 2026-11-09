-- ============================================================================
-- 2026-11-04  China virtual mailbox addresses (main-only carve-out).
--
-- This is section 3 of migrations/2026_11_04_pickup_codes_handover_sms_mailbox.sql
-- on the temp branch, shipped to main on its own ahead of the rest of temp.
-- The SQL below is copied unchanged from that file, so running both files
-- (now, and again when temp is merged) leaves the same rows: every step is
-- safe to run again.
--
-- Run once against the live database, e.g.
--   mysql -u USER -p DBNAME < migrations/2026_11_04_virtual_mailbox_china.sql
-- ============================================================================

SET NAMES utf8mb4;

-- Guard added 2026-11-06: cdb_countries.iso2 and cdb_virtual_mailbox_addresses
-- are referenced below but do not exist on the vendor baseline dump.
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_countries' AND COLUMN_NAME = 'iso2');
SET @sql := IF(@has_col = 0, 'ALTER TABLE cdb_countries ADD COLUMN iso2 CHAR(2) NULL AFTER iso3', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
CREATE TABLE IF NOT EXISTS cdb_virtual_mailbox_addresses (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cdb_countries_id BIGINT UNSIGNED NULL,
    country          VARCHAR(190) NULL,
    city             VARCHAR(190) NULL,
    postcode         VARCHAR(40) NULL,
    address          TEXT NULL,
    default_address  TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

-- Ledger
CREATE TABLE IF NOT EXISTS cdb_schema_migrations (name VARCHAR(190) NOT NULL, applied_at DATETIME NOT NULL, PRIMARY KEY (name)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO cdb_schema_migrations (name, applied_at) SELECT '2026_11_04_virtual_mailbox_china', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM cdb_schema_migrations WHERE name = '2026_11_04_virtual_mailbox_china');
