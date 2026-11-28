<?php
/**
 * Smart Routing: the riders of a day beside a map, a "Create route" dialog, one rider's route as a
 * timeline, the stop (proof of delivery) dialog, and the "Riders & vehicles" view.
 * Data: views/sl/data/smart_routing.php (sl_smartRoutingData). Writes: ajax/sl/smart_routing_ajax.php.
 * Script: dataJs/sl/smart_routing.js. Map: assets/swiftlane/route-map.js (schematic when map tiles are offline).
 */
if (!isset($d)) { $d = []; }
$srView = (string) ($d['view'] ?? 'overview');
$srDet = $d['detail'] ?? null;
$srJson = $d['json'] ?? ['ajax' => 'ajax/sl/smart_routing_ajax.php'];
$srDay = (string) ($d['dayWord'] ?? 'Today');
$srPast = !empty($d['isPast']);
$srFin = !empty($d['finance']);
$srSub = $srView === 'route' ? 'Route details' : ($srView === 'riders' ? 'Riders and vehicles' : 'Overview');
$srAv = fn(array $r, string $cls = '') => '<span class="sr-av' . ($cls ? ' ' . $cls : '') . '" style="--rc:' . h($r['color'] ?? '#64748b') . '" aria-hidden="true">' . h($r['initials'] ?? '?') . '</span>';
?>
<div class="sr" data-sr-root data-sr-view="<?= h($srView) ?>">
<script type="application/json" id="sr-data"><?= json_encode($srJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>

<div class="sl-head sr-head">
  <div class="sr-head-t">
    <h1 class="sr-title">Smart Routing, <span><?= h($srSub) ?></span></h1>
    <p class="sl-lede">Give each rider the shortest order of stops for the day, follow every route on the map and keep proof of each delivery.</p>
  </div>
  <div class="sr-head-act">
    <?php if ($srView !== 'riders'): ?>
    <div class="sr-days" role="group" aria-label="Day to plan">
      <i class="ic ic-sm i-calendar" aria-hidden="true"></i>
      <a href="<?= h(sl_route('SmartRouting')) ?>" class="<?= !empty($d['isToday']) ? 'on' : '' ?>"<?= !empty($d['isToday']) ? ' aria-current="date"' : '' ?>>Today</a>
      <a href="<?= h(sl_route('SmartRouting', ['date' => $d['tomorrow'] ?? ''])) ?>" class="<?= ($d['date'] ?? '') === ($d['tomorrow'] ?? '-') ? 'on' : '' ?>"<?= ($d['date'] ?? '') === ($d['tomorrow'] ?? '-') ? ' aria-current="date"' : '' ?>>Tomorrow</a>
      <label class="sr-pick<?= empty($d['isToday']) && ($d['date'] ?? '') !== ($d['tomorrow'] ?? '') ? ' on' : '' ?>"><span><?= h($d['pickLabel'] ?? 'Pick a date') ?></span><i class="ic ic-sm i-down" aria-hidden="true"></i>
        <input type="date" data-sr-date value="<?= h($d['date'] ?? '') ?>" min="<?= h($d['minDate'] ?? '') ?>" max="<?= h($d['maxDate'] ?? '') ?>" aria-label="Pick another day"></label>
    </div>
    <button type="button" class="sl-btn ghost sr-pillbtn" data-sr-save<?= $srDet && !$srPast ? '' : ' disabled' ?> title="<?= $srDet ? 'Keep this route as a draft without sending it to the rider' : 'Open a route to save it as a draft' ?>"><i class="ic ic-sm i-archive" aria-hidden="true"></i>Save</button>
    <button type="button" class="sl-btn sr-pillbtn sr-primary" data-sr-create<?= $srPast || empty($d['ready']) ? ' disabled' : '' ?>><i class="ic ic-sm i-plus" aria-hidden="true"></i>Create route</button>
    <?php else: ?>
    <a class="sl-btn ghost sr-pillbtn" href="<?= h($d['urls']['overview'] ?? sl_route('SmartRouting')) ?>"><i class="ic ic-sm i-route" aria-hidden="true"></i>Routing overview</a>
    <?php endif; ?>
  </div>
</div>

<nav class="sl-seg boxed sr-tabs" aria-label="Smart Routing sections">
  <a class="<?= $srView !== 'riders' ? 'on' : '' ?>" href="<?= h($d['urls']['overview'] ?? sl_route('SmartRouting')) ?>"<?= $srView !== 'riders' ? ' aria-current="page"' : '' ?>>Routing overview</a>
  <a class="<?= $srView === 'riders' ? 'on' : '' ?>" href="<?= h($d['urls']['riders'] ?? sl_route('SmartRouting', ['view' => 'riders'])) ?>"<?= $srView === 'riders' ? ' aria-current="page"' : '' ?>>Riders and vehicles</a>
  <a href="<?= h(sl_route('RoutePlanner')) ?>">Route planner (advanced)<i class="ic ic-sm i-ext" aria-hidden="true"></i></a>
</nav>

<?php if (empty($d['ready'])): ?>
<section class="sl-card col g10" data-sr-empty>
  <b class="fw5">Smart Routing is not ready yet</b>
  <span class="mut sm">The route planner and its tables are not available. Riders and delivery requests keep working from the dispatch board in the meantime.</span>
  <div class="row g8 wrap"><a class="sl-btn" href="<?= h(sl_route('DeliveryDispatch')) ?>">Open delivery requests</a></div>
</section>
</div>
<?php return; endif; ?>

<?php foreach ((array) ($d['notes'] ?? []) as $n): ?><div class="sl-banner info sr-note" role="status"><i class="ic ic-sm i-info" aria-hidden="true"></i><span><?= h($n) ?></span></div><?php endforeach; ?>

<?php if ($srView === 'riders'): include __DIR__ . '/partials/sr_riders.php'; else: ?>
<div class="sr-body<?= $srDet ? ' is-detail' : '' ?>">
  <?php if ($srDet): include __DIR__ . '/partials/sr_route.php'; else: ?>
  <section class="sl-card sr-panel" aria-label="<?= h($srDay) ?> riders">
    <div class="sr-panel-top">
      <div class="row between g8"><h2 class="sr-h2"><?= h($srDay) ?> riders</h2><span class="sr-count" title="Deliveries waiting for a rider"><b data-sr-kpi="waiting"><?= (int) ($d['kpis']['waiting'] ?? 0) ?></b> waiting</span></div>
      <div class="row g8">
        <label class="sl-search sr-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input type="search" data-sr-rider-search placeholder="Search riders" aria-label="Search riders" autocomplete="off"></label>
        <button type="button" class="sr-round" data-sr-filter-toggle aria-expanded="false" aria-controls="sr-filters" aria-label="Filter riders by status"><i class="ic ic-sm i-filter" aria-hidden="true"></i></button>
      </div>
      <div class="sr-chips" id="sr-filters" role="group" aria-label="Filter riders by status" hidden>
        <?php foreach (['all' => 'All', 'available' => 'Available', 'planned' => 'Route planned', 'trip' => 'On trip', 'off' => 'Off shift'] as $k => $label): ?>
        <button type="button" class="sr-chip<?= $k === 'all' ? ' on' : '' ?>" data-sr-filter="<?= h($k) ?>" aria-pressed="<?= $k === 'all' ? 'true' : 'false' ?>"><?= h($label) ?> <b><?= (int) ($d['counts'][$k] ?? 0) ?></b></button>
        <?php endforeach; ?>
      </div>
    </div>
    <ul class="sr-riders" data-sr-riders>
      <?php foreach ((array) ($d['riders'] ?? []) as $r): ?>
      <li class="sr-rider<?= $r['sel'] ? ' sel' : '' ?>" data-sr-rider="<?= (int) $r['id'] ?>" data-group="<?= h($r['group']) ?>" data-search="<?= h(mb_strtolower($r['name'] . ' ' . $r['sub'])) ?>">
        <button type="button" class="sr-rider-main" data-sr-pick="<?= (int) $r['id'] ?>" aria-pressed="<?= $r['sel'] ? 'true' : 'false' ?>">
          <?= $srAv($r) ?>
          <span class="sr-rider-t"><b><?= h($r['name']) ?></b><span class="mut"><?= h($r['sub']) ?></span></span>
          <span class="sl-pill <?= h($r['stCls']) ?>"><?= h($r['stLabel']) ?></span>
          <span class="sr-rider-load">
            <span class="sr-lb <?= h($r['load']['cls']) ?>" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) min(100, $r['load']['pct']) ?>" aria-label="Vehicle load, <?= h(strtolower($r['load']['label'])) ?>"><i style="width:<?= (int) $r['load']['w'] ?>%"></i></span>
            <span class="mut"><?= h($r['load']['label']) ?> <?= h($r['load']['text']) ?><?= $r['stops'] ? ' · ' . (int) $r['stops'] . ' stop' . ($r['stops'] === 1 ? '' : 's') . ($r['done'] ? ', ' . (int) $r['done'] . ' done' : '') : ' · no route' ?></span>
          </span>
        </button>
        <a class="sr-rider-more" href="<?= h($r['viewUrl']) ?>" aria-label="View <?= h($r['name']) ?>: vehicle and statistics" title="Vehicle and statistics"><i class="ic ic-sm i-right" aria-hidden="true"></i></a>
      </li>
      <?php endforeach; ?>
      <li class="sr-none" data-sr-riders-none hidden>No rider matches. Clear the search or the filter.</li>
      <?php if (empty($d['riders'])): ?><li class="sr-none">No active riders yet. Riders are staff with the rider role; add one under Riders.</li><?php endif; ?>
    </ul>
    <div class="sr-panel-foot">
      <span class="mut sm" data-sr-foot-note>Pick a rider, or let Smart Routing share the work.</span>
      <span class="row g8">
        <a class="sl-btn ghost sr-pillbtn" data-sr-open-route hidden href="#">Open route</a>
        <button type="button" class="sl-btn sr-pillbtn sr-primary" data-sr-create<?= $srPast ? ' disabled' : '' ?>><span data-sr-create-label>Create rider route</span><i class="ic ic-sm i-arrow" aria-hidden="true"></i></button>
      </span>
    </div>
  </section>
  <?php endif; ?>

  <section class="sr-mapwrap" aria-label="Map of <?= h(strtolower($srDay)) ?>'s stops">
    <div class="sr-map" data-sr-map role="application" aria-label="Map: start place, stops waiting for a rider and each rider's route"><div class="sr-map-empty">Loading the map…</div></div>
    <div class="sr-legend" aria-hidden="true">
      <span><i class="sr-lg-depot"></i>Start place</span>
      <?php if (!$srDet): ?><span><i class="sr-lg-wait"></i><?= (int) ($d['kpis']['waiting'] ?? 0) ?> waiting</span><span><i class="sr-lg-route"></i><?= (int) ($d['kpis']['stops'] ?? 0) ?> on routes</span>
      <?php else: ?><span><i class="sr-lg-route"></i>This route</span><span><i class="sr-lg-other"></i>Other riders</span><span><i class="sr-lg-wait"></i>Waiting (tap to add)</span><?php endif; ?>
      <span><i class="sr-lg-done"></i>Delivered</span>
    </div>
  </section>
