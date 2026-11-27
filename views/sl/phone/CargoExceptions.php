<?php
/**
 * M-CargoEx: cargo exceptions on a phone. Same $d as the web page (sl_cargoExceptionsData): ?filter=open|resolved|all,
 * ?id= opens one. Writes go to ajax/sl/cargo_exception_ajax.php with the web page's fields (op=create, op=status, op=arrived).
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$can = !empty($d['canManage']);
$x = !empty($_GET['id']) ? ($d['x'] ?? null) : null;
$icon = ['Damaged' => 'image', 'Customs hold' => 'file', 'Missing' => 'search', 'Hand-over' => 'users', 'Delayed' => 'clock'];
$openN = 0;
foreach ((array) ($d['kpis'] ?? []) as $k) if ($k['l'] === 'Open exceptions') $openN = (int) $k['v'];
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-desk.css')) ?>">
<?= sl_phoneTop($x ? $x['t'] : 'Cargo exceptions', $x ? $x['ref'] : '', ['back' => $x ? sl_url(['id' => null]) : sl_route('FreightHub')]) ?>
<main class="mx-body">
<?php if (empty($d['ready'])): ?>
<?= sl_mxEmpty('alert', 'Exceptions not installed', 'Ask an administrator to run the freight migration.') ?>
<?php elseif ($x): ?>
<section class="mx-card mx-kv"><div><span>Type</span><b><?= h($x['type']) ?></b></div><div><span>Status</span><b><span class="sl-pill <?= h($x['pc']) ?>"><?= h($x['st']) ?></span></b></div><div><span>Severity</span><b><?= h(ucfirst($x['severity'])) ?></b></div><div><span>Owner</span><b><?= h($x['owner']) ?></b></div><?php if ($x['pieces'] !== ''): ?><div><span>Pieces arrived</span><b><?= h($x['pieces']) ?></b></div><?php endif; ?><div><span>Opened</span><b><?= h($x['age']) ?></b></div></section>
<?php if ($x['d'] !== ''): ?><section class="mx-card mx-form"><p class="pd-note" style="margin:0;white-space:pre-line;color:var(--ink-2)"><?= h($x['d']) ?></p></section><?php endif; ?>
<div class="mx-btns">
<?php if ($can && !in_array($x['status'], ['resolved', 'closed'], true)): ?><button type="button" class="mx-btn" data-sl-action="ajax/sl/cargo_exception_ajax.php" data-p-op="status" data-p-id="<?= (int) $x['id'] ?>" data-p-status="resolved" data-sl-reload>Mark resolved</button>
<?php if ($x['typeKey'] === 'partial'): ?><button type="button" class="mx-btn sec" data-sl-action="ajax/sl/cargo_exception_ajax.php" data-p-op="arrived" data-p-id="<?= (int) $x['id'] ?>" data-p-n="1" data-sl-reload>1 more piece arrived</button><?php endif; ?>
<?php elseif ($can): ?><button type="button" class="mx-btn sec" data-sl-action="ajax/sl/cargo_exception_ajax.php" data-p-op="status" data-p-id="<?= (int) $x['id'] ?>" data-p-status="open" data-sl-reload>Reopen</button><?php endif; ?>
<?php if ($x['claim']): ?><a class="mx-btn sec" href="<?= h(sl_route('InsuranceClaims', ['id' => $x['claim']])) ?>">Open the claim</a>
<?php elseif (!empty($d['canInsure']) && in_array($x['typeKey'], ['damaged', 'missing'], true)): ?><a class="mx-btn sec" href="<?= h(sl_route('InsuranceClaims', ['new' => 1, 'track' => $x['ref'], 'exception' => $x['id'], 'type' => $x['typeKey'] === 'missing' ? 'lost' : 'damaged'])) ?>">Start a claim</a><?php endif; ?>
<a class="mx-btn sec" href="<?= h($x['trackHref']) ?>">Track <?= h($x['ref']) ?></a>
</div>
<?php else: ?>
<nav class="mx-seg" aria-label="Show"><?php foreach ($d['filters'] as $f): ?><a class="<?= h($f['cls']) ?>" href="<?= h($f['href']) ?>"<?= $f['cls'] === 'on' ? ' aria-current="page"' : '' ?>><?= h($f['label'] === 'Resolved' ? 'Solved' : $f['label']) ?><?= $f['label'] === 'Open' && $openN ? ' ' . $openN : '' ?></a><?php endforeach; ?></nav>
<?php if ($d['exs']): ?>
<section class="mx-card mx-list"><?php foreach ($d['exs'] as $e): ?><a class="mx-row" href="<?= h($e['href']) ?>"><span class="mx-li"><i class="ic i-<?= h($icon[$e['type']] ?? 'alert') ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($e['t']) ?></b><small><?= h($e['ref'] . ' · ' . $e['type'] . ' · ' . $e['age']) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($e['pc']) ?>"><?= h($e['st']) ?></span></span></a><?php endforeach; ?></section>
<?php else: ?><?= sl_mxEmpty('check', 'Nothing in this list', 'Damaged, missing and held cargo shows up here.') ?><?php endif; ?>
<?php if ($can): ?><div class="mx-btns"><button type="button" class="mx-btn" data-sl-open="pd-ce-new">Log an exception</button></div><?php endif; ?>
<?php endif; ?>
</main>
<?php if ($can && !empty($d['ready'])): ?>
<div class="sl-dialog pd-sheet" id="pd-ce-new" hidden><form class="pd-sheetf" data-sl-form action="ajax/sl/cargo_exception_ajax.php" method="post" enctype="multipart/form-data" role="dialog" aria-label="Log an exception">
<input type="hidden" name="op" value="create"><h2>Log a cargo exception</h2>
<div class="pd-row2"><label class="pd-f"><small>Type</small><select class="pd-in" name="type" required><?php foreach ((array) $d['types'] as $tk => $tl): ?><option value="<?= h($tk) ?>"><?= h($tl) ?></option><?php endforeach; ?></select></label>
<label class="pd-f"><small>Severity</small><select class="pd-in" name="severity"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select></label></div>
<div class="pd-row2"><label class="pd-f"><small>On</small><select class="pd-in" name="entity_type"><option value="shipment">Shipment</option><option value="package">Package</option><option value="consolidation">Consolidation</option><option value="schedule">Flight / sailing</option><option value="waybill">Waybill</option></select></label>
<label class="pd-f"><small>Reference</small><input class="pd-in mono" name="entity_ref" required placeholder="Tracking no." autocomplete="off"></label></div>
<label class="pd-f"><small>Title (optional)</small><input class="pd-in" name="title"></label>
<div class="pd-row2"><label class="pd-f"><small>Pieces expected</small><input class="pd-in mono" type="number" inputmode="numeric" min="0" name="pieces_expected"></label><label class="pd-f"><small>Pieces arrived</small><input class="pd-in mono" type="number" inputmode="numeric" min="0" name="pieces_arrived"></label></div>
<label class="pd-f"><small>Owner</small><select class="pd-in" name="owner_id"><option value="">Me</option><?php foreach ((array) $d['staff'] as $s): ?><option value="<?= (int) $s['id'] ?>"><?= h($s['label']) ?></option><?php endforeach; ?></select></label>
<label class="pd-f"><small>Photos</small><input class="pd-in" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" capture="environment" multiple style="padding-top:12px"></label>
<label class="pd-f"><small>What happened</small><textarea class="pd-in" name="notes" rows="3"></textarea></label>
<div class="mx-tg"><span class="mx-rm"><b>Tell the customer</b><small>Flags that the customer should be told</small></span><button type="button" class="mx-sw on" role="switch" aria-checked="true" aria-label="Tell the customer" data-input="#pd-ce-notify"></button><input type="hidden" name="notify_customer" id="pd-ce-notify" value="1"></div>
<div class="mx-btns"><button type="submit" class="mx-btn">Save exception</button><button type="button" class="mx-btn sec" data-sl-close>Cancel</button></div>
</form></div>
<?php endif; ?>
