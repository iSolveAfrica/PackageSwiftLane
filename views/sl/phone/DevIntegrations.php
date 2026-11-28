<?php
/** M-DevInteg: GitHub and every external integration with its state. Data: sl_dplIntegrationsData (same as the web DevIntegrations); the list is sl_devIntegrations(), as on its "All integrations" tab (keys masked). */
require_once CDP_ROOT . '/views/sl/data/dev_phone.php';
$slPhone['nav'] = 'dev';
$slPhone['tab'] = 'Console';
$gh = $d['gh'] ?? ['configured' => false, 'ready' => false];
$al = $d['allowed'] ?? [];
$list = ($d['tab'] ?? '') === 'all' && isset($d['rows']) && empty($_GET['group']) ? $d['rows'] : sl_devIntegrations();
$on = count(array_filter($list, fn($i) => $i['configured']));
?>
<?= sl_phoneTop('Integrations', $on . ' of ' . count($list) . ' set up', ['back' => sl_route('DevOverview')], sl_phDevMore()) ?>
<main class="mx-body">
<section class="mx-card mx-list">
<?php if (!empty($al['github'])): $ghBad = in_array($gh['last_status'] ?? '', ['error', 'partial'], true); ?>
<a class="mx-row" href="<?= h(sl_url(['view' => 'web', 'tab' => null])) ?>"><span class="mx-li"><i class="ic i-git" aria-hidden="true"></i></span><span class="mx-rm"><b>GitHub</b><small><?= h(!empty($gh['configured']) ? $gh['owner'] . '/' . $gh['repo'] . ' · ' . (!empty($gh['last_sync']) ? 'synced ' . sl_ago($gh['last_sync']) : 'never synced') : 'Not connected · read-only token') ?></small></span><span class="mx-rr"><span class="sl-pill <?= !empty($gh['ready']) ? ($ghBad ? 'overdue' : 'delivered') : 'plain' ?>"><?= !empty($gh['ready']) ? ($ghBad ? 'Error' : 'On') : 'Off' ?></span></span></a>
<?php endif; ?>
<?php foreach ($list as $it): $fail = (int) ($it['failed7'] ?? 0); ?>
<a class="mx-row" href="<?= h($it['href']) ?>"><span class="mx-li"><i class="ic <?= h($it['icon']) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($it['name']) ?></b><small><?= h($it['group'] . ($it['usage'] !== '' ? ' · ' . $it['usage'] : '')) ?></small></span><span class="mx-rr"><span class="sl-pill <?= !$it['configured'] ? 'plain' : ($fail ? 'pending' : 'delivered') ?>"><?= !$it['configured'] ? 'Off' : ($fail ? $fail . ' failed' : 'On') ?></span></span></a>
<?php endforeach; ?>
</section>
<?php if (!empty($al['github']) && !empty($gh['ready'])): ?>
<div class="mx-btns"><button type="button" class="mx-btn" data-sl-action="ajax/sl/dev_github_ajax.php" data-p-op="gh_sync">Sync GitHub</button><button type="button" class="mx-btn sec" data-sl-action="ajax/sl/dev_github_ajax.php" data-p-op="gh_test">Test connection</button></div>
<?php endif; ?>
</main>
