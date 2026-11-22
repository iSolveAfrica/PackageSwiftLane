<?php
/**
 * NotifyStudio: every status and action, and what each sends by e-mail, SMS,
 * WhatsApp and in-app. Data: views/sl/data/notify_studio.php. Saves through
 * ajax/sl/notify_studio_ajax.php. Behaviour: dataJs/sl/notify_studio.js.
 */
if (!isset($d)) { $d = []; }
$cur = $d['cur'];
$ch = $d['channel'];
$chIcon = ['email' => 'i-mail', 'sms' => 'i-phone', 'whatsapp' => 'i-message', 'inapp' => 'i-bell'];
$stateLabel = ['builtin' => 'Built-in', 'custom' => 'Custom', 'off' => 'Off', 'on' => 'On', 'na' => 'Not sent'];
$ed = $cur ? $d['editor'][$ch] : null;
$isComms = $cur && $cur['kind'] === 'comms';
$canSave = $d['canEdit'] && (!$isComms || $d['canComms']);
?>
<div class="sl-head">
  <div>
    <h1 class="sl-title">Notification Studio</h1>
    <p class="sl-lede">What every status and action sends, by e-mail, SMS, WhatsApp and in-app. Nothing saved means the built-in message goes out as it does today.</p>
  </div>
  <div class="row g8 wrap nsd-sum">
    <span class="nsd-chip"><b><?= (int) $d['customCount'] ?></b> custom</span>
    <span class="nsd-chip"><b><?= (int) $d['offCount'] ?></b> switched off</span>
    <span class="nsd-chip <?= $d['commsOn'] ? 'ok' : '' ?>">Automations <?= $d['commsOn'] ? 'on' : 'off' ?></span>
  </div>
</div>

