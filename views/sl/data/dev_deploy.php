<?php
/**
 * Data for the developer console "deploy" boards: DevDeployments (pipeline, deployments, database
 * tools), DevIntegrations (GitHub: connection, Actions, commits, pull requests, releases, plus the
 * existing integrations list) and the DevIssues change log built from commits.
 * Sources: helpers/dev_github.php (cdb_dev_gh_* cache, cdb_dev_deploy_records, cdb_dev_db_jobs,
 * backups/), helpers/devops.php (.git of this checkout, read without a shell), views/sl/data/dev.php.
 */
require_once CDP_ROOT . '/views/sl/data/dev.php';
require_once CDP_ROOT . '/helpers/dev_github.php';

/** Shared dark DevOps stylesheet of the console (package "observe"), when it is installed. */
function sl_dplStyles(): array
{
    return is_file(CDP_ROOT . '/assets/swiftlane/dev_theme.css') ? ['assets/swiftlane/dev_theme.css'] : [];
}

/** Opens the shared developer sub-navigation (views/sl/pages/partials/dev_subnav.php) when present; returns the closing markup. */
function sl_dplSubnav(string $active, $user): string
{
    $f = CDP_ROOT . '/views/sl/pages/partials/dev_subnav.php';
    if (!is_file($f)) {
        return '';
    }
    $dvxActive = $active;
    include $f;
    return function_exists('sl_dvxSubnavEnd') ? sl_dvxSubnavEnd() : '</div></div>';
}

/** Status word -> [label, pill class, dot colour]. */
function sl_dplState(string $s): array
{
    return [
        'success' => ['Success', 'delivered', '#3FD08F'], 'failure' => ['Failed', 'overdue', '#E5545A'], 'running' => ['Running', 'transit', '#5B9BF0'],
        'queued' => ['Queued', 'onway', '#EBB43C'], 'cancelled' => ['Cancelled', 'plain', '#8E8A82'], 'skipped' => ['Skipped', 'plain', '#8E8A82'],
        'rolled_back' => ['Rolled back', 'onway', '#EBB43C'], 'superseded' => ['Superseded', 'plain', '#8E8A82'], 'absent' => ['Not in workflow', 'plain', '#5d6070'],
        'unknown' => ['Unknown', 'plain', '#8E8A82'], 'live' => ['Running here', 'transit', '#5B9BF0'],
    ][$s] ?? [ucfirst($s), 'plain', '#8E8A82'];
}

/** Section links shown on the deploy boards (each hidden when the section is not allowed). */
function sl_dplAllowed($user): array
{
    $out = [];
    foreach (['deployments', 'database', 'integrations', 'github', 'issues', 'changelog'] as $s) {
        $out[$s] = sl_ghSectionAllowed($user, $s);
    }
    return $out;
}

function sl_dplSpark(array $vals, float $w = 160, float $h = 36): array
{
    $vals = array_values(array_map('floatval', $vals));
    if (count($vals) < 2 || max($vals) <= 0) {
        return ['line' => '', 'area' => '', 'w' => $w, 'h' => $h];
    }
    $max = max($vals) * 1.12;
    return ['line' => sl_svgLinePath($vals, $w, $h, 3, $max), 'area' => sl_svgAreaPath($vals, $w, $h, 3, $max), 'w' => $w, 'h' => $h];
}

/* ---------------------------------------------------------------- Deployments */

