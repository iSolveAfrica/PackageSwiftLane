<?php
/**
 * NotificationCenter - from docs/handover/artifact-source/NotificationCenter.dc.html.
 * Data: views/sl/data/notification_center.php (sl_notificationCenterData).
 */
if (!isset($d)) { $d = []; }
$hl = $d['health'];
$ajax = 'ajax/sl/notification_rules_ajax.php';
?>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:20px">Notification Center</h1><p class="sl-lede">Every message the system sends, by e-mail, SMS and WhatsApp, in one place you can edit</p></div><div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('EmailBuilder') ?>"><i class="ic ic-sm i-mail" aria-hidden="true"></i>E-mail builder</a><a class="sl-btn ghost" href="<?= sl_route('MessageTemplates') ?>"><i class="ic ic-sm i-message" aria-hidden="true"></i>SMS &amp; WhatsApp</a><a class="sl-btn ghost" href="<?= sl_route('Broadcast') ?>"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send to customers</a><a class="sl-btn ghost" href="<?= sl_route('MessageCosts') ?>"><i class="ic ic-sm i-dollar" aria-hidden="true"></i>Message costs</a><a class="sl-btn ghost" href="<?= sl_route('MessageLogs') ?>"><i class="ic ic-sm i-clock" aria-hidden="true"></i>Message logs</a><?php if ($d['canEdit']): ?><button class="sl-btn" type="submit" form="nc-form"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= h($d['saveLabel']) ?></button><?php endif; ?></div></div>
<?php if (!$d['ready']): ?><div class="sl-banner warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i>The per-channel switches need the database upgrade <b>2026_11_06_messaging_settings</b>. Until then every channel behaves as it does today.</div><?php endif; ?>
<div class="grid cols3">
  <div class="ov-card"><div class="hd"><span><i class="ic ic-sm i-mail" aria-hidden="true"></i><?= h($hl['email']['label']) ?></span><span class="ov-pill <?= h($hl['email']['pill']) ?>"><?= h($hl['email']['state']) ?></span></div><div class="fig"><?= (int) $d['onEmail'] ?> <i>/ <?= (int) $d['total'] ?> notifications on</i></div><div class="sl-ticks" role="img" aria-label="<?= (int) $d['onEmail'] ?> on"><i style="<?= h($d['wEmail']) ?>"></i></div><div class="ft"><?= sl_num($hl['email']['month']['sent']) ?> sent this month · <?= (int) $hl['email']['month']['rate'] ?>% delivered</div></div>
  <div class="ov-card"><div class="hd"><span><i class="ic ic-sm i-phone" aria-hidden="true"></i><?= h($hl['sms']['label']) ?></span><span class="ov-pill <?= h($hl['sms']['pill']) ?>"><?= h($hl['sms']['state']) ?></span></div><div class="fig"><?= (int) $d['onSms'] ?> <i>/ <?= (int) $d['total'] ?> notifications on</i></div><div class="sl-ticks y" role="img" aria-label="<?= (int) $d['onSms'] ?> on"><i style="<?= h($d['wSms']) ?>"></i></div><div class="ft"><?= sl_num($hl['sms']['month']['sent']) ?> sent this month · <?= (int) $hl['sms']['month']['rate'] ?>% delivered</div></div>
  <div class="ov-card"><div class="hd"><span><i class="ic ic-sm i-message" aria-hidden="true"></i><?= h($hl['whatsapp']['label']) ?></span><span class="ov-pill <?= h($hl['whatsapp']['pill']) ?>"><?= h($hl['whatsapp']['state']) ?></span></div><div class="fig"><?= (int) $d['onWa'] ?> <i>/ <?= (int) $d['total'] ?> notifications on</i></div><div class="sl-ticks" role="img" aria-label="<?= (int) $d['onWa'] ?> on"><i style="<?= h($d['wWa']) ?>"></i></div><div class="ft"><?= sl_num($hl['whatsapp']['month']['sent']) ?> sent this month · <?= (int) $hl['whatsapp']['month']['rate'] ?>% delivered</div></div>
</div>

