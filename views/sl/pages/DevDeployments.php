<?php
/**
 * DevDeployments (dark). Pipelines and deployments: GitHub Actions runs (cdb_dev_gh_runs, jobs/steps mapped to
 * Code push > Tests > Build > Deploy > Health check by helpers/dev_github.php sl_ghPipeline), GitHub deployments,
 * manual records (cdb_dev_deploy_records) and the commit checked out on this server (.git, read without a shell).
 * Database deploy: backups/, staged dump plan, guarded import (ajax/sl/dev_db_upload_ajax.php).
 * Writes: ajax/sl/dev_github_ajax.php, ajax/sl/dev_db_upload_ajax.php (POST + CSRF, audited). Nothing writes to GitHub.
 */
if (!isset($d)) { $d = []; }
$gh = $d['gh'];
$al = $d['allowed'];
$head = $d['head'];
$icons = ['code' => 'i-git', 'tests' => 'i-check', 'build' => 'i-box', 'deploy' => 'i-rocket', 'health' => 'i-activity'];
$dplEnd = sl_dplSubnav('deployments', $user);
?>
<div class="dp">
<div class="sl-head"><div><h1 class="sl-title">Deployments</h1><p class="sl-lede">Pipelines from GitHub Actions, every deployment of this app, and the database deploy tools</p></div>
<div class="row g8 wrap">
<?php if ($head && $head['hash']): ?><span class="dp-chip" title="Commit checked out on this server"><span class="dp-dot g"></span><i class="ic ic-sm i-server" aria-hidden="true"></i><?= h(($head['branch'] ?: 'detached') . ' @ ' . substr($head['hash'], 0, 7)) ?></span><?php endif; ?>
<?php if ($gh['ready']): ?><a class="dp-chip" href="<?= h(sl_route('DevIntegrations')) ?>"><i class="ic ic-sm i-git" aria-hidden="true"></i><?= h($gh['owner'] . '/' . $gh['repo']) ?> · <?= $gh['last_sync'] ? 'synced ' . h(sl_ago($gh['last_sync'])) : 'not synced' ?></a>
<?php if ($al['github']): ?><button type="button" class="sl-btn ghost sm" data-sl-action="ajax/sl/dev_github_ajax.php" data-p-op="gh_sync"><i class="ic ic-sm i-refresh" aria-hidden="true"></i>Sync GitHub</button><?php endif; ?>
<?php else: ?><a class="dp-chip warn" href="<?= h(sl_route('DevIntegrations')) ?>"><i class="ic ic-sm i-git" aria-hidden="true"></i>GitHub not connected</a><?php endif; ?>
<?php if ($d['tab'] === 'pipeline'): ?><button type="button" class="sl-btn sm" data-sl-open="dp-record"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Record deployment</button><?php endif; ?>
</div></div>

<nav class="dp-tabs" aria-label="Deployments sections"><?php foreach ($d['tabs'] as $t): ?><a class="<?= $t['on'] ? 'on' : '' ?>" href="<?= h($t['href']) ?>"<?= $t['on'] ? ' aria-current="page"' : '' ?>><i class="ic ic-sm <?= h($t['icon']) ?>" aria-hidden="true"></i><?= h($t['label']) ?></a><?php endforeach; ?><?php if ($al['changelog']): ?><a href="<?= h(sl_route('DevIssues', ['tab' => 'log'])) ?>"><i class="ic ic-sm i-note" aria-hidden="true"></i>Change log</a><?php endif; ?></nav>

<?php if ($d['tab'] === 'pipeline'):
$dep = $d['dep'];
$run = $d['run'];
?>
<?php if (!$gh['ready']): ?><div class="dp-note"><i class="ic i-info" aria-hidden="true"></i><span>GitHub is not connected, so there are no Actions runs yet. The list shows this server's checkout and deployments recorded by hand. <a href="<?= h(sl_route('DevIntegrations')) ?>">Connect GitHub</a> (read-only token) to see every pipeline.</span></div>
<?php elseif ($dep['allRuns']): ?><div class="dp-note"><i class="ic i-info" aria-hidden="true"></i><span>No workflow name contains a deploy word (<span class="mono"><?= h(str_replace('|', ', ', $gh['deploy_pattern'])) ?></span>), so every workflow run is listed. Change the pattern on <a href="<?= h(sl_route('DevIntegrations')) ?>">Integrations › GitHub</a>.</span></div><?php endif; ?>

