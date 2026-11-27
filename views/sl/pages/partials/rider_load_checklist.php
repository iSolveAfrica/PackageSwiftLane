<?php
/**
 * Loading checklist: scan (camera BarcodeDetector when available, else typing) or tick each
 * parcel of today's route, mark missing ones, then start the route (rider_route_ajax ops
 * load_scan / load_toggle / start). Input: $rv (sl_riderRouteView).
 */
$inv = $rv['inventory'];
$rrTotal = (int) $inv['total'];
$rrDone = (int) $inv['loaded'] + (int) $inv['missing'];
$rrHL = ['fragile' => 'Fragile', 'cold' => 'Keep cold', 'high_value' => 'High value', 'oversize' => 'Oversize', 'liquid' => 'Liquid'];
$rrLocked = !$rv['can_load'];
?>
<section class="rr-load" aria-label="Loading checklist" data-rr-load>
  <div class="rr-load-head da-sheet">
    <div class="row between"><h2 class="sl-ct">Loading checklist</h2><span class="sl-pill <?= $rv['onroute'] ? 'onway' : 'plain' ?>"><?= h($rv['status_label']) ?></span></div>
    <div class="rr-bar big"><span><b data-rr-loaded><?= (int) $inv['loaded'] ?></b> of <?= $rrTotal ?> on board<span data-rr-missing-wrap<?= $inv['missing'] ? '' : ' hidden' ?>> · <span data-rr-missing><?= (int) $inv['missing'] ?></span> missing</span></span><i role="progressbar" aria-valuemin="0" aria-valuemax="<?= $rrTotal ?>" aria-valuenow="<?= $rrDone ?>" aria-label="Parcels checked"><em data-rr-loadbar style="width:<?= $rrTotal ? (int) round($rrDone / $rrTotal * 100) : 0 ?>%"></em></i></div>
    <?php if (!$rrLocked): ?>
    <form class="rr-scan" data-rr-scan autocomplete="off">
      <label class="rr-sr" for="rr-code">Tracking number</label>
      <input class="sl-in mono" id="rr-code" name="code" placeholder="Tracking number" enterkeyhint="go" autocapitalize="characters" spellcheck="false" required>
      <button class="rr-btn pri" type="submit"><i class="ic i-check" aria-hidden="true"></i>Load</button>
      <button class="rr-btn" type="button" data-rr-camera hidden><i class="ic i-scan" aria-hidden="true"></i>Camera</button>
    </form>
    <div class="rr-cam" data-rr-cam hidden><video playsinline muted aria-label="Camera preview for scanning"></video><button type="button" class="sl-btn ghost" data-rr-cam-stop>Stop camera</button></div>
    <p class="rr-scan-msg sm" data-rr-scan-msg role="status" aria-live="polite"></p>
    <?php endif; ?>
    <?php foreach ($rv['load'] as $b): ?>
    <div class="rr-bar"><span><?= h($b['label']) ?></span><i role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $b['pct'] ?>" aria-label="<?= h($b['label']) ?> of vehicle capacity"><em style="width:<?= (int) $b['pct'] ?>%"></em></i><b><?= h($b['val']) ?></b></div>
    <?php endforeach; ?>
  </div>

  <?php foreach ($rv['load_groups'] as $g): $s = $g['stop']; ?>
  <div class="rr-load-stop da-sheet">
    <div class="rr-load-stop-h"><span class="rr-seq st-<?= h($s['status']) ?>"><?= (int) $s['seq'] ?></span><span class="grow"><b><?= h($s['name']) ?></b><small class="mut"><?= h($s['area'] ?: $s['address']) ?></small></span><span class="mut sm"><?= (int) $s['n'] ?> parcel<?= $s['n'] === 1 ? '' : 's' ?></span></div>
    <?php foreach ($g['parcels'] as $p): $ls = (string) ($p['load_state'] ?? 'pending'); $oc = (string) ($p['outcome'] ?? 'onboard'); $fixed = $rrLocked || $oc !== 'onboard'; $key = ($p['module'] ?? 'air') . ':' . (int) $p['order_id']; ?>
    <div class="rr-pk ls-<?= h($ls) ?>" data-rr-parcel="<?= h($key) ?>" data-module="<?= h($p['module'] ?? 'air') ?>" data-order="<?= (int) $p['order_id'] ?>" data-tracking="<?= h($p['tracking'] ?? '') ?>">
      <label class="rr-tick">
        <input type="checkbox" data-rr-tick <?= $ls === 'loaded' ? 'checked' : '' ?> <?= $fixed || $ls === 'missing' ? 'disabled' : '' ?> aria-label="Loaded: <?= h($p['tracking'] ?? '') ?>">
        <span class="rr-pk-m"><b class="mono"><?= h($p['tracking'] ?? '') ?></b><small><?= h(($p['contents'] ?? '') ?: 'Contents not recorded') ?></small>
          <small><?= h(rtrim(rtrim(number_format((float) ($p['kg'] ?? 0), 1), '0'), '.')) ?> kg · <?= h(rtrim(rtrim(number_format((float) ($p['volume_l'] ?? 0), 1), '0'), '.')) ?> L<?php if (!empty($p['l']) && !empty($p['w']) && !empty($p['h'])): ?> · <?= h(rtrim(rtrim(number_format((float) $p['l'], 1), '0'), '.') . '×' . rtrim(rtrim(number_format((float) $p['w'], 1), '0'), '.') . '×' . rtrim(rtrim(number_format((float) $p['h'], 1), '0'), '.')) ?> cm<?php endif; ?></small>
          <?php if (!empty($p['handling'])): ?><span class="rr-hbs"><?php foreach ($p['handling'] as $hk): ?><span class="rp-hb h-<?= h($hk) ?>"><?= h($rrHL[$hk] ?? $hk) ?></span><?php endforeach; ?></span><?php endif; ?>
        </span>
      </label>
      <?php if ($oc !== 'onboard'): ?><span class="sl-pill <?= $oc === 'delivered' ? 'delivered' : 'overdue' ?>"><?= h(ucfirst($oc)) ?></span>
      <?php elseif (!$rrLocked && !$rv['onroute']): ?><button type="button" class="sl-btn sm ghost" data-rr-missing-btn aria-pressed="<?= $ls === 'missing' ? 'true' : 'false' ?>"><?= $ls === 'missing' ? 'Found it' : 'Missing' ?></button><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>

  <?php if (in_array($rv['status'], ['planned', 'loading'], true)): ?>
  <div class="rr-start sl-mobile-sticky">
    <p class="mut sm" data-rr-start-hint><?= $rv['all_loaded'] ? 'Everything is checked. Missing parcels go back to dispatch.' : 'Tick every parcel, or mark it missing, to start.' ?></p>
    <button type="button" class="rr-btn pri" data-rr-op="start" <?= $rv['all_loaded'] ? '' : 'disabled' ?>><i class="ic i-truck" aria-hidden="true"></i>Start route</button>
  </div>
  <?php elseif ($rv['onroute']): ?>
  <div class="da-ok"><i class="ic i-check" aria-hidden="true"></i><span>Route started<?= $rv['started_at'] ? ' at ' . h($rv['started_at']) : '' ?>. <a href="<?= h(sl_route('DriverApp')) ?>">Go to next stop</a></span></div>
  <?php endif; ?>
</section>
