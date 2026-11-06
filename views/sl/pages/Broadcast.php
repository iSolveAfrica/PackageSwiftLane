<?php
/**
 * Broadcast - generated from docs/handover/artifact-source/Broadcast.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  types                        as $t
 *   loop  chans                        as $c
 *   loop  tos                          as $t
 *   loop  users                        as $u
 *   loop  vars                         as $v
 *   loop  rows                         as $r
 *   loop  whens                        as $w
 *   if    pickUsers
 *   if    isInvoice
 *   if    sending
 *   if    chWa
 *   if    hasAttach
 *   if    chSms
 *   if    chMail
 *   if    noCh
 *   value addRow, attach, chMail, chSms, chWa, chans, costBreak, costTotal, deliv, failed, false, hasAttach, invTotal, isInvoice, msg, msgShown, noCh, pickUsers, progW, read, rows, send, sendLabel, sending, sent, setMsg, setSubject, skipNote, smsCount, smsShown, subject, tos, true, typeHint, types, u, users, vars, whens
 * sl-wrap extras in the design: style="max-width:1440px"
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="row g8 mut sm"><a href="<?= sl_route('NotificationCenter') ?>">Notification Center</a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)">Send to customers</span></div>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:20px">Push notifications</h1><p class="sl-lede">Send one message to many customers by WhatsApp, SMS and e-mail, and see exactly what each one gets before you send.</p></div><div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('MessageLogs') ?>"><i class="ic ic-sm i-clock" aria-hidden="true"></i>Sent history</a><a class="sl-btn ghost" href="<?= sl_route('MessageTemplates') ?>">Templates</a></div></div>
<div class="bc"><div class="col g16">
<section class="sl-card col g12"><h2 class="sl-ct">1 · Push type</h2><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'types', []) as $__i => $t): ?><button class="<?= sl_v($t, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($t, 'label') ?></button><?php endforeach; ?></div><span class="mut sm"><?= sl_v($d, 'typeHint') ?></span></section>
<section class="sl-card col g12"><h2 class="sl-ct">2 · Send by</h2><div class="grid cols3" style="gap:8px"><?php foreach ((array) sl_get($d, 'chans', []) as $__i => $c): ?><button class="bc-ch <?= sl_v($c, 'cls') ?>" data-sl-on="click:flip" aria-pressed="<?= sl_v($c, 'p') ?>"><span class="sl-chk <?= sl_v($c, 'chk') ?>" aria-hidden="true"></span><i class="ic <?= sl_v($c, 'icon') ?>" aria-hidden="true"></i><span class="col grow" style="text-align:left"><?= sl_v($c, 'label') ?><small><?= sl_v($c, 'sub') ?></small></span></button><?php endforeach; ?></div></section>
<section class="sl-card col g12"><h2 class="sl-ct">3 · To</h2><div class="bc-to"><?php foreach ((array) sl_get($d, 'tos', []) as $__i => $t): ?><button class="bc-ch <?= sl_v($t, 'cls') ?>" data-sl-on="click:pick"><i class="ic ic-sm <?= sl_v($t, 'icon') ?>" aria-hidden="true"></i><span class="col grow" style="text-align:left"><?= sl_v($t, 'label') ?><small><?= sl_v($t, 'n') ?></small></span></button><?php endforeach; ?></div>
<?php if (sl_get($d, 'pickUsers')): ?><div class="row wrap g6"><?php foreach ((array) sl_get($d, 'users', []) as $__i => $u): ?><span class="sl-badge"><?= sl_v($u, '') ?></span><?php endforeach; ?><a class="sl-btn ghost sm" href="<?= sl_route('Customers') ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Choose customers</a></div><?php endif; ?>
<div class="sl-banner info"><i class="ic i-info" aria-hidden="true"></i><span><?= sl_v($d, 'skipNote') ?></span></div></section>
<section class="sl-card col g12"><h2 class="sl-ct">4 · Message</h2><label class="sl-field"><span class="sl-lab">Notification's subject</span><input class="sl-in" value="<?= sl_v($d, 'subject') ?>" data-sl-on="input:setSubject"></label><label class="sl-field"><span class="sl-lab">Notification content / message</span><textarea class="mt-ta" data-sl-on="input:setMsg"><?= sl_v($d, 'msg') ?></textarea></label>
<div class="row wrap g6"><?php foreach ((array) sl_get($d, 'vars', []) as $__i => $v): ?><button class="mt-var" data-sl-on="click:ins"><?= sl_v($v, 'k') ?></button><?php endforeach; ?></div>
<div class="row between sm"><span class="mut"><?= sl_v($d, 'smsCount') ?></span><label class="row g8" style="cursor:pointer"><i class="ic ic-sm i-file" aria-hidden="true"></i><span>WhatsApp attachment</span><span class="sl-badge"><?= sl_v($d, 'attach') ?></span></label></div></section>
<?php if (sl_get($d, 'isInvoice')): ?><section class="sl-card col g12"><h2 class="sl-ct">Invoice push notification</h2><div class="grid cols3" style="gap:12px"><label class="sl-field"><span class="sl-lab">Shipment date: from</span><input class="sl-in" value="Oct 20, 2026" aria-label="From"></label><label class="sl-field"><span class="sl-lab">Shipment date: to</span><input class="sl-in" value="Nov 5, 2026" aria-label="To"></label><label class="sl-field"><span class="sl-lab">Date of pickup</span><input class="sl-in" value="Nov 8, 2026" aria-label="Pickup date"></label></div>
<div class="col g8"><div class="bc-row up"><span>Item</span><span>Package</span><span>Amount</span><span></span></div><?php foreach ((array) sl_get($d, 'rows', []) as $__i => $r): ?><div class="bc-row"><input class="sl-in" value="<?= sl_v($r, 'item') ?>" aria-label="Item"><input class="sl-in mono" value="<?= sl_v($r, 'pkg') ?>" aria-label="Package"><input class="sl-in mono" value="<?= sl_v($r, 'amt') ?>" aria-label="Amount"><button class="sl-ib" aria-label="Remove row" data-sl-on="click:del"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div><?php endforeach; ?><div class="row between"><button class="sl-btn ghost sm" data-sl-on="click:addRow"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add invoice row</button><span class="fw5">Total <?= sl_v($d, 'invTotal') ?></span></div></div></section><?php endif; ?>
<section class="sl-card col g12"><h2 class="sl-ct">5 · When</h2><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'whens', []) as $__i => $w): ?><button class="<?= sl_v($w, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($w, 'label') ?></button><?php endforeach; ?></div>
<div class="row between wrap g8" style="padding-top:12px;border-top:1px solid var(--line)"><span class="col"><b class="fw5"><?= sl_v($d, 'costTotal') ?></b><span class="mut sm"><?= sl_v($d, 'costBreak') ?></span></span><button class="sl-btn" style="height:46px;padding:0 22px" data-sl-on="click:send"><i class="ic ic-sm i-send" aria-hidden="true"></i><?= sl_v($d, 'sendLabel') ?></button></div>
<?php if (sl_get($d, 'sending')): ?><div class="col g8"><div class="bc-bar"><i style="<?= sl_v($d, 'progW') ?>"></i></div><div class="bc-live"><div><b><?= sl_v($d, 'sent') ?></b><small>Sent</small></div><div><b><?= sl_v($d, 'deliv') ?></b><small>Delivered</small></div><div><b><?= sl_v($d, 'read') ?></b><small>Read (WhatsApp)</small></div><div><b><?= sl_v($d, 'failed') ?></b><small>Failed</small></div></div><a class="sm fw5" href="<?= sl_route('MessageLogs') ?>">Open in message logs →</a></div><?php endif; ?></section>
</div>
<aside class="col g12" style="position:sticky;top:12px"><div class="row between"><h2 class="sl-ct">What customers get</h2><span class="mut sm">Sample: Kwame Mensah</span></div>
<div class="bc-pv">
<?php if (sl_get($d, 'chWa')): ?><div class="ph wa"><div class="ph-sc"><div class="ph-st"><span>9:41</span><span class="ph-ic" aria-hidden="true"><i class="sig"><b></b><b></b><b></b><b></b></i><i class="wifi"></i><i class="bat"><b></b></i></span></div><div class="ph-hd"><span class="ph-av">SL</span><span class="col"><span>Swift Lane</span><span style="font:400 11px Figtree;opacity:.75">WhatsApp</span></span></div><div class="ph-bd"><span class="ph-day">Today</span><?php if (sl_get($d, 'hasAttach')): ?><div class="bub"><span class="doc"><i class="ic ic-sm i-file" aria-hidden="true"></i><?= sl_v($d, 'attach') ?></span><span class="tm">09:41</span></div><?php endif; ?><div class="bub"><span class="hd"><?= sl_v($d, 'subject') ?></span><?= sl_v($d, 'msgShown') ?><span class="tm">09:41 ✓✓</span></div></div></div></div><?php endif; ?>
<?php if (sl_get($d, 'chSms')): ?><div class="ph sms"><div class="ph-sc"><div class="ph-st"><span>9:41</span><span class="ph-ic" aria-hidden="true"><i class="sig"><b></b><b></b><b></b><b></b></i><i class="wifi"></i><i class="bat"><b></b></i></span></div><div class="ph-hd"><span class="ph-av">SL</span><span>SWIFTLANE</span></div><div class="ph-bd"><span class="ph-day">Text Message · Today</span><div class="bub"><?= sl_v($d, 'smsShown') ?></div></div></div></div><?php endif; ?>
<?php if (sl_get($d, 'chMail')): ?><div class="ph em"><div class="ph-sc"><div class="ph-st"><span>9:41</span><span class="ph-ic" aria-hidden="true"><i class="sig"><b></b><b></b><b></b><b></b></i><i class="wifi"></i><i class="bat"><b></b></i></span></div><div class="ph-hd"><span class="ph-av">SL</span><span class="col"><span>Swift Lane</span><span style="font:400 11px Figtree;color:#777">to kwame@gmail.com</span></span></div><div class="ph-bd"><div class="card"><b class="s"><?= sl_v($d, 'subject') ?></b><?= sl_v($d, 'msgShown') ?><span class="cta">Open my account</span></div></div></div></div><?php endif; ?>
<?php if (sl_get($d, 'noCh')): ?><span class="mut">Pick at least one channel.</span><?php endif; ?>
</div></aside></div>
