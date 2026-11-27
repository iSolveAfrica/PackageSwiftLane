<?php
/**
 * Labels and receipts: Label4x6, Label1x2, LabelStudio, ReceiptPaid, ReceiptNotPaid.
 * Barcodes are Code 128-B drawn as SVG in PHP (trusted markup, echoed raw).
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/views/sl/data/ops_common.php';
require_once CDP_ROOT . '/views/sl/data/shipments.php';
require_once CDP_ROOT . '/helpers/sl_qr.php';

/**
 * Code 128 (set B) as an SVG of the given size, with the text under it when $font > 0.
 * A 10-module quiet zone is kept clear on each side (scanners need it) and bars are drawn crisp.
 */
function sl_barcodeSvg(string $text, int $w, int $h, float $font = 0): string
{
    static $pat = ['212222','222122','222221','121223','121322','131222','122213','122312','132212','221213','221312','231212','112232','122132','122231','113222','123122','123221','223211','221132','221231','213212','223112','312131','311222','321122','321221','312212','322112','322211','212123','212321','232121','111323','131123','131321','112313','132113','132311','211313','231113','231311','112133','112331','132131','113123','113321','133121','313121','211331','231131','213113','213311','213131','311123','311321','331121','312113','312311','332111','314111','221411','431111','111224','111422','121124','121421','141122','141221','112214','112412','122114','122411','142112','142211','241211','221114','413111','241112','134111','111242','121142','121241','114212','124112','124211','411212','421112','421211','212141','214121','412121','111143','111341','131141','114113','114311','411113','411311','113141','114131','311141','411131','211412','211214','211232','2331112'];
    $text = preg_replace('/[^\x20-\x7E]/', '', $text);
    if ($text === '') {
        return '';
    }
    // Set B for text, set C (two digits per symbol) for runs of 4+ digits: shorter code, wider bars.
    $codes = [];
    $set = '';
    $len = strlen($text);
    for ($i = 0; $i < $len;) {
        $run = strspn($text, '0123456789', $i);
        if ($run >= 4 || ($run >= 2 && $set === 'C')) {
            if ($run % 2 === 1) { // odd run: the first digit goes in set B
                if ($set !== 'B') { $codes[] = $set === '' ? 104 : 100; $set = 'B'; }
                $codes[] = ord($text[$i]) - 32;
                $i++; $run--;
            }
            if ($set !== 'C') { $codes[] = $set === '' ? 105 : 99; $set = 'C'; }
            for ($e = $i + $run; $i < $e; $i += 2) $codes[] = (int) substr($text, $i, 2);
            continue;
        }
        if ($set !== 'B') { $codes[] = $set === '' ? 104 : 100; $set = 'B'; }
        $codes[] = ord($text[$i]) - 32;
        $i++;
    }
    $sum = $codes[0];
    foreach (array_slice($codes, 1) as $i => $c) {
        $sum += $c * ($i + 1);
    }
    $codes[] = $sum % 103;
    $codes[] = 106;
    $mods = [];
    foreach ($codes as $c) {
        foreach (str_split($pat[$c]) as $k => $n) {
            $mods[] = [(int) $n, $k % 2 === 0];
        }
    }
    $quiet = 10;
    $total = array_sum(array_map(fn($m) => $m[0], $mods)) + 2 * $quiet;
    $unit = $w / max(1, $total);
    $barH = $font > 0 ? $h - $font - 3 : $h;
    $x = $quiet * $unit;
    $out = '<svg class="sl-bc" viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '" role="img" aria-label="Barcode ' . h($text) . '" shape-rendering="crispEdges"><rect width="' . $w . '" height="' . $h . '" fill="#fff"></rect>';
    foreach ($mods as [$n, $bar]) {
        if ($bar) {
            $out .= '<rect x="' . number_format($x, 2, '.', '') . '" y="0" width="' . number_format($n * $unit, 2, '.', '') . '" height="' . $barH . '" fill="#000"></rect>';
        }
        $x += $n * $unit;
    }
    if ($font > 0) {
        $out .= '<text x="' . ($w / 2) . '" y="' . ($h - 1) . '" text-anchor="middle" style="font:' . $font . 'px JetBrains Mono,monospace;fill:#000;letter-spacing:.08em">' . h($text) . '</text>';
    }
    return $out . '</svg>';
}

/** Scannable QR code for $text (helpers/sl_qr.php). Kept under this name for existing callers. */
function sl_codeGridSvg(string $text, int $size): string
{
    return sl_qrSvg($text, $size, 'Scan to track');
}

