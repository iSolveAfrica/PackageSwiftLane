<?php
/**
 * CommsAutomations - Communications > Automations (comms_automations.php).
 * Everything that sends a customer a message, one row per trigger with a
 * WhatsApp / SMS / E-mail switch (and In-app where it applies), grouped as
 * cdp_commTriggerCatalogue() returns them. Edit opens the shared editor
 * drawer (dataJs/sl/comms_editor.js): Communication Center texts in
 * cdb_comm_templates, old-screen texts in their legacy template rows.
 * Data: views/sl/data/comms_automations.php. Behaviour: dataJs/sl/comms_automations.js.
 * Saves: ajax/comms/automation_ajax.php. Styles: pages/CommsAutomations.css.
 */
if (!isset($d)) { $d = []; }
$canEdit = !empty($d['canEdit']);
$isAdmin = !empty($d['isAdmin']);
$ai = $d['ai'] ?? ['ok' => false, 'name' => 'King', 'message' => '', 'setup_url' => '', 'tones' => [], 'code' => ''];
$chs = ['whatsapp' => ['WhatsApp', 'i-wa'], 'sms' => ['SMS', 'i-message'], 'email' => ['E-mail', 'i-mail']];
$engineLabel = ['comms' => 'Automation Center', 'legacy' => 'Old screen', 'custom' => 'Custom rules'];
$ajax = 'ajax/comms/automation_ajax.php';
$cfg = ['page' => 'automations', 'ajax' => $ajax, 'canEdit' => $canEdit, 'ai' => $ai, 'langs' => $d['langs'] ?? [], 'edit' => $d['edit'] ?? '',
    'editAsked' => $d['editAsked'] ?? '', 'tplId' => (int) ($d['tplId'] ?? 0),
    'ch' => $d['ch'] ?? '', 'tab' => $d['tab'] ?? 'all', 'statusesUrl' => $d['statusesUrl'] ?? 'comms_statuses.php'];
$masterText = ['on' => 'Switched-on triggers below send their messages.', 'off' => 'Nothing below is sent by the Automation Center while this is off. Old screens still send their own messages.'];
$cfg['masterText'] = $masterText;
/** Is a trigger sending on at least one channel (a channel with no text sends nothing)? */
$sends = function (array $t): bool {
    if ($t['key'] === 'status:*') {
        return (int) ($t['summary']['on'] ?? 0) > 0;
    }
    foreach ((array) $t['rules'] as $c => $v) {
        if ($v && empty($t['missing'][$c])) {
            return true;
        }
    }
    return false;
};
$totalOn = 0;
$total = 0;
foreach ((array) ($d['catalogue'] ?? []) as $g) {
    foreach ($g['triggers'] as $t) {
        if ($t['engine'] === 'custom' || !empty($t['unassigned'])) {
            continue;
        }
        $total++;
        if ($sends($t)) {
            $totalOn++;
        }
    }
}
?>
<div class="ops ca-page" id="ca-root">
<?php $commsTab = 'commsautomations'; include __DIR__ . '/partials/comms_tabs.php'; ?>
<div class="sl-head">
  <div>
    <h1 class="sl-title">Automations</h1>
    <p class="sl-lede">Everything that sends your customers a message, in one list. Switch each channel on or off per trigger, and edit the text it sends.</p>
  </div>
  <div class="row g8 wrap">
    <a class="sl-btn ghost" href="<?= h($d['statusesUrl'] ?? 'comms_statuses.php') ?>"><i class="ic ic-sm i-layers" aria-hidden="true"></i>Status messages</a>
    <?php if ($isAdmin): ?><a class="sl-btn ghost" href="<?= h($d['settingsUrl'] ?? 'comms_settings.php') ?>"><i class="ic ic-sm i-cog" aria-hidden="true"></i>Settings</a><?php endif; ?>
  </div>
</div>

