<?php
/**
 * M-ReportView: one report. Filters are a GET form on this page (r, from, to and the same select names
 * the web runner sends: status_courier, agency / agency_courier, method / pay_mode, customer_id,
 * employee_id). Totals and the bar chart come from views/sl/data/phone_settings.php for the parcel
 * reports; the exports are the existing excel / print endpoints with the legacy query
 * (range=Y/m/d - Y/m/d plus the filters), exactly as dataJs/sl/report_view.js builds it.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
require_once CDP_ROOT . '/views/sl/data/phone_settings.php';
$rep = $d['rep'];
$p = $rep['params'];
[$from, $to] = sl_phRange();
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
$stats = sl_phReportStats($rep, $from, $to);
$sel = fn(string $k) => (int) ($_GET[$k] ?? 0);
$q = ['range' => str_replace('-', '/', $from) . ' - ' . str_replace('-', '/', $to)];
foreach (['status_courier', 'agency', 'agency_courier', 'method', 'pay_mode', 'customer_id', 'employee_id'] as $k) {
    if (in_array($k, $p, true)) $q[$k] = $sel($k);
}
$q['page'] = 1;
$q['per_page'] = 50;
$qs = http_build_query($q);
$select = function (string $name, string $label, string $all, array $opts) use ($sel) {
    $out = '<label class="mx-f"><small>' . h($label) . '</small><select class="mx-in" name="' . h($name) . '"><option value="0">' . h($all) . '</option>';
    foreach ($opts as $id => $txt) $out .= '<option value="' . (int) $id . '"' . ($sel($name) === (int) $id ? ' selected' : '') . '>' . h($txt) . '</option>';
    return $out . '</select></label>';
};
$fields = [];
if (in_array('status_courier', $p, true)) $fields[] = $select('status_courier', 'Status', 'All', array_column(array_map(fn($s) => [$s->id, $s->mod_style], $d['statuses']), 1, 0));
foreach (['agency', 'agency_courier'] as $k) if (in_array($k, $p, true)) $fields[] = $select($k, 'Branch', 'All', array_column(array_map(fn($a) => [$a->id, $a->name_branch], $d['agencies']), 1, 0));
foreach (['method', 'pay_mode'] as $k) if (in_array($k, $p, true)) $fields[] = $select($k, 'Payment method', 'All', array_column(array_map(fn($m) => [$m->id, $m->name_pay], $d['methods']), 1, 0));
if (in_array('customer_id', $p, true)) $fields[] = $select('customer_id', 'Customer', 'All customers', array_column(array_map(fn($c) => [$c->id, $c->n], $d['customers']), 1, 0));
if (in_array('employee_id', $p, true)) $fields[] = $select('employee_id', $rep['drivers'] ? 'Rider' : 'Employee', 'All', array_column(array_map(fn($e) => [$e->id, $e->n], $d['employees']), 1, 0));
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-settings.css')) ?>">
<?= sl_phoneTop($rep['title'], $rep['group'], ['back' => sl_route('Reports')]) ?>
<main class="mx-body">
<form method="get" aria-label="Report filters"><div class="mx-card mx-form">
<input type="hidden" name="r" value="<?= h($d['key']) ?>">
<div class="mx-two"><label class="mx-f"><small>From</small><input class="mx-in" type="date" name="from" value="<?= h($from) ?>"></label><label class="mx-f"><small>To</small><input class="mx-in" type="date" name="to" value="<?= h($to) ?>"></label></div>
<?php foreach (array_chunk($fields, 2) as $pair): ?>
<?= count($pair) === 2 ? '<div class="mx-two">' . implode('', $pair) . '</div>' : $pair[0] ?>
<?php endforeach; ?>
<button class="mx-btn sec" type="submit">Show</button>
</div></form>
<?php if ($stats): ?>
<section class="mx-k c2">
<div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm <?= h($stats['icon']) ?>" aria-hidden="true"></i></span><small><?= h($stats['noun']) ?></small></div><b><?= h(sl_num($stats['n'])) ?></b><?= sl_phSpark(array_column($stats['bars'], 'h')) ?></div>
<div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-box" aria-hidden="true"></i></span><small>Weight</small></div><b><?= h(sl_num($stats['w'], $stats['w'] > 0 && $stats['w'] < 100 ? 1 : 0)) ?> lb</b><small class="mx-note">in <?= h(sl_num($stats['n'])) ?> <?= h(mb_strtolower($stats['noun'])) ?></small></div>
</section>
<section class="mx-card mx-bars"><div class="mx-bh"><b><?= h($stats['per']) ?></b></div>
<?php if ($stats['n'] > 0): ?>
<div class="mx-bc" role="img" aria-label="<?= h($stats['per'] . ': ' . implode(', ', array_map(fn($b) => $b['l'] . ' ' . $b['n'], $stats['bars']))) ?>"><?php foreach ($stats['bars'] as $i => $b): ?><span class="<?= $i === count($stats['bars']) - 1 ? 'now' : '' ?>"><i style="height:<?= (int) $b['h'] ?>%"></i><small><?= h($b['l']) ?></small></span><?php endforeach; ?></div>
<?php else: ?>
<?= sl_mxEmpty('chart', 'Nothing in this period', 'No records match these dates and filters.') ?>
<?php endif; ?>
</section>
<?php else: ?>
<div class="mx-ban info"><i class="ic ic-sm i-chart" aria-hidden="true"></i><span><?= h(sl_date($from)) ?> – <?= h(sl_date($to)) ?>. Download the report as Excel or open the printable version to see every row.</span></div>
<?php endif; ?>
<div class="mx-btns">
<?php if ($rep['excel']): ?><a class="mx-btn" href="<?= h($rep['excel'] . '?' . $qs) ?>">Download Excel</a><?php endif; ?>
<?php if ($rep['print']): ?><a class="mx-btn sec" href="<?= h($rep['print'] . '?' . $qs) ?>" target="_blank" rel="noopener">Print</a><?php endif; ?>
</div>
<?php if (count($d['siblings']) > 1): ?>
<div class="mx-sec"><h2>Other <?= h(mb_strtolower($rep['group'])) ?> reports</h2></div>
<section class="mx-card mx-list">
<?php foreach ($d['siblings'] as $k => $r): if ($k === $d['key']) continue; ?>
<a class="mx-row" href="<?= h('report_view.php?' . http_build_query(['r' => $k, 'from' => $from, 'to' => $to])) ?>"><span class="mx-li"><i class="ic i-chart" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($r['title']) ?></b></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<?php endforeach; ?>
</section>
<?php endif; ?>
</main>
