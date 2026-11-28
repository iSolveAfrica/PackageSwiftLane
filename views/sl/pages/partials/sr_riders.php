<?php
/**
 * Smart Routing, "Riders and vehicles": riders grouped by starred and vehicle type, the chosen rider's
 * vehicle card, today's route with a small map, earlier routes and statistics.
 * Input: $d['rv'] (srv_ridersView), $srAv. Only recorded figures are shown; nothing is filled in.
 */
$rv = $d['rv'] ?? ['groups' => [], 'sel' => null, 'count' => 0];
$s = $rv['sel'];
/* Side silhouettes, one per vehicle type (drawn here: no stock photos). */
$srVeh = [
  'bicycle' => '<circle cx="78" cy="118" r="34" class="w"/><circle cx="222" cy="118" r="34" class="w"/><path class="f" d="M78 118 124 58h70l28 60M124 58l32 60h66M156 118 190 46h22M112 46h30"/>',
  'motorbike' => '<circle cx="70" cy="120" r="32" class="w"/><circle cx="232" cy="120" r="32" class="w"/><path class="b" d="M96 110c6-28 30-44 62-44h36l22-20h20l-10 22 26 44h-30c-4-18-18-30-36-30-14 0-22 8-30 28Z"/><rect class="box" x="34" y="34" width="62" height="46" rx="8"/><path class="f" d="M214 46h24M70 120l34-34"/>',
  'car' => '<path class="b" d="M22 112c0-16 10-24 26-26l34-34c6-6 14-8 22-8h64c10 0 18 4 24 10l30 30 40 8c10 2 16 10 16 20v14c0 6-4 10-10 10H32c-6 0-10-4-10-10Z"/><path class="g" d="M92 84 116 58h34v26ZM160 58h10c6 0 10 2 14 6l18 20h-42Z"/><circle cx="82" cy="136" r="24" class="w"/><circle cx="216" cy="136" r="24" class="w"/>',
  'van' => '<path class="b" d="M14 44c0-8 6-14 14-14h150c10 0 18 4 24 12l32 40 36 10c8 2 14 10 14 18v20c0 6-4 10-10 10H24c-6 0-10-4-10-10Z"/><path class="g" d="M176 46h8c6 0 10 2 14 8l22 30h-44Z"/><path class="l" d="M168 34v100M96 34v100"/><circle cx="70" cy="140" r="24" class="w"/><circle cx="224" cy="140" r="24" class="w"/>',
  'truck' => '<rect class="b" x="10" y="22" width="170" height="104" rx="8"/><path class="b" d="M188 54h44c8 0 14 4 18 10l22 34c4 4 6 10 6 16v14c0 6-4 10-10 10h-80Z"/><path class="g" d="M204 68h24c4 0 8 2 10 6l14 22h-48Z"/><path class="l" d="M10 126h178"/><circle cx="58" cy="140" r="24" class="w"/><circle cx="118" cy="140" r="24" class="w"/><circle cx="234" cy="140" r="24" class="w"/>',
];
?>
<div class="sr-rv">
  <aside class="sl-card sr-rvlist" aria-label="Riders">
    <label class="sr-rvsearch"><i class="ic ic-sm i-search" aria-hidden="true"></i><input type="search" data-sr-rv-search placeholder="Search riders or vehicles" aria-label="Search riders or vehicles" autocomplete="off"></label>
    <div class="sr-rvgroups">
      <?php foreach ($rv['groups'] as $g): ?>
      <details class="sr-rvg" open data-sr-rvg="<?= h($g['key']) ?>">
        <summary><span><?php if ($g['star']): ?><i class="ic ic-sm i-star sr-star-on" aria-hidden="true"></i><?php endif; ?><?= h($g['label']) ?> <small><?= count($g['rows']) ?></small></span><i class="ic ic-sm i-up" aria-hidden="true"></i></summary>
        <?php foreach ($g['rows'] as $r): ?>
        <div class="sr-rvrow<?= $s && (int) $s['id'] === (int) $r['id'] ? ' sel' : '' ?>" data-search="<?= h(mb_strtolower($r['name'] . ' ' . $r['vehicle']['name'] . ' ' . $r['vehicle']['plate'])) ?>">
          <a href="<?= h($r['href']) ?>"<?= $s && (int) $s['id'] === (int) $r['id'] ? ' aria-current="true"' : '' ?>>
            <span class="sr-avwrap"><?= $srAv($r) ?><?php if ($r['online']): ?><i class="sr-online" title="Phone seen in the last few minutes"></i><?php endif; ?></span>
            <span class="sr-rider-t"><b><?= h($r['name']) ?></b><span class="mut"><?= h($r['vehicle']['name']) ?></span></span>
            <span class="sr-state st-<?= h($r['stateKey']) ?>"><?php if ($r['alert']): ?><i class="sr-alertdot" title="Needs attention"></i><span class="sl-chart-sr">Needs attention: </span><?php endif; ?><?= h($r['state']) ?></span>
          </a>
          <button type="button" class="sr-starbtn<?= $r['fav'] ? ' on' : '' ?>" data-sr-fav="<?= (int) $r['id'] ?>" aria-pressed="<?= $r['fav'] ? 'true' : 'false' ?>" aria-label="<?= $r['fav'] ? 'Remove ' . h($r['name']) . ' from favourites' : 'Add ' . h($r['name']) . ' to favourites' ?>"><i class="ic ic-sm i-star" aria-hidden="true"></i></button>
        </div>
        <?php endforeach; ?>
        <?php if (!$g['rows'] && $g['empty']): ?><p class="sr-rvnone mut"><?= h($g['empty']) ?></p><?php endif; ?>
      </details>
      <?php endforeach; ?>
      <p class="sr-rvnone mut" data-sr-rv-none hidden>No rider matches.</p>
      <?php if (!$rv['count']): ?><p class="sr-rvnone mut">No active riders yet. Riders are staff with the rider role; add one under Riders.</p><?php endif; ?>
    </div>
    <div class="sr-rvfoot"><button type="button" class="sl-btn block" data-sr-veh-open<?= $rv['count'] ? '' : ' disabled' ?>><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add vehicle</button></div>
  </aside>

  <?php if (!$s): ?>
  <section class="sl-card sr-rvmain"><div class="sr-emptybox">Pick a rider to see their vehicle, routes and statistics.</div></section>
  <?php else: $v = $s['vehicle']; $st = $s['stats']; ?>
  <section class="sl-card sr-rvmain" aria-label="<?= h($s['name']) ?>">
    <header class="sr-rvhead">
      <?= $srAv($s, 'lg') ?>
      <div class="grow"><h2 class="sr-h2"><?= h($s['name']) ?></h2><span class="mut">ID: <?= (int) $s['id'] ?><?= $s['zone'] ? ' · ' . h($s['zone']) : '' ?> · <?= h($s['stLabel']) ?></span></div>
      <?php if ($s['wa']): ?><a class="sr-round" href="<?= h($s['wa']) ?>" target="_blank" rel="noopener" aria-label="Message <?= h($s['name']) ?> on WhatsApp" title="Message on WhatsApp"><i class="ic ic-sm i-message" aria-hidden="true"></i></a><?php endif; ?>
      <?php if ($s['tel']): ?><a class="sr-round" href="<?= h($s['tel']) ?>" aria-label="Call <?= h($s['name']) ?>" title="Call <?= h($s['phone']) ?>"><i class="ic ic-sm i-phone" aria-hidden="true"></i></a><?php endif; ?>
      <a class="sr-round" href="<?= h($s['history']) ?>" aria-label="Open <?= h($s['name']) ?>'s full rider profile" title="Rider profile"><i class="ic ic-sm i-more" aria-hidden="true"></i></a>
    </header>

    <div class="sr-vcard" data-sr-vcard>
      <div class="sr-vinfo">
        <h3><?= h($v['name']) ?><?php if (!$v['active']): ?> <span class="sl-pill pending">Out of service</span><?php endif; ?></h3>
        <dl>
          <?php if ($v['max_kg']): ?><div><dt>Payload</dt><dd><?= h(srv_num($v['max_kg'])) ?> kg</dd></div><?php endif; ?>
          <?php if ($v['max_l']): ?><div><dt>Load volume</dt><dd><?= h(srv_num($v['max_l'], 0)) ?> L</dd></div><?php endif; ?>
          <?php if ($v['l']): ?><div><dt>Load length</dt><dd><?= (int) $v['l'] ?> cm</dd></div><?php endif; ?>
          <?php if ($v['w']): ?><div><dt>Load width</dt><dd><?= (int) $v['w'] ?> cm</dd></div><?php endif; ?>
          <?php if ($v['max_n']): ?><div><dt>Most pieces</dt><dd><?= (int) $v['max_n'] ?></dd></div><?php endif; ?>
        </dl>
        <div class="row g16 wrap">
          <?php if ($v['plate']): ?><span class="sr-plate" aria-label="Number plate <?= h($v['plate']) ?>"><small>GH</small><?= h($v['plate']) ?></span><?php else: ?><span class="sr-plate none">No plate saved</span><?php endif; ?>
          <a class="sr-dash" href="<?= h($s['docsUrl']) ?>">Documents<?= $v['docs'] ? ' (' . count($v['docs']) . ')' : '' ?></a>
          <button type="button" class="sr-dash" data-sr-veh-open="<?= (int) $s['id'] ?>">Edit vehicle</button>
        </div>
        <?php if (!$v['saved']): ?><p class="mut sr-fine">These are the standard figures for a <?= h($v['type']) ?>. Save this rider's own vehicle to plan with its real capacity.</p><?php endif; ?>
      </div>
      <svg class="sr-vsvg t-<?= h($v['type']) ?>" viewBox="0 0 300 180" role="img" aria-label="<?= h($v['typeLabel']) ?>"><ellipse cx="150" cy="166" rx="128" ry="7" class="sh"/><?= $srVeh[$v['type']] ?? $srVeh['motorbike'] ?></svg>
    </div>

    <div class="sr-rvcols">
      <div class="sr-rvcol">
        <div class="row between g8"><h3 class="sr-h3">Routes</h3><a class="sr-dash" href="<?= h($s['history']) ?>">History</a></div>
        <?php if ($n = $s['now']): ?>
        <div class="sr-cap"><span><?= h($n['heading']) ?></span></div>
        <a class="sr-rt" href="<?= h($n['url']) ?>"><b>ID: <?= (int) $n['id'] ?> · <?= (int) $n['packages'] ?> package<?= $n['packages'] === 1 ? '' : 's' ?></b><span class="mut"><?= h($n['from']) ?> <i class="ic ic-sm i-arrow" aria-hidden="true"></i> <?= h($n['to'] ?: 'last stop') ?> · <?= (int) $n['done'] ?> of <?= (int) $n['stops'] ?> stops done</span></a>
        <div class="sr-mini" data-sr-mini role="img" aria-label="Map of route <?= (int) $n['id'] ?>"></div>
        <dl class="sr-figs"><div><dt>Distance</dt><dd><?= h($n['distance']) ?></dd></div><div><dt>Time left</dt><dd><?= h($n['timeLeft']) ?></dd></div><div><dt>Weight</dt><dd><?= h($n['weight']) ?></dd></div><div><dt>Volume</dt><dd><?= h($n['volume']) ?></dd></div></dl>
        <?php else: ?>
        <div class="sr-cap"><span>Today</span></div>
        <div class="sr-emptybox">No route today. <a href="<?= h(sl_route('SmartRouting', ['rider' => (int) $s['id']])) ?>">Create one in the routing overview</a>.</div>
        <?php endif; ?>
        <?php if ($s['earlier']): ?><div class="sr-cap"><span>Earlier routes</span></div>
        <?php foreach ($s['earlier'] as $e): ?><a class="sr-rt" href="<?= h($e['url']) ?>"><b>ID: <?= (int) $e["id"] ?> · <?= $e["packages"] ? (int) $e["packages"] . " package" . ($e["packages"] === 1 ? "" : "s") : (int) $e["stops"] . " stop" . ($e["stops"] === 1 ? "" : "s") ?></b><span class="mut"><?= h($e['date']) ?> · <?= h($e['from']) ?> <i class="ic ic-sm i-arrow" aria-hidden="true"></i> <?= h($e['to']) ?> · <?= h($e['distance']) ?></span></a><?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="sr-rvcol" id="sr-stats">
        <div class="row between g8"><h3 class="sr-h3">Rider statistics</h3><a class="sr-round sm" href="<?= h(sl_route('RoutePlanner', ['tab' => 'timeline'])) ?>" aria-label="Open the day timeline in the route planner" title="Day timeline"><i class="ic ic-sm i-ext" aria-hidden="true"></i></a></div>
        <div class="row between g8"><div class="sr-cap plain"><span>Average time per day by category</span></div>
          <span class="sr-range" role="group" aria-label="Period"><?php foreach ($s['ranges'] as $rg): ?><a href="<?= h($rg['href']) ?>" class="<?= $rg['on'] ? 'on' : '' ?>" title="<?= h($rg['title']) ?>" aria-label="<?= h($rg['title']) ?>"<?= $rg['on'] ? ' aria-current="true"' : '' ?>><?= h($rg['label']) ?></a><?php endforeach; ?></span></div>
        <?php if ($st['empty']): ?>
        <div class="sr-emptybox">No working time was recorded for <?= h($s['name']) ?> from <?= h($st['period']) ?>. Time is recorded when a rider loads, starts and finishes a route in the rider app.</div>
        <?php else: ?>
        <div class="sr-stack" role="img" aria-label="Share of time: <?= h(implode(', ', array_map(fn($c) => $c['label'] . ' ' . $c['pct'] . '%', $st['cats']))) ?>">
          <?php foreach ($st['cats'] as $c): ?><span style="flex:<?= h($c['w']) ?> 1 0;--c:<?= h($c['color']) ?>"><small><?= h($c['label']) ?></small><b><?= h($c['pct']) ?>%</b></span><?php endforeach; ?>
        </div>
        <table class="sr-cats"><caption class="sl-chart-sr">Average time per worked day, <?= h($st['period']) ?></caption><tbody>
          <?php foreach ($st['cats'] as $c): ?><tr><th scope="row"><i style="background:<?= h($c['color']) ?>"></i><?= h($c['label']) ?></th><td><?= h($c['avg']) ?></td><td><?= h($c['pct']) ?>%</td></tr><?php endforeach; ?>
        </tbody></table>
        <p class="mut sr-fine"><?= h($st['period']) ?> · <?= (int) $st['workedDays'] ?> day<?= $st['workedDays'] === 1 ? '' : 's' ?> worked · <?= (int) $st['delivered'] ?> delivered, <?= (int) $st['failed'] ?> not delivered · <?= h($st['distance']) ?> driven. Unloading time is not recorded by the rider app, so it is not shown.</p>

        <div class="sr-cap plain"><span>Working time per <?= h($st['unit']) ?></span></div>
        <div class="sr-work">
          <div class="sr-bars n<?= count($st['bars']) > 14 ? 'many' : 'few' ?>" <?= $st['chart'] ?> role="img" aria-label="Working time per <?= h($st['unit']) ?>, average <?= h($st['avgText']) ?>" tabindex="0">
            <?php foreach ($st['bars'] as $b): ?><span class="sr-bar" title="<?= h($b['label'] . ': ' . $b['text'] . ' · average ' . $st['avgText']) ?>"><i class="avg" style="height:<?= (int) $st['avgH'] ?>%"></i><i class="val" style="height:<?= (int) $b['h'] ?>%"></i><small><?= h($b['short']) ?></small></span><?php endforeach; ?>
          </div>
          <div class="sr-axis" aria-hidden="true"><span><?= (int) $st['axis'] ?> h</span><span><?= h(srv_num($st['axis'] / 2)) ?></span><span>0</span></div>
        </div>
        <div class="sr-keys"><span><i class="k-val"></i>Working time</span><span><i class="k-avg"></i>Average (<?= h($st['avgText']) ?>)</span></div>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>
</div>
