<?php
/**
 * Route planner settings: start places (warehouse/office), service times, speeds, alert thresholds,
 * vehicle defaults by type and the allocation goal. Saves with op=settings_save (rp_saveSettings).
 * Input: $d['settingsForm'] (rpv_settingsForm).
 */
$sf = $d['settingsForm'] ?? null;
if (!$sf): ?><section class="sl-card mut sm">Settings are not available.</section><?php return; endif; ?>
<form class="rp-set col g16" data-sl-form data-sl-reload action="<?= h($d['ajax']) ?>" method="post">
  <input type="hidden" name="op" value="settings_save"><input type="hidden" name="revision" value="<?= (int) $sf['revision'] ?>">
  <section class="sl-card col g10"><div class="col"><h2 class="sl-ct">Start places</h2><span class="mut sm">Where riders load and start their routes. Coordinates in decimal degrees (Accra office 5.6037, -0.1870).</span></div>
    <div class="col g8" data-rp-starts>
      <?php foreach ($sf['starts'] as $i => $sp): ?>
      <div class="rp-start-r" data-rp-start-row>
        <label class="row g6 rp-def"><input type="radio" name="default_start" value="<?= h($sp['id']) ?>"<?= (string) $sp['id'] === (string) $sf['default'] ? ' checked' : '' ?>><span class="sm">Default</span></label>
        <input type="hidden" name="start_places[<?= (int) $i ?>][id]" value="<?= h($sp['id']) ?>">
        <label class="sl-field grow"><span class="sl-lab">Name</span><input class="sl-in" name="start_places[<?= (int) $i ?>][label]" value="<?= h($sp['label']) ?>" maxlength="80" required></label>
        <label class="sl-field"><span class="sl-lab">Latitude</span><input class="sl-in mono" name="start_places[<?= (int) $i ?>][lat]" type="number" step="0.000001" min="-90" max="90" value="<?= h($sp['lat']) ?>" required></label>
        <label class="sl-field"><span class="sl-lab">Longitude</span><input class="sl-in mono" name="start_places[<?= (int) $i ?>][lng]" type="number" step="0.000001" min="-180" max="180" value="<?= h($sp['lng']) ?>" required></label>
        <?php if ($i > 0): ?><button type="button" class="sl-btn ghost sm" data-rp-start-del aria-label="Remove <?= h($sp['label']) ?>"><i class="ic ic-sm i-trash" aria-hidden="true"></i></button><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <div><button type="button" class="sl-btn ghost sm" data-rp-start-add><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add a start place</button></div>
  </section>
  <section class="sl-card col g10"><h2 class="sl-ct">Stops, positions and alerts</h2>
    <div class="rp-form3"><?php foreach ($sf['fields'] as [$name, $label, $val, $help, $step]): ?><label class="sl-field"><span class="sl-lab"><?= h($label) ?></span><input class="sl-in mono" type="number" name="<?= h($name) ?>" value="<?= h($val) ?>" min="0" step="<?= h($step) ?>"><span class="mut sm"><?= h($help) ?></span></label><?php endforeach; ?></div>
    <fieldset class="col g6 rp-fs"><legend class="sl-lab">Auto-allocate goal</legend>
      <label class="row g8"><input type="radio" name="fill_mode" value="fill"<?= $sf['fill_mode'] !== 'balance' ? ' checked' : '' ?>><span>Fewest riders, fullest loads (less empty space)</span></label>
      <label class="row g8"><input type="radio" name="fill_mode" value="balance"<?= $sf['fill_mode'] === 'balance' ? ' checked' : '' ?>><span>Balance the work between available riders</span></label></fieldset>
  </section>
  <section class="sl-card col g10"><div class="col"><h2 class="sl-ct">Vehicle defaults</h2><span class="mut sm">Used for riders without their own vehicle card. Speeds are average door-to-door speeds in Accra traffic.</span></div>
    <div class="sl-tw"><table class="sl-table rp-vt"><thead><tr><th>Type</th><th>Speed km/h</th><th>Payload kg</th><th>Volume L</th><th>Parcels</th></tr></thead><tbody>
      <?php foreach ($sf['vehicles'] as $vv): $t = $vv['type']; ?><tr><td><?= h($vv['label']) ?></td><td><input class="sl-in mono" type="number" min="1" max="120" step="1" name="speeds[<?= h($t) ?>]" value="<?= h($vv['speed']) ?>" aria-label="<?= h($vv['label']) ?> speed"></td><td><input class="sl-in mono" type="number" min="0" step="1" name="vehicle_defaults[<?= h($t) ?>][max_kg]" value="<?= h($vv['max_kg']) ?>" aria-label="<?= h($vv['label']) ?> payload"></td><td><input class="sl-in mono" type="number" min="0" step="1" name="vehicle_defaults[<?= h($t) ?>][max_l]" value="<?= h($vv['max_l']) ?>" aria-label="<?= h($vv['label']) ?> volume"></td><td><input class="sl-in mono" type="number" min="0" step="1" name="vehicle_defaults[<?= h($t) ?>][max_parcels]" value="<?= h($vv['max_parcels']) ?>" aria-label="<?= h($vv['label']) ?> parcels"></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </section>
  <?php if (!empty($d['canSettings'])): ?><div class="row g8"><button class="sl-btn" type="submit">Save settings</button></div>
  <?php else: ?><div class="sl-banner warn">These settings apply to every branch. Only head-office managers can change them.</div><?php endif; ?>
</form>