function sl_labelOptions(): array
{
    $def = ['size' => '46', 'sender' => 1, 'fragile' => 0, 'phone' => 1, 'qr' => 1, 'contents' => 1, 'value' => 1, 'customs' => 0, 'code' => 1, 'postage' => 0, 'care' => 1, 'ret' => 0, 'copies' => 1, 'printer' => 'Default printer', 'qrData' => 'details'];
    $o = json_decode((string) sl_slSetting('label_options', ''), true);
    $s = json_decode((string) sl_slSetting('label_small_options', ''), true);
    return ['big' => array_merge($def, is_array($o) ? $o : []), 'small' => array_merge(['use' => 'locker', 'size' => '17', 'tag' => 1, 'barcode' => 1, 'code' => 0], is_array($s) ? $s : [])];
}

/** Everything a label prints for one shipment (air: cdb_add_order) or package (sea: cdb_customers_packages). */
function sl_labelFor(int $id, string $type = 'air'): ?array
{
    global $core;
    if ($type === 'sea') {
        $o = sl_row('SELECT * FROM cdb_customers_packages WHERE order_id = :id', [':id' => $id]);
        if (!$o || !sl_opsInScope($o)) return null;
        $items = sl_rows('SELECT order_item_description AS d, order_item_quantity AS q, order_item_declared_value AS v FROM cdb_customers_packages_detail WHERE order_id = :id', [':id' => $id]);
        $sender = sl_row('SELECT fname, lname, phone, locker FROM cdb_users WHERE id = :id', [':id' => (int) $o->sender_id]);
        $recip = $sender;
        $addr = sl_row('SELECT sa.address, c.name AS city, k.name AS country FROM cdb_senders_addresses sa LEFT JOIN cdb_cities c ON c.id = sa.city LEFT JOIN cdb_countries k ON k.id = sa.country WHERE sa.id_addresses = :a', [':a' => (int) $o->sender_address_id]);
        $toAddr = $addr ? $addr->address : '';
        $toCity = $addr ? trim($addr->city . ', ' . $addr->country, ', ') : '';
        $fromCity = '';
        $carrierNo = (string) ($o->tracking_purchase ?? '');
    } else {
        $s = sl_shipmentLoad($id);
        if (!$s) return null;
        $o = $s['o'];
        $items = array_map(fn($i) => (object) ['d' => $i->order_item_description, 'q' => $i->order_item_quantity, 'v' => $i->order_item_declared_value], $s['items']);
        $sender = $s['sender'];
        $recip = $s['recip'] ?: $sender;
        $a = $s['addr'];
        $user = ($s['rtype'] === 'user' || !$s['recip']);
        $toAddr = $a ? ($user ? $a->sender_address : $a->recipient_address) : '';
        $toCity = $a ? trim(($user ? $a->sender_city : $a->recipient_city) . ', ' . ($user ? $a->sender_country : $a->recipient_country), ', ') : '';
        $fromCity = $a ? trim($a->sender_city . ', ' . $a->sender_country, ', ') : '';
        $carrierNo = (string) ($s['carrier']->tracking_number ?? '');
    }
    $track = $o->order_prefix . $o->order_no;
    $pieces = max(1, (int) array_sum(array_map(fn($i) => (int) $i->q, $items)));
    $declared = array_sum(array_map(fn($i) => (float) $i->v * max(1, (float) $i->q), $items));
    $courier = sl_row('SELECT name_com FROM cdb_courier_com WHERE id = :id', [':id' => (int) $o->order_courier]);
    $deli = sl_row('SELECT delitime FROM cdb_delivery_time WHERE id = :id', [':id' => (int) $o->order_deli_time]);
    $pack = sl_row('SELECT name_pack FROM cdb_packaging WHERE id = :id', [':id' => (int) $o->order_package]);
    $office = sl_row('SELECT name_off, address, city FROM cdb_offices WHERE id = :id', [':id' => (int) $o->origin_off]);
    $sea = $type === 'sea' || (int) $o->order_item_category === 27;
    $phone = (string) ($recip->phone ?? '');
    $masked = strlen($phone) > 6 ? substr($phone, 0, max(3, strlen($phone) - 7)) . ' *** ' . substr($phone, -4) : $phone;
    $bay = '';
    // the column is warehouse_bay (migrations/2026_11_06_warehouse_packages.sql), not bay
    if (sl_columnExists($type === 'sea' ? 'cdb_customers_packages' : 'cdb_add_order', 'warehouse_bay')) {
        $bay = (string) ($o->warehouse_bay ?? '');
    }
    return [
        'id' => $id, 'type' => $type, 'track' => $track, 'carrier' => $courier->name_com ?? '', 'carrierNo' => $carrierNo,
        'mode' => $sea ? 'SEA' : 'AIR', 'svc' => $deli->delitime ?? '', 'pack' => $pack->name_pack ?? 'Parcel',
        'locker' => (string) ($sender->locker ?? ''), 'bay' => $bay, 'dg' => !empty($o->is_dangerous_good),
        'name' => mb_strtoupper(trim(($recip->fname ?? '') . ' ' . ($recip->lname ?? ''))), 'phone' => $masked,
        'addr' => $toAddr, 'to' => mb_strtoupper($toCity ?: '—'), 'from' => mb_strtoupper($fromCity ?: ($office->city ?? '')),
        'hub' => mb_strtoupper(($core->site_name ?? 'Swift Lane') . ($office ? ' · ' . $office->name_off : '')), 'hubAddr' => trim(($office->address ?? '') . ', ' . ($office->city ?? ''), ', '),
        'weight' => (float) $o->total_weight > 0 ? sl_weight($o->total_weight) : '—', 'pieces' => $pieces,
        'received' => sl_date($o->order_date, 'M j'),
        'contents' => implode(', ', array_map(fn($i) => $i->d . ' ×' . (0 + $i->q), $items)) ?: 'Not declared', 'declared' => sl_money($declared),
        'total' => sl_money($o->total_order), 'paid' => sl_shipPaid($o->status_invoice),
        'url' => rtrim((string) ($core->site_url ?? ''), '/') . '/track.php?order_track=' . rawurlencode($track),
        'site' => (string) ($core->site_name ?? 'Swift Lane'), 'ini' => sl_initials((string) ($core->site_name ?? 'SL')),
        'sender' => mb_strtoupper(trim(($sender->fname ?? '') . ' ' . ($sender->lname ?? ''))), 'senderCity' => mb_strtoupper($fromCity),
        'siteUrl' => preg_replace('#^https?://#', '', rtrim((string) ($core->site_url ?? ''), '/')), 'sitePhone' => (string) ($core->c_phone ?? ''),
    ];
}