<div class="dp-kpis"><?php foreach ($d['kpis'] as $k): ?><a class="dp-kpi <?= h($k['c']) ?>" href="<?= h($k['href']) ?>"><span class="t"><?= h($k['l']) ?></span><span class="v"><?= h($k['v']) ?><small><?= h($k['u']) ?></small></span><span class="s"><?= h($k['s']) ?></span><?php if ($k['spark']['line']): ?><svg viewBox="0 0 <?= (int) $k['spark']['w'] ?> <?= (int) $k['spark']['h'] ?>" preserveAspectRatio="none" aria-hidden="true"><path class="a" d="<?= h($k['spark']['area']) ?>"/><path class="l" d="<?= h($k['spark']['line']) ?>"/></svg><?php else: ?><span class="dp-flat" aria-hidden="true"></span><?php endif; ?></a><?php endforeach; ?></div>

<section class="dp-card" id="dp-pipeline"><div class="dp-ch"><div><h2 class="dp-ct">Pipeline<?= $run ? ' · ' . h($run->workflow_name) . ($run->run_number ? ' #' . (int) $run->run_number : '') : '' ?></h2>
<div class="dp-sub"><?php if ($run): ?><?= h($run->title) ?> · <span class="mono"><?= h(substr((string) $run->head_sha, 0, 7)) ?></span> on <span class="mono"><?= h($run->branch) ?></span> · <?= h(sl_ago($run->created_at)) ?> by <?= h($run->actor ?: '—') ?><?php else: ?>Pick a run in the table below. Stages are read from the run's jobs and steps.<?php endif; ?></div></div>
<div class="row g8 wrap"><?php if ($run): $rs = sl_dplState($d['runState']); ?><span class="sl-pill <?= h($rs[1]) ?>"><?= h($rs[0]) ?></span><span class="dp-chip mono"><i class="ic ic-sm i-clock" aria-hidden="true"></i><?= h(sl_ghDur($run->duration_s)) ?></span>
<?php if (!$run->jobs_json && $al['github']): ?><button type="button" class="sl-btn ghost sm" data-sl-action="ajax/sl/dev_github_ajax.php" data-p-op="gh_jobs" data-p-run_id="<?= (int) $run->id ?>"><i class="ic ic-sm i-download" aria-hidden="true"></i>Load jobs</button><?php endif; ?>
<?php if ($run->url): ?><a class="sl-btn ghost sm" href="<?= h($run->url) ?>" target="_blank" rel="noopener noreferrer"><i class="ic ic-sm i-ext" aria-hidden="true"></i>Open on GitHub</a><?php endif; ?><?php endif; ?></div></div>
<ol class="dp-pipe"><?php foreach ($d['pipeline'] as $i => $s): $ps = sl_dplState($s['state']); ?><li class="st-<?= h($s['state']) ?>"><span class="ico"><i class="ic ic-sm <?= h($icons[$s['key']]) ?>" aria-hidden="true"></i></span><b><?= h($s['label']) ?></b><span class="lbl"><?= h($s['state'] === 'absent' ? 'Not in workflow' : ($s['state'] === 'unknown' ? 'Not loaded' : $ps[0])) ?></span><span class="det"><?= h($s['detail']) ?></span><?php if ($s['items']): ?><span class="its"><?= h(implode(' · ', array_slice($s['items'], 0, 3))) ?></span><?php endif; ?></li><?php endforeach; ?></ol>
<?php if ($d['jobs']): ?><div class="dp-jobs"><?php foreach ($d['jobs'] as $j): $js = sl_dplState($j['status'] !== 'completed' ? 'running' : (in_array($j['conclusion'], ['failure', 'timed_out'], true) ? 'failure' : ($j['conclusion'] ?: 'success'))); ?>
<details class="dp-job"><summary><span class="dp-dot" style="background:<?= h($js[2]) ?>"></span><b><?= h($j['name']) ?></b><span class="mut sm"><?= count($j['steps']) ?> steps · <?= h(sl_ghDur($j['started_at'] && $j['completed_at'] ? strtotime($j['completed_at']) - strtotime($j['started_at']) : null)) ?><?= $j['runner'] ? ' · ' . h($j['runner']) : '' ?></span><span class="sl-pill <?= h($js[1]) ?>"><?= h($js[0]) ?></span></summary>
<ol class="dp-steps"><?php foreach ($j['steps'] as $s): $ss = sl_dplState($s['status'] !== 'completed' ? 'running' : (in_array($s['conclusion'], ['failure', 'timed_out'], true) ? 'failure' : ($s['conclusion'] ?: 'success'))); ?><li><span class="dp-dot" style="background:<?= h($ss[2]) ?>"></span><span class="grow"><?= h($s['name']) ?></span><span class="mono mut sm"><?= h(sl_ghDur($s['started_at'] && $s['completed_at'] ? strtotime($s['completed_at']) - strtotime($s['started_at']) : null)) ?></span></li><?php endforeach; ?></ol>
<?php if ($j['url']): ?><a class="sm" href="<?= h($j['url']) ?>" target="_blank" rel="noopener noreferrer">Job log on GitHub</a><?php endif; ?></details><?php endforeach; ?></div><?php endif; ?>
</section>

