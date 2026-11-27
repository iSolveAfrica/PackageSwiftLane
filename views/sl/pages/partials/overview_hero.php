<?php
/**
 * Overview top: header with actions, view bar, "you" card, today cards and the freight section.
 * Data: views/sl/data/overview_hero.php ($d['view'], $d['profile'], $d['cards'], $d['freight']).
 * Styles: assets/swiftlane/overview-hero.css.
 */
$hv = $d['view']; $hp = $d['profile']; $hc = $d['cards']; $hf = $d['freight'];
$canAddCon = $user->cdp_hasPermission(['add_consolidate_shipment', 'add_consolidate_package']);
$canFin = function_exists('cdp_financeEnabled') && cdp_financeEnabled() && $user->cdp_hasPermission('financial_sheet') && cdp_canViewMoney($user);
$bars = function (array $bars, string $cls = '', string $label = 'Last 14 days') {
    if (!$bars) return '';
    $o = '<div class="ovh-bars ' . h($cls) . '" role="img" aria-label="' . h($label) . '">';
    $n = count($bars);
    foreach ($bars as $i => $b) $o .= '<i class="' . ($i === $n - 1 ? 'now' : '') . '" style="' . h($b['s']) . '" title="' . h($b['t']) . '"></i>';
    return $o . '</div>';
};
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/overview-hero.css')) ?>">
<header class="ovh-head">
  <div class="ovh-title"><i class="ic i-grid" aria-hidden="true"></i><h1 class="sl-title">Control panel</h1></div>
  <div class="ovh-acts">
    <span class="ovh-who"><?= h($hp['name']) ?> · <?= h(date('M Y')) ?></span>
    <?php if ($user->cdp_hasPermission('add_shipment')): ?><a class="sl-btn sm" href="<?= sl_route('NewShipment') ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New Shipment</a><?php endif; ?>
    <?php if ($user->cdp_hasPermission('add_package')): ?><a class="sl-btn sm ghost" href="<?= sl_route('NewPackage') ?>">New Package</a><?php endif; ?>
    <?php if ($canAddCon): ?><a class="sl-btn sm ghost" href="<?= sl_route('NewConsolidation') ?>">New Consolidation</a><?php endif; ?>
    <?php if ($canFin): ?><a class="sl-btn sm ghost" href="<?= sl_route('FinancialSheet') ?>">Financial Sheet</a><?php endif; ?>
  </div>
</header>
<?php
$adminView = cdp_dashboardType($user->userlevel) === 'admin';
// The operations briefing (partials/overview_brief.php) is not shown on the Overview.
if (!$adminView) include __DIR__ . '/overview_work.php';
?>

<?php if ($hv['mode'] !== 'none'): ?>
<form class="ovh-view" method="get" action="index.php" aria-label="Dashboard view">
  <span class="ovh-vl"><i class="ic ic-sm i-filter" aria-hidden="true"></i>View</span>
  <?php if ($hv['canAir'] && $hv['canSea']): ?>
  <div class="ovh-seg" role="group" aria-label="Freight mode">
    <?php foreach (['all' => ['Air & Sea', 'i-layers'], 'air' => ['Air', 'i-plane'], 'sea' => ['Sea', 'i-ship']] as $m => [$ml, $mi]): ?>
    <a class="<?= $hv['mode'] === $m ? 'on' : '' ?>" href="<?= h(sl_url(['v' => $m === 'all' ? null : $m])) ?>"<?= $hv['mode'] === $m ? ' aria-current="true"' : '' ?>><i class="ic ic-sm <?= $mi ?>" aria-hidden="true"></i><?= $ml ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <span class="grow"></span>
  <div class="ovh-periods" role="group" aria-label="Period">
    <?php foreach (['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'] as $pk => $pl): ?>
    <a class="<?= $hv['period'] === $pk ? 'on' : '' ?>" href="<?= h(sl_url(['p' => $pk === 'month' ? null : $pk, 'from' => null, 'to' => null])) ?>"<?= $hv['period'] === $pk ? ' aria-current="true"' : '' ?>><?= $pl ?></a>
    <?php endforeach; ?>
  </div>
  <div class="ovh-range">
    <?php if ($hv['mode'] !== 'all'): ?><input type="hidden" name="v" value="<?= h($hv['mode']) ?>"><?php endif; ?>
    <input type="hidden" name="p" value="custom">
    <label aria-label="From"><input type="date" name="from" value="<?= h($hv['from']) ?>" max="<?= h(date('Y-m-d')) ?>"></label>
    <span class="mut">to</span>
    <label aria-label="To"><input type="date" name="to" value="<?= h($hv['toShow']) ?>" max="<?= h(date('Y-m-d')) ?>"></label>
    <button class="sl-btn sm ghost" type="submit">Apply</button>
  </div>
