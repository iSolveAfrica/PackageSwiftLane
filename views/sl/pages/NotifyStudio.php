<?php
/**
 * NotifyStudio: every status and action, and what each sends by e-mail, SMS,
 * WhatsApp and in-app. Data: views/sl/data/notify_studio.php. Saves through
 * ajax/sl/notify_studio_ajax.php. Behaviour: dataJs/sl/notify_studio.js.
 */
if (!isset($d)) { $d = []; }
$cur = $d['cur'];
$ch = $d['channel'];
$ai = $d['ai'];
$king = $ai['name'];
$chIcon = ['email' => 'i-mail', 'sms' => 'i-phone', 'whatsapp' => 'i-message', 'inapp' => 'i-bell'];
$stateLabel = ['draft' => 'Drafted', 'builtin' => 'Built-in', 'custom' => 'Custom', 'off' => 'Off', 'on' => 'On', 'na' => 'Not sent'];
$stateTitle = ['draft' => 'Drafted for this status (sent)', 'builtin' => 'Old shared / built-in message', 'custom' => 'Custom (your saved message)', 'off' => 'Switched off', 'on' => 'On', 'na' => 'Not sent'];
$ed = $cur ? $d['editor'][$ch] : null;
$isComms = $cur && $cur['kind'] === 'comms';
$isStatus = $cur && $cur['kind'] === 'status';
$canSave = $d['canEdit'] && (!$isComms || $d['canComms']);
$chName = $d['channels'][$ch] ?? '';
?>
<div class="sl-head">
  <div>
    <h1 class="sl-title">Notification Studio</h1>
    <p class="sl-lede">What every status and action sends, by e-mail, SMS, WhatsApp and in-app. Each status has its own message, written for that status; save a channel to send your own instead.</p>
  </div>
  <div class="row g8 wrap nsd-sum">
    <span class="nsd-chip" title="Status channels that send the message drafted for their status"><b><?= (int) $d['draftedCount'] ?></b> drafted</span>
    <span class="nsd-chip"><b><?= (int) $d['customCount'] ?></b> custom</span>
    <span class="nsd-chip"><b><?= (int) $d['offCount'] ?></b> switched off</span>
    <span class="nsd-chip <?= $d['commsOn'] ? 'ok' : '' ?>">Automations <?= $d['commsOn'] ? 'on' : 'off' ?></span>
    <?php if ($d['warnCount']): ?><span class="nsd-chip warn" title="Statuses with a possible double message or an odd Communication Center match"><i class="ic ic-sm i-alert" aria-hidden="true"></i><b><?= (int) $d['warnCount'] ?></b> to check</span><?php endif; ?>
  </div>
</div>

<section class="nsd-master <?= $d['draftsOn'] ? 'on' : '' ?>" aria-labelledby="nsd-master-l">
  <label class="nsd-switch">
    <button type="button" class="sl-toggle <?= $d['draftsOn'] ? 'on' : '' ?>" role="switch" aria-checked="<?= $d['draftsOn'] ? 'true' : 'false' ?>" aria-labelledby="nsd-master-l" aria-describedby="nsd-master-t" data-nsd-drafts <?= !empty($d['canGlobal']) ? '' : 'data-static aria-disabled="true"' ?>></button>
    <span id="nsd-master-l" class="fw5">Status-specific messages: <span data-nsd-master-state><?= $d['draftsOn'] ? 'On' : 'Off' ?></span></span>
  </label>
  <p id="nsd-master-t" class="sm nsd-master-t"><?= $d['draftsOn']
      ? 'Every status sends the message written for it (for example "Accepted by Airline" talks about the airline) unless you saved your own. Turn off to go back to the old shared messages.'
      : 'Off: statuses without a saved message send the old shared message, the same for every status. Turn on to send the message drafted for each status.' ?>
    <?php if (empty($d['canGlobal'])): ?><span class="mut"> Only an administrator can change this switch.</span><?php endif; ?></p>
</section>