</div>
<?php endif; ?>

<?php /* ---------------------------------------------------------------- Create route */ ?>
<?php if ($srView !== 'riders' && !$srPast): $srHold = (array) ($d['hold'] ?? []); $srWith = (array) ($d['holdWith'] ?? []); ?>
<div class="sl-dialog" id="sr-create" hidden><form class="sl-modal sr-modal" data-sr-create-form aria-labelledby="sr-create-h" novalidate>
  <div class="sr-modal-h"><div><h2 class="sr-h2" id="sr-create-h">Route details</h2><span class="mut">Who rides, where they start and what they carry <?= $srDay === "Today" || $srDay === "Tomorrow" ? h(strtolower($srDay)) : "on " . h($srDay) ?></span></div><button class="sr-round" type="button" data-sl-close aria-label="Close"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>
  <div class="sr-modal-b">
    <div class="sr-form2">
      <label class="sl-field sr-span2"><span class="sl-lab">Rider</span><select class="sl-in sr-in" name="rider_id" data-sr-f-rider>
        <option value="0">No rider: share between available riders</option>
        <?php foreach ((array) ($d['riders'] ?? []) as $r): if ($r['status'] === 'suspended') continue; ?><option value="<?= (int) $r['id'] ?>"><?= h($r['name'] . ' · ' . $r['vehicle']['typeLabel'] . ' · ' . $r['stLabel']) ?></option><?php endforeach; ?>
      </select></label>
      <label class="sl-field"><span class="sl-lab">Start place</span><select class="sl-in sr-in" name="start_id"><?php foreach ((array) ($d['starts'] ?? []) as $sp): ?><option value="<?= h($sp['id']) ?>"<?= $sp['id'] === ($d['startId'] ?? '') ? ' selected' : '' ?>><?= h($sp['label']) ?></option><?php endforeach; ?></select></label>
      <label class="sl-field" data-sr-manual><span class="sl-lab">Most stops</span><input class="sl-in sr-in" type="number" inputmode="numeric" name="max_stops" min="1" max="60" step="1" value="<?= (int) ($d['maxStops'] ?? 25) ?>"></label>
      <label class="sl-field" data-sr-manual><span class="sl-lab">Most pieces</span><input class="sl-in sr-in" type="number" inputmode="numeric" name="max_pieces" min="1" max="500" step="1" value="" placeholder="Vehicle limit" data-sr-f-pieces></label>
      <?php if ($srFin): ?><label class="sl-field" data-sr-manual><span class="sl-lab">Most cash to collect (<?= h($d['currency'] ?? '') ?>)</span><input class="sl-in sr-in" type="number" inputmode="decimal" name="max_amount" min="0" step="0.01" value="" placeholder="No limit"></label><?php endif; ?>
    </div>

    <div class="sr-auto sl-banner info" data-sr-auto-note hidden><i class="ic ic-sm i-wand" aria-hidden="true"></i><span>With no rider chosen, Smart Routing shares every waiting delivery between the riders who are free, by vehicle capacity and area. You see the plan first; nothing is assigned until you confirm.</span></div>
    <div class="col g8" data-sr-auto-result hidden></div>

    <div class="col g10" data-sr-manual>
      <div class="row between g8 wrap"><b class="fw5">Select deliveries</b>
        <span class="sr-view" role="radiogroup" aria-label="Which deliveries to list">
          <label><input type="radio" name="sr_scope" value="un" checked data-sr-scope>Waiting only</label>
          <label><input type="radio" name="sr_scope" value="all" data-sr-scope<?= $srWith ? '' : ' disabled' ?>>View all<?= $srWith ? '' : ' (none on other routes)' ?></label>
        </span>
      </div>
      <?php if ($srHold || $srWith): ?>
      <div class="row between g8 wrap">
        <label class="sr-all"><input type="checkbox" data-sr-all>Select all</label>
        <label class="sl-search sr-search sr-search-sm"><i class="ic ic-sm i-search" aria-hidden="true"></i><input type="search" data-sr-del-search placeholder="Search name, area or number" aria-label="Search deliveries" autocomplete="off"></label>
      </div>
      <div class="sr-dels" data-sr-dels>
        <?php foreach (array_merge($srHold, $srWith) as $u): ?>
        <label class="sr-del<?= $u['with'] ? ' is-with' : '' ?>" data-search="<?= h(mb_strtolower($u['name'] . ' ' . $u['area'] . ' ' . $u['address'] . ' #' . $u['id'])) ?>"<?= $u['with'] ? ' hidden data-with="' . (int) $u['withId'] . '"' : '' ?>>
          <input type="checkbox" name="request_ids[]" value="<?= (int) $u['id'] ?>" data-n="<?= (int) $u['n'] ?>" data-kg="<?= h($u['kg']) ?>" data-l="<?= h($u['l']) ?>" data-cents="<?= (int) $u['cents'] ?>">
          <span class="sr-del-t"><b><?= h($u['name'] ?: 'Delivery #' . $u['id']) ?></b><span class="mut"><?= h($u['area'] ?: $u['address']) ?> · <?= (int) $u['n'] ?> pc<?= $u['n'] === 1 ? '' : 's' ?> · <?= h($u['kg']) ?> kg<?= $u['money'] ? ' · ' . h($u['money']) : '' ?><?= $u['window'] ? ' · ' . h($u['window']) : '' ?><?= $u['handling'] ? ' · ' . h(str_replace('_', ' ', implode(', ', $u['handling']))) : '' ?></span><?php if ($u['with']): ?><span class="sr-del-with">On <?= h($u['with']) ?>'s route: ticking moves it</span><?php endif; ?></span>
          <span class="sr-del-id mut">#<?= (int) $u['id'] ?></span>
        </label>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="sr-emptybox">No delivery is waiting for a rider. New requests appear here once the customer has approved the delivery fee.</div>
      <?php endif; ?>
      <?php foreach ((array) ($d['unplaceable'] ?? []) as $u): ?><div class="sl-banner sr-note"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>Delivery #<?= (int) $u['id'] ?> (<?= h($u['name']) ?>) cannot be routed yet: <?= h(is_string($u['reason']) ? $u['reason'] : 'no map location') ?>. Fix it under Delivery requests.</span></div><?php endforeach; ?>
      <div class="sr-tally" data-sr-tally aria-live="polite"><span data-sr-tally-text>Nothing ticked yet</span><span class="sr-lb" data-sr-tally-bar hidden><i></i></span></div>
    </div>
  </div>
  <div class="sr-modal-f">
    <button class="sl-btn ghost sr-pillbtn" type="button" data-sl-close>Cancel</button>
    <button class="sl-btn sr-pillbtn sr-primary" type="submit" data-sr-create-go><span data-sr-create-go-label>Create route</span><i class="ic ic-sm i-arrow" aria-hidden="true"></i></button>
  </div>