function sl_dplDeploymentsData($user, $core): array
{
    $d = ['allowed' => sl_dplAllowed($user), 'gh' => sl_ghPublicConfig(), 'version' => sl_devVersion()];
    // the page needs "deployments"; the Database tab also needs "database" (same rule as dev_db_upload_ajax.php)
    $tab = ($_GET['tab'] ?? '') === 'database' && $d['allowed']['database'] ? 'database' : 'pipeline';
    $d['tab'] = $tab;
    $d['tabs'] = [];
    if ($d['allowed']['deployments']) {
        $d['tabs'][] = ['key' => 'pipeline', 'label' => 'Pipelines and deployments', 'icon' => 'i-rocket', 'href' => sl_url(['tab' => null]), 'on' => $tab === 'pipeline'];
    }
    if ($d['allowed']['database']) {
        $d['tabs'][] = ['key' => 'database', 'label' => 'Database deploy', 'icon' => 'i-database', 'href' => sl_url(['tab' => 'database', 'run' => null]), 'on' => $tab === 'database'];
    }
    $head = cdp_devGitHead();
    $d['head'] = $head;
    if ($tab === 'database') {
        return $d + sl_dplDatabaseData();
    }
    $f = [];
    foreach (['branch', 'env', 'status', 'source'] as $k) {
        $f[$k] = mb_substr(trim((string) ($_GET[$k] ?? '')), 0, 160);
    }
    $d['f'] = $f;
    $dep = sl_ghDeployments($f);
    $d['dep'] = $dep;
    $rows = $dep['rows'];
    // KPIs, last 30 days, of the filtered list (this server's checkout is not counted as a run)
    $since = strtotime('-30 days');
    $win = array_values(array_filter($rows, fn($r) => $r['source'] !== 'server' && $r['at'] && strtotime($r['at']) >= $since));
    $ok = count(array_filter($win, fn($r) => $r['state'] === 'success'));
    $bad = count(array_filter($win, fn($r) => in_array($r['state'], ['failure', 'rolled_back'], true)));
    $durs = array_values(array_filter(array_column($win, 'duration_s'), fn($v) => $v !== null));
    $days = [];
    for ($i = 13; $i >= 0; $i--) {
        $days[date('Y-m-d', strtotime("-$i days"))] = ['n' => 0, 'ok' => 0, 'bad' => 0, 'dur' => []];
    }
    foreach ($win as $r) {
        $k = substr((string) $r['at'], 0, 10);
        if (!isset($days[$k])) continue;
        $days[$k]['n']++;
        if ($r['state'] === 'success') $days[$k]['ok']++;
        if (in_array($r['state'], ['failure', 'rolled_back'], true)) $days[$k]['bad']++;
        if ($r['duration_s'] !== null) $days[$k]['dur'][] = $r['duration_s'];
    }
    $rate = array_map(fn($x) => ($x['ok'] + $x['bad']) ? 100 * $x['ok'] / ($x['ok'] + $x['bad']) : 0, $days);
    $avgD = array_map(fn($x) => $x['dur'] ? array_sum($x['dur']) / count($x['dur']) : 0, $days);
    $d['kpis'] = [
        ['l' => 'Success rate', 'v' => ($ok + $bad) ? number_format(100 * $ok / ($ok + $bad), 1) : '—', 'u' => ($ok + $bad) ? '%' : '', 's' => $ok . ' ok / (' . $ok . ' ok + ' . $bad . ' failed) · 30 days', 'spark' => sl_dplSpark($rate), 'c' => 'g', 'href' => sl_url(['status' => 'success'])],
        ['l' => 'Total deployments', 'v' => (string) count($win), 'u' => '', 's' => '30 days · cancelled and running included', 'spark' => sl_dplSpark(array_column($days, 'n')), 'c' => 'b', 'href' => sl_url(['status' => null])],
        ['l' => 'Failed', 'v' => (string) $bad, 'u' => '', 's' => $bad ? 'Click to see them' : 'None in 30 days', 'spark' => sl_dplSpark(array_column($days, 'bad')), 'c' => $bad ? 'r' : 'g', 'href' => sl_url(['status' => 'failure'])],
        ['l' => 'Avg deploy time', 'v' => $durs ? sl_ghDur((int) round(array_sum($durs) / count($durs))) : '—', 'u' => '', 's' => $durs ? count($durs) . ' timed runs' : 'No timed runs yet', 'spark' => sl_dplSpark($avgD), 'c' => 'v', 'href' => '#dp-table'],
    ];
    $mx = max(1, max(array_column($days, 'n')));
    $d['freq'] = array_map(fn($k, $x) => ['l' => date('M j', strtotime($k)), 'n' => $x['n'], 'ok' => $x['ok'], 'bad' => $x['bad'], 'hOk' => round(100 * $x['ok'] / $mx), 'hBad' => round(100 * $x['bad'] / $mx), 'hOther' => round(100 * ($x['n'] - $x['ok'] - $x['bad']) / $mx)], array_keys($days), $days);
    // selected run for the pipeline
    $runId = (int) ($_GET['run'] ?? 0);
    if (!$runId) {
        foreach ($rows as $r) {
            if ($r['run_id']) { $runId = $r['run_id']; break; }
        }
    }
    $run = $runId && cdp_devTableExists('cdb_dev_gh_runs') ? cdp_devOne('SELECT * FROM cdb_dev_gh_runs WHERE id = :i', [':i' => $runId]) : null;
    $d['run'] = $run;
    $d['runState'] = $run ? sl_ghRunState($run) : '';
    $d['pipeline'] = sl_ghPipeline($run);
    $d['jobs'] = $run && $run->jobs_json ? (json_decode((string) $run->jobs_json, true) ?: []) : [];
    $d['table'] = array_slice($rows, 0, 60);
    $d['sourceLabels'] = ['actions' => 'Actions', 'github' => 'GitHub deployment', 'manual' => 'Manual', 'server' => 'This server'];
    $d['releases'] = cdp_devTableExists('cdb_dev_gh_releases') ? cdp_devQ('SELECT tag, name, published_at, url, prerelease FROM cdb_dev_gh_releases WHERE draft = 0 ORDER BY published_at DESC LIMIT 6') : [];
    $d['openPulls'] = cdp_devTableExists('cdb_dev_gh_pulls') ? (int) (cdp_devOne("SELECT COUNT(*) AS n FROM cdb_dev_gh_pulls WHERE state = 'open'")->n ?? 0) : 0;
    $d['reflog'] = array_slice(cdp_devGitLog(12), 0, 8);
    $d['migrations'] = sl_tableExists('cdb_schema_migrations') ? sl_rows('SELECT name, applied_at FROM cdb_schema_migrations ORDER BY applied_at DESC LIMIT 6') : [];
    $d['manualIds'] = [];
    return $d;
}

