# Swiftlane UI audit inventory — 2 November 2026

This inventory began as a read-only static review. The cancellation fix and isolated regression checks below were subsequently implemented. It does not establish that authenticated workflows pass. Broader implementation and browser checks are owned by the parent task.

## Application and foundations

PackageSwiftLane is a PHP courier/logistics management application. Root PHP entrypoints include view templates and use `loader.php` for application classes, sessions, translations and configuration. `helpers/querys.php` supplies database operations through `lib/Conexion.php` (PDO). The `api/v1/` front controller supplies a separate token-authenticated REST API. Composer declares `spipu/html2pdf ^5.2`; bundled helper vendors are separate and should not be replaced. The local dev Dockerfile uses Debian Bookworm PHP packages, MariaDB, Composer and PHPUnit.

The shared UI contract is documented in `docs/UI_PAGE_PATTERN.md`: `swiftlane-tokens.css`, `swiftlane-ds.css`, and `dataJs/swiftlane_ui.js` own styles and interactions. Headers, navigation, forms, cards, tables and modals should follow this contract.

## Discoverable surface

- 303 root PHP entrypoint files. Some are exports, print actions, maintenance tools or public endpoints rather than navigable screens.
- 350 PHP files below `views/`, including 99 reporting views, 89 tools/settings views, 27 print views, 21 modal files, 18 courier views, 15 customer-package views, 11 dashboard views, 9 locations views, and shared fragments.
- 364 AJAX PHP handlers.
- 84 distinct literal PHP destinations discovered in shared sidebar, settings menu and topbar; all resolve to existing root files.
- 219 root PHP files are absent from those three navigation fragments; see `ui-routes.csv` for all entrypoints. Absence from those fragments does not mean unreachable: detail, edit, print, download, AJAX and public actions may be reached from page content.
- 77 distinct literal individual permission tokens appear in shared navigation; grouped permission arrays add further gates.

## Roles and intended coverage

Roles inherit type flags through `parent_role_id`, with cycle/depth guards. `helpers/rbac.php` maps superadmin > admin > staff > driver/agency > customer. Dashboard selection comes from `dashboard_type`, with legacy fallback. Menus use permission gates; customers and drivers have tailored branches. Actual grants depend on the unavailable runtime database and configuration.

| Route family / representative routes | Intended role context | Static inventory | Live workflow status |
| --- | --- | --- | --- |
| Authentication: `login.php`, `auth-otp.php`, `forgot-password.php`, `forgot-reset.php`, `sign-up.php` | Public/account holder | Inspected by auth agent | Blocked by missing loader/config; preview checks must be identified as fixtures |
| Dashboards: `index.php`, `dashboard_roles.php`, admin dashboard entrypoints | Admin/staff/agency/customer/driver by type/grants | Routing and role dispatch inspected | Blocked |
| Shipments: `courier_list.php`, `courier_add.php`, `courier_add_client.php`, `courier_edit.php`, `courier_view.php`, `courier_shipment_tracking.php` | Staff/agency via permission, customer own records, driver assignments | Entry/view/handler inventory | Blocked; cross-account mutation negative tests unexecuted |
| Customer packages: `customer_packages_list.php`, add/edit/view/deliver/tracking | Staff/agency/customer/driver by grants and ownership | Entry/view inventory | Blocked |
| Consolidations: `consolidate_*`, `consolidate_package_*` | Staff/agency, customer views/payments | Entry/view inventory | Blocked |
| Pickups/prealerts: `pickup_*`, `prealert_*` | Staff/agency/customer/driver by grants | Entry/view inventory | Blocked |
| People: customers, recipients, drivers, users, profile | Staff managers and own-profile users | RBAC rank helpers and representative update handlers inspected | Blocked |
| Billing/payments: financial overview, bills, accounts receivable, payment gateways | Role/finance feature flag dependent | Entry/view inventory | Blocked; no real financial transactions attempted |
| Tools/settings/locations/roles | Administrators or precise permission grants | Shared-menu destinations resolve | Blocked |
| Reports/exports/print | Permission-specific staff/agency/customer | Inventory includes files absent from primary menus | Untested output and data scoping |
| Public tracking: `track.php`, `tracking.php`, `track_online_shopping.php`, API tracking | Public | Entry/view/API inventory | Blocked by loader/config |
| API: auth, shipments, customers, recipients, tracking, prealerts, references, notifications | Bearer token or public tracking | Front controller and tests inspected | Existing unit suite must be run separately; full HTTP integration blocked |

