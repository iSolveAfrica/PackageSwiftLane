<?php
/**
 * Warehouse hand-out log: one row per package handed over at the warehouse counter.
 *
 * Table cdb_warehouse_handovers (migrations/2026_11_21_warehouse_handover_log.sql, also created on first use):
 *   module air|sea, order_id, tracking, sender_id, status_courier (15 Picked up / 8 Delivered), handed_by (staff id),
 *   code_method  code    = the customer's pickup code was checked
 *                skipped = a manager handed over without the code (skip_reason says why)
 *                legacy  = backfilled from the tracking history (before this log existed)
 *   paid (1 when the package was paid at hand-over), weight, amount, agency, source (handout | warehouse_delivery | backfill), created_at.
 * Read by views/sl/data/handover_log.php (Hand-out Log page).
 */

if (!function_exists('cdp_handoverLogEnsure')) {

    /** Create the table on first use and backfill past warehouse hand-overs once. */
    function cdp_handoverLogEnsure(): bool
    {
        static $ok = null;
        if ($ok !== null) return $ok;
        try {
            $db = new Conexion;
            $db->cdp_query("SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_warehouse_handovers'");
            $db->cdp_execute();
            $exists = (int) ($db->cdp_registro()->n ?? 0) > 0;
            if (!$exists) {
                $db->cdp_query("CREATE TABLE IF NOT EXISTS cdb_warehouse_handovers (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    module VARCHAR(4) NOT NULL, order_id INT NOT NULL, tracking VARCHAR(80) NOT NULL DEFAULT '',
                    sender_id INT NOT NULL DEFAULT 0, status_courier INT NOT NULL DEFAULT 15, handed_by INT NOT NULL DEFAULT 0,
                    code_method VARCHAR(12) NOT NULL DEFAULT 'code', skip_reason VARCHAR(255) NULL,
                    paid TINYINT(1) NOT NULL DEFAULT 0, weight DECIMAL(12,2) NOT NULL DEFAULT 0, amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                    agency INT NOT NULL DEFAULT 0, source VARCHAR(24) NOT NULL DEFAULT 'handout', created_at DATETIME NOT NULL,
                    KEY idx_wh_created (created_at), KEY idx_wh_by (handed_by, created_at), KEY idx_wh_pkg (module, order_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $db->cdp_execute();
                cdp_handoverLogBackfill();
            }
            $ok = true;
        } catch (Throwable $e) {
            error_log('handover log: ' . $e->getMessage());
            $ok = false;
        }
        return $ok;
    }

    /** Copy past warehouse hand-overs (tracking rows written by the counter or Warehouse Delivery) into the log. */
    function cdp_handoverLogBackfill(): void
    {
        $db = new Conexion;
        $warehouse = "(t.comments LIKE '%(Warehouse)%' OR t.comments LIKE '%via Warehouse Delivery%')";
        $db->cdp_query("INSERT INTO cdb_warehouse_handovers (module, order_id, tracking, sender_id, status_courier, handed_by, code_method, paid, weight, amount, agency, source, created_at)
            SELECT 'air', a.order_id, t.order_track, a.sender_id, t.status_courier, COALESCE(t.user_id, 0), 'legacy', a.status_invoice IN (1,19), COALESCE(a.total_weight, 0), COALESCE(a.total_order, 0), COALESCE(a.agency, 0), 'backfill', t.t_date
            FROM cdb_courier_track t JOIN cdb_add_order a ON CONCAT(a.order_prefix, a.order_no) = t.order_track
            WHERE t.status_courier IN (8,15) AND $warehouse");
        $db->cdp_execute();
        $db->cdp_query("INSERT INTO cdb_warehouse_handovers (module, order_id, tracking, sender_id, status_courier, handed_by, code_method, paid, weight, amount, agency, source, created_at)
            SELECT 'sea', p.order_id, t.order_track, p.sender_id, t.status_courier, COALESCE(t.user_id, 0), 'legacy', p.status_invoice IN (1,19), COALESCE(p.total_weight, 0), COALESCE(p.total_order, 0), COALESCE(p.agency, 0), 'backfill', t.t_date
            FROM cdb_courier_track t JOIN cdb_customers_packages p ON CONCAT(p.order_prefix, p.order_no) = t.order_track
            LEFT JOIN cdb_add_order a ON CONCAT(a.order_prefix, a.order_no) = t.order_track
            WHERE a.order_id IS NULL AND t.status_courier IN (8,15) AND $warehouse");
        $db->cdp_execute();
    }

    /**
     * Log one handed-over package. $row is the package row (air cdb_add_order / sea cdb_customers_packages).
     * $method: code | skipped. Never throws: the hand-over itself has already happened.
     */
    function cdp_handoverLogAdd(string $module, $row, int $status, int $uid, string $method = 'code', string $reason = '', string $source = 'handout'): void
    {
        if (!$row || !cdp_handoverLogEnsure()) return;
        try {
            $db = new Conexion;
            $db->cdp_query('INSERT INTO cdb_warehouse_handovers (module, order_id, tracking, sender_id, status_courier, handed_by, code_method, skip_reason, paid, weight, amount, agency, source, created_at)
                VALUES (:m, :o, :t, :s, :st, :by, :cm, :r, :pd, :w, :a, :ag, :src, :at)');
            $db->bind(':m', $module === 'sea' ? 'sea' : 'air');
            $db->bind(':o', (int) $row->order_id);
            $db->bind(':t', (string) ($row->order_prefix ?? '') . (string) ($row->order_no ?? ''));
            $db->bind(':s', (int) ($row->sender_id ?? 0));
            $db->bind(':st', $status);
            $db->bind(':by', $uid);
            $db->bind(':cm', in_array($method, ['code', 'skipped'], true) ? $method : 'code');
            $db->bind(':r', $reason !== '' ? mb_substr($reason, 0, 255) : null);
            $db->bind(':pd', in_array((int) ($row->status_invoice ?? 0), [1, 19], true) ? 1 : 0);
            $db->bind(':w', (float) ($row->total_weight ?? 0));
            $db->bind(':a', (float) ($row->total_order ?? 0));
            $db->bind(':ag', (int) ($row->agency ?? 0));
            $db->bind(':src', $source);
            $db->bind(':at', date('Y-m-d H:i:s'));
            $db->cdp_execute();
        } catch (Throwable $e) {
            error_log('handover log add: ' . $e->getMessage());
        }
    }

    /**
     * Who may hand over without the customer's pickup code: super admin, admin, or a role with
     * the warehouse_skip_pickup_code permission (warehouse managers).
     */
    function cdp_handoverCanSkipCode($user): bool
    {
        if (!is_object($user)) return false;
        $lvl = (int) ($_SESSION['userlevel'] ?? ($user->userlevel ?? 0));
        if (in_array($lvl, [2, 9], true)) return true;
        require_once __DIR__ . '/rbac.php';
        if (function_exists('cdp_roleHasFlag') && (cdp_roleHasFlag($lvl, 'is_superadmin') || cdp_roleHasFlag($lvl, 'is_admin'))) return true;
        return method_exists($user, 'cdp_hasPermission') && $user->cdp_hasPermission('warehouse_skip_pickup_code');
    }
}
