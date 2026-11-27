<?php
/**
 * M-NotifCtl: which system event tells customers on which channel (notification_center.php).
 * List state: events by group with their channels. ?event=key opens one event: channel switches and its template text.
 * Writes: ajax/sl/notification_rules_ajax.php (op toggle / save / test), the same as the web page.
 */
$ajax = 'ajax/sl/notification_rules_ajax.php';
$open = isset($_GET['event']) && isset($d['cur']['key']) && $_GET['event'] === $d['cur']['key'];
$chL = ['em' => ['email', 'Email'], 'wa' => ['whatsapp', 'WhatsApp'], 'sm' => ['sms', 'SMS']];
$icons = ['Shipments' => 'box', 'Payments' => 'dollar', 'Pickups' => 'key', 'Account' => 'user', 'Warehouse' => 'archive'];
$canEdit = !empty($d['canEdit']);
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-msg.css')) ?>">
<?php if (!$open): ?>
<?= sl_phoneTop('Notification control', '', ['back' => 'menu.php'], sl_phoneIcon('more', 'Open the full page', sl_url(['view' => 'web']))) ?>
<main class="mx-body" data-mx-scope>
<?php if (empty($d['ready'])): ?><div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>Per-channel switches need a database upgrade. Until then every channel stays on.</span></div><?php endif; ?>
<div class="mx-srch"><form method="get" role="search"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q'] ?? '') ?>" aria-label="Search notifications" placeholder="Search <?= (int) ($d['total'] ?? 0) ?> notifications"></label></form></div>
<?php if (count($d['groups']) > 1): ?>
<div class="mx-seg" role="tablist" data-mx-seg><?php foreach ($d['groups'] as $i => $g): ?><button type="button" role="tab" data-mx-panel="g<?= $i ?>" class="<?= $i === 0 ? 'on' : '' ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>"><?= h($g['label']) ?> <?= (int) $g['count'] ?></button><?php endforeach; ?></div>
<?php endif; ?>
<?php foreach ($d['groups'] as $i => $g): ?>
<div class="mx-sec" data-mx-panel-of="g<?= $i ?>"<?= $i === 0 ? '' : ' hidden' ?>><h2>When to tell customers · <?= h($g['label']) ?></h2></div>
<section class="mx-card mx-list" data-mx-panel-of="g<?= $i ?>"<?= $i === 0 ? '' : ' hidden' ?>>
<?php foreach ($g['items'] as $e):
    $on = [];
    foreach ($chL as $short => [$ch, $label]) if ($e[$short] === 'on') $on[] = $label;
