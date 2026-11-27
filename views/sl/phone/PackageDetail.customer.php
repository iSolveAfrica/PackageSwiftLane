<?php
/** M-CParcel for a sea package (customer_packages_view.php, view PackageDetail, customer): see _cparcel.php. */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$stage = (int) ($d['stage'] ?? 1); // 1..5
$ready = in_array((int) ($d['raw'] ?? 0), [6, 32], true);
$done = !empty($d['done']);
$stCls = $done || $ready ? 'ok' : ($stage >= 3 ? 'sky' : 'gr');
$tags = [['Sea freight', 'sea']];
if (!empty($d['con'])) $tags[] = ['In container ' . $d['con']['code'], ''];
$tags[] = [$d['pay'] ?? '—', ($d['payCls'] ?? '') === 'delivered' || ($d['pay'] ?? '') === 'Paid' ? 'ok' : 'warn'];
$acts = [['Full journey', 'i-route', sl_route('LiveTracking') . '?q=' . rawurlencode((string) $d['trk']), 'amb']];
if (!empty($d['can']['print'])) $acts[] = ['Invoice', 'i-receipt', 'print_invoice_package.php?id=' . (int) $d['id'], '', true];
if (!empty($d['can']['pay'])) $acts[] = ['Pay now', 'i-card', 'add_payment_gateways_package.php?id_order=' . (int) $d['id'], ''];
else $acts[] = [$d['pay'] ?? 'Payment', 'i-card', '#cp-pay', 'off'];
if ($ready) $acts[] = ['Pickup code', 'i-key', sl_route('PickupCodes'), ''];
$items = array_map(fn($b) => ['qty' => (float) $b['qty'] == (int) $b['qty'] ? (int) $b['qty'] : $b['qty'], 'desc' => (string) $b['what'], 'w' => (string) $b['w'], 'val' => (string) $b['declared']], (array) ($d['boxes'] ?? []));
$o = $d['owner'] ?? [];
$pay = [['Total', ($d['total'] ?? '—') . ' · ' . strtolower((string) ($d['pay'] ?? ''))]];
foreach ((array) ($d['charges'] ?? []) as $c) {
    if (preg_replace('/[^0-9]/', '', (string) $c[1]) !== '' && (float) preg_replace('/[^0-9.]/', '', (string) $c[1]) > 0) $pay[] = $c;
}
$pay[] = ['Method', (string) ($d['method'] ?? '—')];
$pay[] = ['Paid on', (string) ($d['paidOn'] ?? '—')];
$p = [
    'back' => sl_route('MyParcels'), 'trk' => (string) $d['trk'], 'st' => (string) $d['st'], 'stCls' => $stCls, 'mode' => 'Sea',
    'from' => (string) (($d['office'] ?? '—') !== '—' ? $d['office'] : 'Warehouse'), 'to' => (string) (($d['deliverTo'] ?? '—') !== '—' ? $d['deliverTo'] : 'Pickup hub'),
    'pct' => $done ? 100 : (int) round(($stage - 1) / 4 * 100), 'eta' => (string) ($d['eta'] ?? ''),
    'steps' => (array) ($d['steps'] ?? []), 'step' => max(0, $stage - 1),
    'events' => (array) ($d['events'] ?? []), 'tags' => $tags, 'acts' => $acts, 'items' => $items, 'weight' => (string) ($d['weight'] ?? ''),
    'kv' => [['Owner', trim(($o['name'] ?? '') . (($o['locker'] ?? '') !== '' ? ' · ' . $o['locker'] : ''))], ['Registered', (string) ($d['registered'] ?? '')], ['Warehouse', (string) ($d['office'] ?? '')], ['Store', (string) ($d['supplier'] ?? '')], ['Carrier tracking', (string) ($d['postal'] ?? '')], ['Deliver to', (string) ($d['deliverTo'] ?? '')], ['Estimated arrival', (string) ($d['eta'] ?? '')]],
    'files' => array_map(fn($f) => $f + ['img' => (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', (string) $f['url'])], (array) ($d['files'] ?? [])),
    'pay' => $pay, 'payLink' => !empty($d['can']['pay']) ? 'add_payment_gateways_package.php?id_order=' . (int) $d['id'] : '',
    'code' => $done ? 'done' : ($ready ? 'ready' : ''), 'driver' => null,
    'publicUrl' => sl_route('PublicTracking') . '?order_track=' . rawurlencode((string) $d['trk']), 'deliver' => !$done,
];
include __DIR__ . '/_cparcel.php';
