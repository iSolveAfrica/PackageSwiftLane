<?php
/** Data for Staff & Roles (StaffRoles.dc.html) and Invite staff (InviteStaff.dc.html). */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/sl_people.php';
require_once CDP_ROOT . '/helpers/activity_log.php';

/** Permission "areas" the Staff & Roles page toggles per role (name => action names). */
function sl_staffAreas(): array
{
    return [
        'air' => ['Air shipments', 'View, create, edit', ['view_shipment_list', 'add_shipment', 'edit_shipment']],
        'sea' => ['Sea packages', 'View, create, edit', ['view_package_list', 'add_package', 'edit_package']],
        'wh' => ['Warehouse scanning', 'Receive and hand over', ['warehouse_view', 'warehouse_deliver_package']],
        'cust' => ['Customers', 'View and edit', ['view_client_list', 'edit_client']],
        'money' => ['Payments and money', 'See amounts, record payments', ['view_global_payments', 'view_financial_overview']],
        'reports' => ['Reports', 'View and download', ['view_general_reports']],
        'settings' => ['Settings and API keys', 'Change system settings', ['view_tools', 'manage_api_settings']],
        'pickups' => ['Pickups', 'Schedule and complete', ['view_pickup_list', 'complete_pickup']],
        'users' => ['Staff accounts', 'Add and edit staff', ['view_user_list', 'add_user', 'edit_user']],
    ];
}

