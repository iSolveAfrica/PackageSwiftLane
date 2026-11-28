<?php
/**
 * M-CsRules: ticket rules on a phone. Same $d as the web page (sl_csRulesData). Administrators switch a rule
 * on or off (admin_ajax.php action=rule_status) and add one from a ready-made template (action=rule_template);
 * the full rule builder stays on the web page.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['scripts'][] = 'dataJs/sl/cs_common.js';
$slPhone['scripts'][] = 'dataJs/sl/phone/cs_desk.js';
$admin = !empty($d['isAdmin']);
$free = array_values(array_filter($d['templates'], fn($t) => !$t['added']));
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-desk.css')) ?>">
<?= sl_phoneTop('Ticket rules', '', ['back' => sl_route('CsInbox')], sl_phoneIcon('cog', 'Support settings', sl_route('CsChannels'))) ?>
<main class="mx-body">
<?php if ($d['rules']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['rules'] as $r): ?>
<div class="mx-row"><span class="mx-li"><i class="ic i-filter" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($r['name']) ?></b><small class="pd-clamp"><?= h($r['sentence']) ?></small><small><?= h($r['footer']) ?></small></span><span class="mx-rr"><?php if ($admin): ?><button type="button" class="mx-sw<?= $r['on'] ? ' on' : '' ?>" role="switch" aria-checked="<?= $r['on'] ? 'true' : 'false' ?>" aria-label="<?= h($r['name']) ?>" data-pd-rule="<?= (int) $r['id'] ?>"></button><?php else: ?><span class="sl-pill <?= $r['on'] ? 'delivered' : 'plain' ?>"><?= $r['on'] ? 'On' : ($r['status'] === 'draft' ? 'Draft' : 'Off') ?></span><?php endif; ?></span></div>
<?php endforeach; ?>
</section>
<?php elseif ($d['ready']): ?>
<?= sl_mxEmpty('filter', 'No rules yet', 'Rules route, tag and escalate tickets by themselves.') ?>
<?php else: ?>
<?= sl_mxEmpty('filter', 'Helpdesk not installed', 'Ask an administrator to run the support migration.') ?>
<?php endif; ?>
<?php if ($admin && $d['ready']): ?><div class="mx-btns"><button type="button" class="mx-btn" data-sl-open="pd-tpl">New rule</button></div><?php endif; ?>
</main>
<?php if ($admin && $d['ready']): ?>
<div class="sl-dialog pd-sheet" id="pd-tpl" hidden><div role="dialog" aria-label="New rule">
<h2>New rule from a template</h2><p class="pd-note">Each template is added ready to use; change its details on the computer.</p>
<?php if ($free): ?><section class="mx-card mx-list"><?php foreach ($free as $tp): ?><button type="button" class="mx-row" style="width:100%;background:none;border:0;text-align:left;font:inherit;color:inherit;cursor:pointer" data-cs-action="rule_template" data-cs-url="ajax/support/admin_ajax.php" data-p-key="<?= h($tp['key']) ?>" data-cs-reload><span class="mx-li"><i class="ic <?= h($tp['icon']) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($tp['name']) ?></b><small><?= h($tp['sub']) ?></small></span><i class="ic ic-sm mx-ch i-plus" aria-hidden="true"></i></button><?php endforeach; ?></section>
<?php else: ?><?= sl_mxEmpty('check', 'Every template is added', 'Build a custom rule on the computer.') ?><?php endif; ?>
<button type="button" class="mx-btn sec" data-sl-close>Cancel</button>
</div></div>
<?php endif; ?>
