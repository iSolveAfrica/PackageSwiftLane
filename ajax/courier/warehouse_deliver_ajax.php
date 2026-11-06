<?php
// ============================================================================
// Warehouse — handover confirmation flow (single or multi-select).
// Deliberately separate from the existing courier_deliver_shipment.php page,
// which is left untouched and unlinked from the warehouse list. This is a
// lightweight flow: preview the selected package(s) (customer, locker,
// system + carrier tracking), confirm via SweetAlert, then flip
// status_courier straight to Picked up (15) or Delivered (8), as staff chose
// (helpers/handover.php) — no photo/signature capture; the customer gets the
// picked up / delivered SMS automation only.
//   action=preview : POST order_nos (JSON array) -> package details for the confirm dialog.
//   action=deliver  : POST order_nos (JSON array), handover_type -> marks them Picked up / Delivered.
// ============================================================================

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once("../../helpers/querys.php");
require_once(__DIR__ . "/../../helpers/finance_switch.php");
require_once(__DIR__ . "/../../helpers/whatsapp.php");     // cdp_normalizePhone() for the SMS number
require_once(__DIR__ . "/../../helpers/message_log.php");  // the SMS leaves a message-log row
require_once(__DIR__ . "/../../helpers/hubtel_sms.php");
require_login();
require_permission('deliver_shipment');

header('Content-Type: application/json; charset=UTF-8');

require_once(__DIR__ . '/../../helpers/handover.php');

$action   = $_REQUEST['action'] ?? '';
$rawNos   = $_REQUEST['order_nos'] ?? '';
$decoded  = is_array($rawNos) ? $rawNos : json_decode((string) $rawNos, true);
$orderNos = is_array($decoded)
    ? array_values(array_unique(array_filter(array_map('strval', $decoded), function ($v) { return $v !== ''; })))
    : [];

if (!$orderNos) {
    echo json_encode(['ok' => false, 'message' => 'No packages selected.']);
    exit;
}

// Order numbers repeat across customers: each number is resolved to the one
// package not yet handed over (helpers/pickup_code.php); a number shared by
// several such packages is refused, never guessed.
require_once(__DIR__ . '/../../helpers/pickup_code.php');
$resolved = cdp_pickupCodeResolveNumbers('air', $orderNos);
if ($resolved['ambiguous']) {
    echo json_encode(['ok' => false, 'message' => 'These order numbers belong to more than one package: '
        . implode(', ', $resolved['ambiguous']) . '. Hand each one over from its own page.']);
    exit;
}
$idByNo = [];
foreach ($resolved['ids'] as $rid) {
    $r = cdp_getCourier($rid);
    if ($r) {
        $idByNo[(string) $r->order_no] = (int) $rid;
    }
}
/** The selected package behind a number: the open one, else the (delivered) first match. */
$rowFor = function ($no) use ($idByNo) {
    return isset($idByNo[(string) $no]) ? cdp_getCourier($idByNo[(string) $no]) : cdp_getCourierMultiple($no);
};

if ($action === 'preview') {
    $db = new Conexion;
    $packages = [];

    foreach ($orderNos as $no) {
        $row = $rowFor($no);
        if (!$row) {
            continue;
        }

        $db->cdp_query("SELECT fname, lname, locker FROM cdb_users WHERE id = :sid LIMIT 1");
        $db->bind(':sid', (int) $row->sender_id);
        $db->cdp_execute();
        $sender = $db->cdp_registro();

        $carrier = cdp_getPackageTrackingLegacyAware((int) $row->order_id);

        $packages[] = [
            'order_id'          => (int) $row->order_id,
            'owner_id'          => (int) $row->sender_id,
            'order_no'          => (string) $row->order_no,
            'tracking'          => ($row->order_prefix ?? '') . $row->order_no,
            'carrier'           => $carrier->tracking_number ?: 'N/A',
            'name'              => $sender && function_exists('cdp_nameWithLocker') ? cdp_nameWithLocker($sender) : 'N/A',
            'already_delivered' => cdp_isHandedOver($row->status_courier),
            'handed_over_as'    => cdp_isHandedOver($row->status_courier) ? cdp_handoverLabel($row->status_courier) : '',
            'cleared'           => cdp_fsIsCleared($row->fs_cleared_for_delivery ?? 0),
        ];
    }

    if (!$packages) {
        echo json_encode(['ok' => false, 'message' => 'Could not find the selected package(s).']);
        exit;
    }

    echo json_encode(['ok' => true, 'packages' => $packages]);
    exit;
}

