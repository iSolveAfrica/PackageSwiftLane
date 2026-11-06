# Swift Lane

Courier and logistics management system (air and sea freight, locker packages,
pickups, consolidations, warehouse hand-out, customer portal, billing, support
desk, AI assistant) built in plain PHP 8 with MySQL. The interface is the
approved **Swift Lane UI Redesign** (docs/handover/artifact-source).

The code base is fully readable: there is no ionCube or other source encoding
(see docs/handover/ENCODED_MODULES.md).

## Requirements

- PHP 8.2 or newer (8.3 tested) with `pdo_mysql`, `mbstring`, `gd`, `curl`, `zip`, `json`, `openssl`
- MySQL 8.0+ (9.7 tested) or MariaDB 10.6+
- Apache with `mod_rewrite` (the shipped `.htaccess`) or nginx with equivalent rewrites for `/api/v1/*`
- Node 18+ only for the development helpers in `scripts/` (screenshots, legacy page check)
- Composer for `spipu/html2pdf` (`composer install`); `helpers/vendor` ships the SMS client libraries

## Installation (new server)

```bash
git clone <repo> swiftlane && cd swiftlane
composer install --no-dev
cp config/config.example.php config/config.php      # database credentials, app URL, switches
mysql -u root -p -e "CREATE DATABASE swiftlane CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
mysql -u root -p swiftlane < database/fresh_install_schema.sql
php scripts/create_admin.php --username=admin --email=you@example.com --name="Your Name"
chown -R www-data:www-data assets/uploads support_files driver_files order_files pre_alert_files
```

Point the web server document root at the repository root. Open `/login.php`.

## Upgrading an existing installation

Follow `database/README.md` (backup, `database/upgrade_existing_database.sql`,
deploy code, smoke test). The old page URLs keep working; retired pages redirect.

## Configuration

`config/config.php` (never committed) defines:

| Constant | Meaning |
|---|---|
| `CDP_DB_HOST`, `CDP_DB_NAME`, `CDP_DB_USER`, `CDP_DB_PASS` | Database connection (`host:port` is accepted, or set `CDP_DB_PORT`) |
| `CDP_APP_MODE_DEMO` | `true` blocks every write (demo installations) |
| `CDP_APP_URL` | Public URL used in e-mails and payment returns |
| `CDP_RBAC_MODE` | `off`, `audit` or `enforce` for AJAX permission checks (use `enforce`) |
| `CDP_FINANCE_ENABLED` | Finance module switch (can also be set from Settings) |
| `CDP_API_ALLOWED_ORIGINS` | CORS origins for `/api/v1` |
| `CDP_DISPLAY_ERRORS`, `CDP_ERROR_LOG` | Development only / error log path |

Everything else (company details, SMTP, SMS/WhatsApp providers, payment
gateways, AI providers, exchange rate, tariffs) is edited in the application
under Settings and API Connections and stored in the database.

## Scheduled jobs

```
*/5 * * * *  php /path/to/cron/support_worker.php        # support outbox, SLA timers
0 * * * *    php /path/to/cron/pickup_aging.php          # unclaimed parcel reminders
0 3 * * *    php /path/to/cron/cleanup_tmp_uploads.php
0 4 * * 0    php /path/to/cron/purge_delivered_videos.php
*/10 * * * * php /path/to/cron/dev_alerts.php            # developer console alerts (if present)
```

## Project layout

| Path | Contents |
|---|---|
| `*.php` (root) | Page controllers (one per URL). They check login + permission and render a design page. |
| `views/sl/` | The Swift Lane design pages (`pages/`), data providers (`data/`), shell (`inc/`) |
| `assets/swiftlane/` | Design system CSS/JS and per-page CSS |
| `ajax/` | Form and action endpoints (`ajax/sl/` for the new pages, legacy folders for reused logic) |
| `helpers/` | Business logic, data access (`querys.php`), RBAC, notifications, finance, support, AI |
| `lib/` | `Conexion` (PDO), `Core` (settings), `User` (auth + permissions), `OtpService` |
| `api/v1/` | REST API (token auth, OpenAPI in `api/v1/docs/openapi.yaml`) |
| `migrations/`, `database/` | Schema migrations and the operator database package |
| `docs/handover/` | Developer guide, feature matrix, decision log, security evidence, test report |
| `tests/` | PHPUnit tests (`php vendor/bin/phpunit`) |

## Development

```bash
php -S 127.0.0.1:8084
php scripts/dev_seed.php --i-know-this-is-a-dev-database      # sample data on a local DB only
node scripts/dev_shot.cjs http://127.0.0.1:8084/index.php out.png 1440 admin 'password'
php vendor/bin/phpunit
```

Read `docs/handover/DEV_GUIDE.md` before adding a page.

## API

`api/v1/index.php` serves `/api/v1/*`: token auth (`cdb_api_user_tokens`), OTP challenge/verify,
password reset, RBAC via the same permission tables as the UI, agency/customer scoping, JSON
envelopes, pagination/filter/sort on list endpoints. Specification: `api/v1/docs/openapi.yaml`.

## License

Copyright (c) iSolveAfrica Ltd. All rights reserved. Proprietary and confidential software
developed specifically for Swiftlane. See [LICENSE](LICENSE).