<div class="dp-split">
<section class="dp-card"><div class="dp-ch"><div><h2 class="dp-ct">Deployments per day · 14 days</h2><div class="dp-sub">Successful and failed, all sources</div></div><div class="row g12 sm"><span class="dp-lg"><i style="background:var(--dp-green)"></i>Success</span><span class="dp-lg"><i style="background:var(--dp-red)"></i>Failed</span><span class="dp-lg"><i style="background:var(--dp-faint)"></i>Other</span></div></div>
<div class="dp-bars" role="img" aria-label="Deployments per day for 14 days"><?php foreach ($d['freq'] as $f): ?><div class="c" title="<?= h($f['l'] . ': ' . $f['n'] . ' (' . $f['ok'] . ' ok, ' . $f['bad'] . ' failed)') ?>"><span class="o" style="height:<?= (int) $f['hOther'] ?>%"></span><span class="b" style="height:<?= (int) $f['hBad'] ?>%"></span><span class="g" style="height:<?= (int) $f['hOk'] ?>%"></span></div><?php endforeach; ?></div>
<div class="row between dp-axis"><span><?= h($d['freq'][0]['l']) ?></span><span><?= h($d['freq'][7]['l']) ?></span><span><?= h(end($d['freq'])['l']) ?></span></div></section>
<section class="dp-card"><div class="dp-ch"><h2 class="dp-ct">This server</h2><span class="mut sm">Version <?= h($d['version'] ?: '—') ?></span></div>
<?php if ($head && $head['hash']): $srv = $dep['server']; ?><div class="dp-kv"><span>Branch</span><b class="mono"><?= h($head['branch'] ?: 'detached HEAD') ?></b><span>Commit</span><b class="mono"><?php if ($gh['ready']): ?><a href="<?= h($gh['web'] . '/commit/' . $head['hash']) ?>" target="_blank" rel="noopener noreferrer"><?= h(substr($head['hash'], 0, 12)) ?></a><?php else: ?><?= h(substr($head['hash'], 0, 12)) ?><?php endif; ?></b><span>Last change</span><b><?= h($srv['at'] ? sl_ago($srv['at']) : '—') ?> · <?= h($srv['kind'] ?: '') ?></b><span>Message</span><b class="clip"><?= h($srv['message'] ?: '—') ?></b><?php if ($gh['ready']): ?><span>On GitHub</span><b><?= !empty($srv['on_github']) ? 'Yes, in the synced history' : 'Not in the synced branch history' ?></b><?php endif; ?></div>
<?php else: ?><p class="mut sm">This checkout has no readable .git folder, so the running commit is unknown. Record deployments by hand, or deploy with git.</p><?php endif; ?>
<h3 class="dp-h3">Releases<?= $d['openPulls'] ? ' · ' . (int) $d['openPulls'] . ' open pull requests' : '' ?></h3>
<?php foreach ($d['releases'] as $r): ?><a class="dp-li" href="<?= h($r->url) ?>" target="_blank" rel="noopener noreferrer"><i class="ic ic-sm i-tag" aria-hidden="true"></i><b class="mono"><?= h($r->tag) ?></b><span class="grow clip"><?= h($r->name !== $r->tag ? $r->name : '') ?></span><span class="mut sm"><?= h($r->published_at ? sl_ago($r->published_at) : '') ?></span></a><?php endforeach; ?><?php if (!$d['releases']): ?><p class="mut sm"><?= $gh['ready'] ? 'No releases or tags on GitHub.' : 'Connect GitHub to list releases.' ?></p><?php endif; ?>
</section></div>