<?php if (empty($d['ready'])): ?>
<div class="sl-banner bad">The message tables could not be created. The database user needs the CREATE privilege once.</div>
<?php else: ?>

<section class="sl-card ca-strip" aria-label="Sending">
  <div class="ca-master">
    <button type="button" class="sl-toggle <?= $d['master'] ? 'on' : '' ?>" role="switch" aria-checked="<?= $d['master'] ? 'true' : 'false' ?>" aria-labelledby="ca-master-l" aria-describedby="ca-master-t"
      <?= $isAdmin ? 'data-sl-save="' . h($ajax) . '" data-p-action="master" data-ca-master' : 'data-static' ?>></button>
    <div class="col">
      <b class="fw5" id="ca-master-l">Automations <span data-ca-master-state><?= $d['master'] ? 'on' : 'off' ?></span></b>
      <span class="mut sm" id="ca-master-t"><span data-ca-master-text><?= h($masterText[$d['master'] ? 'on' : 'off']) ?></span><?= $isAdmin ? '' : ' Only an administrator changes this.' ?></span>
    </div>
  </div>
  <div class="ca-facts">
    <span class="ca-fact"><i class="ic ic-sm i-clock" aria-hidden="true"></i><span><?= h($d['quiet']) ?></span></span>
    <span class="ca-fact"><i class="ic ic-sm i-refresh" aria-hidden="true"></i><span>Status messages are sent by <b class="fw5"><?= $d['owner'] === 'comms' ? 'the Automation Center' : 'the old status screens' ?></b><?= $d['owner'] === 'comms' ? '' : ' (hand them over in Settings)' ?>.</span></span>
    <span class="ca-fact"><i class="ic ic-sm i-check" aria-hidden="true"></i><span><b class="fw5"><?= (int) $totalOn ?></b> of <?= (int) $total ?> triggers have a channel on<?= $d['workerLast'] !== '' ? ' · worker ran ' . h(date('M j, H:i', strtotime($d['workerLast']))) : ' · the message worker has not run yet' ?>.</span></span>
  </div>
</section>

<div class="ca-bar">
  <div class="sl-search ca-q"><i class="ic ic-sm i-search" aria-hidden="true"></i><input type="search" data-ca-q aria-label="Search triggers" placeholder="Search triggers" autocomplete="off"></div>
  <div class="sl-seg boxed ca-seg" role="group" aria-label="Show">
    <button type="button" data-ca-f="all" aria-pressed="<?= $d['tab'] === 'all' ? 'true' : 'false' ?>" class="<?= $d['tab'] === 'all' ? 'on' : '' ?>">All</button>
    <button type="button" data-ca-f="on" aria-pressed="false">Switched on</button>
    <button type="button" data-ca-f="other" aria-pressed="<?= $d['tab'] === 'other' ? 'true' : 'false' ?>" class="<?= $d['tab'] === 'other' ? 'on' : '' ?>">Other triggers</button>
  </div>
  <?php if (!$ai['ok']): ?><span class="ca-ai-note mut sm"><span class="ca-k" aria-hidden="true">&#10022;</span><?= h($ai['name']) ?> is not set up<?php if ($ai['setup_url'] !== ''): ?> · <a href="<?= h($ai['setup_url']) ?>">AI centre</a><?php endif; ?></span><?php else: ?><span class="ca-ai-note sm"><span class="ca-k" aria-hidden="true">&#10022;</span>Every editor can write with <?= h($ai['name']) ?></span><?php endif; ?>
</div>

<?php if (($d['ch'] ?? '') !== '' && ($d['edit'] ?? '') === '' && ($d['editAsked'] ?? '') === ''): ?>
<div class="sl-banner info ca-oldlink" role="note"><i class="ic ic-sm i-info" aria-hidden="true"></i><span>The old <?= h($chs[$d['ch']][0]) ?> template list now lives here: <b class="fw5">Edit</b> on a trigger opens its <?= h($chs[$d['ch']][0]) ?> text. Template rows no trigger uses are under <a href="#ca-g-unassigned">Unassigned old templates</a>.</span></div>
<?php endif; ?>

