<?php
$capacity = max(0, (float) $d['capacityKg']);
$loaded = max(0, (float) $d['weightKg']);
$percent = $capacity > 0 ? min(100, round(100 * $loaded / $capacity)) : 0;
$rows = $d['rows'];
?>
<section class="clp" id="load-plan" aria-label="<?= $sea?'Sea':'Air' ?> load plan">
<div class="clp-heading"><div><span class="mut sm"><?= $sea?'Sea container':'Air cargo' ?> · <?= h($d['code']) ?></span><h2>Load plan</h2></div><div class="row wrap g8">
<a class="sl-btn ghost sm" href="#manifest">Manifest</a>
<?php if ($can['packing']): ?><a class="sl-btn ghost sm" href="<?= h($can['packing']) ?>" target="_blank">Packing list</a><?php endif; ?>
<?php if ($d['canEdit']): ?><a class="sl-btn sm" href="<?= h($self.'&add=1') ?>">Add cargo</a><?php endif; ?></div></div>
<div class="clp-main"><div class="clp-deck">
<div class="clp-deck-header"><strong><?= $sea?'Container cargo':'Air cargo' ?></strong><span><?= count($rows) ?> units recorded</span></div>
<div class="clp-grid"><?php foreach ($rows as $i=>$r): ?><a class="clp-slot" href="<?= h($r['href']) ?>" title="<?= h($r['id'].' · '.$r['name'].' · '.$r['w']) ?>"><small><?= str_pad((string)($i+1),2,'0',STR_PAD_LEFT) ?></small><b><?= h($r['id']) ?></b><span><?= h($r['w']) ?></span></a><?php endforeach; ?>
<?php if (!$rows): ?><p class="sl-empty">The load is empty. Add packages to see them here.</p><?php endif; ?></div>
<p class="mut sm">The grid groups manifest entries for review. Physical slot positions require a recorded loading plan.</p>
</div><div class="clp-payload"><h3>Payload</h3><div class="clp-gauge" style="--fill:<?= $percent ?>%"><span><small><?= $percent ?>% recorded</small><strong><?= h(sl_weight($loaded,0)) ?></strong></span></div>
<div class="row between"><span>Recorded weight</span><b><?= h(sl_weight($loaded,0)) ?></b></div>
<div class="row between"><span>Configured capacity</span><b><?= $capacity>0?h(sl_weight($capacity,0)):'Not set' ?></b></div>
<div class="row between"><span>Pieces</span><b><?= (int)$d['pcsN'] ?></b></div>
<div class="row between"><span>Customers</span><b><?= (int)$d['custN'] ?></b></div>
<?php if ($capacity>0 && $loaded>$capacity): ?><p class="sl-banner warn">Recorded weight exceeds the configured capacity.</p><?php endif; ?>
</div></div>
<div class="clp-bottom"><div class="clp-manifest" id="manifest"><div class="row between"><h3>Payload manifest</h3><span class="mut sm"><?= count($rows) ?> packages</span></div><div class="clp-manifest-list"><?php foreach($rows as $r): ?><a href="<?= h($r['href']) ?>"><span class="mono"><?= h($r['id']) ?></span><span><?= h($r['name']) ?></span><b><?= h($r['w']) ?></b><span class="sl-pill <?= h($r['stC']) ?>"><?= h($r['stL']) ?></span></a><?php endforeach; ?></div></div>
<div class="clp-timeline"><h3>Operation timeline</h3><?php foreach($d['steps'] as $step): ?><div class="clp-step <?= h($step['cls']) ?>"><i></i><span><b><?= h($step['t']) ?></b><small><?= h($step['d'] ?: 'Awaiting update') ?></small></span></div><?php endforeach; ?></div></div></section>
