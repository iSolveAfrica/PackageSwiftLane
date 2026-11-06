<?php
/**
 * EmailBuilder - generated from docs/handover/artifact-source/EmailBuilder.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  views                        as $v
 *   loop  stages                       as $g
 *   loop  ltabs                        as $t
 *   loop  palette                      as $p
 *   loop  tpls                         as $t
 *   loop  vars                         as $v
 *   loop  aligns                       as $a
 *   loop  colors                       as $c
 *   loop  blocks                       as $b
 *   if    flash
 *   if    stDesign
 *   if    tabBlocks
 *   if    tabTpl
 *   if    tabVars
 *   if    inspOpen
 *   if    hasText
 *   if    hasAlign
 *   if    hasColor
 *   if    hasLink
 *   if    b.isHeader
 *   if    b.isHeading
 *   if    b.isText
 *   if    b.isButton
 *   if    b.isImage
 *   if    b.isTrack
 *   if    b.isTable
 *   if    b.isCode
 *   if    b.isDivider
 *   if    b.isSpacer
 *   if    b.isCols
 *   if    b.isFooter
 *   if    stSend
 *   value aligns, backDesign, blockCount, blocks, closeInsp, colors, dataLabel, dragEnd, endDrop, endDropFn, endOver, false, flash, hasAlign, hasColor, hasLink, hasText, inspOpen, ltabs, mailCls, palette, pre, preShown, save, saveLabel, selText, selTitle, selType, sendTest, setPre, setSubject, setText, stDesign, stSend, stageOver, stages, subject, subjectShown, tabBlocks, tabTpl, tabVars, textLab, toggleData, tplName, tpls, true, vars, views, widthLabel
 * sl-wrap extras in the design: 
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="row g8 mut sm"><a href="<?= sl_route('NotificationCenter') ?>">Notification Center</a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)">Email builder</span></div>
<div class="sl-head"><div><h1 class="sl-title row g8" style="font-size:20px">Email builder<span class="sl-badge"><?= sl_v($d, 'tplName') ?></span></h1><p class="sl-lede">Drag blocks onto the e-mail, click one to edit it. Variables in [BRACKETS] fill in for each customer.</p></div><div class="row g8 wrap"><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'views', []) as $__i => $v): ?><button class="<?= sl_v($v, 'cls') ?>" data-sl-on="click:pick"><i class="ic ic-sm <?= sl_v($v, 'icon') ?>" aria-hidden="true"></i><?= sl_v($v, 'label') ?></button><?php endforeach; ?></div><button class="sl-btn ghost" data-sl-on="click:toggleData"><i class="ic ic-sm i-eye" aria-hidden="true"></i><?= sl_v($d, 'dataLabel') ?></button><button class="sl-btn ghost" data-sl-on="click:sendTest"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send test</button><button class="sl-btn" data-sl-on="click:save"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= sl_v($d, 'saveLabel') ?></button></div></div>
<?php if (sl_get($d, 'flash')): ?><div class="eb-ok" role="status"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= sl_v($d, 'flash') ?></div><?php endif; ?>
<div class="eb-steps" role="tablist"><?php foreach ((array) sl_get($d, 'stages', []) as $__i => $g): ?><button class="<?= sl_v($g, 'cls') ?>" data-sl-on="click:pick"><b><?= sl_v($g, 'n') ?></b><?= sl_v($g, 'label') ?></button><?php endforeach; ?></div>
<?php if (sl_get($d, 'stDesign')): ?><div class="eb">
<aside class="sl-card col g12" style="padding:14px"><div class="eb-tabs"><?php foreach ((array) sl_get($d, 'ltabs', []) as $__i => $t): ?><button class="<?= sl_v($t, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($t, 'label') ?></button><?php endforeach; ?></div>
<?php if (sl_get($d, 'tabBlocks')): ?><span class="mut sm">Drag onto the e-mail or click to add below the selected block</span><div class="eb-pal"><?php foreach ((array) sl_get($d, 'palette', []) as $__i => $p): ?><button class="eb-blk" draggable="true" data-sl-on="dragstart:drag" data-sl-on="dragend:dragEnd" data-sl-on="click:add"><i class="ic <?= sl_v($p, 'icon') ?>" aria-hidden="true"></i><?= sl_v($p, 'label') ?></button><?php endforeach; ?></div><?php endif; ?>
<?php if (sl_get($d, 'tabTpl')): ?><span class="mut sm">The 20 system e-mails from the codebase</span><div class="col g2"><?php foreach ((array) sl_get($d, 'tpls', []) as $__i => $t): ?><button class="eb-tpl <?= sl_v($t, 'cls') ?>" data-sl-on="click:pick"><i class="ic ic-sm i-mail mut" aria-hidden="true"></i><span class="grow"><?= sl_v($t, 'name') ?></span><span class="sl-dot <?= sl_v($t, 'dot') ?>"></span></button><?php endforeach; ?></div><?php endif; ?>
<?php if (sl_get($d, 'tabVars')): ?><span class="mut sm">Click to put a variable into the selected text. Do not change the words inside the brackets.</span><div class="row wrap g6"><?php foreach ((array) sl_get($d, 'vars', []) as $__i => $v): ?><button class="eb-var" title="<?= sl_v($v, 'd') ?>" data-sl-on="click:ins"><?= sl_v($v, 'k') ?></button><?php endforeach; ?></div><?php endif; ?>
</aside>
<section class="col g8"><div class="row between sm"><span class="mut">Subject: <b class="fw5" style="color:var(--ink)"><?= sl_v($d, 'subjectShown') ?></b></span><span class="mut"><?= sl_v($d, 'blockCount') ?> blocks · <?= sl_v($d, 'widthLabel') ?></span></div>
<div class="eb-stage" data-sl-on="dragover:stageOver"><?php if (sl_get($d, 'inspOpen')): ?><section class="eb-insp col g12"><div class="sl-ch" style="margin:0"><h2 class="sl-ct"><?= sl_v($d, 'selTitle') ?></h2><span class="row g6"><span class="sl-badge"><?= sl_v($d, 'selType') ?></span><button class="sl-ib" style="width:32px;height:32px" aria-label="Close" data-sl-on="click:closeInsp"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></span></div>
<?php if (sl_get($d, 'hasText')): ?><label class="sl-field"><span class="sl-lab"><?= sl_v($d, 'textLab') ?></span><textarea class="eb-ta" data-sl-on="input:setText"><?= sl_v($d, 'selText') ?></textarea></label><?php endif; ?>
<?php if (sl_get($d, 'hasAlign')): ?><div class="sl-field"><span class="sl-lab">Align</span><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'aligns', []) as $__i => $a): ?><button class="<?= sl_v($a, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($a, 'label') ?></button><?php endforeach; ?></div></div><?php endif; ?>
<?php if (sl_get($d, 'hasColor')): ?><div class="sl-field"><span class="sl-lab">Colour</span><div class="row g8"><?php foreach ((array) sl_get($d, 'colors', []) as $__i => $c): ?><button class="eb-sw <?= sl_v($c, 'cls') ?>" style="<?= sl_v($c, 'st') ?>" aria-label="<?= sl_v($c, 'n') ?>" data-sl-on="click:pick"></button><?php endforeach; ?></div></div><?php endif; ?>
<?php if (sl_get($d, 'hasLink')): ?><label class="sl-field"><span class="sl-lab">Link</span><input class="sl-in mono" value="[URL_SHIP]" aria-label="Button link"></label><?php endif; ?>

</section><?php endif; ?><div class="eb-mail <?= sl_v($d, 'mailCls') ?>">
<?php foreach ((array) sl_get($d, 'blocks', []) as $__i => $b): ?>
<div class="eb-drop <?= sl_v($b, 'dropCls') ?>" data-sl-on="dragover:over" data-sl-on="drop:drop"></div>
<div class="eb-b <?= sl_v($b, 'sel') ?>" draggable="true" data-sl-on="dragstart:drag" data-sl-on="dragend:dragEnd" data-sl-on="click:pick"><span class="eb-tag"><?= sl_v($b, 'label') ?></span><span class="eb-tb"><button aria-label="Move up" data-sl-on="click:up"><i class="ic ic-sm i-up" aria-hidden="true"></i></button><button aria-label="Move down" data-sl-on="click:down"><i class="ic ic-sm i-down" aria-hidden="true"></i></button><button aria-label="Duplicate" data-sl-on="click:dup"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button><button aria-label="Delete block" data-sl-on="click:del"><i class="ic ic-sm i-trash" aria-hidden="true"></i></button></span>
<?php if (sl_get($b, 'isHeader')): ?><div class="eb-hd" style="<?= sl_v($b, 'bg') ?>"><span class="eb-logo">SL</span>Swift Lane<span style="margin-left:auto;font:400 12.5px Figtree,sans-serif;color:#7A766E"><?= sl_v($b, 'text') ?></span></div><?php endif; ?>
<?php if (sl_get($b, 'isHeading')): ?><div class="eb-h" style="<?= sl_v($b, 'align') ?>"><?= sl_v($b, 'text') ?></div><?php endif; ?>
<?php if (sl_get($b, 'isText')): ?><div class="eb-t" style="<?= sl_v($b, 'align') ?>"><?= sl_v($b, 'text') ?></div><?php endif; ?>
<?php if (sl_get($b, 'isButton')): ?><div class="eb-btnw" style="<?= sl_v($b, 'align') ?>"><span class="eb-btn" style="<?= sl_v($b, 'btnBg') ?>"><?= sl_v($b, 'text') ?></span></div><?php endif; ?>
<?php if (sl_get($b, 'isImage')): ?><div class="eb-img"><span><i class="ic i-image" aria-hidden="true"></i> <?= sl_v($b, 'text') ?></span></div><?php endif; ?>
<?php if (sl_get($b, 'isTrack')): ?><div class="eb-trk"><div><small>Tracking</small><b><?= sl_v($b, 'trk') ?></b></div><div><small>Status</small><b><?= sl_v($b, 'status') ?></b></div><div><small>Mode</small><b>Air · Newark → Accra</b></div><div><small>Arrives</small><b><?= sl_v($b, 'eta') ?></b></div><div class="eb-prog"><i class="on"></i><i class="on"></i><i class="on"></i><i></i></div></div><?php endif; ?>
<?php if (sl_get($b, 'isTable')): ?><table class="eb-tbl"><tbody><tr><td>Freight (4.2 lb × ₵74.40)</td><td>₵312.48</td></tr><tr><td>Handling</td><td>₵25.00</td></tr><tr><td>Delivery to door</td><td>₵35.00</td></tr><tr><td>Total to pay</td><td>₵372.48</td></tr></tbody></table><?php endif; ?>
<?php if (sl_get($b, 'isCode')): ?><div class="eb-code"><span style="font-size:12.5px;color:#7A766E">Show this code at pickup</span><b><?= sl_v($b, 'code') ?></b></div><?php endif; ?>
<?php if (sl_get($b, 'isDivider')): ?><hr class="eb-hr"><?php endif; ?>
<?php if (sl_get($b, 'isSpacer')): ?><div style="height:28px"></div><?php endif; ?>
<?php if (sl_get($b, 'isCols')): ?><div class="eb-cols"><div><b>Pick up</b><br>Accra Hub, Spintex Rd. Mon–Sat 8:00–18:00</div><div><b>Need help?</b><br>WhatsApp +233 30 290 1144 or reply to this e-mail</div></div><?php endif; ?>
<?php if (sl_get($b, 'isFooter')): ?><div class="eb-ft"><?= sl_v($b, 'text') ?><br><a href="<?= sl_route('NotificationCenter') ?>">Manage notifications</a> · <a href="<?= sl_route('CustomerNotifications') ?>">Unsubscribe</a></div><?php endif; ?>
</div><?php endforeach; ?>
<div class="eb-drop <?= sl_v($d, 'endDrop') ?>" data-sl-on="dragover:endOver" data-sl-on="drop:endDropFn" style="margin-bottom:16px"></div>
</div></div></section>
</div><?php endif; ?>
<?php if (sl_get($d, 'stSend')): ?><div class="eb-set"><section class="sl-card col g12"><h2 class="sl-ct">Subject and sender</h2><label class="sl-field"><span class="sl-lab">Subject</span><input class="sl-in" value="<?= sl_v($d, 'subject') ?>" data-sl-on="input:setSubject"></label><label class="sl-field"><span class="sl-lab">Preview text</span><input class="sl-in" value="<?= sl_v($d, 'pre') ?>" data-sl-on="input:setPre"><span class="sl-hint">Shown after the subject in most inboxes</span></label><div class="grid cols2" style="gap:10px"><label class="sl-field"><span class="sl-lab">From name</span><input class="sl-in" value="Swift Lane" aria-label="From name"></label><label class="sl-field"><span class="sl-lab">Reply to</span><input class="sl-in" value="help@swiftlane.com" aria-label="Reply to"></label></div>
<span class="up">Inbox preview</span><div class="eb-inbox"><span class="eb-logo" style="flex:none">SL</span><div class="col grow" style="min-width:0"><div class="row between sm"><b class="fw5">Swift Lane</b><span class="mut">09:41</span></div><span class="sm fw5" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= sl_v($d, 'subjectShown') ?></span><span class="mut" style="font-size:12.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= sl_v($d, 'preShown') ?></span></div></div></section>
<section class="sl-card col g8"><h2 class="sl-ct">Send a test</h2><div class="row g8"><input class="sl-in grow" value="henry@swiftlane.com" aria-label="Test e-mail address"><button class="sl-btn ghost" data-sl-on="click:sendTest">Send</button></div><div class="row between sm"><span class="mut">Also send the SMS and WhatsApp versions</span><a class="fw5" href="<?= sl_route('MessageTemplates') ?>">Edit them</a></div></section>
<section class="sl-card col g12"><h2 class="sl-ct">Ready to use</h2><span class="mut sm">This e-mail goes out for: <b class="fw5" style="color:var(--ink)"><?= sl_v($d, 'tplName') ?></b></span><div class="row g8"><button class="sl-btn" data-sl-on="click:save"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= sl_v($d, 'saveLabel') ?></button><button class="sl-btn ghost" data-sl-on="click:backDesign">Back to design</button></div></section></div><?php endif; ?>