<?php foreach ($d['catalogue'] as $g): ?>
<section class="sl-card flush ca-group" data-ca-group="<?= h($g['id']) ?>" aria-labelledby="ca-g-<?= h($g['id']) ?>">
  <header class="ca-gh"><h2 class="sl-ct" id="ca-g-<?= h($g['id']) ?>"><?= h($g['label']) ?></h2><?php if ($g['desc'] !== ''): ?><span class="mut sm"><?= h($g['desc']) ?></span><?php endif; ?></header>
  <div class="ca-tw">
  <table class="ca-table pwa-keep">
    <colgroup><col><col class="ca-c-ch"><col class="ca-c-ch"><col class="ca-c-ch"><col class="ca-c-ch"><col class="ca-c-n"><col class="ca-c-a"></colgroup>
    <thead><tr><th scope="col">Trigger</th><?php foreach ($chs as $c => $cl): ?><th scope="col" class="ca-chh"><i class="ic ic-sm <?= $cl[1] ?>" aria-hidden="true"></i><?= h($cl[0]) ?></th><?php endforeach; ?><th scope="col" class="ca-chh"><i class="ic ic-sm i-bell" aria-hidden="true"></i>In-app</th><th scope="col" class="ca-num">This month</th><th scope="col"><span class="ca-vh">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($g['triggers'] as $t):
        $key = (string) $t['key'];
        $legacy = $t['engine'] === 'legacy';
        $isOn = $sends($t);
        $unassigned = !empty($t['unassigned']);
        $f = trim(($isOn ? 'on ' : '') . ($legacy ? 'other' : '') . ' ' . $t['engine']);
        $locked = !empty($t['locked']);
    ?>
      <tr data-ca-row data-key="<?= h($key) ?>" data-f="<?= h($f) ?>">
        <th scope="row" class="ca-name">
          <span class="ca-nt"><?= h($t['label']) ?></span>
          <span class="ca-sub mut sm"><?= h($t['desc']) ?></span>
          <span class="ca-tags">
            <span class="sl-badge <?= $legacy ? 'warn' : ($t['engine'] === 'custom' ? '' : 'ok') ?>"><?= h($engineLabel[$t['engine']] ?? $t['engine']) ?></span>
            <?php if ($locked): ?><span class="sl-badge"><i class="ic ic-sm i-lock" aria-hidden="true"></i>Always on (security)</span><?php endif; ?>
            <?php if (!empty($t['status_family'])): ?><span class="sl-badge" title="With status messages handed to the Automation Center this old send stops on its own">Status family</span><?php endif; ?>
            <?php if ($legacy && empty($t['templates']) && !$locked): ?><span class="mut sm" title="This screen writes its own text; only its channel switches are here.">Wording built into the screen</span><?php endif; ?>
            <?php if (!empty($t['missing'])): ?><span class="sl-badge bad" title="The old template row is missing, so nothing is sent on <?= h(implode(', ', array_map(function ($c) use ($chs) { return $chs[$c][0]; }, array_keys($t['missing'])))) ?>. Edit it to write the text.">No text on <?= h(implode(', ', array_map(function ($c) use ($chs) { return $chs[$c][0]; }, array_keys($t['missing'])))) ?></span><?php endif; ?>
          </span>
        </th>
        <?php foreach ($chs as $c => $cl):
            $can = in_array($c, (array) $t['channels'], true);
            $on = !empty($t['rules'][$c]);
        ?>
        <td class="ca-cell" data-label="<?= h($cl[0]) ?>">
          <?php if ($t['engine'] === 'custom' || $unassigned): ?><span class="mut sm"><?= $unassigned && !empty($t['templates'][$c]) ? count($t['templates'][$c]['ids']) . ' rows' : '—' ?></span>
          <?php elseif ($key === 'status:*'): ?><a class="sm" href="<?= h($t['link']) ?>" title="Each status has its own <?= h($cl[0]) ?> switch">Per status</a>
          <?php elseif (!$can): ?><span class="mut sm" title="<?= h($t['label']) ?> does not send <?= h($cl[0]) ?>">Not sent</span>
          <?php else: ?>
            <?php if (!empty($t['missing'][$c])): ?><span class="ca-miss" title="No text: the template row is missing, so nothing is sent<?= $on ? ' even though the switch is on' : '' ?>. Edit to write it.">No text</span><?php endif; ?>
            <?php if (!empty($t['builtin'][$c])): ?><span class="ca-bi" title="<?= h($cl[0]) ?> wording is built into the screen; only the switch is here." aria-hidden="true">built-in</span><?php endif; ?>
            <button type="button" class="sl-toggle <?= $on ? 'on' : '' ?>" role="switch" aria-checked="<?= $on ? 'true' : 'false' ?>"
              aria-label="<?= h($cl[0] . ': ' . $t['label']) ?>" data-key="<?= h($key) ?>" data-p-channel="<?= h($c) ?>"
              <?= ($canEdit && !$locked) ? 'data-sl-save="' . h($ajax) . '" data-p-action="save_rule"' : 'data-static' ?>></button>
          <?php endif; ?>
        </td>
        <?php endforeach; ?>
        <td class="ca-cell" data-label="In-app">
          <?php if (isset($t['inapp'])): ?><a class="sm" href="<?= h($t['link']) ?>#inapp"><?= (int) $t['inapp']['on'] ?> of <?= (int) $t['inapp']['total'] ?></a>
          <?php else: ?><span class="mut sm" title="Only status messages show a line in the notification bell">—</span><?php endif; ?>
        </td>
        <td class="ca-num" data-label="This month"><?= $t['engine'] === 'custom' ? '<span class="mut sm">—</span>' : number_format((int) $t['sent']) ?></td>
        <td class="ca-act">
          <?php if ($key === 'status:*'): ?>
            <a class="sl-btn sm" href="<?= h($t['link']) ?>"><?= (int) ($t['summary']['on'] ?? 0) ?> of <?= (int) ($t['summary']['total'] ?? 0) ?> on · Open</a>
          <?php elseif ($t['engine'] === 'custom' || ($t['link'] !== '' && !$t['editable'])): ?>
            <a class="sl-btn ghost sm" href="<?= h($t['link']) ?>">Open</a>
          <?php elseif ($t['editable']): ?>
            <button type="button" class="sl-btn ghost sm" data-ca-edit="<?= h($key) ?>" data-label="<?= h($t['label']) ?>"><i class="ic ic-sm i-edit" aria-hidden="true"></i><?= $canEdit ? 'Edit' : 'View' ?></button>
            <?php if ($t['link'] !== ''): ?><a class="sl-btn ghost sm" href="<?= h($t['link']) ?>">Open</a><?php endif; ?>
          <?php elseif ($g['id'] === 'support'): ?>
            <a class="sl-btn ghost sm" href="<?= h(sl_route('CsRules')) ?>">Customer Care</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
<?php endforeach; ?>
<div class="sl-empty ca-none" data-ca-empty hidden>No trigger matches. <button type="button" class="sl-btn ghost sm" data-ca-clear>Show all</button></div>
<p class="mut sm ca-foot">Old-screen messages keep their own wording in the template rows shown in their editor ([TAGS] are filled by that screen). Statuses each send the message written for them; open <a href="<?= h($d['statusesUrl'] ?? 'comms_statuses.php') ?>">Status messages</a> to edit them.</p>
<?php endif; ?>
</div>
<?php $ec = ['template' => true, 'tabs' => ['blocks', 'tpl', 'vars']]; include CDP_ROOT . '/views/sl/inc/email_canvas.php'; ?>
<script type="application/json" id="ca-cfg"><?= json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
