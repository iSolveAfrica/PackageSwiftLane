<?php
/**
 * Data for the staff AI assistant (StaffAI, AiChatMobile) and the AI centre (AiCenter).
 * Logic stays in helpers/ai_assistant*.php; chat goes through ajax/ai_assistant/chat_ajax.php and
 * settings through ajax/ai_assistant/settings_ajax.php. Keys are only ever shown masked.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/ai_assistant.php';
require_once CDP_ROOT . '/helpers/ai_assistant_tools.php';

function sl_aiBase($user): array
{
    $ready = cdp_aiEnsureTables();
    $s = cdp_aiSettings(true);
    return [
        'ready' => $ready, 'settings' => $s, 'configured' => cdp_aiConfigured(), 'hasKey' => cdp_aiApiKey() !== '',
        'enabled' => $s['enabled'] === '1', 'canUse' => cdp_aiCanUse($user), 'canManage' => cdp_aiCanManage($user), 'canInsights' => cdp_aiCanSeeInsights($user),
        'provider' => $s['provider'], 'providerLabel' => cdp_aiProviderLabel($s['provider']), 'model' => $s['model'], 'providers' => cdp_aiProviders(),
    ];
}

function sl_aiStaffData($user, $core, $userData): array
{
    $d = sl_aiBase($user);
    $uid = (int) ($_SESSION['userid'] ?? 0);
    $d['first'] = $userData->fname ?: ($userData->username ?? '');
    $convs = $d['ready'] ? cdp_aiConversations($uid, 30) : [];
    $cid = (int) ($_GET['c'] ?? 0);
    $d['convs'] = array_map(fn($c) => ['id' => $c['id'], 'title' => $c['title'], 'when' => sl_ago($c['updated_at']), 'on' => $c['id'] === $cid], $convs);
    $d['conv'] = null;
    $d['msgs'] = [];
    if ($cid && ($conv = cdp_aiConversation($cid, $uid))) {
        $d['conv'] = ['id' => (int) $conv->id, 'title' => (string) $conv->title];
        foreach (cdp_aiMessages($conv->id) as $m) {
            if (!in_array($m['role'], ['user', 'assistant'], true)) continue;
            $d['msgs'][] = ['me' => $m['role'] === 'user', 'text' => $m['content'], 'tools' => array_map(fn($t) => ($t['name'] ?? '') . (!empty($t['summary']) ? ' · ' . $t['summary'] : ''), (array) $m['tools']), 'at' => sl_date($m['created_at'], 'H:i')];
        }
    }
    // "Explain this page": the shell links here with q + page; the question is pre-filled, never answered by us.
    $q = trim((string) ($_GET['q'] ?? ''));
    $page = basename((string) ($_GET['page'] ?? ''));
    $pageTitle = '';
    if ($page !== '') {
        foreach (cdp_aiPageCatalogue() as $p) {
            if (strtok($p[0], '?') === $page) { $pageTitle = $p[1]; break; }
        }
    }
    $d['draft'] = $q !== '' ? ($page !== '' ? $q . ': ' . ($pageTitle ?: $page) . ' (' . $page . ')' : $q) : '';
    $d['autoSend'] = $d['draft'] !== '' && $d['configured'] && $d['canUse'];
    $ctx = cdp_aiContext($user, $userData);
    $scope = $ctx['scope']['type'] ?? 'all';
    $d['scope'] = ['all' => 'Staff', 'agency' => 'Branch', 'driver' => 'Driver', 'client' => 'Customer'][$scope] ?? ucfirst($scope);
    $starters = [
        'all' => [['i-chart', 'Give me an overview of this week'], ['i-alert', 'What issues need attention?'], ['i-box', 'Unpaid packages over 14 days'], ['i-layers', 'Consolidations in transit'], ['i-clock', 'What changed today?'], ['i-spark', 'Which page would help me most?']],
        'agency' => [['i-chart', 'Overview of my branch this month'], ['i-alert', 'What is stuck at my branch?'], ['i-dollar', 'Unpaid at my branch'], ['i-pin', 'Where do I find…']],
        'driver' => [['i-truck', 'What is assigned to me?'], ['i-clock', 'My deliveries this week'], ['i-alert', 'Any problems on my list?'], ['i-pin', 'Where do I find…']],
        'client' => [['i-box', 'Where are my packages?'], ['i-dollar', 'What do I owe?'], ['i-clock', 'My shipments this month'], ['i-help', 'How do I add a pre-alert?']],
    ];
    $d['starters'] = array_map(fn($s) => ['icon' => $s[0], 'label' => $s[1]], $starters[$scope] ?? $starters['all']);
    $labels = cdp_aiToolAreaLabels();
    $tools = [];
    foreach (cdp_aiToolDefinitions($ctx) as $t) {
        $n = $t['name'] ?? '';
        $tools[] = ['n' => $labels[$n] ?? str_replace('_', ' ', $n), 'c' => mb_substr((string) ($t['description'] ?? ''), 0, 140)];
    }
    $seen = [];
    $d['tools'] = array_values(array_filter($tools, function ($t) use (&$seen) { if (isset($seen[$t['n']])) return false; $seen[$t['n']] = 1; return true; }));
    $d['facts'] = $d['ready'] ? array_slice(array_map(fn($k) => ['id' => $k['id'], 'note' => $k['note']], cdp_aiKnowledge(true)), 0, 5) : [];
    $d['ideas'] = $d['ready'] && $d['canInsights'] ? array_slice(array_map(fn($i) => ['t' => $i['title'], 'v' => $i['votes'], 's' => cdp_aiIdeaStatuses()[$i['status']] ?? $i['status'], 'pc' => ['new' => 'pending', 'planned' => 'onway', 'built' => 'delivered', 'dismissed' => 'plain'][$i['status']] ?? 'plain'], cdp_aiIdeas()), 0, 4) : [];
    $stats = $d['ready'] && $d['canInsights'] ? cdp_aiQuestionStats(30) : ['total' => 0, 'by_area' => [], 'unanswered' => []];
    $max = max(1, $stats['by_area'] ? max(array_column($stats['by_area'], 'count')) : 1);
    $d['qTotal'] = $stats['total'];
    $d['byArea'] = array_map(fn($b) => ['n' => $b['label'], 'c' => $b['count'], 'w' => 'width:' . sl_pct($b['count'], $max) . '%'], array_slice($stats['by_area'], 0, 5));
    $d['unanswered'] = count($stats['unanswered']);
    return $d;
}

function sl_aiCenterData($user, $core): array
{
    $d = sl_aiBase($user);
    $s = $d['settings'];
    $d['keys'] = [];
    foreach ($d['providers'] as $k => $p) {
        $d['keys'][$k] = cdp_aiMaskKey(cdp_aiApiKey($k));
    }
    $d['knowledge'] = $d['ready'] ? cdp_aiKnowledge(false) : [];
    $d['ideas'] = $d['ready'] ? cdp_aiIdeas() : [];
    $d['ideaStatuses'] = cdp_aiIdeaStatuses();
    $stats = $d['ready'] ? cdp_aiQuestionStats(30) : ['total' => 0, 'users' => 0, 'by_role' => [], 'by_area' => [], 'unanswered' => [], 'recent' => []];
    $d['stats'] = $stats;
    $usage = $d['ready'] ? sl_row("SELECT COUNT(*) AS n, COALESCE(SUM(tokens_in),0) AS tin, COALESCE(SUM(tokens_out),0) AS tout FROM cdb_ai_messages WHERE role = 'assistant' AND created_at >= NOW() - INTERVAL 30 DAY") : null;
    $cs = sl_tableExists('cdb_cs_ai_runs') ? sl_row("SELECT COUNT(*) AS n, COALESCE(SUM(tokens_in),0) AS tin, COALESCE(SUM(tokens_out),0) AS tout, SUM(outcome LIKE '%error%' OR outcome LIKE '%fail%') AS errs, AVG(latency_ms) AS ms FROM cdb_cs_ai_runs WHERE created_at >= NOW() - INTERVAL 30 DAY") : null;
    $d['usage'] = ['answers' => (int) ($usage->n ?? 0), 'tokens' => (int) ($usage->tin ?? 0) + (int) ($usage->tout ?? 0), 'csRuns' => (int) ($cs->n ?? 0), 'csTokens' => (int) ($cs->tin ?? 0) + (int) ($cs->tout ?? 0), 'csErrors' => (int) ($cs->errs ?? 0), 'csMs' => $cs && $cs->ms !== null ? (int) $cs->ms : null];
    $spark = function (string $table, string $where = '1=1') {
        if (!sl_tableExists($table)) return array_fill(0, 12, 'height:8%');
        $map = [];
        foreach (sl_rows("SELECT DATE(created_at) AS d, COUNT(*) AS n FROM $table WHERE $where AND created_at >= CURDATE() - INTERVAL 11 DAY GROUP BY d") as $r) $map[$r->d] = (int) $r->n;
        $max = max(1, $map ? max($map) : 1);
        $o = [];
        for ($i = 11; $i >= 0; $i--) { $day = date('Y-m-d', strtotime("-$i days")); $o[] = 'height:' . max(8, sl_pct($map[$day] ?? 0, $max)) . '%'; }
        return $o;
    };
    $king = function_exists('cdp_csSetting') ? cdp_csSetting('king_mode', 'off') : 'off';
    $d['kpis'] = [
        ['icon' => 'i-ai', 'l' => 'Assistant', 'v' => $d['configured'] ? 'On' : ($d['hasKey'] ? 'Off' : 'Not set up'), 's' => $d['providerLabel'] . ' · ' . $d['model'], 'sp' => $spark('cdb_ai_messages', "role = 'assistant'")],
        ['icon' => 'i-clock', 'l' => 'Questions · 30 days', 'v' => sl_num($stats['total']), 's' => sl_num($stats['users'] ?? 0) . ' people asked', 'sp' => $spark('cdb_ai_questions')],
        ['icon' => 'i-alert', 'l' => 'Not answered', 'v' => sl_num(count($stats['unanswered'])), 's' => 'errors or no access', 'sp' => $spark('cdb_ai_questions', "outcome <> 'answered'")],
        ['icon' => 'i-spark', 'l' => 'King for customers', 'v' => ['off' => 'Off', 'copilot' => 'Agents only', 'customer' => 'On'][$king] ?? $king, 's' => sl_num($d['usage']['csRuns']) . ' runs · ' . sl_num($d['usage']['csErrors']) . ' failed', 'sp' => $spark('cdb_cs_ai_runs')],
        ['icon' => 'i-scan', 'l' => 'Tokens · 30 days', 'v' => sl_num($d['usage']['tokens'] + $d['usage']['csTokens']), 's' => 'staff assistant and King', 'sp' => $spark('cdb_ai_messages', "role = 'assistant'")],
    ];
    $cov = [
        ['Staff assistant chat', 'AI assistant', 'StaffAI', $d['configured']], ['Explain this page', 'Help menu → AI assistant', 'StaffAI', $d['configured']], ['Answers scoped to the role', 'AI assistant', 'StaffAI', true],
        ['AI agent for customers (King)', 'AI for customers', 'CsAI', $king === 'customer'], ['Reply drafts for agents', 'Support ticket', 'CsAI', $king !== 'off'], ['Ticket summaries', 'Support ticket', 'CsAI', $king !== 'off'],
        ['Language and tag detection', 'AI for customers', 'CsAI', $king !== 'off'], ['Help articles from tickets', 'Playbook', 'CsPlaybook', $king !== 'off'], ['Providers and keys', 'Settings below', '#', true],
        ['Taught facts', 'Knowledge below', '#', true], ['Page ideas from questions', 'Page ideas below', '#', true], ['Question log', 'Questions below', '#', true], ['Usage and cost', 'Usage above', '#', true],
        ['Profile photo retouch', 'AI profile studio', 'AiStudio', true],
    ];
    $d['cov'] = array_map(fn($c, $i) => ['n' => $i + 1, 't' => $c[0], 'where' => $c[1], 'href' => $c[2] === '#' ? '#' : sl_route($c[2]), 'here' => $c[2] === '#', 'st' => $c[2] === '#' ? 'Here' : ($c[3] ? 'On' : 'Off'), 'pc' => $c[2] === '#' ? 'onway' : ($c[3] ? 'delivered' : 'plain'), 'off' => $c[3] ? '' : 'off'], $cov, array_keys($cov));
    return $d;
}
