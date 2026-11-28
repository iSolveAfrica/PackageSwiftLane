<?php
/**
 * Rider quests and the rider app's emergency number, for staff (Rider fees page, web and phone).
 * Writes: ajax/sl/rider_app_ajax.php op=quest_save, op=site_setting (permission manage_rider_fees).
 * A quest's reward is not paid automatically: the list shows who reached it so staff can pay them
 * through a settlement or payout as usual.
 */
require_once CDP_ROOT . '/helpers/rider_money.php';
require_once CDP_ROOT . '/views/sl/data/riders.php';

function sl_riderAdminData($user): array
{
    $out = ['quests' => [], 'questEdit' => null, 'emergency' => (string) sl_riderPref(0, 'emergency_number', ''), 'canEdit' => $user->cdp_hasPermission('manage_rider_fees'), 'questsReady' => sl_tableExists('cdb_rider_quests')];
    if (!$out['questsReady']) return $out;
    $now = date('Y-m-d H:i:s');
    foreach (sl_rows('SELECT * FROM cdb_rider_quests ORDER BY (ends_at >= :n) DESC, ends_at DESC, id DESC LIMIT 20', [':n' => $now]) as $q) {
        // Who reached it: riders whose finished trips inside the window meet the target.
        $reached = [];
        $started = $q->starts_at <= $now;
        if ($started) {
            foreach (sl_rdStats(substr($q->starts_at, 0, 10), substr(min($q->ends_at, $now), 0, 10)) as $id => $s) {
                if ($s['done'] >= (int) $q->target_trips) $reached[] = $s['rider']->name;
            }
        }
        $state = !(int) $q->active ? ['Paused', 'plain'] : ($q->ends_at < $now ? ['Ended', 'plain'] : ($started ? ['Running', 'delivered'] : ['Starts ' . sl_date($q->starts_at, 'D j M'), 'onway']));
        $out['quests'][] = ['id' => (int) $q->id, 'title' => $q->title, 'target' => (int) $q->target_trips, 'reward' => sl_money($q->reward),
            'window' => sl_date($q->starts_at, 'D j M H:i') . ' – ' . sl_date($q->ends_at, 'D j M H:i'), 'state' => $state[0], 'cls' => $state[1], 'reached' => $reached];
    }
    $want = (string) ($_GET['quest'] ?? '');
    if ($want === 'new') {
        $out['questEdit'] = (object) ['id' => 0, 'title' => '', 'target_trips' => '', 'reward' => '', 'starts_at' => date('Y-m-d 00:00:00', strtotime('next monday')), 'ends_at' => date('Y-m-d 23:59:00', strtotime('next sunday +7 days')), 'active' => 1];
    } elseif (ctype_digit($want)) {
        $out['questEdit'] = sl_row('SELECT * FROM cdb_rider_quests WHERE id = :id', [':id' => (int) $want]);
    }
    return $out;
}