/**
 * Barcodes and QR for one 4 x 6 label: the top barcode is our tracking number (SAS...), the bottom
 * barcode is the courier's original tracking number, and the QR carries the whole package
 * (or only the tracking link when Label Studio's "QR contents" is set to link).
 */
function sl_labelCodes(array $l, array $o, string $piece): array
{
    $qrText = $l['url'];
    if (($o['qrData'] ?? 'details') === 'details') {
        $qrText = implode("\n", array_filter([
            ($l['site'] ?: 'Swift Lane') . ' · ' . $l['track'],
            'To: ' . $l['name'] . ($l['locker'] !== '' ? ' · Locker ' . $l['locker'] : ''),
            'Route: ' . ($l['from'] ?: '—') . ' > ' . $l['to'],
            $l['carrier'] !== '' || $l['carrierNo'] !== '' ? 'Courier: ' . trim($l['carrier'] . ' ' . $l['carrierNo']) : '',
            'Service: ' . $l['mode'] . ($l['svc'] !== '' ? ' · ' . $l['svc'] : ''),
            'Weight: ' . $l['weight'] . ' · Piece ' . $piece,
            'Contents: ' . mb_substr($l['contents'], 0, 120),
            'Received: ' . $l['received'] . ' · ' . ($l['paid'] ? 'PAID' : 'PAYMENT DUE'),
            'Track: ' . $l['url'],
        ]));
    }
    return [
        'bcTrack' => sl_barcodeSvg($l['track'], 230, 56),
        'bc' => $l['carrierNo'] !== '' ? sl_barcodeSvg($l['carrierNo'], 350, 80, 13) : '',
        'qr' => sl_qrSvg($qrText, 104, 'Package details') ?: sl_qrSvg($l['url'], 104, 'Scan to track'),
    ];
}

/** Print data for print_label_ship.php: one or more shipments, size 46 (4x6) or 12 (1x2). */
function sl_labelPrintData(array $ids, string $size, string $type = 'air'): array
{
    $opt = sl_labelOptions();
    $copies = max(1, min(10, (int) ($_GET['copies'] ?? $opt['big']['copies'])));
    $labels = [];
    foreach ($ids as $id) {
        $l = sl_labelFor((int) $id, $type);
        if (!$l) continue;
        $per = $size === '12' ? 1 : $l['pieces'];
        if (isset($_GET['pieces'])) $per = max(1, min(50, (int) $_GET['pieces']));
        for ($p = 1; $p <= $per; $p++) {
            for ($c = 0; $c < $copies; $c++) {
                $labels[] = $l + ['piece' => $p . ' / ' . $per, 'bcSmall' => sl_barcodeSvg($size === '12' && $opt['small']['use'] === 'carrier' && $l['carrierNo'] !== '' ? $l['carrierNo'] : $l['track'], 178, 40, 6.5)] + sl_labelCodes($l, $opt['big'], $p . ' / ' . $per);
            }
        }
    }
    return ['labels' => $labels, 'o' => $opt['big'], 's' => $opt['small'], 'size' => $size, 'p' => sl_printerOptions(), 'test' => false, 'lay' => sl_labelLayout()];
}

