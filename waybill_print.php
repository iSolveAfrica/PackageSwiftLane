<?php
/** Printable cargo manifest / MAWB / house bills of one waybill (Waybills page "Print"). */
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_freight');
require_once 'views/sl/data/freight.php';
$id = (int) ($_GET['id'] ?? 0);
$fmt = in_array($_GET['fmt'] ?? 'iata', ['iata', 'customs', 'load', 'hawb'], true) ? (string) ($_GET['fmt'] ?? 'iata') : 'iata';
if (!sl_tableExists('cdb_waybills') || !($w = sl_row('SELECT w.*, s.voyage_no, s.origin AS s_origin, s.destination AS s_dest FROM cdb_waybills w LEFT JOIN cdb_freight_schedules s ON s.id = w.schedule_id WHERE w.id = :id', [':id' => $id]))) {
    http_response_code(404);
    exit('Waybill not found');
}
$items = sl_rows('SELECT * FROM cdb_waybill_items WHERE waybill_id = :w ORDER BY COALESCE(slot, 9999), id', [':w' => $id]);
if (function_exists('cdp_activityLog')) {
    cdp_activityLog(['module' => 'freight', 'action' => 'waybill_print', 'action_label' => 'Printed manifest', 'verb' => 'view', 'outcome' => 'success', 'summary' => "printed {$w->waybill_no} ($fmt)", 'entity_type' => 'waybill', 'entity_id' => $id, 'entity_label' => $w->waybill_no]);
}
sl_render('WaybillPrint', ['title' => 'Manifest ' . $w->waybill_no, 'plain' => true], [
    'w' => $w,
    'fmt' => $fmt,
    'items' => $items,
    'text' => $fmt === 'hawb' ? '' : sl_frManifestText($w, $items, $fmt, $core),
    'site' => (string) ($core->site_name ?? 'Swift Lane'),
    'address' => (string) ($core->c_address ?? ''),
]);
