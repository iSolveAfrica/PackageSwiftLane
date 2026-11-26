<?php
// *************************************************************************
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *************************************************************************

/**
 * Notification Studio message library: a hand-written message for every kind
 * of shipment / package status, per channel (e-mail blocks, SMS, WhatsApp,
 * in-app). helpers/notify_studio.php sends these by default while nothing is
 * saved for a status channel, and the studio opens them as the starting text.
 *
 * Statuses are recognised by their NAME (case, underscore and space
 * insensitive, keyword rules), never by id: production adds its own statuses
 * ("Accepted by Airline", "Arrived in Accra", "On Auction", ...). A name no
 * rule knows gets a fallback that still speaks about that exact status.
 *
 * Business: Swift Lane forwards parcels from the US to Ghana. Customers get a
 * US warehouse address and a locker ID, shop online, parcels arrive at the US
 * warehouse, are consolidated and flown or shipped by sea to Accra, and are
 * picked up at the office with a pickup code or delivered by our riders.
 *
 * Only placeholders that a status send point fills are used (see
 * cdp_notifyPlaceholders()). A line whose placeholders are all empty is left
 * out when the message is sent (cdp_notifyPruneLines), so optional facts such
 * as {eta}, {comment}, {location}, {locker}, {amount_due} or {company_phone}
 * sit on their own lines.
 *
 * The pickup code is only issued for some statuses (helpers/handover.php
 * cdp_statusReadyKind), so no sentence outside the {pickup_code} line / the
 * pickup code box refers to "the code below": the code line carries its own
 * instructions.
 */

