<?php
/** M-DevConsole: developer console home. Data: sl_dvxOverviewData (same as the web DevOverview). Live: dataJs/sl/phone/dev.js (op=pulse). */
require_once CDP_ROOT . '/views/sl/data/dev_phone.php';
$slPhone['nav'] = 'dev';
$slPhone['tab'] = 'Console';
$slPhone['scripts'][] = 'dataJs/sl/phone/dev.js';
$me = sl_phoneMe();
$ok = $d['ok'] ?? [];
$kp = [];
foreach ($d['kpis'] ?? [] as $k) {
    $kp[$k['k']] = $k;
}
$days = function_exists('cdp_reqmDaily') ? cdp_reqmDaily(30) : [];
$w24 = function_exists('cdp_reqmWindow') ? cdp_reqmWindow(time() - 86400, time() + 1) : ['p95' => null];
$p95s = array_column($days, 'p95');
$tiles = [
    ['k' => 'uptime', 'icon' => 'activity', 't' => 'Uptime 30 d', 'v' => ($kp['uptime']['v'] ?? '—') . ($kp['uptime']['u'] ?? ''), 'sp' => array_map(fn($x) => $x['has'] ? 100 - (float) $x['err_pct'] : null, $days), 'live' => true],
    ['k' => 'throughput', 'icon' => 'cpu', 't' => 'Requests/min', 'v' => $kp['throughput']['v'] ?? '—', 'sp' => array_column($days, 'rpm'), 'live' => true],
    ['k' => 'p95', 'icon' => 'clock', 't' => 'p95 latency', 'v' => $w24['p95'] !== null ? sl_dvxMs($w24['p95']) : '—', 'sp' => $p95s, 'live' => false],
    ['k' => 'errors', 'icon' => 'alert', 't' => 'Errors', 'v' => ($kp['errors']['v'] ?? '—') . ($kp['errors']['u'] ?? ''), 'sp' => array_column($days, 'err_pct'), 'live' => true],
];
$pts = sl_phDevSeries('24h')['points'] ?? [];
$bars = sl_phDevBars(array_column($pts, 'n'), array_column($pts, 'label'), 8, 'sum');
$svc = [];
foreach ($d['status'] ?? [] as $s) {
    $svc[$s['key']] = $s;
}
$show = array_values(array_filter(array_map(fn($k) => $svc[$k] ?? null, ['web', 'outbox', 'db', 'cron'])));
?>
<?= sl_phoneTop('Developer console', (string) ($d['host'] ?? ''), ['avatar' => $me['ini'], 'href' => sl_route('Workspace'), 'online' => true], sl_phoneIcon('search', 'Search', sl_route('LockerSearch')) . sl_phoneIcon('bell', 'Open alerts', sl_route('DevAlerts'), (int) ($d['alertsOpen'] ?? 0))) ?>
<main class="mx-body" data-ph-dev="overview" data-ph-ajax="<?= h($d['ajax'] ?? 'ajax/sl/dev_observe_ajax.php') ?>">
<?php if (empty($d['cov']['rows'])): ?><div class="mx-ban info"><i class="ic ic-sm i-info" aria-hidden="true"></i><span>Request metrics are starting: uptime, response time, errors and throughput fill in as the site is used.</span></div><?php endif; ?>
<section class="mx-k c2">
<?php foreach ($tiles as $t): ?>
<div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-<?= h($t['icon']) ?>" aria-hidden="true"></i></span><small><?= h($t['t']) ?></small></div><b<?= $t['live'] ? ' data-ph-kpi="' . h($t['k']) . '"' : '' ?>><?= h($t['v']) ?></b><?= sl_phDevSparkHtml(sl_phDevSpark($t['sp'])) ?></div>
<?php endforeach; ?>
</section>
<section class="mx-card mx-bars"><div class="mx-bh"><b>Requests, last 24 h</b></div>
<?php if ($bars): ?><?= sl_phDevBarsHtml($bars, fn($v) => sl_num($v) . ' requests') ?><?php else: ?><p class="mx-hint" style="margin:8px 0 0;color:var(--muted);font-size:13px">No request recorded in the last 24 hours yet.</p><?php endif; ?>
</section>
<?= sl_mxSec('Services', !empty($ok['services']) ? 'All' : '', sl_route('DevServices')) ?>
<section class="mx-card mx-list">
<?php foreach ($show as $s): $href = !empty($ok['services']) ? $s['href'] : ''; $tag = $href !== '' ? 'a' : 'div'; ?>
<<?= $tag ?> class="mx-row"<?= $href !== '' ? ' href="' . h($href) . '"' : '' ?>><span class="mx-li"><i class="ic <?= h($s['icon']) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($s['name']) ?></b><small data-ph-svc-sub="<?= h($s['key']) ?>"><?= h($s['sub'] ?: $s['tech']) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h(sl_phDevPill($s['cls'])) ?>" data-ph-svc="<?= h($s['key']) ?>"><?= h($s['st']) ?></span></span></<?= $tag ?>>
<?php endforeach; ?>
<?php if (!$show): ?><?= sl_mxEmpty('server', 'No component checked yet') ?><?php endif; ?>
</section>
<?php if (!empty($d['alerts'])): ?>
<?= sl_mxSec('Open alerts', 'Manage', sl_route('DevAlerts')) ?>
<section class="mx-card mx-list">
<?php foreach (array_slice($d['alerts'], 0, 3) as $a): ?>
<a class="mx-row" href="<?= h(sl_route('DevAlerts', ['alert' => $a['id']])) ?>"><span class="mx-li"><i class="ic i-alert" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($a['t']) ?></b><small><?= h($a['s']) ?> · <?= h($a['at']) ?></small></span><span class="mx-rr"><span class="sl-pill <?= $a['sev'] === 'critical' ? 'overdue' : ($a['sev'] === 'warning' ? 'pending' : 'plain') ?>"><?= h(ucfirst($a['sev'])) ?></span></span></a>
<?php endforeach; ?>
</section>
<?php endif; ?>
<?= sl_mxSec('Latest') ?>
<section class="mx-card mx-list">
<?php foreach (array_slice($d['deploys'] ?? [], 0, 2) as $dp): ?>
<a class="mx-row" href="<?= h($dp['href']) ?>"><span class="mx-li"><i class="ic i-rocket" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($dp['t']) ?></b><small><?= h(trim($dp['s'] . ($dp['at'] !== '' ? ' · ' . $dp['at'] : ''), ' ·')) ?></small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<?php endforeach; ?>
<?php if (empty($d['deploys'])): ?><?= sl_mxEmpty('rocket', 'No deployment recorded', 'This checkout has no git history.') ?><?php endif; ?>
</section>
</main>
