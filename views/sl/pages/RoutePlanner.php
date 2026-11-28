<?php
/**
 * RoutePlanner (admin): parcel allocation, route optimisation, inventory on board, live fleet, roster.
 * Data: views/sl/data/route_planner.php (sl_routePlannerData). Writes: ajax/sl/route_planner_ajax.php
 * (ops propose/move/lock/confirm/discard/reoptimise/add_stop/remove_stop/vehicle_save/settings_save).
 * Script: dataJs/sl/route_planner.js. Maps: assets/swiftlane/route-map.js (SLRouteMap) with a schematic fallback.
 */
if (!isset($d)) { $d = []; }
$rpTab = (string) ($d['tab'] ?? 'plan');
$rpPlan = $d['plan'] ?? null;
$rpSel = $d['sel'] ?? null;
$rpJson = $d['json'] ?? ['ajax' => $d['ajax'] ?? 'ajax/sl/route_planner_ajax.php', 'tab' => $rpTab];
?>
<div class="rp" data-rp-root data-rp-tab="<?= h($rpTab) ?>">
<script type="application/json" id="rp-data"><?= json_encode($rpJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<div class="row g8 mut sm"><a href="<?= h(sl_route('LastMile')) ?>">Last-mile delivery</a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)">Route planner</span></div>
<div class="sl-head rp-head">
  <div><h1 class="sl-title">Route planner</h1><p class="sl-lede">Share the cargo hold between riders by vehicle capacity and area, order each rider's stops from the start place, then follow every route live.</p></div>
  <div class="row g8 wrap rp-actions">
    <span class="rp-date mut sm"><i class="ic ic-sm i-calendar" aria-hidden="true"></i><?= h($d['dateLabel'] ?? '') ?> · <span data-sl-clock><?= h($d['now'] ?? date('H:i')) ?></span></span>
    <a class="sl-btn ghost" href="<?= h(sl_route('RiderDispatch')) ?>"><i class="ic ic-sm i-users" aria-hidden="true"></i>Dispatch board</a>
    <a class="sl-btn ghost" href="<?= h(sl_route('DeliveryDispatch')) ?>"><i class="ic ic-sm i-inbox" aria-hidden="true"></i>Delivery requests</a>
  </div>
</div>
<nav class="sl-seg boxed rp-tabs" aria-label="Route planner sections"><?php foreach ((array) ($d['tabs'] ?? []) as $t): ?><a class="<?= !empty($t['on']) ? 'on' : '' ?>" href="<?= h($t['href']) ?>"<?= !empty($t['on']) ? ' aria-current="page"' : '' ?>><?= h($t['label']) ?></a><?php endforeach; ?></nav>

<?php if (empty($d['ready'])): ?>
<section class="sl-card rp-empty col g10" data-rp-empty>
  <div class="row g10"><span class="sl-tile"><i class="ic i-route" aria-hidden="true"></i></span><div class="col"><b class="fw5">The route planner is being installed</b><span class="mut sm">The planning engine (helpers/route_planner.php) and its tables are not available yet. Riders and delivery requests keep working from the dispatch board in the meantime.</span></div></div>
  <div class="row g8 wrap"><a class="sl-btn" href="<?= h(sl_route('DeliveryDispatch')) ?>">Open delivery requests</a><a class="sl-btn ghost" href="<?= h(sl_route('RiderDispatch')) ?>">Dispatch board</a></div>
</section>
</div>
<?php return; endif; ?>

<?php foreach ((array) ($d['notes'] ?? []) as $n): ?><div class="sl-banner warn" role="status"><?= h($n) ?></div><?php endforeach; ?>
<?php if (in_array($rpTab, ['plan', 'live', 'fleet'], true)) include __DIR__ . '/partials/rp_admin_kpis.php'; ?>

