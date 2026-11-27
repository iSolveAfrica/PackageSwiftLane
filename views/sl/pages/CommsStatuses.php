<?php
/**
 * CommsStatuses - Communications > Status messages (comms_statuses.php).
 * Every status x channel (WhatsApp, SMS, e-mail, in-app) with its state:
 * Drafted (the message written for that status, helpers/notify_drafts.php),
 * Custom (saved here) or Off. Edit opens the shared editor drawer
 * (dataJs/sl/comms_editor.js) with the e-mail in the drag-and-drop canvas;
 * the in-app bell line has its own small editor. Write with King per channel,
 * or Redraft all. Supersedes the Notification Studio (notify_studio.php
 * redirects here) and reuses its engine.
 * Data: views/sl/data/comms_automations.php. Behaviour: dataJs/sl/comms_automations.js.
 * Saves: ajax/comms/automation_ajax.php. Styles: pages/CommsStatuses.css.
 */
if (!isset($d)) { $d = []; }
$canEdit = !empty($d['canEdit']);
$isAdmin = !empty($d['isAdmin']);
$ai = $d['ai'] ?? ['ok' => false, 'name' => 'King', 'message' => '', 'setup_url' => '', 'tones' => [], 'code' => ''];
$king = (string) $ai['name'];
$chs = ['whatsapp' => ['WhatsApp', 'i-wa'], 'sms' => ['SMS', 'i-message'], 'email' => ['E-mail', 'i-mail'], 'inapp' => ['In-app', 'i-bell']];
$stateLabel = ['drafted' => 'Drafted', 'custom' => 'Custom', 'off' => 'Off'];
$ajax = 'ajax/comms/automation_ajax.php';
$rows = (array) ($d['rows'] ?? []);
$cfg = ['page' => 'statuses', 'ajax' => $ajax, 'canEdit' => $canEdit, 'ai' => $ai, 'langs' => $d['langs'] ?? [], 'open' => (int) ($d['open'] ?? 0),
    'owner' => $d['owner'] ?? 'legacy',
    'statuses' => array_map(function ($r) { return ['id' => $r['id'], 'key' => $r['key'], 'label' => $r['label'], 'kind' => $r['kind_label']]; }, $rows)];
$legacyOwner = ($d['owner'] ?? 'legacy') !== 'comms';
?>
<div class="ops cs-page" id="ca-root">
<?php $commsTab = 'commsstatuses'; include __DIR__ . '/partials/comms_tabs.php'; ?>
<div class="sl-head">
  <div>
    <h1 class="sl-title">Status messages</h1>
    <p class="sl-lede">What a customer gets when their shipment or package moves to each status: one message written for every status, on WhatsApp, SMS, e-mail and in the notification bell. Edit any of them, or let <?= h($king) ?> rewrite them.</p>
  </div>
  <div class="row g8 wrap">
    <?php if ($canEdit && $rows): ?><button type="button" class="sl-btn ghost" data-cs-redraft><span class="ca-k" aria-hidden="true">&#10022;</span>Redraft all with <?= h($king) ?></button><?php endif; ?>
    <a class="sl-btn ghost" href="<?= h($d['automationsUrl'] ?? 'comms_automations.php') ?>"><i class="ic ic-sm i-sliders" aria-hidden="true"></i>All automations</a>
  </div>
</div>

<?php if (empty($d['ready'])): ?>
<div class="sl-banner bad">The message tables could not be created. The database user needs the CREATE privilege once.</div>
<?php else: ?>

<?php if ($legacyOwner): ?>
<div class="sl-banner info cs-owner"><i class="ic ic-sm i-info" aria-hidden="true"></i><span><b class="fw5">Status messages are sent today by the old status screens</b>, with the texts on this page. Switching a channel off here stops it there too. To have the Automation Center send them (with quiet hours, retries and the Outbox), switch over in <a href="<?= h($d['settingsUrl'] ?? 'comms_settings.php') ?>">Communications settings</a>.</span></div>
<?php elseif (empty($d['master'])): ?>
<div class="sl-banner cs-owner"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>Status messages belong to the Automation Center, but <b class="fw5">automations are switched off</b>, so none are sent. Switch them on in <a href="<?= h($d['automationsUrl'] ?? 'comms_automations.php') ?>">Automations</a>.</span></div>
<?php endif; ?>

