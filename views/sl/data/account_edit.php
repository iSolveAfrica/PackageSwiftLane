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

/** ID types the forms offer (code => label). Older rows store the label itself ("Ghana Card"). */
function sl_idDocTypes(): array
{
    return ['PSP' => 'Passport', 'ECW' => 'Ghana Card', 'DNI' => 'National identity document'];
}

/** Display URL of a stored ID photo, or '' when nothing usable is on file (blank placeholder, bad path, missing file). */
function sl_idPhotoUrl($stored): string
{
    $p = trim((string) $stored);
    if ($p === '' || stripos($p, 'blank') !== false) {
        return '';
    }
    if (preg_match('#^https?://#i', $p)) {
        return $p;
    }
    $url = sl_avatarSrc($p, '');
    if ($url === 'assets/' || substr($url, -1) === '/') {
        return '';
    }
    return is_file(CDP_ROOT . '/' . $url) ? $url : '';
}

/**
 * The ID card of an account: front (cdb_users.document_photo), back (document_photo_back, once
 * migrations/2026_11_26_id_document_back.sql ran), type and number, with what is still missing.
 */
function sl_idDocState($row): array
{
    $types = sl_idDocTypes();
    $type = trim((string) ($row->document_type ?? ''));
    $number = trim((string) ($row->document_number ?? ''));
    $code = isset($types[$type]) ? $type : (string) (array_search($type, $types, true) ?: $type);
    $backCol = sl_columnExists('cdb_users', 'document_photo_back');
    $front = sl_idPhotoUrl($row->document_photo ?? '');
    $back = $backCol ? sl_idPhotoUrl($row->document_photo_back ?? '') : '';
    $missing = [];
    if ($type === '' || $number === '') $missing[] = 'type and number';
    if ($front === '') $missing[] = 'front';
    if ($backCol && $back === '') $missing[] = 'back';
    return [
        'type' => $code,
        'typeLabel' => $types[$code] ?? $type,
        'number' => $number,
        'detailsSet' => $type !== '' && $number !== '',
        'front' => $front,
        'back' => $back,
        'hasBackCol' => $backCol,
        'missing' => $missing,
        'complete' => !$missing,
        'name' => trim(($row->fname ?? '') . ' ' . ($row->lname ?? '')) ?: (string) ($row->username ?? ''),
    ];
}

/** "Front + back" style summary pill for staff views: complete, back missing, nothing. */
function sl_idDocPill(array $doc): string
{
    if ($doc['front'] !== '' && ($doc['back'] !== '' || !$doc['hasBackCol'])) {
        return sl_pill($doc['hasBackCol'] ? 'Front and back on file' : 'On file', 'delivered');
    }
    if ($doc['front'] === '' && $doc['back'] === '') {
        return sl_pill('No ID photo', 'pending');
    }
    return sl_pill($doc['front'] === '' ? 'Front missing' : 'Back missing', 'onway');
}

/**
 * Two labelled slots, FRONT and BACK, each with a status pill, the photo or an empty placeholder,
 * View (in-page viewer) and Upload / Replace (upload dialog with a preview). Needs sl_idDialogsHtml() once.
 * $o: canUpload (bool), size ('lg' | 'sm').
 */
