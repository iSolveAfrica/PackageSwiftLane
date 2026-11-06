<?php
/** Shipping addresses: the warehouse addresses (cdb_virtual_mailbox_addresses) customers ship to. */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate(['manage_shipping_addresses', 'view_tools']);
require_once 'helpers/virtual_mailbox.php';
$hasExtra = sl_columnExists('cdb_virtual_mailbox_addresses', 'is_active');
$rows = sl_rows('SELECT v.*, c.iso2 FROM cdb_virtual_mailbox_addresses v LEFT JOIN cdb_countries c ON c.id = v.cdb_countries_id ORDER BY ' . ($hasExtra ? 'v.sort_order, ' : '') . 'v.id');
$sel = (int) ($_GET['id'] ?? 0);
$locker = 'SL-' . ($userData->locker ?: '3318');
$list = [];
$pv = null;
$on = 0;
foreach ($rows as $r) {
    $active = $hasExtra ? (int) $r->is_active === 1 : true;
    $on += $active ? 1 : 0;
    $lines = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', strip_tags(preg_replace('/<br\s*\/?>/i', "\n", (string) $r->address)))))));
    $item = ['id' => (int) $r->id, 'cc' => strtoupper((string) ($r->iso2 ?: mb_substr((string) $r->country, 0, 2))), 'label' => ($hasExtra && $r->label) ? $r->label : (string) $r->country, 'mode' => ucfirst($hasExtra ? (string) $r->shipping_mode : 'air'),
        'line' => mb_substr(implode(', ', $lines), 0, 110), 'transit' => $hasExtra ? (string) $r->transit_days : '', 'on' => $active, 'state' => $active ? 'Showing' : 'Hidden', 'sc' => $active ? '' : 'mut',
        'raw' => ['id' => (int) $r->id, 'cdb_countries_id' => (int) $r->cdb_countries_id, 'city' => $r->city, 'postcode' => $r->postcode, 'address' => $r->address, 'label' => $hasExtra ? $r->label : '', 'shipping_mode' => $hasExtra ? $r->shipping_mode : 'air', 'transit_days' => $hasExtra ? $r->transit_days : ''],
        'pv' => array_merge([['k' => 'Name', 'v' => trim(($userData->fname ?? '') . ' ' . ($userData->lname ?? '')) . ' (' . $locker . ')']], array_map(fn($l, $i) => ['k' => $i === 0 ? 'Address' : '', 'v' => preg_replace('/\(?\s*locker\s*id\s*\)?/i', '(' . $locker . ')', $l)], $lines, array_keys($lines)), [['k' => 'City', 'v' => (string) $r->city], ['k' => 'Phone / ZIP', 'v' => (string) $r->postcode]])];
    $list[] = $item;
    if (!$pv || (int) $r->id === $sel) $pv = $item;
}
$countries = sl_rows('SELECT id, name FROM cdb_countries ORDER BY name');
sl_render('ShippingAddresses', ['id' => 'addr', 'title' => 'Shipping addresses', 'board' => 'ShippingAddresses'], [
    'rows' => $list, 'count' => count($list), 'onCount' => $on, 'pv' => $pv, 'countries' => $countries, 'hasExtra' => $hasExtra,
    'edit' => null, 'canEdit' => $user->cdp_hasPermission(['manage_shipping_addresses', 'view_tools']),
]);
