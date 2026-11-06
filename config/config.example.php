<?php
/**
 * Swift Lane configuration. Copy this file to config/config.php and edit it.
 * config/config.php is ignored by git and must never be committed.
 */

// Database (MySQL 8.0+ / MariaDB 10.6+)
define('CDP_DB_HOST', '127.0.0.1');
define('CDP_DB_NAME', 'swiftlane');
define('CDP_DB_USER', 'swiftlane');
define('CDP_DB_PASS', 'change-me');

// Demo mode blocks every write so the UI can be shown safely. Keep false in production.
define('CDP_APP_MODE_DEMO', false);

// Public URL of the installation without a trailing slash (used in e-mails and payment returns).
define('CDP_APP_URL', 'https://example.com');

// Role based access for AJAX endpoints: 'off', 'audit' (log only) or 'enforce'.
define('CDP_RBAC_MODE', 'enforce');

// Finance module (billing hub, transactions, receivables). false hides the money pages.
define('CDP_FINANCE_ENABLED', true);

// Comma separated list of origins allowed to call /api/v1 from a browser. '*' allows all.
define('CDP_API_ALLOWED_ORIGINS', '*');

// Development only. Never enable on a public server.
define('CDP_DISPLAY_ERRORS', false);
// define('CDP_ERROR_LOG', __DIR__ . '/../logs/php-error.log');
