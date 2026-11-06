<?php
/** Data for Shipping Tariffs (Tariffs.dc.html) and the Rate editor (RateEditor.dc.html). Source: cdb_shipping_fees, cdb_shipping_fees_custom. */
require_once CDP_ROOT . '/helpers/sl_view.php';

/** Ship modes are cdb_category rows (26 Air Freight, 27 Ocean Freight, ...). */
function sl_tariffModes(): array
{
    $out = [];
    foreach (sl_rows('SELECT id, name_item FROM cdb_category ORDER BY id') as $r) $out[(int) $r->id] = html_entity_decode((string) $r->name_item, ENT_QUOTES);
    return $out;
}

/** Rates keep two decimals even when the installation shows whole amounts. */
function sl_rate($v): string
{
    global $core;
    return ($core->for_symbol ?? '$') . number_format((float) $v, 2);
}

function sl_tariffsData($user, $core): array
{
    $d = [];
    $modes = sl_tariffModes();
    $hasMin = sl_columnExists('cdb_shipping_fees', 'min_charge');
    $minCol = $hasMin ? 'f.min_charge, f.valid_from, f.valid_until' : '0 AS min_charge, NULL AS valid_from, NULL AS valid_until';
    $q = trim((string) ($_GET['q'] ?? ''));
    $where = '1=1'; $b = [];
    if ($q !== '') { $where .= ' AND (oc.name LIKE :q OR dc.name LIKE :q OR s.name LIKE :q OR ci.name LIKE :q OR CONCAT(u.fname, \' \', u.lname) LIKE :q)'; $b[':q'] = "%$q%"; }
    $rows = sl_rows("SELECT f.*, $minCol, oc.name AS origin_n, dc.name AS dest_n, s.name AS state_n, ci.name AS city_n, u.fname, u.lname
        FROM cdb_shipping_fees f LEFT JOIN cdb_countries oc ON oc.id = f.origin LEFT JOIN cdb_countries dc ON dc.id = f.destiny LEFT JOIN cdb_states s ON s.id = f.state LEFT JOIN cdb_cities ci ON ci.id = f.city LEFT JOIN cdb_users u ON u.id = f.client_id
        WHERE $where ORDER BY f.order_service_options, f.origin, f.destiny, f.initial_range LIMIT 500", $b);
    $groups = [];
    foreach ($rows as $r) {
        $m = (int) $r->order_service_options;
        $sea = $m === 27;
        $unit = $sea ? 'cu ft' : 'lb';
        $route = trim(($r->origin_n ?: '—') . ' → ' . ($r->dest_n ?: '—') . ($r->city_n ? ' · ' . $r->city_n : ($r->state_n ? ' · ' . $r->state_n : '')));
        $groups[$m]['name'] = $modes[$m] ?? ('Mode ' . $m);
        $groups[$m]['sea'] = $sea;
        $groups[$m]['unit'] = $unit;
        $groups[$m]['rows'][] = ['id' => (int) $r->id, 'band' => rtrim(rtrim(number_format((float) $r->initial_range, 2), '0'), '.') . ' – ' . rtrim(rtrim(number_format((float) $r->final_range, 2), '0'), '.') . ' ' . $unit, 'route' => $route,
            'price' => sl_rate($r->price) . ' / ' . $unit, 'raw' => (float) $r->price, 'from' => (float) $r->initial_range, 'to' => (float) $r->final_range, 'vol' => (int) $r->volumetric_percentage, 'mile' => (float) $r->price_mile > 0 ? sl_rate($r->price_mile) . ' / mi' : '—',
            'min' => (float) $r->min_charge > 0 ? sl_rate($r->min_charge) : '—', 'minRaw' => (float) $r->min_charge, 'client' => $r->client_id ? trim($r->fname . ' ' . $r->lname) : '', 'valid' => $r->valid_until ? 'until ' . sl_date($r->valid_until) : ''];
    }
    foreach ($groups as $m => $g) {
        $prices = array_column($g['rows'], 'raw');
        $groups[$m]['from'] = $prices ? sl_rate(min($prices)) . '/' . $g['unit'] : '—';
        $groups[$m]['to'] = $prices ? sl_rate(max($prices)) . '/' . $g['unit'] : '—';
        $groups[$m]['n'] = count($g['rows']);
        $groups[$m]['vol'] = $g['rows'][0]['vol'] ?? 0;
    }
    // air first
    uksort($groups, fn($a, $b) => ($a === 26 ? -1 : ($b === 26 ? 1 : $a <=> $b)));
    $d['groups'] = $groups;
    $d['custom'] = sl_tableExists('cdb_shipping_fees_custom') ? array_map(fn($r) => ['client' => trim(($r->fname ?? '') . ' ' . ($r->lname ?? '')) ?: 'Customer #' . (int) $r->client_id, 'mode' => $modes[(int) $r->ship_mode] ?? '—', 'band' => (float) $r->initial_range . ' – ' . (float) $r->final_range, 'price' => sl_rate($r->price)], sl_rows('SELECT c.*, u.fname, u.lname FROM cdb_shipping_fees_custom c LEFT JOIN cdb_users u ON u.id = c.client_id ORDER BY c.client_id LIMIT 100')) : [];
    $d['q'] = $q;
    $d['count'] = count($rows);
    $d['canAdd'] = $user->cdp_hasPermission('create_shipping_tariff');
    $d['canEdit'] = $user->cdp_hasPermission('edit_shipping_tariff');
    $d['canDelete'] = $user->cdp_hasPermission('delete_shipping_tariff');
    // price check: client-side over the flat rows
    $flat = [];
    foreach ($groups as $m => $g) foreach ($g['rows'] as $r) $flat[] = ['m' => $m, 'from' => $r['from'], 'to' => $r['to'], 'p' => $r['raw'], 'min' => $r['minRaw'], 'vol' => $r['vol'], 'route' => $r['route']];
    $d['flat'] = $flat;
    $d['modes'] = array_map(fn($m, $g) => ['m' => $m, 'label' => $g['name'], 'unit' => $g['unit']], array_keys($groups), $groups);
    $d['insurance'] = isset($core->tax_insurance) ? (float) $core->tax_insurance : 0;
    $d['symbol'] = $core->for_symbol ?? '$';
    return $d;
}

function sl_rateEditorData($user, $core, int $id = 0): array
{
    $d = ['id' => $id, 'row' => null];
    if ($id) {
        $d['row'] = sl_row('SELECT * FROM cdb_shipping_fees WHERE id = :id', [':id' => $id]);
    }
    $r = $d['row'];
    $d['v'] = [
        'origin' => (int) ($_GET['origin'] ?? ($r->origin ?? 0)), 'destiny' => (int) ($_GET['destiny'] ?? ($r->destiny ?? 0)), 'state' => (int) ($_GET['state'] ?? ($r->state ?? 0)), 'city' => (int) ($r->city ?? 0),
        'mode' => (int) ($_GET['mode'] ?? ($r->order_service_options ?? 26)), 'from' => $r->initial_range ?? '', 'to' => $r->final_range ?? '', 'price' => $r->price ?? '', 'vol' => $r->volumetric_percentage ?? 5000, 'mile' => $r->price_mile ?? '0',
        'client' => (int) ($r->client_id ?? 0), 'min' => $r->min_charge ?? '0', 'vfrom' => $r->valid_from ?? '', 'vto' => $r->valid_until ?? '',
    ];
    $d['modes'] = sl_tariffModes();
    $d['countries'] = sl_rows('SELECT id, name FROM cdb_countries ORDER BY name');
    $d['states'] = $d['v']['destiny'] ? sl_rows('SELECT id, name FROM cdb_states WHERE country_id = :c ORDER BY name', [':c' => $d['v']['destiny']]) : [];
    $d['cities'] = $d['v']['state'] ? sl_rows('SELECT id, name FROM cdb_cities WHERE state_id = :s ORDER BY name LIMIT 2000', [':s' => $d['v']['state']]) : [];
    $d['clients'] = sl_rows('SELECT id, fname, lname, locker FROM cdb_users WHERE userlevel = 1 AND active = 1 ORDER BY fname, lname LIMIT 500');
    $names = fn($list, $id) => array_values(array_filter(array_map(fn($x) => (int) $x->id === $id ? $x->name : null, $list)))[0] ?? '';
    $d['title'] = $r ? (($d['modes'][(int) $r->order_service_options] ?? 'Rate') . ' · ' . $names($d['countries'], (int) $r->origin) . ' → ' . $names($d['countries'], (int) $r->destiny)) : 'New rate';
    $d['hasMin'] = sl_columnExists('cdb_shipping_fees', 'min_charge');
    $d['insurance'] = isset($core->tax_insurance) ? (float) $core->tax_insurance : 0;
    $d['canDelete'] = $id && $user->cdp_hasPermission('delete_shipping_tariff');
    return $d;
}
