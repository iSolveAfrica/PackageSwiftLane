<?php
/**
 * Data for the finance boards: Payments, RecordPayment, AccountsReceivable,
 * FinancialOverview(+Restricted), Transactions and BillingHub.
 *
 * Money rules: amounts stored in USD (cdb_add_order.total_order, cdb_charges_order.total)
 * stay USD; ledger rows in GHS (cdb_fs_payments.amount_ghs) are shown in USD with the
 * rate stored on the row (never today's rate); gateway rows (cdb_payments_gateway)
 * keep the currency the gateway settled in. Nothing historical is recomputed.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/sl_charts.php';
require_once CDP_ROOT . '/helpers/finance_switch.php';
require_once CDP_ROOT . '/helpers/rbac.php';
require_once CDP_ROOT . '/helpers/sl_nav.php';
if (is_file(CDP_ROOT . '/helpers/fs_status.php')) {
    require_once CDP_ROOT . '/helpers/fs_status.php';
}

/** Current USD→GHS rate from settings (0 when unset). */
function sl_finRate(): float
{
    global $core;
    return isset($core->exchange_rate) ? (float) $core->exchange_rate : 0.0;
}

/** Convert a GHS amount to USD with a stored rate; falls back to today's rate. */
function sl_finGhsToUsd($ghs, $rate): float
{
    $rate = (float) $rate > 0 ? (float) $rate : sl_finRate();
    return $rate > 0 ? (float) $ghs / $rate : 0.0;
}

/** Gateway status → [label, pill class, bucket]. */
function sl_finStatus(string $src, ?string $status, float $refunded = 0): array
{
    $s = strtolower(trim((string) $status));
    if ($src === 'charge') return ['Paid', 'paid', 'paid'];
    if ($refunded > 0 && in_array($s, ['success', 'partially-refunded', ''], true)) return ['Refunded part', 'paid', 'refunded'];
    if (in_array($s, ['success', 'manual', 'completed', 'approved', 'paid', ''], true)) return ['Paid', 'paid', 'paid'];
    if (in_array($s, ['reversed', 'reversal-pending', 'refunded'], true)) return ['Refunded', 'overdue', 'refunded'];
    if (in_array($s, ['failed', 'abandoned', 'wrong_currency', 'amount_mismatch', 'declined'], true)) return ['Failed', 'overdue', 'failed'];
    return [ucfirst($s ?: 'Pending'), 'pending', 'pending'];
}

/**
 * One SQL that unions every payment source. Columns: ref, at, sender_id, src, gateway,
 * method, usd, fee_usd, status, refunded, currency, amount_raw, pkgs, entity, entity_id.
 */
function sl_finPaymentsSql(): string
{
    $parts = [];
    if (sl_tableExists('cdb_fs_payments')) {
        $parts[] = "SELECT CONCAT('FS-', p.id) AS ref, p.recorded_at AS at, p.sender_id, 'fs' AS src, p.mode AS gateway,
            COALESCE(NULLIF(p.gateway_channel,''), p.mode) AS method,
            (p.amount_ghs - COALESCE(p.refunded_ghs,0)) / NULLIF(p.exchange_rate,0) AS usd,
            COALESCE(p.gateway_fees_ghs,0) / NULLIF(p.exchange_rate,0) AS fee_usd,
            COALESCE(p.gateway_status,'success') AS status, COALESCE(p.refunded_ghs,0) AS refunded,
            'GHS' AS currency, p.amount_ghs AS amount_raw, p.cleared_orders AS pkgs, 'consolidation' AS entity, p.consolidate_id AS entity_id, p.reference AS gw_ref
            FROM cdb_fs_payments p";
    }
    $parts[] = "SELECT COALESCE(NULLIF(g.payment_transaction,''), CONCAT('GW-', g.id)) AS ref, g.date_payment AS at, g.order_track_customer_id AS sender_id, 'gw' AS src,
            g.gateway, g.type_transaccition_courier AS method,
            CASE WHEN UPPER(COALESCE(g.currency,'USD')) IN ('USD','') THEN g.amount ELSE g.amount / NULLIF(" . (sl_finRate() ?: 1) . ",0) END AS usd,
            0 AS fee_usd, g.status, 0 AS refunded, UPPER(COALESCE(g.currency,'USD')) AS currency, g.amount AS amount_raw, g.order_track AS pkgs,
            CASE WHEN g.type_transaccition_courier LIKE '%onsol%' THEN 'consolidation' WHEN g.type_transaccition_courier LIKE '%ackage%' THEN 'package' ELSE 'shipment' END AS entity, 0 AS entity_id, g.payment_transaction AS gw_ref
            FROM cdb_payments_gateway g";
    $parts[] = "SELECT COALESCE(NULLIF(c.number_reference,''), CONCAT('CHG-', c.id_charge)) AS ref, c.charge_date AS at, o.sender_id, 'charge' AS src,
            'Manual' AS gateway, COALESCE(pm.label, 'Manual') AS method, c.total AS usd, 0 AS fee_usd, 'success' AS status, 0 AS refunded,
            'USD' AS currency, c.total AS amount_raw, CONCAT(o.order_prefix, o.order_no) AS pkgs, 'shipment' AS entity, c.order_id AS entity_id, c.number_reference AS gw_ref
            FROM cdb_charges_order c LEFT JOIN cdb_add_order o ON o.order_id = c.order_id LEFT JOIN cdb_payment_methods pm ON pm.id = c.payment_type";
    return '(' . implode(' UNION ALL ', $parts) . ')';
}

function sl_finRange(string $r): array
{
    switch ($r) {
        case 'today': return [date('Y-m-d 00:00:00'), 'Today', 1];
        case '7d': return [date('Y-m-d 00:00:00', strtotime('-6 days')), 'Last 7 days', 7];
        case '90d': return [date('Y-m-d 00:00:00', strtotime('-89 days')), 'Last 90 days', 90];
        case '1y': return [date('Y-m-d 00:00:00', strtotime('-364 days')), 'Last 12 months', 365];
        default: return [date('Y-m-d 00:00:00', strtotime('-29 days')), 'Last 30 days', 30];
    }
}