<?php if ($rpTab === 'plan'): ?>
<section class="rp-bar sl-card" aria-label="Plan controls">
  <form class="row g8 wrap rp-propose" data-rp-propose>
    <label class="sl-field rp-f"><span class="sl-lab">Start place</span><select class="sl-in" name="start_id" aria-label="Start place"><?php foreach ((array) ($d['starts'] ?? []) as $sp): ?><option value="<?= h($sp['id']) ?>"<?= (string) $sp['id'] === (string) ($d['startId'] ?? '') ? ' selected' : '' ?>><?= h($sp['label']) ?></option><?php endforeach; ?></select></label>
    <label class="sl-field rp-f"><span class="sl-lab">Goal</span><select class="sl-in" name="mode" aria-label="Allocation goal"><option value="fill"<?= ($d['fillMode'] ?? 'fill') === 'fill' ? ' selected' : '' ?>>Fewest riders, fullest loads</option><option value="balance"<?= ($d['fillMode'] ?? '') === 'balance' ? ' selected' : '' ?>>Balance work between riders</option></select></label>
    <div class="row g8 wrap rp-bar-btns">
      <button class="sl-btn" type="submit" data-rp-auto><i class="ic ic-sm i-wand" aria-hidden="true"></i><?= $rpPlan ? 'Re-run auto-allocate' : 'Auto-allocate' ?></button>
      <?php if ($rpPlan): ?>
      <button class="sl-btn ghost" type="button" data-rp-discard data-plan="<?= (int) $rpPlan['id'] ?>"><i class="ic ic-sm i-trash" aria-hidden="true"></i>Discard</button>
      <button class="sl-btn rp-go" type="button" data-sl-open="rp-confirm"><i class="ic ic-sm i-check" aria-hidden="true"></i>Confirm and assign</button>
      <?php endif; ?>
    </div>
  </form>
  <?php $rpIss = $d['planIssues'] ?? null; if ($rpPlan && $rpIss): ?><div class="sl-banner warn rp-plan-issue" role="status" data-rp-plan-issue><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><b class="fw5">Plan out of date:</b> <?= h(implode('; ', $rpIss['msgs'])) ?>. Re-run auto-allocate<?= !empty($rpIss['block']) ? ' or move those stops before confirming' : ', or check the riders marked in red' ?>.</span></div><?php endif; ?>
  <div class="rp-plan-note mut sm" data-rp-plan-note><?php if ($rpPlan): ?><span class="sl-pill sorting">Draft plan #<?= (int) $rpPlan['id'] ?></span> <?= (int) $rpPlan['n'] ?> stops across <?= (int) $rpPlan['riders'] ?> riders<?= $rpPlan['created'] ? ' · proposed ' . h($rpPlan['created']) : '' ?>. Drag stops between riders, lock a rider to keep their set, then confirm.<?php else: ?>No draft yet. Auto-allocate proposes a set for each available rider; nothing is assigned until you confirm.<?php endif; ?></div>
</section>
<div class="rp-plan">
  <?php include __DIR__ . '/partials/rp_admin_riders.php'; ?>
  <div class="rp-mid col g16"><?php include __DIR__ . '/partials/rp_admin_rider_card.php'; ?></div>
  <?php include __DIR__ . '/partials/rp_admin_hold.php'; ?>
</div>
<?php elseif ($rpTab === 'live'): ?>
<?php include __DIR__ . '/partials/rp_admin_live.php'; ?>
<?php elseif ($rpTab === 'fleet'): ?>
<div class="rp-fleet">
  <?php $rpNoDrag = true; include __DIR__ . '/partials/rp_admin_riders.php'; ?>
  <div class="rp-mid col g16"><?php include __DIR__ . '/partials/rp_admin_rider_card.php'; ?></div>
</div>
<?php elseif ($rpTab === 'timeline'): ?>
<?php include __DIR__ . '/partials/rp_admin_timeline.php'; ?>
<?php elseif ($rpTab === 'settings'): ?>
<?php include __DIR__ . '/partials/rp_admin_settings.php'; ?>
<?php endif; ?>

