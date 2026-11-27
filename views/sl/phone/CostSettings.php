<?php
/**
 * M-Costs: what it costs to run. Same $d as the web page (views/sl/data/costs.php). "Add cost" opens a form
 * posting to the web endpoint ajax/sl/cost_settings_ajax.php (op=save, new[name|amt|cur|cat|freq]);
 * remove posts op=delete&id. Only people who may edit see those controls (super admin / manage_operating_costs).
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$ajax = 'ajax/sl/cost_settings_ajax.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
$slPhone['scripts'][] = 'dataJs/sl/phone/settings.js';
$icons = ['infra' => 'i-cloud', 'messages' => 'i-message', 'rent' => 'i-warehouse', 'staff' => 'i-users', 'fuel' => 'i-truck', 'software' => 'i-cpu', 'customs' => 'i-shield', 'other' => 'i-dollar'];
$amt = fn($o) => ($o['cur'] === 'USD' ? 'US$' : 'GH₵') . number_format((float) $o['amt'], (float) $o['amt'] == floor((float) $o['amt']) ? 0 : 2);
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-settings.css')) ?>">
<?= sl_phoneTop('Operating costs', $d['ready'] && $d['rows'] ? 'About ' . $d['grandF'] . ' a month' : '', ['back' => sl_route('SettingsHub')], ($user->cdp_hasPermission('view_financial_overview') ? sl_phoneIcon('chart', 'Financial overview', sl_route('FinancialOverview')) : '')) ?>
<main class="mx-body">
<?php if (!$d['ready']): ?>
<section class="mx-card"><?= sl_mxEmpty('dollar', 'Costs table not installed', 'Run the finance database upgrade to record operating costs.') ?></section>
<?php else: ?>
<section class="mx-card mx-list">
<?php foreach ($d['rows'] as $o): ?>
<div class="mx-row"><span class="mx-li"><i class="ic <?= h($icons[$o['cat']] ?? 'i-dollar') ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($o['name']) ?></b><small><?= h(($d['cats'][$o['cat']] ?? ucfirst($o['cat'])) . ' · ' . ($d['freqs'][$o['freq']] ?? $o['freq']) . ($o['unit'] ? ' · ' . $o['unit'] : '')) ?></small></span><span class="mx-rr"><b><?= h($amt($o)) ?></b></span><?php if ($d['canEdit']): ?><button class="mx-ib" type="button" aria-label="Remove <?= h($o['name']) ?>" data-sl-action="<?= h($ajax) ?>" data-p-op="delete" data-p-id="<?= (int) $o['id'] ?>" data-sl-confirm="Remove <?= h($o['name']) ?>?" data-sl-reload><i class="ic ic-sm i-trash" aria-hidden="true"></i></button><?php endif; ?></div>
<?php endforeach; ?>
<?php foreach ($d['msgCount'] as $m): ?>
<a class="mx-row" href="<?= h(sl_route('MessageCosts')) ?>"><span class="mx-li"><i class="ic i-message" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h(ucfirst($m->channel)) ?> messages</b><small>From usage this month</small></span><span class="mx-rr"><b><?= h(sl_num($m->n)) ?> sent</b></span></a>
<?php endforeach; ?>
<?php if (!$d['rows'] && !$d['msgCount']): ?><?= sl_mxEmpty('dollar', 'No cost lines yet', $d['canEdit'] ? 'Add what you pay for servers, rent, staff and messages.' : 'A super admin adds them.') ?><?php endif; ?>
</section>
<?php if ($d['rows']): ?><section class="mx-card"><div class="ph-total"><span>Total per month</span><b><?= h($d['grandF']) ?></b></div></section><?php endif; ?>
<?php if ($d['canEdit']): ?>
<form data-sl-form data-sl-reload action="<?= h($ajax) ?>" method="post">
<div class="mx-card mx-form ph-form" id="cs-add" hidden>
<input type="hidden" name="op" value="save">
<label class="mx-f"><small>Name</small><input class="mx-in" name="new[name]" maxlength="120" placeholder="e.g. Office rent" autocomplete="off"></label>
<div class="mx-two"><label class="mx-f"><small>Amount</small><input class="mx-in" name="new[amt]" inputmode="decimal" placeholder="0.00" autocomplete="off"></label><label class="mx-f"><small>Currency</small><select class="mx-in" name="new[cur]"><option>GHS</option><option>USD</option></select></label></div>
<div class="mx-two"><label class="mx-f"><small>Category</small><select class="mx-in" name="new[cat]"><?php foreach ($d['cats'] as $k => $v): ?><option value="<?= h($k) ?>"><?= h($v) ?></option><?php endforeach; ?></select></label><label class="mx-f"><small>How often</small><select class="mx-in" name="new[freq]"><?php foreach ($d['freqs'] as $k => $v): ?><option value="<?= h($k) ?>"><?= h($v) ?></option><?php endforeach; ?></select></label></div>
<button class="mx-btn" type="submit">Save cost</button>
</div>
</form>
<div class="mx-btns"><button class="mx-btn" type="button" data-ph-show="#cs-add" aria-controls="cs-add" aria-expanded="false">Add cost</button></div>
<?php else: ?>
<div class="mx-ban info"><i class="ic ic-sm i-lock" aria-hidden="true"></i><span>Only the super admin can change operating costs.</span></div>
<?php endif; ?>
<?php endif; ?>
</main>
