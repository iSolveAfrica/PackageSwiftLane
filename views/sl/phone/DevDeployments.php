<?php
/** M-DevDeploy: recent deployments. Data: sl_dplDeploymentsData (same as the web DevDeployments). Writes: ajax/sl/dev_github_ajax.php (existing ops). */
require_once CDP_ROOT . '/views/sl/data/dev_phone.php';
$slPhone['nav'] = 'dev';
$slPhone['tab'] = 'Console';
$al = $d['allowed'] ?? [];
$gh = $d['gh'] ?? ['ready' => false];
$head = $d['head'] ?? null;
$sub = $head && !empty($head['hash']) ? (($head['branch'] ?: 'detached') . ' @ ' . substr((string) $head['hash'], 0, 7)) : (string) ($d['version'] ?? '');
?>
<?= sl_phoneTop('Deployments', $sub, ['back' => sl_route('DevOverview')], sl_phDevMore()) ?>
<main class="mx-body">
<?php if (($d['tab'] ?? 'pipeline') === 'database'): $lb = $d['lastBackup'] ?? null; ?>
<section class="mx-card mx-kv">
<div><span>Database</span><b><?= h((string) ($d['dbName'] ?? '')) ?></b></div>
<div><span>Tables</span><b><?= (int) ($d['dbTables'] ?? 0) ?> · <?= h(sl_dbBytes((int) ($d['dbBytes'] ?? 0))) ?></b></div>
<div><span>Import</span><b><?= !empty($d['importEnabled']) && empty($d['demo']) ? 'Enabled' : 'Switched off' ?></b></div>
<div><span>Backups kept</span><b><?= count($d['backups'] ?? []) ?></b></div>
<div><span>Last backup</span><b><?= $lb ? h(sl_ago(is_numeric($lb['at'] ?? null) ? date('Y-m-d H:i:s', (int) $lb['at']) : (string) ($lb['at'] ?? ''))) : 'None yet' ?></b></div>
</section>
<div class="mx-ban info"><i class="ic ic-sm i-info" aria-hidden="true"></i><span>Uploading and importing a database dump is done on the full page.</span></div>
<div class="mx-btns"><a class="mx-btn" href="<?= h(sl_url(['view' => 'web'])) ?>">Open database deploy</a><a class="mx-btn sec" href="<?= h(sl_url(['tab' => null])) ?>">Back to deployments</a></div>
<?php else: ?>
<?php if (empty($gh['ready'])): ?><div class="mx-ban info"><i class="ic ic-sm i-info" aria-hidden="true"></i><span>GitHub is not connected: this list shows the server's checkout and deployments recorded by hand.</span></div><?php endif; ?>
<?php if (!empty($d['kpis'])): ?>
<section class="mx-k c2">
<?php foreach (array_slice($d['kpis'], 0, 4) as $kp): ?><a class="mx-kt" href="<?= h($kp['href']) ?>"><div class="mx-kh"><small><?= h($kp['l']) ?></small></div><b><?= h($kp['v'] . $kp['u']) ?></b></a><?php endforeach; ?>
</section>
<?php endif; ?>
<section class="mx-card mx-list">
<?php foreach (array_slice($d['table'] ?? [], 0, 25) as $i => $r): $s = sl_dplState($r['state']); $isLive = $r['source'] === 'server'; $href = $r['url'] ?: ''; $tag = $href !== '' ? 'a' : 'div'; ?>
<<?= $tag ?> class="mx-row"<?= $href !== '' ? ' href="' . h($href) . '" target="_blank" rel="noopener noreferrer"' : '' ?>><span class="mx-li"><i class="ic i-rocket" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h(mb_strimwidth((string) $r['title'], 0, 60, '…')) ?></b><small><?= h(trim(($r['sha'] ? substr((string) $r['sha'], 0, 7) . ' · ' : '') . ($r['branch'] ? $r['branch'] . ' · ' : '') . ($r['at'] ? sl_ago($r['at']) : ''), ' ·')) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($isLive ? 'delivered' : $s[1]) ?>"><?= h($isLive ? 'Live' : $s[0]) ?></span></span></<?= $tag ?>>
<?php endforeach; ?>
<?php if (empty($d['table'])): ?><?= sl_mxEmpty('rocket', 'No deployments yet', 'Connect GitHub or record one by hand.') ?><?php endif; ?>
</section>
<details class="mx-card" style="padding:14px"><summary style="cursor:pointer;font-weight:600;min-height:28px">Record a deployment</summary>
<form class="mx-form" data-sl-form data-sl-reload action="ajax/sl/dev_github_ajax.php" method="post" style="padding:12px 0 0"><input type="hidden" name="op" value="deploy_record">
<label class="mx-f"><small>Environment</small><input class="mx-in" name="environment" value="production" required></label>
<label class="mx-f"><small>Result</small><select class="mx-in" name="status"><option value="success">Success</option><option value="failure">Failed</option><option value="rolled_back">Rolled back</option></select></label>
<label class="mx-f"><small>Version</small><input class="mx-in" name="version" value="<?= h((string) ($d['version'] ?? '')) ?>"></label>
<label class="mx-f"><small>Commit</small><input class="mx-in" name="commit_sha" value="<?= h((string) ($head['hash'] ?? '')) ?>" maxlength="40" autocapitalize="off"></label>
<label class="mx-f"><small>Branch</small><input class="mx-in" name="branch" value="<?= h((string) ($head['branch'] ?? '')) ?>" autocapitalize="off"></label>
<label class="mx-f"><small>Duration (minutes)</small><input class="mx-in" name="duration_min" inputmode="decimal"></label>
<label class="mx-f"><small>Deployed at</small><input class="mx-in" type="datetime-local" name="deployed_at" value="<?= h(date('Y-m-d\TH:i')) ?>"></label>
<label class="mx-f"><small>Notes</small><input class="mx-in" name="notes" maxlength="500" placeholder="What was deployed"></label>
<button type="submit" class="mx-btn">Record</button></form></details>
<div class="mx-btns"><?php if (!empty($gh['ready']) && !empty($al['github'])): ?><button type="button" class="mx-btn" data-sl-action="ajax/sl/dev_github_ajax.php" data-p-op="gh_sync">Sync GitHub</button><?php endif; ?><?php if (!empty($al['database'])): ?><a class="mx-btn sec" href="<?= h(sl_url(['tab' => 'database', 'run' => null])) ?>">Database deploy</a><?php endif; ?></div>
<?php endif; ?>
</main>