<?php if ($rpTab === 'plan' && $rpPlan): ?>
<div class="sl-dialog" id="rp-confirm" hidden><div class="sl-modal col g12" aria-labelledby="rp-confirm-h">
  <h2 class="sl-ct" id="rp-confirm-h">Confirm plan #<?= (int) $rpPlan['id'] ?></h2>
  <p class="mut sm">Each stop is assigned to its rider through the normal delivery workflow: the rider gets a message, the customer's delivery page shows the rider, and each rider gets today's route with a loading checklist.</p>
  <?php
  $rpOver = []; $rpBad = [];
  foreach ((array) ($d['planIssues']['ineligible'] ?? []) as $ie) $rpBad[(int) $ie['driver_id']][] = '#' . (int) $ie['request_id'] . ' ' . $ie['reason'];
  ?>
  <div class="col g6 rp-confirm-list"><?php foreach ((array) ($d['riders'] ?? []) as $r): if (!$r['planUnits']) continue;
    // The fullest dimension decides: weight, volume or parcel count.
    $rpTop = null; foreach ($r['bars'] as $bk => $bb) if (!$rpTop || $bb['pct'] > $rpTop['pct']) $rpTop = $bb + ['k' => $bk];
    $rpIsOver = $rpTop && $rpTop['pct'] > 100; if ($rpIsOver) $rpOver[] = $r['name'];
    $rpN = count($r['planUnits']); ?>
    <div class="row between g8 rp-confirm-row<?= $rpIsOver || isset($rpBad[$r['id']]) ? ' bad' : '' ?>"><span class="row g8"><span class="rp-dot" style="background:<?= h($r['color']) ?>"></span><b class="fw5"><?= h($r['name']) ?></b><span class="mut sm"><?= h($r['vehicle']['typeLabel']) ?></span></span><span class="mono sm"><?= $rpN ?> stop<?= $rpN === 1 ? '' : 's' ?> · <?= h($r['route']['km'] ?? '') ?> · <?= (int) ($rpTop['pct'] ?? 0) ?>% <?= h(strtolower($rpTop['label'] ?? '')) ?><?= $rpIsOver ? ' · over capacity' : '' ?></span></div>
    <?php foreach ($rpBad[$r['id']] ?? [] as $bad): ?><div class="sm rp-confirm-row bad">Cannot go with <?= h($r['name']) ?>: <?= h($bad) ?></div><?php endforeach; ?>
  <?php endforeach; ?></div>
  <?php if ($rpOver && empty($d['planIssues']['block'])): ?><label class="row g8 sm sl-banner warn"><input type="checkbox" data-rp-ack-over value="1">I accept the overload for <?= h(implode(', ', $rpOver)) ?> (at most 110% of the vehicle).</label><?php endif; ?>
  <?php if (!empty($d['planIssues']['block'])): ?><div class="sl-banner warn">Some stops do not suit their rider's vehicle or a rider is far over capacity. Move them, or re-run auto-allocate, before confirming.</div><?php endif; ?>
  <?php $rpLeft = count((array) ($d['hold'] ?? [])) + count((array) ($d['holdUnplaceable'] ?? [])); if ($rpLeft): ?><div class="sl-banner warn"><?= (int) $rpLeft ?> request<?= $rpLeft === 1 ? '' : 's' ?> stay<?= $rpLeft === 1 ? 's' : '' ?> in the hold for a later run.</div><?php endif; ?>
  <div class="col g6" data-rp-confirm-result hidden></div>
  <div class="row g8"><button class="sl-btn" type="button" data-rp-confirm data-plan="<?= (int) $rpPlan['id'] ?>"<?= !empty($d['planIssues']['block']) ? ' disabled' : '' ?><?= $rpOver ? ' data-needs-ack' : '' ?>>Assign <?= (int) $rpPlan['n'] ?> stop<?= (int) $rpPlan['n'] === 1 ? '' : 's' ?></button><button class="sl-btn ghost" type="button" data-sl-close>Cancel</button></div>
</div></div>
<?php endif; ?>

<div class="sl-dialog" id="rp-move" hidden><div class="sl-modal col g12" aria-labelledby="rp-move-h">
  <h2 class="sl-ct" id="rp-move-h">Move stop <span data-rp-move-label></span></h2>
  <span class="mut sm">Pick the rider who should take it. Capacity is checked before the move.</span>
  <div class="col g6" data-rp-move-list><?php foreach ((array) ($d['riders'] ?? []) as $r): ?><button type="button" class="rp-mv" data-rp-move-to="<?= (int) $r['id'] ?>"<?= $r['locked'] ? ' disabled data-locked title="Locked: unlock this rider to change their stops"' : '' ?>><span class="rp-dot" style="background:<?= h($r['color']) ?>"></span><span class="grow col"><b class="fw5"><?= h($r['name']) ?></b><span class="mut sm"><?= h($r['vehicle']['typeLabel']) ?> · <?= h($r['stLabel']) ?> · <?= h($r['bars']['kg']['text'] ?? '') ?></span></span><?php if ($r['locked']): ?><span class="sl-pill plain"><i class="ic ic-sm i-lock" aria-hidden="true"></i>Locked</span><?php endif; ?></button><?php endforeach; ?>
  <button type="button" class="rp-mv" data-rp-move-to="0"><span class="rp-dot hold"></span><span class="grow col"><b class="fw5">Back to the cargo hold</b><span class="mut sm">Leave it for a later run</span></span></button></div>
  <div class="row g8"><button class="sl-btn ghost" type="button" data-sl-close>Cancel</button></div>
</div></div>

