<?php
/**
 * Data for the design-system edit pages that replaced the old Bootstrap forms:
 * CustomerEdit (customers_edit.php), ProfileEdit (customers_profile_edit.php?edit=1),
 * StaffEdit (users_edit.php, drivers_edit.php), UserPermissions (user_permissions.php),
 * RecipientForm (recipients_add.php / recipients_edit.php), MailboxAddresses,
 * Backups (backup.php) and BalanceDetail (report_customers_balance_detail.php).
 * Every form posts the legacy field names to the legacy endpoint.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/querys.php';

function sl_countries(): array
{
    return sl_rows('SELECT id, name FROM cdb_countries ORDER BY name');
}

/**
 * Address rows for the shared address editor (views/sl/pages/partials/address_rows.php).
 * Each row carries the state and city options of its country / state so the selects show the stored values.
 */
function sl_addressRows(string $table, string $ownerCol, int $ownerId): array
{
    $rows = sl_rows("SELECT * FROM {$table} WHERE {$ownerCol} = :o ORDER BY id_addresses", [':o' => $ownerId]);
    $out = [];
    foreach ($rows as $r) {
        $country = (int) $r->country;
        $state = (int) $r->state;
        $out[] = [
            'id' => (int) $r->id_addresses,
            'country' => $country,
            'state' => $state,
            'city' => (int) $r->city,
            'postal' => (string) $r->zip_code,
            'address' => (string) $r->address,
            'states' => $country ? sl_rows('SELECT id, name FROM cdb_states WHERE country_id = :c ORDER BY name', [':c' => $country]) : [],
            'cities' => $state ? sl_rows('SELECT id, name FROM cdb_cities WHERE state_id = :s ORDER BY name LIMIT 2000', [':s' => $state]) : [],
        ];
    }
    return $out;
}

function sl_avatarSrc($stored, string $blank = 'uploads/blank.png'): string
{
    return function_exists('cdp_avatarUrl') ? (string) cdp_avatarUrl($stored, $blank) : ('assets/' . ($stored ?: $blank));
}

/** customers_edit.php: staff edit a client account. */
function sl_customerEditData($user, int $id): ?array
{
    require_once CDP_ROOT . '/helpers/rbac.php';
    $data = cdp_getUserEdit4bozo($id);
    if (($data['rowCount'] ?? 0) != 1) {
        return null;
    }
    $row = $data['data'];
    if (!cdp_roleIsClient((int) ($row->userlevel ?? 0))) {
        return ['notClient' => true];
    }
    $history = [];
    if (sl_tableExists('cdb_profile_update_history')) {
        $history = sl_rows('SELECT h.*, u.fname AS update_by_fname, u.lname AS update_by_lname FROM cdb_profile_update_history h LEFT JOIN cdb_users u ON h.update_by = u.id WHERE h.user_id = :u ORDER BY h.datetime DESC', [':u' => $id]);
    }
    return [
        'row' => $row,
        'name' => trim($row->fname . ' ' . $row->lname) ?: $row->username,
        'avatar' => sl_avatarSrc($row->avatar),
        'document' => sl_avatarSrc($row->document_photo, 'uploads/blankID.jpg'),
        'hasDoc' => (string) $row->document_photo !== '' && strpos((string) $row->document_photo, 'blankID') === false,
        'addresses' => sl_addressRows('cdb_senders_addresses', 'user_id', $id),
        'countries' => sl_countries(),
        'history' => $history,
        'canApprove' => $user->cdp_hasPermission('approve_client'),
        'canAvatar' => $user->cdp_hasPermission('edit_client_avatar'),
        'canDocument' => $user->cdp_hasPermission('edit_client_document'),
        'docTypes' => ['PSP' => 'Passport', 'ECW' => 'Ghana Card', 'DNI' => 'National identity document'],
    ];
}

/** customers_profile_edit.php?edit=1: the customer edits their own account (or an admin edits it). */
function sl_profileEditData($user, int $id): ?array
{
    require_once CDP_ROOT . '/helpers/profile.php';
    $data = $id > 0 ? cdp_getUserEdit4bozo($id) : ['rowCount' => 0];
    if (($data['rowCount'] ?? 0) != 1) {
        return null;
    }
    $row = $data['data'];
    $isOwn = (int) $row->id === (int) $user->uid;
    if (cdp_profilePhoneVerified((int) $row->id, (string) $row->phone)) {
        $phoneStatus = 'verified';
    } else {
        $chk = sl_tableExists('cdb_user_details_update_check') ? sl_row('SELECT update_phone FROM cdb_user_details_update_check WHERE user_id = :u', [':u' => (int) $row->id]) : null;
        $phoneStatus = ($chk && (int) $chk->update_phone === 0) ? 'pending' : 'unknown';
    }
    return [
        'row' => $row,
        'isOwn' => $isOwn,
        'name' => trim($row->fname . ' ' . $row->lname) ?: $row->username,
        'avatar' => sl_avatarSrc($row->avatar),
        'document' => sl_avatarSrc($row->document_photo, 'uploads/blankID.jpg'),
        'hasDoc' => strpos(sl_avatarSrc($row->document_photo, 'uploads/blankID.jpg'), 'blankID') === false,
        'phoneStatus' => $phoneStatus,
        'addresses' => sl_addressRows('cdb_senders_addresses', 'user_id', (int) $row->id),
        'countries' => sl_countries(),
        'docTypes' => ['PSP' => 'Passport', 'ECW' => 'Ghana Card', 'DNI' => 'National identity document'],
    ];
}

