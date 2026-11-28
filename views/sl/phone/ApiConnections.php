<?php
/**
 * M-ApiConn: every outside service with its state. Same $d as the web page (views/sl/data/api_connections.php).
 * With ?p=<service> the phone shows that service's keys: same form, field names and endpoint as the web
 * (ajax/sl/api_connections_ajax.php op=save / toggle / test). Secrets are only ever shown masked.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$ajax = 'ajax/sl/api_connections_ajax.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
$icons = ['Payments' => 'i-card', 'Messages' => 'i-message', 'AI' => 'i-ai', 'Maps' => 'i-pin', 'Couriers' => 'i-route', 'Team' => 'i-users'];
$pill = function (array $p) {
    if (!$p['configured']) return ['Not set', 'plain'];
    if (!$p['on']) return ['Off', 'pending'];
    return $p['dot'] === 'r' ? ['Error', 'overdue'] : ['Live', 'delivered'];
};
$detail = isset($_GET['p']) && $_GET['p'] !== '';
$c = $d['cur'];
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-settings.css')) ?>">
<?php if (!$detail): ?>
<?= sl_phoneTop('API connections', (int) $d['conN'] . ' of ' . (int) $d['allN'] . ' connected', ['back' => sl_route('SettingsHub')], sl_phoneIcon('activity', 'Live API traffic', sl_route('ApiTraffic'))) ?>
<main class="mx-body">
<?php if ($d['warnN']): ?><div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span><?= (int) $d['warnN'] ?> connection<?= $d['warnN'] > 1 ? 's need' : ' needs' ?> attention: a failed check or switched off.</span></div><?php endif; ?>
<?php foreach ($d['groups'] as $g): ?>
<div class="mx-sec"><h2><?= h($g['title']) ?></h2></div>
<section class="mx-card mx-list">
<?php foreach ($g['items'] as $p): [$pl, $pc] = $pill($p); ?>
<a class="mx-row" href="<?= h(sl_route('ApiConnections', ['p' => $p['k']])) ?>"><span class="mx-li"><i class="ic <?= h($icons[$g['title']] ?? 'i-link') ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($p['name']) ?></b><small><?= h($p['use'] . ($p['configured'] && $p['lat'] !== 'on' && $p['lat'] !== 'off' ? ' · ' . $p['lat'] : '')) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($pc) ?>"><?= h($pl) ?></span></span></a>
<?php endforeach; ?>
</section>
<?php endforeach; ?>
<div class="mx-btns"><a class="mx-btn" href="<?= h(sl_route('NewIntegration')) ?>">Add connection</a></div>
</main>
<?php else: $slPhone['nav'] = 'none'; ?>
<?= sl_phoneTop($c['name'], $c['desc'], ['back' => sl_route('ApiConnections')]) ?>
<form data-sl-form data-sl-reload action="<?= h($ajax) ?>" method="post" autocomplete="off">
<main class="mx-body">
<input type="hidden" name="op" value="save"><input type="hidden" name="provider" value="<?= h($c['k']) ?>">
<div class="mx-ban <?= $c['pc'] === 'delivered' ? 'ok' : 'info' ?>"><i class="ic ic-sm i-link" aria-hidden="true"></i><span><?= h($c['status']) ?><?= $d['check'] ? ' · last check ' . h($d['check']['at']) . ': ' . ($d['check']['ok'] ? 'passed' : 'failed') . ($d['check']['detail'] !== '' ? ' · ' . h($d['check']['detail']) : '') : '' ?></span></div>
<section class="mx-card mx-form">
<?php foreach ($d['fields'] as $f): ?>
<label class="mx-f"><small><?= h($f['label']) ?></small><input class="mx-in" name="<?= h($f['name']) ?>" value="<?= h($f['val']) ?>" <?= $f['secret'] ? 'type="password" autocomplete="new-password" placeholder="' . h($f['ph']) . '"' : 'autocomplete="off"' ?><?= $d['canEdit'] ? '' : ' readonly' ?>></label>
<?php if ($f['hint'] !== ''): ?><p class="mx-note"><?= h($f['hint']) ?></p><?php endif; ?>
<?php endforeach; ?>
</section>
<?php if ($c['hasSwitch']): ?>
<section class="mx-card mx-tgs"><?= sl_mxToggle('Use this connection', 'Switch off to stop using it without deleting the keys', (bool) $c['on'], $ajax, '', ['op' => 'toggle', 'provider' => $c['k']], !$d['canEdit']) ?></section>
<?php endif; ?>
</main>
<div class="mx-foot ph-foot"><div class="mx-btns"><button class="mx-btn sec" type="button" data-sl-action="<?= h($ajax) ?>" data-p-op="test" data-p-provider="<?= h($c['k']) ?>" data-sl-reload>Test connection</button><?php if ($d['canEdit']): ?><button class="mx-btn" type="submit">Save</button><?php endif; ?></div></div>
</form>
<?php endif; ?>
