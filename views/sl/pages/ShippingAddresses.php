<?php
/**
 * ShippingAddresses - generated from docs/handover/artifact-source/ShippingAddresses.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  fields                       as $f
 *   loop  modes                        as $m
 *   loop  rows                         as $r
 *   loop  pv.lines                     as $l
 *   if    flash
 *   if    formOpen
 *   value add, closeForm, count, false, fields, flash, formOpen, modes, onCount, openForm, rows, true
 * sl-wrap extras in the design: style="max-width:1240px"
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="row g8 mut sm"><a href="<?= sl_route('ControlPanels') ?>">Settings</a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)">Shipping addresses</span></div>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:20px">Shipping addresses</h1><p class="sl-lede">Warehouse addresses customers ship to. Switch one on and it shows on every customer's account right away.</p></div><div class="row g8"><a class="sl-btn ghost" href="<?= sl_route('UserHome') ?>"><i class="ic ic-sm i-eye" aria-hidden="true"></i>See as customer</a><button class="sl-btn" data-sl-on="click:openForm"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add address</button></div></div>
<?php if (sl_get($d, 'flash')): ?><div role="status" style="display:flex;gap:10px;padding:12px 14px;border-radius:12px;background:var(--green-bg);color:var(--green-ink)"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= sl_v($d, 'flash') ?></div><?php endif; ?>
<div class="split">
<div class="a col g12">
<?php if (sl_get($d, 'formOpen')): ?><section class="sl-card col g12" style="border-color:var(--ink)"><div class="sl-ch" style="margin:0"><h2 class="sl-ct">New address</h2><button class="sl-ib" aria-label="Close" data-sl-on="click:closeForm"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>
<div class="grid cols2"><?php foreach ((array) sl_get($d, 'fields', []) as $__i => $f): ?><label class="sl-field"><span class="sl-lab"><?= sl_v($f, 'label') ?></span><input class="sl-in" placeholder="<?= sl_v($f, 'ph') ?>" value="<?= sl_v($f, 'val') ?>" data-sl-on="change:set"></label><?php endforeach; ?></div>
<div class="row g12 wrap"><span class="sl-lab">Shipping mode</span><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'modes', []) as $__i => $m): ?><button class="<?= sl_v($m, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($m, 'label') ?></button><?php endforeach; ?></div><span class="grow"></span><button class="sl-btn ghost" data-sl-on="click:closeForm">Cancel</button><button class="sl-btn" data-sl-on="click:add"><i class="ic ic-sm i-check" aria-hidden="true"></i>Save and show to customers</button></div></section><?php endif; ?>
<section class="sl-card flush"><div class="row between" style="padding:14px 16px"><h2 class="sl-ct">Addresses</h2><span class="mut sm"><?= sl_v($d, 'onCount') ?> of <?= sl_v($d, 'count') ?> showing to customers</span></div>
<?php foreach ((array) sl_get($d, 'rows', []) as $__i => $r): ?><div class="row g12" style="padding:12px 16px;border-top:1px solid var(--line)"><span class="sl-tile" style="font:600 12px var(--mono)"><?= sl_v($r, 'cc') ?></span><div class="grow col"><span class="fw5"><?= sl_v($r, 'label') ?> <span class="mut sm">· <?= sl_v($r, 'mode') ?></span></span><span class="mut sm"><?= sl_v($r, 'line') ?></span></div><span class="mut sm" style="width:110px"><?= sl_v($r, 'transit') ?></span><span class="sm <?= sl_v($r, 'sc') ?>" style="width:80px"><?= sl_v($r, 'state') ?></span><button class="sl-toggle <?= sl_v($r, 'on') ?>" role="switch" aria-checked="<?= sl_v($r, 'aria') ?>" aria-label="Show <?= sl_v($r, 'label') ?> to customers" data-sl-on="click:flip"></button><button class="sl-ib" aria-label="Preview" data-sl-on="click:show"><i class="ic ic-sm i-eye" aria-hidden="true"></i></button><button class="sl-ib" aria-label="Delete" data-sl-on="click:del"><i class="ic ic-sm i-trash" aria-hidden="true"></i></button></div><?php endforeach; ?></section>
</div>
<aside class="b col g12"><span class="up">What customers see</span>
<div style="border-radius:20px;background:var(--ink);color:var(--inverse);padding:20px;display:flex;flex-direction:column;gap:10px"><span class="row g8"><span class="sl-tile" style="background:rgba(255,255,255,.1);color:inherit;font:600 12px var(--mono)"><?= sl_v($d, 'pv.cc') ?></span><b class="fw5"><?= sl_v($d, 'pv.label') ?> · <?= sl_v($d, 'pv.mode') ?></b></span>
<?php foreach ((array) sl_get($d, 'pv.lines', []) as $__i => $l): ?><div class="row between" style="border-top:1px dashed rgba(255,255,255,.18);padding-top:8px;font-size:13.5px"><span style="opacity:.55;font-size:11px;text-transform:uppercase;letter-spacing:.06em"><?= sl_v($l, 'k') ?></span><span><?= sl_v($l, 'v') ?></span></div><?php endforeach; ?>
<span class="sm" style="opacity:.7"><?= sl_v($d, 'pv.transit') ?> to Accra</span></div>
<div class="sl-card col g6"><span class="fw5 sm">Customer's suite number is added automatically</span><span class="mut sm">"Suite SL-3318" is filled in for each customer. Changes are written to Activity Logs.</span></div></aside></div>
