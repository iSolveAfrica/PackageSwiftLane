<?php
/** M-CParcel for an air shipment (courier_view.php, view ShipmentDetail, customer): see _cparcel.php. */
require_once CDP_ROOT . '/helpers/sl_mx.php';
if (!empty($d['missing'])) {
    $slPhone['nav'] = 'customer';
    echo sl_phoneTop('Shipment', '', ['back' => sl_route('MyParcels')]);
    echo '<main class="mx-body">' . sl_mxEmpty('box', 'Shipment not found', 'It may have been removed, or it belongs to another account.', 'Back to my parcels', sl_route('MyParcels')) . '</main>';
    return;
}
$step = (int) ($d['step'] ?? 1); // 1..5
$status = (string) ($d['status'] ?? '');
$ready = stripos($status, 'ready') !== false || in_array((int) ($d['statusId'] ?? 0), [6, 32], true);
$done = $step >= 5;
$air = ($d['mode'] ?? 'Air') === 'Air';
$stCls = $done || $ready ? 'ok' : (($d['stCls'] ?? '') === 'overdue' ? 'bad' : ($step >= 3 ? 'sky' : 'gr'));
$tags = [[$air ? 'Air freight' : 'Sea freight', $air ? 'air' : 'sea']];
if (!empty($d['consol'])) $tags[] = ['In consolidation ' . $d['consol']['code'], ''];
$tags[] = !empty($d['dangerous']) ? ['Dangerous goods', 'bad'] : ['No dangerous goods', 'ok'];
$acts = [['Full journey', 'i-route', sl_route('LiveTracking') . '?q=' . rawurlencode((string) $d['track']), 'amb']];
if (!empty($d['canLabel'])) $acts[] = ['Label', 'i-print', sl_route('Label4x6', ['id' => (int) $d['id']]), '', true];
if (!empty($d['canPrint'])) $acts[] = ['Invoice', 'i-receipt', sl_route('ReceiptNotPaid', ['id' => (int) $d['id'], 'type' => 'air']), '', true];
if (!empty($d['canPay'])) $acts[] = ['Pay now', 'i-card', 'add_payment_gateways_courier.php?id_order=' . (int) $d['id'], ''];
else $acts[] = [(string) ($d['pay'] ?? 'Payment'), 'i-card', '#cp-more', 'off'];
$items = array_map(fn($it) => ['qty' => $it['qty'], 'desc' => (string) $it['desc'], 'w' => (string) $it['kg'], 'val' => (string) ($it['val'] ?? '—')], (array) ($d['items'] ?? []));
$s = $d['sender'] ?? [];
$r = $d['recip'] ?? [];
$pc = $d['pickupCode'] ?? [];
$p = [
    'scene' => $d['scene'] ?? null,
    'back' => sl_route('MyParcels'), 'trk' => (string) $d['track'], 'st' => $status, 'stCls' => $stCls, 'mode' => $air ? 'Air' : 'Sea',
    'from' => (string) ($d['from'] ?? '—'), 'to' => (string) ($d['to'] ?? '—'),
    'pct' => $done ? 100 : (int) round(($step - 1) / 4 * 100), 'eta' => (string) ($d['eta'] ?? ''),
    'steps' => (array) ($d['steps'] ?? []), 'step' => max(0, $step - 1),
    'events' => (array) ($d['events'] ?? []), 'tags' => $tags, 'acts' => $acts, 'items' => $items, 'weight' => (string) ($d['kg'] ?? ''),
    'kv' => [['Sender', trim(($s['name'] ?? '') . (($s['locker'] ?? '') !== '' ? ' · ' . $s['locker'] : ''))], ['From', (string) ($d['from'] ?? '')], ['Recipient', trim(($r['name'] ?? '') . (($r['phone'] ?? '—') !== '—' && ($r['phone'] ?? '') !== '' ? ' · ' . $r['phone'] : ''))], ['Deliver to', (string) ($r['addr'] ?? '')], ['Carrier', trim(($d['carrier'] ?? '') . (($d['carrierNo'] ?? '') !== '' ? ' · ' . $d['carrierNo'] : ''))], ['Estimated arrival', (string) ($d['eta'] ?? '')]],
    'files' => (array) ($d['files'] ?? []),
    'pay' => [['Total', ($d['total'] ?? '—') . ' · ' . strtolower((string) ($d['pay'] ?? ''))], ['Method', (string) ($d['payMethod'] ?? '')], ['Terms', (string) ($d['terms'] ?? '')], ['Reference', (string) ($d['payRef'] ?? '')], ['Paid on', (string) ($d['paidOn'] ?? '')], ['Due', (string) ($d['due'] ?? '')]],
    'payLink' => !empty($d['canPay']) ? 'add_payment_gateways_courier.php?id_order=' . (int) $d['id'] : '',
    'code' => !empty($pc['verified']) ? 'verified' : (!empty($pc['live']) ? 'ready' : ($done ? 'done' : '')),
    'driver' => !empty($d['driver']) ? ['name' => $d['driver']['name'], 'ini' => $d['driver']['ini'], 'phone' => (string) ($d['driver']['phone'] ?? '')] : null,
    'publicUrl' => (string) ($d['publicUrl'] ?? ''), 'deliver' => !$done,
];
include __DIR__ . '/_cparcel.php';
