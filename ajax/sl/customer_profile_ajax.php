<?php
/** Customer 360 writes: staff note, newsletter flag, add/remove address, delete customer. Agency-scoped. */
require_once __DIR__ . '/../../loader.php';
require_once __DIR__ . '/../../helpers/ajax_guard.php';
require_once __DIR__ . '/../../helpers/sl_view.php';
require_once __DIR__ . '/../../helpers/activity_log.php';
require_once __DIR__ . '/../../helpers/querys.php';
require_once CDP_ROOT . '/views/sl/data/customer_profile.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sl_json(['success' => false, 'message' => 'POST required'], 405);
if (CDP_APP_MODE_DEMO === true) sl_json(['success' => false, 'message' => 'Demo mode: changes are disabled'], 403);
$id = (int) ($_POST['id'] ?? 0);
$c = sl_row('SELECT id, fname, lname, agency_id, notes FROM cdb_users WHERE id = :id AND userlevel = 1', [':id' => $id]);
if (!$c) sl_json(['success' => false, 'message' => 'Customer not found'], 404);
if (!sl_cpInScope((int) $c->agency_id)) sl_json(['success' => false, 'message' => 'This customer belongs to another agency'], 403);
$name = trim($c->fname . ' ' . $c->lname);
$db = new Conexion();
$log = fn($action, $summary, $verb = 'update') => cdp_activityLog(['module' => 'customers', 'action' => $action, 'verb' => $verb, 'outcome' => 'ok', 'entity_type' => 'customer', 'entity_id' => $id, 'entity_label' => $name, 'summary' => $summary]);
switch ((string) ($_POST['op'] ?? '')) {
    case 'note':
        require_permission('edit_client');
        $db->cdp_query('UPDATE cdb_users SET notes = :n WHERE id = :id'); $db->bind(':n', mb_substr((string) ($_POST['notes'] ?? ''), 0, 4000)); $db->bind(':id', $id); $db->cdp_execute();
        $log('note', "updated staff note on $name");
        sl_json(['success' => true, 'message' => 'Note saved']);
    case 'pref':
        require_permission('edit_client');
        if (($_POST['key'] ?? '') !== 'newsletter') sl_json(['success' => false, 'message' => 'Unknown setting'], 422);
        $v = (int) !empty($_POST['value']);
        $db->cdp_query('UPDATE cdb_users SET newsletter = :v WHERE id = :id'); $db->bind(':v', $v); $db->bind(':id', $id); $db->cdp_execute();
        $log('newsletter', ($v ? 'subscribed ' : 'unsubscribed ') . "$name to the newsletter");
        sl_json(['success' => true, 'message' => $v ? 'Newsletter on' : 'Newsletter off']);
    case 'address_add':
        require_permission('edit_client');
        $addr = mb_substr(trim((string) ($_POST['address'] ?? '')), 0, 300);
        $country = (int) ($_POST['country'] ?? 0);
        if ($addr === '' || !$country) sl_json(['success' => false, 'message' => 'Address and country are required', 'errors' => array_filter(['address' => $addr === '' ? 'Required' : '', 'country' => $country ? '' : 'Required'])], 422);
        $db->cdp_query('INSERT INTO cdb_senders_addresses (country, state, city, zip_code, address, user_id) VALUES (:c, :s, :ci, :z, :a, :u)');
        $db->bind(':c', $country); $db->bind(':s', (int) ($_POST['state'] ?? 0)); $db->bind(':ci', (int) ($_POST['city'] ?? 0)); $db->bind(':z', mb_substr(trim((string) ($_POST['zip_code'] ?? '')), 0, 40)); $db->bind(':a', $addr); $db->bind(':u', $id);
        $db->cdp_execute();
        $log('address_add', "added an address for $name", 'create');
        sl_json(['success' => true, 'message' => 'Address added']);
    case 'address_delete':
        require_permission(['edit_client', 'view_client_list']);
        $aid = (int) ($_POST['address_id'] ?? 0);
        if (!sl_scalar('SELECT COUNT(*) FROM cdb_senders_addresses WHERE id_addresses = :a AND user_id = :u', [':a' => $aid, ':u' => $id])) sl_json(['success' => false, 'message' => 'Address not found'], 404);
        // same referential check as ajax/customers/customers_delete_address_ajax.php
        if (cdp_verifyReferentialIntegrity('cdb_add_order', 'sender_address_id', $aid) || cdp_verifyReferentialIntegrity('cdb_customers_packages', 'sender_address_id', $aid) || cdp_verifyReferentialIntegrity('cdb_consolidate', 'sender_address_id', $aid)) sl_json(['success' => false, 'message' => 'This address is used by shipments and cannot be removed'], 422);
        cdp_deleteCustomerAddress($aid);
        $log('address_delete', "removed an address of $name", 'delete');
        sl_json(['success' => true, 'message' => 'Address removed']);
    case 'delete':
        require_permission('delete_client');
        $n = (int) sl_scalar('SELECT (SELECT COUNT(*) FROM cdb_add_order WHERE sender_id = :u) + (SELECT COUNT(*) FROM cdb_customers_packages WHERE sender_id = :u2)', [':u' => $id, ':u2' => $id]);
        if ($n) sl_json(['success' => false, 'message' => "$name has $n shipments or packages; block the account instead"], 422);
        $db->cdp_query('DELETE FROM cdb_users WHERE id = :id AND userlevel = 1'); $db->bind(':id', $id); $db->cdp_execute();
        $log('delete', "deleted customer $name", 'delete');
        sl_json(['success' => true, 'message' => "$name deleted", 'redirect' => sl_route('Customers')]);
    default:
        sl_json(['success' => false, 'message' => 'Unknown operation'], 422);
}
