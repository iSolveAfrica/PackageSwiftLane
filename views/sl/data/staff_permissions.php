<?php
/** Data for Staff Permissions (StaffPermissions.dc.html): roles × module actions, departments, per-user overrides. */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/sl_people.php';
require_once CDP_ROOT . '/helpers/activity_log.php';

/** Column of the matrix an action name belongs to. */
function sl_pmColumn(string $action): string
{
    if (preg_match('/^(view|list|track|main_dashboard|prealert_list)/', $action)) return 'View';
    if (preg_match('/^(add|create|courier_add|pickup_add|import|register)/', $action)) return 'Create';
    if (preg_match('/^(edit|update|change|manage|assign|complete|deliver|accept|cancel|warehouse_)/', $action)) return 'Edit';
    if (preg_match('/^(delete|remove|purge)/', $action)) return 'Delete';
    if (preg_match('/^(export|print|download|send)/', $action)) return 'Export';
    return 'Other';
}

function sl_pmModuleLabel(string $name): string
{
    $map = ['PAQUETES_CASILLERO' => 'Sea packages', 'ENVIOS' => 'Air shipments', 'RECOGIDAS' => 'Pickups', 'ENVIOS CONSOLIDADOS' => 'Consolidations (air)', 'REPORTES_GENERALES' => 'Reports', 'CUENTAS_POR_COBRAR' => 'Accounts receivable', 'CLIENTES' => 'Customers and recipients', 'USUARIOS' => 'Staff accounts', 'NIVEL_CONTROL_ACCESO' => 'Roles and permissions', 'CONFIGURACION_GENERAL' => 'General settings', 'LOGISTICA' => 'Logistics', 'CONFIGURACION_ENVIOS' => 'Shipping settings', 'CONFIGURACION_PAGOS' => 'Payment settings', 'LOCALIZACIONES' => 'Locations', 'ACTUALIZACIONES' => 'Updates', 'PREALERTAS' => 'Pre-alerts', 'CONFIGURACION EXTRA' => 'Dashboards and extras', 'PAQUETES CONSOLIDADOS' => 'Consolidations (sea)', 'API_ADMINISTRATION' => 'API', 'SWIFTLANE_UI' => 'Swift Lane interface'];
    return $map[$name] ?? ucfirst(strtolower(str_replace('_', ' ', $name)));
}

/** Every module with its actions grouped by matrix column. */
function sl_pmModules(): array
{
    $cols = ['View', 'Create', 'Edit', 'Delete', 'Export', 'Other'];
    $mods = [];
    foreach (sl_rows('SELECT m.id AS mid, m.module_name, a.id, a.action_name, a.description_module FROM cdb_user_module_actions a JOIN cdb_user_module_permissions m ON m.id = a.module_id ORDER BY m.id, a.id') as $r) {
        $mid = (int) $r->mid;
        if (!isset($mods[$mid])) $mods[$mid] = ['id' => $mid, 'label' => sl_pmModuleLabel($r->module_name), 'cells' => array_fill_keys($cols, []), 'n' => 0];
        $mods[$mid]['cells'][sl_pmColumn($r->action_name)][] = ['id' => (int) $r->id, 'name' => $r->action_name, 'desc' => $r->description_module ?: $r->action_name];
        $mods[$mid]['n']++;
    }
    return ['cols' => $cols, 'mods' => array_values($mods)];
}

