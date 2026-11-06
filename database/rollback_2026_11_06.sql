-- =============================================================================
-- Rollback of the 2026-11-06 Swift Lane UI upgrade.
--
-- Reversible part: the NEW tables below were created by the upgrade and hold
-- only data produced by the new interface. Dropping them is safe when you also
-- roll the code back to the previous release.
--
-- NOT reversed on purpose: columns added to existing tables (cdb_users,
-- cdb_settings, cdb_add_order, cdb_customers_packages, cdb_consolidate*,
-- cdb_courier_track, cdb_user_roles, cdb_countries, cdb_styles rows 28/29/32)
-- and the permission rows. They are additive, the old code ignores them, and
-- dropping them could destroy data entered after the upgrade. If you must
-- remove them, restore the pre-upgrade backup instead (database/README.md).
-- =============================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS cdb_user_preferences, cdb_sl_settings, cdb_customer_blocks, cdb_exchange_rate_log;
-- Tables of the new modules (freight, developer console, automations, loyalty, notification rules, ...)
-- are listed in the migration files under migrations/2026_11_06_*.sql; drop them the same way if required.
SET FOREIGN_KEY_CHECKS = 1;
DELETE FROM cdb_schema_migrations WHERE name LIKE '2026_11_06_%';
SELECT 'rollback done' AS result;
