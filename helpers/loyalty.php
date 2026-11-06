<?php
/**
 * Loyalty points, referral codes and rewards (loyalty_rewards.php, customer portal).
 *
 * Tables: cdb_loyalty_accounts, cdb_loyalty_events, cdb_referrals, cdb_rewards
 * (migrations/2026_11_06_support_ai_loyalty.sql). Rules live in cdb_sl_settings
 * (loyalty_* and referral_* keys).
 *
 * Where to call it from the status-change path (not wired here, see the report):
 *   - ajax/courier/warehouse_deliver_ajax.php, courier_update_multiple_ajax.php and
 *     add_courier_delivered_ajax.php set cdb_add_order.status_courier = 8 (Delivered)
 *     or 15 (Picked up): after the UPDATE call sl_loyaltyOnDelivered('shipment', $orderId).
 *   - the sea package hand-out (customer_packages status 8/15): sl_loyaltyOnDelivered('package', $id).
 * Both calls are idempotent: one earn event per (kind, entity, customer).
 */
require_once __DIR__ . '/sl_view.php';

function sl_loyaltyEnabled(): bool
{
    return sl_tableExists('cdb_loyalty_accounts') && sl_tableExists('cdb_sl_settings');
}

/** Rules with defaults (keys of cdb_sl_settings). */
function sl_loyaltySettings(bool $fresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$fresh) {
        return $cache;
    }
    $cache = [
        'loyalty_enabled' => '1', 'loyalty_points_air' => '1', 'loyalty_points_sea' => '0.5',
        'loyalty_redeem_points' => '100', 'loyalty_redeem_value' => '10',
        'loyalty_tier_silver' => '1000', 'loyalty_tier_gold' => '3000',
        'referral_enabled' => '1', 'referral_friend_reward' => '20 off the first air shipment', 'referral_referrer_points' => '200',
    ];
    if (!sl_tableExists('cdb_sl_settings')) {
        return $cache;
    }
    foreach (sl_rows("SELECT setting_key, setting_value FROM cdb_sl_settings WHERE setting_key LIKE 'loyalty\\_%' OR setting_key LIKE 'referral\\_%'") as $r) {
        $cache[(string) $r->setting_key] = (string) $r->setting_value;
    }
    return $cache;
}

function sl_loyaltySettingSet(string $key, string $value, ?int $by = null): bool
{
    $db = new Conexion();
    $db->cdp_query('INSERT INTO cdb_sl_settings (setting_key, setting_value, updated_by, updated_at) VALUES (:k, :v, :u, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()');
    $db->bind(':k', $key);
    $db->bind(':v', $value);
    $db->bind(':u', $by);
    $ok = $db->cdp_execute() !== false;
    sl_loyaltySettings(true);
    return $ok;
}

function sl_loyaltyTierFor(int $lifetime): string
{
    $s = sl_loyaltySettings();
    if ($lifetime >= (int) $s['loyalty_tier_gold']) return 'gold';
    if ($lifetime >= (int) $s['loyalty_tier_silver']) return 'silver';
    return 'standard';
}

function sl_loyaltyTierLabel(string $tier): string
{
    return ['standard' => 'Standard', 'silver' => 'Silver', 'gold' => 'Gold', 'corporate' => 'Corporate'][$tier] ?? ucfirst($tier);
}

/** A unique referral code from the customer's name, e.g. AMA-SL7K2. */
function sl_loyaltyMakeCode(string $name): string
{
    $base = strtoupper(preg_replace('/[^A-Z]/', '', strtoupper(mb_substr(trim($name), 0, 6)))) ?: 'SL';
    for ($i = 0; $i < 20; $i++) {
        $code = $base . '-SL' . strtoupper(substr(str_replace(['0', 'O', 'I', '1'], '', bin2hex(random_bytes(4))), 0, 3)) . mt_rand(2, 9);
        if (!sl_scalar('SELECT COUNT(*) FROM cdb_loyalty_accounts WHERE referral_code = :c', [':c' => $code])) {
            return $code;
        }
    }
    return $base . '-SL' . time();
}

