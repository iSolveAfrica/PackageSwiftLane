<?php
/**
 * Single-record settings forms shown in the Swift Lane design (settings_form.php).
 * Each form posts the legacy field names to its existing save endpoint, so the
 * validation and storage rules of ajax/tools/config_*_ajax.php are unchanged.
 * field: [label, type, help, options]  type: text|number|textarea|select|lookup|yesno
 */
function sl_settingsForms(): array
{
    return [
        'shipping_defaults' => ['title' => 'Default Shipping Info', 'lede' => 'Values pre-filled on every new shipment and package', 'perm' => 'manage_default_shipping_info',
            'endpoint' => 'ajax/tools/config_info_ship_default_ajax.php', 'source' => 'info',
            'fields' => [
                'logistics_default1' => ['Logistics service', 'lookup', '', ['cdb_category', 'id', 'name_item']],
                'packaging_default2' => ['Type of package', 'lookup', '', ['cdb_packaging', 'id', 'name_pack']],
                'courier_default3' => ['Shipping company', 'lookup', '', ['cdb_courier_com', 'id', 'name_com']],
                'service_default4' => ['Shipping mode', 'lookup', '', ['cdb_shipping_mode', 'id', 'ship_mode']],
                'time_default5' => ['Delivery time', 'lookup', '', ['cdb_delivery_time', 'id', 'delitime']],
                'pay_default6' => ['Payment method', 'lookup', '', ['cdb_met_payment', 'id', 'name_pay']],
                'payment_default7' => ['Payment terms', 'lookup', '', ['cdb_payment_methods', 'id', 'label']],
                'status_default8' => ['Initial status', 'lookup', '', ['cdb_styles', 'id', 'mod_style']],
            ]],
        'taxes' => ['title' => 'Taxes, Fees and Weights', 'lede' => 'Charges and measurement units used by the price calculation', 'perm' => 'manage_taxes_and_fees',
            'endpoint' => 'ajax/tools/config_taxes_ajax.php', 'source' => 'core',
            'fields' => [
                'tax' => ['Tax (%)', 'number', 'Applied to the shipping subtotal'],
                'min_cost_tax' => ['Minimum amount for tax', 'number', 'Below this subtotal no tax is charged'],
                'declared_tax' => ['Declared value tax (%)', 'number', ''],
                'min_cost_declared_tax' => ['Minimum declared value for tax', 'number', ''],
                'insurance' => ['Insurance (%)', 'number', 'Of the insured value'],
                'c_tariffs' => ['Customs tariff (%)', 'number', ''],
                'value_weight' => ['Price per weight unit', 'number', 'Used when no tariff matches'],
                'meter' => ['Volumetric divisor', 'number', 'Length × width × height ÷ this value'],
                'units' => ['Length unit', 'select', '', ['cm' => 'Centimetre', 'm' => 'Metre', 'Pie' => 'Foot', 'in' => 'Inch']],
                'weight_p' => ['Weight unit', 'select', '', ['kg' => 'Kilogram', 'lb' => 'Pound']],
            ]],
        'numbering' => ['title' => 'Tracking and Invoice', 'lede' => 'Tracking number prefixes, number length and invoice wording', 'perm' => 'track_invoices',
            'endpoint' => 'ajax/tools/config_track_invoice_ajax.php', 'source' => 'core',
            'fields' => [
                'prefix' => ['Shipment prefix', 'text', 'e.g. SL-'],
                'track_digit' => ['Shipment number digits', 'number', ''],
                'prefix_consolidate' => ['Consolidation prefix', 'text', ''],
                'track_consolidate' => ['Consolidation number digits', 'number', ''],
                'prefix_online_shopping' => ['Package prefix', 'text', ''],
                'track_online_shopping' => ['Package number digits', 'number', ''],
                'code_number' => ['Random tracking numbers', 'yesno', 'Yes: random digits. No: sequential numbers'],
                'digit_random' => ['Random number length', 'number', ''],
                'signing_customer' => ['Customer signature label', 'text', ''],
                'signing_company' => ['Company signature label', 'text', ''],
                'interms' => ['Invoice terms and conditions', 'textarea', 'Printed at the bottom of invoices'],
            ]],
    ];
}
