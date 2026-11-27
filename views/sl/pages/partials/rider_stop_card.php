<?php
/**
 * One stop of today's route with the rider's big actions (Navigate, Call, WhatsApp, Arrived,
 * Delivered with proof, Failed with reason). Input: $s (sl_riderRouteView stop), $rv (route view),
 * $rrSelected (bool: visible card). Delivered opens the existing proof flow (delivery_job.php);
 * Failed posts ajax/sl/delivery_ajax.php op=fail and then rider_route_ajax op=sync (dataJs/sl/rider_route.js).
 */
$rrIsNext = $rv['next'] && $rv['next']['id'] === $s['id'];
$rrHL = ['fragile' => 'Fragile', 'cold' => 'Keep cold', 'high_value' => 'High value', 'oversize' => 'Oversize', 'liquid' => 'Liquid'];
?>
<article class="rr-card<?= $rrIsNext ? ' is-next' : '' ?>" id="rr-stop-<?= (int) $s['id'] ?>" data-rr-card="<?= (int) $s['id'] ?>" data-request="<?= (int) $s['request_id'] ?>" aria-label="Stop <?= (int) $s['seq'] ?>: <?= h($s['name']) ?>"<?= empty($rrSelected) ? ' hidden' : '' ?>>
  <div class="rr-card-head">
    <span class="rr-seq st-<?= h($s['status']) ?>" aria-hidden="true"><?= (int) $s['seq'] ?></span>
    <div class="rr-card-title">
      <span class="up"><?= $rrIsNext ? ($s['status'] === 'arrived' ? 'You are here' : 'Next stop') : 'Stop ' . (int) $s['seq'] ?><?php if ($s['open'] && $s['eta']): ?> · ETA <b data-rr-eta="<?= (int) $s['id'] ?>"><?= h($s['eta']) ?></b><?php endif; ?></span>
      <h2><?= h($s['name']) ?></h2>
      <p><?= h($s['address']) ?></p>
    </div>
    <span class="sl-pill <?= h($s['cls']) ?>"><?= h($s['status_label']) ?></span>
  </div>
  <div class="rr-chips">
    <span><i class="ic ic-sm i-box" aria-hidden="true"></i><?= (int) $s['n'] ?> parcel<?= $s['n'] === 1 ? '' : 's' ?></span>
    <span><?= h(rtrim(rtrim(number_format($s['kg'], 1), '0'), '.')) ?> kg · <?= h(rtrim(rtrim(number_format($s['l'], 1), '0'), '.')) ?> L</span>
    <?php if ($s['open']): ?><span data-rr-dist="<?= (int) $s['id'] ?>"><?= h($s['leg']) ?> leg</span><?php endif; ?>
    <?php if ($s['window']): ?><span class="<?= $s['late'] ? 'is-late' : '' ?>"><i class="ic ic-sm i-clock" aria-hidden="true"></i><?= h($s['window']) ?><?= $s['late'] && $s['open'] ? ' · may be late' : '' ?></span><?php endif; ?>
    <?php if ($s['priority'] >= 2): ?><span class="is-urgent">Urgent</span><?php elseif ($s['priority'] === 1): ?><span class="is-high">High priority</span><?php endif; ?>
    <?php foreach ($s['handling'] as $hk): ?><span class="rp-hb h-<?= h($hk) ?>"><?= h($rrHL[$hk] ?? $hk) ?></span><?php endforeach; ?>
  </div>
  <?php if ($s['instructions'] !== ''): ?><div class="sl-banner info rr-note"><?= h($s['instructions']) ?></div><?php endif; ?>
  <?php if ($s['open']): ?>
  <div class="rr-actions">
    <a class="rr-btn" data-rr-nav href="<?= h($s['nav_google']) ?>" data-google="<?= h($s['nav_google']) ?>" data-apple="<?= h($s['nav_apple']) ?>" target="_blank" rel="noopener"><i class="ic i-route" aria-hidden="true"></i>Navigate</a>
    <a class="rr-btn rr-nav-google" data-rr-nav-google href="<?= h($s['nav_google']) ?>" target="_blank" rel="noopener" aria-label="Open in Google Maps"><i class="ic i-pin" aria-hidden="true"></i>Google Maps</a>
    <?php if ($s['tel']): ?><a class="rr-btn" href="tel:<?= h($s['tel']) ?>"><i class="ic i-phone" aria-hidden="true"></i>Call</a><?php endif; ?>
    <?php if ($s['wa']): ?><a class="rr-btn" href="<?= h($s['wa']) ?>" target="_blank" rel="noopener"><i class="ic i-message" aria-hidden="true"></i>WhatsApp</a><?php endif; ?>
  </div>
  <?php if (!$rv['onroute']): ?>
  <a class="rr-btn pri" href="<?= h(sl_route('DriverApp', ['tab' => 'load'])) ?>"><i class="ic i-check" aria-hidden="true"></i>Load parcels and start route (<?= (int) $rv['inventory']['loaded'] ?>/<?= (int) $rv['inventory']['total'] ?>)</a>
  <?php else: ?>
  <div class="rr-do">
    <?php if ($s['status'] === 'pending'): ?><button type="button" class="rr-btn arrive" data-rr-op="arrive" data-stop="<?= (int) $s['id'] ?>"><i class="ic i-pin" aria-hidden="true"></i>Arrived</button><?php endif; ?>
    <a class="rr-btn pri" href="<?= h($s['job_url']) ?>"><i class="ic i-check" aria-hidden="true"></i>Delivered · add proof</a>
    <button type="button" class="rr-btn bad" data-rr-fail="<?= (int) $s['request_id'] ?>" data-stop="<?= (int) $s['id'] ?>" data-name="<?= h($s['name']) ?>"><i class="ic i-x" aria-hidden="true"></i>Failed</button>
  </div>
  <?php endif; ?>
  <?php elseif ($s['status'] === 'delivered'): ?>
  <div class="da-ok"><i class="ic i-check" aria-hidden="true"></i><span>Delivered<?= $s['done_at'] ? ' at ' . h($s['done_at']) : '' ?>.</span></div>
  <?php else: ?>
  <div class="da-bad"><i class="ic i-x" aria-hidden="true"></i><span><?= h($s['status_label']) ?><?= $s['fail_reason'] ? ': ' . h($s['fail_reason']) : '' ?>. Bring the parcels back to the office.</span></div>
  <?php endif; ?>
  <details class="rr-parcels"><summary>Parcel details (<?= (int) $s['n'] ?>)</summary>
    <?php foreach ($s['parcels'] as $p): include __DIR__ . '/rp_parcel_card.php'; endforeach; ?>
  </details>
</article>
