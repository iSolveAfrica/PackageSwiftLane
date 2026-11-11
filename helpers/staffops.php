<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************

/**
 * Staff, Agents & Multi-Branch Management.
 *
 *   - Countries of operation and branches (warehouses, offices, hubs, agent
 *     points). A branch can be linked to an existing office (cdb_offices) and
 *     branch office (cdb_branchoffices), which is how shipments already in the
 *     system are counted per branch without changing them.
 *   - Staff profiles on top of the existing user accounts (cdb_users): job
 *     role (warehouse operator, finance officer, dispatch officer, customer
 *     service, branch manager, driver, partner agent), home branch and the
 *     branches the person may work in. The app's own role/permission system
 *     still decides which pages open; the job role decides which workspace a
 *     person sees and what they may do inside this module (a capability
 *     matrix administrators can edit).
 *   - Shifts with check-in / check-out, warehouse tasks, branch-to-branch
 *     transfers with item-level receipt, approval requests with amount
 *     limits, announcements with acknowledgement, agent commissions,
 *     productivity and performance figures, and an activity log (every
 *     change here also goes to the system audit trail, cdb_activity_log).
 *
 * Administrators (rank 3+) can do everything. Everyone else is limited by
 * the capability matrix and, for branch-scoped capabilities, by the branches
 * they are assigned to.
 */

require_once __DIR__ . '/ops_db.php';
require_once __DIR__ . '/rbac.php';

if (!defined('CDP_STAFF_SCHEMA')) {
    define('CDP_STAFF_SCHEMA', '1');
}

// ---------------------------------------------------------------------------
// Catalogue
// ---------------------------------------------------------------------------

if (!function_exists('cdp_staffJobRoles')) {

    function cdp_staffJobRoles()
    {
        return [
            'warehouse_operator' => 'Warehouse operator',
            'finance_officer' => 'Finance officer',
            'dispatch_officer' => 'Dispatch officer',
            'customer_service' => 'Customer service',
            'branch_manager' => 'Branch manager',
            'driver' => 'Driver',
            'agent' => 'Partner agent',
            'administrator' => 'Administrator',
        ];
    }

    /** Capabilities inside this module. Branch-scoped ones are checked against the person's branches. */
    function cdp_staffCapabilities()
    {
        return [
            'staff.view' => ['label' => 'See staff directory', 'scoped' => true],
            'staff.manage' => ['label' => 'Edit staff profiles and branch access', 'scoped' => true],
            'shifts.manage' => ['label' => 'Plan shifts', 'scoped' => true],
            'tasks.manage' => ['label' => 'Create and assign tasks', 'scoped' => true],
            'transfers.create' => ['label' => 'Request transfers', 'scoped' => true],
            'transfers.receive' => ['label' => 'Dispatch and receive transfers', 'scoped' => true],
            'approvals.request' => ['label' => 'Request approvals', 'scoped' => false],
            'approvals.decide' => ['label' => 'Decide approvals (within the limit)', 'scoped' => true],
            'announcements.post' => ['label' => 'Post announcements', 'scoped' => true],
            'commissions.view' => ['label' => 'See agent commissions', 'scoped' => false],
            'commissions.manage' => ['label' => 'Approve and pay commissions', 'scoped' => false],
            'reports.branch' => ['label' => 'Branch performance and productivity', 'scoped' => true],
            'activity.view' => ['label' => 'Staff activity log', 'scoped' => true],
        ];
    }

    function cdp_staffDefaultMatrix()
    {
        return [
            'warehouse_operator' => ['transfers.receive', 'approvals.request'],
            'finance_officer' => ['approvals.request', 'approvals.decide', 'commissions.view', 'commissions.manage'],
            'dispatch_officer' => ['transfers.create', 'transfers.receive', 'approvals.request', 'tasks.manage'],
            'customer_service' => ['approvals.request'],
            'branch_manager' => ['staff.view', 'staff.manage', 'shifts.manage', 'tasks.manage', 'transfers.create', 'transfers.receive', 'approvals.request',
                'approvals.decide', 'announcements.post', 'reports.branch', 'activity.view', 'commissions.view'],
            'driver' => ['approvals.request'],
            'agent' => ['approvals.request'],
            'administrator' => array_keys(cdp_staffCapabilities()),
        ];
    }

    function cdp_staffApprovalKinds()
    {
        return ['expense' => 'Expense', 'refund' => 'Refund', 'discount' => 'Discount / waiver', 'transfer' => 'Branch transfer', 'leave' => 'Leave',
            'shift_swap' => 'Shift swap', 'commission' => 'Commission payout', 'other' => 'Other'];
    }

    function cdp_staffTaskTypes()
    {
        return ['receive' => 'Receive', 'sort' => 'Sort / label', 'pack' => 'Pack / consolidate', 'dispatch' => 'Dispatch', 'count' => 'Stock count', 'deliver' => 'Deliver', 'other' => 'Other'];
    }
}

// ---------------------------------------------------------------------------
// Tables
// ---------------------------------------------------------------------------

if (!function_exists('cdp_staffEnsureTables')) {

    function cdp_staffEnsureTables()
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        if (cdp_opsSetting('staff', 'schema_version', '') === CDP_STAFF_SCHEMA) {
            return $ready = true;
        }
        $t = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';
        $ok = cdp_opsDdl([
            "CREATE TABLE IF NOT EXISTS cdb_staff_countries (
                id INT NOT NULL AUTO_INCREMENT, name VARCHAR(100) NOT NULL, iso2 CHAR(2) NULL, currency VARCHAR(8) NULL, timezone VARCHAR(64) NULL,
                active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY uq_name (name))$t",
            "CREATE TABLE IF NOT EXISTS cdb_staff_branches (
                id INT NOT NULL AUTO_INCREMENT, code VARCHAR(20) NOT NULL, name VARCHAR(120) NOT NULL, type VARCHAR(20) NOT NULL DEFAULT 'warehouse',
                country_id INT NULL, city VARCHAR(100) NULL, address VARCHAR(255) NULL, phone VARCHAR(40) NULL, manager_id INT NULL,
                office_id INT NULL, branch_office_id INT NULL, active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY uq_code (code), KEY idx_country (country_id))$t",
            "CREATE TABLE IF NOT EXISTS cdb_staff_profiles (
                user_id INT NOT NULL, employee_no VARCHAR(30) NULL, job_role VARCHAR(30) NOT NULL DEFAULT 'customer_service', home_branch_id INT NULL,
                hire_date DATE NULL, status VARCHAR(12) NOT NULL DEFAULT 'active', approval_limit DECIMAL(14,2) NOT NULL DEFAULT 0,
                notes VARCHAR(255) NULL, updated_by INT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (user_id), KEY idx_role (job_role), KEY idx_branch (home_branch_id))$t",
            "CREATE TABLE IF NOT EXISTS cdb_staff_branch_access (
                user_id INT NOT NULL, branch_id INT NOT NULL, PRIMARY KEY (user_id, branch_id), KEY idx_branch (branch_id))$t",
            "CREATE TABLE IF NOT EXISTS cdb_staff_shifts (
                id INT NOT NULL AUTO_INCREMENT, user_id INT NOT NULL, branch_id INT NULL, shift_date DATE NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL,
                note VARCHAR(190) NULL, status VARCHAR(12) NOT NULL DEFAULT 'scheduled', check_in_at DATETIME NULL, check_out_at DATETIME NULL,
                created_by INT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), KEY idx_user (user_id, shift_date), KEY idx_branch (branch_id, shift_date))$t",
            "CREATE TABLE IF NOT EXISTS cdb_staff_tasks (
                id INT NOT NULL AUTO_INCREMENT, branch_id INT NULL, title VARCHAR(190) NOT NULL, description TEXT NULL, task_type VARCHAR(20) NOT NULL DEFAULT 'other',
                entity_ref VARCHAR(80) NULL, quantity INT NOT NULL DEFAULT 1, priority VARCHAR(8) NOT NULL DEFAULT 'normal', assignee_id INT NULL, due_at DATETIME NULL,
                status VARCHAR(12) NOT NULL DEFAULT 'open', started_at DATETIME NULL, completed_at DATETIME NULL, created_by INT NULL, created_at DATETIME NOT NULL,
                PRIMARY KEY (id), KEY idx_assignee (assignee_id, status), KEY idx_branch (branch_id, status), KEY idx_done (completed_at))$t",
            "CREATE TABLE IF NOT EXISTS cdb_staff_transfers (
                id INT NOT NULL AUTO_INCREMENT, ref VARCHAR(20) NOT NULL, from_branch_id INT NOT NULL, to_branch_id INT NOT NULL,
                status VARCHAR(12) NOT NULL DEFAULT 'requested', notes VARCHAR(255) NULL, requested_by INT NULL, approved_by INT NULL, approved_at DATETIME NULL,
                dispatched_by INT NULL, dispatched_at DATETIME NULL, received_by INT NULL, received_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY uq_ref (ref), KEY idx_from (from_branch_id, status), KEY idx_to (to_branch_id, status))$t",
            "CREATE TABLE IF NOT EXISTS cdb_staff_transfer_items (
                id INT NOT NULL AUTO_INCREMENT, transfer_id INT NOT NULL, entity_type VARCHAR(12) NOT NULL, entity_id INT NULL, entity_ref VARCHAR(80) NOT NULL,
                received TINYINT(1) NOT NULL DEFAULT 0, received_note VARCHAR(190) NULL,
                PRIMARY KEY (id), KEY idx_transfer (transfer_id), KEY idx_ref (entity_ref))$t",
            "CREATE TABLE IF NOT EXISTS cdb_staff_approvals (
                id INT NOT NULL AUTO_INCREMENT, kind VARCHAR(20) NOT NULL, title VARCHAR(190) NOT NULL, details TEXT NULL, amount DECIMAL(14,2) NULL,
                branch_id INT NULL, ref_type VARCHAR(20) NULL, ref_id INT NULL, requested_by INT NOT NULL, status VARCHAR(12) NOT NULL DEFAULT 'pending',
                decided_by INT NULL, decided_at DATETIME NULL, decision_note VARCHAR(255) NULL, created_at DATETIME NOT NULL,
                PRIMARY KEY (id), KEY idx_status (status, created_at), KEY idx_requester (requested_by))$t",
            "CREATE TABLE IF NOT EXISTS cdb_staff_announcements (
                id INT NOT NULL AUTO_INCREMENT, title VARCHAR(190) NOT NULL, body TEXT NOT NULL, audience_type VARCHAR(10) NOT NULL DEFAULT 'all',
                audience_value VARCHAR(40) NULL, pinned TINYINT(1) NOT NULL DEFAULT 0, requires_ack TINYINT(1) NOT NULL DEFAULT 0, expires_at DATE NULL,
                created_by INT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), KEY idx_created (created_at))$t",
            "CREATE TABLE IF NOT EXISTS cdb_staff_announcement_reads (
                announcement_id INT NOT NULL, user_id INT NOT NULL, read_at DATETIME NOT NULL, ack_at DATETIME NULL, PRIMARY KEY (announcement_id, user_id))$t",
            "CREATE TABLE IF NOT EXISTS cdb_staff_commission_rules (
                id INT NOT NULL AUTO_INCREMENT, agent_id INT NOT NULL DEFAULT 0, calc VARCHAR(8) NOT NULL DEFAULT 'percent', value DECIMAL(10,4) NOT NULL DEFAULT 0,
                min_amount DECIMAL(14,2) NOT NULL DEFAULT 0, active TINYINT(1) NOT NULL DEFAULT 1, updated_at DATETIME NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY uq_agent (agent_id))$t",
            "CREATE TABLE IF NOT EXISTS cdb_staff_commissions (
                id INT NOT NULL AUTO_INCREMENT, agent_id INT NOT NULL, entity_type VARCHAR(12) NOT NULL, entity_id INT NOT NULL, entity_ref VARCHAR(80) NULL,
                basis_amount DECIMAL(14,2) NOT NULL DEFAULT 0, calc VARCHAR(8) NOT NULL, rate DECIMAL(10,4) NOT NULL, amount DECIMAL(14,2) NOT NULL,
                period CHAR(7) NOT NULL, status VARCHAR(10) NOT NULL DEFAULT 'pending', approved_by INT NULL, paid_at DATETIME NULL, paid_ref VARCHAR(80) NULL,
                created_at DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY uq_entity (agent_id, entity_type, entity_id), KEY idx_period (period, status))$t",
        ]);
        if ($ok) {
            if (cdp_opsSetting('staff', 'matrix', '') === '') {
                cdp_opsSettingSet('staff', 'matrix', cdp_opsJson(cdp_staffDefaultMatrix()), 0);
            }
            cdp_opsSettingSet('staff', 'schema_version', CDP_STAFF_SCHEMA, 0);
        }
        return $ready = $ok;
    }
}

