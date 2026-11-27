<?php
/**
 * Tariffs (shipping_tariffs.php): the prices the system really charges, read from where each one is stored.
 *   cdb_settings            price per weight unit, volumetric divisor, tax, insurance, customs, declared-value tax
 *                           (shipment forms, intake, invoices; edited on settings_form.php?f=taxes)
 *   cdb_shipping_fees       route weight bands, public rows only (the public calculator, cotizar.php)
 *   cdb_delivery_config     door delivery: base, per km, per parcel, minimum (edited on delivery_settings.php)
 *   cdb_delivery_zones      door delivery: flat fee per zone
 * Read only. There is no second rate store: every Edit link opens the page that already saves these values.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/delivery_service.php';

/** Who may see the sheet and who may edit each group: the same permissions the edit pages check. */
function sl_tariffsCan($user): array
{
    $staff = !sl_userIs('client') && !sl_userIs('driver');
    $taxes = $staff && $user->cdp_hasPermission('manage_taxes_and_fees');
    $delivery = $staff && dl_manager($user) && !dl_agency() && $user->cdp_hasPermission(['manage_freight', 'manage_rider_fees']);
    return [
        'view' => $staff && ($taxes || $delivery || $user->cdp_hasPermission(['view_tools', 'manage_freight'])),
        'taxes' => $taxes,
        'delivery' => $delivery,
    ];
}

function sl_tariffsEditUrl(string $group): string
{
    return $group === 'delivery' ? sl_route('DeliverySettings') : 'settings_form.php?f=taxes';
}

function sl_tariffsPct($v): string
{
    return rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . '%';
}

/** Money for a rate: like sl_tariffsMoney(), but never rounded to whole units (a per-kg price of 3.55 is not 4). */
function sl_tariffsMoney($amount): string
{
    global $core;
    $symbol = (string) ($core->for_symbol ?? ($core->currency ?? '$'));
    if (function_exists('sl_showGhs') && sl_showGhs()) {
        $amount = sl_displayAmount($amount);
        $symbol = '₵';
    }
    $text = number_format((float) $amount, max(2, (int) ($core->for_decimal ?? 2)), $core->dec_point ?? '.', $core->thousands_sep ?? ',');
    return ($core->cformat ?? '1') == '2' ? $text . ' ' . $symbol : $symbol . $text;
}

function sl_tariffsNum($v): string
{
    return rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
}