/** A calibration label with sample data, to check alignment and darkness on a printer. */
function sl_labelTestData(string $size): array
{
    global $core;
    $opt = sl_labelOptions();
    $track = 'TEST000123';
    $l = ['id' => 0, 'type' => 'air', 'track' => $track, 'carrier' => 'Test carrier', 'carrierNo' => '1Z999AA10123456784', 'mode' => 'AIR', 'svc' => 'Express', 'pack' => 'Small box',
        'locker' => '000000', 'bay' => '', 'dg' => false, 'name' => 'TEST CUSTOMER NAME', 'phone' => '+233 *** 0000', 'addr' => '12 Sample Street, East Legon', 'to' => 'ACCRA, GHANA', 'from' => 'NEW YORK, USA',
        'hub' => mb_strtoupper((string) ($core->site_name ?? 'Swift Lane')), 'hubAddr' => '', 'weight' => '2.5 lbs', 'pieces' => 1, 'received' => date('M j'), 'contents' => 'Printer test ×1', 'declared' => sl_money(0),
        'total' => sl_money(0), 'paid' => true, 'url' => rtrim((string) ($core->site_url ?? ''), '/') . '/track.php', 'site' => (string) ($core->site_name ?? 'Swift Lane'), 'ini' => 'SL',
        'sender' => 'SENDER NAME', 'senderCity' => 'NEW YORK, USA', 'siteUrl' => preg_replace('#^https?://#', '', rtrim((string) ($core->site_url ?? ''), '/')), 'sitePhone' => (string) ($core->c_phone ?? '')];
    $l += ['piece' => '1 / 1', 'bcSmall' => sl_barcodeSvg($track, 178, 40, 6.5)] + sl_labelCodes($l, $opt['big'], '1 / 1');
    return ['labels' => [$l], 'o' => $opt['big'], 's' => $opt['small'], 'size' => $size, 'p' => sl_printerOptions(), 'test' => true, 'lay' => sl_labelLayout()];
}

/**
 * Receipt layout, set in Receipt Studio (receipt_studio.php) and stored in cdb_sl_settings receipt_options.
 * Sections print in the saved order; each can be switched off. Field switches fine-tune a section.
 */
function sl_receiptCatalog(): array
{
    return [
        'blocks' => [
            'logo'     => ['Swift Lane logo', 'Brand logo at the top of the receipt'],
            'contact'  => ['Company contact', 'Phone, email and address under the logo'],
            'barcode'  => ['Tracking barcode', 'Receipt number, date and a scannable barcode'],
            'stamp'    => ['Paid / Not paid stamp', 'Large status banner'],
            'bill'     => ['Bill to', 'Customer name, locker, location, phone'],
            'shipment' => ['Shipment', 'Courier, category, carrier tracking number'],
            'items'    => ['Items', 'Quantities, descriptions and weight'],
            'charges'  => ['Charges', 'Freight, fees and the total'],
            'payment'  => ['Payment', 'Method, reference, date (paid receipts)'],
            'howtopay' => ['How to pay', 'Pay instructions (unpaid receipts)'],
            'qr'       => ['Tracking QR code', 'Customer scans it to open the tracking page'],
            'footer'   => ['Footer note', 'Tracking link and your closing message'],
        ],
        'fields' => [
            'billPhone'  => ['Bill to · phone', 'bill'],
            'billLoc'    => ['Bill to · location', 'bill'],
            'carrierNo'  => ['Shipment · carrier tracking #', 'shipment'],
            'carrierBc'  => ['Shipment · carrier barcode', 'shipment'],
            'weight'     => ['Items · package weight', 'items'],
            'breakdown'  => ['Charges · line-by-line breakdown', 'charges'],
            'cedis'      => ['Charges · amount in cedis', 'charges'],
            'payRef'     => ['Payment · reference', 'payment'],
            'payBy'      => ['Payment · received by', 'payment'],
            'trackLink'  => ['Footer · tracking link', 'footer'],
        ],
    ];
}

function sl_receiptOptionsClean($in): array
{
    $cat = sl_receiptCatalog();
    $in = is_array($in) ? $in : [];
    $order = [];
    foreach ((array) ($in['order'] ?? []) as $k) {
        if (is_string($k) && isset($cat['blocks'][$k]) && !in_array($k, $order, true)) $order[] = $k;
    }
    foreach (array_keys($cat['blocks']) as $k) {
        if (!in_array($k, $order, true)) $order[] = $k; // new sections join at the end
    }
    $hide = [];
    foreach ((array) ($in['hide'] ?? []) as $k => $v) {
        if (isset($cat['blocks'][$k]) && !empty($v)) $hide[$k] = 1;
    }
    $fields = [];
    foreach (array_keys($cat['fields']) as $k) {
        $fields[$k] = (int) (array_key_exists($k, (array) ($in['fields'] ?? [])) ? !empty($in['fields'][$k]) : 1);
    }
    $note = mb_substr(trim(strip_tags((string) ($in['note'] ?? ''))), 0, 300);
    $title = mb_substr(trim(strip_tags((string) ($in['title'] ?? ''))), 0, 40);
    $logo = ($in['logo'] ?? '') === 'black' ? 'black' : 'color';
    return ['order' => $order, 'hide' => $hide, 'fields' => $fields, 'note' => $note, 'title' => $title, 'logo' => $logo];
}

