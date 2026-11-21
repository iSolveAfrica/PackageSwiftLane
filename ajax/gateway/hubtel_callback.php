<?php
// ============================================================================
// Hubtel payment callback receiver (PUBLIC — Hubtel calls this; no login).
// Hubtel POSTs the final status to the PrimaryCallbackUrl. We never trust the
// callback body alone — we re-verify authoritatively via the Status Check API
// before marking the cdb_fs_payments row confirmed. Reconciliation backstop;
// the counter flow already verifies synchronously on save. Inert until Hubtel
// credentials are set in cdb_settings.
//   Configure as your Hubtel PrimaryCallbackUrl: .../ajax/gateway/hubtel_callback.php
// ============================================================================

require_once(__DIR__ . '/../../loader.php');
require_once(__DIR__ . '/../../helpers/fs_gateways.php');
require_once(__DIR__ . '/../../helpers/fs_payments.php');

$raw  = file_get_contents('php://input');
$data = json_decode($raw);

// Hubtel nests under Data; be liberal about shape.
$ref = '';
$status = '';
if (is_object($data)) {
    $d = isset($data->Data) ? $data->Data : $data;
    $ref = (string) ($d->ClientReference ?? ($d->clientReference ?? ''));
    $status = (string) ($d->Status ?? ($d->status ?? ''));
}

if ($ref !== '' && function_exists('cdp_fsRecheckPayment')) {
    try {
        // Authoritative re-check. The callback body is UNTRUSTED (no signature)
        // — we NEVER treat its own "Status" as proof of payment.
        // Only Hubtel rows: cash/Paystack rows share the reference column (cash
        // stores an operator-typed reference), and a forged callback must not
        // be able to rewrite their status. cdp_fsRecheckPayment() leaves the
        // row untouched when Hubtel is unreachable/unconfigured, and applies a
        // real change through cdp_fsApplyPaymentStatus() so clearance, the
        // billing cache and the audit trail stay consistent.
        $db = new Conexion;
        $db->cdp_query("SELECT id FROM cdb_fs_payments WHERE reference = :r AND mode = 'hubtel'");
        $db->bind(':r', $ref);
        $db->cdp_execute();
        foreach ((array) $db->cdp_registros() as $p) {
            cdp_fsRecheckPayment((int) $p->id, null, 'hubtel_callback');
        }
    } catch (Throwable $e) {
        // swallow — always 200
    }
}

http_response_code(200);
echo 'ok';
