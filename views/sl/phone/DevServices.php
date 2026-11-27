<?php
/** M-DevServices: every component with its live health. Data: sl_dvxServicesData (same as the web DevServices). ?svc=key opens a component. */
require_once CDP_ROOT . '/views/sl/data/dev_phone.php';
$slPhone['nav'] = 'dev';
$slPhone['tab'] = 'Console';
$c = $d['counts'] ?? ['all' => 0, 'ok' => 0, 'degraded' => 0, 'down' => 0, 'other' => 0];
$sel = null;
foreach ($d['rows'] ?? [] as $r) {
    if ($r['key'] === ($d['sel'] ?? '')) $sel = $r;
}
$shown = array_values(array_filter($d['rows'] ?? [], fn($r) => !$r['hidden']));
?>
<?= sl_phoneTop('Services', (int) $c['ok'] . ' healthy · ' . (int) $c['degraded'] . ' degraded · ' . (int) $c['down'] . ' down', ['back' => sl_route('DevOverview')], sl_phDevMore()) ?>
<main class="mx-body">
<div class="mx-seg" role="group" aria-label="Status"><form method="get" style="display:contents"><?php if (($d['q'] ?? '') !== ''): ?><input type="hidden" name="q" value="<?= h($d['q']) ?>"><?php endif; ?>
<?php foreach ($d['tabs'] ?? [] as $t): ?><button type="submit" name="filter" value="<?= h($t['k']) ?>" class="<?= h($t['cls']) ?>" aria-pressed="<?= $t['cls'] ? 'true' : 'false' ?>"><?= h($t['label']) ?> <?= (int) $t['n'] ?></button><?php endforeach; ?>
</form></div>
<?php if ($sel): ?>
<section class="mx-card mx-kv" aria-label="<?= h($sel['name']) ?>">
<div><span><b style="color:var(--ink);font-weight:600"><?= h($sel['name']) ?></b></span><b><span class="sl-pill <?= h(sl_phDevPill($sel['cls'])) ?>"><?= h($sel['st']) ?></span></b></div>
<div><span>Health check</span><b><?= h($sel['check'] ?: '—') ?></b></div>
<div><span>Check time</span><b><?= h(sl_dvxMs($sel['check_ms'])) ?></b></div>
<div><span>Success / uptime</span><b><?= $sel['uptime'] !== null ? h(sl_dvxPct($sel['uptime'], 3)) : '—' ?></b></div>
<div><span>Throughput</span><b><?= h($sel['thr']) ?></b></div>
<div><span>Last error</span><b><?= $sel['last_error'] ? h($sel['last_error'] . ($sel['last_error_at'] ? ' · ' . sl_dvxAgo((string) $sel['last_error_at']) : '')) : 'None recorded' ?></b></div>
<div><span><?= $sel['at_label'] === 'checked' ? 'Last checked' : 'Last activity' ?></span><b><?= h($sel['at_ago']) ?></b></div>
<?php if ($sel['detail'] !== ''): ?><div><span>Detail</span><b><?= h($sel['detail']) ?></b></div><?php endif; ?>
</section>
<?php if (isset($d['recent'][$sel['key']])): ?>
<?= sl_mxSec('Recent events · 24 h') ?>
<section class="mx-card mx-list">
<?php foreach ($d['recent'][$sel['key']] as $l): ?>
<?= sl_mxRow($l['lc'] === 'e' ? 'alert' : 'activity', $l['message'], $l['time'] . ' · ' . $l['level'], '', !empty($d['logsOk']) ? sl_route('DevLogs', ['source' => $l['source'], 'window' => 1440, 'row' => $l['id']]) : '') ?>
<?php endforeach; ?>
<?php if (!$d['recent'][$sel['key']]): ?><?= sl_mxEmpty('check', 'Nothing logged in 24 h') ?><?php endif; ?>
</section>
<?php endif; ?>
<div class="mx-btns"><?php if (!empty($d['issuesOk'])): ?><a class="mx-btn sec" href="<?= h(sl_route('DevIssues', ['new' => 1, 'title' => $sel['name'] . ' ' . strtolower($sel['st']), 'log' => mb_substr($sel['name'] . ': ' . $sel['st'] . ' · ' . $sel['detail'], 0, 400), 'page' => $sel['name']])) ?>">Open a ticket</a><?php endif; ?><a class="mx-btn sec" href="<?= h(sl_url(['svc' => null])) ?>">Close</a></div>
<?php endif; ?>
<section class="mx-card mx-list">
<?php foreach ($shown as $r): ?>
<a class="mx-row" href="<?= h(sl_url(['svc' => $r['key']])) ?>"<?= $sel && $sel['key'] === $r['key'] ? ' aria-current="true"' : '' ?>><span class="mx-li"><i class="ic <?= h($r['icon']) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($r['name']) ?></b><small><?= h($r['sub'] ?: $r['tech']) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h(sl_phDevPill($r['cls'])) ?>"><?= h($r['st']) ?></span></span></a>
<?php endforeach; ?>
<?php if (!$shown): ?><?= sl_mxEmpty('server', 'No service matches this filter') ?><?php endif; ?>
</section>
<div class="mx-btns"><a class="mx-btn" href="<?= h(sl_url(['svc' => null])) ?>">Check again</a></div>
</main>
