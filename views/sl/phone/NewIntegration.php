<?php
/**
 * M-ApiConn (add): the catalogue of services. Same $d as the web NewIntegration page; each row opens that
 * service's key form (api_connections.php?p=…), which saves through ajax/sl/api_connections_ajax.php.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-settings.css')) ?>">
<?= sl_phoneTop('Add a connection', 'Payments, messages, carriers, maps and AI', ['back' => sl_route('ApiConnections')]) ?>
<main class="mx-body">
<?php foreach ($d['groups'] as $g): ?>
<div class="mx-sec"><h2><?= h($g['title']) ?></h2></div>
<section class="mx-card mx-list">
<?php foreach ($g['items'] as $p): $done = $p['configured'] && $p['on']; ?>
<a class="mx-row" href="<?= h(sl_route('ApiConnections', ['p' => $p['k']])) ?>"><span class="mx-li"><b class="ph-tag"><?= h($p['short']) ?></b></span><span class="mx-rm"><b><?= h($p['name']) ?></b><small><?= h($p['use']) ?></small></span><span class="mx-rr"><?php if ($done): ?><span class="sl-pill delivered">Connected</span><?php else: ?><span class="mx-lnk">Connect</span><?php endif; ?></span></a>
<?php endforeach; ?>
</section>
<?php endforeach; ?>
</main>
