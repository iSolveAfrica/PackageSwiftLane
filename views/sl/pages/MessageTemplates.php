<?php
/**
 * MessageTemplates - generated from docs/handover/artifact-source/MessageTemplates.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  tabs                         as $t
 *   loop  list                         as $l
 *   loop  heads                        as $h
 *   loop  vars                         as $v
 *   loop  pvs                          as $p
 *   loop  defs                         as $d
 *   if    flash
 *   if    isEdit
 *   if    isWa
 *   if    isWa
 *   if    isWa
 *   if    hImg
 *   if    hDoc
 *   if    hTxt
 *   if    isWa
 *   if    isWa
 *   if    isDef
 *   value actAria, actCls, addWa, body, costLabel, countLabel, curName, curSubj, defs, edSub, edTitle, false, flash, hDoc, hImg, hTxt, heads, inputPh, isDef, isEdit, isWa, list, metaCls, metaSt, meterW, onQ, phCls, phSub, preview, pvNote, pvs, q, reset, save, saveLabel, setBody, subLab, tabs, test, toggleAct, true, vars, waHead
 * sl-wrap extras in the design: style="max-width:1440px"
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="row g8 mut sm"><a href="<?= sl_route('NotificationCenter') ?>">Notification Center</a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)">SMS and WhatsApp templates</span></div>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:20px">SMS and WhatsApp templates</h1><p class="sl-lede">Every text and WhatsApp message the system sends, with a live phone preview. Same templates as Tools › Templates in the system.</p></div><div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('EmailBuilder') ?>"><i class="ic ic-sm i-mail" aria-hidden="true"></i>E-mail builder</a><a class="sl-btn ghost" href="<?= sl_route('Broadcast') ?>"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send to customers</a><button class="sl-btn" data-sl-on="click:save"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= sl_v($d, 'saveLabel') ?></button></div></div>
<div class="mt-tabs" role="tablist"><?php foreach ((array) sl_get($d, 'tabs', []) as $__i => $t): ?><button class="<?= sl_v($t, 'cls') ?>" data-sl-on="click:pick"><i class="ic ic-sm <?= sl_v($t, 'icon') ?>" aria-hidden="true"></i><?= sl_v($t, 'label') ?><span class="sl-badge"><?= sl_v($t, 'n') ?></span></button><?php endforeach; ?></div>
<?php if (sl_get($d, 'flash')): ?><div class="mt-ok" role="status"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= sl_v($d, 'flash') ?></div><?php endif; ?>
<?php if (sl_get($d, 'isEdit')): ?><div class="mt">
<aside class="sl-card flush col"><div style="padding:12px"><div class="sl-search" style="max-width:none;min-width:0"><i class="ic ic-sm i-search" aria-hidden="true"></i><input aria-label="Search templates" placeholder="Search templates" value="<?= sl_v($d, 'q') ?>" data-sl-on="input:onQ"></div></div>
<?php foreach ((array) sl_get($d, 'list', []) as $__i => $l): ?><button class="mt-li <?= sl_v($l, 'cls') ?>" data-sl-on="click:pick"><span class="sl-dot <?= sl_v($l, 'dot') ?>"></span><span class="grow" style="min-width:0"><?= sl_v($l, 'name') ?><small><?= sl_v($l, 'sub') ?></small></span><span class="sl-badge"><?= sl_v($l, 'who') ?></span></button><?php endforeach; ?>
<?php if (sl_get($d, 'isWa')): ?><div style="padding:12px;border-top:1px solid var(--line)"><button class="sl-btn ghost block" data-sl-on="click:addWa"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add new template</button></div><?php endif; ?></aside>
<section class="sl-card col g16"><div class="sl-ch" style="margin:0"><div><h2 class="sl-ct"><?= sl_v($d, 'edTitle') ?></h2><span class="mut sm"><?= sl_v($d, 'edSub') ?></span></div><span class="row g8 sm"><button class="sl-toggle <?= sl_v($d, 'actCls') ?>" role="switch" aria-checked="<?= sl_v($d, 'actAria') ?>" aria-label="Active" data-sl-on="click:toggleAct"></button>Active</span></div>
<div class="grid cols2" style="gap:12px"><label class="sl-field"><span class="sl-lab">Template title</span><input class="sl-in" value="<?= sl_v($d, 'curName') ?>" aria-label="Template title"></label><label class="sl-field"><span class="sl-lab"><?= sl_v($d, 'subLab') ?></span><input class="sl-in" value="<?= sl_v($d, 'curSubj') ?>" aria-label="Subject"></label></div>
<?php if (sl_get($d, 'isWa')): ?><div class="grid cols2" style="gap:12px"><div class="sl-field"><span class="sl-lab">Header</span><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'heads', []) as $__i => $h): ?><button class="<?= sl_v($h, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($h, 'label') ?></button><?php endforeach; ?></div></div><div class="sl-field"><span class="sl-lab">Meta approval</span><div class="row g8"><span class="sl-pill <?= sl_v($d, 'metaCls') ?>"><?= sl_v($d, 'metaSt') ?></span><span class="mut sm">Category: Utility · Language: English</span></div></div></div><?php endif; ?>
<label class="sl-field"><span class="sl-lab">Message</span><textarea class="mt-ta" data-sl-on="input:setBody"><?= sl_v($d, 'body') ?></textarea></label>
<div class="col g6"><div class="row between sm"><span class="mut"><?= sl_v($d, 'countLabel') ?></span><span class="mono"><?= sl_v($d, 'costLabel') ?></span></div><div class="mt-meter"><i style="<?= sl_v($d, 'meterW') ?>"></i></div></div>
<div class="col g6"><span class="up">Variables · click to add · do not change the words inside [ ]</span><div class="row wrap g6"><?php foreach ((array) sl_get($d, 'vars', []) as $__i => $v): ?><button class="mt-var" title="<?= sl_v($v, 'd') ?>" data-sl-on="click:ins"><?= sl_v($v, 'k') ?></button><?php endforeach; ?></div></div>
<?php if (sl_get($d, 'isWa')): ?><div class="col g8"><span class="up">Buttons</span><div class="grid cols2" style="gap:10px"><label class="sl-field"><span class="sl-lab">Link button</span><input class="sl-in" value="Track my parcel → [URL_SHIP]" aria-label="Link button"></label><label class="sl-field"><span class="sl-lab">Call button</span><input class="sl-in" value="Call us → +233 30 290 1144" aria-label="Call button"></label></div></div><?php endif; ?>
<div class="row between wrap g8" style="padding-top:12px;border-top:1px solid var(--line)"><div class="row g8"><input class="sl-in mono" value="+233 24 551 0921" style="width:180px" aria-label="Test number"><button class="sl-btn ghost" data-sl-on="click:test"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send test</button></div><button class="sl-btn ghost sm" data-sl-on="click:reset">Reset to system text</button></div>
</section>
<aside class="col g12" style="align-items:center"><div class="row between" style="width:300px"><span class="up">Live preview</span><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'pvs', []) as $__i => $p): ?><button class="<?= sl_v($p, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($p, 'label') ?></button><?php endforeach; ?></div></div>
<div class="ph <?= sl_v($d, 'phCls') ?>"><div class="ph-sc"><div class="ph-st"><span>9:41</span><span class="ph-ic" aria-hidden="true"><i class="sig"><b></b><b></b><b></b><b></b></i><i class="wifi"></i><i class="bat"><b></b></i></span></div><div class="ph-hd"><span class="ph-av">SL</span><span class="col"><span>Swift Lane</span><span style="font:400 11px Figtree;opacity:.75"><?= sl_v($d, 'phSub') ?></span></span></div>
<div class="ph-bd"><span class="ph-day">Today</span><div class="bub"><?php if (sl_get($d, 'hImg')): ?><span class="img">Parcel photo</span><?php endif; ?><?php if (sl_get($d, 'hDoc')): ?><span class="doc"><i class="ic ic-sm i-file" aria-hidden="true"></i>Invoice-SL-100482.pdf</span><?php endif; ?><?php if (sl_get($d, 'hTxt')): ?><span class="hd"><?= sl_v($d, 'waHead') ?></span><?php endif; ?><?= sl_v($d, 'preview') ?><?php if (sl_get($d, 'isWa')): ?><span class="tm">09:41</span><?php endif; ?></div>
<?php if (sl_get($d, 'isWa')): ?><div class="btns"><span><i class="ic ic-sm i-link" aria-hidden="true"></i>Track my parcel</span><span><i class="ic ic-sm i-phone" aria-hidden="true"></i>Call us</span></div><?php endif; ?></div>
<div class="ph-in"><i class="ic ic-sm i-plus" aria-hidden="true" style="color:#888"></i><span><?= sl_v($d, 'inputPh') ?></span><span class="mic"><i class="ic ic-sm i-send" aria-hidden="true"></i></span></div></div></div>
<span class="mut sm" style="width:300px"><?= sl_v($d, 'pvNote') ?></span></aside>
</div><?php endif; ?>
<?php if (sl_get($d, 'isDef')): ?><section class="sl-card flush mt-def"><div style="padding:16px 18px"><h2 class="sl-ct">Setting default notification templates</h2><span class="mut sm">Pick which template each event uses on each channel, and switch events on or off. From Tools › Default templates in the system.</span></div>
<div class="sl-tw"><table class="sl-table"><thead><tr><th>Activate</th><th>Notification</th><th>E-mail template</th><th>SMS template</th><th>WhatsApp template</th></tr></thead><tbody>
<?php foreach ((array) sl_get($d, 'defs', []) as $__i => $it_d): ?><tr><td><button class="sl-toggle <?= sl_v($it_d, 'cls') ?>" role="switch" aria-checked="<?= sl_v($it_d, 'aria') ?>" aria-label="<?= sl_v($it_d, 'n') ?>" data-sl-on="click:flip"></button></td><td class="fw5"><?= sl_v($it_d, 'n') ?></td><td><span class="mt-pick"><i class="ic ic-sm i-mail mut" aria-hidden="true"></i><span><?= sl_v($it_d, 'e') ?></span></span></td><td><span class="mt-pick"><i class="ic ic-sm i-phone mut" aria-hidden="true"></i><span><?= sl_v($it_d, 's') ?></span></span></td><td><span class="mt-pick"><i class="ic ic-sm i-message mut" aria-hidden="true"></i><span><?= sl_v($it_d, 'w') ?></span></span></td></tr><?php endforeach; ?>
</tbody></table></div><div class="row g8" style="padding:14px 18px;border-top:1px solid var(--line)"><button class="sl-btn" data-sl-on="click:save">Save</button><a class="sl-btn ghost" href="<?= sl_route('NotificationCenter') ?>">Open Notification Center</a></div></section><?php endif; ?>