</form>
<?php endif; ?>

<div class="ovh-wrap"><div class="ovh-grid<?= count($hc) <= 2 ? ' few' : '' ?>" style="--rows:<?= max(1, (int) ceil(count($hc) / 2)) ?>">
  <section class="ovh-me" aria-label="You">
    <div class="ovh-me-top">
      <?= $slPic(64, false) ?>
      <div class="col grow" style="min-width:0">
        <span class="ovh-eyebrow"><?= h($hp['greet']) ?></span>
        <b class="ovh-name"><?= h($hp['name']) ?></b>
        <span class="mut sm"><?= h($slSub ?? '') ?><?= $hp['last'] ? ' · last visit ' . h($hp['last']) : '' ?></span>
      </div>
      <a class="sl-btn sm ghost ovh-prof" href="<?= h($slProfileUrl ?? '#') ?>"><i class="ic ic-sm i-user" aria-hidden="true"></i>My profile</a>
    </div>
    <?php if ($hp['logOn']):
        $tiles = [
            ['Logged actions today', sl_num($hp['today']), 'activity log entries today', null, ''],
            ['Logged actions this week', sl_num($hp['week']), 'since Monday', null, ''],
            ['Days with activity', (int) $hp['active'] . '<small>/30</small>', 'days with recorded activity', null, ''],
            ['Registered by me', sl_num($hp['mine']), ($hv['mode'] === 'sea' ? 'sea packages' : ($hv['mode'] === 'all' ? 'air shipments + sea packages' : 'air shipments')) . ' · ' . $hv['label'], null, ''],
        ];
    ?>
    <p class="mut sm">Your recorded activity. Action counts are log entries, not unique parcels or completed deliveries.</p>
    <div class="ovh-kpis">
      <?php foreach ($tiles as $i => [$tl, $tv, $tu, $td, $tdl]): ?>
      <div class="ovh-kpi">
        <span class="ovh-kl"><?= h($tl) ?></span>
        <b><?= $tv ?></b>
        <?php if ($i === 2): ?><span class="ovh-meter" role="img" aria-label="Active on <?= (int) $hp['active'] ?> of the last 30 days"><i style="width:<?= (int) round($hp['active'] / 30 * 100) ?>%"></i></span>
        <?php elseif ($td !== null): ?><span class="ovh-ku"><span class="<?= $td < 0 ? 'dn' : 'up' ?>"><?= $td < 0 ? '▼' : '▲' ?> <?= abs($td) > 999 ? '999+' : abs($td) ?>%</span> <?= h($tdl) ?></span>
        <?php else: ?><span class="ovh-ku"><?= h($tu) ?></span><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if ($hp['kinds']): ?>
    <div class="ovh-kinds">
      <div class="row between sm"><span class="fw5">Recorded actions this week</span><?php if ($hp['peak']): ?><span class="mut"><i class="ic ic-sm i-clock" aria-hidden="true"></i> Most active hour <?= h($hp['peak']) ?></span><?php endif; ?></div>
      <?php foreach ($hp['kinds'] as $k): ?><div class="ovh-kind"><span><?= h($k['l']) ?> events</span><span class="ovh-kbar"><i style="width:<?= (int) $k['w'] ?>%"></i></span><b><?= sl_num($k['n']) ?></b></div><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="ovh-me-bars">
      <div class="row between sm"><span class="mut">Activity log entries · last 14 days</span><?php if ($hp['lastLabel']): ?><span class="mut ovh-last" title="<?= h($hp['lastLabel']) ?>">Last: <?= h($hp['lastLabel']) ?> · <?= h($hp['lastAgo']) ?></span><?php endif; ?></div>
      <?= $bars($hp['bars'], 'violet') ?>
    </div>
    <?php endif; ?>
    <?php if ($hp['chips'] || $hp['logOn']): ?>
    <div class="ovh-chips">
      <?php foreach ($hp['chips'] as $c): ?><a href="<?= h($c['href']) ?>"><i class="ic ic-sm <?= h($c['icon']) ?>" aria-hidden="true"></i><?= h($c['l']) ?> <b><?= sl_num($c['n']) ?></b></a><?php endforeach; ?>
      <?php if ($hp['logOn']): ?><a href="<?= h(sl_route('ActivityLogs')) ?>"><i class="ic ic-sm i-activity" aria-hidden="true"></i>My activity</a><?php endif; ?>
    </div>
    <?php endif; ?>
  </section>

  <?php foreach ($hc as $c): ?>
  <a class="ovh-card t-<?= h($c['tone']) ?>" href="<?= h($c['href']) ?>">
    <div class="ovh-ch"><span class="ovh-eyebrow"><i class="ic ic-sm <?= h($c['icon']) ?>" aria-hidden="true"></i><?= h($c['eyebrow']) ?></span><?php if (!empty($c['alert'])): ?><span class="ovh-alert"><i class="ic ic-sm i-alert" aria-hidden="true"></i><?= h($c['alert']) ?></span><?php else: ?><i class="ic ic-sm i-expand ovh-go" aria-hidden="true"></i><?php endif; ?></div>
    <b class="ovh-big"><?= h($c['big']) ?></b>
    <span class="ovh-lede"><?= h($c['lede']) ?></span>
    <?php if ($c['sub']): ?><div class="ovh-sub" style="--n:<?= count($c['sub']) ?>"><?php foreach ($c['sub'] as $s): ?><span><b><?= h($s['n']) ?></b><small><?= h($s['l']) ?></small></span><?php endforeach; ?></div><?php endif; ?>
    <?= $bars($c['bars'], '', (string) ($c['barsL'] ?? 'Last 14 days')) ?>
    <div class="ovh-foot"><span><?= h($c['barsL']) ?></span><span><?= h($c['barsR']) ?></span></div>
  </a>
  <?php endforeach; ?>
