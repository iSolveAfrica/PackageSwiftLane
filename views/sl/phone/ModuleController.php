<?php
/**
 * M-Modules: switch whole parts of the system on or off. Same $d as the web page (module_controller.php).
 * Each switch posts {key, value} to ajax/sl/modules_ajax.php (op defaults to toggle) after the same
 * confirmation text the web page shows; core modules and a finance module pinned in config are locked.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$ajax = 'ajax/sl/modules_ajax.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
$slPhone['scripts'][] = 'dataJs/sl/phone/settings.js';
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-settings.css')) ?>">
<?= sl_phoneTop('Modules', (int) $d['onCount'] . ' of ' . (int) $d['switchable'] . ' on', ['back' => sl_route('SettingsHub')]) ?>
<main class="mx-body">
<?php if ($d['offCount']): ?><div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><?= (int) $d['offCount'] ?> module<?= $d['offCount'] > 1 ? 's are' : ' is' ?> off.</span></div><?php endif; ?>
<?php foreach ($d['groups'] as $g): ?>
<div class="mx-sec"><h2><?= h($g['label'] ?? ucfirst($g['key'])) ?></h2><small><?= h($g['count']) ?></small></div>
<section class="mx-card mx-tgs">
<?php foreach ($g['items'] as $m):
    $locked = $m['core'] || $m['pinned'];
    $sub = $m['core'] ? 'Core · always on' : ($m['pinned'] ? 'Pinned in config' : $m['desc']); ?>
<div class="mx-tg"><span class="mx-rm"><b><?= h($m['name']) ?></b><small><?= h($sub) ?></small></span><button type="button" class="mx-sw<?= $m['on'] ? ' on' : '' ?>" role="switch" aria-checked="<?= $m['on'] ? 'true' : 'false' ?>" aria-label="<?= h($m['name']) ?>"<?= $locked ? ' disabled aria-disabled="true"' : ' data-sl-save="' . h($ajax) . '" data-key="' . h($m['id']) . '" data-p-op="toggle" data-ph-confirm="' . h($m['confirm']) . '"' ?>></button></div>
<?php endforeach; ?>
</section>
<?php endforeach; ?>
<div class="mx-ban info"><i class="ic ic-sm i-sliders" aria-hidden="true"></i><span>Turning a module off hides it from the menu, home and every screen for all staff and customers. Nothing is deleted.</span></div>
</main>
