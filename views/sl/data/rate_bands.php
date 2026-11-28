<?php
/**
 * Route prices (rate_bands.php): the public weight bands in cdb_shipping_fees that the rate calculator
 * (cotizar.php) quotes from. A band is origin → destination, optional category, a weight range and a price
 * per weight unit. Client-specific rows (client_id > 0) are not shown or touched here.
 * Writes: ajax/sl/rate_bands_ajax.php.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/views/sl/data/tariffs.php';

function sl_bandsCan($user): bool
{
    return !sl_userIs('client') && !sl_userIs('driver') && $user->cdp_hasPermission('manage_taxes_and_fees');
}

/** Public bands, grouped by route for the list. */
function sl_bandsRows(): array
{
    if (!sl_tableExists('cdb_shipping_fees')) return [];
    return sl_rows('SELECT f.*, co.name AS o_name, cd.name AS d_name, c.name_item AS cat_name
        FROM cdb_shipping_fees f LEFT JOIN cdb_countries co ON co.id = f.origin LEFT JOIN cdb_countries cd ON cd.id = f.destiny
        LEFT JOIN cdb_category c ON c.id = f.order_item_category
        WHERE (f.client_id = 0 OR f.client_id IS NULL)
        ORDER BY co.name, cd.name, f.order_item_category, f.initial_range LIMIT 500');
}

function sl_bandsData($user, $core): array
{
    $unit = (string) ($core->weight_p ?? 'kg');
    $routes = [];
    foreach (sl_bandsRows() as $r) {
        $key = (int) $r->origin . '-' . (int) $r->destiny;
        $routes[$key]['title'] = trim(($r->o_name ?: 'Unknown origin') . ' → ' . ($r->d_name ?: 'Unknown destination'));
        $cat = trim(html_entity_decode((string) ($r->cat_name ?? ''), ENT_QUOTES));
        $today = date('Y-m-d');
        $live = (empty($r->valid_from) || $r->valid_from <= $today) && (empty($r->valid_until) || $r->valid_until >= $today);
        $routes[$key]['bands'][] = [
            'id' => (int) $r->id,
            'range' => sl_tariffsNum($r->initial_range) . '–' . sl_tariffsNum($r->final_range) . ' ' . $unit,
            'cat' => $cat !== '' ? $cat : 'Any category',
            'price' => sl_tariffsMoney($r->price) . ' per ' . $unit,
            'min' => (float) ($r->min_charge ?? 0) > 0 ? 'Minimum ' . sl_tariffsMoney($r->min_charge) : '',
            'live' => $live,
            'dates' => trim((!empty($r->valid_from) ? 'from ' . sl_date($r->valid_from) : '') . (!empty($r->valid_until) ? ' until ' . sl_date($r->valid_until) : '')),
        ];
    }
    $edit = null;
    $want = (string) ($_GET['edit'] ?? '');
    if ($want === 'new') {
        $edit = (object) ['id' => 0, 'origin' => (int) ($_GET['origin'] ?? 0), 'destiny' => (int) ($_GET['destiny'] ?? 0), 'order_item_category' => 0, 'initial_range' => '', 'final_range' => '', 'price' => '', 'volumetric_percentage' => (int) ($core->meter ?? 0) > 0 ? (int) $core->meter : 5000, 'min_charge' => '', 'valid_from' => '', 'valid_until' => ''];
    } elseif (ctype_digit($want)) {
        $edit = sl_row('SELECT * FROM cdb_shipping_fees WHERE id = :id AND (client_id = 0 OR client_id IS NULL)', [':id' => (int) $want]) ?: null;
    }
    return [
        'routes' => array_values($routes),
        'count' => array_sum(array_map(fn($r) => count($r['bands']), $routes)),
        'edit' => $edit,
        'editMissing' => $want !== '' && $edit === null,
        'countries' => sl_rows('SELECT id, name, is_active FROM cdb_countries ORDER BY is_active DESC, name'),
        'cats' => sl_rows('SELECT id, name_item FROM cdb_category ORDER BY id'),
        'unit' => $unit,
        'standard' => (float) ($core->value_weight ?? 0) > 0 ? sl_tariffsMoney($core->value_weight) . ' per ' . $unit : '',
        'canEdit' => sl_bandsCan($user),
    ];
}
