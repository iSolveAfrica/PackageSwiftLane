<?php
/** Package Intake (PackageIntake.dc.html): register a package by carrier tracking number. */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/views/sl/data/ops_common.php';

/** Carrier guessed from the tracking number format (UPS, FedEx, USPS, DHL, Amazon). */
function sl_intakeCarrierGuess(string $t): string
{
    $t = strtoupper(preg_replace('/\s+/', '', $t));
    if (preg_match('/^1Z[0-9A-Z]{16}$/', $t)) return 'UPS';
    if (preg_match('/^TBA\d{9,}$/', $t)) return 'Amazon';
    if (preg_match('/^(94|93|92|95)\d{18,20}$/', $t) || preg_match('/^[A-Z]{2}\d{9}US$/', $t)) return 'USPS';
    if (preg_match('/^(\d{12}|\d{15}|\d{20}|\d{22})$/', $t)) return 'FedEx';
    if (preg_match('/^\d{10,11}$/', $t) || preg_match('/^JJD\d+$/', $t)) return 'DHL';
    return '';
}

function sl_intakeData($user, $core): array
{
    $look = sl_opsLookups();
    $info = cdp_getInfoShipDefault();
    $ctx = cdp_getAgencyContext();
    $scope = sl_opsScope('a');
    $recent = sl_rows("SELECT a.order_id, a.order_prefix, a.order_no, a.total_weight, a.sender_id, a.provider_purchase FROM cdb_customers_packages a WHERE DATE(a.order_datetime) = CURDATE() $scope ORDER BY a.order_id DESC LIMIT 6");
    $users = sl_usersById(array_map(fn($r) => $r->sender_id, $recent));
    require_once CDP_ROOT . '/helpers/ai_assistant.php';
    $ai = function_exists('cdp_aiConfigured') && cdp_aiConfigured();
    $carriers = array_map(fn($c) => ['id' => (int) $c->id, 'label' => $c->label], $look['couriers']);
    return [
        'todayCount' => (int) sl_scalar("SELECT COUNT(*) FROM cdb_customers_packages a WHERE DATE(a.order_datetime) = CURDATE() $scope"),
        'carriers' => $carriers,
        'recent' => array_map(fn($r) => ['id' => (int) $r->order_id, 'track' => $r->order_prefix . $r->order_no, 'name' => sl_userName($users[(int) $r->sender_id] ?? null) . ($r->provider_purchase ? ' · ' . $r->provider_purchase : ''), 'w' => (float) $r->total_weight > 0 ? sl_weight($r->total_weight) : '—'], $recent),
        'defaults' => [
            'agency' => !empty($ctx['is_restricted']) && !empty($ctx['agency_id']) ? (int) $ctx['agency_id'] : (int) ($look['agencies'][0]->id ?? 0),
            'origin_off' => (int) ($look['offices'][0]->id ?? 0), 'order_package' => (int) ($info->packaging_default2 ?? 0), 'order_courier' => (int) ($info->courier_default3 ?? 0),
            'order_deli_time' => (int) ($info->time_default5 ?? 0), 'order_service_options' => (int) ($info->service_default4 ?? 0),
        ],
        'agencies' => $look['agencies'], 'offices' => $look['offices'], 'packaging' => $look['packaging'], 'delitimes' => $look['delitimes'],
        'rates' => ['price' => (float) ($core->value_weight ?? 0), 'ins' => (float) ($core->insurance ?? 0), 'meter' => (float) ($core->meter ?: 500), 'rate' => (float) ($core->exchange_rate ?? 0), 'weight' => (string) ($core->weight_p ?? 'lb'), 'currency' => (string) ($core->currency ?? 'USD')],
        'prefix' => (string) ($core->prefix_online_shopping ?: 'PK-'),
        'ai' => $ai, 'agencyLocked' => !empty($ctx['is_restricted']) && !empty($ctx['agency_id']),
        'canLabel' => $user->cdp_hasPermission('print_label'), 'canReceipt' => $user->cdp_hasPermission('print_package'),
    ];
}
