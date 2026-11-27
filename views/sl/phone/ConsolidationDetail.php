<?php
/**
 * M-ConsolDetail: one air or sea consolidation (consolidate_view.php?id=&type=). Same $d as the web page
 * (views/sl/data/consolidations.php sl_consolidationDetailData); actions are the web page's links, gated by $d['can'].
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'none';
$can = $d['can'];
$info = [];
foreach ($d['info'] as [$k, $v]) $info[$k] = $v;
$route = $info['Route'] ?? '';
$ref = $d['sea'] ? ($info['Bill of lading'] ?? '') : ($info['Master air waybill'] ?? '');
$eta = $d['map']['eta'] ?? 'Not set';
$pct = (int) preg_replace('/\D/', '', $d['capS']);
$rows = $d['rows'];
$show = array_slice($rows, 0, 25);
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-wh.css')) ?>">
<?= sl_phoneTop($d['code'] . ' · ' . ($d['sea'] ? 'Sea' : 'Air'), '', ['back' => sl_route('Consolidations', ['mode' => $d['type']])], $can['edit'] !== '' ? sl_phoneIcon('edit', 'Edit consolidation', $can['edit']) : '') ?>
<main class="mx-body">
<?php if ($d['flash'] !== ''): ?><div class="mx-ban ok" role="status"><i class="ic ic-sm i-check" aria-hidden="true"></i><span><?= h($d['flash']) ?></span></div><?php endif; ?>
<section class="mx-hero">
<div class="mx-hk"><?= h($route) ?><?= $ref !== '' && $ref !== 'Not set' ? ' · ' . ($d['sea'] ? 'B/L ' : 'AWB ') . h($ref) : '' ?><span class="mx-tag"><?= h($d['st']) ?></span></div>
<b class="mx-hv"><?= h($d['wt']) ?></b>
<small><?= sl_num($d['pkN']) ?> parcel<?= $d['pkN'] === 1 ? '' : 's' ?> · <?= sl_num($d['custN']) ?> customer<?= $d['custN'] === 1 ? '' : 's' ?> · ETA <?= h($eta) ?> · <?= h($d['capS']) ?></small>
<div class="mx-prog" role="img" aria-label="<?= h($d['capS']) ?>"><i style="width:<?= min(100, $pct) ?>%"></i></div>
</section>
<?php
$acts = [];
if ($can['edit'] !== '') $acts[] = [$can['edit'], 'scan', 'Add parcel'];
if ($can['packing'] !== '') $acts[] = [$can['packing'], 'print', 'Packing list'];
if ($can['print'] !== '') $acts[] = [$can['print'], 'file', 'Manifest'];
if ($can['notify']) $acts[] = [sl_route('Broadcast', ['consolidation' => $d['code']]), 'send', 'Notify all'];
if ($can['label'] !== '' && count($acts) < 4) $acts[] = [$can['label'], 'tag', 'Labels'];
?>
<?php if ($acts): ?>
<section class="mx-act c<?= count($acts) >= 4 ? 4 : max(3, count($acts)) ?>"><?php foreach (array_slice($acts, 0, 4) as [$u, $ic, $l]): ?><a href="<?= h($u) ?>"<?= in_array($ic, ['print', 'file', 'tag'], true) ? ' target="_blank" rel="noopener"' : '' ?>><span><i class="ic i-<?= h($ic) ?>" aria-hidden="true"></i></span><small><?= h($l) ?></small></a><?php endforeach; ?></section>
<?php endif; ?>
<?= sl_mxSec('Packing list', $can['packing'] !== '' && $d['pkN'] > 0 ? 'All ' . $d['pkN'] : '', $can['packing']) ?>
<?php if ($show): ?>
<section class="mx-card mx-list">
<?php foreach ($show as $r): ?>
<a class="mx-row<?= $r['cls'] === 'mut' ? ' mut' : '' ?>" href="<?= h($r['href']) ?>"><span class="mx-rm"><b><?= h($r['name']) ?></b><small><?= h($r['id']) ?> · <?= h($r['what']) ?></small><?php if ($r['post'] !== '—'): ?><small class="mono"><?= h($r['post']) ?></small><?php endif; ?></span><span class="mx-rr"><b><?= h($r['w']) ?></b><small><?= h($r['stL']) ?></small></span></a>
<?php endforeach; ?>
</section>
<?php if (count($rows) > count($show)): ?><p class="mx-note">Showing <?= count($show) ?> of <?= count($rows) ?> parcels. Open the packing list for all of them.</p><?php endif; ?>
<?php else: ?>
<?= sl_mxEmpty('box', 'No parcels yet', $can['edit'] !== '' ? 'Add parcels to this consolidation from Edit.' : '') ?>
<?php endif; ?>
<?= sl_mxSec('Details') ?>
<section class="mx-card mx-list">
<?php foreach ($d['info'] as [$k, $v]): ?><div class="mx-row"><span class="mx-rm"><small><?= h($k) ?></small><b><?= h($v) ?></b></span></div><?php endforeach; ?>
</section>
<div class="mx-btns">
<?php if ($can['track'] !== ''): ?><a class="mx-btn" href="<?= h($can['track']) ?>"><?= $d['sea'] ? 'Update status' : 'Update status · send to airline' ?></a><?php endif; ?>
<?php if ($can['deliver'] !== ''): ?><a class="mx-btn sec" href="<?= h($can['deliver']) ?>">Hand over</a><?php endif; ?>
<?php if (!empty($d['other'])): ?><a class="mx-btn sec" href="<?= h(sl_route('ConsolidationDetail', ['id' => $d['other']['id'], 'type' => $d['other']['type']])) ?>">Switch to <?= $d['other']['type'] === 'sea' ? 'sea' : 'air' ?> version</a><?php endif; ?>
</div>
<?php
$sea=$d['sea'];
$self=sl_route('ConsolidationDetail',['id'=>$d['cid'],'type'=>$d['type']]);
include CDP_ROOT.'/views/sl/pages/partials/con_load_plan.php';
?>
</main>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pages/ConLoadPlan.css')) ?>">
<script defer src="<?= h(cdp_asset('dataJs/sl/consolidation_load_plan.js')) ?>"></script>