function sl_staffPermissionsData($user, $core): array
{
    $roles = cdp_roleFlagsMap();
    $hasDept = sl_tableExists('cdb_departments') && sl_tableExists('cdb_department_permissions') && sl_tableExists('cdb_department_members');
    $hasOvr = sl_tableExists('cdb_user_permission_overrides');
    $hasLog = cdp_activityTableReady();
    $matrix = sl_pmModules();
    $possible = (int) sl_scalar('SELECT COUNT(*) FROM cdb_user_module_actions');
    $counts = [];
    foreach (sl_rows('SELECT userlevel, COUNT(*) AS n FROM cdb_users GROUP BY userlevel') as $r) $counts[(int) $r->userlevel] = (int) $r->n;
    $roleList = [];
    $palette = ['#161615', '#44433F', '#6B6A65', '#8A8984', '#A9A8A2', '#C2C1BB', '#D9D7D0', '#E9E7E1'];
    $i = 0;
    foreach ($roles as $rid => $r) {
        $rp = cdp_resolveRolePermissions($rid);
        $roleList[$rid] = ['id' => (int) $rid, 'name' => $r->role_name, 'desc' => $r->description ?: sl_roleBlurb($r), 'members' => $counts[(int) $rid] ?? 0, 'count' => $rp['is_wildcard'] ? $possible : count($rp['allowed']), 'dot' => 'background:' . $palette[$i++ % count($palette)], 'wild' => $rp['is_wildcard'], 'allowed' => $rp['allowed'], 'editable' => cdp_canAssignRole($user, $rid) && !$rp['is_wildcard']];
    }
    $roleId = (int) ($_GET['role'] ?? 0);
    if (!isset($roleList[$roleId])) $roleId = isset($roleList[4]) ? 4 : (int) array_key_first($roleList);
    $role = $roleList[$roleId];
    $tab = (string) ($_GET['tab'] ?? 'perm');
    $userId = (int) ($_GET['user'] ?? 0);
    $deptId = (int) ($_GET['dept'] ?? 0);
    if ($userId) $tab = 'user';
    if (!in_array($tab, ['perm', 'members', 'log', 'dept', 'user'], true)) $tab = 'perm';
    $d = ['roles' => array_values($roleList), 'role' => $role, 'tab' => $tab, 'matrix' => $matrix, 'possible' => $possible, 'hasDept' => $hasDept, 'hasOvr' => $hasOvr, 'hasLog' => $hasLog,
        'staffTotal' => (int) sl_scalar('SELECT COUNT(*) FROM cdb_users u WHERE ' . sl_staffWhere('u')), 'customRoles' => max(0, count($roles) - 6),
        'canEdit' => $user->cdp_hasPermission('view_role_assignment'), 'moneyOn' => $role['wild'] || in_array('view_financial_overview', $role['allowed'], true),
        'overrideCount' => $hasOvr ? (int) sl_scalar('SELECT COUNT(DISTINCT user_id) FROM cdb_user_permission_overrides') : 0,
        'deptCount' => $hasDept ? (int) sl_scalar('SELECT COUNT(*) FROM cdb_departments') : 0];
    // direct grants of this role (not inherited) for the matrix
    $direct = [];
    foreach (sl_rows('SELECT module_action_id, permitted FROM cdb_user_role_permissions WHERE role_id = :r', [':r' => $roleId]) as $r) $direct[(int) $r->module_action_id] = (int) $r->permitted;
    $d['roleState'] = [];
    foreach ($matrix['mods'] as $m) foreach ($m['cells'] as $cells) foreach ($cells as $c) $d['roleState'][$c['id']] = $role['wild'] || in_array($c['name'], $role['allowed'], true) ? 1 : 0;
    // sensitive switches = named actions
    $sens = [['view_financial_overview', 'See financial figures', 'Revenue, costs, invoice amounts and margins', 'i-dollar'], ['view_client_list', 'See customers', 'Names, phone and e-mail of customers', 'i-phone'], ['export_activity_logs', 'Export audit logs', 'Download the activity log as CSV', 'i-download'], ['delete_shipment', 'Delete shipments', 'Remove air shipments permanently', 'i-trash'], ['view_system_logs', 'See system logs', 'PHP errors, cron and security events', 'i-clock'], ['manage_api_settings', 'Manage API keys', 'Connect and disconnect integrations', 'i-link']];
    $d['sens'] = [];
    foreach ($sens as $s) {
        $aid = (int) sl_scalar('SELECT id FROM cdb_user_module_actions WHERE action_name = :a', [':a' => $s[0]]);
        if ($aid) $d['sens'][] = ['id' => $aid, 'name' => $s[0], 'label' => $s[1], 'desc' => $s[2], 'icon' => $s[3], 'on' => $d['roleState'][$aid] ?? 0];
    }
    $d['members'] = array_map(fn($r) => ['id' => (int) $r->id, 'name' => trim($r->fname . ' ' . $r->lname) ?: $r->username, 'ini' => sl_initials($r->fname . ' ' . $r->lname), 'branch' => $r->name_off ?: '—', 'seen' => $r->lastlogin && substr($r->lastlogin, 0, 4) !== '0000' ? sl_ago($r->lastlogin) : 'never', 'managed' => cdp_canManageUser($user, (int) $r->userlevel)], sl_rows('SELECT id, username, fname, lname, name_off, lastlogin, userlevel FROM cdb_users WHERE userlevel = :r ORDER BY fname LIMIT 100', [':r' => $roleId]));
    $d['assignable'] = array_values(array_map(fn($r) => ['id' => (int) $r->role_id, 'name' => $r->role_name], sl_assignableRoles($user)));
    $d['log'] = $hasLog ? array_map(fn($r) => ['ini' => sl_initials($r->actor_name ?: '?'), 'who' => $r->actor_name ?: 'System', 'what' => $r->summary ?: $r->action_label, 'at' => sl_date($r->created_at, 'M j')], sl_rows("SELECT actor_name, summary, action_label, created_at FROM cdb_activity_log WHERE module = 'access' AND verb <> 'view' ORDER BY id DESC LIMIT 12")) : [];
    // departments
    $d['depts'] = [];
    $d['dept'] = null;
    if ($hasDept) {
        foreach (sl_rows('SELECT d.*, (SELECT COUNT(*) FROM cdb_department_members m WHERE m.department_id = d.id) AS members, (SELECT COUNT(*) FROM cdb_department_permissions p WHERE p.department_id = d.id) AS rules FROM cdb_departments d ORDER BY d.name') as $r) {
            $d['depts'][] = ['id' => (int) $r->id, 'name' => $r->name, 'desc' => $r->description, 'role' => $roleList[(int) $r->base_role_id]['name'] ?? '—', 'base' => (int) $r->base_role_id, 'members' => (int) $r->members, 'rules' => (int) $r->rules];
        }
        if ($deptId) {
            foreach ($d['depts'] as $dp) if ($dp['id'] === $deptId) $d['dept'] = $dp;
            if ($d['dept']) {
                $d['deptState'] = [];
                foreach (sl_rows('SELECT module_action_id, permitted FROM cdb_department_permissions WHERE department_id = :d', [':d' => $deptId]) as $r) $d['deptState'][(int) $r->module_action_id] = (int) $r->permitted ? 'allow' : 'deny';
                $base = $roleList[$d['dept']['base']] ?? null;
                $d['deptBase'] = [];
                if ($base) foreach ($matrix['mods'] as $m) foreach ($m['cells'] as $cells) foreach ($cells as $c) $d['deptBase'][$c['id']] = $base['wild'] || in_array($c['name'], $base['allowed'], true) ? 1 : 0;
                $memberIds = array_map(fn($r) => (int) $r->user_id, sl_rows('SELECT user_id FROM cdb_department_members WHERE department_id = :d', [':d' => $deptId]));
                $d['deptPeople'] = array_map(fn($r) => ['id' => (int) $r->id, 'name' => trim($r->fname . ' ' . $r->lname) ?: $r->username, 'ini' => sl_initials($r->fname . ' ' . $r->lname), 'member' => in_array((int) $r->id, $memberIds, true), 'role' => $roleList[(int) $r->userlevel]['name'] ?? ''], sl_rows('SELECT id, username, fname, lname, userlevel FROM cdb_users u WHERE ' . sl_staffWhere('u') . ' ORDER BY fname LIMIT 200'));
            }
        }
    }
    // per-user overrides
    $d['target'] = null;
    if ($userId) {
        $t = sl_row('SELECT id, username, fname, lname, userlevel, name_off FROM cdb_users WHERE id = :id', [':id' => $userId]);
        if ($t && cdp_canManageUser($user, (int) $t->userlevel)) {
            $tr = $roleList[(int) $t->userlevel] ?? null;
            $d['target'] = ['id' => (int) $t->id, 'name' => trim($t->fname . ' ' . $t->lname) ?: $t->username, 'ini' => sl_initials($t->fname . ' ' . $t->lname), 'role' => $tr['name'] ?? ('Role #' . $t->userlevel), 'rid' => (int) $t->userlevel, 'branch' => $t->name_off];
            $d['userBase'] = [];
            foreach ($matrix['mods'] as $m) foreach ($m['cells'] as $cells) foreach ($cells as $c) $d['userBase'][$c['id']] = $tr && ($tr['wild'] || in_array($c['name'], $tr['allowed'], true)) ? 1 : 0;
            $d['userState'] = [];
            if ($hasOvr) foreach (sl_rows('SELECT module_action_id, permitted FROM cdb_user_permission_overrides WHERE user_id = :u', [':u' => $userId]) as $r) $d['userState'][(int) $r->module_action_id] = (int) $r->permitted ? 'allow' : 'deny';
            $d['userDepts'] = $hasDept ? array_map(fn($r) => $r->name, sl_rows('SELECT d.name FROM cdb_department_members m JOIN cdb_departments d ON d.id = m.department_id WHERE m.user_id = :u', [':u' => $userId])) : [];
        }
    }
    return $d;
}
