<?php
/**
 * Top of a consolidation: the flight or sailing, the owner's picture for the current status, and the real
 * totals of the packages on board (actual, volumetric and chargeable weight, packages, pieces, customers).
 * No capacity limit and no weight blocks: a consolidation takes as many packages as it needs.
 * Uses $d from views/sl/data/consolidations.php (rows, info, steps, map); $unit, $actual, $vol, $charge and
 * $heavy come from con_load_plan.php.
 */
$sea = !empty($d['sea']);
$map = $d['map'] ?? [];
$lane = $map['lane'] ?? ['from' => ['code' => '', 'city' => ''], 'to' => ['code' => '', 'city' => '']];
$pct = (int) ($map['pct'] ?? 0);
// Only move the marker once the flight / ship has left (step 3 of the operation timeline: in the air / at sea).
$stageNow = 0;
foreach ($d['steps'] ?? [] as $si => $st) { if (($st['cls'] ?? '') === 'now') $stageNow = $si + 1; elseif (($st['cls'] ?? '') === 'done') $stageNow = max($stageNow, $si + 2); }
if ($stageNow < 3 && empty($map['arrived'])) $pct = 0;
$info = [];
foreach ($d['info'] ?? [] as [$k, $v]) $info[$k] = $v;
$from = $lane['from']; $to = $lane['to'];
$arrived = !empty($map['arrived']);
$late = !empty($map['late']);

if (defined('CDP_ROOT')) {
    require_once CDP_ROOT . '/helpers/cargo_art.php';
    $artPick = sl_cargoArtFor((int) ($d['stId'] ?? 0), (string) ($d['st'] ?? ''), $sea);
} else { // stand-alone test page without the app
    $artPick = $sea ? ['key' => 'packing', 'file' => 'assets/swiftlane/cargo/sea-1-packing-container.webp', 'alt' => 'Container being packed'] : ['key' => 'plane', 'file' => 'assets/swiftlane/cargo/air-cargo-plane.webp', 'alt' => 'Cargo aircraft'];
}
$stats = [
    ['Packages', (string) count($d['rows']), 'i-box'],
    ['Customers', (string) (int) ($d['custN'] ?? 0), 'i-users'],
    ['Pieces', (string) (int) ($d['pcsN'] ?? 0), 'i-layers'],
    ['Actual weight', $fmtW($actual), 'i-gauge'],
    [$sea ? 'Volume' : 'Volumetric', $fmtW($vol), 'i-grid'],
    ['Chargeable', $fmtW($charge), 'i-tag'],
];
?>
<section class="ccv-flight" aria-label="<?= $sea ? 'Sailing' : 'Flight' ?>">
  <div class="ccv-fh"><span class="ccv-ic"><i class="ic <?= $sea ? 'i-ship' : 'i-plane' ?>" aria-hidden="true"></i></span><span class="grow"><b><?= $sea ? 'Shipped via ' : 'Flying with ' ?><?= h($info[$sea ? 'Shipping line' : 'Carrier'] ?? '—') ?></b><small><?= $sea ? 'Bill of lading' : 'Master air waybill' ?> <span class="mono"><?= h($info[$sea ? 'Bill of lading' : 'Master air waybill'] ?? 'Not set') ?></span><?= !empty($info[$sea ? 'Seal' : 'Seals / ULD']) && $info[$sea ? 'Seal' : 'Seals / ULD'] !== '—' ? ' · ' . ($sea ? 'Seal ' : 'ULD ') . h($info[$sea ? 'Seal' : 'Seals / ULD']) : '' ?></small></span><?php if (!empty($d['st'])): ?><span class="sl-pill <?= h($d['stCls'] ?? '') ?>"><?= h($d['st']) ?></span><?php endif; ?></div>
  <div class="ccv-route">
    <div class="ccv-end"><b><?= h($from['code']) ?></b><span><?= h($from['city']) ?></span><small><?= $stageNow < 3 ? 'Packing since' : ($sea ? 'Sailed' : 'Departed') ?> · <?= h($info['Departs'] ?? explode(' · ', (string) ($info['Sailed · ETA'] ?? '—'))[0]) ?></small></div>
    <div class="ccv-track" role="img" aria-label="<?= $arrived ? 'Arrived' : h($pct . '% of the way') ?>"><i class="ccv-done" style="width:<?= $pct ?>%"></i><span class="ccv-craft" style="left:<?= $pct ?>%"><i class="ic ic-sm <?= $sea ? 'i-ship' : 'i-plane' ?>" aria-hidden="true"></i></span><small><?= $arrived ? 'Arrived' : ($pct > 0 ? h($pct . '% of the way' . (($map['onway'] ?? '') !== 'Not departed' ? ' · ' . ($map['onway'] ?? '') : '')) : 'Not departed') ?></small></div>
    <div class="ccv-end r"><b><?= h($to['code']) ?></b><span><?= h($to['city']) ?></span><small class="<?= $late ? 'bad' : '' ?>">ETA · <?= h($map['eta'] ?? 'Not set') ?><?= $late ? ' · late' : '' ?></small></div>
  </div>