// ---------------------------------------------------------------------------
// Access
// ---------------------------------------------------------------------------

if (!function_exists('cdp_staffProfile')) {

    function cdp_staffProfile($userId)
    {
        static $cache = [];
        $userId = (int) $userId;
        if (!array_key_exists($userId, $cache)) {
            $cache[$userId] = $userId > 0 ? cdp_opsOne("SELECT * FROM cdb_staff_profiles WHERE user_id = :u", [':u' => $userId]) : null;
        }
        return $cache[$userId];
    }

    /** Job role: the profile's, else derived from the account's role type. */
    function cdp_staffJobRole($u = null)
    {
        global $user;
        $u = $u ?: $user;
        if (!($u instanceof User) || empty($u->logged_in)) {
            return '';
        }
        $p = cdp_staffProfile((int) $u->uid);
        if ($p && (string) $p->status === 'active') {
            return (string) $p->job_role;
        }
        $rank = cdp_opsRank($u);
        if ($rank >= 3) {
            return 'administrator';
        }
        if (cdp_roleHasFlag((int) $u->userlevel, 'is_driver')) {
            return 'driver';
        }
        if (cdp_roleHasFlag((int) $u->userlevel, 'is_agency')) {
            return 'agent';
        }
        return $rank >= 2 ? 'customer_service' : '';
    }

    function cdp_staffMatrix()
    {
        $m = cdp_opsDecode(cdp_opsSetting('staff', 'matrix', ''), []);
        return $m ?: cdp_staffDefaultMatrix();
    }

    /** Branch ids the person may work in (home branch + assigned). Admins: all. */
    function cdp_staffBranchIds($userId = null)
    {
        $userId = (int) ($userId ?? cdp_opsUid());
        $ids = array_map(function ($r) { return (int) $r->branch_id; }, cdp_opsQ("SELECT branch_id FROM cdb_staff_branch_access WHERE user_id = :u", [':u' => $userId]));
        $p = cdp_staffProfile($userId);
        if ($p && (int) $p->home_branch_id > 0) {
            $ids[] = (int) $p->home_branch_id;
        }
        foreach (cdp_opsQ("SELECT id FROM cdb_staff_branches WHERE manager_id = :u", [':u' => $userId]) as $b) {
            $ids[] = (int) $b->id;
        }
        return array_values(array_unique($ids));
    }

    /**
     * May the signed-in person use this capability (for this branch)?
     * Pure decision in cdp_staffCanDecide() (unit tested).
     */
    function cdp_staffCan($cap, $branchId = null, $u = null)
    {
        global $user;
        $u = $u ?: $user;
        $rank = cdp_opsRank($u);
        if ($rank < 0) {
            return false;
        }
        $role = cdp_staffJobRole($u);
        $branches = ($branchId !== null && $rank < 3) ? cdp_staffBranchIds((int) $u->uid) : [];
        return cdp_staffCanDecide($cap, $rank, $role, cdp_staffMatrix(), $branchId, $branches);
    }

    function cdp_staffCanDecide($cap, $rank, $role, array $matrix, $branchId, array $myBranches)
    {
        if ($rank >= 3) {
            return true;
        }
        if ($role === '' || !in_array($cap, (array) ($matrix[$role] ?? []), true)) {
            return false;
        }
        $caps = cdp_staffCapabilities();
        if ($branchId !== null && !empty($caps[$cap]['scoped'])) {
            return in_array((int) $branchId, array_map('intval', $myBranches), true);
        }
        return true;
    }

    /** "Scope" SQL for branch-scoped lists: admins see all, others their branches. */
    function cdp_staffScopeSql($column, $u = null)
    {
        if (cdp_opsRank($u) >= 3) {
            return '1=1';
        }
        $ids = cdp_staffBranchIds();
        return $ids ? "$column IN (" . implode(',', array_map('intval', $ids)) . ")" : '1=0';
    }

    function cdp_staffLog($verb, $summary, $entityType = '', $entityId = '', $label = '', array $meta = [])
    {
        require_once __DIR__ . '/activity_log.php';
        if (function_exists('cdp_activityLog')) {
            cdp_activityLog(['module' => 'users', 'verb' => $verb, 'action' => 'staff.' . $entityType . '.' . $verb, 'label' => 'Staff & Branches · ' . ucfirst($verb),
                'summary' => $summary, 'entity_type' => $entityType, 'entity_id' => (string) $entityId, 'entity_label' => $label, 'meta' => $meta]);
        }
    }
}

// ---------------------------------------------------------------------------
// Countries, branches, staff
// ---------------------------------------------------------------------------