/** The customer's loyalty account (created on first use). */
function sl_loyaltyAccount(int $userId, bool $create = true)
{
    if ($userId <= 0 || !sl_loyaltyEnabled()) {
        return null;
    }
    $a = sl_row('SELECT * FROM cdb_loyalty_accounts WHERE user_id = :u', [':u' => $userId]);
    if ($a || !$create) {
        return $a;
    }
    $u = sl_row('SELECT fname, lname FROM cdb_users WHERE id = :u AND userlevel = 1', [':u' => $userId]);
    if (!$u) {
        return null;
    }
    $db = new Conexion();
    $db->cdp_query('INSERT IGNORE INTO cdb_loyalty_accounts (user_id, referral_code, created_at, updated_at) VALUES (:u, :c, NOW(), NOW())');
    $db->bind(':u', $userId);
    $db->bind(':c', sl_loyaltyMakeCode($u->fname ?: $u->lname));
    $db->cdp_execute();
    return sl_row('SELECT * FROM cdb_loyalty_accounts WHERE user_id = :u', [':u' => $userId]);
}

/**
 * Adds (or removes, negative) points. One event per (kind, entity, customer):
 * a second call for the same shipment is a no-op. Returns the event id or 0.
 */
function sl_loyaltyAward(int $userId, int $points, string $kind, ?string $entityType = null, ?int $entityId = null, string $note = '', ?int $by = null): int
{
    if ($points === 0 || !sl_loyaltyAccount($userId)) {
        return 0;
    }
    if ($entityType !== null && $entityId !== null) {
        $dup = (int) sl_scalar('SELECT id FROM cdb_loyalty_events WHERE kind = :k AND entity_type = :t AND entity_id = :e AND user_id = :u', [':k' => $kind, ':t' => $entityType, ':e' => $entityId, ':u' => $userId]);
        if ($dup) {
            return 0;
        }
    }
    $db = new Conexion();
    $db->cdp_query('INSERT INTO cdb_loyalty_events (user_id, kind, points, entity_type, entity_id, note, created_by, created_at) VALUES (:u, :k, :p, :t, :e, :n, :b, NOW())');
    $db->bind(':u', $userId); $db->bind(':k', $kind); $db->bind(':p', $points); $db->bind(':t', $entityType); $db->bind(':e', $entityId);
    $db->bind(':n', mb_substr($note, 0, 190)); $db->bind(':b', $by);
    if ($db->cdp_execute() === false) {
        return 0;
    }
    $id = (int) $db->cdp_lastInsertId();
    $db->cdp_query('UPDATE cdb_loyalty_accounts SET points_balance = points_balance + :p, lifetime_points = lifetime_points + :lp, updated_at = NOW() WHERE user_id = :u');
    $db->bind(':p', $points); $db->bind(':lp', max(0, $points)); $db->bind(':u', $userId);
    $db->cdp_execute();
    $acc = sl_loyaltyAccount($userId);
    if ($acc && $acc->tier !== 'corporate') {
        $tier = sl_loyaltyTierFor((int) $acc->lifetime_points);
        if ($tier !== $acc->tier) {
            $db->cdp_query('UPDATE cdb_loyalty_accounts SET tier = :t WHERE user_id = :u');
            $db->bind(':t', $tier); $db->bind(':u', $userId);
            $db->cdp_execute();
        }
    }
    return $id;
}

/**
 * Points for a delivered shipment ('shipment' = cdb_add_order, 'package' =
 * cdb_customers_packages). Call it from the status-change path after the row
 * reaches Delivered (8) or Picked up (15). Also settles a pending referral.
 */
function sl_loyaltyOnDelivered(string $type, int $id): int
{
    $s = sl_loyaltySettings();
    if ($s['loyalty_enabled'] !== '1' || !sl_loyaltyEnabled()) {
        return 0;
    }
    if ($type === 'shipment') {
        $r = sl_row('SELECT sender_id, total_order, order_id AS ref FROM cdb_add_order WHERE order_id = :i AND is_deleted = 0', [':i' => $id]);
        $rate = (float) $s['loyalty_points_air'];
    } else {
        $r = sl_row('SELECT sender_id, total_order, order_id AS ref FROM cdb_customers_packages WHERE order_id = :i', [':i' => $id]);
        $rate = (float) $s['loyalty_points_sea'];
    }
    if (!$r || (int) $r->sender_id <= 0) {
        return 0;
    }
    $points = (int) floor((float) $r->total_order * $rate);
    $uid = (int) $r->sender_id;
    $ev = $points > 0 ? sl_loyaltyAward($uid, $points, 'earn', $type, $id, ($type === 'shipment' ? 'Air shipment' : 'Sea package') . ' delivered') : 0;
    // The friend's first delivered shipment rewards the referrer once.
    if ($s['referral_enabled'] === '1' && sl_tableExists('cdb_referrals')) {
        $ref = sl_row("SELECT * FROM cdb_referrals WHERE referee_id = :u AND status <> 'rewarded'", [':u' => $uid]);
        if ($ref) {
            $pts = (int) $s['referral_referrer_points'];
            sl_loyaltyAward((int) $ref->referrer_id, $pts, 'referral', 'referral', (int) $ref->id, 'Referred customer shipped');
            $db = new Conexion();
            $db->cdp_query("UPDATE cdb_referrals SET status = 'rewarded', rewarded_at = NOW() WHERE id = :i");
            $db->bind(':i', (int) $ref->id);
            $db->cdp_execute();
        }
    }
    return $ev;
}