/** Sample receipt for Receipt Studio when there is no shipment to preview with. */
function sl_receiptSample(bool $paid): array
{
    global $core;
    $url = rtrim((string) ($core->site_url ?? ''), '/') . '/track.php?order_track=SAMPLE0001';
    $rate = (float) ($core->exchange_rate ?? 0);
    return [
        'id' => 0, 'type' => 'air', 'track' => 'SAMPLE0001', 'carrier' => 'FEDEX GROUND', 'carrierNo' => '1Z999AA10123456784', 'mode' => 'AIR', 'svc' => '24 - 48 Hours',
        'weight' => '1.0 lbs', 'url' => $url, 'serial' => '', 'fsPay' => null, 'paidState' => $paid, 'date' => date('M j, Y · H:i'), 'office' => '',
        'bill' => ['name' => 'SAMPLE CUSTOMER', 'locker' => '000000', 'phone' => '+233 000 000 000', 'loc' => 'ACCRA, GHANA'],
        'items' => [['q' => 1, 'd' => 'Sample item']], 'charges' => [['l' => 'Freight · 1.0 lbs × ' . sl_money(8), 'v' => sl_money(8)]],
        'total' => sl_money(8), 'totalRaw' => 8.0, 'ghs' => $rate > 0 ? '₵' . number_format(8 * $rate, 2) : '', 'due' => '',
        'pay' => $paid ? ['method' => 'Mobile Money', 'ref' => 'REF123456', 'on' => date('M j, Y · H:i'), 'by' => 'Online'] : null,
        'bcTrack' => sl_barcodeSvg('SAMPLE0001', 340, 74, 12), 'bcCarrier' => sl_barcodeSvg('1Z999AA10123456784', 340, 60, 10), 'qr' => sl_codeGridSvg($url, 120),
        'company' => ['name' => (string) ($core->site_name ?? 'Swift Lane'), 'phone' => (string) ($core->c_phone ?? ''), 'mail' => (string) ($core->site_email ?? ''), 'addr' => trim(($core->c_address ?? '') . ', ' . ($core->c_city ?? '') . ', ' . ($core->c_country ?? ''), ', ')],
        'payUrl' => '#',
    ];
}

function sl_receiptOptions(): array
{
    return sl_receiptOptionsClean(json_decode((string) sl_slSetting('receipt_options', ''), true));
}