</div></div>

<?php if ($hf):
    $n = count($hf['cur']);
    $L = 44; $R = 748; $T = 14; $B = 176;
    $step = ($R - $L) / max(1, $n - 1);
    $peak = max(1, max(array_filter($hf['cumCur'], fn($v) => $v !== null) ?: [0]), max($hf['cumPrev'] ?: [0]));
    $x = fn($i) => round($n > 1 ? $L + $step * $i : ($L + $R) / 2, 1);
    $y = fn($v) => round($B - ($B - $T) * $v / $peak, 1);
    $curLine = ''; $lastI = 0;
    foreach ($hf['cumCur'] as $i => $v) { if ($v === null) break; $curLine .= ($i ? ' L' : 'M') . $x($i) . ',' . $y($v); $lastI = $i; }
    $area = $curLine !== '' ? $curLine . ' L' . $x($lastI) . ',' . $B . ' L' . $x(0) . ',' . $B . ' Z' : '';
    $prevLine = '';
    foreach ($hf['cumPrev'] as $i => $v) $prevLine .= ($i ? ' L' : 'M') . $x($i) . ',' . $y($v);
    $ticks = array_values(array_unique([0, (int) round($peak / 2), (int) $peak]));
    $xl = array_values(array_unique([0, (int) floor(($n - 1) / 3), (int) floor(2 * ($n - 1) / 3), $n - 1]));
    $dPeak = max(1, max($hf['cur'] ?: [0]));
    $dStep = ($R - $L) / max(1, $n); $dw = max(2, min(22, $dStep * .7));
    $endV = $hf['cumCur'][$lastI] ?? 0;
    $pace = $hf['pace'];
