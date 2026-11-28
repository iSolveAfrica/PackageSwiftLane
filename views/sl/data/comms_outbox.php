<?php
/**
 * Data for Communications > Outbox (comms_outbox.php, views/sl/pages/CommsOutbox.php)
 * and Communications > Customer history (comms_customers.php, views/sl/pages/CommsCustomers.php).
 *
 * Everything comes from helpers/comms_outbox_view.php: one read-only list over
 * the message log, the Communication Center outbox, the Support desk and
 * delivery-request queues and the WhatsApp messages customers sent.
 * Row detail, retry / cancel / send again, King's "Explain this failure",
 * CSV export and preference saving: ajax/comms/outbox_ajax.php.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/comms.php';
require_once CDP_ROOT . '/helpers/comms_triggers.php';
require_once CDP_ROOT . '/helpers/comms_outbox_view.php';
require_once CDP_ROOT . '/helpers/message_cost.php';
require_once __DIR__ . '/message_logs.php';   // sl_msgShortDetail()

/** Who may open the Outbox: staff (rank 2+) or anyone with Message logs. */
function sl_commsOutboxAllowed($user): bool
{
    return cdp_opsRank() >= 2 || $user->cdp_hasPermission('view_message_logs');
}

/**
 * May this user open Message Costs (message_costs.php)? The same gate as that
 * page: view_message_logs (sl_gate) and, while it is a developer-console page
 * (helpers/sl_modules.php sl_moduleDevPages), the console's access for its section.
 */
function sl_commsOutboxCanCosts($user): bool
{
    if (!$user->cdp_hasPermission('view_message_logs')) {
        return false;
    }
    try {
        if (!function_exists('sl_moduleDevPages') && is_file(CDP_ROOT . '/helpers/sl_modules.php')) {
            require_once CDP_ROOT . '/helpers/sl_modules.php';
        }
        $sec = function_exists('sl_moduleDevPages') ? (sl_moduleDevPages()['message_costs.php'] ?? null) : null;
        if ($sec === null) {
            return true;
        }
        require_once CDP_ROOT . '/helpers/devops.php';
        if (!function_exists('cdp_devLocked') || !function_exists('cdp_devCanUse') || !function_exists('cdp_devSectionAllowed')) {
            return false;
        }
        return !cdp_devLocked() && cdp_devCanUse($user) && cdp_devSectionAllowed($user, $sec);
    } catch (Throwable $e) {
        return false;
    }
}

/** King availability for the "Explain this failure" button (never throws). */
function sl_commsOutboxAi($user): array
{
    if (!is_file(CDP_ROOT . '/helpers/comms_ai.php')) {
        return ['ok' => false, 'code' => 'not_configured', 'message' => '', 'setup_url' => '', 'assistant' => function_exists('cdp_kingName') ? cdp_kingName() : 'King'];
    }
    require_once CDP_ROOT . '/helpers/comms_ai.php';
    try {
        return cdp_commAiAvailable($user);
    } catch (Throwable $e) {
        return ['ok' => false, 'code' => 'error', 'message' => '', 'setup_url' => '', 'assistant' => 'King'];
    }
}

