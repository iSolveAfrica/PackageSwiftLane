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
    <div class="ccv-ch"><b><?= $sea ? 'On deck' : 'Main deck' ?> · <?= count($d['rows']) ?> package<?= count($d['rows']) === 1 ? '' : 's' ?></b><small><?= $slots ?> <?= $sea ? 'bays' : 'ULD positions' ?> · tap one to see what is in it</small></div>
<?php if (!$sea): ?>
    <div class="ccv-scroll"><svg class="ccv-svg" viewBox="0 0 1000 250" role="img" aria-label="Aircraft side view with <?= count(array_filter($pos, fn($p) => $p['w'] > 0)) ?> of <?= $slots ?> cargo positions loaded">
      <defs><pattern id="ccvh" width="7" height="7" patternUnits="userSpaceOnUse" patternTransform="rotate(35)"><path d="M0 0V7" stroke="var(--line-2)" stroke-width="1"/></pattern>
      <clipPath id="ccvbody"><path d="M28 132 C28 98 62 80 118 78 L812 74 C860 74 900 84 930 104 L972 112 L972 124 C944 146 892 168 830 172 L118 174 C62 174 28 162 28 132 Z"/></clipPath></defs>
      <path class="ccv-wing far" d="M470 128 L560 128 L640 92 L612 92 Z"/>
      <path class="ccv-fin" d="M826 80 L884 8 L930 8 L914 100 Z"/><path class="ccv-line" d="M852 46 H908 M840 64 H904"/>
      <path class="ccv-tail" d="M880 104 L990 92 L994 102 L896 122 Z"/>
      <path class="ccv-body" d="M28 132 C28 98 62 80 118 78 L812 74 C860 74 900 84 930 104 L972 112 L972 124 C944 146 892 168 830 172 L118 174 C62 174 28 162 28 132 Z"/>
      <g clip-path="url(#ccvbody)"><rect x="0" y="0" width="1000" height="250" fill="url(#ccvh)" opacity=".28"/>
        <path class="ccv-line" d="M28 150 H972" opacity=".5"/><?php for ($k = 0; $k < 16; $k++): ?><path class="ccv-line" d="M<?= 150 + $k * 44 ?> 76 V174" opacity=".25"/><?php endfor; ?></g>
      <path class="ccv-cock" d="M44 104 C56 92 72 87 90 86 L104 86 L100 104 Z"/><path class="ccv-line" d="M72 88 L70 104"/>
      <rect class="ccv-door" x="118" y="86" width="26" height="40" rx="3"/>
      <rect class="ccv-hold" x="156" y="84" width="660" height="70" rx="12"/>
<?php foreach ($pos as $i => $p): $x = 162 + $i * 65.2; $h = round(58 * $level($p['w']), 1); ?>
      <g class="ccv-uld<?= $p['w'] > 0 ? ' on' : '' ?>" data-ccv-pos="<?= $i ?>" tabindex="0" role="button" aria-label="Position <?= $i + 1 ?>: <?= count($p['items']) ?> packages, <?= h($fmtW($p['w'])) ?>">
        <path class="ccv-shell" d="M<?= $x ?> 150 V100 C<?= $x ?> 93 <?= $x + 7 ?> 89 <?= $x + 15 ?> 89 H<?= $x + 46 ?> C<?= $x + 54 ?> 89 <?= $x + 59 ?> 94 <?= $x + 59 ?> 102 V150 Z"/>
        <?php if ($h > 0): ?><rect class="ccv-load" x="<?= $x + 2.5 ?>" y="<?= round(148.5 - $h, 1) ?>" width="54" height="<?= $h ?>" rx="4"/><?php endif; ?>
        <text x="<?= $x + 29.5 ?>" y="166" text-anchor="middle"><?= $i + 1 ?></text>
      </g>
<?php endforeach; ?>
      <path class="ccv-wing" d="M420 172 L540 172 L742 240 L700 246 Z"/><path class="ccv-line" d="M560 176 L726 238"/>
      <path class="ccv-eng" d="M548 196 C548 186 560 180 576 180 L652 180 C666 180 672 188 672 196 C672 204 666 212 652 212 L576 212 C560 212 548 206 548 196 Z"/><ellipse class="ccv-intake" cx="553" cy="196" rx="7" ry="15"/><path class="ccv-line" d="M590 180 V212 M630 180 V212"/>
      <path class="ccv-line" d="M300 174 V200 M290 200 H312 M760 172 V198 M750 198 H772"/><circle class="ccv-wheel" cx="301" cy="206" r="8"/><circle class="ccv-wheel" cx="761" cy="204" r="8"/>
    </svg></div>
<?php else: ?>
    <div class="ccv-scroll"><svg class="ccv-svg" viewBox="0 0 1000 300" role="img" aria-label="Container ship with <?= count(array_filter($pos, fn($p) => $p['w'] > 0)) ?> of <?= $slots ?> bays loaded">
      <defs><pattern id="ccvh" width="8" height="8" patternUnits="userSpaceOnUse" patternTransform="rotate(35)"><path d="M0 0V8" stroke="var(--line-2)" stroke-width="1"/></pattern></defs>
      <path class="ccv-body" d="M40 170 L960 170 L930 252 C900 266 860 270 800 270 L150 270 C90 270 60 250 52 232 C30 226 24 200 40 170 Z"/>
      <path class="ccv-hatch" d="M40 170 L960 170 L930 252 C900 266 860 270 800 270 L150 270 C90 270 60 250 52 232 C30 226 24 200 40 170 Z" fill="url(#ccvh)" opacity=".35"/>
      <path class="ccv-line" d="M44 196 H950 M60 236 H930"/>
      <path class="ccv-fin" d="M842 72 H930 V170 H842 Z"/><path class="ccv-line" d="M852 90 H920 M852 110 H920 M852 130 H920 M886 72 V40 M876 40 H896"/>
<?php foreach ($pos as $i => $p): $x = 92 + $i * 61; $tiers = $p['w'] <= 0 ? 0 : max(1, (int) ceil(3 * $level($p['w']))); ?>
      <g class="ccv-uld<?= $p['w'] > 0 ? ' on' : '' ?>" data-ccv-pos="<?= $i ?>" tabindex="0" role="button" aria-label="Bay <?= $i + 1 ?>: <?= count($p['items']) ?> packages, <?= h($fmtW($p['w'])) ?>">
        <?php for ($t = 0; $t < 3; $t++): $y = 140 - $t * 32; ?><rect class="<?= $t < $tiers ? 'ccv-load' : 'ccv-shell' ?>" x="<?= $x ?>" y="<?= $y ?>" width="56" height="30" rx="2"/><?php endfor; ?>
        <text x="<?= $x + 28 ?>" y="188" text-anchor="middle"><?= $i + 1 ?></text>
      </g>
<?php endforeach; ?>
    </svg></div>
<?php endif; ?>
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