?>
<section class="ovh-freight ovh-freight--focus" aria-label="<?= h($hf['title']) ?>">
  <header class="ovh-freight-head">
    <div class="ovh-fl">    <span class="ovh-eyebrow"><i class="ic ic-sm i-layers" aria-hidden="true"></i><?= h($hf['title']) ?></span>
    <div class="ovh-tot"><b class="ovh-huge"><?= sl_num($hf['total']) ?></b><span class="mut">registered</span></div>
    <?php if ($pace === null): ?><p class="ovh-delta mut">Nothing registered at this point in <?= h($hf['prevLabel']) ?></p>
    <?php elseif ((float) $pace === 0.0): ?><p class="ovh-delta"><span class="eq">= on pace</span> with <?= h($hf['prevLabel']) ?></p>
    <?php else: ?><p class="ovh-delta"><span class="<?= $pace < 0 ? 'dn' : 'up' ?>"><?= $pace < 0 ? '↘' : '↗' ?> <?= abs($pace) ?>%</span> <?= $pace < 0 ? 'behind' : 'ahead of' ?> <?= h($hf['prevLabel']) ?> · <?= sl_num($hf['prevSame']) ?> then</p><?php endif; ?>
    </div>
    <div class="ovh-freight-modes">    <?php if ($hf['showSplit']): ?>
    <div class="ovh-split" role="img" aria-label="Air <?= (int) $hf['airPct'] ?>%, sea <?= (int) $hf['seaPct'] ?>%"><i class="air" style="width:<?= (int) $hf['airPct'] ?>%"></i><i class="sea" style="width:<?= (int) $hf['seaPct'] ?>%"></i></div>
    <div class="ovh-legend"><span><i class="air"></i>Air <?= (int) $hf['airPct'] ?>% · <?= sl_num($hf['air']) ?></span><span><i class="sea"></i>Sea <?= (int) $hf['seaPct'] ?>% · <?= sl_num($hf['sea']) ?></span></div>
    <?php endif; ?>
    </div>
  </header>
  <div class="ovh-fr">
    <div class="row between wrap g8"><div><h2 class="sl-ct">Registration growth</h2><p class="mut sm">Cumulative registrations · <?= h($hf['prevLabel']) ?> compared with the selected period</p></div><div class="ovh-legend"><span><i class="cur"></i>Selected period</span><span><i class="prev"></i>Previous period</span></div></div>
    <svg class="ovh-chart" <?= sl_chartAttrs([['name' => 'This period (running)', 'values' => $hf['cumCur'], 'color' => 'var(--sky)'], ['name' => 'Previous period (running)', 'values' => $hf['cumPrev'], 'color' => 'var(--yellow)'], ['name' => 'Registered that ' . $hf['unit'], 'values' => $hf['cur'], 'color' => 'var(--violet)']], $hf['labels'], 'Running total', $x(0), $x($n - 1)) ?> viewBox="0 0 760 200" role="img" aria-label="Running total of registrations compared with the previous period">
      <defs><linearGradient id="ovhArea" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="var(--sky)" stop-opacity=".32"/><stop offset="1" stop-color="var(--sky)" stop-opacity="0"/></linearGradient></defs>
      <?php foreach ($ticks as $t): ?><line class="g" x1="<?= $L ?>" x2="<?= $R ?>" y1="<?= $y($t) ?>" y2="<?= $y($t) ?>"/><text class="ax" x="<?= $L - 8 ?>" y="<?= round($y($t) + 4, 1) ?>" text-anchor="end"><?= sl_num($t) ?></text><?php endforeach; ?>
      <path class="p" d="<?= h($prevLine) ?>"/>
      <?php if ($area): ?><path class="a" d="<?= h($area) ?>" fill="url(#ovhArea)"/><path class="c" d="<?= h($curLine) ?>"/>
      <circle class="dot" cx="<?= $x($lastI) ?>" cy="<?= $y($endV) ?>" r="5"/><text class="end" x="<?= $x($lastI) > $R - 60 ? $x($lastI) - 12 : $x($lastI) + 12 ?>" y="<?= $y($endV) + ($y($endV) < $T + 20 ? 18 : -10) ?>" text-anchor="<?= $x($lastI) > $R - 60 ? 'end' : 'start' ?>"><?= sl_num($endV) ?></text><?php endif; ?>
      <?php foreach ($xl as $i): ?><text class="ax" x="<?= $x($i) ?>" y="198" text-anchor="<?= $i === 0 ? 'start' : ($i === $n - 1 ? 'end' : 'middle') ?>"><?= h($hf['labels'][$i]) ?></text><?php endforeach; ?>
    </svg>
    <details class="ovh-daily-detail"><summary>View registrations per <?= h($hf['unit']) ?></summary>    <div class="ovh-daily">
      <span class="mut sm">Per <?= h($hf['unit']) ?></span>
      <svg viewBox="0 0 760 40" preserveAspectRatio="none" role="img" aria-label="Registrations per <?= h($hf['unit']) ?>">
        <?php foreach ($hf['cur'] as $i => $v): $hh = 36 * $v / $dPeak; ?><rect x="<?= round($L + $dStep * ($i + .5) - $dw / 2, 1) ?>" y="<?= round(38 - max($hh, 1.5), 1) ?>" width="<?= round($dw, 1) ?>" height="<?= round(max($hh, 1.5), 1) ?>" rx="2" class="<?= $v === $hf['best']['n'] && $v > 0 ? 'top' : ($v ? '' : 'zero') ?>"><title><?= h($hf['labels'][$i]) ?>: <?= (int) $v ?></title></rect><?php endforeach; ?>
      </svg>
      <div class="ovh-xl"><?php foreach ($xl as $i): ?><span style="left:<?= round(($L + $dStep * ($i + .5)) / 760 * 100, 2) ?>%"><?= h($hf['labels'][$i]) ?></span><?php endforeach; ?></div>
    </div>
    </details>
  </div>
    <div class="ovh-fstats">
      <div><span>Average a <?= h($hf['unit']) ?></span><b><?= sl_num($hf['avg']) ?></b></div>
      <div><span>Best <?= h($hf['unit']) ?></span><b><?= sl_num($hf['best']['n']) ?></b><small><?= h($hf['best']['l']) ?></small></div>
      <?php if ($hf['forecast'] !== null): ?><div><span>Projected period total</span><b><?= sl_num($hf['forecast']) ?></b><small><?= h($hf['forecastLabel']) ?> at this pace</small></div>
      <?php else: ?><div><span>Total registrations</span><b><?= sl_num($hf['book']) ?></b><small>all time</small></div><?php endif; ?>
      <div><span><?= ucfirst(h($hf['unit'])) ?>s with no registrations</span><b<?= $hf['quiet'] ? ' class="warn"' : '' ?>><?= (int) $hf['quiet'] ?></b><small>of <?= (int) $hf['buckets'] ?> with none</small></div>
    </div>
</section>
<?php endif; ?>

<?php if ($adminView) include __DIR__ . '/overview_work.php'; ?>
