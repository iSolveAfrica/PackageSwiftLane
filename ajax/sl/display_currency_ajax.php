<?php
/** Sidebar USD/GHS switch: store the viewer's display currency. */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_login();
if (sl_userIs('client')) {
    sl_json(['success' => false, 'message' => 'Not available'], 403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sl_json(['success' => false, 'message' => 'POST required'], 405);
}
$want = sl_displayCurrency() === 'GHS' ? 'USD' : 'GHS';
if ($want === 'GHS' && sl_exchangeRateValue() <= 0) {
    sl_json(['success' => false, 'message' => 'Set an exchange rate first (Finance › Exchange rate).']);
}
sl_saveDisplayCurrency((int) ($_SESSION['userid'] ?? 0), $want);
sl_json(['success' => true, 'message' => $want === 'GHS' ? 'Showing amounts in GHS' : 'Showing amounts in USD', 'reload' => true]);
