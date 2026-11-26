<?php
/**
 * Pre-alert lifecycle (cdb_pre_alert, migration 2026_11_27_prealerts_automations).
 *
 *   is_package 0 = open (expected), 1 = arrived / linked to a registered package, 2 = closed (did not arrive)
 *
 * - cdp_preAlertLink():   a registered package (sea: cdb_customers_packages, air: cdb_add_order) takes over
 *                         the pre-alert; the package number is stored on the pre-alert (package_ref).
 * - cdp_preAlertClose():  closed by staff or by the "Close overdue pre-alerts" automation (30 days late).
 * - cdp_preAlertPackagesByTracking(): registered packages carrying the same carrier tracking number.
 */
require_once __DIR__ . '/sl_view.php';

if (!function_exists('cdp_preAlertReady')) {
    /** The lifecycle columns exist (migration applied). */
    function cdp_preAlertReady(): bool
    {
        static $ok = null;
        if ($ok === null) {
            $ok = (int) sl_scalar("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cdb_pre_alert' AND COLUMN_NAME IN ('package_ref','closed_at','updated_at')") === 3;
        }
        return $ok;
    }

    /** Tracking numbers are compared without spaces and case. */
    function cdp_preAlertNormTracking(string $t): string
    {
        return strtoupper(preg_replace('/\s+/', '', $t));
    }

    /** Who may edit: the owner while it is open, or staff with prealert_edit. */
    function cdp_preAlertCanEdit($user, $row): bool
    {
        if (!$row) return false;
        if ($user->cdp_hasPermission('prealert_edit')) return true;
        return sl_userIs('client') && (int) $row->customer_id === (int) $_SESSION['userid'] && (int) $row->is_package === 0;
    }

    function cdp_preAlertGet(int $id)
    {
        return $id > 0 ? sl_row('SELECT p.*, c.name_com FROM cdb_pre_alert p LEFT JOIN cdb_courier_com c ON c.id = p.courier_com WHERE p.pre_alert_id = :id', [':id' => $id]) : null;
    }

    /**
     * Registered packages (sea + air) with this carrier tracking number.
     * @return array<int,array{module:string,id:int,ref:string,customer_id:int,status:int,href:string}>
     */
    function cdp_preAlertPackagesByTracking(string $tracking): array
    {
        $t = cdp_preAlertNormTracking($tracking);
        if ($t === '') return [];
        $out = [];
        foreach (sl_rows("SELECT order_id, order_prefix, order_no, sender_id, status_courier FROM cdb_customers_packages WHERE REPLACE(UPPER(tracking_purchase), ' ', '') = :t ORDER BY order_id DESC LIMIT 5", [':t' => $t]) as $r) {
            $out[] = ['module' => 'package', 'id' => (int) $r->order_id, 'ref' => $r->order_prefix . $r->order_no, 'customer_id' => (int) $r->sender_id, 'status' => (int) $r->status_courier, 'href' => 'customer_packages_view.php?id=' . (int) $r->order_id];
        }
        foreach (sl_rows("SELECT order_id, order_prefix, order_no, sender_id, status_courier FROM cdb_add_order WHERE is_deleted = 0 AND REPLACE(UPPER(tracking_purchase), ' ', '') = :t ORDER BY order_id DESC LIMIT 5", [':t' => $t]) as $r) {
            $out[] = ['module' => 'air', 'id' => (int) $r->order_id, 'ref' => $r->order_prefix . $r->order_no, 'customer_id' => (int) $r->sender_id, 'status' => (int) $r->status_courier, 'href' => 'courier_view.php?id=' . (int) $r->order_id];
        }
        return $out;
    }

    /**
     * Link a pre-alert to a registered package: marks it arrived and stores the package number.
     * $fill: copy the package's own details (customer, store, value) over the pre-alert so both match.
     */
    function cdp_preAlertLink(int $pid, string $module, int $orderId, bool $fill = false): bool
    {
        $module = $module === 'air' ? 'air' : 'package';
        $tbl = $module === 'air' ? 'cdb_add_order' : 'cdb_customers_packages';
        $pkg = sl_row("SELECT order_id, order_prefix, order_no, sender_id, provider_purchase, price_purchase, tracking_purchase FROM $tbl WHERE order_id = :id", [':id' => $orderId]);
        if (!$pkg || $pid <= 0) return false;
        $db = new Conexion;
        if (!cdp_preAlertReady()) {
            $db->cdp_query('UPDATE cdb_pre_alert SET is_package = 1 WHERE pre_alert_id = :id');
            $db->bind(':id', $pid);
            return (bool) $db->cdp_execute();
        }
        $set = 'is_package = 1, package_module = :m, package_id = :o, package_ref = :r, linked_at = NOW(), closed_at = NULL, closed_reason = NULL, updated_at = NOW(), updated_by = :u';
        if ($fill) {
            $set .= ', customer_id = :c'
                . (trim((string) $pkg->provider_purchase) !== '' ? ', provider_shop = :shop' : '')
                . ((float) $pkg->price_purchase > 0 ? ', purchase_price = :price' : '');
        }
        $db->cdp_query("UPDATE cdb_pre_alert SET $set WHERE pre_alert_id = :id");
        $db->bind(':m', $module); $db->bind(':o', (int) $pkg->order_id); $db->bind(':r', $pkg->order_prefix . $pkg->order_no);
        $db->bind(':u', (int) ($_SESSION['userid'] ?? 0)); $db->bind(':id', $pid);
        if ($fill) {
            $db->bind(':c', (int) $pkg->sender_id);
            if (trim((string) $pkg->provider_purchase) !== '') $db->bind(':shop', (string) $pkg->provider_purchase);
            if ((float) $pkg->price_purchase > 0) $db->bind(':price', (float) $pkg->price_purchase);
        }
        $ok = (bool) $db->cdp_execute();
        // the package is flagged as coming from a pre-alert, and takes the store / value the customer gave when it has none
        $pa = cdp_preAlertGet($pid);
        if ($ok && $pa) {
            $db->cdp_query("UPDATE $tbl SET is_prealert = 1"
                . (trim((string) $pkg->provider_purchase) === '' && trim((string) $pa->provider_shop) !== '' ? ', provider_purchase = :shop' : '')
                . ((float) $pkg->price_purchase <= 0 && (float) $pa->purchase_price > 0 ? ', price_purchase = :price' : '')
                . ' WHERE order_id = :id');
            if (trim((string) $pkg->provider_purchase) === '' && trim((string) $pa->provider_shop) !== '') $db->bind(':shop', (string) $pa->provider_shop);
            if ((float) $pkg->price_purchase <= 0 && (float) $pa->purchase_price > 0) $db->bind(':price', (float) $pa->purchase_price);
            $db->bind(':id', (int) $pkg->order_id);
            try { $db->cdp_execute(); } catch (Throwable $e) { /* is_prealert may not exist on old air tables */ }
        }
        return $ok;
    }

    /** After a package is created with ?prealert_id: link it with the new package number. */
    function cdp_preAlertLinkCreated(int $pid, string $module, int $orderId): void
    {
        if ($pid > 0 && $orderId > 0) {
            try { cdp_preAlertLink($pid, $module, $orderId, false); } catch (Throwable $e) { error_log('[prealert link] ' . $e->getMessage()); }
        }
    }

    /** Close an open pre-alert (did not arrive). Returns false when it was not open. */
    function cdp_preAlertClose(int $pid, string $reason, ?int $by = null): bool
    {
        $db = new Conexion;
        if (cdp_preAlertReady()) {
            $db->cdp_query('UPDATE cdb_pre_alert SET is_package = 2, closed_at = NOW(), closed_reason = :r, updated_at = NOW(), updated_by = :u WHERE pre_alert_id = :id AND is_package = 0');
            $db->bind(':r', mb_substr($reason, 0, 200)); $db->bind(':u', (int) ($by ?? ($_SESSION['userid'] ?? 0)));
        } else {
            $db->cdp_query('UPDATE cdb_pre_alert SET is_package = 2 WHERE pre_alert_id = :id AND is_package = 0');
        }
        $db->bind(':id', $pid);
        $db->cdp_execute();
        return $db->cdp_rowCount() > 0;
    }

    function cdp_preAlertReopen(int $pid): bool
    {
        $db = new Conexion;
        $db->cdp_query('UPDATE cdb_pre_alert SET is_package = 0' . (cdp_preAlertReady() ? ', closed_at = NULL, closed_reason = NULL, package_module = NULL, package_id = NULL, package_ref = NULL, linked_at = NULL, updated_at = NOW()' : '') . ' WHERE pre_alert_id = :id');
        $db->bind(':id', $pid);
        return (bool) $db->cdp_execute();
    }

    /** Status of a row for lists: [label, pill class]. */
    function cdp_preAlertStatus($r): array
    {
        $st = (int) $r->is_package;
        if ($st === 1) return ['Arrived', 'delivered'];
        if ($st === 2) return ['Closed · not arrived', 'cancelled'];
        $late = $r->estimated_date && strtotime((string) $r->estimated_date) < strtotime('today');
        return $late ? ['Overdue', 'overdue'] : ['Expected', 'pending'];
    }
}
