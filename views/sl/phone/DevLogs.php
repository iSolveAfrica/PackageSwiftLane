<?php
/** M-DevLogs: merged log stream. Data: sl_dvxLogsData (same as the web DevLogs, same GET filters). Live tail: dataJs/sl/phone/dev.js (op=tail). */
require_once CDP_ROOT . '/views/sl/data/dev_phone.php';
$slPhone['nav'] = 'dev';
$slPhone['tab'] = 'Logs';
$slPhone['scripts'][] = 'dataJs/sl/phone/dev.js';
$me = sl_phoneMe();
$src = (string) ($d['source'] ?? '');
$lv = (string) ($d['level'] ?? '');
$view = (string) ($d['view'] ?? '');
$q = (string) ($d['q'] ?? '');
$win = (int) ($d['window'] ?? 60);
// Segments of the design, each one the same GET filter the web page reads (search and time window kept).
$segs = [
    ['All', [], $src === '' && $lv === '' && $view === ''],
    ['Errors ' . sl_num((int) ($d['nErr'] ?? 0)), ['level' => 'ERROR'], $lv === 'ERROR' && $src === '' && $view === ''],
    ['Warnings ' . sl_num((int) ($d['nWarn'] ?? 0)), ['level' => 'WARN'], $lv === 'WARN' && $src === '' && $view === ''],
    ['Payments', ['view' => 'payments'], $view === 'payments'],
    ['Messages', ['source' => 'messages'], $src === 'messages' && $view === ''],
    ['Requests', ['source' => 'requests'], $src === 'requests' && $view === ''],
    ['PHP', ['source' => 'php'], $src === 'php' && $view === ''],
];
$color = ['e' => 'var(--red-ink)', 'w' => 'var(--amber-ink)', 'i' => 'var(--green-ink)'];
$rows = array_slice($d['rows'] ?? [], 0, 80);
$picked = !empty($_GET['row']) ? ($d['sel'] ?? null) : null;
?>
<?= sl_phoneTop('Logs', (string) ($d['windows'][$win] ?? ''), ['avatar' => $me['ini'], 'href' => sl_route('Workspace'), 'online' => true], sl_phoneIcon('search', 'Search', sl_route('LockerSearch')) . sl_phoneIcon('bell', 'Alerts', sl_route('DevAlerts'))) ?>
<main class="mx-body" data-ph-dev="logs" data-ph-ajax="<?= h($d['ajax'] ?? 'ajax/sl/dev_observe_ajax.php') ?>">
<form class="mx-srch" method="get" role="search"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($q) ?>" maxlength="120" aria-label="Search logs" placeholder="Search logs"></label>
<?php foreach (['source' => $src, 'level' => $lv, 'view' => $view] as $k => $v): if ($v !== ''): ?><input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>"><?php endif; endforeach; ?><?php if ($win !== 60): ?><input type="hidden" name="window" value="<?= $win ?>"><?php endif; ?></form>
<div class="mx-seg" role="group" aria-label="Filter">
<?php foreach ($segs as [$label, $p, $on]): ?><form method="get" style="display:contents"><?php foreach ($p as $k => $v): ?><input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>"><?php endforeach; ?><?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= h($q) ?>"><?php endif; ?><?php if ($win !== 60): ?><input type="hidden" name="window" value="<?= $win ?>"><?php endif; ?><button type="submit" class="<?= $on ? 'on' : '' ?>" aria-pressed="<?= $on ? 'true' : 'false' ?>"><?= h($label) ?></button></form><?php endforeach; ?>
</div>
<?php if ($picked): ?>
<section class="mx-card mx-kv" aria-label="Log details">
<div><span>Time</span><b><?= h($picked['ts']) ?></b></div>
<div><span>Level</span><b style="color:<?= $color[$picked['lc']] ?? $color['i'] ?>"><?= h($picked['level']) ?></b></div>
<div><span>Source</span><b><?= h($picked['srcLabel']) ?></b></div>
<div><span>Service</span><b><?= h($picked['service']) ?></b></div>
<div><span>Message</span><b style="word-break:break-word"><?= h($picked['message']) ?></b></div>
</section>
<div class="mx-btns"><button type="button" class="mx-btn sec" data-sl-copy="<?= h((string) $picked['raw']) ?>">Copy raw line</button><?php if (!empty($d['issuesOk'])): ?><a class="mx-btn sec" href="<?= h(sl_dvxTicketHref($picked)) ?>">Open a ticket</a><?php endif; ?></div>
<?php endif; ?>
<section class="mx-card" style="padding:12px;font:11.5px/1.6 var(--mono);display:flex;flex-direction:column;gap:2px;word-break:break-word" role="log" aria-label="Log lines, newest first" data-ph-tail data-since="<?= h($d['newest'] ?? '') ?>" data-source="<?= h($src) ?>" data-level="<?= h($lv) ?>" data-q="<?= h($q) ?>" data-view="<?= h($view) ?>">
<?php foreach ($rows as $r): ?><a href="<?= h(sl_url(['row' => $r['id']])) ?>" data-id="<?= h($r['id']) ?>" style="color:inherit"><span style="color:var(--muted)"><?= h($r['time']) ?></span> <b style="color:<?= $color[$r['lc']] ?? $color['i'] ?>"><?= h($r['level']) ?></b> <?= h($r['service']) ?> <?= h($r['message']) ?></a>
<?php endforeach; ?>
<?php if (!$rows): ?><span data-ph-empty style="color:var(--muted)">No event matches these filters in this window.</span><?php endif; ?>
</section>
<section class="mx-card mx-kv">
<div><span>Events in the window</span><b><?= h(sl_num((int) ($d['total'] ?? 0))) ?></b></div>
<div><span>Lines per minute</span><b><?= h(number_format(((int) ($d['total'] ?? 0)) / max(1, $win), ((int) ($d['total'] ?? 0)) / max(1, $win) < 10 ? 1 : 0)) ?></b></div>
<div><span>Window</span><b><?php foreach ($d['windows'] ?? [] as $k => $l): ?><?php if ((int) $k === $win): ?><?= h($l) ?><?php endif; ?><?php endforeach; ?></b></div>
<?php if (count($d['rows'] ?? []) > count($rows)): ?><div><span>Shown here</span><b>Newest <?= count($rows) ?></b></div><?php endif; ?>
</section>
<form class="mx-seg" method="get" aria-label="Time window"><?php foreach (['source' => $src, 'level' => $lv, 'view' => $view, 'q' => $q] as $k => $v): if ($v !== ''): ?><input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>"><?php endif; endforeach; ?>
<?php foreach ($d['windows'] ?? [] as $k => $l): ?><button type="submit" name="window" value="<?= (int) $k ?>" class="<?= (int) $k === $win ? 'on' : '' ?>" aria-pressed="<?= (int) $k === $win ? 'true' : 'false' ?>"><?= h($l) ?></button><?php endforeach; ?>
</form>
</main>
