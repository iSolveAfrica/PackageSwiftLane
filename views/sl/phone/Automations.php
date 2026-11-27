<?php
/**
 * M-Automations: rules that run by themselves. Same $d as the web page (views/sl/data/automations.php).
 * The switch on each row pauses / starts it through ajax/sl/automations_ajax.php (op=toggle, id, value),
 * like the web switch. Building a rule needs the full builder, so "New automation" opens the
 * web builder (view=web).
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$ajax = 'ajax/sl/automations_ajax.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-settings.css')) ?>">
<?= sl_phoneTop('Automations', $d['ready'] ? (int) $d['onCount'] . ' on · ' . $d['totalRuns'] . ' runs' : '', ['back' => sl_route('SettingsHub')]) ?>
<main class="mx-body">
<?php if (!$d['ready']): ?>
<section class="mx-card"><?= sl_mxEmpty('bolt', 'Automations not installed', 'Run the database upgrade to use automations.') ?></section>
<?php else: ?>
<nav class="mx-seg" aria-label="Filter"><?php foreach ($d['lfs'] as $x): $on = $x['f'] === $d['lf']; ?><a class="<?= $on ? 'on' : '' ?>" href="<?= h(sl_url(['lf' => $x['f']])) ?>"<?= $on ? ' aria-current="true"' : '' ?>><?= h($x['label']) ?></a><?php endforeach; ?></nav>
<?php if ($d['suggested']): ?><div class="mx-ban info"><i class="ic ic-sm i-ai" aria-hidden="true"></i><span><?= count($d['suggested']) ?> suggested automation<?= count($d['suggested']) > 1 ? 's' : '' ?> waiting for review on the full website.</span></div><?php endif; ?>
<section class="mx-card mx-list">
<?php foreach ($d['rows'] as $r): ?>
<div class="mx-row"><span class="mx-li"><i class="ic <?= h($r['icon'] ?: 'i-bolt') ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($r['name']) ?></b><small><?= h($r['when'] . ($r['cond'] !== 'No condition' ? ' · ' . $r['cond'] : '') . ' · ' . ($r['last'] === 'Never' ? 'never ran' : 'ran ' . $r['last'] . ', ' . $r['runs'] . ' runs')) ?></small></span><span class="mx-rr"><?php if ($d['canEdit']): ?><button type="button" class="mx-sw<?= $r['on'] ? ' on' : '' ?>" role="switch" aria-checked="<?= $r['on'] ? 'true' : 'false' ?>" aria-label="<?= h($r['name']) ?> on" data-sl-save="<?= h($ajax) ?>" data-p-op="toggle" data-p-id="<?= (int) $r['id'] ?>"></button><?php else: ?><span class="sl-pill <?= $r['on'] ? 'delivered' : 'plain' ?>"><?= $r['on'] ? 'On' : 'Off' ?></span><?php endif; ?></span></div>
<?php endforeach; ?>
<?php if (!$d['rows']): ?><?= sl_mxEmpty('bolt', 'No automations here', $d['lf'] === 'all' ? 'Create one to send messages or alerts by themselves.' : 'Nothing matches this filter.') ?><?php endif; ?>
</section>
<?php if ($d['runs']): ?>
<div class="mx-sec"><h2>Latest runs</h2></div>
<section class="mx-card mx-list">
<?php foreach (array_slice($d['runs'], 0, 5) as $x): ?>
<div class="mx-row"><span class="mx-li"><i class="ic i-bolt" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($x['name']) ?></b><small><?= h(trim(($x['label'] ?? '') . ' ' . ($x['detail'] ?? ''))) ?></small></span><span class="mx-rr"><span class="sl-pill <?= in_array($x['outcome'], ['ok', 'success', 'done'], true) ? 'delivered' : ($x['outcome'] === 'skipped' ? 'plain' : 'overdue') ?>"><?= h(ucfirst((string) $x['outcome'])) ?></span><small><?= h($x['at']) ?></small></span></div>
<?php endforeach; ?>
</section>
<?php endif; ?>
<?php if ($d['canEdit']): ?><div class="mx-btns"><a class="mx-btn" href="<?= h(sl_route('Automations', ['view' => 'web'])) ?>">New automation</a></div><p class="mx-note">Opens the full builder (website view).</p><?php endif; ?>
<?php endif; ?>
</main>