function sl_tariffsData($user, $core, array $can): array
{
    $unit = (string) ($core->weight_p ?? 'kg');
    $unit = $unit === 'lbs' ? 'lb' : $unit;
    $len = (string) ($core->units ?? 'cm');
    $price = (float) ($core->value_weight ?? 0);
    $insMode = function_exists('sl_slSetting') ? (string) sl_slSetting('shipment_insurance_mode', 'percentage') : 'percentage';
    if (!function_exists('sl_slSetting') && is_file(CDP_ROOT . '/views/sl/data/ops_common.php')) {
        require_once CDP_ROOT . '/views/sl/data/ops_common.php';
        $insMode = (string) sl_slSetting('shipment_insurance_mode', 'percentage');
    }

    // Freight
    $freight = [];
    $freight[] = ['icon' => 'plane', 't' => 'Freight price', 's' => 'Per ' . $unit . ' of chargeable weight, air and sea', 'v' => $price > 0 ? sl_tariffsMoney($price) : 'Not set', 'set' => $price > 0];
    $meter = (float) ($core->meter ?? 0);
    $freight[] = ['icon' => 'box', 't' => 'Volumetric weight', 's' => 'Length × width × height (' . $len . ') ÷ this number', 'v' => $meter > 0 ? '÷ ' . sl_tariffsNum($meter) : 'Not set', 'set' => $meter > 0];

    // Route weight bands the public calculator reads (public rows only, valid today).
    $bands = [];
    if (sl_tableExists('cdb_shipping_fees')) {
        $valid = sl_columnExists('cdb_shipping_fees', 'valid_from') ? ' AND (f.valid_from IS NULL OR f.valid_from <= CURDATE()) AND (f.valid_until IS NULL OR f.valid_until >= CURDATE())' : '';
        $rows = sl_rows('SELECT f.id, f.initial_range, f.final_range, f.price, f.order_item_category, co.name AS o, cd.name AS d, c.name_item AS cat
            FROM cdb_shipping_fees f LEFT JOIN cdb_countries co ON co.id = f.origin LEFT JOIN cdb_countries cd ON cd.id = f.destiny LEFT JOIN cdb_category c ON c.id = f.order_item_category
            WHERE (f.client_id = 0 OR f.client_id IS NULL)' . $valid . ' ORDER BY co.name, cd.name, f.order_item_category, f.initial_range LIMIT 200');
        foreach ($rows as $r) {
            $cat = trim(html_entity_decode((string) ($r->cat ?? ''), ENT_QUOTES));
            $bands[] = [
                'icon' => stripos($cat, 'ocean') !== false || stripos($cat, 'sea') !== false ? 'ship' : 'plane',
                't' => trim(($r->o ?: 'Any origin') . ' → ' . ($r->d ?: 'Any destination')),
                's' => sl_tariffsNum($r->initial_range) . '–' . sl_tariffsNum($r->final_range) . ' ' . $unit . ($cat !== '' ? ' · ' . $cat : '') . ' · per ' . $unit,
                'v' => sl_tariffsMoney($r->price),
            ];
        }
    }

    // Charges added on top of freight
    $charges = [];
    $tax = (float) ($core->tax ?? 0);
    $minTax = (float) ($core->min_cost_tax ?? 0);
    $charges[] = ['icon' => 'receipt', 't' => 'Tax', 's' => $minTax > 0 ? 'On the shipping subtotal from ' . sl_tariffsMoney($minTax) : 'On the shipping subtotal', 'v' => sl_tariffsPct($tax), 'set' => $tax > 0];
    if ($insMode === 'fixed') {
        $charges[] = ['icon' => 'shield', 't' => 'Insurance', 's' => 'Fixed amount per shipment', 'v' => sl_tariffsMoney((float) sl_slSetting('shipment_insurance_fixed', 0)), 'set' => true];
    } else {
        $ins = (float) ($core->insurance ?? 0);
        $charges[] = ['icon' => 'shield', 't' => 'Insurance', 's' => 'Of the insured value', 'v' => sl_tariffsPct($ins), 'set' => $ins > 0];
    }
    $cus = (float) ($core->c_tariffs ?? 0);
    $charges[] = ['icon' => 'globe', 't' => 'Customs tariff', 's' => 'Of the declared value', 'v' => sl_tariffsPct($cus), 'set' => $cus > 0];
    $dec = (float) ($core->declared_tax ?? 0);
    $minDec = (float) ($core->min_cost_declared_tax ?? 0);
    $charges[] = ['icon' => 'tag', 't' => 'Declared value tax', 's' => $minDec > 0 ? 'On declared values from ' . sl_tariffsMoney($minDec) : 'On the declared value', 'v' => sl_tariffsPct($dec), 'set' => $dec > 0];

    // Door delivery
    $cfg = dl_config();
    $cur = (string) $cfg['currency'];
    $modes = (array) $cfg['modes'];
    $delivery = [];
    $zones = [];
    if (!empty($cfg['enabled'])) {
        if (in_array('kilometre', $modes, true)) {
            $delivery[] = ['icon' => 'route', 't' => 'Base fee', 's' => 'Every delivery priced by distance', 'v' => dl_money((int) $cfg['base_cents'], $cur)];
            $delivery[] = ['icon' => 'pin', 't' => 'Per kilometre', 's' => 'Up to ' . sl_tariffsNum($cfg['max_km']) . ' km from the hub', 'v' => dl_money((int) $cfg['per_km_cents'], $cur)];
            if ((int) $cfg['per_package_cents'] > 0) {
                $delivery[] = ['icon' => 'box', 't' => 'Per parcel', 's' => 'Added for each parcel in the delivery', 'v' => dl_money((int) $cfg['per_package_cents'], $cur)];
            }
            if ((int) $cfg['minimum_cents'] > 0) {
                $delivery[] = ['icon' => 'dollar', 't' => 'Minimum charge', 's' => 'No delivery costs less than this', 'v' => dl_money((int) $cfg['minimum_cents'], $cur)];
            }
        }
        if (in_array('zone', $modes, true) && sl_tableExists('cdb_delivery_zones')) {
            foreach (sl_rows('SELECT name, city_match, fee FROM cdb_delivery_zones WHERE active = 1 ORDER BY fee, name LIMIT 100') as $z) {
                $zones[] = ['icon' => 'truck', 't' => (string) $z->name, 's' => (string) $z->city_match, 'v' => dl_money((int) round((float) $z->fee * 100), $cur)];
            }
        }
    }

    return [
        'can' => $can,
        'unit' => $unit,
        'freight' => $freight,
        'bands' => $bands,
        'charges' => $charges,
        'deliveryOn' => !empty($cfg['enabled']),
        'delivery' => $delivery,
        'zones' => $zones,
        'zoneMode' => in_array('zone', $modes, true),
        'defaultMode' => (string) $cfg['default_mode'],
        'canSend' => $user->cdp_hasPermission(['send_prices', 'push_notifications']),
    ];
}