</section>

<div class="ccv-over">
  <section class="ccv-hero" aria-label="<?= $sea ? 'Container' : 'Aircraft' ?> and load">
    <figure class="ccv-art<?= $artPick['key'] === 'ship' ? ' blend' : '' ?>">
      <img src="<?= h(function_exists('cdp_asset') ? cdp_asset($artPick['file']) : $artPick['file']) ?>" alt="<?= h($artPick['alt']) ?>" width="2000" height="<?= in_array($artPick['key'], ['opened', 'loading'], true) ? 1000 : 667 ?>" decoding="async">
      <?php if (!empty($d['st'])): ?><figcaption><span class="ccv-dot" aria-hidden="true"></span><?= h($d['st']) ?></figcaption><?php endif; ?>
    </figure>
    <dl class="ccv-stats">
      <?php foreach ($stats as [$k, $v, $ic]): ?><div><dt><i class="ic ic-sm <?= h($ic) ?>" aria-hidden="true"></i><?= h($k) ?></dt><dd><?= h($v) ?></dd></div><?php endforeach; ?>
    </dl>
    <?php if ($heavy): ?><p class="ccv-note"><i class="ic ic-sm i-info" aria-hidden="true"></i>Heaviest package: <a class="mono" href="<?= h($heavy['href']) ?>"><?= h($heavy['id']) ?></a> · <?= h($heavy['w']) ?> (<?= h($heavy['name']) ?>). No weight limit is applied to this <?= $sea ? 'container' : 'consolidation' ?>.</p><?php endif; ?>
  </section>

  <aside class="ccv-side">
    <section class="ccv-card" aria-labelledby="ccv-det">
      <h3 id="ccv-det"><?= $sea ? 'Sailing details' : 'Flight details' ?></h3>
      <dl class="ccv-kv"><?php foreach ($d['info'] ?? [] as [$k, $v]): ?><div><dt><?= h($k) ?></dt><dd><?= h($v) ?></dd></div><?php endforeach; ?></dl>
      <?php if (!empty($can['edit'])): ?><a class="sl-btn ghost sm" href="<?= h($can['edit']) ?>"><i class="ic ic-sm i-edit" aria-hidden="true"></i>Edit details</a><?php endif; ?>
    </section>
    <section class="ccv-card" aria-labelledby="ccv-tl">
      <h3 id="ccv-tl">Progress</h3>
      <ol class="ccv-steps"><?php foreach ($d['steps'] ?? [] as $st): ?><li class="<?= h($st['cls']) ?>"><i aria-hidden="true"></i><span><b><?= h($st['t']) ?></b><small><?= h($st['d'] ?: ($st['cls'] === '' ? 'Not yet' : '—')) ?></small></span></li><?php endforeach; ?></ol>
    </section>
  </aside>
</div>