<section class="dp-card flush" id="dp-table"><div class="dp-ch pad"><div><h2 class="dp-ct">Recent deployments</h2><div class="dp-sub"><?= count($dep['rows']) ?> of <?= count($dep['all']) ?> · Actions runs<?= $dep['allRuns'] ? ' (all workflows)' : ' of deploy workflows' ?>, GitHub deployments, manual records, this server</div></div>
<form class="dp-filters" method="get" data-dp-autosubmit><?php foreach (['run' => $_GET['run'] ?? ''] as $k => $v): if ($v !== ''): ?><input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>"><?php endif; endforeach; ?>
<label><span class="sr-only">Branch</span><select class="sl-sel native" name="branch" aria-label="Branch"><option value="">All branches</option><?php foreach ($dep['branches'] as $b): ?><option<?= $d['f']['branch'] === $b ? ' selected' : '' ?>><?= h($b) ?></option><?php endforeach; ?></select></label>
<label><span class="sr-only">Environment</span><select class="sl-sel native" name="env" aria-label="Environment"><option value="">All environments</option><?php foreach ($dep['envs'] as $e): ?><option<?= $d['f']['env'] === $e ? ' selected' : '' ?>><?= h($e) ?></option><?php endforeach; ?></select></label>
<label><span class="sr-only">Status</span><select class="sl-sel native" name="status" aria-label="Status"><option value="">Any status</option><?php foreach (['success', 'failure', 'running', 'queued', 'cancelled', 'rolled_back'] as $s): ?><option value="<?= $s ?>"<?= $d['f']['status'] === $s ? ' selected' : '' ?>><?= h(sl_dplState($s)[0]) ?></option><?php endforeach; ?></select></label>
<label><span class="sr-only">Source</span><select class="sl-sel native" name="source" aria-label="Source"><option value="">All sources</option><?php foreach ($d['sourceLabels'] as $k => $l): ?><option value="<?= $k ?>"<?= $d['f']['source'] === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></label>
<button class="sl-btn ghost sm" type="submit">Apply</button><?php if (array_filter($d['f'])): ?><a class="sl-btn ghost sm" href="<?= h(sl_url(['branch' => null, 'env' => null, 'status' => null, 'source' => null])) ?>">Clear</a><?php endif; ?></form></div>
<div class="sl-tw"><table class="sl-table dp-table"><thead><tr><th>Status</th><th>Deployment</th><th>Commit</th><th>Branch</th><th>Environment</th><th>Duration</th><th>By</th><th>When</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
<?php foreach ($d['table'] as $r): $s = sl_dplState($r['state']); $sel = $run && $r['run_id'] === (int) $run->id; $href = $r['run_id'] ? sl_url(['run' => $r['run_id']]) . '#dp-pipeline' : ($r['url'] ?: ''); ?>
<tr class="<?= $sel ? 'on' : '' ?><?= $r['run_id'] ? ' dp-click' : '' ?>"<?= $r['run_id'] ? ' data-href="' . h($href) . '"' : '' ?>>
<td><span class="sl-pill <?= h($s[1]) ?>"><?= h($s[0]) ?></span></td>
<td><div class="col"><?php if ($r['run_id']): ?><a class="fw5" href="<?= h($href) ?>"><?= h(mb_strimwidth((string) $r['title'], 0, 70, '…')) ?></a><?php else: ?><span class="fw5"><?= h(mb_strimwidth((string) $r['title'], 0, 70, '…')) ?></span><?php endif; ?><span class="mut sm"><span class="dp-src <?= h($r['source']) ?>"><?= h($d['sourceLabels'][$r['source']] ?? $r['source']) ?></span> <?= h($r['workflow']) ?></span></div></td>
<td class="mono sm"><?php if ($r['sha'] && $gh['ready']): ?><a href="<?= h($gh['web'] . '/commit/' . $r['sha']) ?>" target="_blank" rel="noopener noreferrer"><?= h(substr($r['sha'], 0, 7)) ?></a><?php else: ?><?= h($r['sha'] ? substr($r['sha'], 0, 7) : '—') ?><?php endif; ?></td>
<td class="mono sm"><?= h($r['branch'] ?: '—') ?></td><td class="sm"><?= h($r['env'] ?: '—') ?></td><td class="mono sm"><?= h(sl_ghDur($r['duration_s'])) ?></td><td class="sm"><?= h($r['actor'] ?: '—') ?></td><td class="sm mut" title="<?= h((string) $r['at']) ?>"><?= h($r['at'] ? sl_ago($r['at']) : '—') ?></td>
<td class="dp-act"><?php if ($r['url']): ?><a class="sl-btn ghost sm" href="<?= h($r['url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="Open on GitHub"><i class="ic ic-sm i-ext" aria-hidden="true"></i></a><?php endif; ?><?php if ($r['source'] === 'manual'): ?><button type="button" class="sl-btn ghost sm" aria-label="Delete record" data-sl-action="ajax/sl/dev_github_ajax.php" data-p-op="deploy_record_delete" data-p-id="<?= (int) substr($r['id'], 4) ?>" data-sl-confirm="Delete this deployment record?"><i class="ic ic-sm i-trash" aria-hidden="true"></i></button><?php endif; ?></td></tr>
<?php endforeach; ?>
<?php if (!$d['table']): ?><tr><td colspan="9"><div class="sl-empty"><?= array_filter($d['f']) ? 'Nothing matches these filters.' : 'No deployments yet. Connect GitHub or record one by hand.' ?></div></td></tr><?php endif; ?>
</tbody></table></div></section>

