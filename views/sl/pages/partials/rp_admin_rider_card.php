<?php
/**
 * Selected rider card: vehicle (payload, load volume, cargo dimensions, plate, documents), current load,
 * route map, ordered stops with ETAs (or nearest-first), inventory on board and statistics
 * (time by category, working time per day). Input: $d['sel'] (rpv_selected), $rpTab.
 */
$s = $d['sel'] ?? null;
if (!$s): ?>
<section class="sl-card rp-card mut sm">Pick a rider to see their vehicle, load and route.</section>
<?php return; endif;
$v = $s['vehicle'];
$rpIsRoute = $s['source'] === 'route';
$rpRouteSt = (string) ($s['routeStatus'] ?? '');
$rpRouteLabels = ['planned' => ['Planned', 'plain'], 'loading' => ['Loading', 'sorting'], 'onroute' => ['On the way', 'onway'], 'done' => ['Finished', 'delivered']];
?>
<section class="sl-card rp-card col g14" data-rp-card="<?= (int) $s['id'] ?>" style="--rc:<?= h($s['color']) ?>">
  <div class="row between g10 wrap rp-card-h">
    <div class="row g10"><span class="rp-av lg" aria-hidden="true"><?= h($s['initials']) ?></span><div class="col"><h2 class="sl-ct"><?= h($s['name']) ?></h2><span class="mut sm"><?= h($v['typeLabel']) ?><?= $v['make'] ? ' · ' . h($v['make']) : '' ?><?= $s['zone'] ? ' · ' . h($s['zone']) : '' ?><?= $s['phone'] ? ' · <a href="tel:' . h($s['phone']) . '">' . h($s['phone']) . '</a>' : '' ?></span></div></div>
    <div class="row g8 wrap"><span class="sl-pill <?= h($s['stCls']) ?>"><?= h($s['stLabel']) ?></span><?php if ($rpIsRoute && isset($rpRouteLabels[$rpRouteSt])): ?><span class="sl-pill <?= $rpRouteLabels[$rpRouteSt][1] ?>">Route: <?= $rpRouteLabels[$rpRouteSt][0] ?></span><?php elseif ($s['source'] === 'plan'): ?><span class="sl-pill sorting">Proposed</span><?php endif; ?><a class="sl-btn ghost sm" href="<?= h(sl_route('RiderDetail', ['id' => $s['id']])) ?>">Rider detail</a></div>
  </div>

  <div class="rp-veh">
    <div class="rp-veh-t col g4"><span class="up">Vehicle</span><b class="fw5"><?= h($v['typeLabel']) ?></b><span class="rp-plate mono"><?= $v['plate'] ? h($v['plate']) : 'No plate' ?></span><button type="button" class="sl-btn ghost sm" data-sl-open="rp-vehicle"><i class="ic ic-sm i-edit" aria-hidden="true"></i>Edit</button></div>
    <div class="rp-veh-g">
      <span><small>Payload</small><b class="mono"><?= $v['max_kg'] ? h(rtrim(rtrim(number_format($v['max_kg'], 1), '0'), '.')) . ' kg' : '–' ?></b></span>
      <span><small>Load volume</small><b class="mono"><?= $v['max_l'] ? h(number_format($v['max_l'])) . ' L' : '–' ?></b></span>
      <span><small>Parcels</small><b class="mono"><?= $v['max_n'] ?: '–' ?></b></span>
      <span><small>Cargo space</small><b class="mono"><?= $v['dims'] ? h($v['dims']) : '–' ?></b></span>
      <span class="rp-veh-can"><small>Can carry</small><span class="row g6 wrap"><?php foreach (['fragile' => 'fragile', 'cold' => 'cold', 'hv' => 'high_value'] as $flag => $hk): ?><i class="rp-hb h-<?= $hk ?><?= $v[$flag] ? '' : ' no' ?>" title="<?= $v[$flag] ? 'Takes' : 'Cannot take' ?> <?= h(RPV_HANDLING[$hk]) ?>"><?= $v[$flag] ? '' : '✕ ' ?><?= h(RPV_HANDLING[$hk]) ?></i><?php endforeach; ?></span></span>
    </div>
    <div class="rp-docs col g4"><span class="up">Documents</span><?php foreach ($v['docs'] as $doc): ?><span class="row between g6 sm"><span><?= h($doc['name']) ?> <span class="mut">· <?= h($doc['expires']) ?></span></span><span class="sl-pill <?= h($doc['state']) ?>"><?= h($doc['stLabel']) ?></span></span><?php endforeach; ?><?php if (!$v['docs']): ?><span class="mut sm">No licence, insurance or roadworthy on file. Upload them on the rider's profile.</span><?php endif; ?></div>
  </div>

  <div class="col g6"><div class="row between g8"><span class="up">Load<?= $s['source'] === 'plan' ? ' with the proposal' : '' ?></span><?php if ($s['locked']): ?><span class="sl-pill plain"><i class="ic ic-sm i-lock" aria-hidden="true"></i>Locked</span><?php endif; ?></div>
    <div class="rp-bars lg"><?php foreach ($s['bars'] as $b): ?><div class="rp-bar-row"><span class="rp-bl"><?= h($b['label']) ?></span><span class="rp-lb <?= h($b['cls']) ?>" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) min(100, $b['pct']) ?>" aria-label="<?= h($b['label']) ?>"><i style="width:<?= (int) $b['w'] ?>%"></i></span><span class="rp-bv mono"><?= h($b['text']) ?> · <?= (int) $b['pct'] ?>%</span></div><?php endforeach; ?></div>
  </div>

  <?php if ($s['alerts']): ?><div class="col g6"><?php foreach ($s['alerts'] as $a): ?><div class="sl-banner warn" role="status"><?= h($a['msg']) ?></div><?php endforeach; ?></div><?php endif; ?>

  <div class="col g8">
    <div class="row between g8 wrap"><div class="col"><span class="up">Route<?= $rpIsRoute ? '' : ($s['source'] === 'plan' ? ' · proposal' : '') ?></span><span class="sm"><?= $s['summary'] ? h($s['summary']) : 'No route today' ?><?php if ($s['stops'] && $s['provider'] !== 'google'): ?> <span class="mut">· distances estimated (straight line × road factor)</span><?php endif; ?></span></div>
      <?php if ($rpIsRoute && $rpRouteSt !== 'done'): ?><button type="button" class="sl-btn ghost sm" data-rp-reopt="<?= (int) $s['routeId'] ?>"><i class="ic ic-sm i-refresh" aria-hidden="true"></i>Re-optimise</button><?php endif; ?></div>
    <div class="rp-map" data-rp-map="sel" role="img" aria-label="Route map for <?= h($s['name']) ?>"><?php if (!$s['map'] || !$s['map']['stops']): ?><div class="rp-map-empty mut sm">No stops to map yet.</div><?php endif; ?></div>
  </div>

  <?php if ($s['stops']): ?>
  <div class="col g8" data-sl-tabs>
    <div class="row between g8 wrap"><span class="up">Stops · <?= count($s['stops']) ?></span><div class="sl-seg boxed sm" role="tablist"><button type="button" class="on" role="tab" aria-selected="true" data-sl-tab="rp-order">Route order</button><button type="button" role="tab" aria-selected="false" data-sl-tab="rp-near">Nearest first</button></div></div>
    <div class="col g8 rp-stops" data-sl-tabpanel="rp-order"<?= $s['source'] === 'plan' && ($rpTab ?? '') === 'plan' ? ' data-rp-drop="' . (int) $s['id'] . '"' : '' ?>>
      <?php foreach ($s['stops'] as $u) echo rpv_unitCard($u, ['drag' => $s['source'] === 'plan' && ($rpTab ?? '') === 'plan', 'from' => $s['id'], 'move' => $s['source'] === 'plan' && ($rpTab ?? '') === 'plan', 'removeRoute' => $rpIsRoute ? (int) $s['routeId'] : 0]); ?>
    </div>
    <div class="col g6 rp-near" data-sl-tabpanel="rp-near" hidden><span class="mut sm">Straight-line distance from the <?= h($s['nearFrom']) ?>, nearest to furthest.</span><?php foreach ($s['nearest'] as $i => $n): ?><div class="row g10 rp-near-r"><span class="rp-seq mono"><?= (int) $n['seq'] ?></span><span class="col grow"><b class="fw5"><?= h($n['label']) ?></b><span class="mut sm"><?= h($n['area']) ?></span></span><b class="mono"><?= h($n['dist']) ?></b></div><?php endforeach; ?><?php if (!$s['nearest']): ?><span class="mut sm">No open stops.</span><?php endif; ?></div>
  </div>
  <?php endif; ?>

  <?php if ($s['inventory']): $inv = $s['inventory']; ?>
  <div class="col g8 rp-inv"><div class="row between g8"><span class="up">On board</span><span class="mono sm"><?= (int) $inv['total'] ?> parcels · <?= h($inv['kg']) ?> kg · <?= h($inv['l']) ?> L</span></div>
    <div class="rp-inv-g"><?php foreach ($inv['cells'] as [$l, $n, $c]): ?><span class="rp-inv-c c-<?= h($c) ?><?= $n ? '' : ' zero' ?>"><b class="mono"><?= (int) $n ?></b><small><?= h($l) ?></small></span><?php endforeach; ?></div>
    <?php if ($inv['types']): ?><div class="row g6 wrap"><?php foreach ($inv['types'] as $t): ?><span class="sl-badge"><?= h($t['label']) ?> · <?= (int) $t['n'] ?></span><?php endforeach; ?></div><?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if ($s['stats']): $st = $s['stats']; ?>
  <div class="rp-stats">
    <div class="col g8"><div class="row between g8"><span class="up">Time by category · 7 days</span><span class="mono sm"><?= h($st['total']) ?></span></div>
      <?php foreach ($st['cats'] as $c): ?><div class="rp-cat"><span class="sm"><?= h($c['label']) ?></span><span class="rp-cat-b c-<?= h($c['cls']) ?>"><i style="width:<?= (int) $c['pct'] ?>%"></i></span><span class="mono sm"><?= h($c['dur']) ?></span></div><?php endforeach; ?>
      <div class="row g12 wrap sm mut"><span><b class="mono" style="color:var(--ink)"><?= (int) $st['delivered'] ?></b> delivered</span><span><b class="mono" style="color:var(--ink)"><?= (int) $st['failed'] ?></b> failed</span><span><b class="mono" style="color:var(--ink)"><?= h($st['distance']) ?></b> driven</span></div>
    </div>
    <div class="col g8"><span class="up">Working time per day</span>
      <div class="rp-days" role="list"><?php foreach ($st['days'] as $day): ?><div class="rp-day" role="listitem" title="<?= h($day['dow'] . ' ' . $day['date'] . ': ' . $day['label'] . ', ' . $day['stops'] . ' stops') ?>"><span class="rp-day-v mono"><?= $day['work'] ? h(round($day['work'] / 3600, 1)) . 'h' : '' ?></span><span class="rp-day-b"><i style="height:<?= (int) $day['h'] ?>%"></i></span><span class="rp-day-l"><?= h($day['dow']) ?></span></div><?php endforeach; ?></div>
    </div>
  </div>
  <?php endif; ?>
</section>
