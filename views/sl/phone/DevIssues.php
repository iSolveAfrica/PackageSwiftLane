<?php
/** M-DevIssues: tickets by status, one ticket, new ticket, change log. Data: sl_dplIssuesData (same as the web DevIssues). Writes: ajax/sl/dev_ajax.php (existing ops). */
require_once CDP_ROOT . '/views/sl/data/dev_phone.php';
$slPhone['nav'] = 'dev';
$slPhone['tab'] = 'Issues';
$me = sl_phoneMe();
$ready = !empty($d['ready']);
$tabHref = [];
foreach ($d['tabs'] ?? [] as $t) {
    $tabHref[strpos($t['href'], 'tab=log') !== false ? 'log' : (strpos($t['href'], 'tab=site') !== false ? 'site' : 'issues')] = $t;
}
$priPill = ['P1' => ['overdue', 'High'], 'P2' => ['pending', 'Medium'], 'P3' => ['plain', 'Low']];
$cur = !empty($_GET['id']) ? ($d['d'] ?? null) : null;
$pf = $d['prefill'] ?? ['title' => '', 'log' => '', 'page' => ''];
$row = function (array $i) use ($priPill): string {
    $p = $priPill[$i['pri']] ?? ['plain', $i['pri']];
    return '<a class="mx-row" href="' . h($i['href']) . '"><span class="mx-li"><i class="ic i-' . (in_array($i['type'], ['Bug', 'Error'], true) ? 'bug' : 'spark') . '" aria-hidden="true"></i></span><span class="mx-rm"><b>' . h($i['id'] . ' ' . $i['t']) . '</b><small>' . h($i['who'] . ' · ' . strtolower($i['type']) . ' · ' . $i['age']) . '</small></span><span class="mx-rr"><span class="sl-pill ' . h($p[0]) . '">' . h($p[1]) . '</span></span></a>';
};
?>
<?= sl_phoneTop('Issues & change log', '', ['avatar' => $me['ini'], 'href' => sl_route('Workspace'), 'online' => true], sl_phoneIcon('search', 'Search', sl_route('LockerSearch')) . sl_phoneIcon('bell', 'Notifications', sl_route('Notifications'))) ?>
<main class="mx-body" data-mx-scope>
<?php if (!empty($d['flash'])): ?><div class="mx-ban ok"><i class="ic ic-sm i-check" aria-hidden="true"></i><span><?= h($d['flash']) ?></span></div><?php endif; ?>
<?php if (!empty($d['tLog'])): /* ---------------- change log tab ---------------- */ ?>
<div class="mx-seg"><?php foreach (['issues' => 'Tickets', 'log' => 'Change log'] as $k => $l): if (isset($tabHref[$k])): ?><form method="get" style="display:contents"><input type="hidden" name="tab" value="<?= h($k) ?>"><button type="submit" class="<?= $k === 'log' ? 'on' : '' ?>" aria-pressed="<?= $k === 'log' ? 'true' : 'false' ?>"><?= h($l) ?></button></form><?php endif; endforeach; ?></div>
<?php if (($d['src'] ?? 'commits') === 'commits' && !empty($d['log'])): ?>
<?php foreach (array_slice($d['log']['groups'], 0, 8) as $g): ?>
<?= sl_mxSec($g['title'] . ($g['tag'] ? ' · ' . $g['tag'] : '')) ?>
<section class="mx-card mx-list">
<?php $n = 0; foreach ($g['sections'] as $type => $items): foreach ($items as $c): if ($n++ >= 8) break 2; $p = sl_ghParseCommit($c['title']); ?>
<div class="mx-row"><span class="mx-li"><i class="ic i-<?= $type === 'fix' ? 'check' : ($type === 'feat' ? 'spark' : 'git') ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h(($c['scope'] ? $c['scope'] . ': ' : '') . ($p['type'] !== 'other' ? $p['subject'] : $c['title'])) ?></b><small><?= h(($d['log']['labels'][$type] ?? ucfirst($type)) . ' · ' . ($c['author'] ?: '—') . ' · ' . substr((string) $c['sha'], 0, 7) . ($c['at'] ? ' · ' . sl_date($c['at'], 'M j') : '')) ?></small></span></div>
<?php endforeach; endforeach; ?>
<?php if ($g['n'] > 8): ?><div class="mx-row"><span class="mx-rm"><small><?= (int) $g['n'] - 8 ?> more in this group on the full page</small></span></div><?php endif; ?>
</section>
<?php endforeach; ?>
<?php if (!$d['log']['groups']): ?><section class="mx-card"><?= sl_mxEmpty('git', 'No commits to build a change log from yet') ?></section><?php endif; ?>
<?php else: ?>
<section class="mx-card mx-list"><?php foreach ((array) ($d['vers'] ?? []) as $v): foreach ($v['items'] as $e): ?><div class="mx-row"><span class="mx-li"><i class="ic i-check" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($v['v'] . ' · ' . $v['d']) ?></b><small><?= h($e['kl'] . ' · ' . $e['t']) ?></small></span></div><?php endforeach; endforeach; ?><?php if (empty($d['vers'])): ?><?= sl_mxEmpty('note', 'No release notes written yet') ?><?php endif; ?></section>
<?php endif; ?>
<?php else: /* ---------------- tickets ---------------- */ ?>
<?php if (!$ready): ?>
<div class="mx-ban info"><i class="ic ic-sm i-info" aria-hidden="true"></i><span>The tracker needs migrations/2026_11_06_freight_devops.sql.</span></div>
<?php elseif ($cur): ?>
<?php $p = $priPill[$cur['pri']] ?? ['plain', $cur['pri']]; ?>
<section class="mx-card mx-kv" aria-label="<?= h($cur['id']) ?>">
<div><span><b style="color:var(--ink);font-weight:600"><?= h($cur['id'] . ' ' . $cur['t']) ?></b></span><b><span class="sl-pill <?= h($p[0]) ?>"><?= h($p[1]) ?></span></b></div>
<div><span>Status</span><b><?= h($cur['st']) ?></b></div>
<div><span>Type</span><b><?= h($cur['type']) ?></b></div>
<div><span>Area</span><b><?= h($cur['area']) ?></b></div>
<div><span>Reported by</span><b><?= h($cur['by']) ?></b></div>
<div><span>Assigned to</span><b><?= h($cur['who']) ?></b></div>
<div><span>Opened</span><b><?= h($cur['age']) ?></b></div>
<div><span>Fix in</span><b><?= h($cur['rel']) ?></b></div>
<?php if ($cur['steps'] !== ''): ?><div><span>Steps</span><b style="white-space:pre-line"><?= h($cur['steps']) ?></b></div><?php endif; ?>
<?php if ($cur['log'] !== ''): ?><div><span>From the logs</span><b style="white-space:pre-line;font-family:var(--mono);font-size:12px;word-break:break-word"><?= h($cur['log']) ?></b></div><?php endif; ?>
</section>
<div class="mx-btns"><?php foreach ((array) ($d['moves'] ?? []) as $m): ?><button type="button" class="mx-btn<?= $m['cls'] === 'ghost' ? ' sec' : '' ?>" data-sl-action="ajax/sl/dev_ajax.php" data-sl-reload data-p-op="issue_status" data-p-id="<?= (int) $cur['rid'] ?>" data-p-status="<?= h($m['status']) ?>"><?= h($m['label']) ?></button><?php endforeach; ?></div>
<form class="mx-card mx-form" data-sl-form data-sl-reload action="ajax/sl/dev_ajax.php" method="post"><input type="hidden" name="op" value="issue_assign"><input type="hidden" name="id" value="<?= (int) $cur['rid'] ?>">
<label class="mx-f"><small>Assign to</small><select class="mx-in" name="assignee_id"><option value="0">Unassigned</option><?php foreach ((array) ($d['staff'] ?? []) as $s): ?><option value="<?= (int) $s['id'] ?>"<?= $s['id'] === $cur['whoId'] ? ' selected' : '' ?>><?= h($s['label']) ?></option><?php endforeach; ?></select></label>
<button type="submit" class="mx-btn sec">Assign</button></form>
<?= sl_mxSec('Activity') ?>
<section class="mx-card mx-list">
<?php foreach ((array) $cur['cms'] as $c): ?><div class="mx-row"><span class="mx-ava"><?= h($c['ini']) ?></span><span class="mx-rm"><b><?= h($c['who']) ?> <small style="display:inline"><?= h($c['at'] . ' · ' . $c['kind']) ?></small></b><small style="white-space:pre-line"><?= h($c['t']) ?></small></span></div><?php endforeach; ?>
<?php if (!$cur['cms']): ?><?= sl_mxEmpty('message', 'No activity yet') ?><?php endif; ?>
</section>
<form class="mx-card mx-form" data-sl-form data-sl-reload action="ajax/sl/dev_ajax.php" method="post"><input type="hidden" name="op" value="issue_comment"><input type="hidden" name="id" value="<?= (int) $cur['rid'] ?>">
<label class="mx-f"><small>Comment</small><input class="mx-in" name="body" required placeholder="Add a comment"></label><button type="submit" class="mx-btn">Post</button></form>
<div class="mx-btns"><a class="mx-btn sec" href="<?= h(sl_url(['id' => null])) ?>">All tickets</a></div>
<?php elseif (!empty($d['newOpen'])): ?>
<form class="mx-card mx-form" data-sl-form action="ajax/sl/dev_ajax.php" method="post" aria-label="New ticket"><input type="hidden" name="op" value="issue_create">
<label class="mx-f"><small>What's wrong?</small><input class="mx-in" name="title" required maxlength="190" value="<?= h($pf['title']) ?>" placeholder="Short title"></label>
<label class="mx-f"><small>Type</small><select class="mx-in" name="type"><option value="bug">Bug</option><option value="error"<?= $pf['log'] ? ' selected' : '' ?>>Error</option><option value="request">Request</option><option value="task">Task</option></select></label>
<label class="mx-f"><small>Priority</small><select class="mx-in" name="priority"><option value="p1">P1 · High</option><option value="p2" selected>P2 · Medium</option><option value="p3">P3 · Low</option></select></label>
<label class="mx-f"><small>Assign to</small><select class="mx-in" name="assignee_id"><option value="">Nobody yet</option><?php foreach ((array) ($d['staff'] ?? []) as $s): ?><option value="<?= (int) $s['id'] ?>"><?= h($s['label']) ?></option><?php endforeach; ?></select></label>
<label class="mx-f"><small>Area</small><input class="mx-in" name="area" placeholder="Shipments, Billing…"></label>
<label class="mx-f"><small>Page / service</small><input class="mx-in" name="page" value="<?= h($pf['page']) ?>" autocapitalize="off"></label>
<label class="mx-f"><small>Fix in version</small><input class="mx-in" name="fix_version" autocapitalize="off"></label>
<label class="mx-f"><small>Steps to see it</small><textarea class="mx-in area" name="steps" rows="3"></textarea></label>
<label class="mx-f"><small>Log excerpt</small><textarea class="mx-in area" name="log_excerpt" rows="3"><?= h($pf['log']) ?></textarea></label>
<button type="submit" class="mx-btn">Create ticket</button><a class="mx-btn sec" href="<?= h(sl_url(['new' => null, 'title' => null, 'log' => null, 'page' => null])) ?>">Cancel</a></form>
<?php else: $cols = (array) ($d['cols'] ?? []); $labels = ['Open', 'In progress', 'In review', 'Fixed']; ?>
<div class="mx-seg" data-mx-seg role="group" aria-label="Status">
<?php foreach ($cols as $i => $c): ?><button type="button" class="<?= $i === 0 ? 'on' : '' ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>" data-mx-panel="c<?= $i ?>"><?= h(($labels[$i] ?? $c['label']) . ($c['n'] ? ' ' . $c['n'] : '')) ?></button><?php endforeach; ?>
<?php if (isset($tabHref['log'])): ?><form method="get" style="display:contents"><input type="hidden" name="tab" value="log"><button type="submit">Change log</button></form><?php endif; ?>
</div>
<?php foreach ($cols as $i => $c): ?>
<section class="mx-card mx-list" data-mx-panel-of="c<?= $i ?>"<?= $i === 0 ? '' : ' hidden' ?>>
<?php foreach (array_slice($c['items'], 0, 40) as $it): ?><?= $row($it) ?><?php endforeach; ?>
<?php if (!$c['items']): ?><?= sl_mxEmpty('check', 'No ticket here') ?><?php endif; ?>
</section>
<?php endforeach; ?>
<?= sl_mxSec('Change log', isset($tabHref['log']) ? 'All' : '', sl_route('DevIssues', ['tab' => 'log'])) ?>
<section class="mx-card mx-list">
<?php $shown = 0; foreach ((array) ($d['vers'] ?? []) as $v): if ($shown >= 3) break; $shown++; ?>
<div class="mx-row"><span class="mx-li"><i class="ic i-check" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($v['v'] . ' · ' . $v['d']) ?></b><small><?= h($v['items'][0]['t'] ?? $v['title']) ?></small></span></div>
<?php endforeach; ?>
<?php if (!$shown): ?><?= sl_mxEmpty('note', 'No release notes yet', isset($tabHref['log']) ? 'The change log from commits is one tap away.' : '', isset($tabHref['log']) ? 'Open the change log' : '', sl_route('DevIssues', ['tab' => 'log'])) ?><?php endif; ?>
</section>
<?php endif; ?>
<?php endif; ?>
</main>
<?php if ($ready && !$cur && empty($d['newOpen']) && empty($d['tLog']) && empty($d['changelogOnly'])): ?><a class="mx-fab" href="<?= h(sl_url(['new' => 1])) ?>" aria-label="Report an issue"><i class="ic i-plus" aria-hidden="true"></i></a><?php endif; ?>