function sl_staffRolesData($user, $core): array
{
    $roles = sl_staffRoles();
    $staffWhere = sl_staffWhere('u');
    $hasLog = cdp_activityTableReady();
    $hasPresence = sl_tableExists('cdb_staff_presence');
    $hasTrusted = sl_tableExists('cdb_auth_trusted_devices');
    $q = trim((string) ($_GET['q'] ?? ''));
    $role = (int) ($_GET['role'] ?? 0);
    $branch = trim((string) ($_GET['branch'] ?? ''));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = 25;
    $where = $staffWhere;
    $binds = [];
    if ($q !== '') { $where .= " AND (CONCAT(u.fname,' ',u.lname) LIKE :q OR u.email LIKE :q OR u.username LIKE :q)"; $binds[':q'] = "%$q%"; }
    if ($role && isset($roles[$role])) { $where .= ' AND u.userlevel = :r'; $binds[':r'] = $role; }
    if ($branch !== '') { $where .= ' AND u.name_off = :b'; $binds[':b'] = $branch; }
    $total = (int) sl_scalar("SELECT COUNT(*) FROM cdb_users u WHERE $staffWhere");
    $count = (int) sl_scalar("SELECT COUNT(*) FROM cdb_users u WHERE $where", $binds);
    $onlineSql = $hasPresence ? "(SELECT MAX(minute_at) FROM cdb_staff_presence p WHERE p.user_id = u.id)" : 'NULL';
    $todaySql = $hasLog ? "(SELECT COUNT(*) FROM cdb_activity_log l WHERE l.user_id = u.id AND l.verb <> 'view' AND l.created_at >= CURDATE())" : '0';
    $lastSql = $hasLog ? "(SELECT MAX(created_at) FROM cdb_activity_log l WHERE l.user_id = u.id)" : 'NULL';
    $trustSql = $hasTrusted ? "(SELECT COUNT(*) FROM cdb_auth_trusted_devices t WHERE t.user_id = u.id AND t.revoked_at IS NULL AND t.expires_at > NOW())" : '0';
    $rows = sl_rows("SELECT u.id, u.username, u.fname, u.lname, u.email, u.userlevel, u.name_off, u.active, u.lastlogin, u.created, u.avatar, u.password_changed_at,
            $onlineSql AS seen_min, $todaySql AS tasks, $lastSql AS last_act, $trustSql AS trusted
        FROM cdb_users u WHERE $where ORDER BY u.active DESC, u.userlevel DESC, u.fname LIMIT " . (($page - 1) * $per) . ", $per", $binds);
    $rolePerms = [];
    $list = [];
    foreach ($rows as $r) {
        $rid = (int) $r->userlevel;
        if (!isset($rolePerms[$rid])) $rolePerms[$rid] = cdp_resolveRolePermissions($rid);
        $money = $rolePerms[$rid]['is_wildcard'] || in_array('view_monetary_values', $rolePerms[$rid]['allowed'], true) || in_array('view_financial_overview', $rolePerms[$rid]['allowed'], true);
        $lastTs = max((int) strtotime((string) $r->last_act), (int) strtotime((string) $r->lastlogin), (int) strtotime((string) $r->seen_min));
        $online = $lastTs >= time() - 15 * 60;
        $never = !$r->lastlogin || substr((string) $r->lastlogin, 0, 4) === '0000';
        if (!(int) $r->active) { $st = ['Suspended', 'pending']; }
        elseif ($never && !$lastTs) { $st = ['Invited', 'onway']; }
        else { $st = ['Active', 'delivered']; }
        $name = trim($r->fname . ' ' . $r->lname) ?: $r->username;
        $list[] = ['id' => (int) $r->id, 'name' => $name, 'ini' => sl_initials($name), 'mail' => $r->email, 'role' => $roles[$rid]->role_name ?? ('Role #' . $rid), 'rid' => $rid,
            'branch' => $r->name_off ?: '—', 'tasks' => (int) $r->tasks, 'seen' => $online ? 'Now' : ($lastTs ? sl_ago(date('Y-m-d H:i:s', $lastTs)) : ($st[0] === 'Invited' ? 'Invite sent' : 'Never')),
            'dot' => $online ? '' : ($lastTs >= time() - 3600 ? 'away' : 'off'), 'twostep' => (int) $r->trusted > 0, 'money' => $money, 'st' => $st[0], 'cls' => $st[1], 'active' => (int) $r->active,
            'self' => (int) $r->id === (int) $_SESSION['userid'], 'managed' => cdp_canManageUser($user, $rid), 'driver' => cdp_roleHasFlag($rid, 'is_driver'),
            'today' => $hasLog ? 'On' : '—'];
    }
    // KPIs
    $online = $hasPresence ? (int) sl_scalar("SELECT COUNT(DISTINCT user_id) FROM cdb_staff_presence WHERE minute_at >= NOW() - INTERVAL 15 MINUTE") : 0;
    $online = max($online, (int) sl_scalar("SELECT COUNT(*) FROM cdb_users u WHERE $staffWhere AND (u.lastlogin >= NOW() - INTERVAL 15 MINUTE" . ($hasLog ? " OR u.id IN (SELECT user_id FROM cdb_activity_log WHERE created_at >= NOW() - INTERVAL 15 MINUTE)" : '') . ")"));
    $activeToday = $hasLog ? (int) sl_scalar("SELECT COUNT(DISTINCT l.user_id) FROM cdb_activity_log l JOIN cdb_users u ON u.id = l.user_id WHERE $staffWhere AND l.created_at >= CURDATE()") : 0;
    $riderIds = sl_riderRoleIds();
    $ridersOut = (int) sl_scalar("SELECT COUNT(DISTINCT driver_id) FROM cdb_add_order WHERE is_deleted = 0 AND status_courier IN (7,14) AND driver_id IS NOT NULL");
    $suspended = (int) sl_scalar("SELECT COUNT(*) FROM cdb_users u WHERE $staffWhere AND u.active = 0");
    $trusted = $hasTrusted ? (int) sl_scalar("SELECT COUNT(DISTINCT user_id) FROM cdb_auth_trusted_devices t JOIN cdb_users u ON u.id = t.user_id WHERE $staffWhere AND t.revoked_at IS NULL AND t.expires_at > NOW()") : 0;
    $otpOn = !empty($core->active_otp);
    // online list
    $onlineRows = $hasPresence && $online && (int) sl_scalar("SELECT COUNT(*) FROM cdb_staff_presence WHERE minute_at >= NOW() - INTERVAL 15 MINUTE")
        ? sl_rows("SELECT u.id, u.fname, u.lname, p.page, p.minute_at FROM cdb_staff_presence p JOIN cdb_users u ON u.id = p.user_id WHERE $staffWhere AND p.minute_at >= NOW() - INTERVAL 15 MINUTE AND p.minute_at = (SELECT MAX(minute_at) FROM cdb_staff_presence x WHERE x.user_id = p.user_id) GROUP BY u.id ORDER BY p.minute_at DESC LIMIT 8")
        : sl_rows("SELECT u.id, u.fname, u.lname, '' AS page, GREATEST(COALESCE(u.lastlogin, '2000-01-01'), COALESCE($lastSql, '2000-01-01')) AS minute_at FROM cdb_users u WHERE $staffWhere HAVING minute_at >= NOW() - INTERVAL 15 MINUTE ORDER BY minute_at DESC LIMIT 8");
    $onlineList = array_map(fn($r) => ['id' => (int) $r->id, 'name' => trim($r->fname . ' ' . $r->lname), 'ini' => sl_initials($r->fname . ' ' . $r->lname), 'where' => $r->page ? cdp_activityHumanPage(basename((string) $r->page, '.php')) : sl_ago($r->minute_at), 'a' => strtotime($r->minute_at) < time() - 300 ? 'a' : ''], $onlineRows);
    // by role / by branch
    $byRole = sl_rows("SELECT u.userlevel, COUNT(*) AS n FROM cdb_users u WHERE $staffWhere GROUP BY u.userlevel ORDER BY n DESC");
    $rm = $byRole ? max(1, (int) $byRole[0]->n) : 1;
    $byRoleList = array_map(fn($r) => ['label' => $roles[(int) $r->userlevel]->role_name ?? ('Role #' . $r->userlevel), 'n' => (int) $r->n, 'w' => sl_pct($r->n, $rm), 'id' => (int) $r->userlevel], $byRole);
    $byBranch = sl_rows("SELECT COALESCE(NULLIF(u.name_off,''), 'No branch') AS b, COUNT(*) AS n FROM cdb_users u WHERE $staffWhere GROUP BY b ORDER BY n DESC LIMIT 8");
    $bm = $byBranch ? max(1, (int) $byBranch[0]->n) : 1;
    $byBranchList = array_map(fn($r) => ['label' => $r->b, 'n' => (int) $r->n, 'w' => sl_pct($r->n, $bm)], $byBranch);
    // week activity (which days each staff member acted, Mon..Sun of this week)
    $monday = date('Y-m-d', strtotime('monday this week'));
    $week = [];
    if ($hasLog) {
        $wk = sl_rows("SELECT l.user_id, MAX(l.actor_name) AS name, DAYOFWEEK(l.created_at) AS dow, COUNT(*) AS n FROM cdb_activity_log l JOIN cdb_users u ON u.id = l.user_id WHERE $staffWhere AND l.created_at >= :m GROUP BY l.user_id, dow ORDER BY name", [':m' => $monday]);
        foreach ($wk as $r) {
            $week[$r->user_id] = $week[$r->user_id] ?? ['name' => $r->name, 'days' => array_fill(0, 7, 0)];
            $week[$r->user_id]['days'][((int) $r->dow + 5) % 7] = (int) $r->n;
        }
        $week = array_slice($week, 0, 8);
    }
    // recent access changes
    $changes = $hasLog ? array_map(fn($r) => ['who' => ($r->actor_name ?: 'System') . ' ' . ($r->summary ?: strtolower((string) $r->action_label)), 'what' => $r->entity_label ?: cdp_activityModuleLabel($r->module), 'at' => sl_date($r->created_at, 'M j, H:i')], sl_rows("SELECT l.actor_name, l.summary, l.action_label, l.entity_label, l.module, l.created_at FROM cdb_activity_log l WHERE l.module IN ('users','access','drivers') AND l.verb <> 'view' ORDER BY l.id DESC LIMIT 6")) : [];
    // permissions per role
    $selRole = (int) ($_GET['prole'] ?? 0);
    $roleBtns = [];
    $roleCounts = [];
    foreach ($byRole as $r) $roleCounts[(int) $r->userlevel] = (int) $r->n;
    foreach ($roles as $rid => $r) {
        if (!cdp_canAssignRole($user, $rid) || cdp_roleHasFlag($rid, 'is_superadmin')) continue;
        $roleBtns[] = ['id' => $rid, 'label' => $r->role_name, 'n' => $roleCounts[$rid] ?? 0];
    }
    if (!$selRole || !isset($roles[$selRole]) || !cdp_canAssignRole($user, $selRole) || cdp_roleHasFlag($selRole, 'is_superadmin')) $selRole = $roleBtns[0]['id'] ?? 0;
    $perms = [];
    $onCount = 0;
    if ($selRole) {
        $rp = cdp_resolveRolePermissions($selRole);
        foreach (sl_staffAreas() as $key => $a) {
            $on = $rp['is_wildcard'] || !array_diff($a[2], $rp['allowed']);
            if ($on) $onCount++;
            $perms[] = ['key' => $key, 'm' => $a[0], 'a' => $on ? $a[1] : 'No access', 'on' => $on];
        }
    }
    return [
        'total' => $total, 'online' => $online, 'activeToday' => $activeToday, 'ridersOut' => $ridersOut, 'suspended' => $suspended, 'trusted' => $trusted, 'otpOn' => $otpOn,
        'rows' => $list, 'count' => $count, 'page' => $page, 'per' => $per, 'q' => $q, 'role' => $role, 'branch' => $branch,
        'roleFilters' => $byRoleList, 'branches' => array_map(fn($b) => $b['label'], array_filter($byBranchList, fn($b) => $b['label'] !== 'No branch')),
        'onlineList' => $onlineList, 'byRole' => $byRoleList, 'byBranch' => $byBranchList, 'week' => array_values($week), 'weekLabel' => date('M j', strtotime($monday)) . ' – ' . date('M j', strtotime($monday . ' +6 days')), 'changes' => $changes,
        'roleBtns' => $roleBtns, 'selRole' => $selRole, 'selRoleName' => $roles[$selRole]->role_name ?? '', 'selRoleCount' => (int) sl_scalar('SELECT COUNT(*) FROM cdb_users WHERE userlevel = :r', [':r' => $selRole]), 'perms' => $perms, 'onCount' => $onCount,
        'canAdd' => $user->cdp_hasPermission('add_user'), 'canEdit' => $user->cdp_hasPermission('edit_user'), 'canDelete' => $user->cdp_hasPermission('delete_user'), 'canPerms' => $user->cdp_hasPermission('view_role_assignment'),
        'canViewAs' => function_exists('cdp_canViewAs'), 'viewerLevel' => (int) $user->userlevel, 'hasLog' => $hasLog,
    ];
}

function sl_inviteStaffData($user, $core): array
{
    $roles = [];
    foreach (sl_assignableRoles($user) as $rid => $r) {
        $roles[] = ['id' => $rid, 'label' => $r->role_name, 'blurb' => sl_roleBlurb($r), 'driver' => cdp_roleHasFlag($rid, 'is_driver')];
    }
    $branches = array_map(fn($r) => $r->name_off, sl_rows('SELECT name_off FROM cdb_offices ORDER BY name_off'));
    $zones = array_map(fn($r) => $r->zone, sl_rows("SELECT DISTINCT zone FROM cdb_users WHERE zone IS NOT NULL AND zone <> '' ORDER BY zone"));
    return ['roles' => $roles, 'branches' => $branches, 'zones' => $zones, 'mailOn' => !empty($core->site_email) || !empty($core->email_address), 'otpOn' => !empty($core->active_otp), 'hasZone' => sl_columnExists('cdb_users', 'zone')];
}
