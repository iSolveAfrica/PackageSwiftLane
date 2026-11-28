<?php
/**
 * M-ApiTraffic: calls in and out. Same $d as the web page (views/sl/data/api_admin.php sl_apiTrafficData):
 * "Push out" = calls Swift Lane makes to providers (message log, web ?dir=pull), "Pull in" = calls
 * received on the REST API (web ?dir=push), "Errors" = both, 4xx/5xx only (?dir=both&only=errors).
 * The two KPIs: calls in the last minute and errors today; the 12-hour bars come from phone_settings.php.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
require_once CDP_ROOT . '/views/sl/data/phone_settings.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
$errOnly = ($_GET['only'] ?? '') === 'errors';
$tab = $errOnly ? 'errors' : ($d['dir'] === 'pull' ? 'out' : ($d['dir'] === 'push' ? 'in' : 'all'));
$tabs = [['all', 'All', ['dir' => null, 'only' => null]], ['out', 'Push out', ['dir' => 'pull', 'only' => null]], ['in', 'Pull in', ['dir' => 'push', 'only' => null]], ['errors', 'Errors', ['dir' => null, 'only' => 'errors']]];
$rows = [];
if ($d['showPull']) foreach ($d['pull'] as $r) $rows[] = $r + ['out' => true];
if ($d['showPush']) foreach ($d['push'] as $r) $rows[] = $r + ['out' => false];
if ($errOnly) $rows = array_values(array_filter($rows, fn($r) => $r['code'] >= 400 || $r['code'] === 0));
usort($rows, fn($a, $b) => strcmp($b['t'], $a['t']));
$rows = array_slice($rows, 0, 30);
$errToday = (int) preg_replace('/\D/', '', (string) strtok((string) $d['kpis'][0]['s'], ' ')) + (int) preg_replace('/\D/', '', (string) $d['kpis'][3]['v']);
$sp = sl_phTrafficSpark();
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-settings.css')) ?>">
<?= sl_phoneTop('Live API traffic', 'Latest 30 calls', ['back' => sl_route('SettingsHub')], sl_phoneIcon('refresh', 'Refresh', sl_url([]))) ?>
<main class="mx-body">
<nav class="mx-seg" aria-label="Direction"><?php foreach ($tabs as [$k, $l, $q]): ?><a class="<?= $tab === $k ? 'on' : '' ?>" href="<?= h(sl_url($q)) ?>"<?= $tab === $k ? ' aria-current="true"' : '' ?>><?= h($l) ?></a><?php endforeach; ?></nav>
<section class="mx-k c2">
<div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-activity" aria-hidden="true"></i></span><small>Calls/min</small></div><b><?= h(sl_num($d['pullN'] + $d['pushN'])) ?></b><?= sl_phSpark($sp['calls']) ?></div>
<div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-alert" aria-hidden="true"></i></span><small>Errors today</small></div><b><?= h(sl_num($errToday)) ?></b><?= sl_phSpark($sp['errors']) ?></div>
</section>
<section class="mx-card mx-list">
<?php foreach ($rows as $r): $bad = $r['code'] >= 400 || $r['code'] === 0; ?>
<div class="mx-row"><span class="mx-li"><i class="ic <?= $r['out'] ? 'i-up' : 'i-down' ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= $r['out'] ? '→ ' : '← ' ?><?= h($r['p'] . ' · ' . $r['e']) ?></b><small><?= (int) $r['code'] ?> · <?= h($r['ms']) ?></small></span><span class="mx-rr"><?php if ($bad): ?><span class="sl-pill overdue">Error</span><?php endif; ?><small><?= h($r['t']) ?></small></span></div>
<?php endforeach; ?>
<?php if (!$rows): ?><?= sl_mxEmpty('activity', $errOnly ? 'No errors' : 'No calls yet', $errOnly ? 'No failed calls in the latest traffic.' : 'Calls appear here as they happen.') ?><?php endif; ?>
</section>
</main>
