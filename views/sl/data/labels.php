<?php
/**
 * Labels and receipts: Label4x6, Label1x2, LabelStudio, ReceiptPaid, ReceiptNotPaid.
 * Barcodes are Code 128-B drawn as SVG in PHP (trusted markup, echoed raw).
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/views/sl/data/ops_common.php';
require_once CDP_ROOT . '/views/sl/data/shipments.php';

/** Code 128 (set B) as an SVG of the given size, with the text under it when $font > 0. */
function sl_barcodeSvg(string $text, int $w, int $h, float $font = 0): string
{
    static $pat = ['212222','222122','222221','121223','121322','131222','122213','122312','132212','221213','221312','231212','112232','122132','122231','113222','123122','123221','223211','221132','221231','213212','223112','312131','311222','321122','321221','312212','322112','322211','212123','212321','232121','111323','131123','131321','112313','132113','132311','211313','231113','231311','112133','112331','132131','113123','113321','133121','313121','211331','231131','213113','213311','213131','311123','311321','331121','312113','312311','332111','314111','221411','431111','111224','111422','121124','121421','141122','141221','112214','112412','122114','122411','142112','142211','241211','221114','413111','241112','134111','111242','121142','121241','114212','124112','124211','411212','421112','421211','212141','214121','412121','111143','111341','131141','114113','114311','411113','411311','113141','114131','311141','411131','211412','211214','211232','2331112'];
    $text = preg_replace('/[^\x20-\x7E]/', '', $text);
    if ($text === '') {
        return '';
    }
    $codes = [104];
    foreach (str_split($text) as $ch) {
        $codes[] = ord($ch) - 32;
    }
    $sum = 104;
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
    $total = array_sum(array_map(fn($m) => $m[0], $mods));
    $unit = $w / max(1, $total);
    $barH = $font > 0 ? $h - $font - 3 : $h;
    $x = 0.0;
    $out = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '" role="img" aria-label="Barcode ' . h($text) . '">';
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

/** Simple square "QR-style" grid from a hash (visual locator for the tracking URL; not a scannable QR). */
function sl_codeGridSvg(string $text, int $size): string
{
    $n = 21;
    $cell = $size / $n;
    $bits = '';
    foreach (str_split(hash('sha256', $text) . hash('sha256', strrev($text))) as $hx) {
        $bits .= str_pad(base_convert($hx, 16, 2), 4, '0', STR_PAD_LEFT);
    }
    $out = '<svg viewBox="0 0 ' . $size . ' ' . $size . '" width="' . $size . '" height="' . $size . '" aria-hidden="true">';
    $finder = function ($r, $c) { foreach ([[0, 0], [0, 14], [14, 0]] as [$fr, $fc]) { if ($r >= $fr && $r < $fr + 7 && $c >= $fc && $c < $fc + 7) { $rr = $r - $fr; $cc = $c - $fc; return ($rr === 0 || $rr === 6 || $cc === 0 || $cc === 6 || ($rr >= 2 && $rr <= 4 && $cc >= 2 && $cc <= 4)) ? 1 : 0; } } return null; };
    $i = 0;
    for ($r = 0; $r < $n; $r++) {
        for ($c = 0; $c < $n; $c++) {
            $f = $finder($r, $c);
            $on = $f === null ? ($bits[$i++ % strlen($bits)] === '1') : (bool) $f;
            if ($on) {
                $out .= '<rect x="' . number_format($c * $cell, 2, '.', '') . '" y="' . number_format($r * $cell, 2, '.', '') . '" width="' . number_format($cell, 2, '.', '') . '" height="' . number_format($cell, 2, '.', '') . '" fill="#000"></rect>';
            }
        }
    }
    return $out . '</svg>';
}

function sl_labelOptions(): array
{
    $def = ['size' => '46', 'sender' => 1, 'fragile' => 0, 'phone' => 1, 'qr' => 1, 'contents' => 1, 'value' => 1, 'customs' => 0, 'code' => 1, 'postage' => 0, 'care' => 1, 'ret' => 0, 'copies' => 1, 'printer' => 'Default printer'];
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
    if (sl_columnExists($type === 'sea' ? 'cdb_customers_packages' : 'cdb_add_order', 'bay')) {
        $bay = (string) ($o->bay ?? '');
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
                $labels[] = $l + ['piece' => $p . ' / ' . $per, 'bc' => sl_barcodeSvg($l['track'], 350, 88, 13), 'bcSmall' => sl_barcodeSvg($size === '12' && $opt['small']['use'] === 'carrier' && $l['carrierNo'] !== '' ? $l['carrierNo'] : $l['track'], 178, 40, 6.5), 'qr' => sl_codeGridSvg($l['url'], 64)];
            }
        }
    }
    return ['labels' => $labels, 'o' => $opt['big'], 's' => $opt['small'], 'size' => $size];
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
        'bcTrack' => sl_barcodeSvg($l['track'], 170, 46), 'bcCarrier' => $l['carrierNo'] !== '' ? sl_barcodeSvg($l['carrierNo'], 340, 52, 9) : '',
        'qr' => sl_codeGridSvg($l['url'], 86),
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
        'canEdit' => $user->cdp_hasPermission('view_tools'),
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