?>
<a class="mx-row" href="<?= h(sl_url(['event' => $e['key'], 'q' => null])) ?>"><span class="mx-li"><i class="ic i-<?= h($icons[$g['label']] ?? 'bell') ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($e['name']) ?></b><small><?= $on ? h(implode(' · ', $on)) : 'No channel' ?></small></span><span class="mx-rr"><?= $on ? sl_pill('On', 'delivered') : sl_pill('Off', 'plain') ?></span></a>
<?php endforeach; ?>
</section>
<?php endforeach; ?>
<?php if (!$d['groups']): ?><div class="mx-ban info"><i class="ic ic-sm i-search" aria-hidden="true"></i><span>No notification matches “<?= h($d['q']) ?>”.</span></div><?php endif; ?>
<section class="mx-card mx-list">
<a class="mx-row" href="<?= h(sl_route('NotifyStudio')) ?>"><span class="mx-li"><i class="ic i-send" aria-hidden="true"></i></span><span class="mx-rm"><b>Notification studio</b><small>What every status sends</small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<a class="mx-row" href="<?= h(sl_route('MessageTemplates')) ?>"><span class="mx-li"><i class="ic i-file" aria-hidden="true"></i></span><span class="mx-rm"><b>SMS &amp; WhatsApp templates</b></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
</section>
</main>
<?php else: $cur = $d['cur']; $rowOn = []; foreach ($d['tabs'] as $t) $rowOn[$t['key']] = $t['on'] === 'on'; ?>
<?php $slPhone['nav'] = 'none'; ?>
<?= sl_phoneTop($cur['name'], $cur['group'] . ' · ' . $cur['sent'] . ' sent this month', ['back' => sl_url(['event' => null, 'channel' => null, 'tpl' => null])]) ?>
<main class="mx-body">
<p class="mx-note"><?= h($cur['desc']) ?> Sent <?= h($cur['when']) ?>.</p>
<section class="mx-card mx-tgs">
<?php foreach ($d['tabs'] as $t): ?>
<div class="mx-tg"><span class="mx-rm"><b><?= h($t['label']) ?></b><small><?= h($d['health'][$t['key']]['state'] ?? '') ?></small></span><button type="button" class="mx-sw<?= $rowOn[$t['key']] ? ' on' : '' ?>" role="switch" aria-checked="<?= $rowOn[$t['key']] ? 'true' : 'false' ?>" aria-label="<?= h($t['label']) ?> for <?= h($cur['name']) ?>"<?= $canEdit ? ' data-sl-save="' . h($ajax) . '" data-p-op="toggle" data-p-event="' . h($cur['key']) . '" data-p-channel="' . h($t['key']) . '"' : ' disabled aria-disabled="true"' ?>></button></div>
<?php endforeach; ?>
</section>
<label class="mx-f"><small>Message for</small><div class="mx-fseg"><?php foreach ($d['tabs'] as $t): ?><a class="<?= h($t['cls']) ?>" href="<?= h($t['href']) ?>"<?= $t['cls'] ? ' aria-current="true"' : '' ?>><?= h($t['label']) ?></a><?php endforeach; ?></div></label>
<section class="mx-card pm-wa"><div class="mx-msg"><p><?= $d['subjectPrev'] !== '' && $d['isEmail'] ? '<b>' . h($d['subjectPrev']) . '</b>' . "\n" : '' ?><?= h($d['bodyPrev'] !== '' ? $d['bodyPrev'] : 'No template linked to this channel yet.') ?></p><small>Preview for <?= h($d['sample']['name']) ?></small></div></section>
<form id="pm-nc" data-sl-form action="<?= h($ajax) ?>" method="post" data-sl-reload>
<input type="hidden" name="op" value="save"><input type="hidden" name="event" value="<?= h($cur['key']) ?>"><input type="hidden" name="channel" value="<?= h($d['channel']) ?>">
<section class="mx-card mx-form">
<label class="mx-f"><small>Template used</small><span class="pm-sel"><select class="mx-in" name="template_id" onchange="location.href=<?= h(json_encode(sl_url(['event' => $cur['key'], 'channel' => $d['channel']]) . '&tpl=')) ?>+this.value"<?= $canEdit ? '' : ' disabled' ?>><option value="0">— none linked —</option><?php foreach ($d['templates'] as $t): ?><option value="<?= (int) $t['id'] ?>"<?= (int) $t['id'] === (int) $d['templateId'] ? ' selected' : '' ?>><?= h($t['name']) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
<?php if ($d['isEmail']): ?><label class="mx-f"><small>Subject</small><input class="mx-in" name="subject" value="<?= h($d['subject']) ?>"<?= $canEdit ? '' : ' readonly' ?>></label><?php endif; ?>
<label class="mx-f"><small>Message<?= $d['bodyIsHtml'] ? ' (HTML)' : '' ?></small><textarea class="mx-in" name="body" rows="6" data-pm-body<?= $canEdit ? '' : ' readonly' ?>><?= h($d['body']) ?></textarea></label>
<p class="mx-note"><?= h($d['counter']) ?> · <?= h($d['channelNote']) ?></p>
</section>
<div class="mx-chips"><?php foreach ($d['vars'] as $v): ?><button type="button" data-pm-var="<?= h($v['label']) ?>"><?= h($v['label']) ?></button><?php endforeach; ?></div>
<?php if ($canEdit): ?><div class="mx-btns"><button class="mx-btn" type="submit"><?= h($d['saveLabel']) ?></button><button class="mx-btn sec" type="button" data-pm-test="<?= h($ajax) ?>" data-p-op="test" data-p-event="<?= h($cur['key']) ?>" data-p-channel="<?= h($d['channel']) ?>">Send me a test</button></div><?php endif; ?>
</form>
</main>
<?php $slPhone['scripts'][] = 'dataJs/sl/phone/msg.js'; endif; ?>
