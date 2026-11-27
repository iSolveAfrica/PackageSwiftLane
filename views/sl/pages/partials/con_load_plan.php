<?php
$capacity = max(0, (float) $d['capacityKg']);
$loaded = max(0, (float) $d['weightKg']);
$percent = $capacity > 0 ? min(100, round(100 * $loaded / $capacity)) : 0;
$rows = $d['rows'];
$stored=sl_tableExists('cdb_consolidation_load_plans') ? sl_row('SELECT revision,positions_json FROM cdb_consolidation_load_plans WHERE mode=:mode AND consolidation_id=:id',[':mode'=>$d['type'],':id'=>$d['cid']]) : null;
$positions=json_decode($stored->positions_json??'[]',true)?:[];
$positionMap=[];
foreach($positions as $pos)$positionMap[(int)$pos['id']]=$pos;
$editable=$d['canEdit'] && $d['lq']==='' && sl_tableExists('cdb_consolidation_load_plans');

?>
<section data-load-plan data-weight-unit="<?= h($core->weight_p ?? 'lbs') ?>" data-revision="<?= (int)($stored->revision??0) ?>" data-id="<?= (int)$d['cid'] ?>" data-type="<?= h($d['type']) ?>" class="clp" id="load-plan" aria-label="<?= $sea?'Sea':'Air' ?> load plan">
<div class="clp-heading"><div><span class="mut sm"><?= $sea?'Sea container':'Air cargo' ?> · <?= h($d['code']) ?></span><h2>Load plan</h2></div><div class="row wrap g8">
<a class="sl-btn ghost sm" href="#manifest">Manifest</a>
<?php if ($can['packing']): ?><a class="sl-btn ghost sm" href="<?= h($can['packing']) ?>" target="_blank">Packing list</a><?php endif; ?>
<?php if ($d['canEdit']): ?><a class="sl-btn sm" href="<?= h($self.'&add=1') ?>">Add cargo</a><?php endif; ?></div></div>
<div class="clp-main"><div class="clp-deck">
<div class="row wrap g8" aria-label="Load view">
<button class="sl-btn ghost sm" type="button" data-load-view="top" aria-pressed="true">Top</button><button class="sl-btn ghost sm" type="button" data-load-view="side" aria-pressed="false">Side</button><button class="sl-btn ghost sm" type="button" data-load-view="3d" aria-pressed="false">3D</button>
<button class="sl-btn ghost sm" type="button" data-load-zoom="in" aria-label="Zoom in">+</button><button class="sl-btn ghost sm" type="button" data-load-zoom="out" aria-label="Zoom out">−</button><button class="sl-btn ghost sm" type="button" data-load-zoom="reset">Reset</button></div>
<div class="clp-deck-header"><strong><?= $sea?'Container cargo':'Air cargo' ?></strong><span><?= count($rows) ?> units recorded</span></div>
<div class="clp-grid"><?php foreach ($rows as $i=>$r): ?><a class="clp-slot" href="<?= h($r['href']) ?>" title="<?= h($r['id'].' · '.$r['name'].' · '.$r['w']) ?>"><small data-load-label="<?= (int)$r['oid'] ?>"><?= isset($positionMap[(int)$r['oid']]) ? h(implode('-',[$positionMap[(int)$r['oid']]['bay'],$positionMap[(int)$r['oid']]['row'],$positionMap[(int)$r['oid']]['tier']])) : 'Unplaced' ?></small><b><?= h($r['id']) ?></b><span><?= h($r['w']) ?></span></a><?php endforeach; ?>
<?php if (!$rows): ?><p class="sl-empty">The load is empty. Add packages to see them here.</p><?php endif; ?></div>
<p class="mut sm">Bay, row and tier refer to recorded cargo positions.</p>
</div><div class="clp-payload"><h3>Payload</h3><div class="clp-gauge" style="--fill:<?= $percent ?>%"><span><small><?= $percent ?>% recorded</small><strong><?= h(sl_weight($loaded,0)) ?></strong></span></div>
<div class="row between"><span>Recorded weight</span><b><?= h(sl_weight($loaded,0)) ?></b></div>
<div class="row between"><span>Configured capacity</span><b><?= $capacity>0?h(sl_weight($capacity,0)):'Not set' ?></b></div>
<div class="row between"><span>Pieces</span><b><?= (int)$d['pcsN'] ?></b></div>
<div class="row between"><span>Customers</span><b><?= (int)$d['custN'] ?></b></div>
<?php if ($capacity>0 && $loaded>$capacity): ?><p class="sl-banner warn">Recorded weight exceeds the configured capacity.</p><?php endif; ?>
</div></div>
<details class="clp-editor"><summary>Recorded cargo positions</summary>
<?php if($editable): ?><form data-load-form><div class="sl-tw"><table class="sl-table"><thead><tr><th>Package</th><th>Bay</th><th>Row</th><th>Tier</th><th></th></tr></thead><tbody><?php foreach($rows as $r): $pos=$positionMap[(int)$r['oid']]??[]; ?><tr data-load-row="<?= (int)$r['oid'] ?>" data-weight="<?= (float)$r['weightKg'] ?>"><td><?= h($r['id']) ?></td><?php foreach(['bay'=>100,'row'=>20,'tier'=>10] as $axis=>$limit): ?><td><input class="sl-in" type="number" min="1" max="<?= $limit ?>" name="<?= $axis ?>" aria-label="<?= h(ucfirst($axis).' for '.$r['id']) ?>" value="<?= isset($pos[$axis])?(int)$pos[$axis]:'' ?>"></td><?php endforeach; ?><td><button class="sl-btn ghost sm" data-load-clear type="button">Clear</button></td></tr><?php endforeach; ?></tbody></table></div><button class="sl-btn" type="submit">Save positions</button><span data-load-feedback role="status" class="mut sm"></span></form>
<?php else: ?><p class="mut sm"><?= $d['lq']!==''?'Clear the package search to edit the complete load plan.':'Cargo positions are available when load planning is enabled for this consolidation.' ?></p><?php endif; ?></details>
<section class="col g8"><h3>Weight by bay</h3><div data-load-balance class="clp-balance" role="list"></div></section>
<div class="clp-bottom"><div class="clp-manifest" id="manifest"><div class="row between"><h3>Payload manifest</h3><span class="mut sm"><?= count($rows) ?> packages</span></div><div class="clp-manifest-list"><?php foreach($rows as $r): ?><a href="<?= h($r['href']) ?>"><span class="mono"><?= h($r['id']) ?></span><span><?= h($r['name']) ?></span><b><?= h($r['w']) ?></b><span class="sl-pill <?= h($r['stC']) ?>"><?= h($r['stL']) ?></span></a><?php endforeach; ?></div></div>
<div class="clp-timeline"><h3>Operation timeline</h3><?php foreach($d['steps'] as $step): ?><div class="clp-step <?= h($step['cls']) ?>"><i></i><span><b><?= h($step['t']) ?></b><small><?= h($step['d'] ?: 'Awaiting update') ?></small></span></div><?php endforeach; ?></div></div></section>