<div class="dp-split even">
<section class="dp-card"><div class="dp-ch"><h2 class="dp-ct">Changes on this server (git reflog)</h2><span class="mut sm">Pulls, resets and checkouts of this checkout</span></div>
<?php foreach ($d['reflog'] as $e): ?><div class="dp-li"><span class="dp-dot <?= $e['kind'] === 'reset' ? 'y' : 'g' ?>"></span><b class="mono"><?= h($e['hash']) ?></b><span class="grow clip"><?= h($e['message']) ?></span><span class="dp-src <?= $e['kind'] === 'reset' ? 'warn' : '' ?>"><?= h($e['kind'] === 'reset' ? 'rollback' : $e['kind']) ?></span><span class="mut sm"><?= h(sl_ago($e['ts'])) ?></span></div><?php endforeach; ?><?php if (!$d['reflog']): ?><p class="mut sm">No git reflog on this server.</p><?php endif; ?></section>
<section class="dp-card"><div class="dp-ch"><h2 class="dp-ct">Database migrations</h2><span class="mut sm">cdb_schema_migrations</span></div>
<?php foreach ($d['migrations'] as $m): ?><div class="dp-li"><i class="ic ic-sm i-database" aria-hidden="true"></i><span class="mono sm grow clip"><?= h($m->name) ?></span><span class="mut sm"><?= h(sl_date($m->applied_at, 'M j')) ?></span></div><?php endforeach; ?><?php if (!$d['migrations']): ?><p class="mut sm">No migration ledger.</p><?php endif; ?>
<?php if ($al['database']): ?><a class="sl-btn ghost sm" style="align-self:flex-start" href="<?= h(sl_url(['tab' => 'database', 'run' => null])) ?>"><i class="ic ic-sm i-database" aria-hidden="true"></i>Database deploy tools</a><?php endif; ?></section>
</div>

<div class="sl-dialog" id="dp-record" hidden><form class="sl-modal" data-sl-form action="ajax/sl/dev_github_ajax.php" method="post" aria-label="Record a deployment"><input type="hidden" name="op" value="deploy_record"><h2 class="sl-ct">Record a deployment</h2><p class="mut sm">For deployments made outside GitHub Actions (upload by FTP, cPanel, by hand).</p>
<div class="grid cols2" style="gap:10px"><label class="sl-field"><span class="sl-lab">Environment</span><input class="sl-in" name="environment" value="production" required list="dp-envs"><datalist id="dp-envs"><option>production</option><option>staging</option><option>development</option></datalist></label>
<label class="sl-field"><span class="sl-lab">Result</span><select class="sl-sel native" name="status"><option value="success">Success</option><option value="failure">Failed</option><option value="rolled_back">Rolled back</option></select></label>
<label class="sl-field"><span class="sl-lab">Version</span><input class="sl-in mono" name="version" value="<?= h($d['version']) ?>"></label><label class="sl-field"><span class="sl-lab">Commit</span><input class="sl-in mono" name="commit_sha" value="<?= h($head['hash'] ?? '') ?>" maxlength="40"></label>
<label class="sl-field"><span class="sl-lab">Branch</span><input class="sl-in mono" name="branch" value="<?= h($head['branch'] ?? '') ?>"></label><label class="sl-field"><span class="sl-lab">Duration (minutes)</span><input class="sl-in" name="duration_min" inputmode="decimal"></label>
<label class="sl-field"><span class="sl-lab">Deployed at</span><input class="sl-in" type="datetime-local" name="deployed_at" value="<?= h(date('Y-m-d\TH:i')) ?>"></label></div>
<label class="sl-field"><span class="sl-lab">Notes</span><input class="sl-in" name="notes" maxlength="500" placeholder="What was deployed"></label>
<div class="row g8"><button type="submit" class="sl-btn">Record</button><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button></div></form></div>
<?php endif; ?>

