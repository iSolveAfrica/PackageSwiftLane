<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************

/**
 * "Gerald Master View" — the management and administrative dashboard.
 *
 * One engine computes every figure for a period (and the period before it,
 * for comparison) from the tables the rest of the app already writes:
 *   shipments       cdb_add_order (+ cdb_address_shipments for destinations)
 *   tracking        cdb_courier_track (handover dates, last movement)
 *   warehouse       cdb_customers_packages, staff transfers and tasks
 *   money           shipment charges (total_order), purchase cost
 *                   (price_purchase) and, when the financial module is in
 *                   use, the billing ledger and payments (helpers/fs_status.php)
 *   customers       cdb_users (client roles)
 *   complaints      cdb_cs_tickets (Customer Service) and survey scores
 *   staff           helpers/staffops.php
 *
 * The page, the exports (CSV / Excel / PDF) and the scheduled e-mails all call
 * cdp_mgmtData(), so the numbers agree everywhere. Money is shown only to
 * people allowed to see money in reports (cdp_canViewMoney 'reports').
 *
 * Delivery targets are set in days per mode (Settings): a shipment is on time
 * when it was handed over (Delivered / Picked up) within its target, and
 * delayed when it is still open past it.
 */

require_once __DIR__ . '/ops_db.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/staffops.php';

if (!defined('CDP_MGMT_SCHEMA')) {
    define('CDP_MGMT_SCHEMA', '1');
}

if (!function_exists('cdp_mgmtEnsureTables')) {

    function cdp_mgmtEnsureTables()
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        if (cdp_opsSetting('mgmt', 'schema_version', '') === CDP_MGMT_SCHEMA) {
            return $ready = true;
        }
        $t = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';
        $ok = cdp_opsDdl([
            "CREATE TABLE IF NOT EXISTS cdb_mgmt_layouts (user_id INT NOT NULL, layout TEXT NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (user_id))$t",
            "CREATE TABLE IF NOT EXISTS cdb_mgmt_schedules (
                id INT NOT NULL AUTO_INCREMENT, name VARCHAR(120) NOT NULL, frequency VARCHAR(10) NOT NULL DEFAULT 'weekly', weekday TINYINT NOT NULL DEFAULT 1,
                monthday TINYINT NOT NULL DEFAULT 1, hour TINYINT NOT NULL DEFAULT 7, format VARCHAR(6) NOT NULL DEFAULT 'pdf', recipients TEXT NOT NULL,
                sections VARCHAR(500) NOT NULL, branch_id INT NULL, active TINYINT(1) NOT NULL DEFAULT 1, last_run_at DATETIME NULL, last_status VARCHAR(255) NULL,
                next_run_at DATETIME NULL, created_by INT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), KEY idx_next (active, next_run_at))$t",
            "CREATE TABLE IF NOT EXISTS cdb_mgmt_alert_acks (
                alert_key VARCHAR(120) NOT NULL, acked_by INT NOT NULL, note VARCHAR(255) NULL, acked_at DATETIME NOT NULL, PRIMARY KEY (alert_key))$t",
            "CREATE TABLE IF NOT EXISTS cdb_mgmt_insights (
                id INT NOT NULL AUTO_INCREMENT, cache_key CHAR(40) NOT NULL, body MEDIUMTEXT NOT NULL, source VARCHAR(20) NOT NULL, created_by INT NULL, created_at DATETIME NOT NULL,
                PRIMARY KEY (id), KEY idx_key (cache_key, created_at))$t",
        ]);
        if ($ok) {
            cdp_opsSettingSet('mgmt', 'schema_version', CDP_MGMT_SCHEMA, 0);
        }
        return $ready = $ok;
    }

    function cdp_mgmtSettingDefaults()
    {
        return ['sla_air_days' => '10', 'sla_sea_days' => '45', 'sla_other_days' => '14', 'stale_days' => '7', 'overhead_per_shipment' => '0',
            'off_hours_start' => '22:00', 'off_hours_end' => '05:00', 'anomaly_z' => '3', 'login_fail_threshold' => '10'];
    }

    function cdp_mgmtSettings()
    {
        return array_merge(cdp_mgmtSettingDefaults(), cdp_opsSettings('mgmt'));
    }

    /** Admins, and branch managers / finance officers for their own branches. */
    function cdp_mgmtCanView($u = null)
    {
        $rank = cdp_opsRank($u);
        if ($rank >= 3) {
            return true;
        }
        return $rank >= 2 && in_array(cdp_staffJobRole($u), ['branch_manager', 'finance_officer'], true);
    }

    function cdp_mgmtCanMoney($u = null)
    {
        global $user;
        $u = $u ?: $user;
        if (PHP_SAPI === 'cli') {
            return true;    // scheduled reports go to the recipients an administrator chose
        }
        return cdp_opsRank($u) >= 4 || cdp_canViewMoney($u, 'reports');
    }

    function cdp_mgmtWidgets()
    {
        return [
            'executive' => 'Executive overview', 'shipments' => 'Total shipments', 'warehouse' => 'Warehouse activity', 'delivery' => 'Delivery performance',
            'revenue' => 'Revenue performance', 'growth' => 'Monthly growth', 'acquisition' => 'Customer acquisition', 'active_customers' => 'Active vs inactive customers',
            'volume' => 'Shipping volume trend', 'destinations' => 'Revenue by destination', 'receiving' => 'Warehouse receiving productivity',
            'pending' => 'Pending shipments', 'delayed' => 'Delayed shipments', 'balances' => 'Outstanding customer balances', 'complaints' => 'Customer complaints',
            'branches' => 'Branch performance', 'employees' => 'Employee productivity', 'costs' => 'Shipping cost analysis', 'profit' => 'Profitability per shipment',
            'alerts' => 'Fraud and anomaly alerts', 'insights' => 'AI business insights',
        ];
    }
}

// ---------------------------------------------------------------------------
// Filters
// ---------------------------------------------------------------------------

if (!function_exists('cdp_mgmtFilters')) {

    /** Normalised filters: from, to, prev_from, prev_to, branch_id, scope SQL on cdb_add_order o. */
    function cdp_mgmtFilters(array $in)
    {
        $to = cdp_opsDate($in['to'] ?? '', date('Y-m-d'));
        $from = cdp_opsDate($in['from'] ?? '', date('Y-m-d', strtotime('-29 days')));
        if ($from > $to) {
            list($from, $to) = [$to, $from];
        }
        $days = (int) round((strtotime($to) - strtotime($from)) / 86400) + 1;
        $prevTo = date('Y-m-d', strtotime($from . ' -1 day'));
        $prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($days - 1) . ' days'));
        $branch = (int) ($in['branch_id'] ?? 0);
        $allowed = cdp_opsRank() >= 3 || PHP_SAPI === 'cli' ? null : cdp_staffBranchIds();
        if ($allowed !== null && $branch && !in_array($branch, $allowed, true)) {
            $branch = 0;
        }
        $ids = $branch ? [$branch] : ($allowed ?? []);
        $scope = '';
        if ($ids || $allowed !== null) {
            $offices = [];
            $agencies = [];
            foreach ($ids ? cdp_opsQ("SELECT office_id, branch_office_id FROM cdb_staff_branches WHERE id IN (" . implode(',', array_map('intval', $ids)) . ")") : [] as $b) {
                if ($b->office_id) {
                    $offices[] = (int) $b->office_id;
                }
                if ($b->branch_office_id) {
                    $agencies[] = (int) $b->branch_office_id;
                }
            }
            $parts = [];
            if ($offices) {
                $parts[] = 'o.origin_off IN (' . implode(',', $offices) . ')';
            }
            if ($agencies) {
                $parts[] = 'o.agency IN (' . implode(',', $agencies) . ')';
            }
            $scope = ' AND ' . ($parts ? '(' . implode(' OR ', $parts) . ')' : '1=0');
        }
        return ['from' => $from, 'to' => $to, 'prev_from' => $prevFrom, 'prev_to' => $prevTo, 'days' => $days, 'branch_id' => $branch, 'scope' => $scope,
            'money' => cdp_mgmtCanMoney()];
    }

    function cdp_mgmtShipBase($a = 'o')
    {
        return "$a.is_pickup = 0 AND $a.status_courier <> 21";
    }

    function cdp_mgmtPct($cur, $prev)
    {
        if ($prev === null || (float) $prev == 0.0) {
            return null;
        }
        return round(100 * ((float) $cur - (float) $prev) / abs((float) $prev), 1);
    }

    /** Target days for an order_item_category (26 air, 27 sea, other). */
    function cdp_mgmtSlaSql($a = 'o')
    {
        $s = cdp_mgmtSettings();
        return "(CASE $a.order_item_category WHEN 26 THEN " . (int) $s['sla_air_days'] . " WHEN 27 THEN " . (int) $s['sla_sea_days'] . " ELSE " . (int) $s['sla_other_days'] . " END)";
    }

    /** Derived table: first handover date per tracking reference. */
    function cdp_mgmtHandoverJoin($a = 'o')
    {
        return "LEFT JOIN (SELECT order_track, MIN(t_date) handed_at FROM cdb_courier_track WHERE status_courier IN (8, 15) GROUP BY order_track) h
                ON h.order_track = CONCAT(COALESCE($a.order_prefix,''), $a.order_no)";
    }
}

// ---------------------------------------------------------------------------
// Metrics
// ---------------------------------------------------------------------------

