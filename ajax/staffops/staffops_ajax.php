<?php
// *************************************************************************
// * Staff, Agents & Multi-Branch Management — endpoint                     *
// * (helpers/staffops.php). ?action=… GET reads, POST writes.              *
// *                                                                       *
// * Every signed-in staff member, driver or agent may reach their own     *
// * workspace actions (clock in/out, my tasks, my shifts, announcements,  *
// * approval requests). Everything else is checked against the module's  *
// * capability matrix and the person's branches (cdp_staffCan).           *
// *************************************************************************

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/querys.php');
require_once(__DIR__ . '/../../helpers/staffops.php');
require_login();

if (!cdp_staffEnsureTables()) {
    cdp_opsOut(['ok' => false, 'error' => 'The staff tables could not be created. The database user needs the CREATE privilege once.'], 500);
}
$rank = cdp_opsRank();
if ($rank < 1) {
    cdp_opsOut(['ok' => false, 'error' => 'Staff, drivers and agents only.'], 403);
}
$action = (string) ($_REQUEST['action'] ?? '');
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$P = function ($k, $d = '') { return $_POST[$k] ?? $d; };
$G = function ($k, $d = '') { return $_GET[$k] ?? $d; };
$isAdmin = $rank >= 3;
$deny = function ($msg = 'You do not have access to this.') { cdp_opsOut(['ok' => false, 'error' => $msg], 403); };
$reads = ['meta', 'branches', 'staff', 'shifts', 'tasks', 'transfers', 'transfer', 'approvals', 'announcements', 'commissions', 'performance', 'activity',
    'workspace', 'inventory', 'export_performance', 'agent'];
if (!in_array($action, $reads, true) && !$isPost) {
    cdp_opsOut(['ok' => false, 'error' => 'Use POST.'], 405);
}

