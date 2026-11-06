<?php
/**
 * Recipients CRUD for the Swift Lane pages (Recipients, AddCustomer recipient tab).
 * Same tables and helpers as ajax/recipients/* (cdp_insertRecipient, cdp_updateRecipient,
 * cdp_insertAddressRecipient, cdp_deleteRecipient); customers only touch their own rows,
 * agency staff only their agency's.
 */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../helpers/querys.php';
require_once CDP_ROOT . '/views/sl/data/recipients.php';
require_login();
require_permission('view_recipients');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
if (CDP_APP_MODE_DEMO === true) sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
$client = sl_userIs('client');
$ctx = function_exists('cdp_getAgencyContext') ? cdp_getAgencyContext() : ['is_restricted' => false, 'agency_id' => null];
$db = new Conexion();
$find = function (int $id) {
    $r = sl_row('SELECT r.* FROM cdb_recipients r LEFT JOIN cdb_users u ON u.id = r.sender_id WHERE r.id = :id' . sl_rcScope(), [':id' => $id]);
    if (!$r) sl_json(['success' => false, 'message' => 'Recipient not found'], 404);
    return $r;
};
switch ((string) ($_POST['op'] ?? '')) {
    case 'save':
        $id = (int) ($_POST['id'] ?? 0);
        $id ? require_permission(['recipients_edit', 'view_recipients']) : require_permission(['recipients_add', 'view_recipients']);
        $name = trim((string) ($_POST['name'] ?? ''));
        $fname = trim((string) ($_POST['fname'] ?? '')); $lname = trim((string) ($_POST['lname'] ?? ''));
        if ($name !== '' && $fname === '') { $p = preg_split('/\s+/', $name, 2); $fname = $p[0]; $lname = $p[1] ?? ''; }
        $phone = trim((string) ($_POST['phone'] ?? '')); $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $err = [];
        if ($fname === '') $err['name'] = 'Required';
        if ($phone === '') $err['phone'] = 'Required';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $err['email'] = 'Not a valid e-mail';
        $addr = trim((string) ($_POST['address'] ?? '')); $country = (int) ($_POST['country'] ?? 0);
        if (!$id && $addr === '') $err['address'] = 'Required';
        if ($addr !== '' && !$country) $err['country'] = 'Required';
        // owner: customers own their recipients; staff pick the customer (default: themselves, like the legacy form)
        $owner = $client ? (int) $_SESSION['userid'] : (int) ($_POST['sender_id'] ?? $_SESSION['userid']);
        $o = sl_row('SELECT id, userlevel, agency_id FROM cdb_users WHERE id = :id', [':id' => $owner]);
        if (!$o) $err['sender_id'] = 'Unknown customer';
        elseif (!$client && !empty($ctx['is_restricted']) && (int) $o->agency_id !== (int) $ctx['agency_id'] && $owner !== (int) $_SESSION['userid']) $err['sender_id'] = 'Customer of another agency';
        if ($err) sl_json(['success' => false, 'message' => 'Please check the form', 'errors' => $err], 422);
        $data = ['fname' => cdp_sanitize($fname), 'lname' => cdp_sanitize($lname), 'phone' => cdp_sanitize($phone), 'email' => cdp_sanitize($email)];
        if ($id) {
            $r = $find($id);
            cdp_updateRecipient($data + ['id_recipient' => $id]);
            if ($owner !== (int) $r->sender_id && !$client) { $db->cdp_query('UPDATE cdb_recipients SET sender_id = :s WHERE id = :id'); $db->bind(':s', $owner); $db->bind(':id', $id); $db->cdp_execute(); }
        } else {
            $data['sender_id'] = $owner;
            $ag = !empty($ctx['is_restricted']) ? (int) $ctx['agency_id'] : (int) ($o->agency_id ?? 0);
            if ($ag > 0) $data['agency_id'] = $ag;
            $id = (int) cdp_insertRecipient($data);
            if (!$id) sl_json(['success' => false, 'message' => 'Could not save'], 500);
        }
        if ($addr !== '') cdp_insertAddressRecipient(['recipient_id' => $id, 'address' => cdp_sanitize($addr), 'country' => $country, 'city' => (int) ($_POST['city'] ?? 0), 'state' => (int) ($_POST['state'] ?? 0), 'postal' => cdp_sanitize((string) ($_POST['postal'] ?? ''))]);
        cdp_activityLog(['module' => 'recipients', 'verb' => empty($_POST['id']) ? 'create' : 'update', 'action' => 'recipient.save', 'action_label' => empty($_POST['id']) ? 'Added recipient' : 'Updated recipient', 'outcome' => 'ok', 'entity_type' => 'recipient', 'entity_id' => $id, 'entity_label' => trim("$fname $lname"), 'summary' => (empty($_POST['id']) ? 'added recipient ' : 'updated recipient ') . trim("$fname $lname") . ' for user #' . $owner]);
        sl_json(['success' => true, 'message' => 'Recipient saved', 'redirect' => $client ? 'recipients_list.php' : sl_route('Recipients')]);
    case 'address_delete':
        require_permission(['recipients_edit', 'view_recipients']);
        $r = $find((int) ($_POST['id'] ?? 0));
        $aid = (int) ($_POST['address_id'] ?? 0);
        if (!sl_scalar('SELECT COUNT(*) FROM cdb_recipients_addresses WHERE id_addresses = :a AND recipient_id = :r', [':a' => $aid, ':r' => $r->id])) sl_json(['success' => false, 'message' => 'Address not found'], 404);
        if (cdp_verifyReferentialIntegrity('cdb_add_order', 'receiver_address_id', $aid) || cdp_verifyReferentialIntegrity('cdb_customers_packages', 'receiver_address_id', $aid)) sl_json(['success' => false, 'message' => 'This address is used by shipments'], 422);
        cdp_deleteRecipientAddress($aid);
        cdp_activityLog(['module' => 'recipients', 'verb' => 'delete', 'action' => 'recipient.address_delete', 'outcome' => 'ok', 'entity_type' => 'recipient', 'entity_id' => (int) $r->id, 'summary' => 'removed an address of recipient ' . trim($r->fname . ' ' . $r->lname)]);
        sl_json(['success' => true, 'message' => 'Address removed']);
    case 'delete':
        require_permission(['delete_recipient', 'view_recipients']);
        $r = $find((int) ($_POST['id'] ?? 0));
        // same referential checks as ajax/recipients/recipients_delete_ajax.php
        if (cdp_verifyReferentialIntegrity('cdb_add_order', 'receiver_id', $r->id) || cdp_verifyReferentialIntegrity('cdb_customers_packages', 'receiver_id', $r->id) || cdp_verifyReferentialIntegrity('cdb_consolidate', 'receiver_id', $r->id)) sl_json(['success' => false, 'message' => 'This recipient has shipments and cannot be deleted'], 422);
        cdp_deleteRecipient($r->id);
        $db->cdp_query('DELETE FROM cdb_recipients_addresses WHERE recipient_id = :r'); $db->bind(':r', $r->id); $db->cdp_execute();
        cdp_activityLog(['module' => 'recipients', 'verb' => 'delete', 'action' => 'recipient.delete', 'action_label' => 'Deleted recipient', 'outcome' => 'ok', 'entity_type' => 'recipient', 'entity_id' => (int) $r->id, 'entity_label' => trim($r->fname . ' ' . $r->lname), 'summary' => 'deleted recipient ' . trim($r->fname . ' ' . $r->lname)]);
        sl_json(['success' => true, 'message' => 'Recipient deleted']);
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