if (!function_exists('cdp_mgmtShipmentStats')) {

    function cdp_mgmtShipmentStats($from, $to, $scope)
    {
        $r = cdp_opsOne("SELECT COUNT(*) n, SUM(o.order_item_category = 26) air, SUM(o.order_item_category = 27) sea, COALESCE(SUM(o.total_order),0) revenue,
                           COALESCE(SUM(o.price_purchase),0) cost, COALESCE(SUM(o.total_weight),0) weight, COUNT(DISTINCT o.sender_id) customers,
                           SUM(o.status_courier IN (8, 15)) handed
                         FROM cdb_add_order o WHERE " . cdp_mgmtShipBase() . " AND o.order_date BETWEEN :f AND :t $scope", [':f' => $from, ':t' => $to]);
        return ['n' => (int) ($r->n ?? 0), 'air' => (int) ($r->air ?? 0), 'sea' => (int) ($r->sea ?? 0), 'revenue' => round((float) ($r->revenue ?? 0), 2),
            'cost' => round((float) ($r->cost ?? 0), 2), 'weight' => round((float) ($r->weight ?? 0), 2), 'customers' => (int) ($r->customers ?? 0), 'handed' => (int) ($r->handed ?? 0)];
    }

    function cdp_mgmtLedger($from, $to)
    {
        $out = ['available' => false, 'billed' => 0.0, 'received' => 0.0, 'outstanding' => 0.0];
        if (!cdp_opsTableExists('cdb_consolidate_customer_billing')) {
            return $out;
        }
        require_once __DIR__ . '/fs_status.php';
        $out['available'] = true;
        $out['billed'] = round((float) cdp_opsVal("SELECT COALESCE(SUM(amount_usd),0) FROM cdb_consolidate_customer_billing WHERE billed_at BETWEEN :f AND :t",
            [':f' => $from . ' 00:00:00', ':t' => $to . ' 23:59:59']), 2);
        if (cdp_opsTableExists('cdb_fs_payments') && function_exists('cdp_fsMoneyExpr')) {
            $out['received'] = round((float) cdp_opsVal("SELECT COALESCE(SUM(" . cdp_fsMoneyExpr() . "/NULLIF(exchange_rate,0)),0) FROM cdb_fs_payments
                WHERE recorded_at BETWEEN :f AND :t AND " . cdp_fsMoneySqlFilter(), [':f' => $from . ' 00:00:00', ':t' => $to . ' 23:59:59']), 2);
        }
        $out['outstanding'] = round((float) cdp_opsVal("SELECT COALESCE(SUM(GREATEST(0, COALESCE(amount_ghs,0)-COALESCE(discount_ghs,0)-COALESCE(paid_ghs,0))/NULLIF(exchange_rate,0)),0)
            FROM cdb_consolidate_customer_billing"), 2);
        return $out;
    }

    function cdp_mgmtWarehouse($from, $to)
    {
        $f = $from . ' 00:00:00';
        $t = $to . ' 23:59:59';
        $out = [
            'received' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_customers_packages WHERE order_datetime BETWEEN :f AND :t", [':f' => $f, ':t' => $t]),
            'waiting' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_customers_packages WHERE status_courier NOT IN (8, 15, 21)"),
            'ready_pickup' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_add_order WHERE status_courier IN (32, 6)"),
            'in_warehouse' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_add_order WHERE status_courier = 4"),
            'transfers' => 0, 'tasks_done' => 0, 'tasks_open' => 0,
        ];
        if (cdp_staffEnsureTables()) {
            $out['transfers'] = (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_staff_transfers WHERE created_at BETWEEN :f AND :t AND status <> 'cancelled'", [':f' => $f, ':t' => $t]);
            $out['tasks_done'] = (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_staff_tasks WHERE completed_at BETWEEN :f AND :t", [':f' => $f, ':t' => $t]);
            $out['tasks_open'] = (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_staff_tasks WHERE status IN ('open','in_progress')");
        }
        $out['by_day'] = cdp_mgmtDailySeries('cdb_customers_packages', 'order_datetime', $from, $to);
        return $out;
    }

    /** Count per day for [from..to] (labels Y-m-d). $extra is an internal literal. */
    function cdp_mgmtDailySeries($table, $col, $from, $to, $extra = '', $expr = 'COUNT(*)', $alias = '')
    {
        $map = [];
        foreach (cdp_opsQ("SELECT DATE($col) d, $expr v FROM $table $alias WHERE $col BETWEEN :f AND :t $extra GROUP BY DATE($col)", [':f' => $from . ' 00:00:00', ':t' => $to . ' 23:59:59']) as $r) {
            $map[$r->d] = round((float) $r->v, 2);
        }
        $labels = [];
        $values = [];
        for ($d = strtotime($from); $d <= strtotime($to); $d += 86400) {
            $k = date('Y-m-d', $d);
            $labels[] = $k;
            $values[] = $map[$k] ?? 0;
        }
        return ['labels' => $labels, 'values' => $values];
    }

    function cdp_mgmtDelivery($from, $to, $scope)
    {
        $sla = cdp_mgmtSlaSql();
        $r = cdp_opsOne("SELECT COUNT(*) handed, AVG(DATEDIFF(h.handed_at, o.order_date)) avg_days,
                           SUM(DATEDIFF(h.handed_at, o.order_date) <= $sla) on_time, SUM(o.status_courier = 8) delivered, SUM(o.status_courier = 15) picked
                         FROM cdb_add_order o " . cdp_mgmtHandoverJoin() . "
                         WHERE " . cdp_mgmtShipBase() . " AND o.status_courier IN (8, 15) AND h.handed_at BETWEEN :f AND :t $scope",
            [':f' => $from . ' 00:00:00', ':t' => $to . ' 23:59:59']);
        $handed = (int) ($r->handed ?? 0);
        $byMode = cdp_opsQ("SELECT o.order_item_category cat, COUNT(*) n, AVG(DATEDIFF(h.handed_at, o.order_date)) avg_days, SUM(DATEDIFF(h.handed_at, o.order_date) <= $sla) on_time
                            FROM cdb_add_order o " . cdp_mgmtHandoverJoin() . " WHERE " . cdp_mgmtShipBase() . " AND o.status_courier IN (8, 15) AND h.handed_at BETWEEN :f AND :t $scope
                            GROUP BY o.order_item_category", [':f' => $from . ' 00:00:00', ':t' => $to . ' 23:59:59']);
        $modes = [];
        foreach ($byMode as $m) {
            $label = (int) $m->cat === 26 ? 'Air' : ((int) $m->cat === 27 ? 'Sea' : 'Other');
            $modes[$label] = ['n' => (int) $m->n, 'avg_days' => $m->avg_days !== null ? round((float) $m->avg_days, 1) : null,
                'on_time_pct' => (int) $m->n ? round(100 * (int) $m->on_time / (int) $m->n, 1) : null];
        }
        return ['handed' => $handed, 'delivered' => (int) ($r->delivered ?? 0), 'picked_up' => (int) ($r->picked ?? 0),
            'avg_days' => $r && $r->avg_days !== null ? round((float) $r->avg_days, 1) : null,
            'on_time_pct' => $handed ? round(100 * (int) $r->on_time / $handed, 1) : null, 'by_mode' => $modes];
    }

    function cdp_mgmtMonthly($scope, $months = 12)
    {
        $start = date('Y-m-01', strtotime('-' . ($months - 1) . ' months'));
        $ship = [];
        foreach (cdp_opsQ("SELECT DATE_FORMAT(o.order_date, '%Y-%m') m, COUNT(*) n, COALESCE(SUM(o.total_order),0) rev FROM cdb_add_order o
                           WHERE " . cdp_mgmtShipBase() . " AND o.order_date >= :s $scope GROUP BY m", [':s' => $start]) as $r) {
            $ship[$r->m] = $r;
        }
        $roles = implode(',', array_map('intval', cdp_mgmtClientRoles()));
        $cust = [];
        foreach (cdp_opsQ("SELECT DATE_FORMAT(created, '%Y-%m') m, COUNT(*) n FROM cdb_users WHERE userlevel IN ($roles) AND created >= :s GROUP BY m", [':s' => $start]) as $r) {
            $cust[$r->m] = (int) $r->n;
        }
        $rows = [];
        $prev = null;
        for ($i = 0; $i < $months; $i++) {
            $m = date('Y-m', strtotime($start . ' +' . $i . ' months'));
            $n = (int) ($ship[$m]->n ?? 0);
            $rev = round((float) ($ship[$m]->rev ?? 0), 2);
            $rows[] = ['month' => $m, 'shipments' => $n, 'revenue' => $rev, 'new_customers' => $cust[$m] ?? 0,
                'growth_pct' => $prev === null ? null : cdp_mgmtPct($n, $prev['shipments']), 'revenue_growth_pct' => $prev === null ? null : cdp_mgmtPct($rev, $prev['revenue'])];
            $prev = end($rows);
        }
        return $rows;
    }

    function cdp_mgmtClientRoles()
    {
        $ids = [];
        foreach (cdp_roleFlagsMap() as $rid => $r) {
            if (cdp_roleHasFlag($rid, 'is_client')) {
                $ids[] = (int) $rid;
            }
        }
        return $ids ?: [1];
    }

    function cdp_mgmtCustomers($from, $to)
    {
        $roles = implode(',', array_map('intval', cdp_mgmtClientRoles()));
        $total = (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_users WHERE userlevel IN ($roles) AND active = 1");
        $new = (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_users WHERE userlevel IN ($roles) AND created BETWEEN :f AND :t", [':f' => $from . ' 00:00:00', ':t' => $to . ' 23:59:59']);
        $newShipped = (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_users u WHERE u.userlevel IN ($roles) AND u.created BETWEEN :f AND :t
                                        AND (EXISTS (SELECT 1 FROM cdb_add_order o WHERE o.sender_id = u.id AND o.status_courier <> 21)
                                          OR EXISTS (SELECT 1 FROM cdb_customers_packages p WHERE p.sender_id = u.id))", [':f' => $from . ' 00:00:00', ':t' => $to . ' 23:59:59']);
        $active = (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_users u WHERE u.userlevel IN ($roles) AND u.active = 1 AND (
                                       EXISTS (SELECT 1 FROM cdb_add_order o WHERE o.sender_id = u.id AND o.status_courier <> 21 AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY))
                                    OR EXISTS (SELECT 1 FROM cdb_customers_packages p WHERE p.sender_id = u.id AND p.order_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)))");
        $ever = (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_users u WHERE u.userlevel IN ($roles) AND u.active = 1 AND (
                                       EXISTS (SELECT 1 FROM cdb_add_order o WHERE o.sender_id = u.id AND o.status_courier <> 21)
                                    OR EXISTS (SELECT 1 FROM cdb_customers_packages p WHERE p.sender_id = u.id))");
        $bySource = cdp_opsQ("SELECT COALESCE(NULLIF(TRIM(country),''),'Unknown') lbl, COUNT(*) n FROM cdb_users WHERE userlevel IN ($roles) AND created BETWEEN :f AND :t
                              GROUP BY lbl ORDER BY n DESC LIMIT 8", [':f' => $from . ' 00:00:00', ':t' => $to . ' 23:59:59']);
        return ['total' => $total, 'new' => $new, 'new_converted' => $newShipped, 'conversion_pct' => $new ? round(100 * $newShipped / $new, 1) : null,
            'active' => $active, 'inactive' => max(0, $ever - $active), 'never' => max(0, $total - $ever),
            'active_pct' => $total ? round(100 * $active / $total, 1) : null, 'new_by_country' => $bySource];
    }

    function cdp_mgmtDestinations($from, $to, $scope, $limit = 10)
    {
        return cdp_opsQ("SELECT COALESCE(NULLIF(TRIM(a.recipient_country),''),'Unknown') country, COUNT(*) n, COALESCE(SUM(o.total_order),0) revenue,
                           COALESCE(SUM(o.total_order - COALESCE(o.price_purchase,0)),0) margin
                         FROM cdb_add_order o LEFT JOIN cdb_address_shipments a ON a.order_id = o.order_id
                         WHERE " . cdp_mgmtShipBase() . " AND o.order_date BETWEEN :f AND :t $scope GROUP BY country ORDER BY revenue DESC, n DESC LIMIT " . (int) $limit,
            [':f' => $from, ':t' => $to]);
    }

    function cdp_mgmtReceiving($from, $to)
    {
        $f = $from . ' 00:00:00';
        $t = $to . ' 23:59:59';
        $by = cdp_opsQ("SELECT p.user_id, COUNT(*) n, COUNT(DISTINCT DATE(p.order_datetime)) days FROM cdb_customers_packages p
                        WHERE p.order_datetime BETWEEN :f AND :t GROUP BY p.user_id ORDER BY n DESC LIMIT 15", [':f' => $f, ':t' => $t]);
        foreach ($by as $r) {
            $r->name = cdp_opsUserName($r->user_id) ?: 'Unknown';
            $r->per_day = (int) $r->days ? round((int) $r->n / (int) $r->days, 1) : null;
        }
        $hours = array_fill(0, 24, 0);
        foreach (cdp_opsQ("SELECT HOUR(order_datetime) h, COUNT(*) n FROM cdb_customers_packages WHERE order_datetime BETWEEN :f AND :t GROUP BY h", [':f' => $f, ':t' => $t]) as $r) {
            $hours[(int) $r->h] = (int) $r->n;
        }
        return ['by_staff' => $by, 'by_hour' => $hours];
    }

    function cdp_mgmtPending($scope, $limit = 50)
    {
        $s = cdp_mgmtSettings();
        $stale = (int) $s['stale_days'];
        return cdp_opsQ("SELECT o.order_id, CONCAT(COALESCE(o.order_prefix,''), o.order_no) trk, o.order_date, st.mod_style status, st.color,
                           TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) customer, lt.last_move, DATEDIFF(CURDATE(), COALESCE(DATE(lt.last_move), o.order_date)) idle_days
                         FROM cdb_add_order o
                         LEFT JOIN (SELECT order_track, MAX(t_date) last_move FROM cdb_courier_track GROUP BY order_track) lt ON lt.order_track = CONCAT(COALESCE(o.order_prefix,''), o.order_no)
                         LEFT JOIN cdb_styles st ON st.id = o.status_courier LEFT JOIN cdb_users u ON u.id = o.sender_id
                         WHERE " . cdp_mgmtShipBase() . " AND o.status_courier NOT IN (8, 15) AND COALESCE(DATE(lt.last_move), o.order_date) <= DATE_SUB(CURDATE(), INTERVAL $stale DAY) $scope
                         ORDER BY idle_days DESC LIMIT " . (int) $limit);
    }

    function cdp_mgmtDelayed($scope, $limit = 50)
    {
        $sla = cdp_mgmtSlaSql();
        $rows = cdp_opsQ("SELECT o.order_id, CONCAT(COALESCE(o.order_prefix,''), o.order_no) trk, o.order_date, o.order_item_category cat, st.mod_style status, st.color,
                            TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) customer, DATEDIFF(CURDATE(), o.order_date) - $sla days_late
                          FROM cdb_add_order o LEFT JOIN cdb_styles st ON st.id = o.status_courier LEFT JOIN cdb_users u ON u.id = o.sender_id
                          WHERE " . cdp_mgmtShipBase() . " AND o.status_courier NOT IN (8, 15) AND DATEDIFF(CURDATE(), o.order_date) > $sla $scope
                          ORDER BY days_late DESC LIMIT " . (int) $limit);
        $count = (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_add_order o WHERE " . cdp_mgmtShipBase() . " AND o.status_courier NOT IN (8, 15) AND DATEDIFF(CURDATE(), o.order_date) > $sla $scope");
        return ['count' => $count, 'rows' => $rows];
    }

    function cdp_mgmtBalances($limit = 20)
    {
        if (!cdp_opsTableExists('cdb_consolidate_customer_billing')) {
            return ['available' => false, 'rows' => [], 'aging' => []];
        }
        $bal = "GREATEST(0, COALESCE(b.amount_ghs,0)-COALESCE(b.discount_ghs,0)-COALESCE(b.paid_ghs,0))";
        $rows = cdp_opsQ("SELECT b.sender_id, TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) customer, u.locker, SUM($bal) balance, MIN(b.billed_at) oldest, COUNT(*) bills
                          FROM cdb_consolidate_customer_billing b LEFT JOIN cdb_users u ON u.id = b.sender_id
                          GROUP BY b.sender_id, customer, u.locker HAVING balance > 0.009 ORDER BY balance DESC LIMIT " . (int) $limit);
        $aging = cdp_opsOne("SELECT SUM(IF(DATEDIFF(CURDATE(), b.billed_at) <= 30, $bal, 0)) d30, SUM(IF(DATEDIFF(CURDATE(), b.billed_at) BETWEEN 31 AND 60, $bal, 0)) d60,
                               SUM(IF(DATEDIFF(CURDATE(), b.billed_at) BETWEEN 61 AND 90, $bal, 0)) d90, SUM(IF(DATEDIFF(CURDATE(), b.billed_at) > 90, $bal, 0)) d90p,
                               SUM($bal) total, COUNT(DISTINCT IF($bal > 0.009, b.sender_id, NULL)) customers
                             FROM cdb_consolidate_customer_billing b");
        return ['available' => true, 'currency' => 'GHS', 'rows' => $rows, 'aging' => ['0-30' => round((float) ($aging->d30 ?? 0), 2), '31-60' => round((float) ($aging->d60 ?? 0), 2),
            '61-90' => round((float) ($aging->d90 ?? 0), 2), '90+' => round((float) ($aging->d90p ?? 0), 2)], 'total' => round((float) ($aging->total ?? 0), 2), 'customers' => (int) ($aging->customers ?? 0)];
    }

    function cdp_mgmtComplaints($from, $to)
    {
        $out = ['available' => false, 'tickets' => 0, 'complaints' => 0, 'by_category' => [], 'open' => 0, 'avg_resolution_h' => null, 'csat' => null, 'survey_avg' => null, 'unhappy' => 0];
        $f = $from . ' 00:00:00';
        $t = $to . ' 23:59:59';
        if (cdp_opsTableExists('cdb_cs_tickets')) {
            $out['available'] = true;
            $base = "created_at BETWEEN :f AND :t AND disposition = 'none' AND merged_into IS NULL";
            $out['tickets'] = (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_cs_tickets WHERE $base", [':f' => $f, ':t' => $t]);
            $out['complaints'] = (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_cs_tickets WHERE $base AND (category IN ('complaint','claim','damage','lost','delay') OR priority IN ('high','urgent'))", [':f' => $f, ':t' => $t]);
            $out['by_category'] = cdp_opsQ("SELECT category lbl, COUNT(*) n FROM cdb_cs_tickets WHERE $base GROUP BY category ORDER BY n DESC LIMIT 8", [':f' => $f, ':t' => $t]);
            $out['open'] = (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_cs_tickets WHERE disposition = 'none' AND merged_into IS NULL AND status NOT IN ('resolved','closed')");
            $h = cdp_opsVal("SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at))/60 FROM cdb_cs_tickets WHERE resolved_at BETWEEN :f AND :t", [':f' => $f, ':t' => $t], null);
            $out['avg_resolution_h'] = $h !== null ? round((float) $h, 1) : null;
            $c = cdp_opsOne("SELECT COUNT(csat_score) n, SUM(csat_score >= 4) good FROM cdb_cs_tickets WHERE csat_at BETWEEN :f AND :t", [':f' => $f, ':t' => $t]);
            $out['csat'] = $c && (int) $c->n ? round(100 * (int) $c->good / (int) $c->n, 1) : null;
        }
        if (cdp_opsTableExists('cdb_comm_surveys')) {
            $s = cdp_opsOne("SELECT AVG(score) a, SUM(score <= 2) bad FROM cdb_comm_surveys WHERE responded_at BETWEEN :f AND :t", [':f' => $f, ':t' => $t]);
            $out['survey_avg'] = $s && $s->a !== null ? round((float) $s->a, 2) : null;
            $out['unhappy'] = (int) ($s->bad ?? 0);
        }
        return $out;
    }

    function cdp_mgmtBranches($from, $to)
    {
        if (!cdp_staffEnsureTables()) {
            return [];
        }
        $rows = [];
        $sla = cdp_mgmtSlaSql();
        foreach (cdp_staffBranches(true) as $b) {
            if (cdp_opsRank() < 3 && PHP_SAPI !== 'cli' && !in_array((int) $b->id, cdp_staffBranchIds(), true)) {
                continue;
            }
            $parts = [];
            if ($b->office_id) {
                $parts[] = 'o.origin_off = ' . (int) $b->office_id;
            }
            if ($b->branch_office_id) {
                $parts[] = 'o.agency = ' . (int) $b->branch_office_id;
            }
            $scope = ' AND ' . ($parts ? '(' . implode(' OR ', $parts) . ')' : '1=0');
            $s = cdp_mgmtShipmentStats($from, $to, $scope);
            $late = (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_add_order o WHERE " . cdp_mgmtShipBase() . " AND o.status_courier NOT IN (8, 15) AND DATEDIFF(CURDATE(), o.order_date) > $sla $scope");
            $rows[] = ['id' => (int) $b->id, 'name' => $b->name, 'code' => $b->code, 'country' => $b->country_name, 'linked' => (bool) $parts,
                'shipments' => $s['n'], 'revenue' => $s['revenue'], 'margin' => round($s['revenue'] - $s['cost'], 2), 'handed' => $s['handed'], 'delayed' => $late,
                'staff' => (int) $b->staff_count,
                'tasks_done' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_staff_tasks WHERE branch_id = :b AND completed_at BETWEEN :f AND :t", [':b' => (int) $b->id, ':f' => $from . ' 00:00:00', ':t' => $to . ' 23:59:59'])];
        }
        usort($rows, function ($a, $b) { return $b['shipments'] <=> $a['shipments']; });
        return $rows;
    }

    function cdp_mgmtCosts($from, $to, $scope)
    {
        $s = cdp_mgmtSettings();
        $over = (float) $s['overhead_per_shipment'];
        $byMode = cdp_opsQ("SELECT o.order_item_category cat, COUNT(*) n, COALESCE(SUM(o.price_purchase),0) cost, COALESCE(SUM(o.total_order),0) revenue, COALESCE(SUM(o.total_weight),0) weight,
                              SUM(COALESCE(o.price_purchase,0) > 0) costed
                            FROM cdb_add_order o WHERE " . cdp_mgmtShipBase() . " AND o.order_date BETWEEN :f AND :t $scope GROUP BY o.order_item_category", [':f' => $from, ':t' => $to]);
        $rows = [];
        $tot = ['n' => 0, 'cost' => 0.0, 'revenue' => 0.0, 'weight' => 0.0, 'costed' => 0];
        foreach ($byMode as $m) {
            $cost = (float) $m->cost + $over * (int) $m->n;
            $rows[] = ['mode' => (int) $m->cat === 26 ? 'Air' : ((int) $m->cat === 27 ? 'Sea' : 'Other'), 'shipments' => (int) $m->n, 'cost' => round($cost, 2),
                'revenue' => round((float) $m->revenue, 2), 'cost_per_shipment' => (int) $m->n ? round($cost / (int) $m->n, 2) : null,
                'cost_per_kg' => (float) $m->weight > 0 ? round($cost / (float) $m->weight, 2) : null, 'cost_ratio_pct' => (float) $m->revenue > 0 ? round(100 * $cost / (float) $m->revenue, 1) : null,
                'costed_pct' => (int) $m->n ? round(100 * (int) $m->costed / (int) $m->n, 1) : null];
            $tot['n'] += (int) $m->n;
            $tot['cost'] += $cost;
            $tot['revenue'] += (float) $m->revenue;
            $tot['weight'] += (float) $m->weight;
            $tot['costed'] += (int) $m->costed;
        }
        $byCarrier = cdp_opsQ("SELECT COALESCE(NULLIF(TRIM(o.provider_purchase),''),'Not recorded') carrier, COUNT(*) n, COALESCE(SUM(o.price_purchase),0) cost
                               FROM cdb_add_order o WHERE " . cdp_mgmtShipBase() . " AND o.order_date BETWEEN :f AND :t $scope GROUP BY carrier ORDER BY cost DESC LIMIT 8", [':f' => $from, ':t' => $to]);
        return ['by_mode' => $rows, 'by_carrier' => $byCarrier, 'overhead_per_shipment' => $over, 'total_cost' => round($tot['cost'], 2),
            'cost_per_kg' => $tot['weight'] > 0 ? round($tot['cost'] / $tot['weight'], 2) : null, 'costed_pct' => $tot['n'] ? round(100 * $tot['costed'] / $tot['n'], 1) : null];
    }

    /** Margin per shipment = charges - purchase cost - overhead. Pure: unit tested. */
    function cdp_mgmtMargin($revenue, $cost, $overhead)
    {
        $m = (float) $revenue - (float) $cost - (float) $overhead;
        return ['margin' => round($m, 2), 'margin_pct' => (float) $revenue != 0.0 ? round(100 * $m / (float) $revenue, 1) : null];
    }

    function cdp_mgmtProfit($from, $to, $scope)
    {
        $over = (float) cdp_mgmtSettings()['overhead_per_shipment'];
        $expr = "(COALESCE(o.total_order,0) - COALESCE(o.price_purchase,0) - $over)";
        $agg = cdp_opsOne("SELECT COUNT(*) n, AVG($expr) avg_margin, SUM($expr) total_margin, SUM($expr < 0) loss, SUM(COALESCE(o.price_purchase,0) = 0) uncosted,
                             AVG(IF(o.total_order > 0, 100 * $expr / o.total_order, NULL)) avg_pct
                           FROM cdb_add_order o WHERE " . cdp_mgmtShipBase() . " AND o.order_date BETWEEN :f AND :t $scope", [':f' => $from, ':t' => $to]);
        $list = function ($order) use ($expr, $scope, $from, $to) {
            return cdp_opsQ("SELECT CONCAT(COALESCE(o.order_prefix,''), o.order_no) trk, o.order_date, o.total_order revenue, o.price_purchase cost, $expr margin,
                               TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) customer
                             FROM cdb_add_order o LEFT JOIN cdb_users u ON u.id = o.sender_id
                             WHERE " . cdp_mgmtShipBase() . " AND o.order_date BETWEEN :f AND :t AND COALESCE(o.price_purchase,0) > 0 $scope ORDER BY margin $order LIMIT 10", [':f' => $from, ':t' => $to]);
        };
        return ['n' => (int) ($agg->n ?? 0), 'avg_margin' => $agg && $agg->avg_margin !== null ? round((float) $agg->avg_margin, 2) : null,
            'total_margin' => round((float) ($agg->total_margin ?? 0), 2), 'avg_margin_pct' => $agg && $agg->avg_pct !== null ? round((float) $agg->avg_pct, 1) : null,
            'loss_making' => (int) ($agg->loss ?? 0), 'uncosted' => (int) ($agg->uncosted ?? 0), 'worst' => $list('ASC'), 'best' => $list('DESC')];
    }
}

// ---------------------------------------------------------------------------
// Anomalies
// ---------------------------------------------------------------------------

if (!function_exists('cdp_mgmtZScore')) {

    /** z-score of $x against a sample. Pure: unit tested. */
    function cdp_mgmtZScore($x, array $sample)
    {
        $n = count($sample);
        if ($n < 5) {
            return null;
        }
        $mean = array_sum($sample) / $n;
        $var = 0.0;
        foreach ($sample as $v) {
            $var += ($v - $mean) ** 2;
        }
        $sd = sqrt($var / ($n - 1));
        if ($sd < 1e-9) {
            return $x == $mean ? 0.0 : null;
        }
        return round(($x - $mean) / $sd, 2);
    }

    /**
     * Fraud and anomaly checks. Each alert: key (stable, for acknowledging),
     * severity high|medium|low, title, detail, items (refs), and whether it
     * has been acknowledged.
     */
    function cdp_mgmtAlerts($scope = '')
    {
        $s = cdp_mgmtSettings();
        $alerts = [];
        $add = function ($key, $sev, $title, $detail, array $items = []) use (&$alerts) {
            $alerts[] = ['key' => substr($key, 0, 120), 'severity' => $sev, 'title' => $title, 'detail' => $detail, 'items' => array_slice($items, 0, 15)];
        };

        // 1. Unusual daily volume (today vs the previous 30 days).
        $days = [];
        foreach (cdp_opsQ("SELECT o.order_date d, COUNT(*) n FROM cdb_add_order o WHERE " . cdp_mgmtShipBase() . " AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND o.order_date < CURDATE() $scope GROUP BY o.order_date") as $r) {
            $days[] = (int) $r->n;
        }
        $days = array_pad($days, 30, 0);
        $today = (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_add_order o WHERE " . cdp_mgmtShipBase() . " AND o.order_date = CURDATE() $scope");
        $z = cdp_mgmtZScore($today, $days);
        if ($z !== null && abs($z) >= (float) $s['anomaly_z'] && $today > 5) {
            $add('volume:' . date('Y-m-d'), 'medium', 'Unusual shipment volume today', $today . ' shipments today, ' . ($z > 0 ? 'far above' : 'far below') . ' the last 30 days (z = ' . $z . ').');
        }

        // 2. Shipments with no charge.
        $free = cdp_opsQ("SELECT CONCAT(COALESCE(o.order_prefix,''), o.order_no) trk, o.user_id FROM cdb_add_order o
                          WHERE " . cdp_mgmtShipBase() . " AND COALESCE(o.total_order,0) <= 0 AND o.order_incomplete = 1 AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) $scope LIMIT 50");
        if ($free) {
            $by = [];
            foreach ($free as $f) {
                $by[cdp_opsUserName($f->user_id) ?: 'Unknown'] = ($by[cdp_opsUserName($f->user_id) ?: 'Unknown'] ?? 0) + 1;
            }
            arsort($by);
            $add('zero_charge:' . date('Y-W') . ':' . count($free), count($free) > 10 ? 'high' : 'medium', 'Shipments with no charge (last 14 days)',
                count($free) . ' shipments were saved with a total of zero. Most by: ' . implode(', ', array_map(function ($k, $v) { return "$k ($v)"; }, array_keys(array_slice($by, 0, 3, true)), array_slice($by, 0, 3, true))) . '.',
                array_map(function ($f) { return $f->trk; }, $free));
        }

        // 3. Selling below cost.
        $loss = cdp_opsQ("SELECT CONCAT(COALESCE(o.order_prefix,''), o.order_no) trk FROM cdb_add_order o WHERE " . cdp_mgmtShipBase() . "
                          AND o.price_purchase > 0 AND o.total_order < o.price_purchase AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) $scope LIMIT 50");
        if ($loss) {
            $add('below_cost:' . date('Y-W') . ':' . count($loss), 'medium', 'Shipments charged below purchase cost', count($loss) . ' shipments in the last 30 days were charged less than they cost.',
                array_map(function ($f) { return $f->trk; }, $loss));
        }

        // 4. Cancellation rate by staff member.
        $cx = cdp_opsQ("SELECT o.user_id, COUNT(*) total, SUM(o.status_courier = 21) cancelled FROM cdb_add_order o WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) $scope
                        GROUP BY o.user_id HAVING total >= 10");
        $rates = array_map(function ($r) { return (int) $r->cancelled / max(1, (int) $r->total); }, $cx);
        $avg = $rates ? array_sum($rates) / count($rates) : 0;
        foreach ($cx as $r) {
            $rate = (int) $r->cancelled / max(1, (int) $r->total);
            if ((int) $r->cancelled >= 5 && $rate > max(0.15, 3 * $avg)) {
                $add('cancel_rate:' . (int) $r->user_id . ':' . date('Y-m'), 'high', 'High cancellation rate: ' . (cdp_opsUserName($r->user_id) ?: 'Unknown'),
                    (int) $r->cancelled . ' of ' . (int) $r->total . ' shipments cancelled in 30 days (' . round(100 * $rate) . '%, average ' . round(100 * $avg) . '%).');
            }
        }

        // 5. Shipments created out of hours.
        $start = $s['off_hours_start'];
        $end = $s['off_hours_end'];
        $timeCond = $start > $end ? "(TIME(o.order_datetime) >= :s OR TIME(o.order_datetime) < :e)" : "(TIME(o.order_datetime) >= :s AND TIME(o.order_datetime) < :e)";
        $off = cdp_opsQ("SELECT o.user_id, COUNT(*) n FROM cdb_add_order o WHERE o.order_datetime >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND $timeCond $scope GROUP BY o.user_id HAVING n >= 3",
            [':s' => $start . ':00', ':e' => $end . ':00']);
        foreach ($off as $r) {
            $add('off_hours:' . (int) $r->user_id . ':' . date('Y-W'), 'low', 'Out-of-hours activity: ' . (cdp_opsUserName($r->user_id) ?: 'Unknown'),
                (int) $r->n . ' shipments created between ' . $start . ' and ' . $end . ' in the last 7 days.');
        }

        // 6. The same carrier tracking number on several shipments.
        $dup = cdp_opsQ("SELECT o.tracking_purchase t, COUNT(*) n FROM cdb_add_order o WHERE o.tracking_purchase IS NOT NULL AND TRIM(o.tracking_purchase) NOT IN ('', '0')
                         AND o.status_courier <> 21 AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) $scope GROUP BY o.tracking_purchase HAVING n > 1 ORDER BY n DESC LIMIT 20");
        if ($dup) {
            $add('dup_carrier:' . md5(implode(',', array_map(function ($d) { return $d->t; }, $dup))), 'medium', 'Carrier tracking numbers used more than once',
                count($dup) . ' carrier tracking numbers appear on more than one shipment (possible double registration).', array_map(function ($d) { return $d->t . ' ×' . $d->n; }, $dup));
        }

        // 7. Several customer accounts on one phone number.
        $roles = implode(',', array_map('intval', cdp_mgmtClientRoles()));
        $phones = cdp_opsQ("SELECT RIGHT(REPLACE(REPLACE(REPLACE(phone,'+',''),' ',''),'-',''), 9) p, COUNT(*) n FROM cdb_users WHERE userlevel IN ($roles) AND active = 1
                            AND LENGTH(REPLACE(REPLACE(REPLACE(phone,'+',''),' ',''),'-','')) >= 9 GROUP BY p HAVING n >= 3 ORDER BY n DESC LIMIT 20");
        if ($phones) {
            $add('dup_phone:' . md5(implode(',', array_map(function ($d) { return $d->p . $d->n; }, $phones))), 'low', 'Several customer accounts share a phone number',
                count($phones) . ' phone numbers are used by 3 or more customer accounts.', array_map(function ($d) { return '…' . $d->p . ' (' . $d->n . ' accounts)'; }, $phones));
        }

        // 8. Failed logins.
        if (cdp_opsTableExists('cdb_activity_log')) {
            $fails = cdp_opsQ("SELECT ip, COUNT(*) n FROM cdb_activity_log WHERE module = 'auth' AND outcome IN ('failed','denied') AND created_at >= :c GROUP BY ip HAVING n >= :th ORDER BY n DESC LIMIT 10",
                [':c' => cdp_opsNow(-86400), ':th' => (int) $s['login_fail_threshold']]);
            if ($fails) {
                $add('login_fail:' . date('Y-m-d') . ':' . md5(implode(',', array_map(function ($f) { return $f->ip; }, $fails))), 'high', 'Repeated failed sign-ins',
                    count($fails) . ' addresses failed to sign in ' . (int) $s['login_fail_threshold'] . '+ times in 24 hours.', array_map(function ($f) { return $f->ip . ' (' . $f->n . ')'; }, $fails));
            }
            $denied = (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_activity_log WHERE outcome = 'denied' AND module <> 'auth' AND created_at >= :c", [':c' => cdp_opsNow(-86400)]);
            if ($denied >= 20) {
                $add('rbac_denied:' . date('Y-m-d'), 'medium', 'Many blocked actions', $denied . ' actions were blocked for lack of permission in the last 24 hours (Settings › System logs).');
            }
        }

        // 9. Payment received but balance going up (refund heavy)
        if (cdp_opsTableExists('cdb_fs_payments') && cdp_opsColumnExists('cdb_fs_payments', 'refunded_ghs')) {
            $ref = cdp_opsOne("SELECT COUNT(*) n, COALESCE(SUM(refunded_ghs),0) amt FROM cdb_fs_payments WHERE refunded_ghs > 0 AND recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            if ($ref && (int) $ref->n >= 5) {
                $add('refunds:' . date('Y-W'), 'medium', 'Refunds this week', (int) $ref->n . ' payments were refunded in the last 7 days (GHS ' . number_format((float) $ref->amt, 2) . ').');
            }
        }

        $acks = [];
        if (cdp_mgmtEnsureTables() && $alerts) {
            $keys = array_map(function ($a) { return $a['key']; }, $alerts);
            $ph = [];
            $b = [];
            foreach ($keys as $i => $k) {
                $ph[] = ':k' . $i;
                $b[':k' . $i] = $k;
            }
            foreach (cdp_opsQ("SELECT alert_key, acked_by, acked_at, note FROM cdb_mgmt_alert_acks WHERE alert_key IN (" . implode(',', $ph) . ")", $b) as $a) {
                $acks[$a->alert_key] = ['by' => cdp_opsUserName($a->acked_by), 'at' => $a->acked_at, 'note' => $a->note];
            }
        }
        $order = ['high' => 0, 'medium' => 1, 'low' => 2];
        foreach ($alerts as &$a) {
            $a['ack'] = $acks[$a['key']] ?? null;
        }
        unset($a);
        usort($alerts, function ($x, $y) use ($order) { return [(int) !empty($x['ack']), $order[$x['severity']]] <=> [(int) !empty($y['ack']), $order[$y['severity']]]; });
        return $alerts;
    }
}

// ---------------------------------------------------------------------------
// Assembly, insights, layout, exports, schedules
// ---------------------------------------------------------------------------

if (!function_exists('cdp_mgmtData')) {

    /** Every widget (or only $only) for the filters. Money fields are removed when not allowed. */
    function cdp_mgmtData(array $f, array $only = [])
    {
        $want = function ($k) use ($only) { return !$only || in_array($k, $only, true); };
        $d = ['filters' => ['from' => $f['from'], 'to' => $f['to'], 'prev_from' => $f['prev_from'], 'prev_to' => $f['prev_to'], 'branch_id' => $f['branch_id']], 'money' => $f['money']];
        $cur = cdp_mgmtShipmentStats($f['from'], $f['to'], $f['scope']);
        $prev = cdp_mgmtShipmentStats($f['prev_from'], $f['prev_to'], $f['scope']);
        $d['shipments'] = ['current' => $cur, 'previous' => $prev, 'change_pct' => cdp_mgmtPct($cur['n'], $prev['n']),
            'packages' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_customers_packages WHERE order_date BETWEEN :f AND :t", [':f' => $f['from'], ':t' => $f['to']]),
            'consolidations' => cdp_opsTableExists('cdb_consolidate') ? (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_consolidate WHERE c_date BETWEEN :f AND :t AND status_courier <> 21", [':f' => $f['from'], ':t' => $f['to']]) : 0];
        if ($want('warehouse') || $want('executive')) {
            $d['warehouse'] = cdp_mgmtWarehouse($f['from'], $f['to']);
        }
        if ($want('delivery') || $want('executive')) {
            $d['delivery'] = cdp_mgmtDelivery($f['from'], $f['to'], $f['scope']);
            $d['delivery']['previous_on_time_pct'] = cdp_mgmtDelivery($f['prev_from'], $f['prev_to'], $f['scope'])['on_time_pct'];
        }
        if ($want('revenue') || $want('executive')) {
            $d['revenue'] = ['charges' => $cur['revenue'], 'previous' => $prev['revenue'], 'change_pct' => cdp_mgmtPct($cur['revenue'], $prev['revenue']),
                'per_shipment' => $cur['n'] ? round($cur['revenue'] / $cur['n'], 2) : null, 'ledger' => cdp_mgmtLedger($f['from'], $f['to']),
                'by_day' => cdp_mgmtDailySeries('cdb_add_order', 'o.order_date', $f['from'], $f['to'], 'AND ' . cdp_mgmtShipBase() . ' ' . $f['scope'], 'COALESCE(SUM(o.total_order),0)', 'o')];
        }
        if ($want('growth') || $want('acquisition')) {
            $d['growth'] = cdp_mgmtMonthly($f['scope']);
        }
        if ($want('acquisition') || $want('active_customers') || $want('executive')) {
            $d['customers'] = cdp_mgmtCustomers($f['from'], $f['to']);
        }
        if ($want('volume')) {
            $d['volume'] = cdp_mgmtDailySeries('cdb_add_order', 'o.order_date', $f['from'], $f['to'], 'AND ' . cdp_mgmtShipBase() . ' ' . $f['scope'], 'COUNT(*)', 'o');
        }
        if ($want('destinations')) {
            $d['destinations'] = cdp_mgmtDestinations($f['from'], $f['to'], $f['scope']);
        }
        if ($want('receiving')) {
            $d['receiving'] = cdp_mgmtReceiving($f['from'], $f['to']);
        }
        if ($want('pending')) {
            $d['pending'] = cdp_mgmtPending($f['scope']);
        }
        if ($want('delayed') || $want('executive')) {
            $d['delayed'] = cdp_mgmtDelayed($f['scope']);
        }
        if ($want('balances') || $want('executive')) {
            $d['balances'] = cdp_mgmtBalances();
        }
        if ($want('complaints')) {
            $d['complaints'] = cdp_mgmtComplaints($f['from'], $f['to']);
        }
        if ($want('branches')) {
            $d['branches'] = cdp_mgmtBranches($f['from'], $f['to']);
        }
        if ($want('employees')) {
            $d['employees'] = array_slice(cdp_staffPerformance($f['from'], $f['to'], $f['branch_id']), 0, 25);
        }
        if ($want('costs')) {
            $d['costs'] = cdp_mgmtCosts($f['from'], $f['to'], $f['scope']);
        }
        if ($want('profit')) {
            $d['profit'] = cdp_mgmtProfit($f['from'], $f['to'], $f['scope']);
        }
        if ($want('alerts') || $want('executive')) {
            $d['alerts'] = cdp_mgmtAlerts($f['scope']);
        }
        if (!$f['money']) {
            $d = cdp_mgmtStripMoney($d);
        }
        return $d;
    }

    /** Remove every money figure (keys listed here) recursively; objects stay objects. */
    function cdp_mgmtStripMoney($v)
    {
        static $money = ['revenue', 'cost', 'charges', 'per_shipment', 'ledger', 'margin', 'avg_margin', 'total_margin', 'avg_margin_pct', 'balance', 'balances',
            'total_cost', 'cost_per_kg', 'cost_per_shipment', 'cost_ratio_pct', 'costs', 'profit', 'revenue_growth_pct', 'aging'];
        if (is_object($v)) {
            return (object) cdp_mgmtStripMoney(get_object_vars($v));
        }
        if (!is_array($v)) {
            return $v;
        }
        $out = [];
        foreach ($v as $k => $x) {
            if (is_string($k) && in_array($k, $money, true)) {
                continue;
            }
            $out[$k] = cdp_mgmtStripMoney($x);
        }
        return $out;
    }

    /** Rule-based observations: always available, also the AI's starting facts. */
    function cdp_mgmtRuleInsights(array $d)
    {
        $out = [];
        $s = $d['shipments'];
        if ($s['change_pct'] !== null) {
            $out[] = 'Shipments ' . ($s['change_pct'] >= 0 ? 'rose' : 'fell') . ' ' . abs($s['change_pct']) . '% against the previous period (' . $s['current']['n'] . ' vs ' . $s['previous']['n'] . ').';
        }
        if (isset($d['revenue']['change_pct']) && $d['revenue']['change_pct'] !== null) {
            $out[] = 'Shipment charges ' . ($d['revenue']['change_pct'] >= 0 ? 'grew' : 'dropped') . ' ' . abs($d['revenue']['change_pct']) . '%.';
        }
        if (!empty($d['delivery']['on_time_pct'])) {
            $p = $d['delivery']['previous_on_time_pct'];
            $out[] = $d['delivery']['on_time_pct'] . '% of handovers were within target' . ($p !== null ? ' (previous period ' . $p . '%)' : '') . '.';
        }
        if (!empty($d['delayed']['count'])) {
            $out[] = $d['delayed']['count'] . ' open shipments are past their delivery target.';
        }
        if (isset($d['customers'])) {
            $c = $d['customers'];
            if ($c['new']) {
                $out[] = $c['new'] . ' new customers joined; ' . ($c['conversion_pct'] ?? 0) . '% of them have shipped already.';
            }
            $out[] = $c['active'] . ' customers were active in the last 90 days, ' . $c['inactive'] . ' have gone quiet.';
        }
        if (!empty($d['balances']['total'])) {
            $out[] = 'GHS ' . number_format($d['balances']['total'], 2) . ' is outstanding from ' . $d['balances']['customers'] . ' customers, GHS ' . number_format($d['balances']['aging']['90+'], 2) . ' of it older than 90 days.';
        }
        if (!empty($d['alerts'])) {
            $open = array_filter($d['alerts'], function ($a) { return empty($a['ack']); });
            if ($open) {
                $out[] = count($open) . ' anomaly alerts need a look, the first: ' . reset($open)['title'] . '.';
            }
        }
        return $out;
    }

    /**
     * AI insights. Facts: a compact JSON of the period's figures. The model is
     * asked for observations and actions grounded in those numbers only. Cached
     * for the same filters for 6 hours; regenerated on request.
     */
    function cdp_mgmtInsights(array $f, $refresh = false)
    {
        cdp_mgmtEnsureTables();
        $d = cdp_mgmtData($f, ['executive', 'revenue', 'growth', 'acquisition', 'destinations', 'delivery', 'delayed', 'balances', 'complaints', 'costs', 'profit', 'alerts', 'branches']);
        $rules = cdp_mgmtRuleInsights($d);
        $key = sha1(cdp_opsJson([$f['from'], $f['to'], $f['branch_id'], $f['money']]));
        if (!$refresh) {
            $c = cdp_opsOne("SELECT body, source, created_at FROM cdb_mgmt_insights WHERE cache_key = :k AND created_at >= :c ORDER BY id DESC LIMIT 1", [':k' => $key, ':c' => cdp_opsNow(-6 * 3600)]);
            if ($c) {
                return ['ok' => true, 'source' => $c->source, 'text' => $c->body, 'rules' => $rules, 'at' => $c->created_at, 'cached' => true];
            }
        }
        require_once __DIR__ . '/ai_assistant.php';
        require_once __DIR__ . '/ai_assistant_llm.php';
        if (!function_exists('cdp_aiConfigured') || !cdp_aiConfigured()) {
            return ['ok' => true, 'source' => 'rules', 'text' => '', 'rules' => $rules, 'note' => 'Connect an AI provider under Tools › AI Assistant for written analysis.'];
        }
        $facts = $d;
        unset($facts['warehouse']['by_day'], $facts['revenue']['by_day']);
        foreach (['pending', 'delayed'] as $k) {
            if (isset($facts[$k]['rows'])) {
                $facts[$k]['rows'] = array_slice($facts[$k]['rows'], 0, 5);
            }
        }
        $system = 'You are a business analyst for a shipping and parcel-forwarding company. Using ONLY the JSON figures provided, write for the managing director: '
            . '"Highlights" (3-5 bullets), "Risks" (2-4 bullets) and "Recommended actions" (3-5 concrete bullets, each tied to a figure). '
            . 'Quote the numbers you rely on. Do not invent figures, causes you cannot see, or benchmarks. Money values without a currency are in the shipment currency. '
            . 'If a figure is missing or zero, do not speculate about it. Use Markdown headings and bullets, at most 300 words.';
        $prompt = 'Period ' . $f['from'] . ' to ' . $f['to'] . ' (compared with ' . $f['prev_from'] . ' to ' . $f['prev_to'] . ').' . "\nObservations already computed:\n- "
            . implode("\n- ", $rules) . "\n\nFIGURES (JSON):\n" . mb_substr(cdp_opsJson($facts), 0, 24000);
        $r = cdp_aiComplete($system, $prompt, null, null, null, 1500);
        if (empty($r['ok']) || trim((string) $r['text']) === '') {
            return ['ok' => true, 'source' => 'rules', 'text' => '', 'rules' => $rules, 'note' => 'The AI provider did not answer: ' . ($r['error'] ?? 'empty reply')];
        }
        cdp_opsX("INSERT INTO cdb_mgmt_insights (cache_key, body, source, created_by, created_at) VALUES (:k, :b, 'ai', :u, :n)",
            [':k' => $key, ':b' => (string) $r['text'], ':u' => cdp_opsUid() ?: null, ':n' => cdp_opsNow()]);
        return ['ok' => true, 'source' => 'ai', 'text' => (string) $r['text'], 'rules' => $rules, 'at' => cdp_opsNow(), 'cached' => false];
    }

    function cdp_mgmtLayout($userId)
    {
        $all = array_keys(cdp_mgmtWidgets());
        $r = cdp_mgmtEnsureTables() ? cdp_opsOne("SELECT layout FROM cdb_mgmt_layouts WHERE user_id = :u", [':u' => (int) $userId]) : null;
        $l = $r ? cdp_opsDecode($r->layout, []) : [];
        $order = array_values(array_intersect((array) ($l['order'] ?? []), $all));
        foreach ($all as $k) {
            if (!in_array($k, $order, true)) {
                $order[] = $k;
            }
        }
        return ['order' => $order, 'hidden' => array_values(array_intersect((array) ($l['hidden'] ?? []), $all))];
    }

    function cdp_mgmtSaveLayout($userId, array $order, array $hidden)
    {
        $all = array_keys(cdp_mgmtWidgets());
        $l = ['order' => array_values(array_intersect($order, $all)), 'hidden' => array_values(array_intersect($hidden, $all))];
        return cdp_opsX("INSERT INTO cdb_mgmt_layouts (user_id, layout, updated_at) VALUES (:u, :l, :n) ON DUPLICATE KEY UPDATE layout = VALUES(layout), updated_at = VALUES(updated_at)",
            [':u' => (int) $userId, ':l' => cdp_opsJson($l), ':n' => cdp_opsNow()])['ok'];
    }

    /** Table sections for exports (CSV / Excel / PDF / e-mail). */
    function cdp_mgmtReportSections(array $d, array $only = [])
    {
        $want = function ($k) use ($only) { return !$only || in_array($k, $only, true); };
        $m = !empty($d['money']);
        $sec = [];
        $s = $d['shipments'];
        $kpi = [['Shipments', $s['current']['n'], $s['previous']['n'], $s['change_pct']], ['Air', $s['current']['air'], $s['previous']['air'], cdp_mgmtPct($s['current']['air'], $s['previous']['air'])],
            ['Sea', $s['current']['sea'], $s['previous']['sea'], cdp_mgmtPct($s['current']['sea'], $s['previous']['sea'])], ['Customers shipping', $s['current']['customers'], $s['previous']['customers'], cdp_mgmtPct($s['current']['customers'], $s['previous']['customers'])],
            ['Warehouse packages received', $s['packages'], '', ''], ['Consolidations', $s['consolidations'], '', '']];
        if ($m && isset($d['revenue'])) {
            $kpi[] = ['Shipment charges', $d['revenue']['charges'], $d['revenue']['previous'], $d['revenue']['change_pct']];
            if (!empty($d['revenue']['ledger']['available'])) {
                $kpi[] = ['Billed (USD)', $d['revenue']['ledger']['billed'], '', ''];
                $kpi[] = ['Received (USD)', $d['revenue']['ledger']['received'], '', ''];
                $kpi[] = ['Outstanding (USD)', $d['revenue']['ledger']['outstanding'], '', ''];
            }
        }
        if (isset($d['delivery'])) {
            $kpi[] = ['Handed over', $d['delivery']['handed'], '', ''];
            $kpi[] = ['On-time handovers %', $d['delivery']['on_time_pct'], $d['delivery']['previous_on_time_pct'] ?? '', ''];
            $kpi[] = ['Average days to handover', $d['delivery']['avg_days'], '', ''];
        }
        if (isset($d['delayed'])) {
            $kpi[] = ['Delayed (open past target)', $d['delayed']['count'], '', ''];
        }
        if (isset($d['customers'])) {
            $kpi[] = ['New customers', $d['customers']['new'], '', ''];
            $kpi[] = ['Active customers (90 days)', $d['customers']['active'], '', ''];
            $kpi[] = ['Inactive customers', $d['customers']['inactive'], '', ''];
        }
        if ($want('executive') || $want('shipments')) {
            $sec['Executive summary'] = ['head' => ['Measure', 'This period', 'Previous period', 'Change %'], 'rows' => $kpi];
        }
        if ($want('growth') && isset($d['growth'])) {
            $sec['Monthly growth'] = ['head' => $m ? ['Month', 'Shipments', 'Growth %', 'Charges', 'Charges growth %', 'New customers'] : ['Month', 'Shipments', 'Growth %', 'New customers'],
                'rows' => array_map(function ($r) use ($m) { return $m ? [$r['month'], $r['shipments'], $r['growth_pct'], $r['revenue'], $r['revenue_growth_pct'], $r['new_customers']]
                    : [$r['month'], $r['shipments'], $r['growth_pct'], $r['new_customers']]; }, $d['growth'])];
        }
        if ($want('destinations') && isset($d['destinations'])) {
            $sec['By destination'] = ['head' => $m ? ['Country', 'Shipments', 'Charges', 'Margin'] : ['Country', 'Shipments'],
                'rows' => array_map(function ($r) use ($m) { $r = (array) $r; return $m ? [$r['country'], $r['n'], round((float) $r['revenue'], 2), round((float) $r['margin'], 2)] : [$r['country'], $r['n']]; }, $d['destinations'])];
        }
        if ($want('branches') && isset($d['branches'])) {
            $sec['Branches'] = ['head' => $m ? ['Branch', 'Shipments', 'Handed over', 'Delayed', 'Charges', 'Margin', 'Staff', 'Tasks done'] : ['Branch', 'Shipments', 'Handed over', 'Delayed', 'Staff', 'Tasks done'],
                'rows' => array_map(function ($r) use ($m) { return $m ? [$r['name'], $r['shipments'], $r['handed'], $r['delayed'], $r['revenue'], $r['margin'], $r['staff'], $r['tasks_done']]
                    : [$r['name'], $r['shipments'], $r['handed'], $r['delayed'], $r['staff'], $r['tasks_done']]; }, $d['branches'])];
        }
        if ($want('delayed') && isset($d['delayed'])) {
            $sec['Delayed shipments'] = ['head' => ['Tracking', 'Created', 'Customer', 'Status', 'Days late'],
                'rows' => array_map(function ($r) { return [$r->trk, $r->order_date, $r->customer, ucwords(str_replace('_', ' ', (string) $r->status)), $r->days_late]; }, $d['delayed']['rows'])];
        }
        if ($want('pending') && isset($d['pending'])) {
            $sec['Pending shipments (no movement)'] = ['head' => ['Tracking', 'Created', 'Customer', 'Status', 'Last movement', 'Idle days'],
                'rows' => array_map(function ($r) { return [$r->trk, $r->order_date, $r->customer, ucwords(str_replace('_', ' ', (string) $r->status)), $r->last_move, $r->idle_days]; }, $d['pending'])];
        }
        if ($m && $want('balances') && !empty($d['balances']['available'])) {
            $sec['Outstanding balances (GHS)'] = ['head' => ['Customer', 'Locker', 'Balance', 'Oldest bill', 'Bills'],
                'rows' => array_map(function ($r) { return [$r->customer, $r->locker, round((float) $r->balance, 2), $r->oldest, $r->bills]; }, $d['balances']['rows'])];
        }
        if ($want('employees') && isset($d['employees'])) {
            $sec['Employee productivity'] = ['head' => ['Name', 'Role', 'Branch', 'Shipments', 'Packages', 'Tasks done', 'On time %', 'Attendance %', 'Hours', 'Score'],
                'rows' => array_map(function ($r) { return [$r['name'], $r['job_role'], $r['branch'], $r['shipments'], $r['packages'], $r['tasks_done'], $r['on_time_pct'], $r['attendance_pct'], $r['hours'], $r['score']]; }, $d['employees'])];
        }
        if ($m && $want('costs') && isset($d['costs'])) {
            $sec['Shipping costs'] = ['head' => ['Mode', 'Shipments', 'Cost', 'Charges', 'Cost / shipment', 'Cost / kg', 'Cost % of charges', '% with cost recorded'],
                'rows' => array_map(function ($r) { return [$r['mode'], $r['shipments'], $r['cost'], $r['revenue'], $r['cost_per_shipment'], $r['cost_per_kg'], $r['cost_ratio_pct'], $r['costed_pct']]; }, $d['costs']['by_mode'])];
        }
        if ($m && $want('profit') && isset($d['profit'])) {
            $sec['Least profitable shipments'] = ['head' => ['Tracking', 'Date', 'Customer', 'Charges', 'Cost', 'Margin'],
                'rows' => array_map(function ($r) { return [$r->trk, $r->order_date, $r->customer, $r->revenue, $r->cost, round((float) $r->margin, 2)]; }, $d['profit']['worst'])];
        }
        if ($want('complaints') && isset($d['complaints'])) {
            $c = $d['complaints'];
            $sec['Customer complaints'] = ['head' => ['Measure', 'Value'], 'rows' => [['Tickets', $c['tickets']], ['Complaints', $c['complaints']], ['Open tickets', $c['open']],
                ['Average resolution (hours)', $c['avg_resolution_h']], ['Ticket satisfaction %', $c['csat']], ['Survey average (1-5)', $c['survey_avg']], ['Unhappy survey answers', $c['unhappy']]]];
        }
        if ($want('alerts') && isset($d['alerts'])) {
            $sec['Anomaly alerts'] = ['head' => ['Severity', 'Alert', 'Detail', 'Acknowledged'],
                'rows' => array_map(function ($a) { return [$a['severity'], $a['title'], $a['detail'], $a['ack'] ? $a['ack']['by'] . ' ' . $a['ack']['at'] : '']; }, $d['alerts'])];
        }
        return $sec;
    }

    // ---- Scheduled reports ------------------------------------------------

    /** Next run after $from for a schedule. Pure: unit tested. */
    function cdp_mgmtNextRun($frequency, $weekday, $monthday, $hour, $from)
    {
        $hour = max(0, min(23, (int) $hour));
        $from = (int) $from;
        $cand = strtotime(date('Y-m-d', $from) . sprintf(' %02d:00:00', $hour));
        for ($i = 0; $i < 400; $i++) {
            $ts = strtotime('+' . $i . ' day', $cand);
            if ($ts <= $from) {
                continue;
            }
            if ($frequency === 'daily') {
                return $ts;
            }
            if ($frequency === 'weekly' && (int) date('N', $ts) === max(1, min(7, (int) $weekday))) {
                return $ts;
            }
            if ($frequency === 'monthly' && (int) date('j', $ts) === min((int) date('t', $ts), max(1, min(31, (int) $monthday)))) {
                return $ts;
            }
        }
        return $from + 86400;
    }

    /** The period a scheduled report covers: yesterday / last 7 days / last calendar month. */
    function cdp_mgmtSchedulePeriod($frequency, $now = null)
    {
        $now = $now ?? time();
        if ($frequency === 'daily') {
            $d = date('Y-m-d', strtotime('-1 day', $now));
            return [$d, $d];
        }
        if ($frequency === 'monthly') {
            return [date('Y-m-01', strtotime('first day of last month', $now)), date('Y-m-t', strtotime('last day of last month', $now))];
        }
        return [date('Y-m-d', strtotime('-7 days', $now)), date('Y-m-d', strtotime('-1 day', $now))];
    }

    function cdp_mgmtSaveSchedule(array $in)
    {
        $name = trim((string) ($in['name'] ?? ''));
        $freq = in_array($in['frequency'] ?? '', ['daily', 'weekly', 'monthly'], true) ? $in['frequency'] : 'weekly';
        $format = in_array($in['format'] ?? '', ['pdf', 'xlsx', 'csv'], true) ? $in['format'] : 'pdf';
        $emails = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\s,;]+/', (string) ($in['recipients'] ?? ''))), function ($e) { return filter_var($e, FILTER_VALIDATE_EMAIL); })));
        $sections = array_values(array_intersect((array) ($in['sections'] ?? []), array_keys(cdp_mgmtWidgets())));
        if ($name === '' || !$emails || !$sections) {
            return ['ok' => false, 'error' => 'Name, at least one valid e-mail address and at least one section are required.'];
        }
        if (count($emails) > 25) {
            return ['ok' => false, 'error' => 'At most 25 recipients.'];
        }
        $next = date('Y-m-d H:i:s', cdp_mgmtNextRun($freq, (int) ($in['weekday'] ?? 1), (int) ($in['monthday'] ?? 1), (int) ($in['hour'] ?? 7), time()));
        $b = [':n' => mb_substr($name, 0, 120), ':f' => $freq, ':w' => max(1, min(7, (int) ($in['weekday'] ?? 1))), ':m' => max(1, min(31, (int) ($in['monthday'] ?? 1))),
            ':h' => max(0, min(23, (int) ($in['hour'] ?? 7))), ':fo' => $format, ':r' => implode(', ', $emails), ':s' => implode(',', $sections),
            ':b' => (int) ($in['branch_id'] ?? 0) ?: null, ':a' => !isset($in['active']) || !empty($in['active']) ? 1 : 0, ':nx' => $next];
        $id = (int) ($in['id'] ?? 0);
        if ($id) {
            $b[':id'] = $id;
            $r = cdp_opsX("UPDATE cdb_mgmt_schedules SET name = :n, frequency = :f, weekday = :w, monthday = :m, hour = :h, format = :fo, recipients = :r, sections = :s, branch_id = :b, active = :a, next_run_at = :nx WHERE id = :id", $b);
        } else {
            $b[':u'] = cdp_opsUid();
            $b[':c'] = cdp_opsNow();
            $r = cdp_opsX("INSERT INTO cdb_mgmt_schedules (name, frequency, weekday, monthday, hour, format, recipients, sections, branch_id, active, next_run_at, created_by, created_at)
                           VALUES (:n, :f, :w, :m, :h, :fo, :r, :s, :b, :a, :nx, :u, :c)", $b);
        }
        return ['ok' => $r['ok'], 'next_run_at' => $next];
    }

    /** Build one report file: [filename, mime, bytes]. */
    function cdp_mgmtBuildReport($from, $to, $branchId, array $sections, $format)
    {
        $f = cdp_mgmtFilters(['from' => $from, 'to' => $to, 'branch_id' => $branchId]);
        $d = cdp_mgmtData($f, $sections);
        $secs = cdp_mgmtReportSections($d, $sections);
        $ins = cdp_mgmtRuleInsights($d);
        if ($ins) {
            $secs = ['Observations' => ['head' => ['#', 'Observation'], 'rows' => array_map(function ($t, $i) { return [$i + 1, $t]; }, $ins, array_keys($ins))]] + $secs;
        }
        $title = 'Management report ' . $from . ' to ' . $to;
        $base = 'management-report-' . $from . '-to-' . $to;
        if ($format === 'xlsx') {
            $bytes = cdp_opsXlsx($secs);
            if ($bytes !== '') {
                return [$base . '.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $bytes, $secs];
            }
        }
        if ($format === 'pdf') {
            $bytes = cdp_opsPdf($title, $secs, 'Compared with ' . $f['prev_from'] . ' to ' . $f['prev_to']);
            if ($bytes !== '') {
                return [$base . '.pdf', 'application/pdf', $bytes, $secs];
            }
        }
        $csv = '';
        foreach ($secs as $name => $s) {
            $csv .= cdp_opsCsvCell($name) . "\r\n" . substr(cdp_opsCsv($s['head'], $s['rows']), 3) . "\r\n";
        }
        return [$base . '.csv', 'text/csv', "\xEF\xBB\xBF" . $csv, $secs];
    }

    function cdp_mgmtMailReport(array $to, $subject, $htmlBody, $file)
    {
        require_once __DIR__ . '/querys.php';
        require_once __DIR__ . '/message_log.php';
        require_once __DIR__ . '/phpmailer/class.phpmailer.php';
        require_once __DIR__ . '/phpmailer/class.smtp.php';
        $s = cdp_getSettingsCourier();
        try {
            $mail = new PHPMailer(true);
            if (($s->mailer ?? '') !== 'PHP') {
                $mail->isSMTP();
                $mail->Host = $s->smtp_host;
                $mail->SMTPAuth = true;
                $mail->Username = $s->smtp_user;
                $mail->Password = $s->smtp_password;
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;
                $mail->Timeout = 20;
            }
            $mail->setFrom($s->email_address, $s->smtp_names);
            foreach ($to as $addr) {
                $mail->addAddress($addr);
            }
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->addStringAttachment($file[2], $file[0], 'base64', $file[1]);
            $mail->send();
            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => mb_substr($e->getMessage(), 0, 250)];
        }
    }

    function cdp_mgmtRunSchedule($sch)
    {
        list($from, $to) = cdp_mgmtSchedulePeriod((string) $sch->frequency);
        $sections = array_filter(explode(',', (string) $sch->sections));
        $file = cdp_mgmtBuildReport($from, $to, (int) $sch->branch_id, $sections, (string) $sch->format);
        $f = cdp_mgmtFilters(['from' => $from, 'to' => $to, 'branch_id' => (int) $sch->branch_id]);
        $ins = cdp_mgmtRuleInsights(cdp_mgmtData($f, ['executive', 'delivery', 'delayed', 'balances', 'alerts']));
        $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#1f2937"><p>' . cdp_opsEsc($sch->name) . ' for ' . cdp_opsEsc($from) . ' to ' . cdp_opsEsc($to) . ' is attached.</p>'
            . ($ins ? '<ul>' . implode('', array_map(function ($t) { return '<li>' . cdp_opsEsc($t) . '</li>'; }, $ins)) . '</ul>' : '')
            . '<p style="color:#6b7280;font-size:12px">Sent automatically by the Gerald Master View. Change or stop this report under Master View › Scheduled reports.</p></div>';
        $res = cdp_mgmtMailReport(array_filter(array_map('trim', explode(',', (string) $sch->recipients))), $sch->name . ' (' . $from . ' to ' . $to . ')', $html, $file);
        cdp_opsX("UPDATE cdb_mgmt_schedules SET last_run_at = :n, last_status = :s, next_run_at = :nx WHERE id = :id",
            [':n' => cdp_opsNow(), ':s' => $res['ok'] ? 'Sent ' . $file[0] : 'Failed: ' . ($res['error'] ?? ''), ':id' => (int) $sch->id,
                ':nx' => date('Y-m-d H:i:s', cdp_mgmtNextRun($sch->frequency, $sch->weekday, $sch->monthday, $sch->hour, time()))]);
        return $res['ok'];
    }

    function cdp_mgmtRunSchedules()
    {
        if (!cdp_mgmtEnsureTables()) {
            return ['ok' => false];
        }
        $sent = 0;
        $failed = 0;
        foreach (cdp_opsQ("SELECT * FROM cdb_mgmt_schedules WHERE active = 1 AND next_run_at <= :n LIMIT 10", [':n' => cdp_opsNow()]) as $s) {
            cdp_mgmtRunSchedule($s) ? $sent++ : $failed++;
        }
        return ['ok' => true, 'sent' => $sent, 'failed' => $failed];
    }
}