<?php if ($d['tab'] === 'database'):
$st = $d['stage'];
?>
<div class="dp-guards">
<span class="dp-guard <?= $d['importEnabled'] && !$d['demo'] ? 'ok' : 'bad' ?>"><i class="ic ic-sm <?= $d['importEnabled'] ? 'i-check' : 'i-lock' ?>" aria-hidden="true"></i>Import <?= $d['importEnabled'] ? 'enabled' : 'switched off' ?> <span class="mono">CDP_DB_IMPORT_ENABLED</span></span>
<span class="dp-guard ok"><i class="ic ic-sm i-shield" aria-hidden="true"></i>Password + typed confirmation + backup first</span>
<span class="dp-guard <?= $d['privateOutside'] ? 'ok' : 'bad' ?>"><i class="ic ic-sm i-folder" aria-hidden="true"></i><?= $d['privateOutside'] ? 'Uploads and backups stored outside the web root' : 'No private folder outside the web root: uploads and backups are off' ?></span>
<span class="dp-guard"><i class="ic ic-sm i-upload" aria-hidden="true"></i>Upload limit <?= h(sl_dbBytes($d['limit'])) ?> <span class="mut">(upload_max_filesize <?= h($d['iniUpload']) ?>, post_max_size <?= h($d['iniPost']) ?>)</span></span>
<span class="dp-guard"><i class="ic ic-sm i-database" aria-hidden="true"></i><?= h($d['dbName']) ?> · <?= (int) $d['dbTables'] ?> tables · <?= h(sl_dbBytes($d['dbBytes'])) ?></span>
</div>