// ── Payments ────────────────────────────────────────────────────────────────
function sl_paymentsData($user, $core): array
{
    $d = [];
    $range = (string) ($_GET['range'] ?? '30d');
    [$from, $rangeLabel, $days] = sl_finRange($range);
    $u = sl_finPaymentsSql();
    $d['ranges'] = array_map(fn($r) => ['f' => $r[0], 'label' => $r[1], 'cls' => $r[0] === $range ? 'on' : ''], [['today', 'Today'], ['7d', '7d'], ['30d', '30d'], ['90d', '90d']]);
    $d['rangeLabel'] = $rangeLabel;

    $tot = sl_row("SELECT COUNT(*) AS n, COALESCE(SUM(CASE WHEN x.status IN ('success','manual','completed','approved','paid') THEN x.usd ELSE 0 END),0) AS collected,
        COALESCE(SUM(CASE WHEN x.status IN ('success','manual','completed','approved','paid') THEN x.fee_usd ELSE 0 END),0) AS fees,
        COALESCE(SUM(CASE WHEN x.status IN ('failed','abandoned','wrong_currency','amount_mismatch') THEN x.usd ELSE 0 END),0) AS failed,
        SUM(x.status IN ('failed','abandoned','wrong_currency','amount_mismatch')) AS nfailed,
        COALESCE(SUM(CASE WHEN x.refunded > 0 OR x.status IN ('reversed','reversal-pending') THEN x.refunded / NULLIF(" . (sl_finRate() ?: 1) . ",0) ELSE 0 END),0) AS refunds,
        SUM(x.refunded > 0 OR x.status IN ('reversed','reversal-pending')) AS nrefunds,
        SUM(x.status IN ('success','manual','completed','approved','paid')) AS npaid
        FROM $u x WHERE x.at >= :f", [':f' => $from]);
    $prev = (float) sl_scalar("SELECT COALESCE(SUM(x.usd),0) FROM $u x WHERE x.at >= :a AND x.at < :b AND x.status IN ('success','manual','completed','approved','paid')", [':a' => date('Y-m-d', strtotime($from . " -$days days")), ':b' => $from]);
    $collected = (float) $tot->collected;
    $npaid = (int) $tot->npaid;
    $target = sl_tableExists('cdb_sl_settings') ? (float) sl_scalar("SELECT setting_value FROM cdb_sl_settings WHERE setting_key = 'revenue_target_month'", [], 0) : 0;
    $avg = $npaid ? $collected / $npaid : 0;
    $prevN = (int) sl_scalar("SELECT COUNT(*) FROM $u x WHERE x.at >= :a AND x.at < :b AND x.status IN ('success','manual','completed','approved','paid')", [':a' => date('Y-m-d', strtotime($from . " -$days days")), ':b' => $from]);
    $prevAvg = $prevN ? $prev / $prevN : 0;
    $onWay = (float) sl_scalar("SELECT COALESCE(SUM(x.usd),0) FROM $u x WHERE x.at >= :f AND x.status IN ('pending','ongoing','processing','queued')", [':f' => $from]);
    $d['kpi'] = [
        ['label' => 'Collected', 'value' => sl_money($collected), 'sub' => sl_num($npaid) . ' payments' . ($target > 0 ? ' · ' . sl_pct($collected, $target) . '% of target' : '')],
        ['label' => 'Average payment', 'value' => sl_money($avg), 'sub' => $prevAvg > 0 ? (($avg >= $prevAvg ? '▲ ' : '▼ ') . sl_money(abs($avg - $prevAvg)) . ' vs previous period') : 'first period with payments'],
        ['label' => 'Gateway fees', 'value' => sl_money($tot->fees), 'sub' => ($collected > 0 ? number_format((float) $tot->fees / $collected * 100, 1) : '0') . '% of collected'],
        ['label' => 'Net to bank', 'value' => sl_money($collected - (float) $tot->fees), 'sub' => sl_money($onWay) . ' on the way'],
        ['label' => 'Failed', 'value' => sl_money($tot->failed), 'sub' => (int) $tot->nfailed . ' payments · ' . sl_pct($tot->nfailed, $tot->n) . '%'],
        ['label' => 'Refunds', 'value' => sl_money($tot->refunds), 'sub' => (int) $tot->nrefunds . ' refunds'],
    ];

    // money in per day
    $daily = sl_rows("SELECT DATE(x.at) AS d, COALESCE(SUM(x.usd),0) AS amt, COUNT(*) AS n FROM $u x WHERE x.at >= :f AND x.status IN ('success','manual','completed','approved','paid') GROUP BY DATE(x.at)", [':f' => $from]);
    $mapA = $mapN = [];
    foreach ($daily as $r) { $mapA[$r->d] = (float) $r->amt; $mapN[$r->d] = (int) $r->n; }
    $n = max(1, min($days, 90));
    $amts = $cnts = [];
    for ($i = $n - 1; $i >= 0; $i--) { $day = date('Y-m-d', strtotime("-$i days")); $amts[] = $mapA[$day] ?? 0; $cnts[] = $mapN[$day] ?? 0; }
    $maxA = max(1, max($amts));
    $bars = '';
    $bw = 720 / $n; $w = max(2, $bw * 0.75);
    foreach ($amts as $i => $v) {
        $h = round($v / $maxA * 170, 1);
        $bars .= sprintf('<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="3" fill="%s"><title>%s: %s</title></rect>', $i * $bw + ($bw - $w) / 2, 170 - $h, $w, $h, $i === $n - 1 ? '#8E8A82' : '#D6D2CA', date('M j', strtotime('-' . ($n - 1 - $i) . ' days')), h(sl_money($v)));
    }
    $d['chart'] = ['bars' => $bars, 'line' => sl_svgPoints($cnts, 720, 170, 15, max(1, max($cnts)), $bw / 2), 'labels' => [date('M j', strtotime('-' . ($n - 1) . ' days')), date('M j', strtotime('-' . intdiv($n, 2) . ' days')), date('M j')], 'n' => $n];

    // by gateway / method / entity
    $gw = sl_rows("SELECT x.gateway AS k, COALESCE(SUM(x.usd),0) AS amt, COALESCE(SUM(x.fee_usd),0) AS fee, COUNT(*) AS n FROM $u x WHERE x.at >= :f AND x.status IN ('success','manual','completed','approved','paid') GROUP BY x.gateway ORDER BY amt DESC LIMIT 6", [':f' => $from]);
    $gmax = $gw ? max(1, (float) $gw[0]->amt) : 1;
    $d['gateways'] = array_map(fn($r) => ['name' => ucfirst((string) $r->k ?: 'Unknown'), 'sub' => (int) $r->n . ' payments', 'w' => sl_pct($r->amt, $gmax), 'fees' => (float) $r->fee > 0 ? 'Fees ' . number_format((float) $r->fee / max(0.01, (float) $r->amt) * 100, 2) . '% · ' . sl_money($r->fee) : 'Fees —', 'amt' => sl_money($r->amt)], $gw);
    $me = sl_rows("SELECT x.method AS k, COALESCE(SUM(x.usd),0) AS amt FROM $u x WHERE x.at >= :f AND x.status IN ('success','manual','completed','approved','paid') GROUP BY x.method ORDER BY amt DESC LIMIT 6", [':f' => $from]);
    $mmax = $me ? max(1, (float) $me[0]->amt) : 1;
    $d['methods'] = array_map(fn($r) => ['name' => ucfirst((string) $r->k ?: 'Unknown'), 'w' => sl_pct($r->amt, $mmax), 'amt' => sl_money($r->amt)], $me);
    $en = sl_rows("SELECT x.entity AS k, COALESCE(SUM(x.usd),0) AS amt FROM $u x WHERE x.at >= :f AND x.status IN ('success','manual','completed','approved','paid') GROUP BY x.entity ORDER BY amt DESC", [':f' => $from]);
    $emax = $en ? max(1, (float) $en[0]->amt) : 1;
    $labels = ['shipment' => 'Air freight', 'package' => 'Sea freight', 'consolidation' => 'Consolidations'];
    $d['paidFor'] = array_map(fn($r) => ['name' => $labels[$r->k] ?? ucfirst((string) $r->k), 'w' => sl_pct($r->amt, $emax), 'amt' => sl_money($r->amt)], $en);

    // list
    $q = trim((string) ($_GET['q'] ?? ''));
    $filter = (string) ($_GET['filter'] ?? 'all');
    $gwF = trim((string) ($_GET['gateway'] ?? ''));
    $type = trim((string) ($_GET['type'] ?? ''));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = 25;
    $where = 'x.at >= :f';
    $binds = [':f' => $from];
    if ($q !== '') { $where .= " AND (x.ref LIKE :q OR x.pkgs LIKE :q OR CONCAT(u.fname,' ',u.lname) LIKE :q OR u.locker LIKE :q)"; $binds[':q'] = "%$q%"; }
    if ($gwF !== '') { $where .= ' AND x.gateway = :g'; $binds[':g'] = $gwF; }
    if (in_array($type, ['shipment', 'package', 'consolidation'], true)) { $where .= ' AND x.entity = :e'; $binds[':e'] = $type; }
    switch ($filter) {
        case 'paid': $where .= " AND x.status IN ('success','manual','completed','approved','paid') AND x.refunded = 0"; break;
        case 'pending': $where .= " AND x.status IN ('pending','ongoing','processing','queued','unconfigured','unknown')"; break;
        case 'failed': $where .= " AND x.status IN ('failed','abandoned','wrong_currency','amount_mismatch')"; break;
        case 'refunded': $where .= " AND (x.refunded > 0 OR x.status IN ('reversed','reversal-pending'))"; break;
    }
    $sender = sl_userIs('client') ? ' AND x.sender_id = ' . (int) $_SESSION['userid'] : '';
    $count = (int) sl_scalar("SELECT COUNT(*) FROM $u x LEFT JOIN cdb_users u ON u.id = x.sender_id WHERE $where $sender", $binds);
    $rows = sl_rows("SELECT x.*, u.fname, u.lname, u.locker FROM $u x LEFT JOIN cdb_users u ON u.id = x.sender_id WHERE $where $sender ORDER BY x.at DESC LIMIT " . (($page - 1) * $per) . ", $per", $binds);
    $list = [];
    foreach ($rows as $r) {
        [$st, $cls] = sl_finStatus($r->src, $r->status, (float) $r->refunded);
        $pk = (string) $r->pkgs;
        $ids = json_decode($pk, true);
        if (is_array($ids)) { $npk = count($ids); $pk = $npk ? ($npk . ' pkg' . ($npk > 1 ? 's' : '')) : '—'; $pkHref = sl_route('ConsolidationDetail', ['id' => (int) $r->entity_id]); }
        else { $pkHref = $r->entity === 'package' ? sl_route('SeaPackages', ['q' => $pk]) : sl_route('AirShipments', ['q' => $pk]); }
        $name = trim(($r->fname ?? '') . ' ' . ($r->lname ?? '')) ?: 'Customer #' . (int) $r->sender_id;
        $list[] = ['ref' => $r->ref, 'name' => $name, 'ini' => sl_initials($name), 'locker' => $r->locker ?: '', 'sender' => (int) $r->sender_id, 'pkg' => $pk ?: '—', 'pkgHref' => $pkHref, 'entity' => ucfirst($r->entity),
            'method' => ucfirst((string) $r->method), 'gateway' => ucfirst((string) $r->gateway), 'amt' => sl_money($r->usd), 'raw' => $r->currency !== 'USD' ? $r->currency . ' ' . number_format((float) $r->amount_raw, 2) : '',
            'fee' => (float) $r->fee_usd > 0 ? sl_money($r->fee_usd) : '—', 'net' => sl_money((float) $r->usd - (float) $r->fee_usd), 'at' => sl_date($r->at, 'M j, H:i'), 'st' => $st, 'cls' => $cls];
    }
    $d['rows'] = $list; $d['count'] = $count; $d['page'] = $page; $d['per'] = $per; $d['q'] = $q; $d['filter'] = $filter; $d['gateway'] = $gwF; $d['type'] = $type;
    $d['filters'] = [['f' => 'all', 'label' => 'All'], ['f' => 'paid', 'label' => 'Paid'], ['f' => 'pending', 'label' => 'Pending'], ['f' => 'failed', 'label' => 'Failed'], ['f' => 'refunded', 'label' => 'Refunded']];
    $d['gatewayOptions'] = array_map(fn($r) => (string) $r->k, sl_rows("SELECT DISTINCT x.gateway AS k FROM $u x ORDER BY k"));

    // settlements: fs payments by gateway and day (gross, fees, net) last 7 entries
    $d['settlements'] = [];
    if (sl_tableExists('cdb_fs_payments')) {
        foreach (sl_rows("SELECT DATE(p.recorded_at) AS d, p.mode, SUM(p.amount_ghs - COALESCE(p.refunded_ghs,0)) AS gross, SUM(COALESCE(p.gateway_fees_ghs,0)) AS fees, MAX(p.exchange_rate) AS rate, SUM(p.gateway_paid_at IS NULL) AS unpaid FROM cdb_fs_payments p WHERE p.mode <> 'cash' AND " . cdp_fsMoneySqlFilter('p') . " GROUP BY DATE(p.recorded_at), p.mode ORDER BY d DESC LIMIT 6") as $r) {
            $d['settlements'][] = ['date' => sl_date($r->d), 'from' => ucfirst($r->mode) . ' → Bank', 'gross' => sl_money(sl_finGhsToUsd($r->gross, $r->rate)), 'fees' => sl_money(sl_finGhsToUsd($r->fees, $r->rate)), 'net' => sl_money(sl_finGhsToUsd((float) $r->gross - (float) $r->fees, $r->rate)), 'st' => (int) $r->unpaid ? 'On the way' : 'Settled', 'cls' => (int) $r->unpaid ? 'onway' : 'paid'];
        }
    }
    $failed = sl_rows("SELECT x.*, u.fname, u.lname, u.id AS uid FROM $u x LEFT JOIN cdb_users u ON u.id = x.sender_id WHERE x.status IN ('failed','abandoned','wrong_currency','amount_mismatch') ORDER BY x.at DESC LIMIT 5");
    $d['failed'] = array_map(fn($r) => ['name' => trim(($r->fname ?? '') . ' ' . ($r->lname ?? '')) ?: 'Customer', 'amt' => sl_money($r->usd), 'why' => function_exists('cdp_fsStatusMeta') ? cdp_fsStatusMeta((string) $r->status)['hint'] : ucfirst((string) $r->status), 'href' => sl_route('ComposeMessage', ['to' => (int) $r->uid])], $failed);
    $d['failedTotal'] = sl_money($tot->failed);
    $d['canRecord'] = $user->cdp_hasPermission(['fs_record_payment', 'manage_account_payments', 'verify_payments']);
    $d['canSend'] = $user->cdp_hasPermission(['send_prices', 'push_notifications']);
    return $d;
}

// ── Record payment ──────────────────────────────────────────────────────────
function sl_recordPaymentData($user, $core, string $type = 'shipment'): array
{
    $d = ['type' => $type];
    $id = (int) ($_GET['id_order'] ?? $_GET['id'] ?? 0);
    $customerId = (int) ($_GET['customer'] ?? 0);
    $tables = ['shipment' => ['cdb_add_order', 'order_id', 'order_prefix', 'order_no', 'Shipment'], 'package' => ['cdb_customers_packages', 'order_id', 'order_prefix', 'order_no', 'Package'], 'consolidation' => ['cdb_consolidate', 'consolidate_id', 'c_prefix', 'c_no', 'Consolidation'], 'consolidation_packages' => ['cdb_consolidate_packages', 'consolidate_id', 'c_prefix', 'c_no', 'Sea consolidation']];
    [$tbl, $pk, $pre, $no, $label] = $tables[$type] ?? $tables['shipment'];
    $sender = null;
    if ($id) {
        $sender = sl_row("SELECT sender_id FROM $tbl WHERE $pk = :id", [':id' => $id]);
        if ($sender) $customerId = (int) $sender->sender_id;
    }
    $d['customer'] = $customerId ? sl_row('SELECT id, fname, lname, locker, email, phone FROM cdb_users WHERE id = :id', [':id' => $customerId]) : null;
    $d['customerLabel'] = $d['customer'] ? trim($d['customer']->fname . ' ' . $d['customer']->lname) . ($d['customer']->locker ? ' · ' . $d['customer']->locker : '') : 'Pick a customer';
    $deleted = $tbl === 'cdb_add_order' ? ' AND is_deleted = 0' : '';
    $due = sl_columnExists($tbl, 'due_date') ? 'due_date' : 'NULL';
    $invoices = [];
    if ($customerId) {
        foreach (sl_rows("SELECT $pk AS id, CONCAT($pre, $no) AS track, total_order, $due AS due_date, status_invoice, status_courier FROM $tbl WHERE sender_id = :s AND status_invoice = 23 $deleted ORDER BY $pk DESC LIMIT 20", [':s' => $customerId]) as $r) {
            $overdue = $r->due_date && strtotime($r->due_date) < time();
            $invoices[] = ['id' => (int) $r->id, 'track' => $r->track, 'amt' => (float) $r->total_order, 'amtF' => sl_money($r->total_order), 'due' => $r->due_date ? 'Due ' . sl_date($r->due_date) . ($overdue ? ' · overdue' : '') : 'Unpaid', 'on' => $id ? (int) $r->id === $id : true];
        }
    }
    $d['invoices'] = $invoices;
    $selected = array_filter($invoices, fn($i) => $i['on']);
    $d['selectedTotal'] = array_sum(array_column($selected, 'amt'));
    $d['selectedN'] = count($selected);
    $d['methods'] = sl_rows('SELECT id, label FROM cdb_payment_methods ORDER BY id');
    $d['customers'] = $customerId ? [] : sl_rows("SELECT id, fname, lname, locker FROM cdb_users WHERE userlevel = 1 AND active = 1 AND id IN (SELECT sender_id FROM cdb_add_order WHERE status_invoice = 23 AND is_deleted = 0 UNION SELECT sender_id FROM cdb_customers_packages WHERE status_invoice = 23 UNION SELECT sender_id FROM cdb_consolidate WHERE status_invoice = 23) ORDER BY fname, lname LIMIT 300");
    $d['typeLabel'] = $label;
    $d['types'] = [['k' => 'shipment', 'label' => 'Air shipment'], ['k' => 'package', 'label' => 'Sea package'], ['k' => 'consolidation', 'label' => 'Consolidation'], ['k' => 'consolidation_packages', 'label' => 'Sea consolidation']];
    $d['currency'] = $core->currency ?? 'USD';
    $d['rate'] = sl_finRate();
    $d['today'] = date('Y-m-d');
    $d['held'] = $customerId ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_add_order WHERE sender_id = :s AND is_deleted = 0 AND status_courier IN (29,12)", [':s' => $customerId]) : 0;
    $d['hasWhatsapp'] = $d['customer'] && !empty($d['customer']->phone);
    return $d;
}

// ── Accounts receivable ─────────────────────────────────────────────────────
function sl_arData($user, $core): array
{
    $d = [];
    $agency = sl_navAgencyWhere('a');
    $u = "(SELECT a.order_id AS id, CONCAT(a.order_prefix, a.order_no) AS inv, a.sender_id, a.order_date AS issued, a.due_date, a.total_order AS amt, a.status_invoice, a.payment_date, 'shipment' AS kind FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier <> 21 AND a.status_invoice IN (23,19) $agency
        UNION ALL SELECT a.order_id, CONCAT(a.order_prefix, a.order_no), a.sender_id, a.order_date, NULL, a.total_order, a.status_invoice, a.payment_date, 'package' FROM cdb_customers_packages a WHERE a.status_invoice IN (23,19)
        UNION ALL SELECT a.consolidate_id, CONCAT(a.c_prefix, a.c_no), a.sender_id, a.c_date, NULL, a.total_order, a.status_invoice, a.payment_date, 'consolidation' FROM cdb_consolidate a WHERE a.status_invoice IN (23,19))";
    $age = "DATEDIFF(CURDATE(), COALESCE(x.due_date, x.issued))";
    $b = sl_row("SELECT COUNT(*) AS n, COALESCE(SUM(x.amt),0) AS total,
        COALESCE(SUM(CASE WHEN $age <= 0 THEN x.amt ELSE 0 END),0) AS b0,
        COALESCE(SUM(CASE WHEN $age BETWEEN 1 AND 30 THEN x.amt ELSE 0 END),0) AS b1,
        COALESCE(SUM(CASE WHEN $age BETWEEN 31 AND 60 THEN x.amt ELSE 0 END),0) AS b2,
        COALESCE(SUM(CASE WHEN $age > 60 THEN x.amt ELSE 0 END),0) AS b3
        FROM $u x WHERE x.status_invoice = 23");
    $total = (float) $b->total;
    $d['total'] = sl_money($total); $d['n'] = (int) $b->n;
    $d['buckets'] = [
        ['label' => 'Current', 'v' => sl_money($b->b0), 'pct' => sl_pct($b->b0, $total), 'cls' => '', 'f' => 'current'],
        ['label' => '1–30 days', 'v' => sl_money($b->b1), 'pct' => sl_pct($b->b1, $total), 'cls' => 'y', 'f' => '1-30'],
        ['label' => '31–60 days', 'v' => sl_money($b->b2), 'pct' => sl_pct($b->b2, $total), 'cls' => 'o', 'f' => '31-60'],
        ['label' => '60+ days', 'v' => sl_money($b->b3), 'pct' => sl_pct($b->b3, $total), 'cls' => 'r', 'f' => '60'],
    ];
    $q = trim((string) ($_GET['q'] ?? ''));
    $filter = (string) ($_GET['filter'] ?? 'all');
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = 25;
    $where = '1=1'; $binds = [];
    if (sl_userIs('client')) $where .= ' AND x.sender_id = ' . (int) $_SESSION['userid'];
    if ($q !== '') { $where .= " AND (x.inv LIKE :q OR CONCAT(u.fname,' ',u.lname) LIKE :q OR u.locker LIKE :q)"; $binds[':q'] = "%$q%"; }
    switch ($filter) {
        case 'pending': $where .= " AND x.status_invoice = 23 AND $age <= 0"; break;
        case 'overdue': $where .= " AND x.status_invoice = 23 AND $age > 0"; break;
        case 'paid': $where .= " AND x.status_invoice = 19"; break;
        case 'current': $where .= " AND x.status_invoice = 23 AND $age <= 0"; break;
        case '1-30': $where .= " AND x.status_invoice = 23 AND $age BETWEEN 1 AND 30"; break;
        case '31-60': $where .= " AND x.status_invoice = 23 AND $age BETWEEN 31 AND 60"; break;
        case '60': $where .= " AND x.status_invoice = 23 AND $age > 60"; break;
        default: $where .= " AND x.status_invoice = 23";
    }
    $count = (int) sl_scalar("SELECT COUNT(*) FROM $u x LEFT JOIN cdb_users u ON u.id = x.sender_id WHERE $where", $binds);
    $rows = sl_rows("SELECT x.*, u.fname, u.lname, u.locker, u.id AS uid, $age AS age FROM $u x LEFT JOIN cdb_users u ON u.id = x.sender_id WHERE $where ORDER BY x.status_invoice DESC, age DESC LIMIT " . (($page - 1) * $per) . ", $per", $binds);
    $list = [];
    foreach ($rows as $r) {
        $name = trim(($r->fname ?? '') . ' ' . ($r->lname ?? '')) ?: 'Customer';
        if ((int) $r->status_invoice === 19) { $st = ['Paid', 'paid']; $bal = 0; }
        elseif ((int) $r->age > 0) { $st = ['Overdue · ' . (int) $r->age . ' d', 'overdue']; $bal = (float) $r->amt; }
        else { $st = ['Pending', 'pending']; $bal = (float) $r->amt; }
        $href = $r->kind === 'shipment' ? sl_route('ShipmentDetail', ['id' => (int) $r->id]) : ($r->kind === 'package' ? sl_route('PackageDetail', ['id' => (int) $r->id]) : sl_route('ConsolidationDetail', ['id' => (int) $r->id]));
        $list[] = ['id' => (int) $r->id, 'kind' => $r->kind, 'inv' => $r->inv, 'href' => $href, 'name' => $name, 'ini' => sl_initials($name), 'uid' => (int) $r->uid, 'issued' => sl_date($r->issued), 'due' => $r->due_date ? sl_date($r->due_date) : '—', 'amt' => sl_money($r->amt), 'bal' => sl_money($bal), 'st' => $st[0], 'cls' => $st[1], 'open' => (int) $r->status_invoice === 23];
    }
    $d['rows'] = $list; $d['count'] = $count; $d['page'] = $page; $d['per'] = $per; $d['q'] = $q; $d['filter'] = $filter;
    $d['filters'] = [['f' => 'all', 'label' => 'All'], ['f' => 'pending', 'label' => 'Pending'], ['f' => 'overdue', 'label' => 'Overdue'], ['f' => 'paid', 'label' => 'Paid']];
    $d['overdueCustomers'] = (int) sl_scalar("SELECT COUNT(DISTINCT x.sender_id) FROM $u x WHERE x.status_invoice = 23 AND $age > 0");
    $d['canRemind'] = $user->cdp_hasPermission(['view_receivable_accounts', 'push_notifications']) && !sl_userIs('client');
    return $d;
}

// ── Financial overview (full and restricted) ────────────────────────────────
function sl_financialOverviewData($user, $core, bool $money): array
{
    $d = ['money' => $money];
    $range = (string) ($_GET['range'] ?? '30d');
    [$from, $rangeLabel, $days] = sl_finRange($range);
    $d['ranges'] = array_map(fn($r) => ['f' => $r[0], 'label' => $r[1], 'cls' => $r[0] === $range ? 'on' : ''], [['7d', '7d'], ['30d', '30d'], ['90d', '90d'], ['1y', '1y']]);
    $d['rangeLabel'] = $rangeLabel;
    $agency = sl_navAgencyWhere('a');
    $d['hub'] = $core->c_city ?? ($core->site_name ?? 'Hub');
    $prevFrom = date('Y-m-d', strtotime($from . " -$days days"));
    $rev = sl_row("SELECT COALESCE(SUM(a.total_order),0) AS r, COUNT(*) AS n, COALESCE(SUM(a.sub_total),0) AS base, COALESCE(SUM(a.total_tax),0) AS tax FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_courier <> 21 AND a.order_date >= :f $agency", [':f' => $from]);
    $revSea = sl_row("SELECT COALESCE(SUM(a.total_order),0) AS r, COUNT(*) AS n FROM cdb_customers_packages a WHERE a.order_date >= :f", [':f' => $from]);
    $revCons = sl_row("SELECT COALESCE(SUM(a.total_order),0) AS r, COUNT(*) AS n FROM cdb_consolidate a WHERE a.c_date >= :f", [':f' => $from]);
    $revenue = (float) $rev->r + (float) $revSea->r + (float) $revCons->r;
    $prevRev = (float) sl_scalar("SELECT COALESCE(SUM(a.total_order),0) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.order_date >= :a AND a.order_date < :b $agency", [':a' => $prevFrom, ':b' => $from]) + (float) sl_scalar("SELECT COALESCE(SUM(a.total_order),0) FROM cdb_customers_packages a WHERE a.order_date >= :a AND a.order_date < :b", [':a' => $prevFrom, ':b' => $from]);
    $target = sl_tableExists('cdb_sl_settings') ? (float) sl_scalar("SELECT setting_value FROM cdb_sl_settings WHERE setting_key = 'revenue_target_month'", [], 0) : 0;
    $marginTarget = sl_tableExists('cdb_sl_settings') ? (float) sl_scalar("SELECT setting_value FROM cdb_sl_settings WHERE setting_key = 'margin_target_pct'", [], 42) : 42;
    // Costs: operating costs (monthly, prorated to the range) + purchase prices recorded on packages
    $opMonthly = 0.0;
    if (sl_tableExists('cdb_operating_costs')) {
        $rate = sl_finRate();
        foreach (sl_rows("SELECT amount, currency, frequency FROM cdb_operating_costs WHERE is_active = 1 AND on_billing = 1 AND frequency <> 'once'") as $c) {
            $m = (float) $c->amount * ($c->frequency === 'yearly' ? 1 / 12 : ($c->frequency === 'weekly' ? 52 / 12 : ($c->frequency === 'daily' ? 30 : 1)));
            $opMonthly += strtoupper($c->currency) === 'GHS' && $rate > 0 ? $m / $rate : $m;
        }
    }
    $opCost = $opMonthly * $days / 30;
    $purchAir = sl_columnExists('cdb_add_order', 'price_purchase') ? (float) sl_scalar("SELECT COALESCE(SUM(a.price_purchase),0) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.order_date >= :f $agency", [':f' => $from]) : 0;
    $purchSea = (float) sl_scalar("SELECT COALESCE(SUM(a.price_purchase),0) FROM cdb_customers_packages a WHERE a.order_date >= :f", [':f' => $from]);
    $cost = $opCost + $purchAir + $purchSea;
    $margin = $revenue - $cost;
    $d['kpi'] = [
        'revenue' => ['v' => sl_money($revenue), 'sub' => $target > 0 ? '/ ' . sl_money($target) . ' target' : '/ no target set', 'pct' => $target > 0 ? sl_pct($revenue, $target) : ($revenue > 0 ? 100 : 0), 'foot' => $prevRev > 0 ? (($revenue >= $prevRev ? '+' : '−') . number_format(abs($revenue - $prevRev) / $prevRev * 100, 1) . '% vs previous ' . $days . ' days') : 'No revenue in the previous period'],
        'cost' => ['v' => sl_money($cost), 'sub' => '(' . sl_pct($cost, $revenue) . '% of revenue)', 'pct' => sl_pct($cost, $revenue), 'foot' => 'Air ' . sl_money($purchAir + $opCost * 0.6) . ' · Sea ' . sl_money($purchSea + $opCost * 0.4)],
        'margin' => ['v' => sl_money($margin), 'sub' => '(' . ($revenue > 0 ? round($margin / $revenue * 100) : 0) . '%)', 'pct' => $revenue > 0 ? max(0, (int) round($margin / $revenue * 100)) : 0, 'foot' => 'Target ' . (int) $marginTarget . '%'],
    ];
    $d['counts'] = [['l' => 'Parcels billed', 'v' => sl_num((int) $rev->n + (int) $revSea->n), 's' => $rangeLabel], ['l' => 'Consolidations', 'v' => sl_num($revCons->n), 's' => $rangeLabel], ['l' => 'Invoices open', 'v' => sl_num(sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_invoice = 23 $agency")), 's' => 'awaiting payment'], ['l' => 'Overdue', 'v' => sl_num(sl_scalar("SELECT COUNT(*) FROM cdb_add_order a WHERE a.is_deleted = 0 AND a.status_invoice = 23 AND a.due_date < CURDATE() $agency")), 's' => 'past due date']];
    // revenue by month (12 months)
    $m1 = sl_monthlyCounts('cdb_add_order', 'order_date', (int) date('Y'), ' AND is_deleted = 0', 'COALESCE(SUM(total_order),0)');
    $m0 = sl_monthlyCounts('cdb_add_order', 'order_date', (int) date('Y') - 1, ' AND is_deleted = 0', 'COALESCE(SUM(total_order),0)');
    $s1 = sl_monthlyCounts('cdb_customers_packages', 'order_date', (int) date('Y'), '', 'COALESCE(SUM(total_order),0)');
    $s0 = sl_monthlyCounts('cdb_customers_packages', 'order_date', (int) date('Y') - 1, '', 'COALESCE(SUM(total_order),0)');
    $series = []; $labels = [];
    $cm = (int) date('n');
    for ($i = 11; $i >= 0; $i--) {
        $mi = $cm - $i; $y = (int) date('Y');
        if ($mi <= 0) { $mi += 12; $y--; }
        $v = $y === (int) date('Y') ? $m1[$mi - 1] + $s1[$mi - 1] : $m0[$mi - 1] + $s0[$mi - 1];
        $series[] = $v; $labels[] = date('M', mktime(0, 0, 0, $mi, 1, $y));
    }
    $mx = max(1, max($series));
    $d['months'] = [];
    foreach ($series as $i => $v) $d['months'][] = ['hi' => $i === 11 ? 'hi' : '', 'sty' => 'height:' . max(2, sl_pct($v, $mx)) . '%', 'tip' => $labels[$i] . ': ' . sl_money($v)];
    $d['monthLabels'] = [$labels[0], $labels[2], $labels[4], $labels[6], $labels[8], $labels[10]];
    // revenue by gateway
    $u = sl_finPaymentsSql();
    $gw = sl_rows("SELECT x.gateway AS k, COALESCE(SUM(x.usd),0) AS amt, COUNT(*) AS n FROM $u x WHERE x.at >= :f AND x.status IN ('success','manual','completed','approved','paid') GROUP BY x.gateway ORDER BY amt DESC LIMIT 5", [':f' => $from]);
    $gt = array_sum(array_map(fn($r) => (float) $r->amt, $gw)) ?: 1;
    $gcls = ['', 'y', 'v', 'o', 'r'];
    $d['gw'] = [];
    foreach ($gw as $i => $r) $d['gw'][] = ['n' => ucfirst((string) $r->k ?: 'Unknown'), 'v' => sl_money($r->amt) . ' · ' . sl_pct($r->amt, $gt) . '%', 'c' => $gcls[$i % 5], 'sty' => 'width:' . sl_pct($r->amt, $gt) . '%', 'count' => (int) $r->n];
    // profit by service
    $svc = [
        ['Air shipments', (int) $rev->n, (float) $rev->r, $purchAir + $opCost * 0.6],
        ['Sea packages', (int) $revSea->n, (float) $revSea->r, $purchSea + $opCost * 0.4],
        ['Consolidations', (int) $revCons->n, (float) $revCons->r, 0.0],
    ];
    $d['svc'] = array_map(fn($s) => ['n' => $s[0], 'p' => sl_num($s[1]), 'r' => sl_money($s[2]), 'c' => sl_money($s[3]), 'm' => sl_money($s[2] - $s[3]) . ($s[2] > 0 ? ' (' . round(($s[2] - $s[3]) / $s[2] * 100) . '%)' : '')], $svc);
    $st = array_sum(array_column($svc, 1)) ?: 1;
    $d['svcN'] = array_map(fn($s) => ['n' => $s[0], 'w' => 'width:' . sl_pct($s[1], $st) . '%', 'v' => sl_num($s[1])], $svc);
    $me = sl_rows("SELECT x.method AS k, COUNT(*) AS n FROM $u x WHERE x.at >= :f GROUP BY x.method ORDER BY n DESC LIMIT 5", [':f' => $from]);
    $mt = array_sum(array_map(fn($r) => (int) $r->n, $me)) ?: 1;
    $d['methods'] = array_map(fn($r) => ['n' => ucfirst((string) $r->k ?: 'Unknown'), 'w' => 'width:' . sl_pct($r->n, $mt) . '%', 'v' => sl_pct($r->n, $mt) . '%'], $me);
    // restricted extras: invoices to follow up, feed, role capabilities
    $invF = (string) ($_GET['inv'] ?? 'overdue');
    $d['invF'] = array_map(fn($x) => ['f' => $x[0], 'label' => $x[1], 'cls' => $x[0] === $invF ? 'on' : ''], [['overdue', 'Overdue'], ['open', 'Open'], ['all', 'All']]);
    $w = $invF === 'overdue' ? 'AND a.due_date < CURDATE()' : '';
    $invs = sl_rows("SELECT a.order_id, CONCAT(a.order_prefix, a.order_no) AS inv, a.order_date, a.due_date, a.sender_id, u.fname, u.lname, u.locker, DATEDIFF(CURDATE(), a.order_date) AS days FROM cdb_add_order a LEFT JOIN cdb_users u ON u.id = a.sender_id WHERE a.is_deleted = 0 AND a.status_invoice = 23 $w $agency ORDER BY days DESC LIMIT 8");
    $d['invs'] = array_map(function ($r) { $n = trim(($r->fname ?? '') . ' ' . ($r->lname ?? '')) ?: 'Customer'; $od = $r->due_date && strtotime($r->due_date) < time(); return ['ini' => sl_initials($n), 'name' => $n, 'lock' => $r->locker ?: '', 'inv' => $r->inv, 'p' => '1 parcel', 'pc' => $od ? 'overdue' : 'pending', 'st' => $od ? 'Overdue' : 'Open', 'days' => (int) $r->days . ' d', 'uid' => (int) $r->sender_id, 'id' => (int) $r->order_id, 'rl' => 'Remind']; }, $invs);
    $feed = sl_rows("SELECT x.*, u.fname, u.lname FROM $u x LEFT JOIN cdb_users u ON u.id = x.sender_id WHERE x.status IN ('success','manual','completed','approved','paid') ORDER BY x.at DESC LIMIT 6");
    $d['feed'] = array_map(function ($r) { $n = trim(($r->fname ?? '') . ' ' . ($r->lname ?? '')) ?: 'Customer'; return ['ini' => sl_initials($n), 'name' => $n, 'inv' => (string) $r->ref, 'how' => ucfirst((string) $r->method), 'at' => sl_ago($r->at), 'amt' => sl_money($r->usd), 'cls' => '']; }, $feed);
    $d['perm'] = [
        ['t' => 'Invoice list, status and age', 'i' => 'i-check', 'c' => 'ok'],
        ['t' => 'Who paid, how and when', 'i' => 'i-check', 'c' => 'ok'],
        ['t' => 'Send payment reminders', 'i' => 'i-check', 'c' => 'ok'],
        ['t' => 'Amounts, totals and margin', 'i' => $money ? 'i-check' : 'i-lock', 'c' => $money ? 'ok' : 'no'],
        ['t' => 'Export the ledger', 'i' => $money ? 'i-check' : 'i-lock', 'c' => $money ? 'ok' : 'no'],
    ];
    $d['roleName'] = (string) sl_scalar('SELECT role_name FROM cdb_user_roles WHERE role_id = :r', [':r' => (int) ($_SESSION['userlevel'] ?? 0)], 'Staff');
    $d['tickets'] = sl_tableExists('cdb_cs_tickets') ? (int) sl_scalar("SELECT COUNT(*) FROM cdb_cs_tickets WHERE status NOT IN ('closed','resolved')") : 0;
    if (!$money) {
        // Restricted board: no amount leaves the server.
        $hid = '₵ ••••';
        foreach ($d['kpi'] as $k => $v) { $d['kpi'][$k]['v'] = $hid; $d['kpi'][$k]['sub'] = ''; $d['kpi'][$k]['foot'] = 'Amounts hidden for your role'; }
        foreach ($d['gw'] as $k => $v) { $d['gw'][$k]['v'] = $v['count'] . ' payments'; }
        foreach ($d['svc'] as $k => $v) { $d['svc'][$k]['r'] = $hid; $d['svc'][$k]['c'] = $hid; $d['svc'][$k]['m'] = $hid; }
        foreach ($d['feed'] as $k => $v) { $d['feed'][$k]['amt'] = ''; }
        foreach ($d['months'] as $k => $v) { $d['months'][$k]['tip'] = ''; }
    }
    return $d;
}

// ── Transactions ────────────────────────────────────────────────────────────
function sl_transactionsData($user, $core): array
{
    $d = [];
    $range = (string) ($_GET['range'] ?? '30d');
    [$from, $rangeLabel, $days] = sl_finRange($range);
    $d['ranges'] = array_map(fn($r) => ['f' => $r[0], 'label' => $r[1], 'cls' => $r[0] === $range ? 'on' : ''], [['today', 'Today'], ['7d', '7d'], ['30d', '30d'], ['90d', '90d']]);
    $d['rangeLabel'] = $rangeLabel;
    $u = sl_finPaymentsSql();
    $t = sl_row("SELECT COUNT(*) AS n, COALESCE(SUM(CASE WHEN x.status IN ('success','manual','completed','approved','paid') THEN x.usd ELSE 0 END),0) AS inn,
        COALESCE(SUM(CASE WHEN x.refunded > 0 OR x.status IN ('reversed','reversal-pending') THEN x.refunded / NULLIF(" . (sl_finRate() ?: 1) . ",0) ELSE 0 END),0) AS refunds,
        SUM(x.refunded > 0 OR x.status IN ('reversed','reversal-pending')) AS nref,
        COALESCE(SUM(CASE WHEN x.status IN ('pending','ongoing','processing','queued') THEN x.usd ELSE 0 END),0) AS unsettled,
        SUM(x.status IN ('pending','ongoing','processing','queued')) AS nuns
        FROM $u x WHERE x.at >= :f", [':f' => $from]);
    $target = sl_tableExists('cdb_sl_settings') ? (float) sl_scalar("SELECT setting_value FROM cdb_sl_settings WHERE setting_key = 'revenue_target_month'", [], 0) : 0;
    $prev = (float) sl_scalar("SELECT COALESCE(SUM(x.usd),0) FROM $u x WHERE x.at >= :a AND x.at < :b AND x.status IN ('success','manual','completed','approved','paid')", [':a' => date('Y-m-d', strtotime($from . " -$days days")), ':b' => $from]);
    $in = (float) $t->inn;
    $d['in'] = ['v' => sl_money($in), 'target' => $target > 0 ? '/ ' . sl_money($target) . ' target' : '', 'pct' => $target > 0 ? sl_pct($in, $target) : ($in > 0 ? 100 : 0), 'ft' => sl_num($t->n) . ' payments' . ($prev > 0 ? ' · ' . ($in >= $prev ? '▲ ' : '▼ ') . number_format(abs($in - $prev) / $prev * 100, 1) . '%' : '')];
    $d['refunds'] = ['v' => sl_money($t->refunds), 'pct' => sl_pct($t->refunds, $in), 'ft' => (int) $t->nref . ' refunds or reversals'];
    $d['unsettled'] = ['v' => sl_money($t->unsettled), 'pct' => sl_pct($t->unsettled, $in), 'ft' => (int) $t->nuns . ' awaiting the gateway'];
    // net cash per day
    $daily = sl_rows("SELECT DATE(x.at) AS d, COALESCE(SUM(CASE WHEN x.status IN ('success','manual','completed','approved','paid') THEN x.usd ELSE 0 END),0) - COALESCE(SUM(x.refunded / NULLIF(" . (sl_finRate() ?: 1) . ",0)),0) AS net FROM $u x WHERE x.at >= :f GROUP BY DATE(x.at)", [':f' => $from]);
    $map = [];
    foreach ($daily as $r) $map[$r->d] = (float) $r->net;
    $n = max(1, min($days, 90));
    $vals = [];
    for ($i = $n - 1; $i >= 0; $i--) $vals[] = $map[date('Y-m-d', strtotime("-$i days"))] ?? 0;
    $mx = max(1, max($vals));
    $svg = '';
    $steps = 3;
    for ($g = 0; $g <= $steps; $g++) {
        $y = round(190 - $g * 152.6 / $steps, 1);
        $svg .= '<line x1="44" y1="' . $y . '" x2="1030" y2="' . $y . '" stroke="var(--line)" stroke-dasharray="' . ($g ? '3 4' : '0') . '"></line><text x="36" y="' . ($y + 4) . '" text-anchor="end">' . h(sl_money(round($mx * $g / $steps))) . '</text>';
    }
    $bw = 986 / $n; $w = max(3, $bw * 0.6);
    foreach ($vals as $i => $v) {
        $hh = round($v / $mx * 152.6, 1);
        $svg .= sprintf('<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="5" fill="%s"%s><title>%s: %s</title></rect>', 44 + $i * $bw + ($bw - $w) / 2, 190 - $hh, $w, $hh, $i === $n - 1 ? '#ECEAE5' : '#E9E7E1', $i === $n - 1 ? ' stroke="#D9D7D0"' : '', date('M j', strtotime('-' . ($n - 1 - $i) . ' days')), h(sl_money($v)));
    }
    for ($k = 0; $k < 5; $k++) {
        $idx = (int) round($k * ($n - 1) / 4);
        $svg .= '<text x="' . round(44 + $idx * $bw + $bw / 2, 1) . '" y="212" text-anchor="middle">' . date('M j', strtotime('-' . ($n - 1 - $idx) . ' days')) . '</text>';
    }
    $d['chart'] = $svg; $d['chartN'] = $n;
    $d['avg'] = sl_money(array_sum($vals) / $n);
    // list
    $q = trim((string) ($_GET['q'] ?? ''));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = 25;
    $where = 'x.at >= :f'; $binds = [':f' => $from];
    if ($q !== '') { $where .= " AND (x.ref LIKE :q OR x.pkgs LIKE :q OR CONCAT(u.fname,' ',u.lname) LIKE :q)"; $binds[':q'] = "%$q%"; }
    $count = (int) sl_scalar("SELECT COUNT(*) FROM $u x LEFT JOIN cdb_users u ON u.id = x.sender_id WHERE $where", $binds);
    $rows = sl_rows("SELECT x.*, u.fname, u.lname FROM $u x LEFT JOIN cdb_users u ON u.id = x.sender_id WHERE $where ORDER BY x.at DESC LIMIT " . (($page - 1) * $per) . ", $per", $binds);
    $d['rows'] = array_map(function ($r) {
        [$st, , $bucket] = sl_finStatus($r->src, $r->status, (float) $r->refunded);
        $neg = $bucket === 'refunded' && (float) $r->refunded > 0 && (float) $r->usd <= 0;
        $pill = $bucket === 'paid' ? 'ok' : ($bucket === 'failed' || $bucket === 'refunded' ? 'bad' : ($r->src === 'charge' ? 'warn' : ''));
        return ['ref' => $r->ref, 'at' => sl_date($r->at, 'M j, H:i'), 'name' => trim(($r->fname ?? '') . ' ' . ($r->lname ?? '')) ?: 'Customer', 'inv' => is_array(json_decode((string) $r->pkgs, true)) ? 'Consolidation #' . (int) $r->entity_id : (string) $r->pkgs, 'method' => ucfirst((string) $r->gateway) . ($r->method && strtolower($r->method) !== strtolower($r->gateway) ? ' · ' . $r->method : ''), 'amt' => ($neg ? '−' : '') . sl_money(abs((float) $r->usd)), 'neg' => $neg, 'st' => $bucket === 'paid' ? 'Settled' : ($bucket === 'refunded' ? 'Refund' : $st), 'pill' => $pill];
    }, $rows);
    $d['count'] = $count; $d['page'] = $page; $d['per'] = $per; $d['q'] = $q;
    return $d;
}

// ── Billing hub ─────────────────────────────────────────────────────────────
function sl_billingHubData($user, $core): array
{
    $d = [];
    $on = cdp_financeEnabled();
    $pinned = function_exists('cdp_financePinnedByConfig') && cdp_financePinnedByConfig();
    $d['on'] = $on; $d['pinned'] = $pinned;
    $d['mOff'] = $on ? '' : 'off';
    $d['mCls'] = $on ? 'on' : '';
    $d['mAria'] = $on ? 'true' : 'false';
    $d['mNote'] = $on ? 'Prices, invoices, payments and clearance are part of the workflow.' : 'Finance is off: parcels move without invoices, payments or clearance. Nothing is deleted.';
    if ($pinned) $d['mNote'] .= ' Set by CDP_FINANCE_ENABLED in config/config.php, so the switch here is recorded but not applied.';
    $d['canSwitch'] = $user->cdp_hasPermission(['manage_finance_switch', 'superadmin']) || sl_userIs('superadmin');
    $u = sl_finPaymentsSql();
    $monthStart = date('Y-m-01');
    $billedM = (float) sl_scalar("SELECT COALESCE(SUM(total_order),0) FROM cdb_add_order WHERE is_deleted = 0 AND order_date >= :m", [':m' => $monthStart]);
    $recvM = (float) sl_scalar("SELECT COALESCE(SUM(x.usd),0) FROM $u x WHERE x.at >= :m AND x.status IN ('success','manual','completed','approved','paid')", [':m' => $monthStart]);
    $open = sl_row("SELECT COUNT(*) AS n, COALESCE(SUM(total_order),0) AS amt FROM cdb_add_order WHERE is_deleted = 0 AND status_invoice = 23 AND status_courier <> 21");
    $overdue = sl_row("SELECT COUNT(*) AS n, COALESCE(SUM(total_order),0) AS amt FROM cdb_add_order WHERE is_deleted = 0 AND status_invoice = 23 AND due_date < CURDATE()");
    $unpriced = sl_tableExists('cdb_add_order_item') && sl_columnExists('cdb_add_order_item', 'priced_at') ? (int) sl_scalar("SELECT COUNT(DISTINCT i.order_id) FROM cdb_add_order_item i JOIN cdb_add_order a ON a.order_id = i.order_id WHERE i.priced_at IS NULL AND a.is_deleted = 0 AND a.status_courier NOT IN (8,15,21)") : 0;
    $spark = function (string $table, string $col, string $expr, string $where = '') { $v = array_values(sl_dailyCounts($table, $col, 14, $where)); $m = max(1, max($v)); return array_map(fn($x) => 'height:' . max(4, sl_pct($x, $m)) . '%', $v); };
    $d['kpis'] = [
        ['icon' => 'i-receipt', 'l' => 'Billed this month', 'v' => sl_money($billedM), 's' => date('F'), 'sp' => $spark('cdb_add_order', 'order_date', '', ' AND is_deleted = 0')],
        ['icon' => 'i-dollar', 'l' => 'Received this month', 'v' => sl_money($recvM), 's' => sl_pct($recvM, $billedM) . '% of billed', 'sp' => []],
        ['icon' => 'i-clock', 'l' => 'Open invoices', 'v' => sl_num($open->n), 's' => sl_money($open->amt) . ' outstanding', 'sp' => []],
        ['icon' => 'i-alert', 'l' => 'Overdue', 'v' => sl_num($overdue->n), 's' => sl_money($overdue->amt) . ' past due', 'sp' => []],
        ['icon' => 'i-tag', 'l' => 'Waiting for a price', 'v' => sl_num($unpriced), 's' => 'packages without a priced item', 'sp' => []],
    ];
    // customer accounts: top balances
    $accts = sl_rows("SELECT u.id, u.fname, u.lname, u.locker, pm.label AS ptype, COALESCE(pm.days,0) AS days, COALESCE(SUM(CASE WHEN a.status_invoice = 23 THEN a.total_order ELSE 0 END),0) AS bal, SUM(a.status_invoice = 23 AND a.due_date < CURDATE()) AS od FROM cdb_users u JOIN cdb_add_order a ON a.sender_id = u.id AND a.is_deleted = 0 AND a.status_courier <> 21 LEFT JOIN cdb_payment_methods pm ON pm.id = a.order_payment_method WHERE u.userlevel = 1 GROUP BY u.id ORDER BY bal DESC LIMIT 5");
    $d['accts'] = array_map(fn($r) => ['id' => (int) $r->id, 'n' => trim($r->fname . ' ' . $r->lname), 'type' => $r->ptype ?: 'Prepaid', 'lim' => (int) $r->days ? (int) $r->days . ' days' : 'pay on collection', 'bal' => sl_money($r->bal), 'c' => (int) $r->od ? 'var(--red-ink)' : 'var(--muted)', 'note' => (int) $r->od ? (int) $r->od . ' overdue' : 'in terms', 'btn' => (float) $r->bal > 0 ? 'Record payment' : 'Statement'], $accts);
    // contract rates / fees from settings
    $d['fees'] = [
        ['t' => 'Exchange rate', 'v' => 'USD 1 = GHS ' . number_format(sl_finRate(), 2)],
        ['t' => 'Handling fee (GHS)', 'v' => 'tiered: ₵20 under ₵300 … ₵1,000 over ₵20k'],
        ['t' => 'Insurance', 'v' => (isset($core->tax_insurance) ? (float) $core->tax_insurance : 0) . '% of declared value'],
        ['t' => 'Customs tariff', 'v' => (isset($core->tax_custom_tariffis) ? (float) $core->tax_custom_tariffis : 0) . '%'],
        ['t' => 'Tariff rows', 'v' => sl_num(sl_scalar('SELECT COUNT(*) FROM cdb_shipping_fees')) . ' active'],
    ];
    // reconciliation today
    $todayCash = (float) sl_scalar("SELECT COALESCE(SUM(x.usd),0) FROM $u x WHERE DATE(x.at) = CURDATE() AND (x.src = 'charge' OR x.gateway = 'cash' OR x.gateway LIKE 'Manual%')");
    $todayGw = (float) sl_scalar("SELECT COALESCE(SUM(x.usd),0) FROM $u x WHERE DATE(x.at) = CURDATE() AND x.src <> 'charge' AND x.gateway <> 'cash' AND x.gateway NOT LIKE 'Manual%' AND x.status IN ('success','manual','completed','approved','paid')");
    $todayPending = (int) sl_scalar("SELECT COUNT(*) FROM $u x WHERE DATE(x.at) = CURDATE() AND x.status IN ('pending','ongoing','processing','queued')");
    $d['rec'] = [
        ['d' => 'ok', 't' => 'Cash and manual', 's' => 'Recorded at the counter today', 'v' => sl_money($todayCash)],
        ['d' => 'ok', 't' => 'Gateways', 's' => 'Confirmed by Paystack, Hubtel, Stripe or PayPal', 'v' => sl_money($todayGw)],
        ['d' => $todayPending ? 'warn' : 'ok', 't' => 'Awaiting confirmation', 's' => $todayPending ? 'Re-check from the Financial Sheet' : 'Nothing pending', 'v' => (string) $todayPending],
    ];
    $d['reconL'] = 'Open Financial Sheet';
    $d['bulkL'] = 'Bill unbilled packages';
    // coverage: where each finance feature lives
    $cf = (string) ($_GET['area'] ?? 'all');
    $d['cf'] = array_map(fn($x) => ['f' => $x[0], 'label' => $x[1], 'cls' => $x[0] === $cf ? 'on' : ''], [['all', 'All'], ['pricing', 'Pricing'], ['billing', 'Billing'], ['payments', 'Payments'], ['reports', 'Reports']]);
    $cov = [
        ['pricing', 'Shipping tariffs', 'Settings › Shipping tariffs', sl_route('Tariffs'), true],
        ['pricing', 'Rate editor', 'Settings › Shipping tariffs › Add rate', sl_route('RateEditor'), true],
        ['pricing', 'Price package items', 'Financial Sheet › consolidation', 'financial_sheet.php?legacy=1', $on],
        ['pricing', 'Send prices to customers', 'Finance › Send prices', sl_route('SendPrices'), true],
        ['pricing', 'Quote calculator', 'Public site', sl_route('QuoteCalculator'), true],
        ['billing', 'Bill a customer', 'Financial Sheet › consolidation', 'financial_sheet.php?legacy=1', $on],
        ['billing', 'Discounts and adjustments', 'Financial Sheet › customer', 'financial_sheet.php?legacy=1', $on],
        ['billing', 'Accounts receivable', 'Finance › Accounts receivable', sl_route('AccountsReceivable'), $on],
        ['billing', 'Customer bills (self-service)', 'Customer portal › My bills', sl_route('MyBills'), $on],
        ['billing', 'Payment terms', 'Settings › Payment methods', 'payment_methods_list.php', true],
        ['payments', 'Record a payment', 'Finance › Payments › Record', sl_route('RecordPayment'), $on],
        ['payments', 'All payments', 'Finance › Payments', sl_route('Payments'), $on],
        ['payments', 'Gateway set-up', 'Settings › Payment gateways', 'payment_mode_list.php', true],
        ['payments', 'Clear for delivery', 'Financial Sheet › payment', 'financial_sheet.php?legacy=1', $on],
        ['payments', 'Online checkout', 'Customer portal › Pay', sl_route('PayCheckout'), $on],
        ['reports', 'Transactions', 'Finance › Transactions', sl_route('Transactions'), $on],
        ['reports', 'Financial overview', 'Finance › Financial overview', sl_route('FinancialOverview'), $on],
        ['reports', 'Payments received', 'Reports › Payments received', 'report_payments_received_list.php', $on],
        ['reports', 'Customer balances', 'Reports › Customer balances', 'report_customers_balance_list.php', $on],
        ['reports', 'Operating costs', 'Settings › Operating costs', sl_route('CostSettings'), true],
    ];
    $d['cov'] = [];
    foreach ($cov as $i => $c) {
        if ($cf !== 'all' && $c[0] !== $cf) continue;
        $d['cov'][] = ['n' => $i + 1, 't' => $c[1], 'where' => $c[2], 'href' => $c[3], 'off' => $c[4] ? '' : 'off', 'pc' => $c[4] ? 'delivered' : 'plain', 'st' => $c[4] ? 'Live' : 'Off'];
    }
    $d['covN'] = count($cov);
    return $d;
}