</form></div>
<?php endif; ?>

<?php /* ---------------------------------------------------------------- confirm */ ?>
<div class="sl-dialog" id="sr-ask" hidden><div class="sl-modal sr-modal sr-modal-sm" role="alertdialog" aria-labelledby="sr-ask-h" aria-describedby="sr-ask-m">
  <h2 class="sr-h2" id="sr-ask-h" data-sr-ask-title>Are you sure?</h2>
  <p id="sr-ask-m" data-sr-ask-msg></p>
  <div class="row g8 end"><button class="sl-btn ghost sr-pillbtn" type="button" data-sl-close data-sr-ask-cancel>Cancel</button><button class="sl-btn sr-pillbtn sr-primary" type="button" data-sr-ask-ok>Continue</button></div>
</div></div>

<?php /* ---------------------------------------------------------------- add a stop */ ?>
<?php if ($srDet && $srDet['editable']): ?>
<div class="sl-dialog" id="sr-add" hidden><div class="sl-modal sr-modal" aria-labelledby="sr-add-h">
  <div class="sr-modal-h"><div><h2 class="sr-h2" id="sr-add-h">Add a stop</h2><span class="mut">Deliveries waiting for a rider. The order of stops is worked out again after adding.</span></div><button class="sr-round" type="button" data-sl-close aria-label="Close"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>
  <div class="sr-modal-b">
    <?php if (!empty($d['hold'])): ?>
    <label class="sl-search sr-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input type="search" data-sr-add-search placeholder="Search name, area or number" aria-label="Search waiting deliveries" autocomplete="off"></label>
    <div class="sr-addlist" data-sr-addlist>
      <?php foreach ((array) $d['hold'] as $u): ?>
      <button type="button" class="sr-addrow" data-sr-add="<?= (int) $u['id'] ?>" data-search="<?= h(mb_strtolower($u['name'] . ' ' . $u['area'] . ' ' . $u['address'] . ' #' . $u['id'])) ?>">
        <span class="sr-del-t"><b><?= h($u['name'] ?: 'Delivery #' . $u['id']) ?></b><span class="mut"><?= h($u['address']) ?></span><span class="mut"><?= (int) $u['n'] ?> pc<?= $u['n'] === 1 ? '' : 's' ?> · <?= h($u['kg']) ?> kg<?= $u['money'] ? ' · ' . h($u['money']) : '' ?></span></span>
        <span class="sr-addrow-go"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add</span>
      </button>
      <?php endforeach; ?>
    </div>
    <?php else: ?><div class="sr-emptybox">No delivery is waiting for a rider right now.</div><?php endif; ?>
  </div>
