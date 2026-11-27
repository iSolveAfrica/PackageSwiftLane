<?php
/**
 * M-EmailBuilder for newsletter.php (the drag-and-drop e-mail builder). On a phone: the saved designs, and for one design
 * (?design=id) its blocks, a preview of the saved e-mail, and its name / subject / preview text. Saving and test sends use
 * ajax/tools/newsletter_ajax.php (action save | test) with the design's own JSON and HTML, unchanged.
 * Arranging and editing blocks stays on a computer.
 */
require_once CDP_ROOT . '/views/sl/data/phone_msg.php';
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['scripts'][] = 'dataJs/sl/phone/msg.js';
$cfg = $d['cfg'] ?? [];
$ajax = (string) ($cfg['ajax'] ?? 'ajax/tools/newsletter_ajax.php');
$did = (int) ($_GET['design'] ?? 0);
$one = $did > 0 ? sl_pmDesign($did) : null;
$myMail = (string) ($userData->email ?? '');
$bIcon = ['logo' => 'image', 'heading' => 'type', 'text' => 'type', 'footer' => 'file', 'image' => 'image', 'button' => 'link', 'divider' => 'minus', 'spacer' => 'minus', 'imagetext' => 'image', 'columns' => 'grid', 'video' => 'play', 'social' => 'users', 'html' => 'code'];
$bText = function (array $b): string {
    $p = (array) ($b['props'] ?? []);
    foreach (['text', 'html', 'title', 'label', 'heading', 'alt', 'url', 'src'] as $k) {
        if (isset($p[$k]) && is_string($p[$k]) && trim(strip_tags($p[$k])) !== '') return mb_substr(trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($p[$k]), ENT_QUOTES, 'UTF-8'))), 0, 70);
    }
    return '';
};
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-msg.css')) ?>">
<?php if ($one): $r = $one['row']; $slPhone['nav'] = 'none'; ?>
<?= sl_phoneTop($r->name, $r->subject, ['back' => sl_url(['design' => null])], sl_phoneIcon('monitor', 'Edit blocks on the full page', sl_url(['view' => 'web', 'design' => null]))) ?>
<main class="mx-body" data-mx-scope>
<div class="mx-seg" role="tablist" data-mx-seg><button type="button" role="tab" class="on" aria-pressed="true" data-mx-panel="bl">Blocks</button><button type="button" role="tab" aria-pressed="false" data-mx-panel="pv">Preview</button><button type="button" role="tab" aria-pressed="false" data-mx-panel="st">Settings</button></div>
<div class="mx-sec" data-mx-panel-of="bl"><h2>Blocks in this email</h2><small class="mx-note"><?= count($one['blocks']) ?></small></div>
<section class="mx-card mx-list" data-mx-panel-of="bl">
<?php foreach ($one['blocks'] as $b): $type = (string) ($b['type'] ?? 'text'); ?>
<div class="mx-row"><span class="mx-li"><i class="ic i-<?= h($bIcon[$type] ?? 'box') ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h(ucfirst($type === 'imagetext' ? 'Image + text' : $type)) ?></b><?php $tx = $bText($b); if ($tx !== ''): ?><small>“<?= h($tx) ?>”</small><?php endif; ?></span></div>
<?php endforeach; ?>
<?php if (!$one['blocks']): ?><div class="mx-row"><span class="mx-rm"><b>No blocks yet</b></span></div><?php endif; ?>
</section>
<div data-mx-panel-of="pv" hidden><iframe class="pm-prev" title="Preview of <?= h($r->name) ?>" sandbox="" srcdoc="<?= h((string) $r->html) ?>"></iframe></div>
<form id="pm-nl" data-mx-panel-of="st" hidden data-pm-nl="<?= h($ajax) ?>">
<input type="hidden" name="id" value="<?= (int) $r->id ?>">
<input type="hidden" name="design_json" value="<?= h((string) $r->design_json) ?>">
<input type="hidden" name="html" value="<?= h((string) $r->html) ?>">
<section class="mx-card mx-form">
<label class="mx-f"><small>Design name</small><input class="mx-in" name="name" value="<?= h($r->name) ?>" required></label>
<label class="mx-f"><small>Subject</small><input class="mx-in" name="subject" value="<?= h($r->subject) ?>"></label>
<label class="mx-f"><small>Preview text</small><input class="mx-in" name="preheader" value="<?= h($r->preheader) ?>"></label>
<label class="mx-f"><small>Send tests to</small><input class="mx-in" type="email" name="emails" value="<?= h($myMail) ?>" autocomplete="email" inputmode="email"></label>
</section>
</form>
<p class="mx-note">Large layouts are easier on a computer; on a phone you can check the blocks, preview, change the subject and send tests.</p>
<div class="mx-btns"><button class="mx-btn" type="button" data-pm-nl-act="test">Send me a test</button><button class="mx-btn sec" type="button" data-pm-nl-act="save">Save</button></div>
</main>
<?php else: $designs = sl_pmDesigns(); ?>
<?= sl_phoneTop('Email builder', 'Saved designs', ['back' => sl_route('CommsHub')], sl_phoneIcon('more', 'Open the full page', sl_url(['view' => 'web']))) ?>
<main class="mx-body">
<?php if (empty($cfg['ready'])): ?><div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>The newsletter tables could not be created.</span></div><?php endif; ?>
<?php if ($designs): ?>
<section class="mx-card mx-list">
<?php foreach ($designs as $x): ?>
<a class="mx-row" href="<?= h(sl_url(['design' => (int) $x->id])) ?>"><span class="mx-li"><i class="ic i-mail" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($x->name) ?></b><small><?= h($x->subject !== '' ? $x->subject : 'No subject') ?> · edited <?= h(sl_ago($x->updated_at)) ?></small></span><span class="mx-rr"><?= $x->last_sent_at ? sl_pill('Sent', 'delivered') . '<small>' . h(sl_ago($x->last_sent_at)) . '</small>' : sl_pill('Draft', 'plain') ?></span></a>
<?php endforeach; ?>
</section>
<?php else: ?>
<?= sl_mxEmpty('mail', 'No saved designs yet', 'E-mails are designed with blocks on a computer. Saved designs show here to preview and test.') ?>
<?php endif; ?>
<p class="mx-note">Building an e-mail with blocks is easier on a computer; on a phone you can preview designs, change the subject and send tests.</p>
<div class="mx-btns"><a class="mx-btn sec" href="templates_email.php">System email templates</a></div>
</main>
<?php endif; ?>
