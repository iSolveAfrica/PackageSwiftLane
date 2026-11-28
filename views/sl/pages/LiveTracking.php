<?php
/**
 * Live tracking: Bridges-style operations map. Entry: live_tracking.php.
 * One page, three personas:
 *   Staff / admin : every air shipment, sea package and consolidation in their scope,
 *                   on a city-style map with hub pins, live vehicle markers, a card rail
 *                   and a Loading / On Route / Delayed tally bar.
 *   Riders        : the same console personalised to the work assigned to them
 *                   (sl_opsScope limits rows to driver_id), with the customer to call.
 *   Customers     : their own parcels and consolidations.
 * Data: views/sl/data/track_console.php (sl_trackConsoleData).
 * Styles: assets/swiftlane/live-tracking.css · behaviour: assets/swiftlane/live-tracking.js
 */
if (!isset($d)) { $d = []; }
$tc = $d['tc'];
$client = !empty($d['client']);
$rider = !empty($d['rider']);
$items = $tc['items'];
$t = $tc['tally'];

$lvW = 1000;
$lvH = 620;

/** Hub positions on the city canvas: known lane hubs first, then free slots. */
$hubSlots = ['JFK' => [165, 148], 'NYC' => [150, 452], 'ACC' => [812, 208], 'TEM' => [838, 472]];
$freeSlots = [[470, 120], [520, 520], [310, 320], [690, 350], [260, 540], [900, 330]];
$hubs = [];
foreach ($items as $it) {
    foreach ([['h' => $it['from'], 'in' => 0], ['h' => $it['to'], 'in' => 1]] as $e) {
        $c = $e['h']['code'];
        if (!isset($hubs[$c])) {
            $hubs[$c] = ['code' => $c, 'city' => $e['h']['city'], 'n' => 0, 'xy' => $hubSlots[$c] ?? array_shift($freeSlots) ?? [500, 310]];
        }
        if ($e['in']) $hubs[$c]['n']++;
    }
}

/** Street-style dogleg route between two hubs; offset per index so lanes don't overlap. */
function lv_route(array $a, array $b, int $i): array
{
    $xm = round($a[0] + ($b[0] - $a[0]) * (0.34 + 0.09 * ($i % 4)), 1);
    $ym = round(min($a[1], $b[1]) + abs($b[1] - $a[1]) * (0.30 + 0.12 * ($i % 3)), 1);
    $pts = [$a, [$xm, $a[1]], [$xm, $ym], [$b[0], $ym], $b];
    // drop zero-length segments
    $out = [$pts[0]];
    foreach ($pts as $p) { $l = end($out); if ($p[0] != $l[0] || $p[1] != $l[1]) $out[] = $p; }
    return $out;
}

function lv_poly(array $pts): array
{
    $d = 'M' . $pts[0][0] . ' ' . $pts[0][1];
    $len = 0.0;
    $segs = [];
    for ($i = 1; $i < count($pts); $i++) {
        $d .= ' L' . $pts[$i][0] . ' ' . $pts[$i][1];
        $l = sqrt(($pts[$i][0] - $pts[$i - 1][0]) ** 2 + ($pts[$i][1] - $pts[$i - 1][1]) ** 2);
        $segs[] = [$pts[$i - 1], $pts[$i], $l];
        $len += $l;
    }
    return ['d' => $d, 'len' => $len ?: 1, 'segs' => $segs];
}

/** Point at fraction f (0..1) along the polyline + the partial path up to it. */
function lv_at(array $poly, float $f): array
{
    $want = max(0, min(1, $f)) * $poly['len'];
    $acc = 0.0;
    $done = '';
    foreach ($poly['segs'] as [$a, $b, $l]) {
        if ($done === '') $done = 'M' . $a[0] . ' ' . $a[1];
        if ($want <= $acc + $l) {
            $t = $l > 0 ? ($want - $acc) / $l : 0;
            $x = round($a[0] + ($b[0] - $a[0]) * $t, 1);
            $y = round($a[1] + ($b[1] - $a[1]) * $t, 1);
            return [$x, $y, $done . ' L' . $x . ' ' . $y];
        }
        $done .= ' L' . $b[0] . ' ' . $b[1];
        $acc += $l;
    }
    $e = end($poly['segs']);
    return [$e[1][0], $e[1][1], $done];
}

