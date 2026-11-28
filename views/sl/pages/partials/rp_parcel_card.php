<?php
/**
 * Parcel chip / drawer body shared by the route planner and the rider app.
 * Input: $p = rp_parcelDetails() output (tolerant of the admin view model: window may be a string,
 * value may be major units with currency). Optional $rpCardCompact = true for a one-line chip.
 * Styles: assets/swiftlane/route-map.css (.rp-pc).
 */
$p = is_array($p ?? null) ? $p : [];
$rpNum = static fn($v) => rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.');
$rpL = (float) ($p['l'] ?? $p['length_cm'] ?? 0); $rpW = (float) ($p['w'] ?? $p['width_cm'] ?? 0); $rpH = (float) ($p['h'] ?? $p['height_cm'] ?? 0);
$rpDims = $rpL && $rpW && $rpH ? $rpNum($rpL) . ' × ' . $rpNum($rpW) . ' × ' . $rpNum($rpH) . ' cm' : 'Size not recorded';
$rpKg = (float) ($p['kg'] ?? $p['weight_kg'] ?? 0);
$rpVol = (float) ($p['volume_l'] ?? ($rpL && $rpW && $rpH ? $rpL * $rpW * $rpH / 1000 : 0));
$rpWin = $p['window'] ?? '';
if (is_array($rpWin)) $rpWin = trim(substr((string) ($rpWin['start'] ?? ''), 0, 5) . (!empty($rpWin['end']) ? '–' . substr((string) $rpWin['end'], 0, 5) : ''), '–');
elseif (!$rpWin && (!empty($p['window_start']) || !empty($p['window_end']))) $rpWin = trim(substr((string) ($p['window_start'] ?? ''), 0, 5) . '–' . substr((string) ($p['window_end'] ?? ''), 0, 5), '–');
$rpValue = isset($p['value_cents']) && $p['value_cents'] !== null && $p['value_cents'] !== '' ? (int) $p['value_cents'] / 100 : (float) ($p['value'] ?? 0);
$rpCur = (string) ($p['currency'] ?? '');
$rpHandling = $p['handling'] ?? [];
if (is_string($rpHandling)) $rpHandling = array_filter(array_map('trim', explode(',', $rpHandling)));
$rpHLabels = ['fragile' => 'Fragile', 'cold' => 'Keep cold', 'high_value' => 'High value', 'oversize' => 'Oversize', 'liquid' => 'Liquid'];
$rpPrio = (int) ($p['priority'] ?? 0);
$rpLoad = (string) ($p['load_state'] ?? '');
$rpOutcome = (string) ($p['outcome'] ?? '');
$rpCompact = !empty($rpCardCompact);
?>
<div class="rp-pc<?= $rpCompact ? ' is-compact' : '' ?>" data-parcel="<?= h(($p['module'] ?? 'air') . ':' . (int) ($p['order_id'] ?? 0)) ?>">
  <div class="rp-pc-head">
    <b class="rp-pc-trk"><?= h(($p['tracking'] ?? '') ?: ('#' . (int) ($p['order_id'] ?? 0))) ?></b>
    <?php if (!empty($p['module'])): ?><span class="rp-pc-tag"><?= $p['module'] === 'sea' ? 'Sea' : 'Air' ?></span><?php endif; ?>
    <?php if ($rpPrio): ?><span class="rp-pc-tag is-<?= $rpPrio >= 2 ? 'urgent' : 'high' ?>"><?= $rpPrio >= 2 ? 'Urgent' : 'High priority' ?></span><?php endif; ?>
    <?php if ($rpOutcome && $rpOutcome !== 'onboard'): ?><span class="rp-pc-tag is-<?= h($rpOutcome) ?>"><?= h(ucfirst($rpOutcome)) ?></span>
    <?php elseif ($rpLoad): ?><span class="rp-pc-tag is-<?= h($rpLoad) ?>"><?= h(['loaded' => 'On board', 'missing' => 'Missing', 'pending' => 'To load'][$rpLoad] ?? $rpLoad) ?></span>
    <?php elseif (!empty($p['status'])): ?><span class="rp-pc-tag"><?= h($p['status']) ?></span><?php endif; ?>
  </div>
  <?php if (!empty($p['contents'])): ?><div class="rp-pc-contents"><?= h($p['contents']) ?></div><?php endif; ?>
  <dl class="rp-pc-grid">
    <div><dt>Size</dt><dd><?= h($rpDims) ?></dd></div>
    <div><dt>Weight</dt><dd><?= h($rpNum($rpKg)) ?> kg</dd></div>
    <div><dt>Volume</dt><dd><?= h($rpNum($rpVol)) ?> L<?= !empty($p['estimated']) ? ' <small title="Estimated from the weight">est.</small>' : '' ?></dd></div>
    <?php if ($rpValue > 0): ?><div><dt>Value</dt><dd><?= h(trim($rpCur . ' ' . number_format($rpValue, 2))) ?></dd></div><?php endif; ?>
    <?php if ($rpWin): ?><div><dt>Window</dt><dd><?= h($rpWin) ?></dd></div><?php endif; ?>
  </dl>
  <?php if ($rpHandling): ?>
    <div class="rp-pc-badges"><?php foreach ($rpHandling as $rpH1): ?><span class="rp-hb h-<?= h($rpH1) ?>"><?= h($rpHLabels[$rpH1] ?? $rpH1) ?></span><?php endforeach; ?></div>
  <?php endif; ?>
  <?php if (!empty($p['note'])): ?><p class="rp-pc-note"><?= h($p['note']) ?></p><?php endif; ?>
</div>
