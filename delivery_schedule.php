<?php
// *************************************************************************
// * Delivery scheduling links (retired). Messages sent before the change  *
// * carried a signed link here for up to 14 days; customer delivery        *
// * bookings were removed from Notifications & Messages, so every link now *
// * shows a friendly "this link has closed" page with the company contact. *
// * Public page: no login, no data read from the token.                    *
// *************************************************************************

require_once("loader.php");
require_once("helpers/ops_db.php");
require_once("views/comms/public_shell.php");

$core = new Core();
$e = 'cdp_opsEsc';
$phone = trim((string) ($core->c_phone ?? ''));
$email = trim((string) ($core->site_email ?? ''));
http_response_code(410);
header('Cache-Control: no-store');
cdp_commPublicHead('Link closed', (string) $core->site_name);
echo '<h1>This link has closed</h1>';
echo '<p>Booking a delivery time online is no longer available. Our team will contact you to arrange the delivery of your parcel.</p>';
if ($phone !== '' || $email !== '') {
    echo '<p>Questions? Contact ' . $e((string) $core->site_name) . ($phone !== '' ? ' on <a href="tel:' . $e(preg_replace('/[^\d+]/', '', $phone)) . '">' . $e($phone) . '</a>' : '')
        . ($phone !== '' && $email !== '' ? ' or ' : '') . ($email !== '' ? ' at <a href="mailto:' . $e($email) . '">' . $e($email) . '</a>' : '') . '.</p>';
} else {
    echo '<p>Questions? Reply to the message you received and we will help you.</p>';
}
echo '<p class="muted">You can follow your parcel any time with its tracking number on our website.</p>';
cdp_commPublicFoot();