/** users_edit.php and drivers_edit.php. */
function sl_staffEditData($user, $core, int $id, bool $driver): ?array
{
    require_once CDP_ROOT . '/helpers/rbac.php';
    $data = cdp_getUserEdit4bozo($id);
    if (($data['rowCount'] ?? 0) != 1) {
        return null;
    }
    $row = $data['data'];
    if (!$driver && (int) $row->id !== (int) $user->uid && !cdp_canManageUser($user, (int) $row->userlevel)) {
        return ['denied' => true];
    }
    global $lang;
    $role = cdp_getRolesEdit($row->userlevel);
    $viewerLevel = (int) ($_SESSION['userlevel'] ?? 0);
    return [
        'row' => $row,
        'driver' => $driver,
        'isOwn' => (int) $row->id === (int) $user->uid,
        'name' => trim($row->fname . ' ' . $row->lname) ?: $row->username,
        'avatar' => sl_avatarSrc($row->avatar),
        'roleName' => $role['rowCount'] ? $role['data']->role_name : '',
        'roleOptions' => $driver ? '' : cdp_roleOptionsHtml($user, (int) $row->userlevel, $lang ?? []),
        'offices' => (!$driver && $viewerLevel === 9) ? (array) $core->cdp_getOffices() : [],
        'officeMode' => $driver ? '' : ($viewerLevel === 9 ? 'select' : ($viewerLevel === 2 ? 'readonly' : '')),
        'canPerms' => !$driver && (int) $row->id !== (int) $user->uid && $user->cdp_hasPermission('edit_user') && cdp_canManageUser($user, (int) $row->userlevel),
    ];
}

/** user_permissions.php: per-user overrides (Inherit / Allow / Deny) on top of the role. */
function sl_userPermsData($user, int $targetId): ?array
{
    require_once CDP_ROOT . '/helpers/rbac.php';
    $target = sl_row('SELECT id, username, fname, lname, userlevel FROM cdb_users WHERE id = :id', [':id' => $targetId]);
    if (!$target || !cdp_canManageUser($user, (int) $target->userlevel)) {
        return null;
    }
    $roleUser = new User();
    $roleUser->userlevel = (int) $target->userlevel;
    $roleUser->uid = -1;
    $rolePerms = array_flip((array) $roleUser->cdp_getUserPermissions());
    $overrides = [];
    foreach (sl_rows('SELECT module_action_id, permitted FROM cdb_user_permission_overrides WHERE user_id = :u', [':u' => $targetId]) as $r) {
        $overrides[(int) $r->module_action_id] = (int) $r->permitted;
    }
    $modules = [];
    foreach (sl_rows('SELECT m.module_name, a.id AS action_id, a.action_name, a.description_module FROM cdb_user_module_permissions m JOIN cdb_user_module_actions a ON a.module_id = m.id ORDER BY m.module_name, a.action_name') as $c) {
        $aid = (int) $c->action_id;
        $modules[(string) $c->module_name][] = [
            'id' => $aid,
            'name' => (string) $c->action_name,
            'label' => (string) ($c->description_module ?: $c->action_name),
            'roleOn' => isset($rolePerms[$c->action_name]),
            'state' => array_key_exists($aid, $overrides) ? ($overrides[$aid] === 1 ? 'allow' : 'deny') : 'inherit',
        ];
    }
    global $lang;
    $role = cdp_getRolesEdit($target->userlevel);
    return [
        'target' => $target,
        'name' => trim($target->fname . ' ' . $target->lname) ?: $target->username,
        'role' => $lang['role_' . $target->userlevel] ?? ($role['rowCount'] ? $role['data']->role_name : (string) $target->userlevel),
        'modules' => $modules,
        'overrideCount' => count($overrides),
    ];
}

/** recipients_add.php / recipients_edit.php. */
function sl_recipientFormData($user, int $id): ?array
{
    $d = ['row' => null, 'addresses' => [], 'countries' => sl_countries()];
    if ($id > 0) {
        $data = cdp_getRecipientEdit($id);
        if (($data['rowCount'] ?? 0) != 1) {
            return null;
        }
        $row = $data['data'];
        require_once CDP_ROOT . '/helpers/rbac.php';
        if (cdp_roleIsClient((int) ($_SESSION['userlevel'] ?? 0)) && (int) $row->sender_id !== (int) $_SESSION['userid']) {
            return null; // a customer only edits their own address book
        }
        $d['row'] = $row;
        $d['addresses'] = sl_addressRows('cdb_recipients_addresses', 'recipient_id', $id);
    }
    return $d;
}

