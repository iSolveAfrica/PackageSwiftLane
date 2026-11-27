<?php
/**
 * M-Templates for templates_whatsapp.php / templates_sms.php / templates_email.php / templates_default.php.
 * List state (no ?id): the design's tabs and list. ?id=N (or ?new=1 on WhatsApp) opens one template, laid out like the
 * notification studio screen. Writes: ajax/sl/templates_ajax.php (op save | add | delete | test | defaults), the web page's fields.
 */
$slPhone['scripts'][] = 'dataJs/sl/phone/msg.js';
$ajax = 'ajax/sl/templates_ajax.php';
$tab = $d['tab'];
$t = $d['cur'] ?? null;
$editing = !empty($d['isEdit']) && $t && (isset($_GET['id']) || !empty($d['new']));
$tabs = array_values(array_filter($d['tabs'], fn($x) => $x['k'] !== 'default' || $user->cdp_hasPermission(['manage_whatsapp_templates', 'view_tools'])));
$icon = ['whatsapp' => 'message', 'sms' => 'phone', 'email' => 'mail'][$tab] ?? 'file';
$title = ['whatsapp' => 'SMS & WhatsApp', 'sms' => 'SMS & WhatsApp', 'email' => 'Email templates', 'default' => 'Default templates'][$tab] ?? 'Templates';
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-msg.css')) ?>">
<?php if ($editing): $slPhone['nav'] = 'none'; $listUrl = sl_url(['id' => null, 'new' => null]); ?>
<?= sl_phoneTop(!empty($d['new']) ? 'New WhatsApp template' : $t['name'], !empty($d['new']) ? '' : 'Template #' . (int) $t['id'], ['back' => $listUrl],
    $d['isWa'] && empty($d['new']) ? '<button type="button" class="mx-ib" aria-label="Delete this template" data-sl-action="' . h($ajax) . '" data-p-op="delete" data-p-kind="whatsapp" data-p-id="' . (int) $t['id'] . '" data-sl-confirm="Delete this WhatsApp template?"><i class="ic i-trash" aria-hidden="true"></i></button>' : '') ?>
