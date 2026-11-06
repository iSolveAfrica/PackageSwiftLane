<?php
/** Data for API Monitor and API Traffic: cdb_api_request_log (inbound /api/v1) + cdb_message_log (outbound provider calls). */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/sl_charts.php';

/** Period from ?range=1h|24h|7d|30d. */
function sl_apiRange(): array
{
    $r = (string) ($_GET['range'] ?? '24h');
    $map = ['1h' => '1 HOUR', '24h' => '24 HOUR', '7d' => '7 DAY', '30d' => '30 DAY'];
    if (!isset($map[$r])) $r = '24h';
    return [$r, $map[$r]];
}

/** Bar chart SVG (design's ov-chart) from [label => value]. */
function sl_apiBars(array $data, string $unit = ''): string
{
    $max = max(1, max($data ?: [1]));
    $n = max(1, count($data));
    $w = 506 / $n;
    $out = '<line x1="44" y1="190" x2="550" y2="190" stroke="var(--line)"></line>';
    foreach ([0.25, 0.5, 0.75, 1] as $f) {
        $y = 190 - 178 * $f;
        $out .= '<line x1="44" y1="' . $y . '" x2="550" y2="' . $y . '" stroke="var(--line)" stroke-dasharray="3 4"></line><text x="36" y="' . ($y + 4) . '" text-anchor="end">' . h(sl_num($max * $f)) . '</text>';
    }
    $i = 0;
    foreach ($data as $label => $v) {
        $bh = round(178 * $v / $max, 1);
        $x = round(44 + $w * $i + $w * 0.25, 1);
        $out .= '<rect x="' . $x . '" y="' . (190 - $bh) . '" width="' . round($w * 0.5, 1) . '" height="' . $bh . '" rx="5" fill="' . ($i === 0 ? '#ECEAE5' : '#E9E7E1') . '"' . ($i === 0 ? ' stroke="#D9D7D0"' : '') . '></rect>'
            . '<text x="' . round($x + $w * 0.25, 1) . '" y="' . (184 - $bh) . '" text-anchor="middle" style="fill:var(--ink-2);font-weight:500">' . h(sl_num($v)) . h($unit) . '</text>'
            . '<text x="' . round($x + $w * 0.25, 1) . '" y="212" text-anchor="middle">' . h(mb_substr((string) $label, 0, 12)) . '</text>';
        $i++;
    }
    return $out;
}

