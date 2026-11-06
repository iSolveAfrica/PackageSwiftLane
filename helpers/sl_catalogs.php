<?php
/**
 * Catalog registry for the Swift Lane "Lookup lists" manager (catalog.php).
 * Each entry describes one simple reference table: columns shown, form fields,
 * permissions (same keys as the legacy list/add/edit pages) and the tables
 * that reference a row, which block deletion (same rule as the legacy
 * cdp_verifyReferentialIntegrity* checks).
 *
 * field types: text, textarea, number, color, select (options: [value => label]), lookup (table, value, label)
 */
function sl_catalogs(): array
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    $c = [
        'offices' => ['title' => 'Offices', 'noun' => 'office', 'table' => 'cdb_offices', 'order' => 'name_off', 'group' => 'Logistics',
            'perm' => ['view' => 'view_offices', 'create' => 'create_office', 'edit' => 'edit_office'],
            'fields' => ['name_off' => ['Name', 'text', true], 'code_off' => ['Office code', 'text', true], 'address' => ['Address', 'text'], 'city' => ['City', 'text'], 'phone_off' => ['Phone', 'text']],
            'refs' => [['cdb_add_order', 'origin_off'], ['cdb_customers_packages', 'origin_off'], ['cdb_consolidate', 'origin_off']]],
        'branchoffices' => ['title' => 'Agency Groups', 'noun' => 'agency', 'table' => 'cdb_branchoffices', 'order' => 'name_branch', 'group' => 'Logistics',
            'perm' => ['view' => 'view_branches', 'create' => 'create_branch', 'edit' => 'edit_branch'],
            'fields' => ['name_branch' => ['Name', 'text', true], 'branch_address' => ['Address', 'text'], 'branch_city' => ['City', 'text'], 'phone_branch' => ['Phone', 'text']],
            'refs' => [['cdb_add_order', 'agency'], ['cdb_customers_packages', 'agency'], ['cdb_users', 'agency_id']]],
        'courier_company' => ['title' => 'Shipping Companies', 'noun' => 'company', 'table' => 'cdb_courier_com', 'order' => 'name_com', 'group' => 'Logistics',
            'perm' => ['view' => 'view_courier_companies', 'create' => 'create_courier_company', 'edit' => 'edit_courier_company'],
            'fields' => ['name_com' => ['Name', 'text', true], 'address_cou' => ['Address', 'text'], 'phone_cou' => ['Phone', 'text'], 'country_cou' => ['Country', 'text'], 'city_cou' => ['City', 'text'], 'postal_cou' => ['Postal code', 'text']],
            'refs' => [['cdb_add_order', 'order_courier'], ['cdb_customers_packages', 'order_courier'], ['cdb_pre_alert', 'courier_com']]],
        'packaging' => ['title' => 'Type of Packages', 'noun' => 'package type', 'table' => 'cdb_packaging', 'order' => 'name_pack', 'group' => 'Logistics',
            'perm' => ['view' => 'view_packaging', 'create' => 'create_packaging', 'edit' => 'edit_packaging'],
            'fields' => ['name_pack' => ['Name', 'text', true], 'detail_pack' => ['Details', 'textarea']],
            'refs' => [['cdb_add_order', 'order_package'], ['cdb_customers_packages', 'order_package']]],
        'shipping_mode' => ['title' => 'Shipping Modes', 'noun' => 'shipping mode', 'table' => 'cdb_shipping_mode', 'order' => 'ship_mode', 'group' => 'Logistics',
            'perm' => ['view' => 'view_shipping_modes', 'create' => 'create_shipping_mode', 'edit' => 'edit_shipping_mode'],
            'fields' => ['ship_mode' => ['Name', 'text', true], 'detail' => ['Details', 'textarea']],
            'refs' => [['cdb_add_order', 'order_service_options'], ['cdb_customers_packages', 'order_service_options']]],
        'delivery_time' => ['title' => 'Shipping Times', 'noun' => 'delivery time', 'table' => 'cdb_delivery_time', 'order' => 'delitime', 'group' => 'Logistics',
            'perm' => ['view' => 'view_delivery_times', 'create' => 'create_delivery_time', 'edit' => 'edit_delivery_time'],
            'fields' => ['delitime' => ['Name', 'text', true], 'detail' => ['Details', 'textarea']],
            'refs' => [['cdb_add_order', 'order_deli_time'], ['cdb_customers_packages', 'order_deli_time']]],
        'status_courier' => ['title' => 'Styles and States', 'noun' => 'status', 'table' => 'cdb_styles', 'order' => 'id', 'group' => 'Logistics',
            'perm' => ['view' => 'view_statuses', 'create' => 'create_status', 'edit' => 'edit_status'],
            'fields' => ['mod_style' => ['Status name', 'text', true], 'detail' => ['Details', 'textarea'], 'color' => ['Colour', 'color', true],
                'status_type' => ['Applies to', 'select', false, [3 => 'All', 1 => 'Shipments', 2 => 'Packages', 4 => 'Dangerous goods']]],
            'refs' => [['cdb_add_order', 'status_courier'], ['cdb_customers_packages', 'status_courier'], ['cdb_courier_track', 'status_courier']],
            'locked' => [1, 2, 3, 4, 5, 6, 7, 8, 14, 15, 16, 21, 28, 29, 32]],
        'category' => ['title' => 'Logistics Services', 'noun' => 'service', 'table' => 'cdb_category', 'order' => 'name_item', 'group' => 'Logistics',
            'perm' => ['view' => 'view_categories', 'create' => 'create_category', 'edit' => 'edit_category'],
            'fields' => ['name_item' => ['Name', 'text', true], 'detail_item' => ['Details', 'textarea']],
            'refs' => [['cdb_add_order', 'order_item_category'], ['cdb_customers_packages', 'order_item_category']]],
        'incoterms' => ['title' => 'Incoterms', 'noun' => 'incoterm', 'table' => 'cdb_incoterm', 'order' => 'inco_name', 'group' => 'Logistics',
            'perm' => ['view' => 'view_tools', 'create' => 'view_tools', 'edit' => 'view_tools'],
            'fields' => ['inco_name' => ['Name', 'text', true], 'detail' => ['Details', 'textarea']], 'refs' => []],
        'shipline' => ['title' => 'Shipping Lines', 'noun' => 'shipping line', 'table' => 'cdb_shipping_line', 'order' => 'ship_line', 'group' => 'Logistics',
            'perm' => ['view' => 'view_tools', 'create' => 'view_tools', 'edit' => 'view_tools'],
            'fields' => ['ship_line' => ['Name', 'text', true], 'detail' => ['Details', 'textarea']], 'refs' => []],
        'payment_methods' => ['title' => 'Payment Terms', 'noun' => 'payment term', 'table' => 'cdb_payment_methods', 'order' => 'days', 'group' => 'Payments',
            'perm' => ['view' => 'manage_payment_methods', 'create' => 'payment_methods_add', 'edit' => 'payment_methods_edit'],
            'fields' => ['label' => ['Name', 'text', true], 'days' => ['Days to pay', 'number', true]],
            'refs' => [['cdb_add_order', 'order_payment_method'], ['cdb_customers_packages', 'order_payment_method']]],
        'countries' => ['title' => 'Countries', 'noun' => 'country', 'table' => 'cdb_countries', 'order' => 'name', 'group' => 'Locations',
            'perm' => ['view' => 'manage_countries', 'create' => 'manage_countries_add', 'edit' => 'manage_countries_edit'],
            'fields' => ['name' => ['Name', 'text', true], 'iso3' => ['ISO3', 'text'], 'iso2' => ['ISO2', 'text'], 'phone_code' => ['Phone code', 'text'], 'capital' => ['Capital', 'text'],
                'currency' => ['Currency code', 'text'], 'currency_name' => ['Currency name', 'text'], 'currency_symbol' => ['Currency symbol', 'text'], 'region' => ['Region', 'text'],
                'is_active' => ['Active', 'select', true, [1 => 'Active', 0 => 'Inactive']]],
            'refs' => [['cdb_states', 'country_id']], 'search' => ['name', 'iso3', 'region']],
        'states' => ['title' => 'States', 'noun' => 'state', 'table' => 'cdb_states', 'order' => 'name', 'group' => 'Locations',
            'perm' => ['view' => 'manage_states', 'create' => 'manage_states_add', 'edit' => 'manage_states_edit'],
            'fields' => ['name' => ['Name', 'text', true], 'country_id' => ['Country', 'lookup', true, ['cdb_countries', 'id', 'name']], 'iso' => ['ISO code', 'text']],
            'refs' => [['cdb_cities', 'state_id']], 'filter' => 'country_id'],
        'cities' => ['title' => 'Cities', 'noun' => 'city', 'table' => 'cdb_cities', 'order' => 'name', 'group' => 'Locations',
            'perm' => ['view' => 'manage_cities', 'create' => 'manage_cities_add', 'edit' => 'manage_cities_edit'],
            'fields' => ['name' => ['Name', 'text', true], 'state_id' => ['State', 'lookup', true, ['cdb_states', 'id', 'name']]],
            'refs' => [], 'filter' => 'state_id'],
    ];
    return $c;
}

function sl_catalog(string $key): ?array
{
    $all = sl_catalogs();
    return $all[$key] ?? null;
}

/** Options for a lookup field: [id => label], capped for very large tables. */
function sl_catalogLookup(array $lk, string $search = '', int $limit = 400): array
{
    [$table, $val, $lab] = $lk;
    $sql = "SELECT `$val` AS v, `$lab` AS l FROM `$table`";
    $b = [];
    if ($search !== '') {
        $sql .= " WHERE `$lab` LIKE :q";
        $b[':q'] = '%' . $search . '%';
    }
    $sql .= " ORDER BY `$lab` LIMIT " . (int) $limit;
    $out = [];
    foreach (sl_rows($sql, $b) as $r) {
        $out[$r->v] = $r->l;
    }
    return $out;
}
