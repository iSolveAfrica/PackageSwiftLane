<?php
/** Data for Operating costs (CostSettings.dc.html): cdb_operating_costs with monthly totals. */
require_once CDP_ROOT . '/helpers/sl_view.php';

function sl_costFreqs(): array
{
    return ['monthly' => 'Monthly', 'yearly' => 'Yearly', 'weekly' => 'Weekly', 'daily' => 'Daily', 'once' => 'One-off'];
}

function sl_costCats(): array
{
    return ['infra' => 'Servers', 'messages' => 'Messages', 'rent' => 'Rent', 'staff' => 'Staff', 'fuel' => 'Fuel', 'software' => 'Software', 'customs' => 'Customs', 'other' => 'Other'];
}

/** Monthly equivalent of a cost line, in its own currency. One-off costs count in the month they apply from. */
function sl_costMonthly($amount, string $freq, ?string $from = null): float
{
    $a = (float) $amount;
    switch ($freq) {
        case 'yearly': return $a / 12;
        case 'weekly': return $a * 52 / 12;
        case 'daily': return $a * 365 / 12;
        case 'once': return ($from && substr($from, 0, 7) === date('Y-m')) ? $a : 0.0;
        default: return $a;
    }
}

function sl_costSettingsData($user, $core): array
{
    $d = ['ready' => sl_tableExists('cdb_operating_costs')];
    $d['canEdit'] = sl_userIs('superadmin') || $user->cdp_hasPermission('manage_operating_costs');
    $d['me'] = trim(($GLOBALS['userData']->fname ?? '') . ' ' . ($GLOBALS['userData']->lname ?? ''));
    $d['rate'] = isset($core->exchange_rate) ? (float) $core->exchange_rate : 0;
    $d['cats'] = sl_costCats();
    $d['freqs'] = sl_costFreqs();
    $d['rows'] = []; $d['totals'] = []; $d['grand'] = 0; $d['history'] = [];
    if (!$d['ready']) return $d;
    $byCat = [];
    foreach (sl_rows('SELECT * FROM cdb_operating_costs WHERE is_active = 1 ORDER BY category, id') as $r) {
        $m = sl_costMonthly($r->amount, (string) $r->frequency, $r->applies_from);
        $usd = strtoupper((string) $r->currency) === 'GHS' ? ($d['rate'] > 0 ? $m / $d['rate'] : 0) : $m;
        $d['rows'][] = ['id' => (int) $r->id, 'name' => $r->name, 'cat' => $r->category, 'amt' => number_format((float) $r->amount, 2, '.', ''), 'cur' => strtoupper((string) $r->currency), 'freq' => $r->frequency, 'unit' => $r->unit_label, 'bill' => (int) $r->on_billing === 1, 'from' => $r->applies_from];
        $byCat[$r->category] = ($byCat[$r->category] ?? 0) + $usd;
        $d['grand'] += $usd;
    }
    foreach ($byCat as $c => $v) $d['totals'][] = ['label' => $d['cats'][$c] ?? ucfirst($c), 'calc' => 'monthly equivalent', 'total' => sl_money($v)];
    $d['grandF'] = sl_money($d['grand']);
    // what this month's messages cost, from the message log (when present)
    $d['msgCount'] = sl_tableExists('cdb_message_log') ? sl_rows("SELECT channel, COUNT(*) AS n FROM cdb_message_log WHERE created_at >= :m AND status = 'sent' GROUP BY channel", [':m' => date('Y-m-01')]) : [];
    if (sl_tableExists('cdb_operating_cost_log')) {
        foreach (sl_rows('SELECT l.*, u.fname, u.lname FROM cdb_operating_cost_log l LEFT JOIN cdb_users u ON u.id = l.changed_by ORDER BY l.id DESC LIMIT 6') as $h) {
            $d['history'][] = ['t' => $h->cost_name . ' ' . ($h->old_amount === null ? 'added ' . number_format((float) $h->new_amount, 2) : ($h->new_amount === null ? 'removed' : number_format((float) $h->old_amount, 2) . ' → ' . number_format((float) $h->new_amount, 2))), 'who' => sl_date($h->changed_at, 'M j') . ' · ' . sl_initials(trim($h->fname . ' ' . $h->lname))];
        }
    }
    return $d;
}
