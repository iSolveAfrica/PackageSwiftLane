<?php
/**
 * Display currency switch (USD / GHS).
 *
 * Amounts are stored in USD. When a signed-in user switches the sidebar
 * toggle to GHS, every amount printed through sl_money(), cdb_money_format()
 * or cdb_money_format_bar() is converted with cdb_settings.exchange_rate and
 * shown with the cedi sign. Stored values, form inputs and saves are not
 * changed. The choice is kept per user in the session and a cookie.
 */

function sl_displayCurrency(): string
{
    if (!isset($_SESSION['sl_ccy'])) {
        // First request of a session: load this staff member's saved choice.
        $_SESSION['sl_ccy'] = sl_loadDisplayCurrency((int) ($_SESSION['userid'] ?? 0));
    }
    return $_SESSION['sl_ccy'] === 'GHS' ? 'GHS' : 'USD';
}

/** Saved choice for one user (cdb_user_preferences, key display_currency). */
function sl_loadDisplayCurrency(int $uid): string
{
    if ($uid <= 0) return 'USD';
    try {
        $db = new Conexion();
        $db->cdp_query("SELECT pref_value FROM cdb_user_preferences WHERE user_id = :u AND pref_key = 'display_currency' LIMIT 1");
        $db->bind(':u', $uid);
        $row = $db->cdp_registro();
        return ($row && $row->pref_value === 'GHS') ? 'GHS' : 'USD';
    } catch (Throwable $e) {
        return 'USD';
    }
}

/** Store the choice on the user's account so it follows them to any device. */
function sl_saveDisplayCurrency(int $uid, string $ccy): void
{
    $_SESSION['sl_ccy'] = $ccy;
    if ($uid <= 0) return;
    try {
        $db = new Conexion();
        $db->cdp_query("INSERT INTO cdb_user_preferences (user_id, pref_key, pref_value, updated_at) VALUES (:u, 'display_currency', :v, NOW()) ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value), updated_at = NOW()");
        $db->bind(':u', $uid);
        $db->bind(':v', $ccy);
        $db->cdp_execute();
    } catch (Throwable $e) {
        // Session still holds the choice.
    }
}

function sl_exchangeRateValue(): float
{
    static $rate = null;
    if ($rate === null) {
        $rate = 0.0;
        try {
            $db = new Conexion();
            $db->cdp_query('SELECT exchange_rate FROM cdb_settings LIMIT 1');
            $row = $db->cdp_registro();
            $rate = $row ? (float) $row->exchange_rate : 0.0;
        } catch (Throwable $e) {
            $rate = 0.0;
        }
    }
    return $rate;
}

/** True when amounts should be shown in GHS (switch on and a rate is set). */
function sl_showGhs(): bool
{
    if (function_exists('sl_userIs') && sl_userIs('client')) return false;
    return sl_displayCurrency() === 'GHS' && sl_exchangeRateValue() > 0;
}

/** Convert a stored USD amount to the display currency. */
function sl_displayAmount($usd): float
{
    return sl_showGhs() ? (float) $usd * sl_exchangeRateValue() : (float) $usd;
}