function sl_dplDatabaseData(): array
{
    $d = [];
    $d['importEnabled'] = defined('CDP_DB_IMPORT_ENABLED') && CDP_DB_IMPORT_ENABLED === true;
    $d['demo'] = CDP_APP_MODE_DEMO === true;
    $d['dbName'] = CDP_DB_NAME;
    $sz = sl_row('SELECT COUNT(*) AS n, SUM(DATA_LENGTH + INDEX_LENGTH) AS b FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
    $d['dbTables'] = (int) ($sz->n ?? 0);
    $d['dbBytes'] = (int) ($sz->b ?? 0);
    $d['limit'] = sl_dbUploadLimit();
    $d['iniUpload'] = (string) ini_get('upload_max_filesize');
    $d['iniPost'] = (string) ini_get('post_max_size');
    $d['privateOutside'] = sl_ghPrivateOutsideRoot();
    $d['backups'] = sl_dbBackups();
    sl_dbJobsTidy();
    $d['jobs'] = sl_dbJobs(15);
    $st = $_SESSION['sl_dev_db_stage'] ?? null;
    $d['stage'] = $st && is_file((string) ($st['path'] ?? '')) && time() - (int) $st['time'] <= 3600 ? $st['plan'] ?? null : null;
    $d['confirmWord'] = 'IMPORT ' . CDP_DB_NAME;
    $d['gzipCap'] = sl_dbGzipCap();
    $d['keep'] = sl_dbKeepBackups();
    $d['legacyN'] = count(array_filter($d['backups'], fn($b) => $b['legacy']));
    // a table in the dump that already has rows (and is not protected) means "empty" mode cannot work
    $d['needsReplace'] = $d['stage'] ? (bool) array_filter($d['stage']['tables'], fn($t) => !$t['protected'] && $t['existing']) : false;
    $d['backupsBytes'] = array_sum(array_column($d['backups'], 'bytes'));
    $last = $d['backups'][0] ?? null;
    $d['lastBackup'] = $last;
    return $d;
}

/* ---------------------------------------------------------------- Integrations */

function sl_dplIntegrationsData($user, $core): array
{
    $allowed = sl_dplAllowed($user);
    $tabs = $allowed['github'] ? ['github' => 'GitHub', 'actions' => 'Actions', 'commits' => 'Commits', 'pulls' => 'Pull requests', 'releases' => 'Releases', 'all' => 'All integrations'] : ['all' => 'All integrations'];
    $tab = (string) ($_GET['tab'] ?? '');
    if (!isset($tabs[$tab])) $tab = array_key_first($tabs);
    $d = $tab === 'all' ? sl_devIntegrationsData($user, $core) : ['flash' => ''];
    $d['allowed'] = $allowed;
    $d['tab'] = $tab;
    $gh = sl_ghPublicConfig();
    $d['gh'] = $gh;
    $count = function (string $t) {
        return cdp_devTableExists($t) ? (int) (cdp_devOne("SELECT COUNT(*) AS n FROM $t")->n ?? 0) : 0;
    };
    $n = ['actions' => $count('cdb_dev_gh_runs'), 'commits' => $count('cdb_dev_gh_commits'), 'pulls' => $count('cdb_dev_gh_pulls'), 'releases' => $count('cdb_dev_gh_releases')];
    $d['tabs'] = [];
    foreach ($tabs as $k => $l) {
        $d['tabs'][] = ['label' => $l, 'n' => $n[$k] ?? null, 'cls' => $tab === $k ? 'on' : '', 'href' => sl_url(['tab' => $k === 'github' ? null : $k, 'group' => null, 'wf' => null, 'branch' => null, 'st' => null, 'run' => null, 'q' => null])];
    }
    if ($tab === 'all') {
        return $d;
    }
    $d['counts'] = $n;
    if ($tab === 'github') {
        $runs = cdp_devTableExists('cdb_dev_gh_runs') ? cdp_devQ('SELECT * FROM cdb_dev_gh_runs ORDER BY created_at DESC LIMIT 200') : [];
        $okRuns = array_filter($runs, fn($r) => sl_ghRunState($r) === 'success');
        $failRuns = array_filter($runs, fn($r) => sl_ghRunState($r) === 'failure');
        $fin = count($okRuns) + count($failRuns); // same definition as Deployments: success / (success + failure)
        $d['stats'] = [
            ['l' => 'Workflow runs', 'v' => count($runs), 's' => count(array_unique(array_map(fn($r) => $r->workflow_name, $runs))) . ' workflows', 'href' => sl_url(['tab' => 'actions'])],
            ['l' => 'Run success', 'v' => $fin ? number_format(100 * count($okRuns) / $fin, 1) . '%' : '—', 's' => count($okRuns) . ' ok / (' . count($okRuns) . ' + ' . count($failRuns) . ' failed) · all workflows', 'href' => sl_url(['tab' => 'actions', 'st' => 'failure'])],
            ['l' => 'Commits cached', 'v' => $n['commits'], 's' => 'Branch ' . ($gh['branch'] ?: ($gh['default_branch'] ?: '—')), 'href' => sl_url(['tab' => 'commits'])],
            ['l' => 'Open pull requests', 'v' => cdp_devTableExists('cdb_dev_gh_pulls') ? (int) (cdp_devOne("SELECT COUNT(*) AS n FROM cdb_dev_gh_pulls WHERE state = 'open'")->n ?? 0) : 0, 's' => $n['pulls'] . ' recent', 'href' => sl_url(['tab' => 'pulls'])],
        ];
        $d['recentRuns'] = array_slice($runs, 0, 6);
        $d['recentCommits'] = cdp_devTableExists('cdb_dev_gh_commits') ? cdp_devQ('SELECT sha, title, ctype, author, author_login, committed_at, url FROM cdb_dev_gh_commits ORDER BY committed_at DESC LIMIT 6') : [];
        $d['privateOutside'] = sl_ghPrivateOutsideRoot();
        $d['allowedHosts'] = sl_ghAllowedHosts();
        $d['sodium'] = function_exists('sodium_crypto_secretbox');
        $d['decryptOk'] = null;
        if ($gh['has_token']) {
            $full = sl_ghConfig();
            $d['decryptOk'] = sl_ghDecrypt($full['token_box']) !== null;
        }
        $d['cron'] = 'php ' . CDP_ROOT . '/helpers/dev_github.php sync';
        $d['lastCron'] = sl_tableExists('cdb_cron_runs') ? sl_row("SELECT status, started_at, message AS summary FROM cdb_cron_runs WHERE job = 'dev_github_sync' ORDER BY id DESC") : null;
    }
    if ($tab === 'actions') {
        $wf = mb_substr((string) ($_GET['wf'] ?? ''), 0, 160);
        $br = mb_substr((string) ($_GET['branch'] ?? ''), 0, 160);
        $st = (string) ($_GET['st'] ?? '');
        $all = cdp_devTableExists('cdb_dev_gh_runs') ? cdp_devQ('SELECT * FROM cdb_dev_gh_runs ORDER BY created_at DESC LIMIT 300') : [];
        $d['workflows'] = array_values(array_unique(array_filter(array_map(fn($r) => (string) $r->workflow_name, $all))));
        $d['branches'] = array_values(array_unique(array_filter(array_map(fn($r) => (string) $r->branch, $all))));
        sort($d['branches']);
        $d['f'] = ['wf' => $wf, 'branch' => $br, 'st' => $st];
        $d['runs'] = array_values(array_filter($all, fn($r) => ($wf === '' || $r->workflow_name === $wf) && ($br === '' || $r->branch === $br) && ($st === '' || sl_ghRunState($r) === $st)));
        $sel = (int) ($_GET['run'] ?? 0);
        $d['sel'] = null;
        foreach ($d['runs'] as $r) {
            if ((int) $r->id === $sel) $d['sel'] = $r;
        }
        $d['selJobs'] = $d['sel'] && $d['sel']->jobs_json ? (json_decode((string) $d['sel']->jobs_json, true) ?: []) : [];
        $d['selPipe'] = $d['sel'] ? sl_ghPipeline($d['sel']) : [];
    }
    if ($tab === 'commits') {
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $d['q'] = $q;
        $lq = addcslashes($q, '%_\\'); // a literal search: % and _ are not wildcards
        $d['commits'] = cdp_devTableExists('cdb_dev_gh_commits') ? cdp_devQ('SELECT * FROM cdb_dev_gh_commits ' . ($q !== '' ? 'WHERE title LIKE :q OR author LIKE :q2 OR sha LIKE :q3 ' : '') . 'ORDER BY committed_at DESC LIMIT 150', $q !== '' ? [':q' => "%$lq%", ':q2' => "%$lq%", ':q3' => "$lq%"] : []) : [];
        $d['runBySha'] = [];
        if (cdp_devTableExists('cdb_dev_gh_runs')) {
            foreach (cdp_devQ('SELECT id, head_sha, status, conclusion, workflow_name FROM cdb_dev_gh_runs ORDER BY created_at ASC LIMIT 300') as $r) {
                $d['runBySha'][$r->head_sha] = $r;
            }
        }
    }
    if ($tab === 'pulls') {
        $d['pulls'] = cdp_devTableExists('cdb_dev_gh_pulls') ? cdp_devQ('SELECT * FROM cdb_dev_gh_pulls ORDER BY updated_at DESC LIMIT 60') : [];
    }
    if ($tab === 'releases') {
        $d['releases'] = cdp_devTableExists('cdb_dev_gh_releases') ? cdp_devQ('SELECT * FROM cdb_dev_gh_releases ORDER BY published_at DESC LIMIT 60') : [];
    }
    return $d;
}

/* ------------------------------------------------- Issues: change log from commits */

function sl_dplIssuesData($user, $core): array
{
    $allowed = sl_dplAllowed($user);
    if (!$allowed['issues']) {
        $_GET['tab'] = 'log'; // change log only
    } elseif (($_GET['tab'] ?? '') === 'log' && !$allowed['changelog']) {
        $_GET['tab'] = 'issues';
    }
    $d = sl_devIssuesData($user, $core);
    $d['allowed'] = $allowed;
    if (!$allowed['changelog']) {
        $d['tabs'] = array_values(array_filter($d['tabs'], fn($t) => strpos($t['href'], 'tab=log') === false));
    }
    if (!$allowed['issues']) {
        $d['tabs'] = array_values(array_filter($d['tabs'], fn($t) => strpos($t['href'], 'tab=log') !== false));
        $d['kpis'] = [];
        $d['ready'] = false;
        $d['changelogOnly'] = true;
    }
    $nCommits = cdp_devTableExists('cdb_dev_gh_commits') ? (int) (cdp_devOne('SELECT COUNT(*) AS n FROM cdb_dev_gh_commits')->n ?? 0) : 0;
    foreach ($d['tabs'] as &$t) {
        if (strpos($t['href'], 'tab=log') !== false && $nCommits) $t['n'] = $nCommits;
    }
    unset($t);
    $d['gh'] = sl_ghPublicConfig();
    $d['version'] = sl_devVersion();
    $d['headSha'] = (string) (cdp_devGitHead()['hash'] ?? '');
    if (!empty($d['tLog'])) {
        $src = ($_GET['src'] ?? 'commits') === 'manual' ? 'manual' : 'commits';
        $d['src'] = $src;
        $group = ($_GET['group'] ?? 'release') === 'day' ? 'day' : 'release';
        $type = preg_match('/^[a-z]{2,12}$/', (string) ($_GET['type'] ?? '')) ? (string) $_GET['type'] : '';
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $merges = !empty($_GET['merges']);
        $d['cl'] = ['group' => $group, 'type' => $type, 'q' => $q, 'merges' => $merges];
        $d['srcTabs'] = [['label' => 'From commits', 'cls' => $src === 'commits' ? 'on' : '', 'href' => sl_url(['src' => null])], ['label' => 'Release notes (manual)', 'cls' => $src === 'manual' ? 'on' : '', 'href' => sl_url(['src' => 'manual', 'type' => null, 'group' => null, 'q' => null, 'merges' => null])]];
        if ($src === 'commits') {
            $log = sl_ghChangelog(['group' => $group, 'type' => $type, 'q' => $q, 'merges' => $merges]);
            $d['log'] = $log;
            $d['groupTabs'] = [['label' => 'By release', 'cls' => $group === 'release' ? 'on' : '', 'href' => sl_url(['group' => null])], ['label' => 'By day', 'cls' => $group === 'day' ? 'on' : '', 'href' => sl_url(['group' => 'day'])]];
            $d['typeChips'] = [['label' => 'All', 'n' => array_sum($log['types']), 'cls' => $type === '' ? 'on' : '', 'href' => sl_url(['type' => null])]];
            foreach ($log['labels'] as $k => $l) {
                if (!empty($log['types'][$k]) || $type === $k) {
                    $d['typeChips'][] = ['label' => $l, 'n' => $log['types'][$k] ?? 0, 'cls' => ($type === $k ? 'on ' : '') . 't-' . $k, 'href' => sl_url(['type' => $k])];
                }
            }
        }
    }
    return $d;
}