<main class="mx-body">
<section class="mx-card pm-wa"><div class="mx-msg"><p data-pm-preview><?= h($d['preview']) ?></p><small>Preview · <?= h($d['sample']['name']) ?></small></div></section>
<form id="pm-tpl" data-sl-form action="<?= h($ajax) ?>" method="post"<?= !empty($d['new']) ? '' : ' data-sl-reload' ?>>
<input type="hidden" name="op" value="<?= !empty($d['new']) ? 'add' : 'save' ?>"><input type="hidden" name="kind" value="<?= h($tab) ?>"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
<section class="mx-card mx-form">
<label class="mx-f"><small>Template title</small><input class="mx-in" name="name" value="<?= h($t['name']) ?>" required></label>
<label class="mx-f"><small><?= $d['isWa'] ? 'Description' : 'Subject' ?></small><input class="mx-in" name="subject" value="<?= h($t['subject']) ?>"></label>
<label class="mx-f"><small>Message<?= $d['isHtml'] ? ' (HTML)' : '' ?></small><textarea class="mx-in" name="body" rows="<?= $d['isHtml'] ? 10 : 6 ?>" data-pm-body data-pm-fill="tpl"<?= $d['isHtml'] ? ' style="font-family:var(--mono);font-size:14px"' : '' ?>><?= h($d['body']) ?></textarea></label>
<p class="mx-note"><span data-pm-count><?= h($d['countLabel']) ?></span><?= $d['costLabel'] !== '' ? ' · ' . h($d['costLabel']) : '' ?><?= $d['help'] ? ' · ' . h(sl_msgPlain($d['help'])) : '' ?></p>
</section>
<div class="mx-chips"><?php foreach ($d['vars'] as $v): ?><button type="button" data-pm-var="<?= h($v['k']) ?>"><?= h($v['k']) ?></button><?php endforeach; ?></div>
<div class="mx-btns"><button class="mx-btn" type="submit"><?= !empty($d['new']) ? 'Add template' : 'Save template' ?></button><?php if (empty($d['new'])): ?><button class="mx-btn sec" type="button" data-pm-test="<?= h($ajax) ?>" data-p-op="test">Send me a test</button><?php endif; ?></div>
</form>
<script type="application/json" id="pm-sample"><?= json_encode(['name' => $d['sample']['name'], 'first' => $d['sample']['first'], 'locker' => $d['sample']['locker'], 'site' => $core->site_name ?? 'Swift Lane', 'sms' => $tab === 'sms'], JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
</main>
<?php else: ?>
<?= sl_phoneTop($title, '', ['back' => sl_route('CommsHub')], sl_phoneIcon('more', 'Open the full page', sl_url(['view' => 'web']))) ?>
<main class="mx-body">
<nav class="mx-seg" aria-label="Template type"><?php foreach ($tabs as $x): ?><a class="<?= h($x['cls']) ?>" href="<?= h($x['href']) ?>"<?= $x['cls'] ? ' aria-current="page"' : '' ?>><?= h($x['k'] === 'default' ? 'Default' : $x['label']) ?> <?= (int) $x['n'] ?></a><?php endforeach; ?></nav>
<?php if (!empty($d['isDef'])): ?>
<form id="pm-def" data-sl-form action="<?= h($ajax) ?>" method="post"><input type="hidden" name="op" value="defaults">
<section class="mx-card mx-tgs">
<?php foreach ($d['defs'] as $i => $r): ?>
<input type="hidden" name="id[<?= $i ?>]" value="<?= (int) $r['id'] ?>"><input type="hidden" name="active[<?= $i ?>]" id="pm-def-<?= $i ?>" value="<?= $r['on'] ? 1 : 0 ?>">
<div class="mx-tg"><span class="mx-rm"><b><?= h($r['n']) ?></b><span class="pm-sel" style="margin-top:6px"><select class="mx-in" name="id_template[<?= $i ?>]" aria-label="WhatsApp template for <?= h($r['n']) ?>"><?php foreach ($d['waList'] as $w): ?><option value="<?= (int) $w['id'] ?>"<?= $w['id'] === $r['tpl'] ? ' selected' : '' ?>><?= h($w['name']) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></span><button type="button" class="mx-sw<?= $r['on'] ? ' on' : '' ?>" role="switch" aria-checked="<?= $r['on'] ? 'true' : 'false' ?>" aria-label="<?= h($r['n']) ?>" data-input="#pm-def-<?= $i ?>"></button></div>
<?php endforeach; ?>
</section>
<div class="mx-btns"><button class="mx-btn" type="submit">Save</button></div>
</form>
<?php else: ?>
<div class="mx-srch"><form method="get" role="search"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q']) ?>" aria-label="Search templates" placeholder="Search templates"></label></form></div>
<?php if ($d['list']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['list'] as $l): ?>
<a class="mx-row" href="<?= h(sl_url(['id' => $l['id']])) ?>"><span class="mx-li"><i class="ic i-<?= $icon ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($l['name']) ?></b><small><?= h($l['sub'] !== '' ? $l['sub'] : '#' . $l['id']) ?></small></span><span class="mx-rr"><?= sl_pill($l['who'], 'plain') ?></span></a>
<?php endforeach; ?>
</section>
<?php else: ?><div class="mx-ban info"><i class="ic ic-sm i-search" aria-hidden="true"></i><span><?= $d['q'] !== '' ? 'No template matches.' : 'No templates of this type.' ?></span></div><?php endif; ?>
<?php if ($tab === 'email'): ?><p class="mx-note">Newsletters are designed in the <a href="<?= h(sl_route('EmailBuilder')) ?>">email builder</a>.</p><?php endif; ?>
<?php endif; ?>
</main>
<?php if (!empty($d['isWa'])): ?><a class="mx-fab" href="templates_whatsapp.php?new=1" aria-label="New template"><i class="ic i-plus" aria-hidden="true"></i></a><?php endif; ?>
<?php endif; ?>