<section class="sl-card cs-strip" aria-label="Status message settings">
  <div class="ca-master">
    <button type="button" class="sl-toggle <?= $d['draftsOn'] ? 'on' : '' ?>" role="switch" aria-checked="<?= $d['draftsOn'] ? 'true' : 'false' ?>" aria-labelledby="cs-dr-l" aria-describedby="cs-dr-t"
      <?= $isAdmin ? 'data-sl-save="' . h($ajax) . '" data-p-action="drafts"' : 'data-static' ?>></button>
    <div class="col"><b class="fw5" id="cs-dr-l">Predefined messages</b>
      <span class="mut sm" id="cs-dr-t">On: every status without your own text sends the message written for it. Off: those statuses send one shared message.<?= $isAdmin ? '' : ' Only an administrator changes this.' ?></span></div>
  </div>
  <div class="cs-any">
    <div class="col"><b class="fw5">Any other status</b><span class="mut sm">A status with no channel of its own uses these<?= $legacyOwner ? ' (once the Automation Center sends status messages)' : '' ?>.</span></div>
    <div class="row g12 wrap">
      <?php foreach (['whatsapp', 'sms', 'email'] as $c): $on = !empty($d['any'][$c]); ?>
      <label class="cs-anysw"><button type="button" class="sl-toggle <?= $on ? 'on' : '' ?>" role="switch" aria-checked="<?= $on ? 'true' : 'false' ?>" aria-label="Any other status: <?= h($chs[$c][0]) ?>"
        data-key="status:*" data-p-channel="<?= h($c) ?>" <?= $canEdit ? 'data-sl-save="' . h($ajax) . '" data-p-action="save_rule"' : 'data-static' ?>></button><span class="sm"><?= h($chs[$c][0]) ?></span></label>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="cs-counts">
    <span class="cs-chip drafted"><b data-cs-count="drafted"><?= (int) $d['counts']['drafted'] ?></b> drafted</span>
    <span class="cs-chip custom"><b data-cs-count="custom"><?= (int) $d['counts']['custom'] ?></b> custom</span>
    <span class="cs-chip off"><b data-cs-count="off"><?= (int) $d['counts']['off'] ?></b> off</span>
  </div>
</section>

<div class="ca-bar">
  <div class="sl-search ca-q"><i class="ic ic-sm i-search" aria-hidden="true"></i><input type="search" data-ca-q aria-label="Search statuses" placeholder="Search <?= count($rows) ?> statuses" autocomplete="off"></div>
  <div class="sl-seg boxed ca-seg" role="group" aria-label="Show">
    <button type="button" data-ca-f="all" class="on" aria-pressed="true">All</button>
    <button type="button" data-ca-f="custom" aria-pressed="false">Custom</button>
    <button type="button" data-ca-f="off" aria-pressed="false">Has a channel off</button>
    <button type="button" data-ca-f="urgent" aria-pressed="false">Urgent</button>
  </div>
  <span class="cs-key sm" aria-hidden="true"><span class="cs-st drafted">Drafted</span><span class="cs-st custom">Custom</span><span class="cs-st off">Off</span></span>
</div>

