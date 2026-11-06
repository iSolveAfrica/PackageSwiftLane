<?php
/**
 * Swift Lane application bootstrap (readable replacement for the former
 * ionCube-encoded loader.php).
 *
 * Every page, AJAX endpoint and cron script starts with `require_once "loader.php"`.
 * This file:
 *   1. loads config/config.php (database constants, app mode, feature switches),
 *   2. starts the PHP session,
 *   3. loads the core classes (Conexion, Core, User, OtpService),
 *   4. loads the translation array, the shared helper libraries and the
 *      procedural data layer,
 * and leaves the globals the application expects behind:
 *   $db, $settings, $lang, $config_lang, $direction_layout.
 *
 * No licence or source-encoding check is performed. See docs/handover/ENCODED_MODULES.md.
 */

if (!defined('SWIFTLANE_LOADER_LOADED')) {
    define('SWIFTLANE_LOADER_LOADED', true);
}

define('CDP_ROOT', __DIR__);

// ── 1. Configuration ───────────────────────────────────────────────────────────
$cdpConfigFile = CDP_ROOT . '/config/config.php';
if (!is_file($cdpConfigFile)) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Missing config/config.php. Copy config/config.example.php to config/config.php and fill in the database settings.\n");
        exit(1);
    }
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Swift Lane is not configured yet. Copy config/config.example.php to config/config.php and fill in the database settings.";
    exit;
}
require_once $cdpConfigFile;

foreach (['CDP_DB_HOST', 'CDP_DB_NAME', 'CDP_DB_USER', 'CDP_DB_PASS'] as $cdpConst) {
    if (!defined($cdpConst)) {
        http_response_code(503);
        echo "config/config.php must define {$cdpConst}.";
        exit;
    }
}
if (!defined('CDP_APP_MODE_DEMO')) {
    define('CDP_APP_MODE_DEMO', false);
}
unset($cdpConfigFile, $cdpConst);

// ── 2. PHP runtime defaults ────────────────────────────────────────────────────
if (defined('CDP_DISPLAY_ERRORS') && CDP_DISPLAY_ERRORS) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
    ini_set('display_errors', '0');
}
if (defined('CDP_ERROR_LOG') && CDP_ERROR_LOG) {
    ini_set('log_errors', '1');
    ini_set('error_log', CDP_ERROR_LOG);
}

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $cdpSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $cdpSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name(defined('CDP_SESSION_NAME') ? CDP_SESSION_NAME : 'swiftlane_session');
    session_start();
    unset($cdpSecure);
}

// ── 3. Core classes ────────────────────────────────────────────────────────────
require_once CDP_ROOT . '/lib/Conexion.php';
require_once CDP_ROOT . '/lib/Core.php';
require_once CDP_ROOT . '/lib/User.php';
require_once CDP_ROOT . '/lib/OtpService.php';   // also loads helpers/querys.php and helpers/functions.php

// ── 4. Settings row, language and shared helpers ───────────────────────────────
require_once CDP_ROOT . '/helpers/config.lang.php';   // $db, $settings, $lang, $config_lang, $direction_layout
if (!isset($lang) || !is_array($lang)) {
    $lang = [];
    include CDP_ROOT . '/helpers/languages/en.php';
    $config_lang = 'en';
    $direction_layout = 'ltr';
}
require_once CDP_ROOT . '/helpers/autoload_lang.php';
require_once CDP_ROOT . '/helpers/functions.php';
require_once CDP_ROOT . '/helpers/function_exist.php';
require_once CDP_ROOT . '/helpers/querys.php';