<div class="dp-split">
<div class="col g16">
<?php if (!$st): ?>
<section class="dp-card"><div class="dp-ch"><div><h2 class="dp-ct">1 · Upload a database dump</h2><div class="dp-sub">A .sql or .sql.gz export. It is checked against this database before anything runs; schema statements in the file are never executed.</div></div></div>
<?php if (!$d['privateOutside']): ?><div class="dp-note bad"><i class="ic i-lock" aria-hidden="true"></i><span>Create a folder next to the web root named <span class="mono">swiftlane_private</span> (writable by PHP, not served), or set <span class="mono">CDP_PRIVATE_DIR</span> in config/config.php. Dumps and backups are never written inside the web root.</span></div>
<?php elseif (!$d['importEnabled'] || $d['demo']): ?><div class="dp-note"><i class="ic i-lock" aria-hidden="true"></i><span>Import is switched off on this server. Set <span class="mono">CDP_DB_IMPORT_ENABLED</span> to <span class="mono">true</span> in config/config.php only while you deploy data, then switch it off again.</span></div>
<?php else: ?><form class="dp-upload" data-dp-upload action="ajax/sl/dev_db_upload_ajax.php" method="post" enctype="multipart/form-data"><input type="hidden" name="op" value="stage">
<label class="dp-drop" for="dp-dump"><i class="ic i-upload" aria-hidden="true"></i><b>Choose a .sql or .sql.gz file</b><span class="mut sm" data-dp-filename>Up to <?= h(sl_dbBytes($d['limit'])) ?> (a .sql.gz may unpack to <?= h(sl_dbBytes($d['gzipCap'])) ?>)</span><input id="dp-dump" class="dp-file" type="file" name="dump" accept=".sql,.gz,application/sql,application/gzip" required data-max="<?= (int) $d['limit'] ?>"></label>
<div class="row g12 wrap"><button type="submit" class="sl-btn"><i class="ic ic-sm i-scan" aria-hidden="true"></i>Upload and check</button><div class="dp-prog grow" hidden data-dp-upprog role="progressbar" aria-label="Upload progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span></span></div><span class="mut sm" data-dp-upmsg></span></div></form><?php endif; ?>
</section>
<?php else: ?>
<section class="dp-card"><div class="dp-ch"><div><h2 class="dp-ct">2 · Check the plan</h2><div class="dp-sub"><span class="mono"><?= h($st['name']) ?></span> · <?= h(sl_dbBytes($st['bytes'])) ?> · sha256 <span class="mono"><?= h(substr($st['sha256'], 0, 16)) ?>…</span></div></div><button type="button" class="sl-btn ghost sm" data-sl-action="ajax/sl/dev_db_upload_ajax.php" data-p-op="discard" data-sl-confirm="Discard this upload?"><i class="ic ic-sm i-x" aria-hidden="true"></i>Discard</button></div>
<?php if ($st['issues']): ?><div class="dp-note bad"><i class="ic i-alert" aria-hidden="true"></i><span><b><?= count($st['issues']) ?> schema issues.</b> The file does not fit this database; fix the export (or run the migrations) and upload again.</span></div><ul class="dp-issues"><?php foreach ($st['issues'] as $i): ?><li class="mono sm"><?= h($i) ?></li><?php endforeach; ?></ul><?php endif; ?>
<div class="sl-tw"><table class="sl-table"><thead><tr><th>Table in the dump</th><th class="num">Rows now</th><th>Effect</th></tr></thead><tbody><?php foreach ($st['tables'] as $t): ?><tr><td class="mono sm"><?= h($t['name']) ?></td><td class="num mono sm"><?= $t['existing'] === null ? '<span class="dp-warn">missing</span>' : h(number_format($t['existing']) . ($t['existing'] >= 100001 ? '+' : '')) ?></td><td class="sm"><?php if ($t['protected']): ?><span class="dp-src warn">protected, kept</span><?php elseif (!empty($t['ownerOnly'])): ?><span class="dp-src warn">accounts/permissions: console owner only</span><?php elseif ($t['existing']): ?><span class="dp-warn">has rows: needs "replace"</span><?php else: ?><span class="dp-ok">empty: will be filled</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
</section>
<?php if (!$st['issues']): ?>
<section class="dp-card danger"><div class="dp-ch"><div><h2 class="dp-ct">3 · Apply to <?= h($d['dbName']) ?></h2><div class="dp-sub">A full backup is taken automatically first. The import runs in one transaction and is rolled back if anything fails.</div></div></div>
<form class="col g12" data-sl-form action="ajax/sl/dev_db_upload_ajax.php" method="post" data-dp-apply><input type="hidden" name="op" value="apply"><input type="hidden" name="job" value="<?= (int) $st['job'] ?>">
<fieldset class="dp-modes"><legend class="sl-lab">How to apply</legend>
<label class="dp-mode<?= $d['needsReplace'] ? ' off' : '' ?>"><input type="radio" name="mode" value="empty"<?= $d['needsReplace'] ? ' disabled' : ' checked' ?>><span><b>Fill empty tables only</b><span class="mut sm"><?= $d['needsReplace'] ? 'Not possible: some tables in the dump already have rows (see the plan).' : 'Safest. Stops if any table in the dump already has rows.' ?></span></span></label>
<label class="dp-mode"><input type="radio" name="mode" value="replace"<?= $d['needsReplace'] ? ' checked' : '' ?>><span><b>Replace the rows of the tables in the dump</b><span class="mut sm">Deletes the current rows of those tables, then inserts the dump's rows. Console access, the audit log and the migration ledger are never touched.</span></span></label></fieldset>
<div class="grid cols2" style="gap:10px"><label class="sl-field"><span class="sl-lab">Your password</span><input class="sl-in" type="password" name="password" autocomplete="current-password" required></label>
<label class="sl-field"><span class="sl-lab">Type <span class="mono"><?= h($d['confirmWord']) ?></span></span><input class="sl-in mono" name="confirm" autocomplete="off" required data-dp-confirm="<?= h($d['confirmWord']) ?>"></label></div>
<div class="row g8 wrap"><button type="submit" class="sl-btn dp-danger" data-dp-apply-btn disabled><i class="ic ic-sm i-database" aria-hidden="true"></i>Back up and import</button><span class="mut sm" data-dp-progress hidden>Backing up, then importing… keep this page open.</span></div></form>
</section><?php endif; ?>
<?php endif; ?>

<section class="dp-card"><div class="dp-ch"><div><h2 class="dp-ct">Job log</h2><div class="dp-sub">Backups, uploads and imports, newest first</div></div></div>
<?php foreach ($d['jobs'] as $j): $js = ['done' => ['Done', 'delivered'], 'failed' => ['Failed', 'overdue'], 'running' => ['Running', 'transit'], 'staged' => ['Staged', 'onway'], 'rejected' => ['Rejected', 'overdue'], 'discarded' => ['Discarded', 'plain'], 'expired' => ['Expired', 'plain'], 'interrupted' => ['Interrupted', 'overdue']][$j->status] ?? [ucfirst($j->status), 'plain']; ?>
<details class="dp-job"><summary><span class="dp-src"><?= h($j->kind) ?></span><b class="clip"><?= h($j->file_name ?: ($j->backup_file ?: '#' . $j->id)) ?></b><span class="mut sm"><?= h(trim((string) $j->who) ?: 'cron') ?> · <?= h(sl_ago($j->started_at)) ?><?= $j->mode ? ' · ' . h($j->mode) : '' ?></span><span class="sl-pill <?= h($js[1]) ?>"><?= h($js[0]) ?></span></summary><pre class="dp-pre"><?= h((string) $j->log ?: 'No log lines.') ?></pre></details>
<?php endforeach; ?><?php if (!$d['jobs']): ?><p class="mut sm">No database jobs yet.</p><?php endif; ?></section>
</div>