</div></div>
<?php endif; ?>

<?php /* ---------------------------------------------------------------- stop / proof of delivery (filled by the script) */ ?>
<div class="sl-dialog" id="sr-stop" hidden><div class="sl-modal sr-modal sr-pod" aria-labelledby="sr-stop-h" data-sr-stop-box>
  <div class="sr-modal-h"><div><h2 class="sr-h2" id="sr-stop-h" data-sr-stop-title>Stop</h2><span class="mut" data-sr-stop-sub></span></div><button class="sr-round" type="button" data-sl-close aria-label="Close"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>
  <div class="sr-modal-b" data-sr-stop-body aria-live="polite"><div class="sr-loading">Loading the stop…</div></div>
</div></div>

<?php /* ---------------------------------------------------------------- vehicle (riders view) */ ?>
<?php if ($srView === 'riders'): $srSel = $d['rv']['sel'] ?? null; $srV = $srSel['vehicle'] ?? null; ?>
<div class="sl-dialog" id="sr-vehicle" hidden><form class="sl-modal sr-modal" data-sr-vehicle-form aria-labelledby="sr-veh-h">
  <div class="sr-modal-h"><div><h2 class="sr-h2" id="sr-veh-h">Vehicle</h2><span class="mut">Capacity decides how much Smart Routing gives a rider.</span></div><button class="sr-round" type="button" data-sl-close aria-label="Close"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>
  <div class="sr-modal-b">
    <div class="sr-form2">
      <label class="sl-field sr-span2"><span class="sl-lab">Rider</span><select class="sl-in sr-in" name="driver_id" data-sr-veh-rider required><?php foreach ((array) ($d['riders'] ?? []) as $r): ?><option value="<?= (int) $r['id'] ?>"<?= $srSel && (int) $srSel['id'] === (int) $r['id'] ? ' selected' : '' ?> data-v="<?= h(json_encode(['vehicle_type' => $r['vehicle']['type'], 'plate' => $r['vehicle']['plate'], 'make_model' => $r['vehicle']['make'], 'max_kg' => $r['vehicle']['max_kg'], 'max_l' => $r['vehicle']['max_l'], 'max_parcels' => $r['vehicle']['max_n'], 'cargo_l_cm' => $r['vehicle']['l'], 'cargo_w_cm' => $r['vehicle']['w'], 'cargo_h_cm' => $r['vehicle']['h'], 'can_fragile' => $r['vehicle']['fragile'], 'can_cold' => $r['vehicle']['cold'], 'can_high_value' => $r['vehicle']['hv'], 'active' => $r['vehicle']['active']])) ?>"><?= h($r['name']) ?><?= $r['vehicle']['saved'] ? '' : ' (no vehicle saved yet)' ?></option><?php endforeach; ?></select></label>
      <label class="sl-field"><span class="sl-lab">Type</span><select class="sl-in sr-in" name="vehicle_type"><?php foreach (['bicycle', 'motorbike', 'car', 'van', 'truck'] as $vt): ?><option value="<?= $vt ?>"><?= ucfirst($vt) ?></option><?php endforeach; ?></select></label>
      <label class="sl-field"><span class="sl-lab">Number plate</span><input class="sl-in sr-in" name="plate" maxlength="20" placeholder="GR 1234-24"></label>
      <label class="sl-field sr-span2"><span class="sl-lab">Make and model</span><input class="sl-in sr-in" name="make_model" maxlength="80" placeholder="Honda Ace 125"></label>
      <label class="sl-field"><span class="sl-lab">Payload (kg)</span><input class="sl-in sr-in" name="max_kg" type="number" min="1" step="0.5"></label>
      <label class="sl-field"><span class="sl-lab">Load volume (litres)</span><input class="sl-in sr-in" name="max_l" type="number" min="1" step="1"></label>
      <label class="sl-field"><span class="sl-lab">Most pieces</span><input class="sl-in sr-in" name="max_parcels" type="number" min="1" step="1"></label>
      <label class="sl-field"><span class="sl-lab">Load length (cm)</span><input class="sl-in sr-in" name="cargo_l_cm" type="number" min="0"></label>
      <label class="sl-field"><span class="sl-lab">Load width (cm)</span><input class="sl-in sr-in" name="cargo_w_cm" type="number" min="0"></label>
      <label class="sl-field"><span class="sl-lab">Load height (cm)</span><input class="sl-in sr-in" name="cargo_h_cm" type="number" min="0"></label>
    </div>
    <div class="row g16 wrap sr-checks">
      <label><input type="hidden" name="can_fragile" value="0"><input type="checkbox" name="can_fragile" value="1">Takes fragile</label>
      <label><input type="hidden" name="can_cold" value="0"><input type="checkbox" name="can_cold" value="1">Has a cold box</label>
      <label><input type="hidden" name="can_high_value" value="0"><input type="checkbox" name="can_high_value" value="1">Takes high value</label>
      <label><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1">In service</label>
    </div>
  </div>
  <div class="sr-modal-f"><button class="sl-btn ghost sr-pillbtn" type="button" data-sl-close>Cancel</button><button class="sl-btn sr-pillbtn sr-primary" type="submit">Save vehicle</button></div>
</form></div>
<?php endif; ?>
</div>
