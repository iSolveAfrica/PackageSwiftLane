<?php
/** Exercise the actual auth controllers with isolated adapters; no database or delivery. */
if (($argv[1] ?? '') === '--case') {
    $scenario = $argv[2];
    chdir($argv[3]);
    session_start();
    $_SESSION['csrf_token'] = str_repeat('a', 64);
    $_SESSION['forgot_reset_token'] = 'isolated-reset-session';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['_csrf_token' => $_SESSION['csrf_token']];
    $GLOBALS['auth_calls'] = $GLOBALS['writes'] = $GLOBALS['consumes'] = 0;
    set_error_handler(function ($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) return false;
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    ob_start();
    if (strpos($scenario, 'ajax_') === 0) {
        $signup = strpos($scenario, 'ajax_signup_') === 0;
        $_POST += array_fill_keys(['terms', 'country', 'state', 'city', 'address', 'postal', 'username', 'email', 'phone', 'fname', 'lname', 'document_number', 'document_type', 'pass', 'pass2'], 'test');
        if (strpos($scenario, 'missing_token') !== false) unset($_POST['_csrf_token']);
        if (strpos($scenario, '_array') !== false) $_POST[$signup ? 'username' : 'email'] = ['invalid'];
        register_shutdown_function(function () use ($scenario) {
            $data = json_decode(ob_get_clean(), true);
            $blockedToken = strpos($scenario, 'missing_token') !== false;
            $ok = is_array($data) && $data['success'] === false
                && (!$blockedToken || http_response_code() === 403)
                && $GLOBALS['writes'] === 0 && $GLOBALS['consumes'] === 0;
            echo json_encode(['case' => $scenario, 'passed' => $ok]);
            if (!$ok) exit(1);
        });
        chdir('ajax');
        include $signup ? 'sign_up_ajax.php' : 'forgot-password-ajax.php';
        exit;
    } elseif (strpos($scenario, 'login_') === 0) {
        $_POST += ['login' => '1', 'username' => '<script>alert(1)</script>', 'password' => 'a password', 'remember_me' => '1'];
        if ($scenario === 'login_missing_token') unset($_POST['_csrf_token']);
        if ($scenario === 'login_array') $_POST['password'] = ['invalid'];
        include 'login.php';
        $html = ob_get_clean();
        $ok = $GLOBALS['auth_calls'] === ($scenario === 'login_valid_error' ? 1 : 0)
            && strpos($html, 'value="&lt;script&gt;alert(1)&lt;/script&gt;"') !== false
            && strpos($html, 'value="a password"') === false;
    } else {
        $_POST += ['password' => 'Valid-password1', 'password_confirm' => 'Valid-password1'];
        if ($scenario === 'reset_missing_token') unset($_POST['_csrf_token']);
        if ($scenario === 'reset_bad_token') $_POST['_csrf_token'] = 'incorrect';
        if ($scenario === 'reset_array') $_POST['password'] = ['invalid'];
        if ($scenario === 'reset_short') $_POST['password'] = $_POST['password_confirm'] = 'short';
        if ($scenario === 'reset_mismatch') $_POST['password_confirm'] = 'different';
        require 'loader.php';
        include 'views/auth/forgot-reset.php';
        ob_end_clean();
        $valid = $scenario === 'reset_valid';
        $ok = $GLOBALS['consumes'] === (int) $valid && $GLOBALS['writes'] === (int) $valid;
        if ($valid) {
            $ok = $ok && !isset($_SESSION['forgot_reset_token'])
                && password_verify('Valid-password1', $GLOBALS['bound'][':password'] ?? '');
        }
    }
    echo json_encode(['case' => $scenario, 'passed' => $ok]);
    exit($ok ? 0 : 1);
}

$root = dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/swiftlane-auth-test-' . bin2hex(random_bytes(6));
foreach (['helpers', 'lib', 'views/inc', 'views/auth', 'ajax'] as $directory) mkdir($fixture . '/' . $directory, 0700, true);
$sources = ['login.php', 'views/auth/forgot-reset.php', 'ajax/sign_up_ajax.php', 'ajax/forgot-password-ajax.php', 'helpers/csrf.php', 'helpers/asset.php', 'views/inc/auth_head.php', 'views/inc/auth_chrome.php'];
foreach ($sources as $source) copy($root . '/' . $source, $fixture . '/' . $source);
file_put_contents($fixture . '/loader.php', <<<'PHP'
<?php
class Core {
    public $site_name = 'Isolated test';
    public $favicon = '';
    public $logo_web = '';
}
class User {
    public $errors = [];
    public function cdp_loginCheck() { return false; }
    public function cdp_login(...$arguments) {
        $GLOBALS['auth_calls']++;
        $this->errors = ['Invalid credentials'];
        return false;
    }
}
class Conexion {
    public function cdp_query($query) {}
    public function bind($key, $value) { $GLOBALS['bound'][$key] = $value; }
    public function cdp_execute() { $GLOBALS['writes']++; }
}
$lang = array_fill_keys(['message_title_login0', 'left115', 'left116', 'left117', 'left118', 'left119', 'left120', 'left121'], 'Test label');
PHP
);
file_put_contents($fixture . '/lib/OtpService.php', <<<'PHP'
<?php
class OtpService {
    public function consumeResetSession($token) { $GLOBALS['consumes']++; return 41; }
    public function revokeAllTrustedDevices($id) {}
}
PHP
);
file_put_contents($fixture . '/helpers/activity_log.php', '<?php function cdp_activityLogLogin(...$arguments) {}');
file_put_contents($fixture . '/helpers/querys.php', '<?php');
$scenarios = ['login_missing_token', 'login_array', 'login_valid_error', 'reset_missing_token', 'reset_bad_token', 'reset_array', 'reset_short', 'reset_mismatch', 'reset_valid', 'ajax_signup_missing_token', 'ajax_forgot_missing_token', 'ajax_signup_array', 'ajax_forgot_array'];
$failures = 0;
try {
    foreach ($scenarios as $scenario) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, __FILE__, '--case', $scenario, $fixture], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
        $status = proc_close($process);
        if ($status !== 0) $failures++;
        echo ($status === 0 ? 'PASS ' : 'FAIL ') . $scenario . ($error ? ': ' . trim($error) : '') . PHP_EOL;
    }
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    rmdir($fixture);
}
exit($failures ? 1 : 0);