function sl_idSlotsHtml(array $doc, array $o = []): string
{
    $can = !empty($o['canUpload']);
    $sm = ($o['size'] ?? 'lg') === 'sm';
    $out = '<div class="idk-slots ' . ($sm ? 'sm' : 'lg') . '">';
    foreach (['front' => 'Front', 'back' => 'Back'] as $side => $label) {
        if ($side === 'back' && !$doc['hasBackCol']) {
            continue;
        }
        $url = $doc[$side];
        $pill = $url !== '' ? sl_pill('Uploaded', 'delivered') : sl_pill('Not uploaded', 'pending');
        $out .= '<div class="idk-slot' . ($url === '' ? ' is-empty' : '') . '">'
            . '<div class="idk-top"><span class="idk-lab">' . h($label) . ($sm ? '' : ' of ID') . '</span>' . $pill . '</div>';
        if ($url !== '') {
            $out .= '<button type="button" class="idk-face" data-idv-open="' . $side . '" aria-label="View the ' . $side . ' of the ID card larger"><img src="' . h($url) . '" alt="' . h($label) . ' of the ID card" loading="lazy"></button>';
        } elseif ($can) {
            $out .= '<button type="button" class="idk-face idk-ph" data-idu-open="' . $side . '"><i class="ic i-image" aria-hidden="true"></i><span>' . h($label) . ' of ID not uploaded</span><small>Tap to add a photo</small></button>';
        } else {
            $out .= '<div class="idk-face idk-ph"><i class="ic i-image" aria-hidden="true"></i><span>' . h($label) . ' of ID not uploaded</span></div>';
        }
        $out .= '<div class="row g6 wrap">';
        if ($url !== '') {
            $out .= '<button type="button" class="sl-btn ghost sm" data-idv-open="' . $side . '"><i class="ic ic-sm i-eye" aria-hidden="true"></i>View</button>';
        }
        if ($can) {
            $verb = $url === '' ? 'Upload' : 'Replace';
            // In a narrow side panel a photo already on file gets an icon-only Replace next to View.
            $out .= $sm && $url !== ''
                ? '<button type="button" class="sl-ib sm" data-idu-open="' . $side . '" aria-label="Replace the ' . $side . ' of the ID card" title="Replace"><i class="ic ic-sm i-upload" aria-hidden="true"></i></button>'
                : '<button type="button" class="sl-btn ' . ($url === '' ? '' : 'ghost ') . 'sm" data-idu-open="' . $side . '"><i class="ic ic-sm i-upload" aria-hidden="true"></i>' . $verb . '</button>';
        }
        $out .= '</div></div>';
    }
    return $out . '</div>';
}

/**
 * The ID viewer pop-up (front/back switch) and the upload dialog (preview before saving), plus the
 * script that drives them (dataJs/sl/account_security.js). Render once per page, outside other forms.
 * $o: canUpload, endpoint, hidden (name => value), fields (['front' => ..., 'back' => ...] file field
 * names), sideParam ('' or the name of a hidden front/back field the endpoint reads).
 */
