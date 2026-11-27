<?php
$t = $d['tower'];
$tracking = ($_GET['panel'] ?? '') === 'tracking';
$v = $t['detail'];
$place = $t['place'];
$city = trim(implode(', ',array_filter([$place->city ?? '',$place->country ?? ''])));
?>
<nav class="row wrap g8 sm mut" aria-label="Sea shipping"><a href="<?= h(sl_route('ControlPanels')) ?>">Control panels</a> ›
<a href="<?= h(sl_url(['panel'=>'tower'])) ?>">Sea control tower</a><?php if ($tracking): ?> › Shipment tracking<?php endif; ?></nav>
<div class="sl-head"><div><h1 class="sl-title"><?= $tracking ? 'Sea shipment tracking' : 'Sea shipping control tower' ?></h1><p class="sl-lede"><?= $tracking ? 'Follow your cargo from registration to handover.' : 'Monitor sea packages, container movements and handovers.' ?></p></div>
<div class="row wrap g8"><?php if (sl_navAllowed('ai:use',[])): ?><a class="sl-btn ghost" href="<?= h(sl_route('StaffAI')) ?>">Ask AI</a><?php endif; ?><a class="sl-btn ghost" href="<?= h(sl_url(['export'=>'csv'])) ?>">Export CSV</a>
<?php if ($d['canAdd']): ?><a class="sl-btn" href="<?= h(sl_route('NewPackage')) ?>">+ New package</a><?php endif; ?></div></div>
<nav class="row wrap g8" aria-label="Sea freight views"><a class="sl-btn <?= $tracking?'ghost':'' ?>" href="<?= h(sl_url(['panel'=>'tower'])) ?>">Control tower</a>
<a class="sl-btn <?= $tracking?'':'ghost' ?>" href="<?= h(sl_url(['panel'=>'tracking'])) ?>">Track shipments</a><a class="sl-btn ghost" href="<?= h(sl_url(['panel'=>null,'cargo'=>null])) ?>">Package register</a>
<?php if ($user->cdp_hasPermission('view_consolidate_package_list')): ?><a class="sl-btn ghost" href="<?= h(sl_route('Consolidations',['mode'=>'sea'])) ?>">Containers</a><?php endif; ?></nav>
<?php if (!$tracking): ?>
<section class="sea-metrics"><?php foreach (array_merge([['label'=>'Registered','n'=>$d['total'],'href'=>sl_url(['filter'=>'all','page'=>null])],['label'=>'Open packages','n'=>$d['open'],'href'=>sl_url(['filter'=>'open','page'=>null])]],$d['kpi']) as $k): ?>
<a class="sl-card col g8" href="<?= h($k['href']) ?>#sea-cargo"><span class="mut sm"><?= h($k['label']) ?></span><b><?= sl_num($k['n']) ?></b></a><?php endforeach; ?></section>
<div class="sea-charts"><?php foreach ($t['charts'] as $chart): $peak=max(1,...array_merge(...array_column($chart['series'],'values'))); ?>
<section class="sl-card col g12"><header class="row between wrap g8"><h2 class="sl-ct"><?= h($chart['title']) ?></h2><details class="sea-period"><summary><?= (int)$t['months'] ?> months</summary><div><?php foreach ([3,6,12] as $n): ?><a href="<?= h(sl_url(['months'=>$n])) ?>"><?= $n ?> months</a><?php endforeach; ?></div></details></header>
<div class="row wrap g12 sm"><?php foreach ($chart['series'] as $s): ?><span><i class="sea-key" style="background:<?= h($s['color']) ?>"></i><?= h($s['name']) ?></span><?php endforeach; ?></div>
<svg class="sea-chart" viewBox="0 0 640 195" role="img" aria-label="<?= h($chart['title']) ?>" <?= sl_chartAttrs($chart['series'],$t['labels'],$chart['title'],42,610) ?>>
<?php foreach ([0,.5,1] as $fraction): $y=155-139*$fraction; ?><path d="M42 <?= $y ?> H610" stroke="var(--line)" stroke-dasharray="3 5"/><text x="36" y="<?= $y+4 ?>" text-anchor="end"><?= sl_num($peak*$fraction) ?></text><?php endforeach; ?>
<?php foreach ($chart['series'] as $s): ?><path d="<?= h(sl_svgLinePath($s['values'],610,155,16,$peak,42)) ?>" fill="none" stroke="<?= h($s['color']) ?>" stroke-width="2.5"/><?php endforeach; ?>
<?php foreach ($t['labels'] as $i=>$label): if(count($t['labels'])>6 && $i%2===1 && $i!==11)continue; ?><text x="<?= 42+568*$i/(count($t['labels'])-1) ?>" y="184" text-anchor="middle"><?= h($label) ?></text><?php endforeach; ?></svg></section><?php endforeach; ?></div>
<?php endif; ?>
<section class="sea-work <?= $tracking?'tracking':'' ?>" id="sea-cargo">
<aside class="sl-card col g12 sea-list"><h2 class="sl-ct">Shipment tracking</h2>
<form method="get" class="row g6"><input type="hidden" name="panel" value="<?= $tracking?'tracking':'tower' ?>"><input class="sl-in" type="search" name="q" aria-label="Search sea shipments" placeholder="Tracking number or customer" value="<?= h($d['q']) ?>"><button class="sl-btn ghost sm">Search</button></form>
<div class="sea-cards"><?php foreach ($d['rows'] as $r): ?><a href="<?= h(sl_url(['cargo'=>$r['id']])) ?>#sea-cargo" class="sea-ticket <?= (int)$r['id']===(int)$t['selected']?'selected':'' ?>"><span class="row between wrap g8"><b class="mono"><?= h($r['trk']) ?></b><span class="sl-pill <?= h($r['stCls']) ?>"><?= h($r['st']) ?></span></span><span class="sea-lane"><i class="ic i-ship"></i></span><span class="row between wrap g6 sm"><span><?= h($r['name']) ?></span><span><?= h($r['box']) ?></span></span><small class="mut"><?= h($r['date']) ?></small></a><?php endforeach; ?><?php if(!$d['rows']): ?><p class="sl-empty">No matching sea packages.</p><?php endif; ?></div>
<?= sl_pager($d['page'],$d['per'],$d['count'],'packages') ?></aside>
<section class="sl-card sea-map"><header class="row between g8"><h2 class="sl-ct">Destination map</h2><button type="button" data-sea-expand class="sl-btn ghost sm" hidden>Expand</button></header>
<div data-sea-map data-country="<?= h($place->country ?? '') ?>" aria-label="Destination country"></div><div class="sm mut" data-sea-caption><?= h($city ?: 'Destination not recorded') ?></div></section>
<section class="sl-card sea-transit"><div class="row between"><h2 class="sl-ct">Live transit</h2><span class="sl-badge"><i class="ic i-ship"></i> Sea freight</span></div>
<img class="sea-ship" src="assets/swiftlane/images/sea-cargo-ship.png" alt="Illustration of a container ship">
<?php if ($v): ?><div class="row between wrap g8"><div class="col"><b><?= h($v['trk']) ?></b><span class="mut sm"><?= h($v['courier']) ?></span></div><a class="sl-btn ghost sm" href="<?= h(sl_route('PackageDetail',['id'=>$v['id']])) ?>">Open shipment</a></div>
<div class="sea-ports"><div><small>Origin office</small><b><?= h($v['office']) ?></b><small>Registered <?= h($v['registered']) ?></small></div><div><small>Destination</small><b><?= h($city ?: 'Not recorded') ?></b><small>ETA <?= h($v['eta']) ?></small></div></div>
<div class="row between sm"><b><?= h($v['st']) ?></b><span class="mut">Shipment progress</span></div><div class="sea-stages"><?php foreach ($v['steps'] as $i=>$step): ?><span class="<?= $i<$v['stage']?'done':'' ?>" title="<?= h($step) ?>"></span><?php endforeach; ?></div>
<?php if ($v['events']): ?><div class="sea-events"><?php foreach ($v['events'] as $event): ?><div><b><?= h($event['t']) ?></b><small><?= h($event['s']) ?></small></div><?php endforeach; ?></div><?php else: ?><small class="mut">Awaiting first tracking scan.</small><?php endif; ?>
<?php else: ?><p class="sl-empty"><?= $d['rows']?'Shipment details require package-detail access.':'Select a shipment to see its transit history.' ?></p><?php endif; ?></section></section>
