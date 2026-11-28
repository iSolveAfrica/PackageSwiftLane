<?php
/** M-DevInfra: this server, its resources, backups and scheduled jobs. Data: sl_dinfInfraData (same as the web DevInfrastructure). */
require_once CDP_ROOT . '/views/sl/data/dev_phone.php';
$slPhone['nav'] = 'dev';
$slPhone['tab'] = 'Console';
$k = [];
foreach ($d['kpis'] ?? [] as $x) {
    $k[$x['k']] = $x;
}
$fact = [];
foreach ($d['facts'] ?? [] as $f) {
    $fact[$f['k']] = $f;
}
$b = $d['backup'] ?? ['latest' => '', 'latest_at' => 0, 'latest_size' => 0, 'count' => 0];
$bTone = !$b['latest'] ? 'bad' : ($b['latest_at'] < time() - 7 * 86400 ? 'warn' : 'ok');
$plan = array_filter([empty($fact['Hosting plan']['unset']) ? ($fact['Hosting plan']['v'] ?? '') : '', empty($fact['Provider']['unset']) ? ($fact['Provider']['v'] ?? '') : '']);
$region = empty($fact['Region']['unset']) ? ($fact['Region']['v'] ?? '') : '';
$pct = fn(array $x) => $x['v'] !== '—' ? $x['v'] . $x['u'] : '—';
$rows = [
    ['cloud', $plan ? implode(' · ', $plan) : (string) ($d['host'] ?? 'This server'), trim(($fact['CPU cores']['v'] ?? '?') . ' cores · ' . ($fact['Operating system']['v'] ?? '') . ($region !== '' ? ' · ' . $region : '')), $k['cpu']['tone'] ?? 'off', ($k['cpu']['tone'] ?? 'off') === 'off' ? 'Not measured' : 'CPU ' . $pct($k['cpu'])],
    ['server', 'Memory', $k['mem']['sub'] ?? '', $k['mem']['tone'] ?? 'off', $pct($k['mem'] ?? ['v' => '—', 'u' => ''])],
    ['database', 'Disk', $k['disk']['sub'] ?? '', $k['disk']['tone'] ?? 'off', $pct($k['disk'] ?? ['v' => '—', 'u' => ''])],
    ['database', 'Database', $k['db']['sub'] ?? '', $k['db']['tone'] ?? 'off', isset($k['db']) ? ($k['db']['v'] === 'down' ? 'Down' : $k['db']['v'] . ' ' . $k['db']['u']) : '—'],
    ['archive', 'Backups', $b['latest'] ? sl_ago(date('Y-m-d H:i:s', (int) $b['latest_at'])) . ' · ' . (int) $b['count'] . ' kept · ' . sl_devBytes($b['latest_size']) : 'No backup file yet', $bTone, $bTone === 'ok' ? 'OK' : ($bTone === 'warn' ? 'Old' : 'None')],
    ['clock', 'Server uptime', (string) ($fact['Server uptime']['v'] ?? '—'), 'off', 'PHP ' . PHP_VERSION],
];
$crons = array_slice($d['crons'] ?? [], 0, 8);
$q = $d['queue'] ?? [];
?>
<?= sl_phoneTop('Infrastructure', (string) ($d['host'] ?? ''), ['back' => sl_route('DevOverview')], sl_phDevMore()) ?>
<main class="mx-body">
<section class="mx-card mx-list">
<?php foreach ($rows as [$icon, $title, $sub, $tone, $pill]): ?>
<div class="mx-row"><span class="mx-li"><i class="ic i-<?= h($icon) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($title) ?></b><small><?= h($sub) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h(sl_phDevPill($tone)) ?>"><?= h($pill) ?></span></span></div>
<?php endforeach; ?>
</section>
<?= sl_mxSec('Scheduled jobs and queue') ?>
<section class="mx-card mx-list">
<?php foreach ($crons as $c): ?>
<div class="mx-row"><span class="mx-li"><i class="ic i-clock" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($c['name']) ?></b><small><?= h($c['last'] ? 'Last ' . $c['last'] . ' · ' . $c['runs'] . ' runs / ' . $c['fails'] . ' failed in 24 h' : ($c['sched'] ? 'Runs ' . $c['sched'] : ($c['what'] ?: 'Never recorded'))) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h(sl_phDevPill($c['tone'])) ?>"><?= h(ucfirst($c['state'])) ?></span></span></div>
<?php endforeach; ?>
<?php if (!empty($q['exists'])): ?>
<div class="mx-row"><span class="mx-li"><i class="ic i-message" aria-hidden="true"></i></span><span class="mx-rm"><b>Notification queue</b><small><?= (int) $q['queued'] ?> waiting<?= !empty($q['oldest_min']) ? ' · oldest ' . (int) $q['oldest_min'] . ' min' : '' ?> · <?= (int) $q['sent24'] ?> sent in 24 h</small></span><span class="mx-rr"><span class="sl-pill <?= !empty($q['dead']) ? 'pending' : 'delivered' ?>"><?= !empty($q['dead']) ? (int) $q['dead'] . ' dead' : 'OK' ?></span></span></div>
<?php endif; ?>
<?php if (!$crons && empty($q['exists'])): ?><?= sl_mxEmpty('clock', 'No scheduled job found') ?><?php endif; ?>
</section>
<div class="mx-btns"><a class="mx-btn" href="backup.php">Run a backup</a><?php if (is_file(CDP_ROOT . '/dev_database.php')): ?><a class="mx-btn sec" href="dev_database.php">Database and backups</a><?php endif; ?><?php if (!empty($d['canScan'])): ?><button type="button" class="mx-btn sec" data-sl-action="<?= h($d['ajax']) ?>" data-p-op="rescan">Re-scan storage</button><?php endif; ?></div>
</main>
