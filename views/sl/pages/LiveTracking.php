<?php
/**
 * Live tracking: the full-page tracking console. Entry: live_tracking.php.
 * Staff: every air shipment, sea package and air / sea consolidation in their scope, searched on the server.
 * Customers: the same view over their own parcels and consolidations.
 * Data: views/sl/data/track_console.php (sl_trackConsoleData, source 'all' or 'mine').
 */
if (!isset($d)) { $d = []; }
$tc = $d['tc'];
$client = $d['client'];
$t = $tc['tally'];
$tiles = [
    ['air', 'i-plane', $client ? 'My air shipments' : 'Air shipments', $t['air'] ?? null, 'air'],
    ['sea', 'i-ship', $client ? 'My sea shipments' : 'Sea shipments & packages', $t['sea'] ?? null, 'sea'],
    ['con', 'i-layers', 'Air consolidations', $t['air_con'] ?? null, 'air'],
    ['con', 'i-layers', 'Sea consolidations', $t['sea_con'] ?? null, 'sea'],
];
// Air shows air only, Sea shows sea only: no sea tiles on the Air view and the other way round.
if (in_array($d['filter'], ['air', 'sea'], true)) $tiles = array_values(array_filter($tiles, fn($x) => $x[4] === $d['filter']));
$labels = sl_tcFilters();
if ($client) $labels['all'] = 'Everything moving';
?>
<div class="sl-head"><div><h1 class="sl-title row g8" style="font-size:22px"><i class="ic i-route" aria-hidden="true"></i><?= $client ? 'Track my parcels' : 'Live tracking' ?></h1>
<p class="sl-lede"><?= $client ? 'Every parcel you ship with us, by air and by sea, and the consolidations they travel in. Pick one to see where it is.' : 'Every air shipment, sea package and consolidation you can see, on one map. Search a tracking number, customer, locker, store tracking, container or a parcel inside a consolidation.' ?></p></div>
<form method="get" class="sl-search lt-q" role="search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" aria-label="Search all tracking" placeholder="<?= $client ? 'Tracking number or store tracking' : 'Tracking, customer, locker, container…' ?>" value="<?= h($d['q']) ?>"><?php if ($d['filter'] !== 'all'): ?><input type="hidden" name="f" value="<?= h($d['filter']) ?>"><?php endif; ?><?php if ($d['q'] !== ''): ?><a class="lt-x" href="<?= h(sl_url(['q' => null])) ?>" aria-label="Clear search"><i class="ic ic-sm i-x" aria-hidden="true"></i></a><?php endif; ?></form></div>

<div class="lt-tiles">
  <?php foreach ($tiles as [$tf, $ti, $tl, $tn]): if ($tn === null) continue; ?>
  <a class="lt-tile<?= $d['filter'] === $tf ? ' on' : '' ?>" href="<?= h(sl_url(['f' => $tf === 'all' ? null : $tf])) ?>"><i class="ic <?= $ti ?>" aria-hidden="true"></i><span class="col"><b><?= sl_num($tn) ?></b><small><?= h($tl) ?> open</small></span></a>
  <?php endforeach; ?>
</div>

<nav class="lt-filters" aria-label="Show">
  <?php foreach ($labels as $fk => $fl): if ($fk === 'con' && !isset($t['air_con']) && !isset($t['sea_con'])) continue; ?>
  <a class="<?= $d['filter'] === $fk ? 'on' : '' ?>" href="<?= h(sl_url(['f' => $fk === 'all' ? null : $fk])) ?>"<?= $d['filter'] === $fk ? ' aria-current="true"' : '' ?>><?= h($fl) ?></a>
  <?php endforeach; ?>
  <span class="grow"></span>
  <span class="mut sm"><?= $d['q'] !== '' ? 'Results for “' . h($d['q']) . '” · ' : '' ?><?= $tc['shown'] < $tc['total'] ? 'Newest ' . sl_num($tc['shown']) . ' of ' . sl_num($tc['total']) . ' · search to narrow' : sl_num($tc['total']) . ' found' ?></span>
</nav>

<?php $tcTitle = $client ? 'My parcels' : 'Tracking list'; $tcNoun = 'records'; $tcExpand = ''; $tcFilters = false; ?>
<div class="lt-console">
<?php if ($tc['items']): include __DIR__ . '/partials/track_console.php'; else: ?>
<div class="sl-empty"><span class="sl-tile"><i class="ic i-route" aria-hidden="true"></i></span><?= $d['q'] !== '' ? 'Nothing matches “' . h($d['q']) . '”. Check the number, or try the customer name.' : ($client ? 'Nothing on the way right now. Parcels appear here as soon as they reach our warehouse.' : 'Nothing to show for this filter.') ?></div>
<?php endif; ?>
</div>
<style>
.lt-q { min-width: min(100%, 360px); position: relative; }
.lt-x { display: inline-grid; place-items: center; color: var(--muted); }
.lt-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; }
.lt-tile { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: var(--r-lg); background: var(--card); border: 1px solid var(--line); color: inherit; }
.lt-tile .ic { width: 22px; height: 22px; color: var(--muted); }
.lt-tile b { font-size: 22px; font-weight: 600; line-height: 1.1; }
.lt-tile small { color: var(--muted); font-size: 13px; }
.lt-tile:hover { border-color: var(--line-2); }
.lt-tile.on { border-color: var(--ink); }
.lt-filters { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.lt-filters a { padding: 7px 14px; border-radius: 999px; border: 1px solid var(--line-2); font-size: 14px; color: var(--ink-2); background: var(--field); white-space: nowrap; }
.lt-filters a.on { background: var(--ink); color: var(--inverse); border-color: var(--ink); }
.lt-filters .grow { flex: 1; }
.lt-console .tc-wrap .tc-list { max-height: 980px; }
@media (max-width: 680px) { .lt-filters { flex-wrap: nowrap; overflow-x: auto; scrollbar-width: none; } .lt-filters .grow, .lt-filters .mut { display: none; } }
</style>
