<?php
/** Rider app hero: next stop of today's planned route ($d['rv']), else the legacy/delivery run ($d['pwaRun']). */
$rv = $d['rv'] ?? null;
if ($rv):
    $n = $rv['next']; $inv = $rv['inventory'];
    $completed = (int) $rv['progress']['done'] + (int) ($d['done'] ?? 0);
?>
<section class="pwa-rider-hero rr-hero" aria-label="Your delivery run"><div class="row between wrap g8"><span class="pwa-eyebrow"><?= $rv['done'] ? 'ROUTE FINISHED' : ($n ? ($n['status'] === 'arrived' ? 'YOU ARE AT STOP ' : 'NEXT STOP ') . (int) $n['seq'] . ($n['eta'] ? ' · ETA ' . h($n['eta']) : '') : 'YOUR RUN') ?></span><span class="sl-pill plain"><?= h($rv['status_label']) ?></span></div>
<?php if ($n): ?>
<h2><?= h($n['name']) ?></h2><p><?= h($n['area'] ? $n['area'] . ' · ' : '') ?><?= (int) $n['n'] ?> parcel<?= $n['n'] === 1 ? '' : 's' ?> · <?= h($rv['left_km']) ?> and <?= h($rv['left_time']) ?> left</p>
<?php if (!$rv['onroute']): ?><a class="sl-btn" href="<?= h(sl_route('RiderMap', ['view' => 'load'])) ?>">Load parcels (<?= (int) $inv['loaded'] ?>/<?= (int) $inv['total'] ?>)<i class="ic i-right" aria-hidden="true"></i></a>
<?php else: ?><a class="sl-btn" href="#rr-stop-<?= (int) $n['id'] ?>" data-rr-pick="<?= (int) $n['id'] ?>"><?= $n['status'] === 'arrived' ? 'Hand over the parcels' : 'Go to next stop' ?><i class="ic i-right" aria-hidden="true"></i></a><?php endif; ?>
<?php else: ?><h2><?= $rv['done'] ? 'All stops done. Well ridden!' : 'No open stops.' ?></h2><p><?= (int) $inv['delivered'] ?> delivered<?= ($inv['failed'] + $inv['returned']) ? ' · ' . (int) ($inv['failed'] + $inv['returned']) . ' to bring back to the office' : '' ?>.</p><a class="sl-btn" href="<?= h(sl_route('RiderEarnings')) ?>">See my earnings</a><?php endif; ?>
<div class="pwa-rider-run"><div><b><?= (int) $rv['open'] ?></b><small>Stops to go</small></div><div><b><?= (int) ($rv['onroute'] ? $inv['onboard'] : $inv['total']) ?></b><small><?= $rv['onroute'] ? 'Parcels on board' : 'Parcels to load' ?></small></div><div><b><?= $completed ?></b><small>Completed today</small></div></div></section>
<?php return; endif;
$run=$d['pwaRun'];$next=$run['next']; ?>
<section class="pwa-rider-hero" aria-label="Your delivery run"><div class="row between wrap g8"><span class="pwa-eyebrow"><?= $next?'NEXT '.$next['type']:'YOUR RUN' ?></span><?php if($next): ?><span class="sl-pill plain"><?= h($next['status']) ?></span><?php endif; ?></div>
<?php if($next): ?><h2><?= h($next['name']) ?></h2><p><?= h($next['address']) ?></p><a class="sl-btn" href="<?= h($next['href']) ?>"><?= h($next['label']) ?><i class="ic i-right" aria-hidden="true"></i></a><div class="pwa-contact-actions"><?php if($next['phone']): ?><a class="sl-btn ghost" href="tel:<?= h(preg_replace('/[^+0-9]/','',$next['phone'])) ?>"><i class="ic i-phone" aria-hidden="true"></i>Call</a><?php endif; ?><?php if($next['navigate']): ?><a class="sl-btn ghost" href="<?= h($next['navigate']) ?>" target="_blank" rel="noopener"><i class="ic i-route" aria-hidden="true"></i>Navigate</a><?php endif; ?></div><?php if ($messageLink=sl_riderMessageLink($next['phone'])): ?><a class="pwa-rider-message" href="<?= h($messageLink) ?>" target="_blank" rel="noopener">Message on WhatsApp <i class="ic ic-sm i-right" aria-hidden="true"></i></a><?php endif; ?>
<?php else: ?><h2>You’re all caught up.</h2><p>Your next delivery will appear here when dispatch assigns it.</p><a class="sl-btn" href="<?= h(sl_route('RiderMap')) ?>">View my route</a><?php endif; ?>
<div class="pwa-rider-run"><div><b><?= (int)$run['stops'] ?></b><small>Stops to go</small></div><div><b><?= (int)$run['parcels'] ?></b><small>Parcels</small></div><div><b><?= (int)$run['completed'] ?></b><small>Completed today</small></div></div></section>
