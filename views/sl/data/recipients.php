<?php
/** Data for Recipients (Recipients.dc.html): staff see every recipient in scope, customers their own. */
require_once CDP_ROOT . '/helpers/sl_view.php';

/** WHERE fragment limiting recipients to what the viewer may see. */
function sl_rcScope(): string
{
    if (sl_userIs('client')) return ' AND r.sender_id = ' . (int) $_SESSION['userid'];
    if (function_exists('cdp_getAgencyContext')) {
        $ctx = cdp_getAgencyContext();
        if (!empty($ctx['is_restricted']) && $ctx['agency_id'] !== null) return ' AND (r.agency_id = ' . (int) $ctx['agency_id'] . ' OR u.agency_id = ' . (int) $ctx['agency_id'] . ')';
    }
    return '';
}

function sl_recipientsData($user, $core): array
{
    $client = sl_userIs('client');
    $q = trim((string) ($_GET['q'] ?? ''));
    $country = (int) ($_GET['country'] ?? 0);
    $owner = $client ? 0 : (int) ($_GET['owner'] ?? 0);
    $page = max(1, (int) ($_GET['page'] ?? 1)); $per = 25;
    $w = '1=1' . sl_rcScope(); $b = [];
    if ($q !== '') { $w .= " AND (CONCAT(r.fname,' ',r.lname) LIKE :q OR r.phone LIKE :q OR r.email LIKE :q OR CONCAT(u.fname,' ',u.lname) LIKE :q OR EXISTS (SELECT 1 FROM cdb_recipients_addresses a WHERE a.recipient_id = r.id AND a.address LIKE :q))"; $b[':q'] = "%$q%"; }
    if ($country) { $w .= ' AND EXISTS (SELECT 1 FROM cdb_recipients_addresses a WHERE a.recipient_id = r.id AND a.country = :c)'; $b[':c'] = $country; }
    if ($owner) { $w .= ' AND r.sender_id = :o'; $b[':o'] = $owner; }
    $from = 'FROM cdb_recipients r LEFT JOIN cdb_users u ON u.id = r.sender_id';
    $count = (int) sl_scalar("SELECT COUNT(*) $from WHERE $w", $b);
    $rows = [];
    foreach (sl_rows("SELECT r.*, CONCAT(u.fname, ' ', u.lname) AS owner, u.userlevel AS owner_level,
            (SELECT COUNT(*) FROM cdb_recipients_addresses a WHERE a.recipient_id = r.id) AS naddr,
            (SELECT CONCAT_WS(', ', a.address, ci.name, co.name) FROM cdb_recipients_addresses a LEFT JOIN cdb_cities ci ON ci.id = a.city LEFT JOIN cdb_countries co ON co.id = a.country WHERE a.recipient_id = r.id ORDER BY a.id_addresses LIMIT 1) AS addr,
            (SELECT co.name FROM cdb_recipients_addresses a LEFT JOIN cdb_countries co ON co.id = a.country WHERE a.recipient_id = r.id LIMIT 1) AS region,
            (SELECT COUNT(*) FROM cdb_add_order o WHERE o.receiver_id = r.id AND o.is_deleted = 0) AS n
        $from WHERE $w ORDER BY r.id DESC LIMIT " . (($page - 1) * $per) . ", $per", $b) as $r) {
        $name = trim($r->fname . ' ' . $r->lname);
        $rows[] = ['id' => (int) $r->id, 'name' => $name, 'ini' => sl_initials($name), 'phone' => $r->phone ?: '—', 'mail' => $r->email, 'region' => $r->region ?: '—', 'addr' => $r->addr ?: '—', 'naddr' => (int) $r->naddr, 'by' => trim((string) $r->owner) ?: '—', 'ownerId' => (int) $r->sender_id, 'ownerIsCustomer' => (int) $r->owner_level === 1, 'n' => (int) $r->n];
    }
    $edit = null;
    $eid = (int) ($_GET['edit'] ?? 0);
    if ($eid) {
        $e = sl_row("SELECT r.* $from WHERE r.id = :id" . sl_rcScope(), [':id' => $eid]);
        if ($e) $edit = ['id' => (int) $e->id, 'fname' => $e->fname, 'lname' => $e->lname, 'phone' => $e->phone, 'email' => $e->email, 'owner' => (int) $e->sender_id, 'addresses' => sl_rows('SELECT * FROM cdb_recipients_addresses WHERE recipient_id = :r ORDER BY id_addresses', [':r' => $eid])];
    }
    return ['client' => $client, 'rows' => $rows, 'count' => $count, 'page' => $page, 'per' => $per, 'q' => $q, 'country' => $country, 'owner' => $owner, 'edit' => $edit,
        'customers' => $client ? [] : sl_rows('SELECT u.id, u.fname, u.lname, u.locker FROM cdb_users u WHERE u.userlevel = 1' . (function_exists('cdp_getAgencyContext') && !empty(cdp_getAgencyContext()['is_restricted']) ? ' AND u.agency_id = ' . (int) cdp_getAgencyContext()['agency_id'] : '') . ' ORDER BY u.fname, u.lname LIMIT 1000'),
        'countries' => sl_rows('SELECT id, name FROM cdb_countries WHERE is_active = 1 ORDER BY name LIMIT 300'),
        'canAdd' => $user->cdp_hasPermission(['recipients_add', 'view_recipients']), 'canEdit' => $user->cdp_hasPermission(['recipients_edit', 'view_recipients']), 'canDelete' => $user->cdp_hasPermission(['delete_recipient', 'view_recipients'])];
}
