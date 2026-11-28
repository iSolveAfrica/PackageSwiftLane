<?php
/**
 * ConsolidationDetail (sea) and ConsolidationAir (air) - from docs/handover/artifact-source/ConsolidationDetail.dc.html
 * and ConsolidationAir.dc.html. The two designs are the same page; $d['sea'] picks the copy.
 * Data: views/sl/data/consolidations.php (sl_consolidationDetailData). Writes: ajax/sl/consolidation_ajax.php.
 * Operational only: no charges, totals or payment state (consolidation billing is in Finance).
 */
if (!isset($d)) { $d = []; }
$sea = $d['sea']; $can = $d['can'];
$self = sl_route('ConsolidationDetail', ['id' => $d['cid'], 'type' => $d['type']]);
?>
<div class="row g8 mut sm"><a href="<?= sl_route($d['type'] === 'sea' ? 'ConsolidationsSea' : 'ConsolidationsAir') ?>"><?= $d['type'] === 'sea' ? 'Sea Consolidations' : 'Air Consolidations' ?></a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)"><?= h($d['code']) ?></span></div>
<div class="sl-head"><div><h1 class="sl-title row g8" style="font-size:22px"><span class="sl-tile"><i class="ic <?= $sea ? 'i-ship' : 'i-plane' ?>" aria-hidden="true"></i></span><?= h($d['code']) ?><span class="sl-pill <?= h($d['stCls']) ?>"><?= h($d['st']) ?></span><span class="sl-badge"><?= $sea ? 'Sea' : 'Air' ?> consolidation</span></h1><p class="sl-lede"><?= h($d['lede']) ?></p></div><div class="row g8 wrap">
<?php if ($d['canEdit']): ?><a class="sl-btn" href="<?= h($self . '&add=1') ?>#packages" data-cpk-open><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add packages</a><?php endif; ?>
<?php if ($can['packing']): ?><a class="sl-btn ghost" target="_blank" href="<?= h($can['packing']) ?>"><i class="ic ic-sm i-print" aria-hidden="true"></i>Packing list</a><?php elseif ($can['print']): ?><a class="sl-btn ghost" target="_blank" href="<?= h($can['print']) ?>"><i class="ic ic-sm i-print" aria-hidden="true"></i>Packing list</a><?php endif; ?>
<?php if ($can['notify']): ?><a class="sl-btn ghost" href="<?= sl_route('Broadcast', ['consolidation' => $d['code']]) ?>"><i class="ic ic-sm i-send" aria-hidden="true"></i>Notify customers</a><?php endif; ?>
<?php if ($can['label']): ?><a class="sl-btn ghost" target="_blank" href="<?= h($can['label']) ?>"><i class="ic ic-sm i-tag" aria-hidden="true"></i>Labels</a><?php endif; ?>
<?php if ($can['track']): ?><a class="sl-btn ghost" href="<?= h($can['track']) ?>"><i class="ic ic-sm i-refresh" aria-hidden="true"></i>Update status</a><?php endif; ?>
<?php if ($can['deliver']): ?><a class="sl-btn ghost" href="<?= h($can['deliver']) ?>"><i class="ic ic-sm i-box" aria-hidden="true"></i>Hand over</a><?php endif; ?>
<?php if ($can['edit']): ?><a class="sl-btn ghost" href="<?= h($can['edit']) ?>"><i class="ic ic-sm i-edit" aria-hidden="true"></i>Edit</a><?php endif; ?></div></div>
<div class="cd-tabs"><a class="chipbtn on" href="<?= h($self) ?>"><i class="ic ic-sm <?= $sea ? 'i-ship' : 'i-plane' ?>" aria-hidden="true"></i><?= $sea ? 'Sea' : 'Air' ?> · <?= h($d['code']) ?></a><?php if ($d['other']): ?><a class="chipbtn " href="<?= sl_route('ConsolidationDetail', ['id' => $d['other']['id'], 'type' => $d['other']['type']]) ?>"><i class="ic ic-sm <?= $sea ? 'i-plane' : 'i-ship' ?>" aria-hidden="true"></i><?= $sea ? 'Air' : 'Sea' ?> · <?= h($d['other']['code']) ?></a><?php endif; ?><?php if ($d['canNew']): ?><a class="chipbtn" href="<?= sl_route('NewConsolidation', ['type' => $d['type']]) ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New consolidation</a><?php endif; ?></div>
<?php if ($d['flash']): ?><div class="cd-ok" role="status"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= h($d['flash']) ?></div><?php endif; ?>
<?php include __DIR__ . '/partials/con_load_plan.php'; ?>
<?php if ($can['delete']): ?><button class="sl-btn ghost" style="color:var(--red-ink)" data-sl-action="ajax/sl/consolidation_ajax.php" data-p-op="delete" data-p-id="<?= (int) $d['cid'] ?>" data-p-type="<?= h($d['type']) ?>" data-sl-confirm="Cancel consolidation <?= h($d['code']) ?>? Its packages are released."><i class="ic ic-sm i-trash" aria-hidden="true"></i>Cancel consolidation</button><?php endif; ?>