<div class="nsd <?= !empty($d['picked']) ? 'has-sel' : '' ?>" id="top">
  <section class="sl-card flush nsd-list" aria-label="Statuses and actions">
    <form method="get" class="nsd-search">
      <?php if ($cur): ?><input type="hidden" name="t" value="<?= h($cur['key']) ?>"><?php endif; ?>
      <div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" value="<?= h($d['q']) ?>" aria-label="Search statuses and actions" placeholder="Search <?= (int) $d['total'] ?> messages"></div>
      <?php if ($d['canEdit'] && $d['statusKeys']): ?>
        <button type="button" class="sl-btn ghost sm nsd-bulk-btn" data-nsd-bulk><i class="ic ic-sm i-spark" aria-hidden="true"></i>Redraft all statuses with <?= h($king) ?></button>
      <?php endif; ?>
    </form>
    <div class="nsd-legend" aria-hidden="true">
      <span></span><?php foreach ($d['channels'] as $k => $lbl): ?><span title="<?= h($lbl) ?>"><i class="ic ic-sm <?= $chIcon[$k] ?>"></i></span><?php endforeach; ?>
    </div>
    <div class="nsd-scroll">
      <?php foreach ($d['groups'] as $g): ?>
        <div class="nsd-gl"><span><?= h($g['label']) ?></span><small><?= h($g['sub']) ?> · <?= count($g['items']) ?></small></div>
        <?php foreach ($g['items'] as $it): $on = $cur && $it['key'] === $cur['key']; ?>
          <a class="nsd-item <?= $on ? 'on' : '' ?>" href="<?= h($it['href']) ?>" data-key="<?= h($it['key']) ?>"<?= $on ? ' aria-current="page"' : '' ?>>
            <span class="nsd-name"><?php if ($it['color'] !== ''): ?><i class="nsd-sw" style="background:<?= h($it['color']) ?>" aria-hidden="true"></i><?php endif; ?><span class="nsd-nt"><?= h($it['label']) ?></span><?php if ($it['warn'] !== ''): ?><i class="ic ic-sm i-alert nsd-warn-ic" title="<?= h($it['warn']) ?>" aria-hidden="true"></i><span class="nsd-vh"><?= h($it['warn']) ?></span><?php endif; ?><i class="nsd-prop-dot" hidden title="<?= h($king) ?> proposal waiting for review"></i></span>
            <?php foreach ($it['states'] as $k => $st): ?><span class="nsd-dot <?= h($st) ?>" title="<?= h($d['channels'][$k] . ': ' . $stateTitle[$st]) ?>"><span class="nsd-vh"><?= h($d['channels'][$k] . ': ' . $stateTitle[$st]) ?></span></span><?php endforeach; ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <?php if (!$d['groups']): ?><div class="sl-empty" style="padding:24px">Nothing matches “<?= h($d['q']) ?>”.</div><?php endif; ?>
    </div>
    <div class="nsd-key"><span><i class="nsd-dot draft"></i>Drafted</span><span><i class="nsd-dot custom"></i>Custom</span><span><i class="nsd-dot builtin"></i>Built-in</span><span><i class="nsd-dot on"></i>On</span><span><i class="nsd-dot off"></i>Off</span><span><i class="ic ic-sm i-alert nsd-warn-ic" aria-hidden="true"></i>Check</span></div>
  </section>

  <?php if ($cur): ?>
  <section class="sl-card nsd-ed" aria-labelledby="nsd-title">
    <div class="nsd-ed-head">
      <a class="nsd-back" href="<?= h(sl_url(['t' => null, 'ch' => null])) ?>#top"><i class="ic ic-sm i-left" aria-hidden="true"></i>All messages</a>
      <div class="up"><?= h(['status' => 'Status', 'action' => 'Action', 'comms' => 'Automation'][$cur['kind']]) ?></div>
      <h2 id="nsd-title" class="nsd-h"><?= h($cur['label']) ?></h2>
      <p class="mut sm"><?= h($cur['desc']) ?></p>
    </div>

    <?php if ($isStatus): $cf = $cur['conflicts']; ?>
      <div class="nsd-info">
        <p class="sm"><b>Recognised as:</b> <?= h($cur['draftKindLabel']) ?>. <span class="mut"><?= h($cur['meaning']) ?></span></p>
        <details class="nsd-who" <?= ($cf['double'] || !$cf['fits']) ? 'open' : '' ?>>
          <summary class="sm"><b>Who else messages the customer for this status</b>
            <?php if ($cf['double']): ?><span class="sl-badge warn">May send twice</span><?php elseif (!$cf['fits']): ?><span class="sl-badge warn">Check the match</span><?php else: ?><span class="sl-badge ok">No double messages</span><?php endif; ?>
          </summary>
          <ul class="nsd-who-list sm">
            <li>Communication Center: matched to <b><?= h($cf['eventLabel']) ?></b> (<?= h($cf['how']) ?>).
              <?php if (!$d['commsOn']): ?>Its automations are switched off, so it sends nothing extra.
              <?php elseif ($cf['commsChannels']): ?><span class="nsd-red">It also sends <?= h(implode(', ', array_map(function ($c) use ($d) { return $d['channels'][$c]; }, array_keys($cf['commsChannels'])))) ?> for this status, so the customer can get two messages.</span>
              <?php else: ?>No automation channel is switched on for it, so it sends nothing extra.<?php endif; ?>
              <?php if (!$cf['fits']): ?><br><span class="nsd-red">That match looks wrong for "<?= h($cur['label']) ?>". An administrator can change it in the Communication Center (status matching).</span><?php endif; ?>
            </li>
            <?php foreach ($cf['autos'] as $a): ?>
              <li>Automation “<?= h($a['name']) ?>” also sends <?= h(implode(', ', array_map(function ($c) use ($d) { return $d['channels'][$c] ?? $c; }, $a['channels']))) ?> when a shipment moves to this status.</li>
            <?php endforeach; ?>
            <li class="mut">This studio's messages go out with the single status update of an air shipment or sea package (and WhatsApp with the bulk air update). Delivered / picked-up forms, shipment edits, bulk sea updates and consolidation updates still send their own e-mail templates.</li>
          </ul>
          <a class="sl-btn ghost sm" href="<?= h($d['commsUrl']) ?>">Open the Communication Center</a>
        </details>
      </div>
    <?php elseif ($isComms && $d['mappedStatuses']): ?>
      <div class="nsd-info">
        <p class="sm"><b>Statuses that trigger it:</b>
          <?php foreach ($d['mappedStatuses'] as $i => $ms): ?><?= $i ? ', ' : '' ?><a class="nsd-link" href="<?= h($ms['href']) ?>"><?= h($ms['label']) ?></a><?php if (!$ms['fits']): ?> <span class="sl-badge warn">looks wrong</span><?php endif; ?><?php endforeach; ?>.
          <span class="mut">It sends its own text below, in addition to each status message.</span></p>
      </div>
    <?php endif; ?>

    <div class="nsd-tabs" role="tablist" aria-label="Channel">
      <?php foreach ($d['channels'] as $k => $lbl): $e = $d['editor'][$k]; $st = !$e['supported'] ? 'na' : $e['mode']; ?>
        <?php if ($e['supported']): ?>
          <a role="tab" class="nsd-tab <?= $k === $ch ? 'on' : '' ?>" data-ch="<?= h($k) ?>" aria-selected="<?= $k === $ch ? 'true' : 'false' ?>" href="<?= h(sl_url(['t' => $cur['key'], 'ch' => $k])) ?>"><i class="ic ic-sm <?= $chIcon[$k] ?>" aria-hidden="true"></i><?= h($lbl) ?><span class="nsd-state <?= h($st) ?>" title="<?= h($stateTitle[$st]) ?>"><?= h($stateLabel[$st]) ?></span><i class="nsd-prop-dot" hidden title="<?= h($king) ?> proposal waiting"></i></a>
        <?php else: ?>
          <span role="tab" class="nsd-tab na" aria-disabled="true" title="<?= h($cur['label']) ?> does not send <?= h($lbl) ?> messages"><i class="ic ic-sm <?= $chIcon[$k] ?>" aria-hidden="true"></i><?= h($lbl) ?><span class="nsd-state na">Not sent</span></span>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <form class="nsd-form" id="nsd-form" novalidate data-key="<?= h($cur['key']) ?>" data-channel="<?= h($ch) ?>" data-kind="<?= h($cur['kind']) ?>" data-can-save="<?= $canSave ? '1' : '0' ?>">
      <div class="nsd-mode">
        <label class="nsd-switch">
          <button type="button" class="sl-toggle <?= $ed['enabled'] ? 'on' : '' ?>" role="switch" aria-checked="<?= $ed['enabled'] ? 'true' : 'false' ?>" aria-labelledby="nsd-sw-l" data-nsd-enabled <?= $canSave ? '' : 'data-static' ?>></button>
          <span id="nsd-sw-l" class="fw5">Send <?= h($chName) ?></span>
        </label>
        <p class="nsd-modetext sm" data-nsd-modetext>
          <?php if ($isComms): ?>
            <?= $ed['enabled'] ? 'This automation sends the message below.' : 'Switched off: this automation sends nothing on ' . h($chName) . '.' ?>
          <?php elseif ($ed['mode'] === 'draft'): ?>
            <b>Drafted for this status:</b> the message below is what customers receive today. Edit it and save to make it your own.
          <?php elseif ($ed['mode'] === 'builtin' && $isStatus): ?>
            Status-specific messages are off, so the old shared message is sent: <?= h(sl_notifyStudioBuiltin('status', $ch, false)) ?> Save below to send this message instead.
          <?php elseif ($ed['mode'] === 'builtin'): ?>
            Today the built-in message is sent: <?= h($ed['builtin']) ?> Edit below and save to send your own.
          <?php elseif ($ed['mode'] === 'custom'): ?>
            <b>Custom:</b> your saved message below is sent<?= $ed['updated'] !== '' ? ' (saved ' . h($ed['updated']) . ')' : '' ?>.
          <?php else: ?>
            Switched off: nothing is sent on <?= h($chName) ?> for this one.
          <?php endif; ?>
        </p>
      </div>

      <?php if ($canSave): ?>
      <div class="nsd-ai" data-nsd-ai>
        <div class="nsd-ai-head">
          <span class="nsd-ai-t"><i class="ic ic-sm i-spark" aria-hidden="true"></i>Rewrite with <?= h($king) ?></span>
          <span class="mut sm">A proposal appears here for you to accept, retry or discard. Nothing is saved on its own.</span>
        </div>
        <div class="nsd-ai-row">
          <label class="sl-field nsd-ai-ins"><span class="nsd-vh">Instruction for <?= h($king) ?> (optional)</span>
            <input class="sl-in" type="text" maxlength="300" data-nsd-ai-ins placeholder="Optional: “shorter”, “add that the office closes at 5pm”…"></label>
          <div class="nsd-tones" role="radiogroup" aria-label="Tone">
            <?php $first = true; foreach ($ai['tones'] as $tk => $tl): ?><button type="button" class="nsd-tone <?= $first ? 'on' : '' ?>" role="radio" aria-checked="<?= $first ? 'true' : 'false' ?>" data-tone="<?= h($tk) ?>"><?= h($tl) ?></button><?php $first = false; endforeach; ?>
          </div>
        </div>
        <div class="nsd-ai-row">
          <button type="button" class="sl-btn-violet nsd-ai-go" data-nsd-ai-go="one"><i class="ic ic-sm i-spark" aria-hidden="true"></i>Regenerate <?= h($chName) ?> with <?= h($king) ?></button>
          <?php if (count($cur['channels']) > 1): ?><button type="button" class="sl-btn ghost" data-nsd-ai-go="all">Regenerate all channels</button><?php endif; ?>
          <span class="nsd-ai-status sm" data-nsd-ai-status role="status" aria-live="polite"></span>
        </div>
        <div class="nsd-ai-setup sl-banner info sm" data-nsd-ai-setup <?= $ai['ready'] ? 'hidden' : '' ?>>
          <i class="ic ic-sm i-ai" aria-hidden="true"></i>
          <span><span data-nsd-ai-why><?= h($ai['why']) ?></span> <a class="nsd-link" href="<?= h($ai['setupUrl']) ?>"><?= $ai['canManage'] ? 'Set up ' . h($king) . ' in the AI centre' : 'Open the AI centre' ?></a></span>
        </div>
      </div>
      <div class="nsd-prop" data-nsd-prop hidden aria-live="polite"></div>
      <?php endif; ?>

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
            <div class="row between g8 wrap"><span class="sl-lab">Preview <span class="mut">(sample customer)</span></span><span class="nsd-seg sl-seg boxed" role="group" aria-label="Preview width"><button type="button" class="on" data-nsd-w="desk" aria-pressed="true">Desktop</button><button type="button" data-nsd-w="phone" aria-pressed="false">Phone</button></span></div>
            <div class="nsd-mail">
              <div class="nsd-mail-head">
                <div class="nsd-mail-from"><span class="nsd-mail-av" aria-hidden="true"><?= h(mb_substr((string) ($d['sample']['company'] ?? 'S'), 0, 1)) ?></span><span><b><?= h($d['sample']['company'] ?? '') ?></b><span class="mut"> to Efua Owusu</span></span></div>
                <div class="nsd-mail-subj" data-nsd-psubj></div>
              </div>
              <div class="nsd-frame-wrap" data-nsd-fwrap><div class="nsd-frame-in" data-nsd-fin><iframe class="nsd-frame" data-nsd-frame title="E-mail preview" sandbox="allow-same-origin"></iframe></div></div>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="nsd-text">
          <div class="nsd-text-col">
            <label class="sl-field"><span class="sl-lab"><?= $ch === 'inapp' ? 'Notification text' : 'Message' ?></span>
              <textarea class="sl-in nsd-ta" name="body" maxlength="<?= (int) $d['limits'][$ch] ?>" data-nsd-field <?= $canSave ? '' : 'readonly' ?>><?= h($ed['body']) ?></textarea>
            </label>
            <div class="row between sm mut wrap g8"><span data-nsd-count></span><span><?= $ch === 'whatsapp' ? 'Use *stars* for bold' : ($ch === 'sms' ? 'Over 160 characters is sent in several parts' : 'Shown in the bell menu; staff see it too') ?></span></div>
          </div>
          <div class="nsd-prev-col">
            <span class="sl-lab">Preview <span class="mut">(sample customer)</span></span>
            <?php if ($ch === 'sms'): ?>
              <div class="nsd-phone sms"><div class="nsd-ph-top"><span class="nsd-ph-av" aria-hidden="true"><?= h(mb_substr((string) $d['sample']['company'], 0, 1)) ?></span><b><?= h($d['sample']['company']) ?></b><small>Text message · Today</small></div><div class="nsd-ph-body"><div class="nsd-bubble" data-nsd-bubble></div></div></div>
            <?php elseif ($ch === 'whatsapp'): ?>
              <div class="nsd-phone whatsapp"><div class="nsd-ph-top"><span class="nsd-ph-av" aria-hidden="true"><?= h(mb_substr((string) $d['sample']['company'], 0, 1)) ?></span><span><b><?= h($d['sample']['company']) ?></b><small>Business account</small></span></div><div class="nsd-ph-body"><div class="nsd-bubble" data-nsd-bubble></div></div></div>
            <?php else: ?>
              <div class="nsd-phone inapp"><div class="nsd-bell-head"><i class="ic ic-sm i-bell" aria-hidden="true"></i><b>Notifications</b><span class="nsd-bell-n">1 new</span></div><div class="nsd-bell-row"><span class="nsd-bell-ic"><i class="ic ic-sm i-plane" aria-hidden="true"></i></span><span class="nsd-bell-txt"><span class="nsd-bubble" data-nsd-bubble></span><small>Air shipment #1042 · just now</small></span><i class="nsd-bell-dot" aria-hidden="true"></i></div></div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php ob_start(); ?>
        <div class="row g6 wrap nsd-var-row"><?php foreach ($d['vars'] as $v): if (substr($v['key'], -5) === '_html') continue; $vOff = (string) ($v['off'] ?? ''); ?><button type="button" class="nsd-var<?= $vOff !== '' ? ' off' : '' ?>" data-nsd-var="<?= h($v['key']) ?>" title="<?= h($vOff !== '' ? $vOff : $v['desc']) ?>"<?= $vOff !== '' ? ' aria-disabled="true"' : '' ?>><?= h('{' . $v['key'] . '}') ?></button><?php endforeach; ?></div>
        <p class="sm mut nsd-var-note">Click a field to add it where the cursor is. A line whose fields are all empty is left out when the message is sent.</p>
      <?php $varChips = ob_get_clean(); ?>
      <?php if (!$canSave): ?>
      <div class="nsd-vars"><div class="sl-lab">Fields</div><?= $varChips ?></div>
      <?php endif; ?>

      <?php if ($canSave): ?>
      <div class="nsd-actions">
        <details class="nsd-vars nsd-vars-pop" data-nsd-vars-pop>
          <summary class="sl-btn ghost"><span class="nsd-vars-brace" aria-hidden="true">{ }</span>Insert a field</summary>
          <div class="nsd-vars-panel" role="group" aria-label="Fields you can insert"><?= $varChips ?></div>
        </details>
        <button type="submit" class="sl-btn" data-nsd-save>Save</button>
        <span class="nsd-dirty sm" data-nsd-dirty role="status" hidden>Unsaved changes</span>
        <?php if ($ch === 'email'): ?><button type="button" class="sl-btn ghost" data-nsd-test><i class="ic ic-sm i-send" aria-hidden="true"></i>Send test to me</button><?php endif; ?>
        <span class="grow"></span>
        <?php if ($isComms): ?>
          <button type="button" class="sl-btn ghost" data-nsd-reset>Restore default text</button>
        <?php elseif ($isStatus && $ed['mode'] === 'draft'): ?>
          <button type="button" class="sl-btn ghost" data-nsd-undo hidden>Reset to draft</button>
        <?php elseif ($ed['mode'] === 'custom' || $ed['mode'] === 'off'): ?>
          <button type="button" class="sl-btn ghost" data-nsd-reset><?= $isStatus ? ($d['draftsOn'] ? 'Reset to draft' : 'Use the old shared message') : 'Use built-in message' ?></button>
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
      'key' => $cur['key'], 'channel' => $ch, 'kind' => $cur['kind'], 'label' => $cur['label'], 'channels' => $cur['channels'],
      'channelLabels' => $d['channels'], 'enabled' => (bool) $ed['enabled'], 'mode' => $ed['mode'], 'blocks' => $ch === 'email' ? array_values($ed['blocks']) : [],
      'subject' => $ed['subject'], 'body' => $ed['body'], 'draft' => $ed['draft'] ?? null, 'draftsOn' => $d['draftsOn'],
      'types' => $d['blockTypes'], 'sample' => $d['sample'], 'limit' => $d['limits'][$ch], 'channelLabel' => $chName,
      'ai' => $ai, 'statusKeys' => $d['statusKeys'],
  ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
  <?php else: ?>
  <section class="sl-card nsd-ed"><div class="sl-empty">No statuses or actions found.</div></section>
  <script type="application/json" id="nsd-data"><?= json_encode(['key' => '', 'ai' => $ai, 'statusKeys' => $d['statusKeys']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
  <?php endif; ?>
</div>

<?php if ($d['canEdit'] && $d['statusKeys']): ?>
<div class="sl-dialog" id="nsd-bulk" hidden>
  <div class="sl-modal nsd-bulk" aria-labelledby="nsd-bulk-h">
    <div class="row between g8"><h3 id="nsd-bulk-h" class="nsd-h3"><i class="ic ic-sm i-spark" aria-hidden="true"></i> Redraft all statuses with <?= h($king) ?></h3><button type="button" class="sl-ib sm" data-nsd-bulk-close aria-label="Close"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>
    <p class="sm mut"><?= h($king) ?> rewrites the e-mail, SMS, WhatsApp and in-app message of each status, one status at a time. The results are proposals: open a status to review them, or save them all as custom messages after you confirm. Nothing is sent or saved before that.</p>
    <div class="nsd-ai-setup sl-banner info sm" <?= $ai['ready'] ? 'hidden' : '' ?>><i class="ic ic-sm i-ai" aria-hidden="true"></i><span><?= h($ai['why']) ?> <a class="nsd-link" href="<?= h($ai['setupUrl']) ?>"><?= $ai['canManage'] ? 'Set up ' . h($king) . ' in the AI centre' : 'Open the AI centre' ?></a></span></div>
    <label class="row g8 sm nsd-check"><input type="checkbox" data-nsd-bulk-skip checked> Skip statuses that already have a saved (custom) message</label>
    <label class="sl-field"><span class="sl-lab">Instruction for every status <span class="mut">(optional)</span></span><input class="sl-in" type="text" maxlength="300" data-nsd-bulk-ins placeholder="e.g. “mention our WhatsApp support line”"></label>
    <div class="nsd-tones" role="radiogroup" aria-label="Tone"><?php $first = true; foreach ($ai['tones'] as $tk => $tl): ?><button type="button" class="nsd-tone <?= $first ? 'on' : '' ?>" role="radio" aria-checked="<?= $first ? 'true' : 'false' ?>" data-tone="<?= h($tk) ?>"><?= h($tl) ?></button><?php $first = false; endforeach; ?></div>
    <div class="row g8 wrap nsd-bulk-acts">
      <button type="button" class="sl-btn-violet" data-nsd-bulk-start><i class="ic ic-sm i-spark" aria-hidden="true"></i>Start</button>
      <button type="button" class="sl-btn ghost" data-nsd-bulk-stop hidden>Stop</button>
      <button type="button" class="sl-btn ghost" data-nsd-bulk-save hidden>Save all proposals as custom…</button>
      <button type="button" class="sl-btn ghost" data-nsd-bulk-clear hidden>Discard all proposals</button>
    </div>
    <div class="nsd-bulk-prog" data-nsd-bulk-prog hidden><div class="nsd-bar"><span data-nsd-bulk-bar></span></div><p class="sm" data-nsd-bulk-text role="status" aria-live="polite"></p></div>
    <ol class="nsd-bulk-list sm" data-nsd-bulk-list></ol>
  </div>
</div>
<?php endif; ?>
