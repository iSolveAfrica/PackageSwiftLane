# Deployment and rollback

## Release contents
- Application source (this repository, branch `temp`), `composer.lock`, `helpers/composer.lock`
- `database/upgrade_existing_database.sql`, `database/fresh_install_schema.sql`, `database/rollback_2026_11_06.sql`
- No build step: CSS/JS are plain files under `assets/swiftlane/` (no bundler, no minifier required)

## Order of operations (existing installation)
1. Announce maintenance; put the site behind a maintenance page.
2. Backup database (`mysqldump --single-transaction`) and the upload folders (`assets/uploads`, `support_files`, `driver_files`, `order_files`, `pre_alert_files`).
3. Deploy the code (git pull / rsync). Keep the existing `config/config.php`; compare it with `config/config.example.php` and add `CDP_RBAC_MODE`, `CDP_APP_URL`, `CDP_API_ALLOWED_ORIGINS` if missing. Delete any leftover `loader.php` from the old release only if it is the encoded one (the new readable `loader.php` ships in the repository).
4. `composer install --no-dev` (html2pdf).
5. `mysql -u USER -p DBNAME < database/upgrade_existing_database.sql`
6. File permissions: web user must write to the upload folders and `logs/` (create it if `CDP_ERROR_LOG` points there).
7. Restart PHP-FPM / clear opcache. Reload cron entries (README.md).
8. Remove the maintenance page.

## Post-release smoke tests
- `/login.php` renders the new login page; sign in as an admin, as a customer and as a driver.
- Overview loads with figures; Customers, Air Shipments, Sea Packages, Pickups, Consolidations, Warehouse lists load.
- Register a test shipment and a test package; print a label; verify a pickup code flow on a test parcel.
- Settings › General saves; Message Templates list loads; Reports hub loads an Excel export.
- `/api/v1/auth/login` returns a token for a test account.
- `tail` the PHP error log for warnings.

## Rollback
1. Put the maintenance page back.
2. Redeploy the previous release of the code (including its `loader.php`).
3. Database: the upgrade is additive, the previous release ignores new tables/columns. If the schema itself must be reverted, run `database/rollback_2026_11_06.sql` (drops only new tables) or restore the pre-release backup.
4. Restore upload folders only if files were changed by the new release (they are not touched by the upgrade).

## Health checks
- `GET /login.php` → 200
- `GET /api/v1/health` (if enabled in `api/v1/index.php`) → `{ "status": "ok" }`
- Developer console (`dev_overview.php`, permission `view_developer`) shows service, queue and cron status.
