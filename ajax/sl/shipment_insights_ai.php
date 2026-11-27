<?php
/**
 * Shipment insights: AI brief and questions for the Air Shipments page.
 * POST range=30d|90d|12m|all, q=optional question. Sends the user's own (scoped) shipment
 * figures to the AI provider configured on Tools > King and returns a short brief.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/querys.php';
require_once __DIR__ . '/../../helpers/ai_assistant_llm.php';
require_once __DIR__ . '/../../views/sl/data/shipments.php';
require_login();
require_permission('view_shipment_list');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
if (sl_userIs('client')) {
    sl_json(['success' => false, 'message' => 'Not available'], 403);
}
$core = (isset($core) && $core instanceof Core) ? $core : new Core();
$user->cdp_getUserPermissions();
if (!cdp_aiCanUse($user) || !cdp_aiConfigured()) {
    sl_json(['success' => false, 'message' => cdp_kingName() . ' is not set up yet. A super admin turns it on in Tools > King.'], 409);
}

$range = (string) ($_POST['range'] ?? '12m');
$q = mb_substr(trim(strip_tags((string) ($_POST['q'] ?? ''))), 0, 400);
$cacheKey = md5($range . '|' . $q . '|' . sl_opsScope('a'));
$cached = $_SESSION['sl_ship_ai'][$cacheKey] ?? null;
if ($cached && empty($_POST['fresh']) && time() - $cached['t'] < 1800) {
    sl_json(['success' => true, 'text' => $cached['text'], 'at' => date('H:i', $cached['t']), 'cached' => true]);
}

$ins = sl_shipmentInsightsData($range);
$scope = sl_opsScope('a');
$base = "FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier <> 14 $scope";
$facts = [
    'today' => date('Y-m-d'),
    'currency' => (string) ($core->currency ?? ''),
    'all_time' => ['shipments' => $ins['all'], 'customers' => $ins['customers'], 'weight' => round($ins['allWeight'], 1)],
    'right_now' => [
        'open_in_progress' => (int) sl_scalar("SELECT COUNT(*) $base AND a.status_courier NOT IN (8,15,16,21,12)"),
        'delivered' => (int) sl_scalar("SELECT COUNT(*) $base AND a.status_courier IN (8,15)"),
        'awaiting_payment' => (int) sl_scalar("SELECT COUNT(*) $base AND a.status_invoice IN (2,23) AND a.status_courier <> 21"),
        'awaiting_payment_over_14_days' => (int) sl_scalar("SELECT COUNT(*) $base AND a.status_invoice IN (2,23) AND a.status_courier <> 21 AND a.order_date < CURDATE() - INTERVAL 14 DAY"),
        'in_consolidations' => (int) sl_scalar("SELECT COUNT(*) $base AND a.is_consolidate = 1"),
        'held_or_at_customs' => (int) sl_scalar("SELECT COUNT(*) $base AND a.status_courier IN (28,29,30)"),
    ],
    'this_month' => ['name' => $ins['monthName'], 'days_so_far' => $ins['today'], 'registered' => $ins['month'], 'same_days_last_month' => $ins['lastSame'],
        'last_month_total' => $ins['lastFull'], 'weight' => round($ins['monthWeight'], 1), 'value' => round($ins['monthValue'], 2), 'registered_per_day' => array_slice($ins['byDay'], 0, $ins['today'], true)],
    'registered_per_month_last_12' => $ins['months'],
    'period_for_breakdowns' => $ins['rangeLabel'],
    'status_mix' => array_map(fn($s) => [$s['label'] => $s['n']], $ins['status']),
    'top_shippers' => array_map(fn($s) => ['name' => $s['name'], 'locker' => $s['locker'], 'shipments' => $s['n'], 'weight' => round($s['w'], 1), 'value' => round($s['v'], 2)], $ins['shippers']),
    'most_shipped_items' => array_map(fn($i) => ['item' => $i['label'], 'quantity' => $i['q'], 'shipments' => $i['n']], $ins['items']),
];

$system = 'You are the shipment analyst for ' . ($core->site_name ?? 'Swift Lane') . ', a courier and freight forwarder (customers buy abroad, we ship to Ghana by air). '
    . 'You get the air shipment figures as JSON. Use only these figures: never invent numbers, customers or causes; when something cannot be told from the data, say so. '
    . 'Write for a busy operations manager: short markdown, bullet points, bold the key number in each bullet, no tables, no preamble. '
    . 'Weights are in the units used by the business (lbs). Money is in the stated currency.';
$prompt = ($q !== ''
        ? "Question from the manager: $q\n\nAnswer it from the figures below in at most 160 words."
        : "Write today's shipment brief in at most 170 words with three short sections: **What stands out** (trends: this month against last month, the 12-month pattern), **Needs attention** (payment, customs, open work), **Suggested actions** (2-3 concrete steps, naming customers or items where the data supports it).")
    . "\n\nFigures:\n" . json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

$r = cdp_aiComplete($system, $prompt, null, null, null, 1500);
if (empty($r['ok']) || trim((string) ($r['text'] ?? '')) === '') {
    sl_json(['success' => false, 'message' => $r['error'] ?? 'The AI did not answer. Please try again.'], 502);
}
$_SESSION['sl_ship_ai'][$cacheKey] = ['t' => time(), 'text' => $r['text']];
sl_json(['success' => true, 'text' => $r['text'], 'at' => date('H:i'), 'cached' => false]);