function sl_apiMonitorData($user, $core): array
{
    [$range, $iv] = sl_apiRange();
    $d = ['ready' => sl_tableExists('cdb_api_request_log'), 'range' => $range, 'ranges' => ['1h', '24h', '7d', '30d']];
    $d['n'] = 0; $d['ok'] = 0; $d['err'] = 0; $d['p95'] = 0; $d['slow'] = '—'; $d['hours'] = ''; $d['bars'] = ''; $d['live'] = []; $d['top'] = '—'; $d['avgHour'] = 0;
    if ($d['ready']) {
        $w = "created_at >= NOW() - INTERVAL $iv";
        $k = sl_row("SELECT COUNT(*) n, SUM(status < 400) ok, SUM(status >= 400) err FROM cdb_api_request_log WHERE $w");
        $d['n'] = (int) $k->n; $d['ok'] = (int) $k->ok; $d['err'] = (int) $k->err;
        $prev = (int) sl_scalar("SELECT COUNT(*) FROM cdb_api_request_log WHERE created_at < NOW() - INTERVAL $iv AND created_at >= NOW() - INTERVAL $iv - INTERVAL $iv");
        $d['delta'] = $prev ? (($d['n'] >= $prev ? '▲ ' : '▼ ') . abs(sl_pct($d['n'] - $prev, $prev)) . '% vs previous period') : 'first period with traffic';
        if ($d['n']) {
            $off = max(0, (int) floor($d['n'] * 0.95) - 1);
            $d['p95'] = (int) sl_scalar("SELECT duration_ms FROM cdb_api_request_log WHERE $w ORDER BY duration_ms LIMIT $off, 1");
            $s = sl_row("SELECT path, AVG(duration_ms) a FROM cdb_api_request_log WHERE $w GROUP BY path ORDER BY a DESC");
            $d['slow'] = $s ? $s->path : '—';
        }
        $hrs = [];
        foreach (sl_rows("SELECT HOUR(created_at) h, COUNT(*) n FROM cdb_api_request_log WHERE created_at >= NOW() - INTERVAL 24 HOUR GROUP BY h") as $r) $hrs[(int) $r->h] = (int) $r->n;
        $series = [];
        for ($i = 23; $i >= 0; $i--) $series[] = $hrs[(int) date('G', strtotime("-$i hours"))] ?? 0;
        $d['hours'] = sl_svgLinePath($series, 550, 190, 12, max(1, max($series)), 44);
        $d['hourMax'] = max($series);
        $d['avgHour'] = round(array_sum($series) / 24, 1);
        $tops = [];
        foreach (sl_rows("SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(path, '/api/v1/', -1), '/', 1) seg, COUNT(*) n FROM cdb_api_request_log WHERE $w GROUP BY seg ORDER BY n DESC LIMIT 6") as $r) $tops['/' . $r->seg] = (int) $r->n;
        $d['bars'] = sl_apiBars($tops);
        $d['top'] = $tops ? array_key_first($tops) : '—';
        $d['live'] = array_map(fn($r) => ['ts' => date('H:i:s', strtotime($r->created_at)), 'who' => $r->who ?: ($r->status == 401 ? 'unknown token' : 'public'), 'code' => (int) $r->status,
            'cls' => $r->status >= 500 ? 'bad' : ($r->status >= 400 ? 'warn' : 'ok'), 'line' => $r->method . ' ' . $r->path . ' · ' . $r->duration_ms . ' ms'],
            sl_rows("SELECT l.*, TRIM(CONCAT(u.fname, ' ', u.lname)) who FROM cdb_api_request_log l LEFT JOIN cdb_users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 12"));
    }
    $d['rate'] = $d['n'] ? round($d['ok'] / $d['n'] * 100, 2) : 0;
    $hasTok = sl_tableExists('cdb_api_tokens');
    $q = trim((string) ($_GET['q'] ?? ''));
    $keys = [];
    if ($hasTok) {
        $b = [];
        $wq = '';
        if ($q !== '') { $wq = " AND (t.name LIKE :q OR u.fname LIKE :q OR u.lname LIKE :q OR u.username LIKE :q)"; $b[':q'] = '%' . $q . '%'; }
        foreach (sl_rows("SELECT t.id, t.name, t.user_id, t.expires_at, t.revoked_at, t.last_used_at, TRIM(CONCAT(u.fname, ' ', u.lname)) owner FROM cdb_api_tokens t LEFT JOIN cdb_users u ON u.id = t.user_id WHERE t.revoked_at IS NULL AND t.expires_at > NOW() $wq ORDER BY t.last_used_at DESC LIMIT 25", $b) as $t) {
            $calls = $d['ready'] ? sl_row("SELECT COUNT(*) n, SUM(status >= 400) e FROM cdb_api_request_log WHERE user_id = :u AND created_at >= NOW() - INTERVAL 24 HOUR", [':u' => (int) $t->user_id]) : null;
            $n = (int) ($calls->n ?? 0);
            $er = $n ? round((int) $calls->e / $n * 100, 1) : 0;
            $exp = strtotime($t->expires_at) < time() + 7 * 86400;
            $keys[] = ['id' => (int) $t->id, 'name' => ($t->name ?: 'Unnamed') . ' · ' . ($t->owner ?: '#' . $t->user_id), 'mask' => 'sl_••••' . str_pad((string) $t->id, 4, '0', STR_PAD_LEFT), 'calls' => sl_num($n), 'err' => $er . '%',
                'st' => $er > 5 ? ['High errors', 'bad'] : ($exp ? ['Expiring', 'warn'] : ['Healthy', 'ok'])];
        }
    }
    $d['keys'] = $keys;
    $d['q'] = $q;
    $d['keyN'] = $hasTok ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_api_tokens WHERE revoked_at IS NULL AND expires_at > NOW()') : 0;
    $d['userN'] = $hasTok ? (int) sl_scalar('SELECT COUNT(DISTINCT user_id) FROM cdb_api_tokens WHERE revoked_at IS NULL AND expires_at > NOW()') : 0;
    $d['canRevoke'] = $user->cdp_hasPermission(['revoke_api_tokens', 'manage_api_clients']);
    return $d;
}