if (!function_exists('cdp_staffBranches')) {

    function cdp_staffBranches($activeOnly = false)
    {
        return cdp_opsQ("SELECT b.*, c.name country_name, c.currency, TRIM(CONCAT(COALESCE(m.fname,''),' ',COALESCE(m.lname,''))) manager_name,
                           (SELECT COUNT(*) FROM cdb_staff_profiles p WHERE p.home_branch_id = b.id AND p.status = 'active') staff_count
                         FROM cdb_staff_branches b LEFT JOIN cdb_staff_countries c ON c.id = b.country_id LEFT JOIN cdb_users m ON m.id = b.manager_id
                         " . ($activeOnly ? 'WHERE b.active = 1' : '') . " ORDER BY c.name, b.name");
    }

    function cdp_staffSaveCountry(array $d)
    {
        $name = trim((string) ($d['name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'error' => 'Country name is required.'];
        }
        $b = [':n' => mb_substr($name, 0, 100), ':i' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) ($d['iso2'] ?? '')), 0, 2)) ?: null,
            ':c' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) ($d['currency'] ?? '')), 0, 8)) ?: null,
            ':t' => in_array((string) ($d['timezone'] ?? ''), timezone_identifiers_list(), true) ? (string) $d['timezone'] : null, ':a' => !empty($d['active']) ? 1 : 0];
        $id = (int) ($d['id'] ?? 0);
        if ($id) {
            $b[':id'] = $id;
            $r = cdp_opsX("UPDATE cdb_staff_countries SET name = :n, iso2 = :i, currency = :c, timezone = :t, active = :a WHERE id = :id", $b);
        } else {
            $b[':now'] = cdp_opsNow();
            $r = cdp_opsX("INSERT INTO cdb_staff_countries (name, iso2, currency, timezone, active, created_at) VALUES (:n, :i, :c, :t, :a, :now)", $b);
            $id = $r['id'];
        }
        if ($r['ok']) {
            cdp_staffLog(((int) ($d['id'] ?? 0)) ? 'update' : 'create', 'Country of operation ' . $name . ' saved', 'country', $id, $name);
        }
        return ['ok' => $r['ok'], 'id' => $id, 'error' => $r['ok'] ? '' : 'Could not save (is the name already used?)'];
    }

    function cdp_staffSaveBranch(array $d)
    {
        $name = trim((string) ($d['name'] ?? ''));
        $code = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($d['code'] ?? '')));
        if ($name === '' || $code === '') {
            return ['ok' => false, 'error' => 'Code and name are required.'];
        }
        $type = in_array($d['type'] ?? '', ['warehouse', 'office', 'hub', 'agent'], true) ? $d['type'] : 'warehouse';
        $b = [':c' => mb_substr($code, 0, 20), ':n' => mb_substr($name, 0, 120), ':t' => $type, ':co' => (int) ($d['country_id'] ?? 0) ?: null,
            ':ci' => mb_substr(trim((string) ($d['city'] ?? '')), 0, 100), ':a' => mb_substr(trim((string) ($d['address'] ?? '')), 0, 255),
            ':p' => mb_substr(trim((string) ($d['phone'] ?? '')), 0, 40), ':m' => (int) ($d['manager_id'] ?? 0) ?: null,
            ':o' => (int) ($d['office_id'] ?? 0) ?: null, ':bo' => (int) ($d['branch_office_id'] ?? 0) ?: null, ':ac' => !isset($d['active']) || !empty($d['active']) ? 1 : 0, ':now' => cdp_opsNow()];
        $id = (int) ($d['id'] ?? 0);
        if ($id) {
            $b[':id'] = $id;
            $r = cdp_opsX("UPDATE cdb_staff_branches SET code = :c, name = :n, type = :t, country_id = :co, city = :ci, address = :a, phone = :p, manager_id = :m,
                           office_id = :o, branch_office_id = :bo, active = :ac, updated_at = :now WHERE id = :id", $b);
        } else {
            $r = cdp_opsX("INSERT INTO cdb_staff_branches (code, name, type, country_id, city, address, phone, manager_id, office_id, branch_office_id, active, created_at, updated_at)
                           VALUES (:c, :n, :t, :co, :ci, :a, :p, :m, :o, :bo, :ac, :now, :now)", $b);
            $id = $r['id'];
        }
        if ($r['ok']) {
            cdp_staffLog(((int) ($d['id'] ?? 0)) ? 'update' : 'create', 'Branch ' . $code . ' ' . $name . ' saved', 'branch', $id, $name);
        }
        return ['ok' => $r['ok'], 'id' => $id, 'error' => $r['ok'] ? '' : 'Could not save (is the code already used?)'];
    }

    /** Every staff-type account (rank 1+: staff, admins, drivers, agents) with its profile. */
    function cdp_staffDirectory($branchId = 0, $q = '')
    {
        $roles = [];
        foreach (cdp_roleFlagsMap() as $rid => $r) {
            if (cdp_roleRankById($rid) >= 1) {
                $roles[] = (int) $rid;
            }
        }
        $roles = $roles ?: [2, 3, 4, 6, 9];
        $w = 'u.userlevel IN (' . implode(',', $roles) . ')';
        $b = [];
        if ($branchId > 0) {
            $w .= ' AND (p.home_branch_id = :b OR EXISTS (SELECT 1 FROM cdb_staff_branch_access a WHERE a.user_id = u.id AND a.branch_id = :b))';
            $b[':b'] = (int) $branchId;
        }
        if ($q !== '') {
            $w .= ' AND (CONCAT(u.fname, \' \', u.lname) LIKE :q OR u.username LIKE :q OR u.email LIKE :q OR p.employee_no LIKE :q)';
            $b[':q'] = '%' . $q . '%';
        }
        $scope = cdp_opsRank() >= 3 ? '' : ' AND (' . cdp_staffScopeSql('p.home_branch_id') . ' OR u.id = ' . cdp_opsUid() . ')';
        $rows = cdp_opsQ("SELECT u.id, u.username, u.fname, u.lname, u.email, u.phone, u.userlevel, u.active, u.lastlogin, r.role_name,
                                 p.employee_no, p.job_role, p.home_branch_id, p.hire_date, p.status, p.approval_limit, br.name branch_name
                          FROM cdb_users u LEFT JOIN cdb_user_roles r ON r.role_id = u.userlevel
                          LEFT JOIN cdb_staff_profiles p ON p.user_id = u.id LEFT JOIN cdb_staff_branches br ON br.id = p.home_branch_id
                          WHERE $w $scope ORDER BY u.fname, u.lname LIMIT 1000", $b);
        $access = [];
        foreach (cdp_opsQ("SELECT user_id, branch_id FROM cdb_staff_branch_access") as $a) {
            $access[(int) $a->user_id][] = (int) $a->branch_id;
        }
        foreach ($rows as $r) {
            $r->name = trim($r->fname . ' ' . $r->lname) ?: $r->username;
            $r->branch_ids = $access[(int) $r->id] ?? [];
            if (!$r->job_role) {
                $r->job_role_guess = cdp_roleRankById((int) $r->userlevel) >= 3 ? 'administrator'
                    : (cdp_roleHasFlag((int) $r->userlevel, 'is_driver') ? 'driver' : (cdp_roleHasFlag((int) $r->userlevel, 'is_agency') ? 'agent' : 'customer_service'));
            }
        }
        return $rows;
    }

    function cdp_staffSaveProfile(array $d)
    {
        $uid = (int) ($d['user_id'] ?? 0);
        $u = cdp_opsOne("SELECT id, userlevel, fname, lname FROM cdb_users WHERE id = :id", [':id' => $uid]);
        if (!$u || cdp_roleRankById((int) $u->userlevel) < 1) {
            return ['ok' => false, 'error' => 'Not a staff account.'];
        }
        global $user;
        if (!cdp_canManageUser($user, (int) $u->userlevel) && $uid !== cdp_opsUid()) {
            return ['ok' => false, 'error' => 'You cannot manage an account of equal or higher rank.'];
        }
        $role = (string) ($d['job_role'] ?? '');
        if (!isset(cdp_staffJobRoles()[$role])) {
            return ['ok' => false, 'error' => 'Choose a job role.'];
        }
        if ($role === 'administrator' && cdp_roleRankById((int) $u->userlevel) < 3) {
            return ['ok' => false, 'error' => 'The administrator job role needs an administrator account.'];
        }
        $home = (int) ($d['home_branch_id'] ?? 0) ?: null;
        if (cdp_opsRank() < 3 && $home && !in_array($home, cdp_staffBranchIds(), true)) {
            return ['ok' => false, 'error' => 'You can only place staff in your own branches.'];
        }
        $status = in_array($d['status'] ?? 'active', ['active', 'suspended', 'left'], true) ? $d['status'] : 'active';
        $r = cdp_opsX("INSERT INTO cdb_staff_profiles (user_id, employee_no, job_role, home_branch_id, hire_date, status, approval_limit, notes, updated_by, updated_at)
                       VALUES (:u, :e, :r, :h, :hd, :s, :l, :n, :by, :now)
                       ON DUPLICATE KEY UPDATE employee_no = VALUES(employee_no), job_role = VALUES(job_role), home_branch_id = VALUES(home_branch_id), hire_date = VALUES(hire_date),
                         status = VALUES(status), approval_limit = VALUES(approval_limit), notes = VALUES(notes), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)",
            [':u' => $uid, ':e' => mb_substr(trim((string) ($d['employee_no'] ?? '')), 0, 30) ?: null, ':r' => $role, ':h' => $home,
                ':hd' => cdp_opsDate($d['hire_date'] ?? '', '') ?: null, ':s' => $status, ':l' => max(0, (float) ($d['approval_limit'] ?? 0)),
                ':n' => mb_substr(trim((string) ($d['notes'] ?? '')), 0, 255), ':by' => cdp_opsUid(), ':now' => cdp_opsNow()]);
        if ($r['ok'] && isset($d['branch_ids'])) {
            $ids = array_unique(array_filter(array_map('intval', (array) $d['branch_ids'])));
            if (cdp_opsRank() < 3) {
                $ids = array_intersect($ids, cdp_staffBranchIds());
            }
            cdp_opsX("DELETE FROM cdb_staff_branch_access WHERE user_id = :u", [':u' => $uid]);
            foreach ($ids as $bid) {
                cdp_opsX("INSERT IGNORE INTO cdb_staff_branch_access (user_id, branch_id) VALUES (:u, :b)", [':u' => $uid, ':b' => $bid]);
            }
        }
        if ($r['ok']) {
            cdp_staffLog('update', 'Staff profile of ' . trim($u->fname . ' ' . $u->lname) . ' set to ' . cdp_staffJobRoles()[$role], 'staff', $uid, trim($u->fname . ' ' . $u->lname));
        }
        return ['ok' => $r['ok']];
    }
}

// ---------------------------------------------------------------------------
// Shifts
// ---------------------------------------------------------------------------

