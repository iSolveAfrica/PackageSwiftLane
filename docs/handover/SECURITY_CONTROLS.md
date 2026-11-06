# Security controls (OWASP ASVS 4.0.3, selected Level 2 controls)

Scope: the web application in this repository. ISO/IEC 27001 alignment is organisational; this document provides technical evidence only and does not establish certification.

| Area (ASVS) | Control | Implementation | Evidence |
|---|---|---|---|
| V2 Authentication | Password hashing | bcrypt (`password_hash`), unchanged `cdp_login` | lib/User.php |
| V2 | Optional OTP by e-mail/WhatsApp, trusted devices | lib/OtpService.php; OTP templates 27/28/30 seeded if missing | migrations/2026_11_06_legacy_drift.sql |
| V2 | Password rotation (configurable) and admin-forced reset | `sl_passwordRotationGuard()` on every gated page; `force_password_reset` flag | helpers/sl_auth.php, helpers/sl_view.php |
| V3 Session | HttpOnly, SameSite=Lax, Secure on HTTPS; custom session name | loader.php | loader.php |
| V4 Access control | Server-side permission on every page (`sl_gate`) and endpoint (`require_permission`); agency and customer record scoping; rank rules for role assignment | helpers/sl_view.php, helpers/ajax_guard.php, helpers/rbac.php | cross-customer access returned 404/403 in tests |
| V5 Validation / encoding | Output escaped with `h()`; bound SQL parameters in all new code | helpers/sl_view.php | fixed: legacy select2 lookups now require login and cast ids to integers |
| V8 Data protection | API keys stored as SHA-256 hash; provider secrets masked, never echoed | ajax/sl/*, api/v1/ApiAuth.php | |
| V12 Files | Uploads type-checked (finfo/getimagesize), size-capped, random names; `driver_files/` denied over HTTP | ajax/sl/*, driver_files/.htaccess | |
| V13 API | Token auth, per-request log without bodies/tokens | api/v1/index.php, cdb_api_request_log | |
| V14 Config | Security headers (nosniff, frame-options, referrer, permissions, HSTS on HTTPS); secrets only in config/config.php (gitignored); demo mode blocks writes | loader.php, config/config.example.php | |
| Audit | Every new write calls `cdp_activityLog` (actor, action, target, time, outcome) | helpers/activity_log.php | Activity Logs page |
| CSRF | Token on all POST forms and AJAX (meta + header); added to auth-otp verify/resend | helpers/csrf.php, assets/swiftlane/swiftlane.js | |

## Removed attack surface
`unzipper.php` (web archive extraction), `testExecel.php`, `check_permissions_debug.php`, the ionCube licence/update client, the encoded web installer.

## Residual risks and required actions
1. `report_*_excel.php` / `_print.php` legacy exports check login but not permission. Add `cdp_hasPermission('view_general_reports')` to each.
2. Legacy AJAX endpoints may print PHP warnings into JSON when `display_errors` is on — set `CDP_DISPLAY_ERRORS` false and `CDP_ERROR_LOG` in production.
3. `CDP_API_ALLOWED_ORIGINS` defaults to `*`; restrict to the real front-end origins.
4. Notification-centre rules and customer notification preferences are stored but not yet enforced by the message senders (`sl_msgRuleEnabled`, `sl_notifAllowed` to be called in helpers/push_notify.php and the notify_* services).
5. WhatsApp/Slack "test connection" fetch an admin-entered URL server-side (gated by `manage_api_settings`); consider an allow-list.
6. The vendor dump `install.done/swiftlane_database.sql` contains a real SMTP password in `cdb_settings`; rotate that password and remove the file from production servers. The generated `database/fresh_install_schema.sql` blanks all secrets.
7. OTP is off by default in the seed; enable it in Settings for staff accounts.
