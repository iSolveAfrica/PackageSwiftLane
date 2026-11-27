<?php
/**
 * Setup problems that silently break a customer or rider flow, said plainly where an admin will see them
 * (Settings, web and phone). Each: [key, title, what goes wrong, link to fix, link label].
 */
require_once __DIR__ . '/sl_view.php';

/** True when at least one way to pay is switched on (API connections: Cash, PayPal, Stripe, Paystack, Wire…). */
function sl_setupHasPaymentMethod(): bool
{
    return sl_tableExists('cdb_met_payment') && (int) sl_scalar('SELECT COUNT(*) FROM cdb_met_payment WHERE is_active = 1') > 0;
}

function sl_setupChecks($core): array
{
    $out = [];
    if (sl_tableExists('cdb_met_payment') && !sl_setupHasPaymentMethod()) {
        $out[] = ['payment', 'No payment method is switched on', 'Customers cannot book a shipment until at least one way to pay (cash, card, mobile money or transfer) is on.', sl_route('ApiConnections'), 'Switch one on'];
    }
    if (is_file(__DIR__ . '/delivery_service.php')) {
        require_once __DIR__ . '/delivery_service.php';
        $dl = function_exists('dl_config') ? dl_config() : [];
        $site = strtoupper((string) ($core->currency ?? ''));
        if (!empty($dl['enabled']) && $site !== '' && strtoupper((string) ($dl['currency'] ?? $site)) !== $site && (float) ($core->exchange_rate ?? 0) <= 0) {
            $out[] = ['rate', 'Set the exchange rate', 'Door delivery is priced in ' . $dl['currency'] . ' but the books are in ' . $site . '. Without a rate, door-delivery earnings are left out of rider totals and reports.', sl_route('SettingsHub') . '#language', 'Set the rate'];
        }
    }
    return $out;
}
