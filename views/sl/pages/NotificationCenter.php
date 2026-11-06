<?php
/**
 * NotificationCenter - generated from docs/handover/artifact-source/NotificationCenter.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  groups                       as $g
 *   loop  g.items                      as $e
 *   loop  tabs                         as $t
 *   loop  vars                         as $v
 *   if    isEmail
 *   if    isEmail
 *   if    isChat
 *   value body, bodyPrev, bubbleCls, channelNote, counter, false, groups, isChat, isEmail, onBody, onEmail, onQ, onSms, onSubject, onWa, q, reset, save, saveLabel, subject, subjectPrev, tabLabel, tabs, total, true, vars, wEmail, wSms, wWa
 * sl-wrap extras in the design: style="max-width:1120px"
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:20px">Notification Center</h1><p class="sl-lede">Every message the system sends, by e-mail, SMS and WhatsApp, in one place you can edit</p></div><div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('EmailBuilder') ?>"><i class="ic ic-sm i-mail" aria-hidden="true"></i>E-mail builder</a><a class="sl-btn ghost" href="<?= sl_route('MessageTemplates') ?>"><i class="ic ic-sm i-message" aria-hidden="true"></i>SMS &amp; WhatsApp</a><a class="sl-btn ghost" href="<?= sl_route('Broadcast') ?>"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send to customers</a><a class="sl-btn ghost" href="<?= sl_route('MessageCosts') ?>"><i class="ic ic-sm i-dollar" aria-hidden="true"></i>Message costs</a><a class="sl-btn ghost" href="<?= sl_route('MessageLogs') ?>"><i class="ic ic-sm i-clock" aria-hidden="true"></i>Message logs</a><button class="sl-btn" data-sl-on="click:save"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= sl_v($d, 'saveLabel') ?></button></div></div>

<div class="grid cols3">
  <div class="ov-card"><div class="hd"><span><i class="ic ic-sm i-mail" aria-hidden="true"></i>E-mail · SMTP</span><span class="ov-pill ok">Connected</span></div><div class="fig"><?= sl_v($d, 'onEmail') ?> <i>/ <?= sl_v($d, 'total') ?> notifications on</i></div><div class="sl-ticks" role="img" aria-label="<?= sl_v($d, 'onEmail') ?> on"><i style="<?= sl_v($d, 'wEmail') ?>"></i></div><div class="ft">23,100 sent this month · 99.2% delivered</div></div>
  <div class="ov-card"><div class="hd"><span><i class="ic ic-sm i-phone" aria-hidden="true"></i>SMS · Hubtel</span><span class="ov-pill warn">Degraded</span></div><div class="fig"><?= sl_v($d, 'onSms') ?> <i>/ <?= sl_v($d, 'total') ?> notifications on</i></div><div class="sl-ticks y" role="img" aria-label="<?= sl_v($d, 'onSms') ?> on"><i style="<?= sl_v($d, 'wSms') ?>"></i></div><div class="ft">SMS only for registered, ready and handed over</div></div>
  <div class="ov-card"><div class="hd"><span><i class="ic ic-sm i-message" aria-hidden="true"></i>WhatsApp API</span><span class="ov-pill ok">Connected</span></div><div class="fig"><?= sl_v($d, 'onWa') ?> <i>/ <?= sl_v($d, 'total') ?> notifications on</i></div><div class="sl-ticks" role="img" aria-label="<?= sl_v($d, 'onWa') ?> on"><i style="<?= sl_v($d, 'wWa') ?>"></i></div><div class="ft">19,320 sent this month · 97.8% read</div></div>
</div>

<div class="nc-wrap">
  <section class="sl-card flush nc-list">
    <div style="padding:14px 16px;display:flex;flex-direction:column;gap:10px;border-bottom:1px solid var(--line)">
      <div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input aria-label="Search notifications" placeholder="Search <?= sl_v($d, 'total') ?> notifications" value="<?= sl_v($d, 'q') ?>" data-sl-on="change:onQ"></div>
      <div class="nc-head"><span>Notification</span><span title="E-mail"><i class="ic ic-sm i-mail" aria-hidden="true"></i></span><span title="SMS"><i class="ic ic-sm i-phone" aria-hidden="true"></i></span><span title="WhatsApp"><i class="ic ic-sm i-message" aria-hidden="true"></i></span></div>
    </div>
    <div class="nc-scroll">
      <?php foreach ((array) sl_get($d, 'groups', []) as $__i => $g): ?>
        <div class="nc-gl"><?= sl_v($g, 'label') ?> <span><?= sl_v($g, 'count') ?></span></div>
        <?php foreach ((array) sl_get($g, 'items', []) as $__i => $e): ?>
          <div class="nc-ev <?= sl_v($e, 'cls') ?>">
            <button class="nc-name" data-sl-on="click:pick"><b><?= sl_v($e, 'name') ?></b><small><?= sl_v($e, 'when') ?></small></button>
            <button class="nc-t <?= sl_v($e, 'em') ?>" data-sl-on="click:tEm" aria-label="E-mail for <?= sl_v($e, 'name') ?>" aria-pressed="<?= sl_v($e, 'emP') ?>"></button>
            <button class="nc-t <?= sl_v($e, 'sm') ?>" data-sl-on="click:tSm" aria-label="SMS for <?= sl_v($e, 'name') ?>" aria-pressed="<?= sl_v($e, 'smP') ?>"></button>
            <button class="nc-t <?= sl_v($e, 'wa') ?>" data-sl-on="click:tWa" aria-label="WhatsApp for <?= sl_v($e, 'name') ?>" aria-pressed="<?= sl_v($e, 'waP') ?>"></button>
          </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="sl-card col g16 nc-ed">
    <div class="row between wrap g12">
      <div><div class="up"><?= sl_v($d, 'cur.group') ?> · sent <?= sl_v($d, 'cur.when') ?></div><h2 style="font-size:20px;font-weight:600;letter-spacing:-.01em;margin-top:4px"><?= sl_v($d, 'cur.name') ?></h2><p class="mut sm" style="margin-top:2px"><?= sl_v($d, 'cur.desc') ?></p></div>
      <div class="row g8"><span class="ov-pill"><?= sl_v($d, 'cur.audience') ?></span><span class="ov-pill"><?= sl_v($d, 'cur.sent') ?> sent this month</span></div>
    </div>
    <div class="nc-tabs" role="tablist">
      <?php foreach ((array) sl_get($d, 'tabs', []) as $__i => $t): ?><button role="tab" class="<?= sl_v($t, 'cls') ?>" aria-selected="<?= sl_v($t, 'sel') ?>" data-sl-on="click:pick"><i class="ic ic-sm <?= sl_v($t, 'icon') ?>" aria-hidden="true"></i><?= sl_v($t, 'label') ?><span class="nc-sw <?= sl_v($t, 'on') ?>" aria-hidden="true"></span></button><?php endforeach; ?>
    </div>
    <div class="nc-edit">
      <div class="col g12" style="flex:1 1 360px;min-width:0">
        <?php if (sl_get($d, 'isEmail')): ?>
          <div class="sl-field"><label class="sl-lab" for="nc-subj">Subject</label><input id="nc-subj" class="sl-in" value="<?= sl_v($d, 'subject') ?>" data-sl-on="change:onSubject"></div>
        <?php endif; ?>
        <div class="sl-field"><label class="sl-lab" for="nc-body">Message</label><textarea id="nc-body" class="sl-in nc-ta" data-sl-on="change:onBody" value="<?= sl_v($d, 'body') ?>"></textarea>
          <div class="row between sm mut"><span><?= sl_v($d, 'counter') ?></span><span><?= sl_v($d, 'channelNote') ?></span></div></div>
        <div><div class="up" style="margin-bottom:8px">Insert a field</div><div class="row g6 wrap"><?php foreach ((array) sl_get($d, 'vars', []) as $__i => $v): ?><button class="nc-var" data-sl-on="click:add"><?= sl_v($v, 'label') ?></button><?php endforeach; ?></div></div>
        <div class="row g8 wrap" style="margin-top:4px"><button class="sl-btn" data-sl-on="click:save"><?= sl_v($d, 'saveLabel') ?></button><button class="sl-btn ghost"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send test to me</button><button class="sl-btn ghost" data-sl-on="click:reset">Reset to default</button></div>
      </div>
      <div class="nc-prev">
        <div class="up" style="margin-bottom:10px">Preview · <?= sl_v($d, 'tabLabel') ?></div>
        <?php if (sl_get($d, 'isEmail')): ?>
          <div class="nc-mail"><div class="nc-mh"><span class="sl-av sm dark">SL</span><div class="grow"><b class="sm">Swift Lane</b><div class="mut" style="font-size:12px">to efua.owusu@[email]</div></div></div><b style="font-size:15px"><?= sl_v($d, 'subjectPrev') ?></b><p style="white-space:pre-line;font-size:14px;line-height:1.55"><?= sl_v($d, 'bodyPrev') ?></p><span class="sl-btn sm" style="align-self:flex-start">Track parcel</span></div>
        <?php endif; ?>
        <?php if (sl_get($d, 'isChat')): ?>
          <div class="nc-phone"><div class="nc-ph"><span class="sl-av sm dark">SL</span><b class="sm">Swift Lane</b></div><div class="nc-bubble <?= sl_v($d, 'bubbleCls') ?>" style="white-space:pre-line"><?= sl_v($d, 'bodyPrev') ?><small>09:41</small></div></div>
        <?php endif; ?>
        <p class="mut sm" style="margin-top:10px">Sample customer: Efua Owusu · SL-100480 · code 482193</p>
      </div>
    </div>
  </section>
</div>