/** Receipt (paid / not paid) for an air shipment or a sea package. */
function sl_receiptData(int $id, string $type): ?array
{
    global $core;
    $l = sl_labelFor($id, $type);
    if (!$l) return null;
    $tbl = $type === 'sea' ? 'cdb_customers_packages' : 'cdb_add_order';
    $o = sl_row("SELECT * FROM $tbl WHERE order_id = :id", [':id' => $id]);
    $sender = sl_row('SELECT fname, lname, phone, locker, email FROM cdb_users WHERE id = :id', [':id' => (int) $o->sender_id]);
    $items = $type === 'sea'
        ? sl_rows('SELECT order_item_description AS d, order_item_quantity AS q FROM cdb_customers_packages_detail WHERE order_id = :id', [':id' => $id])
        : sl_rows('SELECT order_item_description AS d, order_item_quantity AS q FROM cdb_add_order_item WHERE order_id = :id', [':id' => $id]);
    $charges = [];
    $w = (float) $o->total_weight;
    $charges[] = ['l' => 'Freight' . ($w > 0 && (float) $o->value_weight > 0 ? ' · ' . sl_weight($w) . ' × ' . sl_money($o->value_weight) : ''), 'v' => sl_money($o->sub_total)];
    foreach ([['total_tax_discount', 'Discount', -1], ['total_tax_insurance', 'Insurance', 1], ['total_tax_custom_tariffis', 'Customs tariff', 1], ['total_tax', 'Tax', 1], ['total_declared_value', 'Declared value fee', 1], ['total_fixed_value', 'Handling', 1], ['total_reexp', 'Re-shipping', 1]] as [$col, $lab, $sgn]) {
        if (isset($o->$col) && (float) $o->$col != 0) $charges[] = ['l' => $lab, 'v' => ($sgn < 0 ? '−' : '') . sl_money(abs((float) $o->$col))];
    }
    $pay = null;
    if (sl_shipPaid($o->status_invoice)) {
        $track = $o->order_prefix . $o->order_no;
        $g = sl_row('SELECT gateway, payment_transaction, date_payment FROM cdb_payments_gateway WHERE order_track = :t ORDER BY id DESC', [':t' => $track]);
        $c = $type === 'air' ? sl_row('SELECT c.number_reference, c.charge_date, m.name_pay, u.fname, u.lname FROM cdb_charges_order c LEFT JOIN cdb_met_payment m ON m.id = c.payment_type LEFT JOIN cdb_users u ON u.id = c.user_id WHERE c.order_id = :id ORDER BY c.id_charge DESC', [':id' => $id]) : null;
        $mode = sl_row('SELECT name_pay FROM cdb_met_payment WHERE id = :id', [':id' => (int) $o->order_pay_mode]);
        $pay = ['method' => str_replace('_', ' ', (string) ($g->gateway ?? ($c->name_pay ?? ($mode->name_pay ?? '—')))), 'ref' => $g->payment_transaction ?? ($c->number_reference ?? '—'), 'on' => sl_date($o->payment_date ?: ($g->date_payment ?? ($c->charge_date ?? '')), 'M j, Y · H:i'), 'by' => $c ? trim(($c->fname ?? '') . ' ' . ($c->lname ?? '')) : 'Online'];
    }
    $rate = (float) ($core->exchange_rate ?? 0);
    // Consolidation serial ("n of N") and the Financial Sheet payment that cleared
    // this package, as on the previous invoice layout (views/print/partials/inv_data_helpers.php).
    $serial = '';
    $fsPay = null;
    if ($type === 'air') {
        require_once CDP_ROOT . '/helpers/querys.php';
        $sr = function_exists('cdp_getOrderFinancialSerial') ? cdp_getOrderFinancialSerial($o->order_prefix, $o->order_no, (int) ($_GET['consolidate_id'] ?? 0)) : null;
        if ($sr) {
            $cn = sl_row('SELECT c_prefix, c_no FROM cdb_consolidate WHERE consolidate_id = :c', [':c' => (int) $sr['consolidate_id']]);
            $serial = ($cn ? $cn->c_prefix . $cn->c_no . ' · ' : '') . (int) $sr['sn'] . ' of ' . (int) $sr['total'];
        }
        if (is_file(CDP_ROOT . '/helpers/fs_payments.php')) {
            require_once CDP_ROOT . '/helpers/fs_payments.php';
            $fp = function_exists('cdp_fsPaymentForOrder') ? cdp_fsPaymentForOrder($id) : null;
            if ($fp) {
                $modes = ['cash' => 'Cash', 'paystack' => 'Mobile Money (Paystack)', 'hubtel' => 'Mobile Money (Hubtel)', 'paypal' => 'PayPal'];
                $fsPay = ['mode' => $modes[strtolower((string) $fp->mode)] ?? ucfirst((string) $fp->mode), 'amount' => '₵' . number_format((float) ($fp->amount_ghs ?? 0), 2), 'ref' => (string) ($fp->reference ?? ''), 'on' => sl_date($fp->recorded_at ?? '', 'M j, Y · H:i')];
            }
        }
    }
    return $l + [
        'serial' => $serial, 'fsPay' => $fsPay,
        'paidState' => sl_shipPaid($o->status_invoice), 'date' => sl_date($o->order_datetime ?: $o->order_date, 'M j, Y · H:i'),
        'office' => (string) (sl_scalar('SELECT name_off FROM cdb_offices WHERE id = :id', [':id' => (int) $o->origin_off], '') ?: ''),
        'bill' => ['name' => trim(($sender->fname ?? '') . ' ' . ($sender->lname ?? '')), 'locker' => $sender->locker ?? '—', 'phone' => $sender->phone ?? '—', 'loc' => $l['to']],
        'items' => array_map(fn($i) => ['q' => 0 + $i->q, 'd' => $i->d], $items),
        'charges' => $charges, 'totalRaw' => (float) $o->total_order, 'ghs' => $rate > 0 ? '₵' . number_format((float) $o->total_order * $rate, 2) : '',
        'due' => !empty($o->due_date) ? sl_date($o->due_date) : '', 'pay' => $pay,
        'bcTrack' => sl_barcodeSvg($l['track'], 340, 74, 12), 'bcCarrier' => $l['carrierNo'] !== '' ? sl_barcodeSvg($l['carrierNo'], 340, 60, 10) : '',
        'qr' => sl_codeGridSvg($l['url'], 120),
        'company' => ['name' => (string) ($core->site_name ?? 'Swift Lane'), 'phone' => (string) ($core->c_phone ?? ''), 'mail' => (string) ($core->site_email ?? ''), 'addr' => trim(($core->c_address ?? '') . ', ' . ($core->c_city ?? '') . ', ' . ($core->c_country ?? ''), ', ')],
        'payUrl' => $type === 'air' ? 'add_payment_gateways_courier.php?id_order=' . $id : 'add_payment_gateways_package.php?id_order=' . $id,
    ];
}

