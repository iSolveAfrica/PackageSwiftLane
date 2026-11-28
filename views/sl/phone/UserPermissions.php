<?php
/**
 * M-Permissions: what one person may do. Same $d as the web page (views/sl/data/account_edit.php
 * sl_userPermsData). A switch shows the effective right; flipping it saves through the unchanged
 * ajax/users/users_overrides_save_ajax.php (user_id, changes[action_id]): back to the role's own value
 * saves "inherit", anything else saves "allow" / "deny" for this person only.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$t = $d['target'];
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
$slPhone['scripts'][] = 'dataJs/sl/phone/settings.js';
$open = true;
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-settings.css')) ?>">
<?= sl_phoneTop('Permissions', $d['name'] . ' · ' . $d['role'], ['back' => 'users_edit.php?user=' . (int) $t->id], sl_phoneIcon('user', 'Edit ' . $d['name'], 'users_edit.php?user=' . (int) $t->id)) ?>
<main class="mx-body">
<div class="mx-srch"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" aria-label="Filter permissions" placeholder="Filter permissions" data-mx-menu-q></label></div>
<div class="mx-ban info"><i class="ic ic-sm i-key" aria-hidden="true"></i><span>Role: <?= h($d['role']) ?>. Switches start from the role; a change applies to <?= h($d['name']) ?> only and saves at once.<?= $d['overrideCount'] ? ' ' . (int) $d['overrideCount'] . ' set for this person.' : '' ?></span></div>
<?php foreach ($d['modules'] as $module => $actions): ?>
<details class="mx-grp"<?= $open ? ' open' : '' ?> data-mx-menu-group><summary><span><?= h($module ?: 'Module') ?></span><small><?= count(array_filter($actions, fn($a) => $a['state'] === 'allow' || ($a['state'] === 'inherit' && $a['roleOn']))) ?>/<?= count($actions) ?></small><i class="ic ic-sm i-down" aria-hidden="true"></i></summary><section class="mx-card mx-tgs">
<?php foreach ($actions as $a): $eff = $a['state'] === 'allow' || ($a['state'] === 'inherit' && $a['roleOn']); ?>
<div class="mx-tg" data-mx-menu-item><span class="mx-rm"><b><?= h($a['label']) ?></b><small data-ph-state><?= $a['state'] === 'inherit' ? 'From role' : 'Set for this person' ?></small></span><button type="button" class="mx-sw<?= $eff ? ' on' : '' ?>" role="switch" aria-checked="<?= $eff ? 'true' : 'false' ?>" aria-label="<?= h($a['label']) ?>" data-ph-perm="<?= (int) $a['id'] ?>" data-user="<?= (int) $t->id ?>" data-role-on="<?= $a['roleOn'] ? 1 : 0 ?>"></button></div>
<?php endforeach; ?>
</section></details>
<?php $open = false; endforeach; ?>
<?php if (!$d['modules']): ?><section class="mx-card"><?= sl_mxEmpty('key', 'No permissions defined', '') ?></section><?php endif; ?>
</main>
