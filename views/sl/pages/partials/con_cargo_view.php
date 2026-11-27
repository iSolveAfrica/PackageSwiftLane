<?php
/**
 * Cargo view of a consolidation (top of the load plan): the flight or sailing, a line drawing of the aircraft or
 * container ship with its cargo positions filled by the packages really on board, and the weights.
 * Uses $d from views/sl/data/consolidations.php (rows, weights, capacity, map, info, steps) and $positionMap
 * (saved bay positions, con_load_plan.php). No sample data: an empty consolidation shows empty positions.
 */
$unit = (string) ($core->weight_p ?? 'lbs');
$sea = !empty($d['sea']);
$lane = $d['map']['lane'] ?? ['from' => ['code' => '', 'city' => ''], 'to' => ['code' => '', 'city' => '']];
$pct = (int) ($d['map']['pct'] ?? 0);
// Only move the marker once the flight / ship has left (step 3 of the operation timeline: in the air / at sea).
$stageNow = 0;
foreach ($d['steps'] ?? [] as $si => $st) { if (($st['cls'] ?? '') === 'now') $stageNow = $si + 1; elseif (($st['cls'] ?? '') === 'done') $stageNow = max($stageNow, $si + 2); }
if ($stageNow < 3 && empty($d['map']['arrived'])) $pct = 0;
$info = [];
foreach ($d['info'] ?? [] as [$k, $v]) $info[$k] = $v;
$fmtW = fn($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.') . ' ' . $unit;

// Weights: actual, volumetric, chargeable (the larger, as airlines and lines charge)
$actual = 0.0; $vol = 0.0; $heavy = null;
foreach ($d['rows'] as $r) {
    $actual += (float) ($r['weightKg'] ?? 0); $vol += (float) ($r['vn'] ?? 0);
    if (!$heavy || (float) ($r['weightKg'] ?? 0) > (float) ($heavy['weightKg'] ?? 0)) $heavy = $r;
}
$charge = max($actual, $vol);
$cap = max(0.0, (float) $d['capacityKg']);
$fill = $cap > 0 ? min(100, (int) round(100 * $charge / $cap)) : 0;
$free = $cap > 0 ? max(0, $cap - $charge) : null;

// Cargo positions: air = 10 main-deck ULD positions, sea = 12 container bays. A package goes to its saved bay when it has
// one, otherwise positions are filled front to back, each up to an even share of the capacity.
$slots = $sea ? 12 : 10;
$per = $cap > 0 ? $cap / $slots : max(1.0, $charge / $slots);
$pos = array_fill(0, $slots, ['w' => 0.0, 'items' => []]);
$cursor = 0;
foreach ($d['rows'] as $r) {
    $saved = $positionMap[(int) $r['oid']]['bay'] ?? 0;
    if ($saved > 0) {
        $i = ((int) $saved - 1) % $slots;
    } else {
        while ($cursor < $slots - 1 && $pos[$cursor]['w'] + (float) ($r['weightKg'] ?? 0) > $per && $pos[$cursor]['w'] > 0) $cursor++;
        $i = $cursor;
    }
    $pos[$i]['w'] += (float) ($r['weightKg'] ?? 0);
    $pos[$i]['items'][] = $r;
}
$level = fn($w) => $w <= 0 ? 0 : max(0.12, min(1, $w / $per));
$from = $lane['from']; $to = $lane['to'];
$arrived = !empty($d['map']['arrived']);
$late = !empty($d['map']['late']);
?>
<div class="ccv">
  <section class="ccv-flight" aria-label="<?= $sea ? 'Sailing' : 'Flight' ?>">
    <div class="ccv-fh"><span class="ccv-ic"><i class="ic <?= $sea ? 'i-ship' : 'i-plane' ?>" aria-hidden="true"></i></span><span class="grow"><b><?= $sea ? 'Shipped via ' : 'Flying with ' ?><?= h($info[$sea ? 'Shipping line' : 'Carrier'] ?? '—') ?></b><small><?= $sea ? 'Bill of lading' : 'Master air waybill' ?> <span class="mono"><?= h($info[$sea ? 'Bill of lading' : 'Master air waybill'] ?? 'Not set') ?></span><?= !empty($info[$sea ? 'Seal' : 'Seals / ULD']) && $info[$sea ? 'Seal' : 'Seals / ULD'] !== '—' ? ' · ' . ($sea ? 'Seal ' : 'ULD ') . h($info[$sea ? 'Seal' : 'Seals / ULD']) : '' ?></small></span><?php if (!empty($d['st'])): ?><span class="sl-pill <?= h($d['stCls'] ?? '') ?>"><?= h($d['st']) ?></span><?php endif; ?></div>
    <div class="ccv-route">
      <div class="ccv-end"><b><?= h($from['code']) ?></b><span><?= h($from['city']) ?></span><small><?= $stageNow < 3 ? 'Packing since' : ($sea ? 'Sailed' : 'Departed') ?> · <?= h($info['Departs'] ?? explode(' · ', (string) ($info['Sailed · ETA'] ?? '—'))[0]) ?></small></div>
      <div class="ccv-track" role="img" aria-label="<?= $arrived ? 'Arrived' : h($pct . '% of the way') ?>"><i class="ccv-done" style="width:<?= $pct ?>%"></i><span class="ccv-craft" style="left:<?= $pct ?>%"><i class="ic ic-sm <?= $sea ? 'i-ship' : 'i-plane' ?>" aria-hidden="true"></i></span><small><?= $arrived ? 'Arrived' : ($pct > 0 ? h($pct . '% of the way' . (($d['map']['onway'] ?? '') !== 'Not departed' ? ' · ' . $d['map']['onway'] : '')) : 'Not departed') ?></small></div>
      <div class="ccv-end r"><b><?= h($to['code']) ?></b><span><?= h($to['city']) ?></span><small class="<?= $late ? 'bad' : '' ?>">ETA · <?= h($d['map']['eta'] ?? 'Not set') ?><?= $late ? ' · late' : '' ?></small></div>
    </div>
  </section>

  <section class="ccv-craftbox" aria-label="<?= $sea ? 'Container ship and bays' : 'Aircraft and cargo positions' ?>">
    <div class="ccv-ch"><b><?= $sea ? 'Container' : 'Main deck' ?> · <?= count($d['rows']) ?> package<?= count($d['rows']) === 1 ? '' : 's' ?></b><small><?= $slots ?> <?= $sea ? 'bays' : 'cargo positions' ?> below · tap one to see what is in it</small></div>
<?php
    // The owner's illustrations (assets/swiftlane/cargo, stored unaltered): the picture follows the current status
    // (helpers/cargo_art.php; changeable per status on Settings › Status pictures).
    if (defined('CDP_ROOT')) {
        require_once CDP_ROOT . '/helpers/cargo_art.php';
        $artPick = sl_cargoArtFor((int) ($d['stId'] ?? 0), (string) ($d['st'] ?? ''), $sea);
    } else { // stand-alone test page without the app
        $artPick = $sea ? ['key' => 'packing', 'file' => 'assets/swiftlane/cargo/sea-1-packing-container.webp', 'alt' => 'Container being packed'] : ['key' => 'plane', 'file' => 'assets/swiftlane/cargo/air-cargo-plane.webp', 'alt' => 'Cargo aircraft'];
    }
    $art = [$artPick['key'], $artPick['alt']];
    $artFile = $artPick['file'];
    $stageName = (string) ($d['st'] ?? '');
?>
    <figure class="ccv-art<?= $art[0] === 'ship' ? ' blend' : '' ?>">
      <img src="<?= h(function_exists('cdp_asset') ? cdp_asset($artFile) : $artFile) ?>" alt="<?= h($art[1]) ?>" width="2000" height="<?= in_array($art[0], ['opened', 'loading'], true) ? 1000 : 667 ?>" decoding="async">
      <?php if ($stageName !== ''): ?><figcaption><span class="ccv-dot" aria-hidden="true"></span><?= h($stageName) ?></figcaption><?php endif; ?>
    </figure>
    <div class="ccv-slots" role="group" aria-label="<?= $sea ? 'Bays' : 'Cargo positions' ?>">
<?php foreach ($pos as $i => $p): $lv = (int) round(100 * $level($p['w'])); ?>
      <button type="button" class="ccv-uld<?= $p['w'] > 0 ? ' on' : '' ?>" data-ccv-pos="<?= $i ?>" aria-label="<?= $sea ? 'Bay' : 'Position' ?> <?= $i + 1 ?>: <?= count($p['items']) ?> packages, <?= h($fmtW($p['w'])) ?>"><span class="ccv-bar"><i style="height:<?= $lv ?>%"></i></span><small><?= $i + 1 ?></small></button>
<?php endforeach; ?>
    </div>
    <div class="ccv-legend"><span><i class="ccv-sw on"></i>Loaded</span><span><i class="ccv-sw"></i>Empty</span><span>Fill = weight against an even share of capacity (<?= h($fmtW($per)) ?> each)</span></div>
    <div class="ccv-pos-list">
<?php foreach ($pos as $i => $p): if (!$p['items']) continue; ?>
      <div class="ccv-pos" data-ccv-of="<?= $i ?>" hidden><div class="row between"><b><?= $sea ? 'Bay' : 'Position' ?> <?= $i + 1 ?></b><span class="mut sm"><?= count($p['items']) ?> package<?= count($p['items']) === 1 ? '' : 's' ?> · <?= h($fmtW($p['w'])) ?></span></div>
        <?php foreach ($p['items'] as $r): ?><a class="ccv-pk" href="<?= h($r['href']) ?>"><span class="mono"><?= h($r['id']) ?></span><span class="grow"><?= h($r['name']) ?><?php $sub = array_filter([($r['what'] ?? '—') !== '—' ? $r['what'] : '', (int) ($r['pcs'] ?? 0) > 0 ? (int) $r['pcs'] . ' pcs' : '', $r['stL'] ?? '']); ?><?php if ($sub): ?><small><?= h(implode(' · ', $sub)) ?></small><?php endif; ?></span><b><?= h($r['w']) ?></b></a><?php endforeach; ?>
      </div>
<?php endforeach; ?>
    </div>
  </section>

  <section class="ccv-weight" aria-label="Weight">
    <div class="ccv-gauge" role="img" aria-label="<?= $cap > 0 ? $fill . '% of capacity used' : 'Capacity not set' ?>"><b><?= $cap > 0 ? $fill . '%' : '—' ?></b><small><?= $cap > 0 ? 'of capacity' : 'capacity not set' ?></small><span><i style="width:<?= $fill ?>%" class="<?= $fill >= 95 ? 'bad' : ($fill >= 80 ? 'warn' : '') ?>"></i></span></div>
    <dl class="ccv-kv">
      <div><dt>Actual weight</dt><dd><?= h($fmtW($actual)) ?></dd></div>
      <div><dt>Volumetric weight</dt><dd><?= h($fmtW($vol)) ?></dd></div>
      <div><dt>Chargeable weight</dt><dd><?= h($fmtW($charge)) ?></dd></div>
      <div><dt>Capacity</dt><dd><?= $cap > 0 ? h($fmtW($cap)) : 'Not set' ?></dd></div>
      <div><dt>Space left</dt><dd><?= $free !== null ? h($fmtW($free)) : '—' ?></dd></div>
      <div><dt>Packages</dt><dd><?= count($d['rows']) ?></dd></div>
      <div><dt>Pieces</dt><dd><?= (int) ($d['pcsN'] ?? 0) ?></dd></div>
      <div><dt>Customers</dt><dd><?= (int) ($d['custN'] ?? 0) ?></dd></div>
      <?php if ($heavy): ?><div><dt>Heaviest</dt><dd><a href="<?= h($heavy['href']) ?>"><?= h($heavy['id']) ?></a> · <?= h($heavy['w']) ?></dd></div><?php endif; ?>
    </dl>
    <?php if ($cap > 0 && $charge > $cap): ?><p class="sl-banner warn">Chargeable weight is over the capacity by <?= h($fmtW($charge - $cap)) ?>.</p><?php endif; ?>
  </section>
</div>