/** Label Studio: saved options and a live preview with a real shipment. */
function sl_labelStudioData($user, int $id, string $q, string $view): array
{
    $opt = sl_labelOptions();
    $scope = sl_opsScope('a');
    if ($id <= 0 && $q !== '') {
        $id = (int) sl_scalar("SELECT a.order_id FROM cdb_add_order a WHERE a.is_deleted = 0 $scope AND CONCAT(a.order_prefix, a.order_no) LIKE :q ORDER BY a.order_id DESC", [':q' => '%' . $q . '%'], 0);
    }
    $recent = sl_rows("SELECT a.order_id, a.order_prefix, a.order_no FROM cdb_add_order a WHERE a.is_deleted = 0 $scope ORDER BY a.order_id DESC LIMIT 4");
    if ($id <= 0 && $recent) {
        $id = (int) $recent[0]->order_id;
    }
    $l = $id > 0 ? sl_labelFor($id, 'air') : null;
    $view = in_array($view, ['46', '44', '12'], true) ? $view : ($opt['big']['size'] ?: '46');
    $o = $opt['big'];
    $on = 0;
    foreach (['sender', 'fragile', 'phone', 'qr', 'contents', 'value', 'customs', 'code', 'postage', 'care', 'ret'] as $k) {
        $on += !empty($o[$k]) ? 1 : 0;
    }
    $d = [
        'o' => $o, 's' => $opt['small'], 'l' => $l, 'q' => $q, 'view' => $view, 'onCount' => $on,
        'recent' => array_map(fn($r) => ['id' => (int) $r->order_id, 'track' => $r->order_prefix . $r->order_no], $recent),
        'canEdit' => $user->cdp_hasPermission('view_tools'), 'lay' => sl_labelLayout(), 'blocks' => sl_labelBlocks(),
    ];
    if ($l) {
        $d['bc'] = sl_barcodeSvg($l['track'], 340, 70, 12);
        $d['bcLocker'] = sl_barcodeSvg($l['locker'] ?: $l['track'], 210, 36);
        $d['bcRet'] = sl_barcodeSvg('RET-' . $l['track'], 300, 60, 11);
        $d['bcSmall'] = sl_barcodeSvg($opt['small']['use'] === 'carrier' && $l['carrierNo'] !== '' ? $l['carrierNo'] : $l['track'], 178, 40, 6.5);
        $d['qr'] = sl_codeGridSvg($l['url'], 78);
        $n = $l['pieces'] * max(1, (int) $o['copies']);
        $d['summary'] = $n . ' label' . ($n === 1 ? '' : 's') . ' · ' . $l['pieces'] . ' piece' . ($l['pieces'] === 1 ? '' : 's') . ' × ' . (int) $o['copies'] . ' cop' . ((int) $o['copies'] === 1 ? 'y' : 'ies') . ' · ' . $on . ' add-ons';
    } else {
        $d['summary'] = 'No shipment loaded';
    }
    return $d;
}

/**
 * Printer settings. A team default (cdb_sl_settings printer_options, set in Label Studio by people with
 * view_tools) and a personal override per user (printer_options_u{id}): each desk has its own printer.
 * Saved by ajax/sl/printer_options_ajax.php, applied by the label print pages.
 */
function sl_printerDefaults(): array
{
    return ['type' => 'thermal', 'dpi' => '203', 'big' => '46', 'small' => '21', 'dx' => 0.0, 'dy' => 0.0, 'scale' => 100, 'margin' => 0.0,
        'dark' => 0, 'border' => 1, 'logo' => 1, 'auto' => 0, 'name' => 'Default printer'];
}

/** Clean one set of printer settings (anything unknown falls back to the default). */
function sl_printerClean(array $in): array
{
    $d = sl_printerDefaults();
    $o = [];
    $o['type'] = in_array($in['type'] ?? '', ['thermal', 'laser'], true) ? $in['type'] : $d['type'];
    $o['dpi'] = in_array((string) ($in['dpi'] ?? ''), ['203', '300', '600'], true) ? (string) $in['dpi'] : $d['dpi'];
    $o['big'] = in_array((string) ($in['big'] ?? ''), ['46', '44', 'a4'], true) ? (string) $in['big'] : $d['big'];
    $o['small'] = in_array((string) ($in['small'] ?? ''), ['21', '12', '5025'], true) ? (string) $in['small'] : $d['small'];
    $o['dx'] = max(-15, min(15, round((float) ($in['dx'] ?? 0), 1)));
    $o['dy'] = max(-15, min(15, round((float) ($in['dy'] ?? 0), 1)));
    $o['scale'] = max(80, min(120, (int) ($in['scale'] ?? 100)));
    $o['margin'] = max(0, min(15, round((float) ($in['margin'] ?? 0), 1)));
    foreach (['dark', 'border', 'logo', 'auto'] as $k) $o[$k] = (int) !empty($in[$k]);
    $o['name'] = mb_substr(trim(strip_tags((string) ($in['name'] ?? ''))), 0, 80) ?: $d['name'];
    return $o;
}

/** Effective settings for a user, and where they come from. */
function sl_printerOptions(?int $uid = null): array
{
    $uid = $uid ?? (int) ($_SESSION['userid'] ?? 0);
    $team = json_decode((string) sl_slSetting('printer_options', ''), true);
    $mine = $uid ? json_decode((string) sl_slSetting('printer_options_u' . $uid, ''), true) : null;
    $base = sl_printerClean(array_merge(sl_printerDefaults(), is_array($team) ? $team : []));
    return is_array($mine) ? sl_printerClean(array_merge($base, $mine)) + ['from' => 'mine', 'team' => $base] : $base + ['from' => is_array($team) ? 'team' : 'default', 'team' => $base];
}

