<?php
/**
 * PackageSwiftLane REST API – v1 Front Controller
 *
 * Entry point for all requests to /api/v1/*
 *
 * Routing table
 * ─────────────────────────────────────────────────────────────
 * POST   auth/login                 AuthHandler::login
 * POST   auth/logout                AuthHandler::logout
 * GET    auth/me                    AuthHandler::me
 *
 * GET    shipments                  ShipmentsHandler::index
 * POST   shipments                  ShipmentsHandler::create
 * GET    shipments/{id}             ShipmentsHandler::show
 * PATCH  shipments/{id}/status      ShipmentsHandler::updateStatus
 * DELETE shipments/{id}             ShipmentsHandler::delete
 *
 * GET    customers                  CustomersHandler::index
 * POST   customers                  CustomersHandler::create
 * GET    customers/{id}             CustomersHandler::show
 * PUT    customers/{id}             CustomersHandler::update
 * GET    customers/{id}/addresses   CustomersHandler::addresses
 *
 * GET    recipients                 RecipientsHandler::index
 * POST   recipients                 RecipientsHandler::create
 * GET    recipients/{id}            RecipientsHandler::show
 * PUT    recipients/{id}            RecipientsHandler::update
 * GET    recipients/{id}/addresses  RecipientsHandler::addresses
 *
 * GET    tracking/{order_no}        TrackingHandler::show  (public)
 *
 * GET    pre-alerts                 PreAlertsHandler::index
 * POST   pre-alerts                 PreAlertsHandler::create
 * GET    pre-alerts/{id}            PreAlertsHandler::show
 * DELETE pre-alerts/{id}            PreAlertsHandler::delete
 *
 * GET    references/countries       ReferencesHandler::countries
 * GET    references/states          ReferencesHandler::states
 * GET    references/cities          ReferencesHandler::cities
 * GET    references/couriers        ReferencesHandler::couriers
 * GET    references/statuses        ReferencesHandler::statuses
 * GET    references/packaging       ReferencesHandler::packaging
 * GET    references/shipping-modes  ReferencesHandler::shippingModes
 * GET    references/delivery-times  ReferencesHandler::deliveryTimes
 * GET    references/categories      ReferencesHandler::categories
 * GET    references/offices         ReferencesHandler::offices
 * GET    references/branches        ReferencesHandler::branches
 * GET    references/payment-methods ReferencesHandler::paymentMethods
 *
 * GET    notifications              NotificationsHandler::index
 * PATCH  notifications/{id}/read    NotificationsHandler::markRead
 * PATCH  notifications/read-all     NotificationsHandler::markAllRead
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/ApiResponse.php';
require_once __DIR__ . '/ApiValidator.php';
require_once __DIR__ . '/ApiAuth.php';

// ── Handler includes ─────────────────────────────────────────────────────────
require_once __DIR__ . '/handlers/AuthHandler.php';
require_once __DIR__ . '/handlers/ShipmentsHandler.php';
require_once __DIR__ . '/handlers/CustomersHandler.php';
require_once __DIR__ . '/handlers/RecipientsHandler.php';
require_once __DIR__ . '/handlers/TrackingHandler.php';
require_once __DIR__ . '/handlers/PreAlertsHandler.php';
require_once __DIR__ . '/handlers/ReferencesHandler.php';
require_once __DIR__ . '/handlers/NotificationsHandler.php';

// ── Request log (cdb_api_request_log, one lean row per request) ─────────────
$GLOBALS['cdp_api_t0'] = microtime(true);
register_shutdown_function(function () {
    try {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $db = new Conexion();
        $db->cdp_query("SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_api_request_log'");
        $row = $db->cdp_registro();
        if (!$row || (int) $row->n === 0) {
            return;
        }
        $db->cdp_query('INSERT INTO cdb_api_request_log (created_at, direction, method, path, status, duration_ms, user_id, ip, user_agent) VALUES (NOW(), :d, :m, :p, :s, :ms, :u, :ip, :ua)');
        $db->bind(':d', 'in');
        $db->bind(':m', substr((string) ($_SERVER['REQUEST_METHOD'] ?? ''), 0, 8));
        // Path only: query strings can carry tokens or personal data.
        $db->bind(':p', substr(preg_replace('#^.*?/api/v1#', '/api/v1', $path), 0, 190));
        $db->bind(':s', (int) http_response_code());
        $db->bind(':ms', (int) round((microtime(true) - $GLOBALS['cdp_api_t0']) * 1000));
        $db->bind(':u', isset($_SESSION['userid']) ? (int) $_SESSION['userid'] : null);
        $db->bind(':ip', substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45));
        $db->bind(':ua', substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120));
        $db->cdp_execute();
    } catch (Throwable $e) {
        // logging never breaks the API
    }
});

// ── Route resolution ──────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];

// Strip query string and decode URI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove script prefix so routes are relative to /api/v1/
// e.g. /api/v1/shipments/42 → shipments/42
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($scriptDir !== '' && strpos($uri, $scriptDir) === 0) {
    $uri = substr($uri, strlen($scriptDir));
}
$uri = trim($uri, '/');          // "shipments/42"
$segments = $uri === '' ? [] : explode('/', $uri);

$s0 = $segments[0] ?? '';        // first path segment
$s1 = $segments[1] ?? '';        // id / sub-resource
$s2 = $segments[2] ?? '';        // sub-action (e.g. "status", "addresses", "read")

// ── Global exception safety net ──────────────────────────────────────────────
set_exception_handler(function (Throwable $e) {
    ApiResponse::serverError('An unexpected error occurred.');
});

// ── Route dispatch ────────────────────────────────────────────────────────────
try {
    switch ($s0) {

        // ── auth ─────────────────────────────────────────────────────────────
        case 'auth':
            $handler = new AuthHandler();
            switch (true) {
                case $method === 'POST' && $s1 === 'login':
                    $handler->login();
                    break;
                case $method === 'POST' && $s1 === 'logout':
                    $handler->logout();
                    break;
                case $method === 'GET' && $s1 === 'me':
                    $handler->me();
                    break;
                default:
                    ApiResponse::notFound("Unknown auth endpoint: {$s1}");
            }
            break;

        // ── shipments ────────────────────────────────────────────────────────
        case 'shipments':
            $handler = new ShipmentsHandler();
            switch (true) {
                case $method === 'GET'    && $s1 === '':
                    $handler->index();
                    break;
                case $method === 'POST'   && $s1 === '':
                    $handler->create();
                    break;
                case $method === 'GET'    && $s1 !== '' && $s2 === '':
                    $handler->show((int)$s1);
                    break;
                case $method === 'PATCH'  && $s1 !== '' && $s2 === 'status':
                    $handler->updateStatus((int)$s1);
                    break;
                case $method === 'DELETE' && $s1 !== '' && $s2 === '':
                    $handler->delete((int)$s1);
                    break;
                default:
                    ApiResponse::methodNotAllowed($method);
            }
            break;

        // ── customers ────────────────────────────────────────────────────────
        case 'customers':
            $handler = new CustomersHandler();
            switch (true) {
                case $method === 'GET'  && $s1 === '':
                    $handler->index();
                    break;
                case $method === 'POST' && $s1 === '':
                    $handler->create();
                    break;
                case $method === 'GET'  && $s1 !== '' && $s2 === '':
                    $handler->show((int)$s1);
                    break;
                case $method === 'PUT'  && $s1 !== '' && $s2 === '':
                    $handler->update((int)$s1);
                    break;
                case $method === 'GET'  && $s1 !== '' && $s2 === 'addresses':
                    $handler->addresses((int)$s1);
                    break;
                default:
                    ApiResponse::methodNotAllowed($method);
            }
            break;

        // ── recipients ───────────────────────────────────────────────────────
        case 'recipients':
            $handler = new RecipientsHandler();
            switch (true) {
                case $method === 'GET'  && $s1 === '':
                    $handler->index();
                    break;
                case $method === 'POST' && $s1 === '':
                    $handler->create();
                    break;
                case $method === 'GET'  && $s1 !== '' && $s2 === '':
                    $handler->show((int)$s1);
                    break;
                case $method === 'PUT'  && $s1 !== '' && $s2 === '':
                    $handler->update((int)$s1);
                    break;
                case $method === 'GET'  && $s1 !== '' && $s2 === 'addresses':
                    $handler->addresses((int)$s1);
                    break;
                default:
                    ApiResponse::methodNotAllowed($method);
            }
            break;

        // ── tracking (public) ────────────────────────────────────────────────
        case 'tracking':
            $handler = new TrackingHandler();
            if ($method === 'GET' && $s1 !== '') {
                $handler->show(urldecode($s1));
            } else {
                ApiResponse::methodNotAllowed($method);
            }
            break;

        // ── pre-alerts ───────────────────────────────────────────────────────
        case 'pre-alerts':
            $handler = new PreAlertsHandler();
            switch (true) {
                case $method === 'GET'    && $s1 === '':
                    $handler->index();
                    break;
                case $method === 'POST'   && $s1 === '':
                    $handler->create();
                    break;
                case $method === 'GET'    && $s1 !== '' && $s2 === '':
                    $handler->show((int)$s1);
                    break;
                case $method === 'DELETE' && $s1 !== '' && $s2 === '':
                    $handler->delete((int)$s1);
                    break;
                default:
                    ApiResponse::methodNotAllowed($method);
            }
            break;

        // ── references ───────────────────────────────────────────────────────
        case 'references':
            $handler = new ReferencesHandler();
            if ($method !== 'GET') {
                ApiResponse::methodNotAllowed($method);
            }
            switch ($s1) {
                case 'countries':       $handler->countries();       break;
                case 'states':          $handler->states();          break;
                case 'cities':          $handler->cities();          break;
                case 'couriers':        $handler->couriers();        break;
                case 'statuses':        $handler->statuses();        break;
                case 'packaging':       $handler->packaging();       break;
                case 'shipping-modes':  $handler->shippingModes();   break;
                case 'delivery-times':  $handler->deliveryTimes();   break;
                case 'categories':      $handler->categories();      break;
                case 'offices':         $handler->offices();         break;
                case 'branches':        $handler->branches();        break;
                case 'payment-methods': $handler->paymentMethods();  break;
                default:
                    ApiResponse::notFound("Unknown reference: {$s1}");
            }
            break;

        // ── notifications ────────────────────────────────────────────────────
        case 'notifications':
            $handler = new NotificationsHandler();
            switch (true) {
                case $method === 'GET'   && $s1 === '':
                    $handler->index();
                    break;
                case $method === 'PATCH' && $s1 === 'read-all' && $s2 === '':
                    $handler->markAllRead();
                    break;
                case $method === 'PATCH' && $s1 !== '' && $s2 === 'read':
                    $handler->markRead((int)$s1);
                    break;
                default:
                    ApiResponse::methodNotAllowed($method);
            }
            break;

        // ── root info ─────────────────────────────────────────────────────────
        case '':
            if ($method === 'GET') {
                ApiResponse::success([
                    'name'    => 'PackageSwiftLane REST API',
                    'version' => 'v1',
                    'docs'    => 'See /api/v1/docs/openapi.yaml',
                ]);
            }
            ApiResponse::methodNotAllowed($method);
            break;

        // ── 404 ──────────────────────────────────────────────────────────────
        default:
            ApiResponse::notFound("Endpoint not found: {$uri}");
    }
} catch (Throwable $e) {
    // Log internally; never expose stack traces in production
    error_log('[API] Uncaught: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    ApiResponse::serverError('An unexpected server error occurred.');
}