<section class="sl-card flush ca-group cs-grid" aria-label="Statuses and channels">
  <div class="ca-tw">
  <table class="ca-table">
    <colgroup><col><col class="ca-c-cs"><col class="ca-c-cs"><col class="ca-c-cs"><col class="ca-c-cs"><col class="ca-c-n"><col class="ca-c-a2"></colgroup>
    <thead><tr><th scope="col">Status</th><?php foreach ($chs as $c => $cl): ?><th scope="col" class="ca-chh"><i class="ic ic-sm <?= $cl[1] ?>" aria-hidden="true"></i><?= h($cl[0]) ?></th><?php endforeach; ?><th scope="col" class="ca-num">This month</th><th scope="col"><span class="ca-vh">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
        $f = [];
        foreach ($r['cells'] as $cell) { $f[$cell['state']] = 1; }
        if ($r['urgent']) { $f['urgent'] = 1; }
    ?>
      <tr data-ca-row data-key="<?= h($r['key']) ?>" data-status="<?= (int) $r['id'] ?>" data-f="<?= h(implode(' ', array_keys($f))) ?>">
        <th scope="row" class="ca-name">
          <span class="ca-nt"><?php if ($r['color'] !== ''): ?><i class="cs-sw" style="background:<?= h($r['color']) ?>" aria-hidden="true"></i><?php endif; ?><?= h($r['label']) ?></span>
          <span class="ca-tags"><span class="mut sm"><?= h($r['kind_label']) ?></span><?php if ($r['urgent']): ?><span class="sl-badge warn" title="Sent even during quiet hours">Urgent</span><?php endif; ?><?php if ($r['label'] !== str_replace('_', ' ', $r['name'])): ?><span class="mut sm">· <?= h($r['name']) ?></span><?php endif; ?></span>
        </th>
        <?php foreach ($chs as $c => $cl): $cell = $r['cells'][$c]; ?>
        <td class="ca-cell cs-cell" data-label="<?= h($cl[0]) ?>" data-cell="<?= h($c) ?>">
          <span class="cs-cw">
            <button type="button" class="sl-toggle <?= $cell['on'] ? 'on' : '' ?>" role="switch" aria-checked="<?= $cell['on'] ? 'true' : 'false' ?>"
              aria-label="<?= h($cl[0] . ': ' . $r['label']) ?>" data-key="<?= h($r['key']) ?>" data-p-channel="<?= h($c) ?>"
              <?= $canEdit ? 'data-sl-save="' . h($ajax) . '" data-p-action="save_rule"' : 'data-static' ?>></button>
            <button type="button" class="cs-st <?= h($cell['state']) ?>" data-cs-open="<?= h($c) ?>" title="<?= h($cl[0]) ?>: <?= h($stateLabel[$cell['state']]) ?><?= $cell['via'] === 'any' ? ' (via Any other status)' : '' ?>. Open the <?= h($cl[0]) ?> editor"><?= h($stateLabel[$cell['state']]) ?><?= $cell['via'] === 'any' ? '*' : '' ?></button>
          </span>
        </td>
        <?php endforeach; ?>
        <td class="ca-num" data-label="This month"><?= number_format((int) $r['sent']) ?></td>
        <td class="ca-act"><button type="button" class="sl-btn ghost sm" data-cs-edit><i class="ic ic-sm i-edit" aria-hidden="true"></i><?= $canEdit ? 'Edit' : 'View' ?></button></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <div class="sl-empty ca-none" data-ca-empty hidden>No status matches. <button type="button" class="sl-btn ghost sm" data-ca-clear>Show all</button></div>
</section>
<p class="mut sm ca-foot">* on via "Any other status". The in-app line shows in the notification bell, which staff also see, so it never carries the pickup code. Statuses are matched by name; the predefined text comes from the status message library.</p>
<?php endif; ?>
</div>

<div class="sl-dialog" id="cs-inapp" hidden><div class="sl-modal col g12 cs-inapp" role="dialog" aria-labelledby="cs-inapp-t">
  <div class="row between g8"><div class="col"><h2 class="sl-ct" id="cs-inapp-t">In-app line</h2><span class="mut sm" data-inapp-sub></span></div><button type="button" class="sl-ib" data-sl-close aria-label="Close"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>
  <label class="ce-switch"><input type="checkbox" data-inapp-on checked><span class="ce-sw" aria-hidden="true"></span><span><b class="fw5">Show in the notification bell</b><small class="mut">One short line, at most 200 characters. Staff see the bell too: no pickup codes.</small></span></label>
  <label class="sl-field"><span class="sl-lab">Line</span><textarea class="sl-in cs-inapp-ta" rows="3" maxlength="200" data-inapp-body></textarea></label>
  <div class="row between g8 wrap"><div data-inapp-chips class="grow"></div><span class="mut sm" data-inapp-n></span></div>
  <div class="cs-bell" aria-label="Preview"><span class="cs-bell-ic" aria-hidden="true"><i class="ic ic-sm i-bell"></i></span><div class="col"><b class="sm fw5" data-inapp-pv></b><span class="mut sm">just now</span></div></div>
  <div data-inapp-king></div>
  <div class="row between g8 wrap"><span class="mut sm" data-inapp-st role="status" aria-live="polite"></span><span class="row g8">
    <?php if ($canEdit): ?><button type="button" class="sl-btn ghost" data-inapp-reset>Reset to predefined</button><?php endif; ?>
    <button type="button" class="sl-btn ghost" data-sl-close>Close</button>
    <?php if ($canEdit): ?><button type="button" class="sl-btn" data-inapp-save><i class="ic ic-sm i-check" aria-hidden="true"></i>Save</button><?php endif; ?></span></div>