function sl_commsOutboxData($user, $core): array
{
    $ready = cdp_commEnsureTables() && cdp_msgTableReady();
    $f = cdp_commOutboxFilters($_GET);
    $filtered = cdp_commOutboxFiltered($f);
    [$f, $defaultRange] = cdp_commOutboxDefaultRange($f, $_GET);
    $per = 50;
    // Deep pages cost more on every part of the union; 200 pages is 10,000 messages (narrow the dates for older ones).
    $maxPage = 200;
    $page = min($maxPage, max(1, (int) ($_GET['page'] ?? 1)));
    $d = [
        'ready' => $ready,
        'f' => $f,
        'filtered' => $filtered,
        'defaultRange' => $defaultRange,
        'anyRange' => ($_GET['range'] ?? '') === 'any',
        'maxPage' => $maxPage,
        'page' => $page,
        'per' => $per,
        'rows' => [],
        'total' => 0,
        'stats' => ['total' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'waiting' => 0, 'cancelled' => 0, 'received' => 0, 'cost' => 0, 'currency' => ''],
        'channels' => cdp_commChannels(),
        'statuses' => cdp_commOutboxStatusFilters(),
        'origins' => cdp_commOutboxOrigins(),
        'sources' => [],
        'campaigns' => [],
        'customerName' => $f['customer'] ? cdp_commOutboxCustomerName($f['customer']) : '',
        'open' => preg_match('/^(log|queue|cs|dlv|in)-\d+$/', (string) ($_GET['open'] ?? '')) ? (string) $_GET['open'] : '',
        'canAct' => cdp_opsRank() >= 2,
        'ai' => sl_commsOutboxAi($user),
        'ajax' => 'ajax/comms/outbox_ajax.php',
        'sendUrl' => sl_route('CommsSend'),
        'historyUrl' => sl_route('CommsCustomers'),
        'costsUrl' => sl_route('MessageCosts'),
        'canSend' => cdp_opsRank() >= 2,
        'canCosts' => sl_commsOutboxCanCosts($user),
        'linked' => false,
    ];
    if (!$ready) {
        return $d;
    }
    $d['linked'] = cdp_commOutboxLinked();
    try {
        $feed = cdp_commOutboxFeed($f, $page, $per);
        $d['rows'] = $feed['rows'];
        $d['total'] = $feed['total'];
        $d['stats'] = cdp_commOutboxStats($f);
        $d['sources'] = cdp_commOutboxSourceOptions();
        $d['campaigns'] = cdp_commOutboxCampaignOptions();
    } catch (Throwable $e) {
        error_log('Outbox: ' . $e->getMessage());
        $d['error'] = 'The Outbox could not be read. The error is in the PHP log.';
    }
    if ($f['source'] !== '' && !isset($d['sources'][$f['source']])) {
        $d['sources'][$f['source']] = function_exists('cdp_msgSourceLabel') ? cdp_msgSourceLabel($f['source']) : $f['source'];
    }
    if ($f['campaign'] !== '' && !isset($d['campaigns'][$f['campaign']])) {
        $d['campaigns'][$f['campaign']] = cdp_commOutboxBatchName($f['campaign']);
    }
    $d['exportUrl'] = $d['ajax'] . '?' . http_build_query(['action' => 'export'] + array_filter([
        'channel' => $f['channel'], 'status' => $f['status'], 'origin' => $f['origin'], 'source' => $f['source'], 'campaign' => $f['campaign'],
        'customer' => $f['customer'] ?: '', 'q' => $f['q'], 'from' => $f['from'], 'to' => $f['to'], 'sent_by' => $f['sent_by'] > 0 ? $f['sent_by'] : '',
        'template_id' => $f['template'] ?: '', 'range' => $d['anyRange'] ? 'any' : ''], function ($v) { return $v !== '' && $v !== 0; }));
    return $d;
}

/** Customer history: one customer (?user=) or the start screen (search + recently messaged). */
function sl_commsCustomersData($user, $core): array
{
    $ready = cdp_commEnsureTables();
    $uid = max(0, (int) ($_GET['user'] ?? $_GET['user_id'] ?? $_GET['id'] ?? 0));
    $d = [
        'ready' => $ready,
        'uid' => $uid,
        'customer' => null,
        'prefs' => null,
        'items' => [],
        'counts' => ['sent' => 0, 'in' => 0, 'problems' => 0, 'waiting' => 0],
        'recent' => [],
        'ajax' => 'ajax/comms/outbox_ajax.php',
        'outboxUrl' => sl_route('CommsOutbox'),
        'sendUrl' => sl_route('CommsSend'),
        'ai' => sl_commsOutboxAi($user),
        'canAct' => cdp_opsRank() >= 2,
        'channels' => cdp_commChannels(),
    ];
    if (!$ready) {
        return $d;
    }
    if ($uid > 0) {
        $u = cdp_commUser($uid);
        if ($u) {
            $d['customer'] = ['id' => (int) $u->id, 'name' => trim($u->fname . ' ' . $u->lname) ?: 'Customer', 'locker' => (string) ($u->locker ?? ''),
                'phone' => (string) ($u->phone ?? ''), 'email' => (string) ($u->email ?? ''), 'ini' => sl_initials(trim($u->fname . ' ' . $u->lname) ?: 'C'),
                'profile' => sl_route('CustomerProfile', ['id' => (int) $u->id]), 'outbox' => sl_route('CommsOutbox', ['customer' => (int) $u->id]),
                'send' => sl_route('CommsSend', ['ids' => (int) $u->id])];
            $d['prefs'] = cdp_commPrefs($uid);
            try {
                $d['items'] = cdp_commOutboxHistory($uid, 300);
            } catch (Throwable $e) {
                error_log('Customer history: ' . $e->getMessage());
                $d['error'] = 'The history could not be read. The error is in the PHP log.';
            }
            // The same groups as the History filter: Sent = delivered to the provider, Problems = failed, not sent or cancelled.
            foreach ($d['items'] as $it) {
                if ($it['dir'] === 'out' && $it['status'] === 'sent') {
                    $d['counts']['sent']++;
                } elseif ($it['dir'] === 'in' && $it['channel'] !== 'survey') {
                    $d['counts']['in']++;
                }
                if (in_array($it['status'], ['failed', 'skipped', 'cancelled'], true)) {
                    $d['counts']['problems']++;
                }
                if (in_array($it['status'], ['queued', 'scheduled', 'retrying', 'sending'], true)) {
                    $d['counts']['waiting']++;
                }
            }
        } else {
            $d['missing'] = true;
        }
    }
    if (!$d['customer']) {
        try {
            $d['recent'] = cdp_commOutboxRecentCustomers(12);
        } catch (Throwable $e) {
            $d['recent'] = [];
        }
    }
    return $d;
}