function sl_idDialogsHtml(array $doc, array $o = []): string
{
    $can = !empty($o['canUpload']) && !empty($o['endpoint']);
    $sub = trim(($doc['typeLabel'] !== '' ? $doc['typeLabel'] : 'ID type not set') . ($doc['number'] !== '' ? ' · ' . $doc['number'] : ''));
    $out = '<div class="sl-dialog" id="idv" hidden data-front="' . h($doc['front']) . '" data-back="' . h($doc['back']) . '">'
        . '<div class="sl-modal idv-modal" role="dialog" aria-modal="true" aria-labelledby="idv-t">'
        . '<div class="row g10"><span class="sl-tile"><i class="ic i-card" aria-hidden="true"></i></span><div class="col grow"><b class="fw5" id="idv-t">ID card · ' . h($doc['name']) . '</b><span class="mut sm">' . h($sub) . '</span></div><button type="button" class="sl-ib sm" data-sl-close aria-label="Close"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>';
    if ($doc['hasBackCol']) {
        $out .= '<div class="sl-seg boxed idv-sw" role="group" aria-label="Side of the card">';
        foreach (['front' => 'Front', 'back' => 'Back'] as $side => $label) {
            $out .= '<button type="button" data-idv-side="' . $side . '" aria-pressed="false">' . h($label) . ' <span class="idv-dot ' . ($doc[$side] !== '' ? 'ok' : 'no') . '" role="img" aria-label="' . ($doc[$side] !== '' ? 'uploaded' : 'not uploaded') . '"></span></button>';
        }
        $out .= '</div>';
    }
    $out .= '<figure class="idv-stage"><img data-idv-img alt="" hidden><div class="idv-empty" data-idv-empty hidden><i class="ic i-image" aria-hidden="true"></i><b class="fw5" data-idv-empty-t></b><span class="mut sm">No photo on file for this side yet.</span></div></figure>'
        . '<div class="row g8 wrap">' . ($can ? '<button type="button" class="sl-btn sm" data-idu-open="front"><i class="ic ic-sm i-upload" aria-hidden="true"></i><span>Upload</span></button>' : '') . '<button type="button" class="sl-btn ghost sm" data-sl-close>Close</button></div>'
        . '</div></div>';
    if ($can) {
        $fields = ($o['fields'] ?? []) + ['front' => 'document_photo', 'back' => 'document_photo_back'];
        $out .= '<div class="sl-dialog" id="idu" hidden><form class="sl-modal idu-modal" data-sl-form data-sl-reload action="' . h($o['endpoint']) . '" method="post" enctype="multipart/form-data" role="dialog" aria-modal="true" aria-labelledby="idu-t" data-field-front="' . h($fields['front']) . '" data-field-back="' . h($fields['back']) . '" data-front="' . h($doc['front']) . '" data-back="' . h($doc['back']) . '">';
        foreach ((array) ($o['hidden'] ?? []) as $k => $v) {
            $out .= '<input type="hidden" name="' . h($k) . '" value="' . h($v) . '">';
        }
        if (!empty($o['sideParam'])) {
            $out .= '<input type="hidden" name="' . h($o['sideParam']) . '" value="front" data-idu-side>';
        }
        $out .= '<div class="row g10"><span class="sl-tile"><i class="ic i-upload" aria-hidden="true"></i></span><div class="col grow"><b class="fw5" id="idu-t">Upload the front of the ID card</b><span class="mut sm">A clear photo with all four corners visible. JPEG, PNG, GIF or WEBP, up to 5 MB.</span></div><button type="button" class="sl-ib sm" data-sl-close aria-label="Close"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>'
            . '<label class="idu-drop"><input type="file" name="' . h($fields['front']) . '" accept="image/jpeg,image/png,image/gif,image/webp" data-idu-file required>'
            . '<img data-idu-prev alt="" hidden><span class="idu-ph" data-idu-ph><i class="ic i-image" aria-hidden="true"></i><b class="fw5">Choose a photo</b><span class="mut sm">or take one with your phone camera</span></span></label>'
            . '<span class="sm" data-idu-note role="status"></span>'
            . '<div class="row g8 wrap"><button type="submit" class="sl-btn" data-idu-save disabled><i class="ic ic-sm i-check" aria-hidden="true"></i>Save photo</button><button type="button" class="sl-btn ghost" data-idu-pick><i class="ic ic-sm i-image" aria-hidden="true"></i>Choose another</button><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button></div>'
            . '</form></div>';
    }
    return $out . '<script src="' . h(cdp_asset('dataJs/sl/account_security.js')) . '" defer></script>';
}

/**
 * The signed-in user's own sign-in security: remembered devices (cdb_auth_trusted_devices, with the
 * ip / user_agent columns when migrations/2026_11_26_trusted_device_details.sql added them) and the
 * last five sign-ins from cdb_activity_log.
 */
