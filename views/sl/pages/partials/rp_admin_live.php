<?php
/**
 * Live tab: every rider's last position and planned/confirmed stops on one map, alerts (late, stuck,
 * offline, idle loading) and per-rider progress. Refreshed by op=state every 20 s while visible.
 * Input: $d['map'], $d['alerts'], $d['riders'].
 */
?>
<div class="rp-live">
  <section class="sl-card col g10 rp-live-map">
    <div class="row between g8 wrap"><div class="col"><h2 class="sl-ct">Live map</h2><span class="mut sm">Positions come from the rider app while a route is loading or on the way. <span data-rp-live-stamp>Updated <?= h($d['now'] ?? '') ?></span></span></div><span class="rp-live-dot"><span></span>Live</span></div>
    <div class="rp-map xl" data-rp-map="live" role="img" aria-label="Map of all riders"></div>
    <div class="row g10 wrap sm rp-legend"><?php foreach ((array) ($d['riders'] ?? []) as $r): ?><a class="row g6" href="<?= h(sl_url(['tab' => 'fleet', 'rider' => $r['id']])) ?>"><span class="rp-dot" style="background:<?= h($r['color']) ?>"></span><?= h($r['name']) ?></a><?php endforeach; ?></div>
  </section>
  <aside class="col g12">
    <section class="sl-card col g8"><div class="row between g8"><h2 class="sl-ct">Alerts</h2><span class="sl-badge" data-rp-alert-n><?= count((array) ($d['alerts'] ?? [])) ?></span></div>
      <div class="col g6" data-rp-alerts><?php foreach ((array) ($d['alerts'] ?? []) as $a): ?><div class="rp-alert a-<?= h($a['type']) ?>"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span class="col grow"><b class="fw5 sm"><?= h($a['name'] ?: ucfirst(str_replace('_', ' ', $a['type']))) ?></b><span class="sm"><?= h($a['msg']) ?></span></span><?php if ($a['driver_id']): ?><a class="sl-btn ghost sm" href="<?= h(sl_url(['tab' => 'fleet', 'rider' => $a['driver_id']])) ?>">Open</a><?php endif; ?></div><?php endforeach; ?></div>
      <span class="mut sm" data-rp-alerts-empty<?= !empty($d['alerts']) ? ' hidden' : '' ?>>No delays, stuck or offline riders right now.</span>
    </section>
    <section class="sl-card col g8"><h2 class="sl-ct">Riders</h2>
      <?php foreach ((array) ($d['riders'] ?? []) as $r): $pct = $r['progress']['total'] ? (int) round($r['progress']['done'] * 100 / $r['progress']['total']) : 0; ?>
      <a class="rp-lr" href="<?= h(sl_url(['tab' => 'fleet', 'rider' => $r['id']])) ?>" data-rp-live-rider="<?= (int) $r['id'] ?>" style="--rc:<?= h($r['color']) ?>"><span class="rp-av sm" aria-hidden="true"><?= h($r['initials']) ?></span><span class="col grow"><span class="row between g6"><b class="fw5"><?= h($r['name']) ?></b><span class="sl-pill <?= h($r['stCls']) ?>" data-rp-live-st><?= h($r['stLabel']) ?></span></span><span class="rp-prog"><i style="width:<?= $pct ?>%" data-rp-live-prog></i></span><span class="mut sm" data-rp-live-meta><?= $r['progress']['total'] ? (int) $r['progress']['done'] . '/' . (int) $r['progress']['total'] . ' stops' : 'No route' ?><?= $r['nextEta'] ? ' · next ETA ' . h($r['nextEta']) : '' ?><?= $r['lastPing'] ? ' · seen ' . h($r['lastPing']) : '' ?></span></span></a>
      <?php endforeach; ?>
    </section>
  </aside>
</div>