/** Redeems points for the configured value; returns the amount off or 0. */
function sl_loyaltyRedeem(int $userId, int $points, string $note = '', ?int $by = null): float
{
    $s = sl_loyaltySettings();
    $step = max(1, (int) $s['loyalty_redeem_points']);
    $points = (int) (floor($points / $step) * $step);
    $acc = sl_loyaltyAccount($userId);
    if ($points <= 0 || !$acc || (int) $acc->points_balance < $points) {
        return 0;
    }
    if (!sl_loyaltyAward($userId, -$points, 'redeem', null, null, $note ?: 'Points redeemed', $by)) {
        return 0;
    }
    return $points / $step * (float) $s['loyalty_redeem_value'];
}

/** Records that $refereeId signed up with $code. Returns the referrer id or 0. */
function sl_loyaltyLinkReferral(int $refereeId, string $code, string $channel = 'web'): int
{
    $code = strtoupper(trim($code));
    if ($code === '' || !sl_tableExists('cdb_referrals')) {
        return 0;
    }
    $acc = sl_row('SELECT user_id FROM cdb_loyalty_accounts WHERE referral_code = :c', [':c' => $code]);
    if (!$acc || (int) $acc->user_id === $refereeId) {
        return 0;
    }
    $db = new Conexion();
    $db->cdp_query('INSERT IGNORE INTO cdb_referrals (referrer_id, referee_id, code, status, channel, created_at) VALUES (:r, :e, :c, :s, :ch, NOW())');
    $db->bind(':r', (int) $acc->user_id); $db->bind(':e', $refereeId); $db->bind(':c', $code); $db->bind(':s', 'signed_up'); $db->bind(':ch', $channel);
    $db->cdp_execute();
    $db->cdp_query('UPDATE cdb_loyalty_accounts SET referred_by = :r WHERE user_id = :e AND referred_by IS NULL');
    $db->bind(':r', (int) $acc->user_id); $db->bind(':e', $refereeId);
    $db->cdp_execute();
    return (int) $acc->user_id;
}

/** What the customer portal shows: balance, tier, code, recent events. */
function sl_loyaltyForCustomer(int $userId): array
{
    $s = sl_loyaltySettings();
    $acc = sl_loyaltyAccount($userId);
    if (!$acc) {
        return ['enabled' => false];
    }
    $step = max(1, (int) $s['loyalty_redeem_points']);
    return [
        'enabled' => $s['loyalty_enabled'] === '1',
        'balance' => (int) $acc->points_balance, 'lifetime' => (int) $acc->lifetime_points,
        'tier' => sl_loyaltyTierLabel($acc->tier), 'code' => (string) $acc->referral_code,
        'worth' => sl_money(floor((int) $acc->points_balance / $step) * (float) $s['loyalty_redeem_value']),
        'next_tier' => $acc->tier === 'standard' ? ['Silver', (int) $s['loyalty_tier_silver']] : ($acc->tier === 'silver' ? ['Gold', (int) $s['loyalty_tier_gold']] : null),
        'events' => sl_rows('SELECT kind, points, note, created_at FROM cdb_loyalty_events WHERE user_id = :u ORDER BY id DESC LIMIT 10', [':u' => $userId]),
        'referrals' => (int) sl_scalar('SELECT COUNT(*) FROM cdb_referrals WHERE referrer_id = :u', [':u' => $userId]),
    ];
}