## Checks executed by this audit

`node scripts/check_page_shell.js` — PASS, exit 0. All five enforced static rules report zero baselined violations (shell, wrapper, grid, colours, tables). This check only inspects views that include `inc/topbar.php`; it does not prove full accessibility or functional correctness.

The navigation-destination inventory — PASS for all 84 discovered literal PHP destinations. This checks filesystem existence, not permissions or runtime responses.

`php scripts/check_courier_cancellation.php` in the prepared local Docker image — PASS. Five reason cases (omitted, NULL, empty, zero-string and supplied text) and database failure propagation, with warnings converted to exceptions. Fake database only; no production or test database writes.

`php -l helpers/querys.php` and `php -l scripts/check_courier_cancellation.php` in Docker — PASS.

Historical XML result files under dev-tools were inspected only to locate tests; their results are not claimed as current verification. The full API suite is owned by the parent task.

## Demonstrated code issues / remaining risks

1. **High: courier attachment deletion lacks resource scoping.** `ajax/courier/courier_files_uploads_delete_ajax.php:27` authenticates, gates `view_shipment_list`, accepts a posted file ID and calls `cdp_deleteFileCourier`. `helpers/querys.php:5404` deletes `cdb_order_files WHERE id=:id` without joining its shipment or checking sender, driver or agency ownership. There is no scoped prefetch in this handler. The analogous customer-package helper at `helpers/querys.php:5417` also deletes by bare file ID. This is static evidence; exploitability under actual account grants remains untested. Add an ownership/scoping check aligned with established view/download permissions and test with isolated cross-account fixtures before considering verified.
2. **Fixed: missing cancellation reason produced an undefined-key warning.** `ajax/courier/courier_delete_ajax.php` builds only `id`, while the pickup caller supplies `message`. `helpers/querys.php` now explicitly binds NULL for an omitted reason and preserves all supplied reasons. The isolated regression covers both callers and database failure. Separately, the endpoint still uses only `view_shipment_list` with no per-shipment ownership guard; live authorization verification remains blocked.
3. **Medium: shared sidebar greetings emit editable names without escaping.** `views/inc/left_sidebar.php` directly echoes `$userData->fname` in multiple role branches. The parent task is correcting output escaping; security verification should use a profile name containing markup in isolated test data.
4. **Coverage: README describes a broader API than the checked-out `api/v1/index.php` implements.** README links `api/index.php`/`api/openapi.yaml` and describes OTP/refresh/settings/etc.; this checkout has `api/v1/index.php`, `api/v1/docs/openapi.yaml`, and a smaller route set. Do not claim those undocumented-in-code endpoints were tested or available.

Do not silently change the graduated RBAC policy in `helpers/ajax_guard.php`: its audit/off/enforce behavior is documented and deliberate. Resource ownership checks must be independent of menu visibility and preserve existing permissions.

## Verification limits

`loader.php` and `config/config.php` are absent. The original UI audit tools referenced by `docs/UI_PAGE_PATTERN.md` (`dev_preview/audit2.html`, `/tmp/render_preview.php`, `.claude/launch.json`) are also not part of this checkout. The test bootstrap explicitly stubs functions from the ionCube-protected loader. Consequently a synthetic rendered fixture can verify shared UI layout/interactions, but cannot establish successful login, persistent edits, tenant isolation, real role grants, upload safety, payment integrations, delivery of messages, or production performance.

No actual browsers, accounts, payment services, customer contacts or production records were used by this audit. Screen-reader/VoiceOver/TalkBack checks and full WCAG 2.2 AA conformance remain unverified. Full PHP/DB workflow and comparative production performance measurements require the app foundation, isolated database configuration and representative test accounts.

## Attachment scoping feasibility

No consistently used shared resource-ownership helper was found. Existing courier lists scope customers by `sender_id`, drivers by `driver_id`, and agencies by `name_off` resolved to a branch ID. The separate `cdp_getAgencyContext()` helper instead uses `agency_id` and can also restrict staff; the courier detail view uses legacy role-ID checks. A tenancy fix must reconcile these established differences and add isolated cross-account tests. This task does not silently select or replace that policy.