<section class="dp-card"><div class="dp-ch"><div><h2 class="dp-ct">Backups</h2><div class="dp-sub"><?= count($d['backups']) ?> file<?= count($d['backups']) === 1 ? '' : 's' ?> · <?= h(sl_dbBytes($d['backupsBytes'])) ?> · private folder, newest <?= (int) $d['keep'] ?> kept</div></div><?php if ($d['privateOutside']): ?><button type="button" class="sl-btn sm" data-sl-action="ajax/sl/dev_db_upload_ajax.php" data-p-op="backup_now" data-sl-confirm="Back up the whole database now?"><i class="ic ic-sm i-archive" aria-hidden="true"></i>Back up now</button><?php endif; ?></div>
<?php if ($d['legacyN']): ?><div class="dp-note bad"><i class="ic i-alert" aria-hidden="true"></i><span><b><?= (int) $d['legacyN'] ?> backup<?= $d['legacyN'] === 1 ? ' is' : 's are' ?> still in the web-root folder backups/</b>, where nginx or the PHP built-in server can serve <?= $d['legacyN'] === 1 ? 'it' : 'them' ?> to anyone who knows the name. <button type="button" class="sl-btn sm" data-dp-pw="move_legacy" data-file="" data-label="Move <?= (int) $d['legacyN'] ?> backup<?= $d['legacyN'] === 1 ? '' : 's' ?> to the private folder">Move to the private folder</button></span></div><?php endif; ?>
<?php if ($d['lastBackup']): $age = (time() - strtotime($d['lastBackup']['at'])) / 86400; ?><div class="dp-note <?= $age > 7 ? 'bad' : '' ?>"><i class="ic i-<?= $age > 7 ? 'alert' : 'check' ?>" aria-hidden="true"></i><span>Latest backup <?= h(sl_ago($d['lastBackup']['at'])) ?>. Copy backups off this server too: a backup on the same disk is lost with it.</span></div><?php endif; ?>
<?php foreach (array_slice($d['backups'], 0, 25) as $b): ?><div class="dp-li bk"><i class="ic ic-sm i-file" aria-hidden="true"></i><span class="col grow" style="min-width:0"><span class="sm fw5"><span class="dp-nw"><?= h(sl_date($b['at'], 'M j, Y · H:i:s')) ?></span> · <span class="dp-nw"><?= h(sl_dbBytes($b['bytes'])) ?></span><?php if ($b['legacy']): ?> <span class="dp-src warn">web root</span><?php endif; ?></span><span class="mono mut dp-bkname" title="<?= h($b['name']) ?>"><?= h($b['name']) ?></span></span>
<button type="button" class="sl-btn ghost sm" data-dp-pw="download" data-file="<?= h($b['name']) ?>" aria-label="Download <?= h($b['name']) ?>"><i class="ic ic-sm i-download" aria-hidden="true"></i></button><button type="button" class="sl-btn ghost sm" data-dp-pw="delete_backup" data-file="<?= h($b['name']) ?>" aria-label="Delete <?= h($b['name']) ?>"><i class="ic ic-sm i-trash" aria-hidden="true"></i></button></div><?php endforeach; ?>
<?php if (!$d['backups']): ?><div class="sl-empty">No backups yet. Make the first one.</div><?php endif; ?>
<p class="mut sm">Scheduled backup (cron): <span class="mono">php helpers/dev_github.php backup</span></p>
</section>
</div>

<div class="sl-dialog" id="dp-pw" hidden><form class="sl-modal" data-dp-pwform action="ajax/sl/dev_db_upload_ajax.php" method="post" aria-label="Confirm with your password"><input type="hidden" name="op" value=""><input type="hidden" name="file" value=""><h2 class="sl-ct" data-dp-pwtitle>Confirm with your password</h2><p class="mut sm mono" data-dp-pwfile></p>
<label class="sl-field"><span class="sl-lab">Your password</span><input class="sl-in" type="password" name="password" autocomplete="current-password" required></label>
<div class="row g8"><button type="submit" class="sl-btn">Continue</button><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button></div></form></div>
<?php endif; ?>
</div>
<?= $dplEnd ?>
