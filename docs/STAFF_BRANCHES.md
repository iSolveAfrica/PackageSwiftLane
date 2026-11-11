# Staff, Agents & Multi-Branch Management

Branches in several countries, staff profiles on top of the existing user accounts, job-role workspaces, shifts, warehouse tasks, branch-to-branch transfers, approval workflows, announcements, agent commissions with a partner portal, productivity and performance.

Code: `helpers/staffops.php` (engine), `helpers/staff_workspace.php` (role workspaces and partner portal), `ajax/staffops/staffops_ajax.php`, pages `staff_hub.php` (People › Staff & Branches), `workspace.php` (People › My Workspace), `agent_portal.php` (People › Partner Portal).

## Roles and access

Two layers, both enforced on the server:

1. **Account role and permissions** (existing: Staff & Roles, User permissions) decide which pages open.
2. **Job role** (this module) decides the workspace a person sees and what they may do here: warehouse operator, finance officer, dispatch officer, customer service, branch manager, driver, partner agent, administrator. Without a profile, the job role follows the account type (administrator, driver, agency → agent, other staff → customer service).

The **capability matrix** (Role access tab, administrators) lists abilities per job role. Abilities marked "own branches" apply only to the person's home branch, the extra branches assigned to them, and branches they manage. Administrators (rank 3+) can do everything. Nobody can decide their own approval request, and a manager can approve amounts only up to their personal approval limit.

## Setup

1. Tables (`cdb_staff_*`) are created on first use.
2. **Branches:** add countries of operation, then branches. Link each branch to the existing office and/or agency whose shipments it handles; that link is what counts shipments per branch here and in the Master View.
3. **Staff:** open each profile, choose the job role, home branch, extra branches and approval limit.
4. **Agents:** give agency accounts the "Partner agent" job role; set the default commission rule (or one per agent) under Agent commissions.
5. The worker (`cron/comms_worker.php`) marks unattended shifts as missed, closes forgotten check-outs, and accrues commission nightly.

## How it works

- **Shifts:** weekly planner, overlap check (overnight shifts included), copy a week forward; staff check in (up to 2 hours early) and out from their workspace. Lateness and attendance feed the performance score.
- **Tasks:** typed (receive, sort, pack, dispatch, count, deliver), priority, due time, optional reference; the assignee starts and completes them; overdue ones are flagged.
- **Transfers:** list tracking numbers (scan or paste); unknown references and items already on an open transfer are refused. Optional approval → dispatch by the sending branch → item-by-item receipt by the receiving branch (missing items are recorded). Branch inventory follows the last received transfer of each item.
- **Approvals:** expense, refund, discount, leave, shift swap, other (transfers create their own). Amount limits as above.
- **Announcements:** to everyone or a job role (administrators) or a branch (its managers); pinning, expiry, and "must acknowledge" with counts.
- **Commission:** percentage of shipment charges or a fixed amount, with a minimum; earned once per shipment when it is delivered or picked up, never on cancelled ones. Pending → approved → paid with a payment reference.
- **Performance score (0-100):** tasks on time 35%, attendance 25%, punctuality 15%, output against the busiest person in the same role 25%; missing parts are left out. Exports: CSV, Excel, PDF.
- **Activity log:** the system audit trail filtered to staff; every change in this module is written to it.

## Traceability

| Requirement | Where |
|---|---|
| 1 Multiple warehouse branches | Branches tab, `cdb_staff_branches` |
| 2 Multiple country operations | Countries, `cdb_staff_countries` (currency, time zone) |
| 3 Centralised staff accounts | Staff tab over `cdb_users`, `cdb_staff_profiles` |
| 4 Role-based access control | Role access matrix, `cdp_staffCan`, branch scoping |
| 5-10 Warehouse, finance, dispatch, customer service, branch manager, driver interfaces | My Workspace, `cdp_ws_<role>` |
| 11 Staff activity logs | Activity log tab, `cdp_staffActivity` |
| 12 Staff shift management | Shifts tab, `cdp_staffSaveShift`, `cdp_staffClock` |
| 13 Warehouse task assignments | Tasks tab |
| 14 Staff productivity measurements | Performance tab, `cdp_staffPerformance` |
| 15 Branch-to-branch inventory transfer | Transfers tab |
| 16 Staff performance reporting | Performance exports; Master View employee panel |
| 17 Agent commission tracking | Agent commissions tab |
| 18 Partner agent portal | `agent_portal.php` |
| 19 Internal approval workflows | Approvals tab, `cdp_staffDecide` |
| 20 Staff announcements | Announcements tab and workspace |

Tests: see `docs/COMMUNICATIONS.md` › Tests (one suite covers the three modules).