if ($action === 'deliver') {
    $user     = new User;
    $userData = $user->cdp_getUserData();
    $uid      = (int) ($userData->id ?? ($_SESSION['userid'] ?? 0));
    $status   = cdp_handoverStatusFromInput($_POST['handover_type'] ?? '');
    $label    = cdp_handoverLabel($status);

    // Every package needs its owner's verified pickup code.
    $gate = cdp_pickupCodeGate('air', $resolved['ids'], $status);
    if (!$gate['ok']) {
        echo json_encode(['ok' => false, 'message' => $gate['message'], 'pickup_code' => $gate]);
        exit;
    }

    $delivered = 0;
    $skippedUncleared = 0;
    foreach ($orderNos as $no) {
        if (!isset($idByNo[(string) $no])) {
            continue; // already handed over
        }
        $row = cdp_getCourier($idByNo[(string) $no]);
        if (!$row || cdp_isHandedOver($row->status_courier)) {
            continue;
        }
        // Finance must have cleared the package first — never deliver an
        // uncleared package even if the request is crafted directly.
        if (!cdp_fsIsCleared($row->fs_cleared_for_delivery ?? 0)) {
            $skippedUncleared++;
            continue;
        }

        // By order_id: a number update would also move another customer's package.
        $dbu = new Conexion;
        $dbu->cdp_query("UPDATE cdb_add_order SET status_courier = :s WHERE order_id = :id");
        $dbu->bind(':s', $status);
        $dbu->bind(':id', (int) $row->order_id);
        $dbu->cdp_execute();
        cdp_pickupCodeConsume('air', [(int) $row->order_id], $uid);
        // Loyalty points for a delivered / picked-up shipment (idempotent).
        require_once __DIR__ . '/../../helpers/loyalty.php';
        if (function_exists('sl_loyaltyOnDelivered')) { sl_loyaltyOnDelivered('shipment', (int) $row->order_id); }
        cdp_freeConsolidatedItem(['id' => (int) $row->order_id]);

        $tracking = ($row->order_prefix ?? '') . $no;
        if (function_exists('cdp_updateShipTrackingMultiple')) {
            cdp_updateShipTrackingMultiple($tracking, $status, $label . ' via Warehouse', $row->origin_off ?? 0, $uid);
        }
        // Audit: who delivered this package, when (Warehouse View).
        if (function_exists('cdp_insertCourierShipmentUserHistory')) {
            cdp_insertCourierShipmentUserHistory([
                'user_id'      => $uid,
                'order_id'     => (int) $row->order_id,
                'order_track'  => $tracking,
                'action'       => 'Warehouse — package ' . strtolower($label) . ' (Warehouse View)',
                'date_history' => date('Y-m-d H:i:s'),
            ]);
        }
        // The customer is texted that the package was picked up / delivered
        // (the handed-over SMS automation: SMS switch and opt-out apply; this
        // screen sends no WhatsApp or e-mail).
        try {
            $smsSender = cdp_getSenderCourier((int) ($row->sender_id ?? 0));
            if ($smsSender) {
                $siteName = trim((string) ((new Core)->site_name ?? ''));
                cdp_smsEventNotify($smsSender, ($siteName !== '' ? $siteName . ': ' : '')
                    . 'Package ' . $tracking . ' ' . cdp_handoverPhrase($status) . '. Thank you for shipping with us.', 'handed_over');
            }
        } catch (Throwable $e) { /* best-effort: the delivery itself is already recorded */ }

        $delivered++;
    }

    echo json_encode(['ok' => true, 'delivered' => $delivered, 'label' => $label, 'skipped_uncleared' => $skippedUncleared]);
    exit;
}

echo json_encode(['ok' => false, 'message' => 'Unknown action.']);
