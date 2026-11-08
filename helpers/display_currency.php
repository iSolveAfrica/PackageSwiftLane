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
    $c = $_SESSION['sl_ccy'] ?? ($_COOKIE['sl_ccy'] ?? 'USD');
    return $c === 'GHS' ? 'GHS' : 'USD';
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
    return sl_displayCurrency() === 'GHS' && sl_exchangeRateValue() > 0;
}

/** Convert a stored USD amount to the display currency. */
function sl_displayAmount($usd): float
{
    return sl_showGhs() ? (float) $usd * sl_exchangeRateValue() : (float) $usd;
}