/** client_virtual_mail_box_addresses.php: the customer's warehouse addresses with their locker filled in. */
function sl_mailboxData($core, $userData): array
{
    require_once CDP_ROOT . '/helpers/virtual_mailbox.php';
    $active = sl_columnExists('cdb_virtual_mailbox_addresses', 'is_active') ? ' WHERE is_active = 1' : '';
    $all = (array) $core->cdp_getVirtualMailboxes($active);
    $filter = (int) ($_GET['filter_virtual_mail_boxes'] ?? 0);
    $locker = (string) ($userData->locker ?? '');
    $list = [];
    foreach ($all as $mb) {
        if ($filter && (int) $mb->id !== $filter) {
            continue;
        }
        $c = sl_row('SELECT iso2 FROM cdb_countries WHERE id = :id', [':id' => (int) $mb->cdb_countries_id]);
        $html = cdp_mailboxAddressHtml($mb->address, $locker);
        $list[] = [
            'id' => (int) $mb->id,
            'country' => (string) $mb->country,
            'cc' => strtoupper((string) ($c->iso2 ?? '')),
            'html' => $html,
            'plain' => trim(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>/i', '', $html)), ENT_QUOTES, 'UTF-8')),
            'city' => (string) ($mb->city ?? ''),
            'postcode' => (string) ($mb->postcode ?? ''),
        ];
    }
    return ['list' => $list, 'all' => $all, 'filter' => $filter, 'locker' => $locker];
}

/** backup.php: files in backups/ (newest first), the latest one marked. */
function sl_backupData($core): array
{
    $dir = CDP_ROOT . '/backups/';
    $files = [];
    if (is_dir($dir)) {
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..' || $f === 'user.php' || $f[0] === '.' || is_dir($dir . $f)) {
                continue;
            }
            $files[] = ['name' => $f, 'size' => filesize($dir . $f), 'time' => filemtime($dir . $f), 'latest' => $f === ($core->backup ?? '')];
        }
    }
    usort($files, fn($a, $b) => $b['time'] <=> $a['time']);
    return ['files' => $files, 'ok' => !empty($_GET['backupok']), 'demo' => CDP_APP_MODE_DEMO === true];
}

function sl_fileSize(int $bytes): string
{
    $u = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $n = (float) $bytes;
    while ($n >= 1024 && $i < 3) {
        $n /= 1024;
        $i++;
    }
    return ($i ? number_format($n, 1) : (string) $bytes) . ' ' . $u[$i];
}

/** report_customers_balance_detail.php: the same cdp_fsBillingSummary() query as the old page. */
function sl_balanceDetailData(): array
{
    require_once CDP_ROOT . '/helpers/fs_reports.php';
    $customerId = (int) ($_REQUEST['customer'] ?? 0);
    $from = cdp_sanitize((string) ($_REQUEST['fecha_inicio'] ?? ''));
    $to = cdp_sanitize((string) ($_REQUEST['fecha_fin'] ?? ''));
    $range = ($from !== '' && $to !== '') ? (str_replace('-', '/', $from) . ' - ' . str_replace('-', '/', $to)) : '';
    $rows = cdp_fsBillingSummary(['customer_id' => $customerId, 'range' => $range]);
    $sum = ['total' => 0.0, 'paid' => 0.0, 'balance' => 0.0];
    $list = [];
    foreach ((array) $rows as $r) {
        [$label] = cdp_fsPayStatusLabel($r->pay_status);
        $sum['total'] += (float) $r->amount_ghs;
        $sum['paid'] += (float) $r->paid_ghs;
        $sum['balance'] += (float) $r->balance_ghs;
        $list[] = [
            'id' => (int) $r->consolidate_id,
            'ref' => strip_tags((string) cdp_consolidationRefHtml($r->consol_no)),
            'date' => $r->billed_at ? date('Y-m-d', strtotime($r->billed_at)) : '',
            'discount' => (float) $r->discount_ghs,
            'status' => $label,
            'cls' => $r->pay_status === 'paid' ? 'paid' : ($r->pay_status === 'partial' ? 'pending' : 'overdue'),
            'amount' => (float) $r->amount_ghs,
            'paid' => (float) $r->paid_ghs,
            'balance' => (float) $r->balance_ghs,
        ];
    }
    $sender = sl_row('SELECT fname, lname, username, locker FROM cdb_users WHERE id = :id', [':id' => $customerId]);
    return [
        'customerId' => $customerId,
        'customer' => $sender ? (trim($sender->fname . ' ' . $sender->lname) ?: $sender->username) : 'All customers',
        'locker' => $sender->locker ?? '',
        'from' => str_replace('-', '/', $from),
        'to' => str_replace('-', '/', $to),
        'rows' => $list,
        'sum' => $sum,
    ];
}
