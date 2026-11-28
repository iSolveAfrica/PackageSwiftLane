<?php
/**
 * M-NotifStudio: what one status or action sends, per channel (notify_studio.php?t=key&ch=channel).
 * Saves and tests through ajax/sl/notify_studio_ajax.php with the web page's fields (op, key, channel, enabled, subject, body, blocks).
 * E-mail blocks are kept as they are; only the subject is edited here (block layout stays on a computer).
 */
$slPhone['scripts'][] = 'dataJs/sl/phone/msg.js';
$ajax = 'ajax/sl/notify_studio_ajax.php';
$cur = $d['cur'] ?? null;
$ch = (string) ($d['channel'] ?? '');
$ed = $cur ? ($d['editor'][$ch] ?? ['supported' => false]) : ['supported' => false];
$chL = (array) ($d['channels'] ?? []);
$canEdit = !empty($d['canEdit']);
$fill = function (string $t) use ($d): string {
    $map = [];
    foreach ((array) ($d['sample'] ?? []) as $k => $v) if (is_scalar($v)) $map['{' . $k . '}'] = (string) $v;
    return strtr($t, $map);
};
$isEmail = $ch === 'email';
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-msg.css')) ?>">
<?= sl_phoneTop('Notification studio', $cur['label'] ?? '', ['back' => sl_route('NotificationCenter')], sl_phoneIcon('more', 'Open the full page', sl_url(['view' => 'web']))) ?>
<main class="mx-body">
<?php if (!$cur): ?>
<div class="mx-ban info"><i class="ic ic-sm i-info" aria-hidden="true"></i><span>There is nothing to edit yet.</span></div>
<?php else: ?>
<label class="mx-f"><small>Send</small><span class="pm-sel"><select class="mx-in" aria-label="Which status or action" onchange="if(this.value)location.href=this.value">
<?php foreach ($d['groups'] as $g): ?><optgroup label="<?= h($g['label']) ?>"><?php foreach ($g['items'] as $it): ?><option value="<?= h($it['href']) ?>"<?= $it['key'] === $cur['key'] ? ' selected' : '' ?>>When: <?= h($it['label']) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?>
</select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
<label class="mx-f"><small>Channel</small><div class="mx-fseg"><?php foreach ($cur['channels'] as $c): ?><a class="<?= $c === $ch ? 'on' : '' ?>" href="<?= h(sl_url(['t' => $cur['key'], 'ch' => $c])) ?>"<?= $c === $ch ? ' aria-current="true"' : '' ?>><?= h($chL[$c] ?? ucfirst($c)) ?></a><?php endforeach; ?></div></label>
<?php if (empty($ed['supported'])): ?>
<div class="mx-ban info"><i class="ic ic-sm i-info" aria-hidden="true"></i><span>This channel is not used for this message.</span></div>
<?php else: ?>
<section class="mx-card pm-wa"><div class="mx-msg"><p data-pm-preview><?php if ($isEmail): ?><b><?= h($fill((string) $ed['subject'])) ?></b><?php else: ?><?= h($fill((string) $ed['body'])) ?><?php endif; ?></p><small>Preview<?= $ed['updated'] !== '' ? ' · saved ' . h($ed['updated']) : '' ?></small></div></section>
<form id="pm-ns" data-pm-studio="<?= h($ajax) ?>">
<input type="hidden" name="op" value="save"><input type="hidden" name="key" value="<?= h($cur['key']) ?>"><input type="hidden" name="channel" value="<?= h($ch) ?>">
<input type="hidden" name="enabled" id="pm-ns-on" value="<?= !empty($ed['enabled']) ? 1 : 0 ?>">
<input type="hidden" name="blocks" value="<?= h(json_encode($ed['blocks'] ?? [])) ?>">
<section class="mx-card mx-tgs"><div class="mx-tg"><span class="mx-rm"><b>Send on <?= h($chL[$ch] ?? $ch) ?></b><small><?= h($ed['builtin'] !== '' ? 'Not saved: ' . $ed['builtin'] : 'Custom message') ?></small></span><button type="button" class="mx-sw<?= !empty($ed['enabled']) ? ' on' : '' ?>" role="switch" aria-checked="<?= !empty($ed['enabled']) ? 'true' : 'false' ?>" aria-label="Send on <?= h($chL[$ch] ?? $ch) ?>" data-input="#pm-ns-on"<?= $canEdit ? '' : ' disabled' ?>></button></div></section>
<section class="mx-card mx-form">
<?php if ($isEmail): ?>
<label class="mx-f"><small>Subject</small><input class="mx-in" name="subject" value="<?= h($ed['subject']) ?>" data-pm-body<?= $canEdit ? '' : ' readonly' ?>></label>
<input type="hidden" name="body" value="">
<p class="mx-note">This e-mail is built from <?= count((array) $ed['blocks']) ?> blocks. Changing the blocks is easier on a computer.</p>
<?php else: ?>
<input type="hidden" name="subject" value="<?= h($ed['subject']) ?>">
<label class="mx-f"><small>Message</small><textarea class="mx-in" name="body" rows="6" maxlength="<?= (int) ($d['limits'][$ch] ?? 4000) ?>" data-pm-body<?= $canEdit ? '' : ' readonly' ?>><?= h($ed['body']) ?></textarea></label>
<?php endif; ?>
</section>
<?php if ($d['vars']): ?><div class="mx-chips"><?php foreach ($d['vars'] as $v): if (!empty($v['off'])) continue; ?><button type="button" data-pm-var="{<?= h($v['key']) ?>}" title="<?= h($v['desc']) ?>">{<?= h($v['key']) ?>}</button><?php endforeach; ?></div><?php endif; ?>
<?php if ($canEdit): ?><div class="mx-btns"><button class="mx-btn" type="submit">Save template</button><button class="mx-btn sec" type="button" data-pm-ns-test>Send me a test</button></div><?php endif; ?>
</form>
<script type="application/json" id="pm-sample"><?= json_encode(array_filter((array) ($d['sample'] ?? []), 'is_scalar'), JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>
<?php endif; ?>
</main>
