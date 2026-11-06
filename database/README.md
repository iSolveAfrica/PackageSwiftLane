# Swift Lane database package

Supported engine: **MySQL 8.0 or newer** (tested on MySQL 9.7) and **MariaDB 10.6 or newer**. Character set `utf8mb4`. The application connects with PDO (`lib/Conexion.php`) using the credentials in `config/config.php`.

## Files

| File | Use when |
|---|---|
| `fresh_install_schema.sql` | Creating a **new, empty** database. Contains every table, the reference data (roles, permissions, statuses, lookups, countries/states/cities, templates) and every migration. Creates **no user account** and holds no secrets. Run once on an empty schema. |
| `upgrade_existing_database.sql` | Upgrading an **existing** Swift Lane database (vendor baseline of 2026‑02‑28 or any later production schema). Only additive, guarded statements. |
| `rollback_2026_11_06.sql` | Removing the tables that only the new interface writes to, if the release is rolled back. Column additions are kept (see the file header). |
| `../migrations/*.sql` | The individual migration files the upgrade is built from. `scripts/build_database_package.php` regenerates both package files from them. |

Baseline reference: `install.done/swiftlane_database.sql` is the vendor dump the schema was derived from (demo data, kept for traceability; do not load it on a server).

## Before you start

1. Stop the application (maintenance page) so no writes happen during the upgrade.
2. Back up: `mysqldump --single-transaction --routines --triggers -u USER -p DBNAME > backup-$(date +%F).sql`
3. Verify the backup restores into a scratch database: `mysql -u USER -p scratch_db < backup-….sql`.
4. Check the baseline: `mysql -u USER -p DBNAME -e "SELECT version FROM cdb_settings; SHOW TABLES LIKE 'cdb_schema_migrations';"`

## Execution order (existing installation)

```bash
mysql -u USER -p DBNAME < database/upgrade_existing_database.sql
```

Then deploy the code, copy `config/config.example.php` to `config/config.php` (keep your existing values), clear opcache/restart PHP-FPM, run the post-release smoke tests (README.md).

The upgrade runs the migration files in this order: `2026_11_06_swiftlane_ui_core`, `2026_11_06_legacy_drift`, then the remaining `migrations/*.sql` alphabetically. Every file ends with `SELECT 'ok: <name>'` so you can see progress; a ledger row is written to `cdb_schema_migrations`.

## Fresh installation

```bash
mysql -u USER -p -e "CREATE DATABASE swiftlane CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
mysql -u USER -p swiftlane < database/fresh_install_schema.sql
php scripts/create_admin.php --username=admin --email=you@example.com --name="Your Name"
```

`fresh_install_schema.sql` must run on an empty database: the key/auto-increment statements are not re-runnable (a second run stops with "Multiple primary key defined"; nothing is harmed). The migration part at the end of the file is re-runnable.

## Expected effects

- New tables: ledger `cdb_schema_migrations`, `cdb_user_preferences`, `cdb_sl_settings`, `cdb_customer_blocks`, `cdb_exchange_rate_log`, `cdb_activity_log`, `cdb_message_log`, the lazily-created helper tables (pickup codes, OTP, newsletter, message costs, profile, API tokens) and the tables of the new modules (see each `migrations/2026_11_06_*.sql` header).
- New columns, all nullable or with defaults: `cdb_users` (approve, registration_complete, document_photo, company, address, city, country, postal, signup_source, password_changed_at), `cdb_settings` (exchange_rate, active_otp), order/package/consolidation drift columns (recipient_type, estimated_eta, awb_no, fs_cleared_*, …), `cdb_user_roles` type flags, `cdb_countries.iso2`, `cdb_styles.status_type`, indexes on the order and tracking tables.
- Seed rows: statuses 28 "Held", 29 "On Hold", 32 "Ready for PickUp"; the `SWIFTLANE_UI` permission module and its actions granted to Super admin and Administrator; customer-facing actions granted to Customers.
- No row is deleted or truncated; no column is dropped; no existing value is changed except `cdb_user_roles` type flags where they were all zero.

## Rerun behaviour

Every migration statement is guarded (`CREATE TABLE IF NOT EXISTS`, information_schema checks before `ALTER TABLE ADD COLUMN`, `WHERE NOT EXISTS` before seed inserts). Running `upgrade_existing_database.sql` twice is safe; it was tested twice on the vendor baseline and on an empty database.

If a step fails (for example a privilege problem), fix the cause and run the whole file again: completed steps are skipped.

## Locking and downtime

`ALTER TABLE … ADD COLUMN` and `ADD KEY` on `cdb_add_order`, `cdb_customers_packages` and `cdb_courier_track` rebuild those tables on MySQL 8.0 (instant for most ADD COLUMN, online with a brief metadata lock for ADD KEY). On a table with hundreds of thousands of rows expect a few seconds to a few minutes; run during a maintenance window. Everything else is instant.

MySQL commits DDL implicitly: a failure mid-file leaves the earlier statements applied. That is why every statement is idempotent and why a backup is taken first.

## Verification queries

```sql
SELECT * FROM cdb_schema_migrations ORDER BY applied_at;           -- one row per migration
SELECT COUNT(*) FROM cdb_user_module_actions WHERE module_id = (SELECT id FROM cdb_user_module_permissions WHERE module_name='SWIFTLANE_UI');  -- 32 or more
SELECT id, mod_style FROM cdb_styles WHERE id IN (28,29,32);        -- 3 rows
SELECT COUNT(*) FROM cdb_add_order;                                 -- unchanged vs. the backup
SELECT COUNT(*) FROM cdb_users;                                     -- unchanged vs. the backup
SELECT exchange_rate, active_otp FROM cdb_settings;                 -- columns exist
```

Compare the row counts of `cdb_users`, `cdb_add_order`, `cdb_customers_packages`, `cdb_consolidate`, `cdb_payments_gateway` with the backup: they must be identical.

## Recovery

- Code problem after the upgrade: roll the code back; the old release ignores the new tables and columns.
- Schema problem: `mysql -u USER -p DBNAME < database/rollback_2026_11_06.sql` removes the new tables, or restore the backup taken before the upgrade (`mysql -u USER -p DBNAME < backup-….sql`) for a full return to the previous state.
