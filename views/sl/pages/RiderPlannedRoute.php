<?php
/**
 * RiderPlannedRoute - today's planned route from the Route Planner inside the rider app's Route
 * screen (rider_route.php): optimised stop order, map (schematic offline), live ETAs, and the
 * loading checklist (?view=load). Data: views/sl/data/riders.php sl_driverAppData ($d['rv']).
 */
if (!isset($d)) { $d = []; }
$rv = $d['rv'] ?? null;
$view = (string) ($_GET['view'] ?? 'route') === 'load' ? 'load' : 'route';
$base = sl_route('RiderMap');
$layout = (string) ($d['layout'] ?? 'auto');
?>
<div class="da rr-wrap<?= $layout !== 'auto' ? ' is-' . h($layout) : '' ?>" data-layout="<?= h($layout) ?>" data-tab="<?= h($view) ?>">
  <div class="row between wrap g8" style="padding:12px 16px 0"><a class="sl-btn ghost sm" href="<?= h(sl_route('RiderHome')) ?>"><i class="ic ic-sm i-left" aria-hidden="true"></i>Home</a>
  <div class="sl-seg" role="group" aria-label="Screen layout"><?php foreach (['auto' => 'Auto', 'phone' => 'Phone view', 'web' => 'Web view'] as $k => $lbl): ?><button type="button" class="<?= $layout === $k ? 'on' : '' ?>" aria-pressed="<?= $layout === $k ? 'true' : 'false' ?>" data-rider-layout-set="<?= h($k) ?>"><?= h($lbl) ?></button><?php endforeach; ?></div></div>
  <nav class="sl-seg boxed" aria-label="Route views" style="margin:12px 16px 0">
    <a class="<?= $view === 'route' ? 'on' : '' ?>" href="<?= h($base) ?>"<?= $view === 'route' ? ' aria-current="page"' : '' ?>>Route</a>
    <a class="<?= $view === 'load' ? 'on' : '' ?>" href="<?= h($base . (strpos($base, '?') === false ? '?' : '&') . 'view=load') ?>"<?= $view === 'load' ? ' aria-current="page"' : '' ?>>Load<?php if ($rv && $rv['inventory']['pending_load'] && in_array($rv['status'], ['planned', 'loading'], true)): ?> <b class="rr-tab-n"><?= (int) $rv['inventory']['pending_load'] ?></b><?php endif; ?></a>
  </nav>
  <div class="rr-offline" data-rr-offline role="status" hidden><i class="ic ic-sm i-alert" aria-hidden="true"></i><span data-rr-offline-msg>You are offline. Showing your route as last loaded; actions need a connection.</span><button type="button" class="sl-btn sm" data-rr-retry>Retry</button></div>
  <?php if ($view === 'load'): include __DIR__ . '/partials/rider_load_checklist.php'; else: $rrMode = 'route'; include __DIR__ . '/partials/rider_route_today.php'; endif; ?>
</div>
<?php if ($rv): ?>
<dialog class="rr-dialog" data-rr-fail-dialog aria-labelledby="rr-fail-h"><form method="dialog" class="col g12" data-rr-fail-form novalidate>
<h2 class="sl-ct" id="rr-fail-h">Delivery failed: <span data-rr-fail-name></span></h2>
<fieldset class="rr-reasons"><legend class="sl-lab">What happened?</legend><?php foreach ($rv['fail_reasons'] as $i => $reason): ?><label class="rr-radio"><input type="radio" name="reason" value="<?= h($reason) ?>"><span><?= h($reason) ?></span></label><?php endforeach; ?><label class="rr-radio"><input type="radio" name="reason" value="Other"><span>Other</span></label></fieldset>
<label class="sl-field"><span class="sl-lab">Note for dispatch (optional)</span><textarea class="sl-in" name="note" maxlength="400" rows="2" placeholder="E.g. called twice, gate locked"></textarea></label>
<div class="rr-dialog-actions"><p class="da-bad" data-rr-fail-err role="alert" hidden></p>
<div class="row g8"><button type="button" class="rr-btn" value="cancel" data-rr-fail-cancel>Back</button><button type="submit" class="rr-btn bad" value="ok">Report failed</button></div></div>
</form></dialog>
<script type="application/json" id="rr-data"><?= json_encode($rv['json'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?></script>
<?php endif; ?>