<div class="sl-dialog rp-ask" id="rp-ask" hidden><div class="sl-modal col g12" aria-labelledby="rp-ask-h">
  <h2 class="sl-ct" id="rp-ask-h" data-rp-ask-title>Are you sure?</h2>
  <p class="sm" data-rp-ask-msg></p>
  <div class="row g8"><button class="sl-btn" type="button" data-rp-ask-ok>Continue</button><button class="sl-btn ghost" type="button" data-sl-close data-rp-ask-cancel>Cancel</button></div>
</div></div>

<div class="sl-dialog" id="rp-parcel" hidden><div class="sl-modal col g12 rp-drawer" aria-labelledby="rp-parcel-h">
  <div class="row between g8"><h2 class="sl-ct" id="rp-parcel-h">Parcels</h2><button class="sl-btn ghost sm" type="button" data-sl-close aria-label="Close"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>
  <div class="col g10" data-rp-parcel-body></div>
</div></div>

<?php if ($rpSel): $v = $rpSel['vehicle']; ?>
<div class="sl-dialog" id="rp-vehicle" hidden><form class="sl-modal col g12" data-sl-form data-sl-reload action="<?= h($d['ajax'] ?? 'ajax/sl/route_planner_ajax.php') ?>" method="post" aria-labelledby="rp-veh-h">
  <h2 class="sl-ct" id="rp-veh-h">Vehicle · <?= h($rpSel['name']) ?></h2>
  <input type="hidden" name="op" value="vehicle_save"><input type="hidden" name="driver_id" value="<?= (int) $rpSel['id'] ?>">
  <div class="rp-form2">
    <label class="sl-field"><span class="sl-lab">Type</span><select class="sl-in" name="vehicle_type"><?php foreach (['bicycle', 'motorbike', 'car', 'van', 'truck'] as $vt): ?><option value="<?= $vt ?>"<?= $v['type'] === $vt ? ' selected' : '' ?>><?= ucfirst($vt) ?></option><?php endforeach; ?></select></label>
    <label class="sl-field"><span class="sl-lab">Plate</span><input class="sl-in mono" name="plate" maxlength="20" value="<?= h($v['plate']) ?>" placeholder="GR 1234-24"></label>
    <label class="sl-field"><span class="sl-lab">Make and model</span><input class="sl-in" name="make_model" maxlength="80" value="<?= h($v['make']) ?>" placeholder="Honda Ace 125"></label>
    <label class="sl-field"><span class="sl-lab">Payload (kg)</span><input class="sl-in mono" name="max_kg" type="number" min="0" step="0.5" value="<?= h($v['max_kg']) ?>"></label>
    <label class="sl-field"><span class="sl-lab">Load volume (litres)</span><input class="sl-in mono" name="max_l" type="number" min="0" step="1" value="<?= h($v['max_l']) ?>"></label>
    <label class="sl-field"><span class="sl-lab">Max parcels</span><input class="sl-in mono" name="max_parcels" type="number" min="0" step="1" value="<?= h($v['max_n']) ?>"></label>
    <label class="sl-field"><span class="sl-lab">Cargo length (cm)</span><input class="sl-in mono" name="cargo_l_cm" type="number" min="0" value="<?= h($v['cargo']['l']) ?>"></label>
    <label class="sl-field"><span class="sl-lab">Cargo width (cm)</span><input class="sl-in mono" name="cargo_w_cm" type="number" min="0" value="<?= h($v['cargo']['w']) ?>"></label>
    <label class="sl-field"><span class="sl-lab">Cargo height (cm)</span><input class="sl-in mono" name="cargo_h_cm" type="number" min="0" value="<?= h($v['cargo']['h']) ?>"></label>
  </div>
  <div class="row g16 wrap">
    <?php /* An unticked box is not posted: the hidden 0 before each box makes "off" reach the server. */ ?>
    <label class="row g6"><input type="hidden" name="can_fragile" value="0"><input type="checkbox" name="can_fragile" value="1"<?= $v['fragile'] ? ' checked' : '' ?>>Takes fragile</label>
    <label class="row g6"><input type="hidden" name="can_cold" value="0"><input type="checkbox" name="can_cold" value="1"<?= $v['cold'] ? ' checked' : '' ?>>Has a cold box</label>
    <label class="row g6"><input type="hidden" name="can_high_value" value="0"><input type="checkbox" name="can_high_value" value="1"<?= $v['hv'] ? ' checked' : '' ?>>Takes high value</label>
    <label class="row g6"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1"<?= $v['active'] ? ' checked' : '' ?>>In service</label>
  </div>
  <div class="row g8"><button class="sl-btn" type="submit">Save vehicle</button><button class="sl-btn ghost" type="button" data-sl-close>Cancel</button></div>
</form></div>
<?php endif; ?>
</div>
