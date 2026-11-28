<?php
// Recipients (Swift Lane UI). Customer view: own recipients only.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('view_recipients');
// ?edit=<id> opens the form next to the list on the web page; the phone layout has no such form, so it goes to
// the phone's own edit screen (a link made on the web page can be opened on a phone, e.g. in the installed app).
if (!empty($_GET['edit']) && is_file(__DIR__ . '/helpers/sl_device.php')) {
    require_once __DIR__ . '/helpers/sl_device.php';
    if (function_exists('sl_isPhone') && sl_isPhone()) {
        header('Location: recipients_edit.php?recipient=' . (int) $_GET['edit']);
        exit;
    }
}
require_once 'views/sl/data/recipients.php';
$d = sl_recipientsData($user, $core);
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="recipients-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name', 'Phone', 'E-mail', 'Country', 'Address', 'Added by', 'Parcels']);
    foreach ($d['rows'] as $r) fputcsv($out, [$r['name'], $r['phone'], $r['mail'], $r['region'], $r['addr'], $r['by'], $r['n']]);
    fclose($out);
    exit;
}
sl_render('Recipients', ['id' => 'recip', 'title' => 'Recipients', 'board' => 'Recipients'], $d);
