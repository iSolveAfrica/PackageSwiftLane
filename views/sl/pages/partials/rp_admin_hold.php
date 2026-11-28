<?php
/**
 * Cargo hold: accepted door deliveries (pending requests) not in the draft plan, filterable by area and handling.
 * Each card opens the parcel drawer (rp_parcel_card.php). Drop a stop here to take it out of a rider's set.
 * Input: $d['hold'], $d['holdUnplaceable'], $d['holdAreas'], $d['sel'] (confirmed route => "add to route").
 */
$rpAddRoute = (!empty($d['sel']['routeId']) && ($d['sel']['source'] ?? '') === 'route' && !in_array($d['sel']['routeStatus'] ?? '', ['done'], true)) ? (int) $d['sel']['routeId'] : 0;
$rpHoldN = count((array) ($d['hold'] ?? [])) + count((array) ($d['holdUnplaceable'] ?? []));
$rpHoldKg = array_sum(array_column((array) ($d['holdAll'] ?? []), 'kg'));
$rpHoldN2 = array_sum(array_column((array) ($d['holdAll'] ?? []), 'n'));
?>
<aside class="rp-hold col g10" aria-label="Cargo hold" data-rp-drop="0" data-rp-hold>
  <div class="row between g8"><div class="col"><h2 class="sl-ct">Cargo hold · <span data-rp-hold-n><?= (int) $rpHoldN ?></span></h2><span class="mut sm"><?= (int) $rpHoldN2 ?> parcels · <?= h(rtrim(rtrim(number_format($rpHoldKg, 1), '0'), '.')) ?> kg ready for door delivery</span></div></div>
  <div class="col g6 rp-hold-f">
    <div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input type="search" data-rp-hold-q placeholder="Name, address or tracking" aria-label="Search the hold"></div>
    <div class="row g6">
      <select class="sl-in grow" data-rp-hold-area aria-label="Filter by area"><option value="">All areas</option><?php foreach ((array) ($d['holdAreas'] ?? []) as $a): ?><option value="<?= h($a) ?>"><?= h($a) ?></option><?php endforeach; ?></select>
      <select class="sl-in grow" data-rp-hold-handling aria-label="Filter by handling"><option value="">Any handling</option><?php foreach (RPV_HANDLING as $k => $l): ?><option value="<?= h($k) ?>"><?= h($l) ?></option><?php endforeach; ?></select>
    </div>
  </div>
  <?php if ($rpAddRoute): ?><div class="mut sm rp-hint"><i class="ic ic-sm i-plus" aria-hidden="true"></i> adds a stop to <?= h($d['sel']['name']) ?>'s confirmed route.</div><?php endif; ?>
  <div class="col g8 rp-hold-list" data-rp-hold-list>
    <?php foreach ((array) ($d['hold'] ?? []) as $u) echo rpv_unitCard($u, ['drag' => ($rpTab ?? 'plan') === 'plan', 'from' => 0, 'seq' => false, 'move' => !empty($d['plan']), 'addRoute' => $rpAddRoute]); ?>
    <?php if (!empty($d['holdUnplaceable'])): ?><span class="up rp-grp-h">Needs attention · <?= count($d['holdUnplaceable']) ?></span><span class="mut sm">No map pin, or no rider's vehicle can take it today. The reason is on each card.</span><?php foreach ($d['holdUnplaceable'] as $u) echo rpv_unitCard($u, ['seq' => false]); ?><?php endif; ?>
    <div class="sl-card mut sm rp-hold-empty" data-rp-hold-empty<?= $rpHoldN ? ' hidden' : '' ?>><?= $rpHoldN ? 'Nothing matches these filters.' : (!empty($d['plan']) ? 'Every ready request is in the draft plan.' : 'The hold is empty: no accepted door deliveries are waiting for a rider.') ?></div>
  </div>
</aside>