if (!function_exists('cdp_staffShiftsOverlap')) {

    /** Do two same-day shifts overlap? Times 'HH:MM[:SS]'. Overnight end < start wraps. Pure. */
    function cdp_staffShiftsOverlap($aStart, $aEnd, $bStart, $bEnd)
    {
        $m = function ($t) { $p = explode(':', (string) $t); return (int) $p[0] * 60 + (int) ($p[1] ?? 0); };
        $a1 = $m($aStart);
        $a2 = $m($aEnd);
        $b1 = $m($bStart);
        $b2 = $m($bEnd);
        if ($a2 <= $a1) {
            $a2 += 1440;
        }
        if ($b2 <= $b1) {
            $b2 += 1440;
        }
        return $a1 < $b2 && $b1 < $a2;
    }

    function cdp_staffSaveShift(array $d)
    {
        $uid = (int) ($d['user_id'] ?? 0);
        $branch = (int) ($d['branch_id'] ?? 0) ?: null;
        $date = cdp_opsDate($d['shift_date'] ?? '', '');
        $start = preg_match('/^\d{2}:\d{2}$/', (string) ($d['start_time'] ?? '')) ? $d['start_time'] : '';
        $end = preg_match('/^\d{2}:\d{2}$/', (string) ($d['end_time'] ?? '')) ? $d['end_time'] : '';
        if (!$uid || $date === '' || $start === '' || $end === '' || $start === $end) {
            return ['ok' => false, 'error' => 'Staff member, date, start and end are required.'];
        }
        if (!cdp_staffCan('shifts.manage', $branch)) {
            return ['ok' => false, 'error' => 'You cannot plan shifts for this branch.'];
        }
        $id = (int) ($d['id'] ?? 0);
        foreach (cdp_opsQ("SELECT id, start_time, end_time FROM cdb_staff_shifts WHERE user_id = :u AND shift_date = :d AND status <> 'cancelled' AND id <> :id",
            [':u' => $uid, ':d' => $date, ':id' => $id]) as $o) {
            if (cdp_staffShiftsOverlap($start, $end, $o->start_time, $o->end_time)) {
                return ['ok' => false, 'error' => 'This overlaps another shift of the same person (' . substr($o->start_time, 0, 5) . '-' . substr($o->end_time, 0, 5) . ').'];
            }
        }
        $b = [':u' => $uid, ':b' => $branch, ':d' => $date, ':s' => $start, ':e' => $end, ':n' => mb_substr(trim((string) ($d['note'] ?? '')), 0, 190)];
        if ($id) {
            $b[':id'] = $id;
            $r = cdp_opsX("UPDATE cdb_staff_shifts SET user_id = :u, branch_id = :b, shift_date = :d, start_time = :s, end_time = :e, note = :n WHERE id = :id AND status = 'scheduled'", $b);
        } else {
            $b[':by'] = cdp_opsUid();
            $b[':now'] = cdp_opsNow();
            $r = cdp_opsX("INSERT INTO cdb_staff_shifts (user_id, branch_id, shift_date, start_time, end_time, note, created_by, created_at) VALUES (:u, :b, :d, :s, :e, :n, :by, :now)", $b);
        }
        if ($r['ok']) {
            cdp_staffLog($id ? 'update' : 'create', 'Shift ' . $date . ' ' . $start . '-' . $end . ' for ' . cdp_opsUserName($uid), 'shift', $id ?: $r['id']);
        }
        return ['ok' => $r['ok']];
    }

    /** Copy every scheduled shift of a week to the next week (skips overlaps). */
    function cdp_staffCopyWeek($weekStart, $branchId = 0)
    {
        $from = cdp_opsDate($weekStart, date('Y-m-d', strtotime('monday this week')));
        $to = date('Y-m-d', strtotime($from . ' +6 days'));
        $w = 'shift_date BETWEEN :f AND :t AND status <> \'cancelled\'';
        $b = [':f' => $from, ':t' => $to];
        if ($branchId) {
            $w .= ' AND branch_id = :b';
            $b[':b'] = (int) $branchId;
        }
        $n = 0;
        foreach (cdp_opsQ("SELECT * FROM cdb_staff_shifts WHERE $w", $b) as $s) {
            $r = cdp_staffSaveShift(['user_id' => $s->user_id, 'branch_id' => $s->branch_id, 'shift_date' => date('Y-m-d', strtotime($s->shift_date . ' +7 days')),
                'start_time' => substr($s->start_time, 0, 5), 'end_time' => substr($s->end_time, 0, 5), 'note' => $s->note]);
            $n += $r['ok'] ? 1 : 0;
        }
        return $n;
    }

    /** Check in / out of one's own shift today (a check-in up to 2 hours early is allowed). */
    function cdp_staffClock($op)
    {
        $uid = cdp_opsUid();
        $today = date('Y-m-d');
        $now = cdp_opsNow();
        if ($op === 'in') {
            $s = cdp_opsOne("SELECT * FROM cdb_staff_shifts WHERE user_id = :u AND shift_date = :d AND status = 'scheduled' ORDER BY start_time LIMIT 1", [':u' => $uid, ':d' => $today]);
            if (!$s) {
                return ['ok' => false, 'error' => 'You have no shift planned for today.'];
            }
            if (strtotime($today . ' ' . $s->start_time) - time() > 7200) {
                return ['ok' => false, 'error' => 'Too early: your shift starts at ' . substr($s->start_time, 0, 5) . '.'];
            }
            cdp_opsX("UPDATE cdb_staff_shifts SET status = 'checked_in', check_in_at = :n WHERE id = :id", [':n' => $now, ':id' => (int) $s->id]);
            cdp_staffLog('update', 'Checked in', 'shift', $s->id);
            return ['ok' => true, 'late_minutes' => max(0, (int) floor((time() - strtotime($today . ' ' . $s->start_time)) / 60))];
        }
        $s = cdp_opsOne("SELECT * FROM cdb_staff_shifts WHERE user_id = :u AND status = 'checked_in' ORDER BY check_in_at DESC LIMIT 1", [':u' => $uid]);
        if (!$s) {
            return ['ok' => false, 'error' => 'You are not checked in.'];
        }
        cdp_opsX("UPDATE cdb_staff_shifts SET status = 'completed', check_out_at = :n WHERE id = :id", [':n' => $now, ':id' => (int) $s->id]);
        cdp_staffLog('update', 'Checked out', 'shift', $s->id);
        return ['ok' => true];
    }

    function cdp_staffShifts($from, $to, $branchId = 0, $userId = 0)
    {
        $w = 's.shift_date BETWEEN :f AND :t AND ' . cdp_staffScopeSql('s.branch_id');
        $b = [':f' => $from, ':t' => $to];
        if ($branchId) {
            $w .= ' AND s.branch_id = :b';
            $b[':b'] = (int) $branchId;
        }
        if ($userId) {
            $w = 's.shift_date BETWEEN :f AND :t AND s.user_id = :u';
            $b = [':f' => $from, ':t' => $to, ':u' => (int) $userId];
        }
        return cdp_opsQ("SELECT s.*, TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) staff_name, br.name branch_name
                         FROM cdb_staff_shifts s LEFT JOIN cdb_users u ON u.id = s.user_id LEFT JOIN cdb_staff_branches br ON br.id = s.branch_id
                         WHERE $w ORDER BY s.shift_date, s.start_time, staff_name LIMIT 2000", $b);
    }
}

// ---------------------------------------------------------------------------
// Tasks
// ---------------------------------------------------------------------------

if (!function_exists('cdp_staffSaveTask')) {

    function cdp_staffSaveTask(array $d)
    {
        $title = trim((string) ($d['title'] ?? ''));
        $branch = (int) ($d['branch_id'] ?? 0) ?: null;
        if ($title === '') {
            return ['ok' => false, 'error' => 'Give the task a title.'];
        }
        if (!cdp_staffCan('tasks.manage', $branch)) {
            return ['ok' => false, 'error' => 'You cannot assign tasks in this branch.'];
        }
        $due = trim((string) ($d['due_at'] ?? ''));
        $due = preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}$/', $due) ? str_replace('T', ' ', $due) . ':00' : null;
        $b = [':b' => $branch, ':t' => mb_substr($title, 0, 190), ':d' => mb_substr((string) ($d['description'] ?? ''), 0, 4000),
            ':ty' => isset(cdp_staffTaskTypes()[$d['task_type'] ?? '']) ? $d['task_type'] : 'other', ':r' => mb_substr(trim((string) ($d['entity_ref'] ?? '')), 0, 80) ?: null,
            ':q' => max(1, (int) ($d['quantity'] ?? 1)), ':p' => in_array($d['priority'] ?? '', ['low', 'normal', 'high'], true) ? $d['priority'] : 'normal',
            ':a' => (int) ($d['assignee_id'] ?? 0) ?: null, ':due' => $due];
        $id = (int) ($d['id'] ?? 0);
        if ($id) {
            $b[':id'] = $id;
            $r = cdp_opsX("UPDATE cdb_staff_tasks SET branch_id = :b, title = :t, description = :d, task_type = :ty, entity_ref = :r, quantity = :q, priority = :p, assignee_id = :a, due_at = :due
                           WHERE id = :id AND status IN ('open','in_progress')", $b);
        } else {
            $b[':by'] = cdp_opsUid();
            $b[':now'] = cdp_opsNow();
            $r = cdp_opsX("INSERT INTO cdb_staff_tasks (branch_id, title, description, task_type, entity_ref, quantity, priority, assignee_id, due_at, created_by, created_at)
                           VALUES (:b, :t, :d, :ty, :r, :q, :p, :a, :due, :by, :now)", $b);
            $id = $r['id'];
        }
        if ($r['ok']) {
            cdp_staffLog('assign', 'Task "' . $title . '"' . ($b[':a'] ? ' assigned to ' . cdp_opsUserName($b[':a']) : ''), 'task', $id, $title);
        }
        return ['ok' => $r['ok'], 'id' => $id];
    }

    /** Assignee moves their own task; managers can move any task in their branch. */
    function cdp_staffTaskStatus($id, $status)
    {
        $t = cdp_opsOne("SELECT * FROM cdb_staff_tasks WHERE id = :id", [':id' => (int) $id]);
        if (!$t || !in_array($status, ['open', 'in_progress', 'done', 'cancelled'], true)) {
            return ['ok' => false, 'error' => 'Unknown task or status.'];
        }
        $mine = (int) $t->assignee_id === cdp_opsUid() || ((int) $t->assignee_id === 0 && $status === 'in_progress');
        if (!$mine && !cdp_staffCan('tasks.manage', $t->branch_id !== null ? (int) $t->branch_id : null)) {
            return ['ok' => false, 'error' => 'This task is not yours.'];
        }
        if ($status === 'cancelled' && !cdp_staffCan('tasks.manage', $t->branch_id !== null ? (int) $t->branch_id : null)) {
            return ['ok' => false, 'error' => 'Only a manager can cancel a task.'];
        }
        $now = cdp_opsNow();
        $set = "status = :s";
        $b = [':s' => $status, ':id' => (int) $t->id];
        if ($status === 'in_progress') {
            $set .= ", started_at = COALESCE(started_at, :n), assignee_id = COALESCE(assignee_id, :me)";
            $b[':n'] = $now;
            $b[':me'] = cdp_opsUid();
        }
        if ($status === 'done') {
            $set .= ", completed_at = :n, started_at = COALESCE(started_at, :n)";
            $b[':n'] = $now;
        }
        $r = cdp_opsX("UPDATE cdb_staff_tasks SET $set WHERE id = :id", $b);
        if ($r['ok']) {
            cdp_staffLog('status', 'Task "' . $t->title . '" ' . str_replace('_', ' ', $status), 'task', $t->id, $t->title);
        }
        return ['ok' => $r['ok']];
    }

    function cdp_staffTasks(array $f = [])
    {
        $w = cdp_staffScopeSql('t.branch_id') . ' OR t.assignee_id = ' . cdp_opsUid() . ' OR t.created_by = ' . cdp_opsUid();
        $w = '(' . $w . ')';
        $b = [];
        if (!empty($f['mine'])) {
            $w = 't.assignee_id = ' . cdp_opsUid();
        }
        if (!empty($f['status'])) {
            if ($f['status'] === 'active') {
                $w .= " AND t.status IN ('open','in_progress')";
            } else {
                $w .= ' AND t.status = :s';
                $b[':s'] = $f['status'];
            }
        }
        if (!empty($f['branch_id'])) {
            $w .= ' AND t.branch_id = :b';
            $b[':b'] = (int) $f['branch_id'];
        }
        return cdp_opsQ("SELECT t.*, TRIM(CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,''))) assignee_name, br.name branch_name,
                           (t.status IN ('open','in_progress') AND t.due_at IS NOT NULL AND t.due_at < NOW()) overdue
                         FROM cdb_staff_tasks t LEFT JOIN cdb_users u ON u.id = t.assignee_id LEFT JOIN cdb_staff_branches br ON br.id = t.branch_id
                         WHERE $w ORDER BY FIELD(t.status,'in_progress','open','done','cancelled'), FIELD(t.priority,'high','normal','low'), t.due_at IS NULL, t.due_at, t.id DESC LIMIT 500", $b);
    }
}

// ---------------------------------------------------------------------------
// Branch-to-branch transfers
// ---------------------------------------------------------------------------

if (!function_exists('cdp_staffCreateTransfer')) {

    /** Resolve a reference to a shipment or warehouse package. */
    function cdp_staffResolveItem($ref)
    {
        $ref = trim((string) $ref);
        $o = cdp_opsOne("SELECT order_id FROM cdb_add_order WHERE CONCAT(COALESCE(order_prefix,''), order_no) = :r LIMIT 1", [':r' => $ref]);
        if ($o) {
            return ['type' => 'shipment', 'id' => (int) $o->order_id, 'ref' => $ref];
        }
        $p = cdp_opsOne("SELECT order_id FROM cdb_customers_packages WHERE CONCAT(COALESCE(order_prefix,''), order_no) = :r LIMIT 1", [':r' => $ref]);
        if ($p) {
            return ['type' => 'package', 'id' => (int) $p->order_id, 'ref' => $ref];
        }
        return null;
    }

    function cdp_staffCreateTransfer(array $d)
    {
        $from = (int) ($d['from_branch_id'] ?? 0);
        $to = (int) ($d['to_branch_id'] ?? 0);
        if (!$from || !$to || $from === $to) {
            return ['ok' => false, 'error' => 'Choose two different branches.'];
        }
        if (!cdp_staffCan('transfers.create', $from)) {
            return ['ok' => false, 'error' => 'You cannot send transfers from this branch.'];
        }
        $refs = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\s,;]+/', (string) ($d['items'] ?? ''))))));
        if (!$refs) {
            return ['ok' => false, 'error' => 'List at least one tracking number.'];
        }
        $items = [];
        $unknown = [];
        foreach (array_slice($refs, 0, 500) as $ref) {
            $it = cdp_staffResolveItem($ref);
            if ($it) {
                $items[] = $it;
            } else {
                $unknown[] = $ref;
            }
        }
        if ($unknown) {
            return ['ok' => false, 'error' => 'Not found: ' . implode(', ', array_slice($unknown, 0, 10)) . (count($unknown) > 10 ? '…' : '')];
        }
        // An item already travelling between branches cannot be put on a second transfer.
        foreach ($items as $it) {
            if (cdp_opsOne("SELECT i.id FROM cdb_staff_transfer_items i JOIN cdb_staff_transfers t ON t.id = i.transfer_id
                            WHERE i.entity_ref = :r AND t.status IN ('requested','approved','in_transit')", [':r' => $it['ref']])) {
                return ['ok' => false, 'error' => $it['ref'] . ' is already on an open transfer.'];
            }
        }
        $now = cdp_opsNow();
        $needsApproval = cdp_opsSetting('staff', 'transfer_needs_approval', '1') === '1';
        $ref = 'TR' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $r = cdp_opsX("INSERT INTO cdb_staff_transfers (ref, from_branch_id, to_branch_id, status, notes, requested_by, created_at, updated_at)
                       VALUES (:ref, :f, :t, :s, :n, :u, :now, :now)", [':ref' => $ref, ':f' => $from, ':t' => $to, ':s' => $needsApproval ? 'requested' : 'approved',
            ':n' => mb_substr((string) ($d['notes'] ?? ''), 0, 255), ':u' => cdp_opsUid(), ':now' => $now]);
        if (!$r['ok']) {
            return ['ok' => false, 'error' => 'Could not create the transfer.'];
        }
        foreach ($items as $it) {
            cdp_opsX("INSERT INTO cdb_staff_transfer_items (transfer_id, entity_type, entity_id, entity_ref) VALUES (:t, :ty, :id, :r)",
                [':t' => $r['id'], ':ty' => $it['type'], ':id' => $it['id'], ':r' => $it['ref']]);
        }
        if ($needsApproval) {
            cdp_staffRequestApproval(['kind' => 'transfer', 'title' => 'Transfer ' . $ref . ' (' . count($items) . ' items)', 'details' => (string) ($d['notes'] ?? ''),
                'branch_id' => $from, 'ref_type' => 'transfer', 'ref_id' => $r['id']]);
        }
        cdp_staffLog('create', 'Transfer ' . $ref . ' requested with ' . count($items) . ' items', 'transfer', $r['id'], $ref);
        return ['ok' => true, 'id' => $r['id'], 'ref' => $ref];
    }

    /** dispatch (from branch) / receive (to branch, item by item) / cancel. */
    function cdp_staffTransferAction($id, $op, array $received = [])
    {
        $t = cdp_opsOne("SELECT * FROM cdb_staff_transfers WHERE id = :id", [':id' => (int) $id]);
        if (!$t) {
            return ['ok' => false, 'error' => 'Transfer not found.'];
        }
        $now = cdp_opsNow();
        if ($op === 'dispatch') {
            if ($t->status !== 'approved' || !cdp_staffCan('transfers.receive', (int) $t->from_branch_id)) {
                return ['ok' => false, 'error' => 'Only an approved transfer can be dispatched, by its sending branch.'];
            }
            cdp_opsX("UPDATE cdb_staff_transfers SET status = 'in_transit', dispatched_by = :u, dispatched_at = :n, updated_at = :n WHERE id = :id", [':u' => cdp_opsUid(), ':n' => $now, ':id' => (int) $t->id]);
        } elseif ($op === 'receive') {
            if ($t->status !== 'in_transit' || !cdp_staffCan('transfers.receive', (int) $t->to_branch_id)) {
                return ['ok' => false, 'error' => 'Only a transfer on its way can be received, by the receiving branch.'];
            }
            $ids = array_map('intval', $received);
            foreach (cdp_opsQ("SELECT id FROM cdb_staff_transfer_items WHERE transfer_id = :t", [':t' => (int) $t->id]) as $it) {
                $ok = in_array((int) $it->id, $ids, true);
                cdp_opsX("UPDATE cdb_staff_transfer_items SET received = :r, received_note = :n WHERE id = :id",
                    [':r' => $ok ? 1 : 0, ':n' => $ok ? null : 'Missing on arrival', ':id' => (int) $it->id]);
            }
            cdp_opsX("UPDATE cdb_staff_transfers SET status = 'received', received_by = :u, received_at = :n, updated_at = :n WHERE id = :id", [':u' => cdp_opsUid(), ':n' => $now, ':id' => (int) $t->id]);
        } elseif ($op === 'cancel') {
            if (!in_array($t->status, ['requested', 'approved'], true) || ((int) $t->requested_by !== cdp_opsUid() && !cdp_staffCan('transfers.create', (int) $t->from_branch_id))) {
                return ['ok' => false, 'error' => 'This transfer can no longer be cancelled.'];
            }
            cdp_opsX("UPDATE cdb_staff_transfers SET status = 'cancelled', updated_at = :n WHERE id = :id", [':n' => $now, ':id' => (int) $t->id]);
            cdp_opsX("UPDATE cdb_staff_approvals SET status = 'cancelled' WHERE ref_type = 'transfer' AND ref_id = :id AND status = 'pending'", [':id' => (int) $t->id]);
        } else {
            return ['ok' => false, 'error' => 'Unknown action.'];
        }
        cdp_staffLog('status', 'Transfer ' . $t->ref . ': ' . $op, 'transfer', $t->id, $t->ref);
        return ['ok' => true];
    }

    function cdp_staffTransfers($status = '')
    {
        $scope = cdp_opsRank() >= 3 ? '1=1' : '(' . cdp_staffScopeSql('t.from_branch_id') . ' OR ' . cdp_staffScopeSql('t.to_branch_id') . ')';
        $w = $scope;
        $b = [];
        if ($status === 'open') {
            $w .= " AND t.status IN ('requested','approved','in_transit')";
        } elseif ($status !== '') {
            $w .= ' AND t.status = :s';
            $b[':s'] = $status;
        }
        $rows = cdp_opsQ("SELECT t.*, fb.name from_name, tb.name to_name, (SELECT COUNT(*) FROM cdb_staff_transfer_items i WHERE i.transfer_id = t.id) items,
                            (SELECT SUM(received) FROM cdb_staff_transfer_items i WHERE i.transfer_id = t.id) received_items
                          FROM cdb_staff_transfers t LEFT JOIN cdb_staff_branches fb ON fb.id = t.from_branch_id LEFT JOIN cdb_staff_branches tb ON tb.id = t.to_branch_id
                          WHERE $w ORDER BY t.id DESC LIMIT 300", $b);
        foreach ($rows as $r) {
            $r->requested_name = cdp_opsUserName($r->requested_by);
        }
        return $rows;
    }

    /** What each branch holds: items whose latest received transfer ended there. */
    function cdp_staffBranchInventory()
    {
        return cdp_opsQ("SELECT b.id, b.name, COUNT(x.entity_ref) items FROM cdb_staff_branches b
                         LEFT JOIN (SELECT i.entity_ref, t.to_branch_id FROM cdb_staff_transfer_items i JOIN cdb_staff_transfers t ON t.id = i.transfer_id
                                    WHERE t.status = 'received' AND i.received = 1 AND t.received_at = (SELECT MAX(t2.received_at) FROM cdb_staff_transfer_items i2
                                    JOIN cdb_staff_transfers t2 ON t2.id = i2.transfer_id WHERE i2.entity_ref = i.entity_ref AND t2.status = 'received' AND i2.received = 1)) x
                           ON x.to_branch_id = b.id
                         WHERE b.active = 1 GROUP BY b.id, b.name ORDER BY b.name");
    }
}

// ---------------------------------------------------------------------------
// Approvals
// ---------------------------------------------------------------------------

if (!function_exists('cdp_staffRequestApproval')) {

    function cdp_staffRequestApproval(array $d)
    {
        $kind = isset(cdp_staffApprovalKinds()[$d['kind'] ?? '']) ? $d['kind'] : 'other';
        $title = trim((string) ($d['title'] ?? ''));
        if ($title === '') {
            return ['ok' => false, 'error' => 'Describe what needs approving.'];
        }
        $amount = isset($d['amount']) && $d['amount'] !== '' ? round((float) $d['amount'], 2) : null;
        $r = cdp_opsX("INSERT INTO cdb_staff_approvals (kind, title, details, amount, branch_id, ref_type, ref_id, requested_by, created_at)
                       VALUES (:k, :t, :d, :a, :b, :rt, :ri, :u, :n)", [':k' => $kind, ':t' => mb_substr($title, 0, 190), ':d' => mb_substr((string) ($d['details'] ?? ''), 0, 4000),
            ':a' => $amount, ':b' => (int) ($d['branch_id'] ?? 0) ?: null, ':rt' => $d['ref_type'] ?? null, ':ri' => isset($d['ref_id']) ? (int) $d['ref_id'] : null,
            ':u' => cdp_opsUid(), ':n' => cdp_opsNow()]);
        if ($r['ok']) {
            cdp_staffLog('create', 'Approval requested: ' . $title, 'approval', $r['id'], $title);
        }
        return ['ok' => $r['ok'], 'id' => $r['id']];
    }

    /**
     * May this person decide this request? Never their own; administrators
     * always; others need approvals.decide for the branch and, for an amount,
     * a personal approval limit at least as high. Pure: unit tested.
     */
    function cdp_staffMayDecide($rank, $userId, $canDecideBranch, $limit, $requestedBy, $amount)
    {
        if ((int) $requestedBy === (int) $userId) {
            return false;
        }
        if ($rank >= 3) {
            return true;
        }
        if (!$canDecideBranch) {
            return false;
        }
        return $amount === null || (float) $amount <= (float) $limit;
    }

    function cdp_staffDecide($id, $decision, $note)
    {
        $a = cdp_opsOne("SELECT * FROM cdb_staff_approvals WHERE id = :id", [':id' => (int) $id]);
        if (!$a || $a->status !== 'pending' || !in_array($decision, ['approved', 'rejected'], true)) {
            return ['ok' => false, 'error' => 'This request is not waiting for a decision.'];
        }
        $p = cdp_staffProfile(cdp_opsUid());
        $may = cdp_staffMayDecide(cdp_opsRank(), cdp_opsUid(), cdp_staffCan('approvals.decide', $a->branch_id !== null ? (int) $a->branch_id : null),
            $p ? (float) $p->approval_limit : 0, (int) $a->requested_by, $a->amount === null ? null : (float) $a->amount);
        if (!$may) {
            return ['ok' => false, 'error' => (int) $a->requested_by === cdp_opsUid() ? 'You cannot decide your own request.' : 'This is above your approval limit or outside your branches.'];
        }
        $r = cdp_opsX("UPDATE cdb_staff_approvals SET status = :s, decided_by = :u, decided_at = :n, decision_note = :note WHERE id = :id AND status = 'pending'",
            [':s' => $decision, ':u' => cdp_opsUid(), ':n' => cdp_opsNow(), ':note' => mb_substr(trim((string) $note), 0, 255), ':id' => (int) $a->id]);
        if ($r['rows'] !== 1) {
            return ['ok' => false, 'error' => 'Someone decided it a moment ago.'];
        }
        if ($a->ref_type === 'transfer' && $a->ref_id) {
            cdp_opsX("UPDATE cdb_staff_transfers SET status = :s, approved_by = :u, approved_at = :n, updated_at = :n WHERE id = :id AND status = 'requested'",
                [':s' => $decision === 'approved' ? 'approved' : 'cancelled', ':u' => cdp_opsUid(), ':n' => cdp_opsNow(), ':id' => (int) $a->ref_id]);
        }
        if ($a->ref_type === 'commission' && $a->ref_id && $decision === 'approved') {
            cdp_opsX("UPDATE cdb_staff_commissions SET status = 'approved', approved_by = :u WHERE agent_id = :a AND status = 'pending' AND period = :p",
                [':u' => cdp_opsUid(), ':a' => (int) $a->ref_id, ':p' => substr((string) $a->details, 0, 7)]);
        }
        cdp_staffLog('status', 'Approval "' . $a->title . '" ' . $decision, 'approval', $a->id, $a->title);
        return ['ok' => true];
    }

    function cdp_staffApprovals($status = 'pending', $mine = false)
    {
        $w = $mine ? 'a.requested_by = ' . cdp_opsUid() : '(' . (cdp_opsRank() >= 3 ? '1=1' : cdp_staffScopeSql('a.branch_id') . ' OR a.branch_id IS NULL OR a.requested_by = ' . cdp_opsUid()) . ')';
        $b = [];
        if ($status !== '') {
            $w .= ' AND a.status = :s';
            $b[':s'] = $status;
        }
        $rows = cdp_opsQ("SELECT a.*, br.name branch_name FROM cdb_staff_approvals a LEFT JOIN cdb_staff_branches br ON br.id = a.branch_id WHERE $w ORDER BY a.id DESC LIMIT 300", $b);
        $p = cdp_staffProfile(cdp_opsUid());
        foreach ($rows as $r) {
            $r->requested_name = cdp_opsUserName($r->requested_by);
            $r->decided_name = cdp_opsUserName($r->decided_by);
            $r->can_decide = $r->status === 'pending' && cdp_staffMayDecide(cdp_opsRank(), cdp_opsUid(), cdp_staffCan('approvals.decide', $r->branch_id !== null ? (int) $r->branch_id : null),
                $p ? (float) $p->approval_limit : 0, (int) $r->requested_by, $r->amount === null ? null : (float) $r->amount);
        }
        return $rows;
    }
}

// ---------------------------------------------------------------------------
// Announcements
// ---------------------------------------------------------------------------

if (!function_exists('cdp_staffPostAnnouncement')) {

    function cdp_staffPostAnnouncement(array $d)
    {
        $title = trim((string) ($d['title'] ?? ''));
        $body = trim((string) ($d['body'] ?? ''));
        if ($title === '' || $body === '') {
            return ['ok' => false, 'error' => 'Title and message are required.'];
        }
        $type = in_array($d['audience_type'] ?? 'all', ['all', 'branch', 'role'], true) ? $d['audience_type'] : 'all';
        $val = (string) ($d['audience_value'] ?? '');
        if ($type === 'branch') {
            if (!cdp_staffCan('announcements.post', (int) $val)) {
                return ['ok' => false, 'error' => 'You can only post to your own branches.'];
            }
        } elseif (cdp_opsRank() < 3) {
            return ['ok' => false, 'error' => 'Only administrators post to everyone or to a whole job role.'];
        }
        if ($type === 'role' && !isset(cdp_staffJobRoles()[$val])) {
            return ['ok' => false, 'error' => 'Choose a job role.'];
        }
        $r = cdp_opsX("INSERT INTO cdb_staff_announcements (title, body, audience_type, audience_value, pinned, requires_ack, expires_at, created_by, created_at)
                       VALUES (:t, :b, :ty, :v, :p, :ack, :e, :u, :n)", [':t' => mb_substr($title, 0, 190), ':b' => mb_substr($body, 0, 8000), ':ty' => $type,
            ':v' => $type === 'all' ? null : $val, ':p' => !empty($d['pinned']) ? 1 : 0, ':ack' => !empty($d['requires_ack']) ? 1 : 0,
            ':e' => cdp_opsDate($d['expires_at'] ?? '', '') ?: null, ':u' => cdp_opsUid(), ':n' => cdp_opsNow()]);
        if ($r['ok']) {
            cdp_staffLog('notify', 'Announcement posted: ' . $title, 'announcement', $r['id'], $title);
        }
        return ['ok' => $r['ok']];
    }

    /** Announcements this person should see (or all of them for admins in the hub). */
    function cdp_staffAnnouncementsFor($userId, $all = false)
    {
        $role = cdp_staffJobRole();
        $branches = cdp_staffBranchIds($userId);
        $w = '(a.expires_at IS NULL OR a.expires_at >= CURDATE())';
        if (!$all) {
            $w .= " AND (a.audience_type = 'all' OR (a.audience_type = 'role' AND a.audience_value = :r)"
                . ($branches ? " OR (a.audience_type = 'branch' AND a.audience_value IN ('" . implode("','", array_map('intval', $branches)) . "'))" : '') . ')';
        }
        $rows = cdp_opsQ("SELECT a.*, rd.read_at, rd.ack_at, (SELECT COUNT(*) FROM cdb_staff_announcement_reads x WHERE x.announcement_id = a.id AND (a.requires_ack = 0 OR x.ack_at IS NOT NULL)) acknowledged
                          FROM cdb_staff_announcements a LEFT JOIN cdb_staff_announcement_reads rd ON rd.announcement_id = a.id AND rd.user_id = :u
                          WHERE $w ORDER BY a.pinned DESC, a.id DESC LIMIT 100", $all ? [':u' => (int) $userId] : [':u' => (int) $userId, ':r' => $role]);
        foreach ($rows as $r) {
            $r->author = cdp_opsUserName($r->created_by);
        }
        return $rows;
    }

    function cdp_staffMarkAnnouncement($id, $ack)
    {
        $n = cdp_opsNow();
        return cdp_opsX("INSERT INTO cdb_staff_announcement_reads (announcement_id, user_id, read_at, ack_at) VALUES (:a, :u, :n, :k)
                         ON DUPLICATE KEY UPDATE ack_at = COALESCE(ack_at, VALUES(ack_at))", [':a' => (int) $id, ':u' => cdp_opsUid(), ':n' => $n, ':k' => $ack ? $n : null])['ok'];
    }
}

// ---------------------------------------------------------------------------
// Agents and commissions
// ---------------------------------------------------------------------------

if (!function_exists('cdp_staffCommission')) {

    /** Commission for one basis amount. Pure: unit tested. */
    function cdp_staffCommission($basis, $calc, $value, $minAmount = 0)
    {
        $basis = (float) $basis;
        if ($basis < (float) $minAmount) {
            return 0.0;
        }
        return round($calc === 'flat' ? (float) $value : $basis * (float) $value / 100, 2);
    }

    /** Agent accounts with their branch office (cdb_add_order.agency). */
    function cdp_staffAgents()
    {
        $rows = [];
        foreach (cdp_staffDirectory() as $u) {
            $role = $u->job_role ?: ($u->job_role_guess ?? '');
            if ($role === 'agent') {
                $rows[] = $u;
            }
        }
        foreach ($rows as $a) {
            $a->agency_ids = cdp_staffAgentAgencyIds((int) $a->id);
            $rule = cdp_staffCommissionRule((int) $a->id);
            $a->calc = $rule['calc'];
            $a->rate = $rule['value'];
            $a->min_amount = $rule['min_amount'];
            $a->rule_own = $rule['own'];
        }
        return $rows;
    }

    /**
     * The branch offices whose shipments earn this agent commission: the
     * agency's own branch office (legacy: cdb_users.name_off = cdb_branchoffices.name_branch,
     * see helpers/querys.php) and any staff branch they work in that is linked to one.
     */
    function cdp_staffAgentAgencyIds($agentId)
    {
        $ids = [];
        $u = cdp_opsOne("SELECT name_off FROM cdb_users WHERE id = :id", [':id' => (int) $agentId]);
        if ($u && trim((string) $u->name_off) !== '' && cdp_opsTableExists('cdb_branchoffices')) {
            foreach (cdp_opsQ("SELECT id FROM cdb_branchoffices WHERE name_branch = :n", [':n' => $u->name_off]) as $b) {
                $ids[] = (int) $b->id;
            }
        }
        $bids = cdp_staffBranchIds((int) $agentId);
        if ($bids) {
            foreach (cdp_opsQ("SELECT branch_office_id FROM cdb_staff_branches WHERE branch_office_id IS NOT NULL AND id IN (" . implode(',', array_map('intval', $bids)) . ")") as $b) {
                $ids[] = (int) $b->branch_office_id;
            }
        }
        return array_values(array_unique(array_filter($ids)));
    }

    function cdp_staffCommissionRule($agentId)
    {
        $r = cdp_opsOne("SELECT * FROM cdb_staff_commission_rules WHERE agent_id = :a AND active = 1", [':a' => (int) $agentId]);
        $own = (bool) $r;
        if (!$r) {
            $r = cdp_opsOne("SELECT * FROM cdb_staff_commission_rules WHERE agent_id = 0 AND active = 1");
        }
        return ['calc' => $r ? (string) $r->calc : 'percent', 'value' => $r ? (float) $r->value : 0.0, 'min_amount' => $r ? (float) $r->min_amount : 0.0, 'own' => $own];
    }

    function cdp_staffSaveCommissionRule(array $d)
    {
        $calc = ($d['calc'] ?? '') === 'flat' ? 'flat' : 'percent';
        $value = max(0, (float) ($d['value'] ?? 0));
        if ($calc === 'percent' && $value > 100) {
            return ['ok' => false, 'error' => 'A percentage cannot be above 100.'];
        }
        $r = cdp_opsX("INSERT INTO cdb_staff_commission_rules (agent_id, calc, value, min_amount, active, updated_at) VALUES (:a, :c, :v, :m, 1, :n)
                       ON DUPLICATE KEY UPDATE calc = VALUES(calc), value = VALUES(value), min_amount = VALUES(min_amount), active = 1, updated_at = VALUES(updated_at)",
            [':a' => (int) ($d['agent_id'] ?? 0), ':c' => $calc, ':v' => $value, ':m' => max(0, (float) ($d['min_amount'] ?? 0)), ':n' => cdp_opsNow()]);
        cdp_staffLog('update', 'Commission rule saved for ' . ((int) ($d['agent_id'] ?? 0) ? cdp_opsUserName((int) $d['agent_id']) : 'all agents'), 'commission', (int) ($d['agent_id'] ?? 0));
        return ['ok' => $r['ok']];
    }

    /**
     * Accrue commission on handed-over shipments (Delivered / Picked up) of
     * each agent's branch offices, once per shipment. Cancelled shipments
     * never earn. Basis: the shipment total (cdb_add_order.total_order).
     */
    function cdp_staffAccrueCommissions()
    {
        $n = 0;
        foreach (cdp_staffAgents() as $a) {
            if (!$a->agency_ids || ((float) $a->rate <= 0)) {
                continue;
            }
            $rows = cdp_opsQ("SELECT o.order_id, CONCAT(COALESCE(o.order_prefix,''), o.order_no) trk, COALESCE(o.total_order,0) total, o.order_date
                              FROM cdb_add_order o LEFT JOIN cdb_staff_commissions c ON c.agent_id = :a AND c.entity_type = 'shipment' AND c.entity_id = o.order_id
                              WHERE o.agency IN (" . implode(',', array_map('intval', $a->agency_ids)) . ") AND o.status_courier IN (8, 15) AND c.id IS NULL LIMIT 2000",
                [':a' => (int) $a->id]);
            foreach ($rows as $o) {
                $amt = cdp_staffCommission($o->total, $a->calc, $a->rate, $a->min_amount);
                if ($amt <= 0) {
                    continue;
                }
                $r = cdp_opsX("INSERT IGNORE INTO cdb_staff_commissions (agent_id, entity_type, entity_id, entity_ref, basis_amount, calc, rate, amount, period, created_at)
                               VALUES (:a, 'shipment', :id, :r, :b, :c, :rate, :amt, :p, :n)", [':a' => (int) $a->id, ':id' => (int) $o->order_id, ':r' => $o->trk,
                    ':b' => (float) $o->total, ':c' => $a->calc, ':rate' => (float) $a->rate, ':amt' => $amt, ':p' => substr((string) $o->order_date, 0, 7) ?: date('Y-m'), ':n' => cdp_opsNow()]);
                $n += $r['rows'];
            }
        }
        return $n;
    }

    function cdp_staffCommissionSummary($agentId = 0)
    {
        $w = $agentId ? 'c.agent_id = :a' : '1=1';
        $b = $agentId ? [':a' => (int) $agentId] : [];
        $rows = cdp_opsQ("SELECT c.agent_id, c.period, COUNT(*) items, SUM(c.amount) total, SUM(IF(c.status='pending',c.amount,0)) pending,
                            SUM(IF(c.status='approved',c.amount,0)) approved, SUM(IF(c.status='paid',c.amount,0)) paid
                          FROM cdb_staff_commissions c WHERE $w AND c.status <> 'void' GROUP BY c.agent_id, c.period ORDER BY c.period DESC, c.agent_id LIMIT 500", $b);
        foreach ($rows as $r) {
            $r->agent_name = cdp_opsUserName($r->agent_id);
        }
        return $rows;
    }

    /** Pay approved commission of one agent and period. */
    function cdp_staffPayCommission($agentId, $period, $ref)
    {
        if (!preg_match('/^\d{4}-\d{2}$/', (string) $period)) {
            return ['ok' => false, 'error' => 'Choose a period.'];
        }
        $r = cdp_opsX("UPDATE cdb_staff_commissions SET status = 'paid', paid_at = :n, paid_ref = :r WHERE agent_id = :a AND period = :p AND status = 'approved'",
            [':n' => cdp_opsNow(), ':r' => mb_substr(trim((string) $ref), 0, 80), ':a' => (int) $agentId, ':p' => $period]);
        cdp_staffLog('payment', 'Commission ' . $period . ' paid to ' . cdp_opsUserName($agentId) . ' (' . $r['rows'] . ' items)', 'commission', $agentId);
        return ['ok' => $r['ok'], 'paid' => $r['rows'], 'error' => $r['rows'] ? '' : 'Nothing approved to pay for that period.'];
    }
}

// ---------------------------------------------------------------------------
// Productivity and performance
// ---------------------------------------------------------------------------

if (!function_exists('cdp_staffPerformance')) {

    /** 0-100 score from the four parts (null parts are left out). Pure: unit tested. */
    function cdp_staffScore($onTimePct, $attendancePct, $punctualPct, $outputIndex)
    {
        $parts = [];
        foreach ([[$onTimePct, 0.35], [$attendancePct, 0.25], [$punctualPct, 0.15], [$outputIndex, 0.25]] as $p) {
            if ($p[0] !== null) {
                $parts[] = [max(0, min(100, (float) $p[0])), $p[1]];
            }
        }
        if (!$parts) {
            return null;
        }
        $w = array_sum(array_column($parts, 1));
        $s = 0;
        foreach ($parts as $p) {
            $s += $p[0] * $p[1];
        }
        return round($s / $w, 1);
    }

    /**
     * Per staff member for a period: shipments and packages registered,
     * tasks done (and on time), average task time, shifts planned / attended
     * / on time, hours on shift, and a 0-100 score. Output index compares
     * each person's output with the busiest person in the same job role.
     */
    function cdp_staffPerformance($from, $to, $branchId = 0)
    {
        $f = $from . ' 00:00:00';
        $t = $to . ' 23:59:59';
        $people = [];
        foreach (cdp_staffDirectory((int) $branchId) as $u) {
            if ((int) $u->active !== 1 && $u->active !== null) {
                continue;
            }
            $people[(int) $u->id] = ['user_id' => (int) $u->id, 'name' => $u->name, 'job_role' => $u->job_role ?: ($u->job_role_guess ?? ''), 'branch' => $u->branch_name,
                'shipments' => 0, 'packages' => 0, 'tasks_done' => 0, 'tasks_on_time' => 0, 'tasks_with_due' => 0, 'avg_task_min' => null, 'tasks_open' => 0, 'tasks_overdue' => 0,
                'shifts' => 0, 'attended' => 0, 'on_time' => 0, 'hours' => 0.0, 'deliveries' => 0];
        }
        if (!$people) {
            return [];
        }
        $in = implode(',', array_keys($people));
        foreach (cdp_opsQ("SELECT user_id, COUNT(*) c FROM cdb_add_order WHERE user_id IN ($in) AND order_datetime BETWEEN :f AND :t AND status_courier <> 21 GROUP BY user_id", [':f' => $f, ':t' => $t]) as $r) {
            $people[(int) $r->user_id]['shipments'] = (int) $r->c;
        }
        foreach (cdp_opsQ("SELECT user_id, COUNT(*) c FROM cdb_customers_packages WHERE user_id IN ($in) AND order_datetime BETWEEN :f AND :t GROUP BY user_id", [':f' => $f, ':t' => $t]) as $r) {
            $people[(int) $r->user_id]['packages'] = (int) $r->c;
        }
        foreach (cdp_opsQ("SELECT driver_id, COUNT(*) c FROM cdb_add_order WHERE driver_id IN ($in) AND status_courier IN (8) AND order_date BETWEEN :f AND :t GROUP BY driver_id", [':f' => $from, ':t' => $to]) as $r) {
            $people[(int) $r->driver_id]['deliveries'] = (int) $r->c;
        }
        foreach (cdp_opsQ("SELECT assignee_id, SUM(status='done' AND completed_at BETWEEN :f AND :t) done, SUM(status='done' AND completed_at BETWEEN :f AND :t AND due_at IS NOT NULL) with_due,
                             SUM(status='done' AND completed_at BETWEEN :f AND :t AND due_at IS NOT NULL AND completed_at <= due_at) on_time,
                             AVG(IF(status='done' AND completed_at BETWEEN :f AND :t, TIMESTAMPDIFF(MINUTE, COALESCE(started_at, created_at), completed_at), NULL)) avg_min,
                             SUM(status IN ('open','in_progress')) open_n, SUM(status IN ('open','in_progress') AND due_at < NOW()) overdue
                           FROM cdb_staff_tasks WHERE assignee_id IN ($in) GROUP BY assignee_id", [':f' => $f, ':t' => $t]) as $r) {
            $p = &$people[(int) $r->assignee_id];
            $p['tasks_done'] = (int) $r->done;
            $p['tasks_with_due'] = (int) $r->with_due;
            $p['tasks_on_time'] = (int) $r->on_time;
            $p['avg_task_min'] = $r->avg_min !== null ? (int) round((float) $r->avg_min) : null;
            $p['tasks_open'] = (int) $r->open_n;
            $p['tasks_overdue'] = (int) $r->overdue;
            unset($p);
        }
        foreach (cdp_opsQ("SELECT user_id, COUNT(*) planned, SUM(check_in_at IS NOT NULL) attended,
                             SUM(check_in_at IS NOT NULL AND check_in_at <= TIMESTAMP(shift_date, start_time) + INTERVAL 10 MINUTE) on_time,
                             SUM(IF(check_in_at IS NOT NULL AND check_out_at IS NOT NULL, TIMESTAMPDIFF(MINUTE, check_in_at, check_out_at), 0)) minutes
                           FROM cdb_staff_shifts WHERE user_id IN ($in) AND shift_date BETWEEN :f AND :t AND status <> 'cancelled' AND shift_date <= CURDATE() GROUP BY user_id",
            [':f' => $from, ':t' => $to]) as $r) {
            $p = &$people[(int) $r->user_id];
            $p['shifts'] = (int) $r->planned;
            $p['attended'] = (int) $r->attended;
            $p['on_time'] = (int) $r->on_time;
            $p['hours'] = round((int) $r->minutes / 60, 1);
            unset($p);
        }
        $maxByRole = [];
        foreach ($people as $p) {
            $out = $p['shipments'] + $p['packages'] + $p['tasks_done'] + $p['deliveries'];
            $maxByRole[$p['job_role']] = max($maxByRole[$p['job_role']] ?? 0, $out);
        }
        foreach ($people as &$p) {
            $out = $p['shipments'] + $p['packages'] + $p['tasks_done'] + $p['deliveries'];
            $p['output'] = $out;
            $p['on_time_pct'] = $p['tasks_with_due'] ? round(100 * $p['tasks_on_time'] / $p['tasks_with_due'], 1) : null;
            $p['attendance_pct'] = $p['shifts'] ? round(100 * $p['attended'] / $p['shifts'], 1) : null;
            $p['punctual_pct'] = $p['attended'] ? round(100 * $p['on_time'] / $p['attended'], 1) : null;
            $p['per_hour'] = $p['hours'] > 0 ? round($out / $p['hours'], 2) : null;
            $max = $maxByRole[$p['job_role']] ?? 0;
            $p['score'] = cdp_staffScore($p['on_time_pct'], $p['attendance_pct'], $p['punctual_pct'], $max > 0 ? 100 * $out / $max : null);
        }
        unset($p);
        $rows = array_values($people);
        usort($rows, function ($a, $b) { return ($b['score'] ?? -1) <=> ($a['score'] ?? -1) ?: $b['output'] <=> $a['output']; });
        return $rows;
    }

    /** Staff activity: the system audit trail for staff accounts, newest first. */
    function cdp_staffActivity(array $f)
    {
        if (!cdp_opsTableExists('cdb_activity_log')) {
            return [];
        }
        $w = "a.verb <> 'view'";
        $b = [];
        if (!empty($f['user_id'])) {
            $w .= ' AND a.user_id = :u';
            $b[':u'] = (int) $f['user_id'];
        } elseif (!empty($f['branch_id']) || cdp_opsRank() < 3) {
            $ids = array_map(function ($r) { return (int) $r->id; }, cdp_staffDirectory((int) ($f['branch_id'] ?? 0)));
            $w .= ' AND a.user_id IN (' . ($ids ? implode(',', $ids) : '0') . ')';
        }
        if (!empty($f['from'])) {
            $w .= ' AND a.created_at >= :f';
            $b[':f'] = $f['from'] . ' 00:00:00';
        }
        if (!empty($f['to'])) {
            $w .= ' AND a.created_at <= :t';
            $b[':t'] = $f['to'] . ' 23:59:59';
        }
        if (!empty($f['q'])) {
            $w .= ' AND (a.summary LIKE :q OR a.entity_label LIKE :q OR a.action_label LIKE :q)';
            $b[':q'] = '%' . $f['q'] . '%';
        }
        return cdp_opsQ("SELECT a.id, a.created_at, a.user_id, a.actor_name, a.role_name, a.module, a.verb, a.outcome, a.action_label, a.summary, a.entity_type, a.entity_label, a.ip
                         FROM cdb_activity_log a WHERE $w ORDER BY a.id DESC LIMIT 300", $b);
    }

    /** Cron: missed shifts, commission accrual. */
    function cdp_staffHousekeeping()
    {
        if (!cdp_staffEnsureTables()) {
            return ['ok' => false];
        }
        $missed = cdp_opsX("UPDATE cdb_staff_shifts SET status = 'missed' WHERE status = 'scheduled' AND TIMESTAMP(shift_date, end_time) < :n", [':n' => cdp_opsNow()])['rows'];
        $closed = cdp_opsX("UPDATE cdb_staff_shifts SET status = 'completed', check_out_at = TIMESTAMP(shift_date, end_time) WHERE status = 'checked_in' AND TIMESTAMP(shift_date, end_time) < :n",
            [':n' => cdp_opsNow(-4 * 3600)])['rows'];
        $accrued = 0;
        if (cdp_opsSetting('staff', 'accrual_day', '') !== date('Y-m-d')) {
            $accrued = cdp_staffAccrueCommissions();
            cdp_opsSettingSet('staff', 'accrual_day', date('Y-m-d'), 0);
        }
        return ['ok' => true, 'missed' => $missed, 'auto_checked_out' => $closed, 'commissions' => $accrued];
    }
}
