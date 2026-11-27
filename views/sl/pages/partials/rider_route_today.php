<?php
/**
 * Today's route for the rider app: map (SLRouteMap, schematic offline), the selected stop card
 * with the big buttons, the load meter, the ordered stop list (route order or nearest first) and
 * today's time by activity. Input: $rv (sl_riderRouteView), $d (sl_driverAppData), $rrMode 'home'|'route'.
 * Phone: one column (map strip, stop, load, list, stats). Web layout: map + list left, stop/load/stats right.
 */
$rrMode = $rrMode ?? 'home';
$inv = $rv['inventory'];
$stats = $d['rpStats'] ?? null;
?>
<div class="rr-grid rr-mode-<?= h($rrMode) ?>" data-rr-grid>
<div class="rr-col-main">
  <section class="rr-map-card" aria-label="Route map">
    <div class="rr-map" data-rr-map role="region" aria-label="Map of today's stops"><div class="rr-map-wait mut sm">Loading map…</div></div>
    <div class="rr-map-meta">
      <span><b><?= h($rv['left_km']) ?></b><small>to go</small></span>
      <span><b><?= h($rv['left_time']) ?></b><small>time left</small></span>
      <span><b><?= (int) $rv['open'] ?></b><small>stops left</small></span>
      <span><b><?= h($rv['finish_eta'] ?: '—') ?></b><small>finish</small></span>
    </div>
    <?php if ($rv['estimate']): ?><p class="rr-est mut sm">Distances and times are estimates (straight line × road factor at city speed). Use Navigate for live directions.</p><?php endif; ?>
  </section>

  <section class="rr-stops da-sheet" aria-label="Stops in order">
    <div class="rr-stops-head">
      <h2 class="sl-ct">Stops <small class="mut"><?= (int) $rv['progress']['done'] ?>/<?= (int) $rv['progress']['total'] ?> done</small></h2>
      <div class="rr-seg" role="group" aria-label="Sort stops">
        <button type="button" class="on" data-rr-sort="route" aria-pressed="true">Route order</button>
        <button type="button" data-rr-sort="near" aria-pressed="false">Nearest first</button>
      </div>
    </div>
    <ol class="rr-list" data-rr-list>
      <?php foreach ($rv['stops'] as $s): ?>
      <li class="rr-li st-<?= h($s['status']) ?><?= $rv['next'] && $rv['next']['id'] === $s['id'] ? ' is-sel' : '' ?>" data-rr-li="<?= (int) $s['id'] ?>" data-seq="<?= (int) $s['seq'] ?>" data-lat="<?= h((string) $s['lat']) ?>" data-lng="<?= h((string) $s['lng']) ?>" data-open="<?= $s['open'] ? 1 : 0 ?>" data-near="<?= (int) $s['near_rank'] ?>">
        <button type="button" class="rr-li-btn" data-rr-pick="<?= (int) $s['id'] ?>" aria-controls="rr-stop-<?= (int) $s['id'] ?>">
          <span class="rr-seq st-<?= h($s['status']) ?>"><?= (int) $s['seq'] ?></span>
          <span class="rr-li-m"><b><?= h($s['name']) ?></b><small><?= h($s['area'] ?: $s['address']) ?> · <?= (int) $s['n'] ?> parcel<?= $s['n'] === 1 ? '' : 's' ?><?php if ($s['handling']): ?> · <?= h(implode(', ', array_map(fn($x) => str_replace('_', ' ', $x), $s['handling']))) ?><?php endif; ?></small></span>
          <span class="rr-li-r">
            <?php if ($s['open']): ?><b data-rr-eta="<?= (int) $s['id'] ?>"><?= h($s['eta']) ?></b><small data-rr-away="<?= (int) $s['id'] ?>"><?= $s['near_m'] !== null ? h(sl_rrDistance((int) $s['near_m'])) . ' away' : h($s['leg']) ?></small>
            <?php else: ?><span class="sl-pill <?= h($s['cls']) ?>"><?= h($s['status_label']) ?></span><?php endif; ?>
          </span>
        </button>
      </li>
      <?php endforeach; ?>
    </ol>
    <?php if (!$rv['done'] && $rv['open'] > 1): ?>
    <div class="rr-reopt" data-rr-behind hidden role="status"><i class="ic ic-sm i-clock" aria-hidden="true"></i><span>You are running behind the plan.</span></div>
    <button type="button" class="rr-btn ghost" data-rr-op="reoptimise"><i class="ic i-refresh" aria-hidden="true"></i>Re-optimise from my position</button>
    <?php endif; ?>
  </section>
</div>