function sl_ownSignInData(int $uid, string $username): array
{
    require_once CDP_ROOT . '/views/sl/data/user_360.php';
    $devices = [];
    if (sl_tableExists('cdb_auth_trusted_devices')) {
        $hasIp = sl_columnExists('cdb_auth_trusted_devices', 'ip');
        $hasUa = sl_columnExists('cdb_auth_trusted_devices', 'user_agent');
        $hasLog = sl_tableExists('cdb_activity_log');
        $cookie = explode(':', (string) ($_COOKIE['trusted_device'] ?? ''), 2)[0];
        $rows = sl_rows('SELECT id, selector, created_at, last_used_at, expires_at' . ($hasIp ? ', ip' : '') . ($hasUa ? ', user_agent' : '')
            . ' FROM cdb_auth_trusted_devices WHERE user_id = :u AND revoked_at IS NULL AND expires_at > NOW() ORDER BY COALESCE(last_used_at, created_at) DESC LIMIT 50', [':u' => $uid]);
        foreach ($rows as $r) {
            $ua = $hasUa ? (string) ($r->user_agent ?? '') : '';
            $ip = $hasIp ? (string) ($r->ip ?? '') : '';
            if (($ua === '' || $ip === '') && $hasLog) {
                // Rows from before the device columns: the sign-in that last used this device (a remembered
                // device is checked, and its last_used_at set, at sign-in).
                // Only a sign-in within ten minutes of that moment counts; otherwise the device stays unknown
                // rather than borrowing another browser's sign-in.
                $t = strtotime((string) ($r->last_used_at ?: $r->created_at)) ?: time();
                $seen = sl_row("SELECT ip, user_agent FROM cdb_activity_log WHERE user_id = :u AND verb = 'login' AND outcome = 'success' AND created_at BETWEEN :a AND :b ORDER BY created_at DESC LIMIT 1", [':u' => $uid, ':a' => date('Y-m-d H:i:s', $t - 600), ':b' => date('Y-m-d H:i:s', $t + 600)]);
                if ($seen) {
                    $ua = $ua !== '' ? $ua : (string) $seen->user_agent;
                    $ip = $ip !== '' ? $ip : (string) $seen->ip;
                }
            }
            $label = sl_u360Device($ua);
            $devices[] = [
                'id' => (int) $r->id,
                'label' => $label !== '' ? $label : 'Unknown device',
                'mobile' => (bool) preg_match('/iPhone|Android|iPad|Mobile/i', $ua),
                'ip' => $ip,
                // The whole phrase, so a device that was never used reads "not used yet".
                'used' => $r->last_used_at ? 'last used ' . sl_ago($r->last_used_at) : 'not used yet',
                'usedAt' => sl_date($r->last_used_at ?: $r->created_at, 'j M Y, H:i'),
                'added' => sl_date($r->created_at, 'j M Y'),
                'expires' => sl_date($r->expires_at, 'j M Y'),
                'current' => $cookie !== '' && hash_equals((string) $r->selector, $cookie),
            ];
        }
    }
    $signins = [];
    if (sl_tableExists('cdb_activity_log')) {
        $rows = sl_rows("SELECT created_at, outcome, ip, user_agent FROM cdb_activity_log WHERE verb = 'login' AND (user_id = :u OR (user_id IS NULL AND actor_username = :n)) ORDER BY id DESC LIMIT 5", [':u' => $uid, ':n' => $username]);
        foreach ($rows as $r) {
            $ok = in_array((string) $r->outcome, ['success', 'ok'], true);
            $signins[] = ['ok' => $ok, 'device' => sl_u360Device((string) $r->user_agent) ?: 'Unknown device', 'ip' => (string) $r->ip, 'ago' => sl_ago($r->created_at), 'at' => sl_date($r->created_at, 'j M Y, H:i')];
        }
    }
    return ['devices' => $devices, 'signins' => $signins, 'failed' => count(array_filter($signins, fn($s) => !$s['ok']))];
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
        'doc' => sl_idDocState($row),
        'addresses' => sl_addressRows('cdb_senders_addresses', 'user_id', $id),
        'countries' => sl_countries(),
        'history' => $history,
        'canApprove' => $user->cdp_hasPermission('approve_client'),
        'canAvatar' => $user->cdp_hasPermission('edit_client_avatar'),
        'canDocument' => $user->cdp_hasPermission('edit_client_document'),
        'docTypes' => sl_idDocTypes(),
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
    require_once CDP_ROOT . '/helpers/rbac.php';
    $isClient = cdp_roleIsClient((int) $row->userlevel);
    $doc = sl_idDocState($row);
    // Password: when it was last changed (forgot-reset.php records it), and whether rotation wants a new one.
    $changedCol = sl_columnExists('cdb_users', 'password_changed_at');
    $changedAt = $changedCol ? (string) ($row->password_changed_at ?? '') : '';
    if (strpos($changedAt, '0000') === 0) $changedAt = '';
    // The inline "Change password" form saves through customers_profile_edit_ajax.php, which logs the
    // change (changes.password) but does not stamp password_changed_at: the later of the two counts.
    if (sl_tableExists('cdb_activity_log')) {
        $logged = (string) sl_scalar("SELECT MAX(created_at) FROM cdb_activity_log WHERE action = 'profile.details' AND entity_type = 'user' AND entity_id = :e AND changes LIKE :p", [':e' => (string) (int) $row->id, ':p' => '%"password"%']);
        if ($logged !== '' && ($changedAt === '' || strtotime($logged) > strtotime($changedAt))) {
            $changedAt = $logged;
        }
    }
    $ageRow = clone $row;
    if ($changedCol && $changedAt !== '') $ageRow->password_changed_at = $changedAt;
    $age = ($isOwn && function_exists('sl_passwordAge')) ? sl_passwordAge($ageRow) : null;
    // Fingerprint / face sign-in manages the signed-in user's own passkeys only.
    $passkeys = null;
    if ($isOwn) {
        require_once CDP_ROOT . '/helpers/webauthn.php';
        try {
            if (cdp_passkeysEnabled()) {
                $passkeys = sl_tableExists('cdb_passkeys') ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_passkeys WHERE user_id = :u', [':u' => (int) $row->id]) : 0;
            }
        } catch (Throwable $e) {
            $passkeys = null;
        }
    }
    $own = $isOwn ? sl_ownSignInData((int) $row->id, (string) $row->username) : ['devices' => [], 'signins' => [], 'failed' => 0];
    $phone = trim((string) $row->phone);
    // The checklist: what counts, and what still needs doing. A password with no recorded change date
    // is neither done nor to do (rotation off and nothing recorded).
    $checks = ['phone' => $phone !== '' && $phoneStatus === 'verified', 'idno' => $doc['detailsSet'], 'front' => $doc['front'] !== ''];
    if ($doc['hasBackCol']) $checks['back'] = $doc['back'] !== '';
    if ($changedAt !== '' || ($age && $age['expired'])) $checks['password'] = !($age && $age['expired']);
    $todo = array_keys(array_filter($checks, fn($ok) => !$ok));
    return [
        'row' => $row,
        'isOwn' => $isOwn,
        'isClient' => $isClient,
        'name' => trim($row->fname . ' ' . $row->lname) ?: $row->username,
        'avatar' => sl_avatarSrc($row->avatar),
        'doc' => $doc,
        'phoneStatus' => $phoneStatus,
        'addresses' => sl_addressRows('cdb_senders_addresses', 'user_id', (int) $row->id),
        'countries' => sl_countries(),
        'docTypes' => sl_idDocTypes(),
        'pass' => [
            'changed' => $changedAt !== '' ? sl_date($changedAt, 'j M Y') : '',
            'ago' => $changedAt !== '' ? sl_ago($changedAt) : '',
            'expired' => (bool) ($age['expired'] ?? false),
            'next' => $age ? sl_date($age['next'], 'j M Y') : '',
            // The details endpoint only edits customer accounts; staff change theirs on the password page.
            'inline' => $isClient,
        ],
        'passkeys' => $passkeys,
        'devices' => $own['devices'],
        'signins' => $own['signins'],
        'failed' => $own['failed'],
        'todo' => $todo,
        'done' => count($checks) - count($todo),
        'items' => count($checks),
        'staffEdit' => !$isClient && $isOwn ? 'users_edit.php?user=' . (int) $row->id : '',
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
    require_once CDP_ROOT . '/helpers/sl_auth.php';
    $addr = array_values(array_filter(sl_customerAddresses($userData, $core), fn($a) => !$filter || (int) ($a['id'] ?? 0) === $filter));
    return ['list' => $list, 'all' => $all, 'filter' => $filter, 'locker' => $locker, 'addr' => $addr, 'name' => trim(($userData->fname ?? '') . ' ' . ($userData->lname ?? ''))];
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
