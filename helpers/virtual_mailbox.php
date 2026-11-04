<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************

/**
 * Virtual mailbox addresses (cdb_virtual_mailbox_addresses) as a customer
 * sees them: every "LOCKER ID" placeholder — "(LOCKER ID)", "(LOCKER ID  )",
 * "LOCKER ID" — becomes the customer's own locker, and a multi-line address
 * keeps its lines. Addresses stored as plain text are escaped; an address an
 * admin stored with HTML is shown as stored, as before.
 */
if (!function_exists('cdp_mailboxAddressHtml')) {
    function cdp_mailboxAddressHtml($address, $locker)
    {
        $address = str_replace(["\r\n", "\r"], "\n", trim((string) $address));
        $locker  = trim((string) $locker);
        $isPlain = (strip_tags($address) === $address);
        $lockerOut = $isPlain ? $locker : htmlspecialchars($locker, ENT_QUOTES, 'UTF-8');
        if ($locker !== '') {
            $address = preg_replace('/\(?\s*locker\s*id\s*\)?/i', ' (' . $lockerOut . ') ', $address);
            $address = preg_replace('/[ \t]{2,}/', ' ', $address);
        }
        if (!$isPlain) {
            return $address;
        }
        $lines = array_map('trim', explode("\n", $address));
        return nl2br(htmlspecialchars(implode("\n", $lines), ENT_QUOTES, 'UTF-8'), false);
    }
}