switch ($action) {

    case 'meta':
        $caps = [];
        foreach (array_keys(cdp_staffCapabilities()) as $c) {
            $caps[$c] = cdp_staffCan($c);
        }
        cdp_opsOut(['ok' => true, 'is_admin' => $isAdmin, 'me' => cdp_opsUid(), 'job_role' => cdp_staffJobRole(), 'roles' => cdp_staffJobRoles(),
            'capabilities' => cdp_staffCapabilities(), 'can' => $caps, 'matrix' => cdp_staffMatrix(), 'approval_kinds' => cdp_staffApprovalKinds(), 'task_types' => cdp_staffTaskTypes(),
            'branches' => array_map(function ($b) { return ['id' => (int) $b->id, 'name' => $b->name, 'code' => $b->code]; }, cdp_staffBranches(true)),
            'my_branches' => cdp_staffBranchIds(), 'transfer_needs_approval' => cdp_opsSetting('staff', 'transfer_needs_approval', '1') === '1']);

    // ---- countries and branches ------------------------------------------
    case 'branches':
        $offices = cdp_opsTableExists('cdb_offices') ? cdp_opsQ("SELECT id, name_off name FROM cdb_offices ORDER BY name_off") : [];
        $bo = cdp_opsTableExists('cdb_branchoffices') ? cdp_opsQ("SELECT id, name_branch name FROM cdb_branchoffices ORDER BY name_branch") : [];
        cdp_opsOut(['ok' => true, 'branches' => cdp_staffBranches(), 'countries' => cdp_opsQ("SELECT * FROM cdb_staff_countries ORDER BY name"),
            'offices' => $offices, 'branch_offices' => $bo, 'inventory' => cdp_staffBranchInventory(), 'can_edit' => $isAdmin]);

    case 'save_country':
        $isAdmin || $deny('Administrators manage countries.');
        cdp_opsOut(cdp_staffSaveCountry($_POST));

    case 'save_branch':
        $isAdmin || $deny('Administrators manage branches.');
        cdp_opsOut(cdp_staffSaveBranch($_POST));

    // ---- staff ---------------------------------------------------------
    case 'staff':
        cdp_staffCan('staff.view') || cdp_staffCan('staff.manage') || $deny();
        cdp_opsOut(['ok' => true, 'rows' => cdp_staffDirectory((int) $G('branch_id'), trim((string) $G('q')))]);

    case 'save_profile':
        $home = (int) $P('home_branch_id');
        cdp_staffCan('staff.manage', $home ?: null) || $deny('You cannot manage staff in this branch.');
        $in = $_POST;
        $in['branch_ids'] = (array) ($_POST['branch_ids'] ?? []);
        cdp_opsOut(cdp_staffSaveProfile($in));

    case 'save_matrix':
        $isAdmin || $deny('Administrators change role access.');
        $m = cdp_opsDecode($P('matrix'), []);
        $clean = [];
        foreach (cdp_staffJobRoles() as $r => $label) {
            $clean[$r] = $r === 'administrator' ? array_keys(cdp_staffCapabilities()) : array_values(array_intersect((array) ($m[$r] ?? []), array_keys(cdp_staffCapabilities())));
        }
        cdp_opsSettingSet('staff', 'matrix', cdp_opsJson($clean));
        cdp_opsSettingSet('staff', 'transfer_needs_approval', $P('transfer_needs_approval') === '0' ? '0' : '1');
        cdp_staffLog('update', 'Role access matrix changed', 'matrix', 0);
        cdp_opsOut(['ok' => true]);

    // ---- shifts --------------------------------------------------------
    case 'shifts':
        $from = cdp_opsDate($G('from'), date('Y-m-d', strtotime('monday this week')));
        $to = cdp_opsDate($G('to'), date('Y-m-d', strtotime($from . ' +6 days')));
        $mine = $G('mine') === '1' || !cdp_staffCan('shifts.manage');
        cdp_opsOut(['ok' => true, 'from' => $from, 'to' => $to, 'rows' => cdp_staffShifts($from, $to, (int) $G('branch_id'), $mine ? cdp_opsUid() : 0), 'mine' => $mine]);

    case 'save_shift':
        cdp_opsOut(cdp_staffSaveShift($_POST));

    case 'cancel_shift':
        $s = cdp_opsOne("SELECT * FROM cdb_staff_shifts WHERE id = :id", [':id' => (int) $P('id')]);
        ($s && cdp_staffCan('shifts.manage', $s->branch_id !== null ? (int) $s->branch_id : null)) || $deny();
        cdp_opsOut(['ok' => cdp_opsX("UPDATE cdb_staff_shifts SET status = 'cancelled' WHERE id = :id AND status = 'scheduled'", [':id' => (int) $s->id])['ok']]);

    case 'copy_week':
        cdp_staffCan('shifts.manage', (int) $P('branch_id') ?: null) || $deny();
        cdp_opsOut(['ok' => true, 'copied' => cdp_staffCopyWeek((string) $P('week_start'), (int) $P('branch_id'))]);

    case 'clock':
        cdp_opsOut(cdp_staffClock($P('op') === 'out' ? 'out' : 'in'));

    // ---- tasks ---------------------------------------------------------
    case 'tasks':
        cdp_opsOut(['ok' => true, 'rows' => cdp_staffTasks(['mine' => $G('mine') === '1', 'status' => (string) $G('status', 'active'), 'branch_id' => (int) $G('branch_id')]),
            'assignees' => cdp_staffCan('tasks.manage') ? array_map(function ($u) { return ['id' => (int) $u->id, 'name' => $u->name]; }, cdp_staffDirectory()) : []]);

    case 'save_task':
        cdp_opsOut(cdp_staffSaveTask($_POST));

    case 'task_status':
        cdp_opsOut(cdp_staffTaskStatus((int) $P('id'), (string) $P('status')));

    // ---- transfers -----------------------------------------------------
    case 'transfers':
        cdp_opsOut(['ok' => true, 'rows' => cdp_staffTransfers((string) $G('status'))]);

    case 'transfer':
        $t = cdp_opsOne("SELECT * FROM cdb_staff_transfers WHERE id = :id", [':id' => (int) $G('id')]);
        ($t && ($isAdmin || array_intersect([(int) $t->from_branch_id, (int) $t->to_branch_id], cdp_staffBranchIds()))) || $deny();
        cdp_opsOut(['ok' => true, 'transfer' => $t, 'items' => cdp_opsQ("SELECT * FROM cdb_staff_transfer_items WHERE transfer_id = :t ORDER BY id", [':t' => (int) $t->id]),
            'can_receive' => $t->status === 'in_transit' && cdp_staffCan('transfers.receive', (int) $t->to_branch_id),
            'can_dispatch' => $t->status === 'approved' && cdp_staffCan('transfers.receive', (int) $t->from_branch_id)]);

    case 'create_transfer':
        cdp_opsOut(cdp_staffCreateTransfer($_POST));

    case 'transfer_action':
        cdp_opsOut(cdp_staffTransferAction((int) $P('id'), (string) $P('op'), (array) ($_POST['received'] ?? [])));

    case 'inventory':
        cdp_opsOut(['ok' => true, 'rows' => cdp_staffBranchInventory()]);

    // ---- approvals -----------------------------------------------------
    case 'approvals':
        cdp_opsOut(['ok' => true, 'rows' => cdp_staffApprovals((string) $G('status', 'pending'), $G('mine') === '1')]);

    case 'request_approval':
        cdp_staffCan('approvals.request') || $deny();
        cdp_opsOut(cdp_staffRequestApproval(['kind' => $P('kind'), 'title' => $P('title'), 'details' => $P('details'), 'amount' => $P('amount'), 'branch_id' => $P('branch_id')]));

    case 'decide':
        cdp_opsOut(cdp_staffDecide((int) $P('id'), (string) $P('decision'), (string) $P('note')));

    // ---- announcements -------------------------------------------------
    case 'announcements':
        $all = $G('all') === '1' && ($isAdmin || cdp_staffCan('announcements.post'));
        cdp_opsOut(['ok' => true, 'rows' => cdp_staffAnnouncementsFor(cdp_opsUid(), $all)]);

    case 'post_announcement':
        cdp_opsOut(cdp_staffPostAnnouncement($_POST));

    case 'read_announcement':
        cdp_opsOut(['ok' => cdp_staffMarkAnnouncement((int) $P('id'), $P('ack') === '1')]);

    // ---- commissions ---------------------------------------------------
    case 'commissions':
        $agentView = cdp_staffJobRole() === 'agent' && !cdp_staffCan('commissions.view');
        ($agentView || cdp_staffCan('commissions.view')) || $deny();
        $agentId = $agentView ? cdp_opsUid() : (int) $G('agent_id');
        $items = cdp_opsQ("SELECT * FROM cdb_staff_commissions WHERE " . ($agentId ? 'agent_id = :a' : '1=1') . " ORDER BY id DESC LIMIT 300", $agentId ? [':a' => $agentId] : []);
        cdp_opsOut(['ok' => true, 'summary' => cdp_staffCommissionSummary($agentId), 'items' => $items,
            'agents' => $agentView ? [] : array_map(function ($a) { return ['id' => (int) $a->id, 'name' => $a->name, 'agency_ids' => $a->agency_ids, 'calc' => $a->calc, 'rate' => $a->rate, 'min_amount' => $a->min_amount, 'own' => $a->rule_own]; }, cdp_staffAgents()),
            'default_rule' => cdp_staffCommissionRule(0), 'can_manage' => cdp_staffCan('commissions.manage')]);

    case 'save_commission_rule':
        cdp_staffCan('commissions.manage') || $deny();
        cdp_opsOut(cdp_staffSaveCommissionRule($_POST));

    case 'accrue_commissions':
        cdp_staffCan('commissions.manage') || $deny();
        cdp_opsOut(['ok' => true, 'added' => cdp_staffAccrueCommissions()]);

    case 'approve_commissions':
        cdp_staffCan('commissions.manage') || $deny();
        $r = cdp_opsX("UPDATE cdb_staff_commissions SET status = 'approved', approved_by = :u WHERE agent_id = :a AND period = :p AND status = 'pending'",
            [':u' => cdp_opsUid(), ':a' => (int) $P('agent_id'), ':p' => (string) $P('period')]);
        cdp_staffLog('status', 'Commission ' . $P('period') . ' approved for ' . cdp_opsUserName((int) $P('agent_id')), 'commission', (int) $P('agent_id'));
        cdp_opsOut(['ok' => $r['ok'], 'approved' => $r['rows']]);

    case 'pay_commissions':
        cdp_staffCan('commissions.manage') || $deny();
        cdp_opsOut(cdp_staffPayCommission((int) $P('agent_id'), (string) $P('period'), (string) $P('ref')));

    // ---- performance and activity ---------------------------------------
    case 'performance':
    case 'export_performance':
        $branch = (int) $G('branch_id');
        cdp_staffCan('reports.branch', $branch ?: null) || $deny();
        $from = cdp_opsDate($G('from'), date('Y-m-d', strtotime('-29 days')));
        $to = cdp_opsDate($G('to'), date('Y-m-d'));
        $rows = cdp_staffPerformance($from, $to, $branch);
        if ($action === 'export_performance') {
            $fmt = in_array($G('format'), ['csv', 'xlsx', 'pdf'], true) ? $G('format') : 'csv';
            cdp_opsSendExport($fmt, 'staff-performance-' . $from . '-to-' . $to, 'Staff performance ' . $from . ' to ' . $to, ['Staff performance' => [
                'head' => ['Name', 'Role', 'Branch', 'Shipments', 'Packages', 'Deliveries', 'Tasks done', 'Tasks overdue', 'Avg task min', 'On time %', 'Shifts', 'Attended', 'Attendance %', 'Punctual %', 'Hours', 'Output / hour', 'Score'],
                'rows' => array_map(function ($r) {
                    return [$r['name'], $r['job_role'], $r['branch'], $r['shipments'], $r['packages'], $r['deliveries'], $r['tasks_done'], $r['tasks_overdue'], $r['avg_task_min'],
                        $r['on_time_pct'], $r['shifts'], $r['attended'], $r['attendance_pct'], $r['punctual_pct'], $r['hours'], $r['per_hour'], $r['score']];
                }, $rows)]]);
        }
        cdp_opsOut(['ok' => true, 'from' => $from, 'to' => $to, 'rows' => $rows]);

    case 'activity':
        $branch = (int) $G('branch_id');
        cdp_staffCan('activity.view', $branch ?: null) || $deny();
        cdp_opsOut(['ok' => true, 'rows' => cdp_staffActivity(['user_id' => (int) $G('user_id'), 'branch_id' => $branch, 'from' => cdp_opsDate($G('from'), ''),
            'to' => cdp_opsDate($G('to'), ''), 'q' => trim((string) $G('q'))])]);

    // ---- the person's own workspace ------------------------------------
    case 'workspace':
        require_once(__DIR__ . '/../../helpers/staff_workspace.php');
        cdp_opsOut(['ok' => true, 'data' => cdp_staffWorkspace()]);

    case 'agent':
        require_once(__DIR__ . '/../../helpers/staff_workspace.php');
        (cdp_staffJobRole() === 'agent' || $isAdmin) || $deny('The partner portal is for agent accounts.');
        cdp_opsOut(['ok' => true, 'data' => cdp_staffAgentPortal($isAdmin && (int) $G('agent_id') ? (int) $G('agent_id') : cdp_opsUid())]);

    default:
        cdp_opsOut(['ok' => false, 'error' => 'Unknown action.'], 400);
}
