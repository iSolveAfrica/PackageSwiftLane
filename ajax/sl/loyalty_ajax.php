<?php
/** Loyalty rules, manual point awards / redemptions and rewards (LoyaltyRewards page). */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../helpers/loyalty.php';
require_login();
require_permission('manage_loyalty');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
if (CDP_APP_MODE_DEMO === true) {
    sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
}
if (!sl_loyaltyEnabled()) {
    sl_json(['success' => false, 'message' => 'Loyalty tables are missing: run migrations/2026_11_06_support_ai_loyalty.sql'], 409);
}
$actor = (int) $_SESSION['userid'];
$op = (string) ($_POST['op'] ?? '');
$log = function ($action, $summary, $id = null) {
    cdp_activityLog(['module' => 'loyalty', 'action' => $action, 'action_label' => ucfirst(str_replace('_', ' ', $action)), 'verb' => 'update', 'outcome' => 'ok', 'summary' => $summary, 'entity_type' => 'customer', 'entity_id' => $id]);
};
switch ($op) {
    case 'settings':
        $numeric = ['loyalty_points_air', 'loyalty_points_sea', 'loyalty_redeem_points', 'loyalty_redeem_value', 'loyalty_tier_silver', 'loyalty_tier_gold', 'referral_referrer_points'];
        $errors = [];
        foreach ($numeric as $k) {
            if (isset($_POST[$k]) && (!is_numeric($_POST[$k]) || (float) $_POST[$k] < 0)) $errors[$k] = 'A number of 0 or more';
        }
        if ($errors) sl_json(['success' => false, 'message' => 'Check the highlighted fields', 'errors' => $errors], 422);
        foreach (array_merge($numeric, ['loyalty_enabled', 'referral_enabled', 'referral_friend_reward']) as $k) {
            if (array_key_exists($k, $_POST)) sl_loyaltySettingSet($k, mb_substr(trim((string) $_POST[$k]), 0, 190), $actor);
        }
        $log('settings', 'changed the loyalty rules');
        sl_json(['success' => true, 'message' => 'Loyalty rules saved']);
        break;
    case 'toggle': // data-sl-save switches: key loyalty_enabled / referral_enabled
        $k = (string) ($_POST['key'] ?? '');
        if (!in_array($k, ['loyalty_enabled', 'referral_enabled'], true)) sl_json(['success' => false, 'message' => 'Unknown setting'], 422);
        sl_loyaltySettingSet($k, !empty($_POST['value']) ? '1' : '0', $actor);
        $log('settings', "$k = " . (!empty($_POST['value']) ? 'on' : 'off'));
        sl_json(['success' => true, 'message' => 'Saved']);
        break;
    case 'award':
        $uid = (int) ($_POST['user_id'] ?? 0);
        $pts = (int) ($_POST['points'] ?? 0);
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 190);
        $u = sl_row('SELECT id, fname, lname FROM cdb_users WHERE id = :i AND userlevel = 1', [':i' => $uid]);
        if (!$u) sl_json(['success' => false, 'message' => 'Pick a customer', 'errors' => ['user_id' => 'Pick a customer']], 422);
        if ($pts === 0 || abs($pts) > 100000) sl_json(['success' => false, 'message' => 'Points must be non-zero', 'errors' => ['points' => 'Between -100,000 and 100,000']], 422);
        if ($note === '') sl_json(['success' => false, 'message' => 'Say why', 'errors' => ['note' => 'Required']], 422);
        $acc = sl_loyaltyAccount($uid);
        if ($pts < 0 && (int) $acc->points_balance + $pts < 0) sl_json(['success' => false, 'message' => 'The balance would go below zero', 'errors' => ['points' => 'Balance is ' . (int) $acc->points_balance]], 422);
        $ok = sl_loyaltyAward($uid, $pts, 'adjust', null, null, $note, $actor);
        $name = trim($u->fname . ' ' . $u->lname);
        if ($ok) $log('award', ($pts > 0 ? 'gave ' : 'took ') . abs($pts) . " points " . ($pts > 0 ? 'to ' : 'from ') . "$name ($note)", $uid);
        sl_json(['success' => (bool) $ok, 'message' => $ok ? "$name: " . ($pts > 0 ? '+' : '') . $pts . ' points' : 'Could not save']);
        break;
    case 'reward_save':
        $code = strtoupper(preg_replace('/[^A-Z0-9-]/i', '', (string) ($_POST['code'] ?? '')));
        $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 160);
        $errors = [];
        if ($code === '' || strlen($code) > 40) $errors['code'] = 'Letters, digits and dashes';
        if ($title === '') $errors['title'] = 'Required';
        if ((int) sl_scalar('SELECT COUNT(*) FROM cdb_rewards WHERE code = :c AND id <> :i', [':c' => $code, ':i' => (int) ($_POST['id'] ?? 0)])) $errors['code'] = 'Already used';
        if ($errors) sl_json(['success' => false, 'message' => 'Check the highlighted fields', 'errors' => $errors], 422);
        $db = new Conexion();
        $db->cdp_query('INSERT INTO cdb_rewards (code, title, kind, value_type, value_amount, points_cost, max_uses, active, ends_at, created_by, created_at, updated_at) VALUES (:c, :t, :k, :vt, :va, :pc, :mu, 1, :e, :b, NOW(), NOW())');
        $db->bind(':c', $code); $db->bind(':t', $title);
        $db->bind(':k', in_array($_POST['kind'] ?? '', ['coupon', 'voucher', 'birthday'], true) ? $_POST['kind'] : 'coupon');
        $db->bind(':vt', ($_POST['value_type'] ?? '') === 'percent' ? 'percent' : 'amount');
        $db->bind(':va', max(0, (float) ($_POST['value_amount'] ?? 0)));
        $db->bind(':pc', max(0, (int) ($_POST['points_cost'] ?? 0)));
        $db->bind(':mu', max(0, (int) ($_POST['max_uses'] ?? 0)));
        $db->bind(':e', preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['ends_at'] ?? '')) ? $_POST['ends_at'] : null);
        $db->bind(':b', $actor);
        $ok = $db->cdp_execute() !== false;
        if ($ok) $log('reward_save', "created reward $code");
        sl_json(['success' => $ok, 'message' => $ok ? "Reward $code created" : 'Could not save']);
        break;
    case 'reward_toggle':
        $db = new Conexion();
        $db->cdp_query('UPDATE cdb_rewards SET active = :a, updated_at = NOW() WHERE id = :i');
        $db->bind(':a', !empty($_POST['value']) ? 1 : 0); $db->bind(':i', (int) ($_POST['id'] ?? 0));
        $ok = $db->cdp_execute() !== false;
        $log('reward_toggle', 'reward #' . (int) ($_POST['id'] ?? 0) . (!empty($_POST['value']) ? ' on' : ' off'));
        sl_json(['success' => $ok, 'message' => $ok ? 'Saved' : 'Could not save']);
        break;
    case 'backfill': // points for delivered shipments of the last N days that have none yet (idempotent)
        $days = max(1, min(365, (int) ($_POST['days'] ?? 30)));
        $n = 0;
        foreach (sl_rows('SELECT order_id FROM cdb_add_order WHERE is_deleted = 0 AND status_courier IN (8,15) AND order_date >= CURDATE() - INTERVAL ' . $days . ' DAY LIMIT 2000') as $r) {
            if (sl_loyaltyOnDelivered('shipment', (int) $r->order_id)) $n++;
        }
        $log('backfill', "awarded points for $n delivered shipments (last $days days)");
        sl_json(['success' => true, 'message' => "Points awarded for $n delivered shipments", 'reload' => true]);
        break;
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
