<?php
/**
 * Overview top: header with actions, view bar, "you" card, today cards and the freight section.
 * Data: views/sl/data/overview_hero.php ($d['view'], $d['profile'], $d['cards'], $d['freight']).
 * Styles: assets/swiftlane/overview-hero.css.
 */
$hv = $d['view']; $hp = $d['profile']; $hc = $d['cards']; $hf = $d['freight'];
$canAddCon = $user->cdp_hasPermission(['add_consolidate_shipment', 'add_consolidate_package']);
$canFin = function_exists('cdp_financeEnabled') && cdp_financeEnabled() && $user->cdp_hasPermission('financial_sheet') && cdp_canViewMoney($user);
$bars = function (array $bars, string $cls = '') {
    if (!$bars) return '';
    $o = '<div class="ovh-bars ' . h($cls) . '" role="img" aria-label="Last 14 days">';
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

<div class="ovh-wrap"><div class="ovh-grid<?= count($hc) <= 2 ? ' few' : '' ?>">
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
    <?php if ($hp['logOn']): ?>
    <div class="ovh-me-mid">
      <div class="ovh-ring" style="--p:<?= (int) round($hp['active'] / 30 * 100) ?>" role="img" aria-label="Active on <?= (int) $hp['active'] ?> of the last 30 days"><b><?= (int) $hp['active'] ?></b><span>active days<br>of 30</span></div>
      <dl class="ovh-stats">
        <div><dt>Today</dt><dd><b><?= sl_num($hp['today']) ?></b> actions</dd></div>
        <div><dt>This week</dt><dd><b><?= sl_num($hp['week']) ?></b><?php if ($hp['weekDelta'] !== null): ?> <span class="<?= $hp['weekDelta'] < 0 ? 'dn' : 'up' ?>"><?= $hp['weekDelta'] < 0 ? '▼' : '▲' ?> <?= abs($hp['weekDelta']) ?>%</span><?php endif; ?></dd></div>
        <div><dt>Streak</dt><dd><b><?= (int) $hp['streak'] ?></b> day<?= (int) $hp['streak'] === 1 ? '' : 's' ?> in a row</dd></div>
        <div><dt>I registered · <?= h($hv['period'] === 'custom' ? 'in range' : strtolower($hv['label'])) ?></dt><dd><b><?= sl_num($hp['mine']) ?></b> <?= $hv['mode'] === 'sea' ? 'packages' : 'shipments' ?></dd></div>
      </dl>
    </div>
    <div class="ovh-me-bars">
      <div class="row between sm"><span class="mut">My actions, 14 days<?= !empty($hp['perDay']) ? ' · ' . (int) $hp['perDay'] . ' a working day' : '' ?></span></div>
      <?= $bars($hp['bars'], 'violet') ?>
    </div>
    <?php endif; ?>
    <?php if ($hp['chips']): ?>
    <div class="ovh-chips">
      <?php foreach ($hp['chips'] as $c): ?><a href="<?= h($c['href']) ?>"><i class="ic ic-sm <?= h($c['icon']) ?>" aria-hidden="true"></i><?= h($c['l']) ?> <b><?= sl_num($c['n']) ?></b></a><?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <?php foreach ($hc as $c): ?>
  <a class="ovh-card t-<?= h($c['tone']) ?>" href="<?= h($c['href']) ?>">
    <div class="ovh-ch"><span class="ovh-eyebrow"><i class="ic ic-sm <?= h($c['icon']) ?>" aria-hidden="true"></i><?= h($c['eyebrow']) ?></span><?php if (!empty($c['alert'])): ?><span class="ovh-alert"><i class="ic ic-sm i-alert" aria-hidden="true"></i><?= h($c['alert']) ?></span><?php else: ?><i class="ic ic-sm i-expand ovh-go" aria-hidden="true"></i><?php endif; ?></div>
    <b class="ovh-big"><?= h($c['big']) ?></b>
    <span class="ovh-lede"><?= h($c['lede']) ?></span>
    <?php if ($c['sub']): ?><div class="ovh-sub" style="--n:<?= count($c['sub']) ?>"><?php foreach ($c['sub'] as $s): ?><span><b><?= h($s['n']) ?></b><small><?= h($s['l']) ?></small></span><?php endforeach; ?></div><?php endif; ?>
    <?= $bars($c['bars']) ?>
    <div class="ovh-foot"><span><?= h($c['barsL']) ?></span><span><?= h($c['barsR']) ?></span></div>
  </a>
  <?php endforeach; ?>
</div></div>

<?php if ($hf):
    $n = count($hf['cur']);
    $L = 40; $R = 748; $T = 16; $B = 186;
    $step = ($R - $L) / max(1, $n);
    $bw = min(26, $step * .6);
    $prior = '';
    foreach ($hf['prev'] as $i => $v) $prior .= ($i ? ' L' : 'M') . round($L + $step * ($i + .5), 1) . ',' . round($B - ($B - $T) * $v / $hf['peak'], 1);
    $ticks = array_values(array_unique([0, (int) round($hf['peak'] / 2), (int) $hf['peak']]));
    $xl = array_values(array_unique([0, (int) floor(($n - 1) / 3), (int) floor(2 * ($n - 1) / 3), $n - 1]));
?>
<section class="ovh-freight" aria-label="<?= h($hf['title']) ?>">
  <div class="ovh-fl">
    <span class="ovh-eyebrow"><i class="ic ic-sm i-layers" aria-hidden="true"></i><?= h($hf['title']) ?></span>
    <b class="ovh-huge"><?= sl_num($hf['total']) ?></b>
    <?php if ($hf['change'] !== null && (float) $hf['change'] === 0.0): ?><p class="ovh-delta"><span class="eq">= 0%</span> same as <?= h($hf['prevLabel']) ?></p>
    <?php elseif ($hf['change'] !== null): ?><p class="ovh-delta"><span class="<?= $hf['change'] < 0 ? 'dn' : 'up' ?>"><?= $hf['change'] < 0 ? '↘' : '↗' ?> <?= abs($hf['change']) ?>%</span> <?= $hf['change'] < 0 ? 'below' : 'above' ?> <?= h($hf['prevLabel']) ?></p>
    <?php else: ?><p class="ovh-delta mut">Nothing registered in <?= h($hf['prevLabel']) ?></p><?php endif; ?>
    <p class="mut">Registered <?= h($hf['rangeLabel']) ?> · <?= sl_num($hf['book']) ?> on the whole book</p>
    <?php if ($hf['showSplit']): ?>
    <div class="ovh-split" role="img" aria-label="Air <?= (int) $hf['airPct'] ?>%, sea <?= (int) $hf['seaPct'] ?>%"><i class="air" style="width:<?= (int) $hf['airPct'] ?>%"></i><i class="sea" style="width:<?= (int) $hf['seaPct'] ?>%"></i></div>
    <div class="ovh-legend"><span><i class="air"></i>Air <?= (int) $hf['airPct'] ?>% · <?= sl_num($hf['air']) ?></span><span><i class="sea"></i>Sea <?= (int) $hf['seaPct'] ?>% · <?= sl_num($hf['sea']) ?></span></div>
    <?php endif; ?>
  </div>
  <div class="ovh-fr">
    <div class="row between wrap g8"><b class="fw5">Registrations per <?= h($hf['unit']) ?></b><div class="ovh-legend"><span><i class="cur"></i>This period</span><span><i class="prev"></i>Previous period</span></div></div>
    <svg class="ovh-chart" <?= sl_chartAttrs([['name' => 'This period', 'values' => $hf['cur'], 'color' => 'var(--sky)'], ['name' => 'Previous period', 'values' => $hf['prev'], 'color' => 'var(--yellow)']], $hf['labels'], 'Registrations', $L + $step / 2, $R - $step / 2) ?> viewBox="0 0 760 214" role="img" aria-label="Registrations per <?= h($hf['unit']) ?> compared with the previous period">
      <?php foreach ($ticks as $t): $y = $B - ($B - $T) * $t / $hf['peak']; ?><line class="g" x1="<?= $L ?>" x2="<?= $R ?>" y1="<?= round($y, 1) ?>" y2="<?= round($y, 1) ?>"/><text class="ax" x="<?= $L - 8 ?>" y="<?= round($y + 4, 1) ?>" text-anchor="end"><?= sl_num($t) ?></text><?php endforeach; ?>
      <?php foreach ($hf['cur'] as $i => $v): $hh = ($B - $T) * $v / $hf['peak']; ?><rect class="b" x="<?= round($L + $step * ($i + .5) - $bw / 2, 1) ?>" y="<?= round($B - $hh, 1) ?>" width="<?= round($bw, 1) ?>" height="<?= round(max($hh, $v ? 2 : 0), 1) ?>" rx="3"><title><?= h($hf['labels'][$i]) ?>: <?= (int) $v ?></title></rect><?php endforeach; ?>
      <path class="p" d="<?= h($prior) ?>"/>
      <?php foreach ($xl as $i): ?><text class="ax" x="<?= round($L + $step * ($i + .5), 1) ?>" y="208" text-anchor="middle"><?= h($hf['labels'][$i]) ?></text><?php endforeach; ?>
    </svg>
  </div>
</section>
<?php endif; ?>
