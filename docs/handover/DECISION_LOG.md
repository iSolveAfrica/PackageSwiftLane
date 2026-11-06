# Decision log (old vs. new)

| # | Decision | Reason | Evidence |
|---|---|---|---|
| 1 | Keep PHP 8 + MySQL + page-per-URL architecture; no framework migration | The approved design is static HTML/CSS; rendering it server-side from PHP keeps every existing business rule, endpoint and the REST API intact with the smallest change surface | Shell + 128 converted templates render in the legacy routing |
| 2 | Keep legacy URLs for pages that existed; new URLs only for new screens; retired pages 301-redirect | Deep links, bookmarks, e-mails and the API must keep working | `helpers/sl_routes.php` |
| 3 | One design system stylesheet copied verbatim from the artifact (`assets/swiftlane/swiftlane.css`) plus a small `app.css` for runtime states; no Bootstrap in new pages | Preserves the approved look exactly; runtime states (toast, dialog, validation) were not part of the static design | screenshots in docs/handover/screenshots |
| 4 | Templates generated from the artboards by `scripts/sl_convert.py`, then bound by hand | Mechanical conversion keeps markup fidelity; data binding needs judgement | views/sl/pages |
| 5 | Navigation driven by a registry with permission gates and live counters; items the role cannot use are hidden; unknown modules hidden except for super admin | The design shows every module for a super admin; staff roles must only see what they may use | `helpers/sl_nav.php` |
| 6 | Finance figures hidden when `cdp_canViewMoney()` denies, finance group hidden when the finance switch is off | Existing business rule (FinancialOverviewRestricted artboard confirms the intent) | Overview, Customers |
| 7 | Replace the ionCube loader/licence/installer with readable PHP and remove the LicenseBox update flow | Required by the brief; updates come from git | ENCODED_MODULES.md |
| 8 | Customer blocking implemented as a first-class record (`cdb_customer_blocks`) plus `active=0`, with optional parcel hold (status 29) and e-mail | The design's block dialog is richer than the legacy activate/deactivate toggle; keeping `active=0` preserves old behaviour for login checks | ajax/sl/customer_status_ajax.php |
| 9 | KPIs computed live from the transaction tables with 90-day activity windows; targets/capacity stored in `cdb_sl_settings` | No invented figures; operators set targets | views/sl/data/overview.php |
| 10 | Theme toggle persisted per user (`cdb_user_preferences`) and in localStorage | The design has light and dark variants | assets/swiftlane/swiftlane.js |
| 11 | Migrations guarded with information_schema checks and a ledger table; no DROP/TRUNCATE; fresh install built from the vendor dump without secrets or business rows | Safe re-runs, auditable upgrades | database/README.md |
| 12 | Legacy debug/maintenance scripts retired (`unzipper.php`, `testExecel.php`, `check_permissions_debug.php`) | Web-reachable archive extraction and schema dumps are security risks and not features | git history |
| 13 | Security headers set centrally in `loader.php`; session cookie HttpOnly/SameSite/Secure | Baseline ASVS controls without touching every page | loader.php |
