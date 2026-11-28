<?php
/** Status picture with a "Now" caption. Expects $sc (sl_parcelScene()) and optional $scDone. Styles: assets/swiftlane/parcel-scene.css. */
if (empty($sc)) return;
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/parcel-scene.css')) ?>">
<figure class="sdx-scene<?= $sc['agent'] ? ' agent' : '' ?> m-<?= h($sc['mode'] ?? 'air') ?>">
  <img src="<?= h(cdp_asset($sc['img'])) ?>" alt="<?= h($sc['title'] . ': ' . $sc['text']) ?>" loading="lazy" decoding="async">
  <figcaption><span class="sdx-live"><i></i><?= !empty($scDone) ? 'Done' : 'Now' ?></span><b><?= h($sc['title']) ?></b><span><?= h($sc['text']) ?></span></figcaption>
</figure>