/** Card / marker state in the Bridges vocabulary. */
function lv_state(array $it): array
{
    if ($it['arrived']) return ['done', 'Arrived'];
    if ($it['late']) return ['late', 'Delayed'];
    if ($it['pct'] <= 8) return ['loading', 'Loading'];
    return ['onroute', 'On Route'];
}

$geo = [];
$tally = ['loading' => 0, 'onroute' => 0, 'late' => 0, 'done' => 0];
foreach ($items as $i => $it) {
    $poly = lv_poly(lv_route($hubs[$it['from']['code']]['xy'], $hubs[$it['to']['code']]['xy'], $i));
    $f = $it['arrived'] ? 1.0 : max(0.05, min(0.96, $it['pct'] / 100));
    [$vx, $vy, $donePath] = lv_at($poly, $f);
    [$cls, $label] = lv_state($it);
    $geo[$it['key']] = ['d' => $poly['d'], 'done' => $donePath, 'vx' => $vx, 'vy' => $vy, 'cls' => $cls, 'label' => $label];
    $tally[$cls === 'late' ? 'late' : $cls]++;
}
$first = $items[0]['key'] ?? '';
$lede = $rider
    ? 'The deliveries assigned to you right now, on one map. Tap a job to see its route, then call or message the customer.'
    : ($client
        ? 'Every parcel you ship with us, live on the map. Pick one to see where it is.'
        : 'Every air shipment, sea package and consolidation you can see, live on one map. Search a tracking number, customer, locker or container.');
$chips = $rider
    ? ['all' => 'All my jobs', 'late' => 'Delayed', 'ready' => 'Arrived', 'done' => 'Delivered']
    : ['all' => 'All', 'air' => 'Air', 'sea' => 'Sea', 'con' => 'Consolidations', 'late' => 'Delayed', 'ready' => 'Arrived', 'done' => 'Delivered'];
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/live-tracking.css')) ?>">
<script defer src="<?= h(cdp_asset('assets/swiftlane/live-tracking.js')) ?>"></script>

<div class="sl-head"><div><h1 class="sl-title row g8" style="font-size:22px"><i class="ic i-route" aria-hidden="true"></i><?= $rider ? 'My route' : ($client ? 'Track my parcels' : 'Live tracking') ?></h1>
<p class="sl-lede"><?= h($lede) ?></p></div></div>

