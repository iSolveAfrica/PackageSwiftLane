# UI/UX audit — temp branch, 7 November 2026

Reviewed the design-system shell, application pages, record links, and customer
and driver navigation. Changes are in the local `temp` checkout; production was
not deployed or modified.

## Fixes

| Area | Result |
| --- | --- |
| Navigation | Usage and billing opens Message Costs. Settings cards open payment connections, AI configuration, and shipping defaults. My Activity passes the selected user correctly. Profile shortcuts respect destination permissions. |
| Customer support | Replaced the incompatible legacy jQuery widget in the new shell with a styled shortcut to the working support chat. Customer help opens customer support; unavailable staff support links are hidden for drivers. Removed the misleading mobile-app download shortcut. |
| Responsive layout | Fixed overflowing segments, consolidation search, label previews, customer charts, staff schedules, rider fees, email previews, support charts, tariffs, logs, developer alerts, infrastructure and issue boards. Wide document previews remain scrollable. |
| Hidden content | Hidden empty states, panels, and form sections stay hidden when their component styles specify flex/grid display. |
| Keyboard interaction | Menu buttons work by keyboard, expose their state, and keep offscreen navigation out of the focus order. Phone drawers and dialogs contain focus; Escape dismisses them and restores focus. Address tabs and password visibility work by keyboard. |
| Table actions | Row menus escape scrolling table/card containers and stay within the viewport. Overflowing tables can receive keyboard focus. |
| Saving | Pending forms/actions reject duplicate submissions. Failed switch saves restore visual, accessible, and submitted values. Field errors clear on retry and focus the invalid field. HTTP errors, unexpected HTML, and expired sessions cannot be reported as successful saves. |
| Controls and copy | API endpoint search filters real results. Theme changes work on pages that default to dark. Package/pickup remove controls explain when the last required row cannot be removed. Static indicators are not presented as active buttons. Integration setup gives a useful next step. Dashboard and import/consolidation copy describes the actual task. |

## Validation

All checks below used a separate local MariaDB database built from the shipped
fresh-install schema and development seed, with local admin/customer/driver
fixtures. No production credentials were used.

| Check | Result |
| --- | --- |
| Route registry and literal references | 131 route definitions and 864 templates/helpers checked; all referenced routes are mapped and their controllers exist. |
| Main-page browser sweep | 111 routes at 360, 390, 768, 1024 and 1440px: 555 layout checks. No page-level horizontal overflow, uncaught JavaScript errors, or displayed PHP warnings/fatals after fixes. Affected developer/configuration pages were rechecked after the last layout adjustment. |
| Page and record destinations | 307 URLs opened: 290 rendered successfully, 16 initiated expected downloads, and the shipment detail URL without its required ID returned its expected 404. Real shipment record URLs rendered successfully. No HTTP 5xx or displayed PHP warnings/fatals. |
| Role navigation | All 28 distinct customer-dashboard links and 14 driver-dashboard links checked after fixes; no login/permission-error destinations or dashboard JavaScript errors. |
| Real interactions | Package/pickup add/remove, endpoint search, settings destinations, unchanged profile save, dark-page theme changes, phone table menu, customer support shortcut and chat workspace passed in Chromium. |
| Shared runtime regression | `node scripts/check_design_runtime.cjs` passed using real application assets and isolated HTTP fixtures. Covers navigation, dialogs, menus, filters, tabs, theme, password visibility, rollback, validation, duplicate requests, CSRF, failed HTTP responses and expired sessions. |
| Syntax and structure | All 17 changed PHP files passed `php -l`; changed JavaScript passed syntax checks; `node scripts/check_page_shell.js`, `node scripts/check_design_routes.cjs` and `git diff --check` passed. |

The route and runtime checks are retained under `scripts/` and documented in
`DEV_GUIDE.md`. Local screenshots and the consolidated layout results are in
`dev_reports/ui-audit-2026-11-07/` (ignored development artifacts).

## Limits

Browser verification used Chromium with external requests blocked. Real payment,
email, SMS, WhatsApp and AI providers, externally hosted fonts, other browsers,
screen readers, production traffic volumes and every possible permission/data
combination were not validated. Destructive actions and real outbound sends were
not performed. The component catalogue intentionally contains demonstration
controls; unavailable AI image features remain explicitly disabled.
