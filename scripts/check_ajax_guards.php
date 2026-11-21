<?php
/**
 * Every ajax/** endpoint must check who is calling (require_login / require_permission / sl_gate /
 * cdp_loginCheck / an explicit owner or token check), except the short list of public endpoints below.
 * Run: php scripts/check_ajax_guards.php   (exit 1 and a list when an endpoint has no check)
 */
$root = dirname(__DIR__);
$public = [
    // reachable without an account on purpose: sign-up, password reset, public tracking, payment
    // provider callbacks (verified by signature or reference inside the file)
    'ajax/sign_up_ajax.php', 'ajax/forgot-password-ajax.php', 'ajax/check_tracking.php', 'ajax/check_email_ajax.php',
    'ajax/validate_track_number_courier.php', 'ajax/validate_track_number_consolidate.php', 'ajax/validate_track_number_customer_package.php',
    'ajax/validate_locker_virtual.php', 'ajax/gateway/', 'ajax/support/public_',
    // location pickers of the sign-up page (dataJs/sl/auth.js): reference data only, inputs bound
    'ajax/select2_countries.php', 'ajax/select2_states.php', 'ajax/select2_cities.php',
];
// The last alternative is the direct-request stop of a library that lives under ajax/ but is only
// ever included (ajax/notify_*/api_*_service*.php, ajax/reports/financial_sheet_sl.php):
//   if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(403); exit; }
$guards = '/require_login\s*\(|require_permission\s*\(|sl_gate\s*\(|cdp_loginCheck\s*\(|cdp_requireLogin|sl_ajaxRequire|require_once[^;]*_guard\.php[^;]*;\s*\w+\s*\(|ApiAuth::|hash_equals\s*\(|realpath\(\s*\$_SERVER\[\'SCRIPT_FILENAME\'\][^)]*\)\s*===\s*__FILE__/';
$missing = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/ajax', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->getExtension() !== 'php') continue;
    $rel = substr($f->getPathname(), strlen($root) + 1);
    foreach ($public as $p) if (str_starts_with($rel, $p)) continue 2;
    $src = file_get_contents($f->getPathname());
    if (!preg_match($guards, $src)) $missing[] = $rel;
}
sort($missing);
if ($missing) {
    echo count($missing) . " AJAX endpoint(s) with no login or permission check:\n  " . implode("\n  ", $missing) . "\n";
    exit(1);
}
echo "AJAX guard check passed: every endpoint checks the caller (or is on the public list).\n";