<div class="lv" data-lv>
  <div class="lv-map" aria-label="Live map">
    <svg class="lv-svg" data-lv-svg viewBox="0 0 <?= $lvW ?> <?= $lvH ?>" preserveAspectRatio="xMidYMid slice" role="img" aria-label="Map of everything moving">
      <g data-lv-pan>
      <rect x="-400" y="-400" width="<?= $lvW + 800 ?>" height="<?= $lvH + 800 ?>" class="lv-bg"/>
      <!-- water, parks, blocks -->
      <path class="lv-water" d="M-60 330 C 120 290, 230 370, 370 335 S 560 250, 690 292 S 880 365, 1060 322 L 1060 392 C 880 435, 740 345, 690 350 S 500 312, 370 392 S 120 358, -60 400 Z"/>
      <g class="lv-park"><rect x="70" y="40" width="110" height="80" rx="8"/><rect x="430" y="60" width="80" height="100" rx="8"/><rect x="730" y="50" width="130" height="70" rx="8"/><rect x="170" y="500" width="120" height="60" rx="8"/><rect x="600" y="490" width="90" height="70" rx="8"/><rect x="880" y="420" width="90" height="80" rx="8"/></g>
      <g class="lv-block"><rect x="250" y="70" width="70" height="46" rx="5"/><rect x="340" y="150" width="60" height="60" rx="5"/><rect x="560" y="110" width="80" height="50" rx="5"/><rect x="650" y="190" width="56" height="56" rx="5"/><rect x="90" y="230" width="66" height="48" rx="5"/><rect x="480" y="420" width="70" height="52" rx="5"/><rect x="330" y="470" width="58" height="50" rx="5"/><rect x="760" y="300" width="64" height="44" rx="5"/></g>
      <!-- roads -->
      <g class="lv-road lg"><path d="M-60 140H1060"/><path d="M-60 250H1060"/><path d="M-60 440H1060"/><path d="M210 -40V660"/><path d="M420 -40V660"/><path d="M640 -40V660"/><path d="M860 -40V660"/></g>
      <g class="lv-road sm"><path d="M-60 70H1060M-60 200H1060M-60 520H1060M-60 580H1060M120 -40V660M310 -40V660M530 -40V660M740 -40V660M950 -40V660"/></g>
      <path class="lv-road dg" d="M-40 610 L 330 300 L 560 250 L 1040 60"/>

      <!-- routes + vehicles -->
      <?php foreach ($items as $it): $g = $geo[$it['key']]; ?>
      <g class="lv-geo<?= $it['key'] === $first ? ' on' : '' ?>" data-lv-geo="<?= h($it['key']) ?>">
        <path class="lv-rt" d="<?= h($g['d']) ?>"/>
        <path class="lv-dn <?= h($g['cls']) ?>" d="<?= h($g['done']) ?>"/>
      </g>
      <?php endforeach; ?>

      <!-- hubs -->
      <?php foreach ($hubs as $hb): [$hx, $hy] = $hb['xy']; ?>
      <g class="lv-hub" transform="translate(<?= $hx ?> <?= $hy ?>)">
        <circle r="17" class="halo"/>
        <rect x="-13" y="-13" width="26" height="26" rx="8" class="bx"/>
        <path class="gl" d="M-6 3v-5l6-3.4L6 -2v5h-3.4v-3.2h-5.2V3Z"/>
        <g class="lv-hublab" transform="translate(0 -24)"><rect x="-44" y="-14" width="88" height="20" rx="10"/><text y="0"><?= h($hb['code']) ?> Hub<?= $hb['n'] ? ' · ' . (int) $hb['n'] : '' ?></text></g>
      </g>
      <?php endforeach; ?>

      <!-- vehicle pins (on top of hubs) -->
      <?php foreach ($items as $it): $g = $geo[$it['key']]; ?>
      <g class="lv-veh <?= h($g['cls']) ?><?= $it['key'] === $first ? ' on' : '' ?>" data-lv-pin="<?= h($it['key']) ?>" transform="translate(<?= $g['vx'] ?> <?= $g['vy'] ?>)" tabindex="0" role="button" aria-label="<?= h($it['code'] . ' · ' . $g['label']) ?>">
        <circle r="18" class="pulse"/>
        <circle r="11" class="ring"/>
        <circle r="8.5" class="core"/>
        <?php if ($it['mode'] === 'sea'): ?><path class="veh" d="M-4.5 -1.5h5l3 1.5-3 2h-5l-1.5-2z"/><?php else: ?><path class="veh" d="M-4.5 -2.5h5v3.4h-5zM.9 -1.3h2l1.7 1.6v1.1H.9z"/><circle class="veh" cx="-2.6" cy="2.3" r="1"/><circle class="veh" cx="2.2" cy="2.3" r="1"/><?php endif; ?>
      </g>
      <?php endforeach; ?>
      </g>
    </svg>

    <div class="lv-zoom" role="group" aria-label="Map zoom">
      <button type="button" data-lv-zoom="in" aria-label="Zoom in"><i class="ic ic-sm i-plus" aria-hidden="true"></i></button>
      <button type="button" data-lv-zoom="out" aria-label="Zoom out"><i class="ic ic-sm i-minus" aria-hidden="true"></i></button>
      <button type="button" data-lv-zoom="fit" aria-label="Reset the map view"><i class="ic ic-sm i-expand" aria-hidden="true"></i></button>
    </div>

    <div class="lv-tally" aria-label="Fleet status">
      <a class="<?= $d['filter'] === 'loading' ? 'on' : '' ?>" href="<?= h(sl_url(['f' => 'loading'])) ?>"><i class="dot loading"></i>Loading<b><?= (int) $tally['loading'] ?></b></a>
      <a class="<?= $d['filter'] === 'transit' ? 'on' : '' ?>" href="<?= h(sl_url(['f' => 'transit'])) ?>"><i class="dot onroute"></i>On Route<b><?= (int) $tally['onroute'] ?></b></a>
      <a class="<?= $d['filter'] === 'late' ? 'on' : '' ?>" href="<?= h(sl_url(['f' => 'late'])) ?>"><i class="dot late"></i>Delayed<b><?= (int) ($tc['counts']['late'] ?: $tally['late']) ?></b></a>
      <?php if ($tally['done']): ?><a class="<?= $d['filter'] === 'done' ? 'on' : '' ?>" href="<?= h(sl_url(['f' => 'done'])) ?>"><i class="dot done"></i>Arrived<b><?= (int) $tally['done'] ?></b></a><?php endif; ?>
    </div>
  </div>

  <aside class="lv-side">
    <form method="get" class="lv-q" role="search">
      <i class="ic ic-sm i-search" aria-hidden="true"></i>
      <input name="q" aria-label="Search all tracking" placeholder="<?= $rider ? 'Search my jobs' : ($client ? 'Tracking or store tracking' : 'Tracking, customer, locker, container…') ?>" value="<?= h($d['q']) ?>">
      <?php if ($d['filter'] !== 'all'): ?><input type="hidden" name="f" value="<?= h($d['filter']) ?>"><?php endif; ?>
      <?php if ($d['q'] !== ''): ?><a class="lv-x" href="<?= h(sl_url(['q' => null])) ?>" aria-label="Clear search"><i class="ic ic-sm i-x" aria-hidden="true"></i></a><?php endif; ?>
    </form>
    <nav class="lv-chips" aria-label="Show">
      <?php foreach ($chips as $fk => $fl): if ($fk === 'con' && !isset($t['air_con']) && !isset($t['sea_con'])) continue; ?>
      <a class="<?= $d['filter'] === $fk ? 'on' : '' ?>" href="<?= h(sl_url(['f' => $fk === 'all' ? null : $fk])) ?>"<?= $d['filter'] === $fk ? ' aria-current="true"' : '' ?>><?= h($fl) ?></a>
      <?php endforeach; ?>
    </nav>
    <p class="lv-found mut sm"><?= $d['q'] !== '' ? 'Results for “' . h($d['q']) . '” · ' : '' ?><?= $tc['shown'] < $tc['total'] ? 'Newest ' . sl_num($tc['shown']) . ' of ' . sl_num($tc['total']) : sl_num($tc['total']) . ' found' ?></p>

    <div class="lv-cards" role="listbox" aria-label="<?= $rider ? 'My deliveries' : 'Everything moving' ?>">
      <?php foreach ($items as $it):
          $g = $geo[$it['key']];
          $who = $rider ? ($it['sender'] ?? null) : ($it['driver'] ?? null);
          $whoRole = $rider ? 'Customer' : 'Rider';
      ?>
      <div class="lv-card<?= $it['key'] === $first ? ' on' : '' ?>" role="option" tabindex="0" aria-selected="<?= $it['key'] === $first ? 'true' : 'false' ?>" data-lv-key="<?= h($it['key']) ?>">
        <div class="lv-r1">
          <span class="lv-tile"><i class="ic <?= $it['mode'] === 'sea' ? 'i-ship' : ($it['kind'] === 'consolidation' ? 'i-layers' : 'i-plane') ?>" aria-hidden="true"></i></span>
          <span class="col"><b class="mono">#<?= h($it['code']) ?></b><small><?= h($it['modeL'] . ' ' . strtolower($it['kindL'])) ?><?= $client || $rider ? '' : ' · ' . h($it['customer']) ?></small></span>
          <span class="lv-st <?= h($g['cls']) ?>"><i></i><?= h($g['label']) ?></span>
        </div>
        <div class="lv-route">
          <span class="col"><span class="lv-pt"><i class="ic ic-sm i-pin" aria-hidden="true"></i><b><?= h($it['from']['code']) ?></b></span><small><?= h($it['from']['city']) ?></small></span>
          <span class="lv-dash" aria-hidden="true"><i class="ln"></i><i class="dot <?= h($g['cls']) ?>"></i><i class="ln"></i></span>
          <span class="col r"><span class="lv-pt r"><b><?= h($it['to']['code']) ?></b><i class="ic ic-sm i-flag" aria-hidden="true"></i></span><small><?= h($it['to']['city']) ?></small></span>
        </div>
        <div class="lv-meta">
          <span><i class="ic ic-sm i-clock" aria-hidden="true"></i>ETA: <b><?= h($it['arrived'] ? 'Arrived' : ($it['etaShort'] === '—' ? '–' : $it['etaShort'])) ?></b></span>
          <span class="r"><i class="ic ic-sm i-gauge" aria-hidden="true"></i><?= (int) $it['pct'] ?>% · <?= h($it['onway']) ?></span>
        </div>
        <div class="lv-prog" role="img" aria-label="<?= (int) $it['pct'] ?>% of the route covered"><i class="<?= h($g['cls']) ?>" style="width:<?= max(3, min(100, (int) $it['pct'])) ?>%"></i></div>
        <?php if ($who): ?>
        <div class="lv-who">
          <span class="sl-av"><?= h(sl_initials($who['name'])) ?></span>
          <span class="col grow"><b class="fw5"><?= h($who['name']) ?></b><small class="mut"><?= h($whoRole) ?></small></span>
          <?php if (!$client): ?>
          <a class="lv-ib" href="<?= h(sl_route('ComposeMessage', ['user' => $who['id'], 'order' => $it['code']])) ?>" aria-label="Message <?= h($whoRole) ?>" title="Message"><i class="ic ic-sm i-message" aria-hidden="true"></i></a>
          <?php if ($who['phone'] !== ''): ?><a class="lv-ib" href="tel:<?= h($who['phone']) ?>" aria-label="Call <?= h($whoRole) ?>" title="Call"><i class="ic ic-sm i-phone" aria-hidden="true"></i></a><?php endif; ?>
          <?php endif; ?>
        </div>
        <?php elseif (!$client && !$rider): ?>
        <div class="lv-who none"><span class="sl-av mut">–</span><span class="col grow"><b class="fw5 mut">No rider assigned</b><small class="mut">Assign one from the record</small></span></div>
        <?php endif; ?>
        <a class="lv-open" href="<?= h($it['href']) ?>">Open <?= h($rider ? 'job' : strtolower($it['kindL'])) ?> <i class="ic ic-sm i-right" aria-hidden="true"></i></a>
      </div>
      <?php endforeach; ?>
      <?php if (!$items): ?>
      <div class="sl-empty"><span class="sl-tile"><i class="ic i-route" aria-hidden="true"></i></span><?= $d['q'] !== '' ? 'Nothing matches “' . h($d['q']) . '”. Check the number, or try the customer name.' : ($rider ? 'No deliveries assigned to you right now. New jobs appear here when dispatch assigns them.' : ($client ? 'Nothing on the way right now. Parcels appear here as soon as they reach our warehouse.' : 'Nothing to show for this filter.')) ?></div>
      <?php endif; ?>
    </div>
  </aside>
</div>
