<?php
/** API Access: the signed-in user's own REST API keys. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['use_api_tokens', 'manage_api_clients', 'manage_api_settings']);
require_once 'api/v1/ApiAuth.php';
new ApiAuth(); // ensures cdb_api_tokens exists
$uid = (int) $_SESSION['userid'];
$keys = [];
foreach (sl_rows('SELECT id, name, expires_at, last_used_at, created_at, revoked_at FROM cdb_api_tokens WHERE user_id = :u ORDER BY id DESC LIMIT 30', [':u' => $uid]) as $k) {
    $state = $k->revoked_at ? ['Revoked', 'bad'] : (strtotime($k->expires_at) < time() ? ['Expired', 'warn'] : (strtotime($k->expires_at) < time() + 7 * 86400 ? ['Expiring', 'warn'] : ['Active', 'ok']));
    $calls = sl_tableExists('cdb_api_request_log') ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_api_request_log WHERE user_id = :u AND created_at >= :c', [':u' => $uid, ':c' => $k->created_at]) : 0;
    $keys[] = ['id' => (int) $k->id, 'name' => $k->name ?: 'Unnamed key', 'state' => $state[0], 'cls' => $state[1], 'mask' => 'sl_••••' . str_pad((string) $k->id, 4, '0', STR_PAD_LEFT), 'used' => $k->last_used_at ? sl_ago($k->last_used_at) : 'never',
        'expires' => sl_date($k->expires_at), 'calls' => sl_num($calls) . ' calls', 'active' => $state[0] !== 'Revoked' && $state[0] !== 'Expired'];
}
$active = count(array_filter($keys, fn($k) => $k['active']));
$month = sl_tableExists('cdb_api_request_log') ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_api_request_log WHERE user_id = :u AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')", [':u' => $uid]) : 0;
$peak = sl_tableExists('cdb_api_request_log') ? (int) sl_scalar("SELECT MAX(n) FROM (SELECT COUNT(*) n FROM cdb_api_request_log WHERE user_id = :u AND created_at >= CURDATE() GROUP BY DATE_FORMAT(created_at, '%H%i')) x", [':u' => $uid]) : 0;
$lang = (string) ($_GET['lang'] ?? 'curl');
$base = rtrim((string) (cdp_getSettingsCourier()->site_url ?? ''), '/') ?: ('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
sl_render('ApiAccess', ['id' => 'apis', 'title' => 'API Access', 'board' => 'ApiAccess'], [
    'keys' => $keys, 'keyCount' => $active, 'max' => ApiAuth::MAX_TOKENS_PER_USER, 'keyW' => 'width:' . sl_pct($active, ApiAuth::MAX_TOKENS_PER_USER) . '%', 'month' => $month, 'peak' => $peak,
    'owner' => trim(($userData->fname ?? '') . ' ' . ($userData->lname ?? '')), 'locker' => $userData->locker ?? '', 'base' => $base . '/api/v1', 'lang' => $lang,
    'langs' => [['k' => 'curl', 'label' => 'cURL'], ['k' => 'js', 'label' => 'JavaScript'], ['k' => 'php', 'label' => 'PHP']],
]);
