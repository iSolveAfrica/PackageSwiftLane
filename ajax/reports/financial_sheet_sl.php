<?php
// ============================================================================
// Financial Sheet — JSON read models for the Swift Lane design page
// (views/sl/pages/FinancialSheet.php). Included by financial_sheet_ajax.php
// after its shared helpers; read-only, gated by 'financial_sheet' through the
// endpoint's default permission. Every write still goes through the original
// actions (save_item, bill_customer, record_payment, ...). Money figures are
// computed with the same helpers the legacy HTML renderers use.
// ============================================================================

if (!defined('FS_SL_INCLUDED')) {
    define('FS_SL_INCLUDED', 1);
}

/** Text-only date for the timelines. */
function fs_sl_when($v)
{
    $t = strtotime((string) $v);
    return $t ? date('Y-m-d H:i', $t) : '';
}

// ---------------------------------------------------------------------------
// sl_list: consolidations (number, date, dangerous goods); stats via list_stats.
// ---------------------------------------------------------------------------
if ($action === 'sl_list') {
    header('Content-Type: application/json; charset=UTF-8');
    $q = trim((string) ($_REQUEST['q'] ?? ''));
    $where = '';
    if ($q !== '') {
        $where = " WHERE CONCAT(COALESCE(c.c_prefix,''), COALESCE(c.c_no,'')) LIKE :q OR CAST(c.consolidate_id AS CHAR) LIKE :q ";
    }
    $db->cdp_query("SELECT c.consolidate_id, c.c_prefix, c.c_no, c.c_date, c.is_dangerous_good
                    FROM cdb_consolidate c $where ORDER BY c.consolidate_id DESC LIMIT 200");
    if ($q !== '') {
        $db->bind(':q', '%' . $q . '%');
    }
    $db->cdp_execute();
    $out = [];
    foreach ((array) $db->cdp_registros() as $c) {
        $out[] = [
            'id'   => (int) $c->consolidate_id,
            'no'   => (string) ($c->c_prefix . $c->c_no),
            'date' => (string) $c->c_date,
            'dg'   => (int) $c->is_dangerous_good === 1,
        ];
    }
    echo json_encode(['ok' => true, 'items' => $out]);
    exit;
}

// ---------------------------------------------------------------------------
// sl_find: consolidations holding a package / a customer (search boxes).
// ---------------------------------------------------------------------------
if ($action === 'sl_find') {
    header('Content-Type: application/json; charset=UTF-8');
    $q = trim((string) ($_REQUEST['q'] ?? ''));
    $by = ($_REQUEST['by'] ?? 'package') === 'customer' ? 'customer' : 'package';
    if ($q === '') {
        echo json_encode(['ok' => true, 'items' => []]);
        exit;
    }
    if ($by === 'package') {
        $tn = 0;
        try {
            $db->cdp_query("SELECT COUNT(*) AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_add_order' AND COLUMN_NAME = 'tracking_num'");
            $db->cdp_execute();
            $tn = (int) ($db->cdp_registro()->n ?? 0);
        } catch (Throwable $e) {
        }
        $db->cdp_query("SELECT DISTINCT c.consolidate_id, c.c_prefix, c.c_no, c.c_date,
                               CONCAT(d.order_prefix, d.order_no) AS hit, a.sender_id AS sid
                        FROM cdb_consolidate_detail d
                        INNER JOIN cdb_consolidate c ON c.consolidate_id = d.consolidate_id
                        LEFT JOIN cdb_add_order a ON a.order_prefix = d.order_prefix AND a.order_no = d.order_no
                        LEFT JOIN cdb_package_tracking_number pt ON pt.order_id = a.order_id
                        WHERE CONCAT(d.order_prefix, d.order_no) LIKE :q " . ($tn ? 'OR a.tracking_num LIKE :q' : '') . " OR pt.tracking_number LIKE :q
                        ORDER BY c.consolidate_id DESC LIMIT 30");
    } else {
        $words = array_values(array_filter(preg_split('/\s+/', $q)));
        $conds = [];
        foreach ($words as $i => $w) {
            $conds[] = "(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')) LIKE :w$i OR u.locker LIKE :w$i)";
        }
        $db->cdp_query("SELECT DISTINCT c.consolidate_id, c.c_prefix, c.c_no, c.c_date,
                               TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''),' ',COALESCE(u.locker,''))) AS hit, u.id AS sid
                        FROM cdb_consolidate_detail d
                        INNER JOIN cdb_consolidate c ON c.consolidate_id = d.consolidate_id
                        INNER JOIN cdb_add_order a ON a.order_prefix = d.order_prefix AND a.order_no = d.order_no
                        INNER JOIN cdb_users u ON u.id = a.sender_id
                        WHERE " . implode(' AND ', $conds) . "
                        ORDER BY c.consolidate_id DESC LIMIT 30");
        foreach ($words as $i => $w) {
            $db->bind(":w$i", '%' . $w . '%');
        }
    }
    if ($by === 'package') {
        $db->bind(':q', '%' . $q . '%');
    }
    try {
        $db->cdp_execute();
        $rows = (array) $db->cdp_registros();
    } catch (Throwable $e) {
        $rows = [];
    }
    $out = [];
    foreach ($rows as $r) {
        $out[] = ['id' => (int) $r->consolidate_id, 'no' => (string) ($r->c_prefix . $r->c_no), 'date' => (string) $r->c_date, 'hit' => (string) $r->hit, 'sid' => (int) $r->sid];
    }
    echo json_encode(['ok' => true, 'items' => $out]);
    exit;
}

