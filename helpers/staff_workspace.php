<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************

/**
 * Role workspaces: what each job role needs on one screen.
 *
 *   warehouse operator  receiving today, packages waiting, incoming transfers
 *   finance officer     balances, payments today, money approvals
 *   dispatch officer    delivery bookings, out for delivery, transfers to send
 *   customer service    WhatsApp chats waiting, open tickets, failed messages
 *   branch manager      the branch today: on shift, overdue tasks, approvals
 *   driver              my deliveries today and bookings; "I am arriving"
 *   partner agent       a link to the partner portal
 *
 * Every person also gets their shift (check in / out), tasks, announcements
 * and their approval requests. Panels are plain data ({title, kpis, table,
 * links, action}); dataJs/workspace.js draws them all the same way, so a
 * new role is one more case below.
 */

require_once __DIR__ . '/staffops.php';

if (!function_exists('cdp_staffWorkspace')) {

    function cdp_wsTable(array $cols, array $rows)
    {
        return ['cols' => $cols, 'rows' => $rows];
    }

    function cdp_wsTrackCol($a = 'o')
    {
        return "CONCAT(COALESCE($a.order_prefix,''), $a.order_no)";
    }

    function cdp_staffWorkspace()
    {
        $uid = cdp_opsUid();
        $role = cdp_staffJobRole();
        $branches = cdp_staffBranchIds();
        $today = date('Y-m-d');
        $p = cdp_staffProfile($uid);
        $out = [
            'me' => ['name' => cdp_opsUserName($uid), 'role' => $role, 'role_label' => cdp_staffJobRoles()[$role] ?? 'Staff', 'branches' => $branches,
                'branch_names' => array_map(function ($b) { return $b->name; }, $branches ? cdp_opsQ("SELECT name FROM cdb_staff_branches WHERE id IN (" . implode(',', array_map('intval', $branches)) . ")") : []),
                'has_profile' => (bool) $p],
            'shift' => cdp_opsOne("SELECT s.*, b.name branch_name FROM cdb_staff_shifts s LEFT JOIN cdb_staff_branches b ON b.id = s.branch_id
                                   WHERE s.user_id = :u AND (s.shift_date = :d OR s.status = 'checked_in') AND s.status IN ('scheduled','checked_in','completed') ORDER BY s.status = 'checked_in' DESC, s.start_time LIMIT 1",
                [':u' => $uid, ':d' => $today]),
            'week' => cdp_staffShifts($today, date('Y-m-d', strtotime('+6 days')), 0, $uid),
            'tasks' => cdp_staffTasks(['mine' => true, 'status' => 'active']),
            'announcements' => array_slice(cdp_staffAnnouncementsFor($uid), 0, 10),
            'requests' => cdp_staffApprovals('', true),
            'to_decide' => array_values(array_filter(cdp_staffApprovals('pending'), function ($a) { return !empty($a->can_decide); })),
            'panels' => [],
        ];
        $roleFn = 'cdp_ws_' . $role;
        if (function_exists($roleFn)) {
            $out['panels'] = $roleFn($uid, $branches);
        }
        return $out;
    }

    /** Office / agency scope SQL on cdb_add_order o for a set of staff branches. */
    function cdp_wsBranchScope(array $branches, $a = 'o')
    {
        if (!$branches) {
            return '';
        }
        $parts = [];
        foreach (cdp_opsQ("SELECT office_id, branch_office_id FROM cdb_staff_branches WHERE id IN (" . implode(',', array_map('intval', $branches)) . ")") as $b) {
            if ($b->office_id) {
                $parts[] = "$a.origin_off = " . (int) $b->office_id;
            }
            if ($b->branch_office_id) {
                $parts[] = "$a.agency = " . (int) $b->branch_office_id;
            }
        }
        return $parts ? ' AND (' . implode(' OR ', $parts) . ')' : '';
    }

    function cdp_ws_warehouse_operator($uid, array $branches)
    {
        $today = date('Y-m-d');
        $incoming = $branches ? cdp_opsQ("SELECT t.id, t.ref, fb.name from_name, t.dispatched_at, (SELECT COUNT(*) FROM cdb_staff_transfer_items i WHERE i.transfer_id = t.id) items
                                           FROM cdb_staff_transfers t LEFT JOIN cdb_staff_branches fb ON fb.id = t.from_branch_id
                                           WHERE t.status = 'in_transit' AND t.to_branch_id IN (" . implode(',', array_map('intval', $branches)) . ") ORDER BY t.dispatched_at LIMIT 20") : [];
        return [[
            'title' => 'Receiving',
            'kpis' => [
                ['label' => 'I received today', 'value' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_customers_packages WHERE user_id = :u AND DATE(order_datetime) = :d", [':u' => $uid, ':d' => $today])],
                ['label' => 'Received today (all)', 'value' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_customers_packages WHERE DATE(order_datetime) = :d", [':d' => $today])],
                ['label' => 'In warehouse', 'value' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_add_order WHERE status_courier = 4")],
                ['label' => 'Ready for pickup', 'value' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_add_order WHERE status_courier IN (32, 6)")],
            ],
            'links' => [['href' => 'package_intake.php', 'label' => 'Package intake'], ['href' => 'customer_packages_add.php', 'label' => 'Register a package'], ['href' => 'warehouse.php', 'label' => 'Warehouse']],
        ], [
            'title' => 'Transfers arriving at my branch',
            'table' => cdp_wsTable([['ref', 'Transfer'], ['from_name', 'From'], ['items', 'Items'], ['dispatched_at', 'Sent']], $incoming),
            'empty' => 'Nothing on its way to your branch.',
            'links' => [['href' => 'staff_hub.php#transfers', 'label' => 'Receive a transfer']],
        ]];
    }

    function cdp_ws_finance_officer($uid, array $branches)
    {
        $panels = [];
        $k = [['label' => 'Approvals for me', 'value' => count(array_filter(cdp_staffApprovals('pending'), function ($a) { return !empty($a->can_decide); }))]];
        if (cdp_opsTableExists('cdb_consolidate_customer_billing') && function_exists('cdp_canViewMoney') && (cdp_opsRank() >= 4 || cdp_canViewMoney($GLOBALS['user'] ?? null, 'reports'))) {
            $bal = "GREATEST(0, COALESCE(amount_ghs,0)-COALESCE(discount_ghs,0)-COALESCE(paid_ghs,0))";
            $k[] = ['label' => 'Outstanding (GHS)', 'value' => number_format((float) cdp_opsVal("SELECT SUM($bal) FROM cdb_consolidate_customer_billing"), 2)];
            $k[] = ['label' => 'Customers owing', 'value' => (int) cdp_opsVal("SELECT COUNT(DISTINCT sender_id) FROM cdb_consolidate_customer_billing WHERE $bal > 0.009")];
            if (cdp_opsTableExists('cdb_fs_payments')) {
                $k[] = ['label' => 'Payments today', 'value' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_fs_payments WHERE DATE(recorded_at) = CURDATE()")];
            }
            $top = cdp_opsQ("SELECT TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) customer, u.locker, ROUND(SUM(GREATEST(0, COALESCE(b.amount_ghs,0)-COALESCE(b.discount_ghs,0)-COALESCE(b.paid_ghs,0))),2) balance
                             FROM cdb_consolidate_customer_billing b LEFT JOIN cdb_users u ON u.id = b.sender_id GROUP BY b.sender_id, customer, u.locker HAVING balance > 0 ORDER BY balance DESC LIMIT 8");
            $panels[] = ['title' => 'Largest balances (GHS)', 'table' => cdp_wsTable([['customer', 'Customer'], ['locker', 'Locker'], ['balance', 'Balance']], $top), 'empty' => 'Nobody owes anything.'];
        }
        array_unshift($panels, ['title' => 'Finance today', 'kpis' => $k, 'links' => [['href' => 'financial_sheet.php', 'label' => 'Financial sheet'],
            ['href' => 'accounts_receivable.php', 'label' => 'Accounts receivable'], ['href' => 'staff_hub.php#approvals', 'label' => 'Approvals']]]);
        return $panels;
    }

    function cdp_ws_dispatch_officer($uid, array $branches)
    {
        $panels = [];
        $scope = cdp_wsBranchScope($branches);
        $out = cdp_opsQ("SELECT " . cdp_wsTrackCol() . " trk, TRIM(CONCAT(COALESCE(d.fname,''),' ',COALESCE(d.lname,''))) driver, TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) customer
                         FROM cdb_add_order o LEFT JOIN cdb_users d ON d.id = o.driver_id LEFT JOIN cdb_users u ON u.id = o.sender_id WHERE o.status_courier = 7 $scope ORDER BY o.order_id DESC LIMIT 15");
        $panels[] = ['title' => 'Out for delivery', 'kpis' => [['label' => 'On route now', 'value' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_add_order o WHERE o.status_courier = 7 $scope")],
            ['label' => 'Without a driver', 'value' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_add_order o WHERE o.status_courier = 7 AND COALESCE(o.driver_id,0) = 0 $scope")]],
            'table' => cdp_wsTable([['trk', 'Tracking'], ['customer', 'Customer'], ['driver', 'Driver']], $out), 'empty' => 'Nothing on route.',
            'links' => [['href' => 'delivery_dispatch.php', 'label' => 'Delivery requests'], ['href' => 'rider_dispatch.php', 'label' => 'Dispatch board']]];
        if (cdp_opsTableExists('cdb_comm_delivery_slots')) {
            $slots = cdp_opsQ("SELECT s.entity_ref, s.slot_date, s.slot_window, s.address_note, TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) customer
                               FROM cdb_comm_delivery_slots s LEFT JOIN cdb_users u ON u.id = s.user_id WHERE s.status = 'booked' AND s.slot_date >= CURDATE() ORDER BY s.slot_date, s.slot_window LIMIT 15");
            $panels[] = ['title' => 'Deliveries customers booked', 'table' => cdp_wsTable([['slot_date', 'Day'], ['slot_window', 'Window'], ['entity_ref', 'Reference'], ['customer', 'Customer'], ['address_note', 'Notes']], $slots),
                'empty' => 'No bookings ahead.', 'links' => [['href' => 'comms_center.php#deliveries', 'label' => 'All bookings']]];
        }
        $send = $branches ? cdp_opsQ("SELECT t.ref, tb.name to_name, (SELECT COUNT(*) FROM cdb_staff_transfer_items i WHERE i.transfer_id = t.id) items FROM cdb_staff_transfers t
                                      LEFT JOIN cdb_staff_branches tb ON tb.id = t.to_branch_id WHERE t.status = 'approved' AND t.from_branch_id IN (" . implode(',', array_map('intval', $branches)) . ") LIMIT 15") : [];
        $panels[] = ['title' => 'Transfers to send', 'table' => cdp_wsTable([['ref', 'Transfer'], ['to_name', 'To'], ['items', 'Items']], $send), 'empty' => 'No approved transfers waiting.',
            'links' => [['href' => 'staff_hub.php#transfers', 'label' => 'Transfers']]];
        return $panels;
    }

    function cdp_ws_customer_service($uid, array $branches)
    {
        $k = [];
        $links = [];
        if (cdp_opsTableExists('cdb_comm_threads')) {
            $k[] = ['label' => 'WhatsApp chats waiting', 'value' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_comm_threads WHERE status = 'open'")];
            $k[] = ['label' => 'Assigned to me', 'value' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_comm_threads WHERE status <> 'closed' AND assignee_id = :u", [':u' => $uid])];
            $links[] = ['href' => 'comms_inbox.php', 'label' => 'WhatsApp Inbox'];
        }
        if (cdp_opsTableExists('cdb_cs_tickets')) {
            $k[] = ['label' => 'Open tickets', 'value' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_cs_tickets WHERE disposition = 'none' AND merged_into IS NULL AND status NOT IN ('resolved','closed')")];
            $k[] = ['label' => 'My tickets', 'value' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_cs_tickets WHERE assignee_id = :u AND status NOT IN ('resolved','closed')", [':u' => $uid])];
            $links[] = ['href' => 'support_inbox.php', 'label' => 'Support desk'];
        }
        if (cdp_opsTableExists('cdb_message_log')) {
            $k[] = ['label' => 'Messages failed (24 h)', 'value' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_message_log WHERE status = 'failed' AND created_at >= :c", [':c' => cdp_opsNow(-86400)])];
            $links[] = ['href' => 'message_logs.php', 'label' => 'Message logs'];
        }
        $links[] = ['href' => 'comms_center.php#customers', 'label' => 'Customer history'];
        return [['title' => 'Customers waiting', 'kpis' => $k, 'links' => $links]];
    }

    function cdp_ws_branch_manager($uid, array $branches)
    {
        if (!$branches) {
            return [['title' => 'My branch', 'kpis' => [], 'note' => 'You are not assigned to a branch yet. An administrator sets this under Staff & Branches.']];
        }
        $in = implode(',', array_map('intval', $branches));
        $scope = cdp_wsBranchScope($branches);
        $onShift = cdp_opsQ("SELECT TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) name, s.start_time, s.end_time, s.status FROM cdb_staff_shifts s JOIN cdb_users u ON u.id = s.user_id
                             WHERE s.branch_id IN ($in) AND s.shift_date = CURDATE() AND s.status <> 'cancelled' ORDER BY s.start_time");
        foreach ($onShift as $s) {
            $s->hours = substr($s->start_time, 0, 5) . '-' . substr($s->end_time, 0, 5);
            $s->status = str_replace('_', ' ', $s->status);
        }
        return [[
            'title' => 'My branch today',
            'kpis' => [
                ['label' => 'Shipments today', 'value' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_add_order o WHERE o.order_date = CURDATE() AND o.status_courier <> 21 $scope")],
                ['label' => 'Checked in', 'value' => count(array_filter($onShift, function ($s) { return $s->status === 'checked in'; })) . ' / ' . count($onShift)],
                ['label' => 'Overdue tasks', 'value' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_staff_tasks WHERE branch_id IN ($in) AND status IN ('open','in_progress') AND due_at < NOW()")],
                ['label' => 'Open transfers', 'value' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_staff_transfers WHERE (from_branch_id IN ($in) OR to_branch_id IN ($in)) AND status IN ('requested','approved','in_transit')")],
            ],
            'links' => [['href' => 'staff_hub.php#shifts', 'label' => 'Shifts'], ['href' => 'staff_hub.php#tasks', 'label' => 'Tasks'], ['href' => 'staff_hub.php#performance', 'label' => 'Performance'], ['href' => 'master_view.php', 'label' => 'Master View']],
        ], [
            'title' => 'On shift today',
            'table' => cdp_wsTable([['name', 'Staff'], ['hours', 'Hours'], ['status', 'Status']], $onShift),
            'empty' => 'No shifts planned today.',
        ]];
    }

    function cdp_ws_driver($uid, array $branches)
    {
        $mine = cdp_opsQ("SELECT " . cdp_wsTrackCol() . " trk, TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) customer, u.phone, s.mod_style status
                          FROM cdb_add_order o LEFT JOIN cdb_users u ON u.id = o.sender_id LEFT JOIN cdb_styles s ON s.id = o.status_courier
                          WHERE o.driver_id = :u AND o.status_courier NOT IN (8, 15, 21) ORDER BY o.status_courier = 7 DESC, o.order_id DESC LIMIT 30", [':u' => $uid]);
        foreach ($mine as $m) {
            $m->status = ucwords(str_replace('_', ' ', (string) $m->status));
        }
        $panels = [[
            'title' => 'My deliveries',
            'kpis' => [['label' => 'Assigned to me', 'value' => count($mine)],
                ['label' => 'Delivered today', 'value' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_courier_track t JOIN cdb_add_order o ON " . cdp_wsTrackCol() . " = t.order_track WHERE o.driver_id = :u AND t.status_courier = 8 AND DATE(t.t_date) = CURDATE()", [':u' => $uid])]],
            'table' => cdp_wsTable([['trk', 'Tracking'], ['customer', 'Customer'], ['phone', 'Phone'], ['status', 'Status']], $mine),
            'empty' => 'No deliveries assigned to you.',
            'action' => 'driver_arriving',
            'links' => [['href' => 'driver_app.php', 'label' => 'Rider app']],
        ]];
        if (cdp_opsTableExists('cdb_comm_delivery_slots')) {
            $slots = cdp_opsQ("SELECT s.entity_ref, s.slot_date, s.slot_window, s.phone, s.address_note FROM cdb_comm_delivery_slots s WHERE s.driver_id = :u AND s.status = 'booked' ORDER BY s.slot_date LIMIT 20", [':u' => $uid]);
            $panels[] = ['title' => 'Booked time slots', 'table' => cdp_wsTable([['slot_date', 'Day'], ['slot_window', 'Window'], ['entity_ref', 'Reference'], ['phone', 'Phone'], ['address_note', 'Notes']], $slots), 'empty' => 'No booked slots assigned to you.'];
        }
        return $panels;
    }

    function cdp_ws_agent($uid, array $branches)
    {
        return [['title' => 'Partner portal', 'kpis' => [], 'note' => 'Your shipments, commissions and statements are in the partner portal.', 'links' => [['href' => 'agent_portal.php', 'label' => 'Open the partner portal']]]];
    }

    function cdp_ws_administrator($uid, array $branches)
    {
        return array_merge(cdp_ws_customer_service($uid, $branches), [['title' => 'Organisation', 'kpis' => [
            ['label' => 'Branches', 'value' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_staff_branches WHERE active = 1")],
            ['label' => 'Staff with a profile', 'value' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_staff_profiles WHERE status = 'active'")],
            ['label' => 'Approvals pending', 'value' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_staff_approvals WHERE status = 'pending'")],
            ['label' => 'Checked in now', 'value' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_staff_shifts WHERE status = 'checked_in'")],
        ], 'links' => [['href' => 'staff_hub.php', 'label' => 'Staff & Branches'], ['href' => 'master_view.php', 'label' => 'Gerald Master View']]]]);
    }

    // ---------------------------------------------------------------------
    // Partner agent portal
    // ---------------------------------------------------------------------

    function cdp_staffAgentPortal($agentId)
    {
        $agencies = cdp_staffAgentAgencyIds((int) $agentId);
        $where = $agencies ? 'o.agency IN (' . implode(',', array_map('intval', $agencies)) . ')' : '1=0';
        $ships = cdp_opsQ("SELECT " . cdp_wsTrackCol() . " trk, o.order_date, s.mod_style status, s.color, TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) customer,
                             COALESCE(o.total_order,0) total
                           FROM cdb_add_order o LEFT JOIN cdb_styles s ON s.id = o.status_courier LEFT JOIN cdb_users u ON u.id = o.sender_id
                           WHERE $where ORDER BY o.order_id DESC LIMIT 50");
        foreach ($ships as $s) {
            $s->status = ucwords(str_replace('_', ' ', (string) $s->status));
        }
        $month = date('Y-m-01');
        $rule = cdp_staffCommissionRule((int) $agentId);
        return [
            'agent' => ['id' => (int) $agentId, 'name' => cdp_opsUserName($agentId), 'linked' => (bool) $agencies],
            'kpis' => [
                ['label' => 'Shipments this month', 'value' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_add_order o WHERE $where AND o.order_date >= :m AND o.status_courier <> 21", [':m' => $month])],
                ['label' => 'Open shipments', 'value' => (int) cdp_opsVal("SELECT COUNT(*) FROM cdb_add_order o WHERE $where AND o.status_courier NOT IN (8, 15, 21)")],
                ['label' => 'Commission pending', 'value' => number_format((float) cdp_opsVal("SELECT SUM(amount) FROM cdb_staff_commissions WHERE agent_id = :a AND status IN ('pending','approved')", [':a' => (int) $agentId]), 2)],
                ['label' => 'Commission paid (all time)', 'value' => number_format((float) cdp_opsVal("SELECT SUM(amount) FROM cdb_staff_commissions WHERE agent_id = :a AND status = 'paid'", [':a' => (int) $agentId]), 2)],
            ],
            'rule' => $rule['calc'] === 'flat' ? 'A fixed ' . number_format($rule['value'], 2) . ' per handed-over shipment' : number_format($rule['value'], 2) . '% of the charges of each handed-over shipment',
            'shipments' => $ships,
            'statements' => cdp_staffCommissionSummary((int) $agentId),
            'announcements' => array_slice(cdp_staffAnnouncementsFor((int) $agentId), 0, 5),
        ];
    }
}