<div class="nc-wrap">
  <section class="sl-card flush nc-list">
    <form method="get" style="padding:14px 16px;display:flex;flex-direction:column;gap:10px;border-bottom:1px solid var(--line)">
      <input type="hidden" name="event" value="<?= h($d['cur']['key']) ?>"><input type="hidden" name="channel" value="<?= h($d['channel']) ?>">
      <div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" aria-label="Search notifications" placeholder="Search <?= (int) $d['total'] ?> notifications" value="<?= h($d['q']) ?>"></div>
      <div class="nc-head"><span>Notification</span><span title="E-mail"><i class="ic ic-sm i-mail" aria-hidden="true"></i></span><span title="SMS"><i class="ic ic-sm i-phone" aria-hidden="true"></i></span><span title="WhatsApp"><i class="ic ic-sm i-message" aria-hidden="true"></i></span></div>
    </form>
    <div class="nc-scroll">
      <?php foreach ($d['groups'] as $g): ?>
        <div class="nc-gl"><?= h($g['label']) ?> <span><?= (int) $g['count'] ?></span></div>
        <?php foreach ($g['items'] as $e): ?>
          <div class="nc-ev <?= h($e['cls']) ?>">
            <a class="nc-name" href="<?= h($e['href']) ?>"><b><?= h($e['name']) ?></b><small><?= h($e['when']) ?></small></a>
            <?php foreach (['em' => 'email', 'sm' => 'sms', 'wa' => 'whatsapp'] as $short => $ch): ?>
            <button type="button" class="nc-t <?= h($e[$short]) ?>" aria-label="<?= h(ucfirst($ch)) ?> for <?= h($e['name']) ?>" aria-pressed="<?= h($e[$short . 'P']) ?>" data-nc-toggle data-event="<?= h($e['key']) ?>" data-channel="<?= $ch ?>" <?= $d['canEdit'] ? '' : 'disabled' ?>></button>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <?php if (!$d['groups']): ?><div class="sl-empty" style="padding:24px">No notification matches “<?= h($d['q']) ?>”.</div><?php endif; ?>
    </div>
  </section>

  <section class="sl-card col g16 nc-ed">
    <div class="row between wrap g12">
      <div><div class="up"><?= h($d['cur']['group']) ?> · sent <?= h($d['cur']['when']) ?></div><h2 style="font-size:20px;font-weight:600;letter-spacing:-.01em;margin-top:4px"><?= h($d['cur']['name']) ?></h2><p class="mut sm" style="margin-top:2px"><?= h($d['cur']['desc']) ?></p></div>
      <div class="row g8"><span class="ov-pill"><?= h($d['cur']['audience']) ?></span><span class="ov-pill"><?= h($d['cur']['sent']) ?> sent this month</span></div>
    </div>
    <div class="nc-tabs" role="tablist">
      <?php foreach ($d['tabs'] as $t): ?><button type="button" role="tab" class="<?= h($t['cls']) ?>" aria-selected="<?= h($t['sel']) ?>" onclick="location.href='<?= h($t['href']) ?>'"><i class="ic ic-sm <?= h($t['icon']) ?>" aria-hidden="true"></i><?= h($t['label']) ?><span class="nc-sw <?= h($t['on']) ?>" aria-hidden="true"></span></button><?php endforeach; ?>
    </div>
    <form class="nc-edit" id="nc-form" data-sl-form action="<?= $ajax ?>" method="post">
      <input type="hidden" name="op" value="save"><input type="hidden" name="event" value="<?= h($d['cur']['key']) ?>"><input type="hidden" name="channel" value="<?= h($d['channel']) ?>">
      <div class="col g12" style="flex:1 1 360px;min-width:0">
        <div class="sl-field"><label class="sl-lab" for="nc-tpl">Template used</label><label class="sl-sel native"><select id="nc-tpl" name="template_id" onchange="location.href='<?= h(sl_url(['event' => $d['cur']['key'], 'channel' => $d['channel']])) ?>&tpl='+this.value"><option value="0">— none linked —</option><?php foreach ($d['templates'] as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === (int) $d['templateId'] ? 'selected' : '' ?>>#<?= (int) $t['id'] ?> · <?= h($t['name']) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label></div>
        <?php if ($d['isEmail']): ?>
          <div class="sl-field"><label class="sl-lab" for="nc-subj">Subject</label><input id="nc-subj" name="subject" class="sl-in" value="<?= h($d['subject']) ?>"></div>
        <?php endif; ?>
        <div class="sl-field"><label class="sl-lab" for="nc-body">Message<?= $d['bodyIsHtml'] ? ' (HTML)' : '' ?></label><textarea id="nc-body" name="body" class="sl-in nc-ta" <?= $d['canEdit'] ? '' : 'readonly' ?>><?= h($d['body']) ?></textarea>
          <div class="row between sm mut"><span data-nc-counter><?= h($d['counter']) ?></span><span><?= h($d['channelNote']) ?></span></div></div>
        <div><div class="up" style="margin-bottom:8px">Insert a field</div><div class="row g6 wrap"><?php foreach ($d['vars'] as $v): ?><button type="button" class="nc-var" data-nc-var="<?= h($v['label']) ?>"><?= h($v['label']) ?></button><?php endforeach; ?></div></div>
        <?php if ($d['canEdit']): ?><div class="row g8 wrap" style="margin-top:4px"><button class="sl-btn" type="submit"><?= h($d['saveLabel']) ?></button><button type="button" class="sl-btn ghost" data-nc-test><i class="ic ic-sm i-send" aria-hidden="true"></i>Send test to me</button><button type="button" class="sl-btn ghost" onclick="location.reload()">Discard edits</button></div><?php endif; ?>
      </div>
      <div class="nc-prev">
        <div class="up" style="margin-bottom:10px">Preview · <?= h($d['tabLabel']) ?></div>
        <?php if ($d['isEmail']): ?>
          <div class="nc-mail"><div class="nc-mh"><span class="sl-av sm dark"><?= h(sl_initials($core->site_name ?? 'SL')) ?></span><div class="grow"><b class="sm"><?= h($core->site_name ?? 'Swift Lane') ?></b><div class="mut" style="font-size:12px">to <?= h($d['sample']['email']) ?></div></div></div><b style="font-size:15px" data-nc-prev-subject><?= h($d['subjectPrev']) ?></b><p style="white-space:pre-line;font-size:14px;line-height:1.55" data-nc-prev-body><?= h($d['bodyPrev']) ?></p><span class="sl-btn sm" style="align-self:flex-start">Track parcel</span></div>
        <?php else: ?>
          <div class="nc-phone"><div class="nc-ph"><span class="sl-av sm dark"><?= h(sl_initials($core->site_name ?? 'SL')) ?></span><b class="sm"><?= h($core->site_name ?? 'Swift Lane') ?></b></div><div class="nc-bubble <?= h($d['bubbleCls']) ?>" style="white-space:pre-line"><span data-nc-prev-body><?= h($d['bodyPrev']) ?></span><small><?= date('H:i') ?></small></div></div>
        <?php endif; ?>
        <p class="mut sm" style="margin-top:10px">Sample customer: <?= h($d['sample']['name']) ?> · <?= h($d['sample']['locker']) ?> · code 482193</p>
      </div>
    </form>
  </section>
</div>
<script>
(function(){
  var sample = <?= json_encode($d['sample']) ?>, site = <?= json_encode($core->site_name ?? 'Swift Lane') ?>;
  function fill(t){ return t.replace(/\[USERNAME\]|\[NAME\]/g, sample.name).replace(/\[FIRST_NAME\]/g, sample.first).replace(/\[SITE_NAME\]/g, site).replace(/\[TRACKING_NUMBER\]|\[TRACKING\]/g, 'SL-100480').replace(/\[STATUS\]/g, 'Ready for pickup').replace(/\[LOCKER\]/g, sample.locker).replace(/\[URL_SHIP\]|\[URL\]/g, location.origin).replace(/\[MESSAGE\]/g, 'Your parcel is ready.').replace(/\[CODE\]|\[PICKUP_CODE\]/g, '482193'); }
  var body = document.getElementById('nc-body'), subj = document.getElementById('nc-subj'), isSms = <?= json_encode($d['channel'] === 'sms') ?>;
  function refresh(){
    var pb = document.querySelector('[data-nc-prev-body]'); if (pb) pb.textContent = fill(body.value.replace(/<br\s*\/?>/gi, '\n').replace(/<[^>]+>/g, ''));
    var ps = document.querySelector('[data-nc-prev-subject]'); if (ps && subj) ps.textContent = fill(subj.value);
    var c = document.querySelector('[data-nc-counter]'), n = body.value.length;
    if (c) c.textContent = isSms ? n + ' characters · ' + Math.max(1, Math.ceil(n / 153)) + ' SMS part' + (n > 153 ? 's' : '') : n + ' characters';
  }
  body.addEventListener('input', refresh); if (subj) subj.addEventListener('input', refresh);
  document.addEventListener('click', function(e){
    var v = e.target.closest('[data-nc-var]'); if (v) { var s = body.selectionStart || body.value.length; body.value = body.value.slice(0, s) + v.getAttribute('data-nc-var') + body.value.slice(s); refresh(); body.focus(); return; }
    var t = e.target.closest('[data-nc-toggle]'); if (t) { var on = !t.classList.contains('on'); t.classList.toggle('on', on); t.classList.toggle('off', !on); t.setAttribute('aria-pressed', on ? 'true' : 'false');
      SL.post('<?= $ajax ?>', { op: 'toggle', event: t.getAttribute('data-event'), channel: t.getAttribute('data-channel'), value: on ? 1 : 0 }).then(function(j){ SL.toast(j.message || (j.success ? 'Saved' : 'Not saved'), !j.success); if (!j.success) { t.classList.toggle('on', !on); t.classList.toggle('off', on); } }); return; }
    var x = e.target.closest('[data-nc-test]'); if (x) { x.classList.add('loading'); SL.post('<?= $ajax ?>', { op: 'test', event: <?= json_encode($d['cur']['key']) ?>, channel: <?= json_encode($d['channel']) ?>, subject: subj ? subj.value : '', body: body.value }).then(function(j){ SL.toast(j.message || '', !j.success); }).finally(function(){ x.classList.remove('loading'); }); }
  });
})();
</script>