// ---------------------------------------------------------------------------
// sl_sheet: one consolidation -> customers (money state) -> packages + billing log.
// ---------------------------------------------------------------------------
if ($action === 'sl_sheet') {
    header('Content-Type: application/json; charset=UTF-8');
    $cid = (int) ($_REQUEST['consolidate_id'] ?? 0);
    $db->cdp_query("SELECT consolidate_id, c_prefix, c_no, c_date, is_dangerous_good FROM cdb_consolidate WHERE consolidate_id = :cid LIMIT 1");
    $db->bind(':cid', $cid);
    $db->cdp_execute();
    $c = $db->cdp_registro();
    if (!$c) {
        echo json_encode(['ok' => false, 'message' => 'Consolidation not found.']);
        exit;
    }
    $groups  = fs_customer_groups($cid);
    $allOids = [];
    foreach ($groups as $g) {
        foreach ($g['oids'] as $o) {
            $allOids[] = (int) $o;
        }
    }
    $stats   = fs_item_stats($allOids);
    $billing = fs_billing_map($cid);
    $state   = [];
    if ($allOids) {
        $db->cdp_query("SELECT order_id, fs_cleared_for_delivery, status_courier FROM cdb_add_order WHERE order_id IN (" . implode(',', array_unique($allOids)) . ")");
        $db->cdp_execute();
        foreach ((array) $db->cdp_registros() as $r) {
            $state[(int) $r->order_id] = $r;
        }
    }
    $canCheck = $user->cdp_hasPermission('fs_check_payment');
    $customers = [];
    foreach ($groups as $g) {
        $sid = (int) $g['sender_id'];
        $pkgCount = count($g['oids']);
        $pkgPriced = 0;
        $packages = [];
        $clearedCt = 0;
        $deliveredCt = 0;
        $pcIds = [];
        foreach ($g['rows'] as $p) {
            $oid = (int) $p->oid;
            $s = $stats[$oid] ?? null;
            if (!$s || $s['priced'] >= $s['items']) {
                $pkgPriced++;
            }
            $st = $state[$oid] ?? null;
            $status = $st ? $st->status_courier : 0;
            $delivered = function_exists('cdp_isHandedOver') ? cdp_isHandedOver($status) : in_array((int) $status, [8, 15], true);
            $cleared = $st && (int) $st->fs_cleared_for_delivery === 1;
            if ($cleared) { $clearedCt++; }
            if ($st && in_array((int) $status, [8, 15], true)) { $deliveredCt++; }
            $w = (float) ($p->pkg_weight ?? 0);
            if ($w <= 0) { $w = (float) $p->detail_weight; }
            $packages[] = [
                'oid'       => $oid,
                'no'        => (string) ($p->order_prefix . $p->order_no),
                'carrier'   => (string) ($p->carrier_tracking ?? ''),
                'weight'    => round($w, 2),
                'usd'       => round((float) $p->total_order, 2),
                'items'     => $s ? (int) $s['items'] : 0,
                'priced'    => $s ? (int) $s['priced'] : 0,
                'cleared'   => $cleared,
                'delivered' => $delivered,
                'handover'  => ($delivered && function_exists('cdp_handoverLabel')) ? (string) cdp_handoverLabel($status) : '',
            ];
        }
        $b = $billing[$sid] ?? null;
        $billGhs = 0.0;
        if ($b) {
            $billGhs = ($b->amount_ghs !== null) ? (float) $b->amount_ghs : (float) cdp_customerPayableGhs((float) $b->amount_usd, true)['total'];
        }
        $discGhs = $b ? (float) $b->discount_ghs : 0.0;
        $paidGhs = ($b && $b->paid_ghs !== null) ? (float) $b->paid_ghs : 0.0;
        $netGhs = round(max(0, $billGhs - $discGhs), 2);
        $owing = round(max(0, $netGhs - $paidGhs), 2);

        // Billing log: bill, payments, failed attempts, discounts, notes (newest first).
        $log = [];
        if ($b) {
            $feeGhs = ($b->handling_ghs !== null) ? (float) $b->handling_ghs : 0.0;
            $log[] = ['ts' => strtotime((string) $b->billed_at), 'icon' => 'i-receipt', 'who' => (string) $b->biller,
                'text' => 'billed this customer ₵' . number_format($billGhs, 2) . ' ($' . number_format((float) $b->amount_usd, 2)
                    . ($feeGhs > 0 ? ' + ₵' . number_format($feeGhs, 2) . ' handling fee' : '') . ')', 'when' => fs_sl_when($b->billed_at)];
            try {
                $db->cdp_query("SELECT p.id, p.amount_ghs, p.refunded_ghs, p.mode, p.reference, p.gateway_status, p.gateway_channel,
                                       p.gateway_response, p.reversed_at, p.reversal_reason, p.cleared_for_delivery, p.recorded_at,
                                       COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))),''), u.username, CONCAT('User ', p.recorded_by)) AS payer
                                FROM cdb_fs_payments p LEFT JOIN cdb_users u ON u.id = p.recorded_by
                                WHERE p.consolidate_id = :cid AND p.sender_id = :sid ORDER BY p.id DESC");
                $db->bind(':cid', $cid);
                $db->bind(':sid', $sid);
                $db->cdp_execute();
                foreach ((array) $db->cdp_registros() as $pp) {
                    $isCash = strtolower((string) $pp->mode) === 'cash';
                    $meta = cdp_fsStatusMeta((string) $pp->gateway_status, (string) $pp->mode);
                    $facts = [];
                    if (!$isCash && (string) $pp->gateway_channel !== '') { $facts[] = 'via ' . str_replace('_', ' ', (string) $pp->gateway_channel); }
                    if (!$isCash && (string) $pp->gateway_response !== '') { $facts[] = (string) $pp->gateway_response; }
                    if ((float) $pp->refunded_ghs > 0) { $facts[] = '₵' . number_format((float) $pp->refunded_ghs, 2) . ' refunded'; }
                    if ($pp->reversed_at !== null) { $facts[] = 'Reversed ' . fs_sl_when($pp->reversed_at) . ((string) $pp->reversal_reason !== '' ? ' — ' . $pp->reversal_reason : ''); }
                    if ((int) $pp->cleared_for_delivery === 1) { $facts[] = $meta['money'] ? 'cleared for delivery' : 'clearance revoked'; }
                    $log[] = ['ts' => strtotime((string) $pp->recorded_at), 'icon' => 'i-dollar', 'who' => (string) $pp->payer,
                        'text' => 'recorded a ' . ucfirst((string) $pp->mode) . ' payment of ₵' . number_format((float) $pp->amount_ghs, 2),
                        'status' => (string) $meta['label'], 'statusOk' => (bool) $meta['money'], 'ref' => (string) $pp->reference,
                        'facts' => implode(' · ', $facts), 'when' => fs_sl_when($pp->recorded_at), 'payment_id' => (int) $pp->id,
                        'check' => !$isCash && (string) $pp->reference !== '' && $canCheck];
                }
            } catch (Throwable $e) {
            }
            try {
                $db->cdp_query("SELECT reference, amount_ghs, mode, status, fail_reason, created_at FROM cdb_fs_payment_intents
                                WHERE consolidate_id = :cid AND sender_id = :sid AND status <> 'success' ORDER BY id DESC");
                $db->bind(':cid', $cid);
                $db->bind(':sid', $sid);
                $db->cdp_execute();
                foreach ((array) $db->cdp_registros() as $t) {
                    $lbl = ['pending' => 'Not completed', 'processing' => 'Being confirmed', 'failed' => 'Failed'][(string) $t->status] ?? ucfirst((string) $t->status);
                    $log[] = ['ts' => strtotime((string) $t->created_at), 'icon' => 'i-alert', 'who' => 'Customer',
                        'text' => 'tried to pay ' . ucfirst((string) $t->mode) . ' ₵' . number_format((float) $t->amount_ghs, 2),
                        'status' => $lbl, 'statusOk' => false, 'ref' => (string) $t->reference,
                        'facts' => 'No money was taken for this attempt.' . ((string) $t->fail_reason !== '' ? ' ' . $t->fail_reason : ''),
                        'when' => fs_sl_when($t->created_at), 'intent' => (string) $t->reference];
                }
            } catch (Throwable $e) {
            }
            try {
                $db->cdp_query("SELECT d.amount_ghs, d.reason, d.applied_at,
                                       COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))),''), u.username, CONCAT('User ', d.applied_by)) AS author
                                FROM cdb_fs_discounts d LEFT JOIN cdb_users u ON u.id = d.applied_by
                                WHERE d.consolidate_id = :cid AND d.sender_id = :sid ORDER BY d.id DESC");
                $db->bind(':cid', $cid);
                $db->bind(':sid', $sid);
                $db->cdp_execute();
                foreach ((array) $db->cdp_registros() as $dd) {
                    $log[] = ['ts' => strtotime((string) $dd->applied_at), 'icon' => 'i-tag', 'who' => (string) $dd->author,
                        'text' => 'applied a ₵' . number_format((float) $dd->amount_ghs, 2) . ' discount' . ((string) $dd->reason !== '' ? ' (' . $dd->reason . ')' : ''),
                        'when' => fs_sl_when($dd->applied_at)];
                }
            } catch (Throwable $e) {
            }
            try {
                $db->cdp_query("SELECT n.note, n.created_at,
                                       COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))),''), u.username, CONCAT('User ', n.created_by)) AS author
                                FROM cdb_consolidate_billing_notes n LEFT JOIN cdb_users u ON u.id = n.created_by
                                WHERE n.consolidate_id = :cid AND n.sender_id = :sid ORDER BY n.id DESC LIMIT 10");
                $db->bind(':cid', $cid);
                $db->bind(':sid', $sid);
                $db->cdp_execute();
                foreach ((array) $db->cdp_registros() as $n) {
                    $log[] = ['ts' => strtotime((string) $n->created_at), 'icon' => 'i-note', 'who' => (string) $n->author,
                        'text' => 'noted: “' . $n->note . '”', 'when' => fs_sl_when($n->created_at)];
                }
            } catch (Throwable $e) {
            }
            usort($log, function ($x, $y) { return $y['ts'] <=> $x['ts']; });
        }
        $customers[] = [
            'sid'          => $sid,
            'name'         => (string) $g['name'],
            'locker'       => (string) $g['locker'],
            'label'        => (string) $g['label'],
            'usd'          => round((float) $g['total'], 2),
            'weight'       => round((float) $g['weight'], 2),
            'pkgs'         => $pkgCount,
            'pkgs_priced'  => $pkgPriced,
            'can_bill'     => $pkgCount > 0 && $pkgPriced >= $pkgCount,
            'billed'       => (bool) $b,
            'bill_ghs'     => round($billGhs, 2),
            'disc_ghs'     => round($discGhs, 2),
            'net_ghs'      => $netGhs,
            'paid_ghs'     => round($paidGhs, 2),
            'has_paid'     => $b && $b->paid_ghs !== null,
            'owing_ghs'    => $owing,
            'billed_by'    => $b ? (string) $b->biller : '',
            'billed_at'    => $b ? fs_sl_when($b->billed_at) : '',
            'all_cleared'  => $pkgCount > 0 && $clearedCt >= $pkgCount,
            'all_delivered' => $pkgCount > 0 && $deliveredCt >= $pkgCount,
            'orders'       => $g['orders'],
            'packages'     => $packages,
            'log'          => $log,
        ];
    }
    $rateLog = null;
    try {
        $db->cdp_query("SELECT l.changed_at, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))),''), u.username) AS uname
                        FROM cdb_exchange_rate_log l LEFT JOIN cdb_users u ON u.id = l.changed_by ORDER BY l.id DESC LIMIT 1");
        $db->cdp_execute();
        $rl = $db->cdp_registro();
        if ($rl) { $rateLog = ['by' => (string) $rl->uname, 'at' => fs_sl_when($rl->changed_at)]; }
    } catch (Throwable $e) {
    }
    echo json_encode([
        'ok'        => true,
        'consol'    => ['id' => $cid, 'no' => (string) ($c->c_prefix . $c->c_no), 'date' => (string) $c->c_date, 'dg' => (int) $c->is_dangerous_good === 1],
        'summary'   => fs_consol_summary($cid),
        'rate'      => fs_live_rate(),
        'rate_log'  => $rateLog,
        'customers' => $customers,
        'perms'     => [
            'price'    => $user->cdp_hasPermission('fs_price_items'),
            'bill'     => $user->cdp_hasPermission('fs_bill_customer'),
            'pay'      => $user->cdp_hasPermission('fs_record_payment'),
            'discount' => $user->cdp_hasPermission('fs_apply_discount'),
            'clear'    => $user->cdp_hasPermission('fs_clear_delivery'),
            'check'    => $canCheck,
        ],
    ]);
    exit;
}