<div class="nsd <?= !empty($d['picked']) ? 'has-sel' : '' ?>" id="top">
  <section class="sl-card flush nsd-list" aria-label="Statuses and actions">
    <form method="get" class="nsd-search">
      <?php if ($cur): ?><input type="hidden" name="t" value="<?= h($cur['key']) ?>"><?php endif; ?>
      <div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" value="<?= h($d['q']) ?>" aria-label="Search statuses and actions" placeholder="Search <?= (int) $d['total'] ?> messages"></div>
    </form>
    <div class="nsd-legend" aria-hidden="true">
      <span></span><?php foreach ($d['channels'] as $k => $lbl): ?><span title="<?= h($lbl) ?>"><i class="ic ic-sm <?= $chIcon[$k] ?>"></i></span><?php endforeach; ?>
    </div>
    <div class="nsd-scroll">
      <?php foreach ($d['groups'] as $g): ?>
        <div class="nsd-gl"><span><?= h($g['label']) ?></span><small><?= h($g['sub']) ?> · <?= count($g['items']) ?></small></div>
        <?php foreach ($g['items'] as $it): $on = $cur && $it['key'] === $cur['key']; ?>
          <a class="nsd-item <?= $on ? 'on' : '' ?>" href="<?= h($it['href']) ?>"<?= $on ? ' aria-current="page"' : '' ?>>
            <span class="nsd-name"><?php if ($it['color'] !== ''): ?><i class="nsd-sw" style="background:<?= h($it['color']) ?>" aria-hidden="true"></i><?php endif; ?><?= h($it['label']) ?></span>
            <?php foreach ($it['states'] as $k => $st): ?><span class="nsd-dot <?= h($st) ?>" title="<?= h($d['channels'][$k] . ': ' . $stateLabel[$st]) ?>"><span class="nsd-vh"><?= h($d['channels'][$k] . ': ' . $stateLabel[$st]) ?></span></span><?php endforeach; ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <?php if (!$d['groups']): ?><div class="sl-empty" style="padding:24px">Nothing matches “<?= h($d['q']) ?>”.</div><?php endif; ?>
    </div>
    <div class="nsd-key"><span><i class="nsd-dot builtin"></i>Built-in</span><span><i class="nsd-dot custom"></i>Custom</span><span><i class="nsd-dot on"></i>On</span><span><i class="nsd-dot off"></i>Off</span></div>
  </section>

  <?php if ($cur): ?>
  <section class="sl-card nsd-ed" aria-labelledby="nsd-title">
    <div class="nsd-ed-head">
      <a class="nsd-back" href="<?= h(sl_url(['t' => null, 'ch' => null])) ?>#top"><i class="ic ic-sm i-left" aria-hidden="true"></i>All messages</a>
      <div class="up"><?= h(['status' => 'Status', 'action' => 'Action', 'comms' => 'Automation'][$cur['kind']]) ?></div>
      <h2 id="nsd-title" class="nsd-h"><?= h($cur['label']) ?></h2>
      <p class="mut sm"><?= h($cur['desc']) ?></p>
    </div>

    <div class="nsd-tabs" role="tablist" aria-label="Channel">
      <?php foreach ($d['channels'] as $k => $lbl): $e = $d['editor'][$k]; $st = !$e['supported'] ? 'na' : $e['mode']; ?>
        <?php if ($e['supported']): ?>
          <a role="tab" class="nsd-tab <?= $k === $ch ? 'on' : '' ?>" aria-selected="<?= $k === $ch ? 'true' : 'false' ?>" href="<?= h(sl_url(['t' => $cur['key'], 'ch' => $k])) ?>"><i class="ic ic-sm <?= $chIcon[$k] ?>" aria-hidden="true"></i><?= h($lbl) ?><span class="nsd-state <?= h($st) ?>"><?= h($stateLabel[$st]) ?></span></a>
        <?php else: ?>
          <span role="tab" class="nsd-tab na" aria-disabled="true" title="<?= h($cur['label']) ?> does not send <?= h($lbl) ?> messages"><i class="ic ic-sm <?= $chIcon[$k] ?>" aria-hidden="true"></i><?= h($lbl) ?><span class="nsd-state na">Not sent</span></span>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <form class="nsd-form" id="nsd-form" novalidate data-key="<?= h($cur['key']) ?>" data-channel="<?= h($ch) ?>" data-kind="<?= h($cur['kind']) ?>" data-can-save="<?= $canSave ? '1' : '0' ?>">
      <div class="nsd-mode">
        <label class="nsd-switch">
          <button type="button" class="sl-toggle <?= $ed['enabled'] ? 'on' : '' ?>" role="switch" aria-checked="<?= $ed['enabled'] ? 'true' : 'false' ?>" aria-labelledby="nsd-sw-l" data-nsd-enabled <?= $canSave ? '' : 'disabled' ?>></button>
          <span id="nsd-sw-l" class="fw5">Send <?= h($d['channels'][$ch]) ?></span>
        </label>
        <p class="nsd-modetext sm" data-nsd-modetext>
          <?php if ($isComms): ?>
            <?= $ed['enabled'] ? 'This automation sends the message below.' : 'Switched off: this automation sends nothing on ' . h($d['channels'][$ch]) . '.' ?>
          <?php elseif ($ed['mode'] === 'builtin'): ?>
            Today the built-in message is sent: <?= h($ed['builtin']) ?> Edit below and save to send your own.
          <?php elseif ($ed['mode'] === 'custom'): ?>
            Your message below is sent<?= $ed['updated'] !== '' ? ' (saved ' . h($ed['updated']) . ')' : '' ?>.
          <?php else: ?>
            Switched off: nothing is sent on <?= h($d['channels'][$ch]) ?> for this one.
          <?php endif; ?>
        </p>
      </div>

      <?php if ($ch === 'email'): ?>
        <label class="sl-field"><span class="sl-lab">Subject</span><input class="sl-in" name="subject" value="<?= h($ed['subject']) ?>" maxlength="190" data-nsd-field <?= $canSave ? '' : 'readonly' ?>></label>
        <div class="nsd-builder">
          <div class="nsd-blocks-col">
            <div class="row between wrap g8"><span class="sl-lab">E-mail blocks</span>
              <span class="nsd-add">
                <label class="sl-sel native nsd-add-sel"><select aria-label="Block to add" data-nsd-addtype><?php foreach ($d['blockTypes'] as $t => $bt): ?><option value="<?= h($t) ?>"><?= h($bt['label']) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label>
                <button type="button" class="sl-btn ghost sm" data-nsd-add <?= $canSave ? '' : 'disabled' ?>>Add block</button>
              </span>
            </div>
            <ol class="nsd-blocks" data-nsd-blocks aria-label="E-mail blocks, top to bottom"></ol>
          </div>
          <div class="nsd-prev-col">
            <div class="row between g8"><span class="sl-lab">Preview <span class="mut">(sample customer)</span></span><span class="nsd-seg nsd-wtoggle sl-seg boxed" role="group" aria-label="Preview width"><button type="button" class="on" data-nsd-w="desk">Desktop</button><button type="button" data-nsd-w="phone">Phone</button></span></div>
            <div class="nsd-mail"><div class="nsd-mail-subj"><span class="mut">Subject</span> <b data-nsd-psubj></b></div><iframe class="nsd-frame" data-nsd-frame title="E-mail preview" sandbox=""></iframe></div>
          </div>
        </div>
      <?php else: ?>
        <div class="nsd-text">
          <div class="nsd-text-col">
            <label class="sl-field"><span class="sl-lab"><?= $ch === 'inapp' ? 'Notification text' : 'Message' ?></span>
              <textarea class="sl-in nsd-ta" name="body" maxlength="<?= (int) $d['limits'][$ch] ?>" data-nsd-field <?= $canSave ? '' : 'readonly' ?>><?= h($ed['body']) ?></textarea>
            </label>
            <div class="row between sm mut"><span data-nsd-count></span><span><?= $ch === 'whatsapp' ? 'Use *stars* for bold' : ($ch === 'sms' ? 'Long texts are sent in several parts' : 'Shown in the bell menu') ?></span></div>
          </div>
          <div class="nsd-prev-col">
            <span class="sl-lab">Preview <span class="mut">(sample customer)</span></span>
            <div class="nsd-phone <?= h($ch) ?>"><div class="nsd-bubble" data-nsd-bubble></div></div>
          </div>
        </div>
      <?php endif; ?>

      <div class="nsd-vars">
        <div class="sl-lab">Insert a field <span class="mut">(click to add it where the cursor is)</span></div>
        <div class="row g6 wrap"><?php foreach ($d['vars'] as $v): if ($ch !== 'email' && substr($v['key'], -5) === '_html') continue; ?><button type="button" class="nsd-var" data-nsd-var="<?= h($v['key']) ?>" title="<?= h($v['desc']) ?>"><?= h('{' . $v['key'] . '}') ?></button><?php endforeach; ?></div>
      </div>

      <?php if ($canSave): ?>
      <div class="nsd-actions">
        <button type="submit" class="sl-btn" data-nsd-save>Save</button>
        <span class="nsd-dirty sm" data-nsd-dirty role="status" hidden>Unsaved changes</span>
        <?php if ($ch === 'email'): ?><button type="button" class="sl-btn ghost" data-nsd-test><i class="ic ic-sm i-send" aria-hidden="true"></i>Send test to me</button><?php endif; ?>
        <span class="grow"></span>
        <?php if ($isComms): ?>
          <button type="button" class="sl-btn ghost" data-nsd-reset>Restore default text</button>
        <?php elseif ($ed['mode'] !== 'builtin'): ?>
          <button type="button" class="sl-btn ghost" data-nsd-reset>Use built-in message</button>
        <?php endif; ?>
      </div>
      <?php elseif ($isComms && !$d['canComms']): ?>
        <p class="sl-banner warn sm">Only administrators change automation messages.</p>
      <?php else: ?>
        <p class="sl-banner warn sm">You can look at these messages. Changing them needs the notification settings permission.</p>
      <?php endif; ?>
    </form>
  </section>
  <script type="application/json" id="nsd-data"><?= json_encode([
      'key' => $cur['key'], 'channel' => $ch, 'kind' => $cur['kind'],
      'enabled' => (bool) $ed['enabled'], 'mode' => $ed['mode'], 'blocks' => $ch === 'email' ? array_values($ed['blocks']) : [],
      'types' => $d['blockTypes'], 'sample' => $d['sample'], 'limit' => $d['limits'][$ch], 'channelLabel' => $d['channels'][$ch],
  ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
  <?php else: ?>
  <section class="sl-card nsd-ed"><div class="sl-empty">No statuses or actions found.</div></section>
  <?php endif; ?>
</div>
