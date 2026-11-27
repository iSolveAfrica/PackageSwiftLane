<?php
/**
 * Drag-and-drop e-mail canvas (shared editor kit, WP-B).
 *
 * The markup that dataJs/sl/email_builder.js mounts with
 * window.SLEmailCanvas.mount(rootEl, options). Every element the script uses
 * carries data-ec="<name>", so one page can hold several canvases.
 *
 * Set $ec before the include (all keys optional):
 *   prefix       string  id prefix ('eb' gives the historical ids eb-mail, eb-insp, ...; '' = no ids)
 *   tags         array   ['[FIRST_NAME]' => 'First name', ...] or ['{first_name}' => 'First name', ...]
 *   tabs         array   subset of ['blocks', 'tpl', 'saved', 'vars'] (default all four)
 *   toolbar      bool    device switch, Undo and Preview inside the canvas (default true)
 *   subject_line bool    "Subject: ..." line above the e-mail (default false)
 *   template     bool    wrap in <template data-ec-template> so comms_editor.js can clone it (default false)
 *   class        string  extra classes on the root
 *   tags_hint    string  text above the tag buttons
 * Styles: assets/swiftlane/comms_editor.css (scoped to .ec-canvas); the Email
 * builder page still has its own copy in pages/EmailBuilder.css.
 */
$ec = (isset($ec) && is_array($ec) ? $ec : []) + [
    'prefix' => '', 'tags' => [], 'tabs' => ['blocks', 'tpl', 'saved', 'vars'], 'toolbar' => true,
    'subject_line' => false, 'template' => false, 'class' => '',
    'tags_hint' => 'Click to put a tag into the selected block\'s text. Do not change the words inside the tag.',
];
$ecP = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $ec['prefix']);
$ecId = static function (string $n) use ($ecP): string {
    return $ecP !== '' ? ' id="' . $ecP . '-' . $n . '"' : '';
};
$ecTabs = array_values(array_intersect(['blocks', 'tpl', 'saved', 'vars'], (array) $ec['tabs'])) ?: ['blocks'];
$ecTabLabels = ['blocks' => 'Blocks', 'tpl' => 'Templates', 'saved' => 'Saved', 'vars' => 'Tags'];
?>
<?php if ($ec['template']): ?><template data-ec-template><?php endif; ?>
<div class="eb ec-canvas<?= $ec['class'] !== '' ? ' ' . h($ec['class']) : '' ?>"<?= $ecId('design') ?> data-ec-root>
<aside class="sl-card col g12 ec-side" style="padding:14px"><div class="eb-tabs" data-ec="ltabs"<?= $ecId('ltabs') ?> role="tablist" aria-label="Canvas tools"><?php foreach ($ecTabs as $i => $t): ?><button type="button" role="tab"<?= $i === 0 ? ' class="on" aria-selected="true"' : ' aria-selected="false"' ?> data-t="<?= $t ?>"><?= $ecTabLabels[$t] ?></button><?php endforeach; ?></div>
<?php if (in_array('blocks', $ecTabs, true)): ?><div data-lt="blocks" class="col g8"<?= $ecTabs[0] !== 'blocks' ? ' hidden' : '' ?>><span class="mut sm">Drag onto the e-mail or click to add below the selected block</span><div class="eb-pal" data-ec="pal"<?= $ecId('pal') ?>></div></div><?php endif; ?>
<?php if (in_array('tpl', $ecTabs, true)): ?><div data-lt="tpl" class="col g4"<?= $ecTabs[0] !== 'tpl' ? ' hidden' : '' ?>><span class="mut sm">Start from a template. Your current design is replaced.</span><div class="col g4" data-ec="tpls"<?= $ecId('tpls') ?>></div></div><?php endif; ?>
<?php if (in_array('saved', $ecTabs, true)): ?><div data-lt="saved" class="col g4"<?= $ecTabs[0] !== 'saved' ? ' hidden' : '' ?>><span class="mut sm">Designs saved on this installation</span><div class="col g4" data-ec="saved"<?= $ecId('saved') ?>><span class="mut sm">Loading…</span></div></div><?php endif; ?>
<?php if (in_array('vars', $ecTabs, true)): ?><div data-lt="vars" class="col g8"<?= $ecTabs[0] !== 'vars' ? ' hidden' : '' ?> data-ec="vars"><span class="mut sm"><?= h($ec['tags_hint']) ?></span><div class="row wrap g6"><?php foreach ((array) $ec['tags'] as $k => $l): ?><button type="button" class="eb-var" title="<?= h(is_array($l) ? ($l[0] ?? '') : $l) ?>" data-var="<?= h($k) ?>"><?= h($k) ?></button><?php endforeach; ?></div></div><?php endif; ?>
<div class="col g8" style="border-top:1px solid var(--line);padding-top:12px"><b class="fw5 sm">E-mail colours</b><div class="grid cols2" style="gap:8px" data-ec="glob"<?= $ecId('glob') ?>></div></div>
</aside>
<section class="col g8 ec-main"><div class="row between wrap g8 sm"><?php if ($ec['subject_line']): ?><span class="mut">Subject: <b class="fw5" style="color:var(--ink)" data-ec="subj-shown"<?= $ecId('subj-shown') ?>>(none yet)</b></span><?php else: ?><span></span><?php endif; ?>
<span class="row g8 wrap"><?php if ($ec['toolbar']): ?><span class="sl-seg boxed ec-dev" data-ec="dev" role="group" aria-label="Canvas width"><button type="button" class="on" data-w="600"><i class="ic ic-sm i-monitor" aria-hidden="true"></i>Desktop</button><button type="button" data-w="375"><i class="ic ic-sm i-phone" aria-hidden="true"></i>Phone</button></span><button type="button" class="sl-btn ghost sm" data-ec="undo"><i class="ic ic-sm i-reply" aria-hidden="true"></i>Undo</button><?php endif; ?><span class="mut" data-ec="count"<?= $ecId('count') ?>>0 blocks · 600 px</span></span></div>
<section class="eb-insp sl-card col g12" data-ec="insp"<?= $ecId('insp') ?> hidden aria-label="Block settings"></section><div class="eb-stage" data-ec="stage"<?= $ecId('stage') ?>><div class="eb-mail" data-ec="mail"<?= $ecId('mail') ?>></div></div></section>
<input type="file" data-ec="file"<?= $ecId('file') ?> accept="image/jpeg,image/png,image/gif,image/webp" hidden aria-label="Upload an image">
</div>
<?php if ($ec['template']): ?></template><?php endif; ?>
<?php unset($ec, $ecP, $ecId, $ecTabs, $ecTabLabels); ?>
