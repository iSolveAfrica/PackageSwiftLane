<?php
/** Data for Message Costs (MessageCosts.dc.html) via helpers/message_cost.php (cdp_mcReport). */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/sl_charts.php';
require_once CDP_ROOT . '/helpers/message_log_query.php';
require_once CDP_ROOT . '/helpers/message_cost.php';

function sl_messageCostsData($user, $core): array
{
    $range = (string) ($_GET['range'] ?? 'month');
    $today = date('Y-m-d');
    $from = ['today' => $today, '7d' => date('Y-m-d', strtotime('-6 days')), 'month' => date('Y-m-01'), 'year' => date('Y-01-01'), 'all' => ''][$range] ?? date('Y-m-01');
    if (!in_array($range, ['today', '7d', 'month', 'year', 'all'], true)) $range = 'month';
    $_REQUEST['from'] = $from;
    $_REQUEST['to'] = $range === 'all' ? '' : $today;
    $_REQUEST['group'] = (string) ($_GET['group'] ?? 'auto');
    $r = cdp_mcReport(cdp_mcFilters());
    $cur = cdp_mcSettings()['currency'];
    $money = fn($v) => $cur . ' ' . cdp_mcMoney($v);
    $d = ['ok' => !empty($r['ok']), 'range' => $range, 'currency' => $cur, 'canEdit' => cdp_mcCanEdit($user),
        'ranges' => [['k' => 'today', 'l' => 'Today'], ['k' => '7d', 'l' => '7d'], ['k' => 'month', 'l' => 'This month'], ['k' => 'year', 'l' => 'This year'], ['k' => 'all', 'l' => 'All time']]];
    $labels = ['sms' => ['SMS', 'i-message', ''], 'whatsapp' => ['WhatsApp', 'i-phone', 'y'], 'email' => ['E-mail · SMTP', 'i-mail', 'r']];
    $current = $r['current'] ?? cdp_mcCurrentRates();
    $grand = max(0.000001, (float) ($r['grand']['cost'] ?? 0));
    $d['cards'] = [];
    foreach ($labels as $ch => $l) {
        $t = $r['totals'][$ch] ?? ['sent' => 0, 'cost' => 0];
        $d['cards'][] = ['label' => $l[0], 'icon' => $l[1], 'tick' => $l[2], 'fig' => $money($t['cost']), 'share' => sl_pct($t['cost'], $grand), 'ft' => sl_num($t['sent']) . ' sent · ' . $money($current[$ch] ?? 0) . ' each', 'href' => sl_route('MessageLogs', ['filter' => $ch])];
    }
    $periods = $r['periods'] ?? [];
    $series = array_map(fn($p) => (float) $p['cost'], $periods);
    $d['grand'] = $money($r['grand']['cost'] ?? 0);
    $d['avg'] = $periods ? $money(array_sum($series) / count($series)) : $money(0);
    $d['group'] = $r['group'] ?? 'day';
    $max = max(0.01, max($series ?: [0]));
    $d['line'] = count($series) > 1 ? sl_svgLinePath($series, 550, 190, 12, $max, 44) : '';
    $d['lineMax'] = $money($max);
    $d['xFirst'] = $periods ? $periods[0]['period'] : '';
    $d['xLast'] = $periods ? end($periods)['period'] : '';
    $src = array_slice($r['sources'] ?? [], 0, 6);
    $bars = [];
    foreach ($src as $s) $bars[mb_substr($s['label'], 0, 12)] = round($s['cost'], 2);
    require_once CDP_ROOT . '/views/sl/data/api_admin.php';
    $d['bars'] = $bars ? sl_apiBars($bars) : '';
    $d['topSrc'] = $src ? $src[0]['label'] : '—';
    $d['rows'] = array_map(fn($p) => ['p' => $d['group'] === 'month' ? date('M Y', strtotime($p['period'] . '-01')) : sl_date($p['period'], 'M j'), 'sms' => sl_num($p['channels']['sms']['sent']), 'wa' => sl_num($p['channels']['whatsapp']['sent']), 'em' => sl_num($p['channels']['email']['sent']), 'n' => sl_num($p['sent']), 'cost' => $money($p['cost'])], array_reverse(array_slice($periods, -31)));
    $d['current'] = array_map(fn($v) => cdp_mcMoney($v), $current);
    $hist = [];
    foreach (cdp_mcRates() as $ch => $list) {
        foreach ($list as $i => $x) {
            $hist[] = ['id' => $x['id'], 'what' => cdp_mlChannelLabel($ch) . ' ' . ($i ? cdp_mcMoney($list[$i - 1]['unit_cost']) . ' → ' : '') . cdp_mcMoney($x['unit_cost']), 'when' => sl_date($x['effective_from'], 'M j, Y'), 'd' => $x['effective_from']];
        }
    }
    usort($hist, fn($a, $b) => strcmp($b['d'], $a['d']));
    $d['history'] = array_slice($hist, 0, 6);
    $d['nextMonth'] = date('Y-m-d', strtotime('first day of next month'));
    return $d;
}
