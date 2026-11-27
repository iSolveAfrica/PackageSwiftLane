<?php
/**
 * Pre-alert receipt: when a scanned parcel is keyed in from its pre-alert (Package Intake, New Shipment,
 * package from pre-alert), the pre-alert is closed (is_package = 1) and stamped with when, by whom and the
 * shipment / package it became (columns from migrations/2026_11_21_warehouse_handover_log.sql, added on first use).
 */

if (!function_exists('cdp_preAlertEnsureColumns')) {

    function cdp_preAlertEnsureColumns(): bool
    {
        static $ok = null;
        if ($ok !== null) return $ok;
        try {
            $db = new Conexion;
            $db->cdp_query("SELECT COUNT(*) AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_pre_alert' AND COLUMN_NAME = 'received_at'");
            $db->cdp_execute();
            if ((int) ($db->cdp_registro()->n ?? 0) === 0) {
                $db->cdp_query('ALTER TABLE cdb_pre_alert ADD COLUMN received_at DATETIME NULL, ADD COLUMN received_by INT NULL, ADD COLUMN received_module VARCHAR(4) NULL, ADD COLUMN received_order_id INT NULL');
                $db->cdp_execute();
            }
            $ok = true;
        } catch (Throwable $e) {
            error_log('pre-alert columns: ' . $e->getMessage());
            $ok = false;
        }
        return $ok;
    }

    /** Close a pre-alert as received and keyed in as $module (air|sea) order $orderId. Never throws. */
    function cdp_preAlertMarkReceived(int $preAlertId, string $module, int $orderId, int $uid, string $tracking = ''): void
    {
        if ($preAlertId <= 0) return;
        try {
            $db = new Conexion;
            if (cdp_preAlertEnsureColumns()) {
                $db->cdp_query('UPDATE cdb_pre_alert SET is_package = 1, received_at = :at, received_by = :u, received_module = :m, received_order_id = :o WHERE pre_alert_id = :id');
                $db->bind(':at', date('Y-m-d H:i:s')); $db->bind(':u', $uid); $db->bind(':m', $module === 'sea' ? 'sea' : 'air'); $db->bind(':o', $orderId);
            } else {
                $db->cdp_query('UPDATE cdb_pre_alert SET is_package = 1 WHERE pre_alert_id = :id');
            }
            $db->bind(':id', $preAlertId);
            $db->cdp_execute();
            if (!function_exists('cdp_activityLog')) @include_once __DIR__ . '/activity_log.php';
            if (function_exists('cdp_activityLog')) {
                cdp_activityLog(['module' => 'prealerts', 'action' => 'receive', 'action_label' => 'Pre-alert received', 'verb' => 'update', 'outcome' => 'ok',
                    'summary' => 'Pre-alert #' . $preAlertId . ' received and keyed in' . ($tracking !== '' ? ' as ' . $tracking : ''), 'entity_type' => 'prealert', 'entity_id' => $preAlertId, 'entity_label' => $tracking]);
            }
        } catch (Throwable $e) {
            error_log('pre-alert receive: ' . $e->getMessage());
        }
    }

    /** Receipt details of a pre-alert row (needs the received_* columns), or null when it is still open. */
    function cdp_preAlertReceipt($row): ?array
    {
        if (!$row || (int) ($row->is_package ?? 0) !== 1) return null;
        $out = ['at' => '', 'by' => '', 'module' => (string) ($row->received_module ?? ''), 'order_id' => (int) ($row->received_order_id ?? 0), 'track' => '', 'href' => ''];
        if (!empty($row->received_at)) $out['at'] = date('M j, Y H:i', strtotime($row->received_at));
        if (!empty($row->received_by) && function_exists('sl_row')) {
            $u = sl_row('SELECT fname, lname FROM cdb_users WHERE id = :id', [':id' => (int) $row->received_by]);
            if ($u) $out['by'] = trim($u->fname . ' ' . $u->lname);
        }
        if ($out['order_id'] && function_exists('sl_row')) {
            $sea = $out['module'] === 'sea';
            $o = sl_row('SELECT order_prefix, order_no FROM ' . ($sea ? 'cdb_customers_packages' : 'cdb_add_order') . ' WHERE order_id = :id', [':id' => $out['order_id']]);
            if ($o) $out['track'] = $o->order_prefix . $o->order_no;
            if (function_exists('sl_route')) $out['href'] = $sea ? sl_route('PackageDetail', ['id' => $out['order_id']]) : sl_route('ShipmentDetail', ['id' => $out['order_id']]);
        }
        return $out;
    }
}