// ---------------------------------------------------------------------------
// sl_items: items of one package (groups + singles) under the edit lock, plus
// the package change log. Same lock rule as the legacy 'items' action.
// ---------------------------------------------------------------------------
if ($action === 'sl_items') {
    header('Content-Type: application/json; charset=UTF-8');
    $order_id = (int) ($_REQUEST['order_id'] ?? 0);
    $db->cdp_query("SELECT * FROM cdb_add_order_item WHERE order_id = :oid ORDER BY order_item_id ASC");
    $db->bind(':oid', $order_id);
    $db->cdp_execute();
    $items = (array) $db->cdp_registros();
    $lock = $items ? cdp_fsAcquireLock($order_id, $uid, $uname) : ['ok' => true];
    $groups = [];
    $singles = [];
    foreach ($items as $it) {
        $mode = ($it->custom_price !== null) ? 'custom' : 'weight';
        if ($it->custom_price !== null) {
            $val = (float) $it->custom_price;
        } else {
            $w = (float) $it->order_item_weight;
            $val = ($w > 0 || !empty($it->priced_at)) ? $w : '';
        }
        $row = ['iid' => (int) $it->order_item_id, 'qty' => (int) $it->order_item_quantity, 'desc' => (string) $it->order_item_description,
            'mode' => $mode, 'value' => $val, 'priced' => !empty($it->priced_at)];
        $gk = trim((string) ($it->order_item_weight_group ?? ''));
        if ($gk !== '') {
            if (!isset($groups[$gk])) {
                $groups[$gk] = ['token' => $gk, 'mode' => $mode, 'value' => $val, 'members' => []];
            }
            $groups[$gk]['members'][] = $row;
        } else {
            $singles[] = $row;
        }
    }
    $db->cdp_query("SELECT order_prefix, order_no FROM cdb_add_order WHERE order_id = :oid LIMIT 1");
    $db->bind(':oid', $order_id);
    $db->cdp_execute();
    $o = $db->cdp_registro();
    $hist = [];
    if ($o) {
        $db->cdp_query("SELECT h.action, h.date_history,
                               COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))),''), u.username, CONCAT('User ', h.user_id)) AS uname
                        FROM cdb_order_user_history h LEFT JOIN cdb_users u ON u.id = h.user_id
                        WHERE h.order_track = :track AND h.order_id = :oid AND h.action LIKE 'Financial Sheet%'
                        ORDER BY h.history_id DESC LIMIT 12");
        $db->bind(':track', $o->order_prefix . $o->order_no);
        $db->bind(':oid', $order_id);
        $db->cdp_execute();
        foreach ((array) $db->cdp_registros() as $hh) {
            $hist[] = ['who' => (string) $hh->uname, 'what' => preg_replace('/^Financial Sheet\s*[—-]\s*/u', '', (string) $hh->action), 'when' => fs_sl_when($hh->date_history)];
        }
    }
    echo json_encode(['ok' => true, 'editable' => (bool) $lock['ok'], 'locked_by' => $lock['by'] ?? '', 'groups' => array_values($groups), 'singles' => $singles, 'history' => $hist]);
    exit;
}
