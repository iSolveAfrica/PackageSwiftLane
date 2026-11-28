<?php
/**
 * M-Waybills: master and house waybills on a phone. Same $d as the web page (sl_waybillsData): ?tab=awb|man,
 * ?id= opens a master bill. Writes go to ajax/sl/waybill_ajax.php with the web page's fields (op=create, op=issue).
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$can = !empty($d['canManage']);
$cur = $d['cur'] ?? null;
$open = null;
foreach ((array) ($d['mawbs'] ?? []) as $m) if ($m['open']) $open = $m;
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-desk.css')) ?>">
<?= sl_phoneTop('Waybills & manifests', $cur ? $cur['no'] : '', ['back' => sl_route('FreightHub')]) ?>
<main class="mx-body">
<?php if (empty($d['ready'])): ?>
<?= sl_mxEmpty('file', 'Waybills not installed', 'Ask an administrator to run the freight migration.') ?>
<?php else: ?>
<?php if (!empty($d['flash'])): ?><div class="mx-ban info"><i class="ic ic-sm i-check" aria-hidden="true"></i><span><?= h($d['flash']) ?></span></div><?php endif; ?>
<nav class="mx-seg" aria-label="Show">
<a class="<?= $d['tAwb'] ? 'on' : '' ?>" href="<?= h(sl_url(['tab' => null])) ?>">MAWB <?= count($d['mawbs']) ?></a>
<a class="<?= $d['tMan'] ? 'on' : '' ?>" href="<?= h(sl_url(['tab' => 'man'])) ?>">Manifest<?= $cur ? ' · ' . (int) $cur['n'] . ' HAWB' : '' ?></a>
</nav>
<?php if ($d['tMan'] && $cur): ?>
<section class="mx-card mx-kv"><div><span>Master bill</span><b><?= h($cur['no']) ?></b></div><div><span>Flight</span><b><?= h($cur['fl']) ?></b></div><div><span>House bills</span><b><?= (int) $cur['n'] ?> · <?= h($cur['kg']) ?></b></div><div><span>Status</span><b><?= h(ucfirst($cur['status'])) ?></b></div></section>
<?php if (!empty($d['fmts'])): ?><nav class="mx-seg" aria-label="Layout"><?php foreach ($d['fmts'] as $f): ?><a class="<?= h($f['cls']) ?>" href="<?= h($f['href']) ?>"><?= h($f['label']) ?></a><?php endforeach; ?></nav><?php endif; ?>
<section class="mx-card mx-form"><pre class="mono" style="margin:0;font-size:11.5px;white-space:pre;overflow-x:auto"><?= h($d['manifest']) ?></pre></section>
<?php else: ?>
<?php if ($d['mawbs']): ?>
<section class="mx-card mx-list"><?php foreach ($d['mawbs'] as $m): ?><a class="mx-row" href="<?= h($m['href']) ?>"<?= $m['open'] ? ' aria-current="true"' : '' ?>><span class="mx-li"><i class="ic i-file" aria-hidden="true"></i></span><span class="mx-rm"><b>MAWB <?= h($m['no']) ?></b><small><?= h($m['fl'] . ' · ' . $m['n'] . ' HAWB · ' . $m['kg']) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($m['pc']) ?>"><?= h($m['st']) ?></span></span></a><?php endforeach; ?></section>
<?php else: ?><?= sl_mxEmpty('file', 'No master waybills yet', $can ? 'Create one for the next departure.' : '') ?><?php endif; ?>
<?php if ($open): ?>
<?= sl_mxSec('House bills · ' . $open['no']) ?>
<?php if ($open['hawbs']): ?>
<section class="mx-card mx-list"><?php foreach ($open['hawbs'] as $hb): ?><a class="mx-row" href="<?= h(sl_route('ShipmentTracking', ['q' => $hb['trk']])) ?>"><span class="mx-li"><i class="ic i-file" aria-hidden="true"></i></span><span class="mx-rm"><b>HAWB <?= h($hb['trk']) ?></b><small><?= h($hb['cust'] . ' · ' . $hb['kg']) ?></small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a><?php endforeach; ?></section>
<?php endif; ?>
<?php if ($open['more'] !== ''): ?><p class="mx-note"><?= h($open['more']) ?></p><?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<div class="mx-btns">
<?php if ($can): ?><button type="button" class="mx-btn" data-sl-open="pd-wb-new">New master waybill</button><?php endif; ?>
<?php if ($can && $cur && empty($d['issueDisabled'])): ?><button type="button" class="mx-btn sec" data-sl-action="ajax/sl/waybill_ajax.php" data-p-op="issue" data-p-id="<?= (int) $cur['id'] ?>" data-sl-confirm="Issue this master waybill? House bills are frozen into the manifest."><?= h($d['issueLabel']) ?></button><?php endif; ?>
<?php if ($cur): ?><a class="mx-btn sec" href="waybill_print.php?id=<?= (int) $cur['id'] ?>&amp;fmt=<?= h($d['fmt'] ?? 'iata') ?>" target="_blank" rel="noopener">Print manifest</a><?php endif; ?>
</div>
<?php endif; ?>
</main>
<?php if ($can && !empty($d['ready'])): ?>
<div class="sl-dialog pd-sheet" id="pd-wb-new" hidden><form class="pd-sheetf" data-sl-form action="ajax/sl/waybill_ajax.php" method="post" role="dialog" aria-label="New master waybill">
<input type="hidden" name="op" value="create"><h2>New master waybill</h2>
<label class="pd-f"><small>MAWB / bill of lading no.</small><input class="pd-in mono" name="waybill_no" required autocomplete="off"></label>
<label class="pd-f"><small>Departure</small><select class="pd-in" name="schedule_id"><option value="">— none —</option><?php foreach ((array) $d['schedules'] as $s): ?><option value="<?= (int) $s['id'] ?>"><?= h($s['label']) ?></option><?php endforeach; ?></select></label>
<label class="pd-f"><small>Carrier</small><input class="pd-in" name="carrier"></label>
<label class="pd-f"><small>Consolidation no.</small><input class="pd-in mono" name="consolidation_no"></label>
<div class="pd-row2"><label class="pd-f"><small>From</small><input class="pd-in" name="origin" placeholder="from departure"></label><label class="pd-f"><small>To</small><input class="pd-in" name="destination"></label></div>
<div class="pd-row2"><label class="pd-f"><small>Rate per kg</small><input class="pd-in mono" type="number" inputmode="decimal" step="0.0001" min="0" name="rate_per_kg"></label><label class="pd-f"><small>Surcharges per kg</small><input class="pd-in mono" type="number" inputmode="decimal" step="0.0001" min="0" name="surcharge_per_kg"></label></div>
<div class="pd-row2"><label class="pd-f"><small>Container no. (sea)</small><input class="pd-in mono" name="container_no"></label><label class="pd-f"><small>Seal no.</small><input class="pd-in mono" name="seal_no"></label></div>
<label class="pd-f"><small>Container volume ft³</small><input class="pd-in mono" type="number" inputmode="numeric" step="1" min="0" name="container_ft3"></label>
<div class="mx-btns"><button type="submit" class="mx-btn">Create</button><button type="button" class="mx-btn sec" data-sl-close>Cancel</button></div>
</form></div>
<?php endif; ?>