/** Paper size in mm, and @page CSS, for a label kind under these settings. */
function sl_printerPage(array $p, string $kind): array
{
    $sizes = ['46' => [101.6, 152.4, '4 × 6 in'], '44' => [101.6, 101.6, '4 × 4 in'], 'a4' => [210, 297, 'A4 sheet'], '21' => [50.8, 25.4, '2 × 1 in'], '12' => [25.4, 50.8, '1 × 2 in'], '5025' => [50, 25, '50 × 25 mm']];
    [$w, $h, $l] = $sizes[$kind === 'small' ? $p['small'] : $p['big']];
    return ['w' => $w, 'h' => $h, 'label' => $l, 'css' => '@page{size:' . $w . 'mm ' . $h . 'mm;margin:' . (float) $p['margin'] . 'mm}'];
}

/** Inline style that applies the offsets, scale and darkness to a label. */
function sl_printerStyle(array $p): string
{
    return '--lb-dx:' . (float) $p['dx'] . 'mm;--lb-dy:' . (float) $p['dy'] . 'mm;--lb-scale:' . ((int) $p['scale'] / 100) . ';';
}

/** Receipt Studio: saved receipt layout and a preview with a real shipment (or a sample). */
function sl_receiptStudioData($user, int $id, string $q, string $as): array
{
    $scope = sl_opsScope('a');
    if ($id <= 0 && $q !== '') {
        $id = (int) sl_scalar("SELECT a.order_id FROM cdb_add_order a WHERE a.is_deleted = 0 $scope AND CONCAT(a.order_prefix, a.order_no) LIKE :q ORDER BY a.order_id DESC", [':q' => '%' . $q . '%'], 0);
    }
    $recent = sl_rows("SELECT a.order_id, a.order_prefix, a.order_no FROM cdb_add_order a WHERE a.is_deleted = 0 $scope ORDER BY a.order_id DESC LIMIT 4");
    if ($id <= 0 && $recent && $q === '') {
        $id = (int) $recent[0]->order_id;
    }
    $l = $id > 0 ? sl_labelFor($id, 'air') : null;
    $as = in_array($as, ['paid', 'due'], true) ? $as : 'paid';
    $src = 'print_invoice_package.php?' . ($l ? 'id=' . (int) $l['id'] . '&type=air' : 'sample=' . $as) . '&as=' . $as . '&embed=1';
    return [
        'o' => sl_receiptOptions(), 'cat' => sl_receiptCatalog(), 'l' => $l, 'q' => $q, 'as' => $as, 'src' => $src,
        'recent' => array_map(fn($r) => ['id' => (int) $r->order_id, 'track' => $r->order_prefix . $r->order_no], $recent),
        'canEdit' => $user->cdp_hasPermission('view_tools'),
    ];
}

/**
 * 4 x 6 label layout, arranged by drag and drop in Label Studio (cdb_sl_settings label_layout).
 * "from" and "cont" are switched with the existing sender / contents label options.
 */
function sl_labelBlocks(): array
{
    return [
        'top'   => ['Logo & service', 'Swift logo with AIR / SEA and the service'],
        'trk'   => ['Tracking barcode & QR', 'Our tracking number as a barcode, piece count, QR with the package details'],
        'to'    => ['Deliver to', 'Customer, locker, address'],
        'route' => ['Route', 'From → destination'],
        'facts' => ['Weight · pieces · date · payment', 'Four fact boxes'],
        'from'  => ['Sender', 'Who sent it'],
        'cont'  => ['Contents', 'Items and declared value'],
        'flags' => ['Handling flags', 'Fragile, dangerous goods, this side up'],
        'bc'    => ['Courier barcode', 'Original tracking number from the courier (FedEx, UPS...)'],
        'foot'  => ['Footer', 'Hub and tracking link'],
    ];
}

function sl_labelLayoutClean($in): array
{
    $cat = sl_labelBlocks();
    $in = is_array($in) ? $in : [];
    $order = [];
    foreach ((array) ($in['order'] ?? []) as $k) {
        if (is_string($k) && isset($cat[$k]) && !in_array($k, $order, true)) $order[] = $k;
    }
    foreach (array_keys($cat) as $k) {
        if (!in_array($k, $order, true)) $order[] = $k;
    }
    $hide = [];
    foreach ((array) ($in['hide'] ?? []) as $k => $v) {
        if (isset($cat[$k]) && !in_array($k, ['from', 'cont'], true) && !empty($v)) $hide[$k] = 1;
    }
    return ['order' => $order, 'hide' => $hide];
}

function sl_labelLayout(): array
{
    return sl_labelLayoutClean(json_decode((string) sl_slSetting('label_layout', ''), true));
}
