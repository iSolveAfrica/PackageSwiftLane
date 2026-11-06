# Encoded module inventory and replacements

Target: readable, editable source only; no ionCube Loader or other source
encoding at runtime. Status: **complete, zero encoded application files remain.**

## Inventory (starting commit 76d1dbb)

| Encoded module | Callers / purpose | Replacement | Verification |
|---|---|---|---|
| `loader.php` (not in git, ionCube, required by ~915 files) | Bootstrap: DB constants, session, `lib/*`, `$lang`, helper libraries, licence check | `loader.php` rewritten as plain PHP (config from `config/config.php`, session cookie flags, security headers, core classes, `helpers/config.lang.php`, helper libraries). No licence check. | Every page boots under PHP 8.3 without the loader extension; `php -l`; full app run in the local environment (ionCube absent). |
| `helpers/function_exist.php` (ionCube) | Shared helpers: `cdp_cleanOut`, `cdp_email_users_notifications`, `getSize`, `cdp_round_out`, `cdp_sumardias`, `cdp_generarCodigo`, `cdp_getChecked*`, status labels (`cdp_userStatus`, `cdp_isAdmin`, `cdp_locationStatus`, `cdp_paymentStatus`), settings data helpers (`cdp_getPaymentMethodEdit`, `cdp_insertPaymentMethod`, `cdp_updatePaymentMethod`, Nexmo updaters, `cdp_userwebEmailExiste`, `getCustomerPackageForOrderNo`, `getSenderCourier`) | Rewritten in readable PHP from the call-site contracts and the readable `*x` twins in `helpers/functions.php`. `cdp_generarCodigo` uses `random_int`; status labels escape output. | Call sites exercised through the pages (customer list, user list, config forms, tracking); unit of each function checked in the CLI. |
| `helpers/functions_money.php` (ionCube) | LicenseBox client (`activate_license`, `check_update`, `download_update`), despite the name no money code | Removed together with `verify_purchase.php` / `verify_update.php` views (now 301 redirects). Updates are deployed from git. | No readable code referenced the class except the two retired views. |
| `install.done/index.php` (ionCube installer) | One-time web installer that wrote `config/config.php` and loaded `swiftlane_database.sql` | Removed. Installation = `config/config.example.php` + `database/fresh_install_schema.sql` + `scripts/create_admin.php` (database/README.md). | Fresh install tested on an empty MySQL 9.7 database. |
| `helpers/.lic`, `helpers/.lic_domain` | Licence artefacts read only by encoded code | Removed. | grep shows no reader. |

Hidden logic that could not be recovered from the encoded files: none that
any execution path needs. The only functions whose original bodies were
unknown are the status-label helpers and the settings data helpers listed
above; their behaviour was defined by the HTML the callers print and the
columns they bind, and the replacements follow those contracts (they are
not claimed to be byte-identical to the originals).

## Runtime dependencies removed

- `composer.json`: no ionCube requirement existed; nothing to change.
- `.htaccess`, `api/.htaccess`: no loader directives.
- `tests/bootstrap.php` and `api/v1/bootstrap.php` keep their function stubs (harmless; they are skipped when `loader.php` already defined the functions).

## Scan evidence

`grep -rl "ionCube\|_il_exec" --include=*.php .` after the change lists only
files that mention ionCube in comments (`loader.php`, `helpers/function_exist.php`,
`api/v1/bootstrap.php`, `tests/bootstrap.php`). A byte scan for the ionCube
signature (`HR+c`) and for `_il_exec(` calls returns nothing.