</div></div>

<?php if ($canEdit): ?>
<div class="sl-dialog" id="cs-redraft" hidden><div class="sl-modal col g12 cs-rd" role="dialog" aria-labelledby="cs-rd-t">
  <div class="row between g8"><div class="col"><h2 class="sl-ct" id="cs-rd-t">Redraft all with <?= h($king) ?></h2><span class="mut sm"><?= h($king) ?> rewrites every status message you choose. You review each proposal; nothing is saved until you accept it.</span></div><button type="button" class="sl-ib" data-sl-close aria-label="Close"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>
  <?php if (!$ai['ok']): ?>
    <div class="sl-banner"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><?= h($ai['message'] ?: $king . ' is not set up yet.') ?><?php if ($ai['setup_url'] !== ''): ?> <a href="<?= h($ai['setup_url']) ?>">Open the AI centre</a><?php endif; ?></span></div>
  <?php else: ?>
  <div class="cs-rd-form col g12" data-rd-form>
    <fieldset class="cs-rd-ch"><legend class="sl-lab">Channels</legend>
      <?php foreach (['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'E-mail'] as $c => $l): ?><label class="row g6"><input type="checkbox" value="<?= h($c) ?>" data-rd-ch <?= $c !== 'email' ? 'checked' : '' ?>><span><?= h($l) ?></span></label><?php endforeach; ?>
    </fieldset>
    <fieldset class="cs-rd-ch"><legend class="sl-lab">Statuses</legend>
      <label class="row g6"><input type="radio" name="rd-which" value="all" data-rd-which checked><span>All <?= count($rows) ?></span></label>
      <label class="row g6"><input type="radio" name="rd-which" value="shown" data-rd-which><span>Only those shown in the list</span></label>
    </fieldset>
    <label class="sl-field"><span class="sl-lab">Tone</span><select class="sl-in" data-rd-tone><?php foreach ($ai['tones'] as $t): ?><option value="<?= h($t[0]) ?>"><?= h($t[1]) ?></option><?php endforeach; ?></select></label>
    <label class="sl-field"><span class="sl-lab">Instruction <span class="mut">(optional)</span></span><textarea class="sl-in" rows="2" maxlength="400" data-rd-ins placeholder="e.g. Mention that customers can reply to this WhatsApp number"></textarea></label>
  </div>
  <div class="cs-rd-prog" data-rd-prog hidden><div class="cs-rd-bar"><i data-rd-bar></i></div><span class="sm" data-rd-n role="status" aria-live="polite"></span></div>
  <ol class="cs-rd-list" data-rd-list hidden></ol>
  <?php endif; ?>
  <div class="row between g8 wrap"><span class="mut sm" data-rd-st></span><span class="row g8 wrap">
    <button type="button" class="sl-btn ghost" data-sl-close>Close</button>
    <?php if ($ai['ok']): ?><button type="button" class="sl-btn ghost" data-rd-stop hidden>Stop</button><button type="button" class="sl-btn ghost" data-rd-accept-all hidden><i class="ic ic-sm i-check" aria-hidden="true"></i>Accept all</button><button type="button" class="sl-btn" data-rd-go><span class="ca-k" aria-hidden="true">&#10022;</span>Start</button><?php endif; ?>
  </span></div>
</div></div>
<?php endif; ?>
<?php $ec = ['template' => true, 'tabs' => ['blocks', 'tpl', 'vars']]; include CDP_ROOT . '/views/sl/inc/email_canvas.php'; ?>
<script type="application/json" id="ca-cfg"><?= json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
