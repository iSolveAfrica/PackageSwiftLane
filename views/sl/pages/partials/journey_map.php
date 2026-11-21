<?php
/**
 * Journey map: a real map (Leaflet with OpenStreetMap tiles) showing the direct
 * route, with the aircraft drawn on it at the shipment's stage.
 * Expects $d['map'] = ['from' => [lat, lon, label], 'to' => [...], 'frac' => 0..1] or null (sea freight).
 * The chip text comes from $d['vName'], $d['vSub'], $d['vNow'], $d['vIcon'] when present.
 */
$m = $d['map'] ?? null;
static $assetsPrinted = false;
?>
<?php if (!$assetsPrinted): $assetsPrinted = true; ?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/vendor/libs/leaflet/leaflet.css')) ?>">
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/journey-map.css')) ?>">
<script src="<?= h(cdp_asset('assets/vendor/libs/leaflet/leaflet.js')) ?>" defer></script>
<script src="<?= h(cdp_asset('assets/swiftlane/journey-map.js')) ?>" defer></script>
<?php endif; ?>
<div class="tk-map jm-map" <?php if ($m): ?>data-journey-map data-from="<?= h(json_encode(['lat' => (float) $m['from']['lat'], 'lon' => (float) $m['from']['lon'], 'label' => $m['from']['label']])) ?>" data-to="<?= h(json_encode(['lat' => (float) $m['to']['lat'], 'lon' => (float) $m['to']['lon'], 'label' => $m['to']['label']])) ?>" data-frac="<?= h((string) $m['frac']) ?>" role="img" aria-label="Flight from <?= h($m['from']['label']) ?> to <?= h($m['to']['label']) ?>, <?= (int) round($m['frac'] * 100) ?> percent of the way"<?php else: ?>role="img" aria-label="Sea freight is tracked by vessel"<?php endif; ?>>
  <?php if (!$m): ?>
    <div class="jm-empty"><i class="ic i-ship" aria-hidden="true"></i><span>Sea freight is tracked by vessel. There is no air route map for this shipment.</span></div>
  <?php else: ?>
    <noscript><div class="jm-empty"><span><?= h($m['from']['label']) ?> → <?= h($m['to']['label']) ?></span></div></noscript>
  <?php endif; ?>
  <?php if ($m && !empty($d['vName'])): ?>
  <div class="tk-chip jm-chip-a"><i class="ic ic-sm <?= h($d['vIcon'] ?? 'i-plane') ?>" aria-hidden="true"></i><span class="col"><b class="fw5"><?= h($d['vName']) ?></b><span class="mut jm-sub"><?= h($d['vSub'] ?? '') ?></span></span></div>
  <div class="tk-chip jm-chip-b"><span class="col"><span class="mut jm-sub">Now</span><b class="fw5"><?= h($d['vNow'] ?? '') ?></b></span></div>
  <?php endif; ?>
</div>