if (!function_exists('cdp_notifyDraftKind')) {

    /** "Accepted_by_Airline " -> "accepted by airline". */
    function cdp_notifyDraftNorm($name)
    {
        $n = strtolower(trim((string) $name));
        $n = str_replace(['_', '-', '.', '/'], ' ', $n);
        return trim(preg_replace('/\s+/', ' ', $n));
    }

    /**
     * Display form of a status name: "Pending_Collection" -> "Pending Collection",
     * "PENDING PAYMENT" -> "Pending Payment" (all-caps names are title-cased,
     * known abbreviations such as US stay upper case). '' for no name.
     */
    function cdp_notifyDraftLabel($name)
    {
        $l = trim(preg_replace('/\s+/', ' ', str_replace('_', ' ', (string) $name)));
        if ($l === '') {
            return 'Updated';
        }
        if (preg_match('/[A-Z]{2}/', $l) && $l === strtoupper($l)) {
            $keep = ['US', 'USA', 'UK', 'UAE', 'DHL', 'UPS', 'USPS', 'GH', 'ETA', 'ID', 'JFK', 'KIA'];
            $l = implode(' ', array_map(function ($w) use ($keep) {
                return in_array($w, $keep, true) ? $w : ucfirst(strtolower($w));
            }, explode(' ', $l)));
        }
        return $l === strtolower($l) ? ucfirst($l) : $l;
    }

    /**
     * Keyword rules, first match wins: [kind, regex on the normalised name,
     * optional "unless" regex that makes the rule skip the name]. The order
     * matters: "not picked up" before "picked up", "payment received" before
     * "payment", "customs cleared" before "customs", "delivered to airline"
     * past "delivered", "pending approval" before "approved".
     *
     * @return array<int,array{0:string,1:string,2?:string}>
     */
    function cdp_notifyDraftRules()
    {
        $wait = '/\b(pending|awaiting|waiting|to\s*be)\b/';
        return [
            ['auction',            '/auction/'],
            ['not_picked_up',      '/\b(not|no|un|never)\s*(yet\s*)?(picked|pick|collected|claimed)\b|uncollected|unclaimed/'],
            ['cancelled',          '/cancel/'],
            ['rejected',           '/reject|declin|denied/'],
            ['returned',           '/return/'],
            ['failed_delivery',    '/fail|unsuccess|undeliver|not\s*deliver|attempt|refused|missed\s*deliver|reschedul/'],
            ['not_shipped',        '/not\s*shipped|unshipped|not\s*loaded|missed\s*(the\s*)?(flight|shipment|cut\s*off|vessel)/'],
            ['pre_alert',          '/pre\s*alert|prealert/'],
            ['invoice_needed',     '/(awaiting|waiting\s*(for)?|need(ed|s)?|missing|request(ed)?)\s*(the\s*|a\s*)?(purchase\s*)?(invoice|receipt)|(invoice|receipt)\s*(required|needed|missing|requested)/'],
            ['on_hold',            '/\bheld\b|\bhold\b|suspend|on\s*stop|blocked/'],
            ['pending_quote',      '/(pending|awaiting|waiting|requested|request)\s*(for\s*)?(a\s*)?quot|quot\w*\s*(pending|requested|request)/'],
            ['quote_accepted',     '/quot\w*\s*(accepted|approved|confirmed|agreed)|accepted\s*(the\s*)?quot/'],
            ['quotation',          '/quot/', $wait],
            ['pending_payment',    '/not\s*paid|payment\s*(due|pending|required|needed|overdue|outstanding)|(awaiting|pending|waiting\s*for)\s*payment/'],
            ['paid',               '/payment\s*(received|confirmed|complete|completed|made|successful|done)|\bpaid\b|payment\s*ok/'],
            ['invoiced',           '/invoic|\bbilled\b/', $wait],
            ['pending_payment',    '/payment|unpaid|\bpay\b|balance\s*due|amount\s*due/'],
            ['pending_collection', '/(pending|awaiting|waiting\s*for)\s*collection|to\s*be\s*collected/'],
            ['ready_customs',      '/ready\s*(at|for|in)\s*customs/'],
            ['customs_cleared',    '/custom\w*.*\b(cleared|released|complete|completed|done)\b|\b(cleared|released)\b.*custom/'],
            ['customs',            '/\bcustoms\b|clearance|clearing|\bduty\b|duties/'],
            ['ready_delivery',     '/ready\s*for\s*delivery|awaiting\s*delivery|scheduled\s*for\s*delivery/'],
            ['out_for_delivery',   '/on\s*route|en\s*route(?!\s*to)|out\s*for\s*delivery|with\s*(the\s*|our\s*)?(driver|rider)|on\s*the\s*way\s*to\s*you|dispatched\s*for\s*delivery/'],
            ['ready_pickup',       '/ready\s*(for|to)\s*(pick|collect)|ready\s*for\s*collection|available|awaiting\s*(pick|collection)|^ready$/'],
            ['delivered',          '/deliver/', '/deliver\w*\s+to\s+(the\s*)?(airline|carrier|port|airport|warehouse|agent|shipping\s*line|vessel|hub|depot|office)/'],
            ['picked_up',          '/picked\s*up|collected|handed\s*over/'],
            ['pickup_scheduled',   '/^pick\s*up$|^pickup$|pick\s*up\s*(scheduled|booked|requested|request)|scheduled\s*pick|collection\s*(scheduled|booked)/'],
            ['accepted_airline',   '/airline|air\s*carrier|tendered|accepted\s*by\s*(the\s*)?(carrier|shipping\s*line|vessel|agent)|deliver\w*\s+to\s+(the\s*)?(carrier|shipping\s*line|vessel|agent)/'],
            ['departed',           '/depart|\bleft\b|airborne|sailed|on\s*board|loaded|shipped|dispatched|took\s*off|\bflown\b/'],
            ['in_transit',         '/transit|on\s*the\s*way|en\s*route\s*to|moving/'],
            ['arrived',            '/arriv|landed|discharged|at\s*destination|reached|docked/'],
            ['distribution',       '/distribut|sorting|sorted|processing|unload/'],
            ['awaiting_consolidation', '/(pending|awaiting|waiting\s*(for)?|to\s*be|ready\s*(for|to))\s*(be\s*)?consolidat/'],
            ['consolidated',       '/consolidat|grouped|combined|merged/'],
            ['awaiting_approval',  '/(pending|awaiting|waiting\s*(for)?|needs?|requires?)\s*approv|approval\s*(pending|required|needed)/'],
            ['approved',           '/approv|confirmed|verified/'],
            ['in_warehouse',       '/in\s*(the\s*)?warehouse|at\s*(our\s*|the\s*)?warehouse|stored|in\s*storage/'],
            ['received',           '/receiv|warehouse|check(ed)?\s*in|in\s*stock/'],
            ['pending',            '/pending|awaiting|waiting/'],
        ];
    }

    /** Which kind of message a status name needs ('fallback' when no rule matches). */
    function cdp_notifyDraftKind($name)
    {
        $n = cdp_notifyDraftNorm($name);
        $ghana = '/accra|ghana|kumasi|tema|takoradi|kotoka|\bkia\b/';
        foreach (cdp_notifyDraftRules() as $rule) {
            [$kind, $re] = $rule;
            if (!preg_match($re, $n) || (isset($rule[2]) && preg_match($rule[2], $n))) {
                continue;
            }
            if ($kind === 'received' && preg_match($ghana, $n)) {
                return 'arrived';      // "Received in Accra" is an arrival, not the US warehouse
            }
            if ($kind === 'arrived' && !preg_match($ghana, $n)
                && preg_match('/\bus\b|usa|united\s*states|america|miami|new\s*york|houston|atlanta|new\s*jersey|warehouse|locker/', $n)) {
                return 'received';     // "Arrived at US Warehouse" is still in the US
            }
            return $kind;
        }
        return 'fallback';
    }

    /** Human names of the kinds (the studio shows "Recognised as ..."). */
    function cdp_notifyDraftKindLabels()
    {
        return [
            'pending_collection' => 'Waiting to be collected', 'pre_alert' => 'Pre-alert received',
            'received' => 'Received at the US warehouse', 'in_warehouse' => 'Stored at the US warehouse',
            'awaiting_approval' => 'Waiting for approval', 'approved' => 'Approved for shipping',
            'awaiting_consolidation' => 'Waiting to be consolidated', 'consolidated' => 'Added to a consolidation',
            'accepted_airline' => 'Accepted by the airline / carrier', 'departed' => 'Departed', 'in_transit' => 'In transit to Ghana',
            'customs' => 'Customs clearance', 'customs_cleared' => 'Cleared customs', 'ready_customs' => 'Ready for collection at customs',
            'arrived' => 'Arrived', 'distribution' => 'Being sorted at the office', 'ready_pickup' => 'Ready for pickup',
            'ready_delivery' => 'Ready for delivery', 'out_for_delivery' => 'Out for delivery', 'delivered' => 'Delivered',
            'picked_up' => 'Picked up', 'not_picked_up' => 'Not collected yet (reminder)', 'pickup_scheduled' => 'Pickup requested',
            'auction' => 'Listed for auction', 'on_hold' => 'On hold', 'invoice_needed' => 'Purchase invoice needed',
            'pending_payment' => 'Waiting for payment', 'paid' => 'Payment received', 'invoiced' => 'Invoice issued', 'pending' => 'Pending',
            'quotation' => 'Quote ready', 'quote_accepted' => 'Quote accepted', 'pending_quote' => 'Quote being prepared',
            'rejected' => 'Not accepted', 'cancelled' => 'Cancelled', 'not_shipped' => 'Not shipped this time',
            'returned' => 'Returned to the sender', 'failed_delivery' => 'Delivery not completed', 'fallback' => 'Other status',
        ];
    }

    /** Kinds whose message carries the pickup code (the send point fills it). */
    function cdp_notifyDraftCodeKinds()
    {
        return ['ready_pickup', 'ready_customs', 'ready_delivery', 'out_for_delivery'];
    }

    /** Kinds that ask the customer to pay ({pay_url}, {amount_due}). */
    function cdp_notifyDraftPayKinds()
    {
        return ['pending_payment', 'invoiced'];
    }

    /**
     * Kinds where an estimated arrival makes no sense any more (the package is
     * already in Ghana, delivered, stopped or listed for auction): {eta} is
     * left empty for them, so the shipment card shows no ETA row.
     */
    function cdp_notifyDraftNoEtaKinds()
    {
        return ['pending_collection', 'arrived', 'distribution', 'customs_cleared', 'ready_customs', 'ready_pickup', 'ready_delivery',
            'out_for_delivery', 'delivered', 'picked_up', 'not_picked_up', 'auction', 'on_hold', 'invoice_needed', 'rejected', 'cancelled',
            'returned', 'failed_delivery', 'not_shipped', 'pickup_scheduled'];
    }

    /**
     * The place a status name mentions: "Arrived in Accra" -> ['in', 'Accra'],
     * "Departed US Warehouse" -> ['', 'US Warehouse'], "Arrived" -> ['', ''].
     *
     * @return array{0:string,1:string}
     */
    function cdp_notifyDraftPlace($name)
    {
        $l = cdp_notifyDraftLabel($name);
        if (preg_match('/\b(in|at|to|from|into|for)\s+(.+)$/i', $l, $m)) {
            $place = trim($m[2], " \t.,;:-");
            if ($place !== '' && !preg_match('/^(pick\s*up|delivery|collection|shipping|customs|payment|clearance)$/i', $place)) {
                return [strtolower($m[1]), $place];
            }
            return ['', ''];
        }
        // "Departed US Warehouse", "Arrived Accra": the words after the verb.
        if (preg_match('/^(departed|left|arrived|landed|reached|received|shipped|sailed)\s+(.+)$/i', $l, $m)) {
            return ['', trim($m[2], " \t.,;:-")];
        }
        return ['', ''];
    }

    /** "US Warehouse" -> "our US warehouse", "Accra" -> "Accra", "Kotoka Airport" -> "Kotoka Airport". */
    function cdp_notifyDraftPlaceNoun($place)
    {
        if ($place === '') {
            return '';
        }
        if (preg_match('/warehouse|office|hub|facility|depot|branch|store/i', $place)) {
            // Our own sites are common nouns: "our US warehouse", not "our US Warehouse".
            $p = preg_replace_callback('/\b(Warehouse|Office|Hub|Facility|Depot|Branch|Store|Main|Head)\b/', function ($m) {
                return strtolower($m[1]);
            }, $place);
            if (preg_match('/^(our|the)\s+(.*)$/i', $p, $m)) {
                return strtolower($m[1]) . ' ' . $m[2];
            }
            return 'our ' . $p;
        }
        return $place;
    }

    /**
     * Everything the library knows about one status name: what it means, and
     * the hand-written content of every channel.
     *
     * note: an optional line with {comment}; note_first puts it right after the
     * main paragraph (a reason / details), otherwise it comes after the
     * follow-up paragraph, just before the shipment card.
     *
     * @return array{kind:string,label:string,meaning:string,subject:string,heading:string,text:string,more:string,
     *               note:string,note_first:bool,card:bool,items:bool,pickup:bool,pay:bool,button:string,sms:string,whatsapp:string,inapp:string}
     */
    function cdp_notifyDraftSpec($statusName)
    {
        static $memo = [];
        $memoKey = (string) $statusName;
        if (isset($memo[$memoKey])) {
            return $memo[$memoKey];
        }
        $kind = cdp_notifyDraftKind($statusName);
        $label = cdp_notifyDraftLabel($statusName);
        $n = cdp_notifyDraftNorm($statusName);
        [$prep, $place] = cdp_notifyDraftPlace($statusName);
        $placeNoun = cdp_notifyDraftPlaceNoun($place);

        $hello = "Hello {first_name},\n\n";
        $signThanks = "Thank you for shipping with *{company}*.";
        $signTeam = "*{company}* team";
        $trackWa = "Track your shipment:\n{track_url}";
        $s = [
            'kind' => $kind, 'label' => $label, 'meaning' => '', 'subject' => '', 'heading' => '', 'text' => '', 'more' => '',
            'note' => 'Note from our team: {comment}', 'note_first' => false, 'card' => true, 'items' => false, 'pickup' => false, 'pay' => false,
            'button' => 'Track your shipment', 'sms' => '', 'whatsapp' => '', 'inapp' => '',
        ];
        $wa = function ($main, array $facts = [], $tail = null, $sign = null) use ($hello, $signThanks, $trackWa) {
            $out = $hello . $main;
            $facts = array_values(array_filter($facts, 'strlen'));
            if ($facts) {
                $out .= "\n\n" . implode("\n", $facts);
            }
            return $out . "\n\n" . ($tail ?? $trackWa) . "\n\n" . ($sign ?? $signThanks);
        };
        $noteWa = '*Note:* {comment}';

        switch ($kind) {
            case 'pending_collection':
                $s['meaning'] = 'The package is waiting to be collected. This one status is used in three situations: '
                    . '(a) a package that has been ready at our Accra office for a while and is still waiting for the customer to collect it (pickup reminders); '
                    . '(b) a package we expect at our US warehouse that has not been received yet; '
                    . '(c) a home pickup our team will collect from the customer\'s address. '
                    . 'Write text that is true in all three cases: do not say where the package is, and do not say we have or have not collected it.';
                $s['subject'] = '{tracking} is waiting to be collected';
                $s['heading'] = 'Waiting to be collected';
                $s['text'] = 'Your shipment {tracking} is now marked as waiting to be collected.';
                $s['more'] = "If your package is ready at our office, please collect it soon and bring your pickup code. If we are collecting it from you, our team will contact you to confirm the time.\n\nQuestions? Reply to this e-mail.";
                $s['sms'] = "{company}: {tracking} is waiting to be collected. If it is at our office, please collect it soon with your pickup code. Questions? Contact us.";
                $s['whatsapp'] = $wa("Your shipment *{tracking}* is now *waiting to be collected*.\n\nIf it is ready at our office, please collect it soon and bring your pickup code. If we are collecting it from you, our team will contact you to confirm the time.", [$noteWa]);
                $s['inapp'] = 'Shipment {tracking} is waiting to be collected.';
                break;

            case 'pre_alert':
                $s['meaning'] = 'The customer (or the store) told us a package is on its way to our US warehouse: a pre-alert. We have NOT received the package yet.';
                $s['subject'] = 'We have your pre-alert for {tracking}';
                $s['heading'] = 'Pre-alert received';
                $s['text'] = 'Thank you: we have your pre-alert for {tracking}.';
                $s['more'] = 'We will let you know as soon as the package reaches our US warehouse.';
                $s['sms'] = "{company}: we have your pre-alert for {tracking}. We will let you know when the package reaches our US warehouse.";
                $s['whatsapp'] = $wa("Thank you, we have your *pre-alert* for *{tracking}*.\n\nWe will let you know as soon as the package reaches our US warehouse.", [$noteWa]);
                $s['inapp'] = 'Pre-alert received for {tracking}.';
                break;

            case 'received':
                $s['meaning'] = 'The package has been received at our US warehouse and checked in to the customer\'s locker. It will be prepared for shipping to Ghana.';
                $s['subject'] = 'We have received your package {tracking}';
                $s['heading'] = 'Your package is at our US warehouse';
                $s['text'] = "Good news: your package {tracking} has arrived at our US warehouse.\nLocker: {locker}";
                $s['more'] = 'We will prepare it for shipping to Ghana and keep you updated at every step.';
                $s['items'] = true;
                $s['button'] = 'View your package';
                $s['sms'] = "{company}: we have received your package {tracking} at our US warehouse. We will update you when it ships.";
                $s['whatsapp'] = $wa("Good news! We have *received your package* *{tracking}* at our *US warehouse*.", ['*Locker:* {locker}', '*Weight:* {weight}', $noteWa]);
                $s['inapp'] = 'Package {tracking} was received at our US warehouse.';
                break;

            case 'in_warehouse':
                $s['meaning'] = 'The package is stored at our US warehouse in the customer\'s locker, waiting for its shipment to Ghana.';
                $s['subject'] = '{tracking} is safe in our US warehouse';
                $s['heading'] = 'Safe in our US warehouse';
                $s['text'] = "Your package {tracking} is stored safely at our US warehouse.\nLocker: {locker}";
                $s['more'] = 'It is waiting for its shipment to Ghana. We will let you know as soon as it is on its way.';
                $s['items'] = true;
                $s['button'] = 'View your package';
                $s['sms'] = "{company}: your package {tracking} is safe in our US warehouse, waiting for its shipment to Ghana.";
                $s['whatsapp'] = $wa("Your package *{tracking}* is *stored safely at our US warehouse*, waiting for its shipment to Ghana.\n\nWe will let you know as soon as it is on its way.", ['*Locker:* {locker}', '*Weight:* {weight}', $noteWa]);
                $s['inapp'] = 'Package {tracking} is in our US warehouse, waiting for its shipment to Ghana.';
                break;

            case 'awaiting_approval':
                $s['meaning'] = 'The package is waiting for approval (a check by our team, or a confirmation from the customer) before it can ship. It has NOT been approved yet.';
                $s['subject'] = '{tracking} is waiting for approval';
                $s['heading'] = 'Waiting for approval';
                $s['text'] = 'Your package {tracking} is waiting for approval before it can ship.';
                $s['more'] = 'If we need anything from you, our team will contact you. You can also reply to this e-mail with any question.';
                $s['sms'] = "{company}: {tracking} is waiting for approval before it can ship. We will contact you if we need anything.";
                $s['whatsapp'] = $wa("Your package *{tracking}* is *waiting for approval* before it can ship.\n\nIf we need anything from you, our team will contact you. You can also reply here with any question.", [$noteWa]);
                $s['inapp'] = 'Package {tracking} is waiting for approval before it can ship.';
                break;

            case 'approved':
                $s['meaning'] = 'The package has been checked and approved for shipping to Ghana. It will leave with the next available shipment.';
                $s['subject'] = '{tracking} is approved for shipping';
                $s['heading'] = 'Approved for shipping';
                $s['text'] = 'Good news: your package {tracking} has been checked and approved for shipping to Ghana.';
                $s['more'] = 'It will leave with the next available shipment. We will message you as soon as it is on its way.';
                $s['sms'] = "{company}: good news, {tracking} has been approved for shipping to Ghana. We will update you when it leaves.";
                $s['whatsapp'] = $wa("Good news! Your package *{tracking}* has been *approved for shipping* to Ghana.\n\nIt will leave with the next available shipment, and we will message you as soon as it is on its way.", [$noteWa]);
                $s['inapp'] = 'Package {tracking} was approved for shipping to Ghana.';
                break;

            case 'awaiting_consolidation':
                $s['meaning'] = 'The package is at our US warehouse, waiting to be packed together with other parcels into the next shipment to Ghana. It has NOT been packed yet.';
                $s['subject'] = '{tracking} is waiting for the next shipment';
                $s['heading'] = 'Waiting for the next shipment';
                $s['text'] = 'Your package {tracking} is at our US warehouse, waiting to be packed into the next shipment to Ghana.';
                $s['more'] = 'We will let you know when it has been packed and when it leaves.';
                $s['items'] = true;
                $s['sms'] = "{company}: {tracking} is at our US warehouse, waiting to be packed into the next shipment to Ghana.";
                $s['whatsapp'] = $wa("Your package *{tracking}* is at our US warehouse, *waiting to be packed* into the next shipment to Ghana.\n\nWe will let you know when it has been packed and when it leaves.", [$noteWa]);
                $s['inapp'] = 'Package {tracking} is waiting to be packed into the next shipment to Ghana.';
                break;

            case 'consolidated':
                $s['meaning'] = 'The package has been packed together with other parcels into one consolidated shipment to Ghana. The group ships together.';
                $s['subject'] = '{tracking} is packed for shipping to Ghana';
                $s['heading'] = 'Packed into a consolidated shipment';
                $s['text'] = 'Your package {tracking} has been packed into a consolidated shipment with other parcels going to Ghana.';
                $s['more'] = 'The shipment will leave our US warehouse soon. We will tell you when it departs and when it arrives in Accra.';
                $s['items'] = true;
                $s['sms'] = "{company}: {tracking} has been packed into a consolidated shipment to Ghana and will leave our US warehouse soon.";
                $s['whatsapp'] = $wa("Your package *{tracking}* has been *packed into a consolidated shipment* with other parcels going to Ghana.\n\nWe will tell you when it departs and when it arrives in Accra.", ['*Estimated arrival:* {eta}', $noteWa]);
                $s['inapp'] = 'Package {tracking} was added to a consolidated shipment to Ghana.';
                break;

            case 'accepted_airline':
                $air = (bool) preg_match('/\bair/', $n) || !preg_match('/carrier|shipping\s*line|vessel|agent/', $n);
                $accepted = (bool) preg_match('/accept/', $n);
                if ($air) {
                    $s['meaning'] = 'The shipment was handed over to the airline at the US gateway' . ($accepted ? ' and the airline accepted it for its flight to Accra' : ' for its flight to Accra')
                        . '. It has NOT taken off yet. Next updates: when it departs, and when it lands in Accra.';
                    $s['subject'] = $accepted ? '{tracking} has been accepted by the airline' : '{tracking} is with the airline';
                    $s['heading'] = $accepted ? 'Accepted by the airline' : 'Handed over to the airline';
                    $s['text'] = $accepted
                        ? 'Good news: your shipment {tracking} has been handed over to the airline and accepted for its flight to Accra.'
                        : 'Good news: your shipment {tracking} has been handed over to the airline for its flight to Accra.';
                    $s['more'] = 'We will message you again when it departs and when it lands in Accra.';
                    $s['sms'] = $accepted
                        ? "{company}: {tracking} has been accepted by the airline for its flight to Accra. We will update you when it departs."
                        : "{company}: {tracking} has been handed over to the airline for its flight to Accra. We will update you when it departs.";
                    $s['whatsapp'] = $wa('Good news! Your shipment *{tracking}* has been *' . ($accepted ? 'accepted by the airline' : 'handed over to the airline') . "* for its flight to Accra.\n\nWe will message you again when it departs and when it lands in Accra.", ['*Estimated arrival:* {eta}', $noteWa]);
                    $s['inapp'] = 'Shipment {tracking} ' . ($accepted ? 'was accepted by the airline' : 'was handed over to the airline') . ' for its flight to Accra.';
                } else {
                    $by = preg_match('/(carrier|shipping\s*line|vessel|agent)/', $n, $m) ? $m[1] : 'carrier';
                    $s['meaning'] = 'The shipment was handed over to the ' . $by . ' for transport to Ghana. It is waiting to depart.';
                    $s['subject'] = '{tracking} is with the ' . $by;
                    $s['heading'] = 'Handed over to the ' . $by;
                    $s['text'] = 'Good news: your shipment {tracking} has been handed over to the ' . $by . ' for transport to Ghana.';
                    $s['more'] = 'It is now waiting to depart. We will message you again when it leaves and when it arrives in Accra.';
                    $s['sms'] = "{company}: {tracking} has been handed over to the " . $by . " for transport to Ghana. We will update you when it departs.";
                    $s['whatsapp'] = $wa("Good news! Your shipment *{tracking}* has been *handed over to the " . $by . "* for transport to Ghana.\n\nWe will message you again when it departs and when it arrives in Accra.", ['*Estimated arrival:* {eta}', $noteWa]);
                    $s['inapp'] = 'Shipment {tracking} was handed over to the ' . $by . ' for transport to Ghana.';
                }
                break;

            case 'departed':
                if ($place !== '' && preg_match('/accra|ghana|kotoka|tema/i', $place)) {
                    // Leaving Accra / Ghana: we do not know the next stop, so say no more than that.
                    $s['meaning'] = 'The shipment has left ' . $placeNoun . '. (It is not travelling to Ghana: it is leaving it.)';
                    $s['subject'] = '{tracking} has left ' . $placeNoun;
                    $s['heading'] = 'Departed ' . $placeNoun;
                    $s['text'] = 'Your shipment {tracking} has left ' . $placeNoun . '.';
                    $s['more'] = 'We will send you the next update as soon as there is news. Questions? Reply to this e-mail.';
                    $s['sms'] = "{company}: {tracking} has left " . $placeNoun . ". We will send you the next update soon.";
                    $s['whatsapp'] = $wa("Your shipment *{tracking}* has *left " . $placeNoun . "*.\n\nWe will send you the next update as soon as there is news.", [$noteWa]);
                    $s['inapp'] = 'Shipment {tracking} has left ' . $placeNoun . '.';
                    break;
                }
                $from = $placeNoun !== '' ? $placeNoun : 'our US warehouse';
                $s['meaning'] = 'The shipment has left ' . $from . ' and is on its way to Ghana.';
                $s['subject'] = '{tracking} is on its way to Ghana';
                $s['heading'] = 'On its way to Ghana';
                $s['text'] = 'Your shipment {tracking} has left ' . $from . ' and is on its way to Ghana.';
                $s['more'] = 'We will let you know as soon as it arrives in Accra.';
                $s['sms'] = "{company}: {tracking} has left " . $from . " and is on its way to Ghana.";
                $s['whatsapp'] = $wa("Your shipment *{tracking}* has *left " . $from . "* and is on its way to Ghana.\n\nWe will let you know as soon as it arrives in Accra.", ['*Estimated arrival:* {eta}', $noteWa]);
                $s['inapp'] = 'Shipment {tracking} has left ' . $from . ' and is on its way to Ghana.';
                break;

            case 'in_transit':
                $s['meaning'] = 'The shipment is on its way from the US to Ghana (in the air or at sea). Nothing is needed from the customer.';
                $s['subject'] = '{tracking} is in transit to Ghana';
                $s['heading'] = 'In transit to Ghana';
                $s['text'] = "Your shipment {tracking} is in transit from the US to Ghana.\n\nLatest update from: {location}";
                $s['more'] = 'There is nothing you need to do right now. We will message you when it arrives in Accra.';
                $s['sms'] = "{company}: {tracking} is in transit to Ghana. We will text you when it arrives in Accra.";
                $s['whatsapp'] = $wa("Your shipment *{tracking}* is *in transit* from the US to Ghana.\n\nThere is nothing you need to do right now. We will message you when it arrives in Accra.", ['*Latest update from:* {location}', '*Estimated arrival:* {eta}', $noteWa]);
                $s['inapp'] = 'Shipment {tracking} is in transit to Ghana.';
                break;

            case 'customs':
                $s['meaning'] = 'The shipment has reached Ghana and is going through customs clearance. Once cleared it moves to our office. Customs may ask for an invoice, another document or a duty payment.';
                $s['subject'] = '{tracking} is going through customs';
                $s['heading'] = 'Customs clearance in progress';
                $s['text'] = 'Your shipment {tracking} has reached Ghana and is now going through customs clearance.';
                $s['more'] = 'If customs asks for an invoice, another document or a duty payment, our team will contact you. We will let you know as soon as it has been cleared.';
                $s['sms'] = "{company}: {tracking} is going through customs clearance in Ghana. We will contact you if anything is needed.";
                $s['whatsapp'] = $wa("Your shipment *{tracking}* has reached Ghana and is now going through *customs clearance*.\n\nIf customs asks for an invoice, another document or a duty payment, our team will contact you. We will let you know as soon as it has been cleared.", [$noteWa]);
                $s['inapp'] = 'Shipment {tracking} is going through customs clearance in Ghana.';
                break;

            case 'customs_cleared':
                $s['meaning'] = 'The shipment has cleared customs in Ghana and is moving to our office. It is not ready for pickup yet; the pickup code follows when it is.';
                $s['subject'] = '{tracking} has cleared customs';
                $s['heading'] = 'Cleared customs';
                $s['text'] = 'Good news: your shipment {tracking} has cleared customs in Ghana and is moving to our office.';
                $s['more'] = 'We will send you your pickup code as soon as it is ready for pickup.';
                $s['sms'] = "{company}: {tracking} has cleared customs in Ghana and is moving to our office. We will send your pickup code when it is ready.";
                $s['whatsapp'] = $wa("Good news! Your shipment *{tracking}* has *cleared customs* in Ghana and is moving to our office.\n\nWe will send you your *pickup code* as soon as it is ready for pickup.", [$noteWa]);
                $s['inapp'] = 'Shipment {tracking} has cleared customs in Ghana.';
                break;

            case 'ready_customs':
                $s['meaning'] = 'The package has cleared customs and is ready to be collected. The customer brings their pickup code (sent with this message when one is issued).';
                $s['subject'] = '{tracking} is ready for collection';
                $s['heading'] = 'Ready for collection';
                $s['text'] = 'Your package {tracking} has cleared customs and is ready to be collected.';
                $s['more'] = 'Please come during office hours to collect it.';
                $s['pickup'] = true;
                $s['items'] = true;
                $s['button'] = 'View your package';
                $s['sms'] = "{company}: {tracking} has cleared customs and is ready to collect.\n{pickup_code}";
                $s['whatsapp'] = $wa("Your package *{tracking}* has *cleared customs* and is *ready to be collected*.\n\n{pickup_code}", [$noteWa]);
                $s['inapp'] = 'Package {tracking} has cleared customs and is ready to be collected.';
                break;

            case 'arrived':
                if ($place !== '') {
                    $at = ($prep === 'at' || ($prep === '' && preg_match('/warehouse|office|hub|airport|port|terminal|facility|depot|branch/i', $place))) ? 'at ' . $placeNoun : 'in ' . $place;
                } else {
                    $at = 'in Ghana';
                }
                $atSms = mb_strlen($at) > 28 ? '' : ' ' . $at;
                $s['meaning'] = 'The shipment has arrived ' . $at . '. It will now be unloaded, cleared if needed and sorted before it is ready for pickup or delivery.';
                $s['subject'] = '{tracking} has arrived ' . $at;
                $s['heading'] = 'Arrived ' . $at;
                $s['text'] = 'Great news: your shipment {tracking} has arrived ' . $at . '.';
                $s['more'] = 'Our team will now unload and sort it. We will send you another message, with your pickup code, as soon as it is ready for pickup.';
                $s['sms'] = "{company}: great news, {tracking} has arrived" . $atSms . ". We will text you your pickup code when it is ready for pickup.";
                $s['whatsapp'] = $wa("Great news! Your shipment *{tracking}* has *arrived " . $at . "*.\n\nOur team will now unload and sort it. We will send you your *pickup code* as soon as it is ready for pickup.", [$noteWa]);
                $s['inapp'] = 'Shipment {tracking} has arrived ' . $at . '.';
                break;

            case 'distribution':
                $s['meaning'] = 'The shipment is at our office in Ghana and is being sorted and prepared for pickup or delivery.';
                $s['subject'] = '{tracking} is being sorted at our office';
                $s['heading'] = 'Being sorted at our office';
                $s['text'] = 'Your package {tracking} is at our office in Ghana and is being sorted for pickup or delivery.';
                $s['more'] = 'We will send you your pickup code as soon as it is ready. Please wait for that message before coming to the office.';
                $s['sms'] = "{company}: {tracking} is at our office in Ghana and is being sorted. We will text you your pickup code when it is ready.";
                $s['whatsapp'] = $wa("Your package *{tracking}* is at our office in Ghana and is *being sorted*.\n\nWe will send you your *pickup code* as soon as it is ready. Please wait for that message before coming to the office.", [$noteWa]);
                $s['inapp'] = 'Package {tracking} is being sorted at our office in Ghana.';
                break;

            case 'ready_pickup':
                $s['meaning'] = 'The package is at our office and can be collected now. The pickup code (sent with this message when one is issued) must be shown when collecting.';
                $s['subject'] = '{tracking} is ready for pickup';
                $s['heading'] = 'Ready for pickup';
                $s['text'] = 'Your package {tracking} is ready for pickup at our office.';
                $s['more'] = 'Please come during office hours to collect it.';
                $s['pickup'] = true;
                $s['items'] = true;
                $s['button'] = 'View your package';
                $s['sms'] = "{company}: {tracking} is ready for pickup at our office.\n{pickup_code}";
                $s['whatsapp'] = $wa("Your package *{tracking}* is *ready for pickup* at our office. Please come during office hours to collect it.\n\n{pickup_code}", [$noteWa]);
                $s['inapp'] = 'Package {tracking} is ready for pickup at our office.';
                break;

            case 'ready_delivery':
                $s['meaning'] = 'The package is ready and will be delivered to the customer by one of our riders.';
                $s['subject'] = '{tracking} is ready for delivery';
                $s['heading'] = 'Ready for delivery';
                $s['text'] = 'Your package {tracking} is ready and will be delivered to you by one of our riders.';
                $s['more'] = 'Please keep your phone close so the rider can reach you.';
                $s['pickup'] = true;
                $s['items'] = true;
                $s['sms'] = "{company}: {tracking} is ready for delivery. Our rider will call you.\n{pickup_code}";
                $s['whatsapp'] = $wa("Your package *{tracking}* is *ready for delivery*. One of our riders will bring it to you.\n\nPlease keep your phone close so the rider can reach you.\n\n{pickup_code}", [$noteWa]);
                $s['inapp'] = 'Package {tracking} is ready for delivery.';
                break;

            case 'out_for_delivery':
                $s['meaning'] = 'A rider has the package and is on the way to deliver it to the customer now.';
                $s['subject'] = '{tracking} is out for delivery';
                $s['heading'] = 'Out for delivery';
                $s['text'] = 'Your package {tracking} is on its way to you with one of our riders.';
                $s['more'] = 'Please keep your phone close so the rider can reach you.';
                $s['pickup'] = true;
                $s['sms'] = "{company}: {tracking} is out for delivery. Keep your phone close.\n{pickup_code}";
                $s['whatsapp'] = $wa("Your package *{tracking}* is *out for delivery* with one of our riders.\n\nPlease keep your phone close so the rider can reach you.\n\n{pickup_code}", [$noteWa]);
                $s['inapp'] = 'Package {tracking} is out for delivery.';
                break;

            case 'delivered':
                $s['meaning'] = 'The package has been delivered to the customer (or to the person they chose).';
                $s['subject'] = '{tracking} has been delivered';
                $s['heading'] = 'Delivered';
                $s['text'] = 'Your package {tracking} has been delivered. We hope everything arrived in perfect condition.';
                $s['more'] = 'If anything is not right, reply to this e-mail and our team will help. We look forward to your next order.';
                $s['card'] = false;
                $s['items'] = true;
                $s['button'] = 'View delivery details';
                $s['sms'] = "{company}: {tracking} has been delivered. Thank you for shipping with us! If anything is not right, please contact us.";
                $s['whatsapp'] = $wa("Your package *{tracking}* has been *delivered*. We hope everything arrived in perfect condition.\n\nIf anything is not right, just reply to this message and our team will help.", [$noteWa], 'Delivery details:' . "\n{track_url}");
                $s['inapp'] = 'Package {tracking} has been delivered.';
                break;

            case 'picked_up':
                $s['meaning'] = 'The package has been picked up. Used both when the customer (or someone with the pickup code) collects it from our office, and when our rider collects a home-pickup parcel from the customer\'s address. Do not say where it was picked up.';
                $s['subject'] = '{tracking} has been picked up';
                $s['heading'] = 'Picked up';
                $s['text'] = 'Your package {tracking} has been picked up on {date}. Thank you for shipping with us.';
                $s['more'] = 'If you were not expecting this, please contact us right away.';
                $s['card'] = false;
                $s['items'] = true;
                $s['button'] = 'View your package';
                $s['sms'] = "{company}: {tracking} has been picked up. Thank you! Not expecting this? Contact us right away.";
                $s['whatsapp'] = $wa("Your package *{tracking}* has been *picked up* on {date}. Thank you for shipping with us.\n\nIf you were not expecting this, please contact us right away.", [$noteWa], 'Details:' . "\n{track_url}");
                $s['inapp'] = 'Package {tracking} has been picked up.';
                break;

            case 'not_picked_up':
                $s['meaning'] = 'A reminder: the package has not been collected yet and is waiting at our office (after it was ready for pickup, or after a delivery attempt that did not work). Packages left uncollected for a long time may be subject to storage charges and, in the end, auction.';
                $s['subject'] = 'Reminder: {tracking} is waiting for you';
                $s['heading'] = 'Your package is still waiting for you';
                $s['text'] = 'Your package {tracking} has not been collected yet and is waiting for you at our office.';
                $s['more'] = "Please collect it as soon as you can with your pickup code, or reply to this e-mail if you would like it delivered or someone else will collect it.\n\nPackages left uncollected for a long time may be subject to storage charges and, in the end, auction.";
                $s['items'] = true;
                $s['sms'] = "{company}: reminder, {tracking} has not been collected yet. Please collect it soon or contact us to arrange delivery.";
                $s['whatsapp'] = $wa("*Reminder:* your package *{tracking}* has *not been collected yet* and is waiting for you at our office.\n\nPlease collect it as soon as you can with your pickup code, or reply here if you would like it delivered or someone else will collect it.\n\nPackages left uncollected for a long time may be subject to storage charges and, in the end, auction.", [$noteWa]);
                $s['inapp'] = 'Reminder: package {tracking} is waiting at our office to be collected.';
                break;

            case 'pickup_scheduled':
                $s['meaning'] = 'The customer asked us to collect the package from their address (a pickup request). It is NOT confirmed yet: our team will confirm the date and time.';
                $s['subject'] = 'We received your pickup request for {tracking}';
                $s['heading'] = 'Pickup requested';
                $s['text'] = 'Thank you, we have received your request to collect shipment {tracking} from your address.';
                $s['more'] = 'Our team will confirm the pickup date and time with you shortly. If anything changes, reply to this e-mail.';
                $s['sms'] = "{company}: we received your pickup request for {tracking}. Our team will contact you to confirm the time.";
                $s['whatsapp'] = $wa("Thank you, we have received your *pickup request* for shipment *{tracking}*.\n\nOur team will confirm the pickup date and time with you shortly. If anything changes, reply here.", [$noteWa]);
                $s['inapp'] = 'Pickup requested for shipment {tracking}.';
                break;

            case 'auction':
                $s['meaning'] = 'URGENT. The package stayed at our office unclaimed for too long and is now listed for auction. The customer must contact us today. Do NOT promise that the auction can be stopped: if it has not been sold yet, our team will say whether it can still be released and what is due.';
                $s['subject'] = 'Urgent: {tracking} is listed for auction';
                $s['heading'] = 'Your package is listed for auction';
                $s['text'] = 'Your package {tracking} has been waiting at our office for a long time without being collected, so it has now been listed for auction.';
                $s['more'] = "If you still want this package, contact us today. If it has not been sold yet, our team will tell you whether it can still be released and exactly what is due.\n\nReply to this e-mail or call / WhatsApp us: {company_phone}";
                $s['card'] = false;
                $s['button'] = 'View your package';
                $s['sms'] = "URGENT {company}: your package {tracking} was not collected and is now listed for auction. Contact us today.\n{company_phone}";
                $s['whatsapp'] = "*Urgent: your package is listed for auction*\n\nHello {first_name},\n\nYour package *{tracking}* has been at our office for a long time without being collected, so it is now *listed for auction*.\n\n*What to do:* reply to this message or call us today. If the package has not been sold yet, we will tell you whether it can still be released and what is due.\n\n*Call us:* {company_phone}\n*Note:* {comment}\n\nYour package:\n{track_url}\n\n*{company}*";
                $s['inapp'] = 'Urgent: package {tracking} is listed for auction. Contact us today.';
                break;

            case 'on_hold':
                $s['meaning'] = 'The shipment is on hold for now. Holds are often internal (customs inspection, airline embargo, a check by our team); sometimes we need something from the customer, and then our team contacts them. Do not say the customer must act.';
                $s['subject'] = '{tracking} is on hold';
                $s['heading'] = 'Your shipment is on hold';
                $s['text'] = 'Your shipment {tracking} is on hold for now.';
                $s['note'] = 'Details: {comment}';
                $s['note_first'] = true;
                $s['more'] = "If we need anything from you, our team will contact you. You can also reply to this e-mail or call / WhatsApp us: {company_phone}";
                $s['sms'] = "{company}: {tracking} is on hold for now. We will contact you if we need anything.\nQuestions? {company_phone}";
                $s['whatsapp'] = $wa("Your shipment *{tracking}* is *on hold* for now.\n\n*Details:* {comment}\n\nIf we need anything from you we will contact you; you can also reply here.", ['*Call us:* {company_phone}']);
                $s['inapp'] = 'Shipment {tracking} is on hold for now.';
                break;

            case 'invoice_needed':
                $s['meaning'] = 'We need the purchase invoice (the store receipt) for the package, usually for customs or to confirm its value. It cannot move on until we have it.';
                $s['subject'] = 'We need the purchase invoice for {tracking}';
                $s['heading'] = 'Purchase invoice needed';
                $s['text'] = 'We need the purchase invoice (your receipt from the store) for {tracking}.';
                $s['note'] = 'Details: {comment}';
                $s['note_first'] = true;
                $s['more'] = 'Please reply to this e-mail with a copy or a screenshot of it, so we can keep your package moving.';
                $s['sms'] = "{company}: we need the purchase invoice for {tracking}. Please reply to our e-mail with a copy so we can keep it moving.";
                $s['whatsapp'] = $wa("We need the *purchase invoice* (your receipt from the store) for *{tracking}*.\n\nPlease reply here with a copy or a screenshot of it, so we can keep your package moving.", [$noteWa]);
                $s['inapp'] = 'We need the purchase invoice for {tracking}.';
                break;

            case 'pending_payment':
                $s['meaning'] = 'The shipment is waiting for payment. It will be released and moved to the next step once the balance is paid.';
                $s['subject'] = 'Payment needed for {tracking}';
                $s['heading'] = 'Payment needed';
                $s['text'] = "Your shipment {tracking} is waiting for payment. Once it is paid, we will release it and move it to the next step.\n\nAmount due: {amount_due}";
                $s['more'] = 'You can pay securely online with the button below. If you have already paid, reply to this e-mail and we will check it for you.';
                $s['pay'] = true;
                $s['sms'] = "{company}: please pay for {tracking} so we can release it:\n{pay_url}";
                $s['whatsapp'] = $wa("Your shipment *{tracking}* is *waiting for payment*. Once it is paid, we will release it and move it to the next step.\n\n*Amount due:* {amount_due}\n\nPay securely online:\n{pay_url}", [$noteWa]);
                $s['inapp'] = 'Payment is needed for shipment {tracking} before it can move on.';
                break;

            case 'invoiced':
                $s['meaning'] = 'The invoice for the shipment has been issued. The amount is due and the customer can pay online.';
                $s['subject'] = 'Your invoice for {tracking} is ready';
                $s['heading'] = 'Your invoice is ready';
                $s['text'] = "We have issued the invoice for your shipment {tracking}.\n\nAmount due: {amount_due}";
                $s['more'] = 'You can pay securely online with the button below. Paying early keeps your shipment moving without delay.';
                $s['pay'] = true;
                $s['sms'] = "{company}: invoice for {tracking} is ready. Pay online:\n{pay_url}";
                $s['whatsapp'] = $wa("The *invoice* for your shipment *{tracking}* is ready.\n\n*Amount due:* {amount_due}\n\nPay securely online:\n{pay_url}\n\nPaying early keeps your shipment moving without delay.", [$noteWa]);
                $s['inapp'] = 'The invoice for shipment {tracking} is ready to pay.';
                break;

            case 'paid':
                $duty = (bool) preg_match('/duty|duties/', $n);
                $s['meaning'] = $duty ? 'The customs duty for the shipment has been paid.' : 'We have received the customer\'s payment for the shipment. Nothing more is due for it now; do not ask for payment.';
                $s['subject'] = $duty ? 'Duty paid for {tracking}' : 'Payment received for {tracking}';
                $s['heading'] = $duty ? 'Customs duty paid' : 'Payment received, thank you';
                $s['text'] = $duty
                    ? 'The customs duty for your shipment {tracking} has been paid.'
                    : 'We have received your payment for {tracking}, thank you.';
                $s['more'] = 'We will move it to the next step and keep you updated.';
                $s['sms'] = $duty
                    ? "{company}: the customs duty for {tracking} has been paid. We will keep you updated."
                    : "{company}: we have received your payment for {tracking}, thank you. We will move it to the next step.";
                $s['whatsapp'] = $wa($duty
                    ? "The *customs duty* for your shipment *{tracking}* has been *paid*.\n\nWe will move it to the next step and keep you updated."
                    : "We have *received your payment* for *{tracking}*, thank you.\n\nWe will move it to the next step and keep you updated.", [$noteWa]);
                $s['inapp'] = $duty ? 'Customs duty paid for shipment {tracking}.' : 'Payment received for shipment {tracking}. Thank you.';
                break;

            case 'pending':
                $s['meaning'] = 'The shipment is pending: it is waiting on a step before it can move on. It is NOT a payment request; there is nothing the customer needs to do unless our team contacts them.';
                $s['subject'] = '{tracking} is pending';
                $s['heading'] = 'Your shipment is pending';
                $s['text'] = 'Your shipment {tracking} is pending: it is waiting on a step before it can move on.';
                $s['more'] = 'There is nothing you need to do unless our team contacts you. If you have a question, reply to this e-mail.';
                $s['sms'] = "{company}: {tracking} is pending and waiting on a step before it moves on. We will contact you if anything is needed.";
                $s['whatsapp'] = $wa("Your shipment *{tracking}* is *pending*: it is waiting on a step before it can move on.\n\nThere is nothing you need to do unless our team contacts you.", [$noteWa]);
                $s['inapp'] = 'Shipment {tracking} is pending.';
                break;

            case 'quotation':
                $s['meaning'] = 'We have prepared a shipping quote. The customer accepts it or asks questions by replying to us (there is no online quote page).';
                $s['subject'] = 'Your shipping quote for {tracking} is ready';
                $s['heading'] = 'Your quote is ready';
                $s['text'] = 'We have prepared the shipping quote for {tracking}.';
                $s['more'] = 'Reply to this e-mail to accept it or to ask any question about the price, and we will get your package moving.';
                $s['sms'] = "{company}: your shipping quote for {tracking} is ready. Reply to our e-mail or contact us to accept it.";
                $s['whatsapp'] = $wa("Your *shipping quote* for *{tracking}* is ready.\n\nReply here to accept it or ask any question about the price.", [$noteWa]);
                $s['inapp'] = 'Your shipping quote for {tracking} is ready.';
                break;

            case 'quote_accepted':
                $s['meaning'] = 'The customer accepted our shipping quote. We will now get the package moving.';
                $s['subject'] = 'Thank you for accepting the quote for {tracking}';
                $s['heading'] = 'Quote accepted';
                $s['text'] = 'Thank you for accepting the quote for {tracking}; we will get it moving.';
                $s['more'] = 'We will keep you updated at every step.';
                $s['sms'] = "{company}: thank you for accepting the quote for {tracking}. We will get it moving and keep you updated.";
                $s['whatsapp'] = $wa("Thank you for *accepting the quote* for *{tracking}*. We will get it moving and keep you updated.", [$noteWa]);
                $s['inapp'] = 'Quote accepted for {tracking}.';
                break;

            case 'pending_quote':
                $s['meaning'] = 'The customer asked for a quote; we are working out the shipping cost and will send it soon. Nothing is needed from the customer.';
                $s['subject'] = 'We are preparing your quote for {tracking}';
                $s['heading'] = 'Preparing your quote';
                $s['text'] = 'Thank you for your request. We are working out the shipping cost for {tracking} and will send you the quote shortly.';
                $s['more'] = 'There is nothing you need to do for now.';
                $s['sms'] = "{company}: thank you, we are preparing the shipping quote for {tracking} and will send it to you shortly.";
                $s['whatsapp'] = $wa("Thank you for your request. We are *preparing the shipping quote* for *{tracking}* and will send it to you shortly.\n\nThere is nothing you need to do for now.", [$noteWa]);
                $s['inapp'] = 'We are preparing the shipping quote for {tracking}.';
                break;

            case 'rejected':
                $s['meaning'] = 'The shipment or request could not be accepted (for example prohibited items, missing details or failed checks). The customer should contact us for the reason and next steps.';
                $s['subject'] = 'We could not accept {tracking}';
                $s['heading'] = 'Your shipment was not accepted';
                $s['text'] = 'We are sorry, but we could not accept your shipment {tracking}.';
                $s['note'] = 'Reason: {comment}';
                $s['note_first'] = true;
                $s['more'] = 'Please reply to this e-mail or contact our team. We will explain what happened and what you can do next.';
                $s['card'] = false;
                $s['sms'] = "{company}: sorry, we could not accept {tracking}. Please contact us and we will explain what you can do next.";
                $s['whatsapp'] = $wa("We are sorry, but we *could not accept* your shipment *{tracking}*.\n\n*Reason:* {comment}\n\nPlease reply to this message and we will explain what you can do next.", [], null, $signTeam);
                $s['inapp'] = 'Shipment {tracking} was not accepted. Please contact us for details.';
                break;

            case 'cancelled':
                $s['meaning'] = 'The shipment was cancelled and will not be shipped.';
                $s['subject'] = '{tracking} has been cancelled';
                $s['heading'] = 'Shipment cancelled';
                $s['text'] = 'Your shipment {tracking} has been cancelled and will not be shipped.';
                $s['note'] = 'Note: {comment}';
                $s['note_first'] = true;
                $s['more'] = 'If you did not ask for this, or you think it is a mistake, reply to this e-mail and we will look into it right away.';
                $s['card'] = false;
                $s['button'] = 'View your shipment';
                $s['sms'] = "{company}: {tracking} has been cancelled. If you did not ask for this, please contact us right away.";
                $s['whatsapp'] = $wa("Your shipment *{tracking}* has been *cancelled* and will not be shipped.\n\n*Note:* {comment}\n\nIf you did not ask for this, or think it is a mistake, reply here and we will look into it right away.", [], "Details:\n{track_url}", $signTeam);
                $s['inapp'] = 'Shipment {tracking} has been cancelled.';
                break;

            case 'not_shipped':
                $s['meaning'] = 'The package was not included in the latest shipment (for example it missed the cut-off, or it is waiting for payment or a document). It is still at our US warehouse. Do not promise which shipment it will go with.';
                $s['subject'] = '{tracking} was not shipped this time';
                $s['heading'] = 'Not shipped yet';
                $s['text'] = 'Your package {tracking} was not included in the latest shipment to Ghana and is still safe at our US warehouse.';
                $s['note'] = 'Reason: {comment}';
                $s['note_first'] = true;
                $s['more'] = 'It can go out with a later shipment once anything holding it back is sorted. If anything is needed from you, our team will be in touch, or reply to this e-mail to ask.';
                $s['card'] = false;
                $s['sms'] = "{company}: {tracking} missed the latest shipment to Ghana and is safe in our US warehouse. We will update you when it ships.";
                $s['whatsapp'] = $wa("Your package *{tracking}* was *not included in the latest shipment* to Ghana. It is still safe at our US warehouse.\n\n*Reason:* {comment}\n\nWe will update you when it ships. If you have any questions, just reply here.", []);
                $s['inapp'] = 'Package {tracking} was not in the latest shipment to Ghana.';
                break;

            case 'returned':
                $s['meaning'] = 'The package is being returned to the sender or seller instead of being shipped to Ghana.';
                $s['subject'] = '{tracking} is being returned to the sender';
                $s['heading'] = 'Returned to the sender';
                $s['text'] = 'Your package {tracking} is being returned to the sender.';
                $s['note'] = 'Reason: {comment}';
                $s['note_first'] = true;
                $s['more'] = 'If you were not expecting this, reply to this e-mail and our team will explain.';
                $s['card'] = false;
                $s['sms'] = "{company}: {tracking} is being returned to the sender. If you were not expecting this, please contact us.";
                $s['whatsapp'] = $wa("Your package *{tracking}* is being *returned to the sender*.\n\n*Reason:* {comment}\n\nIf you were not expecting this, reply here and our team will explain.", [], null, $signTeam);
                $s['inapp'] = 'Package {tracking} is being returned to the sender.';
                break;

            case 'failed_delivery':
                if (preg_match('/reschedul/', $n)) {
                    $s['meaning'] = 'The delivery of the package has been moved to another time. Our team will confirm the new time with the customer.';
                    $s['subject'] = 'Delivery of {tracking} rescheduled';
                    $s['heading'] = 'Delivery rescheduled';
                    $s['text'] = 'The delivery of your package {tracking} has been rescheduled.';
                    $s['note'] = 'Details: {comment}';
                    $s['note_first'] = true;
                    $s['more'] = 'Our team will confirm the new delivery time with you. If you would like to change it, reply to this e-mail.';
                    $s['sms'] = "{company}: the delivery of {tracking} has been rescheduled. We will confirm the new time with you.";
                    $s['whatsapp'] = $wa("The delivery of your package *{tracking}* has been *rescheduled*.\n\nOur team will confirm the new delivery time with you. If you would like to change it, reply here.", ['*Details:* {comment}'], null, $signTeam);
                    $s['inapp'] = 'Delivery of package {tracking} has been rescheduled.';
                    break;
                }
                $s['meaning'] = 'A delivery attempt was not successful. The customer needs to arrange a new delivery time or collect the package from our office.';
                $s['subject'] = 'We could not deliver {tracking}';
                $s['heading'] = 'We could not complete the delivery';
                $s['text'] = 'We tried to deliver your package {tracking} but could not complete the delivery.';
                $s['note'] = 'Rider\'s note: {comment}';
                $s['note_first'] = true;
                $s['more'] = 'Please reply to this e-mail to choose a new delivery time, or collect the package from our office with your pickup code.';
                $s['sms'] = "{company}: we could not deliver {tracking} today. Please contact us to choose a new time or collect it from our office.";
                $s['whatsapp'] = $wa("We tried to deliver your package *{tracking}* but *could not complete the delivery*.\n\nPlease reply here to choose a new delivery time, or collect it from our office with your pickup code.", ['*Rider\'s note:* {comment}'], null, $signTeam);
                $s['inapp'] = 'We could not deliver package {tracking}. Please choose a new time.';
                break;

            default:
                $s['kind'] = 'fallback';
                if ($label === '' || $label === 'Updated') {
                    // No status name at all: a plain "there is an update" message.
                    $s['meaning'] = 'There is a new update on the shipment (its status name is not known). Say only that there is an update and where to see it.';
                    $s['subject'] = 'Update on {tracking}';
                    $s['heading'] = 'An update on your shipment';
                    $s['text'] = 'There is a new update on your shipment {tracking}.';
                    $s['more'] = 'You can see the full history at any time with the tracking link below. If you have a question, reply to this e-mail and our team will help.';
                    $s['sms'] = "{company}: there is an update on {tracking}. Questions? Contact us.";
                    $s['whatsapp'] = $wa("There is a new update on your shipment *{tracking}*.\n\nIf you have a question about it, just reply here.", ['*Location:* {location}', $noteWa]);
                    $s['inapp'] = 'There is an update on shipment {tracking}.';
                    break;
                }
                $s['meaning'] = 'The shipment was moved to the status "' . $label . '". (No specific meaning is known for this status name; describe exactly this status and nothing else.)';
                $s['subject'] = '{tracking}: ' . $label;
                $s['heading'] = $label;
                $s['text'] = 'There is a new update on your shipment {tracking}: it is now marked as "' . $label . '".';
                $s['more'] = 'You can see the full history at any time with the tracking link below. If you have a question about this update, reply to this e-mail and our team will help.';
                $s['sms'] = "{company}: {tracking} is now \"" . $label . "\". Questions? Contact us.";
                $s['whatsapp'] = $wa("There is a new update on your shipment *{tracking}*:\n\n*" . $label . "*\n\nIf you have a question about this update, just reply here.", ['*Location:* {location}', $noteWa]);
                $s['inapp'] = 'Shipment {tracking} is now "' . $label . '".';
                break;
        }
        return $memo[$memoKey] = $s;
    }

    /** The e-mail footer every drafted e-mail ends with. */
    function cdp_notifyDraftFooter()
    {
        return "Thank you for shipping with {company}.\nQuestions? Reply to this e-mail and our team will help.\nCall or WhatsApp us: {company_phone}";
    }

    /**
     * The drafted message for one status name and channel, in the shape the
     * studio edits: ['subject' => '', 'body' => '', 'blocks' => [...]].
     *
     * E-mail order: header, heading, greeting + main paragraph, pickup code
     * box, a reason/details note, the follow-up paragraph, an optional team
     * note, shipment card, items, Pay now, the track button, footer.
     */
    function cdp_notifyDraft($statusName, $channel)
    {
        $s = cdp_notifyDraftSpec($statusName);
        if ($channel === 'email') {
            $blocks = [
                ['type' => 'header', 'text' => '{company}'],
                ['type' => 'heading', 'text' => $s['heading']],
                ['type' => 'text', 'text' => "Hello {first_name},\n\n" . $s['text']],
            ];
            if ($s['pickup']) {
                $blocks[] = ['type' => 'pickup_code'];
            }
            if ($s['note'] !== '' && $s['note_first']) {
                $blocks[] = ['type' => 'text', 'text' => $s['note']];
            }
            if ($s['more'] !== '') {
                $blocks[] = ['type' => 'text', 'text' => $s['more']];
            }
            if ($s['note'] !== '' && !$s['note_first']) {
                $blocks[] = ['type' => 'text', 'text' => $s['note']];
            }
            if ($s['card']) {
                $blocks[] = ['type' => 'status_card'];
            }
            if ($s['items']) {
                $blocks[] = ['type' => 'items'];
            }
            if ($s['pay']) {
                $blocks[] = ['type' => 'button', 'text' => 'Pay now', 'url' => '{pay_url}'];
            }
            $blocks[] = ['type' => 'button', 'text' => $s['button'], 'url' => '{track_url}'];
            $blocks[] = ['type' => 'footer', 'text' => cdp_notifyDraftFooter()];
            return ['subject' => $s['subject'], 'body' => '', 'blocks' => $blocks];
        }
        $body = ['sms' => $s['sms'], 'whatsapp' => $s['whatsapp'], 'inapp' => $s['inapp']][$channel] ?? '';
        return ['subject' => '', 'body' => $body, 'blocks' => []];
    }

    /**
     * Communication Center automations that fit a kind of status. A status the
     * Communication Center matches to an event outside this list gets a
     * "looks wrong" note in the studio (e.g. "No Picked up" -> satisfaction survey).
     * '' (no specific automation: only the generic alert) always fits.
     */
    function cdp_notifyDraftCommsFit($kind)
    {
        $fit = [
            'received' => ['warehouse_received'], 'in_warehouse' => ['warehouse_received'], 'pre_alert' => [],
            'pending_collection' => [], 'awaiting_approval' => [], 'approved' => [], 'awaiting_consolidation' => [], 'consolidated' => [],
            'accepted_airline' => ['departure'], 'departed' => ['departure'], 'in_transit' => ['departure'],
            'customs' => ['customs'], 'customs_cleared' => ['customs', 'arrival'], 'ready_customs' => ['ready_pickup', 'customs'],
            'arrived' => ['arrival'], 'distribution' => ['arrival'],
            'ready_pickup' => ['ready_pickup', 'delivery_schedule'], 'ready_delivery' => ['delivery_schedule', 'ready_pickup'],
            'out_for_delivery' => ['driver_arrival'], 'delivered' => ['survey'], 'picked_up' => ['survey'],
            'not_picked_up' => ['claim_reminder'], 'pickup_scheduled' => [], 'auction' => ['claim_reminder'],
            'on_hold' => [], 'invoice_needed' => [], 'pending_payment' => ['payment_reminder'], 'invoiced' => ['payment_reminder'],
            'paid' => [], 'pending' => [], 'quotation' => [], 'quote_accepted' => [], 'pending_quote' => [], 'rejected' => [], 'cancelled' => [],
            'not_shipped' => [], 'returned' => [], 'failed_delivery' => ['failed_delivery'], 'fallback' => [],
        ];
        return array_merge(['', 'none', 'shipment_alert'], $fit[$kind] ?? []);
    }
}
