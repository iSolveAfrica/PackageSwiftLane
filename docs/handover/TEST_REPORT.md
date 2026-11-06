# Test report — Swift Lane UI integration (branch `temp`)

Environment: Linux container, PHP 8.3.6 CLI + built-in server, MySQL 9.7.4 (conda-forge build), Chromium via Playwright. **ionCube Loader not installed.** Database: vendor baseline dump (`install.done/swiftlane_database.sql`) + all migrations + development seed (`scripts/dev_seed.php`: 60 customers, ~1,470 shipments, ~410 packages, 25 pre-alerts, 20 consolidations).

## Results

| Check | Command / method | Result |
|---|---|---|
| Syntax | `php -l` on all 1,748 PHP files outside vendor | pass, except `pdf/_tcpdf_5.0.002/barcodes.php` (bundled TCPDF 5 uses `{}` string offsets; not loaded: `helpers/pdf.php` uses the Composer html2pdf/tcpdf) |
| Boot without ionCube | every page served by `php -S` with no loader extension | pass |
| ionCube scan | `grep -rl "HR+c\|_il_exec(" --include=*.php .` | no matches outside docs |
| Authenticated route sweep | 280 page routes × admin, driver, customer (840 requests; logout/print/export excluded) | 0 fatals, 0 HTTP 5xx. admin 199×200 / 80 redirects (legacy redirects, record pages without id); driver 26×200; customer 43×200 (others redirect to 403/login by permission) |
| PHP warnings during sweep | `/tmp/claude-0/php.log` | only legacy pages opened without their required query parameters (e.g. `report_customers_balance_detail.php`, `payment_return.php`, `customers_edit.php` without `?user=`). Pre-existing behaviour |
| Fresh install | empty DB ← `database/fresh_install_schema.sql` | pass, 157 tables, 11 migration ledger rows |
| Upgrade from baseline | baseline dump ← `database/upgrade_existing_database.sql`, run twice | pass both runs, 157 tables, user rows unchanged (4), ledger 11 |
| Migration rerun | each `migrations/*.sql` applied twice | pass |
| Write actions | exercised once per endpoint by the domain agents (curl with session + CSRF, and browser) | see FEATURE_MATRIX "Verification" column per screen |
| Visual | Playwright full-page captures at 1440 and 390 | `docs/handover/screenshots/` (admin, customer, driver, login) |
| PHPUnit | `tests/` | **not run**: `vendor/bin/phpunit` is not installed in this checkout (`composer install` needed with network access to Packagist) |

## Feature status
FEATURE_MATRIX.md: 128 screens — 113 done, 12 partial (listed with reasons), 2 not applicable (DevBilling, Piece-7d1a), 0 planned.

## Not tested / limits
- Real providers: SMTP, SMS (Hubtel/mNotify/Twilio), WhatsApp, Paystack/Hubtel payments and AI providers were not reachable from the sandbox; sends are recorded as failed/skipped in `cdb_message_log`. Test with sandbox credentials before release.
- OTP verification with a real code (OTP switched off locally).
- Apache `.htaccess` rules (only the PHP built-in server was used).
- MariaDB (only MySQL 9.7 was used).
- Browsers other than Chromium; screen readers.
- A role holding only the new freight/developer permissions.
- Performance under production data volume (indexes were added for the list and dashboard queries; not load-tested).
