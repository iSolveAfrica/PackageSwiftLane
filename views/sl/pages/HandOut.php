<?php
/**
 * HandOut - generated from docs/handover/artifact-source/HandOut.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  steps                        as $s
 *   loop  queue                        as $q
 *   loop  boxes                        as $b
 *   loop  keys                         as $k
 *   loop  pkgs                         as $p
 *   if    stCode
 *   if    codeBad
 *   if    stScan
 *   if    scanBad
 *   if    stDone
 *   value boxes, codeBad, codeCls, codeHint, confLabel, confOp, confirm, doneMsg, doneToday, false, fill, flipId, flipPay, flipSig, idCls, keys, next, onScan, payCls, payTxt, pkN, pkgs, qN, queue, resend, resendLabel, reset, scanBad, scanLive, scanN, scanVal, sigCls, stCode, stDone, stScan, steps, triesLeft, true
 * sl-wrap extras in the design: style="max-width:1400px"
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:22px">Hand out packages</h1><p class="sl-lede">Counter at Accra Hub · enter the pickup code, fetch from the bay, scan each package to confirm, hand over.</p></div><div class="row g8 wrap"><span class="sl-badge row g6"><span class="pr-dot" style="width:8px;height:8px;border-radius:50%;background:var(--green);display:inline-block"></span><?= sl_v($d, 'doneToday') ?> handed out today</span><a class="sl-btn ghost" href="<?= sl_route('WarehousePickups') ?>"><i class="ic ic-sm i-clock" aria-hidden="true"></i>Today's hand-outs</a></div></div>
<div class="ho-steps"><?php foreach ((array) sl_get($d, 'steps', []) as $__i => $s): ?><span class="<?= sl_v($s, 'cls') ?>"><b><?= sl_v($s, 'n') ?></b><?= sl_v($s, 't') ?></span><?php endforeach; ?></div>
<div class="ho">
<aside class="sl-card col g8" style="padding:14px"><div class="row between"><b class="fw5">Waiting at the counter</b><span class="sl-badge"><?= sl_v($d, 'qN') ?></span></div><span class="mut" style="font-size:12px">Checked in by WhatsApp or at the door</span>
<?php foreach ((array) sl_get($d, 'queue', []) as $__i => $q): ?><button class="ho-q <?= sl_v($q, 'cls') ?>" data-sl-on="click:pick"><span class="me-pic <?= sl_v($q, 'pic') ?>" style="width:38px;height:38px"></span><span class="col grow" style="min-width:0"><b class="fw5 sm"><?= sl_v($q, 'name') ?></b><span class="mut" style="font-size:12px"><?= sl_v($q, 'lock') ?> · <?= sl_v($q, 'n') ?> · <?= sl_v($q, 'wait') ?></span></span><span class="sl-pill <?= sl_v($q, 'pc') ?>"><?= sl_v($q, 'st') ?></span></button><?php endforeach; ?>
<div class="sl-search" style="max-width:none;min-width:0;margin-top:6px"><i class="ic ic-sm i-search" aria-hidden="true"></i><input aria-label="Find customer" placeholder="Name, locker or phone"></div></aside>
<section class="ho-big">
<?php if (sl_get($d, 'stCode')): ?><div class="col g6"><h2 style="margin:0;font-size:22px;font-weight:600">Ask for the pickup code</h2><span class="mut"><?= sl_v($d, 'codeHint') ?></span></div>
<div class="ho-code <?= sl_v($d, 'codeCls') ?>"><?php foreach ((array) sl_get($d, 'boxes', []) as $__i => $b): ?><span class="<?= sl_v($b, 'cls') ?>"><?= sl_v($b, 'd') ?></span><?php endforeach; ?></div>
<?php if (sl_get($d, 'codeBad')): ?><div class="ho-bad"><i class="ic i-x" aria-hidden="true"></i><span>That code doesn't match any ready package. <?= sl_v($d, 'triesLeft') ?> tries left before it's voided.</span></div><?php endif; ?>
<div class="row g16 wrap" style="align-items:flex-start"><div class="ho-pad"><?php foreach ((array) sl_get($d, 'keys', []) as $__i => $k): ?><button aria-label="<?= sl_v($k, 'l') ?>" data-sl-on="click:press"><?= sl_v($k, 'l') ?></button><?php endforeach; ?></div><div class="col g8" style="max-width:300px"><span class="mut sm">No code? Check their Ghana card and send a new code by WhatsApp.</span><button class="sl-btn ghost" data-sl-on="click:resend"><i class="ic ic-sm i-message" aria-hidden="true"></i><?= sl_v($d, 'resendLabel') ?></button><button class="sl-btn ghost" data-sl-on="click:fill"><i class="ic ic-sm i-key" aria-hidden="true"></i>Customer shows code on phone</button></div></div><?php endif; ?>
<?php if (sl_get($d, 'stScan')): ?><div class="ho-cust"><span class="me-pic <?= sl_v($d, 'c.pic') ?>" style="width:56px;height:56px"></span><span class="col grow"><b class="fw5" style="font-size:18px"><?= sl_v($d, 'c.name') ?></b><span class="mut sm"><?= sl_v($d, 'c.lock') ?> · <?= sl_v($d, 'c.phone') ?> · code matched ✓</span></span><span class="sl-pill <?= sl_v($d, 'c.payCls') ?>"><?= sl_v($d, 'c.payL') ?></span></div>
<div class="row between"><b class="fw5">Fetch from the bay, then scan each package</b><span class="mono"><?= sl_v($d, 'scanN') ?> / <?= sl_v($d, 'pkN') ?> scanned</span></div>
<?php foreach ((array) sl_get($d, 'pkgs', []) as $__i => $p): ?><div class="ho-pk <?= sl_v($p, 'cls') ?>"><span class="ho-bay"><small>BAY</small><b><?= sl_v($p, 'bay') ?></b></span><span class="col" style="min-width:0"><b class="fw5"><?= sl_v($p, 'id') ?></b><span class="mut sm"><?= sl_v($p, 'what') ?> · <?= sl_v($p, 'w') ?> · from <?= sl_v($p, 'from') ?></span></span><span class="mono sm"><?= sl_v($p, 'pcs') ?></span><button class="sl-btn <?= sl_v($p, 'bCls') ?>" data-sl-on="click:scan"><i class="ic ic-sm <?= sl_v($p, 'icon') ?>" aria-hidden="true"></i><?= sl_v($p, 'bl') ?></button></div><?php endforeach; ?>
<label class="ho-scan <?= sl_v($d, 'scanLive') ?>"><span class="ho-beam" aria-hidden="true"></span><input aria-label="Scan barcode" placeholder="Scan the label barcode or type the tracking number" value="<?= sl_v($d, 'scanVal') ?>" data-sl-on="change:onScan"><span class="mut sm">Scanner ready</span></label>
<?php if (sl_get($d, 'scanBad')): ?><div class="ho-bad"><i class="ic i-x" aria-hidden="true"></i><span><?= sl_v($d, 'scanBad') ?></span></div><?php endif; ?>
<div class="grid cols3" style="gap:8px"><button class="ho-chk" data-sl-on="click:flipId"><span class="sl-chk <?= sl_v($d, 'idCls') ?>" aria-hidden="true"></span>Ghana card checked</button><button class="ho-chk" data-sl-on="click:flipPay"><span class="sl-chk <?= sl_v($d, 'payCls') ?>" aria-hidden="true"></span><?= sl_v($d, 'payTxt') ?></button><button class="ho-chk" data-sl-on="click:flipSig"><span class="sl-chk <?= sl_v($d, 'sigCls') ?>" aria-hidden="true"></span>Customer signed</button></div>
<button class="ho-pri" style="opacity:<?= sl_v($d, 'confOp') ?>" data-sl-on="click:confirm"><i class="ic i-check" aria-hidden="true"></i><?= sl_v($d, 'confLabel') ?></button>
<button class="sl-btn ghost" data-sl-on="click:reset">Cancel · back to code</button><?php endif; ?>
<?php if (sl_get($d, 'stDone')): ?><div class="ho-done"><span class="sl-tile" style="width:72px;height:72px;border-radius:22px;background:var(--green-bg);color:var(--green-ink)"><i class="ic i-check" aria-hidden="true"></i></span><h2 style="margin:0;font-size:26px;font-weight:600"><?= sl_v($d, 'doneMsg') ?></h2><span class="mut">Receipt sent to <?= sl_v($d, 'c.phone') ?> by WhatsApp · logged in Warehouse Pickups</span><button class="ho-pri" style="max-width:360px" data-sl-on="click:next"><i class="ic i-right" aria-hidden="true"></i>Next customer</button></div><?php endif; ?>
</section></div>
