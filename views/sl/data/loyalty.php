<?php
/** Data for LoyaltyRewards.dc.html (loyalty_rewards.php) and the customer's own balance. */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/loyalty.php';

function sl_loyaltyData($user, $core): array
{
    $d = ['ready' => sl_loyaltyEnabled(), 'settings' => sl_loyaltySettings(true), 'kpis' => [], 'refs' => [], 'tiers' => [], 'rewards' => [], 'events' => [], 'cov' => []];
    if (!$d['ready']) {
        return $d;
    }
    $s = $d['settings'];
    $step = max(1, (int) $s['loyalty_redeem_points']);
    $customers = (int) sl_scalar('SELECT COUNT(*) FROM cdb_users WHERE userlevel = 1');
    $members = (int) sl_scalar('SELECT COUNT(*) FROM cdb_loyalty_accounts');
    $refM = sl_row("SELECT COUNT(*) AS n, SUM(status = 'rewarded') AS r FROM cdb_referrals WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    $issued = (int) sl_scalar("SELECT COALESCE(SUM(points),0) FROM cdb_loyalty_events WHERE points > 0");
    $redeemed = (int) -sl_scalar("SELECT COALESCE(SUM(points),0) FROM cdb_loyalty_events WHERE kind = 'redeem'");
    $used = (int) sl_scalar('SELECT COALESCE(SUM(used_count),0) FROM cdb_rewards');
    $act = (int) sl_scalar("SELECT COUNT(DISTINCT sender_id) FROM cdb_add_order WHERE is_deleted = 0 AND order_date >= CURDATE() - INTERVAL 90 DAY AND sender_id IN (SELECT user_id FROM cdb_loyalty_accounts)");
    $spark = function (string $where) {
        $map = [];
        foreach (sl_rows("SELECT DATE(created_at) AS d, COUNT(*) AS n FROM cdb_loyalty_events WHERE $where AND created_at >= CURDATE() - INTERVAL 11 DAY GROUP BY d") as $r) $map[$r->d] = (int) $r->n;
        $max = max(1, $map ? max($map) : 1);
        $o = [];
        for ($i = 11; $i >= 0; $i--) { $day = date('Y-m-d', strtotime("-$i days")); $o[] = $map[$day] ?? 0; }
        return sl_sparkBars($o, 8);
    };
    $d['kpis'] = [
        ['icon' => 'i-users', 'l' => 'Members', 'v' => sl_num($members), 's' => sl_pct($members, $customers) . '% of customers', 'sp' => $spark("kind = 'earn'")],
        ['icon' => 'i-link', 'l' => 'Referrals this month', 'v' => sl_num($refM->n ?? 0), 's' => sl_num($refM->r ?? 0) . ' shipped and rewarded', 'sp' => $spark("kind = 'referral'")],
        ['icon' => 'i-star', 'l' => 'Points issued', 'v' => sl_num($issued), 's' => sl_money(floor($issued / $step) * (float) $s['loyalty_redeem_value']) . ' value · ' . sl_pct($redeemed, $issued) . '% redeemed', 'sp' => $spark('points > 0')],
        ['icon' => 'i-tag', 'l' => 'Rewards used', 'v' => sl_num($used), 's' => (int) sl_scalar('SELECT COUNT(*) FROM cdb_rewards WHERE active = 1') . ' active coupons', 'sp' => $spark("kind = 'redeem'")],
        ['icon' => 'i-refresh', 'l' => 'Active (90 d)', 'v' => sl_pct($act, $members) . '%', 's' => 'members who shipped', 'sp' => $spark('1=1')],
    ];
    $d['refs'] = array_map(fn($r) => ['id' => (int) $r->user_id, 'n' => trim($r->fname . ' ' . $r->lname), 'ini' => sl_initials($r->fname . ' ' . $r->lname), 's' => (int) $r->n . ' friend' . ((int) $r->n === 1 ? '' : 's') . ' · ' . (int) $r->shipped . ' shipped', 'v' => sl_num($r->pts) . ' pts', 'code' => $r->referral_code],
        sl_rows("SELECT a.user_id, a.referral_code, u.fname, u.lname, COUNT(r.id) AS n, SUM(r.status = 'rewarded') AS shipped, COALESCE((SELECT SUM(points) FROM cdb_loyalty_events e WHERE e.user_id = a.user_id AND e.kind = 'referral'),0) AS pts FROM cdb_loyalty_accounts a JOIN cdb_users u ON u.id = a.user_id JOIN cdb_referrals r ON r.referrer_id = a.user_id GROUP BY a.user_id ORDER BY n DESC LIMIT 5"));
    $tc = [];
    foreach (sl_rows('SELECT tier, COUNT(*) AS n FROM cdb_loyalty_accounts GROUP BY tier') as $r) $tc[$r->tier] = (int) $r->n;
    $d['tiers'] = [
        ['t' => 'Standard', 'd' => 'Points on every delivered shipment', 'n' => $tc['standard'] ?? 0],
        ['t' => 'Silver · ' . sl_num($s['loyalty_tier_silver']) . ' pts', 'd' => 'Reached on lifetime points', 'n' => $tc['silver'] ?? 0],
        ['t' => 'Gold · ' . sl_num($s['loyalty_tier_gold']) . ' pts', 'd' => 'Reached on lifetime points', 'n' => $tc['gold'] ?? 0],
        ['t' => 'Corporate', 'd' => 'Set by hand for business accounts', 'n' => $tc['corporate'] ?? 0],
    ];
    $d['rewards'] = sl_rows('SELECT * FROM cdb_rewards ORDER BY active DESC, id DESC LIMIT 8');
    $d['events'] = sl_rows('SELECT e.*, u.fname, u.lname FROM cdb_loyalty_events e JOIN cdb_users u ON u.id = e.user_id ORDER BY e.id DESC LIMIT 8');
    $d['top'] = sl_rows('SELECT a.*, u.fname, u.lname FROM cdb_loyalty_accounts a JOIN cdb_users u ON u.id = a.user_id ORDER BY a.points_balance DESC LIMIT 5');
    $d['canManage'] = $user->cdp_hasPermission('manage_loyalty');
    $cov = [['Referral programme', 'Referrals', '#'], ['Referral codes and links', 'Referrals', '#'], ['Automatic referral rewards', 'On the friend\'s first delivered shipment', '#'], ['Shipping loyalty points', 'Points and tiers', '#'], ['Membership tiers', 'Points and tiers', '#'], ['Coupon management', 'Coupons and vouchers', '#'], ['Gift and reward vouchers', 'Coupons and vouchers', '#'], ['Manual adjustments', 'Award points', '#'], ['Customer balance on the portal', 'Customer account', 'UserHome'], ['Retention campaigns', 'Push and messages', 'Broadcast'], ['Review and feedback requests', 'Messages and automation', 'CommsHub']];
    $d['cov'] = array_map(fn($c, $i) => ['n' => $i + 1, 't' => $c[0], 'where' => $c[1], 'href' => $c[2] === '#' ? '#' : sl_route($c[2]), 'here' => $c[2] === '#', 'st' => $c[2] === '#' ? 'Here' : 'Built', 'pc' => $c[2] === '#' ? 'onway' : 'delivered'], $cov, array_keys($cov));
    return $d;
}