function sl_apiTrafficData($user, $core): array
{
    $dir = (string) ($_GET['dir'] ?? 'both');
    $d = ['dir' => $dir, 'dirs' => [['k' => 'both', 'label' => 'Both'], ['k' => 'pull', 'label' => 'Pull'], ['k' => 'push', 'label' => 'Push']], 'showPull' => $dir !== 'push', 'showPush' => $dir !== 'pull'];
    $hasIn = sl_tableExists('cdb_api_request_log');
    $hasOut = sl_tableExists('cdb_message_log');
    $cc = fn($s) => $s >= 500 || $s === 0 ? 'e' : ($s >= 400 ? 'w' : '');
    $push = [];
    if ($hasIn) {
        foreach (sl_rows('SELECT id, created_at, method, path, status, duration_ms, ip FROM cdb_api_request_log ORDER BY id DESC LIMIT 30') as $r) {
            $push[] = ['id' => 'in' . $r->id, 't' => date('H:i:s', strtotime($r->created_at)), 'p' => 'REST API', 'e' => $r->method . ' ' . $r->path, 'code' => (int) $r->status, 'cc' => $cc((int) $r->status), 'ms' => $r->duration_ms . ' ms',
                'json' => json_encode(['direction' => 'in', 'method' => $r->method, 'path' => $r->path, 'status' => (int) $r->status, 'duration_ms' => (int) $r->duration_ms, 'ip' => $r->ip, 'at' => $r->created_at], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)];
        }
    }
    $pull = [];
    $fails = [];
    $prov = ['sms' => 'SMS provider', 'whatsapp' => 'WhatsApp API', 'email' => 'SMTP'];
    if ($hasOut) {
        foreach (sl_rows('SELECT id, created_at, channel, status, status_detail, recipient_to, source_label, entity_label FROM cdb_message_log ORDER BY id DESC LIMIT 30') as $r) {
            $code = $r->status === 'sent' ? 200 : ($r->status === 'skipped' ? 204 : 500);
            $row = ['id' => 'out' . $r->id, 't' => date('H:i:s', strtotime($r->created_at)), 'p' => $prov[$r->channel] ?? $r->channel, 'e' => (string) ($r->source_label ?: 'message'), 'code' => $code, 'cc' => $cc($code), 'ms' => $r->status,
                'json' => json_encode(['direction' => 'out', 'channel' => $r->channel, 'status' => $r->status, 'detail' => $r->status_detail, 'to' => $r->recipient_to, 'linked' => $r->entity_label, 'at' => $r->created_at], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)];
            $pull[] = $row;
            if ($r->status === 'failed' && count($fails) < 6) $fails[] = ['p' => $row['p'], 'e' => $row['e'], 'why' => mb_substr((string) $r->status_detail, 0, 80) ?: 'failed'];
        }
    }
    $d['push'] = $push;
    $d['pull'] = $pull;
    $d['fails'] = $fails;
    $min = fn($t, $col) => $t ? (int) sl_scalar("SELECT COUNT(*) FROM $t WHERE created_at >= NOW() - INTERVAL 1 MINUTE") : 0;
    $d['pullN'] = $min($hasOut ? 'cdb_message_log' : '', 'created_at');
    $d['pushN'] = $min($hasIn ? 'cdb_api_request_log' : '', 'created_at');
    $in = $hasIn ? sl_row('SELECT COUNT(*) n, SUM(status >= 400) e, AVG(duration_ms) a FROM cdb_api_request_log WHERE created_at >= CURDATE()') : null;
    $out = $hasOut ? sl_row("SELECT COUNT(*) n, SUM(status = 'failed') e FROM cdb_message_log WHERE created_at >= CURDATE()") : null;
    $d['kpis'] = [
        ['l' => 'API calls received today', 'v' => sl_num($in->n ?? 0), 's' => sl_num($in->e ?? 0) . ' errors', 'a' => ''],
        ['l' => 'Average response', 'v' => round((float) ($in->a ?? 0)) . ' ms', 's' => 'our REST API', 'a' => ''],
        ['l' => 'Provider calls today', 'v' => sl_num($out->n ?? 0), 's' => 'SMS, WhatsApp, e-mail', 'a' => ''],
        ['l' => 'Provider failures', 'v' => sl_num($out->e ?? 0), 's' => 'today', 'a' => ''],
    ];
    $provs = [];
    foreach (['REST API' => [$hasIn, 'cdb_api_request_log', 'status >= 400', 'push'], 'SMS provider' => [$hasOut, 'cdb_message_log', "status = 'failed'", "channel = 'sms'"], 'WhatsApp API' => [$hasOut, 'cdb_message_log', "status = 'failed'", "channel = 'whatsapp'"], 'SMTP' => [$hasOut, 'cdb_message_log', "status = 'failed'", "channel = 'email'"]] as $name => [$ok, $t, $bad, $w]) {
        if (!$ok) continue;
        $where = $w === 'push' ? '1=1' : $w;
        $r = sl_row("SELECT COUNT(*) n, SUM($bad) e FROM $t WHERE $where AND created_at >= NOW() - INTERVAL 24 HOUR");
        $hrs = [];
        foreach (sl_rows("SELECT HOUR(created_at) h, COUNT(*) n, SUM($bad) e FROM $t WHERE $where AND created_at >= NOW() - INTERVAL 12 HOUR GROUP BY h") as $x) $hrs[(int) $x->h] = $x;
        $sp = [];
        $mx = 1;
        foreach ($hrs as $x) $mx = max($mx, (int) $x->n);
        for ($i = 11; $i >= 0; $i--) { $x = $hrs[(int) date('G', strtotime("-$i hours"))] ?? null; $sp[] = ['c' => $x && $x->e ? 'e' : '', 's' => 'height:' . max(8, sl_pct($x->n ?? 0, $mx)) . '%']; }
        $n = (int) $r->n; $e = (int) $r->e;
        $provs[] = ['n' => $name, 'kind' => $w === 'push' ? 'Inbound' : 'Outbound', 'rate' => sl_num($n) . ' in 24h', 'ms' => $n ? (100 - sl_pct($e, $n)) . '% ok' : 'idle', 'dot' => $n === 0 ? 'off' : ($e / max(1, $n) > 0.05 ? 'e' : ($e ? 'w' : 'live')), 'sp' => $sp];
    }
    $d['provs'] = $provs;
    $d['first'] = $push[0] ?? ($pull[0] ?? null);
    return $d;
}
