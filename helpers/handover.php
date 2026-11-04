<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************

/**
 * Handover — how a package leaves our hands, said as it happened.
 *
 *   Picked up (15)  the customer (or someone for them) collected it from us.
 *   Delivered (8)   we took it to the customer's door.
 *
 * Both are a finished handover: same pickup-code gate, same "no more edits"
 * rules, same place in reports. They differ only in what they say. Every
 * handover screen offers both, Picked up first, and every message, chip and
 * history line uses the word that matches the status that was saved.
 */

if (!defined('CDP_STATUS_DELIVERED')) {
    define('CDP_STATUS_DELIVERED', 8);
    define('CDP_STATUS_PICKED_UP', 15);
}

if (!function_exists('cdp_handoverStatuses')) {

    /** Both handover statuses, for SQL IN (...) lists and in_array checks. */
    function cdp_handoverStatuses()
    {
        return [CDP_STATUS_DELIVERED, CDP_STATUS_PICKED_UP];
    }

    /** "8, 15" for SQL. */
    function cdp_handoverStatusesSql()
    {
        return implode(', ', cdp_handoverStatuses());
    }

    /** Has the package been handed over (Delivered or Picked up)? */
    function cdp_isHandedOver($status)
    {
        return in_array((int) $status, cdp_handoverStatuses(), true);
    }

    /**
     * The status a handover form asked for: 'delivered' => 8, anything else
     * (including nothing) => 15 Picked up.
     */
    function cdp_handoverStatusFromInput($value)
    {
        return (strtolower(trim((string) $value)) === 'delivered') ? CDP_STATUS_DELIVERED : CDP_STATUS_PICKED_UP;
    }

    /** 'picked_up' | 'delivered' for a status. */
    function cdp_handoverKind($status)
    {
        return ((int) $status === CDP_STATUS_DELIVERED) ? 'delivered' : 'picked_up';
    }

    /** "Picked up" / "Delivered". */
    function cdp_handoverLabel($status)
    {
        return ((int) $status === CDP_STATUS_DELIVERED) ? 'Delivered' : 'Picked up';
    }

    /**
     * Sentence fragments for customer messages.
     *   cdp_handoverPhrase(15, true)  "have been picked up"
     *   cdp_handoverPhrase(8, false)  "has been delivered"
     */
    function cdp_handoverPhrase($status, $plural = false)
    {
        $verb = ((int) $status === CDP_STATUS_DELIVERED) ? 'delivered' : 'picked up';
        return ($plural ? 'have been ' : 'has been ') . $verb;
    }

    /**
     * The "Picked up / Delivered" choice for a handover form. Posts as
     * handover_type = picked_up | delivered.
     */
    function cdp_handoverChoiceHtml($name = 'handover_type', $default = 'picked_up')
    {
        $name = htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8');
        $isDelivered = ($default === 'delivered');
        $opt = function ($value, $label, $hint, $checked) use ($name) {
            $id = $name . '_' . $value;
            return '<div class="custom-control custom-radio custom-control-inline">'
                 . '<input type="radio" class="custom-control-input" name="' . $name . '" id="' . $id . '" value="' . $value . '"' . ($checked ? ' checked' : '') . '>'
                 . '<label class="custom-control-label" for="' . $id . '"><b>' . $label . '</b> <span class="text-muted small">' . $hint . '</span></label>'
                 . '</div>';
        };
        return '<div class="form-group cdp-handover-choice">'
             . '<label class="control-label col-form-label d-block">How Was It Handed Over?</label>'
             . $opt('picked_up', 'Picked up', '(collected from us)', !$isDelivered)
             . $opt('delivered', 'Delivered', '(taken to the customer)', $isDelivered)
             . '</div>';
    }

    /**
     * Is this status "ready"? 'pickup' (32 Ready for PickUp, 6 Available at
     * the office), 'delivery' (7 On Route / out for delivery), or ''.
     * Operator-made statuses are matched by name.
     */
    function cdp_statusReadyKind($statusId, $label = '')
    {
        $id = (int) $statusId;
        if (in_array($id, [32, 6], true)) {
            return 'pickup';
        }
        if ($id === 7) {
            return 'delivery';
        }
        if ($id > 0 && in_array($id, [1, 2, 3, 4, 5, 8, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 21, 23, 25], true)) {
            return '';
        }
        $l = strtolower(str_replace('_', ' ', (string) $label));
        if (preg_match('/ready\s*for\s*pick\s*-?\s*up/', $l)) {
            return 'pickup';
        }
        if (preg_match('/ready\s*for\s*delivery|out\s*for\s*delivery|on\s*route/', $l)) {
            return 'delivery';
        }
        return '';
    }

    /**
     * The SMS automation a status change belongs to (helpers/hubtel_sms.php):
     * 'ready', 'handed_over', or '' for every status that texts nobody.
     * Registration is not a status change; its callers say 'registered'.
     */
    function cdp_statusSmsEvent($statusId, $label = '')
    {
        $id = (int) $statusId;
        if (cdp_isHandedOver($id)) {
            return 'handed_over';
        }
        if (cdp_statusReadyKind($id, $label) !== '') {
            return 'ready';
        }
        if ($id <= 0) {
            $l = strtolower(str_replace('_', ' ', (string) $label));
            if (strpos($l, 'not picked') === false && preg_match('/\b(delivered|picked\s*up)\b/', $l)) {
                return 'handed_over';
            }
        }
        return '';
    }

    /** History / track-row comment: "The shipment has been picked up". */
    function cdp_handoverTrackComment($status)
    {
        return 'The shipment ' . cdp_handoverPhrase($status);
    }

    /** E-mail subject: "Your shipment has been picked up - SL123". */
    function cdp_handoverSubject($status, $tracking)
    {
        return 'Your shipment ' . cdp_handoverPhrase($status) . ' - ' . $tracking;
    }

    /**
     * Customer text for a pickup (SMS and WhatsApp share it; WhatsApp markup
     * is stripped from SMS). Delivered keeps the business's own templates.
     */
    function cdp_handoverPickedUpText($name, array $trackings, $siteName = '')
    {
        $plural = count($trackings) > 1;
        return 'Hello ' . trim((string) $name) . ",\n\n"
             . 'Your package' . ($plural ? 's ' : ' ') . '*' . implode(', ', $trackings) . '* '
             . cdp_handoverPhrase(CDP_STATUS_PICKED_UP, $plural) . '. '
             . 'Thank you for shipping with ' . (trim((string) $siteName) !== '' ? '*' . trim((string) $siteName) . '*' : 'us') . '!';
    }
}
