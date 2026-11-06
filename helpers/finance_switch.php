<?php
/**
 * Master switch for the financial module (Financial Sheet, Financial Overview,
 * Transactions, Accounts Receivable, global payments, the Accounts Receivable
 * reports and the customer's My Bills).
 *
 * Nothing is deleted when the module is off: the pages, endpoints, tables and
 * ledgers all stay, they are only hidden and refused. The switch is the
 * CDP_FINANCE_ENABLED constant in config/config.php when it is defined, else
 * the 'finance_enabled' row of cdb_sl_settings (the Billing hub toggle).
 *
 * While it is off:
 *  - every finance permission reads as "not granted", for every role including
 *    the superadmin, so menus, buttons, pages and AJAX endpoints that already
 *    check those permissions disappear or refuse on their own
 *    (User::cdp_hasPermission);
 *  - the dashboards hide their money panels (cdp_canViewMoney);
 *  - "cleared for delivery" — which only a recorded payment could set — no
 *    longer holds packages back: warehouse delivery, the deliver actions and
 *    the pickup-aging clock treat every package as cleared
 *    (cdp_fsClearedSql / cdp_fsIsCleared);
 *  - the Paystack webhook keeps running, so a payment already under way is
 *    still booked.
 */

if (!function_exists('cdp_financeEnabled')) {

    /**
     * Is the financial module on?
     *  - A CDP_FINANCE_ENABLED constant (config/config.php) always wins, so an
     *    environment can pin the module on or off regardless of the database.
     *  - Without the constant the switch lives in cdb_sl_settings
     *    ('finance_enabled', written by the Billing hub toggle). No row, no
     *    table or no database yet means OFF, exactly as before.
     * The answer is cached for the request: User::cdp_hasPermission() calls it
     * on every permission check.
     */
    function cdp_financeEnabled(): bool
    {
        static $cached = null;
        if (defined('CDP_FINANCE_ENABLED')) {
            return (bool) CDP_FINANCE_ENABLED;
        }
        if ($cached !== null) {
            return $cached;
        }
        $cached = false;
        if (class_exists('Conexion')) {
            try {
                $db = new Conexion();
                $db->cdp_query("SELECT setting_value FROM cdb_sl_settings WHERE setting_key = 'finance_enabled' LIMIT 1");
                $db->cdp_execute();
                $row = $db->cdp_registro();
                if ($row) {
                    $cached = in_array(strtolower(trim((string) $row->setting_value)), ['1', 'true', 'on', 'yes'], true);
                }
            } catch (Throwable $e) {
                $cached = false;
            }
        }
        return $cached;
    }

    /** True when config/config.php pins the switch, so the database setting is ignored. */
    function cdp_financePinnedByConfig(): bool
    {
        return defined('CDP_FINANCE_ENABLED');
    }

    /** Permission keys that belong to the financial module. */
    function cdp_financePermissions(): array
    {
        return [
            'financial_sheet',
            'fs_price_items',
            'fs_bill_customer',
            'fs_record_payment',
            'fs_apply_discount',
            'fs_clear_delivery',
            'fs_check_payment',
            'view_financial_overview',
            'view_transactions',
            'view_receivable_accounts',
            'view_global_payments',
            'view_my_bills',
            'pay_my_bills',
            'view_client_balance',
            'view_accounts_summary',
            'view_received_payments',
            'view_module_accounts_receivable_reports',
        ];
    }

    function cdp_isFinancePermission($permission): bool
    {
        return is_string($permission) && in_array($permission, cdp_financePermissions(), true);
    }

    /**
     * SQL condition for "this package may be handed over". With the module on it
     * is the payment clearance flag; with it off, every package qualifies.
     *
     * @param string $alias table alias of cdb_add_order ('' for none)
     */
    function cdp_fsClearedSql(string $alias = ''): string
    {
        if (!cdp_financeEnabled()) {
            return '1 = 1';
        }
        $col = ($alias !== '' ? $alias . '.' : '') . 'fs_cleared_for_delivery';
        return $col . ' = 1';
    }

    /**
     * SQL condition for the Warehouse Delivery queue (list cards + sidebar badge).
     * With the module on the queue is "paid and cleared". With it off, clearance
     * cannot happen, so the queue falls back to "physically here in Ghana":
     * Pending Collection (1), In Warehouse (4), Ready for PickUp (32) and
     * Sorting at Accra Office (33) — otherwise every in-transit package would
     * flood it.
     */
    function cdp_wdQueueSql(string $alias = ''): string
    {
        $p = $alias !== '' ? $alias . '.' : '';
        if (!cdp_financeEnabled()) {
            return $p . 'status_courier IN (1, 4, 32, 33)';
        }
        return $p . 'fs_cleared_for_delivery = 1';
    }

    /** PHP-side twin of cdp_fsClearedSql() for a fetched row value. */
    function cdp_fsIsCleared($flag): bool
    {
        return !cdp_financeEnabled() || (int) $flag === 1;
    }

    /**
     * Stop a finance page when the module is off: the visitor lands on their
     * dashboard instead of an empty or half-working screen.
     */
    function cdp_financeGuardPage(string $home = 'index.php'): void
    {
        if (cdp_financeEnabled()) {
            return;
        }
        if (!headers_sent()) {
            header('Location: ' . $home);
        }
        exit;
    }

    /** Same guard for AJAX endpoints: a JSON refusal. */
    function cdp_financeGuardAjax(): void
    {
        if (cdp_financeEnabled()) {
            return;
        }
        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['ok' => false, 'success' => false, 'error' => 'finance_disabled', 'message' => 'The financial module is currently switched off.']);
        exit;
    }
}