<aside class="rr-col-side">
  <?php if ($rv['done']): ?>
  <section class="rr-done da-sheet" aria-label="Route finished"><b class="fw5">Route finished<?= $rv['started_at'] ? ' · started ' . h($rv['started_at']) : '' ?></b><span class="mut sm"><?= (int) $inv['delivered'] ?> delivered · <?= (int) ($inv['failed'] + $inv['returned']) ?> to return to the office.</span></section>
  <?php elseif (!$rv['onroute']): ?>
  <section class="rr-start-cta" aria-label="Loading">
    <div><span class="up">Before you go</span><b><?= (int) $inv['loaded'] ?> of <?= (int) $inv['total'] ?> parcels loaded</b><small>Tick or scan each parcel, then start the route.</small></div>
    <a class="rr-btn pri" href="<?= h(sl_route('DriverApp', ['tab' => 'load'])) ?>"><i class="ic i-scan" aria-hidden="true"></i>Open loading checklist</a>
  </section>
  <?php endif; ?>

  <div class="rr-cards" data-rr-cards>
    <?php foreach ($rv['stops'] as $s): $rrSelected = $rv['next'] ? $rv['next']['id'] === $s['id'] : false; include __DIR__ . '/rider_stop_card.php'; endforeach; ?>
    <?php if (!$rv['next'] && !$rv['done']): ?><div class="da-sheet mut sm">No open stops on this route.</div><?php endif; ?>
  </div>

  <section class="rr-loadm da-sheet" aria-label="Current load">
    <div class="row between"><h2 class="sl-ct"><?= $rv['onroute'] || $rv['done'] ? 'On board now' : 'Load for this route' ?></h2><span class="mut sm"><?= h($rv['vehicle']['type']) ?><?= $rv['vehicle']['plate'] ? ' · ' . h($rv['vehicle']['plate']) : '' ?></span></div>
    <?php foreach ($rv['load'] as $b): ?>
    <div class="rr-bar"><span><?= h($b['label']) ?></span><i role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $b['pct'] ?>" aria-label="<?= h($b['label']) ?> used"><em style="width:<?= (int) $b['pct'] ?>%"></em></i><b><?= h($b['val']) ?></b></div>
    <?php endforeach; ?>
    <div class="rr-inv">
      <div><b><?= (int) $inv['onboard'] ?></b><small>On board</small></div>
      <div><b><?= (int) $inv['delivered'] ?></b><small>Delivered</small></div>
      <div><b><?= (int) $inv['pending_load'] ?></b><small>To load</small></div>
      <div><b><?= (int) ($inv['failed'] + $inv['returned']) ?></b><small>Failed / back</small></div>
    </div>
    <?php $bt = $inv['by_type'] ?? []; if (array_sum(array_map('intval', $bt))): ?>
    <p class="mut sm"><?= (int) ($bt['air'] ?? 0) ?> air · <?= (int) ($bt['sea'] ?? 0) ?> sea<?= !empty($bt['fragile']) ? ' · ' . (int) $bt['fragile'] . ' fragile' : '' ?><?= !empty($bt['cold']) ? ' · ' . (int) $bt['cold'] . ' cold' : '' ?><?= !empty($bt['high_value']) ? ' · ' . (int) $bt['high_value'] . ' high value' : '' ?><?= !empty($inv['missing']) ? ' · ' . (int) $inv['missing'] . ' missing' : '' ?></p>
    <?php endif; ?>
  </section>

  <?php if ($stats): $bc = $stats['by_category'] ?? []; $tot = max(1, array_sum(array_map('intval', $bc))); ?>
  <section class="rr-stats da-sheet" aria-label="Your day">
    <div class="row between"><h2 class="sl-ct">Your day</h2>
      <?php if ($rv['onroute']): $onBreak = ($d['rpActivity'] ?? '') === 'break'; ?><button type="button" class="sl-btn sm ghost" data-rr-break="<?= $onBreak ? 'onway' : 'break' ?>"><?= $onBreak ? 'Back on the way' : 'Take a break' ?></button><?php endif; ?>
    </div>
    <div class="rr-cat" role="img" aria-label="Time by activity today">
      <?php foreach (['onway_s' => 'On the way', 'loading_s' => 'Loading', 'waiting_s' => 'Waiting', 'break_s' => 'Break'] as $k => $lbl): $sec = (int) ($bc[$k] ?? 0); ?>
      <div class="rr-cat-row c-<?= h(substr($k, 0, -2)) ?>"><span><?= h($lbl) ?></span><i><em style="width:<?= (int) round($sec / $tot * 100) ?>%"></em></i><b><?= h(sl_rrDuration($sec)) ?></b></div>
      <?php endforeach; ?>
    </div>
    <p class="mut sm"><?= (int) ($stats['delivered'] ?? 0) ?> delivered · <?= (int) ($stats['failed'] ?? 0) ?> failed · <?= h(sl_rrDistance((int) ($stats['distance_m'] ?? 0))) ?> travelled today</p>
  </section>
  <?php endif; ?>
</aside>
</div>
